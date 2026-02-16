import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import Toggle from '../toggle/Toggle.vue';

describe('Toggle', () => {
    it('renders as switch role', () => {
        const wrapper = mount(Toggle);
        expect(wrapper.attributes('role')).toBe('switch');
    });

    it('defaults to off state', () => {
        const wrapper = mount(Toggle);
        expect(wrapper.attributes('aria-checked')).toBe('false');
    });

    it('renders on state when modelValue is true', () => {
        const wrapper = mount(Toggle, {
            props: { modelValue: true },
        });
        expect(wrapper.attributes('aria-checked')).toBe('true');
    });

    it('emits update:modelValue on click', async () => {
        const wrapper = mount(Toggle, {
            props: { modelValue: false },
        });
        await wrapper.trigger('click');
        expect(wrapper.emitted('update:modelValue')).toBeTruthy();
        expect(wrapper.emitted('update:modelValue')![0]).toEqual([true]);
    });

    it('toggles from on to off', async () => {
        const wrapper = mount(Toggle, {
            props: { modelValue: true },
        });
        await wrapper.trigger('click');
        expect(wrapper.emitted('update:modelValue')![0]).toEqual([false]);
    });

    it('does not emit when disabled', async () => {
        const wrapper = mount(Toggle, {
            props: { modelValue: false, disabled: true },
        });
        await wrapper.trigger('click');
        expect(wrapper.emitted('update:modelValue')).toBeFalsy();
    });

    it('renders disabled state', () => {
        const wrapper = mount(Toggle, {
            props: { disabled: true },
        });
        expect(wrapper.attributes('disabled')).toBeDefined();
    });

    it('applies aria-label', () => {
        const wrapper = mount(Toggle, {
            props: { ariaLabel: 'Enable notifications' },
        });
        expect(wrapper.attributes('aria-label')).toBe('Enable notifications');
    });

    it('has data-slot attribute', () => {
        const wrapper = mount(Toggle);
        expect(wrapper.attributes('data-slot')).toBe('toggle');
    });
});
