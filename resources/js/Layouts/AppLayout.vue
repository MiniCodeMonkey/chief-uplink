<script setup>
import { Link, usePage, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const page = usePage();
const currentUrl = computed(() => page.url);
const auth = computed(() => page.props.auth);
const currentTeam = computed(() => auth.value?.currentTeam);
const teams = computed(() => auth.value?.teams ?? []);
const hasMultipleTeams = computed(() => teams.value.length > 1);

const showTeamSwitcher = ref(false);

function toggleTeamSwitcher() {
    showTeamSwitcher.value = !showTeamSwitcher.value;
}

function closeTeamSwitcher() {
    showTeamSwitcher.value = false;
}

function switchTeam(teamId) {
    if (teamId === currentTeam.value?.id) {
        closeTeamSwitcher();
        return;
    }
    router.put('/team/switch', { team_id: teamId }, {
        onSuccess: () => closeTeamSwitcher(),
    });
}

const navItems = [
    { label: 'Home', href: '/', icon: 'home' },
    { label: 'Devices', href: '/devices', icon: 'devices' },
    { label: 'Servers', href: '/servers', icon: 'servers' },
    { label: 'Settings', href: '/settings/team', icon: 'settings' },
];

function isActive(href) {
    if (href === '/') {
        return currentUrl.value === '/';
    }
    return currentUrl.value.startsWith(href);
}
</script>

<template>
    <div class="flex min-h-screen bg-bg">
        <!-- Desktop Sidebar -->
        <aside class="hidden md:flex md:w-56 md:flex-col md:fixed md:inset-y-0 border-r border-border bg-bg-card">
            <div class="flex h-14 items-center px-4">
                <span class="text-lg font-bold text-text-heading">Chief Uplink</span>
            </div>

            <!-- Team Switcher (Desktop) -->
            <div v-if="currentTeam" class="px-2 mb-2 relative">
                <button
                    @click="toggleTeamSwitcher"
                    class="flex w-full items-center justify-between rounded-md px-3 py-2 text-sm transition-colors hover:bg-bg-surface/50"
                    :class="showTeamSwitcher ? 'bg-bg-surface text-text-heading' : 'text-text-secondary'"
                >
                    <span class="truncate font-medium text-text-heading">{{ currentTeam.name }}</span>
                    <svg v-if="hasMultipleTeams" class="h-4 w-4 shrink-0 text-text-secondary transition-transform" :class="showTeamSwitcher ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m6 9 6 6 6-6"/>
                    </svg>
                </button>

                <!-- Dropdown -->
                <div
                    v-if="showTeamSwitcher && hasMultipleTeams"
                    class="absolute left-2 right-2 z-50 mt-1 rounded-md border border-border bg-bg-card shadow-lg"
                >
                    <div class="py-1">
                        <button
                            v-for="team in teams"
                            :key="team.id"
                            @click="switchTeam(team.id)"
                            class="flex w-full items-center gap-2 px-3 py-2 text-sm transition-colors hover:bg-bg-surface/50"
                            :class="team.id === currentTeam.id ? 'text-interactive font-medium' : 'text-text-secondary'"
                        >
                            <svg v-if="team.id === currentTeam.id" class="h-3 w-3 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            <span v-else class="w-3 shrink-0"></span>
                            <span class="truncate">{{ team.name }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <nav class="flex-1 px-2 py-2 space-y-1">
                <Link
                    v-for="item in navItems"
                    :key="item.href"
                    :href="item.href"
                    class="flex items-center gap-3 rounded-md px-3 py-2 text-sm transition-colors"
                    :class="isActive(item.href)
                        ? 'bg-bg-surface text-text-heading font-medium'
                        : 'text-text-secondary hover:bg-bg-surface/50 hover:text-text'"
                >
                    <!-- Home icon -->
                    <svg v-if="item.icon === 'home'" class="h-4 w-4 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8" />
                        <path d="M3 10a2 2 0 0 1 .709-1.528l7-5.999a2 2 0 0 1 2.582 0l7 5.999A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
                    </svg>
                    <!-- Devices icon -->
                    <svg v-if="item.icon === 'devices'" class="h-4 w-4 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect width="14" height="8" x="5" y="2" rx="2" />
                        <rect width="20" height="8" x="2" y="14" rx="2" />
                        <path d="M6 18h2" /><path d="M12 18h6" />
                    </svg>
                    <!-- Servers icon -->
                    <svg v-if="item.icon === 'servers'" class="h-4 w-4 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect width="20" height="8" x="2" y="2" rx="2" ry="2" />
                        <rect width="20" height="8" x="2" y="14" rx="2" ry="2" />
                        <line x1="6" x2="6.01" y1="6" y2="6" />
                        <line x1="6" x2="6.01" y1="18" y2="18" />
                    </svg>
                    <!-- Settings icon -->
                    <svg v-if="item.icon === 'settings'" class="h-4 w-4 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z" />
                        <circle cx="12" cy="12" r="3" />
                    </svg>
                    {{ item.label }}
                </Link>
            </nav>
        </aside>

        <!-- Main content -->
        <main class="flex-1 md:pl-56">
            <div class="min-h-screen pb-16 md:pb-0">
                <slot />
            </div>
        </main>

        <!-- Mobile Bottom Tab Bar -->
        <nav class="fixed inset-x-0 bottom-0 z-50 flex md:hidden border-t border-border bg-bg-card">
            <!-- Team switcher button (mobile) -->
            <button
                v-if="hasMultipleTeams"
                @click="toggleTeamSwitcher"
                class="flex flex-1 flex-col items-center gap-1 py-2 text-xs transition-colors text-text-secondary"
            >
                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 21a8 8 0 0 0-16 0" />
                    <circle cx="10" cy="8" r="5" />
                    <path d="M22 20c0-3.37-2.69-6.29-6.44-7.4" />
                    <path d="M16 3.13a4 4 0 0 1 0 9.74" />
                </svg>
                Teams
            </button>

            <Link
                v-for="item in navItems"
                :key="item.href"
                :href="item.href"
                class="flex flex-1 flex-col items-center gap-1 py-2 text-xs transition-colors"
                :class="isActive(item.href)
                    ? 'bg-bg-surface text-text-heading font-medium'
                    : 'text-text-secondary'"
            >
                <!-- Home icon -->
                <svg v-if="item.icon === 'home'" class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8" />
                    <path d="M3 10a2 2 0 0 1 .709-1.528l7-5.999a2 2 0 0 1 2.582 0l7 5.999A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
                </svg>
                <!-- Devices icon -->
                <svg v-if="item.icon === 'devices'" class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect width="14" height="8" x="5" y="2" rx="2" />
                    <rect width="20" height="8" x="2" y="14" rx="2" />
                    <path d="M6 18h2" /><path d="M12 18h6" />
                </svg>
                <!-- Servers icon -->
                <svg v-if="item.icon === 'servers'" class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect width="20" height="8" x="2" y="2" rx="2" ry="2" />
                    <rect width="20" height="8" x="2" y="14" rx="2" ry="2" />
                    <line x1="6" x2="6.01" y1="6" y2="6" />
                    <line x1="6" x2="6.01" y1="18" y2="18" />
                </svg>
                <!-- Settings icon -->
                <svg v-if="item.icon === 'settings'" class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z" />
                    <circle cx="12" cy="12" r="3" />
                </svg>
                {{ item.label }}
            </Link>
        </nav>

        <!-- Mobile Team Switcher Modal -->
        <div v-if="showTeamSwitcher && hasMultipleTeams" class="fixed inset-0 z-[60] md:hidden" @click.self="closeTeamSwitcher">
            <div class="absolute inset-0 bg-black/50" @click="closeTeamSwitcher"></div>
            <div class="absolute inset-x-4 bottom-20 rounded-lg border border-border bg-bg-card shadow-lg">
                <div class="px-4 py-3 border-b border-border">
                    <span class="text-sm font-medium text-text-heading">Switch Team</span>
                </div>
                <div class="py-1">
                    <button
                        v-for="team in teams"
                        :key="team.id"
                        @click="switchTeam(team.id)"
                        class="flex w-full items-center gap-3 px-4 py-3 text-sm transition-colors hover:bg-bg-surface/50"
                        :class="team.id === currentTeam.id ? 'text-interactive font-medium' : 'text-text-secondary'"
                    >
                        <svg v-if="team.id === currentTeam.id" class="h-4 w-4 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                        <span v-else class="w-4 shrink-0"></span>
                        <span class="truncate">{{ team.name }}</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
