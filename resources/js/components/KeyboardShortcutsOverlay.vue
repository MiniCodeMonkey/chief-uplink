<script setup lang="ts">
import { X } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps<{
    open: boolean;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const isMac = ref(false);

onMounted(() => {
    isMac.value = navigator.platform.toUpperCase().includes('MAC');
});

const mod = computed(() => (isMac.value ? '\u2318' : 'Ctrl'));

const shortcuts = computed(() => [
    { keys: [`${mod.value}+K`], description: 'Open command palette' },
    { keys: [`${mod.value}+Enter`], description: 'Start / resume run' },
    { keys: [`${mod.value}+.`], description: 'Pause run' },
    { keys: ['Esc'], description: 'Stop run / close modal' },
    { keys: ['?'], description: 'Toggle this help' },
]);

function close() {
    emit('update:open', false);
}

function handleKeydown(e: KeyboardEvent) {
    if (e.key === 'Escape') {
        close();
    }
}

onMounted(() => {
    document.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
    document.removeEventListener('keydown', handleKeydown);
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-[var(--duration-standard)] ease-[var(--ease-snappy)]"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-[var(--duration-micro)] ease-[var(--ease-snappy)]"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="props.open"
                class="fixed inset-0 z-50 flex items-center justify-center bg-background/80 backdrop-blur-sm"
                @click.self="close"
            >
                <div
                    class="mx-4 w-full max-w-md rounded-xl border border-border bg-card p-6 shadow-lg"
                    role="dialog"
                    aria-label="Keyboard shortcuts"
                    aria-modal="true"
                >
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-lg font-semibold">
                            Keyboard shortcuts
                        </h2>
                        <button
                            class="focus-ring rounded-md p-1 transition-colors hover:bg-accent"
                            aria-label="Close"
                            @click="close"
                        >
                            <X class="size-4" />
                        </button>
                    </div>
                    <div class="space-y-2">
                        <div
                            v-for="shortcut in shortcuts"
                            :key="shortcut.description"
                            class="flex items-center justify-between py-1.5"
                        >
                            <span class="text-sm text-muted-foreground">{{
                                shortcut.description
                            }}</span>
                            <div class="flex gap-1">
                                <kbd
                                    v-for="key in shortcut.keys"
                                    :key="key"
                                    class="inline-flex min-w-[28px] items-center justify-center rounded border border-border bg-muted px-2 py-1 font-mono text-xs"
                                >
                                    {{ key }}
                                </kbd>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
