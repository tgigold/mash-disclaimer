/*
 * Colour theme: follows the system unless the visitor picked one; the choice
 * is kept in localStorage. Loaded in <head> without defer so the right theme
 * is set before the first paint.
 */
(() => {
    'use strict';

    const KEY = 'tater-theme';
    const root = document.documentElement;
    const media = window.matchMedia('(prefers-color-scheme: dark)');

    const stored = () => {
        try {
            const value = localStorage.getItem(KEY);
            return value === 'light' || value === 'dark' ? value : null;
        } catch {
            return null;
        }
    };

    const store = (value) => {
        try {
            localStorage.setItem(KEY, value);
        } catch {
            // Private mode or blocked storage: the choice lasts for this page only.
        }
    };

    const apply = (theme) => {
        root.setAttribute('data-bs-theme', theme);
        document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
            button.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');
            button.setAttribute('aria-label', theme === 'dark' ? 'Switch to light theme' : 'Switch to dark theme');
        });
    };

    const current = () => stored() ?? (media.matches ? 'dark' : 'light');

    apply(current());

    media.addEventListener('change', () => {
        if (stored() === null) {
            apply(current());
        }
    });

    document.addEventListener('DOMContentLoaded', () => {
        apply(current());
        document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
            button.addEventListener('click', () => {
                const next = root.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
                store(next);
                apply(next);
            });
        });
    });
})();
