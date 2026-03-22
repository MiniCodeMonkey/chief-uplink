import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';

let unmountCallbacks = [];

vi.mock('vue', () => ({
    onUnmounted: (cb) => unmountCallbacks.push(cb),
}));

import { useDeviceChannel } from './useDeviceChannel';

describe('useDeviceChannel', () => {
    let mockChannel;

    beforeEach(() => {
        unmountCallbacks = [];
        mockChannel = {
            listen: vi.fn().mockReturnThis(),
        };
        window.Echo = {
            private: vi.fn().mockReturnValue(mockChannel),
            leave: vi.fn(),
        };
    });

    afterEach(() => {
        delete window.Echo;
    });

    it('subscribes to private device channel', () => {
        useDeviceChannel(42);
        expect(window.Echo.private).toHaveBeenCalledWith('device.42');
    });

    it('listens for DeviceStateUpdated when handler provided', () => {
        const onStateUpdated = vi.fn();
        useDeviceChannel(1, { onStateUpdated });
        expect(mockChannel.listen).toHaveBeenCalledWith('DeviceStateUpdated', onStateUpdated);
    });

    it('listens for DeviceStreamEvent when handler provided', () => {
        const onStreamEvent = vi.fn();
        useDeviceChannel(1, { onStreamEvent });
        expect(mockChannel.listen).toHaveBeenCalledWith('DeviceStreamEvent', onStreamEvent);
    });

    it('does not listen for events without handlers', () => {
        useDeviceChannel(1);
        expect(mockChannel.listen).not.toHaveBeenCalled();
    });

    it('leaves channel on unmount', () => {
        useDeviceChannel(5);
        expect(unmountCallbacks).toHaveLength(1);
        unmountCallbacks[0]();
        expect(window.Echo.leave).toHaveBeenCalledWith('device.5');
    });
});
