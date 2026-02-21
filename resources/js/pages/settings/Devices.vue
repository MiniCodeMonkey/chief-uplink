<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { AlertTriangle, Monitor, Smartphone, Server } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import DeviceController from '@/actions/App/Http/Controllers/Settings/DeviceController';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { EmptyState } from '@/components/ui/empty-state';
import { StatusDot } from '@/components/ui/status-dot';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useChiefCommands } from '@/composables/useChiefCommands';
import { isVersionCompatible, formatVersion } from '@/composables/useVersionCompatibility';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';

type Device = {
    id: number;
    device_name: string;
    os: string | null;
    arch: string | null;
    chief_version: string | null;
    last_connected_at: string | null;
    last_ip: string | null;
    is_online: boolean;
};

defineProps<{
    devices: Device[];
}>();

const page = usePage();
const flash = computed(() => page.props.flash as { success?: string });
const { chiefLoginCommand } = useChiefCommands();

const deauthorizeTarget = ref<Device | null>(null);
const showConfirmDialog = ref(false);
const processing = ref(false);
const removingId = ref<number | null>(null);

function promptDeauthorize(device: Device) {
    deauthorizeTarget.value = device;
    showConfirmDialog.value = true;
}

function confirmDeauthorize() {
    if (!deauthorizeTarget.value) return;

    const deviceId = deauthorizeTarget.value.id;
    processing.value = true;

    const action = DeviceController.destroy.form(deviceId);
    router.delete(action.action, {
        preserveScroll: true,
        onSuccess: () => {
            removingId.value = deviceId;
            showConfirmDialog.value = false;
            processing.value = false;
            deauthorizeTarget.value = null;
            // Clear the removing animation after transition
            setTimeout(() => {
                removingId.value = null;
            }, 300);
        },
        onError: () => {
            processing.value = false;
        },
    });
}

function cancelDeauthorize() {
    showConfirmDialog.value = false;
    deauthorizeTarget.value = null;
}

function formatRelativeTime(dateStr: string | null): string {
    if (!dateStr) return 'Never';
    const date = new Date(dateStr);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffSecs = Math.floor(diffMs / 1000);
    const diffMins = Math.floor(diffSecs / 60);
    const diffHours = Math.floor(diffMins / 60);
    const diffDays = Math.floor(diffHours / 24);

    if (diffSecs < 60) return 'Just now';
    if (diffMins < 60) return `${diffMins}m ago`;
    if (diffHours < 24) return `${diffHours}h ago`;
    if (diffDays < 30) return `${diffDays}d ago`;
    return date.toLocaleDateString();
}

function getDeviceIcon(os: string | null) {
    switch (os) {
        case 'darwin':
            return Monitor;
        case 'windows':
            return Monitor;
        case 'linux':
            return Server;
        default:
            return Smartphone;
    }
}

function formatOs(os: string | null): string {
    switch (os) {
        case 'darwin':
            return 'macOS';
        case 'linux':
            return 'Linux';
        case 'windows':
            return 'Windows';
        default:
            return os ?? 'Unknown';
    }
}
</script>

<template>
    <AppLayout>
        <Head title="Device settings" />

        <h1 class="sr-only">Device Settings</h1>

        <SettingsLayout>
            <div class="space-y-6">
                <Heading
                    variant="small"
                    title="Devices"
                    description="Manage your authorized devices"
                />

                <!-- Success flash message -->
                <Transition
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="opacity-0 -translate-y-1"
                    leave-active-class="transition duration-150 ease-in"
                    leave-to-class="opacity-0 -translate-y-1"
                >
                    <p
                        v-if="flash.success"
                        class="text-sm text-success"
                        role="status"
                    >
                        {{ flash.success }}
                    </p>
                </Transition>

                <!-- Device list -->
                <div
                    v-if="devices.length > 0"
                    class="divide-y divide-border rounded-lg border border-border"
                >
                    <TransitionGroup
                        enter-active-class="transition duration-200 ease-out"
                        enter-from-class="opacity-0 scale-95"
                        leave-active-class="transition duration-200 ease-in"
                        leave-to-class="opacity-0 scale-95 -translate-x-4"
                    >
                        <div
                            v-for="device in devices"
                            :key="device.id"
                            :class="[
                                'flex items-start gap-4 p-4',
                                removingId === device.id && 'pointer-events-none opacity-0 transition-opacity duration-300',
                            ]"
                        >
                            <!-- Device icon -->
                            <div
                                class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground"
                            >
                                <component
                                    :is="getDeviceIcon(device.os)"
                                    class="size-5"
                                />
                            </div>

                            <!-- Device info -->
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <StatusDot
                                        :state="device.is_online ? 'online' : 'offline'"
                                    />
                                    <span class="font-medium text-foreground truncate">
                                        {{ device.device_name }}
                                    </span>
                                </div>

                                <div class="mt-1 flex flex-wrap gap-x-3 gap-y-0.5 text-xs text-muted-foreground">
                                    <span v-if="device.os">
                                        {{ formatOs(device.os) }}<span v-if="device.arch"> ({{ device.arch }})</span>
                                    </span>
                                    <span v-if="device.chief_version" class="inline-flex items-center gap-1">
                                        Chief v{{ formatVersion(device.chief_version) }}
                                        <TooltipProvider v-if="!isVersionCompatible(device.chief_version)">
                                            <Tooltip>
                                                <TooltipTrigger as-child>
                                                    <Badge variant="destructive" class="gap-0.5 px-1 py-0 text-[10px] leading-tight">
                                                        <AlertTriangle class="size-2.5" />
                                                        Outdated
                                                    </Badge>
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    <p>Requires Chief v0.5.0+. Some features may not work.</p>
                                                </TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>
                                    </span>
                                    <span>
                                        Last seen {{ formatRelativeTime(device.last_connected_at) }}
                                    </span>
                                    <span v-if="device.last_ip">
                                        {{ device.last_ip }}
                                    </span>
                                </div>
                            </div>

                            <!-- Deauthorize button -->
                            <Button
                                variant="ghost"
                                size="sm"
                                class="shrink-0 text-destructive hover:text-destructive hover:bg-destructive/10"
                                @click="promptDeauthorize(device)"
                            >
                                Deauthorize
                            </Button>
                        </div>
                    </TransitionGroup>
                </div>

                <!-- Empty state -->
                <EmptyState
                    v-else
                    :icon="Monitor"
                    title="No devices authorized"
                    :description="`Run \`${chiefLoginCommand}\` on your machine to get started.`"
                />
            </div>

            <!-- Confirm deauthorize dialog -->
            <ConfirmDialog
                v-model:open="showConfirmDialog"
                title="Deauthorize device"
                :description="`Are you sure you want to deauthorize &quot;${deauthorizeTarget?.device_name ?? ''}&quot;? This will revoke its access token and disconnect it immediately.`"
                confirm-label="Deauthorize"
                variant="destructive"
                @confirm="confirmDeauthorize"
                @cancel="cancelDeauthorize"
            />
        </SettingsLayout>
    </AppLayout>
</template>
