import { ref, type Ref } from 'vue';
import { useToast } from './useToast';

export interface UseOptimisticOptions<T> {
    /** The action to perform (returns a promise) */
    action: () => Promise<T>;
    /** Callback to apply optimistic update immediately */
    onOptimistic: () => void;
    /** Callback when the action succeeds */
    onSuccess?: (result: T) => void;
    /** Callback to rollback the optimistic update on failure */
    onRollback: () => void;
    /** Error message to show in toast on failure */
    errorMessage?: string;
}

export interface UseOptimisticReturn {
    /** Whether the action is in progress */
    isPending: Ref<boolean>;
    /** Execute the optimistic action */
    execute: () => Promise<void>;
}

export function useOptimistic<T = unknown>(
    options: UseOptimisticOptions<T>,
): UseOptimisticReturn {
    const isPending = ref(false);
    const { error: showError } = useToast();

    async function execute() {
        if (isPending.value) return;

        isPending.value = true;

        // Apply optimistic update immediately
        options.onOptimistic();

        try {
            const result = await options.action();
            options.onSuccess?.(result);
        } catch (err) {
            // Rollback on failure
            options.onRollback();

            // Show error toast with shake animation hint
            const message =
                options.errorMessage ??
                (err instanceof Error ? err.message : 'Something went wrong');
            showError(message);
        } finally {
            isPending.value = false;
        }
    }

    return { isPending, execute };
}
