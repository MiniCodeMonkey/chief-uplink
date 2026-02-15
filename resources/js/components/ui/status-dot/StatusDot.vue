<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { computed } from 'vue';
import { cn } from '@/lib/utils';

export type StatusDotState = 'online' | 'reconnecting' | 'offline' | 'never-connected';

const props = withDefaults(
    defineProps<{
        state: StatusDotState;
        class?: HTMLAttributes['class'];
    }>(),
    {},
);

const stateClasses = computed(() => {
    switch (props.state) {
        case 'online':
            return 'bg-success';
        case 'reconnecting':
            return 'bg-warning animate-[pulse-dot_1.5s_ease-in-out_infinite]';
        case 'offline':
            return 'bg-muted-foreground/50';
        case 'never-connected':
            return 'bg-transparent border-2 border-muted-foreground/50';
        default:
            return 'bg-muted-foreground/50';
    }
});

const stateLabel = computed(() => {
    switch (props.state) {
        case 'online':
            return 'Online';
        case 'reconnecting':
            return 'Reconnecting';
        case 'offline':
            return 'Offline';
        case 'never-connected':
            return 'Never connected';
        default:
            return 'Unknown';
    }
});
</script>

<template>
    <span
        data-slot="status-dot"
        role="status"
        :aria-label="stateLabel"
        :class="cn('status-dot', stateClasses, props.class)"
    >
        <span class="sr-only">{{ stateLabel }}</span>
    </span>
</template>
