<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Toggle } from '@/components/ui/toggle';
import { usePushNotifications } from '@/composables/usePushNotifications';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';

const page = usePage();
const user = computed(
    () => page.props.auth.user as Record<string, unknown> | null,
);
const hasEmail = computed(() => !!user.value?.email);
const emailEnabled = ref(
    ((user.value?.notification_preferences as Record<string, boolean>) ?? {})
        .email ?? true,
);

const { permission, isSubscribed, subscribe, unsubscribe } =
    usePushNotifications();

const isLoading = ref(false);
const isEmailLoading = ref(false);

const isSupported = computed(() => permission.value !== 'unsupported');
const isDenied = computed(() => permission.value === 'denied');

async function handleToggle() {
    isLoading.value = true;
    try {
        if (isSubscribed.value) {
            await unsubscribe();
        } else {
            await subscribe();
        }
    } finally {
        isLoading.value = false;
    }
}

async function handleEmailToggle() {
    isEmailLoading.value = true;
    const newValue = !emailEnabled.value;
    try {
        const csrfToken = document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content');

        const response = await fetch('/settings/notification-preferences', {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken || '',
                Accept: 'application/json',
            },
            body: JSON.stringify({ email: newValue }),
        });

        if (response.ok) {
            emailEnabled.value = newValue;
        }
    } finally {
        isEmailLoading.value = false;
    }
}
</script>

<template>
    <AppLayout>
        <Head title="Preferences" />

        <h1 class="sr-only">Preferences</h1>

        <SettingsLayout>
            <div class="space-y-6">
                <Heading
                    variant="small"
                    title="Notifications"
                    description="Configure how you want to be notified about important events"
                />

                <div class="space-y-4">
                    <div
                        class="flex items-center justify-between rounded-lg border border-border p-4"
                    >
                        <div class="space-y-1">
                            <p class="font-medium text-foreground">
                                Push notifications
                            </p>
                            <p class="text-sm text-muted-foreground">
                                Get notified in your browser when runs complete,
                                fail, or pause
                            </p>
                        </div>
                        <div v-if="isSupported && !isDenied">
                            <Toggle
                                :model-value="isSubscribed"
                                :disabled="isLoading"
                                aria-label="Toggle push notifications"
                                @update:model-value="handleToggle"
                            />
                        </div>
                    </div>

                    <div
                        class="flex items-center justify-between rounded-lg border border-border p-4"
                    >
                        <div class="space-y-1">
                            <p class="font-medium text-foreground">
                                Email notifications
                            </p>
                            <p class="text-sm text-muted-foreground">
                                Receive email digests when runs complete, fail,
                                or when servers go offline
                            </p>
                        </div>
                        <div>
                            <Toggle
                                :model-value="emailEnabled"
                                :disabled="isEmailLoading || !hasEmail"
                                aria-label="Toggle email notifications"
                                @update:model-value="handleEmailToggle"
                            />
                        </div>
                    </div>

                    <div
                        v-if="!hasEmail"
                        class="rounded-lg border border-border bg-muted/50 p-4"
                    >
                        <p class="text-sm text-muted-foreground">
                            Add an email address in your
                            <a
                                href="/settings/profile"
                                class="text-primary underline underline-offset-4 hover:text-primary/80"
                                >account settings</a
                            >
                            to receive email notifications.
                        </p>
                    </div>

                    <div
                        v-if="isDenied"
                        class="rounded-lg border border-destructive/50 bg-destructive/10 p-4"
                    >
                        <p class="text-sm font-medium text-destructive">
                            Push notifications are blocked
                        </p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Your browser has blocked push notifications for this
                            site. To re-enable them, click the lock icon in your
                            browser's address bar, find "Notifications", and
                            change it to "Allow".
                        </p>
                    </div>

                    <div
                        v-if="!isSupported"
                        class="rounded-lg border border-border bg-muted/50 p-4"
                    >
                        <p class="text-sm text-muted-foreground">
                            Push notifications are not supported in this
                            browser.
                        </p>
                    </div>

                    <div
                        class="rounded-lg border border-border bg-muted/30 p-4"
                    >
                        <p
                            class="mb-2 text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            You'll be notified when:
                        </p>
                        <ul class="space-y-1 text-sm text-muted-foreground">
                            <li>A run completes successfully</li>
                            <li>A run fails</li>
                            <li>A run is paused (quota exhausted)</li>
                            <li>A server goes offline unexpectedly</li>
                        </ul>
                    </div>
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
