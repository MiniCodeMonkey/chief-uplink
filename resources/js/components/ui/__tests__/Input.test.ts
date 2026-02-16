import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { Input } from '../input';

describe('Input', () => {
    it('renders as input element', () => {
        const wrapper = mount(Input);
        expect(wrapper.element.tagName).toBe('INPUT');
    });

    it('has data-slot input', () => {
        const wrapper = mount(Input);
        expect(wrapper.attributes('data-slot')).toBe('input');
    });

    it('emits update:modelValue on input', async () => {
        const wrapper = mount(Input, {
            props: { modelValue: '' },
        });
        await wrapper.setValue('hello');
        expect(wrapper.emitted('update:modelValue')).toBeTruthy();
    });

    it('displays default value', () => {
        const wrapper = mount(Input, {
            props: { defaultValue: 'default text' },
        });
        expect((wrapper.element as HTMLInputElement).value).toBe(
            'default text',
        );
    });

    it('applies custom class', () => {
        const wrapper = mount(Input, {
            props: { class: 'w-full' },
        });
        expect(wrapper.classes()).toContain('w-full');
    });

    it('has focus styling classes', () => {
        const wrapper = mount(Input);
        const html = wrapper.html();
        expect(html).toContain('focus-visible:border-ring');
    });

    it('has aria-invalid error styling', () => {
        const wrapper = mount(Input);
        const html = wrapper.html();
        expect(html).toContain('aria-invalid:border-destructive');
    });

    it('has disabled styling', () => {
        const wrapper = mount(Input);
        const html = wrapper.html();
        expect(html).toContain('disabled:opacity-50');
    });
});
