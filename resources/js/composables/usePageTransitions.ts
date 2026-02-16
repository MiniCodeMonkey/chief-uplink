import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

type Direction = 'forward' | 'back' | 'none';

const navigationDirection = ref<Direction>('none');
const isNavigating = ref(false);

let historyStack: string[] = [];
let initialized = false;

/**
 * Tracks navigation direction for mobile directional page transitions.
 * Forward navigation slides left, back navigation slides right.
 */
export function usePageTransitions() {
    if (!initialized) {
        initialized = true;
        historyStack = [window.location.pathname];

        // Listen for popstate (browser back/forward)
        const handlePopstate = () => {
            const currentPath = window.location.pathname;
            const prevIndex = historyStack.indexOf(currentPath);

            if (prevIndex !== -1 && prevIndex < historyStack.length - 1) {
                // Going back — path exists earlier in the stack
                navigationDirection.value = 'back';
                historyStack = historyStack.slice(0, prevIndex + 1);
            } else {
                navigationDirection.value = 'forward';
                historyStack.push(currentPath);
            }
        };

        window.addEventListener('popstate', handlePopstate);

        // Listen for Inertia navigation events
        const removeBeforeListener = router.on('before', () => {
            isNavigating.value = true;
        });

        const removeNavigateListener = router.on('navigate', (event) => {
            const newPath = new URL(event.detail.page.url, window.location.origin).pathname;
            const currentPath = historyStack[historyStack.length - 1];

            if (newPath !== currentPath) {
                // Determine if this is back navigation by checking history
                const existingIndex = historyStack.indexOf(newPath);

                if (existingIndex !== -1 && existingIndex < historyStack.length - 1) {
                    navigationDirection.value = 'back';
                    historyStack = historyStack.slice(0, existingIndex + 1);
                } else {
                    navigationDirection.value = 'forward';
                    historyStack.push(newPath);
                }
            }

            isNavigating.value = false;

            // Reset direction after transition completes
            setTimeout(() => {
                navigationDirection.value = 'none';
            }, 300);
        });

        // Cleanup on HMR
        if (import.meta.hot) {
            import.meta.hot.dispose(() => {
                window.removeEventListener('popstate', handlePopstate);
                removeBeforeListener();
                removeNavigateListener();
                initialized = false;
            });
        }
    }

    return {
        navigationDirection,
        isNavigating,
    };
}
