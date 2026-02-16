<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Check, GitFork } from 'lucide-vue-next';
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
import { ProgressBar } from '@/components/ui/progress-bar';
import { Spinner } from '@/components/ui/spinner';
import { useChiefMessages } from '@/composables/useChiefMessages';
import { useCommandRelay } from '@/composables/useCommandRelay';
import { useToast } from '@/composables/useToast';

const props = defineProps<{
    open: boolean;
    deviceId: number;
    isOnline: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

const { sendCommand } = useCommandRelay();
const { subscribe, on, off, unsubscribe } = useChiefMessages(props.deviceId);
const { success: successToast, error: errorToast } = useToast();

const repoUrl = ref('');
const directoryName = ref('');
const directoryManuallyEdited = ref(false);
const urlError = ref('');
const urlValid = ref(false);
const isCloning = ref(false);
const cloneOutput = ref('');
const cloneProgress = ref<number | null>(null);
const cloneError = ref('');

const urlInput = ref<{ $el?: HTMLInputElement } | null>(null);

// Extract repo name from URL for auto-fill
function extractRepoName(url: string): string {
    const trimmed = url.trim();
    if (!trimmed) return '';

    // Remove trailing .git
    let cleaned = trimmed.replace(/\.git$/, '');
    // Remove trailing slashes
    cleaned = cleaned.replace(/\/+$/, '');

    // Extract last segment
    const segments = cleaned.split('/');
    const last = segments[segments.length - 1];

    // For SSH URLs like git@github.com:user/repo
    if (last && last.includes(':')) {
        const parts = last.split(':');
        const sshSegments = parts[parts.length - 1].split('/');
        return sshSegments[sshSegments.length - 1] || '';
    }

    return last || '';
}

// Validate URL format
function validateUrl(url: string): boolean {
    const trimmed = url.trim();
    if (!trimmed) return false;

    // HTTPS URL pattern
    const httpsPattern = /^https?:\/\/.+\/.+/;
    // SSH URL pattern
    const sshPattern = /^git@.+:.+\/.+/;

    return httpsPattern.test(trimmed) || sshPattern.test(trimmed);
}

// Auto-update directory name as URL changes
watch(repoUrl, (newUrl) => {
    if (!directoryManuallyEdited.value) {
        directoryName.value = extractRepoName(newUrl);
    }
    // Reset validation state when URL changes
    if (urlError.value || urlValid.value) {
        urlError.value = '';
        urlValid.value = false;
    }
});

// Auto-focus URL input when dialog opens
watch(
    () => props.open,
    async (isOpen) => {
        if (isOpen) {
            // Subscribe to WebSocket messages
            subscribe();

            await nextTick();
            const inputEl = urlInput.value?.$el ?? urlInput.value;
            if (inputEl instanceof HTMLInputElement) {
                inputEl.focus();
            }
        } else {
            resetForm();
            unsubscribe();
        }
    },
);

function handleUrlBlur() {
    const trimmed = repoUrl.value.trim();
    if (!trimmed) {
        urlError.value = '';
        urlValid.value = false;
        return;
    }

    if (validateUrl(trimmed)) {
        urlError.value = '';
        urlValid.value = true;
    } else {
        urlError.value = 'Enter a valid HTTPS or SSH repository URL';
        urlValid.value = false;
    }
}

function handleDirectoryInput() {
    directoryManuallyEdited.value = true;
}

const canSubmit = computed(() => {
    return (
        props.isOnline &&
        repoUrl.value.trim() !== '' &&
        directoryName.value.trim() !== '' &&
        !isCloning.value &&
        !urlError.value
    );
});

function handleCloneProgress(message: { payload: Record<string, unknown> | null }) {
    if (!message.payload) return;

    if (message.payload.output) {
        cloneOutput.value += message.payload.output as string;
    }

    if (typeof message.payload.percentage === 'number') {
        cloneProgress.value = message.payload.percentage;
    }
}

function handleCloneComplete(message: { payload: Record<string, unknown> | null }) {
    isCloning.value = false;

    if (message.payload?.error) {
        cloneError.value = message.payload.error as string;

        // Provide actionable suggestions
        const errorStr = cloneError.value.toLowerCase();
        if (errorStr.includes('authentication') || errorStr.includes('permission') || errorStr.includes('denied')) {
            cloneError.value += '\n\nEnsure the server\'s SSH key has access to this repository.';
        } else if (errorStr.includes('not found') || errorStr.includes('does not exist')) {
            cloneError.value += '\n\nCheck the repository URL and try again.';
        }

        errorToast('Clone failed', 'Could not clone the repository.');
        return;
    }

    const projectSlug = message.payload?.project_slug as string | undefined;

    successToast('Repository cloned', `Successfully cloned to ${directoryName.value}`);
    close();

    if (projectSlug) {
        router.visit(`/projects/${projectSlug}`);
    }
}

async function handleSubmit() {
    // Validate URL first
    if (!validateUrl(repoUrl.value.trim())) {
        urlError.value = 'Enter a valid HTTPS or SSH repository URL';
        return;
    }

    isCloning.value = true;
    cloneOutput.value = '';
    cloneProgress.value = null;
    cloneError.value = '';

    // Listen for clone progress and completion
    on('clone_progress', handleCloneProgress);
    on('clone_complete', handleCloneComplete);

    const result = await sendCommand(props.deviceId, 'clone_repo', {
        url: repoUrl.value.trim(),
        directory: directoryName.value.trim(),
    });

    if (!result) {
        isCloning.value = false;
        off('clone_progress');
        off('clone_complete');
    }
}

function handleCancel() {
    if (isCloning.value) {
        // TODO: Send cancel command to chief if supported
        isCloning.value = false;
        off('clone_progress');
        off('clone_complete');
    }
    close();
}

function close() {
    emit('update:open', false);
}

function resetForm() {
    repoUrl.value = '';
    directoryName.value = '';
    directoryManuallyEdited.value = false;
    urlError.value = '';
    urlValid.value = false;
    isCloning.value = false;
    cloneOutput.value = '';
    cloneProgress.value = null;
    cloneError.value = '';
    off('clone_progress');
    off('clone_complete');
}
</script>

<template>
    <Dialog
        :open="open"
        @update:open="(val) => { if (!val) handleCancel(); emit('update:open', val); }"
    >
        <DialogContent
            :class="[
                'sm:max-w-lg',
                'max-sm:fixed max-sm:inset-0 max-sm:translate-x-0 max-sm:translate-y-0 max-sm:top-0 max-sm:left-0 max-sm:max-w-none max-sm:rounded-none max-sm:border-0 max-sm:h-full max-sm:w-full',
            ]"
        >
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <GitFork class="size-4" />
                    Clone Repository
                </DialogTitle>
                <DialogDescription>
                    Clone a git repository to your server.
                </DialogDescription>
            </DialogHeader>

            <!-- Clone form -->
            <div v-if="!isCloning && !cloneError" class="space-y-4">
                <!-- Repository URL -->
                <div class="space-y-2">
                    <label
                        for="repo-url"
                        class="text-sm font-medium leading-none"
                    >
                        Repository URL
                    </label>
                    <div class="relative">
                        <Input
                            id="repo-url"
                            ref="urlInput"
                            v-model="repoUrl"
                            type="url"
                            inputmode="url"
                            placeholder="https://github.com/user/repo.git"
                            :aria-invalid="!!urlError || undefined"
                            @blur="handleUrlBlur"
                        />
                        <Transition
                            enter-active-class="transition duration-[var(--duration-micro)]"
                            enter-from-class="opacity-0 scale-75"
                            enter-to-class="opacity-100 scale-100"
                            leave-active-class="transition duration-[var(--duration-micro)]"
                            leave-from-class="opacity-100 scale-100"
                            leave-to-class="opacity-0 scale-75"
                        >
                            <div
                                v-if="urlValid"
                                class="absolute right-2.5 top-1/2 -translate-y-1/2"
                            >
                                <Check class="size-4 text-success" />
                            </div>
                        </Transition>
                    </div>
                    <InputError :message="urlError" />
                </div>

                <!-- Directory name -->
                <div class="space-y-2">
                    <label
                        for="dir-name"
                        class="text-sm font-medium leading-none"
                    >
                        Directory name
                    </label>
                    <Input
                        id="dir-name"
                        v-model="directoryName"
                        type="text"
                        placeholder="my-project"
                        @input="handleDirectoryInput"
                    />
                </div>

                <!-- Info text -->
                <p class="text-xs text-muted-foreground">
                    Private repos require SSH access configured on the server. No credentials are stored in the web app.
                </p>
            </div>

            <!-- Clone progress -->
            <div v-if="isCloning" class="space-y-4">
                <div class="space-y-2">
                    <div class="flex items-center gap-2 text-sm">
                        <Spinner class="size-4" />
                        <span>Cloning repository...</span>
                    </div>

                    <ProgressBar
                        v-if="cloneProgress !== null"
                        :value="cloneProgress"
                        :max="100"
                        class="h-1.5"
                        indicator-class="transition-all duration-300"
                    />
                </div>

                <!-- Clone output -->
                <div
                    v-if="cloneOutput"
                    class="max-h-40 overflow-y-auto rounded-md border border-border bg-muted/50 p-3 font-mono text-xs text-muted-foreground"
                >
                    <pre class="whitespace-pre-wrap">{{ cloneOutput }}</pre>
                </div>
            </div>

            <!-- Clone error -->
            <div v-if="cloneError" class="space-y-3">
                <div class="rounded-md border border-destructive/30 bg-destructive/5 p-3">
                    <p class="text-sm text-destructive whitespace-pre-wrap">
                        {{ cloneError }}
                    </p>
                </div>
                <Button
                    variant="outline"
                    size="sm"
                    @click="cloneError = ''; isCloning = false"
                >
                    Try again
                </Button>
            </div>

            <DialogFooter v-if="!isCloning && !cloneError">
                <Button variant="outline" @click="handleCancel">
                    Cancel
                </Button>
                <Button
                    :disabled="!canSubmit"
                    :title="!isOnline ? 'Server offline' : undefined"
                    @click="handleSubmit"
                >
                    Clone
                </Button>
            </DialogFooter>

            <!-- Cancel during clone -->
            <DialogFooter v-if="isCloning">
                <Button variant="outline" @click="handleCancel">
                    Cancel
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
