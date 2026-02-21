import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export function useChiefCommands() {
    const page = usePage();

    const serverUrlFlag = computed(() => {
        const url = page.props.chiefServerUrl as string | null;
        return url ? ` --server-url ${url}` : '';
    });

    const chiefLoginCommand = computed(() => `chief login${serverUrlFlag.value}`);
    const chiefServeCommand = computed(() => `chief serve${serverUrlFlag.value}`);

    return { chiefLoginCommand, chiefServeCommand };
}
