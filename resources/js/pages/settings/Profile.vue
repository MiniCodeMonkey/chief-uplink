<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/DeleteUser.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';

type Props = {
    status?: string;
};

defineProps<Props>();

const page = usePage();
const user = page.props.auth.user;

const memberSince = new Date(user.created_at).toLocaleDateString(undefined, {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
});
</script>

<template>
    <AppLayout>
        <Head title="Account settings" />

        <h1 class="sr-only">Account Settings</h1>

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <Heading
                    variant="small"
                    title="Account"
                    description="Your GitHub account information"
                />

                <div class="flex items-center gap-4">
                    <img
                        v-if="user.avatar_url"
                        :src="user.avatar_url"
                        :alt="`${user.github_username}'s avatar`"
                        class="h-16 w-16 rounded-full border border-border"
                    />
                    <div
                        v-else
                        class="bg-muted text-muted-foreground flex h-16 w-16 items-center justify-center rounded-full border border-border text-xl font-medium"
                    >
                        {{ (user.github_username || user.name || '?').charAt(0).toUpperCase() }}
                    </div>
                    <div>
                        <p class="font-medium text-foreground">
                            {{ user.github_username }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            Member since {{ memberSince }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="flex flex-col space-y-6">
                <Heading
                    variant="small"
                    title="Profile information"
                    description="Update your name and email address"
                />

                <Form
                    v-bind="ProfileController.update.form()"
                    class="space-y-6"
                    v-slot="{ errors, processing, recentlySuccessful }"
                >
                    <div class="grid gap-2">
                        <Label for="name">Name</Label>
                        <Input
                            id="name"
                            class="mt-1 block w-full"
                            name="name"
                            :default-value="user.name"
                            required
                            autocomplete="name"
                            placeholder="Full name"
                            :aria-invalid="!!errors.name"
                        />
                        <InputError :message="errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="email">Email address</Label>
                        <Input
                            id="email"
                            type="email"
                            class="mt-1 block w-full"
                            name="email"
                            :default-value="user.email ?? ''"
                            autocomplete="username"
                            placeholder="Email address"
                            :aria-invalid="!!errors.email"
                        />
                        <p class="text-xs text-muted-foreground">
                            Used for notifications. Leave empty for push-only notifications.
                        </p>
                        <InputError :message="errors.email" />
                    </div>

                    <div class="flex items-center gap-4">
                        <Button
                            :disabled="processing"
                            data-test="update-profile-button"
                            >Save</Button
                        >

                        <Transition
                            enter-active-class="transition ease-in-out"
                            enter-from-class="opacity-0"
                            leave-active-class="transition ease-in-out"
                            leave-to-class="opacity-0"
                        >
                            <p
                                v-show="recentlySuccessful"
                                class="text-sm text-neutral-600"
                            >
                                Saved.
                            </p>
                        </Transition>
                    </div>
                </Form>
            </div>

            <DeleteUser />
        </SettingsLayout>
    </AppLayout>
</template>
