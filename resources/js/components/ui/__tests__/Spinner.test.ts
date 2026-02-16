import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import Spinner from '../spinner/Spinner.vue';

describe('Spinner', () => {
    it('renders with role status', () => {
        const wrapper = mount(Spinner);
        expect(wrapper.attributes('role')).toBe('status');
    });

    it('has aria-label Loading', () => {
        const wrapper = mount(Spinner);
        expect(wrapper.attributes('aria-label')).toBe('Loading');
    });

    it('has animate-spin class', () => {
        const wrapper = mount(Spinner);
        expect(wrapper.classes()).toContain('animate-spin');
    });
});
