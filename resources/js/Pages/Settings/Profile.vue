<script setup>
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import { ref, watch, onMounted } from 'vue';

const props = defineProps({
    user: Object,
});

const page = usePage();

const profileForm = useForm({
    name: props.user.name,
    email: props.user.email,
});

const themeForm = useForm({
    theme_preference: props.user.theme_preference,
});

const themeOptions = [
    { value: 'dark', label: 'Dark' },
    { value: 'light', label: 'Light' },
    { value: 'system', label: 'System' },
];

function submitProfile() {
    profileForm.put('/settings/profile');
}

function applyTheme(theme) {
    localStorage.setItem('theme_preference', theme);

    if (theme === 'system') {
        document.documentElement.removeAttribute('data-theme');
    } else {
        document.documentElement.setAttribute('data-theme', theme);
    }
}

function submitTheme() {
    themeForm.put('/settings/theme', {
        onSuccess: () => {
            applyTheme(themeForm.theme_preference);
        },
    });
}

onMounted(() => {
    const stored = props.user.theme_preference || 'system';
    localStorage.setItem('theme_preference', stored);
});

watch(() => themeForm.theme_preference, (newTheme) => {
    applyTheme(newTheme);
});
</script>

<template>
    <Head title="Profile Settings" />

    <div class="p-6 md:p-8 max-w-2xl">
        <h1 class="text-2xl font-bold text-text-heading">Profile Settings</h1>
        <p class="mt-1 text-sm text-text-secondary">Manage your profile and theme preference.</p>

        <!-- Flash messages -->
        <div v-if="page.props.flash?.success" class="mt-4 rounded-md border border-success/30 bg-success/10 px-4 py-3 text-sm text-success">
            {{ page.props.flash.success }}
        </div>

        <!-- Profile Section -->
        <section class="mt-8">
            <h2 class="text-lg font-semibold text-text-heading">Profile Information</h2>
            <p class="mt-1 text-sm text-text-secondary">Update your name and email address.</p>

            <form @submit.prevent="submitProfile" class="mt-4 space-y-4">
                <div>
                    <label for="name" class="block text-sm font-medium text-text">Name</label>
                    <input
                        id="name"
                        v-model="profileForm.name"
                        type="text"
                        class="mt-1 block w-full rounded-md border border-border bg-bg-card px-3 py-2 text-sm text-text placeholder-text-muted focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand"
                    />
                    <p v-if="profileForm.errors.name" class="mt-1 text-sm text-error">{{ profileForm.errors.name }}</p>
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-text">Email</label>
                    <input
                        id="email"
                        v-model="profileForm.email"
                        type="email"
                        class="mt-1 block w-full rounded-md border border-border bg-bg-card px-3 py-2 text-sm text-text placeholder-text-muted focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand"
                    />
                    <p v-if="profileForm.errors.email" class="mt-1 text-sm text-error">{{ profileForm.errors.email }}</p>
                </div>

                <button
                    type="submit"
                    :disabled="profileForm.processing"
                    class="rounded-md bg-interactive px-4 py-2 text-sm font-medium text-bg transition-colors hover:opacity-90 disabled:opacity-50"
                >
                    Save Profile
                </button>
            </form>
        </section>

        <!-- Theme Section -->
        <section class="mt-8">
            <h2 class="text-lg font-semibold text-text-heading">Theme Preference</h2>
            <p class="mt-1 text-sm text-text-secondary">Choose how the app looks to you.</p>

            <form @submit.prevent="submitTheme" class="mt-4">
                <div class="flex gap-3">
                    <label
                        v-for="option in themeOptions"
                        :key="option.value"
                        class="flex cursor-pointer items-center gap-2 rounded-md border px-4 py-3 text-sm transition-colors"
                        :class="themeForm.theme_preference === option.value
                            ? 'border-brand bg-brand/10 text-text-heading font-medium'
                            : 'border-border text-text-secondary hover:border-border hover:bg-bg-surface/50'"
                    >
                        <input
                            type="radio"
                            :value="option.value"
                            v-model="themeForm.theme_preference"
                            class="sr-only"
                        />
                        {{ option.label }}
                    </label>
                </div>
                <p v-if="themeForm.errors.theme_preference" class="mt-1 text-sm text-error">{{ themeForm.errors.theme_preference }}</p>

                <button
                    type="submit"
                    :disabled="themeForm.processing"
                    class="mt-4 rounded-md bg-interactive px-4 py-2 text-sm font-medium text-bg transition-colors hover:opacity-90 disabled:opacity-50"
                >
                    Save Theme
                </button>
            </form>
        </section>
    </div>
</template>
