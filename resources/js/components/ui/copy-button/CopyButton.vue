<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { ref } from 'vue';
import { Check, Copy } from 'lucide-vue-next';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        value: string;
        label?: string;
        class?: HTMLAttributes['class'];
    }>(),
    {
        label: 'Copy',
    },
);

const copied = ref(false);
let timer: ReturnType<typeof setTimeout> | undefined;

async function copy() {
    try {
        await navigator.clipboard.writeText(props.value);
        copied.value = true;
        if (timer) clearTimeout(timer);
        timer = setTimeout(() => {
            copied.value = false;
        }, 2000);
    } catch {
        // Fallback for older browsers
        const textarea = document.createElement('textarea');
        textarea.value = props.value;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        copied.value = true;
        if (timer) clearTimeout(timer);
        timer = setTimeout(() => {
            copied.value = false;
        }, 2000);
    }
}
</script>

<template>
    <button
        type="button"
        data-slot="copy-button"
        :aria-label="copied ? 'Copied' : label"
        :class="
            cn(
                'inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs font-medium transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background outline-none',
                copied && 'text-success',
                props.class,
            )
        "
        @click="copy"
    >
        <Transition
            enter-active-class="transition-all duration-150"
            leave-active-class="transition-all duration-150"
            enter-from-class="scale-0 opacity-0"
            leave-to-class="scale-0 opacity-0"
            mode="out-in"
        >
            <Check v-if="copied" class="size-3.5" />
            <Copy v-else class="size-3.5" />
        </Transition>
        <span>{{ copied ? 'Copied!' : label }}</span>
    </button>
</template>
