import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import ProgressBar from '../progress-bar/ProgressBar.vue';

describe('ProgressBar', () => {
    it('renders with role progressbar', () => {
        const wrapper = mount(ProgressBar, { props: { value: 50 } });
        expect(wrapper.attributes('role')).toBe('progressbar');
    });

    it('sets aria-valuenow to current value', () => {
        const wrapper = mount(ProgressBar, { props: { value: 75 } });
        expect(wrapper.attributes('aria-valuenow')).toBe('75');
    });

    it('sets aria-valuemin to 0', () => {
        const wrapper = mount(ProgressBar, { props: { value: 50 } });
        expect(wrapper.attributes('aria-valuemin')).toBe('0');
    });

    it('sets aria-valuemax to default 100', () => {
        const wrapper = mount(ProgressBar, { props: { value: 50 } });
        expect(wrapper.attributes('aria-valuemax')).toBe('100');
    });

    it('sets aria-valuemax to custom max', () => {
        const wrapper = mount(ProgressBar, {
            props: { value: 5, max: 10 },
        });
        expect(wrapper.attributes('aria-valuemax')).toBe('10');
    });

    it('renders 0% width at value 0', () => {
        const wrapper = mount(ProgressBar, { props: { value: 0 } });
        const indicator = wrapper.find('[data-slot="progress-bar-indicator"]');
        expect(indicator.attributes('style')).toContain('width: 0%');
    });

    it('renders 100% width at full value', () => {
        const wrapper = mount(ProgressBar, { props: { value: 100 } });
        const indicator = wrapper.find('[data-slot="progress-bar-indicator"]');
        expect(indicator.attributes('style')).toContain('width: 100%');
    });

    it('renders 50% width at half value', () => {
        const wrapper = mount(ProgressBar, { props: { value: 50 } });
        const indicator = wrapper.find('[data-slot="progress-bar-indicator"]');
        expect(indicator.attributes('style')).toContain('width: 50%');
    });

    it('clamps percentage to max 100', () => {
        const wrapper = mount(ProgressBar, { props: { value: 150 } });
        const indicator = wrapper.find('[data-slot="progress-bar-indicator"]');
        expect(indicator.attributes('style')).toContain('width: 100%');
    });

    it('clamps percentage to min 0', () => {
        const wrapper = mount(ProgressBar, { props: { value: -10 } });
        const indicator = wrapper.find('[data-slot="progress-bar-indicator"]');
        expect(indicator.attributes('style')).toContain('width: 0%');
    });

    it('handles custom max value correctly', () => {
        const wrapper = mount(ProgressBar, {
            props: { value: 5, max: 10 },
        });
        const indicator = wrapper.find('[data-slot="progress-bar-indicator"]');
        expect(indicator.attributes('style')).toContain('width: 50%');
    });

    it('has data-slot attribute', () => {
        const wrapper = mount(ProgressBar, { props: { value: 50 } });
        expect(wrapper.attributes('data-slot')).toBe('progress-bar');
    });
});
