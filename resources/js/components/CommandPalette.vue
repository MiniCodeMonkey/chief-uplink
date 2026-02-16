<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import {
    FileText,
    FolderGit2,
    GitBranch,
    LogOut,
    Play,
    Plus,
    Search,
    Server,
    Settings,
} from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import StatusDot from '@/components/ui/status-dot/StatusDot.vue';
import type { StatusDotState } from '@/components/ui/status-dot/StatusDot.vue';
import type { DeviceSummary, ProjectSummary } from '@/types';

const props = defineProps<{
    open: boolean;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const query = ref('');
const selectedIndex = ref(0);
const inputRef = ref<HTMLInputElement | null>(null);
const listRef = ref<HTMLDivElement | null>(null);

const RECENT_KEY = 'command-palette-recent';
const MAX_RECENT = 5;

function getRecentItems(): string[] {
    try {
        const raw = localStorage.getItem(RECENT_KEY);
        return raw ? JSON.parse(raw) : [];
    } catch {
        return [];
    }
}

function addRecentItem(id: string) {
    const recent = getRecentItems().filter((r) => r !== id);
    recent.unshift(id);
    localStorage.setItem(
        RECENT_KEY,
        JSON.stringify(recent.slice(0, MAX_RECENT)),
    );
}

const page = usePage();

const devices = computed(
    () =>
        (page.props.devices as (DeviceSummary & {
            projects: ProjectSummary[];
        })[]) || [],
);

type CommandItem = {
    id: string;
    label: string;
    category: 'Projects' | 'Servers' | 'Actions';
    icon: typeof Search;
    subtitle?: string;
    shortcut?: string;
    action: () => void;
};

const allItems = computed<CommandItem[]>(() => {
    const items: CommandItem[] = [];

    // Projects
    for (const device of devices.value) {
        for (const project of device.projects) {
            items.push({
                id: `project:${project.project_slug}`,
                label: project.project_name,
                category: 'Projects',
                icon: FolderGit2,
                subtitle: project.git_branch
                    ? `${device.device_name} · ${project.git_branch}`
                    : device.device_name,
                action: () => {
                    router.visit(`/projects/${project.project_slug}`);
                },
            });
        }
    }

    // Servers
    for (const device of devices.value) {
        items.push({
            id: `server:${device.id}`,
            label: device.device_name,
            category: 'Servers',
            icon: Server,
            subtitle: device.connection_status,
            action: () => {
                document.cookie = `selected_device_id=${device.id};path=/;max-age=${60 * 60 * 24 * 365}`;
                router.visit('/dashboard');
            },
        });
    }

    // Actions
    items.push(
        {
            id: 'action:clone',
            label: 'Clone Repository',
            category: 'Actions',
            icon: GitBranch,
            action: () => {
                router.visit('/dashboard');
            },
        },
        {
            id: 'action:create-project',
            label: 'Create Project',
            category: 'Actions',
            icon: Plus,
            action: () => {
                router.visit('/dashboard');
            },
        },
        {
            id: 'action:new-prd',
            label: 'New PRD',
            category: 'Actions',
            icon: FileText,
            action: () => {
                router.visit('/dashboard');
            },
        },
        {
            id: 'action:start-run',
            label: 'Start Run',
            category: 'Actions',
            icon: Play,
            action: () => {
                router.visit('/dashboard');
            },
        },
        {
            id: 'action:settings',
            label: 'Settings',
            category: 'Actions',
            icon: Settings,
            action: () => {
                router.visit('/settings/profile');
            },
        },
        {
            id: 'action:sign-out',
            label: 'Sign Out',
            category: 'Actions',
            icon: LogOut,
            action: () => {
                router.flushAll();
                router.post('/logout');
            },
        },
    );

    return items;
});

function fuzzyMatch(text: string, pattern: string): boolean {
    const lowerText = text.toLowerCase();
    const lowerPattern = pattern.toLowerCase();
    let pi = 0;
    for (let ti = 0; ti < lowerText.length && pi < lowerPattern.length; ti++) {
        if (lowerText[ti] === lowerPattern[pi]) {
            pi++;
        }
    }
    return pi === lowerPattern.length;
}

function getMatchIndices(text: string, pattern: string): number[] {
    const indices: number[] = [];
    const lowerText = text.toLowerCase();
    const lowerPattern = pattern.toLowerCase();
    let pi = 0;
    for (let ti = 0; ti < lowerText.length && pi < lowerPattern.length; ti++) {
        if (lowerText[ti] === lowerPattern[pi]) {
            indices.push(ti);
            pi++;
        }
    }
    return pi === lowerPattern.length ? indices : [];
}

const filteredItems = computed<CommandItem[]>(() => {
    const q = query.value.trim();
    if (!q) {
        // Show recent items first, then the rest
        const recent = getRecentItems();
        const sorted = [...allItems.value].sort((a, b) => {
            const aIndex = recent.indexOf(a.id);
            const bIndex = recent.indexOf(b.id);
            if (aIndex !== -1 && bIndex !== -1) return aIndex - bIndex;
            if (aIndex !== -1) return -1;
            if (bIndex !== -1) return 1;
            return 0;
        });
        return sorted;
    }
    return allItems.value.filter(
        (item) =>
            fuzzyMatch(item.label, q) ||
            (item.subtitle && fuzzyMatch(item.subtitle, q)),
    );
});

type GroupedItems = {
    category: string;
    items: CommandItem[];
};

const groupedItems = computed<GroupedItems[]>(() => {
    const groups = new Map<string, CommandItem[]>();
    for (const item of filteredItems.value) {
        const list = groups.get(item.category);
        if (list) {
            list.push(item);
        } else {
            groups.set(item.category, [item]);
        }
    }
    const result: GroupedItems[] = [];
    const order: string[] = ['Projects', 'Servers', 'Actions'];
    for (const cat of order) {
        const items = groups.get(cat);
        if (items && items.length > 0) {
            result.push({ category: cat, items });
        }
    }
    return result;
});

const flatItems = computed(() => {
    return groupedItems.value.flatMap((g) => g.items);
});

function close() {
    emit('update:open', false);
}

function selectItem(item: CommandItem) {
    addRecentItem(item.id);
    close();
    item.action();
}

function handleKeydown(e: KeyboardEvent) {
    if (!props.open) return;

    switch (e.key) {
        case 'ArrowDown':
            e.preventDefault();
            selectedIndex.value = Math.min(
                selectedIndex.value + 1,
                flatItems.value.length - 1,
            );
            scrollSelectedIntoView();
            break;
        case 'ArrowUp':
            e.preventDefault();
            selectedIndex.value = Math.max(selectedIndex.value - 1, 0);
            scrollSelectedIntoView();
            break;
        case 'Enter':
            e.preventDefault();
            if (flatItems.value[selectedIndex.value]) {
                selectItem(flatItems.value[selectedIndex.value]);
            }
            break;
        case 'Escape':
            e.preventDefault();
            close();
            break;
    }
}

function scrollSelectedIntoView() {
    nextTick(() => {
        const el = listRef.value?.querySelector(
            `[data-index="${selectedIndex.value}"]`,
        );
        el?.scrollIntoView({ block: 'nearest' });
    });
}

// Reset state when opening
watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            query.value = '';
            selectedIndex.value = 0;
            nextTick(() => {
                inputRef.value?.focus();
            });
        }
    },
);

// Reset selection when query changes
watch(query, () => {
    selectedIndex.value = 0;
});

onMounted(() => {
    document.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
    document.removeEventListener('keydown', handleKeydown);
});

function getItemIndex(item: CommandItem): number {
    return flatItems.value.indexOf(item);
}

function toStatusDotState(status: string): StatusDotState {
    return status as StatusDotState;
}

function getConnectionStatusLabel(status: string): string {
    switch (status) {
        case 'online':
            return 'Online';
        case 'reconnecting':
            return 'Reconnecting';
        case 'offline':
            return 'Offline';
        case 'never-connected':
            return 'Never connected';
        default:
            return status;
    }
}
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-[var(--duration-standard)] ease-[var(--ease-snappy)]"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition duration-[var(--duration-micro)] ease-[var(--ease-snappy)]"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div
                v-if="props.open"
                class="fixed inset-0 z-50 flex items-start justify-center pt-[15vh]"
                role="dialog"
                aria-label="Command palette"
                aria-modal="true"
            >
                <!-- Backdrop -->
                <div
                    class="absolute inset-0 bg-background/80 backdrop-blur-sm"
                    @click="close"
                />

                <!-- Palette -->
                <div
                    class="relative mx-4 flex w-full max-w-lg flex-col overflow-hidden rounded-xl border border-border bg-card shadow-2xl"
                    style="max-height: 60vh"
                >
                    <!-- Search input -->
                    <div
                        class="flex items-center gap-3 border-b border-border px-4"
                    >
                        <Search
                            class="size-4 shrink-0 text-muted-foreground"
                        />
                        <input
                            ref="inputRef"
                            v-model="query"
                            type="text"
                            placeholder="Search projects, servers, actions..."
                            class="h-12 w-full bg-transparent text-sm text-foreground placeholder:text-muted-foreground focus:outline-none"
                            autocomplete="off"
                            spellcheck="false"
                        />
                        <kbd
                            class="hidden shrink-0 rounded border border-border bg-muted px-1.5 py-0.5 font-mono text-[10px] text-muted-foreground sm:inline-flex"
                        >
                            Esc
                        </kbd>
                    </div>

                    <!-- Results -->
                    <div
                        ref="listRef"
                        class="overflow-y-auto overscroll-contain p-2"
                        role="listbox"
                        aria-label="Search results"
                    >
                        <template v-if="flatItems.length > 0">
                            <div
                                v-for="group in groupedItems"
                                :key="group.category"
                            >
                                <div
                                    class="px-2 py-1.5 text-xs font-medium text-muted-foreground"
                                >
                                    {{ group.category }}
                                </div>
                                <div
                                    v-for="item in group.items"
                                    :key="item.id"
                                    :data-index="getItemIndex(item)"
                                    role="option"
                                    :aria-selected="
                                        selectedIndex === getItemIndex(item)
                                    "
                                    class="flex cursor-pointer items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition-colors duration-[var(--duration-micro)]"
                                    :class="
                                        selectedIndex === getItemIndex(item)
                                            ? 'bg-accent text-accent-foreground'
                                            : 'text-foreground hover:bg-accent/50'
                                    "
                                    @click="selectItem(item)"
                                    @mouseenter="
                                        selectedIndex = getItemIndex(item)
                                    "
                                >
                                    <component
                                        :is="item.icon"
                                        class="size-4 shrink-0 text-muted-foreground"
                                    />
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2">
                                            <!-- Label with match highlighting -->
                                            <span
                                                v-if="query.trim()"
                                                class="truncate"
                                            >
                                                <template
                                                    v-for="(
                                                        char, ci
                                                    ) in item.label.split('')"
                                                    :key="ci"
                                                >
                                                    <span
                                                        v-if="
                                                            getMatchIndices(
                                                                item.label,
                                                                query.trim(),
                                                            ).includes(ci)
                                                        "
                                                        class="text-primary font-semibold"
                                                        >{{ char }}</span
                                                    >
                                                    <span v-else>{{
                                                        char
                                                    }}</span>
                                                </template>
                                            </span>
                                            <span v-else class="truncate">{{
                                                item.label
                                            }}</span>
                                            <span
                                                class="shrink-0 rounded-md border border-border px-1.5 py-0.5 text-[10px] text-muted-foreground"
                                            >
                                                {{ item.category }}
                                            </span>
                                        </div>
                                        <div
                                            v-if="item.subtitle"
                                            class="mt-0.5 flex items-center gap-1.5 text-xs text-muted-foreground"
                                        >
                                            <template
                                                v-if="
                                                    item.category ===
                                                        'Servers' &&
                                                    item.subtitle
                                                "
                                            >
                                                <StatusDot
                                                    :state="
                                                        toStatusDotState(
                                                            item.subtitle,
                                                        )
                                                    "
                                                />
                                                <span>{{
                                                    getConnectionStatusLabel(
                                                        item.subtitle,
                                                    )
                                                }}</span>
                                            </template>
                                            <template v-else>
                                                {{ item.subtitle }}
                                            </template>
                                        </div>
                                    </div>
                                    <kbd
                                        v-if="item.shortcut"
                                        class="hidden shrink-0 rounded border border-border bg-muted px-1.5 py-0.5 font-mono text-[10px] text-muted-foreground sm:inline-flex"
                                    >
                                        {{ item.shortcut }}
                                    </kbd>
                                </div>
                            </div>
                        </template>
                        <div
                            v-else
                            class="px-3 py-8 text-center text-sm text-muted-foreground"
                        >
                            No results found
                        </div>
                    </div>

                    <!-- Footer -->
                    <div
                        class="flex items-center gap-4 border-t border-border px-4 py-2 text-xs text-muted-foreground"
                    >
                        <span class="flex items-center gap-1">
                            <kbd
                                class="rounded border border-border bg-muted px-1 py-0.5 font-mono text-[10px]"
                                >↑↓</kbd
                            >
                            navigate
                        </span>
                        <span class="flex items-center gap-1">
                            <kbd
                                class="rounded border border-border bg-muted px-1 py-0.5 font-mono text-[10px]"
                                >↵</kbd
                            >
                            select
                        </span>
                        <span class="flex items-center gap-1">
                            <kbd
                                class="rounded border border-border bg-muted px-1 py-0.5 font-mono text-[10px]"
                                >esc</kbd
                            >
                            close
                        </span>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
