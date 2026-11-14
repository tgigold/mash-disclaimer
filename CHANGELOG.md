# Changelog

Alle wichtigen Änderungen an diesem Projekt stehen hier. Das Format folgt [Keep a Changelog](https://keepachangelog.com/de/1.1.0/), die Versionen folgen [Semantic Versioning](https://semver.org/lang/de/).

## [Unreleased]

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

[Unreleased]: https://github.com/tgigold/mash-disclaimer/compare/v4.0.0...HEAD
[4.0.0]: https://github.com/tgigold/mash-disclaimer/releases/tag/v4.0.0
