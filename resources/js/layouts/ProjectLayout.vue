<script setup lang="ts">
import { ref } from 'vue';
import AppContent from '@/components/AppContent.vue';
import AppHeader from '@/components/AppHeader.vue';
import CommandPalette from '@/components/CommandPalette.vue';
import DeviceStatusBanner from '@/components/DeviceStatusBanner.vue';
import KeyboardShortcutsOverlay from '@/components/KeyboardShortcutsOverlay.vue';
import ProjectTabBar from '@/components/ProjectTabBar.vue';
import ToastContainer from '@/components/ui/toast/ToastContainer.vue';
import VersionCompatibilityBanner from '@/components/VersionCompatibilityBanner.vue';
import { useDeviceStatus } from '@/composables/useDeviceStatus';
import { useFlashToasts } from '@/composables/useFlashToasts';
import { useKeyboardShortcuts } from '@/composables/useKeyboardShortcuts';
import { useNetworkStatus } from '@/composables/useNetworkStatus';

useDeviceStatus();
useFlashToasts();
useNetworkStatus();

const props = defineProps<{
    projectSlug: string;
}>();

const showCommandPalette = ref(false);
const showShortcutsOverlay = ref(false);

useKeyboardShortcuts({
    showCommandPalette,
    showShortcutsOverlay,
});
</script>

<template>
    <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-background focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:shadow-lg focus:ring-2 focus:ring-ring">
        Skip to main content
    </a>
    <div class="flex min-h-screen w-full flex-col">
        <AppHeader v-model:show-shortcuts="showShortcutsOverlay" :current-project-slug="props.projectSlug" show-back />
        <DeviceStatusBanner />
        <VersionCompatibilityBanner />
        <ProjectTabBar :project-slug="props.projectSlug" />
        <AppContent class="flex-1 pb-16 lg:pb-0">
            <slot />
        </AppContent>
    </div>

    <CommandPalette v-model:open="showCommandPalette" />
    <KeyboardShortcutsOverlay v-model:open="showShortcutsOverlay" />
    <ToastContainer />
</template>
