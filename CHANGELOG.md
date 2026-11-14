# Changelog

Alle wichtigen Änderungen an diesem Projekt stehen hier. Das Format folgt [Keep a Changelog](https://keepachangelog.com/de/1.1.0/), die Versionen folgen [Semantic Versioning](https://semver.org/lang/de/).

## [Unreleased]

### Hinzugefügt

- Grundgerüst: Front-Controller, Routenliste mit Login als Standard und expliziter Markierung öffentlicher Routen, Kernel mit Weiterleitung auf die konfigurierte HTTPS-Domain, Fehlerseiten und Fehler-Log mit Größenbegrenzung.
- Startseite im neuen Design mit Hell-/Dunkel-Modus (Vorgabe aus dem System, Wahl wird im Browser gemerkt); die Suche ist noch deaktiviert.
- Health-Check unter `/health` für das Deployment.
- Bootstrap 5.3.9, Bootstrap Icons 1.13.2 und Bricolage Grotesque 5.3.0 lokal eingebunden, Stylesheets mit Subresource Integrity.
- Sicherheits-Header, strikte Content Security Policy, Sperre für Suchmaschinen.
- CI (Syntax, PHPStan Level 8, PHPUnit, `composer audit`), Deployment per rsync über SSH, Dependabot.
- Pflichtdateien, README mit Steckbrief und Anleitung für die SSH-Einrichtung, Fachlogik in `docs/fachlogik.md`.
