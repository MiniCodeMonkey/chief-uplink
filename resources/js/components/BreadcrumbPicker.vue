<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import ProjectDropdown from '@/components/ProjectDropdown.vue';
import ServerDropdown from '@/components/ServerDropdown.vue';
import type { DeviceSummary, ProjectSummary } from '@/types';

defineProps<{
    currentProjectSlug?: string;
}>();

const page = usePage();
const devices = computed(
    () =>
        (page.props.devices as (DeviceSummary & {
            projects: ProjectSummary[];
        })[]) || [],
);
const sharedSelectedDeviceId = computed(
    () => page.props.selectedDeviceId as number | null,
);

const selectedDeviceId = ref<number | null>(
    sharedSelectedDeviceId.value ?? null,
);

watch(
    sharedSelectedDeviceId,
    (val) => {
        if (val && !selectedDeviceId.value) {
            selectedDeviceId.value = val;
        }
    },
    { immediate: true },
);

// Auto-select first device if none selected
watch(
    devices,
    (devs) => {
        if (!selectedDeviceId.value && devs.length > 0) {
            selectedDeviceId.value = devs[0].id;
        }
    },
    { immediate: true },
);

const selectedDeviceProjects = computed(() => {
    const device = devices.value.find((d) => d.id === selectedDeviceId.value);
    return device?.projects ?? [];
});
</script>

<template>
    <div class="flex items-center gap-1">
        <Link
            href="/"
            prefetch
            class="focus-ring flex shrink-0 items-center gap-2 rounded-md px-1.5 py-1.5 transition-colors duration-[var(--duration-micro)] hover:bg-accent"
            aria-label="Chief home"
        >
            <AppLogoIcon class="size-5" />
            <span class="hidden text-sm font-semibold sm:inline">Chief</span>
        </Link>

        <template v-if="devices.length > 0">
            <ChevronRight class="size-3.5 shrink-0 text-muted-foreground/60" />
            <ServerDropdown v-model="selectedDeviceId" />

            <template
                v-if="selectedDeviceProjects.length > 0 || currentProjectSlug"
            >
                <ChevronRight
                    class="hidden size-3.5 shrink-0 text-muted-foreground/60 lg:block"
                />
                <div class="hidden lg:block">
                    <ProjectDropdown
                        :projects="selectedDeviceProjects"
                        :current-project-slug="currentProjectSlug"
                    />
                </div>
            </template>
        </template>
    </div>
</template>
