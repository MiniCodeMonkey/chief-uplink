<script setup>
import { Link } from '@inertiajs/vue3';
import EmptyState from '../../Components/EmptyState.vue';
</script>

<template>
    <EmptyState
        title="Launch a Chief server in the cloud"
        description="Provision a managed server with your preferred cloud provider."
    >
        <template #icon>
            <svg class="h-12 w-12" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <rect width="20" height="8" x="2" y="2" rx="2" ry="2" />
                <rect width="20" height="8" x="2" y="14" rx="2" ry="2" />
                <line x1="6" y1="6" x2="6.01" y2="6" />
                <line x1="6" y1="18" x2="6.01" y2="18" />
            </svg>
        </template>

        <div class="grid gap-4 sm:grid-cols-2">
            <!-- Hetzner card -->
            <Link
                href="/servers/create"
                class="block rounded-lg border border-border bg-bg-card p-5 text-left transition-colors hover:border-border-hover"
            >
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-bg-surface">
                        <svg class="h-5 w-5 text-text-heading" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M0 0h24v24H0V0zm11.03 20.03H5.59V9.3h5.44v10.73zm7.42 0H13V3.97h5.44v16.06z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-medium text-text-heading">Hetzner</h3>
                        <p class="text-xs text-text-secondary">European cloud hosting</p>
                    </div>
                </div>
            </Link>

            <!-- DigitalOcean card -->
            <Link
                href="/servers/create"
                class="block rounded-lg border border-border bg-bg-card p-5 text-left transition-colors hover:border-border-hover"
            >
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-bg-surface">
                        <svg class="h-5 w-5 text-text-heading" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 4.17 2.58 7.73 6.23 9.18v-4.69h-2.5v-4.5h2.5V9.75c0-2.47 1.49-3.84 3.76-3.84 1.09 0 2.23.19 2.23.19v2.45h-1.26c-1.24 0-1.62.77-1.62 1.56v1.87h2.77l-.44 4.5h-2.33v4.69c3.65-1.45 6.23-5.01 6.23-9.18C21.87 6.45 17.5 2 12.04 2z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-medium text-text-heading">DigitalOcean</h3>
                        <p class="text-xs text-text-secondary">Simple cloud infrastructure</p>
                    </div>
                </div>
            </Link>
        </div>
    </EmptyState>
</template>
