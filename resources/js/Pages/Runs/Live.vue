<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
import { useDeviceChannel } from '@/composables/useDeviceChannel.js';
import { createHighlighter } from 'shiki';

const props = defineProps({
    run: { type: Object, required: true },
    prd: { type: Object, default: null },
    device: { type: Object, required: true },
});

const runData = ref({ ...props.run });
const outputLines = ref([]);
const outputContainer = ref(null);
const autoScroll = ref(true);
const highlighter = ref(null);

const isRunning = computed(() => runData.value.status === 'running');
const isFinished = computed(() => ['completed', 'failed', 'stopped'].includes(runData.value.status));

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

function toolIcon(toolName) {
    if (!toolName) return '⚙';
    const lower = toolName.toLowerCase();
    if (lower.includes('read') || lower.includes('file')) return '📄';
    if (lower.includes('write') || lower.includes('edit')) return '✏️';
    if (lower.includes('test') || lower.includes('run')) return '▶';
    if (lower.includes('search') || lower.includes('grep') || lower.includes('glob')) return '🔍';
    if (lower.includes('bash') || lower.includes('command') || lower.includes('exec')) return '⚡';
    return '⚙';
}

function parseToolLabel(line) {
    if (line.type !== 'tool') return null;
    const name = line.tool_name || 'Tool';
    const desc = line.tool_description || '';
    return `${toolIcon(name)} ${name}${desc ? ': ' + desc : ''}`;
}

async function initHighlighter() {
    try {
        highlighter.value = await createHighlighter({
            themes: ['tokyo-night'],
            langs: ['javascript', 'typescript', 'php', 'go', 'bash', 'json', 'vue', 'html', 'css', 'markdown', 'yaml', 'sql'],
        });
    } catch {
        // Shiki failed to load — fall back to plain text
    }
}

function highlightCode(code, lang) {
    if (!highlighter.value || !lang) return escapeHtml(code);
    try {
        return highlighter.value.codeToHtml(code, {
            lang,
            theme: 'tokyo-night',
        });
    } catch {
        return escapeHtml(code);
    }
}

function escapeHtml(text) {
    return text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

function appendOutput(line) {
    outputLines.value.push({
        id: outputLines.value.length,
        timestamp: new Date(),
        ...line,
    });

    if (autoScroll.value) {
        nextTick(() => {
            if (outputContainer.value) {
                outputContainer.value.scrollTop = outputContainer.value.scrollHeight;
            }
        });
    }
}

function handleScroll() {
    if (!outputContainer.value) return;
    const { scrollTop, scrollHeight, clientHeight } = outputContainer.value;
    const isAtBottom = scrollHeight - scrollTop - clientHeight < 40;
    autoScroll.value = isAtBottom;
}

function handleOutputClick() {
    if (autoScroll.value) {
        autoScroll.value = false;
    }
}

function scrollToBottom() {
    autoScroll.value = true;
    if (outputContainer.value) {
        outputContainer.value.scrollTop = outputContainer.value.scrollHeight;
    }
}

// Listen for DeviceStreamEvent via device channel
useDeviceChannel(props.device.id, {
    onStreamEvent(data) {
        if (data.type !== 'state.run.output') return;
        if (data.run_id && data.run_id !== props.run.id) return;

        const payload = data.payload || data;

        if (payload.content_type === 'tool_use') {
            appendOutput({
                type: 'tool',
                tool_name: payload.tool_name || payload.name,
                tool_description: payload.description || payload.content,
            });
        } else if (payload.content_type === 'code') {
            appendOutput({
                type: 'code',
                content: payload.content,
                language: payload.language || null,
            });
        } else {
            appendOutput({
                type: 'text',
                content: payload.content || payload.text || '',
            });
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

// Elapsed time
const now = ref(Date.now());
let timer = null;

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

onMounted(() => {
    initHighlighter();
    setupRunChannel();
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
    <Head :title="prd ? `Live — ${prd.title}` : 'Live Output'" />

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

            <!-- View Toggle + Actions -->
            <div class="flex shrink-0 items-center gap-2">
                <!-- Summary / Live toggle -->
                <div class="inline-flex rounded-md border border-border">
                    <Link
                        :href="`/runs/${run.id}`"
                        class="rounded-l-md px-3 py-1.5 text-sm font-medium transition-colors text-text-secondary hover:bg-bg-surface hover:text-text"
                    >
                        Summary
                    </Link>
                    <span
                        class="rounded-r-md bg-bg-surface px-3 py-1.5 text-sm font-medium text-text-heading"
                    >
                        Live
                    </span>
                </div>
            </div>
        </div>

        <!-- Terminal Output -->
        <div class="relative flex min-h-0 flex-1 flex-col overflow-hidden rounded-lg border border-border">
            <!-- Terminal title bar -->
            <div class="flex items-center justify-between border-b border-border bg-bg-card px-4 py-2">
                <div class="flex items-center gap-2">
                    <span class="h-3 w-3 rounded-full" :class="isRunning ? 'bg-success animate-pulse' : isFinished ? 'bg-text-muted' : 'bg-warning'"></span>
                    <span class="font-mono text-xs text-text-secondary">
                        {{ isRunning ? 'Streaming output...' : isFinished ? 'Run finished' : 'Waiting for output...' }}
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="font-mono text-xs text-text-muted">{{ outputLines.length }} lines</span>
                </div>
            </div>

            <!-- Scrolling output area -->
            <div
                ref="outputContainer"
                class="flex-1 overflow-y-auto bg-[#1a1b26] p-4 font-mono text-sm leading-relaxed"
                @scroll="handleScroll"
                @click="handleOutputClick"
            >
                <div v-if="outputLines.length === 0" class="flex h-full items-center justify-center text-text-muted">
                    <div class="text-center">
                        <div class="mb-2 text-2xl">{{ isRunning ? '...' : isFinished ? '—' : '⏳' }}</div>
                        <div class="text-xs">
                            {{ isRunning ? 'Waiting for output stream...' : isFinished ? 'No output was captured for this run.' : 'Run has not started yet.' }}
                        </div>
                    </div>
                </div>

                <div v-for="line in outputLines" :key="line.id" class="mb-1">
                    <!-- Tool usage line -->
                    <div v-if="line.type === 'tool'" class="flex items-center gap-2 rounded bg-[#24283b] px-2 py-1 text-xs text-info">
                        <span>{{ parseToolLabel(line) }}</span>
                    </div>

                    <!-- Code block with syntax highlighting -->
                    <div v-else-if="line.type === 'code' && highlighter" class="shiki-output overflow-x-auto rounded" v-html="highlightCode(line.content, line.language)"></div>
                    <pre v-else-if="line.type === 'code'" class="overflow-x-auto rounded bg-[#24283b] p-2 text-[#c0caf5]"><code>{{ line.content }}</code></pre>

                    <!-- Text output -->
                    <span v-else class="whitespace-pre-wrap text-[#c0caf5]">{{ line.content }}</span>
                </div>

                <!-- Blinking cursor when running -->
                <span v-if="isRunning" class="inline-block h-4 w-2 animate-pulse bg-[#c0caf5]"></span>
            </div>

            <!-- Auto-scroll indicator -->
            <Transition
                enter-active-class="transition-opacity duration-200"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition-opacity duration-200"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <button
                    v-if="!autoScroll && outputLines.length > 0"
                    @click="scrollToBottom"
                    class="absolute bottom-4 right-4 rounded-full border border-border bg-bg-card px-3 py-1.5 text-xs font-medium text-text-secondary shadow-lg transition-colors hover:bg-bg-surface hover:text-text-heading"
                >
                    Auto-scroll paused — click to resume
                </button>
            </Transition>
        </div>
    </div>
</template>

<style scoped>
.shiki-output :deep(pre) {
    margin: 0;
    padding: 0.5rem;
    border-radius: 0.25rem;
    overflow-x: auto;
}

.shiki-output :deep(code) {
    font-family: var(--font-mono);
}
</style>
