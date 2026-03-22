<script setup>
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import EmptyTeamMembers from '../../Components/EmptyTeamMembers.vue';

const props = defineProps({
    team: Object,
    members: Array,
    isOwner: Boolean,
});

const page = usePage();
const authUser = page.props.auth?.user;

const nameForm = useForm({
    name: props.team.name,
});

const editingName = ref(false);

function submitName() {
    nameForm.put('/settings/team/name', {
        onSuccess: () => {
            editingName.value = false;
        },
    });
}

function cancelEditName() {
    nameForm.name = props.team.name;
    nameForm.clearErrors();
    editingName.value = false;
}

const hasOtherMembers = computed(() => props.members.length > 1);

const confirmingRemove = ref(null);

function removeMember(userId) {
    router.delete('/settings/team/members', {
        data: { user_id: userId },
        onSuccess: () => {
            confirmingRemove.value = null;
        },
    });
}

const confirmingTransfer = ref(null);

function transferOwnership(userId) {
    router.put('/settings/team/transfer', {
        user_id: userId,
    }, {
        onSuccess: () => {
            confirmingTransfer.value = null;
        },
    });
}
</script>

<template>
    <Head title="Team Settings" />

    <div class="p-6 md:p-8 max-w-2xl">
        <h1 class="text-2xl font-bold text-text-heading">Team Settings</h1>
        <p class="mt-1 text-sm text-text-secondary">Manage your team name and members.</p>

        <!-- Flash messages -->
        <div v-if="page.props.flash?.success" class="mt-4 rounded-md border border-success/30 bg-success/10 px-4 py-3 text-sm text-success">
            {{ page.props.flash.success }}
        </div>

        <!-- Team Name Section -->
        <section class="mt-8">
            <h2 class="text-lg font-semibold text-text-heading">Team Name</h2>

            <div v-if="!editingName" class="mt-3 flex items-center gap-3">
                <span class="text-text">{{ team.name }}</span>
                <button
                    v-if="isOwner"
                    @click="editingName = true"
                    class="text-sm font-medium text-brand hover:underline"
                >
                    Edit
                </button>
            </div>

            <form v-else @submit.prevent="submitName" class="mt-3 flex items-start gap-3">
                <div class="flex-1">
                    <input
                        v-model="nameForm.name"
                        type="text"
                        autofocus
                        class="block w-full rounded-md border border-border bg-bg-card px-3 py-2 text-sm text-text placeholder-text-muted focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand"
                    />
                    <p v-if="nameForm.errors.name" class="mt-1 text-sm text-error">{{ nameForm.errors.name }}</p>
                </div>
                <button
                    type="submit"
                    :disabled="nameForm.processing"
                    class="rounded-md bg-interactive px-3 py-2 text-sm font-medium text-bg transition-colors hover:opacity-90 disabled:opacity-50"
                >
                    Save
                </button>
                <button
                    type="button"
                    @click="cancelEditName"
                    class="rounded-md border border-border px-3 py-2 text-sm font-medium text-text-secondary transition-colors hover:bg-bg-surface"
                >
                    Cancel
                </button>
            </form>
        </section>

        <!-- Members Section -->
        <section class="mt-8">
            <h2 class="text-lg font-semibold text-text-heading">Members</h2>

            <EmptyTeamMembers v-if="isOwner && !hasOtherMembers" class="mt-3" />

            <div v-else class="mt-3 divide-y divide-border rounded-md border border-border">
                <div
                    v-for="member in members"
                    :key="member.id"
                    class="flex items-center justify-between px-4 py-3"
                >
                    <div class="flex items-center gap-3">
                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-bg-surface text-sm font-medium text-text-heading">
                            {{ member.name?.charAt(0)?.toUpperCase() || '?' }}
                        </div>
                        <div>
                            <div class="text-sm font-medium text-text-heading">
                                {{ member.name }}
                                <span v-if="member.id === authUser?.id" class="text-text-muted">(you)</span>
                            </div>
                            <div class="text-xs text-text-secondary">{{ member.email }}</div>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <span class="rounded-sm bg-bg-surface px-2 py-0.5 text-xs font-medium text-text-secondary capitalize">
                            {{ member.role }}
                        </span>

                        <!-- Owner actions for non-self members -->
                        <template v-if="isOwner && member.id !== authUser?.id">
                            <!-- Transfer ownership -->
                            <div v-if="confirmingTransfer === member.id" class="flex items-center gap-2">
                                <span class="text-xs text-text-secondary">Transfer?</span>
                                <button
                                    @click="transferOwnership(member.id)"
                                    class="text-xs font-medium text-warning hover:underline"
                                >
                                    Confirm
                                </button>
                                <button
                                    @click="confirmingTransfer = null"
                                    class="text-xs text-text-muted hover:underline"
                                >
                                    Cancel
                                </button>
                            </div>
                            <button
                                v-else
                                @click="confirmingTransfer = member.id"
                                class="text-xs font-medium text-text-secondary hover:text-text hover:underline"
                            >
                                Make owner
                            </button>

                            <!-- Remove member -->
                            <div v-if="confirmingRemove === member.id" class="flex items-center gap-2">
                                <span class="text-xs text-text-secondary">Remove?</span>
                                <button
                                    @click="removeMember(member.id)"
                                    class="text-xs font-medium text-error hover:underline"
                                >
                                    Confirm
                                </button>
                                <button
                                    @click="confirmingRemove = null"
                                    class="text-xs text-text-muted hover:underline"
                                >
                                    Cancel
                                </button>
                            </div>
                            <button
                                v-else
                                @click="confirmingRemove = member.id"
                                class="text-xs font-medium text-error/80 hover:text-error hover:underline"
                            >
                                Remove
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
