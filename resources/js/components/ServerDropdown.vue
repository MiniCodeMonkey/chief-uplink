<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { ChevronDown, Server } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { StatusDot } from '@/components/ui/status-dot';
import { useChiefCommands } from '@/composables/useChiefCommands';
import { formatRelativeTime } from '@/composables/useConnectionStatus';
import type { DeviceSummary } from '@/types';

const props = defineProps<{
    modelValue: number | null;
}>();

const emit = defineEmits<{
    'update:modelValue': [id: number];
}>();

const open = ref(false);
const triggerRef = ref<HTMLButtonElement | null>(null);

const page = usePage();
const { chiefLoginCommand, chiefServeCommand } = useChiefCommands();
const devices = computed(
    () =>
        (page.props.devices as (DeviceSummary & { projects: unknown[] })[]) ||
        [],
);

const selectedDevice = computed(() => {
    if (!props.modelValue) return devices.value[0] ?? null;
    return (
        devices.value.find((d) => d.id === props.modelValue) ??
        devices.value[0] ??
        null
    );
});

function selectDevice(device: DeviceSummary) {
    emit('update:modelValue', device.id);
    open.value = false;
    document.cookie = `selected_device_id=${device.id};path=/;max-age=${60 * 60 * 24 * 365};samesite=lax`;
    triggerRef.value?.focus();
}

function handleKeydown(event: KeyboardEvent) {
    if (event.key === 'Escape') {
        open.value = false;
        triggerRef.value?.focus();
    }
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        const items = Array.from(
            document.querySelectorAll('[data-server-item]'),
        ) as HTMLElement[];
        const currentIndex = items.findIndex(
            (el) => el === document.activeElement,
        );
        let nextIndex: number;
        if (event.key === 'ArrowDown') {
            nextIndex = currentIndex < items.length - 1 ? currentIndex + 1 : 0;
        } else {
            nextIndex = currentIndex > 0 ? currentIndex - 1 : items.length - 1;
        }
        items[nextIndex]?.focus();
    }
}
</script>

<template>
    <div class="relative">
        <button
            ref="triggerRef"
            class="focus-ring flex items-center gap-1.5 rounded-md px-2 py-1.5 text-sm font-medium transition-colors duration-[var(--duration-micro)] hover:bg-accent"
            :aria-expanded="open"
            aria-haspopup="listbox"
            @click="open = !open"
            @keydown.escape="open = false"
        >
            <Server class="size-4 shrink-0 text-muted-foreground" />
            <template v-if="selectedDevice">
                <StatusDot
                    :state="selectedDevice.connection_status"
                    class="size-2"
                />
                <span class="max-w-[120px] truncate lg:max-w-[160px]">{{
                    selectedDevice.device_name
                }}</span>
            </template>
            <span v-else class="text-muted-foreground">No servers</span>
            <ChevronDown
                class="size-3.5 text-muted-foreground transition-transform duration-[var(--duration-micro)]"
                :class="{ 'rotate-180': open }"
            />
        </button>

        <Teleport to="body">
            <div v-if="open" class="fixed inset-0 z-40" @click="open = false" />
        </Teleport>

        <Transition
            enter-active-class="transition duration-[var(--duration-standard)] ease-[var(--ease-snappy)]"
            enter-from-class="opacity-0 scale-95 -translate-y-1"
            enter-to-class="opacity-100 scale-100 translate-y-0"
            leave-active-class="transition duration-[var(--duration-micro)] ease-[var(--ease-snappy)]"
            leave-from-class="opacity-100 scale-100 translate-y-0"
            leave-to-class="opacity-0 scale-95 -translate-y-1"
        >
            <div
                v-if="open"
                role="listbox"
                aria-label="Select server"
                class="absolute top-full left-0 z-50 mt-1 min-w-[220px] overflow-hidden rounded-lg border border-border bg-popover p-1 shadow-md"
                @keydown="handleKeydown"
            >
                <div
                    v-if="devices.length === 0"
                    class="px-3 py-4 text-center text-sm text-muted-foreground"
                >
                    No servers available.
                    <br />
                    <span class="text-xs"
                        >Run
                        <code
                            class="rounded bg-muted px-1 py-0.5 font-mono text-xs"
                            >{{ chiefLoginCommand }}</code
                        >
                        to connect.</span
                    >
                </div>
                <button
                    v-for="device in devices"
                    :key="device.id"
                    data-server-item
                    role="option"
                    :aria-selected="selectedDevice?.id === device.id"
                    class="focus-ring flex w-full items-center gap-2.5 rounded-md px-2.5 py-2 text-left text-sm transition-colors duration-[var(--duration-micro)] hover:bg-accent"
                    :class="{
                        'bg-accent': selectedDevice?.id === device.id,
                    }"
                    @click="selectDevice(device)"
                    @keydown.enter.prevent="selectDevice(device)"
                >
                    <StatusDot
                        :state="device.connection_status"
                        class="size-2"
                    />
                    <div class="flex flex-1 flex-col">
                        <span class="font-medium">{{
                            device.device_name
                        }}</span>
                        <span
                            v-if="
                                device.connection_status === 'offline' &&
                                device.last_connected_at
                            "
                            class="text-xs text-muted-foreground"
                        >
                            Offline — last synced
                            {{
                                formatRelativeTime(
                                    device.last_connected_at,
                                )
                            }}
                        </span>
                        <span
                            v-else-if="
                                device.connection_status === 'reconnecting'
                            "
                            class="text-xs text-warning"
                        >
                            Reconnecting...
                        </span>
                        <span
                            v-else-if="
                                device.connection_status === 'never-connected'
                            "
                            class="text-xs text-muted-foreground"
                        >
                            Run
                            <code class="font-mono">{{ chiefServeCommand }}</code> to
                            connect
                        </span>
                    </div>
                </button>
            </div>
        </Transition>
    </div>
</template>
