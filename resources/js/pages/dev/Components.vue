<script setup lang="ts">
import {
    BoxIcon,
    CheckCircleIcon,
    CopyIcon,
    FolderOpenIcon,
    InfoIcon,
    Loader2Icon,
    PlusIcon,
    SettingsIcon,
    Trash2Icon,
} from 'lucide-vue-next';
import { ref } from 'vue';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { CopyButton } from '@/components/ui/copy-button';
import { EmptyState } from '@/components/ui/empty-state';
import { Input } from '@/components/ui/input';
import { ProgressBar } from '@/components/ui/progress-bar';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { StatusDot } from '@/components/ui/status-dot';
import { ToastContainer } from '@/components/ui/toast';
import { Toggle } from '@/components/ui/toggle';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useAppearance } from '@/composables/useAppearance';
import { useToast } from '@/composables/useToast';

const { appearance, updateAppearance } = useAppearance();
const { success, error, warning, info } = useToast();

const toggleValue = ref(false);
const selectValue = ref('');
const inputValue = ref('');
const progressValue = ref(65);
const confirmOpen = ref(false);
const dangerConfirmOpen = ref(false);
</script>

<template>
    <ToastContainer />
    <div class="min-h-screen bg-background text-foreground">
        <div class="mx-auto max-w-5xl space-y-12 px-4 py-8 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="space-y-2">
                <h1 class="text-3xl font-bold tracking-tight">
                    Component Playground
                </h1>
                <p class="text-lg text-muted-foreground">
                    Chief Design System — all components in all states.
                </p>
                <div class="flex items-center gap-2">
                    <span class="text-sm text-muted-foreground">Theme:</span>
                    <Button
                        :variant="
                            appearance === 'light' ? 'default' : 'outline'
                        "
                        size="sm"
                        @click="updateAppearance('light')"
                    >
                        Light
                    </Button>
                    <Button
                        :variant="appearance === 'dark' ? 'default' : 'outline'"
                        size="sm"
                        @click="updateAppearance('dark')"
                    >
                        Dark
                    </Button>
                    <Button
                        :variant="
                            appearance === 'system' ? 'default' : 'outline'
                        "
                        size="sm"
                        @click="updateAppearance('system')"
                    >
                        System
                    </Button>
                </div>
            </div>

            <!-- Buttons -->
            <section class="space-y-4">
                <h2 class="text-xl font-semibold">Buttons</h2>
                <Card>
                    <CardHeader>
                        <CardTitle>Button Variants</CardTitle>
                        <CardDescription>
                            All button variants with press-scale
                            micro-interaction.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="flex flex-wrap gap-3">
                        <Button>Primary</Button>
                        <Button variant="secondary">Secondary</Button>
                        <Button variant="destructive">Danger</Button>
                        <Button variant="ghost">Ghost</Button>
                        <Button variant="outline">Outline</Button>
                        <Button variant="link">Link</Button>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Button Sizes & States</CardTitle>
                    </CardHeader>
                    <CardContent class="flex flex-wrap items-center gap-3">
                        <Button size="sm">Small</Button>
                        <Button>Default</Button>
                        <Button size="lg">Large</Button>
                        <Button size="icon"><PlusIcon /></Button>
                        <Button disabled>Disabled</Button>
                        <Button disabled>
                            <Spinner class="size-4" />
                            Loading
                        </Button>
                    </CardContent>
                </Card>
            </section>

            <!-- Input -->
            <section class="space-y-4">
                <h2 class="text-xl font-semibold">Input</h2>
                <Card>
                    <CardHeader>
                        <CardTitle>Text Input</CardTitle>
                    </CardHeader>
                    <CardContent class="flex max-w-md flex-col gap-3">
                        <Input
                            v-model="inputValue"
                            placeholder="Default input"
                        />
                        <Input
                            placeholder="Disabled input"
                            disabled
                            model-value="Disabled"
                        />
                        <Input
                            placeholder="Invalid input"
                            aria-invalid="true"
                        />
                    </CardContent>
                </Card>
            </section>

            <!-- Select -->
            <section class="space-y-4">
                <h2 class="text-xl font-semibold">Select</h2>
                <Card>
                    <CardHeader>
                        <CardTitle>Select Dropdown</CardTitle>
                    </CardHeader>
                    <CardContent class="max-w-md">
                        <Select v-model="selectValue">
                            <SelectTrigger>
                                <SelectValue placeholder="Select a framework" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="vue">Vue</SelectItem>
                                <SelectItem value="react">React</SelectItem>
                                <SelectItem value="angular">Angular</SelectItem>
                                <SelectItem value="svelte">Svelte</SelectItem>
                            </SelectContent>
                        </Select>
                    </CardContent>
                </Card>
            </section>

            <!-- Toggle -->
            <section class="space-y-4">
                <h2 class="text-xl font-semibold">Toggle</h2>
                <Card>
                    <CardHeader>
                        <CardTitle>Toggle Switch</CardTitle>
                    </CardHeader>
                    <CardContent class="flex items-center gap-4">
                        <Toggle
                            v-model="toggleValue"
                            aria-label="Toggle example"
                        />
                        <span class="text-sm">{{
                            toggleValue ? 'On' : 'Off'
                        }}</span>
                        <Toggle
                            :model-value="true"
                            disabled
                            aria-label="Disabled on"
                        />
                        <Toggle
                            :model-value="false"
                            disabled
                            aria-label="Disabled off"
                        />
                    </CardContent>
                </Card>
            </section>

            <!-- Badge -->
            <section class="space-y-4">
                <h2 class="text-xl font-semibold">Badge</h2>
                <Card>
                    <CardHeader>
                        <CardTitle>Badge Variants</CardTitle>
                    </CardHeader>
                    <CardContent class="flex flex-wrap gap-2">
                        <Badge>Default</Badge>
                        <Badge variant="secondary">Secondary</Badge>
                        <Badge variant="destructive">Destructive</Badge>
                        <Badge variant="outline">Outline</Badge>
                    </CardContent>
                </Card>
            </section>

            <!-- Status Dot -->
            <section class="space-y-4">
                <h2 class="text-xl font-semibold">Status Dot</h2>
                <Card>
                    <CardHeader>
                        <CardTitle>Connection States</CardTitle>
                        <CardDescription>
                            Green (online), Yellow pulsing (reconnecting), Gray
                            (offline), Hollow (never connected).
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="flex flex-wrap items-center gap-6">
                        <div class="flex items-center gap-2">
                            <StatusDot state="online" />
                            <span class="text-sm">Online</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <StatusDot state="reconnecting" />
                            <span class="text-sm">Reconnecting</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <StatusDot state="offline" />
                            <span class="text-sm">Offline</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <StatusDot state="never-connected" />
                            <span class="text-sm">Never Connected</span>
                        </div>
                    </CardContent>
                </Card>
            </section>

            <!-- Progress Bar -->
            <section class="space-y-4">
                <h2 class="text-xl font-semibold">Progress Bar</h2>
                <Card>
                    <CardHeader>
                        <CardTitle>Progress Indicators</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-2">
                            <div class="flex justify-between text-sm">
                                <span>{{ progressValue }}%</span>
                                <input
                                    v-model.number="progressValue"
                                    type="range"
                                    min="0"
                                    max="100"
                                    class="w-32"
                                />
                            </div>
                            <ProgressBar :value="progressValue" />
                        </div>
                        <ProgressBar :value="0" />
                        <ProgressBar :value="100" />
                    </CardContent>
                </Card>
            </section>

            <!-- Skeleton -->
            <section class="space-y-4">
                <h2 class="text-xl font-semibold">Skeleton</h2>
                <Card>
                    <CardHeader>
                        <CardTitle>Loading Skeletons</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="flex items-center gap-4">
                            <Skeleton class="size-12 rounded-full" />
                            <div class="space-y-2">
                                <Skeleton class="h-4 w-48" />
                                <Skeleton class="h-3 w-32" />
                            </div>
                        </div>
                        <Skeleton class="h-24 w-full" />
                    </CardContent>
                </Card>
            </section>

            <!-- Spinner -->
            <section class="space-y-4">
                <h2 class="text-xl font-semibold">Spinner</h2>
                <Card>
                    <CardHeader>
                        <CardTitle>Loading Spinners</CardTitle>
                    </CardHeader>
                    <CardContent class="flex items-center gap-4">
                        <Spinner class="size-4" />
                        <Spinner class="size-6" />
                        <Spinner class="size-8" />
                        <Spinner class="size-8 text-primary" />
                    </CardContent>
                </Card>
            </section>

            <!-- Toast -->
            <section class="space-y-4">
                <h2 class="text-xl font-semibold">Toast</h2>
                <Card>
                    <CardHeader>
                        <CardTitle>Toast Notifications</CardTitle>
                        <CardDescription>
                            Click to trigger toasts. Success/info/warning
                            auto-dismiss; errors persist.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="flex flex-wrap gap-3">
                        <Button
                            variant="outline"
                            @click="
                                success(
                                    'Settings saved',
                                    'Your changes have been applied.',
                                )
                            "
                        >
                            <CheckCircleIcon class="size-4" />
                            Success
                        </Button>
                        <Button
                            variant="outline"
                            @click="
                                error(
                                    'Connection failed',
                                    'Unable to reach the server.',
                                )
                            "
                        >
                            <Trash2Icon class="size-4" />
                            Error
                        </Button>
                        <Button
                            variant="outline"
                            @click="
                                warning(
                                    'Server busy',
                                    'Retrying in 5 seconds...',
                                )
                            "
                        >
                            <Loader2Icon class="size-4" />
                            Warning
                        </Button>
                        <Button
                            variant="outline"
                            @click="
                                info(
                                    'New version available',
                                    'Update your CLI to v0.6.0',
                                )
                            "
                        >
                            <InfoIcon class="size-4" />
                            Info
                        </Button>
                    </CardContent>
                </Card>
            </section>

            <!-- Copy Button -->
            <section class="space-y-4">
                <h2 class="text-xl font-semibold">Copy Button</h2>
                <Card>
                    <CardHeader>
                        <CardTitle>Click to Copy</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div
                            class="flex items-center justify-between rounded-md bg-muted px-3 py-2"
                        >
                            <code class="font-mono text-sm">
                                ssh chief@192.168.1.100
                            </code>
                            <CopyButton value="ssh chief@192.168.1.100" />
                        </div>
                        <div
                            class="flex items-center justify-between rounded-md bg-muted px-3 py-2"
                        >
                            <code class="font-mono text-sm">ABCD-1234</code>
                            <CopyButton value="ABCD-1234" label="Copy Code" />
                        </div>
                    </CardContent>
                </Card>
            </section>

            <!-- Tooltip -->
            <section class="space-y-4">
                <h2 class="text-xl font-semibold">Tooltip</h2>
                <Card>
                    <CardHeader>
                        <CardTitle>Tooltips</CardTitle>
                    </CardHeader>
                    <CardContent class="flex gap-4">
                        <TooltipProvider>
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <Button variant="outline" size="icon">
                                        <SettingsIcon />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p>Settings</p>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                        <TooltipProvider>
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <Button variant="outline" size="icon">
                                        <CopyIcon />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p>Copy to clipboard</p>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                    </CardContent>
                </Card>
            </section>

            <!-- Confirm Dialog -->
            <section class="space-y-4">
                <h2 class="text-xl font-semibold">Confirm Dialog</h2>
                <Card>
                    <CardHeader>
                        <CardTitle>Confirmation Dialogs</CardTitle>
                    </CardHeader>
                    <CardContent class="flex flex-wrap gap-3">
                        <Button variant="outline" @click="confirmOpen = true">
                            Simple Confirm
                        </Button>
                        <Button
                            variant="destructive"
                            @click="dangerConfirmOpen = true"
                        >
                            Danger Confirm
                        </Button>
                    </CardContent>
                </Card>
                <ConfirmDialog
                    v-model:open="confirmOpen"
                    title="Confirm action"
                    description="Are you sure you want to proceed with this action?"
                    @confirm="confirmOpen = false"
                    @cancel="confirmOpen = false"
                />
                <ConfirmDialog
                    v-model:open="dangerConfirmOpen"
                    title="Delete Account"
                    description="This will permanently delete your account and all associated data."
                    variant="destructive"
                    confirm-label="Delete"
                    confirm-text="DELETE"
                    @confirm="dangerConfirmOpen = false"
                    @cancel="dangerConfirmOpen = false"
                />
            </section>

            <!-- Empty State -->
            <section class="space-y-4">
                <h2 class="text-xl font-semibold">Empty State</h2>
                <Card>
                    <CardContent class="pt-6">
                        <EmptyState
                            :icon="FolderOpenIcon"
                            title="No projects yet"
                            description="Clone a repository or create a new project to get started."
                        >
                            <template #action>
                                <Button>
                                    <PlusIcon class="size-4" />
                                    New Project
                                </Button>
                            </template>
                        </EmptyState>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="pt-6">
                        <EmptyState
                            :icon="BoxIcon"
                            title="No cloud servers"
                            description="Deploy one to run chief without managing your own VPS."
                        />
                    </CardContent>
                </Card>
            </section>

            <!-- Color Palette -->
            <section class="space-y-4">
                <h2 class="text-xl font-semibold">Color Palette</h2>
                <Card>
                    <CardHeader>
                        <CardTitle>Semantic Colors</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            <div class="space-y-1.5">
                                <div class="h-12 rounded-md bg-primary" />
                                <p class="text-xs">Primary (Amber)</p>
                            </div>
                            <div class="space-y-1.5">
                                <div class="h-12 rounded-md bg-secondary" />
                                <p class="text-xs">Secondary</p>
                            </div>
                            <div class="space-y-1.5">
                                <div class="h-12 rounded-md bg-destructive" />
                                <p class="text-xs">Destructive</p>
                            </div>
                            <div class="space-y-1.5">
                                <div class="h-12 rounded-md bg-accent" />
                                <p class="text-xs">Accent</p>
                            </div>
                            <div class="space-y-1.5">
                                <div class="h-12 rounded-md bg-success" />
                                <p class="text-xs">Success</p>
                            </div>
                            <div class="space-y-1.5">
                                <div class="h-12 rounded-md bg-warning" />
                                <p class="text-xs">Warning</p>
                            </div>
                            <div class="space-y-1.5">
                                <div class="h-12 rounded-md bg-info" />
                                <p class="text-xs">Info</p>
                            </div>
                            <div class="space-y-1.5">
                                <div class="h-12 rounded-md bg-muted" />
                                <p class="text-xs">Muted</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Surface & Background</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            <div class="space-y-1.5">
                                <div
                                    class="h-12 rounded-md border bg-background"
                                />
                                <p class="text-xs">Background</p>
                            </div>
                            <div class="space-y-1.5">
                                <div class="h-12 rounded-md border bg-card" />
                                <p class="text-xs">Card</p>
                            </div>
                            <div class="space-y-1.5">
                                <div
                                    class="h-12 rounded-md border bg-popover"
                                />
                                <p class="text-xs">Popover</p>
                            </div>
                            <div class="space-y-1.5">
                                <div class="h-12 rounded-md bg-border" />
                                <p class="text-xs">Border</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </section>

            <!-- Typography -->
            <section class="space-y-4">
                <h2 class="text-xl font-semibold">Typography</h2>
                <Card>
                    <CardHeader>
                        <CardTitle>Font Family</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div>
                            <p class="mb-1 text-xs text-muted-foreground">
                                Geist (sans-serif)
                            </p>
                            <p class="text-2xl font-bold">
                                The quick brown fox jumps over the lazy dog
                            </p>
                            <p class="text-base">
                                ABCDEFGHIJKLMNOPQRSTUVWXYZ
                                abcdefghijklmnopqrstuvwxyz 0123456789
                            </p>
                        </div>
                        <div>
                            <p class="mb-1 text-xs text-muted-foreground">
                                Geist Mono (monospace)
                            </p>
                            <p class="font-mono text-2xl font-bold">
                                The quick brown fox jumps over the lazy dog
                            </p>
                            <p class="font-mono text-base">
                                ABCDEFGHIJKLMNOPQRSTUVWXYZ
                                abcdefghijklmnopqrstuvwxyz 0123456789
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </section>

            <!-- Touch Targets -->
            <section class="space-y-4">
                <h2 class="text-xl font-semibold">Touch Targets</h2>
                <Card>
                    <CardHeader>
                        <CardTitle>44x44px Minimum</CardTitle>
                        <CardDescription>
                            All interactive elements meet the 44x44px touch
                            target minimum.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="flex flex-wrap items-center gap-4">
                        <Button size="icon" variant="outline">
                            <PlusIcon />
                        </Button>
                        <Toggle
                            v-model="toggleValue"
                            aria-label="Touch target toggle"
                        />
                        <CopyButton value="test" />
                    </CardContent>
                </Card>
            </section>
        </div>
    </div>
</template>
