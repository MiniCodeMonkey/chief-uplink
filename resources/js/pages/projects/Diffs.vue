<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CheckCircle2,
    ChevronRight,
    Code2,
    FileText,
    Loader2,
    Minus,
    Plus,
    XCircle,
} from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import DiffFileTree from '@/components/DiffFileTree.vue';
import DiffFileViewer from '@/components/DiffFileViewer.vue';
import { Card, CardContent } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { Skeleton } from '@/components/ui/skeleton';
import { StatusDot } from '@/components/ui/status-dot';
import { useChiefMessages } from '@/composables/useChiefMessages';
import { useCommandRelay } from '@/composables/useCommandRelay';
import { useConnectionStatus } from '@/composables/useConnectionStatus';
import ProjectLayout from '@/layouts/ProjectLayout.vue';

interface StoryDetail {
    id: string;
    title: string;
    status: string;
    iterations?: number;
    error_summary?: string;
}

interface DiffFile {
    filename: string;
    additions: number;
    deletions: number;
    patch: string;
}

interface StoryDiff {
    story_id: string;
    files: DiffFile[];
    total_additions: number;
    total_deletions: number;
}

const props = defineProps<{
    projectSlug: string;
    projectName: string;
    deviceId: number;
    storyDetails: StoryDetail[] | null;
}>();

const { isOnline } = useConnectionStatus();
const { sendCommand } = useCommandRelay();
const { subscribe, on } = useChiefMessages(props.deviceId);

const serverNotLive = computed(() => !isOnline.value);

// Story accordion state
const expandedStoryId = ref<string | null>(null);

// Per-story diff data cache
const storyDiffs = ref<Record<string, StoryDiff>>({});
const loadingStoryId = ref<string | null>(null);
const storyDiffErrors = ref<Record<string, string>>({});

// Selected file for viewing diff
const selectedFile = ref<string | null>(null);

// Completed stories that can have diffs
const completedStories = computed(() => {
    if (!props.storyDetails) return [];
    return props.storyDetails.filter((s) => s.status === 'completed');
});

// All stories for display (completed stories have diffs)
const displayStories = computed(() => {
    if (!props.storyDetails) return [];
    return props.storyDetails.filter(
        (s) => s.status === 'completed' || s.status === 'failed',
    );
});

const hasNoStories = computed(() => displayStories.value.length === 0);

// Get the currently selected file's diff data
const selectedDiffFile = computed<DiffFile | null>(() => {
    if (!expandedStoryId.value || !selectedFile.value) return null;
    const diff = storyDiffs.value[expandedStoryId.value];
    if (!diff) return null;
    return diff.files.find((f) => f.filename === selectedFile.value) ?? null;
});

// Listen for diff responses from chief
onMounted(() => {
    subscribe();

    on('diffs_response', (message) => {
        const payload = message.message as Record<string, unknown>;
        if (payload.project_slug !== props.projectSlug) return;

        const storyId = payload.story_id as string;
        const files = (payload.files as DiffFile[]) ?? [];
        const totalAdditions = files.reduce((sum, f) => sum + f.additions, 0);
        const totalDeletions = files.reduce((sum, f) => sum + f.deletions, 0);

        storyDiffs.value[storyId] = {
            story_id: storyId,
            files,
            total_additions: totalAdditions,
            total_deletions: totalDeletions,
        };

        if (loadingStoryId.value === storyId) {
            loadingStoryId.value = null;
        }

        // Clear any previous error
        delete storyDiffErrors.value[storyId];

        // Auto-select first file if none selected
        if (expandedStoryId.value === storyId && !selectedFile.value && files.length > 0) {
            selectedFile.value = files[0].filename;
        }
    });

    on('error', (message) => {
        const payload = message.message as Record<string, unknown>;
        if (loadingStoryId.value) {
            storyDiffErrors.value[loadingStoryId.value] =
                (payload.message as string) || 'Failed to load diff';
            loadingStoryId.value = null;
        }
    });
});

// Toggle story accordion
function toggleStory(storyId: string) {
    if (expandedStoryId.value === storyId) {
        expandedStoryId.value = null;
        selectedFile.value = null;
        return;
    }

    expandedStoryId.value = storyId;
    selectedFile.value = null;

    // Fetch diff if not already loaded and story is completed
    const story = completedStories.value.find((s) => s.id === storyId);
    if (story && !storyDiffs.value[storyId] && !storyDiffErrors.value[storyId]) {
        fetchStoryDiff(storyId);
    } else if (storyDiffs.value[storyId]?.files.length) {
        // Auto-select first file
        selectedFile.value = storyDiffs.value[storyId].files[0].filename;
    }
}

async function fetchStoryDiff(storyId: string) {
    if (!isOnline.value) return;

    loadingStoryId.value = storyId;
    delete storyDiffErrors.value[storyId];

    const result = await sendCommand(props.deviceId, 'get_diffs', {
        project_slug: props.projectSlug,
        story_id: storyId,
    });

    if (!result) {
        loadingStoryId.value = null;
        storyDiffErrors.value[storyId] = 'Failed to fetch diff. Server may be offline.';
    }
}

function retryDiff(storyId: string) {
    delete storyDiffErrors.value[storyId];
    fetchStoryDiff(storyId);
}

function selectFile(filename: string) {
    selectedFile.value = filename;
}

function clearFileSelection() {
    selectedFile.value = null;
}

// Re-fetch expanded story diff when device comes back online
watch(isOnline, (online, wasOnline) => {
    if (online && !wasOnline && expandedStoryId.value) {
        const storyId = expandedStoryId.value;
        if (!storyDiffs.value[storyId]) {
            fetchStoryDiff(storyId);
        }
    }
});

function storyStatusIcon(status: string) {
    switch (status) {
        case 'completed':
            return CheckCircle2;
        case 'failed':
            return XCircle;
        default:
            return FileText;
    }
}

function storyStatusColor(status: string): string {
    switch (status) {
        case 'completed':
            return 'text-success';
        case 'failed':
            return 'text-destructive';
        default:
            return 'text-muted-foreground';
    }
}
</script>

<template>
    <Head :title="`${props.projectName} — Diffs`" />

    <ProjectLayout :project-slug="props.projectSlug">
        <div class="flex flex-1 flex-col gap-4 p-4">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold">Diffs</h2>
            </div>

            <!-- Offline state -->
            <div
                v-if="serverNotLive && hasNoStories"
                class="flex flex-1 flex-col items-center justify-center gap-3 py-12 text-center"
            >
                <StatusDot state="offline" class="size-3" />
                <p class="text-sm text-muted-foreground">
                    Connect server to view diffs
                </p>
                <p class="text-xs text-muted-foreground">
                    Diffs are fetched live from the chief server.
                </p>
            </div>

            <!-- Empty state (no completed stories) -->
            <EmptyState
                v-else-if="hasNoStories && !storyDetails"
                :icon="Code2"
                title="No diffs available"
                description="Diffs will appear here as stories are completed."
            />

            <EmptyState
                v-else-if="hasNoStories"
                :icon="Code2"
                title="Diffs will appear here as stories are completed"
                description="Start a run to begin generating code changes."
            />

            <!-- Story accordion -->
            <div v-else class="space-y-2">
                <TransitionGroup
                    enter-active-class="transition-all duration-[var(--duration-standard)] ease-[var(--ease-gentle)]"
                    enter-from-class="opacity-0 translate-y-2"
                    enter-to-class="opacity-100 translate-y-0"
                >
                    <Card
                        v-for="(story, index) in displayStories"
                        :key="story.id"
                        class="overflow-hidden border-border transition-colors duration-[var(--duration-micro)]"
                        :style="{ animationDelay: `${index * 50}ms` }"
                    >
                        <!-- Story header (accordion trigger) -->
                        <button
                            class="flex w-full items-center gap-3 px-4 py-3 text-left transition-colors duration-[var(--duration-micro)] hover:bg-accent/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/50 focus-visible:ring-offset-2 focus-visible:ring-offset-background"
                            :aria-expanded="expandedStoryId === story.id"
                            :disabled="story.status !== 'completed' || serverNotLive"
                            @click="toggleStory(story.id)"
                        >
                            <!-- Chevron -->
                            <ChevronRight
                                class="size-4 shrink-0 text-muted-foreground transition-transform duration-[var(--duration-slow)] ease-[var(--ease-snappy)]"
                                :class="{
                                    'rotate-90': expandedStoryId === story.id,
                                    'opacity-30': story.status !== 'completed' || serverNotLive,
                                }"
                            />

                            <!-- Status icon -->
                            <component
                                :is="storyStatusIcon(story.status)"
                                class="size-4 shrink-0"
                                :class="storyStatusColor(story.status)"
                            />

                            <!-- Story info -->
                            <div class="flex min-w-0 flex-1 items-center gap-2">
                                <span class="truncate text-sm font-medium">
                                    {{ story.title }}
                                </span>
                                <span class="shrink-0 font-mono text-xs text-muted-foreground">
                                    {{ story.id }}
                                </span>
                            </div>

                            <!-- File count & line changes (if diff loaded) -->
                            <div
                                v-if="storyDiffs[story.id]"
                                class="flex shrink-0 items-center gap-3 text-xs text-muted-foreground"
                            >
                                <span>{{ storyDiffs[story.id].files.length }} {{ storyDiffs[story.id].files.length === 1 ? 'file' : 'files' }}</span>
                                <span class="flex items-center gap-1">
                                    <span class="text-success">+{{ storyDiffs[story.id].total_additions }}</span>
                                    <span class="text-destructive">-{{ storyDiffs[story.id].total_deletions }}</span>
                                </span>
                            </div>

                            <!-- Loading spinner for this story -->
                            <Loader2
                                v-else-if="loadingStoryId === story.id"
                                class="size-4 shrink-0 animate-spin text-muted-foreground"
                            />

                            <!-- Failed status indicator -->
                            <span
                                v-else-if="story.status === 'failed'"
                                class="shrink-0 text-xs text-muted-foreground"
                            >
                                No diff available
                            </span>

                            <!-- Offline indicator on completed stories -->
                            <span
                                v-else-if="serverNotLive && story.status === 'completed'"
                                class="shrink-0 text-xs text-muted-foreground"
                            >
                                Offline
                            </span>
                        </button>

                        <!-- Expanded content -->
                        <div
                            class="grid transition-[grid-template-rows] duration-[var(--duration-slow)] ease-[var(--ease-gentle)]"
                            :class="expandedStoryId === story.id ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'"
                        >
                            <div class="overflow-hidden">
                                <div class="border-t border-border">
                                    <!-- Loading state -->
                                    <div
                                        v-if="loadingStoryId === story.id"
                                        class="space-y-3 p-4"
                                    >
                                        <div
                                            v-for="i in 3"
                                            :key="i"
                                            class="flex items-center gap-3"
                                        >
                                            <Skeleton class="h-4 w-4" />
                                            <Skeleton class="h-4 w-48" />
                                            <div class="ml-auto flex gap-2">
                                                <Skeleton class="h-4 w-8" />
                                                <Skeleton class="h-4 w-8" />
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Error state -->
                                    <div
                                        v-else-if="storyDiffErrors[story.id]"
                                        class="p-4 text-center"
                                    >
                                        <p class="text-sm text-destructive">
                                            {{ storyDiffErrors[story.id] }}
                                        </p>
                                        <button
                                            class="mt-2 text-sm text-primary underline-offset-4 hover:underline"
                                            :disabled="serverNotLive"
                                            @click.stop="retryDiff(story.id)"
                                        >
                                            Retry
                                        </button>
                                    </div>

                                    <!-- Diff content with file tree -->
                                    <div
                                        v-else-if="storyDiffs[story.id]"
                                    >
                                        <!-- Empty diff -->
                                        <div
                                            v-if="storyDiffs[story.id].files.length === 0"
                                            class="p-4 text-center"
                                        >
                                            <p class="text-sm text-muted-foreground">
                                                No file changes for this story.
                                            </p>
                                        </div>

                                        <!-- File tree + diff viewer layout -->
                                        <div v-else>
                                            <!-- Mobile: file list + selected file diff -->
                                            <div class="lg:hidden">
                                                <!-- Back button when viewing a file -->
                                                <div
                                                    v-if="selectedFile"
                                                    class="border-b border-border"
                                                >
                                                    <button
                                                        class="flex w-full items-center gap-2 px-4 py-2.5 text-sm text-primary transition-colors hover:bg-accent/50"
                                                        @click="clearFileSelection"
                                                    >
                                                        <ArrowLeft class="size-4" />
                                                        Back to file list
                                                    </button>
                                                    <div class="flex items-center gap-2 border-t border-border px-4 py-2 text-sm">
                                                        <FileText class="size-3.5 shrink-0 text-muted-foreground" />
                                                        <span class="min-w-0 flex-1 truncate font-mono text-xs">
                                                            {{ selectedFile }}
                                                        </span>
                                                    </div>
                                                </div>

                                                <!-- File list (mobile) -->
                                                <div
                                                    v-if="!selectedFile"
                                                    class="divide-y divide-border"
                                                >
                                                    <button
                                                        v-for="file in storyDiffs[story.id].files"
                                                        :key="file.filename"
                                                        class="flex w-full items-center gap-3 px-4 py-2.5 text-left transition-colors hover:bg-accent/50"
                                                        @click="selectFile(file.filename)"
                                                    >
                                                        <FileText class="size-4 shrink-0 text-muted-foreground" />
                                                        <span class="min-w-0 flex-1 truncate font-mono text-sm">
                                                            {{ file.filename }}
                                                        </span>
                                                        <div class="flex shrink-0 items-center gap-2 font-mono text-xs">
                                                            <span
                                                                v-if="file.additions > 0"
                                                                class="flex items-center gap-0.5 text-success"
                                                            >
                                                                <Plus class="size-3" />{{ file.additions }}
                                                            </span>
                                                            <span
                                                                v-if="file.deletions > 0"
                                                                class="flex items-center gap-0.5 text-destructive"
                                                            >
                                                                <Minus class="size-3" />{{ file.deletions }}
                                                            </span>
                                                        </div>
                                                        <ChevronRight class="size-4 shrink-0 text-muted-foreground" />
                                                    </button>
                                                </div>

                                                <!-- Selected file diff (mobile) -->
                                                <DiffFileViewer
                                                    v-if="selectedFile && selectedDiffFile"
                                                    :file="selectedDiffFile"
                                                />
                                            </div>

                                            <!-- Desktop: file tree sidebar + diff viewer -->
                                            <div class="hidden lg:flex">
                                                <!-- File tree sidebar -->
                                                <div class="w-64 shrink-0 border-r border-border">
                                                    <DiffFileTree
                                                        :files="storyDiffs[story.id].files"
                                                        :selected-file="selectedFile"
                                                        @select="selectFile"
                                                    />
                                                </div>

                                                <!-- Diff viewer -->
                                                <div class="min-w-0 flex-1">
                                                    <DiffFileViewer
                                                        v-if="selectedDiffFile"
                                                        :file="selectedDiffFile"
                                                    />
                                                    <div
                                                        v-else
                                                        class="flex items-center justify-center py-12 text-sm text-muted-foreground"
                                                    >
                                                        Select a file to view its diff
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Summary footer -->
                                            <div class="border-t border-border bg-accent/30 px-4 py-2 text-xs text-muted-foreground">
                                                {{ storyDiffs[story.id].files.length }}
                                                {{ storyDiffs[story.id].files.length === 1 ? 'file' : 'files' }}
                                                changed,
                                                <span class="text-success">{{ storyDiffs[story.id].total_additions }} additions</span>,
                                                <span class="text-destructive">{{ storyDiffs[story.id].total_deletions }} deletions</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </Card>
                </TransitionGroup>
            </div>

            <!-- Skeleton loading state (when story details are still loading from page props) -->
            <div
                v-if="!storyDetails && !hasNoStories"
                class="space-y-2"
            >
                <Card
                    v-for="i in 4"
                    :key="i"
                    class="border-border"
                >
                    <CardContent class="p-4">
                        <div class="flex items-center gap-3">
                            <Skeleton class="size-4" />
                            <Skeleton class="size-4" />
                            <Skeleton class="h-4 w-48" />
                            <Skeleton class="ml-auto h-4 w-20" />
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </ProjectLayout>
</template>
