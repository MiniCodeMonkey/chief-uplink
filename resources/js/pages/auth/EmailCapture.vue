<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

const emailInput = ref<HTMLInputElement | null>(null);

const form = useForm({
    email: '',
});

function submit() {
    form.post(route('email-capture.store'));
}

function skip() {
    form.post(route('email-capture.skip'));
}

onMounted(() => {
    emailInput.value?.focus();
});
</script>

<template>
    <Head title="Add your email" />

    <div class="relative flex min-h-svh flex-col items-center justify-center overflow-hidden bg-background p-6 md:p-10">
        <div class="relative z-10 w-full max-w-sm">
            <div class="flex flex-col items-center gap-8">
                <!-- Logo and heading -->
                <div class="flex flex-col items-center gap-4">
                    <div class="mb-1 flex h-12 w-12 items-center justify-center">
                        <AppLogoIcon class="size-12 fill-current text-foreground" />
                    </div>
                    <div class="space-y-2 text-center">
                        <h1 class="text-2xl font-semibold tracking-tight text-foreground">
                            Add your email
                        </h1>
                        <p class="text-sm text-muted-foreground">
                            We need your email for notifications &mdash; we'll never spam you.
                        </p>
                    </div>
                </div>

                <!-- Email form -->
                <div class="w-full rounded-lg border border-border bg-card p-6">
                    <form class="space-y-4" @submit.prevent="submit">
                        <div class="grid gap-2">
                            <Label for="email">Email address</Label>
                            <Input
                                id="email"
                                ref="emailInput"
                                v-model="form.email"
                                type="email"
                                placeholder="you@example.com"
                                autocomplete="email"
                                required
                                data-test="email-capture-input"
                            />
                            <InputError :message="form.errors.email" />
                        </div>

                        <Button
                            type="submit"
                            class="w-full"
                            size="lg"
                            :disabled="form.processing"
                            data-test="email-capture-submit"
                        >
                            <Spinner v-if="form.processing" />
                            {{ form.processing ? 'Saving...' : 'Continue' }}
                        </Button>
                    </form>

                    <div class="mt-4 text-center">
                        <button
                            type="button"
                            class="text-sm text-muted-foreground underline-offset-4 transition-colors hover:text-foreground hover:underline"
                            :disabled="form.processing"
                            data-test="email-capture-skip"
                            @click="skip"
                        >
                            Skip for now
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
