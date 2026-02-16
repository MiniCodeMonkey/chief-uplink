<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    ChevronDown,
    FolderPlus,
    GitBranch,
    GitFork,
    MessageSquare,
    MoreHorizontal,
    Pause,
    Plus,
    Square,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { ProgressBar } from '@/components/ui/progress-bar';
import { Skeleton } from '@/components/ui/skeleton';
import { useCommandRelay } from '@/composables/useCommandRelay';
import { formatRelativeTime, useConnectionStatus } from '@/composables/useConnectionStatus';
import AppLayout from '@/layouts/AppLayout.vue';
import type { DeviceSummary, ProjectSummary } from '@/types';

interface DeviceWithProjects extends DeviceSummary {
    projects: ProjectSummary[];
}

const page = usePage();
const { sendCommand } = useCommandRelay();
const { isOnline } = useConnectionStatus();

const newMenuOpen = ref(false);
const longPressTimer = ref<ReturnType<typeof setTimeout> | null>(null);
const longPressProjectSlug = ref<string | null>(null);

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

function handleLongPressStart(slug: string) {
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
            <!-- Top actions bar -->
            <div
                v-if="hasDevices"
                class="flex items-center justify-between"
            >
                <h1 class="text-lg font-semibold">Projects</h1>
                <!-- + New dropdown -->
                <div class="relative">
                    <Button
                        size="sm"
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
                            class="absolute right-0 top-full z-50 mt-1 min-w-[200px] overflow-hidden rounded-lg border border-border bg-popover p-1 shadow-md"
                        >
                            <button
                                class="flex w-full items-center gap-2.5 rounded-md px-3 py-2 text-left text-sm transition-colors duration-[var(--duration-micro)] hover:bg-accent"
                                :disabled="!isOnline"
                                :class="{ 'opacity-50 cursor-not-allowed': !isOnline }"
                                :title="!isOnline ? 'Server offline' : undefined"
                                @click="newMenuOpen = false"
                            >
                                <GitFork class="size-4 text-muted-foreground" />
                                Clone Repository
                            </button>
                            <button
                                class="flex w-full items-center gap-2.5 rounded-md px-3 py-2 text-left text-sm transition-colors duration-[var(--duration-micro)] hover:bg-accent"
                                :disabled="!isOnline"
                                :class="{ 'opacity-50 cursor-not-allowed': !isOnline }"
                                :title="!isOnline ? 'Server offline' : undefined"
                                @click="newMenuOpen = false"
                            >
                                <FolderPlus class="size-4 text-muted-foreground" />
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

            <!-- Empty state: no devices -->
            <EmptyState
                v-else-if="!hasDevices"
                :icon="FolderPlus"
                title="No devices connected"
                description="Connect a device by running `chief login` on your machine, or deploy a cloud server to get started."
                class="flex-1"
            />

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
                    >
                        <Plus class="size-4" />
                        New Project
                    </Button>
                </template>
            </EmptyState>

            <!-- Project cards grid -->
            <div
                v-else
                class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
            >
                <div
                    v-for="(project, index) in projects"
                    :key="project.id"
                    class="project-card group relative cursor-pointer rounded-lg border border-border bg-card p-5 transition-all duration-[var(--duration-standard)] ease-[var(--ease-snappy)] hover:border-foreground/20 hover:-translate-y-px active:scale-[0.98]"
                    :style="{ animationDelay: `${index * 50}ms` }"
                    role="link"
                    :tabindex="0"
                    :aria-label="`${project.project_name} — ${statusLabel(project.status)}`"
                    @click="navigateToProject(project.project_slug)"
                    @keydown.enter="navigateToProject(project.project_slug)"
                    @touchstart="handleLongPressStart(project.project_slug)"
                    @touchend="handleLongPressEnd"
                    @touchcancel="handleLongPressEnd"
                >
                    <!-- Desktop hover action buttons -->
                    <div
                        v-if="project.status === 'running' && currentDevice"
                        class="absolute right-3 top-3 z-10 flex gap-1 opacity-0 transition-opacity duration-[var(--duration-standard)] group-hover:opacity-100"
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

                    <!-- Mobile long-press hint (... icon) -->
                    <div
                        v-if="project.status === 'running'"
                        class="absolute right-3 top-3 z-10 flex lg:hidden group-hover:hidden"
                    >
                        <MoreHorizontal class="size-4 text-muted-foreground/40" />
                    </div>

                    <!-- Card content -->
                    <div class="flex flex-col gap-3">
                        <!-- Header: name + status -->
                        <div class="flex items-start justify-between gap-2">
                            <h3 class="font-medium leading-snug">
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
                            v-if="project.status === 'running' && project.current_prd_name"
                            class="space-y-2"
                        >
                            <p class="text-xs text-muted-foreground">
                                {{ project.current_prd_name }}
                            </p>
                            <div
                                v-if="project.stories_total"
                                class="space-y-1"
                            >
                                <ProgressBar
                                    :value="project.stories_completed ?? 0"
                                    :max="project.stories_total"
                                    class="h-1.5"
                                    indicator-class="transition-all duration-500"
                                />
                                <p class="text-[11px] text-muted-foreground">
                                    {{ project.stories_completed ?? 0 }}/{{ project.stories_total }} stories
                                </p>
                            </div>
                        </div>

                        <!-- Active sessions badge -->
                        <div
                            v-if="project.active_sessions && project.active_sessions > 0"
                            class="flex items-center gap-1.5"
                        >
                            <MessageSquare class="size-3 text-primary" />
                            <span class="text-[11px] text-primary">
                                {{ project.active_sessions }} active {{ project.active_sessions === 1 ? 'session' : 'sessions' }}
                            </span>
                        </div>

                        <!-- Footer: branch + last activity -->
                        <div class="flex items-center justify-between gap-2 pt-1 text-xs text-muted-foreground">
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
                    <div class="fixed inset-x-4 bottom-4 z-50 overflow-hidden rounded-xl border border-border bg-popover shadow-lg safe-area-bottom">
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
