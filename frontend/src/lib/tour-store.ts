import { create } from 'zustand';

const STORAGE_KEY = 'cvb-tour-completed';

function getStoredTourState(): boolean {
  if (typeof window === 'undefined') return false;
  try {
    return localStorage.getItem(STORAGE_KEY) === 'true';
  } catch {
    return false;
  }
}

function setStoredTourState(completed: boolean): void {
  if (typeof window === 'undefined') return;
  try {
    localStorage.setItem(STORAGE_KEY, completed ? 'true' : 'false');
  } catch {
    // localStorage not available
  }
}

interface TourState {
  run: boolean;
  tourComplete: boolean;
  stepIndex: number;
  startTour: () => void;
  stopTour: () => void;
  completeTour: () => void;
  resetTour: () => void;
  setStepIndex: (index: number) => void;
}

export const useTourStore = create<TourState>((set, get) => ({
  run: false,
  tourComplete: getStoredTourState(),
  stepIndex: 0,

  startTour: () => {
    set({ run: true, stepIndex: 0 });
  },

  stopTour: () => {
    set({ run: false });
  },

  completeTour: () => {
    set({ run: false, tourComplete: true });
    setStoredTourState(true);
  },

  resetTour: () => {
    set({ run: false, tourComplete: false, stepIndex: 0 });
    setStoredTourState(false);
  },

  setStepIndex: (index: number) => {
    set({ stepIndex: index });
  },
}));
