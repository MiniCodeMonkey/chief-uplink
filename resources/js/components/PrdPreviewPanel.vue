<script setup lang="ts">
import { Radio } from 'lucide-vue-next';
import MarkdownIt from 'markdown-it';
import { computed, nextTick, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    content: string;
    isGenerating: boolean;
}>();

const previewContainer = ref<HTMLElement | null>(null);
const isAutoScrollEnabled = ref(true);

// Initialize markdown-it with sensible defaults
const md = new MarkdownIt({
    html: false,
    linkify: true,
    typographer: true,
});

const renderedHtml = computed(() => {
    if (!props.content) return '';
    return md.render(props.content);
});

// Auto-scroll to follow new content
function scrollToBottom() {
    if (!previewContainer.value || !isAutoScrollEnabled.value) return;
    previewContainer.value.scrollTo({
        top: previewContainer.value.scrollHeight,
        behavior: 'smooth',
    });
}

function handleScroll() {
    if (!previewContainer.value) return;
    const { scrollTop, scrollHeight, clientHeight } = previewContainer.value;
    const distanceFromBottom = scrollHeight - scrollTop - clientHeight;
    isAutoScrollEnabled.value = distanceFromBottom < 50;
}

// Watch content changes and scroll when generating
watch(
    () => props.content,
    () => {
        if (props.isGenerating) {
            nextTick(scrollToBottom);
        }
    },
);

onMounted(() => {
    if (props.content) {
        nextTick(scrollToBottom);
    }
});
</script>

<template>
    <div class="flex h-full flex-col">
        <!-- Preview header -->
        <div
            class="flex items-center justify-between border-b border-border px-4 py-2"
        >
            <h2 class="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                Preview
            </h2>
            <Transition
                enter-active-class="transition-opacity duration-[var(--duration-standard)]"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition-opacity duration-[var(--duration-micro)]"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <span
                    v-if="isGenerating"
                    class="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-medium text-primary"
                >
                    <Radio class="size-3 animate-pulse" />
                    Live
                </span>
            </Transition>
        </div>

        <!-- Preview content -->
        <div
            ref="previewContainer"
            class="flex-1 overflow-y-auto"
            @scroll="handleScroll"
        >
            <!-- Empty state -->
            <div
                v-if="!content"
                class="flex h-full items-center justify-center p-8 text-center"
            >
                <p class="text-sm text-muted-foreground">
                    The PRD preview will appear here as Claude generates it.
                </p>
            </div>

            <!-- Rendered markdown -->
            <div
                v-else
                class="prd-preview p-6"
                v-html="renderedHtml"
            />
        </div>
    </div>
</template>

<style scoped>
/* PRD preview markdown styling */
.prd-preview :deep(h1) {
    font-size: 1.5rem;
    font-weight: 700;
    margin-top: 1.5rem;
    margin-bottom: 0.75rem;
    color: var(--foreground);
    line-height: 1.3;
}

.prd-preview :deep(h1:first-child) {
    margin-top: 0;
}

.prd-preview :deep(h2) {
    font-size: 1.25rem;
    font-weight: 600;
    margin-top: 1.5rem;
    margin-bottom: 0.5rem;
    color: var(--foreground);
    line-height: 1.35;
}

.prd-preview :deep(h3) {
    font-size: 1.1rem;
    font-weight: 600;
    margin-top: 1.25rem;
    margin-bottom: 0.5rem;
    color: var(--foreground);
    line-height: 1.4;
}

.prd-preview :deep(h4) {
    font-size: 1rem;
    font-weight: 600;
    margin-top: 1rem;
    margin-bottom: 0.25rem;
    color: var(--foreground);
}

.prd-preview :deep(p) {
    margin-top: 0.5rem;
    margin-bottom: 0.5rem;
    line-height: 1.7;
    color: var(--foreground);
}

.prd-preview :deep(ul),
.prd-preview :deep(ol) {
    margin-top: 0.5rem;
    margin-bottom: 0.5rem;
    padding-left: 1.5rem;
}

.prd-preview :deep(ul) {
    list-style-type: disc;
}

.prd-preview :deep(ol) {
    list-style-type: decimal;
}

.prd-preview :deep(li) {
    margin-top: 0.25rem;
    margin-bottom: 0.25rem;
    line-height: 1.6;
    color: var(--foreground);
}

.prd-preview :deep(li > ul),
.prd-preview :deep(li > ol) {
    margin-top: 0.125rem;
    margin-bottom: 0.125rem;
}

.prd-preview :deep(code) {
    font-family: var(--font-mono, 'Geist Mono', monospace);
    font-size: 0.875em;
    background-color: var(--muted);
    padding: 0.125rem 0.375rem;
    border-radius: 0.25rem;
}

.prd-preview :deep(pre) {
    margin-top: 0.75rem;
    margin-bottom: 0.75rem;
    padding: 0.75rem 1rem;
    background-color: var(--muted);
    border-radius: 0.5rem;
    overflow-x: auto;
}

.prd-preview :deep(pre code) {
    background-color: transparent;
    padding: 0;
    font-size: 0.85em;
}

.prd-preview :deep(blockquote) {
    border-left: 3px solid var(--border);
    margin-top: 0.75rem;
    margin-bottom: 0.75rem;
    padding-left: 1rem;
    color: var(--muted-foreground);
}

.prd-preview :deep(hr) {
    border: none;
    border-top: 1px solid var(--border);
    margin-top: 1.5rem;
    margin-bottom: 1.5rem;
}

.prd-preview :deep(table) {
    width: 100%;
    border-collapse: collapse;
    margin-top: 0.75rem;
    margin-bottom: 0.75rem;
}

.prd-preview :deep(th),
.prd-preview :deep(td) {
    border: 1px solid var(--border);
    padding: 0.5rem 0.75rem;
    text-align: left;
}

.prd-preview :deep(th) {
    background-color: var(--muted);
    font-weight: 600;
}

.prd-preview :deep(strong) {
    font-weight: 600;
}

.prd-preview :deep(a) {
    color: var(--primary);
    text-decoration: underline;
    text-decoration-color: var(--primary);
    text-underline-offset: 2px;
}

.prd-preview :deep(a:hover) {
    opacity: 0.8;
}
</style>
