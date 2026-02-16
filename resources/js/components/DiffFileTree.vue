<script setup lang="ts">
import { computed } from 'vue';
import DiffFileTreeNode from '@/components/DiffFileTreeNode.vue';

interface DiffFile {
    filename: string;
    additions: number;
    deletions: number;
    patch: string;
}

interface TreeNode {
    name: string;
    path: string;
    type: 'file' | 'directory';
    file?: DiffFile;
    children?: TreeNode[];
    additions: number;
    deletions: number;
}

const props = defineProps<{
    files: DiffFile[];
    selectedFile: string | null;
}>();

const emit = defineEmits<{
    (e: 'select', filename: string): void;
}>();

// Build a tree structure from flat file paths
function buildTree(files: DiffFile[]): TreeNode[] {
    interface BuildNode {
        name: string;
        path: string;
        file?: DiffFile;
        children: Map<string, BuildNode>;
        additions: number;
        deletions: number;
    }

    const rootChildren = new Map<string, BuildNode>();

    for (const file of files) {
        const parts = file.filename.split('/');
        let currentMap = rootChildren;

        for (let i = 0; i < parts.length; i++) {
            const part = parts[i];
            const isFile = i === parts.length - 1;
            const path = parts.slice(0, i + 1).join('/');

            if (!currentMap.has(part)) {
                currentMap.set(part, {
                    name: part,
                    path,
                    file: isFile ? file : undefined,
                    children: new Map(),
                    additions: 0,
                    deletions: 0,
                });
            }

            const node = currentMap.get(part)!;

            if (isFile) {
                node.file = file;
                node.additions = file.additions;
                node.deletions = file.deletions;
            } else {
                node.additions += file.additions;
                node.deletions += file.deletions;
                currentMap = node.children;
            }
        }
    }

    function toTreeNodes(map: Map<string, BuildNode>): TreeNode[] {
        const result: TreeNode[] = [];
        for (const node of map.values()) {
            const isFile = node.file !== undefined;
            const treeNode: TreeNode = {
                name: node.name,
                path: node.path,
                type: isFile ? 'file' : 'directory',
                file: node.file,
                additions: node.additions,
                deletions: node.deletions,
                ...(isFile ? {} : { children: toTreeNodes(node.children) }),
            };
            result.push(treeNode);
        }
        return result.sort((a, b) => {
            if (a.type !== b.type) return a.type === 'directory' ? -1 : 1;
            return a.name.localeCompare(b.name);
        });
    }

    return toTreeNodes(rootChildren);
}

// Flatten single-child directory chains
function collapseTree(nodes: TreeNode[]): TreeNode[] {
    return nodes.map((node) => {
        if (
            node.type === 'directory' &&
            node.children &&
            node.children.length === 1 &&
            node.children[0].type === 'directory'
        ) {
            const child = node.children[0];
            const merged: TreeNode = {
                ...child,
                name: `${node.name}/${child.name}`,
                path: child.path,
                children: child.children ? collapseTree(child.children) : undefined,
            };
            return merged;
        }
        if (node.type === 'directory' && node.children) {
            return { ...node, children: collapseTree(node.children) };
        }
        return node;
    });
}

const collapsedTree = computed(() => collapseTree(buildTree(props.files)));
</script>

<template>
    <div class="flex flex-col overflow-y-auto text-sm">
        <div class="px-3 py-2 text-xs font-medium uppercase tracking-wider text-muted-foreground">
            Changed Files
        </div>
        <div class="flex flex-col">
            <DiffFileTreeNode
                v-for="node in collapsedTree"
                :key="node.path"
                :node="node"
                :depth="0"
                :selected-file="selectedFile"
                @select="(f: string) => emit('select', f)"
            />
        </div>
    </div>
</template>
