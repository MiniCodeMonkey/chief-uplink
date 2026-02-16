<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Cloud,
    Eye,
    EyeOff,
    KeyRound,
    Loader2,
    RefreshCw,
    Rocket,
    Server,
    Terminal,
    Trash2,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { CopyButton } from '@/components/ui/copy-button';
import { EmptyState } from '@/components/ui/empty-state';
import { StatusDot } from '@/components/ui/status-dot';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';

type ProviderKey = {
    id: number;
    provider: string;
    masked_key: string;
    account_name: string | null;
    created_at: string | null;
};

type Deployment = {
    id: number;
    provider: string;
    region: string;
    tier: string;
    ip_address: string | null;
    status: 'provisioning' | 'active' | 'suspended' | 'destroyed';
    monthly_cost: string;
    provider_server_id: string | null;
    device_name: string | null;
    device_is_online: boolean;
    created_at: string | null;
};

const props = defineProps<{
    providerKeys: ProviderKey[];
    supportedProviders: string[];
    deployments: Deployment[];
}>();

const page = usePage();
const flash = computed(() => page.props.flash as { success?: string });

// Add key form state
const showAddForm = ref<string | null>(null);
const showApiKey = ref(false);

const form = useForm({
    provider: '',
    api_key: '',
});

// Remove key state
const removeTarget = ref<ProviderKey | null>(null);
const showRemoveDialog = ref(false);
const removing = ref(false);

// Deployment actions state
const destroyTarget = ref<Deployment | null>(null);
const showDestroyDialog = ref(false);
const restartingId = ref<number | null>(null);
const destroyError = ref<string | null>(null);
const restartError = ref<string | null>(null);

function getProviderName(provider: string): string {
    switch (provider) {
        case 'hetzner':
            return 'Hetzner';
        case 'digitalocean':
            return 'DigitalOcean';
        default:
            return provider;
    }
}

function getProviderDescription(provider: string): string {
    switch (provider) {
        case 'hetzner':
            return 'Generate an API token at Hetzner Cloud Console → Security → API Tokens.';
        case 'digitalocean':
            return 'Generate a personal access token at DigitalOcean → API → Tokens.';
        default:
            return '';
    }
}

function hasKeyForProvider(provider: string): boolean {
    return props.providerKeys.some((k) => k.provider === provider);
}

function getKeyForProvider(provider: string): ProviderKey | undefined {
    return props.providerKeys.find((k) => k.provider === provider);
}

function startAddKey(provider: string) {
    form.reset();
    form.clearErrors();
    form.provider = provider;
    showApiKey.value = false;
    showAddForm.value = provider;
}

function cancelAddKey() {
    showAddForm.value = null;
    form.reset();
    form.clearErrors();
    showApiKey.value = false;
}

function submitKey() {
    form.post('/settings/cloud-servers', {
        preserveScroll: true,
        onSuccess: () => {
            showAddForm.value = null;
            form.reset();
            showApiKey.value = false;
        },
    });
}

function promptRemove(key: ProviderKey) {
    removeTarget.value = key;
    showRemoveDialog.value = true;
}

function confirmRemove() {
    if (!removeTarget.value) return;

    const keyId = removeTarget.value.id;
    removing.value = true;

    router.delete(`/settings/cloud-servers/${keyId}`, {
        preserveScroll: true,
        onSuccess: () => {
            showRemoveDialog.value = false;
            removing.value = false;
            removeTarget.value = null;
        },
        onError: () => {
            removing.value = false;
        },
    });
}

function cancelRemove() {
    showRemoveDialog.value = false;
    removeTarget.value = null;
}

// Deployment actions
function getStatusVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    switch (status) {
        case 'active':
            return 'default';
        case 'provisioning':
            return 'secondary';
        case 'suspended':
            return 'outline';
        case 'destroyed':
            return 'destructive';
        default:
            return 'secondary';
    }
}

function getStatusLabel(status: string): string {
    switch (status) {
        case 'active':
            return 'Active';
        case 'provisioning':
            return 'Provisioning';
        case 'suspended':
            return 'Suspended';
        case 'destroyed':
            return 'Destroyed';
        default:
            return status;
    }
}

function formatRegion(provider: string, region: string): string {
    const hetznerRegions: Record<string, string> = {
        nbg1: 'Nuremberg',
        fsn1: 'Falkenstein',
        hel1: 'Helsinki',
        ash: 'Ashburn',
    };
    const doRegions: Record<string, string> = {
        nyc1: 'New York 1',
        nyc3: 'New York 3',
        sfo3: 'San Francisco 3',
        lon1: 'London',
        ams3: 'Amsterdam',
        sgp1: 'Singapore',
        fra1: 'Frankfurt',
        tor1: 'Toronto',
        blr1: 'Bangalore',
        syd1: 'Sydney',
    };

    if (provider === 'hetzner') {
        return hetznerRegions[region] ?? region;
    }
    if (provider === 'digitalocean') {
        return doRegions[region] ?? region;
    }
    return region;
}

async function restartChief(deployment: Deployment) {
    restartingId.value = deployment.id;
    restartError.value = null;

    try {
        const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
        const response = await fetch(`/settings/cloud-deploy/${deployment.id}/restart`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken ?? '',
                Accept: 'application/json',
            },
        });

        const data = await response.json();

        if (!response.ok) {
            restartError.value = data.error ?? 'Failed to restart Chief.';
        }

        // Refresh the page data
        router.reload({ preserveScroll: true });
    } catch {
        restartError.value = 'Network error. Please try again.';
    } finally {
        restartingId.value = null;
    }
}

function promptDestroy(deployment: Deployment) {
    destroyTarget.value = deployment;
    showDestroyDialog.value = true;
    destroyError.value = null;
}

async function confirmDestroy() {
    if (!destroyTarget.value) return;

    const deploymentId = destroyTarget.value.id;
    destroyError.value = null;

    try {
        const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
        const response = await fetch(`/settings/cloud-deploy/${deploymentId}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken ?? '',
                Accept: 'application/json',
            },
        });

        const data = await response.json();

        if (!response.ok) {
            destroyError.value = data.error ?? 'Failed to destroy server.';
            return;
        }

        showDestroyDialog.value = false;
        destroyTarget.value = null;

        // Refresh the page data
        router.reload({ preserveScroll: true });
    } catch {
        destroyError.value = 'Network error. Please try again.';
    }
}

function cancelDestroy() {
    showDestroyDialog.value = false;
    destroyTarget.value = null;
    destroyError.value = null;
}

const activeDeployments = computed(() => props.deployments.filter((d) => d.status !== 'destroyed'));
const destroyedDeployments = computed(() => props.deployments.filter((d) => d.status === 'destroyed'));
</script>

<template>
    <AppLayout>
        <Head title="Cloud servers" />

        <h1 class="sr-only">Cloud Server Settings</h1>

        <SettingsLayout>
            <div class="space-y-8">
                <!-- Header with deploy button -->
                <div class="flex items-start justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Cloud Servers"
                        description="Deploy and manage cloud VPS instances running Chief"
                    />
                    <Button as-child size="sm">
                        <Link href="/settings/cloud-deploy">
                            <Rocket class="size-4" />
                            Deploy Server
                        </Link>
                    </Button>
                </div>

                <!-- Success flash message -->
                <Transition
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="opacity-0 -translate-y-1"
                    leave-active-class="transition duration-150 ease-in"
                    leave-to-class="opacity-0 -translate-y-1"
                >
                    <p v-if="flash.success" class="text-sm text-success" role="status">
                        {{ flash.success }}
                    </p>
                </Transition>

                <!-- Error messages -->
                <Transition
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="opacity-0 -translate-y-1"
                    leave-active-class="transition duration-150 ease-in"
                    leave-to-class="opacity-0 -translate-y-1"
                >
                    <p v-if="restartError" class="text-sm text-destructive" role="alert">
                        {{ restartError }}
                    </p>
                </Transition>

                <!-- ===== Deployed Servers Section ===== -->
                <div class="space-y-3">
                    <h2 class="text-sm font-medium text-foreground">Servers</h2>

                    <!-- Active / Provisioning servers -->
                    <div v-if="activeDeployments.length > 0" class="space-y-3">
                        <div
                            v-for="deployment in activeDeployments"
                            :key="deployment.id"
                            class="rounded-lg border border-border"
                        >
                            <div class="p-4 space-y-3">
                                <!-- Top row: provider + status -->
                                <div class="flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div
                                            class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground"
                                        >
                                            <Server class="size-5" />
                                        </div>
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2">
                                                <Badge variant="secondary" class="text-xs shrink-0">
                                                    {{ getProviderName(deployment.provider) }}
                                                </Badge>
                                                <Badge
                                                    :variant="getStatusVariant(deployment.status)"
                                                    class="text-xs shrink-0"
                                                >
                                                    <Loader2
                                                        v-if="deployment.status === 'provisioning'"
                                                        class="size-3 animate-spin"
                                                    />
                                                    {{ getStatusLabel(deployment.status) }}
                                                </Badge>
                                            </div>
                                            <p class="text-xs text-muted-foreground mt-0.5">
                                                {{ formatRegion(deployment.provider, deployment.region) }}
                                                · {{ deployment.tier.toUpperCase() }}
                                                · ${{ deployment.monthly_cost }}/mo
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Details row -->
                                <div class="flex flex-wrap gap-x-4 gap-y-2 text-sm">
                                    <!-- IP address -->
                                    <div v-if="deployment.ip_address" class="flex items-center gap-1.5">
                                        <span class="text-muted-foreground">IP:</span>
                                        <code class="font-mono text-xs text-foreground">{{ deployment.ip_address }}</code>
                                        <CopyButton :value="deployment.ip_address" label="Copy IP" class="text-muted-foreground" />
                                    </div>

                                    <!-- SSH command -->
                                    <div v-if="deployment.ip_address" class="flex items-center gap-1.5">
                                        <Terminal class="size-3.5 text-muted-foreground" />
                                        <code class="font-mono text-xs text-foreground">ssh chief@{{ deployment.ip_address }}</code>
                                        <CopyButton :value="`ssh chief@${deployment.ip_address}`" label="Copy SSH" class="text-muted-foreground" />
                                    </div>

                                    <!-- Linked device -->
                                    <div v-if="deployment.device_name" class="flex items-center gap-1.5">
                                        <StatusDot
                                            :state="deployment.device_is_online ? 'online' : 'offline'"
                                        />
                                        <span class="text-xs text-muted-foreground">
                                            {{ deployment.device_name }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Action buttons -->
                                <div
                                    v-if="deployment.status === 'active' || deployment.status === 'suspended'"
                                    class="flex items-center gap-2 pt-1 border-t border-border"
                                >
                                    <Button
                                        v-if="deployment.status === 'active'"
                                        variant="outline"
                                        size="sm"
                                        :disabled="restartingId === deployment.id"
                                        @click="restartChief(deployment)"
                                    >
                                        <Loader2
                                            v-if="restartingId === deployment.id"
                                            class="size-4 animate-spin"
                                        />
                                        <RefreshCw v-else class="size-4" />
                                        {{ restartingId === deployment.id ? 'Restarting...' : 'Restart Chief' }}
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="text-destructive hover:text-destructive hover:bg-destructive/10"
                                        @click="promptDestroy(deployment)"
                                    >
                                        <Trash2 class="size-4" />
                                        Destroy Server
                                    </Button>
                                </div>

                                <!-- Provisioning progress -->
                                <div
                                    v-if="deployment.status === 'provisioning'"
                                    class="pt-1 border-t border-border"
                                >
                                    <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                        <Loader2 class="size-3.5 animate-spin text-primary" />
                                        <span>Server is being provisioned. This usually takes about 60 seconds...</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Destroyed servers (grayed out history) -->
                    <div v-if="destroyedDeployments.length > 0" class="space-y-3">
                        <div
                            v-for="deployment in destroyedDeployments"
                            :key="deployment.id"
                            class="rounded-lg border border-border opacity-50"
                        >
                            <div class="p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div
                                            class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground"
                                        >
                                            <Server class="size-5" />
                                        </div>
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2">
                                                <Badge variant="secondary" class="text-xs shrink-0">
                                                    {{ getProviderName(deployment.provider) }}
                                                </Badge>
                                                <Badge variant="destructive" class="text-xs shrink-0">
                                                    Destroyed
                                                </Badge>
                                            </div>
                                            <p class="text-xs text-muted-foreground mt-0.5">
                                                {{ formatRegion(deployment.provider, deployment.region) }}
                                                · {{ deployment.tier.toUpperCase() }}
                                                <span v-if="deployment.ip_address"> · {{ deployment.ip_address }}</span>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Empty state: no servers -->
                    <EmptyState
                        v-if="deployments.length === 0"
                        :icon="Server"
                        title="No cloud servers"
                        description="Deploy one to run Chief without managing your own VPS."
                    >
                        <template #action>
                            <Button as-child size="sm">
                                <Link href="/settings/cloud-deploy">
                                    <Rocket class="size-4" />
                                    Deploy Server
                                </Link>
                            </Button>
                        </template>
                    </EmptyState>
                </div>

                <!-- ===== API Keys Section ===== -->
                <div class="space-y-3">
                    <h2 class="text-sm font-medium text-foreground">API Keys</h2>

                    <!-- Provider sections -->
                    <div class="space-y-3">
                        <div
                            v-for="provider in supportedProviders"
                            :key="provider"
                            class="rounded-lg border border-border"
                        >
                            <!-- Provider header -->
                            <div class="flex items-center justify-between gap-4 p-4">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground"
                                    >
                                        <Cloud class="size-5" />
                                    </div>
                                    <div>
                                        <h3 class="font-medium text-foreground">
                                            {{ getProviderName(provider) }}
                                        </h3>
                                        <p
                                            v-if="hasKeyForProvider(provider)"
                                            class="text-xs text-muted-foreground"
                                        >
                                            Connected as {{ getKeyForProvider(provider)?.account_name }}
                                        </p>
                                    </div>
                                </div>

                                <!-- Key status / actions -->
                                <div v-if="hasKeyForProvider(provider)" class="flex items-center gap-2">
                                    <Badge variant="secondary" class="font-mono text-xs">
                                        {{ getKeyForProvider(provider)?.masked_key }}
                                    </Badge>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="shrink-0 text-destructive hover:text-destructive hover:bg-destructive/10"
                                        @click="promptRemove(getKeyForProvider(provider)!)"
                                    >
                                        <Trash2 class="size-4" />
                                        <span class="sr-only sm:not-sr-only">Remove</span>
                                    </Button>
                                </div>
                                <Button
                                    v-else-if="showAddForm !== provider"
                                    variant="outline"
                                    size="sm"
                                    @click="startAddKey(provider)"
                                >
                                    <KeyRound class="size-4" />
                                    Add API Key
                                </Button>
                            </div>

                            <!-- Add key form -->
                            <Transition
                                enter-active-class="transition-all duration-200 ease-out"
                                enter-from-class="opacity-0 max-h-0"
                                enter-to-class="opacity-100 max-h-96"
                                leave-active-class="transition-all duration-150 ease-in"
                                leave-from-class="opacity-100 max-h-96"
                                leave-to-class="opacity-0 max-h-0"
                            >
                                <div
                                    v-if="showAddForm === provider"
                                    class="overflow-hidden border-t border-border"
                                >
                                    <form class="space-y-4 p-4" @submit.prevent="submitKey">
                                        <p class="text-sm text-muted-foreground">
                                            {{ getProviderDescription(provider) }}
                                        </p>

                                        <!-- API key input -->
                                        <div class="space-y-2">
                                            <label
                                                :for="`api-key-${provider}`"
                                                class="text-sm font-medium text-foreground"
                                            >
                                                API Key
                                            </label>
                                            <div class="relative">
                                                <input
                                                    :id="`api-key-${provider}`"
                                                    v-model="form.api_key"
                                                    :type="showApiKey ? 'text' : 'password'"
                                                    placeholder="Paste your API key"
                                                    autocomplete="off"
                                                    :class="[
                                                        'border-input bg-background focus-visible:border-ring focus-visible:ring-ring/50 h-9 w-full rounded-md border px-3 pr-10 text-sm font-mono shadow-xs outline-none transition-[color,box-shadow] focus-visible:ring-[3px]',
                                                        form.errors.api_key &&
                                                            'border-destructive ring-destructive/20 ring-[3px]',
                                                    ]"
                                                />
                                                <button
                                                    type="button"
                                                    class="absolute right-2 top-1/2 -translate-y-1/2 p-1 text-muted-foreground hover:text-foreground transition-colors"
                                                    :aria-label="showApiKey ? 'Hide API key' : 'Show API key'"
                                                    @click="showApiKey = !showApiKey"
                                                >
                                                    <EyeOff v-if="showApiKey" class="size-4" />
                                                    <Eye v-else class="size-4" />
                                                </button>
                                            </div>
                                            <p
                                                v-if="form.errors.api_key"
                                                class="text-sm text-destructive"
                                                role="alert"
                                            >
                                                {{ form.errors.api_key }}
                                            </p>
                                            <p
                                                v-if="form.errors.provider"
                                                class="text-sm text-destructive"
                                                role="alert"
                                            >
                                                {{ form.errors.provider }}
                                            </p>
                                        </div>

                                        <!-- Actions -->
                                        <div class="flex items-center gap-2">
                                            <Button
                                                type="submit"
                                                :disabled="form.processing || !form.api_key"
                                                size="sm"
                                            >
                                                <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                                                {{ form.processing ? 'Validating...' : 'Validate & Save' }}
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                :disabled="form.processing"
                                                @click="cancelAddKey"
                                            >
                                                Cancel
                                            </Button>
                                        </div>
                                    </form>
                                </div>
                            </Transition>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Confirm remove API key dialog -->
            <ConfirmDialog
                v-model:open="showRemoveDialog"
                title="Remove API key"
                :description="`This will prevent you from managing existing cloud servers through ${removeTarget ? getProviderName(removeTarget.provider) : 'this provider'}.`"
                confirm-label="Remove"
                variant="destructive"
                @confirm="confirmRemove"
                @cancel="cancelRemove"
            />

            <!-- Confirm destroy server dialog (double confirmation: dialog + type IP) -->
            <ConfirmDialog
                v-model:open="showDestroyDialog"
                title="Destroy server"
                :description="`This will permanently destroy the ${destroyTarget ? getProviderName(destroyTarget.provider) : ''} server${destroyTarget?.ip_address ? ' at ' + destroyTarget.ip_address : ''}. This action cannot be undone.`"
                confirm-label="Destroy Server"
                variant="destructive"
                :confirm-text="destroyTarget?.ip_address ?? ''"
                @confirm="confirmDestroy"
                @cancel="cancelDestroy"
            />
        </SettingsLayout>
    </AppLayout>
</template>
