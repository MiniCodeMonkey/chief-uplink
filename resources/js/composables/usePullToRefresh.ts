import { router } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref, type Ref } from 'vue';

interface PullToRefreshOptions {
    /** Element ref that is the scroll container (or null for window) */
    containerRef?: Ref<HTMLElement | null>;
    /** Minimum pull distance in px before triggering refresh */
    threshold?: number;
    /** Callback after refresh completes */
    onRefresh?: () => void;
}

export function usePullToRefresh(options: PullToRefreshOptions = {}) {
    const { threshold = 80, onRefresh } = options;

    const isRefreshing = ref(false);
    const pullDistance = ref(0);
    const isPulling = ref(false);

    let startY = 0;
    let currentY = 0;

    function isAtTop(): boolean {
        if (options.containerRef?.value) {
            return options.containerRef.value.scrollTop <= 0;
        }
        return window.scrollY <= 0;
    }

    function onTouchStart(e: TouchEvent) {
        if (isRefreshing.value) return;
        if (!isAtTop()) return;

        startY = e.touches[0].clientY;
        isPulling.value = true;
    }

    function onTouchMove(e: TouchEvent) {
        if (!isPulling.value || isRefreshing.value) return;
        if (!isAtTop()) {
            isPulling.value = false;
            pullDistance.value = 0;
            return;
        }

        currentY = e.touches[0].clientY;
        const distance = currentY - startY;

        if (distance > 0) {
            // Apply resistance — diminishing returns as you pull further
            pullDistance.value = Math.min(distance * 0.4, threshold * 1.5);
        }
    }

    function onTouchEnd() {
        if (!isPulling.value || isRefreshing.value) return;

        if (pullDistance.value >= threshold) {
            isRefreshing.value = true;
            pullDistance.value = threshold * 0.5; // Snap to a smaller position while refreshing

            router.reload({
                onFinish: () => {
                    isRefreshing.value = false;
                    pullDistance.value = 0;
                    onRefresh?.();
                },
            });
        } else {
            pullDistance.value = 0;
        }

        isPulling.value = false;
    }

    onMounted(() => {
        const target = options.containerRef?.value ?? document;
        target.addEventListener('touchstart', onTouchStart as EventListener, {
            passive: true,
        });
        target.addEventListener('touchmove', onTouchMove as EventListener, {
            passive: true,
        });
        target.addEventListener('touchend', onTouchEnd as EventListener, {
            passive: true,
        });
    });

    onUnmounted(() => {
        const target = options.containerRef?.value ?? document;
        target.removeEventListener(
            'touchstart',
            onTouchStart as EventListener,
        );
        target.removeEventListener(
            'touchmove',
            onTouchMove as EventListener,
        );
        target.removeEventListener('touchend', onTouchEnd as EventListener);
    });

    return {
        isRefreshing,
        pullDistance,
        isPulling,
    };
}
