<script setup lang="ts">
import type { SelectTriggerProps } from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import { ChevronDown } from 'lucide-vue-next';
import { SelectIcon, SelectTrigger } from 'reka-ui';
import { cn } from '@/lib/utils';

const props = defineProps<
    SelectTriggerProps & { class?: HTMLAttributes['class'] }
>();
</script>

<template>
    <SelectTrigger
        data-slot="select-trigger"
        :class="
            cn(
                'border-input data-[placeholder]:text-muted-foreground [&_svg:not([class*=text-])]:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive dark:bg-input/30 dark:hover:bg-input/50 flex h-9 w-full items-center justify-between gap-2 rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 *:data-[slot=select-value]:line-clamp-1 *:data-[slot=select-value]:flex *:data-[slot=select-value]:items-center *:data-[slot=select-value]:gap-2',
                props.class,
            )
        "
    >
        <slot />
        <SelectIcon as-child>
            <ChevronDown class="size-4 shrink-0 opacity-50" />
        </SelectIcon>
    </SelectTrigger>
</template>
