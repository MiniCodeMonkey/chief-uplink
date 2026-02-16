import { usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import type { DeviceSummary } from '@/types';

const MIN_CHIEF_VERSION = '0.5.0';

/**
 * Compare two semver version strings.
 * Returns -1 if a < b, 0 if a == b, 1 if a > b.
 */
function compareVersions(a: string, b: string): number {
    const partsA = a.split('.').map(Number);
    const partsB = b.split('.').map(Number);
    const len = Math.max(partsA.length, partsB.length);

    for (let i = 0; i < len; i++) {
        const numA = partsA[i] ?? 0;
        const numB = partsB[i] ?? 0;
        if (numA < numB) return -1;
        if (numA > numB) return 1;
    }
    return 0;
}

/**
 * Check if a version meets the minimum required version.
 */
export function isVersionCompatible(version: string | null): boolean {
    if (!version) return false;
    return compareVersions(version, MIN_CHIEF_VERSION) >= 0;
}

// Session-scoped set of dismissed device IDs
const dismissedDeviceIds = ref(new Set<number>());

/**
 * Composable that provides version compatibility information
 * for the currently selected device.
 */
export function useVersionCompatibility() {
    const page = usePage();

    const selectedDeviceId = computed(
        () => page.props.selectedDeviceId as number | null,
    );

    const devices = computed(
        () => (page.props.devices as DeviceSummary[]) || [],
    );

    const selectedDevice = computed(() => {
        if (!selectedDeviceId.value) return devices.value[0] ?? null;
        return (
            devices.value.find((d) => d.id === selectedDeviceId.value) ??
            devices.value[0] ??
            null
        );
    });

    const isCompatible = computed(() => {
        if (!selectedDevice.value) return true;
        return isVersionCompatible(selectedDevice.value.chief_version);
    });

    const isDismissed = computed(() => {
        if (!selectedDevice.value) return false;
        return dismissedDeviceIds.value.has(selectedDevice.value.id);
    });

    const showWarning = computed(() => {
        return !isCompatible.value && !isDismissed.value;
    });

    const deviceVersion = computed(() => {
        return selectedDevice.value?.chief_version ?? null;
    });

    function dismiss() {
        if (selectedDevice.value) {
            dismissedDeviceIds.value.add(selectedDevice.value.id);
        }
    }

    return {
        isCompatible,
        showWarning,
        deviceVersion,
        minVersion: MIN_CHIEF_VERSION,
        dismiss,
    };
}
