import { Injectable, signal } from '@angular/core';

export interface Theme {
  id:    string;
  label: string;
  color: string; // swatch color shown in the picker
  dark:  boolean;
}

export const THEMES: Theme[] = [
  { id: 'dark',     label: 'Dark',          color: '#161b22', dark: true  },
  { id: 'light',    label: 'Light',         color: '#ffffff', dark: false },
  { id: 'ocean',    label: 'Ocean',         color: '#06b6d4', dark: true  },
  { id: 'sunset',   label: 'Sunset',        color: '#f97316', dark: true  },
  { id: 'emerald',  label: 'Emerald',       color: '#10b981', dark: true  },
  { id: 'contrast', label: 'High Contrast', color: '#facc15', dark: true  },
];

const STORAGE_KEY = 'hrms_theme';
const DEFAULT     = 'dark';

@Injectable({ providedIn: 'root' })
export class ThemeService {

  readonly themes = THEMES;

  /** Reactive current theme id — templates can bind to this signal */
  readonly current = signal<string>(this.stored());

  constructor() {
    this.apply(this.current());
  }

  /** Switch to a theme and persist it */
  set(id: string): void {
    if (!THEMES.find(t => t.id === id)) return;
    this.current.set(id);
    localStorage.setItem(STORAGE_KEY, id);
    this.apply(id);
  }

  /** Returns whether current theme is light */
  get isLight(): boolean {
    return !THEMES.find(t => t.id === this.current())?.dark;
  }

  // ── Private ────────────────────────────────────────────────────────

  private stored(): string {
    return localStorage.getItem(STORAGE_KEY) || DEFAULT;
  }

  private apply(id: string): void {
    document.documentElement.setAttribute('data-theme', id);
  }
}
