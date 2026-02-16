import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import CopyButton from '../copy-button/CopyButton.vue';

describe('CopyButton', () => {
    it('renders with default Copy label', () => {
        const wrapper = mount(CopyButton, {
            props: { value: 'some-text' },
        });
        expect(wrapper.text()).toContain('Copy');
    });

    it('renders with custom label', () => {
        const wrapper = mount(CopyButton, {
            props: { value: 'some-text', label: 'Copy IP' },
        });
        expect(wrapper.text()).toContain('Copy IP');
    });

    it('has type button', () => {
        const wrapper = mount(CopyButton, {
            props: { value: 'test' },
        });
        expect(wrapper.attributes('type')).toBe('button');
    });

    it('has data-slot attribute', () => {
        const wrapper = mount(CopyButton, {
            props: { value: 'test' },
        });
        expect(wrapper.attributes('data-slot')).toBe('copy-button');
    });

    it('has aria-label Copy by default', () => {
        const wrapper = mount(CopyButton, {
            props: { value: 'test' },
        });
        expect(wrapper.attributes('aria-label')).toBe('Copy');
    });

    it('copies text on click and shows Copied! feedback', async () => {
        const writeText = vi.fn().mockResolvedValue(undefined);
        Object.defineProperty(navigator, 'clipboard', {
            value: { writeText },
            writable: true,
            configurable: true,
        });

        const wrapper = mount(CopyButton, {
            props: { value: 'copy-me' },
        });
        await wrapper.trigger('click');

        expect(writeText).toHaveBeenCalledWith('copy-me');
        await vi.waitFor(() => {
            expect(wrapper.text()).toContain('Copied!');
        });
    });

    it('changes aria-label to Copied after copy', async () => {
        const writeText = vi.fn().mockResolvedValue(undefined);
        Object.defineProperty(navigator, 'clipboard', {
            value: { writeText },
            writable: true,
            configurable: true,
        });

        const wrapper = mount(CopyButton, {
            props: { value: 'test' },
        });
        await wrapper.trigger('click');

        await vi.waitFor(() => {
            expect(wrapper.attributes('aria-label')).toBe('Copied');
        });
    });
});
