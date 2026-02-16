<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Save } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { Toggle } from '@/components/ui/toggle';
import { useChiefMessages } from '@/composables/useChiefMessages';
import { useCommandRelay } from '@/composables/useCommandRelay';
import { useConnectionStatus } from '@/composables/useConnectionStatus';
import { useToast } from '@/composables/useToast';
import ProjectLayout from '@/layouts/ProjectLayout.vue';

interface ProjectSettings {
    max_iterations: number;
    auto_commit: boolean;
    commit_prefix: string;
    claude_model: string;
    test_command: string;
}

const props = defineProps<{
    projectSlug: string;
    projectName: string;
    deviceId: number;
}>();

const { sendCommand } = useCommandRelay();
const { isOnline } = useConnectionStatus();
const { success, error: errorToast } = useToast();

const serverNotLive = computed(() => !isOnline.value);

// Settings state
const isLoading = ref(true);
const isSaving = ref(false);
const loadError = ref<string | null>(null);

// Server-side values (source of truth)
const serverSettings = ref<ProjectSettings | null>(null);

// Form values (user edits)
const maxIterations = ref(10);
const autoCommit = ref(true);
const commitPrefix = ref('');
const claudeModel = ref('claude-sonnet-4-5-20250929');
const testCommand = ref('');

// Field error tracking
const fieldErrors = ref<Record<string, string>>({});

// Available Claude models
const claudeModels = [
    { value: 'claude-opus-4-6', label: 'Claude Opus 4.6' },
    { value: 'claude-sonnet-4-5-20250929', label: 'Claude Sonnet 4.5' },
    { value: 'claude-haiku-4-5-20251001', label: 'Claude Haiku 4.5' },
];

// Dirty state tracking
const isDirty = computed(() => {
    if (!serverSettings.value) return false;
    return (
        maxIterations.value !== serverSettings.value.max_iterations ||
        autoCommit.value !== serverSettings.value.auto_commit ||
        commitPrefix.value !== serverSettings.value.commit_prefix ||
        claudeModel.value !== serverSettings.value.claude_model ||
        testCommand.value !== serverSettings.value.test_command
    );
});

// Listen for chief messages (settings response)
const chiefMessages = useChiefMessages(props.deviceId);

chiefMessages.on('settings_response', (message) => {
    const payload = message.message as Record<string, unknown>;
    if (payload.project_slug !== props.projectSlug) return;

    const settings = payload.settings as ProjectSettings;
    applySettings(settings);
    isLoading.value = false;
    loadError.value = null;
});

chiefMessages.on('settings_updated', (message) => {
    const payload = message.message as Record<string, unknown>;
    if (payload.project_slug !== props.projectSlug) return;

    const settings = payload.settings as ProjectSettings;
    applySettings(settings);
    isSaving.value = false;
    fieldErrors.value = {};
    success('Settings saved');
});

chiefMessages.on('error', (message) => {
    const payload = message.message as Record<string, unknown>;

    if (isSaving.value) {
        isSaving.value = false;
        const errorMsg = (payload.message as string) || 'Failed to save settings';
        errorToast('Save failed', errorMsg);
    }

    if (isLoading.value) {
        isLoading.value = false;
        loadError.value = (payload.message as string) || 'Failed to load settings';
    }
});

function applySettings(settings: ProjectSettings) {
    serverSettings.value = { ...settings };
    maxIterations.value = settings.max_iterations;
    autoCommit.value = settings.auto_commit;
    commitPrefix.value = settings.commit_prefix;
    claudeModel.value = settings.claude_model;
    testCommand.value = settings.test_command;
}

// Load settings on mount
async function loadSettings() {
    if (!isOnline.value) {
        isLoading.value = false;
        return;
    }

    isLoading.value = true;
    loadError.value = null;

    const result = await sendCommand(props.deviceId, 'get_settings', {
        project_slug: props.projectSlug,
    });

    if (!result) {
        // Command failed (sendCommand handles toast)
        isLoading.value = false;
        loadError.value = 'Failed to load settings. Server may be offline.';
    }
    // On success, the settings_response message handler will update state
}

// Save settings
async function saveSettings() {
    if (!isDirty.value || serverNotLive.value || isSaving.value) return;

    // Basic validation
    fieldErrors.value = {};
    if (maxIterations.value < 1) {
        fieldErrors.value.max_iterations = 'Must be at least 1';
        return;
    }
    if (maxIterations.value > 100) {
        fieldErrors.value.max_iterations = 'Must be 100 or less';
        return;
    }

    isSaving.value = true;

    const result = await sendCommand(props.deviceId, 'update_settings', {
        project_slug: props.projectSlug,
        settings: {
            max_iterations: maxIterations.value,
            auto_commit: autoCommit.value,
            commit_prefix: commitPrefix.value,
            claude_model: claudeModel.value,
            test_command: testCommand.value,
        },
    });

    if (!result) {
        isSaving.value = false;
    }
    // On success, the settings_updated message handler will update state
}

// Warn before navigating away with unsaved changes
function handleBeforeUnload(e: BeforeUnloadEvent) {
    if (isDirty.value) {
        e.preventDefault();
    }
}

// Subscribe to chief messages and load settings on mount
onMounted(() => {
    chiefMessages.subscribe();
    loadSettings();
    window.addEventListener('beforeunload', handleBeforeUnload);
});

onUnmounted(() => {
    window.removeEventListener('beforeunload', handleBeforeUnload);
});

// Re-load settings when device comes back online
watch(isOnline, (online, wasOnline) => {
    if (online && !wasOnline && !serverSettings.value) {
        loadSettings();
    }
});
</script>

<template>
    <Head :title="`${props.projectName} — Settings`" />

    <ProjectLayout :project-slug="props.projectSlug">
        <div class="flex flex-1 flex-col gap-4 p-4">
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Project Settings</CardTitle>
                </CardHeader>
                <CardContent>
                    <!-- Offline state -->
                    <div
                        v-if="serverNotLive && !serverSettings"
                        class="py-8 text-center"
                    >
                        <p class="text-sm text-muted-foreground">
                            Connect to server to manage project settings.
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Settings can only be loaded and saved when the server is online.
                        </p>
                    </div>

                    <!-- Load error state -->
                    <div
                        v-else-if="loadError && !serverSettings"
                        class="py-8 text-center"
                    >
                        <p class="text-sm text-destructive">{{ loadError }}</p>
                        <Button
                            variant="outline"
                            class="mt-4"
                            :disabled="serverNotLive"
                            @click="loadSettings"
                        >
                            Retry
                        </Button>
                    </div>

                    <!-- Skeleton loading state -->
                    <div
                        v-else-if="isLoading"
                        class="space-y-6"
                    >
                        <div v-for="i in 5" :key="i" class="space-y-2">
                            <Skeleton class="h-4 w-32" />
                            <Skeleton class="h-9 w-full" />
                            <Skeleton class="h-3 w-48" />
                        </div>
                    </div>

                    <!-- Settings form -->
                    <form
                        v-else
                        class="content-reveal space-y-6"
                        @submit.prevent="saveSettings"
                    >
                        <!-- Offline notice when form is shown with cached data -->
                        <div
                            v-if="serverNotLive && serverSettings"
                            class="rounded-md border border-border bg-muted/50 px-4 py-3 text-sm text-muted-foreground"
                        >
                            Server is offline. Settings cannot be saved until the server reconnects.
                        </div>

                        <!-- Max Iterations -->
                        <div class="space-y-2">
                            <Label for="max-iterations">Max Iterations</Label>
                            <Input
                                id="max-iterations"
                                v-model.number="maxIterations"
                                type="number"
                                inputmode="numeric"
                                min="1"
                                max="100"
                                :disabled="serverNotLive"
                                :class="{ 'border-destructive': fieldErrors.max_iterations }"
                                :aria-invalid="!!fieldErrors.max_iterations"
                            />
                            <p
                                v-if="fieldErrors.max_iterations"
                                class="text-xs text-destructive"
                            >
                                {{ fieldErrors.max_iterations }}
                            </p>
                            <p v-else class="text-xs text-muted-foreground">
                                Maximum number of iterations Chief will attempt per story before stopping.
                            </p>
                        </div>

                        <!-- Auto Commit -->
                        <div class="flex items-start justify-between gap-4">
                            <div class="space-y-1">
                                <Label for="auto-commit">Auto Commit</Label>
                                <p class="text-xs text-muted-foreground">
                                    Automatically commit changes after each story completes successfully.
                                </p>
                            </div>
                            <Toggle
                                id="auto-commit"
                                v-model="autoCommit"
                                :disabled="serverNotLive"
                                aria-label="Auto commit"
                            />
                        </div>

                        <!-- Commit Prefix -->
                        <div class="space-y-2">
                            <Label for="commit-prefix">Commit Prefix</Label>
                            <Input
                                id="commit-prefix"
                                v-model="commitPrefix"
                                type="text"
                                placeholder="e.g., feat:"
                                :disabled="serverNotLive"
                            />
                            <p class="text-xs text-muted-foreground">
                                Prefix added to all auto-generated commit messages. Leave empty for no prefix.
                            </p>
                        </div>

                        <!-- Claude Model -->
                        <div class="space-y-2">
                            <Label for="claude-model">Claude Model</Label>
                            <Select v-model="claudeModel" :disabled="serverNotLive">
                                <SelectTrigger id="claude-model">
                                    <SelectValue placeholder="Select a model" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="model in claudeModels"
                                        :key="model.value"
                                        :value="model.value"
                                    >
                                        {{ model.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p class="text-xs text-muted-foreground">
                                The Claude model used for code generation and PRD refinement.
                            </p>
                        </div>

                        <!-- Test Command -->
                        <div class="space-y-2">
                            <Label for="test-command">Test Command</Label>
                            <Input
                                id="test-command"
                                v-model="testCommand"
                                type="text"
                                placeholder="e.g., npm test"
                                class="font-mono"
                                :disabled="serverNotLive"
                            />
                            <p class="text-xs text-muted-foreground">
                                Command to run after each story to verify changes. Leave empty to skip tests.
                            </p>
                        </div>

                        <!-- Save button -->
                        <div class="flex items-center justify-end gap-3 border-t border-border pt-4">
                            <p
                                v-if="isDirty"
                                class="text-xs text-muted-foreground"
                            >
                                You have unsaved changes
                            </p>
                            <Button
                                type="submit"
                                :disabled="!isDirty || serverNotLive || isSaving"
                                :title="serverNotLive ? 'Server offline' : !isDirty ? 'No changes to save' : undefined"
                            >
                                <Spinner v-if="isSaving" class="size-4" />
                                <Save v-else class="size-4" />
                                {{ isSaving ? 'Saving...' : 'Save Settings' }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </ProjectLayout>
</template>
