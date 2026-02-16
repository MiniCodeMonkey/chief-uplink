<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { github } from '@/routes/auth';

defineProps<{
    status?: string;
}>();

const isLoading = ref(false);
const signInButton = ref<HTMLElement | null>(null);

function signInWithGitHub() {
    isLoading.value = true;
    router.visit(github.url());
}

onMounted(() => {
    signInButton.value?.focus();
});
</script>

<template>
    <Head title="Sign in" />

    <div class="login-page relative flex min-h-svh flex-col items-center justify-center overflow-hidden bg-background p-6 md:p-10">
        <!-- Ambient gradient animation -->
        <div class="ambient-bg pointer-events-none absolute inset-0" aria-hidden="true" />

        <div class="relative z-10 w-full max-w-sm">
            <div class="flex flex-col items-center gap-8">
                <!-- Logo and tagline -->
                <div class="flex flex-col items-center gap-4">
                    <div class="mb-1 flex h-12 w-12 items-center justify-center">
                        <AppLogoIcon class="size-12 fill-current text-foreground" />
                    </div>
                    <div class="space-y-2 text-center">
                        <h1 class="text-2xl font-semibold tracking-tight text-foreground">
                            Welcome to Chief
                        </h1>
                        <p class="text-sm text-muted-foreground">
                            Monitor runs, review progress, and manage projects from anywhere.
                        </p>
                    </div>
                </div>

                <!-- Status message -->
                <div
                    v-if="status"
                    class="w-full rounded-lg border border-border bg-card px-4 py-3 text-center text-sm text-muted-foreground"
                >
                    {{ status }}
                </div>

                <!-- Sign in card -->
                <div class="w-full rounded-lg border border-border bg-card p-6">
                    <Button
                        ref="signInButton"
                        class="w-full gap-3"
                        size="lg"
                        :disabled="isLoading"
                        data-test="github-login-button"
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
                </div>

                <Link
                    href="/docs"
                    class="text-sm text-muted-foreground underline underline-offset-2 hover:text-foreground"
                >
                    Documentation
                </Link>
            </div>
        </div>
    </div>
</template>

<style scoped>
.ambient-bg {
    background:
        radial-gradient(
            ellipse 80% 50% at 50% 120%,
            oklch(0.646 0.174 53.03 / 0.08),
            transparent
        );
    animation: ambient-shift 8s ease-in-out infinite alternate;
}

:is(.dark) .ambient-bg {
    background:
        radial-gradient(
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

@media (prefers-reduced-motion: reduce) {
    .ambient-bg {
        animation: none;
        opacity: 0.8;
    }
}
</style>
