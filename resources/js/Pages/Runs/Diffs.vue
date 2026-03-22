<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useDeviceChannel } from '@/composables/useDeviceChannel.js';
import { createHighlighter } from 'shiki';
import axios from 'axios';

const props = defineProps({
    run: { type: Object, required: true },
    prd: { type: Object, default: null },
    device: { type: Object, required: true },
});

const runData = ref({ ...props.run });
const files = ref([]);
const selectedFile = ref(null);
const isLoading = ref(false);
const isDrawerOpen = ref(false);
const highlighter = ref(null);

const isRunning = computed(() => runData.value.status === 'running');
const isFinished = computed(() => ['completed', 'failed', 'stopped'].includes(runData.value.status));

const elapsedTime = computed(() => {
    if (!runData.value.started_at) return '--';
    const start = new Date(runData.value.started_at).getTime();
    const end = runData.value.completed_at
        ? new Date(runData.value.completed_at).getTime()
        : now.value;
    const diffMs = Math.max(0, end - start);
    const secs = Math.floor(diffMs / 1000);
    const mins = Math.floor(secs / 60);
    const hours = Math.floor(mins / 60);

    if (hours > 0) return `${hours}h ${mins % 60}m ${secs % 60}s`;
    if (mins > 0) return `${mins}m ${secs % 60}s`;
    return `${secs}s`;
});

function statusBadgeClass(status) {
    const map = {
        pending: 'bg-bg-surface text-text-secondary',
        running: 'bg-info/15 text-info',
        completed: 'bg-success/15 text-success',
        failed: 'bg-error/15 text-error',
        stopped: 'bg-warning/15 text-warning',
    };
    return map[status] ?? 'bg-bg-surface text-text-secondary';
}

function fileStatusIcon(status) {
    const icons = {
        added: { label: 'A', class: 'text-success bg-success/15' },
        modified: { label: 'M', class: 'text-info bg-info/15' },
        deleted: { label: 'D', class: 'text-error bg-error/15' },
        renamed: { label: 'R', class: 'text-warning bg-warning/15' },
    };
    return icons[status] ?? { label: '?', class: 'text-text-secondary bg-bg-surface' };
}

function formatLineCounts(additions, deletions) {
    const parts = [];
    if (additions > 0) parts.push(`+${additions}`);
    if (deletions > 0) parts.push(`-${deletions}`);
    return parts.join(' ');
}

function fileName(path) {
    return path.split('/').pop();
}

function fileDir(path) {
    const parts = path.split('/');
    if (parts.length <= 1) return '';
    return parts.slice(0, -1).join('/') + '/';
}

// Group files by directory for the tree
const fileTree = computed(() => {
    const dirs = {};
    for (const file of files.value) {
        const dir = fileDir(file.path) || '/';
        if (!dirs[dir]) dirs[dir] = [];
        dirs[dir].push(file);
    }
    return Object.entries(dirs).sort(([a], [b]) => a.localeCompare(b));
});

const collapsedDirs = ref(new Set());

function toggleDir(dir) {
    if (collapsedDirs.value.has(dir)) {
        collapsedDirs.value.delete(dir);
    } else {
        collapsedDirs.value.add(dir);
    }
}

function selectFile(file) {
    selectedFile.value = file;
    isDrawerOpen.value = false;
}

// Parse unified diff into lines with metadata
function parseDiffLines(diff) {
    if (!diff) return [];
    const lines = diff.split('\n');
    const result = [];

    for (const line of lines) {
        if (line.startsWith('@@')) {
            result.push({ type: 'hunk', content: line });
        } else if (line.startsWith('+')) {
            result.push({ type: 'addition', content: line.substring(1) });
        } else if (line.startsWith('-')) {
            result.push({ type: 'deletion', content: line.substring(1) });
        } else if (line.startsWith(' ')) {
            result.push({ type: 'context', content: line.substring(1) });
        } else if (line.startsWith('\\')) {
            result.push({ type: 'meta', content: line });
        } else {
            result.push({ type: 'context', content: line });
        }
    }

    return result;
}

// Detect language from file extension
function detectLanguage(filePath) {
    if (!filePath) return 'text';
    const ext = filePath.split('.').pop()?.toLowerCase();
    const langMap = {
        js: 'javascript',
        jsx: 'javascript',
        ts: 'typescript',
        tsx: 'typescript',
        vue: 'vue',
        php: 'php',
        go: 'go',
        sh: 'bash',
        bash: 'bash',
        zsh: 'bash',
        json: 'json',
        html: 'html',
        css: 'css',
        md: 'markdown',
        yaml: 'yaml',
        yml: 'yaml',
        sql: 'sql',
        py: 'python',
        rb: 'ruby',
        rs: 'rust',
    };
    return langMap[ext] ?? 'text';
}

function highlightLine(content, lang) {
    if (!highlighter.value || !content || lang === 'text') return escapeHtml(content);
    try {
        const html = highlighter.value.codeToHtml(content, {
            lang,
            themes: { dark: 'tokyo-night', light: 'github-light' },
        });
        // Extract just the inner code content from the shiki output
        const match = html.match(/<code[^>]*><span[^>]*>([\s\S]*?)<\/span><\/code>/);
        return match ? match[1] : escapeHtml(content);
    } catch {
        return escapeHtml(content);
    }
}

function escapeHtml(text) {
    return text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

async function initHighlighter() {
    try {
        highlighter.value = await createHighlighter({
            themes: ['tokyo-night', 'github-light'],
            langs: ['javascript', 'typescript', 'php', 'go', 'bash', 'json', 'vue', 'html', 'css', 'markdown', 'yaml', 'sql', 'python', 'ruby', 'rust'],
        });
    } catch {
        // Shiki failed to load — fall back to plain text
    }
}

// Request diffs from device via cmd.diffs.get
function requestDiffs() {
    if (!props.device?.id) return;
    isLoading.value = true;

    axios.post(`/api/devices/${props.device.id}/commands`, {
        type: 'cmd.diffs.get',
        payload: { run_id: runData.value.id },
    }).catch(() => {
        isLoading.value = false;
    });
}

// Listen for diff data via device channel
useDeviceChannel(props.device.id, {
    onStreamEvent(data) {
        if (data.type !== 'state.diffs.response') return;
        if (data.run_id && data.run_id !== props.run.id) return;

        const payload = data.payload || data;
        if (payload.files) {
            files.value = payload.files;
            isLoading.value = false;

            // Auto-select first file
            if (!selectedFile.value && payload.files.length > 0) {
                selectedFile.value = payload.files[0];
            }
        }
    },
    onStateUpdated(data) {
        if (data.run) {
            runData.value = { ...runData.value, ...data.run };
        }
    },
});

// Also listen on run channel for status updates
let runChannel = null;

function setupRunChannel() {
    if (!window.Echo) return;
    runChannel = window.Echo.private(`run.${props.run.id}`);
    runChannel.listen('RunUpdated', (data) => {
        runData.value = {
            ...runData.value,
            status: data.status,
            stories: data.stories,
            started_at: data.started_at,
            completed_at: data.completed_at,
        };
    });
}

// Timer
const now = ref(Date.now());
let timer = null;

onMounted(() => {
    initHighlighter();
    setupRunChannel();
    requestDiffs();
    timer = setInterval(() => { now.value = Date.now(); }, 1000);
});

onUnmounted(() => {
    if (runChannel && window.Echo) {
        window.Echo.leave(`run.${props.run.id}`);
    }
    if (timer) clearInterval(timer);
    if (highlighter.value) highlighter.value.dispose();
});
</script>

<template>
    <Head :title="prd ? `Diffs — ${prd.title}` : 'Diffs'" />

    <div class="flex h-[calc(100vh-4rem)] flex-col p-6 md:p-8">
        <!-- Header -->
        <div class="mb-4 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <h1 class="text-2xl font-bold text-text-heading">
                    {{ prd ? prd.title : `Run #${run.id}` }}
                </h1>
                <div class="mt-1 flex flex-wrap items-center gap-3 text-sm text-text-secondary">
                    <span
                        class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
                        :class="statusBadgeClass(runData.status)"
                    >
                        {{ runData.status }}
                    </span>
                    <span class="font-mono text-xs">{{ elapsedTime }}</span>
                    <span class="flex items-center gap-1.5">
                        <span
                            class="h-2 w-2 rounded-full"
                            :class="device.connected ? 'bg-success' : 'bg-error'"
                        ></span>
                        <span class="text-xs">{{ device.name }}</span>
                    </span>
                </div>
            </div>

            <!-- View Toggle -->
            <div class="flex shrink-0 items-center gap-2">
                <div class="inline-flex rounded-md border border-border">
                    <Link
                        :href="`/runs/${run.id}`"
                        class="rounded-l-md px-3 py-1.5 text-sm font-medium transition-colors text-text-secondary hover:bg-bg-surface hover:text-text"
                    >
                        Summary
                    </Link>
                    <Link
                        :href="`/runs/${run.id}/live`"
                        class="border-x border-border px-3 py-1.5 text-sm font-medium transition-colors text-text-secondary hover:bg-bg-surface hover:text-text"
                    >
                        Live
                    </Link>
                    <span
                        class="rounded-r-md bg-bg-surface px-3 py-1.5 text-sm font-medium text-text-heading"
                    >
                        Diffs
                    </span>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex min-h-0 flex-1 flex-col gap-4 md:flex-row">
            <!-- Mobile: Collapsible file drawer -->
            <div class="md:hidden">
                <button
                    @click="isDrawerOpen = !isDrawerOpen"
                    class="flex w-full items-center justify-between rounded-lg border border-border bg-bg-card px-4 py-2.5 text-sm font-medium text-text-heading transition-colors hover:bg-bg-surface"
                >
                    <span class="flex items-center gap-2">
                        <svg class="h-4 w-4 text-text-secondary" viewBox="0 0 16 16" fill="currentColor">
                            <path d="M1.75 1A1.75 1.75 0 0 0 0 2.75v10.5C0 14.216.784 15 1.75 15h12.5A1.75 1.75 0 0 0 16 13.25v-8.5A1.75 1.75 0 0 0 14.25 3H7.5a.25.25 0 0 1-.2-.1l-.9-1.2C6.07 1.26 5.55 1 5 1H1.75z" />
                        </svg>
                        {{ files.length }} changed file{{ files.length !== 1 ? 's' : '' }}
                    </span>
                    <svg
                        class="h-4 w-4 text-text-secondary transition-transform"
                        :class="{ 'rotate-180': isDrawerOpen }"
                        viewBox="0 0 16 16"
                        fill="currentColor"
                    >
                        <path fill-rule="evenodd" d="M4.22 6.22a.75.75 0 0 1 1.06 0L8 8.94l2.72-2.72a.75.75 0 1 1 1.06 1.06l-3.25 3.25a.75.75 0 0 1-1.06 0L4.22 7.28a.75.75 0 0 1 0-1.06z" />
                    </svg>
                </button>

                <Transition
                    enter-active-class="transition-all duration-200 ease-out"
                    enter-from-class="max-h-0 opacity-0"
                    enter-to-class="max-h-80 opacity-100"
                    leave-active-class="transition-all duration-200 ease-in"
                    leave-from-class="max-h-80 opacity-100"
                    leave-to-class="max-h-0 opacity-0"
                >
                    <div v-if="isDrawerOpen" class="mt-2 overflow-hidden rounded-lg border border-border bg-bg-card">
                        <div class="max-h-64 overflow-y-auto p-2">
                            <template v-for="[dir, dirFiles] in fileTree" :key="dir">
                                <button
                                    v-for="file in dirFiles"
                                    :key="file.path"
                                    @click="selectFile(file)"
                                    class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm transition-colors"
                                    :class="selectedFile?.path === file.path
                                        ? 'bg-bg-surface text-text-heading font-medium'
                                        : 'text-text-secondary hover:bg-bg-surface/50 hover:text-text'"
                                >
                                    <span
                                        class="inline-flex h-4 w-4 shrink-0 items-center justify-center rounded text-[10px] font-bold"
                                        :class="fileStatusIcon(file.status).class"
                                    >
                                        {{ fileStatusIcon(file.status).label }}
                                    </span>
                                    <span class="truncate">{{ fileName(file.path) }}</span>
                                    <span v-if="file.additions || file.deletions" class="ml-auto shrink-0 font-mono text-xs">
                                        <span v-if="file.additions" class="text-success">+{{ file.additions }}</span>
                                        <span v-if="file.deletions" class="text-error"> -{{ file.deletions }}</span>
                                    </span>
                                </button>
                            </template>
                        </div>
                    </div>
                </Transition>
            </div>

            <!-- Desktop: File tree sidebar -->
            <aside class="hidden w-72 shrink-0 overflow-y-auto rounded-lg border border-border bg-bg-card md:block">
                <div class="border-b border-border px-4 py-2.5">
                    <span class="text-xs font-medium uppercase tracking-wider text-text-secondary">
                        {{ files.length }} changed file{{ files.length !== 1 ? 's' : '' }}
                    </span>
                </div>

                <div v-if="isLoading" class="flex items-center justify-center p-8">
                    <div class="flex items-center gap-2 text-sm text-text-secondary">
                        <span class="h-4 w-4 animate-spin rounded-full border-2 border-text-muted border-t-info"></span>
                        Loading diffs...
                    </div>
                </div>

                <div v-else-if="files.length === 0" class="p-4 text-center text-sm text-text-muted">
                    No changed files.
                </div>

                <div v-else class="p-2">
                    <template v-for="[dir, dirFiles] in fileTree" :key="dir">
                        <!-- Directory header -->
                        <button
                            v-if="dir !== '/'"
                            @click="toggleDir(dir)"
                            class="flex w-full items-center gap-1.5 rounded-md px-2 py-1 text-left text-xs font-medium text-text-secondary transition-colors hover:text-text"
                        >
                            <svg
                                class="h-3 w-3 transition-transform"
                                :class="{ '-rotate-90': collapsedDirs.has(dir) }"
                                viewBox="0 0 16 16"
                                fill="currentColor"
                            >
                                <path fill-rule="evenodd" d="M4.22 6.22a.75.75 0 0 1 1.06 0L8 8.94l2.72-2.72a.75.75 0 1 1 1.06 1.06l-3.25 3.25a.75.75 0 0 1-1.06 0L4.22 7.28a.75.75 0 0 1 0-1.06z" />
                            </svg>
                            <span class="truncate font-mono">{{ dir }}</span>
                        </button>

                        <!-- Files in this directory -->
                        <template v-if="!collapsedDirs.has(dir)">
                            <button
                                v-for="file in dirFiles"
                                :key="file.path"
                                @click="selectFile(file)"
                                class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm transition-colors"
                                :class="[
                                    dir !== '/' ? 'pl-6' : '',
                                    selectedFile?.path === file.path
                                        ? 'bg-bg-surface text-text-heading font-medium'
                                        : 'text-text-secondary hover:bg-bg-surface/50 hover:text-text'
                                ]"
                            >
                                <span
                                    class="inline-flex h-4 w-4 shrink-0 items-center justify-center rounded text-[10px] font-bold"
                                    :class="fileStatusIcon(file.status).class"
                                >
                                    {{ fileStatusIcon(file.status).label }}
                                </span>
                                <span class="truncate font-mono text-xs">{{ fileName(file.path) }}</span>
                                <span v-if="file.additions || file.deletions" class="ml-auto shrink-0 font-mono text-xs">
                                    <span v-if="file.additions" class="text-success">+{{ file.additions }}</span>
                                    <span v-if="file.deletions" class="text-error"> -{{ file.deletions }}</span>
                                </span>
                            </button>
                        </template>
                    </template>
                </div>
            </aside>

            <!-- Diff Panel -->
            <main class="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden rounded-lg border border-border">
                <!-- File title bar -->
                <div v-if="selectedFile" class="flex items-center justify-between border-b border-border bg-bg-card px-4 py-2">
                    <div class="flex min-w-0 items-center gap-2">
                        <span
                            class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded text-[11px] font-bold"
                            :class="fileStatusIcon(selectedFile.status).class"
                        >
                            {{ fileStatusIcon(selectedFile.status).label }}
                        </span>
                        <span class="truncate font-mono text-sm text-text-heading">{{ selectedFile.path }}</span>
                    </div>
                    <div v-if="selectedFile.additions || selectedFile.deletions" class="shrink-0 font-mono text-xs">
                        <span v-if="selectedFile.additions" class="text-success">+{{ selectedFile.additions }}</span>
                        <span v-if="selectedFile.deletions" class="text-error ml-1">-{{ selectedFile.deletions }}</span>
                    </div>
                </div>

                <!-- Diff content -->
                <div class="flex-1 overflow-auto bg-bg font-mono text-sm">
                    <!-- Loading state -->
                    <div v-if="isLoading" class="flex h-full items-center justify-center">
                        <div class="flex items-center gap-2 text-text-secondary">
                            <span class="h-4 w-4 animate-spin rounded-full border-2 border-text-muted border-t-info"></span>
                            Fetching diffs from device...
                        </div>
                    </div>

                    <!-- Empty state -->
                    <div v-else-if="files.length === 0" class="flex h-full items-center justify-center text-text-muted">
                        <div class="text-center">
                            <div class="mb-2 text-2xl">
                                {{ isRunning ? '...' : isFinished ? '--' : '' }}
                            </div>
                            <div class="text-xs">
                                {{ isRunning ? 'Waiting for diff data...' : isFinished ? 'No diffs available for this run.' : 'Run has not started yet.' }}
                            </div>
                        </div>
                    </div>

                    <!-- No file selected -->
                    <div v-else-if="!selectedFile" class="flex h-full items-center justify-center text-text-muted">
                        <div class="text-center text-sm">Select a file to view its diff</div>
                    </div>

                    <!-- Diff lines -->
                    <div v-else class="diff-content">
                        <template v-for="(line, idx) in parseDiffLines(selectedFile.diff)" :key="idx">
                            <!-- Hunk header -->
                            <div
                                v-if="line.type === 'hunk'"
                                class="sticky top-0 z-10 border-y border-border bg-info/5 px-4 py-1 text-xs text-info"
                            >
                                {{ line.content }}
                            </div>

                            <!-- Meta line -->
                            <div
                                v-else-if="line.type === 'meta'"
                                class="px-4 py-0.5 text-xs text-text-muted italic"
                            >
                                {{ line.content }}
                            </div>

                            <!-- Addition -->
                            <div
                                v-else-if="line.type === 'addition'"
                                class="diff-line-add flex border-l-2 border-success bg-success/8"
                            >
                                <span class="flex w-10 shrink-0 select-none items-center justify-center text-xs text-success/60">+</span>
                                <pre class="flex-1 whitespace-pre-wrap break-all px-2 py-0.5 text-text" v-html="highlightLine(line.content, detectLanguage(selectedFile?.path))"></pre>
                            </div>

                            <!-- Deletion -->
                            <div
                                v-else-if="line.type === 'deletion'"
                                class="diff-line-del flex border-l-2 border-error bg-error/8"
                            >
                                <span class="flex w-10 shrink-0 select-none items-center justify-center text-xs text-error/60">-</span>
                                <pre class="flex-1 whitespace-pre-wrap break-all px-2 py-0.5 text-text" v-html="highlightLine(line.content, detectLanguage(selectedFile?.path))"></pre>
                            </div>

                            <!-- Context line -->
                            <div v-else class="flex">
                                <span class="flex w-10 shrink-0 select-none items-center justify-center text-xs text-text-muted">&nbsp;</span>
                                <pre class="flex-1 whitespace-pre-wrap break-all px-2 py-0.5 text-text" v-html="highlightLine(line.content, detectLanguage(selectedFile?.path))"></pre>
                            </div>
                        </template>
                    </div>
                </div>
            </main>
        </div>
    </div>
</template>

<style scoped>
.diff-content :deep(.shiki) {
    display: inline;
    background: transparent !important;
}
.diff-content :deep(.shiki code) {
    font-family: var(--font-mono);
}
</style>
