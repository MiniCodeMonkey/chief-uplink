<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    Check,
    Cloud,
    Eye,
    EyeOff,
    Loader2,
    MapPin,
    Rocket,
    Server,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { CopyButton } from '@/components/ui/copy-button';
import { ProgressBar } from '@/components/ui/progress-bar';
import { Spinner } from '@/components/ui/spinner';
import { useToast } from '@/composables/useToast';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';

type ProviderKey = {
    id: number;
    provider: string;
    masked_key: string;
    account_name: string | null;
};

type Region = {
    id: string;
    name: string;
    description: string;
};

type Tier = {
    id: string;
    name: string;
    cpu: number;
    ram: string;
    ram_mb: number;
    disk: string;
    disk_gb: number;
    monthly_cost: number;
    recommended: boolean;
};

const props = defineProps<{
    providerKeys: ProviderKey[];
    supportedProviders: string[];
}>();

const { success: successToast } = useToast();

// Wizard state
const currentStep = ref(1);
const totalSteps = 4;
const slideDirection = ref<'left' | 'right'>('left');

// Step 1: Provider
const selectedProvider = ref<string | null>(null);

// Inline API key form (when provider has no key)
const showApiKeyForm = ref(false);
const showApiKey = ref(false);
const apiKeyForm = useForm({
    provider: '',
    api_key: '',
});

// Step 2: Region
const regions = ref<Region[]>([]);
const selectedRegion = ref<Region | null>(null);
const loadingRegions = ref(false);
const regionError = ref('');

// Step 3: Tier
const tiers = ref<Tier[]>([]);
const selectedTier = ref<Tier | null>(null);
const loadingTiers = ref(false);
const tierError = ref('');

// Step 4: Deploy
const deploying = ref(false);
const deployError = ref('');
const deploymentId = ref<number | null>(null);
const deploymentStatus = ref<string>('');
const deployedIp = ref<string | null>(null);
const deploySuccess = ref(false);
const statusPollTimer = ref<ReturnType<typeof setInterval> | null>(null);

// Helpers
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

// Step validation
const canProceed = computed(() => {
    switch (currentStep.value) {
        case 1:
            return selectedProvider.value !== null && hasKeyForProvider(selectedProvider.value);
        case 2:
            return selectedRegion.value !== null;
        case 3:
            return selectedTier.value !== null;
        case 4:
            return true;
        default:
            return false;
    }
});

// Step 1: Select provider
function selectProvider(provider: string) {
    selectedProvider.value = provider;

    if (!hasKeyForProvider(provider)) {
        showApiKeyForm.value = true;
        apiKeyForm.provider = provider;
        apiKeyForm.api_key = '';
        apiKeyForm.clearErrors();
    } else {
        showApiKeyForm.value = false;
    }
}

function submitApiKey() {
    apiKeyForm.post('/settings/cloud-servers', {
        preserveScroll: true,
        onSuccess: () => {
            showApiKeyForm.value = false;
            showApiKey.value = false;
            // The page will be re-rendered with updated providerKeys
        },
    });
}

function cancelApiKey() {
    showApiKeyForm.value = false;
    apiKeyForm.reset();
    apiKeyForm.clearErrors();
    showApiKey.value = false;
}

// Navigation
function nextStep() {
    if (!canProceed.value) return;

    slideDirection.value = 'left';

    if (currentStep.value === 1) {
        // Fetch regions when moving to step 2
        fetchRegions();
    } else if (currentStep.value === 2) {
        // Fetch tiers when moving to step 3
        fetchTiers();
    }

    currentStep.value = Math.min(currentStep.value + 1, totalSteps);
}

function prevStep() {
    slideDirection.value = 'right';
    currentStep.value = Math.max(currentStep.value - 1, 1);
}

function handleKeydown(e: KeyboardEvent) {
    if (e.key === 'Enter' && canProceed.value && currentStep.value < totalSteps) {
        e.preventDefault();
        nextStep();
    } else if (e.key === 'Escape') {
        e.preventDefault();
        router.visit('/settings/cloud-servers');
    }
}

// Fetch regions from provider API
async function fetchRegions() {
    if (!selectedProvider.value) return;

    loadingRegions.value = true;
    regionError.value = '';
    regions.value = [];
    selectedRegion.value = null;

    try {
        const response = await fetch('/settings/cloud-deploy/regions', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
            },
            body: JSON.stringify({ provider: selectedProvider.value }),
        });

        const data = await response.json();

        if (!response.ok) {
            regionError.value = data.error || 'Failed to fetch regions';
            return;
        }

        regions.value = data.regions;
    } catch {
        regionError.value = 'Network error — check your connection and try again.';
    } finally {
        loadingRegions.value = false;
    }
}

// Fetch tiers from provider API
async function fetchTiers() {
    if (!selectedProvider.value || !selectedRegion.value) return;

    loadingTiers.value = true;
    tierError.value = '';
    tiers.value = [];
    selectedTier.value = null;

    try {
        const response = await fetch('/settings/cloud-deploy/tiers', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
            },
            body: JSON.stringify({
                provider: selectedProvider.value,
                region: selectedRegion.value.id,
            }),
        });

        const data = await response.json();

        if (!response.ok) {
            tierError.value = data.error || 'Failed to fetch server tiers';
            return;
        }

        tiers.value = data.tiers;
    } catch {
        tierError.value = 'Network error — check your connection and try again.';
    } finally {
        loadingTiers.value = false;
    }
}

// Deploy
async function handleDeploy() {
    if (!selectedProvider.value || !selectedRegion.value || !selectedTier.value) return;

    deploying.value = true;
    deployError.value = '';

    try {
        const response = await fetch('/settings/cloud-deploy', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
            },
            body: JSON.stringify({
                provider: selectedProvider.value,
                region: selectedRegion.value.id,
                tier: selectedTier.value.id,
                monthly_cost: selectedTier.value.monthly_cost,
            }),
        });

        const data = await response.json();

        if (!response.ok) {
            deployError.value = data.error || 'Failed to create server';
            deploying.value = false;
            return;
        }

        deploymentId.value = data.deployment_id;
        deploymentStatus.value = 'provisioning';
        deployedIp.value = data.ip_address;

        // Start polling for status
        startStatusPolling();
    } catch {
        deployError.value = 'Network error — check your connection and try again.';
        deploying.value = false;
    }
}

function startStatusPolling() {
    if (statusPollTimer.value) {
        clearInterval(statusPollTimer.value);
    }

    statusPollTimer.value = setInterval(async () => {
        if (!deploymentId.value) return;

        try {
            const response = await fetch(`/settings/cloud-deploy/${deploymentId.value}/status`, {
                headers: {
                    'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
                },
            });

            if (!response.ok) return;

            const data = await response.json();
            deploymentStatus.value = data.status;
            if (data.ip_address) {
                deployedIp.value = data.ip_address;
            }

            if (data.status === 'active') {
                deploying.value = false;
                deploySuccess.value = true;
                stopStatusPolling();
                successToast('Server deployed', 'Your cloud server is ready.');
            }
        } catch {
            // Silently ignore polling errors
        }
    }, 5000);
}

function stopStatusPolling() {
    if (statusPollTimer.value) {
        clearInterval(statusPollTimer.value);
        statusPollTimer.value = null;
    }
}

function handleRetry() {
    deployError.value = '';
    handleDeploy();
}

function goToDashboard() {
    router.visit('/dashboard');
}

// Cleanup polling on unmount
onBeforeUnmount(() => {
    stopStatusPolling();
});

// Reset downstream selections when upstream changes
watch(selectedProvider, () => {
    selectedRegion.value = null;
    selectedTier.value = null;
    regions.value = [];
    tiers.value = [];
});

watch(selectedRegion, () => {
    selectedTier.value = null;
    tiers.value = [];
});

// Summary
const monthlyCost = computed(() => {
    if (!selectedTier.value) return '—';
    return `$${Number(selectedTier.value.monthly_cost).toFixed(2)}/mo`;
});

const sshCommand = computed(() => {
    if (!deployedIp.value) return '';
    return `ssh chief@${deployedIp.value}`;
});
</script>

<template>
    <AppLayout>
        <Head title="Deploy Server" />

        <h1 class="sr-only">Deploy Cloud Server</h1>

        <SettingsLayout>
            <div class="space-y-6" @keydown="handleKeydown">
                <Heading
                    variant="small"
                    title="Deploy Server"
                    description="Launch a cloud VPS to run Chief without managing infrastructure"
                />

                <!-- Step indicator -->
                <nav aria-label="Wizard progress" class="flex items-center gap-2">
                    <template v-for="step in totalSteps" :key="step">
                        <div
                            class="flex items-center gap-2"
                            :class="{ 'flex-1': step < totalSteps }"
                        >
                            <div
                                class="flex size-8 shrink-0 items-center justify-center rounded-full border-2 text-xs font-medium transition-all duration-[var(--duration-standard)]"
                                :class="[
                                    step < currentStep
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : step === currentStep
                                          ? 'border-primary text-primary'
                                          : 'border-border text-muted-foreground',
                                ]"
                            >
                                <Check v-if="step < currentStep" class="size-4" />
                                <span v-else>{{ step }}</span>
                            </div>
                            <div
                                v-if="step < totalSteps"
                                class="h-px flex-1 transition-colors duration-[var(--duration-standard)]"
                                :class="step < currentStep ? 'bg-primary' : 'bg-border'"
                            />
                        </div>
                    </template>
                </nav>

                <!-- Step content with transitions -->
                <div class="relative min-h-[280px]">
                    <!-- Step 1: Select provider -->
                    <Transition
                        :enter-active-class="`transition-all duration-[var(--duration-standard)] ease-[var(--ease-gentle)]`"
                        :enter-from-class="slideDirection === 'left' ? 'opacity-0 translate-x-4' : 'opacity-0 -translate-x-4'"
                        enter-to-class="opacity-100 translate-x-0"
                        :leave-active-class="`transition-all duration-[var(--duration-standard)] ease-[var(--ease-gentle)]`"
                        leave-from-class="opacity-100 translate-x-0"
                        :leave-to-class="slideDirection === 'left' ? 'opacity-0 -translate-x-4' : 'opacity-0 translate-x-4'"
                    >
                        <div v-if="currentStep === 1" class="space-y-4">
                            <h3 class="text-sm font-medium text-foreground">Select Provider</h3>

                            <div class="grid gap-3 sm:grid-cols-2">
                                <button
                                    v-for="provider in supportedProviders"
                                    :key="provider"
                                    class="focus-ring flex items-center gap-3 rounded-lg border p-4 text-left transition-all duration-[var(--duration-micro)]"
                                    :class="[
                                        selectedProvider === provider
                                            ? 'border-primary bg-primary/5'
                                            : 'border-border hover:border-foreground/20',
                                    ]"
                                    @click="selectProvider(provider)"
                                >
                                    <div
                                        class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground"
                                    >
                                        <Cloud class="size-5" />
                                    </div>
                                    <div>
                                        <span class="font-medium">{{ getProviderName(provider) }}</span>
                                        <p
                                            v-if="hasKeyForProvider(provider)"
                                            class="text-xs text-success"
                                        >
                                            API key configured
                                        </p>
                                        <p
                                            v-else
                                            class="text-xs text-muted-foreground"
                                        >
                                            API key required
                                        </p>
                                    </div>
                                    <div
                                        v-if="selectedProvider === provider"
                                        class="ml-auto"
                                    >
                                        <Check class="size-5 text-primary" />
                                    </div>
                                </button>
                            </div>

                            <!-- Inline API key form -->
                            <Transition
                                enter-active-class="transition-all duration-[var(--duration-standard)] ease-[var(--ease-gentle)]"
                                enter-from-class="opacity-0 max-h-0"
                                enter-to-class="opacity-100 max-h-96"
                                leave-active-class="transition-all duration-[var(--duration-micro)] ease-[var(--ease-gentle)]"
                                leave-from-class="opacity-100 max-h-96"
                                leave-to-class="opacity-0 max-h-0"
                            >
                                <div
                                    v-if="showApiKeyForm && selectedProvider"
                                    class="overflow-hidden rounded-lg border border-border p-4"
                                >
                                    <form class="space-y-3" @submit.prevent="submitApiKey">
                                        <p class="text-sm text-muted-foreground">
                                            {{ getProviderDescription(selectedProvider) }}
                                        </p>

                                        <div class="space-y-2">
                                            <label
                                                for="wizard-api-key"
                                                class="text-sm font-medium text-foreground"
                                            >
                                                API Key
                                            </label>
                                            <div class="relative">
                                                <input
                                                    id="wizard-api-key"
                                                    v-model="apiKeyForm.api_key"
                                                    :type="showApiKey ? 'text' : 'password'"
                                                    placeholder="Paste your API key"
                                                    autocomplete="off"
                                                    :class="[
                                                        'border-input bg-background focus-visible:border-ring focus-visible:ring-ring/50 h-9 w-full rounded-md border px-3 pr-10 text-sm font-mono shadow-xs outline-none transition-[color,box-shadow] focus-visible:ring-[3px]',
                                                        apiKeyForm.errors.api_key && 'border-destructive ring-destructive/20 ring-[3px]',
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
                                                v-if="apiKeyForm.errors.api_key"
                                                class="text-sm text-destructive"
                                                role="alert"
                                            >
                                                {{ apiKeyForm.errors.api_key }}
                                            </p>
                                            <p
                                                v-if="apiKeyForm.errors.provider"
                                                class="text-sm text-destructive"
                                                role="alert"
                                            >
                                                {{ apiKeyForm.errors.provider }}
                                            </p>
                                        </div>

                                        <div class="flex items-center gap-2">
                                            <Button
                                                type="submit"
                                                :disabled="apiKeyForm.processing || !apiKeyForm.api_key"
                                                size="sm"
                                            >
                                                <Loader2
                                                    v-if="apiKeyForm.processing"
                                                    class="size-4 animate-spin"
                                                />
                                                {{ apiKeyForm.processing ? 'Validating...' : 'Validate & Save' }}
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                :disabled="apiKeyForm.processing"
                                                @click="cancelApiKey"
                                            >
                                                Cancel
                                            </Button>
                                        </div>
                                    </form>
                                </div>
                            </Transition>
                        </div>
                    </Transition>

                    <!-- Step 2: Select region -->
                    <Transition
                        :enter-active-class="`transition-all duration-[var(--duration-standard)] ease-[var(--ease-gentle)]`"
                        :enter-from-class="slideDirection === 'left' ? 'opacity-0 translate-x-4' : 'opacity-0 -translate-x-4'"
                        enter-to-class="opacity-100 translate-x-0"
                        :leave-active-class="`transition-all duration-[var(--duration-standard)] ease-[var(--ease-gentle)]`"
                        leave-from-class="opacity-100 translate-x-0"
                        :leave-to-class="slideDirection === 'left' ? 'opacity-0 -translate-x-4' : 'opacity-0 translate-x-4'"
                    >
                        <div v-if="currentStep === 2" class="space-y-4">
                            <h3 class="text-sm font-medium text-foreground">Select Region</h3>

                            <!-- Loading -->
                            <div v-if="loadingRegions" class="flex items-center gap-2 py-8 text-sm text-muted-foreground">
                                <Spinner class="size-4" />
                                <span>Fetching available regions...</span>
                            </div>

                            <!-- Error -->
                            <div v-else-if="regionError" class="rounded-md border border-destructive/30 bg-destructive/5 p-3">
                                <p class="text-sm text-destructive">{{ regionError }}</p>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="mt-2"
                                    @click="fetchRegions"
                                >
                                    Retry
                                </Button>
                            </div>

                            <!-- Region list -->
                            <div v-else class="grid gap-2">
                                <button
                                    v-for="region in regions"
                                    :key="region.id"
                                    class="focus-ring flex items-center gap-3 rounded-lg border p-3 text-left transition-all duration-[var(--duration-micro)]"
                                    :class="[
                                        selectedRegion?.id === region.id
                                            ? 'border-primary bg-primary/5'
                                            : 'border-border hover:border-foreground/20',
                                    ]"
                                    @click="selectedRegion = region"
                                >
                                    <MapPin class="size-4 shrink-0 text-muted-foreground" />
                                    <div class="flex-1">
                                        <span class="text-sm font-medium">{{ region.name }}</span>
                                        <span class="ml-2 text-xs text-muted-foreground">{{ region.id }}</span>
                                    </div>
                                    <Check
                                        v-if="selectedRegion?.id === region.id"
                                        class="size-4 text-primary"
                                    />
                                </button>
                            </div>
                        </div>
                    </Transition>

                    <!-- Step 3: Select tier -->
                    <Transition
                        :enter-active-class="`transition-all duration-[var(--duration-standard)] ease-[var(--ease-gentle)]`"
                        :enter-from-class="slideDirection === 'left' ? 'opacity-0 translate-x-4' : 'opacity-0 -translate-x-4'"
                        enter-to-class="opacity-100 translate-x-0"
                        :leave-active-class="`transition-all duration-[var(--duration-standard)] ease-[var(--ease-gentle)]`"
                        leave-from-class="opacity-100 translate-x-0"
                        :leave-to-class="slideDirection === 'left' ? 'opacity-0 -translate-x-4' : 'opacity-0 translate-x-4'"
                    >
                        <div v-if="currentStep === 3" class="space-y-4">
                            <h3 class="text-sm font-medium text-foreground">Select Server Tier</h3>

                            <!-- Loading -->
                            <div v-if="loadingTiers" class="flex items-center gap-2 py-8 text-sm text-muted-foreground">
                                <Spinner class="size-4" />
                                <span>Fetching available tiers...</span>
                            </div>

                            <!-- Error -->
                            <div v-else-if="tierError" class="rounded-md border border-destructive/30 bg-destructive/5 p-3">
                                <p class="text-sm text-destructive">{{ tierError }}</p>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="mt-2"
                                    @click="fetchTiers"
                                >
                                    Retry
                                </Button>
                            </div>

                            <!-- Tier list -->
                            <div v-else class="grid gap-2">
                                <button
                                    v-for="tier in tiers"
                                    :key="tier.id"
                                    class="focus-ring relative flex items-center gap-3 rounded-lg border p-3 text-left transition-all duration-[var(--duration-micro)]"
                                    :class="[
                                        selectedTier?.id === tier.id
                                            ? 'border-primary bg-primary/5'
                                            : 'border-border hover:border-foreground/20',
                                    ]"
                                    @click="selectedTier = tier"
                                >
                                    <Server class="size-4 shrink-0 text-muted-foreground" />
                                    <div class="flex flex-1 flex-wrap items-center gap-x-4 gap-y-1">
                                        <span class="text-sm font-medium">{{ tier.name }}</span>
                                        <div class="flex items-center gap-3 text-xs text-muted-foreground">
                                            <span>{{ tier.cpu }} vCPU</span>
                                            <span>{{ tier.ram }}</span>
                                            <span>{{ tier.disk }}</span>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <Badge
                                            v-if="tier.recommended"
                                            variant="secondary"
                                            class="text-xs"
                                        >
                                            Recommended
                                        </Badge>
                                        <span class="text-sm font-medium text-foreground whitespace-nowrap">
                                            ${{ Number(tier.monthly_cost).toFixed(2) }}/mo
                                        </span>
                                    </div>
                                    <Check
                                        v-if="selectedTier?.id === tier.id"
                                        class="size-4 shrink-0 text-primary"
                                    />
                                </button>
                            </div>
                        </div>
                    </Transition>

                    <!-- Step 4: Confirm & Deploy -->
                    <Transition
                        :enter-active-class="`transition-all duration-[var(--duration-standard)] ease-[var(--ease-gentle)]`"
                        :enter-from-class="slideDirection === 'left' ? 'opacity-0 translate-x-4' : 'opacity-0 -translate-x-4'"
                        enter-to-class="opacity-100 translate-x-0"
                        :leave-active-class="`transition-all duration-[var(--duration-standard)] ease-[var(--ease-gentle)]`"
                        leave-from-class="opacity-100 translate-x-0"
                        :leave-to-class="slideDirection === 'left' ? 'opacity-0 -translate-x-4' : 'opacity-0 translate-x-4'"
                    >
                        <div v-if="currentStep === 4" class="space-y-4">
                            <!-- Deploy success -->
                            <template v-if="deploySuccess">
                                <div class="space-y-4 text-center">
                                    <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-success/10">
                                        <Check class="size-6 text-success" />
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-semibold">Server deployed!</h3>
                                        <p class="text-sm text-muted-foreground">
                                            Your server will auto-connect and appear in the dashboard.
                                        </p>
                                    </div>

                                    <div
                                        v-if="deployedIp"
                                        class="rounded-lg border border-border bg-muted/50 p-4 text-left"
                                    >
                                        <p class="mb-2 text-xs text-muted-foreground">SSH Access</p>
                                        <div class="flex items-center justify-between gap-2">
                                            <code class="text-sm font-mono">{{ sshCommand }}</code>
                                            <CopyButton :text="sshCommand" />
                                        </div>
                                    </div>

                                    <Button class="w-full" @click="goToDashboard">
                                        <Rocket class="size-4" />
                                        Go to Dashboard
                                    </Button>
                                </div>
                            </template>

                            <!-- Provisioning in progress -->
                            <template v-else-if="deploying">
                                <div class="space-y-4 text-center py-4">
                                    <Spinner class="mx-auto size-8" />
                                    <div>
                                        <h3 class="font-medium">Provisioning...</h3>
                                        <p class="text-sm text-muted-foreground">
                                            This typically takes about 60 seconds.
                                        </p>
                                    </div>
                                    <ProgressBar
                                        :value="deploymentStatus === 'provisioning' ? 60 : 100"
                                        :max="100"
                                        class="h-1.5"
                                        indicator-class="transition-all duration-1000"
                                        :indeterminate="deploymentStatus === 'provisioning'"
                                    />
                                    <p
                                        v-if="deployedIp"
                                        class="text-xs text-muted-foreground"
                                    >
                                        IP: {{ deployedIp }}
                                    </p>
                                </div>
                            </template>

                            <!-- Deploy error -->
                            <template v-else-if="deployError">
                                <div class="space-y-3">
                                    <div class="rounded-md border border-destructive/30 bg-destructive/5 p-3">
                                        <p class="text-sm text-destructive">{{ deployError }}</p>
                                    </div>
                                    <Button variant="outline" size="sm" @click="handleRetry">
                                        Retry
                                    </Button>
                                </div>
                            </template>

                            <!-- Confirmation summary -->
                            <template v-else>
                                <h3 class="text-sm font-medium text-foreground">Confirm Deployment</h3>

                                <div class="rounded-lg border border-border overflow-hidden">
                                    <div class="divide-y divide-border">
                                        <div class="flex items-center justify-between p-3">
                                            <span class="text-sm text-muted-foreground">Provider</span>
                                            <span class="text-sm font-medium">
                                                {{ selectedProvider ? getProviderName(selectedProvider) : '—' }}
                                            </span>
                                        </div>
                                        <div class="flex items-center justify-between p-3">
                                            <span class="text-sm text-muted-foreground">Region</span>
                                            <span class="text-sm font-medium">
                                                {{ selectedRegion?.name ?? '—' }}
                                            </span>
                                        </div>
                                        <div class="flex items-center justify-between p-3">
                                            <span class="text-sm text-muted-foreground">Server</span>
                                            <div class="text-right text-sm">
                                                <span class="font-medium">{{ selectedTier?.name ?? '—' }}</span>
                                                <p class="text-xs text-muted-foreground">
                                                    {{ selectedTier?.cpu }} vCPU · {{ selectedTier?.ram }} · {{ selectedTier?.disk }}
                                                </p>
                                            </div>
                                        </div>
                                        <div class="flex items-center justify-between bg-muted/30 p-3">
                                            <span class="text-sm font-medium">Monthly Cost</span>
                                            <span class="text-sm font-semibold text-primary">
                                                {{ monthlyCost }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </Transition>
                </div>

                <!-- Navigation buttons -->
                <div
                    v-if="!deploySuccess && !deploying"
                    class="flex items-center justify-between border-t border-border pt-4"
                >
                    <Button
                        v-if="currentStep > 1 && !deployError"
                        variant="outline"
                        size="sm"
                        @click="prevStep"
                    >
                        <ArrowLeft class="size-4" />
                        Back
                    </Button>
                    <Button
                        v-else
                        variant="ghost"
                        size="sm"
                        as-child
                    >
                        <a href="/settings/cloud-servers">Cancel</a>
                    </Button>

                    <div class="flex items-center gap-2">
                        <Button
                            v-if="currentStep < totalSteps"
                            :disabled="!canProceed"
                            size="sm"
                            :title="!canProceed ? 'Complete this step first' : undefined"
                            @click="nextStep"
                        >
                            Next
                            <ArrowRight class="size-4" />
                        </Button>

                        <Button
                            v-else-if="!deployError"
                            :disabled="!canProceed"
                            size="sm"
                            @click="handleDeploy"
                        >
                            <Rocket class="size-4" />
                            Deploy
                        </Button>
                    </div>
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
