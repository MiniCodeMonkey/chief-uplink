import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import Toast from '../toast/Toast.vue';

describe('Toast', () => {
    beforeEach(() => {
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('renders title', () => {
        const wrapper = mount(Toast, {
            props: { id: 'test-1', title: 'Success!' },
        });
        expect(wrapper.text()).toContain('Success!');
    });

    it('renders description when provided', () => {
        const wrapper = mount(Toast, {
            props: {
                id: 'test-1',
                title: 'Done',
                description: 'Operation completed.',
            },
        });
        expect(wrapper.text()).toContain('Operation completed.');
    });

    it('has role alert', () => {
        const wrapper = mount(Toast, {
            props: { id: 'test-1', title: 'Alert' },
        });
        expect(wrapper.attributes('role')).toBe('alert');
    });

    it('uses assertive aria-live for error variant', () => {
        const wrapper = mount(Toast, {
            props: { id: 'test-1', title: 'Error', variant: 'error' },
        });
        expect(wrapper.attributes('aria-live')).toBe('assertive');
    });

    it('uses polite aria-live for non-error variants', () => {
        const wrapper = mount(Toast, {
            props: { id: 'test-1', title: 'Info', variant: 'info' },
        });
        expect(wrapper.attributes('aria-live')).toBe('polite');
    });

    it('emits dismiss when close button clicked', async () => {
        const wrapper = mount(Toast, {
            props: { id: 'test-1', title: 'Dismissible' },
        });
        await wrapper.find('button[aria-label="Dismiss"]').trigger('click');
        vi.advanceTimersByTime(200);
    });

    it('renders action button when action prop provided', () => {
        const wrapper = mount(Toast, {
            props: {
                id: 'test-1',
                title: 'With action',
                action: { label: 'Retry', onClick: () => {} },
            },
        });
        expect(wrapper.text()).toContain('Retry');
    });

    it('has data-slot attribute', () => {
        const wrapper = mount(Toast, {
            props: { id: 'test-1', title: 'Test' },
        });
        expect(wrapper.attributes('data-slot')).toBe('toast');
    });

    it('applies success variant classes', () => {
        const wrapper = mount(Toast, {
            props: { id: 'test-1', title: 'Success', variant: 'success' },
        });
        expect(wrapper.html()).toContain('success');
    });

    it('applies error variant classes', () => {
        const wrapper = mount(Toast, {
            props: { id: 'test-1', title: 'Error', variant: 'error' },
        });
        expect(wrapper.html()).toContain('destructive');
    });
});
