import { mount } from '@vue/test-utils';
import { describe, expect, it, vi, beforeEach, afterEach } from 'vitest';
import { nextTick } from 'vue';

// Mock @inertiajs/vue3
vi.mock('@inertiajs/vue3', () => ({
    router: {
        visit: vi.fn(),
        post: vi.fn(),
        flushAll: vi.fn(),
    },
    usePage: () => ({
        props: {
            devices: [
                {
                    id: 1,
                    device_name: 'hetzner-vps',
                    connection_status: 'online',
                    projects: [
                        {
                            project_slug: 'chief-uplink',
                            project_name: 'Chief Uplink',
                            git_branch: 'main',
                            status: 'running',
                            stories_completed: 3,
                            stories_total: 10,
                        },
                        {
                            project_slug: 'api-gateway',
                            project_name: 'API Gateway',
                            git_branch: 'develop',
                            status: 'idle',
                            stories_completed: 0,
                            stories_total: 0,
                        },
                    ],
                },
                {
                    id: 2,
                    device_name: 'macbook-pro',
                    connection_status: 'offline',
                    projects: [],
                },
            ],
        },
    }),
}));

import CommandPalette from '../CommandPalette.vue';

// Helper to get teleported content
function getBody() {
    return document.body.innerHTML;
}

describe('CommandPalette', () => {
    beforeEach(() => {
        localStorage.clear();
    });

    afterEach(() => {
        document.body.innerHTML = '';
    });

    it('does not render dialog when closed', () => {
        const wrapper = mount(CommandPalette, {
            props: { open: false },
            attachTo: document.body,
        });
        expect(document.querySelector('[role="dialog"]')).toBeNull();
    });

    it('renders dialog when open', async () => {
        const wrapper = mount(CommandPalette, {
            props: { open: true },
            attachTo: document.body,
        });
        await nextTick();
        expect(document.querySelector('[role="dialog"]')).not.toBeNull();
    });

    it('has search input', async () => {
        const wrapper = mount(CommandPalette, {
            props: { open: true },
            attachTo: document.body,
        });
        await nextTick();
        const input = document.querySelector('input[type="text"]') as HTMLInputElement;
        expect(input).not.toBeNull();
    });

    it('shows results grouped by category', async () => {
        const wrapper = mount(CommandPalette, {
            props: { open: true },
            attachTo: document.body,
        });
        await nextTick();
        const html = getBody();
        expect(html).toContain('Projects');
        expect(html).toContain('Servers');
        expect(html).toContain('Actions');
    });

    it('shows project items from devices', async () => {
        const wrapper = mount(CommandPalette, {
            props: { open: true },
            attachTo: document.body,
        });
        await nextTick();
        const html = getBody();
        expect(html).toContain('Chief Uplink');
        expect(html).toContain('API Gateway');
    });

    it('shows server items from devices', async () => {
        const wrapper = mount(CommandPalette, {
            props: { open: true },
            attachTo: document.body,
        });
        await nextTick();
        const html = getBody();
        expect(html).toContain('hetzner-vps');
        expect(html).toContain('macbook-pro');
    });

    it('shows action items', async () => {
        const wrapper = mount(CommandPalette, {
            props: { open: true },
            attachTo: document.body,
        });
        await nextTick();
        const html = getBody();
        expect(html).toContain('Clone Repository');
        expect(html).toContain('Create Project');
        expect(html).toContain('New PRD');
        expect(html).toContain('Start Run');
        expect(html).toContain('Settings');
        expect(html).toContain('Sign Out');
    });

    it('filters items based on query', async () => {
        const wrapper = mount(CommandPalette, {
            props: { open: true },
            attachTo: document.body,
        });
        await nextTick();

        const input = document.querySelector('input[type="text"]') as HTMLInputElement;
        input.value = 'chief';
        input.dispatchEvent(new Event('input'));
        await nextTick();

        const textContent = document.body.textContent || '';
        expect(textContent).toContain('Chief Uplink');
        expect(textContent).not.toContain('API Gateway');
    });

    it('shows no results message when nothing matches', async () => {
        const wrapper = mount(CommandPalette, {
            props: { open: true },
            attachTo: document.body,
        });
        await nextTick();

        const input = document.querySelector('input[type="text"]') as HTMLInputElement;
        input.value = 'zzzznonexistent';
        input.dispatchEvent(new Event('input'));
        await nextTick();

        expect(document.body.textContent).toContain('No results found');
    });

    it('emits update:open false on Escape key', async () => {
        const wrapper = mount(CommandPalette, {
            props: { open: true },
            attachTo: document.body,
        });
        await nextTick();

        document.dispatchEvent(
            new KeyboardEvent('keydown', { key: 'Escape' }),
        );

        expect(wrapper.emitted('update:open')).toBeTruthy();
        expect(wrapper.emitted('update:open')![0]).toEqual([false]);
    });

    it('has listbox role on results', async () => {
        const wrapper = mount(CommandPalette, {
            props: { open: true },
            attachTo: document.body,
        });
        await nextTick();

        expect(document.querySelector('[role="listbox"]')).not.toBeNull();
    });

    it('result items have option role', async () => {
        const wrapper = mount(CommandPalette, {
            props: { open: true },
            attachTo: document.body,
        });
        await nextTick();

        const options = document.querySelectorAll('[role="option"]');
        expect(options.length).toBeGreaterThan(0);
    });

    it('highlights matched characters in search results', async () => {
        const wrapper = mount(CommandPalette, {
            props: { open: true },
            attachTo: document.body,
        });
        await nextTick();

        const input = document.querySelector('input[type="text"]') as HTMLInputElement;
        input.value = 'chief';
        input.dispatchEvent(new Event('input'));
        await nextTick();

        expect(getBody()).toContain('font-semibold');
    });

    it('shows keyboard shortcut hints in footer', async () => {
        const wrapper = mount(CommandPalette, {
            props: { open: true },
            attachTo: document.body,
        });
        await nextTick();

        const html = getBody();
        expect(html).toContain('navigate');
        expect(html).toContain('select');
        expect(html).toContain('close');
    });
});
