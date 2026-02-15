<script setup lang="ts">
import type { SelectContentProps } from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import { SelectContent, SelectPortal, SelectViewport } from 'reka-ui';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<SelectContentProps & { class?: HTMLAttributes['class'] }>(),
    {
        position: 'popper',
    },
);
</script>

<template>
    <SelectPortal>
        <SelectContent
            data-slot="select-content"
            :class="
                cn(
                    'bg-popover text-popover-foreground data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2 relative z-50 max-h-[min(var(--reka-select-content-available-height),24rem)] min-w-[8rem] overflow-hidden rounded-md border shadow-md',
                    props.position === 'popper' &&
                        'data-[side=bottom]:translate-y-1 data-[side=left]:-translate-x-1 data-[side=right]:translate-x-1 data-[side=top]:-translate-y-1',
                    props.class,
                )
            "
            :position="props.position"
            v-bind="$attrs"
        >
            <SelectViewport
                :class="
                    cn(
                        'p-1',
                        props.position === 'popper' &&
                            'h-[var(--reka-select-trigger-height)] w-full min-w-[var(--reka-select-trigger-width)]',
                    )
                "
            >
                <slot />
            </SelectViewport>
        </SelectContent>
    </SelectPortal>
</template>
