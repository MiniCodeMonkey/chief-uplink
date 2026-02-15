<script setup lang="ts">
import { StatusDot } from '@/components/ui/status-dot';
import { useConnectionStatus } from '@/composables/useConnectionStatus';

const { selectedDevice, connectionStatus, isOnline, statusText } =
    useConnectionStatus();
</script>

<template>
    <Transition
        enter-active-class="transition duration-[var(--duration-standard)] ease-[var(--ease-gentle)]"
        enter-from-class="opacity-0 -translate-y-1"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition duration-[var(--duration-standard)] ease-[var(--ease-gentle)]"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 -translate-y-1"
    >
        <div
            v-if="selectedDevice && !isOnline && connectionStatus"
            class="flex items-center gap-2 border-b border-border px-4 py-1.5 text-xs"
            :class="{
                'bg-warning/10 text-warning':
                    connectionStatus === 'reconnecting',
                'bg-muted/50 text-muted-foreground':
                    connectionStatus === 'offline' ||
                    connectionStatus === 'never-connected',
            }"
            role="status"
            :aria-label="statusText ?? undefined"
        >
            <StatusDot :state="connectionStatus" class="size-2" />
            <span>{{ statusText }}</span>
        </div>
    </Transition>
</template>
