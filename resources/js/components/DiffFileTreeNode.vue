<script setup lang="ts">
import {
    ChevronRight,
    FileText,
    Folder,
} from 'lucide-vue-next';
import { ref } from 'vue';

interface TreeNode {
    name: string;
    path: string;
    type: 'file' | 'directory';
    additions: number;
    deletions: number;
    children?: TreeNode[];
}

const props = withDefaults(
    defineProps<{
        node: TreeNode;
        depth?: number;
        selectedFile?: string | null;
    }>(),
    {
        depth: 0,
        selectedFile: null,
    },
);

const emit = defineEmits<{
    (e: 'select', filename: string): void;
}>();

const expanded = ref(true);

function toggleExpand() {
    expanded.value = !expanded.value;
}

const paddingLeft = `${props.depth * 12 + 8}px`;
</script>

<template>
    <!-- Directory node -->
    <div v-if="node.type === 'directory'">
        <button
            class="flex w-full items-center gap-1.5 px-2 py-1 text-left text-sm text-muted-foreground transition-colors duration-[var(--duration-micro)] hover:bg-accent/50"
            :style="{ paddingLeft }"
            :aria-expanded="expanded"
            :aria-label="`Toggle ${node.name} directory`"
            @click="toggleExpand"
        >
            <ChevronRight
                class="size-3 shrink-0 transition-transform duration-[var(--duration-standard)]"
                :class="expanded ? 'rotate-90' : ''"
            />
            <Folder class="size-3.5 shrink-0 text-primary/70" />
            <span class="truncate">{{ node.name }}</span>
        </button>
        <div v-if="expanded && node.children">
            <DiffFileTreeNode
                v-for="child in node.children"
                :key="child.path"
                :node="child"
                :depth="depth + 1"
                :selected-file="selectedFile"
                @select="(f: string) => emit('select', f)"
            />
        </div>
    </div>

    <!-- File node -->
    <button
        v-else
        class="flex w-full items-center gap-1.5 px-2 py-1 text-left text-sm transition-colors duration-[var(--duration-micro)]"
        :class="
            selectedFile === node.path
                ? 'bg-primary/10 text-foreground font-medium'
                : 'text-muted-foreground hover:bg-accent/50 hover:text-foreground'
        "
        :style="{ paddingLeft }"
        @click="emit('select', node.path)"
    >
        <FileText class="size-3.5 shrink-0" />
        <span class="min-w-0 flex-1 truncate">{{ node.name }}</span>
        <span class="flex shrink-0 items-center gap-1 font-mono text-xs">
            <span v-if="node.additions > 0" class="text-success">+{{ node.additions }}</span>
            <span v-if="node.deletions > 0" class="text-destructive">-{{ node.deletions }}</span>
        </span>
    </button>
</template>
