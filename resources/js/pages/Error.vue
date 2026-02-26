<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    status: number;
    message?: string;
}>();

const title = computed(() => {
    switch (props.status) {
        case 404:
            return 'Page not found';
        case 403:
            return 'Forbidden';
        case 500:
            return 'Something went wrong';
        case 503:
            return 'Service unavailable';
        default:
            return 'Error';
    }
});

const description = computed(() => {
    switch (props.status) {
        case 404:
            return "The page you're looking for doesn't exist or has been moved.";
        case 403:
            return "You don't have permission to access this page.";
        case 500:
            return "We're working on it. Please try again in a moment.";
        case 503:
            return 'The service is temporarily unavailable. Please try again shortly.';
        default:
            return props.message || 'An unexpected error occurred.';
    }
});

function tryAgain() {
    window.location.reload();
}
</script>

<template>
    <Head :title="title" />

    <div class="flex min-h-svh flex-col items-center justify-center bg-background p-6">
        <div class="w-full max-w-sm">
            <div class="flex flex-col items-center gap-8">
                <div class="flex flex-col items-center gap-4">
                    <Link href="/" class="mb-1 flex h-12 w-12 items-center justify-center">
                        <AppLogoIcon class="size-12" />
                    </Link>
                    <div class="space-y-2 text-center">
                        <p class="text-6xl font-bold tracking-tight text-muted-foreground/50">
                            {{ status }}
                        </p>
                        <h1 class="text-2xl font-semibold tracking-tight text-foreground">
                            {{ title }}
                        </h1>
                        <p class="text-sm text-muted-foreground">
                            {{ description }}
                        </p>
                    </div>
                </div>

                <div class="flex w-full flex-col gap-3">
                    <Button
                        v-if="status >= 500"
                        class="w-full"
                        size="lg"
                        @click="tryAgain"
                    >
                        Try again
                    </Button>
                    <Button
                        :variant="status >= 500 ? 'outline' : 'default'"
                        class="w-full"
                        size="lg"
                        as-child
                    >
                        <Link href="/">
                            Go to dashboard
                        </Link>
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
