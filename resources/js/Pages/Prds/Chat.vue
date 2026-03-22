<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({
    prd: {
        type: Object,
        required: true,
    },
    project: {
        type: Object,
        default: null,
    },
    chatHistory: {
        type: Array,
        default: () => [],
    },
    device: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <Head :title="`Chat — ${prd.title}`" />

    <div class="p-6 md:p-8">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-text-heading">{{ prd.title }}</h1>
            <p v-if="project" class="mt-1 text-sm text-text-secondary">{{ project.name }}</p>
        </div>

        <div class="rounded-lg border border-border bg-bg-card p-4">
            <div v-if="chatHistory.length === 0" class="py-12 text-center text-text-tertiary">
                No chat history yet.
            </div>

            <div v-else class="space-y-4">
                <div
                    v-for="(message, index) in chatHistory"
                    :key="index"
                    class="text-sm"
                    :class="message.role === 'user' ? 'text-text-heading' : 'text-text-secondary'"
                >
                    <span class="font-medium">{{ message.role === 'user' ? 'You' : 'Assistant' }}:</span>
                    <span class="ml-2">{{ message.content }}</span>
                </div>
            </div>
        </div>
    </div>
</template>
