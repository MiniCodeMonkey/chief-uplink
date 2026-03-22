<script setup>
import { Head } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useDeviceChannel } from '@/composables/useDeviceChannel.js';
import { createHighlighter } from 'shiki';
import axios from 'axios';

const props = defineProps({
    device: { type: Object, required: true },
    initialPath: { type: String, default: '' },
});

const currentPath = ref(props.initialPath);
const entries = ref([]);
const fileContent = ref(null);
const selectedFilePath = ref(null);
const isLoadingDir = ref(false);
const isLoadingFile = ref(false);
const isMobileDrawerOpen = ref(false);
const highlighter = ref(null);

// Breadcrumb segments from current path
const breadcrumbs = computed(() => {
    if (!currentPath.value) return [];
    const parts = currentPath.value.split('/').filter(Boolean);
    return parts.map((part, i) => ({
        label: part,
        path: parts.slice(0, i + 1).join('/'),
    }));
});

// Sort entries: directories first, then alphabetically
const sortedEntries = computed(() => {
    return [...entries.value].sort((a, b) => {
        if (a.is_dir !== b.is_dir) return a.is_dir ? -1 : 1;
        return a.name.localeCompare(b.name);
    });
});

// Language detection for syntax highlighting
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
        xml: 'html',
        env: 'bash',
        dockerfile: 'dockerfile',
        makefile: 'makefile',
    };
    return langMap[ext] ?? 'text';
}

function escapeHtml(text) {
    return text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

// Highlighted HTML for file content
const highlightedContent = computed(() => {
    if (!fileContent.value) return '';
    const lang = detectLanguage(selectedFilePath.value);
    if (!highlighter.value || lang === 'text') {
        return fileContent.value.split('\n').map(line => escapeHtml(line));
    }
    try {
        const html = highlighter.value.codeToHtml(fileContent.value, {
            lang,
            themes: { dark: 'tokyo-night', light: 'github-light' },
        });
        // Extract lines from shiki output
        const codeMatch = html.match(/<code[^>]*>([\s\S]*?)<\/code>/);
        if (codeMatch) {
            // Shiki wraps each line in a span — split by line spans
            const lines = codeMatch[1].split('\n');
            return lines;
        }
        return fileContent.value.split('\n').map(line => escapeHtml(line));
    } catch {
        return fileContent.value.split('\n').map(line => escapeHtml(line));
    }
});

const selectedFileName = computed(() => {
    if (!selectedFilePath.value) return '';
    return selectedFilePath.value.split('/').pop();
});

async function initHighlighter() {
    try {
        highlighter.value = await createHighlighter({
            themes: ['tokyo-night', 'github-light'],
            langs: ['javascript', 'typescript', 'php', 'go', 'bash', 'json', 'vue', 'html', 'css', 'markdown', 'yaml', 'sql', 'python', 'ruby', 'rust'],
        });
    } catch {
        // Fall back to plain text
    }
}

// Send cmd.files.list to device
function requestDirectoryListing(path) {
    if (!props.device?.id) return;
    isLoadingDir.value = true;
    entries.value = [];

    axios.post(`/api/devices/${props.device.id}/commands`, {
        type: 'cmd.files.list',
        payload: { path: path || '' },
    }).catch(() => {
        isLoadingDir.value = false;
    });
}

// Send cmd.file.get to device
function requestFileContent(path) {
    if (!props.device?.id || !path) return;
    isLoadingFile.value = true;
    fileContent.value = null;
    selectedFilePath.value = path;

    axios.post(`/api/devices/${props.device.id}/commands`, {
        type: 'cmd.file.get',
        payload: { path },
    }).catch(() => {
        isLoadingFile.value = false;
    });
}

function navigateToDir(path) {
    currentPath.value = path;
    selectedFilePath.value = null;
    fileContent.value = null;
    requestDirectoryListing(path);
}

function handleEntryClick(entry) {
    if (entry.is_dir) {
        const newPath = currentPath.value
            ? `${currentPath.value}/${entry.name}`
            : entry.name;
        navigateToDir(newPath);
    } else {
        const filePath = currentPath.value
            ? `${currentPath.value}/${entry.name}`
            : entry.name;
        requestFileContent(filePath);
    }
}

function navigateUp() {
    const parts = currentPath.value.split('/').filter(Boolean);
    parts.pop();
    navigateToDir(parts.join('/'));
}

function formatSize(bytes) {
    if (bytes == null) return '';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function fileIcon(entry) {
    if (entry.is_dir) return 'dir';
    const ext = entry.name.split('.').pop()?.toLowerCase();
    const codeExts = ['js', 'ts', 'jsx', 'tsx', 'vue', 'php', 'go', 'py', 'rb', 'rs', 'java', 'c', 'cpp', 'h', 'cs'];
    const dataExts = ['json', 'yaml', 'yml', 'xml', 'toml', 'csv'];
    const docExts = ['md', 'txt', 'rst', 'doc'];
    if (codeExts.includes(ext)) return 'code';
    if (dataExts.includes(ext)) return 'data';
    if (docExts.includes(ext)) return 'doc';
    return 'file';
}

// Listen for responses via device channel
useDeviceChannel(props.device.id, {
    onStreamEvent(data) {
        if (data.type === 'state.files.list') {
            const payload = data.payload || data;
            if (payload.files) {
                entries.value = payload.files;
                isLoadingDir.value = false;
            }
        } else if (data.type === 'state.file.response') {
            const payload = data.payload || data;
            if (payload.content != null) {
                fileContent.value = payload.content;
                isLoadingFile.value = false;
            }
        }
    },
});

onMounted(() => {
    initHighlighter();
    requestDirectoryListing(currentPath.value);
});

onUnmounted(() => {
    if (highlighter.value) highlighter.value.dispose();
});
</script>

<template>
    <Head :title="`Files — ${device.name}`" />

    <div class="flex h-[calc(100vh-4rem)] flex-col p-6 md:p-8">
        <!-- Header -->
        <div class="mb-4 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <h1 class="text-2xl font-bold text-text-heading">File Browser</h1>
                <div class="mt-1 flex items-center gap-3 text-sm text-text-secondary">
                    <span class="flex items-center gap-1.5">
                        <span
                            class="h-2 w-2 rounded-full"
                            :class="device.connected ? 'bg-success' : 'bg-error'"
                        ></span>
                        <span class="text-xs">{{ device.name }}</span>
                    </span>
                </div>
            </div>
        </div>

        <!-- Breadcrumb Navigation -->
        <nav class="mb-4 flex items-center gap-1 text-sm">
            <button
                @click="navigateToDir('')"
                class="rounded px-1.5 py-0.5 font-medium transition-colors"
                :class="currentPath ? 'text-interactive hover:bg-bg-surface' : 'text-text-heading'"
            >
                /
            </button>
            <template v-for="(crumb, i) in breadcrumbs" :key="crumb.path">
                <span class="text-text-muted">/</span>
                <button
                    @click="navigateToDir(crumb.path)"
                    class="rounded px-1.5 py-0.5 transition-colors"
                    :class="i === breadcrumbs.length - 1 && !selectedFilePath
                        ? 'font-medium text-text-heading'
                        : 'text-interactive hover:bg-bg-surface'"
                >
                    {{ crumb.label }}
                </button>
            </template>
            <template v-if="selectedFilePath">
                <span class="text-text-muted">/</span>
                <span class="rounded px-1.5 py-0.5 font-medium text-text-heading">
                    {{ selectedFileName }}
                </span>
            </template>
        </nav>

        <!-- Main Content -->
        <div class="flex min-h-0 flex-1 flex-col gap-4 md:flex-row">
            <!-- Directory Tree Sidebar -->
            <aside class="hidden w-72 shrink-0 overflow-y-auto rounded-lg border border-border bg-bg-card md:block">
                <div class="border-b border-border px-4 py-2.5">
                    <span class="text-xs font-medium uppercase tracking-wider text-text-secondary">
                        Directory
                    </span>
                </div>

                <div v-if="isLoadingDir" class="flex items-center justify-center p-8">
                    <div class="flex items-center gap-2 text-sm text-text-secondary">
                        <span class="h-4 w-4 animate-spin rounded-full border-2 border-text-muted border-t-info"></span>
                        Loading...
                    </div>
                </div>

                <div v-else-if="sortedEntries.length === 0" class="p-4 text-center text-sm text-text-muted">
                    Empty directory.
                </div>

                <div v-else class="p-2">
                    <!-- Parent directory link -->
                    <button
                        v-if="currentPath"
                        @click="navigateUp"
                        class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm text-text-secondary transition-colors hover:bg-bg-surface/50 hover:text-text"
                    >
                        <svg class="h-4 w-4 shrink-0 text-text-muted" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m12 19-7-7 7-7" />
                            <path d="M19 12H5" />
                        </svg>
                        <span class="font-mono text-xs">..</span>
                    </button>

                    <!-- Entries -->
                    <button
                        v-for="entry in sortedEntries"
                        :key="entry.name"
                        @click="handleEntryClick(entry)"
                        class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm transition-colors"
                        :class="selectedFilePath && selectedFilePath.endsWith('/' + entry.name)
                            ? 'bg-bg-surface text-text-heading font-medium'
                            : 'text-text-secondary hover:bg-bg-surface/50 hover:text-text'"
                    >
                        <!-- Directory icon -->
                        <svg v-if="entry.is_dir" class="h-4 w-4 shrink-0 text-info" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z" />
                        </svg>
                        <!-- Code file icon -->
                        <svg v-else-if="fileIcon(entry) === 'code'" class="h-4 w-4 shrink-0 text-interactive" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="16 18 22 12 16 6" />
                            <polyline points="8 6 2 12 8 18" />
                        </svg>
                        <!-- Data file icon -->
                        <svg v-else-if="fileIcon(entry) === 'data'" class="h-4 w-4 shrink-0 text-warning" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z" />
                            <path d="M14 2v4a2 2 0 0 0 2 2h4" />
                        </svg>
                        <!-- Generic file icon -->
                        <svg v-else class="h-4 w-4 shrink-0 text-text-muted" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z" />
                            <path d="M14 2v4a2 2 0 0 0 2 2h4" />
                        </svg>
                        <span class="truncate font-mono text-xs">{{ entry.name }}</span>
                        <span v-if="!entry.is_dir && entry.size != null" class="ml-auto shrink-0 text-xs text-text-muted">
                            {{ formatSize(entry.size) }}
                        </span>
                    </button>
                </div>
            </aside>

            <!-- Mobile: Directory listing as collapsible drawer -->
            <div class="md:hidden">
                <button
                    @click="isMobileDrawerOpen = !isMobileDrawerOpen"
                    class="flex w-full items-center justify-between rounded-lg border border-border bg-bg-card px-4 py-2.5 text-sm font-medium text-text-heading transition-colors hover:bg-bg-surface"
                >
                    <span class="flex items-center gap-2">
                        <svg class="h-4 w-4 text-text-secondary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z" />
                        </svg>
                        {{ sortedEntries.length }} item{{ sortedEntries.length !== 1 ? 's' : '' }}
                    </span>
                    <svg
                        class="h-4 w-4 text-text-secondary transition-transform"
                        :class="{ 'rotate-180': isMobileDrawerOpen }"
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
                    <div v-if="isMobileDrawerOpen" class="mt-2 overflow-hidden rounded-lg border border-border bg-bg-card">
                        <div class="max-h-64 overflow-y-auto p-2">
                            <button
                                v-if="currentPath"
                                @click="navigateUp(); isMobileDrawerOpen = false"
                                class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm text-text-secondary transition-colors hover:bg-bg-surface/50"
                            >
                                <svg class="h-4 w-4 shrink-0 text-text-muted" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="m12 19-7-7 7-7" />
                                    <path d="M19 12H5" />
                                </svg>
                                <span class="font-mono text-xs">..</span>
                            </button>
                            <button
                                v-for="entry in sortedEntries"
                                :key="entry.name"
                                @click="handleEntryClick(entry); if (!entry.is_dir) isMobileDrawerOpen = false"
                                class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm text-text-secondary transition-colors hover:bg-bg-surface/50 hover:text-text"
                            >
                                <svg v-if="entry.is_dir" class="h-4 w-4 shrink-0 text-info" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z" />
                                </svg>
                                <svg v-else class="h-4 w-4 shrink-0 text-text-muted" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z" />
                                    <path d="M14 2v4a2 2 0 0 0 2 2h4" />
                                </svg>
                                <span class="truncate font-mono text-xs">{{ entry.name }}</span>
                            </button>
                        </div>
                    </div>
                </Transition>
            </div>

            <!-- File Viewer Panel -->
            <main class="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden rounded-lg border border-border">
                <!-- File title bar -->
                <div v-if="selectedFilePath" class="flex items-center justify-between border-b border-border bg-bg-card px-4 py-2">
                    <span class="truncate font-mono text-sm text-text-heading">{{ selectedFilePath }}</span>
                    <button
                        @click="selectedFilePath = null; fileContent = null"
                        class="ml-2 shrink-0 rounded p-1 text-text-secondary transition-colors hover:bg-bg-surface hover:text-text"
                    >
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 6 6 18" />
                            <path d="m6 6 12 12" />
                        </svg>
                    </button>
                </div>

                <!-- File content -->
                <div class="file-content flex-1 overflow-auto bg-bg font-mono text-sm">
                    <!-- Loading directory -->
                    <div v-if="isLoadingDir && !selectedFilePath" class="flex h-full items-center justify-center">
                        <div class="flex items-center gap-2 text-text-secondary">
                            <span class="h-4 w-4 animate-spin rounded-full border-2 border-text-muted border-t-info"></span>
                            Fetching directory listing...
                        </div>
                    </div>

                    <!-- Loading file -->
                    <div v-else-if="isLoadingFile" class="flex h-full items-center justify-center">
                        <div class="flex items-center gap-2 text-text-secondary">
                            <span class="h-4 w-4 animate-spin rounded-full border-2 border-text-muted border-t-info"></span>
                            Fetching file content...
                        </div>
                    </div>

                    <!-- No file selected -->
                    <div v-else-if="!selectedFilePath" class="flex h-full items-center justify-center text-text-muted">
                        <div class="text-center text-sm">Select a file to view its contents</div>
                    </div>

                    <!-- File lines -->
                    <div v-else-if="fileContent != null" class="min-w-fit">
                        <div
                            v-for="(line, idx) in highlightedContent"
                            :key="idx"
                            class="flex hover:bg-bg-surface/30"
                        >
                            <span class="flex w-12 shrink-0 select-none items-center justify-end pr-4 text-xs text-text-muted">
                                {{ idx + 1 }}
                            </span>
                            <pre class="flex-1 whitespace-pre px-2 py-0.5 text-text" v-html="line"></pre>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</template>

<style scoped>
.file-content :deep(.shiki) {
    display: inline;
    background: transparent !important;
}
.file-content :deep(.shiki code) {
    font-family: var(--font-mono);
}
</style>
