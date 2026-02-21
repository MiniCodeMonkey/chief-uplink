<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { verify, authorize, deny } from '@/routes/oauth/device';

const props = defineProps<{
    confirmDevice?: {
        device_name: string;
        user_code: string;
    };
}>();

const page = usePage();
const successDevice = computed(() => page.props.flash?.success as string | undefined);

const codeInput = ref<InstanceType<typeof Input> | null>(null);

const verifyForm = useForm({
    user_code: props.confirmDevice?.user_code ?? '',
});

const authorizeForm = useForm({
    user_code: props.confirmDevice?.user_code ?? '',
});

const denyForm = useForm({
    user_code: props.confirmDevice?.user_code ?? '',
});

const showSuccess = ref(false);

// Keep form data in sync with props when Inertia reuses the component instance
watch(() => props.confirmDevice, (device) => {
    if (device) {
        authorizeForm.user_code = device.user_code;
        denyForm.user_code = device.user_code;
    }
}, { immediate: true });

// Handle flash data arriving via Inertia prop updates (not just on mount)
watch(successDevice, (val) => {
    if (val) showSuccess.value = true;
});

function formatCode(value: string): string {
    // Remove anything that's not alphanumeric
    const clean = value.replace(/[^A-Za-z0-9]/g, '').toUpperCase();
    // Insert hyphen after 4 chars
    if (clean.length > 4) {
        return clean.slice(0, 4) + '-' + clean.slice(4, 8);
    }
    return clean;
}

function onInput(event: Event) {
    const input = event.target as HTMLInputElement;
    const cursorPos = input.selectionStart ?? 0;
    const oldValue = input.value;
    const formatted = formatCode(input.value);

    verifyForm.user_code = formatted;

    // Adjust cursor position after formatting
    nextTick(() => {
        const el = codeInput.value?.$el as HTMLInputElement | undefined;
        if (el) {
            let newPos = cursorPos;
            // If we just typed the 4th character, skip past the hyphen
            if (cursorPos === 5 && formatted.length >= 5 && oldValue.length < formatted.length) {
                newPos = 6;
            }
            // Ensure cursor doesn't exceed length
            newPos = Math.min(newPos, formatted.length);
            el.setSelectionRange(newPos, newPos);
        }
    });
}

function onPaste(event: ClipboardEvent) {
    event.preventDefault();
    const pasted = event.clipboardData?.getData('text') ?? '';
    const formatted = formatCode(pasted);
    verifyForm.user_code = formatted;
}

function submitCode() {
    verifyForm.post(verify.url(), {
        preserveScroll: true,
    });
}

function authorizeDevice() {
    authorizeForm.post(authorize.url());
}

function denyDevice() {
    denyForm.post(deny.url());
}

onMounted(() => {
    if (successDevice.value) {
        showSuccess.value = true;
    } else if (!props.confirmDevice) {
        (codeInput.value?.$el as HTMLInputElement | undefined)?.focus();
    }
});
</script>

<template>
    <Head title="Authorize Device" />

    <div class="relative flex min-h-svh flex-col items-center justify-center overflow-hidden bg-background p-6 md:p-10">
        <div class="relative z-10 w-full max-w-sm">
            <div class="flex flex-col items-center gap-8">
                <!-- Logo and heading -->
                <div class="flex flex-col items-center gap-4">
                    <div class="mb-1 flex h-12 w-12 items-center justify-center">
                        <AppLogoIcon class="size-12 fill-current text-foreground" />
                    </div>
                    <div class="space-y-2 text-center">
                        <h1 class="text-2xl font-semibold tracking-tight text-foreground">
                            {{ confirmDevice ? 'Authorize Device' : 'Enter Device Code' }}
                        </h1>
                        <p class="text-sm text-muted-foreground">
                            {{
                                confirmDevice
                                    ? 'Confirm that you want to authorize this device.'
                                    : 'Enter the code shown by your chief CLI to authorize the device.'
                            }}
                        </p>
                    </div>
                </div>

                <!-- Success state -->
                <div
                    v-if="showSuccess"
                    class="w-full rounded-lg border border-border bg-card p-6"
                >
                    <div class="flex flex-col items-center gap-4 text-center">
                        <div class="flex size-12 items-center justify-center rounded-full bg-success/10">
                            <svg
                                class="size-6 text-success"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2.5"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <polyline points="20 6 9 17 4 12" />
                            </svg>
                        </div>
                        <div class="space-y-1">
                            <h2 class="text-lg font-semibold text-foreground">Device Authorized</h2>
                            <p class="text-sm text-muted-foreground">
                                <span class="font-medium text-foreground">{{ successDevice }}</span> has been authorized successfully.
                            </p>
                        </div>
                        <div class="mt-2 w-full rounded-md border border-border bg-background p-3">
                            <p class="text-sm text-muted-foreground">
                                Return to your terminal and run:
                            </p>
                            <code class="mt-1 block text-sm font-semibold text-foreground">chief serve</code>
                        </div>
                    </div>
                </div>

                <!-- Confirmation state -->
                <div
                    v-else-if="confirmDevice"
                    class="w-full rounded-lg border border-border bg-card p-6"
                >
                    <div class="flex flex-col gap-4">
                        <div class="rounded-md border border-border bg-background p-4">
                            <div class="flex items-center gap-3">
                                <div class="flex size-10 flex-shrink-0 items-center justify-center rounded-full bg-muted">
                                    <svg
                                        class="size-5 text-muted-foreground"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        aria-hidden="true"
                                    >
                                        <rect x="2" y="3" width="20" height="14" rx="2" ry="2" />
                                        <line x1="8" y1="21" x2="16" y2="21" />
                                        <line x1="12" y1="17" x2="12" y2="21" />
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-foreground">{{ confirmDevice.device_name }}</p>
                                    <p class="text-xs text-muted-foreground">Code: {{ confirmDevice.user_code }}</p>
                                </div>
                            </div>
                        </div>

                        <p class="text-sm text-muted-foreground">
                            Authorize this device to connect to your Chief account?
                        </p>

                        <div class="flex gap-3">
                            <Button
                                class="flex-1"
                                variant="secondary"
                                :disabled="denyForm.processing || authorizeForm.processing"
                                @click="denyDevice"
                            >
                                <Spinner v-if="denyForm.processing" />
                                Deny
                            </Button>
                            <Button
                                class="flex-1"
                                :disabled="authorizeForm.processing || denyForm.processing"
                                @click="authorizeDevice"
                            >
                                <Spinner v-if="authorizeForm.processing" />
                                {{ authorizeForm.processing ? 'Authorizing...' : 'Authorize' }}
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- Code entry state -->
                <div
                    v-else
                    class="w-full rounded-lg border border-border bg-card p-6"
                >
                    <form class="space-y-4" @submit.prevent="submitCode">
                        <div class="grid gap-2">
                            <Label for="user_code">Device code</Label>
                            <Input
                                id="user_code"
                                ref="codeInput"
                                :model-value="verifyForm.user_code"
                                type="text"
                                placeholder="XXXX-XXXX"
                                autocomplete="off"
                                autocapitalize="characters"
                                spellcheck="false"
                                maxlength="9"
                                class="text-center font-mono text-2xl tracking-widest uppercase"
                                :aria-invalid="!!verifyForm.errors.user_code"
                                data-test="device-code-input"
                                @input="onInput"
                                @paste="onPaste"
                            />
                            <InputError :message="verifyForm.errors.user_code" />
                        </div>

                        <Button
                            type="submit"
                            class="w-full"
                            size="lg"
                            :disabled="verifyForm.processing || verifyForm.user_code.length !== 9"
                            data-test="device-code-submit"
                        >
                            <Spinner v-if="verifyForm.processing" />
                            {{ verifyForm.processing ? 'Verifying...' : 'Continue' }}
                        </Button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>
