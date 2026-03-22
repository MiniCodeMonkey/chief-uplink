<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    credentials: Array,
    sshKeys: Array,
});

const step = ref(1);

const form = useForm({
    credential_id: '',
    region_id: '',
    size_id: '',
    name: '',
    ssh_key_id: '',
});

// Dynamic data fetched from provider API
const regions = ref([]);
const sizes = ref([]);
const loadingRegions = ref(false);
const loadingSizes = ref(false);
const fetchError = ref(null);

const selectedCredential = ref(null);

function selectCredential(credential) {
    form.credential_id = credential.id;
    selectedCredential.value = credential;
    form.region_id = '';
    form.size_id = '';
    regions.value = [];
    sizes.value = [];
    fetchError.value = null;
    goToStep(2);
    fetchRegionsAndSizes(credential.id);
}

async function fetchRegionsAndSizes(credentialId) {
    loadingRegions.value = true;
    loadingSizes.value = true;
    fetchError.value = null;

    try {
        const [regionsRes, sizesRes] = await Promise.all([
            fetch(`/servers/credentials/${credentialId}/regions`),
            fetch(`/servers/credentials/${credentialId}/sizes`),
        ]);

        if (!regionsRes.ok || !sizesRes.ok) {
            fetchError.value = 'Failed to fetch provider data. Check your API key.';
            return;
        }

        regions.value = await regionsRes.json();
        sizes.value = await sizesRes.json();
    } catch {
        fetchError.value = 'Failed to connect to provider API.';
    } finally {
        loadingRegions.value = false;
        loadingSizes.value = false;
    }
}

function goToStep(s) {
    step.value = s;
}

function canProceedToStep3() {
    return form.region_id && form.size_id;
}

function proceedToStep3() {
    if (canProceedToStep3()) {
        goToStep(3);
    }
}

function submitForm() {
    form.post('/servers');
}

const providerLabels = {
    hetzner: 'Hetzner',
    digitalocean: 'DigitalOcean',
};

function formatMemory(mb) {
    if (mb >= 1024) {
        return `${(mb / 1024).toFixed(mb % 1024 === 0 ? 0 : 1)} GB`;
    }
    return `${mb} MB`;
}

function formatPrice(price) {
    return `$${price.toFixed(2)}/mo`;
}
</script>

<template>
    <Head title="Provision Server" />

    <div class="p-6 md:p-8 max-w-2xl">
        <h1 class="text-2xl font-bold text-text-heading">Provision Server</h1>
        <p class="mt-1 text-sm text-text-secondary">Configure and launch a new cloud server.</p>

        <!-- Step indicator -->
        <div class="mt-6 flex items-center gap-2 text-sm">
            <button
                @click="goToStep(1)"
                class="rounded-full px-3 py-1 font-medium transition-colors"
                :class="step === 1 ? 'bg-interactive text-bg' : 'bg-bg-surface text-text-secondary hover:text-text'"
            >
                1. Provider
            </button>
            <span class="text-text-muted">&rarr;</span>
            <button
                @click="form.credential_id ? goToStep(2) : null"
                :disabled="!form.credential_id"
                class="rounded-full px-3 py-1 font-medium transition-colors"
                :class="step === 2 ? 'bg-interactive text-bg' : form.credential_id ? 'bg-bg-surface text-text-secondary hover:text-text' : 'bg-bg-surface text-text-muted cursor-not-allowed'"
            >
                2. Region &amp; Size
            </button>
            <span class="text-text-muted">&rarr;</span>
            <button
                @click="canProceedToStep3() ? goToStep(3) : null"
                :disabled="!canProceedToStep3()"
                class="rounded-full px-3 py-1 font-medium transition-colors"
                :class="step === 3 ? 'bg-interactive text-bg' : canProceedToStep3() ? 'bg-bg-surface text-text-secondary hover:text-text' : 'bg-bg-surface text-text-muted cursor-not-allowed'"
            >
                3. Server Details
            </button>
        </div>

        <!-- Step 1: Select credential -->
        <section v-if="step === 1" class="mt-6">
            <h2 class="text-lg font-semibold text-text-heading">Select Cloud Provider Credential</h2>
            <p class="mt-1 text-sm text-text-secondary">Choose which API key to use for provisioning.</p>

            <div v-if="credentials.length" class="mt-4 space-y-2">
                <button
                    v-for="credential in credentials"
                    :key="credential.id"
                    @click="selectCredential(credential)"
                    class="flex w-full items-center justify-between rounded-md border px-4 py-3 text-left transition-colors"
                    :class="form.credential_id === credential.id
                        ? 'border-interactive bg-interactive/10 text-text-heading'
                        : 'border-border bg-bg-card text-text hover:border-interactive/50'"
                >
                    <div>
                        <div class="text-sm font-medium text-text-heading">{{ credential.name }}</div>
                        <div class="text-xs text-text-secondary">{{ providerLabels[credential.provider] || credential.provider }}</div>
                    </div>
                    <svg v-if="form.credential_id === credential.id" class="h-5 w-5 text-interactive" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                </button>
            </div>

            <div v-else class="mt-4 rounded-md border border-border bg-bg-card p-6 text-center">
                <p class="text-sm text-text-muted">No cloud provider credentials found.</p>
                <a href="/settings/credentials" class="mt-2 inline-block text-sm font-medium text-interactive hover:underline">
                    Add a credential in Settings
                </a>
            </div>
        </section>

        <!-- Step 2: Region & Size -->
        <section v-if="step === 2" class="mt-6">
            <h2 class="text-lg font-semibold text-text-heading">Select Region &amp; Size</h2>
            <p class="mt-1 text-sm text-text-secondary">
                Using <span class="font-medium text-text-heading">{{ selectedCredential?.name }}</span>
                ({{ providerLabels[selectedCredential?.provider] }})
            </p>

            <div v-if="fetchError" class="mt-4 rounded-md border border-error/30 bg-error/10 px-4 py-3 text-sm text-error">
                {{ fetchError }}
            </div>

            <!-- Region -->
            <div class="mt-4">
                <label class="block text-sm font-medium text-text">Region</label>
                <div v-if="loadingRegions" class="mt-2 space-y-2">
                    <div v-for="i in 3" :key="i" class="h-10 animate-pulse rounded-md bg-bg-surface"></div>
                </div>
                <div v-else class="mt-2 grid grid-cols-2 gap-2">
                    <button
                        v-for="region in regions.filter(r => r.available)"
                        :key="region.id"
                        @click="form.region_id = region.id"
                        class="rounded-md border px-3 py-2 text-left text-sm transition-colors"
                        :class="form.region_id === region.id
                            ? 'border-interactive bg-interactive/10 text-text-heading'
                            : 'border-border bg-bg-card text-text hover:border-interactive/50'"
                    >
                        <div class="font-medium">{{ region.name }}</div>
                        <div class="text-xs text-text-secondary">{{ region.slug }}</div>
                    </button>
                </div>
                <p v-if="form.errors.region_id" class="mt-1 text-sm text-error">{{ form.errors.region_id }}</p>
            </div>

            <!-- Size -->
            <div class="mt-6">
                <label class="block text-sm font-medium text-text">Size</label>
                <div v-if="loadingSizes" class="mt-2 space-y-2">
                    <div v-for="i in 3" :key="i" class="h-14 animate-pulse rounded-md bg-bg-surface"></div>
                </div>
                <div v-else class="mt-2 space-y-2">
                    <button
                        v-for="size in sizes"
                        :key="size.id"
                        @click="form.size_id = size.id"
                        class="flex w-full items-center justify-between rounded-md border px-4 py-3 text-left transition-colors"
                        :class="form.size_id === size.id
                            ? 'border-interactive bg-interactive/10 text-text-heading'
                            : 'border-border bg-bg-card text-text hover:border-interactive/50'"
                    >
                        <div>
                            <div class="text-sm font-medium">{{ size.name }}</div>
                            <div class="text-xs text-text-secondary">
                                {{ size.vcpus }} vCPU · {{ formatMemory(size.memory_mb) }} RAM · {{ size.disk_gb }} GB disk
                            </div>
                        </div>
                        <div class="text-sm font-medium text-text-secondary">{{ formatPrice(size.price_monthly) }}</div>
                    </button>
                </div>
                <p v-if="form.errors.size_id" class="mt-1 text-sm text-error">{{ form.errors.size_id }}</p>
            </div>

            <div class="mt-6">
                <button
                    @click="proceedToStep3"
                    :disabled="!canProceedToStep3()"
                    class="rounded-md bg-interactive px-4 py-2 text-sm font-medium text-bg transition-colors hover:opacity-90 disabled:opacity-50"
                >
                    Continue
                </button>
            </div>
        </section>

        <!-- Step 3: Server Details -->
        <section v-if="step === 3" class="mt-6">
            <h2 class="text-lg font-semibold text-text-heading">Server Details</h2>
            <p class="mt-1 text-sm text-text-secondary">Name your server and select an SSH key.</p>

            <form @submit.prevent="submitForm" class="mt-4 space-y-4">
                <div>
                    <label for="server-name" class="block text-sm font-medium text-text">Server Name</label>
                    <input
                        id="server-name"
                        v-model="form.name"
                        type="text"
                        placeholder="e.g. web-prod-01"
                        class="mt-1 block w-full rounded-md border border-border bg-bg px-3 py-2 text-sm text-text placeholder-text-muted focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand"
                    />
                    <p v-if="form.errors.name" class="mt-1 text-sm text-error">{{ form.errors.name }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-text">SSH Key</label>
                    <div v-if="sshKeys.length" class="mt-2 space-y-2">
                        <button
                            v-for="key in sshKeys"
                            :key="key.id"
                            type="button"
                            @click="form.ssh_key_id = key.id"
                            class="flex w-full items-center justify-between rounded-md border px-4 py-3 text-left transition-colors"
                            :class="form.ssh_key_id === key.id
                                ? 'border-interactive bg-interactive/10 text-text-heading'
                                : 'border-border bg-bg-card text-text hover:border-interactive/50'"
                        >
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-medium">{{ key.name }}</div>
                                <div class="mt-0.5 truncate text-xs text-text-secondary font-mono">{{ key.public_key }}</div>
                            </div>
                            <svg v-if="form.ssh_key_id === key.id" class="ml-3 h-5 w-5 shrink-0 text-interactive" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                        </button>
                    </div>
                    <div v-else class="mt-2 rounded-md border border-border bg-bg-card p-4 text-center">
                        <p class="text-sm text-text-muted">No SSH keys found.</p>
                        <a href="/settings/credentials" class="mt-1 inline-block text-sm font-medium text-interactive hover:underline">
                            Add an SSH key in Settings
                        </a>
                    </div>
                    <p v-if="form.errors.ssh_key_id" class="mt-1 text-sm text-error">{{ form.errors.ssh_key_id }}</p>
                </div>

                <p v-if="form.errors.credential_id" class="text-sm text-error">{{ form.errors.credential_id }}</p>

                <div class="flex gap-2 pt-2">
                    <button
                        type="submit"
                        :disabled="form.processing || !form.name || !form.ssh_key_id"
                        class="rounded-md bg-interactive px-4 py-2 text-sm font-medium text-bg transition-colors hover:opacity-90 disabled:opacity-50"
                    >
                        <span v-if="form.processing">Provisioning...</span>
                        <span v-else>Provision Server</span>
                    </button>
                    <button
                        type="button"
                        @click="goToStep(2)"
                        class="rounded-md border border-border px-4 py-2 text-sm font-medium text-text-secondary transition-colors hover:bg-bg-surface"
                    >
                        Back
                    </button>
                </div>
            </form>
        </section>
    </div>
</template>
