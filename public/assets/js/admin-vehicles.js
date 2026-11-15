/*
 * Admin vehicle page: live preview of the disclaimer while typing, value fields
 * that follow the chosen type, and filters that apply on change. Import
 * review: a box in the column head ticks or clears all boxes of the column. Without
 * JavaScript everything still works with the normal forms; the preview then
 * updates on save.
 */
(() => {
    'use strict';

    // Filters: apply selects at once.
    document.querySelectorAll('form[data-autosubmit] select').forEach((select) => {
        select.addEventListener('change', () => select.form.requestSubmit());
    });

    // "Select all" boxes: hidden without JavaScript, they follow the column.
    document.querySelectorAll('input[data-check-all]').forEach((all) => {
        const boxes = [...document.querySelectorAll(`input[type="checkbox"][name="${CSS.escape(all.dataset.checkAll)}"]`)];
        const sync = () => {
            const ticked = boxes.filter((box) => box.checked).length;
            all.checked = ticked > 0 && ticked === boxes.length;
            all.indeterminate = ticked > 0 && ticked < boxes.length;
        };
        all.hidden = false;
        all.addEventListener('change', () => boxes.forEach((box) => { box.checked = all.checked; }));
        boxes.forEach((box) => box.addEventListener('change', sync));
        sync();
    });

    const form = document.querySelector('[data-vehicle-form]');
    if (!form) {
        return;
    }

    const preview = form.querySelector('[data-preview]');
    const drivetrain = form.querySelector('[data-drivetrain]');
    const token = form.querySelector('input[name="_csrf"]')?.value ?? '';
    let timer = null;
    let pending = null;

    // Only the value fields of the chosen type are shown (the server ignores the others).
    const syncFields = () => {
        if (!drivetrain) {
            return;
        }
        const type = drivetrain.value;
        form.querySelectorAll('[data-used-by]').forEach((row) => {
            row.hidden = type !== '' && !row.dataset.usedBy.split(' ').includes(type);
        });
        const hint = form.querySelector('[data-values-hint]');
        if (hint) {
            hint.hidden = type !== '';
        }
    };

    const showText = (text) => {
        preview.classList.remove('is-muted');
        preview.textContent = text;
    };

    const showHint = (message) => {
        preview.classList.add('is-muted');
        preview.textContent = message;
    };

    const refresh = async () => {
        if (pending) {
            pending.abort();
        }
        pending = new AbortController();
        try {
            const response = await fetch('/admin/vehicles/preview', {
                method: 'POST',
                body: new FormData(form),
                headers: { Accept: 'application/json', 'X-CSRF-Token': token },
                credentials: 'same-origin',
                signal: pending.signal,
            });
            if (!response.ok) {
                showHint('Preview not available – are you still signed in?');
                return;
            }
            const body = await response.json();
            if (body.text) {
                showText(body.text);
            } else {
                const first = Object.entries(body.errors ?? {})[0];
                showHint(first ? `${labelFor(first[0])}: ${first[1]}` : 'The text appears once name and values are complete.');
            }
        } catch (error) {
            if (error.name !== 'AbortError') {
                showHint('Preview not available right now.');
            }
        }
    };

    const labelFor = (name) => {
        const input = form.querySelector(`[name="${CSS.escape(name)}"]`);
        const label = input ? form.querySelector(`label[for="${CSS.escape(input.id)}"]`) : null;
        return label ? label.firstChild.textContent.trim() : name;
    };

    form.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(refresh, 250);
    });

    drivetrain?.addEventListener('change', () => {
        syncFields();
        refresh();
    });

    syncFields();
})();
