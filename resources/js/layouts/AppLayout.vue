<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppHeader from '@/components/AppHeader.vue';
import DeviceStatusBanner from '@/components/DeviceStatusBanner.vue';
import { useDeviceStatus } from '@/composables/useDeviceStatus';

useDeviceStatus();
</script>

<template>
    <div class="flex min-h-screen w-full flex-col">
        <AppHeader />
        <DeviceStatusBanner />
        <AppContent>
            <slot />
        </AppContent>
    </div>
</template>
