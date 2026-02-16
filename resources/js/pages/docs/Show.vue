<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    Book,
    ChevronRight,
    Cloud,
    FileCode,
    GitCompare,
    Menu,
    Monitor,
    Play,
    Search,
    Server,
    Settings2,
    X,
} from 'lucide-vue-next';
import MarkdownIt from 'markdown-it';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type DocSection = {
    slug: string;
    title: string;
};

const props = defineProps<{
    slug: string;
    content: string;
    sections: DocSection[];
}>();

const sectionIcons: Record<string, typeof Book> = {
    'getting-started': Book,
    prds: FileCode,
    runs: Play,
    diffs: GitCompare,
    'remote-monitoring': Monitor,
    configuration: Settings2,
    'cloud-deployment': Cloud,
    'self-hosting': Server,
};

// Markdown rendering
const md = new MarkdownIt({
    html: false,
    linkify: true,
    typographer: true,
});

const renderedHtml = computed(() => {
    if (!props.content) return '';
    return md.render(props.content);
});

// Mobile sidebar toggle
const isSidebarOpen = ref(false);

function toggleSidebar() {
    isSidebarOpen.value = !isSidebarOpen.value;
}

// Close sidebar on navigation
watch(
    () => props.slug,
    () => {
        isSidebarOpen.value = false;
    },
);

// Search functionality
const searchQuery = ref('');
const searchResults = ref<{ slug: string; title: string; excerpt: string }[]>([]);
const isSearching = ref(false);
const allDocs = ref<{ slug: string; title: string; content: string }[]>([]);
const searchLoaded = ref(false);

async function loadSearchIndex() {
    if (searchLoaded.value) return;

    // Use the sections + current content as the search index
    // All docs are loaded via a fetch call to each doc page
    const docs: { slug: string; title: string; content: string }[] = [];

    for (const section of props.sections) {
        if (section.slug === props.slug) {
            docs.push({ slug: section.slug, title: section.title, content: props.content });
        } else {
            try {
                const response = await fetch(`/docs/${section.slug}`, {
                    headers: { 'X-Inertia': 'true', 'X-Inertia-Version': usePage().version || '' },
                });
                if (response.ok) {
                    const data = await response.json();
                    docs.push({
                        slug: section.slug,
                        title: section.title,
                        content: data.props?.content || '',
                    });
                }
            } catch {
                // Skip docs that fail to load
            }
        }
    }

    allDocs.value = docs;
    searchLoaded.value = true;
}

function performSearch() {
    if (!searchQuery.value.trim()) {
        searchResults.value = [];
        isSearching.value = false;
        return;
    }

    isSearching.value = true;
    const query = searchQuery.value.toLowerCase();

    searchResults.value = allDocs.value
        .filter((doc) => {
            return doc.title.toLowerCase().includes(query) || doc.content.toLowerCase().includes(query);
        })
        .map((doc) => {
            // Find an excerpt around the matched text
            const contentLower = doc.content.toLowerCase();
            const matchIndex = contentLower.indexOf(query);
            let excerpt = '';

            if (matchIndex !== -1) {
                const start = Math.max(0, matchIndex - 60);
                const end = Math.min(doc.content.length, matchIndex + query.length + 60);
                excerpt = (start > 0 ? '...' : '') + doc.content.slice(start, end).replace(/\n/g, ' ') + (end < doc.content.length ? '...' : '');
            } else {
                // Title match — show first 120 chars of content
                excerpt = doc.content.slice(0, 120).replace(/\n/g, ' ').replace(/^#+ /, '') + '...';
            }

            return {
                slug: doc.slug,
                title: doc.title,
                excerpt,
            };
        });
}

watch(searchQuery, performSearch);

function handleSearchFocus() {
    loadSearchIndex();
}

// Previous/Next navigation
const currentIndex = computed(() => props.sections.findIndex((s) => s.slug === props.slug));
const prevSection = computed(() => (currentIndex.value > 0 ? props.sections[currentIndex.value - 1] : null));
const nextSection = computed(() => (currentIndex.value < props.sections.length - 1 ? props.sections[currentIndex.value + 1] : null));

// Auth state for showing login vs dashboard link
const page = usePage();
const isAuthenticated = computed(() => !!(page.props as Record<string, unknown>).auth);

// Close sidebar on escape
function handleKeydown(e: KeyboardEvent) {
    if (e.key === 'Escape' && isSidebarOpen.value) {
        isSidebarOpen.value = false;
    }
}

onMounted(() => {
    document.addEventListener('keydown', handleKeydown);
});

onBeforeUnmount(() => {
    document.removeEventListener('keydown', handleKeydown);
});
</script>

<template>
    <Head :title="`${sections.find((s) => s.slug === slug)?.title || 'Documentation'} - Docs`" />

    <div class="flex min-h-screen flex-col bg-background">
        <!-- Top bar -->
        <header class="sticky top-0 z-30 border-b border-border bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
            <div class="mx-auto flex h-14 max-w-7xl items-center justify-between px-4">
                <div class="flex items-center gap-3">
                    <!-- Mobile menu button -->
                    <Button
                        variant="ghost"
                        size="icon"
                        class="lg:hidden"
                        aria-label="Toggle navigation"
                        @click="toggleSidebar"
                    >
                        <Menu v-if="!isSidebarOpen" class="size-5" />
                        <X v-else class="size-5" />
                    </Button>

                    <Link href="/" class="flex items-center gap-2">
                        <AppLogoIcon class="size-7 fill-current text-foreground" />
                        <span class="text-sm font-semibold">Chief</span>
                    </Link>

                    <ChevronRight class="size-4 text-muted-foreground" />
                    <span class="text-sm text-muted-foreground">Docs</span>
                </div>

                <div class="flex items-center gap-2">
                    <Link
                        v-if="isAuthenticated"
                        href="/dashboard"
                        class="text-sm text-muted-foreground transition-colors hover:text-foreground"
                    >
                        Dashboard
                    </Link>
                    <Link
                        v-else
                        href="/login"
                        class="text-sm text-muted-foreground transition-colors hover:text-foreground"
                    >
                        Sign in
                    </Link>
                </div>
            </div>
        </header>

        <div class="mx-auto flex w-full max-w-7xl flex-1">
            <!-- Mobile sidebar overlay -->
            <Transition
                enter-active-class="transition-opacity duration-[var(--duration-standard)]"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition-opacity duration-[var(--duration-micro)]"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="isSidebarOpen"
                    class="fixed inset-0 z-40 bg-black/50 lg:hidden"
                    @click="isSidebarOpen = false"
                />
            </Transition>

            <!-- Sidebar navigation -->
            <aside
                :class="[
                    'fixed inset-y-0 left-0 z-50 w-72 shrink-0 border-r border-border bg-background pt-14 transition-transform duration-[var(--duration-standard)] lg:sticky lg:top-14 lg:z-auto lg:h-[calc(100vh-3.5rem)] lg:translate-x-0 lg:pt-0',
                    isSidebarOpen ? 'translate-x-0' : '-translate-x-full',
                ]"
            >
                <div class="flex h-full flex-col overflow-y-auto p-4">
                    <!-- Search -->
                    <div class="relative mb-4">
                        <Search class="absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                        <Input
                            v-model="searchQuery"
                            type="search"
                            placeholder="Search docs..."
                            class="pl-9"
                            @focus="handleSearchFocus"
                        />
                    </div>

                    <!-- Search results -->
                    <div v-if="searchQuery.trim() && isSearching" class="mb-4 space-y-1">
                        <p class="mb-2 text-xs font-medium uppercase tracking-wider text-muted-foreground">
                            {{ searchResults.length }} result{{ searchResults.length === 1 ? '' : 's' }}
                        </p>
                        <Link
                            v-for="result in searchResults"
                            :key="result.slug"
                            :href="`/docs/${result.slug}`"
                            class="block rounded-md px-3 py-2 text-sm transition-colors hover:bg-accent"
                        >
                            <span class="font-medium text-foreground">{{ result.title }}</span>
                            <p class="mt-0.5 line-clamp-2 text-xs text-muted-foreground">
                                {{ result.excerpt }}
                            </p>
                        </Link>
                        <p
                            v-if="searchResults.length === 0"
                            class="px-3 py-2 text-sm text-muted-foreground"
                        >
                            No results found.
                        </p>
                    </div>

                    <!-- Navigation -->
                    <nav v-else class="space-y-1" aria-label="Documentation">
                        <Link
                            v-for="section in sections"
                            :key="section.slug"
                            :href="`/docs/${section.slug}`"
                            prefetch
                            :class="[
                                'flex items-center gap-2.5 rounded-md px-3 py-2 text-sm transition-colors',
                                section.slug === slug
                                    ? 'bg-accent font-medium text-accent-foreground'
                                    : 'text-muted-foreground hover:bg-accent/50 hover:text-foreground',
                            ]"
                            :aria-current="section.slug === slug ? 'page' : undefined"
                        >
                            <component
                                :is="sectionIcons[section.slug] || Book"
                                class="size-4 shrink-0"
                            />
                            {{ section.title }}
                        </Link>
                    </nav>
                </div>
            </aside>

            <!-- Main content -->
            <main class="min-w-0 flex-1 px-4 py-8 lg:px-12">
                <article class="docs-content mx-auto max-w-3xl" v-html="renderedHtml" />

                <!-- Previous / Next navigation -->
                <nav class="mx-auto mt-12 flex max-w-3xl items-center justify-between border-t border-border pt-6" aria-label="Pagination">
                    <Link
                        v-if="prevSection"
                        :href="`/docs/${prevSection.slug}`"
                        prefetch
                        class="group flex items-center gap-2 text-sm text-muted-foreground transition-colors hover:text-foreground"
                    >
                        <ArrowLeft class="size-4 transition-transform group-hover:-translate-x-0.5" />
                        {{ prevSection.title }}
                    </Link>
                    <div v-else />

                    <Link
                        v-if="nextSection"
                        :href="`/docs/${nextSection.slug}`"
                        prefetch
                        class="group flex items-center gap-2 text-sm text-muted-foreground transition-colors hover:text-foreground"
                    >
                        {{ nextSection.title }}
                        <ArrowRight class="size-4 transition-transform group-hover:translate-x-0.5" />
                    </Link>
                    <div v-else />
                </nav>
            </main>
        </div>
    </div>
</template>

<style scoped>
/* Documentation markdown styling — reuses patterns from PrdPreviewPanel */
.docs-content :deep(h1) {
    font-size: 1.875rem;
    font-weight: 700;
    margin-top: 0;
    margin-bottom: 1rem;
    color: var(--foreground);
    line-height: 1.2;
    letter-spacing: -0.025em;
}

.docs-content :deep(h2) {
    font-size: 1.375rem;
    font-weight: 600;
    margin-top: 2rem;
    margin-bottom: 0.75rem;
    color: var(--foreground);
    line-height: 1.3;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid var(--border);
}

.docs-content :deep(h3) {
    font-size: 1.125rem;
    font-weight: 600;
    margin-top: 1.5rem;
    margin-bottom: 0.5rem;
    color: var(--foreground);
    line-height: 1.4;
}

.docs-content :deep(h4) {
    font-size: 1rem;
    font-weight: 600;
    margin-top: 1.25rem;
    margin-bottom: 0.25rem;
    color: var(--foreground);
}

.docs-content :deep(p) {
    margin-top: 0.75rem;
    margin-bottom: 0.75rem;
    line-height: 1.75;
    color: var(--foreground);
}

.docs-content :deep(ul),
.docs-content :deep(ol) {
    margin-top: 0.75rem;
    margin-bottom: 0.75rem;
    padding-left: 1.5rem;
}

.docs-content :deep(ul) {
    list-style-type: disc;
}

.docs-content :deep(ol) {
    list-style-type: decimal;
}

.docs-content :deep(li) {
    margin-top: 0.25rem;
    margin-bottom: 0.25rem;
    line-height: 1.7;
    color: var(--foreground);
}

.docs-content :deep(li > ul),
.docs-content :deep(li > ol) {
    margin-top: 0.125rem;
    margin-bottom: 0.125rem;
}

.docs-content :deep(code) {
    font-family: var(--font-mono, 'Geist Mono', monospace);
    font-size: 0.875em;
    background-color: var(--muted);
    padding: 0.125rem 0.375rem;
    border-radius: 0.25rem;
}

.docs-content :deep(pre) {
    margin-top: 1rem;
    margin-bottom: 1rem;
    padding: 1rem 1.25rem;
    background-color: var(--muted);
    border-radius: 0.5rem;
    overflow-x: auto;
    line-height: 1.6;
}

.docs-content :deep(pre code) {
    background-color: transparent;
    padding: 0;
    font-size: 0.85em;
}

.docs-content :deep(blockquote) {
    border-left: 3px solid var(--primary);
    margin-top: 1rem;
    margin-bottom: 1rem;
    padding-left: 1rem;
    color: var(--muted-foreground);
}

.docs-content :deep(hr) {
    border: none;
    border-top: 1px solid var(--border);
    margin-top: 2rem;
    margin-bottom: 2rem;
}

.docs-content :deep(table) {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1rem;
    margin-bottom: 1rem;
    font-size: 0.875rem;
}

.docs-content :deep(th),
.docs-content :deep(td) {
    border: 1px solid var(--border);
    padding: 0.5rem 0.75rem;
    text-align: left;
}

.docs-content :deep(th) {
    background-color: var(--muted);
    font-weight: 600;
}

.docs-content :deep(strong) {
    font-weight: 600;
}

.docs-content :deep(a) {
    color: var(--primary);
    text-decoration: underline;
    text-decoration-color: var(--primary);
    text-underline-offset: 2px;
}

.docs-content :deep(a:hover) {
    opacity: 0.8;
}

/* Responsive table */
@media (max-width: 640px) {
    .docs-content :deep(table) {
        display: block;
        overflow-x: auto;
    }
}
</style>
