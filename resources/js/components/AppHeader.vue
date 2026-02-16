<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { computed } from 'vue';
import BreadcrumbPicker from '@/components/BreadcrumbPicker.vue';
import ConnectionStatusIndicator from '@/components/ConnectionStatusIndicator.vue';
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
    showBack?: boolean;
}>();

const showShortcuts = defineModel<boolean>('showShortcuts', { default: false });

const page = usePage();
const auth = computed(() => page.props.auth);
</script>

<template>
    <header class="border-b border-border">
        <div class="flex h-14 items-center justify-between px-4">
            <!-- Left: Mobile back arrow (when showBack) + Breadcrumb picker -->
            <div class="flex items-center gap-1">
                <Link
                    v-if="showBack"
                    href="/dashboard"
                    class="focus-ring -ml-1 mr-1 flex items-center justify-center rounded-md p-1.5 text-muted-foreground transition-colors duration-[var(--duration-micro)] hover:bg-accent hover:text-foreground lg:hidden"
                    aria-label="Back to dashboard"
                    style="min-width: 44px; min-height: 44px"
                >
                    <ArrowLeft class="size-5" />
                </Link>
                <BreadcrumbPicker
                    :current-project-slug="currentProjectSlug"
                />
            </div>

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
</template>
