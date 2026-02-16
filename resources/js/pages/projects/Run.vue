<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    AlertCircle,
    CheckCircle2,
    ChevronDown,
    ChevronRight,
    Circle,
    CircleDot,
    Clock,
    Loader2,
    XCircle,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { EmptyState } from '@/components/ui/empty-state';
import { ProgressBar } from '@/components/ui/progress-bar';
import { formatRelativeTime } from '@/composables/useConnectionStatus';
import ProjectLayout from '@/layouts/ProjectLayout.vue';

interface StoryDetail {
    id: string;
    title: string;
    status: string;
    iterations?: number;
    error_summary?: string;
}

interface ProjectRunData {
    id: number;
    status: string;
    current_prd_name: string | null;
    stories_completed: number;
    stories_total: number;
    story_details: StoryDetail[] | null;
    tokens_used: number | null;
}

interface RunHistoryItem {
    id: number;
    prd_name: string;
    status: string;
    stories_completed: number;
    stories_total: number;
    story_results: StoryDetail[] | null;
    duration_seconds: number | null;
    tokens_used: number | null;
    error_message: string | null;
    started_at: string | null;
    finished_at: string | null;
}

const props = defineProps<{
    projectSlug: string;
    projectName: string;
    deviceId: number;
    project: ProjectRunData;
    runHistory: RunHistoryItem[];
}>();

const expandedHistoryId = ref<number | null>(null);

const isRunning = computed(() => props.project.status === 'running');
const isPaused = computed(() => props.project.status === 'paused');
const isError = computed(() => props.project.status === 'error');
const hasActiveRun = computed(() => isRunning.value || isPaused.value || isError.value);

const stories = computed(() => props.project.story_details ?? []);

const progressPercentage = computed(() => {
    if (!props.project.stories_total) return 0;
    return Math.round((props.project.stories_completed / props.project.stories_total) * 100);
});

function storyStatusIcon(status: string) {
    switch (status) {
        case 'completed':
            return CheckCircle2;
        case 'in_progress':
            return Loader2;
        case 'failed':
            return XCircle;
        default:
            return Circle;
    }
}

function storyStatusColor(status: string): string {
    switch (status) {
        case 'completed':
            return 'text-success';
        case 'in_progress':
            return 'text-primary';
        case 'failed':
            return 'text-destructive';
        default:
            return 'text-muted-foreground';
    }
}

function runStatusColor(status: string): string {
    switch (status) {
        case 'completed':
            return 'bg-success/10 text-success border-success/20';
        case 'failed':
            return 'bg-destructive/10 text-destructive border-destructive/20';
        case 'paused':
            return 'bg-warning/10 text-warning border-warning/20';
        case 'stopped':
            return 'bg-muted text-muted-foreground border-border';
        default:
            return 'bg-muted text-muted-foreground border-border';
    }
}

function formatDuration(seconds: number | null): string {
    if (!seconds) return '—';
    if (seconds < 60) return `${seconds}s`;
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return `${minutes}m`;
    const hours = Math.floor(minutes / 60);
    const remainingMin = minutes % 60;
    return remainingMin > 0 ? `${hours}h ${remainingMin}m` : `${hours}h`;
}

function formatTokens(tokens: number | null): string {
    if (!tokens) return '—';
    if (tokens >= 1_000_000) return `${(tokens / 1_000_000).toFixed(1)}M`;
    if (tokens >= 1_000) return `${(tokens / 1_000).toFixed(0)}K`;
    return tokens.toString();
}

function toggleHistoryExpand(id: number) {
    expandedHistoryId.value = expandedHistoryId.value === id ? null : id;
}

function navigateToDiff() {
    router.visit(`/projects/${props.projectSlug}/diffs`);
}

function scrollToOutput() {
    const outputEl = document.getElementById('claude-output');
    if (outputEl) {
        outputEl.scrollIntoView({ behavior: 'smooth' });
    }
}
</script>

<template>
    <Head :title="`${props.projectName} — Run`" />

    <ProjectLayout :project-slug="props.projectSlug">
        <div class="flex flex-1 flex-col lg:flex-row">
            <!-- Story List Panel (left on desktop, full on mobile) -->
            <div
                class="flex flex-col border-border lg:w-1/2 lg:border-r"
                :class="{ 'lg:w-full': !hasActiveRun }"
            >
                <!-- Progress bar spanning full width at top -->
                <div
                    v-if="project.stories_total > 0"
                    class="border-b border-border px-4 py-3"
                >
                    <div class="mb-2 flex items-center justify-between text-sm">
                        <span class="font-medium">
                            {{ project.stories_completed }}/{{ project.stories_total }} stories
                        </span>
                        <span class="tabular-nums text-muted-foreground">{{ progressPercentage }}%</span>
                    </div>
                    <ProgressBar
                        :value="project.stories_completed"
                        :max="project.stories_total"
                        class="h-2"
                        indicator-class="transition-all duration-500"
                    />
                    <!-- Stats below progress bar -->
                    <div class="mt-2 flex items-center gap-3 text-xs text-muted-foreground">
                        <span v-if="project.tokens_used" class="tabular-nums">
                            {{ formatTokens(project.tokens_used) }} tokens
                        </span>
                    </div>
                </div>

                <!-- Story list -->
                <div class="flex-1 overflow-y-auto">
                    <div v-if="stories.length > 0" class="divide-y divide-border">
                        <button
                            v-for="story in stories"
                            :key="story.id"
                            class="flex w-full items-start gap-3 px-4 py-3 text-left transition-colors duration-[var(--duration-micro)] hover:bg-accent"
                            :class="{
                                'bg-primary/5': story.status === 'in_progress',
                            }"
                            @click="story.status === 'completed' ? navigateToDiff() : story.status === 'in_progress' ? scrollToOutput() : undefined"
                        >
                            <!-- Status icon -->
                            <component
                                :is="storyStatusIcon(story.status)"
                                class="mt-0.5 size-4 shrink-0"
                                :class="[
                                    storyStatusColor(story.status),
                                    { 'animate-spin': story.status === 'in_progress' },
                                ]"
                            />

                            <!-- Story info -->
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="font-mono text-xs"
                                        :class="storyStatusColor(story.status)"
                                    >
                                        {{ story.id }}
                                    </span>
                                    <span class="truncate text-sm">{{ story.title }}</span>
                                </div>
                                <!-- Error summary for failed stories -->
                                <p
                                    v-if="story.status === 'failed' && story.error_summary"
                                    class="mt-1 text-xs text-destructive"
                                >
                                    {{ story.error_summary }}
                                </p>
                            </div>

                            <!-- Iteration count -->
                            <span
                                v-if="story.iterations && (story.status === 'completed' || story.status === 'in_progress')"
                                class="shrink-0 text-xs tabular-nums text-muted-foreground"
                            >
                                {{ story.iterations }}x
                            </span>
                        </button>
                    </div>

                    <!-- Empty state for no stories -->
                    <EmptyState
                        v-else-if="!hasActiveRun && runHistory.length === 0"
                        :icon="Clock"
                        title="No runs yet"
                        description="Start a run to begin executing your PRD stories."
                        class="py-12"
                    />

                    <!-- No active run but have history -->
                    <div
                        v-else-if="!hasActiveRun"
                        class="p-4"
                    >
                        <p class="mb-4 text-center text-sm text-muted-foreground">
                            No active run. View run history below.
                        </p>
                    </div>
                </div>

                <!-- Run History Section -->
                <div
                    v-if="runHistory.length > 0"
                    class="border-t border-border"
                >
                    <div class="px-4 py-3">
                        <h3 class="text-sm font-medium">Run History</h3>
                    </div>
                    <div class="divide-y divide-border">
                        <div
                            v-for="run in runHistory"
                            :key="run.id"
                        >
                            <!-- Run header (clickable to expand) -->
                            <button
                                class="flex w-full items-center gap-3 px-4 py-3 text-left transition-colors duration-[var(--duration-micro)] hover:bg-accent"
                                @click="toggleHistoryExpand(run.id)"
                            >
                                <component
                                    :is="expandedHistoryId === run.id ? ChevronDown : ChevronRight"
                                    class="size-4 shrink-0 text-muted-foreground"
                                />
                                <component
                                    :is="run.status === 'completed' ? CheckCircle2 : run.status === 'failed' ? XCircle : run.status === 'paused' ? CircleDot : Clock"
                                    class="size-4 shrink-0"
                                    :class="{
                                        'text-success': run.status === 'completed',
                                        'text-destructive': run.status === 'failed',
                                        'text-warning': run.status === 'paused',
                                        'text-muted-foreground': run.status === 'stopped',
                                    }"
                                />
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <span class="truncate text-sm font-medium">{{ run.prd_name }}</span>
                                        <Badge
                                            variant="outline"
                                            class="text-[10px] leading-tight"
                                            :class="runStatusColor(run.status)"
                                        >
                                            {{ run.status }}
                                        </Badge>
                                    </div>
                                    <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                        <span class="tabular-nums">
                                            {{ run.stories_completed }}/{{ run.stories_total }} stories
                                        </span>
                                        <span>&middot;</span>
                                        <span>{{ formatDuration(run.duration_seconds) }}</span>
                                        <span v-if="run.tokens_used">&middot;</span>
                                        <span v-if="run.tokens_used" class="tabular-nums">
                                            {{ formatTokens(run.tokens_used) }} tokens
                                        </span>
                                        <span v-if="run.started_at">&middot;</span>
                                        <span v-if="run.started_at">
                                            {{ formatRelativeTime(run.started_at) }}
                                        </span>
                                    </div>
                                </div>
                            </button>

                            <!-- Expanded run details -->
                            <div
                                v-if="expandedHistoryId === run.id"
                                class="border-t border-border bg-muted/30 px-4 py-3"
                            >
                                <!-- Error message -->
                                <div
                                    v-if="run.error_message"
                                    class="mb-3 flex items-start gap-2 rounded-md bg-destructive/10 px-3 py-2"
                                >
                                    <AlertCircle class="mt-0.5 size-4 shrink-0 text-destructive" />
                                    <p class="text-sm text-destructive">{{ run.error_message }}</p>
                                </div>

                                <!-- Per-story results -->
                                <div
                                    v-if="run.story_results && run.story_results.length > 0"
                                    class="space-y-1"
                                >
                                    <div
                                        v-for="story in run.story_results"
                                        :key="story.id"
                                        class="flex items-center gap-2 py-1"
                                    >
                                        <component
                                            :is="storyStatusIcon(story.status)"
                                            class="size-3.5 shrink-0"
                                            :class="storyStatusColor(story.status)"
                                        />
                                        <span class="font-mono text-xs text-muted-foreground">{{ story.id }}</span>
                                        <span class="truncate text-sm">{{ story.title }}</span>
                                    </div>
                                </div>
                                <p
                                    v-else
                                    class="text-sm text-muted-foreground"
                                >
                                    No detailed story results available.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Claude Output Panel (right on desktop, below on mobile) -->
            <div
                v-if="hasActiveRun"
                id="claude-output"
                class="flex flex-col border-t border-border lg:w-1/2 lg:border-t-0"
            >
                <div class="border-b border-border px-4 py-3">
                    <h3 class="text-sm font-medium">Claude Output</h3>
                </div>
                <div class="flex-1 p-4">
                    <p class="text-sm text-muted-foreground">
                        Live Claude output streaming will be implemented in a future story.
                    </p>
                </div>
            </div>
        </div>
    </ProjectLayout>
</template>
