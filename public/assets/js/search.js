/*
 * Search as you type. Progressive enhancement: without JavaScript the form is
 * a plain GET to /?q=… and every result is a normal link. With JavaScript the
 * page updates in place via /api/v1/…, keeps the focus in the field and
 * supports the keyboard (arrows, Enter, Esc).
 *
 * All data goes into the page via textContent, never innerHTML (S-19).
 * The markup mirrors templates/partials/*.php – keep both in step.
 */
(() => {
    'use strict';

    const form = document.querySelector('[data-search-form]');
    const results = document.querySelector('[data-search-results]');
    if (!form || !results) {
        return;
    }

    const input = form.querySelector('input[name="q"]');
    const list = form.querySelector('[role="listbox"]');
    const chips = document.querySelector('[data-search-chips]');
    const SUGGEST_LIMIT = 8;
    const DEBOUNCE_MS = 120;

    let suggestions = [];
    let active = -1;
    let debounce = null;
    let pending = null;

    // ---------- API ----------

    const api = async (path) => {
        if (pending) {
            pending.abort();
        }
        pending = new AbortController();
        const response = await fetch(path, {
            headers: { Accept: 'application/json' },
            signal: pending.signal,
            credentials: 'same-origin',
        });
        if (!response.ok) {
            const body = await response.json().catch(() => null);
            throw new Error(body?.error?.message ?? 'The search is not available right now.');
        }
        return response.json();
    };

    const searchUrl = (q, limit) => `/api/v1/search?q=${encodeURIComponent(q)}&limit=${limit}`;

    // ---------- building blocks ----------

    const el = (tag, className, text) => {
        const node = document.createElement(tag);
        if (className) {
            node.className = className;
        }
        if (text !== undefined) {
            node.textContent = text;
        }
        return node;
    };

    const codeLine = (v) => [v.type_code, v.series].filter(Boolean).join(' · ');

    const badge = (drivetrain) => el('span', `badge-drivetrain badge-${drivetrain.toLowerCase()}`, drivetrain);

    const show = (node) => {
        results.replaceChildren(...(node ? [node] : []));
        if (chips) {
            chips.hidden = true;
        }
    };

    // ---------- results ----------

    const vehicleCard = (v) => {
        const card = el('article', 'result-card');
        card.dataset.vehicleId = v.id;
        card.append(el('h2', 'result-title', v.name));

        const meta = el('p', 'result-meta');
        meta.append(badge(v.drivetrain), el('span', '', codeLine(v)));
        card.append(meta);

        const box = el('div', 'disclaimer');
        const text = el('p', 'disclaimer-text', v.disclaimer);
        text.id = `disclaimer-${v.id}`;
        const button = el('button', 'btn btn-primary copy-button');
        button.type = 'button';
        button.dataset.copy = text.id;
        const icon = el('i', 'bi bi-copy');
        icon.setAttribute('aria-hidden', 'true');
        const label = el('span', '', 'Copy');
        label.dataset.copyLabel = '';
        button.append(icon, label);
        box.append(text, button);
        card.append(box);
        return card;
    };

    const resultList = (items) => {
        const section = el('section', 'result-list');
        section.append(el('h2', 'result-list-title', `${items.length} matches – choose a vehicle`));
        const ul = el('ul');
        items.forEach((v) => {
            const a = el('a');
            a.href = v.url;
            a.dataset.vehicleLink = v.id;
            const meta = el('span', 'result-list-meta');
            meta.append(badge(v.drivetrain), document.createTextNode(` ${codeLine(v)}`));
            a.append(el('span', 'result-list-name', v.name), meta);
            const li = el('li');
            li.append(a);
            ul.append(li);
        });
        section.append(ul);
        return section;
    };

    const noMatch = (q) => {
        const box = el('div', 'result-empty');
        box.append(
            el('p', 'result-empty-title', `No match for “${q}”.`),
            el('p', '', 'Check the spelling or search by type code or series. Model missing? Add it in the dashboard.'),
        );
        return box;
    };

    const problem = (message) => {
        const box = el('div', 'result-empty');
        box.append(el('p', 'result-empty-title', 'That search did not work.'), el('p', '', message));
        return box;
    };

    const showVehicle = (v, push = true) => {
        show(vehicleCard(v));
        document.title = `${v.name} · Disclaimer Tool`;
        if (push && location.pathname !== v.url) {
            history.pushState({ vehicle: v }, '', v.url);
        }
    };

    // ---------- suggestions (listbox) ----------

    const closeList = () => {
        list.hidden = true;
        list.replaceChildren();
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
        suggestions = [];
        active = -1;
    };

    const setActive = (index) => {
        const options = list.querySelectorAll('[role="option"]');
        options.forEach((option, i) => option.setAttribute('aria-selected', i === index ? 'true' : 'false'));
        active = index;
        if (index >= 0 && options[index]) {
            input.setAttribute('aria-activedescendant', options[index].id);
            options[index].scrollIntoView({ block: 'nearest' });
        } else {
            input.removeAttribute('aria-activedescendant');
        }
    };

    const openList = (items) => {
        suggestions = items;
        active = -1;
        if (items.length === 0) {
            closeList();
            return;
        }
        list.replaceChildren(...items.map((v, i) => {
            const li = el('li', 'search-suggestion');
            li.id = `search-option-${i}`;
            li.setAttribute('role', 'option');
            li.setAttribute('aria-selected', 'false');
            const meta = el('span', 'search-suggestion-meta');
            meta.append(badge(v.drivetrain), document.createTextNode(` ${codeLine(v)}`));
            li.append(el('span', 'search-suggestion-name', v.name), meta);
            // mousedown instead of click: fires before the input loses focus.
            li.addEventListener('mousedown', (event) => {
                event.preventDefault();
                choose(i);
            });
            return li;
        }));
        list.hidden = false;
        input.setAttribute('aria-expanded', 'true');
    };

    const choose = (index) => {
        const v = suggestions[index];
        if (!v) {
            return;
        }
        input.value = v.name;
        closeList();
        showVehicle(v);
        input.focus();
    };

    const suggest = async () => {
        const q = input.value.trim();
        if (q === '') {
            closeList();
            return;
        }
        try {
            const body = await api(searchUrl(q, SUGGEST_LIMIT));
            if (input.value.trim() === q) {
                openList(body.data);
            }
        } catch (error) {
            if (error.name !== 'AbortError') {
                closeList();
            }
        }
    };

    // ---------- full search (Enter / submit) ----------

    const submit = async () => {
        const q = input.value.trim();
        closeList();
        if (q === '') {
            show(null);
            return;
        }
        try {
            const body = await api(searchUrl(q, 20));
            if (body.data.length === 1) {
                showVehicle(body.data[0]);
                return;
            }
            show(body.data.length === 0 ? noMatch(q) : resultList(body.data));
            history.pushState({ q }, '', `/?q=${encodeURIComponent(q)}`);
            document.title = 'Disclaimer Tool';
        } catch (error) {
            if (error.name !== 'AbortError') {
                show(problem(error.message));
            }
        }
    };

    // ---------- events ----------

    input.addEventListener('input', () => {
        clearTimeout(debounce);
        debounce = setTimeout(suggest, DEBOUNCE_MS);
    });

    input.addEventListener('keydown', (event) => {
        const open = !list.hidden && suggestions.length > 0;
        switch (event.key) {
            case 'ArrowDown':
                if (open) {
                    event.preventDefault();
                    setActive((active + 1) % suggestions.length);
                }
                break;
            case 'ArrowUp':
                if (open) {
                    event.preventDefault();
                    setActive(active <= 0 ? suggestions.length - 1 : active - 1);
                }
                break;
            case 'Enter':
                if (open && active >= 0) {
                    event.preventDefault();
                    choose(active);
                }
                break;
            case 'Escape':
                if (open) {
                    event.preventDefault();
                    closeList();
                } else if (input.value !== '') {
                    event.preventDefault();
                    input.value = '';
                }
                break;
            default:
                break;
        }
    });

    input.addEventListener('blur', () => setTimeout(closeList, 100));

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        submit();
    });

    // Links in the result list and the "Try:" chips stay on the page.
    document.addEventListener('click', async (event) => {
        const chip = event.target.closest('[data-search-chip]');
        if (chip) {
            event.preventDefault();
            input.value = chip.dataset.searchChip;
            input.focus();
            submit();
            return;
        }

        const link = event.target.closest('[data-vehicle-link]');
        if (link && !event.metaKey && !event.ctrlKey && !event.shiftKey) {
            event.preventDefault();
            try {
                const body = await api(`/api/v1/vehicles/${encodeURIComponent(link.dataset.vehicleLink)}`);
                showVehicle(body.data);
                input.focus();
            } catch (error) {
                if (error.name !== 'AbortError') {
                    location.href = link.href;
                }
            }
            return;
        }

        const copy = event.target.closest('[data-copy]');
        if (copy) {
            copyText(copy);
        }
    });

    // Back and forward re-render what the URL says.
    window.addEventListener('popstate', (event) => {
        if (event.state?.vehicle) {
            showVehicle(event.state.vehicle, false);
        } else if (event.state?.q) {
            input.value = event.state.q;
            submit();
        } else {
            location.reload();
        }
    });

    // ---------- copy ----------

    const copyText = async (button) => {
        const source = document.getElementById(button.dataset.copy);
        const label = button.querySelector('[data-copy-label]');
        if (!source || !label) {
            return;
        }
        let ok = false;
        try {
            await navigator.clipboard.writeText(source.textContent);
            ok = true;
        } catch {
            // Clipboard API blocked: select the text so Cmd/Ctrl+C works.
            const range = document.createRange();
            range.selectNodeContents(source);
            const selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(range);
        }
        label.textContent = ok ? 'Copied' : 'Text selected';
        button.classList.toggle('is-copied', ok);
        clearTimeout(button.copyTimer);
        button.copyTimer = setTimeout(() => {
            label.textContent = 'Copy';
            button.classList.remove('is-copied');
        }, 2000);
    };

    // The copy buttons rendered by the server are hidden until JavaScript runs.
    const reveal = () => document.querySelectorAll('[data-copy][hidden]').forEach((b) => { b.hidden = false; });
    reveal();
    new MutationObserver(reveal).observe(results, { childList: true, subtree: true });

    // Keep the current state for the back button.
    history.replaceState(history.state ?? { initial: true }, '');
})();
