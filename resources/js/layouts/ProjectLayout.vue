<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppHeader from '@/components/AppHeader.vue';
import DeviceStatusBanner from '@/components/DeviceStatusBanner.vue';
import ProjectTabBar from '@/components/ProjectTabBar.vue';
import { useDeviceStatus } from '@/composables/useDeviceStatus';

useDeviceStatus();

const props = defineProps<{
    projectSlug: string;
}>();
</script>

<template>
    <div class="flex min-h-screen w-full flex-col">
        <AppHeader :current-project-slug="props.projectSlug" show-back />
        <DeviceStatusBanner />
        <ProjectTabBar :project-slug="props.projectSlug" />
        <AppContent class="flex-1 pb-16 lg:pb-0">
            <slot />
        </AppContent>
    </div>
</template>
