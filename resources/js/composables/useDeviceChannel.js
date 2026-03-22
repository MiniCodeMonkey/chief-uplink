import { onUnmounted } from 'vue';

/**
 * Subscribe to a private device channel for real-time events.
 *
 * @param {string|number} deviceId
 * @param {{ onStateUpdated?: (data: object) => void, onStreamEvent?: (data: object) => void }} handlers
 */
export function useDeviceChannel(deviceId, handlers = {}) {
    const channel = window.Echo.private(`device.${deviceId}`);

    if (handlers.onStateUpdated) {
        channel.listen('DeviceStateUpdated', handlers.onStateUpdated);
    }

    if (handlers.onStreamEvent) {
        channel.listen('DeviceStreamEvent', handlers.onStreamEvent);
    }

    onUnmounted(() => {
        window.Echo.leave(`device.${deviceId}`);
    });

    return channel;
}
