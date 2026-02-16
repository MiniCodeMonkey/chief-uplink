import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import StatusDot from '../status-dot/StatusDot.vue';

describe('StatusDot', () => {
    it('renders with online state', () => {
        const wrapper = mount(StatusDot, { props: { state: 'online' } });
        expect(wrapper.attributes('role')).toBe('status');
        expect(wrapper.attributes('aria-label')).toBe('Online');
        expect(wrapper.find('.sr-only').text()).toBe('Online');
    });

    it('renders with reconnecting state', () => {
        const wrapper = mount(StatusDot, { props: { state: 'reconnecting' } });
        expect(wrapper.attributes('aria-label')).toBe('Reconnecting');
        expect(wrapper.find('.sr-only').text()).toBe('Reconnecting');
    });

    it('renders with offline state', () => {
        const wrapper = mount(StatusDot, { props: { state: 'offline' } });
        expect(wrapper.attributes('aria-label')).toBe('Offline');
        expect(wrapper.find('.sr-only').text()).toBe('Offline');
    });

    it('renders with never-connected state', () => {
        const wrapper = mount(StatusDot, { props: { state: 'never-connected' } });
        expect(wrapper.attributes('aria-label')).toBe('Never connected');
        expect(wrapper.find('.sr-only').text()).toBe('Never connected');
    });

    it('applies online class for online state', () => {
        const wrapper = mount(StatusDot, { props: { state: 'online' } });
        expect(wrapper.classes()).toContain('bg-success');
    });

    it('applies custom class', () => {
        const wrapper = mount(StatusDot, {
            props: { state: 'online', class: 'custom-class' },
        });
        expect(wrapper.classes()).toContain('custom-class');
    });

    it('has data-slot attribute', () => {
        const wrapper = mount(StatusDot, { props: { state: 'online' } });
        expect(wrapper.attributes('data-slot')).toBe('status-dot');
    });
});
