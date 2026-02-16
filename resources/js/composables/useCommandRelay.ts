import axios, { type AxiosError } from 'axios';
import { ref } from 'vue';
import { useToast } from '@/composables/useToast';

export type CommandType =
    | 'start_run'
    | 'pause_run'
    | 'resume_run'
    | 'stop_run'
    | 'new_prd'
    | 'prd_message'
    | 'close_prd_session'
    | 'clone_repo'
    | 'create_project'
    | 'get_logs'
    | 'get_diffs'
    | 'get_settings'
    | 'update_settings'
    | 'get_prds'
    | 'refine_prd';

export interface CommandResponse {
    status: string;
    type: string;
    device_id: number;
    session_timeout_remaining?: number;
}

interface CommandErrorResponse {
    error: string;
    message: string;
}

interface ChiefRateLimitPayload {
    code: string;
    message: string;
    retry_after?: number;
}

const pendingCommands = ref<Set<string>>(new Set());

export function useCommandRelay() {
    const { warning, error: errorToast } = useToast();
    const retryTimers = new Map<string, ReturnType<typeof setTimeout>>();

    async function sendCommand(
        deviceId: number,
        type: CommandType,
        payload: Record<string, unknown> = {},
    ): Promise<CommandResponse | null> {
        const commandKey = `${deviceId}:${type}`;

        // Debounce: prevent duplicate commands within 300ms
        if (pendingCommands.value.has(commandKey)) {
            return null;
        }

        pendingCommands.value.add(commandKey);

        try {
            const response = await axios.post<CommandResponse>(`/ws/command/${deviceId}`, {
                type,
                payload,
            });

            return response.data;
        } catch (err) {
            const axiosError = err as AxiosError<CommandErrorResponse>;

            if (axiosError.response) {
                const { status, data } = axiosError.response;

                if (status === 503) {
                    errorToast('Server offline', 'The server is not connected. Please try again when it comes back online.');
                } else if (status === 429) {
                    warning('Too many requests', 'Please slow down and try again in a moment.');
                } else if (status === 422) {
                    errorToast('Invalid command', data?.message || 'The command could not be processed.');
                } else if (status === 403) {
                    errorToast('Not authorized', data?.message || 'You are not authorized to send commands to this device.');
                } else {
                    errorToast('Command failed', data?.message || 'An unexpected error occurred.');
                }
            } else {
                errorToast('Connection lost', 'Unable to reach the server. Please check your connection.');
            }

            return null;
        } finally {
            // Remove from pending after 300ms debounce window
            setTimeout(() => {
                pendingCommands.value.delete(commandKey);
            }, 300);
        }
    }

    /**
     * Handle a RATE_LIMITED response from a chief server.
     * Shows a toast and schedules an automatic retry.
     */
    function handleChiefRateLimit(
        payload: ChiefRateLimitPayload,
        retryFn?: () => void,
    ): void {
        const retryAfter = payload.retry_after ?? 5;

        warning(
            'Server busy',
            `Retrying in ${retryAfter} seconds`,
        );

        if (retryFn) {
            const retryKey = `retry-${Date.now()}`;

            // Cancel any existing retry with the same key
            const existingTimer = retryTimers.get(retryKey);
            if (existingTimer) {
                clearTimeout(existingTimer);
            }

            const timer = setTimeout(() => {
                retryTimers.delete(retryKey);
                retryFn();
            }, retryAfter * 1000);

            retryTimers.set(retryKey, timer);
        }
    }

    function clearRetryTimers() {
        for (const [, timer] of retryTimers) {
            clearTimeout(timer);
        }
        retryTimers.clear();
    }

    return {
        sendCommand,
        handleChiefRateLimit,
        clearRetryTimers,
        pendingCommands,
    };
}
