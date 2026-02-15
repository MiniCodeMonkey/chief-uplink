<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ChevronDown, FolderOpen } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import type { ProjectSummary } from '@/types';

const props = defineProps<{
    projects: ProjectSummary[];
    currentProjectSlug?: string;
}>();

const open = ref(false);
const triggerRef = ref<HTMLButtonElement | null>(null);

const currentProject = computed(() => {
    if (!props.currentProjectSlug) return null;
    return (
        props.projects.find(
            (p) => p.project_slug === props.currentProjectSlug,
        ) ?? null
    );
});

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

function selectProject(project: ProjectSummary) {
    open.value = false;
    router.visit(`/projects/${project.project_slug}`);
    triggerRef.value?.focus();
}

function handleKeydown(event: KeyboardEvent) {
    if (event.key === 'Escape') {
        open.value = false;
        triggerRef.value?.focus();
    }
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        const items = Array.from(
            document.querySelectorAll('[data-project-item]'),
        ) as HTMLElement[];
        const currentIndex = items.findIndex(
            (el) => el === document.activeElement,
        );
        let nextIndex: number;
        if (event.key === 'ArrowDown') {
            nextIndex = currentIndex < items.length - 1 ? currentIndex + 1 : 0;
        } else {
            nextIndex = currentIndex > 0 ? currentIndex - 1 : items.length - 1;
        }
        items[nextIndex]?.focus();
    }
}
</script>

<template>
    <div class="relative">
        <button
            ref="triggerRef"
            class="focus-ring flex items-center gap-1.5 rounded-md px-2 py-1.5 text-sm font-medium transition-colors duration-[var(--duration-micro)] hover:bg-accent"
            :aria-expanded="open"
            aria-haspopup="listbox"
            @click="open = !open"
            @keydown.escape="open = false"
        >
            <FolderOpen class="size-4 shrink-0 text-muted-foreground" />
            <template v-if="currentProject">
                <span class="max-w-[140px] truncate lg:max-w-[180px]">{{
                    currentProject.project_name
                }}</span>
            </template>
            <span v-else class="text-muted-foreground">Select project</span>
            <ChevronDown
                class="size-3.5 text-muted-foreground transition-transform duration-[var(--duration-micro)]"
                :class="{ 'rotate-180': open }"
            />
        </button>

        <Teleport to="body">
            <div v-if="open" class="fixed inset-0 z-40" @click="open = false" />
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
                v-if="open"
                role="listbox"
                aria-label="Select project"
                class="absolute top-full left-0 z-50 mt-1 min-w-[240px] overflow-hidden rounded-lg border border-border bg-popover p-1 shadow-md"
                @keydown="handleKeydown"
            >
                <div
                    v-if="projects.length === 0"
                    class="px-3 py-4 text-center text-sm text-muted-foreground"
                >
                    No projects on this server.
                </div>
                <button
                    v-for="project in projects"
                    :key="project.id"
                    data-project-item
                    role="option"
                    :aria-selected="
                        currentProject?.project_slug === project.project_slug
                    "
                    class="focus-ring flex w-full items-center gap-2.5 rounded-md px-2.5 py-2 text-left text-sm transition-colors duration-[var(--duration-micro)] hover:bg-accent"
                    :class="{
                        'bg-accent':
                            currentProject?.project_slug ===
                            project.project_slug,
                    }"
                    @click="selectProject(project)"
                    @keydown.enter.prevent="selectProject(project)"
                >
                    <div class="flex flex-1 flex-col gap-0.5">
                        <div class="flex items-center gap-2">
                            <span class="font-medium">{{
                                project.project_name
                            }}</span>
                            <Badge
                                variant="outline"
                                class="text-[10px] leading-tight"
                                :class="statusColor(project.status)"
                            >
                                {{ project.status }}
                            </Badge>
                        </div>
                        <span
                            v-if="project.git_branch"
                            class="font-mono text-xs text-muted-foreground"
                        >
                            {{ project.git_branch }}
                        </span>
                    </div>
                    <span
                        v-if="
                            project.status === 'running' &&
                            project.stories_total
                        "
                        class="text-xs text-muted-foreground"
                    >
                        {{ project.stories_completed ?? 0 }}/{{
                            project.stories_total
                        }}
                    </span>
                </button>
            </div>
        </Transition>
    </div>
</template>
