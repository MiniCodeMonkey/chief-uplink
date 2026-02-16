import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { h } from 'vue';
import EmptyState from '../empty-state/EmptyState.vue';

describe('EmptyState', () => {
    it('renders title', () => {
        const wrapper = mount(EmptyState, {
            props: { title: 'No data found' },
        });
        expect(wrapper.find('h3').text()).toBe('No data found');
    });

    it('renders description when provided', () => {
        const wrapper = mount(EmptyState, {
            props: {
                title: 'No data',
                description: 'Try adding some items.',
            },
        });
        expect(wrapper.find('p').text()).toBe('Try adding some items.');
    });

    it('does not render description when not provided', () => {
        const wrapper = mount(EmptyState, {
            props: { title: 'No data' },
        });
        expect(wrapper.findAll('p')).toHaveLength(0);
    });

    it('renders icon when provided', () => {
        const IconComponent = { template: '<svg data-test="icon"></svg>' };
        const wrapper = mount(EmptyState, {
            props: { title: 'No data', icon: IconComponent as any },
        });
        expect(wrapper.find('[data-test="icon"]').exists()).toBe(true);
    });

    it('does not render icon container when not provided', () => {
        const wrapper = mount(EmptyState, {
            props: { title: 'No data' },
        });
        expect(wrapper.find('.rounded-full').exists()).toBe(false);
    });

    it('renders action slot when provided', () => {
        const wrapper = mount(EmptyState, {
            props: { title: 'No data' },
            slots: {
                action: () => h('button', { 'data-test': 'action' }, 'Add Item'),
            },
        });
        expect(wrapper.find('[data-test="action"]').text()).toBe('Add Item');
    });

    it('does not render action container when slot is empty', () => {
        const wrapper = mount(EmptyState, {
            props: { title: 'No data' },
        });
        expect(wrapper.find('.mt-2').exists()).toBe(false);
    });

    it('has data-slot attribute', () => {
        const wrapper = mount(EmptyState, {
            props: { title: 'No data' },
        });
        expect(wrapper.attributes('data-slot')).toBe('empty-state');
    });
});
