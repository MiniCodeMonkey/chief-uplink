<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
    run: { type: Object, required: true },
    prd: { type: Object, default: null },
    device: { type: Object, required: true },
});

const page = usePage();
const currentTeam = computed(() => page.props.auth?.currentTeam);

// Reactive copy for real-time updates
const runData = ref({ ...props.run });
const activeStoryIndex = ref(null);
const isStopping = ref(false);
const now = ref(Date.now());

// Stories from run data
const stories = computed(() => runData.value.stories ?? []);

const doneCount = computed(() => stories.value.filter(s => s.status === 'done').length);
const totalCount = computed(() => stories.value.length);
const progressPercent = computed(() => totalCount.value === 0 ? 0 : Math.round((doneCount.value / totalCount.value) * 100));

// Auto-select the active (in-progress) story, or first story
const activeStory = computed(() => {
    if (activeStoryIndex.value !== null && stories.value[activeStoryIndex.value]) {
        return stories.value[activeStoryIndex.value];
    }
    const inProgressIdx = stories.value.findIndex(s => s.status === 'in_progress');
    if (inProgressIdx !== -1) return stories.value[inProgressIdx];
    return stories.value[0] ?? null;
});

const activeIdx = computed(() => {
    if (activeStoryIndex.value !== null) return activeStoryIndex.value;
    const inProgressIdx = stories.value.findIndex(s => s.status === 'in_progress');
    return inProgressIdx !== -1 ? inProgressIdx : 0;
});

// Elapsed time
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

function storyStatusIcon(status) {
    const map = {
        done: 'done',
        in_progress: 'in_progress',
        failed: 'failed',
        pending: 'pending',
    };
    return map[status] ?? 'pending';
}

function selectStory(index) {
    activeStoryIndex.value = index;
}

async function stopRun() {
    isStopping.value = true;
    try {
        await axios.post(`/api/devices/${props.device.id}/commands`, {
            type: 'cmd.run.stop',
            payload: { run_id: runData.value.id },
        });
    } finally {
        isStopping.value = false;
    }
}

// Real-time updates via Reverb
let channel = null;

function setupEcho() {
    if (!window.Echo) return;

    channel = window.Echo.private(`run.${props.run.id}`);
    channel.listen('RunUpdated', (data) => {
        const previousStatus = runData.value.status;
        runData.value = {
            ...runData.value,
            status: data.status,
            stories: data.stories,
            started_at: data.started_at,
            completed_at: data.completed_at,
        };

        // Push notification on completion when tab is not active
        if (
            document.hidden &&
            previousStatus !== data.status &&
            (data.status === 'completed' || data.status === 'failed' || data.status === 'stopped')
        ) {
            sendPushNotification(data.status);
        }
    });
}

function sendPushNotification(status) {
    if (!('Notification' in window) || Notification.permission !== 'granted') return;

    const titles = {
        completed: 'Run Completed',
        failed: 'Run Failed',
        stopped: 'Run Stopped',
    };
    const bodies = {
        completed: `${doneCount.value}/${totalCount.value} stories completed successfully.`,
        failed: 'The run encountered an error.',
        stopped: 'The run was stopped.',
    };

    new Notification(titles[status] ?? 'Run Update', {
        body: bodies[status] ?? 'Run status changed.',
        icon: '/favicon.ico',
    });
}

// Request notification permission
function requestNotificationPermission() {
    if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission();
    }
}

// Timer for elapsed time
let timer = null;

onMounted(() => {
    setupEcho();
    requestNotificationPermission();

    timer = setInterval(() => {
        now.value = Date.now();
    }, 1000);
});

onUnmounted(() => {
    if (channel && window.Echo) {
        window.Echo.leave(`run.${props.run.id}`);
    }
    if (timer) {
        clearInterval(timer);
    }
});
</script>

<template>
    <Head :title="prd ? `Run — ${prd.title}` : 'Run'" />

    <div class="p-6 md:p-8">
        <!-- Header -->
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
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

            <!-- Action Buttons -->
            <div class="flex shrink-0 items-center gap-2">
                <button
                    v-if="isRunning"
                    @click="stopRun"
                    :disabled="isStopping"
                    class="inline-flex items-center gap-1.5 rounded-md border border-error/30 px-3 py-1.5 text-sm font-medium text-error transition-colors hover:bg-error/10 disabled:opacity-50"
                >
                    {{ isStopping ? 'Stopping...' : 'Stop Run' }}
                </button>
                <button
                    class="inline-flex items-center gap-1.5 rounded-md border border-border bg-bg-card px-3 py-1.5 text-sm font-medium text-text-heading transition-colors hover:border-border-hover hover:bg-bg-surface"
                >
                    View Diffs
                </button>
                <button
                    class="inline-flex items-center gap-1.5 rounded-md border border-border bg-bg-card px-3 py-1.5 text-sm font-medium text-text-heading transition-colors hover:border-border-hover hover:bg-bg-surface"
                >
                    View Log
                </button>
            </div>
        </div>

        <!-- Main Layout: sidebar on desktop, stacked on mobile -->
        <div class="flex flex-col gap-6 md:flex-row">
            <!-- Story Sidebar -->
            <aside class="w-full md:w-72 md:shrink-0">
                <!-- Progress Bar -->
                <div class="mb-4 rounded-lg border border-border bg-bg-card p-4">
                    <div class="mb-2 flex items-center justify-between text-sm">
                        <span class="font-medium text-text-heading">Progress</span>
                        <span class="text-text-secondary">{{ doneCount }}/{{ totalCount }} stories ({{ progressPercent }}%)</span>
                    </div>
                    <div class="h-2 overflow-hidden rounded-full bg-bg-surface">
                        <div
                            class="h-full rounded-full bg-interactive transition-all duration-500"
                            :style="{ width: `${progressPercent}%` }"
                        ></div>
                    </div>
                </div>

                <!-- Story List -->
                <div class="space-y-1">
                    <button
                        v-for="(story, index) in stories"
                        :key="story.id"
                        @click="selectStory(index)"
                        class="flex w-full items-center gap-3 rounded-md px-3 py-2.5 text-left text-sm transition-colors"
                        :class="activeIdx === index
                            ? 'bg-bg-surface text-text-heading font-medium'
                            : 'text-text-secondary hover:bg-bg-surface/50 hover:text-text'"
                    >
                        <!-- Status Indicator -->
                        <span class="shrink-0">
                            <!-- Done: green check -->
                            <svg v-if="storyStatusIcon(story.status) === 'done'" class="h-4 w-4 text-success" viewBox="0 0 16 16" fill="currentColor">
                                <path fill-rule="evenodd" d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm3.78-9.72a.75.75 0 0 0-1.06-1.06L6.75 9.19 5.28 7.72a.75.75 0 0 0-1.06 1.06l2 2a.75.75 0 0 0 1.06 0l4.5-4.5z" />
                            </svg>
                            <!-- In Progress: pulsing blue dot -->
                            <span v-else-if="storyStatusIcon(story.status) === 'in_progress'" class="flex h-4 w-4 items-center justify-center">
                                <span class="h-2.5 w-2.5 animate-pulse rounded-full bg-info"></span>
                            </span>
                            <!-- Failed: red X -->
                            <svg v-else-if="storyStatusIcon(story.status) === 'failed'" class="h-4 w-4 text-error" viewBox="0 0 16 16" fill="currentColor">
                                <path fill-rule="evenodd" d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zM5.28 5.28a.75.75 0 0 1 1.06 0L8 6.94l1.66-1.66a.75.75 0 1 1 1.06 1.06L9.06 8l1.66 1.66a.75.75 0 0 1-1.06 1.06L8 9.06l-1.66 1.66a.75.75 0 0 1-1.06-1.06L6.94 8 5.28 6.34a.75.75 0 0 1 0-1.06z" />
                            </svg>
                            <!-- Pending: gray circle -->
                            <span v-else class="flex h-4 w-4 items-center justify-center">
                                <span class="h-2.5 w-2.5 rounded-full border border-text-muted"></span>
                            </span>
                        </span>

                        <span class="truncate">{{ story.title }}</span>
                    </button>
                </div>
            </aside>

            <!-- Active Story Detail -->
            <main class="min-w-0 flex-1">
                <div v-if="activeStory" class="rounded-lg border border-border bg-bg-card p-6">
                    <!-- Story Header -->
                    <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <h2 class="text-lg font-bold text-text-heading">{{ activeStory.title }}</h2>
                            <span class="mt-1 text-xs text-text-secondary">{{ activeStory.id }}</span>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <span
                                v-if="activeStory.priority"
                                class="inline-flex rounded-md px-2 py-0.5 text-xs font-medium"
                                :class="{
                                    'bg-error/15 text-error': activeStory.priority <= 1,
                                    'bg-warning/15 text-warning': activeStory.priority === 2,
                                    'bg-info/15 text-info': activeStory.priority === 3,
                                    'bg-bg-surface text-text-secondary': activeStory.priority > 3,
                                }"
                            >
                                P{{ activeStory.priority }}
                            </span>
                            <span
                                class="inline-flex rounded-md px-2 py-0.5 text-xs font-medium"
                                :class="{
                                    'bg-success/15 text-success': activeStory.status === 'done',
                                    'bg-info/15 text-info': activeStory.status === 'in_progress',
                                    'bg-bg-surface text-text-secondary': activeStory.status === 'pending',
                                    'bg-error/15 text-error': activeStory.status === 'failed',
                                }"
                            >
                                {{ activeStory.status === 'in_progress' ? 'in progress' : activeStory.status }}
                            </span>
                        </div>
                    </div>

                    <!-- Description -->
                    <div v-if="activeStory.description" class="mb-4">
                        <h3 class="mb-1.5 text-xs font-medium uppercase tracking-wider text-text-secondary">Description</h3>
                        <p class="text-sm text-text">{{ activeStory.description }}</p>
                    </div>

                    <!-- Acceptance Criteria -->
                    <div v-if="activeStory.acceptance_criteria?.length > 0" class="mb-4">
                        <h3 class="mb-1.5 text-xs font-medium uppercase tracking-wider text-text-secondary">Acceptance Criteria</h3>
                        <ul class="space-y-1.5">
                            <li
                                v-for="(criterion, ci) in activeStory.acceptance_criteria"
                                :key="ci"
                                class="flex items-start gap-2 text-sm text-text"
                            >
                                <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-text-tertiary"></span>
                                <span>{{ criterion }}</span>
                            </li>
                        </ul>
                    </div>

                    <!-- Progress Notes -->
                    <div v-if="activeStory.progress_notes" class="mb-4">
                        <h3 class="mb-1.5 text-xs font-medium uppercase tracking-wider text-text-secondary">Progress Notes</h3>
                        <div class="rounded-md bg-bg-surface p-3 text-sm text-text font-mono whitespace-pre-wrap">{{ activeStory.progress_notes }}</div>
                    </div>

                    <!-- Iteration Count -->
                    <div v-if="activeStory.iteration_count > 0" class="flex items-center gap-2 text-xs text-text-secondary">
                        <span>Iterations:</span>
                        <span class="font-mono font-medium text-text-heading">{{ activeStory.iteration_count }}</span>
                    </div>
                </div>

                <!-- Empty state -->
                <div v-else class="flex items-center justify-center rounded-lg border border-border bg-bg-card p-12 text-text-tertiary">
                    No stories in this run.
                </div>
            </main>
        </div>
    </div>
</template>
