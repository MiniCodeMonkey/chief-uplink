<script setup lang="ts">
import type { Component, HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

defineProps<{
    icon?: Component;
    title: string;
    description?: string;
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <div
        data-slot="empty-state"
        :class="
            cn(
                'flex flex-col items-center justify-center gap-4 py-12 text-center',
                $props.class,
            )
        "
    >
        <div
            v-if="icon"
            class="bg-muted text-muted-foreground flex size-12 items-center justify-center rounded-full"
        >
            <component :is="icon" class="size-6" />
        </div>
        <div class="space-y-1.5">
            <h3 class="text-foreground text-sm font-medium">{{ title }}</h3>
            <p v-if="description" class="text-muted-foreground text-sm">
                {{ description }}
            </p>
        </div>
        <div v-if="$slots.action" class="mt-2">
            <slot name="action" />
        </div>
    </div>
</template>
