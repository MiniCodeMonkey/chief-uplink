<script setup lang="ts">
import { html as diff2html } from 'diff2html';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { CopyButton } from '@/components/ui/copy-button';

interface DiffFile {
    filename: string;
    additions: number;
    deletions: number;
    patch: string;
}

const props = defineProps<{
    file: DiffFile;
}>();

const isDark = ref(false);
const diffContainer = ref<HTMLElement | null>(null);

// Line count for virtualization — show truncation notice for very large diffs
const LARGE_DIFF_LINE_THRESHOLD = 5000;
const showFullDiff = ref(false);

const lineCount = computed(() => {
    if (!props.file.patch) return 0;
    return props.file.patch.split('\n').length;
});

const isLargeDiff = computed(() => lineCount.value > LARGE_DIFF_LINE_THRESHOLD);

const truncatedPatch = computed(() => {
    if (!isLargeDiff.value || showFullDiff.value) return props.file.patch;
    // Show first N lines
    const lines = props.file.patch.split('\n');
    return lines.slice(0, LARGE_DIFF_LINE_THRESHOLD).join('\n');
});

// Generate diff HTML using diff2html
const diffHtml = computed(() => {
    const patch = truncatedPatch.value;
    if (!patch) return '';

    // Construct a full unified diff string with proper headers
    const fullPatch = patch.startsWith('---')
        ? patch
        : `--- a/${props.file.filename}\n+++ b/${props.file.filename}\n${patch}`;

    return diff2html(fullPatch, {
        outputFormat: 'line-by-line',
        drawFileList: false,
        matching: 'lines',
        diffStyle: 'word',
    });
});

// Detect dark mode by checking for .dark class on <html>
function checkDarkMode() {
    isDark.value = document.documentElement.classList.contains('dark');
}

let observer: MutationObserver | null = null;

onMounted(() => {
    checkDarkMode();
    // Watch for dark mode class changes on <html>
    observer = new MutationObserver(checkDarkMode);
    observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['class'],
    });
});

onUnmounted(() => {
    observer?.disconnect();
});

// Also recheck on file change
watch(() => props.file, checkDarkMode);
</script>

<template>
    <div class="diff-file-viewer">
        <!-- Diff content -->
        <div
            ref="diffContainer"
            class="diff-content overflow-x-auto"
            :class="isDark ? 'd2h-dark-color-scheme' : 'd2h-light-color-scheme'"
            v-html="diffHtml"
        />

        <!-- Large diff truncation notice -->
        <div
            v-if="isLargeDiff && !showFullDiff"
            class="flex items-center justify-center gap-2 border-t border-border bg-muted/50 px-4 py-3"
        >
            <p class="text-sm text-muted-foreground">
                Large diff truncated ({{ lineCount.toLocaleString() }} lines).
            </p>
            <button
                class="text-sm font-medium text-primary underline-offset-4 hover:underline"
                @click="showFullDiff = true"
            >
                Show full diff
            </button>
        </div>

        <!-- Copy button for the full diff -->
        <div class="flex justify-end border-t border-border bg-muted/30 px-3 py-1.5">
            <CopyButton :value="props.file.patch" label="Copy diff" />
        </div>
    </div>
</template>

<style>
/* Import diff2html styles */
@import 'diff2html/bundles/css/diff2html.min.css';

/* Override diff2html styles to match our design system */
.diff-file-viewer .d2h-wrapper {
    font-family: var(--font-mono, 'Geist Mono', ui-monospace, monospace);
    font-size: 13px;
}

.diff-file-viewer .d2h-file-wrapper {
    border: none;
    border-radius: 0;
    margin-bottom: 0;
}

.diff-file-viewer .d2h-file-header {
    display: none;
}

.diff-file-viewer .d2h-diff-table {
    font-family: var(--font-mono, 'Geist Mono', ui-monospace, monospace);
    font-size: 13px;
    line-height: 1.5;
}

/* Light mode overrides */
.diff-file-viewer.d2h-light-color-scheme .d2h-code-linenumber,
.diff-file-viewer .d2h-light-color-scheme .d2h-code-linenumber {
    background-color: var(--background);
    border-color: var(--border);
    color: var(--muted-foreground);
}

.diff-file-viewer.d2h-light-color-scheme .d2h-code-line,
.diff-file-viewer .d2h-light-color-scheme .d2h-code-line {
    color: var(--foreground);
}

/* Dark mode overrides to match our theme */
.diff-file-viewer .d2h-dark-color-scheme {
    background-color: transparent;
}

.diff-file-viewer .d2h-dark-color-scheme .d2h-code-linenumber {
    background-color: var(--background);
    border-color: var(--border);
}

.diff-file-viewer .d2h-dark-color-scheme .d2h-file-wrapper {
    border-color: var(--border);
}

/* Ensure code content is selectable */
.diff-file-viewer .d2h-code-line-ctn {
    user-select: text;
}
</style>
