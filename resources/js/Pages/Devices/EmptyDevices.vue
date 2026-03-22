<script setup>
import { Link } from '@inertiajs/vue3';
import EmptyState from '../../Components/EmptyState.vue';
</script>

<template>
    <EmptyState
        title="Connect your first Chief server"
        description="Run Chief on your local machine or launch a cloud server to get started."
    >
        <template #icon>
            <svg class="h-12 w-12" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <rect width="14" height="8" x="5" y="2" rx="2" />
                <rect width="20" height="8" x="2" y="14" rx="2" />
                <path d="M6 18h2" /><path d="M12 18h6" />
            </svg>
        </template>

        <div class="grid gap-4 sm:grid-cols-2">
            <!-- Run locally card -->
            <div class="rounded-lg border border-border bg-bg-card p-5 text-left">
                <h3 class="text-sm font-medium text-text-heading">Run locally</h3>
                <p class="mt-1 text-xs text-text-secondary">Install Chief on your machine and connect it to Uplink.</p>
                <div class="mt-4 space-y-2">
                    <div class="flex items-center rounded-md bg-bg-surface px-3 py-2">
                        <code class="text-xs font-mono text-text">chief login</code>
                    </div>
                    <div class="flex items-center rounded-md bg-bg-surface px-3 py-2">
                        <code class="text-xs font-mono text-text">chief serve</code>
                    </div>
                </div>
            </div>

            <!-- Launch cloud server card -->
            <Link
                href="/servers/create"
                class="block rounded-lg border border-border bg-bg-card p-5 text-left transition-colors hover:border-border-hover"
            >
                <h3 class="text-sm font-medium text-text-heading">Launch cloud server</h3>
                <p class="mt-1 text-xs text-text-secondary">Provision a managed server on Hetzner or DigitalOcean.</p>
                <div class="mt-4">
                    <span class="inline-flex items-center gap-1.5 rounded-md bg-brand/10 px-3 py-1.5 text-xs font-medium text-brand">
                        Get started
                        <svg class="h-3 w-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M5 12h14" /><path d="m12 5 7 7-7 7" />
                        </svg>
                    </span>
                </div>
            </Link>
        </div>
    </EmptyState>
</template>
