<script setup>
defineProps({
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <div class="flex flex-col items-center justify-center rounded-lg border border-dashed border-border bg-bg-card/50 px-6 py-16 text-center">
        <div v-if="$slots.icon" class="text-text-tertiary">
            <slot name="icon" />
        </div>
        <h2 class="mt-4 text-sm font-medium text-text-heading">{{ title }}</h2>
        <p v-if="description" class="mt-1 max-w-md text-sm text-text-secondary">{{ description }}</p>
        <div v-if="$slots.default" class="mt-6 w-full max-w-2xl">
            <slot />
        </div>
    </div>
</template>
