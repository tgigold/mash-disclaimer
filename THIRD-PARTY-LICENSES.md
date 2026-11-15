# Fremde Bestandteile

Diese Dateien werden mit dem Projekt ausgeliefert und stehen unter ihren eigenen Lizenzen. Der volle Lizenztext liegt jeweils als `LICENSE` im genannten Ordner.

| Bestandteil | Version | Lizenz | Ordner | Quelle |
|---|---|---|---|---|
| Bootstrap (nur CSS) | 5.3.9 | MIT | `public/assets/vendor/bootstrap-5.3.9/` | <https://getbootstrap.com/> |
| Bootstrap Icons | 1.13.2 | MIT | `public/assets/vendor/bootstrap-icons-1.13.2/` | <https://icons.getbootstrap.com/> |
| Bricolage Grotesque (latin, 400/600/700/800, woff2) | 5.3.0 (`@fontsource/bricolage-grotesque`) | SIL Open Font License 1.1 | `public/assets/vendor/bricolage-grotesque-5.3.0/` | <https://github.com/ateliertriay/bricolage> |

Composer-Pakete werden auf dem Server mit ausgeliefert (`vendor/`); ihre Lizenztexte liegen in den Paketen selbst. Laufzeit-Abhängigkeiten (`composer licenses --no-dev`):

| Paket | Lizenz | Wozu |
|---|---|---|
| `phpoffice/phpspreadsheet` | MIT | XLSX lesen |
| `composer/pcre`, `maennchen/zipstream-php`, `markbaker/complex`, `markbaker/matrix`, `phpoffice/wmf`, `psr/simple-cache` | MIT | Abhängigkeiten von PhpSpreadsheet |
