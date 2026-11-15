# Changelog

Alle wichtigen Änderungen an diesem Projekt stehen hier. Das Format folgt [Keep a Changelog](https://keepachangelog.com/de/1.1.0/), die Versionen folgen [Semantic Versioning](https://semver.org/lang/de/).

## [Unreleased]

### Hinzugefügt

- Fahrzeugverwaltung unter Admin → Vehicles: Liste mit Kennzahlen, Filtern nach Typ, Status und Herkunft und Suche; Seitenleiste zum Bearbeiten mit Vorschau des Disclaimers beim Tippen.
- Fahrzeuge live stellen, aus der Suche nehmen, löschen; bearbeitete Live-Werte importierter Fahrzeuge sind markiert und lassen sich auf die Werksangaben zurücksetzen, der eigene Name bleibt.
- Fahrzeuge von Hand anlegen („New vehicle“), mit Werten direkt aus Excel (`19.5 / 17.2`, auch mit Dezimalkomma).
- Normalisierung der Werte nach den Regeln der Fachlogik (höherer Wert zuerst, identische Paare zusammengefasst, Kilometer ganzzahlig, CO₂-Klassen sortiert) – dieselbe Klasse nutzt später der Import.
- „Regenerate texts“ unter Updates baut alle Live-Texte nach einer Vorlagenänderung neu.
- Kommandozeile: `user:create` als Notzugang.
- Browser-Tests für die Seitenleiste.

### Behoben

- Vor dem Setup verglich die Origin-Prüfung den Host samt Port; Formulare auf Adressen mit Port (z. B. `localhost:8080`) wären abgelehnt worden.

## [4.3.0] – 2026-11-15

### Hinzugefügt

- Admin-Bereich unter `/admin` mit Anmeldung: Dashboard mit Kennzahlen, Nutzerverwaltung (anlegen, neues Passwort setzen, deaktivieren, reaktivieren), Change history aus dem Audit-Log, Updates für Datenbank-Migrationen im Browser, eigenes Konto (Passwort ändern, Daten herunterladen, Konto löschen).
- Login mit Argon2id, Sperre nach 5 Fehlversuchen pro IP bzw. 25 insgesamt für 15 Minuten, Ausnahme für bekannte Geräte, Sitzungsende nach 2 Stunden Inaktivität bzw. 12 Stunden.
- Sensible Aktionen fragen das eigene Passwort erneut ab; ein neues Passwort oder das Deaktivieren beendet alle Sitzungen des Kontos.
- Aufbewahrungsfristen: Login-Fehlversuche 15 Minuten, bekannte Geräte 1 Jahr, gekürzte IPs im Audit-Log 90 Tage, Audit-Log 24 Monate.
- Migration `0002`: Tabellen für Login-Fehlversuche und bekannte Geräte.

### Geändert

- „Disclaimer Tool“ oben links im Admin-Bereich führt zur öffentlichen Suche.

## [4.2.0] – 2026-11-15

### Hinzugefügt

- Öffentliche Suche auf der Startseite: Vorschläge beim Tippen, Tastaturbedienung (Pfeile, Enter, Esc), Treffer ohne Seitenwechsel, Trefferliste bei mehreren Fahrzeugen, „No match“, Copy-Button mit Rückmeldung; funktioniert auch ohne JavaScript als GET-Formular.
- Feste Adresse je Fahrzeug unter `/vehicles/{id}/{slug}` mit Weiterleitung auf den richtigen Slug.
- Lesende JSON-API `/api/v1/search` und `/api/v1/vehicles/{id}` mit einheitlichem Fehlerformat.
- Rate-Limit pro Minute und gekürzter IP für Suche und API (`429` mit `Retry-After`).
- Vorschläge „Try:“ mit den Modellreihen, die die meisten Live-Fahrzeuge haben.
- Browser-Tests für Such-JavaScript und Theme-Umschalter in der CI (Chrome über das DevTools-Protokoll, ohne npm).

### Behoben

- Die CI wartet bis zu 60 Sekunden auf den Start von Chrome; nach 10 Sekunden war der Deploy von #6 abgebrochen, weil Chrome auf dem Runner langsamer startete.

## [4.1.0] – 2026-11-15

### Hinzugefügt

- Geführtes Setup unter `/setup`: Setup-Code aus `data/setup-code.txt`, Prüfung der Server-Voraussetzungen, Datenbank-Zugang, erster Admin (Argon2id), optional erfundene Beispielfahrzeuge; schreibt `data/config.php` und sperrt sich danach selbst.
- Datenbankschema als Migration `0001`: Einstellungen, Admin-Konten, Audit-Log, Fahrzeuge (Work) und Live-Fahrzeuge, alles `utf8mb4`/InnoDB mit Präfix `v4_`.
- Migrationen mit automatischem Backup vorher, per Kommandozeile `php85 bin/console migrate`.
- Disclaimer-Vorlagen als Pflege-Datei `config/disclaimer-templates.php` und `DisclaimerBuilder`, der nur die Felder des jeweiligen Fahrzeugtyps zulässt.
- Zufällige 8-stellige Fahrzeug-IDs (Crockford-Base32).
- Sessions mit `__Host-`-Cookie, CSRF-Schutz für jeden POST samt Prüfung von `Origin` und `Sec-Fetch-Site`, Begrenzung der Request-Größe.
- Datenbanktests gegen MariaDB in der CI.

### Geändert

- Steckbrief auf Coding-Regeln 1.3.0 gehoben (Korrektur von `G-1`, umgesetzt bereits in 4.0.0).

## [4.0.0] – 2026-11-14

### Hinzugefügt

- Grundgerüst: Front-Controller, Routenliste mit Login als Standard und expliziter Markierung öffentlicher Routen, Kernel mit Weiterleitung auf die konfigurierte HTTPS-Domain, Fehlerseiten und Fehler-Log mit Größenbegrenzung.
- Startseite im neuen Design mit Hell-/Dunkel-Modus (Vorgabe aus dem System, Wahl wird im Browser gemerkt); die Suche ist noch deaktiviert.
- Health-Check unter `/health` für das Deployment.
- Bootstrap 5.3.9, Bootstrap Icons 1.13.2 und Bricolage Grotesque 5.3.0 lokal eingebunden, Stylesheets mit Subresource Integrity.
- Sicherheits-Header, strikte Content Security Policy, Sperre für Suchmaschinen.
- CI (Syntax, PHPStan Level 8, PHPUnit, `composer audit`), Deployment per rsync über SSH, Dependabot.
- Pflichtdateien, README mit Steckbrief und Anleitung für die SSH-Einrichtung, Fachlogik in `docs/fachlogik.md`.

### Behoben

- Jede Anfrage endete mit `403`: Die sperrende `.htaccess` im Projektverzeichnis galt auch für `public/`, weil Apache die `.htaccess` der Elternverzeichnisse mit auswertet. Gesperrt sind jetzt nur die Verzeichnisse neben `public/`.
- Der Deploy bricht ab, bevor etwas übertragen wird, wenn ein Secret fehlt oder `DEPLOY_PATH` kein tiefer absoluter Pfad ist; ein leerer Pfad hätte rsync mit `--delete` auf `/` zielen lassen.

[Unreleased]: https://github.com/tgigold/mash-disclaimer/compare/v4.3.0...HEAD
[4.3.0]: https://github.com/tgigold/mash-disclaimer/releases/tag/v4.3.0
[4.2.0]: https://github.com/tgigold/mash-disclaimer/releases/tag/v4.2.0
[4.1.0]: https://github.com/tgigold/mash-disclaimer/releases/tag/v4.1.0
[4.0.0]: https://github.com/tgigold/mash-disclaimer/releases/tag/v4.0.0
