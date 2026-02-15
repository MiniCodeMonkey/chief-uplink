<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import BreadcrumbPicker from '@/components/BreadcrumbPicker.vue';
import ConnectionStatusIndicator from '@/components/ConnectionStatusIndicator.vue';
import KeyboardShortcutsOverlay from '@/components/KeyboardShortcutsOverlay.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { getInitials } from '@/composables/useInitials';

defineProps<{
    currentProjectSlug?: string;
}>();

const page = usePage();
const auth = computed(() => page.props.auth);

const showShortcuts = ref(false);
</script>

<template>
    <header class="border-b border-border">
        <div class="flex h-14 items-center justify-between px-4">
            <!-- Left: Breadcrumb picker -->
            <BreadcrumbPicker :current-project-slug="currentProjectSlug" />

            <!-- Right: Connection status + User avatar dropdown -->
            <div class="flex items-center gap-2">
                <ConnectionStatusIndicator />
                <DropdownMenu>
                    <DropdownMenuTrigger :as-child="true">
                        <Button
                            variant="ghost"
                            size="icon"
                            class="relative size-9 rounded-full p-0.5 focus-within:ring-2 focus-within:ring-primary"
                            aria-label="User menu"
                        >
                            <Avatar class="size-8 overflow-hidden rounded-full">
                                <AvatarImage
                                    v-if="auth.user.avatar"
                                    :src="auth.user.avatar"
                                    :alt="auth.user.name"
                                />
                                <AvatarFallback
                                    class="rounded-full bg-muted font-semibold text-foreground"
                                >
                                    {{ getInitials(auth.user?.name) }}
                                </AvatarFallback>
                            </Avatar>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-56">
                        <UserMenuContent
                            :user="auth.user"
                            @show-shortcuts="showShortcuts = true"
                        />
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>
    </header>

    <KeyboardShortcutsOverlay v-model:open="showShortcuts" />
</template>
