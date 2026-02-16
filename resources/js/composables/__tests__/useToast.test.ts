import { describe, expect, it, beforeEach } from 'vitest';
import { useToast } from '../useToast';

describe('useToast', () => {
    let toastApi: ReturnType<typeof useToast>;

    beforeEach(() => {
        toastApi = useToast();
        toastApi.dismissAll();
    });

    it('starts with empty toast list', () => {
        expect(toastApi.toasts.value).toHaveLength(0);
    });

    it('toast adds a toast to the list', () => {
        toastApi.toast({ title: 'Test toast' });
        expect(toastApi.toasts.value).toHaveLength(1);
        expect(toastApi.toasts.value[0].title).toBe('Test toast');
    });

    it('toast accepts string shorthand', () => {
        toastApi.toast('Quick message');
        expect(toastApi.toasts.value).toHaveLength(1);
        expect(toastApi.toasts.value[0].title).toBe('Quick message');
        expect(toastApi.toasts.value[0].variant).toBe('info');
    });

    it('success creates success toast', () => {
        toastApi.success('Operation completed');
        expect(toastApi.toasts.value[0].variant).toBe('success');
        expect(toastApi.toasts.value[0].duration).toBe(5000);
    });

    it('error creates error toast with no auto-dismiss', () => {
        toastApi.error('Something failed');
        expect(toastApi.toasts.value[0].variant).toBe('error');
        expect(toastApi.toasts.value[0].duration).toBe(0);
    });

    it('warning creates warning toast', () => {
        toastApi.warning('Be careful');
        expect(toastApi.toasts.value[0].variant).toBe('warning');
        expect(toastApi.toasts.value[0].duration).toBe(5000);
    });

    it('info creates info toast', () => {
        toastApi.info('FYI');
        expect(toastApi.toasts.value[0].variant).toBe('info');
    });

    it('dismiss removes specific toast by id', () => {
        const id = toastApi.toast('First');
        toastApi.toast('Second');
        toastApi.dismiss(id);
        expect(toastApi.toasts.value).toHaveLength(1);
        expect(toastApi.toasts.value[0].title).toBe('Second');
    });

    it('dismissAll removes all toasts', () => {
        toastApi.toast('A');
        toastApi.toast('B');
        toastApi.toast('C');
        toastApi.dismissAll();
        expect(toastApi.toasts.value).toHaveLength(0);
    });

    it('success includes description', () => {
        toastApi.success('Done', 'Files saved successfully.');
        expect(toastApi.toasts.value[0].description).toBe(
            'Files saved successfully.',
        );
    });

    it('error with variant sets duration to 0', () => {
        toastApi.toast({ title: 'Error', variant: 'error' });
        expect(toastApi.toasts.value[0].duration).toBe(0);
    });

    it('generates unique ids', () => {
        const id1 = toastApi.toast('First');
        const id2 = toastApi.toast('Second');
        expect(id1).not.toBe(id2);
    });
});
