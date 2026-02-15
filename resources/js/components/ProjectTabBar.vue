<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Code2, Eye, FileText, Play } from 'lucide-vue-next';
import { computed } from 'vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';

const props = defineProps<{
    projectSlug: string;
}>();

const { isCurrentUrl } = useCurrentUrl();

const tabs = computed(() => [
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

function isActive(href: string): boolean {
    return isCurrentUrl(href);
}
</script>

<template>
    <!-- Desktop: horizontal top tab bar -->
    <nav
        class="hidden border-b border-border lg:block"
        aria-label="Project tabs"
    >
        <div class="flex gap-0.5 px-4">
            <Link
                v-for="tab in tabs"
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

    <!-- Mobile: bottom tab bar -->
    <nav
        class="fixed inset-x-0 bottom-0 z-30 border-t border-border bg-background/95 backdrop-blur-sm lg:hidden"
        aria-label="Project tabs"
        style="padding-bottom: env(safe-area-inset-bottom)"
    >
        <div class="flex items-stretch">
            <Link
                v-for="tab in tabs"
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
        </div>
    </nav>
</template>
