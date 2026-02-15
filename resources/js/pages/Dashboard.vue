<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout>
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                <div
                    class="relative aspect-video overflow-hidden rounded-xl border border-border"
                />
                <div
                    class="relative aspect-video overflow-hidden rounded-xl border border-border"
                />
                <div
                    class="relative aspect-video overflow-hidden rounded-xl border border-border"
                />
            </div>
            <div
                class="relative min-h-[50vh] flex-1 rounded-xl border border-border md:min-h-min"
            />
        </div>
    </AppLayout>
</template>
