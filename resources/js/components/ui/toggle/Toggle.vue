<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { computed } from 'vue';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        modelValue?: boolean;
        disabled?: boolean;
        class?: HTMLAttributes['class'];
        ariaLabel?: string;
    }>(),
    {
        modelValue: false,
        disabled: false,
    },
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: boolean): void;
}>();

const isOn = computed(() => props.modelValue);

function toggle() {
    if (!props.disabled) {
        emit('update:modelValue', !isOn.value);
    }
}
</script>

<template>
    <button
        type="button"
        role="switch"
        data-slot="toggle"
        :aria-checked="isOn"
        :aria-label="ariaLabel"
        :disabled="disabled"
        :class="
            cn(
                'peer inline-flex h-5 w-9 shrink-0 cursor-pointer items-center rounded-full border-2 border-transparent shadow-xs transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background outline-none disabled:cursor-not-allowed disabled:opacity-50',
                isOn ? 'bg-primary' : 'bg-input',
                props.class,
            )
        "
        @click="toggle"
    >
        <span
            :class="
                cn(
                    'pointer-events-none block size-4 rounded-full bg-background shadow-lg ring-0 transition-transform',
                    isOn ? 'translate-x-4' : 'translate-x-0',
                )
            "
        />
    </button>
</template>
