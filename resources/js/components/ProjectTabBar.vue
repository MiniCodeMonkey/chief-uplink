<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Code2, EllipsisVertical, Eye, FileText, Play, Settings } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';

const props = defineProps<{
    projectSlug: string;
}>();

const { isCurrentUrl } = useCurrentUrl();

const primaryTabs = computed(() => [
    {
        title: 'Overview',
        href: `/projects/${props.projectSlug}`,
        icon: Eye,
        slug: 'overview',
    },
    {
        title: 'Run',
        href: `/projects/${props.projectSlug}/run`,
        icon: Play,
        slug: 'run',
    },
    {
        title: 'Diffs',
        href: `/projects/${props.projectSlug}/diffs`,
        icon: Code2,
        slug: 'diffs',
    },
    {
        title: 'PRDs',
        href: `/projects/${props.projectSlug}/prds`,
        icon: FileText,
        slug: 'prds',
    },
]);

const settingsTab = computed(() => ({
    title: 'Settings',
    href: `/projects/${props.projectSlug}/settings`,
    icon: Settings,
    slug: 'settings',
}));

const allTabs = computed(() => [...primaryTabs.value, settingsTab.value]);

function isActive(href: string): boolean {
    return isCurrentUrl(href);
}

const isSettingsActive = computed(() => isActive(settingsTab.value.href));

// Mobile overflow menu
const overflowOpen = ref(false);

function handleOverflowItemClick(href: string) {
    overflowOpen.value = false;
    router.visit(href);
}

function handleOverflowToggle() {
    overflowOpen.value = !overflowOpen.value;
}

function handleOverflowBackdropClick() {
    overflowOpen.value = false;
}
</script>

<template>
    <!-- Desktop: horizontal top tab bar with all tabs including Settings -->
    <nav
        class="hidden border-b border-border lg:block"
        aria-label="Project tabs"
    >
        <div class="flex gap-0.5 px-4">
            <Link
                v-for="tab in allTabs"
                :key="tab.slug"
                :href="tab.href"
                class="focus-ring relative flex items-center gap-1.5 px-3 py-2.5 text-sm font-medium transition-colors duration-[var(--duration-micro)] hover:text-foreground"
                :class="
                    isActive(tab.href)
                        ? 'text-foreground'
                        : 'text-muted-foreground'
                "
                :aria-current="isActive(tab.href) ? 'page' : undefined"
            >
                <component :is="tab.icon" class="size-4" />
                {{ tab.title }}
                <span
                    v-if="isActive(tab.href)"
                    class="absolute bottom-0 left-0 h-0.5 w-full bg-primary"
                />
            </Link>
        </div>
    </nav>

    <!-- Mobile: bottom tab bar with overflow -->
    <nav
        class="fixed inset-x-0 bottom-0 z-30 border-t border-border bg-background/95 backdrop-blur-sm lg:hidden"
        aria-label="Project tabs"
        style="padding-bottom: env(safe-area-inset-bottom)"
    >
        <div class="flex items-stretch">
            <Link
                v-for="tab in primaryTabs"
                :key="tab.slug"
                :href="tab.href"
                class="focus-ring flex flex-1 flex-col items-center justify-center gap-0.5 py-2 text-[11px] font-medium transition-colors duration-[var(--duration-micro)]"
                :class="
                    isActive(tab.href)
                        ? 'text-primary'
                        : 'text-muted-foreground'
                "
                :aria-current="isActive(tab.href) ? 'page' : undefined"
                style="min-height: 44px"
            >
                <component :is="tab.icon" class="size-5" />
                {{ tab.title }}
            </Link>

            <!-- Overflow menu trigger (three-dot) -->
            <div class="relative flex flex-1 items-stretch">
                <button
                    class="focus-ring flex flex-1 flex-col items-center justify-center gap-0.5 py-2 text-[11px] font-medium transition-colors duration-[var(--duration-micro)]"
                    :class="
                        isSettingsActive
                            ? 'text-primary'
                            : 'text-muted-foreground'
                    "
                    style="min-height: 44px"
                    :aria-expanded="overflowOpen"
                    aria-haspopup="menu"
                    aria-label="More options"
                    @click="handleOverflowToggle"
                >
                    <EllipsisVertical class="size-5" />
                    <span>More</span>
                </button>

                <!-- Overflow menu backdrop -->
                <Transition
                    enter-active-class="transition-opacity duration-[var(--duration-standard)]"
                    enter-from-class="opacity-0"
                    enter-to-class="opacity-100"
                    leave-active-class="transition-opacity duration-[var(--duration-micro)]"
                    leave-from-class="opacity-100"
                    leave-to-class="opacity-0"
                >
                    <div
                        v-if="overflowOpen"
                        class="fixed inset-0 z-40"
                        @click="handleOverflowBackdropClick"
                    />
                </Transition>

                <!-- Overflow menu popup -->
                <Transition
                    enter-active-class="transition-all duration-[var(--duration-standard)] ease-[var(--ease-snappy)]"
                    enter-from-class="translate-y-2 scale-95 opacity-0"
                    enter-to-class="translate-y-0 scale-100 opacity-100"
                    leave-active-class="transition-all duration-[var(--duration-micro)] ease-[var(--ease-snappy)]"
                    leave-from-class="translate-y-0 scale-100 opacity-100"
                    leave-to-class="translate-y-2 scale-95 opacity-0"
                >
                    <div
                        v-if="overflowOpen"
                        class="absolute bottom-full right-0 z-50 mb-2 min-w-[160px] rounded-lg border border-border bg-background p-1 shadow-lg"
                        role="menu"
                    >
                        <button
                            class="focus-ring flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition-colors duration-[var(--duration-micro)] hover:bg-accent"
                            :class="
                                isSettingsActive
                                    ? 'text-primary'
                                    : 'text-foreground'
                            "
                            role="menuitem"
                            @click="handleOverflowItemClick(settingsTab.href)"
                        >
                            <Settings class="size-4" />
                            Settings
                        </button>
                    </div>
                </Transition>
            </div>
        </div>
    </nav>
</template>
