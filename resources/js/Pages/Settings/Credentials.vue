<script setup>
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    credentials: Array,
    sshKeys: Array,
    isOwner: Boolean,
});

const page = usePage();

// Cloud Provider Credential forms
const showAddCredential = ref(false);
const editingCredentialId = ref(null);

const credentialForm = useForm({
    name: '',
    provider: 'hetzner',
    api_key: '',
});

const editCredentialForm = useForm({
    name: '',
    provider: 'hetzner',
    api_key: '',
});

function submitCredential() {
    credentialForm.post('/settings/credentials', {
        onSuccess: () => {
            credentialForm.reset();
            showAddCredential.value = false;
        },
    });
}

function startEditCredential(credential) {
    editingCredentialId.value = credential.id;
    editCredentialForm.name = credential.name;
    editCredentialForm.provider = credential.provider;
    editCredentialForm.api_key = '';
}

function cancelEditCredential() {
    editingCredentialId.value = null;
    editCredentialForm.clearErrors();
}

function submitEditCredential(id) {
    editCredentialForm.put(`/settings/credentials/${id}`, {
        onSuccess: () => {
            editingCredentialId.value = null;
        },
    });
}

const confirmingDeleteCredential = ref(null);

function deleteCredential(id) {
    router.delete(`/settings/credentials/${id}`, {
        onSuccess: () => {
            confirmingDeleteCredential.value = null;
        },
    });
}

// SSH Key forms
const showAddSshKey = ref(false);
const editingSshKeyId = ref(null);

const sshKeyForm = useForm({
    name: '',
    public_key: '',
});

const editSshKeyForm = useForm({
    name: '',
    public_key: '',
});

function submitSshKey() {
    sshKeyForm.post('/settings/ssh-keys', {
        onSuccess: () => {
            sshKeyForm.reset();
            showAddSshKey.value = false;
        },
    });
}

function startEditSshKey(key) {
    editingSshKeyId.value = key.id;
    editSshKeyForm.name = key.name;
    editSshKeyForm.public_key = key.public_key;
}

function cancelEditSshKey() {
    editingSshKeyId.value = null;
    editSshKeyForm.clearErrors();
}

function submitEditSshKey(id) {
    editSshKeyForm.put(`/settings/ssh-keys/${id}`, {
        onSuccess: () => {
            editingSshKeyId.value = null;
        },
    });
}

const confirmingDeleteSshKey = ref(null);

function deleteSshKey(id) {
    router.delete(`/settings/ssh-keys/${id}`, {
        onSuccess: () => {
            confirmingDeleteSshKey.value = null;
        },
    });
}

const providerLabels = {
    hetzner: 'Hetzner',
    digitalocean: 'DigitalOcean',
};
</script>

<template>
    <Head title="Credentials" />

    <div class="p-6 md:p-8 max-w-2xl">
        <h1 class="text-2xl font-bold text-text-heading">Credentials</h1>
        <p class="mt-1 text-sm text-text-secondary">Manage cloud provider API keys and SSH keys for your team.</p>

        <!-- Flash messages -->
        <div v-if="page.props.flash?.success" class="mt-4 rounded-md border border-success/30 bg-success/10 px-4 py-3 text-sm text-success">
            {{ page.props.flash.success }}
        </div>

        <!-- Cloud Provider Credentials Section -->
        <section class="mt-8">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-text-heading">Cloud Provider API Keys</h2>
                <button
                    v-if="isOwner && !showAddCredential"
                    @click="showAddCredential = true"
                    class="rounded-md bg-interactive px-3 py-1.5 text-sm font-medium text-bg transition-colors hover:opacity-90"
                >
                    Add Key
                </button>
            </div>

            <!-- Add Credential Form -->
            <form v-if="showAddCredential" @submit.prevent="submitCredential" class="mt-4 rounded-md border border-border bg-bg-card p-4 space-y-3">
                <div>
                    <label for="cred-name" class="block text-sm font-medium text-text">Name</label>
                    <input
                        id="cred-name"
                        v-model="credentialForm.name"
                        type="text"
                        placeholder="e.g. Production API Key"
                        class="mt-1 block w-full rounded-md border border-border bg-bg px-3 py-2 text-sm text-text placeholder-text-muted focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand"
                    />
                    <p v-if="credentialForm.errors.name" class="mt-1 text-sm text-error">{{ credentialForm.errors.name }}</p>
                </div>

                <div>
                    <label for="cred-provider" class="block text-sm font-medium text-text">Provider</label>
                    <select
                        id="cred-provider"
                        v-model="credentialForm.provider"
                        class="mt-1 block w-full rounded-md border border-border bg-bg px-3 py-2 text-sm text-text focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand"
                    >
                        <option value="hetzner">Hetzner</option>
                        <option value="digitalocean">DigitalOcean</option>
                    </select>
                    <p v-if="credentialForm.errors.provider" class="mt-1 text-sm text-error">{{ credentialForm.errors.provider }}</p>
                </div>

                <div>
                    <label for="cred-api-key" class="block text-sm font-medium text-text">API Key</label>
                    <input
                        id="cred-api-key"
                        v-model="credentialForm.api_key"
                        type="password"
                        class="mt-1 block w-full rounded-md border border-border bg-bg px-3 py-2 text-sm text-text placeholder-text-muted focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand"
                    />
                    <p v-if="credentialForm.errors.api_key" class="mt-1 text-sm text-error">{{ credentialForm.errors.api_key }}</p>
                </div>

                <div class="flex gap-2">
                    <button
                        type="submit"
                        :disabled="credentialForm.processing"
                        class="rounded-md bg-interactive px-3 py-2 text-sm font-medium text-bg transition-colors hover:opacity-90 disabled:opacity-50"
                    >
                        Save
                    </button>
                    <button
                        type="button"
                        @click="showAddCredential = false; credentialForm.reset(); credentialForm.clearErrors()"
                        class="rounded-md border border-border px-3 py-2 text-sm font-medium text-text-secondary transition-colors hover:bg-bg-surface"
                    >
                        Cancel
                    </button>
                </div>
            </form>

            <!-- Credentials List -->
            <div v-if="credentials.length" class="mt-4 divide-y divide-border rounded-md border border-border">
                <div v-for="credential in credentials" :key="credential.id" class="px-4 py-3">
                    <!-- Edit mode -->
                    <form v-if="editingCredentialId === credential.id" @submit.prevent="submitEditCredential(credential.id)" class="space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-text">Name</label>
                            <input
                                v-model="editCredentialForm.name"
                                type="text"
                                class="mt-1 block w-full rounded-md border border-border bg-bg px-3 py-2 text-sm text-text focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand"
                            />
                            <p v-if="editCredentialForm.errors.name" class="mt-1 text-sm text-error">{{ editCredentialForm.errors.name }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-text">Provider</label>
                            <select
                                v-model="editCredentialForm.provider"
                                class="mt-1 block w-full rounded-md border border-border bg-bg px-3 py-2 text-sm text-text focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand"
                            >
                                <option value="hetzner">Hetzner</option>
                                <option value="digitalocean">DigitalOcean</option>
                            </select>
                            <p v-if="editCredentialForm.errors.provider" class="mt-1 text-sm text-error">{{ editCredentialForm.errors.provider }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-text">API Key</label>
                            <input
                                v-model="editCredentialForm.api_key"
                                type="password"
                                placeholder="Leave blank to keep current key"
                                class="mt-1 block w-full rounded-md border border-border bg-bg px-3 py-2 text-sm text-text placeholder-text-muted focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand"
                            />
                            <p v-if="editCredentialForm.errors.api_key" class="mt-1 text-sm text-error">{{ editCredentialForm.errors.api_key }}</p>
                        </div>
                        <div class="flex gap-2">
                            <button
                                type="submit"
                                :disabled="editCredentialForm.processing"
                                class="rounded-md bg-interactive px-3 py-2 text-sm font-medium text-bg transition-colors hover:opacity-90 disabled:opacity-50"
                            >
                                Save
                            </button>
                            <button
                                type="button"
                                @click="cancelEditCredential"
                                class="rounded-md border border-border px-3 py-2 text-sm font-medium text-text-secondary transition-colors hover:bg-bg-surface"
                            >
                                Cancel
                            </button>
                        </div>
                    </form>

                    <!-- Display mode -->
                    <div v-else class="flex items-center justify-between">
                        <div>
                            <div class="text-sm font-medium text-text-heading">{{ credential.name }}</div>
                            <div class="text-xs text-text-secondary">{{ providerLabels[credential.provider] || credential.provider }}</div>
                        </div>
                        <div v-if="isOwner" class="flex items-center gap-3">
                            <button
                                @click="startEditCredential(credential)"
                                class="text-xs font-medium text-text-secondary hover:text-text hover:underline"
                            >
                                Edit
                            </button>
                            <div v-if="confirmingDeleteCredential === credential.id" class="flex items-center gap-2">
                                <span class="text-xs text-text-secondary">Delete?</span>
                                <button
                                    @click="deleteCredential(credential.id)"
                                    class="text-xs font-medium text-error hover:underline"
                                >
                                    Confirm
                                </button>
                                <button
                                    @click="confirmingDeleteCredential = null"
                                    class="text-xs text-text-muted hover:underline"
                                >
                                    Cancel
                                </button>
                            </div>
                            <button
                                v-else
                                @click="confirmingDeleteCredential = credential.id"
                                class="text-xs font-medium text-error/80 hover:text-error hover:underline"
                            >
                                Delete
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <p v-else-if="!showAddCredential" class="mt-4 text-sm text-text-muted">No cloud provider credentials yet.</p>
        </section>

        <!-- SSH Keys Section -->
        <section class="mt-8">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-text-heading">SSH Keys</h2>
                <button
                    v-if="isOwner && !showAddSshKey"
                    @click="showAddSshKey = true"
                    class="rounded-md bg-interactive px-3 py-1.5 text-sm font-medium text-bg transition-colors hover:opacity-90"
                >
                    Add Key
                </button>
            </div>

            <!-- Add SSH Key Form -->
            <form v-if="showAddSshKey" @submit.prevent="submitSshKey" class="mt-4 rounded-md border border-border bg-bg-card p-4 space-y-3">
                <div>
                    <label for="ssh-name" class="block text-sm font-medium text-text">Name</label>
                    <input
                        id="ssh-name"
                        v-model="sshKeyForm.name"
                        type="text"
                        placeholder="e.g. Deploy Key"
                        class="mt-1 block w-full rounded-md border border-border bg-bg px-3 py-2 text-sm text-text placeholder-text-muted focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand"
                    />
                    <p v-if="sshKeyForm.errors.name" class="mt-1 text-sm text-error">{{ sshKeyForm.errors.name }}</p>
                </div>

                <div>
                    <label for="ssh-public-key" class="block text-sm font-medium text-text">Public Key</label>
                    <textarea
                        id="ssh-public-key"
                        v-model="sshKeyForm.public_key"
                        rows="3"
                        placeholder="ssh-ed25519 AAAA..."
                        class="mt-1 block w-full rounded-md border border-border bg-bg px-3 py-2 text-sm text-text placeholder-text-muted focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand font-mono"
                    />
                    <p v-if="sshKeyForm.errors.public_key" class="mt-1 text-sm text-error">{{ sshKeyForm.errors.public_key }}</p>
                </div>

                <div class="flex gap-2">
                    <button
                        type="submit"
                        :disabled="sshKeyForm.processing"
                        class="rounded-md bg-interactive px-3 py-2 text-sm font-medium text-bg transition-colors hover:opacity-90 disabled:opacity-50"
                    >
                        Save
                    </button>
                    <button
                        type="button"
                        @click="showAddSshKey = false; sshKeyForm.reset(); sshKeyForm.clearErrors()"
                        class="rounded-md border border-border px-3 py-2 text-sm font-medium text-text-secondary transition-colors hover:bg-bg-surface"
                    >
                        Cancel
                    </button>
                </div>
            </form>

            <!-- SSH Keys List -->
            <div v-if="sshKeys.length" class="mt-4 divide-y divide-border rounded-md border border-border">
                <div v-for="key in sshKeys" :key="key.id" class="px-4 py-3">
                    <!-- Edit mode -->
                    <form v-if="editingSshKeyId === key.id" @submit.prevent="submitEditSshKey(key.id)" class="space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-text">Name</label>
                            <input
                                v-model="editSshKeyForm.name"
                                type="text"
                                class="mt-1 block w-full rounded-md border border-border bg-bg px-3 py-2 text-sm text-text focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand"
                            />
                            <p v-if="editSshKeyForm.errors.name" class="mt-1 text-sm text-error">{{ editSshKeyForm.errors.name }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-text">Public Key</label>
                            <textarea
                                v-model="editSshKeyForm.public_key"
                                rows="3"
                                class="mt-1 block w-full rounded-md border border-border bg-bg px-3 py-2 text-sm text-text focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand font-mono"
                            />
                            <p v-if="editSshKeyForm.errors.public_key" class="mt-1 text-sm text-error">{{ editSshKeyForm.errors.public_key }}</p>
                        </div>
                        <div class="flex gap-2">
                            <button
                                type="submit"
                                :disabled="editSshKeyForm.processing"
                                class="rounded-md bg-interactive px-3 py-2 text-sm font-medium text-bg transition-colors hover:opacity-90 disabled:opacity-50"
                            >
                                Save
                            </button>
                            <button
                                type="button"
                                @click="cancelEditSshKey"
                                class="rounded-md border border-border px-3 py-2 text-sm font-medium text-text-secondary transition-colors hover:bg-bg-surface"
                            >
                                Cancel
                            </button>
                        </div>
                    </form>

                    <!-- Display mode -->
                    <div v-else class="flex items-center justify-between">
                        <div class="min-w-0 flex-1">
                            <div class="text-sm font-medium text-text-heading">{{ key.name }}</div>
                            <div class="mt-0.5 truncate text-xs text-text-secondary font-mono">{{ key.public_key }}</div>
                        </div>
                        <div v-if="isOwner" class="flex items-center gap-3 ml-4 shrink-0">
                            <button
                                @click="startEditSshKey(key)"
                                class="text-xs font-medium text-text-secondary hover:text-text hover:underline"
                            >
                                Edit
                            </button>
                            <div v-if="confirmingDeleteSshKey === key.id" class="flex items-center gap-2">
                                <span class="text-xs text-text-secondary">Delete?</span>
                                <button
                                    @click="deleteSshKey(key.id)"
                                    class="text-xs font-medium text-error hover:underline"
                                >
                                    Confirm
                                </button>
                                <button
                                    @click="confirmingDeleteSshKey = null"
                                    class="text-xs text-text-muted hover:underline"
                                >
                                    Cancel
                                </button>
                            </div>
                            <button
                                v-else
                                @click="confirmingDeleteSshKey = key.id"
                                class="text-xs font-medium text-error/80 hover:text-error hover:underline"
                            >
                                Delete
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <p v-else-if="!showAddSshKey" class="mt-4 text-sm text-text-muted">No SSH keys yet.</p>
        </section>
    </div>
</template>
