<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { X } from 'lucide-vue-next';
import { cn } from '@/lib/utils';

export type ToastVariant = 'success' | 'error' | 'warning' | 'info';

const props = withDefaults(
    defineProps<{
        id: string;
        title: string;
        description?: string;
        variant?: ToastVariant;
        duration?: number;
        action?: { label: string; onClick: () => void };
        class?: HTMLAttributes['class'];
    }>(),
    {
        variant: 'info',
        duration: 5000,
    },
);

const emit = defineEmits<{
    (e: 'dismiss', id: string): void;
}>();

const isVisible = ref(false);
let timer: ReturnType<typeof setTimeout> | undefined;

const variantClasses = computed(() => {
    switch (props.variant) {
        case 'success':
            return 'border-success/30 bg-success/10 text-success-foreground';
        case 'error':
            return 'border-destructive/30 bg-destructive/10 text-destructive';
        case 'warning':
            return 'border-warning/30 bg-warning/10 text-warning-foreground';
        case 'info':
            return 'border-info/30 bg-info/10 text-info-foreground';
        default:
            return '';
    }
});

const shouldAutoDismiss = computed(
    () => props.variant !== 'error' && props.duration > 0,
);

function dismiss() {
    isVisible.value = false;
    setTimeout(() => emit('dismiss', props.id), 200);
}

function startTimer() {
    if (shouldAutoDismiss.value) {
        timer = setTimeout(dismiss, props.duration);
    }
}

onMounted(() => {
    requestAnimationFrame(() => {
        isVisible.value = true;
    });
    startTimer();
});

onUnmounted(() => {
    if (timer) clearTimeout(timer);
});
</script>

<template>
    <div
        role="alert"
        :aria-live="variant === 'error' ? 'assertive' : 'polite'"
        data-slot="toast"
        :class="
            cn(
                'pointer-events-auto relative flex w-full items-start gap-3 overflow-hidden rounded-lg border p-4 shadow-lg transition-all',
                isVisible
                    ? 'translate-y-0 opacity-100'
                    : 'translate-y-2 opacity-0',
                variantClasses,
                props.class,
            )
        "
    >
        <div class="flex-1 space-y-1">
            <p class="text-sm font-medium">{{ title }}</p>
            <p v-if="description" class="text-muted-foreground text-xs">
                {{ description }}
            </p>
        </div>
        <button
            v-if="action"
            class="text-sm font-medium underline underline-offset-2 hover:opacity-80"
            @click="action.onClick"
        >
            {{ action.label }}
        </button>
        <button
            class="text-muted-foreground hover:text-foreground shrink-0 transition-colors"
            aria-label="Dismiss"
            @click="dismiss"
        >
            <X class="size-4" />
        </button>
    </div>
</template>
