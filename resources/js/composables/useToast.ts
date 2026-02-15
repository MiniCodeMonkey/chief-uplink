import { ref } from 'vue';
import type { ToastVariant } from '@/components/ui/toast';

export interface ToastItem {
    id: string;
    title: string;
    description?: string;
    variant: ToastVariant;
    duration: number;
    action?: { label: string; onClick: () => void };
}

const toasts = ref<ToastItem[]>([]);

let idCounter = 0;

function toast(options: Omit<ToastItem, 'id'> & { id?: string }): string {
    const id = options.id ?? `toast-${++idCounter}`;
    toasts.value.push({ ...options, id });
    return id;
}

function dismiss(id: string) {
    toasts.value = toasts.value.filter((t) => t.id !== id);
}

function dismissAll() {
    toasts.value = [];
}

export function useToast() {
    return {
        toasts,
        toast: (
            options:
                | (Omit<ToastItem, 'id' | 'variant' | 'duration'> & {
                      variant?: ToastVariant;
                      duration?: number;
                  })
                | string,
        ) => {
            if (typeof options === 'string') {
                return toast({
                    title: options,
                    variant: 'info',
                    duration: 5000,
                });
            }
            return toast({
                variant: 'info',
                duration: options.variant === 'error' ? 0 : 5000,
                ...options,
            });
        },
        success: (title: string, description?: string) =>
            toast({ title, description, variant: 'success', duration: 5000 }),
        error: (title: string, description?: string) =>
            toast({ title, description, variant: 'error', duration: 0 }),
        warning: (title: string, description?: string) =>
            toast({ title, description, variant: 'warning', duration: 5000 }),
        info: (title: string, description?: string) =>
            toast({ title, description, variant: 'info', duration: 5000 }),
        dismiss,
        dismissAll,
    };
}
