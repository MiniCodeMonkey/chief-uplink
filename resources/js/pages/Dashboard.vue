<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ChevronDown,
    FolderPlus,
    GitBranch,
    GitFork,
    MessageSquare,
    Monitor,
    MoreHorizontal,
    Pause,
    Plus,
    Rocket,
    Square,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import CloneRepositoryModal from '@/components/CloneRepositoryModal.vue';
import CreateProjectModal from '@/components/CreateProjectModal.vue';
import Onboarding from '@/components/Onboarding.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { ProgressBar } from '@/components/ui/progress-bar';
import { Skeleton } from '@/components/ui/skeleton';
import { StatusDot } from '@/components/ui/status-dot';
import { useCommandRelay } from '@/composables/useCommandRelay';
import {
    formatRelativeTime,
    useConnectionStatus,
} from '@/composables/useConnectionStatus';
import AppLayout from '@/layouts/AppLayout.vue';
import type { DeviceSummary, ProjectSummary } from '@/types';

interface DeviceWithProjects extends DeviceSummary {
    projects: ProjectSummary[];
}

const page = usePage();
const { sendCommand } = useCommandRelay();
const { isOnline, isOffline, isNeverConnected, selectedDevice, statusText } =
    useConnectionStatus();

const newMenuOpen = ref(false);
const cloneModalOpen = ref(false);
const createProjectModalOpen = ref(false);
const longPressTimer = ref<ReturnType<typeof setTimeout> | null>(null);
const longPressProjectSlug = ref<string | null>(null);
const offlineBannerDismissed = ref(false);

const devices = computed(
    () => (page.props.devices as DeviceWithProjects[]) || [],
);

const selectedDeviceId = computed(
    () => page.props.selectedDeviceId as number | null,
);

const currentDevice = computed(() => {
    if (!selectedDeviceId.value) return devices.value[0] ?? null;
    return (
        devices.value.find((d) => d.id === selectedDeviceId.value) ??
        devices.value[0] ??
        null
    );
});

const projects = computed(() => currentDevice.value?.projects ?? []);

const hasDevices = computed(() => devices.value.length > 0);

const showOnboarding = computed(
    () => page.props.showOnboarding as boolean,
);

const existingProjectNames = computed(() =>
    projects.value.map((p) => p.project_name),
);

// Server is not live (offline, reconnecting, or never connected)
const serverNotLive = computed(() => !isOnline.value);

// Show offline banner when server is offline and banner hasn't been dismissed
const showOfflineBanner = computed(
    () => hasDevices.value && isOffline.value && !offlineBannerDismissed.value,
);

// Offline banner text with last synced time
const offlineBannerText = computed(() => {
    if (!selectedDevice.value?.last_connected_at) {
        return 'Server offline — showing last known state';
    }
    return `Server offline — showing last known state from ${formatRelativeTime(selectedDevice.value.last_connected_at)}`;
});

// Show never-connected empty state when device exists but never connected and has no projects
const showNeverConnectedEmpty = computed(
    () =>
        hasDevices.value &&
        isNeverConnected.value &&
        projects.value.length === 0,
);

function dismissOfflineBanner() {
    offlineBannerDismissed.value = true;
}

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

function navigateToProject(slug: string) {
    router.visit(`/projects/${slug}`);
}

function lastActivityTime(project: ProjectSummary): string | null {
    if (!project.recent_activity || project.recent_activity.length === 0) {
        return null;
    }
    const latest = project.recent_activity[project.recent_activity.length - 1];
    return formatRelativeTime(latest.timestamp);
}

async function pauseRun(e: Event, deviceId: number) {
    e.stopPropagation();
    e.preventDefault();
    await sendCommand(deviceId, 'pause_run');
}

async function stopRun(e: Event, deviceId: number) {
    e.stopPropagation();
    e.preventDefault();
    await sendCommand(deviceId, 'stop_run');
}

function handleLongPressStart(slug: string, status: string) {
    // Only allow long-press for running projects when server is online
    if (!isOnline.value || status !== 'running') return;
    longPressTimer.value = setTimeout(() => {
        longPressProjectSlug.value = slug;
    }, 500);
}

function handleLongPressEnd() {
    if (longPressTimer.value) {
        clearTimeout(longPressTimer.value);
        longPressTimer.value = null;
    }
}

function closeLongPressMenu() {
    longPressProjectSlug.value = null;
}

function longPressPause(e: Event) {
    if (currentDevice.value) {
        pauseRun(e, currentDevice.value.id);
    }
    closeLongPressMenu();
}

function longPressStop(e: Event) {
    if (currentDevice.value) {
        stopRun(e, currentDevice.value.id);
    }
    closeLongPressMenu();
}

// Skeleton loading: devices is a lazy Inertia prop, initially undefined
const isLoading = computed(() => page.props.devices === undefined);
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout>
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <!-- Offline banner -->
            <Transition
                enter-active-class="transition duration-[var(--duration-standard)] ease-[var(--ease-gentle)]"
                enter-from-class="opacity-0 -translate-y-2"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition duration-[var(--duration-standard)] ease-[var(--ease-gentle)]"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 -translate-y-2"
            >
                <div
                    v-if="showOfflineBanner"
                    class="flex items-center gap-2 rounded-lg border border-border bg-muted/50 px-4 py-2.5 text-sm text-muted-foreground"
                    role="status"
                    aria-live="polite"
                >
                    <StatusDot state="offline" class="size-2 shrink-0" />
                    <span class="flex-1">{{ offlineBannerText }}</span>
                    <button
                        class="focus-ring -mr-1 flex size-6 shrink-0 items-center justify-center rounded-md text-muted-foreground/60 transition-colors hover:text-foreground"
                        title="Dismiss"
                        aria-label="Dismiss offline banner"
                        @click="dismissOfflineBanner"
                    >
                        <X class="size-3.5" />
                    </button>
                </div>
            </Transition>

            <!-- Top actions bar -->
            <div
                v-if="hasDevices && !showNeverConnectedEmpty"
                class="flex items-center justify-between"
            >
                <h1 class="text-lg font-semibold">Projects</h1>
                <!-- + New dropdown -->
                <div class="relative">
                    <Button
                        size="sm"
                        :disabled="serverNotLive"
                        :title="serverNotLive ? 'Server offline' : undefined"
                        @click="newMenuOpen = !newMenuOpen"
                    >
                        <Plus class="size-4" />
                        New
                        <ChevronDown
                            class="size-3.5 transition-transform duration-[var(--duration-micro)]"
                            :class="{ 'rotate-180': newMenuOpen }"
                        />
                    </Button>

                    <Teleport to="body">
                        <div
                            v-if="newMenuOpen"
                            class="fixed inset-0 z-40"
                            @click="newMenuOpen = false"
                        />
                    </Teleport>

                    <Transition
                        enter-active-class="transition duration-[var(--duration-standard)] ease-[var(--ease-snappy)]"
                        enter-from-class="opacity-0 scale-95 -translate-y-1"
                        enter-to-class="opacity-100 scale-100 translate-y-0"
                        leave-active-class="transition duration-[var(--duration-micro)] ease-[var(--ease-snappy)]"
                        leave-from-class="opacity-100 scale-100 translate-y-0"
                        leave-to-class="opacity-0 scale-95 -translate-y-1"
                    >
                        <div
                            v-if="newMenuOpen"
                            class="absolute top-full right-0 z-50 mt-1 min-w-[200px] overflow-hidden rounded-lg border border-border bg-popover p-1 shadow-md"
                        >
                            <button
                                class="flex w-full items-center gap-2.5 rounded-md px-3 py-2 text-left text-sm transition-colors duration-[var(--duration-micro)] hover:bg-accent"
                                :disabled="!isOnline"
                                :class="{
                                    'cursor-not-allowed opacity-50': !isOnline,
                                }"
                                :title="
                                    !isOnline ? 'Server offline' : undefined
                                "
                                @click="
                                    newMenuOpen = false;
                                    cloneModalOpen = true;
                                "
                            >
                                <GitFork class="size-4 text-muted-foreground" />
                                Clone Repository
                            </button>
                            <button
                                class="flex w-full items-center gap-2.5 rounded-md px-3 py-2 text-left text-sm transition-colors duration-[var(--duration-micro)] hover:bg-accent"
                                :disabled="!isOnline"
                                :class="{
                                    'cursor-not-allowed opacity-50': !isOnline,
                                }"
                                :title="
                                    !isOnline ? 'Server offline' : undefined
                                "
                                @click="
                                    newMenuOpen = false;
                                    createProjectModalOpen = true;
                                "
                            >
                                <FolderPlus
                                    class="size-4 text-muted-foreground"
                                />
                                New Project
                            </button>
                        </div>
                    </Transition>
                </div>
            </div>

            <!-- Skeleton loading state -->
            <div
                v-if="isLoading"
                class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
            >
                <div
                    v-for="n in 4"
                    :key="n"
                    class="rounded-lg border border-border bg-card p-5"
                >
                    <div class="flex flex-col gap-3">
                        <div class="flex items-start justify-between gap-2">
                            <Skeleton class="h-5 w-32" />
                            <Skeleton class="h-5 w-16 rounded-full" />
                        </div>
                        <Skeleton class="h-3 w-40" />
                        <div class="flex items-center justify-between pt-1">
                            <Skeleton class="h-3 w-24" />
                            <Skeleton class="h-3 w-14" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Onboarding: first-time user with no devices ever -->
            <Onboarding v-else-if="showOnboarding && !hasDevices" />

            <!-- Empty state: no devices (returning user who deauthorized all) -->
            <EmptyState
                v-else-if="!hasDevices"
                :icon="FolderPlus"
                title="No devices connected"
                description="Connect a device by running `chief login` on your machine, or deploy a cloud server to get started."
                class="flex-1"
            >
                <template #action>
                    <div class="flex flex-col items-center gap-3">
                        <Button as-child>
                            <a href="/settings/cloud-deploy">
                                <Rocket class="size-4" />
                                Deploy Server
                            </a>
                        </Button>
                        <Link
                            href="/docs/getting-started"
                            class="text-sm text-muted-foreground underline underline-offset-2 hover:text-foreground"
                        >
                            Read the docs
                        </Link>
                    </div>
                </template>
            </EmptyState>

            <!-- Empty state: server never connected, no cached state -->
            <EmptyState
                v-else-if="showNeverConnectedEmpty"
                :icon="Monitor"
                title="Server has never connected"
                description="Run `chief serve` on your device to connect it. Once connected, your projects will appear here."
                class="flex-1"
            >
                <template #action>
                    <div class="flex flex-col items-center gap-3">
                        <div
                            class="flex items-center gap-2 text-xs text-muted-foreground"
                        >
                            <StatusDot state="never-connected" class="size-2" />
                            <span>{{ statusText }}</span>
                        </div>
                        <Link
                            href="/docs/remote-monitoring"
                            class="text-sm text-muted-foreground underline underline-offset-2 hover:text-foreground"
                        >
                            Learn about remote monitoring
                        </Link>
                    </div>
                </template>
            </EmptyState>

            <!-- Empty state: device has no projects -->
            <EmptyState
                v-else-if="projects.length === 0"
                :icon="FolderPlus"
                title="No projects on this server"
                description="Clone a repository or create a new project to get started."
                class="flex-1"
            >
                <template #action>
                    <Button
                        :disabled="!isOnline"
                        :title="!isOnline ? 'Server offline' : undefined"
                        @click="createProjectModalOpen = true"
                    >
                        <Plus class="size-4" />
                        New Project
                    </Button>
                </template>
            </EmptyState>

            <!-- Project cards grid -->
            <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="(project, index) in projects"
                    :key="project.id"
                    class="project-card group relative cursor-pointer rounded-lg border border-border bg-card p-5 transition-all duration-[var(--duration-standard)] ease-[var(--ease-snappy)] hover:-translate-y-px hover:border-foreground/20 active:scale-[0.98]"
                    :class="{ 'opacity-70': serverNotLive }"
                    :style="{ animationDelay: `${index * 50}ms` }"
                    role="link"
                    :tabindex="0"
                    :aria-label="`${project.project_name} — ${statusLabel(project.status)}`"
                    @click="navigateToProject(project.project_slug)"
                    @keydown.enter="navigateToProject(project.project_slug)"
                    @touchstart="
                        handleLongPressStart(
                            project.project_slug,
                            project.status,
                        )
                    "
                    @touchend="handleLongPressEnd"
                    @touchcancel="handleLongPressEnd"
                >
                    <!-- Desktop hover action buttons (hidden when offline) -->
                    <div
                        v-if="
                            project.status === 'running' &&
                            currentDevice &&
                            isOnline
                        "
                        class="absolute top-3 right-3 z-10 flex gap-1 opacity-0 transition-opacity duration-[var(--duration-standard)] group-hover:opacity-100"
                    >
                        <button
                            class="focus-ring flex size-7 items-center justify-center rounded-md border border-border bg-card text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                            title="Pause run"
                            @click="pauseRun($event, currentDevice.id)"
                        >
                            <Pause class="size-3.5" />
                        </button>
                        <button
                            class="focus-ring flex size-7 items-center justify-center rounded-md border border-destructive/30 bg-card text-destructive transition-colors hover:bg-destructive/10"
                            title="Stop run"
                            @click="stopRun($event, currentDevice.id)"
                        >
                            <Square class="size-3.5" />
                        </button>
                    </div>

                    <!-- Mobile long-press hint (... icon) — hidden when offline -->
                    <div
                        v-if="project.status === 'running' && isOnline"
                        class="absolute top-3 right-3 z-10 flex group-hover:hidden lg:hidden"
                    >
                        <MoreHorizontal
                            class="size-4 text-muted-foreground/40"
                        />
                    </div>

                    <!-- Card content -->
                    <div class="flex flex-col gap-3">
                        <!-- Header: name + status -->
                        <div class="flex items-start justify-between gap-2">
                            <h3 class="leading-snug font-medium">
                                {{ project.project_name }}
                            </h3>
                            <Badge
                                variant="outline"
                                class="shrink-0 text-[10px] leading-tight"
                                :class="statusColor(project.status)"
                            >
                                {{ statusLabel(project.status) }}
                            </Badge>
                        </div>

                        <!-- PRD name + story progress (running projects) -->
                        <div
                            v-if="
                                project.status === 'running' &&
                                project.current_prd_name
                            "
                            class="space-y-2"
                        >
                            <p class="text-xs text-muted-foreground">
                                {{ project.current_prd_name }}
                            </p>
                            <div v-if="project.stories_total" class="space-y-1">
                                <ProgressBar
                                    :value="project.stories_completed ?? 0"
                                    :max="project.stories_total"
                                    class="h-1.5"
                                    indicator-class="transition-all duration-500"
                                />
                                <p class="text-[11px] text-muted-foreground">
                                    {{ project.stories_completed ?? 0 }}/{{
                                        project.stories_total
                                    }}
                                    stories
                                </p>
                            </div>
                        </div>

                        <!-- Active sessions badge -->
                        <div
                            v-if="
                                project.active_sessions &&
                                project.active_sessions > 0
                            "
                            class="flex items-center gap-1.5"
                        >
                            <MessageSquare class="size-3 text-primary" />
                            <span class="text-[11px] text-primary">
                                {{ project.active_sessions }} active
                                {{
                                    project.active_sessions === 1
                                        ? 'session'
                                        : 'sessions'
                                }}
                            </span>
                        </div>

                        <!-- Footer: branch + last activity -->
                        <div
                            class="flex items-center justify-between gap-2 pt-1 text-xs text-muted-foreground"
                        >
                            <span
                                v-if="project.git_branch"
                                class="flex items-center gap-1 truncate font-mono"
                            >
                                <GitBranch class="size-3 shrink-0" />
                                {{ project.git_branch }}
                            </span>
                            <span v-else />
                            <span
                                v-if="lastActivityTime(project)"
                                class="shrink-0"
                            >
                                {{ lastActivityTime(project) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Mobile long-press context menu -->
            <Teleport to="body">
                <div
                    v-if="longPressProjectSlug"
                    class="fixed inset-0 z-50"
                    @click="closeLongPressMenu"
                >
                    <div class="fixed inset-0 bg-black/20" />
                    <div
                        class="safe-area-bottom fixed inset-x-4 bottom-4 z-50 overflow-hidden rounded-xl border border-border bg-popover shadow-lg"
                    >
                        <div class="flex flex-col p-1">
                            <button
                                class="flex items-center gap-3 rounded-lg px-4 py-3 text-left text-sm transition-colors hover:bg-accent"
                                @click.stop="longPressPause"
                            >
                                <Pause class="size-4 text-muted-foreground" />
                                Pause Run
                            </button>
                            <button
                                class="flex items-center gap-3 rounded-lg px-4 py-3 text-left text-sm text-destructive transition-colors hover:bg-destructive/10"
                                @click.stop="longPressStop"
                            >
                                <Square class="size-4" />
                                Stop Run
                            </button>
                        </div>
                    </div>
                </div>
            </Teleport>
        </div>

        <!-- Clone Repository Modal -->
        <CloneRepositoryModal
            v-if="currentDevice"
            v-model:open="cloneModalOpen"
            :device-id="currentDevice.id"
            :is-online="isOnline"
        />

        <!-- Create Project Modal -->
        <CreateProjectModal
            v-if="currentDevice"
            v-model:open="createProjectModalOpen"
            :device-id="currentDevice.id"
            :is-online="isOnline"
            :existing-project-names="existingProjectNames"
        />
    </AppLayout>
</template>

<style scoped>
.project-card {
    animation: card-enter var(--duration-slow) var(--ease-gentle) both;
}

@keyframes card-enter {
    from {
        opacity: 0;
        transform: translateY(8px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (prefers-reduced-motion: reduce) {
    .project-card {
        animation: none;
    }
}

.safe-area-bottom {
    padding-bottom: env(safe-area-inset-bottom, 0px);
}
</style>
