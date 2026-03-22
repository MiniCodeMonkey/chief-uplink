import { describe, it, expect } from 'vitest';
import { useStreamingText } from './useStreamingText';

describe('useStreamingText', () => {
    it('starts with empty text', () => {
        const { text } = useStreamingText();
        expect(text.value).toBe('');
    });

    it('appends chunks to text', () => {
        const { text, append } = useStreamingText();
        append('Hello');
        append(' World');
        expect(text.value).toBe('Hello World');
    });

    it('resets text to empty', () => {
        const { text, append, reset } = useStreamingText();
        append('Some text');
        reset();
        expect(text.value).toBe('');
    });

    it('can append after reset', () => {
        const { text, append, reset } = useStreamingText();
        append('First');
        reset();
        append('Second');
        expect(text.value).toBe('Second');
    });
});
