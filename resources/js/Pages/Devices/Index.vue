<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import { computed, ref, onUnmounted } from 'vue';
import EmptyDevices from './EmptyDevices.vue';

const props = defineProps({
    devices: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const currentTeam = computed(() => page.props.auth?.currentTeam);

const deviceMap = ref(new Map(props.devices.map(d => [d.id, { ...d }])));

const sortedDevices = computed(() => {
    const list = Array.from(deviceMap.value.values());
    list.sort((a, b) => {
        if (a.connected !== b.connected) {
            return a.connected ? -1 : 1;
        }
        const aTime = a.last_seen_at ? new Date(a.last_seen_at).getTime() : 0;
        const bTime = b.last_seen_at ? new Date(b.last_seen_at).getTime() : 0;
        return bTime - aTime;
    });
    return list;
});

const hasDevices = computed(() => deviceMap.value.size > 0);

// Real-time status updates via Reverb
let channel = null;
if (currentTeam.value && window.Echo) {
    channel = window.Echo.private(`team.${currentTeam.value.id}.devices`);
    channel.listen('DeviceStatusChanged', (data) => {
        const device = deviceMap.value.get(data.id);
        if (device) {
            deviceMap.value.set(data.id, {
                ...device,
                connected: data.connected,
                last_seen_at: data.last_seen_at,
            });
        }
    });
}

onUnmounted(() => {
    if (currentTeam.value && window.Echo) {
        window.Echo.leave(`team.${currentTeam.value.id}.devices`);
    }
});

function formatLastSeen(dateString) {
    if (!dateString) {
        return 'Never';
    }
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now - date;
    const diffSec = Math.floor(diffMs / 1000);
    const diffMin = Math.floor(diffSec / 60);
    const diffHour = Math.floor(diffMin / 60);
    const diffDay = Math.floor(diffHour / 24);

    if (diffSec < 60) {
        return 'Just now';
    }
    if (diffMin < 60) {
        return `${diffMin}m ago`;
    }
    if (diffHour < 24) {
        return `${diffHour}h ago`;
    }
    if (diffDay < 30) {
        return `${diffDay}d ago`;
    }
    return date.toLocaleDateString();
}

function formatOs(os) {
    const map = { darwin: 'macOS', linux: 'Linux', windows: 'Windows' };
    return map[os] || os;
}
</script>

<template>
    <Head title="Devices" />

    <div class="p-6 md:p-8">
        <h1 class="text-2xl font-bold text-text-heading mb-6">Devices</h1>

        <EmptyDevices v-if="!hasDevices" />

        <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="device in sortedDevices"
                :key="device.id"
                class="rounded-lg border border-border bg-bg-card p-4 transition-colors hover:border-border-hover"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <span
                                class="h-2.5 w-2.5 shrink-0 rounded-full"
                                :class="device.connected ? 'bg-success' : 'bg-error'"
                            ></span>
                            <h2 class="truncate text-sm font-medium text-text-heading">{{ device.name }}</h2>
                        </div>

                        <div class="mt-3 space-y-1.5 text-xs text-text-secondary">
                            <div class="flex items-center gap-2">
                                <span class="w-16 shrink-0 text-text-tertiary">OS</span>
                                <span>{{ formatOs(device.os) }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="w-16 shrink-0 text-text-tertiary">Arch</span>
                                <span>{{ device.arch }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="w-16 shrink-0 text-text-tertiary">Chief</span>
                                <span>{{ device.chief_version }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="w-16 shrink-0 text-text-tertiary">Last seen</span>
                                <span>{{ device.connected ? 'Now' : formatLastSeen(device.last_seen_at) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
