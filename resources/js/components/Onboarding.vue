<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import {
    Bell,
    Check,
    Cloud,
    Terminal,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { CopyButton } from '@/components/ui/copy-button';
import { StatusDot } from '@/components/ui/status-dot';
import { usePushNotifications } from '@/composables/usePushNotifications';
import type { DeviceSummary } from '@/types';

const page = usePage();
const { permission, isSubscribed, subscribe } = usePushNotifications();

const showCelebration = ref(false);
const celebrationDone = ref(false);
const notificationDismissed = ref(false);

const chiefLoginCommand = 'chief login';

const devices = computed(
    () => (page.props.devices as (DeviceSummary & { projects: unknown[] })[]) || [],
);

const hasDevices = computed(() => devices.value.length > 0);

// Show push notification prompt when supported and not yet decided
const showNotificationPrompt = computed(
    () =>
        !notificationDismissed.value &&
        permission.value !== 'unsupported' &&
        permission.value !== 'denied' &&
        !isSubscribed.value,
);

// Watch for a device connecting — trigger celebration
watch(hasDevices, (newValue) => {
    if (newValue && !celebrationDone.value) {
        showCelebration.value = true;
        celebrationDone.value = true;

        // After celebration animation, navigate to dashboard
        setTimeout(() => {
            router.reload();
        }, 2000);
    }
});

async function enableNotifications() {
    await subscribe();
    notificationDismissed.value = true;
}

function dismissNotifications() {
    notificationDismissed.value = true;
}
</script>

<template>
    <div class="relative flex flex-1 flex-col items-center justify-center p-4">
        <!-- Celebration overlay -->
        <Transition
            enter-active-class="transition-all duration-500 ease-[var(--ease-gentle)]"
            enter-from-class="opacity-0 scale-90"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition-all duration-300 ease-[var(--ease-snappy)]"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="showCelebration"
                class="absolute inset-0 z-10 flex items-center justify-center bg-background/80 backdrop-blur-sm"
            >
                <div class="flex flex-col items-center gap-4 text-center">
                    <div
                        class="celebration-icon flex size-16 items-center justify-center rounded-full bg-success/20 text-success"
                    >
                        <Check class="size-8" />
                    </div>
                    <div class="space-y-1.5">
                        <h2 class="text-xl font-semibold">
                            Device connected!
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            Taking you to your dashboard...
                        </p>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- Onboarding content -->
        <div class="w-full max-w-lg space-y-8">
            <!-- Welcome header -->
            <div class="space-y-2 text-center">
                <h1 class="text-2xl font-semibold tracking-tight">
                    Welcome to Chief Uplink
                </h1>
                <p class="text-sm text-muted-foreground">
                    Connect a device to start managing your projects remotely.
                </p>
            </div>

            <!-- Step 1: chief login command -->
            <div
                class="onboarding-card rounded-lg border border-border bg-card p-5"
            >
                <div class="flex items-start gap-3">
                    <div
                        class="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary"
                    >
                        <Terminal class="size-4" />
                    </div>
                    <div class="min-w-0 flex-1 space-y-3">
                        <div class="space-y-1">
                            <h3 class="text-sm font-medium">
                                Connect your machine
                            </h3>
                            <p class="text-xs text-muted-foreground">
                                Run this on your machine or VPS. Chief will
                                show a code to enter here.
                            </p>
                        </div>

                        <!-- Command box -->
                        <div
                            class="flex items-center justify-between gap-2 rounded-md border border-border bg-muted/50 px-3 py-2"
                        >
                            <code class="font-mono text-sm">
                                {{ chiefLoginCommand }}
                            </code>
                            <CopyButton
                                :value="chiefLoginCommand"
                                label="Copy"
                                class="shrink-0"
                            />
                        </div>

                        <p class="text-[11px] text-muted-foreground/70">
                            Credentials are stored in
                            <code
                                class="rounded bg-muted px-1 py-0.5 font-mono text-[11px]"
                                >~/.chief/credentials.yaml</code
                            >
                        </p>
                    </div>
                </div>
            </div>

            <!-- Step 2: Cloud deploy alternative -->
            <div
                class="onboarding-card rounded-lg border border-border bg-card p-5"
                style="animation-delay: 100ms"
            >
                <div class="flex items-start gap-3">
                    <div
                        class="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary"
                    >
                        <Cloud class="size-4" />
                    </div>
                    <div class="min-w-0 flex-1 space-y-3">
                        <div class="space-y-1">
                            <h3 class="text-sm font-medium">
                                Or deploy a cloud server
                            </h3>
                            <p class="text-xs text-muted-foreground">
                                Spin up a VPS on Hetzner or DigitalOcean — no
                                SSH setup required.
                            </p>
                        </div>
                        <Button
                            variant="outline"
                            size="sm"
                            as-child
                        >
                            <a href="/settings/cloud-deploy">
                                <Cloud class="size-4" />
                                Deploy Server
                            </a>
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Notification opt-in -->
            <Transition
                enter-active-class="transition-all duration-[var(--duration-standard)] ease-[var(--ease-gentle)]"
                enter-from-class="opacity-0 translate-y-2"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition-all duration-[var(--duration-micro)] ease-[var(--ease-snappy)]"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 -translate-y-1"
            >
                <div
                    v-if="showNotificationPrompt"
                    class="onboarding-card rounded-lg border border-border bg-card p-5"
                    style="animation-delay: 200ms"
                >
                    <div class="flex items-start gap-3">
                        <div
                            class="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary"
                        >
                            <Bell class="size-4" />
                        </div>
                        <div class="min-w-0 flex-1 space-y-3">
                            <div class="space-y-1">
                                <h3 class="text-sm font-medium">
                                    Want to get notified when runs complete?
                                </h3>
                                <p class="text-xs text-muted-foreground">
                                    Get push notifications so you can step
                                    away while Chief works.
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <Button
                                    size="sm"
                                    @click="enableNotifications"
                                >
                                    <Bell class="size-4" />
                                    Enable
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    @click="dismissNotifications"
                                >
                                    Not now
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>

            <!-- Waiting indicator -->
            <div
                class="flex flex-col items-center gap-3 pt-2 text-center"
            >
                <div class="flex items-center gap-2 text-sm text-muted-foreground">
                    <StatusDot state="reconnecting" class="size-2" />
                    <span>Waiting for connection...</span>
                </div>
                <p class="text-xs text-muted-foreground/60">
                    This page updates automatically when a device connects.
                </p>
                <a
                    href="https://chiefloop.com/docs/getting-started"
                    target="_blank"
                    rel="noopener"
                    class="text-xs text-muted-foreground underline underline-offset-2 hover:text-foreground"
                >
                    Read the documentation
                </a>
            </div>
        </div>
    </div>
</template>

<style scoped>
.onboarding-card {
    animation: card-enter var(--duration-slow) var(--ease-gentle) both;
}

@keyframes card-enter {
    from {
        opacity: 0;
        transform: translateY(8px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.celebration-icon {
    animation: celebration-pulse 600ms var(--ease-gentle);
}

@keyframes celebration-pulse {
    0% {
        transform: scale(0);
        opacity: 0;
    }
    50% {
        transform: scale(1.2);
    }
    100% {
        transform: scale(1);
        opacity: 1;
    }
}

@media (prefers-reduced-motion: reduce) {
    .onboarding-card {
        animation: none;
    }

    .celebration-icon {
        animation: none;
    }
}
</style>
