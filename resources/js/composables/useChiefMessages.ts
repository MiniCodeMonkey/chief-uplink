import { onUnmounted, ref } from 'vue';
import { useCommandRelay } from '@/composables/useCommandRelay';
import { useEcho } from '@/composables/useEcho';

export interface ChiefMessage {
    device_id: number;
    type: string;
    payload: Record<string, unknown> | null;
    message: Record<string, unknown>;
}

type ChiefMessageHandler = (message: ChiefMessage) => void;

export function useChiefMessages(deviceId: number) {
    const { subscribeToDeviceChannel, leaveDeviceChannel } = useEcho();
    const { handleChiefRateLimit } = useCommandRelay();

    const messages = ref<ChiefMessage[]>([]);
    const isSubscribed = ref(false);
    const handlers = new Map<string, ChiefMessageHandler[]>();

    function subscribe() {
        if (isSubscribed.value) return;

        subscribeToDeviceChannel(deviceId, {
            'chief.message': (data: unknown) => {
                const message = data as ChiefMessage;
                handleMessage(message);
            },
        });

        isSubscribed.value = true;
    }

    function handleMessage(message: ChiefMessage) {
        messages.value.push(message);

        // Handle RATE_LIMITED errors from chief automatically
        if (
            message.type === 'error' &&
            message.message &&
            (message.message as Record<string, unknown>).code === 'RATE_LIMITED'
        ) {
            handleChiefRateLimit({
                code: 'RATE_LIMITED',
                message: (message.message as Record<string, unknown>).message as string,
                retry_after: (message.message as Record<string, unknown>).retry_after as number | undefined,
            });
        }

        // Call registered handlers for this message type
        const typeHandlers = handlers.get(message.type);
        if (typeHandlers) {
            for (const handler of typeHandlers) {
                handler(message);
            }
        }

        // Call wildcard handlers
        const wildcardHandlers = handlers.get('*');
        if (wildcardHandlers) {
            for (const handler of wildcardHandlers) {
                handler(message);
            }
        }
    }

    function on(type: string, handler: ChiefMessageHandler) {
        const existing = handlers.get(type) ?? [];
        existing.push(handler);
        handlers.set(type, existing);
    }

    function off(type: string, handler?: ChiefMessageHandler) {
        if (!handler) {
            handlers.delete(type);
            return;
        }
        const existing = handlers.get(type) ?? [];
        handlers.set(
            type,
            existing.filter((h) => h !== handler),
        );
    }

    function unsubscribe() {
        if (!isSubscribed.value) return;
        leaveDeviceChannel(deviceId);
        isSubscribed.value = false;
        handlers.clear();
    }

    // Auto-cleanup on component unmount
    onUnmounted(() => {
        unsubscribe();
    });

    return {
        messages,
        isSubscribed,
        subscribe,
        unsubscribe,
        on,
        off,
    };
}
