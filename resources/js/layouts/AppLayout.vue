<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppHeader from '@/components/AppHeader.vue';
</script>

<template>
    <div class="flex min-h-screen w-full flex-col">
        <AppHeader />
        <AppContent>
            <slot />
        </AppContent>
    </div>
</template>
