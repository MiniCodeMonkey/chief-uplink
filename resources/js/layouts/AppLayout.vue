<script setup lang="ts">
import { ref } from 'vue';
import AppContent from '@/components/AppContent.vue';
import AppHeader from '@/components/AppHeader.vue';
import CommandPalette from '@/components/CommandPalette.vue';
import DeviceStatusBanner from '@/components/DeviceStatusBanner.vue';
import KeyboardShortcutsOverlay from '@/components/KeyboardShortcutsOverlay.vue';
import { useDeviceStatus } from '@/composables/useDeviceStatus';
import { useKeyboardShortcuts } from '@/composables/useKeyboardShortcuts';

useDeviceStatus();

const showCommandPalette = ref(false);
const showShortcutsOverlay = ref(false);

useKeyboardShortcuts({
    showCommandPalette,
    showShortcutsOverlay,
});
</script>

<template>
    <div class="flex min-h-screen w-full flex-col">
        <AppHeader v-model:show-shortcuts="showShortcutsOverlay" />
        <DeviceStatusBanner />
        <AppContent>
            <slot />
        </AppContent>
    </div>

    <CommandPalette v-model:open="showCommandPalette" />
    <KeyboardShortcutsOverlay v-model:open="showShortcutsOverlay" />
</template>
