<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineOptions({ layout: false });
</script>

<template>
    <Head title="Remote control for Chief" />

    <div class="min-h-screen bg-bg">
        <!-- Navigation -->
        <nav class="fixed top-0 inset-x-0 z-50 border-b border-border/50 bg-bg/80 backdrop-blur-md">
            <div class="mx-auto flex h-14 max-w-5xl items-center justify-between px-6">
                <div class="flex items-center gap-2.5">
                    <!-- Mark -->
                    <svg class="h-7 w-7 text-text-heading" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="2" y="2" width="28" height="28" rx="6" stroke="currentColor" stroke-width="2.5" />
                        <path d="M10 16h12M16 10v12" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" />
                    </svg>
                    <span class="text-base font-semibold text-text-heading tracking-tight">Uplink</span>
                </div>
                <div class="flex items-center gap-3">
                    <Link
                        href="/login"
                        class="rounded-md px-3 py-1.5 text-sm text-text-secondary transition-colors hover:text-text-heading"
                    >
                        Sign in
                    </Link>
                    <Link
                        href="/register"
                        class="rounded-md bg-interactive px-3 py-1.5 text-sm font-medium text-bg transition-colors hover:opacity-90"
                    >
                        Sign up
                    </Link>
                </div>
            </div>
        </nav>

        <!-- Hero -->
        <section class="relative pt-32 pb-20 px-6">
            <div class="mx-auto max-w-5xl">
                <div class="max-w-2xl">
                    <h1 class="text-4xl font-bold text-text-heading sm:text-5xl leading-[1.1]">
                        Remote control for Chief
                    </h1>
                    <p class="mt-5 text-lg text-text-secondary leading-relaxed max-w-lg">
                        Monitor runs, manage servers, and chat with PRDs — all from a web dashboard that connects to your Chief CLI.
                    </p>
                    <div class="mt-8 flex flex-wrap items-center gap-3">
                        <Link
                            href="/register"
                            class="rounded-md bg-interactive px-5 py-2.5 text-sm font-medium text-bg transition-colors hover:opacity-90"
                        >
                            Get Started
                        </Link>
                        <a
                            href="https://github.com/nicholasgriffintn/chief"
                            class="flex items-center gap-2 rounded-md border border-border px-5 py-2.5 text-sm font-medium text-text transition-colors hover:bg-bg-surface"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                            </svg>
                            View on GitHub
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Screenshot -->
        <section class="px-6 pb-24">
            <div class="mx-auto max-w-5xl">
                <div class="overflow-hidden rounded-lg border border-border bg-bg-card shadow-2xl shadow-black/20">
                    <!-- Window chrome -->
                    <div class="flex items-center gap-2 border-b border-border px-4 py-3">
                        <span class="h-3 w-3 rounded-full bg-text-muted/40"></span>
                        <span class="h-3 w-3 rounded-full bg-text-muted/40"></span>
                        <span class="h-3 w-3 rounded-full bg-text-muted/40"></span>
                        <span class="ml-4 text-xs text-text-muted font-mono">chief-uplink</span>
                    </div>
                    <!-- Simulated app UI -->
                    <div class="flex min-h-[320px] sm:min-h-[400px]">
                        <!-- Sidebar mock -->
                        <div class="hidden sm:flex w-44 flex-col border-r border-border p-3 gap-1.5">
                            <div class="mb-3 text-sm font-semibold text-text-heading px-2">Chief Uplink</div>
                            <div class="rounded-md bg-bg-surface px-2 py-1.5 text-xs text-text-heading font-medium">Home</div>
                            <div class="rounded-md px-2 py-1.5 text-xs text-text-muted">Devices</div>
                            <div class="rounded-md px-2 py-1.5 text-xs text-text-muted">Servers</div>
                            <div class="rounded-md px-2 py-1.5 text-xs text-text-muted">Settings</div>
                        </div>
                        <!-- Content mock -->
                        <div class="flex-1 p-6">
                            <div class="text-lg font-bold text-text-heading">Welcome to Chief Uplink</div>
                            <div class="mt-6 grid grid-cols-2 sm:grid-cols-3 gap-3">
                                <div class="rounded-md border border-border bg-bg-surface p-3">
                                    <div class="text-xs text-text-muted">Devices</div>
                                    <div class="mt-1 text-xl font-bold text-text-heading">3</div>
                                </div>
                                <div class="rounded-md border border-border bg-bg-surface p-3">
                                    <div class="text-xs text-text-muted">Active Runs</div>
                                    <div class="mt-1 text-xl font-bold text-success">2</div>
                                </div>
                                <div class="rounded-md border border-border bg-bg-surface p-3">
                                    <div class="text-xs text-text-muted">Servers</div>
                                    <div class="mt-1 text-xl font-bold text-text-heading">5</div>
                                </div>
                            </div>
                            <div class="mt-6 rounded-md border border-border bg-bg-surface p-3">
                                <div class="flex items-center gap-2">
                                    <span class="h-2 w-2 rounded-full bg-success animate-pulse"></span>
                                    <span class="text-xs text-text-secondary">macbook-pro — running</span>
                                </div>
                                <div class="mt-2 font-mono text-xs text-text-muted leading-relaxed">
                                    <span class="text-brand">$</span> chief run --story US-028
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- How it works -->
        <section class="px-6 py-24 border-t border-border/50">
            <div class="mx-auto max-w-5xl">
                <h2 class="text-2xl font-bold text-text-heading">How it works</h2>
                <p class="mt-2 text-text-secondary">Three steps to get your CLI connected.</p>

                <div class="mt-12 grid gap-8 sm:grid-cols-3">
                    <!-- Step 1 -->
                    <div class="relative">
                        <div class="flex h-10 w-10 items-center justify-center rounded-md border border-border bg-bg-card text-sm font-bold text-text-heading font-mono">
                            1
                        </div>
                        <h3 class="mt-4 text-base font-semibold text-text-heading">Install Chief</h3>
                        <p class="mt-2 text-sm text-text-secondary leading-relaxed">
                            Install the Chief CLI on your machine. It handles code generation, PRDs, and run orchestration locally.
                        </p>
                        <div class="mt-3 rounded-md bg-bg-card border border-border px-3 py-2">
                            <code class="text-xs text-text font-mono">go install github.com/...chief</code>
                        </div>
                    </div>

                    <!-- Step 2 -->
                    <div class="relative">
                        <div class="flex h-10 w-10 items-center justify-center rounded-md border border-border bg-bg-card text-sm font-bold text-text-heading font-mono">
                            2
                        </div>
                        <h3 class="mt-4 text-base font-semibold text-text-heading">Connect to Uplink</h3>
                        <p class="mt-2 text-sm text-text-secondary leading-relaxed">
                            Point your CLI at your Uplink instance. Chief registers as a device and opens a persistent WebSocket connection.
                        </p>
                        <div class="mt-3 rounded-md bg-bg-card border border-border px-3 py-2">
                            <code class="text-xs text-text font-mono">chief connect &lt;uplink-url&gt;</code>
                        </div>
                    </div>

                    <!-- Step 3 -->
                    <div class="relative">
                        <div class="flex h-10 w-10 items-center justify-center rounded-md border border-border bg-bg-card text-sm font-bold text-text-heading font-mono">
                            3
                        </div>
                        <h3 class="mt-4 text-base font-semibold text-text-heading">Manage from anywhere</h3>
                        <p class="mt-2 text-sm text-text-secondary leading-relaxed">
                            Open the dashboard to monitor runs, review diffs, provision servers, and manage PRDs — all remotely.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Features -->
        <section class="px-6 py-24 border-t border-border/50">
            <div class="mx-auto max-w-5xl">
                <h2 class="text-2xl font-bold text-text-heading">What you get</h2>
                <p class="mt-2 text-text-secondary">Everything you need to operate Chief remotely.</p>

                <div class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <!-- PRD Chat -->
                    <div class="rounded-lg border border-border bg-bg-card p-5">
                        <div class="flex h-9 w-9 items-center justify-center rounded-md bg-bg-surface">
                            <svg class="h-4.5 w-4.5 text-text-secondary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                            </svg>
                        </div>
                        <h3 class="mt-3 text-sm font-semibold text-text-heading">PRD Chat</h3>
                        <p class="mt-1.5 text-sm text-text-secondary leading-relaxed">
                            Chat with your PRDs to refine requirements and generate user stories before kicking off a run.
                        </p>
                    </div>

                    <!-- Run Monitoring -->
                    <div class="rounded-lg border border-border bg-bg-card p-5">
                        <div class="flex h-9 w-9 items-center justify-center rounded-md bg-bg-surface">
                            <svg class="h-4.5 w-4.5 text-text-secondary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                            </svg>
                        </div>
                        <h3 class="mt-3 text-sm font-semibold text-text-heading">Run Monitoring</h3>
                        <p class="mt-1.5 text-sm text-text-secondary leading-relaxed">
                            Watch runs in real time. See progress, logs, and story status as Chief works through your PRD.
                        </p>
                    </div>

                    <!-- Code Review -->
                    <div class="rounded-lg border border-border bg-bg-card p-5">
                        <div class="flex h-9 w-9 items-center justify-center rounded-md bg-bg-surface">
                            <svg class="h-4.5 w-4.5 text-text-secondary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="m18 16 4-4-4-4"/><path d="m6 8-4 4 4 4"/><path d="m14.5 4-5 16"/>
                            </svg>
                        </div>
                        <h3 class="mt-3 text-sm font-semibold text-text-heading">Code Review</h3>
                        <p class="mt-1.5 text-sm text-text-secondary leading-relaxed">
                            Review diffs generated by each run. See exactly what Chief changed before merging.
                        </p>
                    </div>

                    <!-- Cloud Servers -->
                    <div class="rounded-lg border border-border bg-bg-card p-5">
                        <div class="flex h-9 w-9 items-center justify-center rounded-md bg-bg-surface">
                            <svg class="h-4.5 w-4.5 text-text-secondary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect width="20" height="8" x="2" y="2" rx="2" ry="2"/><rect width="20" height="8" x="2" y="14" rx="2" ry="2"/><line x1="6" x2="6.01" y1="6" y2="6"/><line x1="6" x2="6.01" y1="18" y2="18"/>
                            </svg>
                        </div>
                        <h3 class="mt-3 text-sm font-semibold text-text-heading">Cloud Servers</h3>
                        <p class="mt-1.5 text-sm text-text-secondary leading-relaxed">
                            Provision and manage cloud servers directly from the dashboard. Connect your provider credentials once.
                        </p>
                    </div>

                    <!-- Teams -->
                    <div class="rounded-lg border border-border bg-bg-card p-5">
                        <div class="flex h-9 w-9 items-center justify-center rounded-md bg-bg-surface">
                            <svg class="h-4.5 w-4.5 text-text-secondary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 21a8 8 0 0 0-16 0"/><circle cx="10" cy="8" r="5"/><path d="M22 20c0-3.37-2.69-6.29-6.44-7.4"/><path d="M16 3.13a4 4 0 0 1 0 9.74"/>
                            </svg>
                        </div>
                        <h3 class="mt-3 text-sm font-semibold text-text-heading">Teams</h3>
                        <p class="mt-1.5 text-sm text-text-secondary leading-relaxed">
                            Organize devices and servers under teams. Switch between team workspaces with one click.
                        </p>
                    </div>

                    <!-- Open Source -->
                    <div class="rounded-lg border border-border bg-bg-card p-5">
                        <div class="flex h-9 w-9 items-center justify-center rounded-md bg-bg-surface">
                            <svg class="h-4.5 w-4.5 text-text-secondary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"/><line x1="2" x2="22" y1="12" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
                            </svg>
                        </div>
                        <h3 class="mt-3 text-sm font-semibold text-text-heading">Open Source</h3>
                        <p class="mt-1.5 text-sm text-text-secondary leading-relaxed">
                            The entire stack is open source. Read the code, file issues, and contribute on GitHub.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Self-hosting -->
        <section class="px-6 py-20 border-t border-border/50">
            <div class="mx-auto max-w-5xl text-center">
                <h2 class="text-xl font-bold text-text-heading">Self-host it</h2>
                <p class="mt-3 text-sm text-text-secondary leading-relaxed max-w-md mx-auto">
                    Uplink is designed to run on your own infrastructure. Clone the repo, configure your environment, and deploy.
                </p>
                <a
                    href="https://github.com/nicholasgriffintn/chief"
                    class="mt-5 inline-flex items-center gap-2 text-sm font-medium text-brand hover:underline"
                >
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                    </svg>
                    View on GitHub
                </a>
            </div>
        </section>

        <!-- Footer -->
        <footer class="border-t border-border/50 px-6 py-8">
            <div class="mx-auto max-w-5xl flex items-center justify-between">
                <div class="flex items-center gap-2 text-sm text-text-muted">
                    <svg class="h-5 w-5" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="2" y="2" width="28" height="28" rx="6" stroke="currentColor" stroke-width="2.5" />
                        <path d="M10 16h12M16 10v12" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" />
                    </svg>
                    Chief Uplink
                </div>
                <p class="text-xs text-text-muted">
                    Built with Chief
                </p>
            </div>
        </footer>
    </div>
</template>
