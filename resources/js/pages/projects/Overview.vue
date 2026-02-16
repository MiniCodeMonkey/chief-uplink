<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    Activity,
    CheckCircle2,
    CircleDot,
    Clock,
    FileText,
    GitBranch,
    GitCommitHorizontal,
    MessageSquare,
    Play,
    Plus,
    XCircle,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { ProgressBar } from '@/components/ui/progress-bar';
import { useCommandRelay } from '@/composables/useCommandRelay';
import { formatRelativeTime, useConnectionStatus } from '@/composables/useConnectionStatus';
import ProjectLayout from '@/layouts/ProjectLayout.vue';
import type { DeviceSummary, RecentActivity } from '@/types';

interface StoryDetail {
    id: string;
    title: string;
    status: string;
}

interface ProjectData {
    id: number;
    device_authorization_id: number;
    status: string;
    current_prd_name: string | null;
    stories_completed: number;
    stories_total: number;
    story_details: StoryDetail[] | null;
    active_sessions: number;
    recent_activity: RecentActivity[] | null;
    git_branch: string | null;
    last_commit_hash: string | null;
    last_commit_message: string | null;
}

interface RunHistoryItem {
    id: number;
    prd_name: string;
    status: string;
    stories_completed: number;
    stories_total: number;
    duration_seconds: number | null;
    tokens_used: number | null;
    error_message: string | null;
    started_at: string | null;
    finished_at: string | null;
}

interface DeviceWithProjects extends DeviceSummary {
    projects: { project_slug: string; device_authorization_id: number }[];
}

const props = defineProps<{
    projectSlug: string;
    projectName: string;
    project: ProjectData;
    recentRuns: RunHistoryItem[];
}>();

const page = usePage();
const { sendCommand } = useCommandRelay();
const { isOnline } = useConnectionStatus();

const serverNotLive = computed(() => !isOnline.value);

// Find the device for this project
const deviceId = computed(() => {
    const devices = (page.props.devices as DeviceWithProjects[]) || [];
    for (const device of devices) {
        for (const project of device.projects ?? []) {
            if (project.project_slug === props.projectSlug) {
                return device.id;
            }
        }
    }
    return null;
});

const hasNoPrd = computed(() => props.project.status === 'no_prd');
const isRunning = computed(() => props.project.status === 'running');
const isPaused = computed(() => props.project.status === 'paused');

const progressPercentage = computed(() => {
    if (!props.project.stories_total) return 0;
    return Math.round((props.project.stories_completed / props.project.stories_total) * 100);
});

const storiesRemaining = computed(() => {
    return Math.max(0, props.project.stories_total - props.project.stories_completed);
});

const storiesFailed = computed(() => {
    if (!props.project.story_details) return 0;
    return props.project.story_details.filter((s) => s.status === 'failed').length;
});

function statusLabel(status: string): string {
    switch (status) {
        case 'running':
            return 'Running';
        case 'idle':
            return 'Idle';
        case 'error':
            return 'Error';
        case 'paused':
            return 'Paused';
        case 'no_prd':
            return 'No PRD';
        default:
            return status;
    }
}

function statusColor(status: string): string {
    switch (status) {
        case 'running':
            return 'bg-primary/10 text-primary border-primary/20';
        case 'idle':
            return 'bg-muted text-muted-foreground border-border';
        case 'error':
            return 'bg-destructive/10 text-destructive border-destructive/20';
        case 'paused':
            return 'bg-warning/10 text-warning border-warning/20';
        default:
            return 'bg-muted text-muted-foreground border-border';
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

async function startRun() {
    if (!deviceId.value) return;
    await sendCommand(deviceId.value, 'start_run');
}

function navigateToNewPrd() {
    router.visit(`/projects/${props.projectSlug}/prds`);
}

function navigateToRunTab() {
    router.visit(`/projects/${props.projectSlug}/run`);
}

function navigateToDiffsTab() {
    router.visit(`/projects/${props.projectSlug}/diffs`);
}

function navigateToPrdsTab() {
    router.visit(`/projects/${props.projectSlug}/prds`);
}

function navigateToActivityView(event: RecentActivity) {
    // Navigate based on event type content
    const eventText = event.event.toLowerCase();
    if (eventText.includes('run') || eventText.includes('story')) {
        navigateToRunTab();
    } else if (eventText.includes('prd')) {
        navigateToPrdsTab();
    } else if (eventText.includes('diff')) {
        navigateToDiffsTab();
    } else {
        navigateToRunTab();
    }
}
</script>

<template>
    <Head :title="`${props.projectName} — Overview`" />

    <ProjectLayout :project-slug="props.projectSlug">
        <div class="flex flex-1 flex-col gap-4 p-4">
            <!-- Empty state for new projects (no PRD) -->
            <EmptyState
                v-if="hasNoPrd"
                :icon="FileText"
                title="Get started by creating a PRD"
                description="Define what you want to build by creating a PRD. Chief will use it to generate and execute your project."
                class="flex-1"
            >
                <template #action>
                    <div class="flex flex-col items-center gap-3">
                        <Button
                            :disabled="serverNotLive"
                            :title="serverNotLive ? 'Server offline' : undefined"
                            @click="navigateToNewPrd"
                        >
                            <Plus class="size-4" />
                            New PRD
                        </Button>
                        <Link
                            href="/docs/prds"
                            class="text-sm text-muted-foreground underline underline-offset-2 hover:text-foreground"
                        >
                            Learn about PRDs
                        </Link>
                    </div>
                </template>
            </EmptyState>

            <!-- Main content when project has a PRD -->
            <template v-else>
                <!-- Status Card + Quick Actions -->
                <div class="grid gap-4 lg:grid-cols-3">
                    <!-- Status Card -->
                    <Card class="lg:col-span-2">
                        <CardHeader class="pb-3">
                            <div class="flex items-start justify-between gap-2">
                                <CardTitle class="text-base">Status</CardTitle>
                                <Badge
                                    variant="outline"
                                    class="text-[10px] leading-tight"
                                    :class="statusColor(project.status)"
                                >
                                    {{ statusLabel(project.status) }}
                                </Badge>
                            </div>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <!-- PRD name -->
                            <div v-if="project.current_prd_name" class="space-y-1">
                                <p class="text-xs text-muted-foreground">Current PRD</p>
                                <p class="text-sm font-medium">{{ project.current_prd_name }}</p>
                            </div>

                            <!-- Progress bar + story count -->
                            <div v-if="project.stories_total > 0" class="space-y-2">
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-muted-foreground">Story progress</span>
                                    <span class="font-medium tabular-nums">
                                        {{ project.stories_completed }}/{{ project.stories_total }}
                                        <span class="text-muted-foreground font-normal">({{ progressPercentage }}%)</span>
                                    </span>
                                </div>
                                <ProgressBar
                                    :value="project.stories_completed"
                                    :max="project.stories_total"
                                    class="h-2"
                                    indicator-class="transition-all duration-500"
                                />
                            </div>

                            <!-- Active sessions -->
                            <div
                                v-if="project.active_sessions > 0"
                                class="flex items-center gap-2 rounded-md bg-primary/5 px-3 py-2"
                            >
                                <MessageSquare class="size-4 text-primary" />
                                <span class="text-sm text-primary">
                                    {{ project.active_sessions }} active {{ project.active_sessions === 1 ? 'session' : 'sessions' }}
                                </span>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Quick Actions -->
                    <Card>
                        <CardHeader class="pb-3">
                            <CardTitle class="text-base">Quick Actions</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="flex flex-col gap-2">
                                <Button
                                    class="w-full"
                                    :disabled="serverNotLive || isRunning"
                                    :title="serverNotLive ? 'Server offline' : isRunning ? 'Run already in progress' : undefined"
                                    @click="startRun"
                                >
                                    <Play class="size-4" />
                                    {{ isPaused ? 'Resume Run' : 'Start Run' }}
                                </Button>
                                <Button
                                    variant="outline"
                                    class="w-full"
                                    :disabled="serverNotLive"
                                    :title="serverNotLive ? 'Server offline' : undefined"
                                    @click="navigateToNewPrd"
                                >
                                    <Plus class="size-4" />
                                    New PRD
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Middle row: Activity Feed + Git Info -->
                <div class="grid gap-4 lg:grid-cols-2">
                    <!-- Recent Activity Feed -->
                    <Card>
                        <CardHeader class="pb-3">
                            <CardTitle class="text-base">Recent Activity</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div
                                v-if="project.recent_activity && project.recent_activity.length > 0"
                                class="space-y-1"
                            >
                                <button
                                    v-for="(event, index) in project.recent_activity.slice(0, 10)"
                                    :key="index"
                                    class="flex w-full items-start gap-3 rounded-md px-2 py-2 text-left transition-colors duration-[var(--duration-micro)] hover:bg-accent"
                                    @click="navigateToActivityView(event)"
                                >
                                    <Activity class="mt-0.5 size-3.5 shrink-0 text-muted-foreground" />
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm">{{ event.event }}</p>
                                        <p class="text-xs text-muted-foreground">
                                            {{ formatRelativeTime(event.timestamp) }}
                                        </p>
                                    </div>
                                </button>
                            </div>
                            <p v-else class="py-4 text-center text-sm text-muted-foreground">
                                No recent activity
                            </p>
                        </CardContent>
                    </Card>

                    <!-- Git Info Card -->
                    <Card>
                        <CardHeader class="pb-3">
                            <CardTitle class="text-base">Git Info</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div v-if="project.git_branch" class="flex items-center gap-2">
                                <GitBranch class="size-4 shrink-0 text-muted-foreground" />
                                <span class="truncate font-mono text-sm">{{ project.git_branch }}</span>
                            </div>
                            <div
                                v-if="project.last_commit_hash"
                                class="flex items-start gap-2"
                            >
                                <GitCommitHorizontal class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                                <div class="min-w-0 flex-1">
                                    <span class="font-mono text-sm text-primary">{{ project.last_commit_hash }}</span>
                                    <p v-if="project.last_commit_message" class="mt-0.5 truncate text-sm text-muted-foreground">
                                        {{ project.last_commit_message }}
                                    </p>
                                </div>
                            </div>
                            <p
                                v-if="!project.git_branch && !project.last_commit_hash"
                                class="py-4 text-center text-sm text-muted-foreground"
                            >
                                No git information available
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <!-- Bottom row: Stats + Run History -->
                <div class="grid gap-4 lg:grid-cols-2">
                    <!-- Stats Card -->
                    <Card>
                        <CardHeader class="pb-3">
                            <CardTitle class="text-base">Stats</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                                <div class="space-y-1">
                                    <p class="text-xs text-muted-foreground">Total</p>
                                    <p class="text-lg font-semibold tabular-nums">
                                        {{ project.stories_total || 0 }}
                                    </p>
                                </div>
                                <div class="space-y-1">
                                    <p class="text-xs text-muted-foreground">Completed</p>
                                    <p class="text-lg font-semibold tabular-nums text-success">
                                        {{ project.stories_completed || 0 }}
                                    </p>
                                </div>
                                <div class="space-y-1">
                                    <p class="text-xs text-muted-foreground">Remaining</p>
                                    <p class="text-lg font-semibold tabular-nums">
                                        {{ storiesRemaining }}
                                    </p>
                                </div>
                                <div class="space-y-1">
                                    <p class="text-xs text-muted-foreground">Failed</p>
                                    <p
                                        class="text-lg font-semibold tabular-nums"
                                        :class="storiesFailed > 0 ? 'text-destructive' : ''"
                                    >
                                        {{ storiesFailed }}
                                    </p>
                                </div>
                            </div>

                            <!-- Token usage from latest run -->
                            <div
                                v-if="recentRuns.length > 0 && recentRuns[0].tokens_used"
                                class="mt-4 border-t border-border pt-3"
                            >
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-muted-foreground">Last run tokens</span>
                                    <span class="font-medium tabular-nums">{{ formatTokens(recentRuns[0].tokens_used) }}</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Recent Runs -->
                    <Card>
                        <CardHeader class="pb-3">
                            <div class="flex items-center justify-between">
                                <CardTitle class="text-base">Recent Runs</CardTitle>
                                <button
                                    v-if="recentRuns.length > 0"
                                    class="text-xs text-primary hover:underline"
                                    @click="navigateToRunTab"
                                >
                                    View all
                                </button>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div v-if="recentRuns.length > 0" class="space-y-2">
                                <button
                                    v-for="run in recentRuns"
                                    :key="run.id"
                                    class="flex w-full items-center gap-3 rounded-md border border-border px-3 py-2 text-left transition-colors duration-[var(--duration-micro)] hover:bg-accent"
                                    @click="navigateToRunTab"
                                >
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
                                            <span class="tabular-nums">{{ run.stories_completed }}/{{ run.stories_total }} stories</span>
                                            <span>&middot;</span>
                                            <span>{{ formatDuration(run.duration_seconds) }}</span>
                                            <span v-if="run.started_at">&middot;</span>
                                            <span v-if="run.started_at">{{ formatRelativeTime(run.started_at) }}</span>
                                        </div>
                                    </div>
                                </button>
                            </div>
                            <p v-else class="py-4 text-center text-sm text-muted-foreground">
                                No runs yet. Create a PRD and start your first run.
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </template>

            <!-- Skeleton loading fallback (not shown since data is server-rendered) -->
        </div>
    </ProjectLayout>
</template>
