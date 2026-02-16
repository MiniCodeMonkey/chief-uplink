import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { Badge } from '../badge';

describe('Badge', () => {
    it('renders slot content', () => {
        const wrapper = mount(Badge, {
            slots: { default: 'Active' },
        });
        expect(wrapper.text()).toBe('Active');
    });

    it('has data-slot badge', () => {
        const wrapper = mount(Badge, {
            slots: { default: 'Test' },
        });
        expect(wrapper.attributes('data-slot')).toBe('badge');
    });

    it('applies default variant', () => {
        const wrapper = mount(Badge, {
            slots: { default: 'Default' },
        });
        expect(wrapper.html()).toContain('bg-primary');
    });

    it('applies secondary variant', () => {
        const wrapper = mount(Badge, {
            props: { variant: 'secondary' },
            slots: { default: 'Secondary' },
        });
        expect(wrapper.html()).toContain('bg-secondary');
    });

    it('applies destructive variant', () => {
        const wrapper = mount(Badge, {
            props: { variant: 'destructive' },
            slots: { default: 'Error' },
        });
        expect(wrapper.html()).toContain('bg-destructive');
    });

    it('applies outline variant', () => {
        const wrapper = mount(Badge, {
            props: { variant: 'outline' },
            slots: { default: 'Outlined' },
        });
        expect(wrapper.html()).toContain('border');
    });

    it('applies custom class', () => {
        const wrapper = mount(Badge, {
            props: { class: 'ml-2' },
            slots: { default: 'Custom' },
        });
        expect(wrapper.classes()).toContain('ml-2');
    });
});
