<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';

defineOptions({ layout: false });

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post('/register', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <Head title="Create Account" />

    <div class="flex min-h-screen items-center justify-center bg-bg px-4">
        <div class="w-full max-w-sm">
            <div class="mb-8 text-center">
                <h1 class="text-2xl font-bold text-text-heading">Create your account</h1>
                <p class="mt-2 text-sm text-text-secondary">Get started with Chief Uplink</p>
            </div>

            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <label for="name" class="block text-sm font-medium text-text">Name</label>
                    <input
                        id="name"
                        v-model="form.name"
                        type="text"
                        autocomplete="name"
                        required
                        autofocus
                        class="mt-1 block w-full rounded-md border border-border bg-bg-card px-3 py-2 text-sm text-text placeholder-text-muted focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand"
                    />
                    <p v-if="form.errors.name" class="mt-1 text-sm text-error">{{ form.errors.name }}</p>
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-text">Email</label>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        autocomplete="email"
                        required
                        class="mt-1 block w-full rounded-md border border-border bg-bg-card px-3 py-2 text-sm text-text placeholder-text-muted focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand"
                    />
                    <p v-if="form.errors.email" class="mt-1 text-sm text-error">{{ form.errors.email }}</p>
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-text">Password</label>
                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        autocomplete="new-password"
                        required
                        class="mt-1 block w-full rounded-md border border-border bg-bg-card px-3 py-2 text-sm text-text placeholder-text-muted focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand"
                    />
                    <p v-if="form.errors.password" class="mt-1 text-sm text-error">{{ form.errors.password }}</p>
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-text">Confirm Password</label>
                    <input
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        type="password"
                        autocomplete="new-password"
                        required
                        class="mt-1 block w-full rounded-md border border-border bg-bg-card px-3 py-2 text-sm text-text placeholder-text-muted focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand"
                    />
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full rounded-md bg-interactive px-4 py-2 text-sm font-medium text-bg transition-colors hover:opacity-90 disabled:opacity-50"
                >
                    Create account
                </button>
            </form>

            <p class="mt-6 text-center text-sm text-text-secondary">
                Already have an account?
                <Link href="/login" class="font-medium text-brand hover:underline">Sign in</Link>
            </p>
        </div>
    </div>
</template>
