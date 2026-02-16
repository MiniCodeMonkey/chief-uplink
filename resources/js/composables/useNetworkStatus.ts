import { onMounted, onUnmounted, ref } from 'vue';
import { useToast } from '@/composables/useToast';

const isOnline = ref(true);
let initialized = false;

/**
 * Monitors browser online/offline state and shows toast notifications.
 * Should be called once in the root layout.
 */
export function useNetworkStatus() {
    const { error, success, dismiss } = useToast();
    let offlineToastId: string | null = null;

    function handleOffline() {
        isOnline.value = false;
        offlineToastId = error(
            'Connection lost',
            'Unable to reach the server. Please check your connection.',
        );
    }

    function handleOnline() {
        isOnline.value = true;
        if (offlineToastId) {
            dismiss(offlineToastId);
            offlineToastId = null;
        }
        success('Connection restored');
    }

    onMounted(() => {
        if (!initialized) {
            isOnline.value = navigator.onLine;
            initialized = true;
        }

        window.addEventListener('offline', handleOffline);
        window.addEventListener('online', handleOnline);
    });

    onUnmounted(() => {
        window.removeEventListener('offline', handleOffline);
        window.removeEventListener('online', handleOnline);
    });

    return { isOnline };
}
