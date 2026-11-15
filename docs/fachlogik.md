# Fachlogik und Planung

Dieses Dokument hält fest, wie die Import-Datei aufgebaut ist, wie Werte normalisiert und Disclaimer erzeugt werden, wie die Daten gespeichert sind und wie Import und Oberfläche funktionieren sollen. Es ist der Planungsstand vom 14. November 2026 und wird mit jedem Schritt mitgepflegt; was noch nicht gebaut ist, steht im [Fahrplan](../README.md#fahrplan).

## 1. Was die echte XLSX zeigt

Geprüft an `20261008_WLTP_BMW_DE_2026_11_12_2027_01_V3.xlsx` (vollständig eingelesen). Diese Fakten sind belegt; wo sie vom ursprünglichen Auftrag abweichen, ist das markiert.

**Aufbau**

- 30 Tabs, ein Tab pro Modellreihe (`F70`, `G80`, `NA0` …), 152 Typschlüssel. Tab-Namen sind nicht sauber: `'G65 '` (Leerzeichen am Ende), `'G90 G99'` (zwei Reihen in einem Tab).
- Spalte A enthält die Bezeichnungen, ab Spalte B stehen die Fahrzeuge. **Pro Typschlüssel in der Regel zwei Spalten:** erst „WLTP Werte Pflichtangabe nach PKW-EnVKV“, dann „WLTP Werte individ. Konfiguration (Worst Case/Best Case)“.
- ⚠ **Abweichung:** Der Typschlüssel steht **nicht immer in Zeile 2**. In 20 Tabs Zeile 2, in 10 Tabs Zeile 3 (dort ist eine Leerzeile eingeschoben). Die Folgezeilen verschieben sich mit: Name = Typschlüsselzeile + 1, Marker = + 2, „Range“ = + 3. → Die Typschlüsselzeile wird erkannt (erste Zeile, in der Spalte B auf `^[0-9A-Z]{4}$` passt), nicht fest verdrahtet.
- ⚠ **Abweichung:** Die Überschrift in Zeile 1 ist kein verlässliches Merkmal für die Pflichtspalte:
  - Tab `G65 ` hat in Zeile 1 gar keine Spaltenüberschriften (5 Typschlüssel).
  - In `U06`, `U10`, `U11` haben 21 Typschlüssel **vier** Spalten, und die dritte ist fälschlich ebenfalls mit „Pflichtangabe“ überschrieben, enthält aber Getriebe-Werte.
  - `21DM` (Tab `G87`) hat drei Spalten.
  - Verlässlich ist: **die erste Spalte je Typschlüssel**, und sie trägt in der Marker-Zeile immer `EnVKV (max.WC/min.WC)`. Regel: erste Spalte nehmen, Marker prüfen; fehlt der Marker, wird der Datensatz dem Nutzer vorgelegt.
- Das Erstelldatum (`Erstelldatum: 14.09.2026`) steht in Spalte A, einmal **pro Tab** (gilt also für alle Fahrzeuge des Tabs). Es ist die letzte *nicht leere* Zeile; danach folgen Zeilen mit nur einem Leerzeichen. Gesucht wird per Präfix, nicht per Position.
- Die Modellreihe steht im Tab-Namen, in A1 in Klammern und als Suffix des Namens (`…, G80`). Verlässlich ist das Suffix nach dem letzten Komma (im Tab `G90 G99` enden die Namen auf `G90` bzw. `G99`); Fallback: Tab-Name.
- Die Namen in der XLSX sind deutsch und technisch (`BMW M3 Competition M xDrive Limousine 4-türig, G80`), die Disclaimer brauchen englische Marketingnamen (`BMW M3 Competition Sedan with M xDrive`). Darum bleibt der eigene Name bei Folgeimporten erhalten.

**Bezeichnungen in Spalte A**

- ⚠ **Abweichung:** Mehrere Bezeichnungen heißen anders als im ursprünglichen Auftrag. Tatsächlich (in allen 30 Tabs identisch, eingerückt mit führenden Leerzeichen):

  | Auftrag | tatsächlich in der XLSX |
  |---|---|
  | CO2 Emission Kombiniert (g/km) | `CO2 Emissionen Kombiniert (g/km)` |
  | Phase Kombiniert (l/100km) | `FC Phase Kombiniert (l/100km)` |
  | EC Phase kombiniert (kWh/100 km) | `EC Phase Kombiniert (kWh/100km)` |
  | FCCS Phase Kombiniert (l/100 km) | `FCCS Phase Kombiniert (l/100km)` |
  | FCW Kombiniert (l/100 km) | `FCW Kombiniert (l/100km)` |

  Die übrigen stimmen: `Charge Sustaining Kombiniert (g/km)`, `Gewichtet Kombiniert (g/km)`, `EC AC Charge Gewichtet (kWh/100km)`, `EAER Phase Kombiniert (km)`, `PER Phase Kombiniert (km)`, `CO2-Klasse`, `CO2-Klasse bei entladener Batterie (PHEV)`.
- Verglichen wird **exakt nach Normalisierung** (Kleinschreibung, sämtlicher Leerraum entfernt), **nicht** als Teilstring: „Phase Kombiniert (l/100km)“ käme sonst in `FC …` und `FCCS …` vor.
- `CO2-Klasse` kommt in jedem Tab **zweimal** vor: als Abschnittsüberschrift ohne Werte und direkt darunter als Wertezeile. Genommen wird die Zeile, die in der Fahrzeugspalte einen Wert hat.

**Werte**

- Die Werte stehen als Text in den Zellen. Ausnahme: Die Zeilen `CO2-Klasse` und `CO2-Klasse bei entladener Batterie (PHEV)` sind **Formeln** (680 Zellen); der Import liest das Ergebnis, das Excel gespeichert hat, und rechnet nie selbst nach. In der Datei vom September 2026 hat jede Formelzelle ein gespeichertes Ergebnis. Formate in den Pflichtspalten: `237 / 235`, `10.4 / 10.4`, `425.0 / 425.0`, `N/A`, Klassen `G` oder `E-D`.
- Werte mit Klammerzusatz kommen auch in Pflichtspalten vor (z. B. `NA0`/`51JB`: `18.0 / 13.4 (16.1 / 13.5)`). Der Fall `N/A (158.0 / 148.0)` kommt in dieser Datei in den benötigten Feldern nicht vor, muss aber behandelt werden.
- Reichweiten stehen als `729.0 / 911.0` – also niedriger Wert zuerst und mit `.0`.
- Viele Paare sind identisch (`10.4 / 10.4`).
- Hinweiszeilen wie „Neue Typschlüssel“ oder „Heute vs. 01.05.2026 (DOC aktuelle Zeitscheibe)“ sind für uns ohne Bedeutung.

**Typ-Erkennung** – die vier Muster decken alle 152 Typschlüssel lückenlos ab:

| Typ | Erkennungsmerkmal (Wert vorhanden, nicht `N/A`) |
|---|---|
| PHEV | `Gewichtet Kombiniert (g/km)` |
| BEV | `EC Phase Kombiniert (kWh/100km)` und kein CO₂-Wert |
| MHEV | `Charge Sustaining Kombiniert (g/km)`, aber kein gewichteter Wert |
| ICE | `CO2 Emissionen Kombiniert (g/km)` |

Geprüft wird in dieser Reihenfolge. Passt keins, wird der Datensatz dem Nutzer vorgelegt. Der Wasserstoff-Typ FCEV aus V3 kommt in der Datei nicht vor und ist in V4 nicht vorgesehen.

Eine ältere Beispieldatei (`example-disclaimer-xls.xlsx`) hat ein altes, englisches Format (3 Tabs, eine Spalte je Fahrzeug) und ist kein Importformat.

## 2. Fachlogik

### Felder

Die XLSX-Bezeichnungen werden auf sieben eigene Felder abgebildet. So braucht es nur ein Datenmodell für alle Typen.

| Feld | ICE | MHEV | PHEV | BEV |
|---|---|---|---|---|
| `fuel_consumption` (l/100 km) | FC Phase Kombiniert | FCCS Phase Kombiniert | FCW Kombiniert | – |
| `electric_consumption` (kWh/100 km) | – | – | EC AC Charge Gewichtet | EC Phase Kombiniert |
| `co2_emissions` (g/km) | CO2 Emissionen Kombiniert | Charge Sustaining Kombiniert | Gewichtet Kombiniert | – (fest 0) |
| `co2_class` | CO2-Klasse | CO2-Klasse | CO2-Klasse | CO2-Klasse |
| `co2_class_discharged` | – | – | CO2-Klasse bei entladener Batterie (PHEV) | – |
| `fuel_consumption_discharged` (l/100 km) | – | – | FCCS Phase Kombiniert | – |
| `electric_range` (km) | – | – | EAER Phase Kombiniert | PER Phase Kombiniert |

Diese Zuordnung (Typ → Feld → XLSX-Bezeichnung), die Erkennungsmerkmale, der Marker der Pflichtspalte und die Marken-Präfixe liegen in einer **Pflege-Datei** `config/import-mapping.php` – getrennt von der Config mit Zugangsdaten –, damit sie bei einer Änderung der XLSX ohne Codeänderung angepasst werden können.

### Normalisierung

Dieselbe Normalisierung gilt für den Import und für Werte, die im Admin von Hand eingegeben oder aus Excel eingefügt werden.

1. Leerraum am Anfang/Ende und Steuerzeichen entfernen, Zeilenumbrüche in Bezeichnungen zu einem Leerzeichen.
2. Klammerzusatz abschneiden: `10.2 / 9.4 (10.2 / 9.5)` → `10.2 / 9.4`.
3. Bleibt etwas anderes als `Zahl / Zahl`, eine einzelne Zahl bzw. eine Klasse `A`–`G` oder `X-Y` übrig (z. B. `N/A`) in einem für den Typ benötigten Feld → beim Import wird der Datensatz nicht automatisch übernommen, sondern dem Nutzer vorgelegt; im Formular ist es ein Validierungsfehler.
4. Zahlenpaare: **höherer Wert zuerst**, mit `-` verbunden (`7 / 5` → `7-5`). **Ausnahme Reichweite:** niedriger Wert zuerst (`911.0 / 729.0` → `729-911`). Bis 4.4 stand auch die Reichweite hoch–niedrig; Migration `0004` dreht gespeicherte Werte um.
5. Identische Paare werden zu einem Wert: `10.4 / 10.4` → `10.4`.
6. Verglichen wird numerisch, **ausgegeben wird der Originaltext** (Punkt als Dezimalzeichen, Nachkommastellen wie geliefert). Verbrauch in Litern und kWh behält das `.0` (`10.0` bleibt `10.0`). Es wird nie gerechnet oder gerundet. Ein Dezimalkomma, wie es ein deutsches Excel kopiert (`10,4`), wird als Punkt gelesen und gespeichert (`10.4`).
7. **Kilometer stehen nur als ganze Zahl:** Bei der Reichweite fällt das `.0` weg (`911.0` → `911`). Käme je eine andere Nachkommastelle (`425.5`), wird nicht gerundet, sondern der Datensatz dem Nutzer vorgelegt.
8. CO₂-Klassen: höherer Buchstabe zuerst (`D-E` → `E-D`), identische zu einem (`E-E` → `E`).

Gespeichert werden die normalisierten Strings (`235-233`), nicht Min/Max getrennt wie in V3.

### Disclaimer-Vorlagen

Eine Vorlage je Typ mit Platzhaltern, gepflegt in der Pflege-Datei `config/disclaimer-templates.php` (Änderungen laufen über Pull Request). Anführungszeichen einheitlich gerade: `'Pkw-EnVKV'`.

- **ICE** und **MHEV** `{name}. Mandatory information according to German law 'Pkw-EnVKV' based on WLTP: energy consumption combined: {fuel_consumption} l/100 km; CO₂ emissions combined: {co2_emissions} g/km; CO₂ class(es): {co2_class}.`
- **PHEV** `{name}. Mandatory information according to German law 'Pkw-EnVKV' based on WLTP: energy consumption weighted combined: {electric_consumption} kWh/100 km and {fuel_consumption} l/100 km; CO₂ emissions weighted combined: {co2_emissions} g/km; CO₂ classes: with discharged battery: {co2_class_discharged}; weighted combined: {co2_class}; Fuel consumption with depleted battery combined: {fuel_consumption_discharged} l/100km; electric range: {electric_range} km.`
- **BEV** `{name}. Mandatory information according to German law 'Pkw-EnVKV' based on WLTP: energy consumption combined: {electric_consumption} kWh/100 km; CO₂ emissions combined: 0 g/km; CO₂ class: {co2_class}; electric range: {electric_range} km.`

Der Text wird **immer** aus Vorlage, Name und Werten erzeugt und nie frei überschrieben. Bearbeitbar sind nur Name und Werte. Der fertige Text liegt trotzdem in der Live-Tabelle (die Suche liest nur). Ändert sich eine Vorlage, erzeugt eine Admin-Aktion „Regenerate texts“ alle Live-Texte neu.

## 3. Datenmodell

Das Konzept „Work-Tabelle mit Daten, Live-Tabelle mit fertigem Text“ wird übernommen und um eine Staging-Stufe für den Import ergänzt. Alle Tabellen `utf8mb4`, InnoDB, Präfix `v4_`.

| Tabelle | Inhalt |
|---|---|
| `v4_vehicles` (**Work**) | Ein Datensatz je Fahrzeug: `id` (PK, zufällige Kurz-ID, siehe unten), `type_code` (eindeutig, **darf leer sein**), `origin` (`import` / `manual`), `brand` (BMW / MINI / BMW ALPINA), `series`, `drivetrain` (ICE/MHEV/PHEV/BEV), `name` (eigener Name, wird vom Import nie überschrieben), `source_name` (Name laut XLSX, wird bei jedem Import aktualisiert), die sieben Wertfelder (Werksangaben bzw. bei Handanlage die eingegebenen Werte), `source_created_on` (Erstelldatum laut XLSX), `created_at`, `first_imported_at`, `last_imported_at`, `last_import_id` |
| `v4_vehicles_live` (**Live**) | Nur live gestellte Fahrzeuge: `vehicle_id` (PK, FK), die sieben Wertfelder (bearbeitbar), `disclaimer_text` (fertiger Text), `is_edited`, `activated_at`, `edited_at`, `edited_by` |
| `v4_imports` | Ein Datensatz je Upload: Originalname, gespeicherter Dateiname, SHA-256, Nutzer, Zeitpunkte, Status (`parsed` → `applied` → `released` / `aborted`), Zähler |
| `v4_import_items` (**Staging**) | Je Import und Typschlüssel: erkannter Typ, Marke, Reihe, Name, normalisierte Werte, die komplette Pflichtspalte roh als JSON, Einstufung (`new` / `changed` / `unchanged` / `problem`), Problemliste, Entscheidung des Nutzers |
| `v4_users`, `v4_login_attempts` | Admin-Konten (Argon2id), Login-Sperre |
| `v4_audit_log` | Wer hat wann was getan (Import, Aktivieren, Bearbeiten, Zurücksetzen, Nutzerverwaltung) – speist „Change history“ |
| `v4_settings` | Alltagseinstellungen, u. a. die Frontend-Sperre (seit wann, von wem) |
| `v4_migrations` | Stand der nummerierten Migrationen |

Begründungen:

- **Nicht die ganze XLSX 1:1 in die Datenbank.** Die Originaldatei wird ohnehin zur Dokumentation abgelegt. In die Staging-Tabelle kommt je Fahrzeug die vollständige Pflichtspalte als JSON (rund 70 Werte) plus die ausgewerteten Felder. Das löst das Problem „Werte stehen in wechselnden Zeilen“ beim Einlesen statt beim Abfragen und lässt später weitere Felder zu, ohne neu hochzuladen.
- **Der Name liegt nur einmal vor**, im Work-Datensatz, und gehört zum Fahrzeug, nicht zum Live-Stand. Damit überlebt er Deaktivieren, Zurücksetzen und Folgeimporte von selbst.
- **Eigene `id` statt Typschlüssel als Primärschlüssel**, weil von Hand angelegte Fahrzeuge (noch) keinen Typschlüssel haben. Für importierte Fahrzeuge bleibt der Typschlüssel der eindeutige Abgleichschlüssel. In URLs steht die `id` (`A-5`).
- **Die `id` ist keine laufende Nummer**, sondern eine zufällige Kurz-ID: 8 Zeichen aus `random_bytes`, Alphabet ohne verwechselbare Zeichen (Crockford-Base32: Ziffern und Großbuchstaben ohne `I`, `L`, `O`, `U`), z. B. `7K3QX9MB`. Sie sieht einem Typschlüssel bewusst nicht ähnlich (doppelt so lang), ist nicht erratbar und verrät nicht, wie viele Fahrzeuge es gibt. Erzeugt wird sie beim Anlegen, bei einer Kollision neu gewürfelt; danach ändert sie sich nie – auch nicht, wenn ein Hand-Fahrzeug später einen Typschlüssel bekommt.
- **Feste Spalten statt Schlüssel-Wert-Tabelle** wie in V3: Es sind nur sieben Felder, und sie werden nie berechnet.
- Typschlüssel: validiert gegen `^[0-9A-Z]{4,5}$` (in der Datei immer 4 Zeichen, V3 erlaubte 5).

### Von Hand angelegte Fahrzeuge

- Nötig für neue Fahrzeuge, die noch in keiner XLSX stehen. Eingabe: Name, Marke, Typ, optional Reihe und Typschlüssel, dazu die Werte des Typs – als Freitext, sodass man `19.5 / 17.2` direkt aus Excel einfügen kann (gleiche Normalisierung wie beim Import).
- Sie haben `origin = manual`. „Auf Werksangaben zurücksetzen“ gibt es für sie erst, wenn ein Import sie übernommen hat.
- Trägt ein Hand-Fahrzeug einen Typschlüssel und bringt ein Import denselben mit, wird es wie ein bestehendes Fahrzeug behandelt: Werte werden überschrieben, Name bleibt, `origin` wird `import`.
- Hand-Fahrzeuge ohne Typschlüssel kann der Import nicht zuordnen. Nach jedem Import zeigt der Prüfschritt deshalb alle Hand-Fahrzeuge mit dem Hinweis, sie auf Dopplungen zu prüfen; je Fahrzeug: behalten, einem importierten Typschlüssel zuordnen (zusammenführen, Name des Hand-Fahrzeugs bleibt) oder löschen.

### Marken (BMW, MINI, BMW ALPINA)

Ob MINI und ALPINA später in derselben oder in eigenen Dateien kommen, ist offen. Das Modell hält beides aus:

- `brand` wird beim Import aus dem Namensanfang abgeleitet (`BMW ALPINA` vor `BMW` prüfen, `MINI`); die Präfixe stehen in der Pflege-Datei.
- **„Entfallen“ wird nur innerhalb der Marken geprüft, die in der hochgeladenen Datei vorkommen.** Eine reine BMW-Datei kann also nie MINI-Fahrzeuge als entfallen melden; eine Gesamtdatei prüft alle.
- Entfallene Fahrzeuge werden nie automatisch gelöscht, sondern immer im Prüfschritt entschieden.
- Weicht das Format einer Marke später ab, bekommt die Pflege-Datei ein zweites Profil. Bis dahin wird nichts auf Vorrat gebaut.

## 4. Import (letzter Entwicklungsschritt)

1. **Upload** einer XLSX oder Auswahl einer bereits abgelegten. Prüfung: Größe, Typ per `finfo`, ZIP-Struktur. Ablage außerhalb des Web-Roots unter zufälligem Namen; Originalname und Upload-Datum stehen in `v4_imports` (`S-43`; der im Auftrag gewünschte Name `[Originalname]-[Uploaddatum]` wird beim Herunterladen als Dateiname gesetzt).
2. **Frontend sperren**, sobald der Import gestartet wird: Wartungsseite statt Suche, auch für die Such-API. Die öffentliche Seite nennt nur, seit wann die Sperre besteht; wer sie gestartet hat, steht ausschließlich im Admin-Bereich.
3. **Einlesen** aller Tabs mit PhpSpreadsheet (nur Daten, keine Formatierung) in `v4_import_items`. Work und Live bleiben unberührt.
4. **Prüfschritt** mit Gegenüberstellung zum Bestand:
   - neu (Typschlüssel bisher unbekannt) → importieren ja/nein
   - entfallen (im Bestand der betroffenen Marken, aber nicht in der Datei) → behalten oder löschen
   - geändert (alt → neu je Feld); von Hand geänderte Live-Werte sind markiert und **werden überschrieben**
   - unverändert
   - Problemfälle (Text statt Zahl, Typ nicht erkennbar, Marker fehlt, Typschlüssel doppelt) → einzeln entscheiden
5. **Auswahl**, was in den Live-Stand geht: a) alle, b) nur derzeit aktive, c) ausgewählte.
6. **Übernehmen** in einer einzigen Datenbank-Transaktion: Work aktualisieren, Live-Werte überschreiben, Texte neu erzeugen, Audit-Eintrag. **Namen bleiben immer erhalten.** Schlägt etwas fehl, bleibt alles beim Alten. Vorher entsteht automatisch ein Backup der betroffenen Tabellen.
7. **Nachkontrolle:** Liste der von Hand angelegten Fahrzeuge mit Hinweis auf mögliche Dopplungen (siehe Abschnitt 3).
8. **Frontend freigeben** – bewusst von Hand per Button, nicht automatisch. „Abbrechen“ ist bis Schritt 6 jederzeit möglich; auch danach muss die Sperre von Hand gelöst werden. Jeder Admin kann die Sperre lösen (falls jemand mitten im Import aufhört); das steht im Audit-Log.

## 5. Oberfläche

**Sprache:** alles in britischem Englisch (`colour`, `licence`, Datumsformat `14 November 2026`), öffentlich wie im Admin. Code und Bezeichner englisch, Doku deutsch.

**Design** (die Entwürfe sind Richtung, nicht 1:1-Vorgabe): Hintergrund `rgb(246,244,238)`, Schrift schwarz, Akzente Violett `rgb(125,124,150)`, Pink `rgb(172,78,149)`, Blau `rgb(67,92,118)`, Schrift Bricolage Grotesque. Hell-/Dunkel-Modus von Anfang an über CSS-Variablen und `data-bs-theme`, Umschalter auf der Startseite, Vorgabe aus dem System, Wahl im `localStorage`.

**Suche**

- Ein großes Suchfeld; beim Tippen Vorschläge, die die Auswahl eingrenzen. Gesucht wird in Name, Typschlüssel und Modellreihe, jeder Namensbestandteil zählt, mehrere Wörter werden UND-verknüpft, Groß-/Kleinschreibung egal. Nur Live-Fahrzeuge.
- Enter oder Abschicken zeigt den Treffer: Name, Typ-Badge, Typschlüssel · Reihe, Disclaimer, „Copy“ mit sichtbarer Rückmeldung. Mehrere Treffer → Trefferliste zur Auswahl.
- Funktioniert auch ohne JavaScript als normales GET-Formular; mit JavaScript ohne Seitenwechsel.
- „Nativ“ heißt hier: sofortige Rückmeldung, Tastaturbedienung (Pfeile, Enter, Esc), sanfte Übergänge mit Rücksicht auf `prefers-reduced-motion`, kein Layout-Springen, Fokus bleibt im Feld.
- Fußzeile: Dashboard, Contact (→ `https://lab09.de/tater`), Version.
- Für Suchmaschinen gesperrt: `X-Robots-Tag: noindex, nofollow` auf jeder Antwort und `robots.txt` mit `Disallow: /`.

**Admin**

- Liste aller Fahrzeuge aus Work mit Filter nach Typ, Suche, Status (Live/Draft), Herkunft (Import/Manual), Kennzahlen oben.
- Bearbeiten in einer Seitenleiste statt des langen V3-Formulars: Name, Werte, Vorschau des erzeugten Textes (nur Anzeige). Aktionen: live stellen, zurückziehen, auf Werksangaben zurücksetzen.
- „New vehicle“ legt ein Fahrzeug von Hand an (Abschnitt 3).
- Bei importierten Fahrzeugen sind Typschlüssel, Reihe und Typ nicht bearbeitbar; bei Hand-Fahrzeugen schon.
- Weitere Bereiche: Import, Change history, Users, Updates (Migrationen, „Regenerate texts“).

**Nutzerverwaltung**

- Eine Rolle. Jeder Admin kann weitere Admins anlegen, deaktivieren und ihnen ein neues Passwort setzen; vorher wird das eigene Passwort erneut abgefragt (`S-35`). Alles steht im Audit-Log (`S-39`).
- Der erste Admin entsteht im Setup. Niemand kann das letzte aktive Konto deaktivieren.

