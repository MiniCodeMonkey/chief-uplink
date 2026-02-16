<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const page = usePage();
const user = page.props.auth.user;
const open = ref(false);

const form = useForm({
    username: '',
});

function submit() {
    const action = ProfileController.destroy.form();
    form.delete(action.action, {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
        onError: () => {
            // Keep dialog open so user can see the error
        },
    });
}

function handleCancel() {
    form.clearErrors();
    form.reset();
    open.value = false;
}
</script>

<template>
    <div class="space-y-6">
        <Heading
            variant="small"
            title="Delete account"
            description="Permanently delete your account and all associated data"
        />
        <div
            class="space-y-4 rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10"
        >
            <div class="relative space-y-0.5 text-red-600 dark:text-red-100">
                <p class="font-medium">Warning</p>
                <p class="text-sm">
                    This will permanently delete your account, deauthorize all devices, and destroy
                    any cloud servers. This action cannot be undone.
                </p>
            </div>
            <Dialog v-model:open="open">
                <DialogTrigger as-child>
                    <Button variant="destructive" data-test="delete-user-button"
                        >Delete account</Button
                    >
                </DialogTrigger>
                <DialogContent>
                    <form @submit.prevent="submit" class="space-y-6">
                        <DialogHeader class="space-y-3">
                            <DialogTitle>Are you sure you want to delete your account?</DialogTitle>
                            <DialogDescription>
                                This will permanently delete your account, deauthorize all devices,
                                and destroy any cloud servers. Type your username to confirm.
                            </DialogDescription>
                        </DialogHeader>

                        <div class="grid gap-2">
                            <Label for="delete-username">
                                Type
                                <strong class="text-foreground">{{ user.github_username }}</strong>
                                to confirm
                            </Label>
                            <Input
                                id="delete-username"
                                v-model="form.username"
                                type="text"
                                :placeholder="user.github_username"
                                :aria-invalid="!!form.errors.username"
                                data-test="delete-username-input"
                            />
                            <InputError :message="form.errors.username" />
                        </div>

                        <DialogFooter class="gap-2">
                            <DialogClose as-child>
                                <Button variant="secondary" @click="handleCancel"> Cancel </Button>
                            </DialogClose>

                            <Button
                                type="submit"
                                variant="destructive"
                                :disabled="
                                    form.processing || form.username !== user.github_username
                                "
                                data-test="confirm-delete-user-button"
                            >
                                Delete account
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    </div>
</template>
