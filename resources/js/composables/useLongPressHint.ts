import { computed, ref } from 'vue';

const STORAGE_KEY = 'chief:longpress-hint';
const MAX_HINT_VISITS = 3;

/**
 * Tracks whether to show the long-press discoverability hint on project cards.
 * Shows for the first 3 visits, then hides once the user has discovered the feature.
 */
export function useLongPressHint() {
    const visitCount = ref(getVisitCount());

    function getVisitCount(): number {
        try {
            const stored = localStorage.getItem(STORAGE_KEY);
            if (stored === null) return 0;
            const parsed = parseInt(stored, 10);
            return isNaN(parsed) ? 0 : parsed;
        } catch {
            return 0;
        }
    }

    function incrementVisitCount() {
        try {
            const count = visitCount.value + 1;
            localStorage.setItem(STORAGE_KEY, String(count));
            visitCount.value = count;
        } catch {
            // localStorage unavailable
        }
    }

    function markFeatureUsed() {
        try {
            localStorage.setItem(STORAGE_KEY, String(MAX_HINT_VISITS + 1));
            visitCount.value = MAX_HINT_VISITS + 1;
        } catch {
            // localStorage unavailable
        }
    }

    const showHint = computed(() => visitCount.value < MAX_HINT_VISITS);

    return {
        showHint,
        incrementVisitCount,
        markFeatureUsed,
    };
}
