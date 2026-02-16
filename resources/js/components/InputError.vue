<script setup lang="ts">
defineProps<{
    message?: string;
}>();
</script>

<template>
    <Transition
        enter-active-class="transition-all duration-[var(--duration-micro)] ease-[var(--ease-snappy)]"
        leave-active-class="transition-all duration-[var(--duration-micro)] ease-[var(--ease-snappy)]"
        enter-from-class="opacity-0 -translate-y-1"
        leave-to-class="opacity-0 -translate-y-1"
    >
        <p v-if="message" class="mt-1 text-sm text-destructive" role="alert">
            {{ message }}
        </p>
    </Transition>
</template>
