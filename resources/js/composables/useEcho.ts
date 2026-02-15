import { usePage } from '@inertiajs/vue3';
import type Echo from 'laravel-echo';
import type { Channel } from 'laravel-echo';
import { computed, ref } from 'vue';

export type EchoConnectionState = 'connected' | 'reconnecting' | 'disconnected';

const connectionState = ref<EchoConnectionState>('disconnected');
const subscribedUserChannel = ref<Channel | null>(null);
const subscribedDeviceChannels = ref<Map<number, Channel>>(new Map());
const initialized = ref(false);

function getEcho(): Echo | null {
    return window.Echo ?? null;
}

function initConnection() {
    const echo = getEcho();
    if (!echo || initialized.value) return;

    const connector = echo.connector;
    if (connector?.pusher) {
        const pusher = connector.pusher;

        pusher.connection.bind('connected', () => {
            connectionState.value = 'connected';
        });

        pusher.connection.bind('connecting', () => {
            connectionState.value = 'reconnecting';
        });

        pusher.connection.bind('unavailable', () => {
            connectionState.value = 'disconnected';
        });

        pusher.connection.bind('failed', () => {
            connectionState.value = 'disconnected';
        });

        pusher.connection.bind('disconnected', () => {
            connectionState.value = 'disconnected';
        });

        // Check initial state
        const state = pusher.connection.state;
        if (state === 'connected') {
            connectionState.value = 'connected';
        } else if (state === 'connecting' || state === 'reconnecting') {
            connectionState.value = 'reconnecting';
        }
    }

    initialized.value = true;
}

export function useEcho() {
    const page = usePage();
    const userId = computed(() => page.props.auth?.user?.id as number | undefined);

    initConnection();

    function subscribeToUserChannel(
        listeners: Record<string, (data: unknown) => void>,
    ) {
        const echo = getEcho();
        if (!echo || !userId.value) return;

        // Unsubscribe from previous channel
        if (subscribedUserChannel.value) {
            echo.leave(`user.${userId.value}`);
            subscribedUserChannel.value = null;
        }

        const channel = echo.private(`user.${userId.value}`);
        subscribedUserChannel.value = channel;

        for (const [event, callback] of Object.entries(listeners)) {
            channel.listen(`.${event}`, callback);
        }

        return channel;
    }

    function subscribeToDeviceChannel(
        deviceId: number,
        listeners: Record<string, (data: unknown) => void>,
    ) {
        const echo = getEcho();
        if (!echo) return;

        // Unsubscribe from previous subscription for this device
        if (subscribedDeviceChannels.value.has(deviceId)) {
            echo.leave(`device.${deviceId}`);
            subscribedDeviceChannels.value.delete(deviceId);
        }

        const channel = echo.private(`device.${deviceId}`);
        subscribedDeviceChannels.value.set(deviceId, channel);

        for (const [event, callback] of Object.entries(listeners)) {
            channel.listen(`.${event}`, callback);
        }

        return channel;
    }

    function leaveUserChannel() {
        const echo = getEcho();
        if (!echo || !userId.value) return;
        echo.leave(`user.${userId.value}`);
        subscribedUserChannel.value = null;
    }

    function leaveDeviceChannel(deviceId: number) {
        const echo = getEcho();
        if (!echo) return;
        echo.leave(`device.${deviceId}`);
        subscribedDeviceChannels.value.delete(deviceId);
    }

    function leaveAllChannels() {
        const echo = getEcho();
        if (!echo) return;

        if (userId.value) {
            echo.leave(`user.${userId.value}`);
            subscribedUserChannel.value = null;
        }

        for (const [deviceId] of subscribedDeviceChannels.value) {
            echo.leave(`device.${deviceId}`);
        }
        subscribedDeviceChannels.value.clear();
    }

    return {
        connectionState,
        subscribeToUserChannel,
        subscribeToDeviceChannel,
        leaveUserChannel,
        leaveDeviceChannel,
        leaveAllChannels,
        getEcho,
    };
}

export function useEchoConnectionStatus() {
    initConnection();

    return {
        connectionState,
        isConnected: computed(() => connectionState.value === 'connected'),
        isReconnecting: computed(() => connectionState.value === 'reconnecting'),
        isDisconnected: computed(() => connectionState.value === 'disconnected'),
    };
}
