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
    Pause,
    Play,
    Square,
    XCircle,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import ClaudeOutputPanel from '@/components/ClaudeOutputPanel.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { EmptyState } from '@/components/ui/empty-state';
import { ProgressBar } from '@/components/ui/progress-bar';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useChiefMessages } from '@/composables/useChiefMessages';
import { useCommandRelay } from '@/composables/useCommandRelay';
import { formatRelativeTime, useConnectionStatus } from '@/composables/useConnectionStatus';
import { isInputFocused, isModKey } from '@/composables/useKeyboardShortcuts';
import { usePullToRefresh } from '@/composables/usePullToRefresh';
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

const { isOnline } = useConnectionStatus();
const { sendCommand } = useCommandRelay();
const { subscribe, on } = useChiefMessages(props.deviceId);
const { isRefreshing: isPullRefreshing, pullDistance: runPullDistance } = usePullToRefresh();
const expandedHistoryId = ref<number | null>(null);
const showStopConfirm = ref(false);

// Optimistic run state — tracks what we optimistically believe the run state is
type RunState = 'idle' | 'running' | 'paused' | 'error' | 'no_prd' | 'starting' | 'pausing' | 'resuming' | 'stopping';
const optimisticState = ref<RunState | null>(null);
const shakeControl = ref(false);

// Actual project status from server props
const serverStatus = computed(() => props.project.status);

// Effective status: optimistic if set, otherwise server
const effectiveStatus = computed<RunState>(() => {
    if (optimisticState.value) return optimisticState.value;
    return serverStatus.value as RunState;
});

const isRunning = computed(() => effectiveStatus.value === 'running');
const isPaused = computed(() => effectiveStatus.value === 'paused');
const isError = computed(() => effectiveStatus.value === 'error');
const isStarting = computed(() => effectiveStatus.value === 'starting');
const isPausing = computed(() => effectiveStatus.value === 'pausing');
const isResuming = computed(() => effectiveStatus.value === 'resuming');
const isStopping = computed(() => effectiveStatus.value === 'stopping');
const hasActiveRun = computed(() =>
    isRunning.value || isPaused.value || isError.value ||
    isStarting.value || isPausing.value || isResuming.value || isStopping.value,
);

// Show output panel when there's an active run or when output chunks exist
const showOutputPanel = computed(() =>
    hasActiveRun.value || outputChunks.value.length > 0,
);

// Show Start when no run is active (idle, no_prd, error — not transitioning)
const showStartButton = computed(() =>
    !hasActiveRun.value && effectiveStatus.value !== 'starting',
);

// Show Pause when running (not transitioning)
const showPauseButton = computed(() =>
    isRunning.value || isPausing.value,
);

// Show Resume when paused (not transitioning)
const showResumeButton = computed(() =>
    isPaused.value || isResuming.value,
);

// Show Stop when running, paused, or error (not while already stopping)
const showStopButton = computed(() =>
    (isRunning.value || isPaused.value || isError.value ||
     isStarting.value || isPausing.value || isResuming.value) && !isStopping.value,
);

// Controls are disabled when offline
const controlsDisabled = computed(() => !isOnline.value);

// Claude output streaming state
interface OutputChunk {
    storyId: string | null;
    text: string;
}
const outputChunks = ref<OutputChunk[]>([]);
const mobileOutputCollapsed = ref(false);
const currentOutputStoryId = ref<string | null>(null);

const stories = computed(() => props.project.story_details ?? []);

const progressPercentage = computed(() => {
    if (!props.project.stories_total) return 0;
    return Math.round((props.project.stories_completed / props.project.stories_total) * 100);
});

// Subscribe to chief messages on mount
onMounted(() => {
    subscribe();

    // Listen for run lifecycle events from chief
    on('run_progress', () => {
        // Server confirmed run is progressing — clear optimistic state
        optimisticState.value = null;
    });

    on('run_complete', () => {
        optimisticState.value = null;
    });

    on('run_paused', () => {
        optimisticState.value = null;
    });

    on('error', (message) => {
        const payload = message.message as Record<string, unknown>;
        // If we get an error related to run commands, rollback optimistic state
        if (payload.code === 'ALREADY_RUNNING' || payload.code === 'NOT_RUNNING' || payload.code === 'ALREADY_PAUSED') {
            // Graceful handling of duplicate commands — no error shown
            optimisticState.value = null;
        } else if (optimisticState.value) {
            triggerShake();
            optimisticState.value = null;
        }
    });

    // Listen for Claude output streaming
    on('claude_output', (message) => {
        const payload = message.message as Record<string, unknown>;
        const text = (payload.text as string) ?? '';
        const storyId = (payload.story_id as string) ?? currentOutputStoryId.value;

        if (storyId !== currentOutputStoryId.value) {
            currentOutputStoryId.value = storyId;
        }

        if (text) {
            outputChunks.value.push({
                storyId,
                text,
            });
        }
    });

    // Clear output when a new run starts
    on('run_progress', (message) => {
        const payload = message.message as Record<string, unknown>;
        // If stories_completed is 0, this is likely the beginning of a new run
        if (payload.stories_completed === 0 && outputChunks.value.length > 0) {
            outputChunks.value = [];
            currentOutputStoryId.value = null;
        }
    });
});

// Run-specific keyboard shortcuts (Cmd+Enter, Cmd+., Escape)
function handleRunKeydown(e: KeyboardEvent) {
    // Cmd/Ctrl+Enter: Start or resume run
    if (e.key === 'Enter' && isModKey(e)) {
        e.preventDefault();
        if (controlsDisabled.value) return;
        if (showStartButton.value) {
            handleStartRun();
        } else if (showResumeButton.value) {
            handleResumeRun();
        }
        return;
    }

    // Cmd/Ctrl+.: Pause run
    if (e.key === '.' && isModKey(e)) {
        e.preventDefault();
        if (controlsDisabled.value) return;
        if (showPauseButton.value) {
            handlePauseRun();
        }
        return;
    }

    // Escape: Stop run (with confirmation) — only when not in input and no modal is open
    if (e.key === 'Escape' && !isInputFocused()) {
        if (showStopConfirm.value) {
            // Don't interfere — ConfirmDialog handles its own Escape
            return;
        }
        if (showStopButton.value && !controlsDisabled.value) {
            e.preventDefault();
            showStopConfirm.value = true;
            return;
        }
    }
}

onMounted(() => {
    document.addEventListener('keydown', handleRunKeydown);
});

onUnmounted(() => {
    document.removeEventListener('keydown', handleRunKeydown);
});

function triggerShake() {
    shakeControl.value = true;
    setTimeout(() => {
        shakeControl.value = false;
    }, 500);
}

// Clear optimistic state when server props change (Inertia re-render)
let prevStatus = serverStatus.value;
const statusWatchInterval = setInterval(() => {
    if (serverStatus.value !== prevStatus) {
        prevStatus = serverStatus.value;
        optimisticState.value = null;
    }
}, 200);

onUnmounted(() => {
    clearInterval(statusWatchInterval);
});

async function handleStartRun() {
    if (controlsDisabled.value) return;
    optimisticState.value = 'starting';

    const result = await sendCommand(props.deviceId, 'start_run', {
        project_slug: props.projectSlug,
    });

    if (!result) {
        // Command failed — rollback
        triggerShake();
        optimisticState.value = null;
    }
    // On success, wait for run_progress event to confirm
}

async function handlePauseRun() {
    if (controlsDisabled.value) return;
    optimisticState.value = 'pausing';

    const result = await sendCommand(props.deviceId, 'pause_run', {
        project_slug: props.projectSlug,
    });

    if (!result) {
        triggerShake();
        optimisticState.value = null;
    }
}

async function handleResumeRun() {
    if (controlsDisabled.value) return;
    optimisticState.value = 'resuming';

    const result = await sendCommand(props.deviceId, 'resume_run', {
        project_slug: props.projectSlug,
    });

    if (!result) {
        triggerShake();
        optimisticState.value = null;
    }
}

async function handleStopRun() {
    showStopConfirm.value = false;
    if (controlsDisabled.value) return;
    optimisticState.value = 'stopping';

    const result = await sendCommand(props.deviceId, 'stop_run', {
        project_slug: props.projectSlug,
    });

    if (!result) {
        triggerShake();
        optimisticState.value = null;
    }
}

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
    if (!seconds) return '\u2014';
    if (seconds < 60) return `${seconds}s`;
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return `${minutes}m`;
    const hours = Math.floor(minutes / 60);
    const remainingMin = minutes % 60;
    return remainingMin > 0 ? `${hours}h ${remainingMin}m` : `${hours}h`;
}

function formatTokens(tokens: number | null): string {
    if (!tokens) return '\u2014';
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
            <!-- Pull-to-refresh indicator (mobile) -->
            <Transition
                enter-active-class="transition duration-[var(--duration-standard)] ease-[var(--ease-gentle)]"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition duration-[var(--duration-standard)] ease-[var(--ease-gentle)]"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="runPullDistance > 0 || isPullRefreshing"
                    class="flex items-center justify-center py-2 text-sm text-muted-foreground lg:hidden"
                    :style="{ transform: `translateY(${Math.max(0, runPullDistance - 20)}px)` }"
                >
                    <Loader2
                        class="mr-2 size-4"
                        :class="{ 'animate-spin': isPullRefreshing }"
                    />
                    <span v-if="isPullRefreshing">Refreshing...</span>
                    <span v-else-if="runPullDistance >= 80">Release to refresh</span>
                    <span v-else>Pull to refresh</span>
                </div>
            </Transition>

            <!-- Story List Panel (left on desktop, full on mobile) -->
            <div
                class="flex flex-col border-border lg:w-1/2 lg:border-r"
                :class="{ 'lg:w-full': !showOutputPanel }"
            >
                <!-- Run Control Bar -->
                <div
                    class="flex items-center gap-2 border-b border-border px-4 py-3"
                    :class="{ 'shake': shakeControl }"
                >
                    <TooltipProvider>
                        <!-- Start Run Button -->
                        <Transition
                            enter-active-class="transition-all duration-[var(--duration-standard)]"
                            enter-from-class="opacity-0 scale-95"
                            enter-to-class="opacity-100 scale-100"
                            leave-active-class="transition-all duration-[var(--duration-micro)]"
                            leave-from-class="opacity-100 scale-100"
                            leave-to-class="opacity-0 scale-95"
                        >
                            <Tooltip v-if="showStartButton">
                                <TooltipTrigger as-child>
                                    <Button
                                        :disabled="controlsDisabled || isStarting"
                                        @click="handleStartRun"
                                    >
                                        <Loader2
                                            v-if="isStarting"
                                            class="animate-spin"
                                        />
                                        <Play v-else />
                                        <span class="min-w-[5rem]">{{ isStarting ? 'Starting...' : 'Start Run' }}</span>
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent v-if="controlsDisabled">
                                    Server offline
                                </TooltipContent>
                            </Tooltip>
                        </Transition>

                        <!-- Pause Button -->
                        <Transition
                            enter-active-class="transition-all duration-[var(--duration-standard)]"
                            enter-from-class="opacity-0 scale-95"
                            enter-to-class="opacity-100 scale-100"
                            leave-active-class="transition-all duration-[var(--duration-micro)]"
                            leave-from-class="opacity-100 scale-100"
                            leave-to-class="opacity-0 scale-95"
                        >
                            <Tooltip v-if="showPauseButton">
                                <TooltipTrigger as-child>
                                    <Button
                                        variant="secondary"
                                        :disabled="controlsDisabled || isPausing"
                                        @click="handlePauseRun"
                                    >
                                        <Loader2
                                            v-if="isPausing"
                                            class="animate-spin"
                                        />
                                        <Pause v-else />
                                        <span>{{ isPausing ? 'Pausing...' : 'Pause' }}</span>
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent v-if="controlsDisabled">
                                    Server offline
                                </TooltipContent>
                            </Tooltip>
                        </Transition>

                        <!-- Resume Button -->
                        <Transition
                            enter-active-class="transition-all duration-[var(--duration-standard)]"
                            enter-from-class="opacity-0 scale-95"
                            enter-to-class="opacity-100 scale-100"
                            leave-active-class="transition-all duration-[var(--duration-micro)]"
                            leave-from-class="opacity-100 scale-100"
                            leave-to-class="opacity-0 scale-95"
                        >
                            <Tooltip v-if="showResumeButton">
                                <TooltipTrigger as-child>
                                    <Button
                                        :disabled="controlsDisabled || isResuming"
                                        @click="handleResumeRun"
                                    >
                                        <Loader2
                                            v-if="isResuming"
                                            class="animate-spin"
                                        />
                                        <Play v-else />
                                        <span>{{ isResuming ? 'Resuming...' : 'Resume' }}</span>
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent v-if="controlsDisabled">
                                    Server offline
                                </TooltipContent>
                            </Tooltip>
                        </Transition>

                        <!-- Stop Button -->
                        <Transition
                            enter-active-class="transition-all duration-[var(--duration-standard)]"
                            enter-from-class="opacity-0 scale-95"
                            enter-to-class="opacity-100 scale-100"
                            leave-active-class="transition-all duration-[var(--duration-micro)]"
                            leave-from-class="opacity-100 scale-100"
                            leave-to-class="opacity-0 scale-95"
                        >
                            <Tooltip v-if="showStopButton">
                                <TooltipTrigger as-child>
                                    <Button
                                        variant="destructive"
                                        :disabled="controlsDisabled || isStopping"
                                        @click="showStopConfirm = true"
                                    >
                                        <Loader2
                                            v-if="isStopping"
                                            class="animate-spin"
                                        />
                                        <Square v-else />
                                        <span>{{ isStopping ? 'Stopping...' : 'Stop' }}</span>
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent v-if="controlsDisabled">
                                    Server offline
                                </TooltipContent>
                            </Tooltip>
                        </Transition>
                    </TooltipProvider>

                    <!-- PRD name indicator -->
                    <span
                        v-if="project.current_prd_name && hasActiveRun"
                        class="ml-auto truncate text-xs text-muted-foreground"
                    >
                        {{ project.current_prd_name }}
                    </span>
                </div>

                <!-- Progress bar spanning full width at top -->
                <div
                    v-if="project.stories_total > 0"
                    role="status"
                    :aria-label="`Progress: ${project.stories_completed} of ${project.stories_total} stories completed`"
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
                        indicator-class="transition-all duration-300"
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
                        description="Create a PRD and start your first run."
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
                                :aria-expanded="expandedHistoryId === run.id"
                                aria-label="Toggle run history details"
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
                v-if="showOutputPanel"
                id="claude-output"
                class="flex flex-col border-t border-border lg:w-1/2 lg:border-t-0"
            >
                <ClaudeOutputPanel
                    v-model:is-collapsed="mobileOutputCollapsed"
                    :device-id="deviceId"
                    :chunks="outputChunks"
                    :has-active-run="hasActiveRun"
                />
            </div>
        </div>

        <!-- Stop Run Confirmation Dialog -->
        <ConfirmDialog
            v-model:open="showStopConfirm"
            title="Stop this run?"
            description="Progress will be saved but the current story will be abandoned."
            confirm-label="Stop Run"
            variant="destructive"
            @confirm="handleStopRun"
            @cancel="showStopConfirm = false"
        />
    </ProjectLayout>
</template>
