# Plan: Seitenimport mit Elternseite

_Erstellt am: 2026-08-21 · Eigenständiges Kleinvorhaben · Komponente: CDB-Designer (v3.1.94)_

**Dieser Plan ist unabhängig von den drei Geschwisterplänen**
(`PLAN-Aktionsleiste-Autoausblenden.md`, `PLAN-Formeln-in-Blocktiteln.md`,
`Theme/docs/PLAN-Inhaltsverzeichnis-Navigation.md`). Ihre Dateimengen sind
disjunkt; alle vier dürfen gleichzeitig laufen.

## 0. Anweisungen für den ausführenden Agenten

Du hast keinen Zugriff auf das Gespräch, in dem dieser Plan entstand. Er ist
die einzige Wahrheitsquelle.

1. Bearbeite die Arbeitspakete der Reihe nach. Bleib strikt im Scope; fällt
   dir Verbesserungspotenzial außerhalb auf, notiere es, setze es nicht um.
2. **TDD:** Tests zuerst, Fehlschlag bestätigen, roter Commit, dann
   implementieren bis grün. **Tests niemals abändern, damit sie bestehen.**
   Hältst du einen Test für inhaltlich falsch, stoppe und melde es.
3. Ein AP ohne bestandene Tests ist nicht fertig.
4. Commit-Nachrichten **ohne Anführungszeichen** — die Shell dieses Projekts
   ist PowerShell und übergibt den Text sonst als Pathspec. Mehrzeilige
   Nachrichten im Bash-Werkzeug per echtem Heredoc, **keine**
   PowerShell-Here-Strings.
5. Kein `git add .` und kein `git add -A` — immer nur die eigenen Pfade
   einzeln nennen.

**Projektspezifische Pflichtregeln:**

6. **PHP 7.4.** Zielumgebung ist 7.4.33, lokal läuft PHP 8.x, und `php -l`
   meldet 8.0-Syntax **nicht** als Fehler. Nach jeder PHP-Änderung
   `php tools/check-php74.php` grün bekommen. Verboten: `match`, Nullsafe,
   Constructor Promotion, benannte Argumente, `str_contains`,
   `str_starts_with`, `str_ends_with`, `mixed`, Union Types.
7. **`wp_unslash()` vor jedem `json_decode()`** von `$_POST`. WordPress
   slasht `$_POST`; ohne Entfernen scheitert das Dekodieren stillschweigend.
   Dieser Fehler hat schon einmal alle Icon-Werte zerstört (v3.1.78).
8. **`wp_slash()` vor `wp_insert_post()`.** Ohne die Maskierung entfernt
   WordPress **jeden Backslash** — aus `\cdot` wird `cdot`, jede LaTeX-Formel
   im importierten Inhalt wäre still zerstört.
9. **Keine Versionsnummer erhöhen** — das erledigt `create-plugin-zip.js`
   selbst.
10. **Debug-Ausgaben gaten:** `console.log` nur hinter `window.cbdDebug`;
    PHP-Informationslogs hinter `if (defined('WP_DEBUG') && WP_DEBUG)`.

## 1. Ziel

Der Seitenimport (**Seitenmanager → Seiten importieren**) legt heute jede
Seite auf oberster Ebene an. Künftig soll im Dialog eine **Elternseite**
wählbar sein; **alle** Seiten eines Importlaufs bekommen dieselbe.

Nutzen: Ein Kapitel aus mehreren Markdown-Dateien landet direkt an der
richtigen Stelle im Seitenbaum, statt hinterher von Hand einsortiert zu
werden.

## 2. Nicht-Ziele

- **Keine Elternseite je Datei.** Ausdrücklich alle Seiten eines Laufs
  gleich — das ist die Anforderung, nicht eine Vereinfachung.
- **Kein Verschieben bestehender Seiten.** Der Importer legt an, er sortiert
  nicht um. Dafür gibt es die Sammelaktionen des Seitenmanagers im Theme.
- **Kein Umbau des Markdown-Parsers.** `parse_markdown_content()` ist mit dem
  Editor-Importer geteilt; jede Änderung träfe beide Wege.
- **Das Theme wird nicht geändert.** Der Menüeintrag hängt zwar am
  Theme-Slug `page-manager`, wird aber nur über den vorhandenen Rückfall
  gelesen.
- **Kein neuer Eingabeweg.** Weiterhin nur hochgeladene `.md`-Dateien.

## 3. Kontext & Constraints

- **Komponente:** `Plugins/CDB-Designer/`, Version 3.1.94, Branch `main`.
- **Umgebung produktiv:** All-inkl Shared Hosting, PHP 7.4.33, kein SSH,
  kein WP-CLI.
- **Testumgebung:** `C:\allinkl-testserver`, Start über `start-server.cmd`.
  WordPress unter `http://fos.localhost:8080/` — per `curl` nur mit Kopfzeile
  `Host: fos.localhost` erreichbar, und die Seiten antworten mit einer
  Weiterleitung, also `-L` verwenden.
  Installationspfad `C:\allinkl-testserver\www\htdocs\w0000001\fos`.
  Admin `admin` / `Testserver2026!`. Datenbank `d0000001` / `d0000001` /
  `EBZvYRyrEM34gtfmv3Z8`, Client
  `C:\allinkl-testserver\mariadb\bin\mysql.exe`.
  **Die Plugins liegen dort als Kopie, nicht als Verknüpfung** — nach einer
  Änderung dorthin kopieren, sonst prüfst du den alten Stand.
  Bei HTTP 503 liegt eine `.maintenance`-Datei des Auto-Updaters herum: sie
  und `wp-content/upgrade/wordpress-*` löschen.
- **Konventionen:** `CLAUDE.md` und `reference_file_map.md` des Plugins haben
  Vorrang. Keine neuen Konventionen erfinden.

## 4. Ausgangslage (aus der Datei-Map)

| Datei | Rolle heute | Änderung |
|---|---|---|
| `includes/class-cbd-page-importer.php` | Seitenimport: `ajax_titel_pruefen`, `ajax_seiten_importieren`; **`wp_insert_post()` mit fest verdrahtetem `post_parent` = 0** (etwa Zeile 319–324) | ändern |
| `admin/page-import.php` | Ansicht der Importseite | ändern |
| `assets/js/page-importer.js` | Oberfläche: Dateiauswahl, Stil-Dialog, Fortschritt; ein AJAX-Aufruf **je Datei** | ändern |
| `tools/test-page-importer.php` | – | **neu** |
| `includes/class-cbd-content-importer.php` | liefert `parse_markdown_content()` | nur lesen |
| `includes/class-cbd-block-serializer.php` | baut den `post_content` | nur lesen |

**Endpunkte (bestehend):** `cbd_import_pages` und `cbd_check_page_titles` mit
Nonce `cbd_page_import`; `cbd_parse_import_file` und `cbd_get_style_mappings`
mit Nonce `cbd_content_import`. Capability `edit_pages` — also auch für die
Rolle **Block-Redakteur**.

**Ein Aufruf je Datei, nicht ein Sammelaufruf.** Das ist bewusst so: Der
Fortschritt bleibt sichtbar, ein PHP-Timeout bei vielen Dateien ist
ausgeschlossen, und ein Fehler betrifft nur eine Datei. Die gewählte
Elternseite muss deshalb bei **jedem** dieser Aufrufe mitgehen.

## 5. Architekturentscheidungen

| Entscheidung | Begründung | Verworfene Alternative |
|---|---|---|
| **Die Elternseite geht als eigener Parameter bei jedem AJAX-Aufruf mit, statt serverseitig zwischengespeichert zu werden** | Der Import feuert einen Aufruf je Datei. Ein Zwischenspeicher müsste angelegt, gefunden und wieder aufgeräumt werden und überlebte einen Abbruch mitten im Lauf als Leiche | Transient je Importlauf: mehr Zustand, kein Gewinn |
| **Geprüft wird serverseitig, nicht im Browser** | Das Auswahlfeld ist eine Bequemlichkeit, keine Sicherung. Der Server muss ohnehin prüfen, ob die ID existiert und eine Seite ist — sonst entstünden Seiten unter einer gelöschten oder fremden ID | Nur clientseitig prüfen: der Endpunkt bliebe offen |
| **Der Wert `0` bleibt gültig und ist die Vorgabe** | „Oberste Ebene" ist das heutige Verhalten und muss ohne Zutun erhalten bleiben | Elternseite erzwingen: Rückwärtsbruch |
| **Ein ungültiger Wert fällt stillschweigend auf `0` zurück, statt den Lauf abzubrechen** | Der Import läuft Datei für Datei. Ein Abbruch in der Mitte ließe die Hälfte der Seiten angelegt und die andere nicht — ein schlechterer Zustand als eine Seite auf oberster Ebene, die sich im Seitenmanager verschieben lässt | Fehler werfen: zerreißt den Lauf |
| **Die Auswahl zeigt den Seitenbaum eingerückt über `wp_dropdown_pages()`** | Bei rund 260 Seiten ist eine alphabetische Liste unbrauchbar. Die Funktion erledigt Einrückung und Sortierung und ist WordPress-Bordmittel | Eigener Baumaufbau: eine weitere Fassung derselben Logik im Projekt |
| **Keine Seite wird aus der Auswahl ausgeschlossen** | Jede veröffentlichte oder entworfene Seite darf Elternteil sein. Eine Einschränkung wäre eine Annahme über die Arbeitsweise des Nutzers | Filter auf Tiefe oder Vorlage: bevormundend |

## 6. Risiken & Rollback

| Risiko | Auswirkung | Gegenmaßnahme |
|---|---|---|
| **Ungeprüfte `parent_id` erzeugt Seiten unter einer fremden oder gelöschten ID** | mittel | Serverseitig `get_post()`, Typ `page`, Status nicht `trash`. Alles andere fällt auf `0` zurück |
| **`wp_slash()` beim Einfügen fällt weg** | **hoch** | Es steht bereits im Code und darf nicht verloren gehen. Ohne die Maskierung verliert jeder importierte Inhalt seine Backslashes und damit jede LaTeX-Formel. AP-3 prüft das ausdrücklich |
| **Der Fortschrittsdialog bricht ab, weil ein Parameter fehlt** | gering | Der neue Parameter ist optional; fehlt er, gilt `0` und der Import verhält sich wie bisher |
| **PHP-8.0-Syntax gelangt ins Plugin** | hoch | `php tools/check-php74.php` als Akzeptanzkriterium jedes PHP-APs |

**Rollback:** Ein Datenbank-Eingriff findet nicht statt, das Schema bleibt
unverändert. Vor dem ersten AP `git tag vor-importer-elternseite` setzen,
Rückweg ist `git reset --hard vor-importer-elternseite`. Bereits importierte
Seiten sind gewöhnliche Entwürfe und im Seitenmanager verschiebbar.

## 7. Arbeitspakete

### AP-1: Elternseite serverseitig annehmen und prüfen

**Modell:** sonnet
**Dateien:** `includes/class-cbd-page-importer.php`,
`tools/test-page-importer.php` (neu)

**Umsetzung:**

1. Neue private Methode `bereinige_elternseite($roh): int`:
   - `wp_unslash()`, dann `filter_var($roh, FILTER_VALIDATE_INT)` —
     **nicht** `(int)`. Eine überlange Ziffernfolge erzeugt beim Cast ab
     PHP 8.1 eine Warnung und wird auf `PHP_INT_MAX` abgebildet, statt
     abgelehnt zu werden. Dieselbe Regel steht mit derselben Begründung
     bereits in `includes/class-cbd-design-transfer.php` (etwa Zeile
     911–915).
   - Werte `<= 0` ergeben `0` (oberste Ebene).
   - Sonst `get_post()`; fehlt der Beitrag, ist er nicht vom Typ `page` oder
     steht er auf `trash`, ergibt sich `0`.
2. `ajax_seiten_importieren()` liest `$_POST['parent_id']` durch diese
   Methode und gibt das Ergebnis an `wp_insert_post()` weiter — aus
   `post_parent` gleich `0` wird `post_parent` gleich `$eltern_id`.
3. `wp_slash()` und die übrige Kette bleiben unverändert.

**Akzeptanzkriterien:**

- AK1: Fehlender, leerer, nicht numerischer, negativer und `0`-Wert ergeben
  alle `0`.
- AK2: Eine gültige Seiten-ID wird unverändert durchgereicht.
- AK3: Die ID eines Beitrags, einer gelöschten Seite und einer nicht
  existierenden ID ergeben `0` — **ohne** Fehlermeldung an den Nutzer.
- AK4: Eine 20-stellige Ziffernfolge ergibt `0` und erzeugt **keine**
  PHP-Warnung. Nachweis über einen eigenen `set_error_handler`, der jede
  Warnung zum Fehlschlag macht — ohne ihn sieht der Harnisch die Warnung
  nicht, weil sie den Ablauf nicht unterbricht.
- AK5: Ein Wert mit vorangestellten Slashes, wie er aus `$_POST` kommt, wird
  korrekt gelesen.
- AK6: `php tools/check-php74.php` ist grün.

**Tests (TDD):** `tools/test-page-importer.php`, Prüfharnisch **ohne
WordPress** nach dem Muster von `tools/test-block-content-api.php`
(CLI-Wächter plus Stubs). Rote Tests zuerst committen.

---

### AP-2: Auswahlfeld im Dialog und Übergabe je Datei

**Modell:** sonnet
**Abhängigkeiten:** AP-1
**Dateien:** `admin/page-import.php`, `assets/js/page-importer.js`

**Umsetzung:**

1. In `admin/page-import.php` ein Auswahlfeld über `wp_dropdown_pages()`:
   `show_option_none` = „— oberste Ebene —", `option_none_value` = `0`,
   `name` und `id` = `cbd-import-parent`, `sort_column` =
   `menu_order,post_title`. Dazu ein Erklärsatz, dass **alle** Seiten dieses
   Laufs dieselbe Elternseite bekommen.
2. In `assets/js/page-importer.js` den Wert **einmal vor Beginn** des Laufs
   auslesen und bei **jedem** `cbd_import_pages`-Aufruf als `parent_id`
   mitschicken. Nicht je Datei neu lesen — sonst änderte eine Bedienung
   während des Laufs die Zuordnung mitten drin.
3. Während des Laufs das Auswahlfeld auf `disabled` setzen und danach wieder
   freigeben.

**Akzeptanzkriterien:**

- AK1: Ohne Auswahl entstehen Seiten wie bisher auf oberster Ebene.
- AK2: Mit Auswahl tragen **alle** Seiten des Laufs dieselbe Elternseite.
- AK3: Das Feld ist während des Laufs nicht bedienbar.
- AK4: `node --check assets/js/page-importer.js` ist grün, und es gibt keine
  `console.log` außerhalb von `window.cbdDebug`.

---

### AP-3: Abnahme auf dem Testserver

**Modell:** sonnet
**Abhängigkeiten:** AP-1, AP-2

1. Die geänderten Dateien auf den Testserver kopieren — die Plugins liegen
   dort als Kopie, nicht als Verknüpfung.
2. Drei `.md`-Dateien in einem Lauf importieren, einmal **ohne** und einmal
   **mit** Elternseite.
3. In der Datenbank prüfen: Alle drei Seiten eines Laufs tragen dieselbe
   `post_parent`.
4. **Regression, die empfindlichste Stelle dieses Codewegs:** Eine
   importierte Seite mit LaTeX im Inhalt öffnen und prüfen, dass die
   Backslashes einfach sind (`\cdot`, nicht `cdot`). Geht `wp_slash()`
   verloren, ist jede Formel still zerstört.
5. Denselben Ablauf als **Block-Redakteur** wiederholen (Capability
   `edit_pages`).
6. Dublettenprüfung und Fortschrittsanzeige weiterhin funktionsfähig.
7. `debug.log` auf neue Warnungen prüfen.

**Akzeptanzkriterien:** AK1 bis AK7 entsprechend den sieben Schritten;
`debug.log` ohne neue Zeile.

---

### AP-4: Dokumentation

**Modell:** sonnet
**Abhängigkeiten:** AP-3
**Dateien:** `CLAUDE.md`, `reference_file_map.md`, dieser Plan

1. `CLAUDE.md`, Abschnitt **„Seitenimport (Markdown → Seiten)"**: den neuen
   Parameter beschreiben, die stillschweigende Rückfallregel auf `0`
   ausdrücklich begründen, und festhalten, dass die Elternseite für den
   ganzen Lauf gilt.
2. `reference_file_map.md`: die Zeilen für `class-cbd-page-importer.php`,
   `admin/page-import.php` und `assets/js/page-importer.js` ergänzen, dazu
   eine neue Zeile für `tools/test-page-importer.php`.
3. Abschnitt 8 und 9 dieses Plans vollständig füllen.
4. **Nur mit dem Edit-Werkzeug arbeiten**, niemals per
   PowerShell-Lese-Schreib-Zyklus: `Get-Content` mit `Set-Content -Encoding
   UTF8` doppelkodiert alle Umlaute, und ein Latin-1-Reparaturversuch
   verliert Zeichen unwiederbringlich. Nachweis:
   `grep -c 'Ã\|â€' <datei>` liefert 0.

## 8. Status

| AP | Titel | Modell | Abhängig von | Status |
|---|---|---|---|---|
| AP-1 | Elternseite serverseitig annehmen und prüfen | sonnet | – | ☐ |
| AP-2 | Auswahlfeld im Dialog und Übergabe je Datei | sonnet | 1 | ☐ |
| AP-3 | Abnahme auf dem Testserver | sonnet | 1, 2 | ☐ |
| AP-4 | Dokumentation | sonnet | 3 | ☐ |

## 9. Testprotokoll

| AP | Test | Ergebnis | Datum |
|---|---|---|---|
| AP-1 | `php tools/test-page-importer.php` | – | – |
| AP-1 | `php tools/check-php74.php` | – | – |
| AP-2 | `node --check assets/js/page-importer.js` | – | – |
| AP-3 | Import mit und ohne Elternseite, Prüfung in der Datenbank | – | – |
| AP-3 | Regression: LaTeX-Backslashes einfach | – | – |
| AP-3 | Derselbe Ablauf als Block-Redakteur | – | – |
| AP-4 | Mojibake-Kontrolle | – | – |
