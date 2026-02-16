<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { FolderPlus } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Toggle } from '@/components/ui/toggle';
import { useChiefMessages } from '@/composables/useChiefMessages';
import { useCommandRelay } from '@/composables/useCommandRelay';
import { useToast } from '@/composables/useToast';

const props = defineProps<{
    open: boolean;
    deviceId: number;
    isOnline: boolean;
    existingProjectNames: string[];
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

const { sendCommand } = useCommandRelay();
const { subscribe, on, off, unsubscribe } = useChiefMessages(props.deviceId);
const { success: successToast, error: errorToast } = useToast();

const projectName = ref('');
const nameError = ref('');
const gitInit = ref(true);
const startPrd = ref(true);
const isCreating = ref(false);

const nameInput = ref<{ $el?: HTMLInputElement } | null>(null);

// Filesystem-safe character validation
const INVALID_CHARS = /[<>:"/\\|?*\x00-\x1F]/;
const RESERVED_NAMES = [
    'con',
    'prn',
    'aux',
    'nul',
    'com1',
    'com2',
    'com3',
    'com4',
    'com5',
    'com6',
    'com7',
    'com8',
    'com9',
    'lpt1',
    'lpt2',
    'lpt3',
    'lpt4',
    'lpt5',
    'lpt6',
    'lpt7',
    'lpt8',
    'lpt9',
];

function validateName(name: string): string {
    const trimmed = name.trim();
    if (!trimmed) return '';

    if (INVALID_CHARS.test(trimmed)) {
        return 'Name contains invalid characters';
    }

    if (RESERVED_NAMES.includes(trimmed.toLowerCase())) {
        return 'This name is reserved by the operating system';
    }

    if (trimmed.startsWith('.') || trimmed.startsWith('-')) {
        return 'Name cannot start with a dot or hyphen';
    }

    if (trimmed.endsWith('.') || trimmed.endsWith(' ')) {
        return 'Name cannot end with a dot or space';
    }

    if (trimmed.length > 255) {
        return 'Name is too long (max 255 characters)';
    }

    // Check for duplicates (case-insensitive)
    const isDuplicate = props.existingProjectNames.some(
        (existing) => existing.toLowerCase() === trimmed.toLowerCase(),
    );
    if (isDuplicate) {
        return 'A project with this name already exists on this server';
    }

    return '';
}

// Clear error on input
watch(projectName, () => {
    if (nameError.value) {
        nameError.value = '';
    }
});

// Auto-focus name input when dialog opens
watch(
    () => props.open,
    async (isOpen) => {
        if (isOpen) {
            subscribe();

            await nextTick();
            const inputEl = nameInput.value?.$el ?? nameInput.value;
            if (inputEl instanceof HTMLInputElement) {
                inputEl.focus();
            }
        } else {
            resetForm();
            unsubscribe();
        }
    },
);

function handleNameBlur() {
    const error = validateName(projectName.value);
    nameError.value = error;
}

const canSubmit = computed(() => {
    return (
        props.isOnline &&
        projectName.value.trim() !== '' &&
        !isCreating.value &&
        !nameError.value
    );
});

function handleCreateComplete(message: {
    payload: Record<string, unknown> | null;
}) {
    isCreating.value = false;

    if (message.payload?.error) {
        const errorStr = message.payload.error as string;
        nameError.value = errorStr;
        errorToast('Creation failed', 'Could not create the project.');
        return;
    }

    const projectSlug = message.payload?.project_slug as string | undefined;

    successToast('Project created', `${projectName.value} has been created.`);
    const shouldStartPrd = startPrd.value;
    const slug =
        projectSlug ||
        projectName.value.trim().toLowerCase().replace(/\s+/g, '-');
    close();

    if (shouldStartPrd && slug) {
        router.visit(`/projects/${slug}/prd/new`);
    } else if (slug) {
        router.visit(`/projects/${slug}`);
    }
}

async function handleSubmit() {
    // Validate name
    const error = validateName(projectName.value);
    if (error) {
        nameError.value = error;
        return;
    }

    isCreating.value = true;

    // Listen for create completion
    on('create_project_complete', handleCreateComplete);

    const result = await sendCommand(props.deviceId, 'create_project', {
        project_name: projectName.value.trim(),
        git_init: gitInit.value,
    });

    if (!result) {
        isCreating.value = false;
        off('create_project_complete');
    }
}

function handleCancel() {
    close();
}

function close() {
    emit('update:open', false);
}

function resetForm() {
    projectName.value = '';
    nameError.value = '';
    gitInit.value = true;
    startPrd.value = true;
    isCreating.value = false;
    off('create_project_complete');
}
</script>

<template>
    <Dialog
        :open="open"
        @update:open="
            (val) => {
                if (!val) handleCancel();
                emit('update:open', val);
            }
        "
    >
        <DialogContent
            :class="[
                'sm:max-w-lg',
                'max-sm:fixed max-sm:inset-0 max-sm:top-0 max-sm:left-0 max-sm:h-full max-sm:w-full max-sm:max-w-none max-sm:translate-x-0 max-sm:translate-y-0 max-sm:rounded-none max-sm:border-0',
            ]"
        >
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <FolderPlus class="size-4" />
                    New Project
                </DialogTitle>
                <DialogDescription>
                    Create a new empty project on your server.
                </DialogDescription>
            </DialogHeader>

            <!-- Create form -->
            <div v-if="!isCreating" class="space-y-4">
                <!-- Project name -->
                <div class="space-y-2">
                    <label
                        for="project-name"
                        class="text-sm leading-none font-medium"
                    >
                        Project name
                    </label>
                    <Input
                        id="project-name"
                        ref="nameInput"
                        v-model="projectName"
                        type="text"
                        placeholder="my-project"
                        :aria-invalid="!!nameError || undefined"
                        @blur="handleNameBlur"
                        @keydown.enter="canSubmit && handleSubmit()"
                    />
                    <InputError :message="nameError" />
                    <p class="text-xs text-muted-foreground">
                        Use filesystem-safe characters. No duplicates allowed.
                    </p>
                </div>

                <!-- Git init toggle -->
                <div class="flex items-center justify-between gap-4">
                    <div class="space-y-0.5">
                        <label
                            for="git-init"
                            class="cursor-pointer text-sm leading-none font-medium"
                        >
                            Initialize git repository
                        </label>
                        <p class="text-xs text-muted-foreground">
                            Run
                            <code
                                class="rounded bg-muted px-1 py-0.5 font-mono text-[11px]"
                                >git init</code
                            >
                            in the new project directory.
                        </p>
                    </div>
                    <Toggle
                        id="git-init"
                        v-model="gitInit"
                        aria-label="Initialize git repository"
                    />
                </div>

                <!-- Start PRD creation toggle -->
                <div class="flex items-center justify-between gap-4">
                    <div class="space-y-0.5">
                        <label
                            for="start-prd"
                            class="cursor-pointer text-sm leading-none font-medium"
                        >
                            Start PRD creation
                        </label>
                        <p class="text-xs text-muted-foreground">
                            Open the PRD chat to define what you want to build.
                        </p>
                    </div>
                    <Toggle
                        id="start-prd"
                        v-model="startPrd"
                        aria-label="Start PRD creation after project is created"
                    />
                </div>
            </div>

            <!-- Creating state -->
            <div v-if="isCreating" class="flex items-center gap-2 py-4 text-sm">
                <Spinner class="size-4" />
                <span>Creating project...</span>
            </div>

            <DialogFooter v-if="!isCreating">
                <Button variant="outline" @click="handleCancel">
                    Cancel
                </Button>
                <Button
                    :disabled="!canSubmit"
                    :title="!isOnline ? 'Server offline' : undefined"
                    @click="handleSubmit"
                >
                    Create
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
