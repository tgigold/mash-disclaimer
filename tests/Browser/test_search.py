"""Browser tests for the public search (Q-3): public/assets/js/search.js and theme.js.

Expects the app at BASE_URL with the sample vehicles loaded and a headless
Chrome listening on DEBUG_PORT. The CI sets both up (.github/workflows/ci.yml);
locally see README, "Browser-Tests".

Run: python3 -I tests/Browser/test_search.py
"""

import os
import sys
import traceback

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from cdp import Browser  # noqa: E402

BASE_URL = os.environ.get('BASE_URL', 'http://127.0.0.1:8080')
DEBUG_PORT = os.environ.get('DEBUG_PORT', '9222')

FIELD = 'document.getElementById("search-q")'
OPTIONS = '[...document.querySelectorAll("#search-suggestions [role=option]")]'


def open_page(path='/'):
    return Browser(DEBUG_PORT, BASE_URL + path)


def test_suggestions_keyboard_and_vehicle():
    b = open_page()
    assert b.js('document.activeElement.id') == 'search-q', 'focus starts in the field'

    b.type('sed')
    names = b.wait(f'{OPTIONS}.length === 4 && {OPTIONS}.map(o => o.querySelector(".search-suggestion-name").textContent)')
    assert b.js(f'{FIELD}.getAttribute("aria-expanded")') == 'true'

    b.press('ArrowDown')
    b.press('ArrowDown')
    assert b.js(f'{FIELD}.getAttribute("aria-activedescendant")') == 'search-option-1'
    b.press('ArrowUp')
    assert b.js(f'{FIELD}.getAttribute("aria-activedescendant")') == 'search-option-0'
    b.press('ArrowDown')

    b.press('Enter')
    title = b.wait('document.querySelector(".result-title")?.textContent')
    assert title == names[1], (title, names)
    assert b.js('location.pathname').startswith('/vehicles/')
    assert b.js('document.activeElement.id') == 'search-q', 'focus stays in the field'
    assert b.js('document.getElementById("search-suggestions").hidden') is True
    assert b.js('!document.querySelector("[data-copy]").hidden'), 'copy button is visible'


def test_escape_closes_then_clears():
    b = open_page()
    b.type('bmw')
    b.wait(f'{OPTIONS}.length > 0')
    b.press('Escape')
    assert b.js('document.getElementById("search-suggestions").hidden') is True
    assert b.js(f'{FIELD}.value') == 'bmw', 'first Escape only closes the list'
    b.press('Escape')
    assert b.js(f'{FIELD}.value') == '', 'second Escape clears the field'


def test_submit_list_no_match_and_back():
    b = open_page()
    b.type('sedan')
    b.wait(f'{OPTIONS}.length > 0')
    b.press('Escape')
    b.press('Enter')
    assert b.wait('document.querySelector(".result-list-title")?.textContent') == '4 matches – choose a vehicle'
    assert b.js('location.pathname + location.search') == '/?q=sedan'

    b.js(f'{FIELD}.value = ""')
    b.type('i8 Roadster')
    b.press('Enter')
    assert b.wait('document.querySelector(".result-empty-title")?.textContent') == 'No match for “i8 Roadster”.'

    b.js('history.back()')
    assert b.wait('document.querySelector(".result-list-title")?.textContent') == '4 matches – choose a vehicle'

    b.js('document.querySelector("[data-vehicle-link]").click()')
    b.wait('document.querySelector(".result-card")')
    assert b.js('performance.getEntriesByType("navigation").length') == 1, 'no page reload'


def test_copy_puts_disclaimer_on_clipboard():
    b = open_page('/?q=x5')
    origin = b.js('location.origin')
    b.call('Browser.grantPermissions', {
        'permissions': ['clipboardReadWrite', 'clipboardSanitizedWrite'],
        'origin': origin,
    })
    expected = b.js('document.querySelector(".disclaimer-text").textContent')
    b.js('document.querySelector("[data-copy]").click()')
    assert b.wait('document.querySelector("[data-copy-label]").textContent === "Copied"')
    assert b.js('navigator.clipboard.readText()') == expected


def test_chips_search_in_place():
    b = open_page()
    chip = b.js('document.querySelector("[data-search-chip]")?.dataset.searchChip')
    assert chip, 'the start page suggests series'
    b.js('document.querySelector("[data-search-chip]").click()')
    b.wait('document.querySelector(".result-list, .result-card")')
    assert b.js(f'{FIELD}.value') == chip


def test_markup_in_query_stays_text():
    b = open_page()
    b.type('<img src=x onerror=window.__pwned=1>')
    b.press('Enter')
    b.wait('document.querySelector(".result-empty-title")')
    assert b.js('window.__pwned === undefined && !document.querySelector(".result-empty img")')


def test_theme_toggle_remembers_choice():
    b = open_page()
    before = b.js('document.documentElement.dataset.bsTheme')
    b.js('document.querySelector("[data-theme-toggle]").click()')
    after = b.js('document.documentElement.dataset.bsTheme')
    assert {before, after} == {'light', 'dark'}
    assert b.js('localStorage.getItem("tater-theme")') == after
    b.js('location.reload()')
    b.wait('document.readyState === "complete" && document.querySelector("[data-theme-toggle]")')
    assert b.js('document.documentElement.dataset.bsTheme') == after, 'choice survives a reload'
    b.js('localStorage.removeItem("tater-theme")')


def main():
    tests = [(name, fn) for name, fn in globals().items() if name.startswith('test_') and callable(fn)]
    failed = 0
    for name, fn in tests:
        try:
            fn()
            print(f'PASS {name}')
        except Exception:  # noqa: BLE001 – report every failure, then exit non-zero
            failed += 1
            print(f'FAIL {name}')
            traceback.print_exc()
    print(f'\n{len(tests) - failed} passed, {failed} failed')
    return 1 if failed else 0


if __name__ == '__main__':
    sys.exit(main())
