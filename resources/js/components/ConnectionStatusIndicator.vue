<script setup lang="ts">
import { computed } from 'vue';
import { useEchoConnectionStatus } from '@/composables/useEcho';

const { connectionState, isConnected, isReconnecting, isDisconnected } =
    useEchoConnectionStatus();

const statusLabel = computed(() => {
    switch (connectionState.value) {
        case 'connected':
            return 'Connected';
        case 'reconnecting':
            return 'Reconnecting...';
        case 'disconnected':
        default:
            return 'Disconnected';
    }
});

const dotClasses = computed(() => {
    switch (connectionState.value) {
        case 'connected':
            return 'bg-success';
        case 'reconnecting':
            return 'bg-warning animate-[pulse-dot_1.5s_ease-in-out_infinite]';
        case 'disconnected':
        default:
            return 'bg-destructive';
    }
});
</script>

<template>
    <div
        v-if="!isConnected"
        class="flex items-center gap-1.5 rounded-md px-2 py-1 text-xs"
        :class="{
            'text-warning': isReconnecting,
            'text-destructive': isDisconnected,
        }"
        role="status"
        :aria-label="statusLabel"
    >
        <span
            class="inline-block size-1.5 shrink-0 rounded-full"
            :class="dotClasses"
        />
        <span>{{ statusLabel }}</span>
    </div>
</template>
