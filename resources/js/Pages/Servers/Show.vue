<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    server: Object,
    isOwner: Boolean,
    hasGitHubToken: Boolean,
});

const page = usePage();
const addingToGitHub = ref(false);
const copied = ref(false);

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

function addToGitHub() {
    if (!props.server.ssh_key) return;
    addingToGitHub.value = true;
    router.post(`/servers/${props.server.id}/deploy-key`, {}, {
        preserveScroll: true,
        onFinish: () => {
            addingToGitHub.value = false;
        },
    });
}

function copyPublicKey() {
    if (!props.server.ssh_key) return;
    navigator.clipboard.writeText(props.server.ssh_key.public_key);
    copied.value = true;
    setTimeout(() => { copied.value = false; }, 2000);
}

const flash = computed(() => page.props.flash);
</script>

<template>
    <Head :title="server.name" />

    <div class="p-6 md:p-8 max-w-3xl">
        <!-- Flash messages -->
        <div v-if="flash.success" class="mb-4 rounded-md border border-success/30 bg-success/10 px-4 py-3 text-sm text-success">
            {{ flash.success }}
        </div>
        <div v-if="flash.error" class="mb-4 rounded-md border border-error/30 bg-error/10 px-4 py-3 text-sm text-error">
            {{ flash.error }}
        </div>

        <!-- Header -->
        <div class="mb-6 flex items-center gap-3">
            <Link href="/servers" class="text-text-tertiary hover:text-text transition-colors">
                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
            </Link>
            <div>
                <h1 class="text-2xl font-bold text-text-heading">{{ server.name }}</h1>
                <span
                    class="mt-1 inline-block rounded-full px-2 py-0.5 text-xs font-medium"
                    :class="getStatusConfig(server.status).class"
                >
                    {{ getStatusConfig(server.status).label }}
                </span>
            </div>
        </div>

        <!-- Server details -->
        <div class="rounded-lg border border-border bg-bg-card p-5">
            <h2 class="text-sm font-semibold text-text-heading uppercase tracking-wider">Server Info</h2>
            <div class="mt-4 space-y-3 text-sm">
                <div class="flex items-center gap-3">
                    <span class="w-28 shrink-0 text-text-tertiary">IP Address</span>
                    <span class="text-text">{{ server.ip_address || '—' }}</span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="w-28 shrink-0 text-text-tertiary">Provider</span>
                    <span class="text-text">{{ formatProvider(server.provider) }}</span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="w-28 shrink-0 text-text-tertiary">Credential</span>
                    <span class="text-text">{{ server.credential_name }}</span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="w-28 shrink-0 text-text-tertiary">Region</span>
                    <span class="text-text">{{ server.region_id }}</span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="w-28 shrink-0 text-text-tertiary">Size</span>
                    <span class="text-text">{{ server.size_id }}</span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="w-28 shrink-0 text-text-tertiary">Device</span>
                    <span class="flex items-center gap-1.5 text-text">
                        <span
                            class="h-2 w-2 shrink-0 rounded-full"
                            :class="hasOnlineDevice(server) ? 'bg-success' : 'bg-error'"
                        ></span>
                        {{ hasOnlineDevice(server) ? 'Online' : 'Offline' }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Deploy Key section -->
        <div v-if="server.ssh_key" class="mt-6 rounded-lg border border-border bg-bg-card p-5">
            <h2 class="text-sm font-semibold text-text-heading uppercase tracking-wider">Deploy Key</h2>
            <p class="mt-2 text-sm text-text-secondary">
                Add this server's SSH key to GitHub so Chief can clone private repos.
            </p>

            <div class="mt-4">
                <div class="flex items-center gap-2 text-sm text-text-secondary">
                    <span class="font-medium text-text">{{ server.ssh_key.name }}</span>
                </div>
                <div class="mt-2 rounded-md bg-bg-surface px-3 py-2 font-mono text-xs text-text-secondary break-all">
                    {{ server.ssh_key.public_key }}
                </div>
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                <!-- Add to GitHub button -->
                <button
                    v-if="hasGitHubToken"
                    @click="addToGitHub"
                    :disabled="addingToGitHub"
                    class="inline-flex items-center gap-2 rounded-md bg-interactive px-4 py-2 text-sm font-medium text-bg transition-colors hover:opacity-90 disabled:opacity-50"
                >
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-.135-.345-.72-1.41-1.23-1.695-.42-.225-1.02-.78-.015-.795.945-.015 1.62.87 1.845 1.23 1.08 1.815 2.805 1.305 3.495.99.105-.78.42-1.305.765-1.605-2.67-.3-5.46-1.335-5.46-5.925 0-1.305.465-2.385 1.23-3.225-.12-.3-.54-1.53.12-3.18 0 0 1.005-.315 3.3 1.23.96-.27 1.98-.405 3-.405s2.04.135 3 .405c2.295-1.56 3.3-1.23 3.3-1.23.66 1.65.24 2.88.12 3.18.765.84 1.23 1.905 1.23 3.225 0 4.605-2.805 5.625-5.475 5.925.435.375.81 1.095.81 2.22 0 1.605-.015 2.895-.015 3.3 0 .315.225.69.825.57A12.02 12.02 0 0024 12c0-6.63-5.37-12-12-12z"/>
                    </svg>
                    <span v-if="addingToGitHub">Adding...</span>
                    <span v-else>Add to GitHub</span>
                </button>

                <!-- Fallback: manual copy -->
                <button
                    @click="copyPublicKey"
                    class="inline-flex items-center gap-2 rounded-md border border-border px-4 py-2 text-sm font-medium text-text-secondary transition-colors hover:bg-bg-surface"
                >
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                        <path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/>
                    </svg>
                    <span v-if="copied">Copied!</span>
                    <span v-else>Copy Public Key</span>
                </button>
            </div>

            <p v-if="!hasGitHubToken" class="mt-3 text-sm text-text-secondary">
                <a href="/auth/github" class="font-medium text-interactive hover:underline">Log in with GitHub</a>
                to add this key automatically, or copy the key above and add it manually.
            </p>
        </div>
    </div>
</template>
