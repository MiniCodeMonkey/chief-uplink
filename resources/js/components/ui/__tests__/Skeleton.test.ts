import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import Skeleton from '../skeleton/Skeleton.vue';

describe('Skeleton', () => {
    it('renders a div element', () => {
        const wrapper = mount(Skeleton);
        expect(wrapper.element.tagName).toBe('DIV');
    });

    it('has animate-pulse class', () => {
        const wrapper = mount(Skeleton);
        expect(wrapper.classes()).toContain('animate-pulse');
    });

    it('has rounded-md class', () => {
        const wrapper = mount(Skeleton);
        expect(wrapper.classes()).toContain('rounded-md');
    });

    it('applies custom class', () => {
        const wrapper = mount(Skeleton, {
            props: { class: 'h-4 w-32' },
        });
        expect(wrapper.classes()).toContain('h-4');
        expect(wrapper.classes()).toContain('w-32');
    });

    it('has data-slot attribute', () => {
        const wrapper = mount(Skeleton);
        expect(wrapper.attributes('data-slot')).toBe('skeleton');
    });
});
