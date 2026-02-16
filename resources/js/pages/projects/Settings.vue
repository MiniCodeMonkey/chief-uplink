<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ProjectLayout from '@/layouts/ProjectLayout.vue';

const props = defineProps<{
    projectSlug: string;
    projectName: string;
}>();
</script>

<template>
    <Head :title="`${props.projectName} — Settings`" />

    <ProjectLayout :project-slug="props.projectSlug">
        <div class="flex flex-1 flex-col gap-4 p-4">
            <div class="rounded-lg border border-border p-6">
                <p class="text-sm text-muted-foreground">
                    Project settings will be implemented in a future story.
                </p>
            </div>
        </div>
    </ProjectLayout>
</template>
