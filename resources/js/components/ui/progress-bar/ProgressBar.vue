<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { computed } from 'vue';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        value?: number;
        max?: number;
        class?: HTMLAttributes['class'];
        indicatorClass?: HTMLAttributes['class'];
    }>(),
    {
        value: 0,
        max: 100,
    },
);

const percentage = computed(() =>
    Math.min(100, Math.max(0, (props.value / props.max) * 100)),
);
</script>

<template>
    <div
        role="progressbar"
        data-slot="progress-bar"
        :aria-valuenow="value"
        :aria-valuemin="0"
        :aria-valuemax="max"
        :class="
            cn(
                'bg-primary/20 relative h-2 w-full overflow-hidden rounded-full',
                props.class,
            )
        "
    >
        <div
            data-slot="progress-bar-indicator"
            :class="
                cn(
                    'bg-primary h-full rounded-full transition-all',
                    indicatorClass,
                )
            "
            :style="{ width: `${percentage}%` }"
        />
    </div>
</template>
