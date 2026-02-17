<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { FileText, Pencil, Play, Plus } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { Skeleton } from '@/components/ui/skeleton';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useChiefMessages } from '@/composables/useChiefMessages';
import { useCommandRelay } from '@/composables/useCommandRelay';
import { useConnectionStatus } from '@/composables/useConnectionStatus';
import ProjectLayout from '@/layouts/ProjectLayout.vue';

interface PrdItem {
    id: string;
    name: string;
    story_count: number;
    status: 'active' | 'done' | 'draft';
}

const props = defineProps<{
    projectSlug: string;
    projectName: string;
    deviceId: number;
}>();

const { isOnline } = useConnectionStatus();
const { sendCommand } = useCommandRelay();
const { subscribe, on } = useChiefMessages(props.deviceId);

const serverNotLive = computed(() => !isOnline.value);

// PRD list state
const isLoading = ref(true);
const loadError = ref<string | null>(null);
const prds = ref<PrdItem[]>([]);

// Listen for chief server responses
onMounted(() => {
    subscribe();

    on('prds_response', (message) => {
        const payload = message.payload as Record<string, unknown>;
        if (payload.project !== props.projectSlug) return;

        if (loadTimeout) clearTimeout(loadTimeout);
        prds.value = (payload.prds as PrdItem[]) ?? [];
        isLoading.value = false;
        loadError.value = null;
    });

    on('error', (message) => {
        const payload = message.message as Record<string, unknown>;
        if (isLoading.value) {
            isLoading.value = false;
            loadError.value = (payload.message as string) || 'Failed to load PRDs';
        }
    });

    loadPrds();
});

let loadTimeout: ReturnType<typeof setTimeout> | null = null;

async function loadPrds() {
    if (!isOnline.value) {
        isLoading.value = false;
        return;
    }

    isLoading.value = true;
    loadError.value = null;

    if (loadTimeout) clearTimeout(loadTimeout);
    loadTimeout = setTimeout(() => {
        if (isLoading.value) {
            isLoading.value = false;
            loadError.value = 'Server did not respond. Please try again.';
        }
    }, 15000);

    const result = await sendCommand(props.deviceId, 'get_prds', {
        project: props.projectSlug,
    });

    if (!result) {
        if (loadTimeout) clearTimeout(loadTimeout);
        isLoading.value = false;
        loadError.value = 'Failed to load PRDs. Server may be offline.';
    }
}

// Re-load when device comes back online
watch(isOnline, (online, wasOnline) => {
    if (online && !wasOnline && prds.value.length === 0) {
        loadPrds();
    }
});

function statusBadgeClasses(status: string): string {
    switch (status) {
        case 'active':
            return 'bg-success/10 text-success border-success/20';
        case 'done':
            return 'bg-muted text-muted-foreground border-border';
        case 'draft':
            return 'bg-warning/10 text-warning border-warning/20';
        default:
            return 'bg-muted text-muted-foreground border-border';
    }
}

function statusLabel(status: string): string {
    switch (status) {
        case 'active':
            return 'Active';
        case 'done':
            return 'Done';
        case 'draft':
            return 'Draft';
        default:
            return status;
    }
}

function handleRun(prdId: string) {
    sendCommand(props.deviceId, 'start_run', {
        project: props.projectSlug,
        prd_id: prdId,
    });
    router.visit(`/projects/${props.projectSlug}/run`);
}

function handleRefine(prdId: string) {
    router.visit(`/projects/${props.projectSlug}/prd/${prdId}/refine`);
}

function handleNewPrd() {
    router.visit(`/projects/${props.projectSlug}/prd/new`);
}
</script>

<template>
    <Head :title="`${props.projectName} — PRDs`" />

    <ProjectLayout :project-slug="props.projectSlug">
        <div class="flex flex-1 flex-col gap-4 p-4">
            <!-- Header with New PRD button -->
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold">PRDs</h2>
                <TooltipProvider>
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button
                                :disabled="serverNotLive"
                                @click="handleNewPrd"
                            >
                                <Plus class="size-4" />
                                New PRD
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent v-if="serverNotLive">
                            Server offline
                        </TooltipContent>
                    </Tooltip>
                </TooltipProvider>
            </div>

            <!-- Offline state (no cached data) -->
            <div
                v-if="serverNotLive && prds.length === 0 && !isLoading"
                class="py-12 text-center"
            >
                <p class="text-sm text-muted-foreground">
                    Connect to server to view PRDs.
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    PRDs are fetched live from the chief server.
                </p>
            </div>

            <!-- Load error state -->
            <div
                v-else-if="loadError && prds.length === 0 && !isLoading"
                class="py-12 text-center"
            >
                <p class="text-sm text-destructive">{{ loadError }}</p>
                <Button
                    variant="outline"
                    class="mt-4"
                    :disabled="serverNotLive"
                    @click="loadPrds"
                >
                    Retry
                </Button>
            </div>

            <!-- Skeleton loading state -->
            <div
                v-else-if="isLoading"
                class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
            >
                <Card
                    v-for="i in 3"
                    :key="i"
                    class="border-border"
                >
                    <CardContent class="p-4">
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <Skeleton class="h-5 w-32" />
                                <Skeleton class="h-5 w-16 rounded-full" />
                            </div>
                            <Skeleton class="h-4 w-24" />
                            <div class="flex items-center gap-2 pt-2">
                                <Skeleton class="h-8 w-16" />
                                <Skeleton class="h-8 w-20" />
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Empty state -->
            <EmptyState
                v-else-if="prds.length === 0"
                :icon="FileText"
                title="No PRDs yet"
                description="Create one to define what you want to build."
            >
                <template #action>
                    <Button
                        :disabled="serverNotLive"
                        @click="handleNewPrd"
                    >
                        <Plus class="size-4" />
                        Create PRD
                    </Button>
                </template>
            </EmptyState>

            <!-- PRD Cards -->
            <div
                v-else
                class="content-reveal grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
            >
                <TransitionGroup
                    enter-active-class="transition-all duration-[var(--duration-standard)] ease-[var(--ease-gentle)]"
                    enter-from-class="opacity-0 translate-y-2"
                    enter-to-class="opacity-100 translate-y-0"
                >
                    <Card
                        v-for="(prd, index) in prds"
                        :key="prd.id"
                        class="border-border transition-colors duration-[var(--duration-micro)]"
                        :style="{ animationDelay: `${index * 50}ms` }"
                    >
                        <CardContent class="p-4">
                            <div class="space-y-3">
                                <!-- Name + Status -->
                                <div class="flex items-start justify-between gap-2">
                                    <div class="flex items-center gap-2">
                                        <FileText class="size-4 shrink-0 text-muted-foreground" />
                                        <span class="text-sm font-medium">{{ prd.name }}</span>
                                    </div>
                                    <Badge
                                        variant="outline"
                                        class="shrink-0 text-[10px] leading-tight"
                                        :class="statusBadgeClasses(prd.status)"
                                    >
                                        {{ statusLabel(prd.status) }}
                                    </Badge>
                                </div>

                                <!-- Story count -->
                                <p class="text-xs text-muted-foreground">
                                    {{ prd.story_count }} {{ prd.story_count === 1 ? 'story' : 'stories' }}
                                </p>

                                <!-- Action buttons -->
                                <div class="flex items-center gap-2 pt-1">
                                    <TooltipProvider>
                                        <Tooltip>
                                            <TooltipTrigger as-child>
                                                <Button
                                                    size="sm"
                                                    :disabled="serverNotLive"
                                                    @click="handleRun(prd.id)"
                                                >
                                                    <Play class="size-3.5" />
                                                    Run
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent v-if="serverNotLive">
                                                Server offline
                                            </TooltipContent>
                                        </Tooltip>
                                    </TooltipProvider>
                                    <TooltipProvider>
                                        <Tooltip>
                                            <TooltipTrigger as-child>
                                                <Button
                                                    size="sm"
                                                    variant="secondary"
                                                    :disabled="serverNotLive"
                                                    @click="handleRefine(prd.id)"
                                                >
                                                    <Pencil class="size-3.5" />
                                                    Refine
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent v-if="serverNotLive">
                                                Server offline
                                            </TooltipContent>
                                        </Tooltip>
                                    </TooltipProvider>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </TransitionGroup>
            </div>
        </div>
    </ProjectLayout>
</template>
