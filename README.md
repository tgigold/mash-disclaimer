# Disclaimer Tool V4 („Tater“)

Verwaltet die WLTP-Verbrauchs- und CO₂-Werte von Fahrzeugen der BMW Group (BMW, MINI, BMW ALPINA) und erzeugt daraus fertige Disclaimer-Texte nach der deutschen Pkw-EnVKV. Die Suche ist öffentlich: Fahrzeug eingeben, Disclaimer kopieren. Gepflegt werden die Daten in einem Admin-Bereich hinter Login, gespeist aus der XLSX-Datei des Herstellers.

V4 löst die bisherige Version 3 ab. In der Entwicklung läuft sie unter <https://tater.lab09.de>, danach unter `disclaimer.lab09.de`.

## Steckbrief

| | |
|---|---|
| **Zweck** | Verbrauchswerte von BMW-Group-Fahrzeugen verwalten und EnVKV-Disclaimer zum Kopieren bereitstellen |
| **Abhängigkeiten** | mit Composer · Laufzeit: `phpoffice/phpspreadsheet` zum Lesen der XLSX (kommt mit dem Import-Schritt) · Entwicklung: PHPUnit, PHPStan |
| **Frontend** | Bootstrap 5.3 + Bootstrap Icons, lokal · Schrift: Bricolage Grotesque (woff2, lokal, aus `@fontsource/bricolage-grotesque`) · JavaScript: ja, für Autovervollständigung, Copy, Theme-Umschalter, Admin-Liste · kein Build-Schritt |
| **Datenbank** | MySQL/MariaDB, Tabellenpräfix `v4_`, eigener Datenbanknutzer |
| **Zugang** | gemischt: Suche öffentlich, Admin hinter Login (mehrere Nutzer, eine Rolle; jeder Admin kann weitere anlegen) · API: nur intern für die Autovervollständigung, öffentlich lesend |
| **Personenbezogene Daten** | Admin-Konten (Nutzername), gekürzte IPs in Logs, volle IP nur für die Dauer der Login-Sperre |
| **Uploads** | XLSX, nur Admins, Höchstgröße 5 MB |
| **Fremde Dienste** | keine |
| **Repo** | öffentlich · Lizenz: Unlicense |
| **Server** | Shared Webspace (lab09.de) · PHP 8.5 (FPM) · Document Root zeigt auf `public/` · HTTPS |
| **Deployment** | rsync über SSH, `main` ist live (in der Entwicklung `tater.lab09.de`) |
| **Cronjobs** | keine |
| **Coding-Regeln** | 1.3.0 |

### Abweichungen von den Coding-Regeln

| Regel | Was hier stattdessen gilt | Warum |
|---|---|---|
| `S-29` | Kein Passwort-Reset per E-Mail; ein anderer Admin setzt ein neues Passwort | kleines Team, kein Mailversand im Projekt |
| `S-38` | Vorerst kein TOTP-Zweitfaktor | kleines Team; steht im Fahrplan unter „Später“ |
| `D-10` | Kein eigenes Impressum; die Fußzeile verweist auf `https://lab09.de/tater` | Seite ist für Suchmaschinen gesperrt, die Angaben liegen zentral bei lab09.de |

## Regeln für dieses Projekt

Das Projekt folgt den [Coding-Regeln](https://github.com/tgigold/coding-regeln) in der oben genannten Version. Verweise wie `S-12` oder `H-13` in Code, Pull Requests und dieser README beziehen sich auf deren Regelnummern. Wer am Projekt arbeitet – Mensch oder Assistent –, liest vor der Arbeit an einem Thema die passende Datei der Regeln und vor jedem Pull Request die Checkliste.

Für dieses Projekt gilt außerdem:

- Oberfläche in britischem Englisch (`colour`, `licence`, Datum `14 November 2026`), Code und Bezeichner englisch, Dokumentation deutsch.
- Die fachlichen Entscheidungen – Aufbau der XLSX, Normalisierung der Werte, Disclaimer-Vorlagen, Datenmodell, Ablauf des Imports – stehen in [docs/fachlogik.md](docs/fachlogik.md). Wer davon abweicht, passt das Dokument im selben Pull Request an.
- Die echte Hersteller-XLSX kommt nicht ins Repo; Tests laufen gegen eine selbst gebaute Datei, die die Sonderfälle nachstellt.

## Fahrplan

Jeder Schritt ist ein Pull Request mit Tests, CHANGELOG und README-Pflege; jedes Feature ist eine Minor-Version.

| # | Schritt | Ergebnis | Stand |
|---|---|---|---|
| 1 | Repo, Pflichtdateien, Grundgerüst, CI, Deployment | leere Seite im Design mit Hell/Dunkel, Health-Check grün | 4.0.0 |
| 2 | Setup, Migrationen, Schema, Fake-Daten | Installation auf dem Webspace läuft durch | 4.1.0 |
| 3 | Öffentliche Suche | Autovervollständigung, Treffer, Copy auf Fake-Daten | 4.2.0 |
| 4 | Login, Nutzerverwaltung, Audit-Log | Admin-Bereich erreichbar und abgesichert | |
| 5 | Fahrzeugverwaltung | Liste, live/zurückziehen, bearbeiten, zurücksetzen, von Hand anlegen, Textgenerator | |
| 6 | Import | Upload, Einlesen, Prüfschritt, Übernehmen, Frontend-Sperre | |
| 7 | Ablösung V3 | Umzug auf `disclaimer.lab09.de`, V3 abschalten | |

**Später:** Rollen, Passwort-Reset per E-Mail, Zwei-Faktor-Anmeldung (`S-29`, `S-38`); MINI und BMW ALPINA, sobald deren Dateien vorliegen.

## Aufbau

```
public/            Web-Root: index.php, .htaccess, robots.txt, assets/ (css, js, vendor/<name>-<version>/)
src/               Lab09\Disclaimer: Kernel, Services, Config,
                   Controller/, Http/, Security/, Service/, Repository/, Domain/, Database/, Console/, Support/
templates/         PHP-Templates, jede Ausgabe über e()
config/            routes.php (alle Routen), disclaimer-templates.php (Pflege-Datei), config.example.php
migrations/        nummerierte Datenbank-Migrationen 0001_….php
bin/               console (Kommandozeile)
docs/              Fachlogik und Planung
data/              nur auf dem Server: config.php, setup-code.txt, logs/, backups/ (später uploads/)
deploy/            rsync-exclude.txt
tests/             Unit/, Kernel/, Integration/ (gegen MariaDB), Browser/ (Chrome), Support/
.github/           CI, Deployment, Dependabot
```

- Alle Anfragen, die keine Datei treffen, gehen an `public/index.php`; der `Kernel` leitet auf die konfigurierte Domain um, sucht die Route und ruft den Controller.
- Alle Routen stehen in `config/routes.php`. Eine Route ist ohne Angabe nur für angemeldete Admins erreichbar; öffentliche Routen sind mit `Access::Public` markiert (`A-3`). Bis zum Login-Schritt antworten nicht öffentliche Routen mit `404`.
- Jeder POST braucht ein CSRF-Token; der Kernel prüft es zentral, zusammen mit `Origin` und `Sec-Fetch-Site` (`S-12`, `S-14`). Formulare enthalten das Feld `_csrf`.
- SQL steht nur in `src/Repository/` und in den Migrationen; Logik in `src/Service/`, Controller lesen nur Request und geben eine Response zurück (`A-1`).
- Die Disclaimer-Texte entstehen in `DisclaimerBuilder` aus den Vorlagen in `config/disclaimer-templates.php`. Eine Vorlage darf nur die Felder ihres Fahrzeugtyps verwenden, sonst startet die App nicht.
- Die Version steht nur in `src/App.php` (`G-5`); alle Asset-URLs tragen sie (`A-6`).
- Fremde Dateien liegen unter `public/assets/vendor/<name>-<version>/` samt Lizenz; Stylesheets werden mit Subresource Integrity eingebunden, ein Test prüft die Hashes (`T-5`–`T-7`).

## Suche

Die Startseite ist die öffentliche Suche. Gesucht wird nur in Live-Fahrzeugen, in Name, Typschlüssel und Modellreihe. Jedes Wort muss vorkommen (UND), Groß- und Kleinschreibung spielen keine Rolle. Exakte Treffer auf Typschlüssel oder Reihe stehen oben.

- **Ohne JavaScript** ist das Feld ein normales GET-Formular: `/?q=…` zeigt bei einem Treffer direkt den Disclaimer, bei mehreren eine Liste mit Links, sonst „No match“.
- **Mit JavaScript** (`public/assets/js/search.js`) erscheinen beim Tippen Vorschläge. Pfeiltasten wählen aus, Enter zeigt das Fahrzeug, Esc schließt die Liste und leert beim zweiten Mal das Feld. Alles passiert ohne Seitenwechsel, der Fokus bleibt im Feld, die URL wird mitgeführt, und Zurück/Vor funktionieren. „Copy“ legt den Disclaimer in die Zwischenablage.
- Jedes Fahrzeug hat eine feste Adresse `/vehicles/{id}/{slug}`. Der Slug ist Kosmetik; ein falscher oder fehlender leitet per 301 auf den richtigen um (`A-5`).
- Unter dem leeren Feld schlägt „Try:“ die Modellreihen mit den meisten Live-Fahrzeugen vor.
- Rate-Limit: 120 Seitenaufrufe bzw. 300 API-Anfragen pro Minute und gekürzter IP. Gezählt wird in `data/ratelimit/` mit gehashten Schlüsseln, ältere Minuten werden laufend gelöscht.

## API

Eine kleine, nur lesende JSON-API für das Suchfeld. Sie ist öffentlich und liefert genau das, was die öffentliche Seite zeigt. Antworten tragen immer `Content-Type: application/json; charset=utf-8`.

### `GET /api/v1/search`

| Parameter | Pflicht | Bedeutung |
|---|---|---|
| `q` | nein | Suchtext, höchstens 100 Zeichen; leer ergibt eine leere Liste |
| `limit` | nein | 1 bis 20, Vorgabe 20 |

```http
GET /api/v1/search?q=i5&limit=5
```

```json
{
  "data": [
    {
      "id": "7K3QX9MB",
      "name": "BMW i5 eDrive40 Sedan",
      "type_code": "Z0007",
      "series": "G60",
      "drivetrain": "BEV",
      "disclaimer": "BMW i5 eDrive40 Sedan. WLTP energy consumption combined: …",
      "url": "/vehicles/7K3QX9MB/bmw-i5-edrive40-sedan"
    }
  ],
  "meta": { "query": "i5", "count": 1 }
}
```

### `GET /api/v1/vehicles/{id}`

Ein Live-Fahrzeug, Felder wie oben unter `data`.

### Fehler

Einheitlich nach `API-5`:

```json
{ "error": { "code": "invalid_limit", "message": "Invalid limit.", "fields": { "limit": "integer from 1 to 20" } } }
```

| Status | `code` | Wann |
|---|---|---|
| 404 | `not_found` | unbekannter Endpunkt oder Fahrzeug nicht (mehr) live |
| 422 | `invalid_query`, `invalid_limit` | Parameter ungültig; `fields` nennt Feld und erwartete Form, nie den Inhalt |
| 429 | `rate_limited` | zu viele Anfragen; `Retry-After` nennt die Sekunden bis zur nächsten Minute |

## Lokale Entwicklung

Voraussetzungen: PHP 8.4 oder neuer mit `mbstring`, Composer.

```sh
composer install
php -S localhost:8000 -t public     # dann http://localhost:8000
composer test                       # PHPUnit
composer analyse                    # PHPStan, Level 8
```

**Datenbanktests** (`tests/Integration/`) brauchen eine leere Wegwerf-Datenbank und werden ohne sie übersprungen. In der CI läuft dafür MariaDB 10.11 im Container, dort darf kein Test übersprungen werden. Lokal:

```sh
TATER_TEST_DB_HOST=127.0.0.1 TATER_TEST_DB_NAME=tater_test TATER_TEST_DB_USER=root TATER_TEST_DB_PASSWORD= composer test
```

Achtung: Die Tests löschen alle `v4_`-Tabellen dieser Datenbank.

**Browser-Tests** (`tests/Browser/`, `Q-3`) prüfen das Such-JavaScript und den Theme-Umschalter in einem echten Chrome: Vorschläge, Tastatur, Treffer ohne Seitenwechsel, Zurück-Button, Copy samt Zwischenablage, Escaping und das Merken des Themes. Sie brauchen nur Python 3 und Chrome. Gesteuert wird Chrome über das DevTools-Protokoll mit einem kleinen Client aus der Standardbibliothek (`tests/Browser/cdp.py`), ohne npm und ohne Pakete. In der CI laufen sie gegen den eingebauten PHP-Server mit den Beispieldaten in eigener Datenbank. Lokal, wenn eine installierte Instanz unter `BASE_URL` läuft:

```sh
"/Applications/Google Chrome.app/Contents/MacOS/Google Chrome" --headless=new --remote-debugging-port=9222 --user-data-dir=/tmp/tater-chrome &
BASE_URL=http://127.0.0.1:8000 DEBUG_PORT=9222 python3 -I tests/Browser/test_search.py
```

Der Server läuft mit PHP 8.5; `composer.json` verlangt deshalb `^8.5` und löst Abhängigkeiten für 8.5 auf. Damit lokal auch PHP 8.4 reicht, ist die Plattform-Prüfung von Composer abgeschaltet – Code nutzt deshalb keine Sprachmittel, die erst mit 8.5 kamen, solange lokal 8.4 läuft. Die CI testet mit 8.5.

Ohne `data/config.php` läuft die App mit den Defaults aus `src/Config.php`; Startseite und Setup-Formular lassen sich so ansehen. Das Setup selbst braucht HTTPS, weil das Session-Cookie das Präfix `__Host-` trägt.

## Installation

Nach dem ersten Deploy einmalig im Browser (`G-11`):

1. **Datenbank anlegen:** beim Hoster eine leere MySQL/MariaDB-Datenbank mit eigenem Benutzer nur für dieses Projekt (`G-16`, `S-46`).
2. **`/setup` aufrufen**, z. B. `https://tater.lab09.de/setup`. Beim ersten Aufruf entsteht `data/setup-code.txt`.
3. **Setup-Code per SFTP lesen** und im Formular eingeben – ohne ihn kann niemand die Instanz übernehmen, der nicht auf den Server kommt.
4. **Formular ausfüllen:** Adresse der Installation, Datenbank-Zugang, erster Admin (Passwort mindestens 12 Zeichen). Optional die erfundenen Beispielfahrzeuge.
5. Das Setup prüft PHP-Version und Erweiterungen, legt die Tabellen per Migration an, erzeugt den ersten Admin (Argon2id), schreibt `data/config.php` (nur für den Eigentümer lesbar) und löscht den Setup-Code. Danach antwortet `/setup` mit `404`.

Geht etwas schief (falsche Zugangsdaten, fehlende Rechte), zeigt das Formular den Grund, und es lässt sich erneut abschicken. Eine Datenbank, die schon Admin-Konten dieses Tools enthält, lehnt das Setup ab.

### Beispieldaten

Die Beispielfahrzeuge sind **erfunden** – Namen echter Modelle, aber ausgedachte Werte. Sie dienen nur der Entwicklung, bis der Import fertig ist, und müssen vor dem Live-Gang weg. Ihre Typschlüssel sind fünfstellig und beginnen mit `Z` (`Z0001` …), damit sie nie mit echten Typschlüsseln kollidieren.

```sh
php85 bin/console sample-data:remove    # entfernen
php85 bin/console sample-data:load      # wieder anlegen
```

## Kommandozeile

Per SSH im Projektverzeichnis. Auf dem Webspace ist `php` noch Version 8.3, deshalb immer `php85`:

| Befehl | Wirkung |
|---|---|
| `php85 bin/console migrate` | Backup, dann ausstehende Migrationen ausführen |
| `php85 bin/console migrate:status` | angewandte und ausstehende Migrationen |
| `php85 bin/console backup` | Backup aller `v4_`-Tabellen nach `data/backups/` |
| `php85 bin/console sample-data:load` / `:remove` | Beispielfahrzeuge anlegen / entfernen |

Die Migrationen im Browser kommen mit dem Admin-Bereich (Schritt 4/5).

## Konfiguration

Die Config mit Zugangsdaten liegt auf dem Server unter `data/config.php`, außerhalb des Web-Roots und nie im Repo (`G-14`). Ein Beispiel ohne echte Werte steht in [config/config.example.php](config/config.example.php). Jeder Schlüssel ist optional, Defaults stehen im Code (`G-13`). Ab Schritt 2 schreibt das geführte Setup die Datei.

| Schlüssel | Bedeutung |
|---|---|
| `base_url` | kanonische Adresse; Anfragen auf anderen Hosts oder per HTTP werden dorthin umgeleitet |
| `trusted_proxies` | IPs von Reverse-Proxys, deren `X-Forwarded-Proto` gilt (sonst leer) |
| `db` | `host`, `port`, `name`, `user`, `password` der Datenbank; ohne diesen Schlüssel gilt die App als nicht installiert |

## Deployment

Jeder Merge auf `main` geht nach grüner CI automatisch live (`H-9`, `H-11`):

1. `.github/workflows/deploy.yml` ruft die CI auf dem gemergten Stand auf.
2. Composer installiert ohne Entwicklungspakete, `vendor/` bekommt eine sperrende `.htaccess`.
3. rsync über SSH überträgt das Projekt. Was in [deploy/rsync-exclude.txt](deploy/rsync-exclude.txt) steht – vor allem `data/` mit Config, Logs, Uploads und Backups –, wird weder hochgeladen noch auf dem Server gelöscht (`H-13`).
4. Der Workflow ruft den Health-Check <https://tater.lab09.de/health> auf; antwortet er nicht mit `200`, schlägt das Deployment sichtbar fehl (`H-14`).

**Zugangsdaten:** Host, Benutzer, Zielpfad, privater Deploy-Schlüssel und Host-Key liegen ausschließlich als Secrets im GitHub-Environment `production` (`SSH_HOST`, `SSH_USER`, `SSH_KEY`, `SSH_KNOWN_HOSTS`, `DEPLOY_PATH`), die Health-Check-URL als Variable `HEALTH_URL`. Der Deploy-Schlüssel gehört nur zu diesem Projekt (`S-46`). Zugriff haben die Maintainer des Repos.

**Zurückrollen:** per Revert-Pull-Request, der wie jede Änderung durch die CI geht (`H-16`).

### SSH zwischen GitHub und Server einrichten

Einmalig beim Aufsetzen des Projekts, oder wenn der Deploy-Schlüssel ersetzt werden muss:

1. **Schlüssel erzeugen** (lokal, ohne Passphrase, nur für dieses Projekt):
   `ssh-keygen -t ed25519 -N '' -C 'deploy mash-disclaimer' -f ./deploy_key`
2. **Öffentlichen Teil auf dem Server hinterlegen:** Inhalt von `deploy_key.pub` als eigene Zeile an `~/.ssh/authorized_keys` des SSH-Benutzers anhängen (beim Hoster in der Verwaltungsoberfläche oder per SSH). Die Zeile darf mit Optionen eingeschränkt werden, etwa `from="…"`, solange rsync funktioniert.
3. **Host-Key ermitteln und prüfen:** `ssh-keyscan -t ed25519 <host>` liefert die Zeile für `SSH_KNOWN_HOSTS`. Den Fingerabdruck (`ssh-keygen -lf <(ssh-keyscan -t ed25519 <host>)`) mit der Angabe des Hosters abgleichen – nie blind übernehmen.
4. **Secrets setzen** (per `gh`, Werte nie in Chat, Commit oder Log):
   ```sh
   gh api -X PUT repos/tgigold/mash-disclaimer/environments/production
   gh secret set SSH_KEY         --env production < deploy_key
   gh secret set SSH_KNOWN_HOSTS --env production < known_hosts_line
   gh secret set SSH_HOST        --env production
   gh secret set SSH_USER        --env production
   gh secret set DEPLOY_PATH     --env production   # Projektverzeichnis, das public/ enthält
   gh variable set HEALTH_URL    --env production --body 'https://tater.lab09.de/health'
   ```
5. **Privaten Schlüssel löschen:** `rm deploy_key` – er liegt danach nur noch in GitHub.
6. **Probelauf:** Vor dem ersten Deploy `rsync --dry-run` gegen den Server laufen lassen, damit nichts Unerwartetes gelöscht wird.

Ist ein Schlüssel verbrannt (`S-47`): neuen Schlüssel nach Schritt 1–5 erzeugen, den alten aus `authorized_keys` entfernen.

## Betrieb

- **Fehler** landen in `data/logs/error.log` (per SFTP lesbar, über das Web gesperrt). Die Datei wird bei 1 MB nach `error.log.1` rotiert, es gibt also höchstens zwei Dateien (`S-50`, `S-51`). Bisher stehen darin keine personenbezogenen Daten.
- **Backups:** Vor jeder Migration entsteht automatisch ein gzip-komprimierter SQL-Dump aller `v4_`-Tabellen unter `data/backups/`; die 30 neuesten bleiben erhalten. Zurückspielen per phpMyAdmin oder `zcat <datei> | mysql …`.
- **Updates** kommen per Merge auf `main`; Datenbank-Migrationen werden danach bewusst ausgelöst (`H-15`): `php85 bin/console migrate`.
- **Audit-Log:** Setup, Anlegen von Admins und Laden der Beispieldaten stehen in `v4_audit_log`, mit gekürzter IP (`D-6`). Die Aufbewahrungsfrist wird mit der „Change history“ in Schritt 4 festgelegt.
- **Suchmaschinen** sind ausgesperrt: `X-Robots-Tag: noindex, nofollow` auf jeder Antwort und `robots.txt` mit `Disallow: /`.

## Lizenz

Der Code steht unter der [Unlicense](LICENSE). Mitgelieferte Bibliotheken und Schriften behalten ihre Lizenzen, siehe [THIRD-PARTY-LICENSES.md](THIRD-PARTY-LICENSES.md).
