import { Injectable, signal } from '@angular/core';

export interface Theme {
  id:    string;
  label: string;
  color: string;
}

export const THEMES: Theme[] = [
  { id: 'dark',     label: 'Dark',           color: '#1e293b' },
  { id: 'light',    label: 'Light',          color: '#e2e8f0' },
  { id: 'ocean',    label: 'Ocean',          color: '#06b6d4' },
  { id: 'sunset',   label: 'Sunset',         color: '#f97316' },
  { id: 'emerald',  label: 'Emerald',        color: '#10b981' },
  { id: 'contrast', label: 'High Contrast',  color: '#facc15' },
];

const STORAGE_KEY = 'hrms_theme';

@Injectable({ providedIn: 'root' })
export class ThemeService {
  readonly themes = THEMES;
  readonly current = signal<string>(this.stored());

  constructor() { this.apply(this.current()); }

  set(id: string): void {
    if (!THEMES.find(t => t.id === id)) return;
    this.current.set(id);
    localStorage.setItem(STORAGE_KEY, id);
    this.apply(id);
  }

  cycle(): void {
    const ids  = THEMES.map(t => t.id);
    const next = ids[(ids.indexOf(this.current()) + 1) % ids.length];
    this.set(next);
  }

  get currentTheme(): Theme {
    return THEMES.find(t => t.id === this.current()) ?? THEMES[0];
  }

  private stored(): string {
    return localStorage.getItem(STORAGE_KEY) || 'dark';
  }

  private apply(id: string): void {
    document.documentElement.setAttribute('data-theme', id);
  }
}
