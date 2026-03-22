<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import EmptyServers from './EmptyServers.vue';

const props = defineProps({
    servers: {
        type: Array,
        default: () => [],
    },
    isOwner: {
        type: Boolean,
        default: false,
    },
});

const hasServers = computed(() => props.servers.length > 0);

const statusConfig = {
    provisioning: { label: 'Provisioning', class: 'bg-warning/10 text-warning' },
    active: { label: 'Active', class: 'bg-success/10 text-success' },
    stopped: { label: 'Stopped', class: 'bg-text-tertiary/10 text-text-tertiary' },
    failed: { label: 'Error', class: 'bg-error/10 text-error' },
};

function getStatusConfig(status) {
    return statusConfig[status] || { label: status, class: 'bg-text-tertiary/10 text-text-tertiary' };
}

function formatProvider(provider) {
    const map = { hetzner: 'Hetzner', digitalocean: 'DigitalOcean' };
    return map[provider] || provider;
}

function hasOnlineDevice(server) {
    return server.devices.some(d => d.connected);
}
</script>

<template>
    <Head title="Servers" />

    <div class="p-6 md:p-8">
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-2xl font-bold text-text-heading">Servers</h1>
            <Link
                v-if="isOwner"
                href="/servers/create"
                class="rounded-lg bg-brand px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-brand-hover"
            >
                New Server
            </Link>
        </div>

        <EmptyServers v-if="!hasServers" />

        <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="server in servers"
                :key="server.id"
                class="rounded-lg border border-border bg-bg-card p-4 transition-colors hover:border-border-hover"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <h2 class="truncate text-sm font-medium text-text-heading">{{ server.name }}</h2>
                            <span
                                class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="getStatusConfig(server.status).class"
                            >
                                {{ getStatusConfig(server.status).label }}
                            </span>
                        </div>

                        <div class="mt-3 space-y-1.5 text-xs text-text-secondary">
                            <div class="flex items-center gap-2">
                                <span class="w-16 shrink-0 text-text-tertiary">IP</span>
                                <span>{{ server.ip_address || '—' }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="w-16 shrink-0 text-text-tertiary">Provider</span>
                                <span>{{ formatProvider(server.provider) }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="w-16 shrink-0 text-text-tertiary">Region</span>
                                <span>{{ server.region_id }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="w-16 shrink-0 text-text-tertiary">Size</span>
                                <span>{{ server.size_id }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="w-16 shrink-0 text-text-tertiary">Device</span>
                                <span class="flex items-center gap-1.5">
                                    <span
                                        class="h-2 w-2 shrink-0 rounded-full"
                                        :class="hasOnlineDevice(server) ? 'bg-success' : 'bg-error'"
                                    ></span>
                                    <span>{{ hasOnlineDevice(server) ? 'Online' : 'Offline' }}</span>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
