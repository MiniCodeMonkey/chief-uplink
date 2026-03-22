import { ref, onMounted } from 'vue';

/**
 * Composable for managing browser push notification subscriptions.
 *
 * Handles service worker registration, push subscription management,
 * and syncing subscriptions with the server.
 */
export function usePushNotifications() {
    const isSupported = ref(false);
    const isSubscribed = ref(false);
    const permission = ref('default');

    const vapidPublicKey = import.meta.env.VITE_VAPID_PUBLIC_KEY;

    onMounted(async () => {
        isSupported.value = 'serviceWorker' in navigator && 'PushManager' in window && !!vapidPublicKey;

        if (!isSupported.value) return;

        permission.value = Notification.permission;

        try {
            const registration = await navigator.serviceWorker.register('/sw.js');
            const subscription = await registration.pushManager.getSubscription();
            isSubscribed.value = !!subscription;
        } catch {
            // Service worker registration failed — push not available
        }
    });

    async function subscribe() {
        if (!isSupported.value) return false;

        try {
            const perm = await Notification.requestPermission();
            permission.value = perm;

            if (perm !== 'granted') return false;

            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(vapidPublicKey),
            });

            const subJson = subscription.toJSON();

            await fetch('/api/push-subscriptions', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
                body: JSON.stringify({
                    endpoint: subJson.endpoint,
                    keys: {
                        p256dh: subJson.keys.p256dh,
                        auth: subJson.keys.auth,
                    },
                }),
            });

            isSubscribed.value = true;
            return true;
        } catch {
            return false;
        }
    }

    async function unsubscribe() {
        if (!isSupported.value) return false;

        try {
            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.getSubscription();

            if (!subscription) {
                isSubscribed.value = false;
                return true;
            }

            await fetch('/api/push-subscriptions', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
                body: JSON.stringify({
                    endpoint: subscription.endpoint,
                }),
            });

            await subscription.unsubscribe();
            isSubscribed.value = false;
            return true;
        } catch {
            return false;
        }
    }

    return {
        isSupported,
        isSubscribed,
        permission,
        subscribe,
        unsubscribe,
    };
}

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; i++) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
}

function getCsrfToken() {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}
