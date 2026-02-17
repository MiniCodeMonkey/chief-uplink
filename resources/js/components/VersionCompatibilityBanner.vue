<script setup lang="ts">
import { AlertTriangle, X } from 'lucide-vue-next';
import { useVersionCompatibility, formatVersion } from '@/composables/useVersionCompatibility';

const { showWarning, deviceVersion, dismiss } =
    useVersionCompatibility();
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
            v-if="showWarning"
            class="flex items-center gap-2 border-b border-warning/20 bg-warning/10 px-4 py-1.5 text-xs text-warning"
            role="status"
            aria-label="Version compatibility warning"
        >
            <AlertTriangle class="size-3.5 shrink-0" />
            <span class="flex-1">
                This server is running Chief v{{ deviceVersion ? formatVersion(deviceVersion) : 'unknown' }}. Some features may not work.
                <a
                    href="/docs"
                    class="underline underline-offset-2 hover:text-warning/80"
                >
                    Update instructions
                </a>
            </span>
            <button
                class="shrink-0 rounded p-0.5 hover:bg-warning/20"
                aria-label="Dismiss version warning"
                @click="dismiss"
            >
                <X class="size-3.5" />
            </button>
        </div>
    </Transition>
</template>
