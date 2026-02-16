import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';

// Mock @inertiajs/vue3
vi.mock('@inertiajs/vue3', () => ({
    router: {
        visit: vi.fn(),
    },
    usePage: () => ({
        props: {
            devices: [
                {
                    id: 1,
                    device_name: 'hetzner-vps',
                    connection_status: 'online',
                    os: 'linux',
                    arch: 'amd64',
                    chief_version: '0.5.0',
                    projects: [
                        {
                            project_slug: 'my-project',
                            project_name: 'My Project',
                            status: 'running',
                            git_branch: 'main',
                            stories_completed: 3,
                            stories_total: 10,
                        },
                    ],
                },
            ],
            selectedDeviceId: 1,
        },
    }),
    Link: {
        template: '<a :href="href"><slot /></a>',
        props: ['href', 'prefetch'],
    },
}));

import BreadcrumbPicker from '../BreadcrumbPicker.vue';

describe('BreadcrumbPicker', () => {
    it('renders the Chief logo link', async () => {
        const wrapper = mount(BreadcrumbPicker, {
            attachTo: document.body,
        });
        await nextTick();

        const homeLink = wrapper.find('a[href="/"]');
        expect(homeLink.exists()).toBe(true);
    });

    it('renders Chief text label', async () => {
        const wrapper = mount(BreadcrumbPicker, {
            attachTo: document.body,
        });
        await nextTick();

        expect(wrapper.html()).toContain('Chief');
    });

    it('renders chevron separator when devices exist', async () => {
        const wrapper = mount(BreadcrumbPicker, {
            attachTo: document.body,
        });
        await nextTick();

        // ChevronRight icons serve as separators
        expect(wrapper.html()).toContain('size-3.5');
    });

    it('has aria-label for home link', async () => {
        const wrapper = mount(BreadcrumbPicker, {
            attachTo: document.body,
        });
        await nextTick();

        const homeLink = wrapper.find('[aria-label="Chief home"]');
        expect(homeLink.exists()).toBe(true);
    });
});
