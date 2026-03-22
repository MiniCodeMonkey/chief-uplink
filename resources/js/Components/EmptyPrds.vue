<script setup>
import EmptyState from './EmptyState.vue';
</script>

<template>
    <EmptyState
        title="Create your first PRD"
        description="Product requirements documents help Chief understand what to build. Start with a conversation or write one from scratch."
    >
        <template #icon>
            <svg class="h-12 w-12" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z" />
                <path d="M14 2v6h6" />
                <path d="M16 13H8" />
                <path d="M16 17H8" />
                <path d="M10 9H8" />
            </svg>
        </template>

        <div class="grid gap-4 sm:grid-cols-2">
            <!-- Chat with Claude card -->
            <div class="rounded-lg border border-border bg-bg-card p-5 text-left">
                <h3 class="text-sm font-medium text-text-heading">Chat with Claude</h3>
                <p class="mt-1 text-xs text-text-secondary">Describe your idea and let Claude help you turn it into a structured PRD.</p>
                <div class="mt-4">
                    <span class="inline-flex items-center gap-1.5 rounded-md bg-brand/10 px-3 py-1.5 text-xs font-medium text-brand">
                        Start conversation
                        <svg class="h-3 w-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M5 12h14" /><path d="m12 5 7 7-7 7" />
                        </svg>
                    </span>
                </div>
            </div>

            <!-- Write it yourself card -->
            <div class="rounded-lg border border-border bg-bg-card p-5 text-left">
                <h3 class="text-sm font-medium text-text-heading">Write it yourself</h3>
                <p class="mt-1 text-xs text-text-secondary">Create a blank PRD and write your requirements from scratch in Markdown.</p>
                <div class="mt-4">
                    <span class="inline-flex items-center gap-1.5 rounded-md bg-bg-surface px-3 py-1.5 text-xs font-medium text-text-secondary">
                        Blank PRD
                        <svg class="h-3 w-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 5v14" /><path d="M5 12h14" />
                        </svg>
                    </span>
                </div>
            </div>
        </div>
    </EmptyState>
</template>
