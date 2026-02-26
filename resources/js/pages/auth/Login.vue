<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    Activity,
    Cloud,
    MessageSquareText,
    ToggleRight,
} from 'lucide-vue-next';
import { ref, onMounted, type ComponentPublicInstance } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { github } from '@/routes/auth';

defineProps<{
    status?: string;
}>();

const isLoading = ref(false);
const signInButton = ref<ComponentPublicInstance | null>(null);

function signInWithGitHub() {
    isLoading.value = true;
    window.location.href = github.url();
}

onMounted(() => {
    signInButton.value?.$el?.focus();
});

const features = [
    {
        icon: Activity,
        title: 'Live monitoring',
        description:
            'See what Chief is doing in real time from your phone or browser.',
    },
    {
        icon: ToggleRight,
        title: 'Remote control',
        description: 'Start, pause, or stop runs without opening a terminal.',
    },
    {
        icon: MessageSquareText,
        title: 'PRD chat',
        description: 'Write and refine PRDs in a conversation with Claude.',
    },
    {
        icon: Cloud,
        title: 'Cloud deploy',
        description:
            'Spin up a Chief server on Hetzner or DigitalOcean in one click.',
    },
];
</script>

<template>
    <Head title="Sign in" />

    <div class="login-page relative min-h-svh overflow-hidden bg-background">
        <!-- Ambient gradient animation -->
        <div
            class="ambient-bg pointer-events-none fixed inset-0"
            aria-hidden="true"
        />

        <!-- Top Nav (sticky) -->
        <nav
            class="sticky top-0 z-20 border-b border-border/50 bg-background/80 backdrop-blur-sm"
        >
            <div
                class="mx-auto flex max-w-3xl items-center justify-between px-6 py-3"
            >
                <div class="flex items-center gap-2.5">
                    <AppLogoIcon class="size-7" />
                    <span
                        class="text-sm font-semibold tracking-tight text-foreground"
                        >Chief Uplink</span
                    >
                </div>
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="isLoading"
                    data-test="github-login-button"
                    @click="signInWithGitHub"
                >
                    <Spinner v-if="isLoading" />
                    <svg
                        v-else
                        class="size-4"
                        viewBox="0 0 24 24"
                        fill="currentColor"
                        aria-hidden="true"
                    >
                        <path
                            fill-rule="evenodd"
                            clip-rule="evenodd"
                            d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z"
                        />
                    </svg>
                    {{ isLoading ? 'Redirecting...' : 'Sign in with GitHub' }}
                </Button>
            </div>
        </nav>

        <!-- Status message -->
        <div v-if="status" class="relative z-10 mx-auto max-w-3xl px-6 pt-4">
            <div
                class="rounded-lg border border-border bg-card px-4 py-3 text-center text-sm text-muted-foreground"
            >
                {{ status }}
            </div>
        </div>

        <!-- Hero -->
        <div
            class="relative z-10 mx-auto max-w-3xl px-6 pt-24 pb-12 text-center sm:pt-32"
        >
            <h1
                class="text-3xl font-bold tracking-tight text-foreground sm:text-4xl"
            >
                Chief, from anywhere.
            </h1>
            <p
                class="mx-auto mt-4 max-w-lg text-sm leading-relaxed text-muted-foreground sm:text-base"
            >
                Uplink is a free add-on that gives you a remote dashboard for
                Chief &mdash; check on runs, manage PRDs, and control sessions
                from any device.
            </p>
            <div class="mt-8 flex flex-col items-center gap-4">
                <Button
                    ref="signInButton"
                    size="lg"
                    class="gap-3"
                    :disabled="isLoading"
                    @click="signInWithGitHub"
                >
                    <Spinner v-if="isLoading" />
                    <svg
                        v-else
                        class="size-5"
                        viewBox="0 0 24 24"
                        fill="currentColor"
                        aria-hidden="true"
                    >
                        <path
                            fill-rule="evenodd"
                            clip-rule="evenodd"
                            d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z"
                        />
                    </svg>
                    {{ isLoading ? 'Redirecting...' : 'Sign in with GitHub' }}
                </Button>
                <a
                    href="https://chiefloop.com"
                    target="_blank"
                    rel="noopener"
                    class="text-sm text-muted-foreground underline underline-offset-2 hover:text-foreground"
                >
                    Learn more about Chief
                </a>
            </div>
        </div>

        <!-- Feature Grid -->
        <div class="relative z-10 mx-auto max-w-3xl px-6 pb-16">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div
                    v-for="(feature, index) in features"
                    :key="feature.title"
                    class="feature-card rounded-lg border border-border bg-card/50 p-5 backdrop-blur-sm transition-colors hover:border-border/80 hover:bg-card/70"
                    :style="{ animationDelay: `${index * 80}ms` }"
                >
                    <div
                        class="mb-3 flex size-9 items-center justify-center rounded-lg bg-primary/10"
                    >
                        <component
                            :is="feature.icon"
                            class="size-4.5 text-primary"
                        />
                    </div>
                    <h3 class="text-sm font-semibold text-foreground">
                        {{ feature.title }}
                    </h3>
                    <p
                        class="mt-1 text-sm leading-relaxed text-muted-foreground"
                    >
                        {{ feature.description }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="relative z-10 mx-auto max-w-3xl px-6 pb-12 text-center">
            <p class="text-sm text-muted-foreground">
                Free and open source.
                <a
                    href="https://chiefloop.com/docs"
                    target="_blank"
                    rel="noopener"
                    class="underline underline-offset-2 hover:text-foreground"
                >
                    Read the docs
                </a>
            </p>
        </div>
    </div>
</template>

<style scoped>
.ambient-bg {
    background: radial-gradient(
        ellipse 80% 50% at 50% 120%,
        oklch(0.646 0.174 53.03 / 0.08),
        transparent
    );
    animation: ambient-shift 8s ease-in-out infinite alternate;
}

:is(.dark) .ambient-bg {
    background: radial-gradient(
        ellipse 80% 50% at 50% 120%,
        oklch(0.759 0.157 62.79 / 0.06),
        transparent
    );
}

@keyframes ambient-shift {
    0% {
        opacity: 0.6;
        transform: scale(1) translateY(0);
    }
    100% {
        opacity: 1;
        transform: scale(1.05) translateY(-2%);
    }
}

.feature-card {
    animation: fade-in-up 0.4s ease-out both;
}

@keyframes fade-in-up {
    from {
        opacity: 0;
        transform: translateY(8px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (prefers-reduced-motion: reduce) {
    .ambient-bg {
        animation: none;
        opacity: 0.8;
    }

    .feature-card {
        animation: none;
    }
}
</style>
