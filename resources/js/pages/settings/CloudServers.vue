<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Cloud, Eye, EyeOff, KeyRound, Loader2, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { EmptyState } from '@/components/ui/empty-state';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';

type ProviderKey = {
    id: number;
    provider: string;
    masked_key: string;
    account_name: string | null;
    created_at: string | null;
};

const props = defineProps<{
    providerKeys: ProviderKey[];
    supportedProviders: string[];
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
</script>

<template>
    <AppLayout>
        <Head title="Cloud servers" />

        <h1 class="sr-only">Cloud Server Settings</h1>

        <SettingsLayout>
            <div class="space-y-6">
                <Heading
                    variant="small"
                    title="Cloud Servers"
                    description="Manage your cloud provider API keys for deploying servers"
                />

                <!-- Success flash message -->
                <Transition
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="opacity-0 -translate-y-1"
                    leave-active-class="transition duration-150 ease-in"
                    leave-to-class="opacity-0 -translate-y-1"
                >
                    <p
                        v-if="flash.success"
                        class="text-sm text-success"
                        role="status"
                    >
                        {{ flash.success }}
                    </p>
                </Transition>

                <!-- Provider sections -->
                <div class="space-y-4">
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
                                <form
                                    class="space-y-4 p-4"
                                    @submit.prevent="submitKey"
                                >
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
                                                    form.errors.api_key && 'border-destructive ring-destructive/20 ring-[3px]',
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
                                            <Loader2
                                                v-if="form.processing"
                                                class="size-4 animate-spin"
                                            />
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

                <!-- Empty state when no providers configured -->
                <EmptyState
                    v-if="providerKeys.length === 0 && !showAddForm"
                    :icon="KeyRound"
                    title="No API keys configured"
                    description="Add a cloud provider API key to deploy and manage servers."
                />
            </div>

            <!-- Confirm remove dialog -->
            <ConfirmDialog
                v-model:open="showRemoveDialog"
                title="Remove API key"
                :description="`This will prevent you from managing existing cloud servers through ${removeTarget ? getProviderName(removeTarget.provider) : 'this provider'}.`"
                confirm-label="Remove"
                variant="destructive"
                @confirm="confirmRemove"
                @cancel="cancelRemove"
            />
        </SettingsLayout>
    </AppLayout>
</template>
