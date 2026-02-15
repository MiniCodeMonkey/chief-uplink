import { router } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref, watch } from 'vue';
import { useEcho } from '@/composables/useEcho';

interface DeviceEventPayload {
    deviceId: number;
    userId: number;
}

const isListening = ref(false);
const reconnectDebounceTimers = new Map<number, ReturnType<typeof setTimeout>>();

const RECONNECTING_DEBOUNCE_MS = 2000;
const RECONNECTING_TIMEOUT_MS = 60000;

export function useDeviceStatus() {
    const { subscribeToUserChannel, leaveUserChannel, connectionState } = useEcho();

    function startListening() {
        if (isListening.value) return;

        subscribeToUserChannel({
            'device.connected': (data: unknown) => {
                const payload = data as DeviceEventPayload;
                clearReconnectTimer(payload.deviceId);
                // Reload Inertia shared props to get fresh device list
                router.reload({ only: ['devices'] });
            },
            'device.disconnected': (data: unknown) => {
                const payload = data as DeviceEventPayload;
                handleDeviceDisconnected(payload.deviceId);
            },
            'device.token.revoked': (data: unknown) => {
                const payload = data as DeviceEventPayload;
                clearReconnectTimer(payload.deviceId);
                // Reload to remove revoked device from list
                router.reload({ only: ['devices'] });
            },
        });

        isListening.value = true;
    }

    function handleDeviceDisconnected(deviceId: number) {
        // Debounce: don't show "reconnecting" for brief blips (<2s)
        if (reconnectDebounceTimers.has(deviceId)) return;

        const timer = setTimeout(() => {
            reconnectDebounceTimers.delete(deviceId);
            // After debounce period, reload to get updated status
            router.reload({ only: ['devices'] });

            // Set a timeout to transition from reconnecting to offline
            const offlineTimer = setTimeout(() => {
                router.reload({ only: ['devices'] });
            }, RECONNECTING_TIMEOUT_MS - RECONNECTING_DEBOUNCE_MS);

            reconnectDebounceTimers.set(deviceId, offlineTimer);
        }, RECONNECTING_DEBOUNCE_MS);

        reconnectDebounceTimers.set(deviceId, timer);
    }

    function clearReconnectTimer(deviceId: number) {
        const timer = reconnectDebounceTimers.get(deviceId);
        if (timer) {
            clearTimeout(timer);
            reconnectDebounceTimers.delete(deviceId);
        }
    }

    function stopListening() {
        if (!isListening.value) return;
        leaveUserChannel();
        isListening.value = false;

        // Clear all debounce timers
        for (const [, timer] of reconnectDebounceTimers) {
            clearTimeout(timer);
        }
        reconnectDebounceTimers.clear();
    }

    // Auto-start listening when mounted, auto-stop on unmount
    onMounted(() => {
        startListening();
    });

    onUnmounted(() => {
        stopListening();
    });

    // Re-subscribe when Echo reconnects
    watch(connectionState, (newState, oldState) => {
        if (newState === 'connected' && oldState !== 'connected') {
            // On reconnect, reload all device data and re-subscribe
            if (isListening.value) {
                isListening.value = false;
                startListening();
            }
            router.reload({ only: ['devices'] });
        }
    });

    return {
        isListening,
        startListening,
        stopListening,
    };
}
