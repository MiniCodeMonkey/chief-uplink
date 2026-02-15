import { usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import type { DeviceSummary } from '@/types';

/**
 * Format a date as relative time (e.g., "2 minutes ago", "3 hours ago").
 */
export function formatRelativeTime(dateString: string): string {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffSeconds = Math.floor(diffMs / 1000);

    if (diffSeconds < 60) {
        return 'just now';
    }

    const diffMinutes = Math.floor(diffSeconds / 60);
    if (diffMinutes < 60) {
        return `${diffMinutes}m ago`;
    }

    const diffHours = Math.floor(diffMinutes / 60);
    if (diffHours < 24) {
        return `${diffHours}h ago`;
    }

    const diffDays = Math.floor(diffHours / 24);
    if (diffDays < 30) {
        return `${diffDays}d ago`;
    }

    return date.toLocaleDateString();
}

/**
 * Composable that provides reactive connection status information
 * for the currently selected device.
 */
export function useConnectionStatus(deviceId?: () => number | null) {
    const page = usePage();
    const now = ref(Date.now());
    let tickInterval: ReturnType<typeof setInterval> | null = null;

    // Tick every 30s to update relative times
    onMounted(() => {
        tickInterval = setInterval(() => {
            now.value = Date.now();
        }, 30000);
    });

    onUnmounted(() => {
        if (tickInterval) {
            clearInterval(tickInterval);
        }
    });

    const devices = computed(
        () => (page.props.devices as DeviceSummary[]) || [],
    );

    const selectedDeviceId = computed(() => {
        if (deviceId) return deviceId();
        return page.props.selectedDeviceId as number | null;
    });

    const selectedDevice = computed(() => {
        if (!selectedDeviceId.value) return devices.value[0] ?? null;
        return (
            devices.value.find((d) => d.id === selectedDeviceId.value) ??
            devices.value[0] ??
            null
        );
    });

    const connectionStatus = computed(() => {
        return selectedDevice.value?.connection_status ?? null;
    });

    const isOnline = computed(() => connectionStatus.value === 'online');
    const isReconnecting = computed(() => connectionStatus.value === 'reconnecting');
    const isOffline = computed(() => connectionStatus.value === 'offline');
    const isNeverConnected = computed(() => connectionStatus.value === 'never-connected');

    const statusText = computed(() => {
        // Force reactivity on `now`
        void now.value;

        if (!selectedDevice.value) return null;

        switch (connectionStatus.value) {
            case 'online':
                return null;
            case 'reconnecting':
                return 'Reconnecting...';
            case 'offline':
                if (selectedDevice.value.last_connected_at) {
                    return `Offline — last synced ${formatRelativeTime(selectedDevice.value.last_connected_at)}`;
                }
                return 'Offline';
            case 'never-connected':
                return 'Run `chief serve` to connect';
            default:
                return null;
        }
    });

    return {
        selectedDevice,
        connectionStatus,
        isOnline,
        isReconnecting,
        isOffline,
        isNeverConnected,
        statusText,
    };
}
