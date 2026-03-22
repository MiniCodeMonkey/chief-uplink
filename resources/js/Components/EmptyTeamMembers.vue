<script setup>
import { useForm } from '@inertiajs/vue3';
import EmptyState from './EmptyState.vue';

const form = useForm({
    email: '',
});

function submitInvite() {
    form.post('/settings/team/invite', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
        },
    });
}
</script>

<template>
    <EmptyState
        title="Invite your team"
        description="Add team members by email to collaborate on projects and servers."
    >
        <template #icon>
            <svg class="h-12 w-12" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                <circle cx="9" cy="7" r="4" />
                <line x1="19" y1="8" x2="19" y2="14" />
                <line x1="22" y1="11" x2="16" y2="11" />
            </svg>
        </template>

        <form @submit.prevent="submitInvite" class="flex items-start gap-2 mx-auto max-w-md">
            <div class="flex-1">
                <input
                    v-model="form.email"
                    type="email"
                    placeholder="colleague@company.com"
                    class="block w-full rounded-md border border-border bg-bg-card px-3 py-2 text-sm text-text placeholder-text-muted focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand"
                />
                <p v-if="form.errors.email" class="mt-1 text-left text-xs text-error">{{ form.errors.email }}</p>
            </div>
            <button
                type="submit"
                :disabled="form.processing"
                class="shrink-0 rounded-md bg-interactive px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-interactive-hover disabled:opacity-50"
            >
                Invite
            </button>
        </form>
    </EmptyState>
</template>
