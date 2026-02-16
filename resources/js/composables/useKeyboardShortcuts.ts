import { onMounted, onUnmounted, type Ref } from 'vue';

/**
 * Checks if an element is an input-like element where regular shortcuts should be suppressed.
 */
export function isInputFocused(): boolean {
    const el = document.activeElement;
    if (!el) return false;
    const tag = el.tagName;
    if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return true;
    if ((el as HTMLElement).isContentEditable) return true;
    return false;
}

/**
 * Detects if the current platform is macOS.
 */
export function isMacPlatform(): boolean {
    return navigator.platform.toUpperCase().includes('MAC');
}

/**
 * Checks if the modifier key (Cmd on Mac, Ctrl on Windows/Linux) is pressed.
 */
export function isModKey(e: KeyboardEvent): boolean {
    return isMacPlatform() ? e.metaKey : e.ctrlKey;
}

interface KeyboardShortcutOptions {
    showCommandPalette: Ref<boolean>;
    showShortcutsOverlay: Ref<boolean>;
}

/**
 * Global keyboard shortcuts composable.
 *
 * Registers app-wide keyboard shortcuts:
 * - Cmd/Ctrl+K: Toggle command palette (active even in input fields)
 * - ?: Toggle keyboard shortcuts help overlay (disabled in input fields)
 * - Escape: Close command palette or shortcuts overlay
 */
export function useKeyboardShortcuts(options: KeyboardShortcutOptions) {
    const { showCommandPalette, showShortcutsOverlay } = options;

    function handleKeydown(e: KeyboardEvent) {
        // Cmd/Ctrl+K: Toggle command palette (always active, even in input fields)
        if (e.key === 'k' && isModKey(e)) {
            e.preventDefault();
            showCommandPalette.value = !showCommandPalette.value;
            return;
        }

        // Escape: Close command palette or shortcuts overlay (always active)
        if (e.key === 'Escape') {
            if (showCommandPalette.value) {
                showCommandPalette.value = false;
                e.preventDefault();
                return;
            }
            if (showShortcutsOverlay.value) {
                showShortcutsOverlay.value = false;
                e.preventDefault();
                return;
            }
            return;
        }

        // All remaining shortcuts are disabled when input is focused
        if (isInputFocused()) return;

        // ?: Toggle keyboard shortcuts help overlay
        if (e.key === '?' && !e.metaKey && !e.ctrlKey && !e.altKey) {
            e.preventDefault();
            showShortcutsOverlay.value = !showShortcutsOverlay.value;
            return;
        }
    }

    onMounted(() => {
        document.addEventListener('keydown', handleKeydown);
    });

    onUnmounted(() => {
        document.removeEventListener('keydown', handleKeydown);
    });
}
