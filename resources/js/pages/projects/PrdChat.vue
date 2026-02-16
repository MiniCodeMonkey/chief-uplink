<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    AlertTriangle,
    ArrowDown,
    ArrowLeft,
    ChevronDown,
    Clock,
    Eye,
    Loader2,
    MessageSquare,
    RefreshCw,
    Save,
    Send,
} from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import PrdPreviewPanel from '@/components/PrdPreviewPanel.vue';
import { Button } from '@/components/ui/button';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useChiefMessages } from '@/composables/useChiefMessages';
import { useCommandRelay } from '@/composables/useCommandRelay';
import { useConnectionStatus } from '@/composables/useConnectionStatus';
import { useDeviceStatus } from '@/composables/useDeviceStatus';
import { useEchoConnectionStatus } from '@/composables/useEcho';
import { useToast } from '@/composables/useToast';

interface ChatMessage {
    id: string;
    role: 'user' | 'claude';
    content: string;
    isStreaming?: boolean;
}

const props = defineProps<{
    projectSlug: string;
    projectName: string;
    deviceId: number;
    mode: 'create' | 'refine';
    prdId?: string;
    hasActiveRun?: boolean;
}>();

const isRefineMode = computed(() => props.mode === 'refine');

useDeviceStatus();
const { isOnline } = useConnectionStatus();
const { sendCommand } = useCommandRelay();
const { subscribe, on } = useChiefMessages(props.deviceId);
const { success, error: errorToast } = useToast();
const { isConnected: echoConnected } = useEchoConnectionStatus();

// Chat state
const messages = ref<ChatMessage[]>([]);
const userInput = ref('');
const isClaudeResponding = ref(false);
const sessionId = ref<string | null>(null);
const hasActiveSession = ref(false);
const isSaving = ref(false);
const saveAction = ref<'close' | 'run' | null>(null);
const saveStep = ref<'saving' | 'starting' | null>(null);
const sessionExpired = ref(false);
const isResuming = ref(false);
const showRunConfirm = ref(false);

// Session timeout tracking
const sessionTimeoutRemaining = ref<number | null>(null);
let timeoutTickInterval: ReturnType<typeof setInterval> | null = null;

// Preview state — accumulated PRD content from Claude messages
const prdContent = ref('');

// Mobile view toggle
const mobileView = ref<'chat' | 'preview'>('chat');

// Resizable divider state
const dividerPosition = ref(50); // percentage
const isDragging = ref(false);
const containerRef = ref<HTMLElement | null>(null);

// Refs for DOM elements
const messagesContainer = ref<HTMLElement | null>(null);
const textareaRef = ref<HTMLTextAreaElement | null>(null);
const isAutoScrollPaused = ref(false);

// Reconnection tracking
const wasDisconnected = ref(false);
const isReplayingMessages = ref(false);

// Message ID counter
let messageIdCounter = 0;
function generateMessageId(): string {
    return `msg-${++messageIdCounter}-${Date.now()}`;
}

// Generate session ID
function generateSessionId(): string {
    return `session-${Date.now()}-${Math.random().toString(36).slice(2, 11)}`;
}

const serverNotLive = computed(() => !isOnline.value);

// Computed: has any preview content to show
const hasPreviewContent = computed(() => prdContent.value.length > 0);

// Computed: formatted countdown timer text
const countdownText = computed(() => {
    if (sessionTimeoutRemaining.value === null) return null;
    const total = sessionTimeoutRemaining.value;
    if (total <= 0) return '0:00';
    const mins = Math.floor(total / 60);
    const secs = total % 60;
    return `${mins}:${secs.toString().padStart(2, '0')}`;
});

// Computed: should show timer prominently (< 1 minute)
const isTimerUrgent = computed(() => {
    return sessionTimeoutRemaining.value !== null && sessionTimeoutRemaining.value < 60;
});

// Computed: should show timer at all (< 10 minutes or always when session is active)
const showTimer = computed(() => {
    return hasActiveSession.value && sessionTimeoutRemaining.value !== null && sessionTimeoutRemaining.value <= 600;
});

// Text area auto-resize
function adjustTextareaHeight() {
    const textarea = textareaRef.value;
    if (!textarea) return;

    // Reset height to auto to get the actual scrollHeight
    textarea.style.height = 'auto';

    // Max height is roughly 6 lines (6 * line-height ~24px = 144px)
    const maxHeight = 144;
    const scrollHeight = textarea.scrollHeight;
    textarea.style.height = `${Math.min(scrollHeight, maxHeight)}px`;

    // Enable internal scrolling if content exceeds max height
    textarea.style.overflowY = scrollHeight > maxHeight ? 'auto' : 'hidden';
}

watch(userInput, () => {
    nextTick(adjustTextareaHeight);
});

// Auto-scroll to bottom
function scrollToBottom(smooth = true) {
    if (!messagesContainer.value) return;
    messagesContainer.value.scrollTo({
        top: messagesContainer.value.scrollHeight,
        behavior: smooth ? 'smooth' : 'instant',
    });
}

function handleScroll() {
    if (!messagesContainer.value) return;
    const { scrollTop, scrollHeight, clientHeight } = messagesContainer.value;
    const distanceFromBottom = scrollHeight - scrollTop - clientHeight;
    isAutoScrollPaused.value = distanceFromBottom > 50;
}

function jumpToBottom() {
    isAutoScrollPaused.value = false;
    scrollToBottom();
}

// Focus the textarea
function focusInput() {
    nextTick(() => {
        textareaRef.value?.focus();
    });
}

// Resizable divider handlers
function startDrag(e: MouseEvent) {
    e.preventDefault();
    isDragging.value = true;
    document.addEventListener('mousemove', onDrag);
    document.addEventListener('mouseup', stopDrag);
}

function onDrag(e: MouseEvent) {
    if (!isDragging.value || !containerRef.value) return;
    const rect = containerRef.value.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const percentage = (x / rect.width) * 100;
    // Clamp between 25% and 75%
    dividerPosition.value = Math.max(25, Math.min(75, percentage));
}

function stopDrag() {
    isDragging.value = false;
    document.removeEventListener('mousemove', onDrag);
    document.removeEventListener('mouseup', stopDrag);
}

// Start the countdown timer tick
function startTimeoutTick(initialRemaining: number) {
    sessionTimeoutRemaining.value = initialRemaining;

    // Clear any existing ticker
    if (timeoutTickInterval) {
        clearInterval(timeoutTickInterval);
    }

    timeoutTickInterval = setInterval(() => {
        if (sessionTimeoutRemaining.value !== null && sessionTimeoutRemaining.value > 0) {
            sessionTimeoutRemaining.value--;
        } else if (sessionTimeoutRemaining.value !== null && sessionTimeoutRemaining.value <= 0) {
            // Timer reached zero — the backend will send session_expired
            if (timeoutTickInterval) {
                clearInterval(timeoutTickInterval);
                timeoutTickInterval = null;
            }
        }
    }, 1000);
}

// Reset the timeout timer (called when sending a message)
function resetTimeoutTimer() {
    const timeout = 1800; // 30 minutes default
    startTimeoutTick(timeout);
}

// Send a user message
async function handleSend() {
    const text = userInput.value.trim();
    if (!text || isClaudeResponding.value || serverNotLive.value || sessionExpired.value) return;

    // Add user message
    const userMsg: ChatMessage = {
        id: generateMessageId(),
        role: 'user',
        content: text,
    };
    messages.value.push(userMsg);

    // Clear input and reset textarea height
    userInput.value = '';
    nextTick(() => {
        adjustTextareaHeight();
        scrollToBottom();
    });

    // Mark Claude as responding
    isClaudeResponding.value = true;

    // Add a placeholder Claude message for streaming
    const claudeMsg: ChatMessage = {
        id: generateMessageId(),
        role: 'claude',
        content: '',
        isStreaming: true,
    };
    messages.value.push(claudeMsg);

    if (!hasActiveSession.value) {
        // First message — start the session
        sessionId.value = generateSessionId();
        hasActiveSession.value = true;

        let result;
        if (isRefineMode.value && props.prdId) {
            // Refine existing PRD
            result = await sendCommand(props.deviceId, 'refine_prd', {
                project_slug: props.projectSlug,
                session_id: sessionId.value,
                prd_id: props.prdId,
                message: text,
            });
        } else {
            // Create new PRD
            result = await sendCommand(props.deviceId, 'new_prd', {
                project_slug: props.projectSlug,
                session_id: sessionId.value,
                message: text,
            });
        }

        // Start timeout tracking from server response
        if (result) {
            const remaining = result.session_timeout_remaining;
            if (remaining !== undefined) {
                startTimeoutTick(remaining);
            } else {
                resetTimeoutTimer();
            }
        }
    } else {
        // Subsequent message — send via prd_message (resets inactivity timer)
        const result = await sendCommand(props.deviceId, 'prd_message', {
            project_slug: props.projectSlug,
            session_id: sessionId.value,
            message: text,
        });

        // Update timeout from server response
        if (result) {
            const remaining = result.session_timeout_remaining;
            if (remaining !== undefined) {
                startTimeoutTick(remaining);
            } else {
                resetTimeoutTimer();
            }
        }
    }
}

// Handle keyboard events in textarea
function handleKeydown(e: KeyboardEvent) {
    // Desktop: Enter sends, Shift+Enter adds newline
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        handleSend();
    }
}

// Handle save actions
async function handleSaveAndClose() {
    if (!hasActiveSession.value || isSaving.value) return;

    isSaving.value = true;
    saveAction.value = 'close';
    saveStep.value = 'saving';

    const closePayload: Record<string, unknown> = {
        project_slug: props.projectSlug,
        session_id: sessionId.value,
        save: true,
    };
    if (isRefineMode.value && props.prdId) {
        closePayload.prd_id = props.prdId;
    }

    const result = await sendCommand(props.deviceId, 'close_prd_session', closePayload);

    if (result) {
        hasActiveSession.value = false;
        clearTimeoutTimer();
        success('PRD saved');
        router.visit(`/projects/${props.projectSlug}/prds`);
    } else {
        errorToast('Save failed', 'Failed to save the PRD. Please try again.');
        isSaving.value = false;
        saveAction.value = null;
        saveStep.value = null;
    }
}

function handleSaveAndRunClick() {
    if (!hasActiveSession.value || isSaving.value) return;

    // If another run is active, show confirmation dialog
    if (props.hasActiveRun) {
        showRunConfirm.value = true;
        return;
    }

    executeSaveAndRun();
}

async function executeSaveAndRun() {
    showRunConfirm.value = false;
    if (!hasActiveSession.value || isSaving.value) return;

    isSaving.value = true;
    saveAction.value = 'run';
    saveStep.value = 'saving';

    const closePayload: Record<string, unknown> = {
        project_slug: props.projectSlug,
        session_id: sessionId.value,
        save: true,
    };
    if (isRefineMode.value && props.prdId) {
        closePayload.prd_id = props.prdId;
    }

    const closeResult = await sendCommand(props.deviceId, 'close_prd_session', closePayload);

    if (!closeResult) {
        errorToast('Save failed', 'Failed to save the PRD. Please try again.');
        isSaving.value = false;
        saveAction.value = null;
        saveStep.value = null;
        return;
    }

    hasActiveSession.value = false;
    clearTimeoutTimer();

    // Step 2: Start the run
    saveStep.value = 'starting';

    const startPayload: Record<string, unknown> = {
        project_slug: props.projectSlug,
    };
    // Pass the PRD ID so chief knows which PRD to run
    if (isRefineMode.value && props.prdId) {
        startPayload.prd_id = props.prdId;
    }

    const startResult = await sendCommand(props.deviceId, 'start_run', startPayload);

    if (startResult) {
        success('PRD saved — starting run');
        router.visit(`/projects/${props.projectSlug}/run`);
    } else {
        errorToast(
            'Run failed to start',
            'PRD was saved but the run could not start. Check the PRD and try again from the Run tab.',
        );
        // Navigate to run tab so user can see the error and retry
        router.visit(`/projects/${props.projectSlug}/run`);
    }
}

// Resume an expired session
async function handleResume() {
    if (isResuming.value || serverNotLive.value) return;

    isResuming.value = true;
    sessionExpired.value = false;

    // Generate a new session ID for the resumed session
    sessionId.value = generateSessionId();
    hasActiveSession.value = true;

    // Add a system message to indicate resumption
    const systemMsg: ChatMessage = {
        id: generateMessageId(),
        role: 'claude',
        content: '_Session resumed. You can continue where you left off._',
        isStreaming: false,
    };
    messages.value.push(systemMsg);

    // Start a new session with context from the saved PRD
    let result;
    if (isRefineMode.value && props.prdId) {
        result = await sendCommand(props.deviceId, 'refine_prd', {
            project_slug: props.projectSlug,
            session_id: sessionId.value,
            prd_id: props.prdId,
            message: 'Continue from where we left off. The session timed out.',
        });
    } else {
        result = await sendCommand(props.deviceId, 'new_prd', {
            project_slug: props.projectSlug,
            session_id: sessionId.value,
            message: 'Continue from where we left off. The session timed out.',
        });
    }

    if (result) {
        isClaudeResponding.value = true;
        const remaining = result.session_timeout_remaining;
        if (remaining !== undefined) {
            startTimeoutTick(remaining);
        } else {
            resetTimeoutTimer();
        }

        // Add Claude message placeholder for the streaming response
        const claudeMsg: ChatMessage = {
            id: generateMessageId(),
            role: 'claude',
            content: '',
            isStreaming: true,
        };
        messages.value.push(claudeMsg);
    } else {
        sessionExpired.value = true;
        hasActiveSession.value = false;
        errorToast('Resume failed', 'Could not resume the session. Please try again.');
    }

    isResuming.value = false;
    focusInput();
}

// Clear timeout timer
function clearTimeoutTimer() {
    if (timeoutTickInterval) {
        clearInterval(timeoutTickInterval);
        timeoutTickInterval = null;
    }
    sessionTimeoutRemaining.value = null;
}

// Back navigation with unsaved changes confirmation
function handleBack() {
    if (hasActiveSession.value) {
        if (confirm('You have an active session. Leave without saving?')) {
            // Kill the session without saving
            const closePayload: Record<string, unknown> = {
                project_slug: props.projectSlug,
                session_id: sessionId.value,
                save: false,
            };
            if (isRefineMode.value && props.prdId) {
                closePayload.prd_id = props.prdId;
            }
            sendCommand(props.deviceId, 'close_prd_session', closePayload);
            hasActiveSession.value = false;
            clearTimeoutTimer();
            router.visit(`/projects/${props.projectSlug}/prds`);
        }
    } else {
        router.visit(`/projects/${props.projectSlug}/prds`);
    }
}

// Before unload warning
function handleBeforeUnload(e: BeforeUnloadEvent) {
    if (hasActiveSession.value) {
        e.preventDefault();
    }
}

// Replay buffered messages after a browser reconnection
async function replayBufferedMessages() {
    if (!sessionId.value || isReplayingMessages.value) return;

    isReplayingMessages.value = true;

    try {
        const response = await axios.post('/ws/buffer/replay', {
            device_id: props.deviceId,
        });

        const sessions = response.data.sessions as Record<string, Array<{ message: Record<string, unknown>; timestamp: number }>> | undefined;
        if (!sessions) {
            isReplayingMessages.value = false;
            return;
        }

        // Process all buffered messages across sessions, filtering for our PRD session
        for (const [, bufferedMessages] of Object.entries(sessions)) {
            for (const entry of bufferedMessages) {
                const msg = entry.message as Record<string, unknown>;
                const type = msg.type as string;

                // Only process messages for our current session
                const msgSessionId = msg.session_id as string | undefined;
                if (msgSessionId && msgSessionId !== sessionId.value) continue;

                if (type === 'prd_output') {
                    const text = msg.text as string;
                    if (text) {
                        const lastMsg = messages.value[messages.value.length - 1];
                        if (lastMsg && lastMsg.role === 'claude' && lastMsg.isStreaming) {
                            lastMsg.content += text;
                        }
                        prdContent.value = getLatestClaudeContent();
                    }
                } else if (type === 'prd_response_complete') {
                    isClaudeResponding.value = false;
                    const lastMsg = messages.value[messages.value.length - 1];
                    if (lastMsg && lastMsg.role === 'claude') {
                        lastMsg.isStreaming = false;
                    }
                    prdContent.value = getLatestClaudeContent();
                } else if (type === 'session_expired') {
                    isClaudeResponding.value = false;
                    hasActiveSession.value = false;
                    sessionExpired.value = true;
                    clearTimeoutTimer();
                    const lastMsg = messages.value[messages.value.length - 1];
                    if (lastMsg && lastMsg.role === 'claude' && lastMsg.isStreaming) {
                        lastMsg.isStreaming = false;
                    }
                }
            }
        }

        nextTick(() => scrollToBottom(false));
    } catch {
        // Buffer replay is best-effort — don't block on failure
    } finally {
        isReplayingMessages.value = false;
    }
}

// Watch for Echo reconnection to replay missed messages
watch(echoConnected, (connected, wasConnected) => {
    if (connected && !wasConnected && wasDisconnected.value && hasActiveSession.value) {
        replayBufferedMessages();
    }
    if (!connected) {
        wasDisconnected.value = true;
    }
});

// Listen for streaming output from chief
onMounted(() => {
    subscribe();

    // Handle PRD chat output (claude streaming)
    on('prd_output', (message) => {
        const payload = message.message as Record<string, unknown>;
        if (payload.session_id !== sessionId.value) return;

        const text = payload.text as string;
        if (!text) return;

        // Append to the last Claude message if streaming
        const lastMsg = messages.value[messages.value.length - 1];
        if (lastMsg && lastMsg.role === 'claude' && lastMsg.isStreaming) {
            lastMsg.content += text;
        }

        // Update PRD preview content — accumulate the latest Claude response
        // The PRD content is the latest Claude message being generated
        prdContent.value = getLatestClaudeContent();

        if (!isAutoScrollPaused.value) {
            nextTick(() => scrollToBottom());
        }
    });

    // Handle Claude response complete
    on('prd_response_complete', (message) => {
        const payload = message.message as Record<string, unknown>;
        if (payload.session_id !== sessionId.value) return;

        isClaudeResponding.value = false;

        // Mark the last Claude message as not streaming
        const lastMsg = messages.value[messages.value.length - 1];
        if (lastMsg && lastMsg.role === 'claude') {
            lastMsg.isStreaming = false;
        }

        // Update PRD content with final state
        prdContent.value = getLatestClaudeContent();

        focusInput();
    });

    // Handle errors
    on('error', (message) => {
        const payload = message.message as Record<string, unknown>;
        if (payload.session_id && payload.session_id !== sessionId.value) return;

        isClaudeResponding.value = false;

        // Mark the last Claude message as not streaming
        const lastMsg = messages.value[messages.value.length - 1];
        if (lastMsg && lastMsg.role === 'claude' && lastMsg.isStreaming) {
            if (lastMsg.content === '') {
                // Remove empty Claude message if error occurred before any output
                messages.value.pop();
            } else {
                lastMsg.isStreaming = false;
            }
        }

        // Update PRD content
        prdContent.value = getLatestClaudeContent();

        focusInput();
    });

    // Handle session timeout warning
    on('session_timeout_warning', (message) => {
        const payload = message.message as Record<string, unknown>;
        if (payload.session_id !== sessionId.value) return;

        const minutesRemaining = payload.minutes_remaining as number;

        // Update the countdown timer
        startTimeoutTick(minutesRemaining * 60);

        if (minutesRemaining <= 1) {
            errorToast('Session expiring', 'Your session will expire in less than 1 minute. Save your work.');
        } else {
            useToast().warning('Session timeout', `Session will expire in ${minutesRemaining} minutes.`);
        }
    });

    // Handle session expired
    on('session_expired', (message) => {
        const payload = message.message as Record<string, unknown>;
        if (payload.session_id !== sessionId.value) return;

        isClaudeResponding.value = false;
        hasActiveSession.value = false;
        sessionExpired.value = true;
        clearTimeoutTimer();

        const lastMsg = messages.value[messages.value.length - 1];
        if (lastMsg && lastMsg.role === 'claude' && lastMsg.isStreaming) {
            lastMsg.isStreaming = false;
        }

        prdContent.value = getLatestClaudeContent();
    });

    window.addEventListener('beforeunload', handleBeforeUnload);

    // Auto-focus the input
    focusInput();
});

onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', handleBeforeUnload);
    clearTimeoutTimer();
    // Clean up drag listeners
    document.removeEventListener('mousemove', onDrag);
    document.removeEventListener('mouseup', stopDrag);
});

// Get the latest Claude message content for the PRD preview
// Shows the most recent Claude response as the PRD being generated
function getLatestClaudeContent(): string {
    const claudeMessages = messages.value.filter((m) => m.role === 'claude' && m.content);
    if (claudeMessages.length === 0) return '';
    return claudeMessages[claudeMessages.length - 1].content;
}

// Simple markdown-like rendering for Claude messages in chat bubbles
const renderedMarkdown = computed(() => {
    const cache = new Map<string, string>();
    return (content: string) => {
        if (cache.has(content)) return cache.get(content)!;
        // Basic markdown rendering — escape HTML, then handle basic patterns
        let html = content
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');

        // Code blocks (```...```)
        html = html.replace(/```(\w*)\n([\s\S]*?)```/g, (_match, lang, code) => {
            return `<pre class="my-2 rounded-md bg-background p-3 text-sm"><code class="language-${lang}">${code}</code></pre>`;
        });

        // Inline code (`...`)
        html = html.replace(/`([^`]+)`/g, '<code class="rounded bg-background px-1.5 py-0.5 font-mono text-sm">$1</code>');

        // Bold (**...**)
        html = html.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');

        // Italic (*...*)
        html = html.replace(/(?<!\*)\*([^*]+)\*(?!\*)/g, '<em>$1</em>');

        // Headers (# ...)
        html = html.replace(/^### (.+)$/gm, '<h3 class="mt-3 mb-1 text-sm font-semibold">$1</h3>');
        html = html.replace(/^## (.+)$/gm, '<h2 class="mt-4 mb-1 text-base font-semibold">$1</h2>');
        html = html.replace(/^# (.+)$/gm, '<h1 class="mt-4 mb-2 text-lg font-bold">$1</h1>');

        // Unordered lists (- ...)
        html = html.replace(/^- (.+)$/gm, '<li class="ml-4 list-disc">$1</li>');

        // Ordered lists (1. ...)
        html = html.replace(/^\d+\. (.+)$/gm, '<li class="ml-4 list-decimal">$1</li>');

        // Line breaks
        html = html.replace(/\n/g, '<br>');

        cache.set(content, html);
        return html;
    };
});

// Save button label
const saveButtonLabel = computed(() => {
    if (isSaving.value) {
        if (saveAction.value === 'run') {
            return saveStep.value === 'starting' ? 'Starting run...' : 'Saving PRD...';
        }
        return 'Saving...';
    }
    return 'Save';
});
</script>

<template>
    <Head :title="`${props.projectName} — ${isRefineMode ? 'Refine PRD' : 'New PRD'}`" />

    <div class="flex h-screen w-full flex-col bg-background">
        <!-- Custom header for chat page (no tab bar) -->
        <header class="border-b border-border">
            <div class="flex h-14 items-center justify-between px-4">
                <!-- Left: Back button + title -->
                <div class="flex items-center gap-2">
                    <button
                        class="focus-ring flex items-center justify-center rounded-md p-1.5 text-muted-foreground transition-colors duration-[var(--duration-micro)] hover:bg-accent hover:text-foreground"
                        style="min-width: 44px; min-height: 44px"
                        aria-label="Back to PRDs"
                        @click="handleBack"
                    >
                        <ArrowLeft class="size-5" />
                    </button>
                    <div class="min-w-0">
                        <h1 class="truncate text-sm font-semibold">{{ isRefineMode ? 'Refine PRD' : 'New PRD' }}</h1>
                        <p class="truncate text-xs text-muted-foreground">{{ props.projectName }}</p>
                    </div>
                </div>

                <!-- Center: Mobile view toggle + countdown timer -->
                <div class="flex items-center gap-3">
                    <!-- Countdown timer -->
                    <div
                        v-if="showTimer"
                        class="flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium transition-all duration-[var(--duration-standard)]"
                        :class="isTimerUrgent
                            ? 'bg-destructive/10 text-destructive scale-110'
                            : 'text-muted-foreground'"
                        role="timer"
                        :aria-label="`Session expires in ${countdownText}`"
                    >
                        <Clock class="size-3.5" :class="{ 'animate-pulse': isTimerUrgent }" />
                        <span :class="{ 'text-sm font-semibold': isTimerUrgent }">{{ countdownText }}</span>
                    </div>

                    <div class="flex items-center lg:hidden">
                        <div class="flex rounded-lg border border-border p-0.5">
                            <button
                                class="focus-ring rounded-md px-3 py-1 text-xs font-medium transition-colors duration-[var(--duration-micro)]"
                                :class="mobileView === 'chat' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'"
                                @click="mobileView = 'chat'"
                            >
                                <MessageSquare class="mr-1 inline-block size-3" />
                                Chat
                            </button>
                            <button
                                class="focus-ring rounded-md px-3 py-1 text-xs font-medium transition-colors duration-[var(--duration-micro)]"
                                :class="mobileView === 'preview' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'"
                                :disabled="!hasPreviewContent"
                                @click="mobileView = 'preview'"
                            >
                                <Eye class="mr-1 inline-block size-3" />
                                Preview
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Right: Save dropdown -->
                <div class="flex items-center gap-2">
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button
                                :disabled="!hasActiveSession || isSaving || serverNotLive"
                                size="sm"
                            >
                                <Loader2
                                    v-if="isSaving"
                                    class="size-4 animate-spin"
                                />
                                <Save v-else class="size-4" />
                                {{ saveButtonLabel }}
                                <ChevronDown class="size-3.5" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem @click="handleSaveAndClose">
                                Save & Close
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="handleSaveAndRunClick">
                                Save & Run
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>
        </header>

        <!-- Active run warning banner -->
        <div
            v-if="isRefineMode && props.hasActiveRun"
            class="flex items-center gap-2 border-b border-warning/20 bg-warning/5 px-4 py-2 text-sm text-warning"
        >
            <AlertTriangle class="size-4 shrink-0" />
            <span>This PRD is currently in use by an active run. Changes will apply to future runs.</span>
        </div>

        <!-- Session expired banner -->
        <div
            v-if="sessionExpired"
            class="flex items-center justify-between gap-2 border-b border-destructive/20 bg-destructive/5 px-4 py-3"
        >
            <div class="flex items-center gap-2 text-sm text-destructive">
                <Clock class="size-4 shrink-0" />
                <span>Session expired. Your progress has been saved.</span>
            </div>
            <Button
                size="sm"
                :disabled="serverNotLive || isResuming"
                @click="handleResume"
            >
                <Loader2
                    v-if="isResuming"
                    class="size-4 animate-spin"
                />
                <RefreshCw v-else class="size-4" />
                Resume
            </Button>
        </div>

        <!-- Main content area -->
        <div
            ref="containerRef"
            class="relative flex min-h-0 flex-1"
        >
            <!-- Chat panel -->
            <div
                class="flex flex-col"
                :class="{
                    'hidden lg:flex': mobileView === 'preview',
                    'flex': mobileView === 'chat',
                    'w-full lg:w-auto': true,
                }"
                :style="{ flexBasis: `${dividerPosition}%`, flexShrink: 0, flexGrow: 0 }"
            >
                <!-- Chat messages area -->
                <div
                    ref="messagesContainer"
                    class="flex-1 overflow-y-auto"
                    @scroll="handleScroll"
                >
                    <div class="mx-auto max-w-3xl space-y-4 p-4">
                        <!-- Empty state -->
                        <div
                            v-if="messages.length === 0"
                            class="flex flex-col items-center justify-center py-24 text-center"
                        >
                            <div class="mb-4 rounded-full bg-primary/10 p-4">
                                <Send class="size-8 text-primary" />
                            </div>
                            <h2 class="text-lg font-semibold">{{ isRefineMode ? 'Refine your PRD' : 'Create a new PRD' }}</h2>
                            <p class="mt-2 max-w-sm text-sm text-muted-foreground">
                                <template v-if="isRefineMode">
                                    Describe the changes you want to make. Claude will see the full PRD and make targeted updates.
                                </template>
                                <template v-else>
                                    Describe what you want to build. Claude will help you create a detailed Product Requirements Document.
                                </template>
                            </p>
                        </div>

                        <!-- Chat messages -->
                        <TransitionGroup
                            enter-active-class="transition-all duration-[var(--duration-standard)] ease-[var(--ease-gentle)]"
                            enter-from-class="opacity-0 translate-y-2"
                            enter-to-class="opacity-100 translate-y-0"
                        >
                            <div
                                v-for="msg in messages"
                                :key="msg.id"
                                class="flex"
                                :class="msg.role === 'user' ? 'justify-end' : 'justify-start'"
                            >
                                <!-- User message -->
                                <div
                                    v-if="msg.role === 'user'"
                                    class="max-w-[85%] rounded-2xl rounded-br-md bg-primary/10 px-4 py-2.5 text-sm text-foreground lg:max-w-[70%]"
                                >
                                    <p class="whitespace-pre-wrap break-words">{{ msg.content }}</p>
                                </div>

                                <!-- Claude message -->
                                <div
                                    v-else
                                    class="max-w-[85%] rounded-2xl rounded-bl-md bg-surface px-4 py-2.5 text-sm text-foreground lg:max-w-[70%]"
                                    :class="{ 'border border-border': true }"
                                >
                                    <!-- Streaming content -->
                                    <div
                                        v-if="msg.content"
                                        class="prose-chat break-words"
                                        v-html="renderedMarkdown(msg.content)"
                                    />

                                    <!-- Typing indicator (empty streaming message) -->
                                    <div
                                        v-if="msg.isStreaming && msg.content === ''"
                                        class="flex items-center gap-1.5 py-1"
                                    >
                                        <span class="inline-block size-2 animate-pulse rounded-full bg-muted-foreground" style="animation-delay: 0ms" />
                                        <span class="inline-block size-2 animate-pulse rounded-full bg-muted-foreground" style="animation-delay: 200ms" />
                                        <span class="inline-block size-2 animate-pulse rounded-full bg-muted-foreground" style="animation-delay: 400ms" />
                                    </div>

                                    <!-- Streaming cursor -->
                                    <span
                                        v-if="msg.isStreaming && msg.content !== ''"
                                        class="inline-block h-4 w-0.5 animate-pulse bg-primary align-text-bottom"
                                    />
                                </div>
                            </div>
                        </TransitionGroup>
                    </div>
                </div>

                <!-- New messages pill -->
                <Transition
                    enter-active-class="transition-all duration-[var(--duration-standard)] ease-[var(--ease-snappy)]"
                    enter-from-class="opacity-0 translate-y-2"
                    enter-to-class="opacity-100 translate-y-0"
                    leave-active-class="transition-all duration-[var(--duration-micro)] ease-[var(--ease-snappy)]"
                    leave-from-class="opacity-100 translate-y-0"
                    leave-to-class="opacity-0 translate-y-2"
                >
                    <button
                        v-if="isAutoScrollPaused && messages.length > 0"
                        class="focus-ring absolute bottom-24 left-1/2 z-10 -translate-x-1/2 rounded-full bg-primary px-4 py-1.5 text-xs font-medium text-primary-foreground shadow-lg transition-colors hover:bg-primary/90 lg:left-auto lg:translate-x-0"
                        :style="{ left: `${dividerPosition / 2}%` }"
                        @click="jumpToBottom"
                    >
                        <ArrowDown class="mr-1 inline-block size-3" />
                        New messages
                    </button>
                </Transition>

                <!-- Input area (pinned to bottom) -->
                <div class="border-t border-border bg-background">
                    <div class="mx-auto flex max-w-3xl items-end gap-2 p-4">
                        <div class="relative flex-1">
                            <textarea
                                ref="textareaRef"
                                v-model="userInput"
                                :disabled="isSaving || serverNotLive || sessionExpired"
                                class="focus-ring w-full resize-none rounded-xl border border-border bg-surface px-4 py-3 pr-12 text-sm text-foreground placeholder-muted-foreground transition-colors duration-[var(--duration-micro)] focus:border-primary disabled:cursor-not-allowed disabled:opacity-50 lg:pr-4"
                                :class="{ 'opacity-50': isClaudeResponding }"
                                :placeholder="sessionExpired ? 'Session expired — click Resume to continue' : (isRefineMode ? 'Describe the changes you want to make...' : 'Describe what you want to build...')"
                                rows="1"
                                style="overflow-y: hidden"
                                @keydown="handleKeydown"
                            />

                            <!-- Mobile send button (inside textarea) -->
                            <button
                                class="focus-ring absolute bottom-2 right-2 flex items-center justify-center rounded-lg bg-primary p-2 text-primary-foreground transition-colors hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-50 lg:hidden"
                                :disabled="!userInput.trim() || isClaudeResponding || isSaving || serverNotLive || sessionExpired"
                                aria-label="Send message"
                                @click="handleSend"
                            >
                                <Send class="size-4" />
                            </button>
                        </div>

                        <!-- Desktop send button -->
                        <Button
                            class="hidden lg:flex"
                            :disabled="!userInput.trim() || isClaudeResponding || isSaving || serverNotLive || sessionExpired"
                            @click="handleSend"
                        >
                            <Send class="size-4" />
                            Send
                        </Button>
                    </div>

                    <!-- Helper text -->
                    <div class="mx-auto max-w-3xl px-4 pb-3">
                        <p class="text-[10px] text-muted-foreground">
                            <span class="hidden lg:inline">Press Enter to send, Shift+Enter for new line.</span>
                            <span v-if="serverNotLive" class="text-destructive"> Server offline — messages cannot be sent.</span>
                            <span v-else-if="sessionExpired" class="text-destructive"> Session expired — click Resume to continue.</span>
                            <span v-else-if="isClaudeResponding" class="text-primary"> Claude is thinking...</span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Resizable divider (desktop only) -->
            <div
                class="hidden lg:flex group relative z-10 w-0 cursor-col-resize items-center justify-center"
                @mousedown="startDrag"
            >
                <div
                    class="h-full w-px bg-border transition-colors duration-[var(--duration-micro)] group-hover:bg-primary/50"
                    :class="{ 'bg-primary': isDragging }"
                />
                <div
                    class="absolute flex h-8 w-4 items-center justify-center rounded-full border border-border bg-background opacity-0 transition-opacity duration-[var(--duration-standard)] group-hover:opacity-100"
                    :class="{ 'opacity-100': isDragging }"
                >
                    <div class="flex gap-px">
                        <div class="h-3 w-px rounded-full bg-muted-foreground" />
                        <div class="h-3 w-px rounded-full bg-muted-foreground" />
                    </div>
                </div>
            </div>

            <!-- Preview panel -->
            <div
                class="flex-1 border-l border-border bg-background"
                :class="{
                    'hidden lg:block': mobileView === 'chat',
                    'block': mobileView === 'preview',
                }"
            >
                <PrdPreviewPanel
                    :content="prdContent"
                    :is-generating="isClaudeResponding"
                />
            </div>
        </div>

        <!-- Active run confirmation dialog -->
        <ConfirmDialog
            v-model:open="showRunConfirm"
            title="A run is already in progress"
            description="Stop the current run and start a new one with this PRD?"
            confirm-label="Stop & Start New Run"
            cancel-label="Cancel"
            variant="destructive"
            @confirm="executeSaveAndRun"
            @cancel="showRunConfirm = false"
        />
    </div>
</template>

<style scoped>
/* Surface color for Claude messages */
.bg-surface {
    background-color: var(--surface, oklch(0.21 0.006 285));
}

/* Prose-like styling for Claude message markdown */
.prose-chat :deep(h1),
.prose-chat :deep(h2),
.prose-chat :deep(h3) {
    color: var(--foreground);
}

.prose-chat :deep(code) {
    font-family: var(--font-mono, 'Geist Mono', monospace);
}

.prose-chat :deep(pre) {
    overflow-x: auto;
}

.prose-chat :deep(li) {
    margin-top: 0.125rem;
    margin-bottom: 0.125rem;
}

.prose-chat :deep(strong) {
    font-weight: 600;
}

/* Typing dots stagger animation */
.animate-pulse {
    animation: pulse 1.5s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

/* Prevent text selection while dragging the divider */
.cursor-col-resize {
    user-select: none;
}
</style>
