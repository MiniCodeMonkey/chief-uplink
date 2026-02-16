<script setup lang="ts">
import { ArrowDown, ChevronDown, ChevronUp } from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';

interface OutputChunk {
    storyId: string | null;
    text: string;
}

const props = defineProps<{
    deviceId: number;
    chunks: OutputChunk[];
    isCollapsed?: boolean;
    hasActiveRun: boolean;
}>();

const emit = defineEmits<{
    'update:isCollapsed': [value: boolean];
}>();

const outputContainer = ref<HTMLElement | null>(null);
const isAutoScrollPaused = ref(false);
const isUserNearBottom = ref(true);
const displayedText = ref('');
const animationFrameId = ref<number | null>(null);

// Track rendered text length for smooth character-by-character animation
const targetText = computed(() => {
    return props.chunks.map((c) => c.text).join('');
});

// Story separators: detect when story ID changes
const storyTransitions = computed(() => {
    const transitions: { index: number; fromStory: string | null; toStory: string }[] = [];
    let currentStory: string | null = null;
    let charIndex = 0;

    for (const chunk of props.chunks) {
        if (chunk.storyId && chunk.storyId !== currentStory) {
            transitions.push({
                index: charIndex,
                fromStory: currentStory,
                toStory: chunk.storyId,
            });
            currentStory = chunk.storyId;
        }
        charIndex += chunk.text.length;
    }

    return transitions;
});

// Build the display content with story separators interleaved
const renderedSegments = computed(() => {
    const segments: { type: 'text' | 'separator'; content: string; storyId?: string }[] = [];
    const text = displayedText.value;
    const transitions = storyTransitions.value;

    if (transitions.length === 0) {
        if (text) {
            segments.push({ type: 'text', content: text });
        }
        return segments;
    }

    let lastIndex = 0;
    for (const transition of transitions) {
        // Clamp to displayed text length
        const idx = Math.min(transition.index, text.length);

        if (idx > lastIndex) {
            segments.push({ type: 'text', content: text.slice(lastIndex, idx) });
        }

        if (idx <= text.length) {
            // Only show separator if we've rendered past this point or are at it
            if (transition.fromStory !== null) {
                segments.push({
                    type: 'separator',
                    content: transition.toStory,
                    storyId: transition.toStory,
                });
            } else {
                // First story — show as a header-style separator
                segments.push({
                    type: 'separator',
                    content: transition.toStory,
                    storyId: transition.toStory,
                });
            }
        }

        lastIndex = idx;
    }

    // Remaining text after the last transition
    if (lastIndex < text.length) {
        segments.push({ type: 'text', content: text.slice(lastIndex) });
    }

    return segments;
});

// Smooth character-by-character animation
function animateText() {
    const target = targetText.value;
    const current = displayedText.value;

    if (current.length < target.length) {
        // Add characters in batches for smoother feel (5-10 chars per frame)
        const charsToAdd = Math.min(
            Math.max(5, Math.ceil((target.length - current.length) * 0.15)),
            target.length - current.length,
        );
        displayedText.value = target.slice(0, current.length + charsToAdd);

        if (!isAutoScrollPaused.value && outputContainer.value) {
            nextTick(() => {
                scrollToBottom();
            });
        }

        animationFrameId.value = requestAnimationFrame(animateText);
    } else {
        animationFrameId.value = null;
    }
}

// Watch for new chunks and start animation
watch(targetText, () => {
    if (animationFrameId.value === null) {
        animationFrameId.value = requestAnimationFrame(animateText);
    }
});

// Auto-scroll logic
function scrollToBottom() {
    if (outputContainer.value) {
        outputContainer.value.scrollTop = outputContainer.value.scrollHeight;
    }
}

function handleScroll() {
    if (!outputContainer.value) return;

    const { scrollTop, scrollHeight, clientHeight } = outputContainer.value;
    const distanceFromBottom = scrollHeight - scrollTop - clientHeight;

    // User is "near bottom" if within 50px
    isUserNearBottom.value = distanceFromBottom < 50;

    if (isUserNearBottom.value) {
        isAutoScrollPaused.value = false;
    } else {
        isAutoScrollPaused.value = true;
    }
}

function jumpToBottom() {
    isAutoScrollPaused.value = false;
    scrollToBottom();
}

function toggleCollapse() {
    emit('update:isCollapsed', !props.isCollapsed);
}

onMounted(() => {
    // Initialize with any existing text
    if (targetText.value) {
        displayedText.value = targetText.value;
        nextTick(() => scrollToBottom());
    }
});

onUnmounted(() => {
    if (animationFrameId.value !== null) {
        cancelAnimationFrame(animationFrameId.value);
    }
});
</script>

<template>
    <!-- Mobile: Collapsible section header -->
    <div class="lg:hidden">
        <button
            class="flex w-full items-center justify-between border-t border-border px-4 py-3 text-left transition-colors duration-[var(--duration-micro)] hover:bg-accent"
            @click="toggleCollapse"
        >
            <h3 class="text-sm font-medium">Claude Output</h3>
            <component
                :is="isCollapsed ? ChevronDown : ChevronUp"
                class="size-4 text-muted-foreground"
            />
        </button>
    </div>

    <!-- Desktop: Header -->
    <div class="hidden border-b border-border px-4 py-3 lg:block">
        <h3 class="text-sm font-medium">Claude Output</h3>
    </div>

    <!-- Content area -->
    <div
        v-show="!isCollapsed"
        class="relative flex-1"
    >
        <div
            ref="outputContainer"
            class="absolute inset-0 overflow-y-auto p-4"
            @scroll="handleScroll"
        >
            <div v-if="renderedSegments.length > 0" class="space-y-0">
                <template v-for="(segment, idx) in renderedSegments" :key="idx">
                    <!-- Story separator -->
                    <div
                        v-if="segment.type === 'separator'"
                        class="my-4 flex items-center gap-3 first:mt-0"
                    >
                        <div class="h-px flex-1 bg-border" />
                        <span class="shrink-0 rounded-full bg-primary/10 px-3 py-1 font-mono text-xs text-primary">
                            {{ segment.content }}
                        </span>
                        <div class="h-px flex-1 bg-border" />
                    </div>

                    <!-- Text content -->
                    <pre
                        v-else
                        class="whitespace-pre-wrap break-words font-mono text-sm leading-relaxed text-foreground"
                    >{{ segment.content }}</pre>
                </template>
            </div>

            <!-- Empty state -->
            <div
                v-else-if="hasActiveRun"
                class="flex h-full items-center justify-center"
            >
                <p class="text-sm text-muted-foreground">
                    Waiting for Claude output...
                </p>
            </div>
        </div>

        <!-- Jump to bottom FAB -->
        <Transition
            enter-active-class="transition-all duration-[var(--duration-standard)] ease-[var(--ease-snappy)]"
            enter-from-class="opacity-0 translate-y-2"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition-all duration-[var(--duration-micro)] ease-[var(--ease-snappy)]"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 translate-y-2"
        >
            <Button
                v-if="isAutoScrollPaused && renderedSegments.length > 0"
                size="sm"
                variant="secondary"
                class="absolute bottom-4 right-4 shadow-md"
                @click="jumpToBottom"
            >
                <ArrowDown class="size-4" />
                <span>Jump to bottom</span>
            </Button>
        </Transition>
    </div>
</template>
