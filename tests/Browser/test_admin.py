"""Browser tests for the admin vehicle sidebar (Q-3): public/assets/js/admin-vehicles.js.

Expects the app at BASE_URL with the sample vehicles and an admin ADMIN_USER /
ADMIN_PASSWORD, and a headless Chrome on DEBUG_PORT (see test_search.py).

Run: python3 -I tests/Browser/test_admin.py
"""

import os
import sys
import traceback

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from cdp import Browser  # noqa: E402

BASE_URL = os.environ.get('BASE_URL', 'http://127.0.0.1:8080')
DEBUG_PORT = os.environ.get('DEBUG_PORT', '9222')
ADMIN_USER = os.environ.get('ADMIN_USER', 'ci-admin')
ADMIN_PASSWORD = os.environ.get('ADMIN_PASSWORD', '')

PREVIEW = 'document.querySelector("[data-preview]").textContent'


def signed_in(path):
    b = Browser(DEBUG_PORT, BASE_URL + '/login?next=' + path)
    # The browser keeps the session between tests; then /login forwards at once.
    if b.js(f'location.pathname === {path!r}'):
        return b
    b.js(f'document.getElementById("login-username").value = {ADMIN_USER!r}')
    b.js(f'document.getElementById("login-password").value = {ADMIN_PASSWORD!r}')
    b.js('document.querySelector("form[action=\\"/login\\"]").submit()')
    b.wait(f'location.pathname === {path!r} && document.readyState === "complete"', timeout=10)
    return b


def set_value(b, selector, value):
    """Types like a person: focus, replace the value, fire an input event."""
    b.js(f'(() => {{ const el = document.querySelector({selector!r}); el.focus(); el.value = ""; }})()')
    b.type(value)


def test_new_vehicle_fields_follow_type_and_preview_updates():
    b = signed_in('/admin/vehicles/new')
    assert b.js('!document.querySelector("[data-used-by] #v-fuel_consumption").closest("[data-used-by]").hidden'), \
        'before a type is chosen all fields are visible'

    b.js('(() => { const s = document.getElementById("v-drivetrain"); s.value = "BEV"; s.dispatchEvent(new Event("change")); })()')
    assert b.js('document.getElementById("v-fuel_consumption").closest("[data-used-by]").hidden'), 'BEV hides fuel'
    assert b.js('!document.getElementById("v-electric_range").closest("[data-used-by]").hidden'), 'BEV shows range'

    set_value(b, '#v-name', 'Browser Test BEV')
    set_value(b, '#v-electric_consumption', '15,5 / 14,0')
    set_value(b, '#v-co2_class', 'a')
    set_value(b, '#v-electric_range', '400.0 / 420.0')
    text = b.wait(f'{PREVIEW}.startsWith("Browser Test BEV.") && {PREVIEW}', timeout=5)
    assert '15.5-14.0 kWh/100 km' in text, text
    assert 'WLTP electric range: 420-400 km.' in text, text


def test_preview_names_the_problem():
    b = signed_in('/admin/vehicles/new')
    b.js('(() => { const s = document.getElementById("v-drivetrain"); s.value = "ICE"; s.dispatchEvent(new Event("change")); })()')
    set_value(b, '#v-name', 'Broken')
    set_value(b, '#v-fuel_consumption', 'N/A')
    hint = b.wait(f'{PREVIEW}.includes("Expected a number") && {PREVIEW}', timeout=5)
    assert hint.startswith('Fuel consumption'), hint


def test_editing_a_live_vehicle_previews_the_new_name():
    b = signed_in('/admin/vehicles?q=Z0007')
    b.js('document.querySelector(".vehicle-name a").click()')
    b.wait('document.querySelector("[data-vehicle-form]") && document.readyState === "complete"', timeout=10)
    set_value(b, '#v-name', 'Renamed i5')
    b.wait(f'{PREVIEW}.startsWith("Renamed i5. WLTP energy consumption combined")', timeout=5)


def main():
    if not ADMIN_PASSWORD:
        print('ADMIN_PASSWORD is not set')
        return 1
    tests = [(name, fn) for name, fn in globals().items() if name.startswith('test_') and callable(fn)]
    failed = 0
    for name, fn in tests:
        try:
            fn()
            print(f'PASS {name}')
        except Exception:  # noqa: BLE001
            failed += 1
            print(f'FAIL {name}')
            traceback.print_exc()
    print(f'\n{len(tests) - failed} passed, {failed} failed')
    return 1 if failed else 0


if __name__ == '__main__':
    sys.exit(main())
