import { reactive } from 'vue';

const STORAGE_KEY = 'ui.focus_mode';

const readPersisted = () => {
    try {
        return window.localStorage.getItem(STORAGE_KEY) === '1';
    } catch {
        return false;
    }
};

/**
 * Layout-wide preferences that must survive Inertia navigations and reloads.
 */
export const ui = reactive({
    focusMode: readPersisted(),
});

export function toggleFocusMode() {
    ui.focusMode = !ui.focusMode;

    try {
        window.localStorage.setItem(STORAGE_KEY, ui.focusMode ? '1' : '0');
    } catch {
        /* modo privado: o estado vale só para a aba atual */
    }
}
