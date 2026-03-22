import { ref } from 'vue';

/**
 * Accumulate streamed text chunks into a single reactive string.
 */
export function useStreamingText() {
    const text = ref('');

    function append(chunk) {
        text.value += chunk;
    }

    function reset() {
        text.value = '';
    }

    return { text, append, reset };
}
