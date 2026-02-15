<script setup lang="ts">
import { useToast } from '@/composables/useToast';
import Toast from './Toast.vue';

const { toasts, dismiss } = useToast();
</script>

<template>
    <Teleport to="body">
        <div
            class="pointer-events-none fixed top-4 right-4 z-[100] flex max-w-sm flex-col gap-2 sm:right-4 max-sm:left-4 max-sm:right-4"
            aria-label="Notifications"
        >
            <TransitionGroup
                enter-active-class="transition-all duration-200"
                leave-active-class="transition-all duration-200"
                enter-from-class="translate-y-2 opacity-0"
                leave-to-class="translate-x-4 opacity-0"
            >
                <Toast
                    v-for="toast in toasts"
                    :key="toast.id"
                    v-bind="toast"
                    @dismiss="dismiss"
                />
            </TransitionGroup>
        </div>
    </Teleport>
</template>
