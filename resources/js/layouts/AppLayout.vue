<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue';
import AppContent from '@/components/AppContent.vue';
import AppHeader from '@/components/AppHeader.vue';
import CommandPalette from '@/components/CommandPalette.vue';
import DeviceStatusBanner from '@/components/DeviceStatusBanner.vue';
import { useDeviceStatus } from '@/composables/useDeviceStatus';

useDeviceStatus();

const showCommandPalette = ref(false);

function handleGlobalKeydown(e: KeyboardEvent) {
    if (e.key === 'k' && (e.metaKey || e.ctrlKey)) {
        e.preventDefault();
        showCommandPalette.value = !showCommandPalette.value;
    }
}

onMounted(() => {
    document.addEventListener('keydown', handleGlobalKeydown);
});

onUnmounted(() => {
    document.removeEventListener('keydown', handleGlobalKeydown);
});
</script>

<template>
    <div class="flex min-h-screen w-full flex-col">
        <AppHeader />
        <DeviceStatusBanner />
        <AppContent>
            <slot />
        </AppContent>
    </div>

    <CommandPalette v-model:open="showCommandPalette" />
</template>
