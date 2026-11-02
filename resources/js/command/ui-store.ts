import { create } from 'zustand';

/** Which shell overlay is open. One store so hotkeys, the palette and the top bar agree. */
type Overlay =
    | 'palette'
    | 'shortcuts'
    | 'theme'
    | 'branch'
    | 'notifications'
    | null;

type UiStore = {
    overlay: Overlay;
    open: (overlay: Exclude<Overlay, null>) => void;
    close: () => void;
    toggle: (overlay: Exclude<Overlay, null>) => void;
};

export const useUiStore = create<UiStore>((set, get) => ({
    overlay: null,
    open: (overlay) => set({ overlay }),
    close: () => set({ overlay: null }),
    toggle: (overlay) =>
        set({ overlay: get().overlay === overlay ? null : overlay }),
}));
