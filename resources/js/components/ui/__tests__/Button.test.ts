import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { Button } from '../button';

describe('Button', () => {
    it('renders slot content', () => {
        const wrapper = mount(Button, {
            slots: { default: 'Click me' },
        });
        expect(wrapper.text()).toBe('Click me');
    });

    it('renders as button element by default', () => {
        const wrapper = mount(Button);
        expect(wrapper.element.tagName).toBe('BUTTON');
    });

    it('has data-slot button', () => {
        const wrapper = mount(Button);
        expect(wrapper.attributes('data-slot')).toBe('button');
    });

    it('applies default variant classes', () => {
        const wrapper = mount(Button);
        expect(wrapper.classes()).toContain('bg-primary');
    });

    it('applies destructive variant classes', () => {
        const wrapper = mount(Button, {
            props: { variant: 'destructive' },
        });
        expect(wrapper.classes()).toContain('bg-destructive');
    });

    it('applies outline variant classes', () => {
        const wrapper = mount(Button, {
            props: { variant: 'outline' },
        });
        expect(wrapper.classes()).toContain('border');
    });

    it('applies secondary variant classes', () => {
        const wrapper = mount(Button, {
            props: { variant: 'secondary' },
        });
        expect(wrapper.classes()).toContain('bg-secondary');
    });

    it('applies ghost variant classes', () => {
        const wrapper = mount(Button, {
            props: { variant: 'ghost' },
        });
        expect(wrapper.html()).toContain('hover:bg-accent');
    });

    it('applies link variant classes', () => {
        const wrapper = mount(Button, {
            props: { variant: 'link' },
        });
        expect(wrapper.html()).toContain('underline-offset-4');
    });

    it('applies default size classes', () => {
        const wrapper = mount(Button);
        expect(wrapper.classes()).toContain('h-9');
    });

    it('applies sm size classes', () => {
        const wrapper = mount(Button, {
            props: { size: 'sm' },
        });
        expect(wrapper.classes()).toContain('h-8');
    });

    it('applies lg size classes', () => {
        const wrapper = mount(Button, {
            props: { size: 'lg' },
        });
        expect(wrapper.classes()).toContain('h-10');
    });

    it('applies icon size classes', () => {
        const wrapper = mount(Button, {
            props: { size: 'icon' },
        });
        expect(wrapper.classes()).toContain('size-9');
    });

    it('includes press animation class', () => {
        const wrapper = mount(Button);
        expect(wrapper.html()).toContain('active:scale-[0.97]');
    });

    it('includes disabled styling', () => {
        const wrapper = mount(Button);
        expect(wrapper.html()).toContain('disabled:opacity-50');
    });

    it('applies custom class', () => {
        const wrapper = mount(Button, {
            props: { class: 'w-full' },
        });
        expect(wrapper.classes()).toContain('w-full');
    });
});
