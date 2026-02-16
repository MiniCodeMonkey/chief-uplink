import { ref } from 'vue';

export type PushPermissionState =
    | 'default'
    | 'granted'
    | 'denied'
    | 'unsupported';

const permission = ref<PushPermissionState>(getInitialPermission());
const isSubscribed = ref(false);

function getInitialPermission(): PushPermissionState {
    if (
        typeof window === 'undefined' ||
        !('Notification' in window) ||
        !('serviceWorker' in navigator)
    ) {
        return 'unsupported';
    }
    return Notification.permission as PushPermissionState;
}

function urlBase64ToUint8Array(base64String: string): Uint8Array {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding)
        .replace(/-/g, '+')
        .replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
}

async function getRegistration(): Promise<ServiceWorkerRegistration | null> {
    if (!('serviceWorker' in navigator)) return null;
    try {
        return await navigator.serviceWorker.ready;
    } catch {
        return null;
    }
}

async function checkExistingSubscription(): Promise<void> {
    const registration = await getRegistration();
    if (!registration) return;
    const subscription = await registration.pushManager.getSubscription();
    isSubscribed.value = subscription !== null;
}

export function usePushNotifications() {
    // Check existing subscription on first use
    checkExistingSubscription();

    async function subscribe(): Promise<boolean> {
        if (permission.value === 'unsupported') return false;

        try {
            const result = await Notification.requestPermission();
            permission.value = result as PushPermissionState;

            if (result !== 'granted') return false;

            const registration = await getRegistration();
            if (!registration) return false;

            const vapidPublicKey = import.meta.env.VITE_VAPID_PUBLIC_KEY;
            if (!vapidPublicKey) {
                console.warn('VAPID public key not configured');
                return false;
            }

            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(vapidPublicKey),
            });

            const subscriptionJson = subscription.toJSON();

            // Send to backend
            const csrfToken = document.querySelector<HTMLMetaElement>(
                'meta[name="csrf-token"]',
            )?.content;
            const response = await fetch('/settings/push-subscription', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken || '',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    endpoint: subscriptionJson.endpoint,
                    keys: {
                        p256dh: subscriptionJson.keys?.p256dh,
                        auth: subscriptionJson.keys?.auth,
                    },
                    contentEncoding:
                        (subscriptionJson as Record<string, unknown>)
                            .contentEncoding || 'aesgcm',
                }),
            });

            if (response.ok) {
                isSubscribed.value = true;
                return true;
            }

            return false;
        } catch (error) {
            console.error('Push subscription failed:', error);
            return false;
        }
    }

    async function unsubscribe(): Promise<boolean> {
        try {
            const registration = await getRegistration();
            if (!registration) return false;

            const subscription =
                await registration.pushManager.getSubscription();
            if (!subscription) {
                isSubscribed.value = false;
                return true;
            }

            const endpoint = subscription.endpoint;
            await subscription.unsubscribe();

            // Remove from backend
            const csrfToken = document.querySelector<HTMLMetaElement>(
                'meta[name="csrf-token"]',
            )?.content;
            await fetch('/settings/push-subscription', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken || '',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ endpoint }),
            });

            isSubscribed.value = false;
            return true;
        } catch (error) {
            console.error('Push unsubscribe failed:', error);
            return false;
        }
    }

    return {
        permission,
        isSubscribed,
        subscribe,
        unsubscribe,
    };
}
