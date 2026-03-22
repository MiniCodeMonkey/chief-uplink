<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { marked } from 'marked';
import { EditorView, keymap, lineNumbers } from '@codemirror/view';
import { EditorState } from '@codemirror/state';
import { markdown } from '@codemirror/lang-markdown';
import { oneDark } from '@codemirror/theme-one-dark';
import { defaultKeymap, history, historyKeymap } from '@codemirror/commands';
import axios from 'axios';

const props = defineProps({
    prd: {
        type: Object,
        required: true,
    },
    project: {
        type: Object,
        default: null,
    },
    device: {
        type: Object,
        required: true,
    },
});

const isEditing = ref(false);
const editorContent = ref(props.prd.content ?? '');
const isSaving = ref(false);
const isDeleting = ref(false);
const isStartingRun = ref(false);
const showDeleteModal = ref(false);
const editorContainer = ref(null);
let editorView = null;

const renderedContent = computed(() => {
    if (!props.prd.content) {
        return '<p class="text-text-tertiary">No content yet.</p>';
    }
    return marked(props.prd.content);
});

const stories = computed(() => {
    return parseStories(props.prd.content ?? '');
});

function parseStories(content) {
    const storyList = [];
    const lines = content.split('\n');
    let currentStory = null;
    let inAcceptanceCriteria = false;

    for (const line of lines) {
        const storyMatch = line.match(/^##\s+(?:US-\d+\s*[-–—:]\s*)?(.+)/);
        if (storyMatch) {
            if (currentStory) {
                storyList.push(currentStory);
            }
            currentStory = {
                title: storyMatch[1].trim(),
                priority: extractPriority(line),
                status: extractStatus(line),
                acceptanceCriteria: [],
            };
            inAcceptanceCriteria = false;
            continue;
        }

        if (currentStory) {
            if (/acceptance\s*criteria/i.test(line)) {
                inAcceptanceCriteria = true;
                continue;
            }
            if (inAcceptanceCriteria) {
                const criterionMatch = line.match(/^\s*[-*]\s+(.+)/);
                if (criterionMatch) {
                    currentStory.acceptanceCriteria.push(criterionMatch[1].trim());
                }
            }

            const priorityMatch = line.match(/priority\s*:\s*(\w+)/i);
            if (priorityMatch) {
                currentStory.priority = priorityMatch[1];
            }
            const statusMatch = line.match(/status\s*:\s*(\w+)/i);
            if (statusMatch) {
                currentStory.status = statusMatch[1];
            }
        }
    }

    if (currentStory) {
        storyList.push(currentStory);
    }

    return storyList;
}

function extractPriority(line) {
    const match = line.match(/\[P(\d+)\]/i) || line.match(/priority\s*:\s*(\w+)/i);
    return match ? match[1] : null;
}

function extractStatus(line) {
    const match = line.match(/\[(done|todo|in.?progress|pending|completed)\]/i);
    return match ? match[1] : null;
}

function statusBadgeClass(status) {
    if (!status) return 'bg-bg-surface text-text-secondary';
    const s = status.toLowerCase();
    if (s === 'done' || s === 'completed') return 'bg-success/15 text-success';
    if (s === 'in progress' || s === 'in-progress') return 'bg-info/15 text-info';
    if (s === 'todo' || s === 'pending') return 'bg-warning/15 text-warning';
    return 'bg-bg-surface text-text-secondary';
}

function priorityBadgeClass(priority) {
    if (!priority) return 'bg-bg-surface text-text-secondary';
    const p = parseInt(priority);
    if (p <= 1) return 'bg-error/15 text-error';
    if (p <= 2) return 'bg-warning/15 text-warning';
    if (p <= 3) return 'bg-info/15 text-info';
    return 'bg-bg-surface text-text-secondary';
}

function toggleEditor() {
    if (isEditing.value) {
        isEditing.value = false;
        destroyEditor();
    } else {
        editorContent.value = props.prd.content ?? '';
        isEditing.value = true;
        nextTick(() => initEditor());
    }
}

function initEditor() {
    if (!editorContainer.value) return;

    const state = EditorState.create({
        doc: editorContent.value,
        extensions: [
            lineNumbers(),
            history(),
            keymap.of([...defaultKeymap, ...historyKeymap]),
            markdown(),
            oneDark,
            EditorView.updateListener.of((update) => {
                if (update.docChanged) {
                    editorContent.value = update.state.doc.toString();
                }
            }),
            EditorView.theme({
                '&': { height: '100%', fontSize: '14px' },
                '.cm-scroller': { overflow: 'auto' },
                '.cm-content': { fontFamily: 'var(--font-mono)' },
            }),
        ],
    });

    editorView = new EditorView({
        state,
        parent: editorContainer.value,
    });
}

function destroyEditor() {
    if (editorView) {
        editorView.destroy();
        editorView = null;
    }
}

async function sendCommand(type, payload = {}) {
    return axios.post(`/api/devices/${props.device.id}/commands`, {
        type,
        payload,
    });
}

async function saveContent() {
    isSaving.value = true;
    try {
        await sendCommand('cmd.prd.update', {
            prd_id: props.prd.id,
            content: editorContent.value,
        });
        isEditing.value = false;
        destroyEditor();
    } finally {
        isSaving.value = false;
    }
}

async function startRun() {
    isStartingRun.value = true;
    try {
        await sendCommand('cmd.run.start', {
            prd_id: props.prd.id,
        });
    } finally {
        isStartingRun.value = false;
    }
}

async function confirmDelete() {
    isDeleting.value = true;
    try {
        await sendCommand('cmd.prd.delete', {
            prd_id: props.prd.id,
        });
        showDeleteModal.value = false;
    } finally {
        isDeleting.value = false;
    }
}

onUnmounted(() => {
    destroyEditor();
});
</script>

<template>
    <Head :title="prd.title" />

    <div class="p-6 md:p-8">
        <!-- Header -->
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <h1 class="text-2xl font-bold text-text-heading">{{ prd.title }}</h1>
                <div class="mt-1 flex items-center gap-3 text-sm text-text-secondary">
                    <span v-if="project">{{ project.name }}</span>
                    <span
                        class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
                        :class="{
                            'bg-warning/15 text-warning': prd.status === 'draft',
                            'bg-success/15 text-success': prd.status === 'active',
                            'bg-info/15 text-info': prd.status === 'paused',
                            'bg-bg-surface text-text-secondary': prd.status === 'completed',
                            'bg-bg-surface text-text-tertiary': prd.status === 'archived',
                        }"
                    >
                        {{ prd.status }}
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span
                            class="h-2 w-2 rounded-full"
                            :class="device.connected ? 'bg-success' : 'bg-error'"
                        ></span>
                        <span class="text-xs">{{ device.name }}</span>
                    </span>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex shrink-0 items-center gap-2">
                <Link
                    :href="`/prds/${prd.id}/chat`"
                    class="inline-flex items-center gap-1.5 rounded-md border border-border bg-bg-card px-3 py-1.5 text-sm font-medium text-text-heading transition-colors hover:border-border-hover hover:bg-bg-surface"
                >
                    Chat
                </Link>

                <button
                    @click="toggleEditor"
                    class="inline-flex items-center gap-1.5 rounded-md border border-border bg-bg-card px-3 py-1.5 text-sm font-medium text-text-heading transition-colors hover:border-border-hover hover:bg-bg-surface"
                    :class="{ 'border-interactive text-interactive': isEditing }"
                >
                    {{ isEditing ? 'Preview' : 'Edit' }}
                </button>

                <button
                    @click="startRun"
                    :disabled="isStartingRun"
                    class="inline-flex items-center gap-1.5 rounded-md bg-interactive px-3 py-1.5 text-sm font-medium text-white transition-colors hover:bg-interactive-hover disabled:opacity-50"
                >
                    {{ isStartingRun ? 'Starting...' : 'Start Run' }}
                </button>

                <button
                    @click="showDeleteModal = true"
                    class="inline-flex items-center gap-1.5 rounded-md border border-error/30 px-3 py-1.5 text-sm font-medium text-error transition-colors hover:bg-error/10"
                >
                    Delete
                </button>
            </div>
        </div>

        <!-- Editor Mode -->
        <div v-if="isEditing" class="space-y-4">
            <div
                ref="editorContainer"
                class="min-h-[400px] overflow-hidden rounded-lg border border-border bg-bg-card"
            ></div>
            <div class="flex items-center justify-end gap-2">
                <button
                    @click="toggleEditor"
                    class="rounded-md border border-border px-3 py-1.5 text-sm font-medium text-text-secondary transition-colors hover:border-border-hover hover:text-text-heading"
                >
                    Cancel
                </button>
                <button
                    @click="saveContent"
                    :disabled="isSaving"
                    class="rounded-md bg-interactive px-3 py-1.5 text-sm font-medium text-white transition-colors hover:bg-interactive-hover disabled:opacity-50"
                >
                    {{ isSaving ? 'Saving...' : 'Save' }}
                </button>
            </div>
        </div>

        <!-- Preview Mode -->
        <div v-else class="space-y-6">
            <!-- Markdown Preview -->
            <div class="rounded-lg border border-border bg-bg-card p-6">
                <div
                    class="prose prose-invert max-w-none text-sm text-text [&_a]:text-interactive [&_code]:rounded [&_code]:bg-bg-surface [&_code]:px-1.5 [&_code]:py-0.5 [&_code]:text-xs [&_h1]:text-text-heading [&_h2]:text-text-heading [&_h3]:text-text-heading [&_h4]:text-text-heading [&_hr]:border-border [&_li]:text-text [&_ol]:text-text [&_p]:text-text [&_pre]:bg-bg-surface [&_pre]:text-sm [&_strong]:text-text-heading [&_ul]:text-text"
                    v-html="renderedContent"
                ></div>
            </div>

            <!-- Story List -->
            <div v-if="stories.length > 0" class="rounded-lg border border-border bg-bg-card p-6">
                <h2 class="mb-4 text-lg font-bold text-text-heading">User Stories</h2>
                <div class="space-y-3">
                    <div
                        v-for="(story, index) in stories"
                        :key="index"
                        class="rounded-md border border-border bg-bg-surface p-4"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <h3 class="text-sm font-medium text-text-heading">{{ story.title }}</h3>
                            <div class="flex shrink-0 items-center gap-2">
                                <span
                                    v-if="story.priority"
                                    class="inline-flex rounded-md px-2 py-0.5 text-xs font-medium"
                                    :class="priorityBadgeClass(story.priority)"
                                >
                                    P{{ story.priority }}
                                </span>
                                <span
                                    v-if="story.status"
                                    class="inline-flex rounded-md px-2 py-0.5 text-xs font-medium"
                                    :class="statusBadgeClass(story.status)"
                                >
                                    {{ story.status }}
                                </span>
                            </div>
                        </div>

                        <ul
                            v-if="story.acceptanceCriteria.length > 0"
                            class="mt-3 space-y-1.5 border-t border-border pt-3"
                        >
                            <li
                                v-for="(criterion, ci) in story.acceptanceCriteria"
                                :key="ci"
                                class="flex items-start gap-2 text-xs text-text-secondary"
                            >
                                <span class="mt-0.5 h-1.5 w-1.5 shrink-0 rounded-full bg-text-tertiary"></span>
                                <span>{{ criterion }}</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <Teleport to="body">
        <div
            v-if="showDeleteModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60"
            @click.self="showDeleteModal = false"
        >
            <div class="mx-4 w-full max-w-sm rounded-lg border border-border bg-bg-card p-6">
                <h3 class="text-lg font-bold text-text-heading">Delete PRD</h3>
                <p class="mt-2 text-sm text-text-secondary">
                    Are you sure you want to delete "{{ prd.title }}"? This action cannot be undone.
                </p>
                <div class="mt-6 flex items-center justify-end gap-2">
                    <button
                        @click="showDeleteModal = false"
                        class="rounded-md border border-border px-3 py-1.5 text-sm font-medium text-text-secondary transition-colors hover:border-border-hover hover:text-text-heading"
                    >
                        Cancel
                    </button>
                    <button
                        @click="confirmDelete"
                        :disabled="isDeleting"
                        class="rounded-md bg-error px-3 py-1.5 text-sm font-medium text-white transition-colors hover:bg-error/80 disabled:opacity-50"
                    >
                        {{ isDeleting ? 'Deleting...' : 'Delete' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
