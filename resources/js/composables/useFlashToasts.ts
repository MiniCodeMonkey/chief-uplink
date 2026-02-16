import { usePage } from '@inertiajs/vue3';
import { watch } from 'vue';
import { useToast } from '@/composables/useToast';

interface FlashProps {
    success?: string;
    error?: string;
}

/**
 * Watches Inertia flash props and displays them as toast notifications.
 * Should be called once per layout (AppLayout / ProjectLayout).
 */
export function useFlashToasts() {
    const page = usePage();
    const { success, error } = useToast();

    watch(
        () => (page.props.flash as FlashProps)?.success,
        (message) => {
            if (message) {
                success(message);
            }
        },
    );

    watch(
        () => (page.props.flash as FlashProps)?.error,
        (message) => {
            if (message) {
                error('Error', message);
            }
        },
    );
}
