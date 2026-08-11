# Datei-Map: Plugin „Container Block Designer"

_Stand: 2026-08-10 · Plugin-Version 3.1.85_

Navigationshilfe auf Dateiebene. Die fachlichen Details stehen in `CLAUDE.md`
— dort insbesondere die Abschnitte zum Content-Importer, Block-Serializer,
Seitenimport, zur Icon-Bibliothek und zur Icon-Größe.

Diese Datei entstand am 2026-08-10 im Zuge des Vorhabens „Seitenimport"
(`docs/PLAN-Seitenimport.md`, AP-4.1). Sie war die letzte fehlende
Datei-Map des Projekts; Theme und „Eigene WP Blocks" hatten längst eine.

## Wurzel und Bootstrap

| Datei | Zweck | Wichtige Funktionen/Inhalte | Hängt ab von |
|---|---|---|---|
| `container-block-designer.php` | Hauptdatei, Singleton `ContainerBlockDesigner` | Konstanten (`CBD_VERSION`, `CBD_PLUGIN_DIR`, `CBD_TABLE_BLOCKS`), `load_dependencies()` mit allen `require_once`, `init()` auf `init` Priorität 0, `create_block_editor_role()` | `includes/*` |
| `composer.json` / `composer.lock` | Abhängigkeiten (mPDF, TCPDF-Rückfall, Dev: phpunit, wpcs) | – | – |
| `create-plugin-zip.js` | Verteilungspaket bauen | erhöht die Version selbstständig, ruft vorher `tools/check-php74.php`, stellt **`composer dump-autoload --no-dev`** her und danach wieder den Dev-Autoloader. **Diesen Schritt nie entfernen** — ein Autoloader mit Dev-Paketen bindet phpunit ein und ergibt HTTP 500 auf der Zielinstallation | `tools/check-php74.php` |
| `syntax-check.js` | `php -l` über alle Plugin-Dateien | – | – |
| `remove-debug-logs.js` | Werkzeug zum Entfernen von Debug-Ausgaben | – | – |
| `package.json` | npm-Skripte für die Bauwerkzeuge | – | – |

## Kernklassen (`includes/`)

| Datei | Zweck | Wichtige Funktionen/Inhalte | Hängt ab von |
|---|---|---|---|
| `class-service-container.php` | Einfache Abhängigkeitsverwaltung | `cbd_get_service()`; verwaltet database, style_loader, block_registration, ajax_handler, admin | – |
| `class-autoloader.php` | PSR-4-Rückfall ohne Composer | – | – |
| `class-cbd-database.php` | Datenbank-Zugriffsschicht | delegiert an den Schema-Manager | `Database/class-schema-manager.php` |
| `class-cbd-block-registration.php` | Registriert die Container-Blöcke aus der Datenbank | `register_blocks()`, `render_block()` (fängt Throwables pro Block ab), `render_icon()`, Asset-Einbindung für Editor und Frontend, Isolierung von Inline-Skripten in IIFEs | `CBD_TABLE_BLOCKS`, `class-cbd-style-loader.php` |
| `class-cbd-style-loader.php` | Erzeugt das dynamische CSS je Block-Design | Transient-Zwischenspeicher, `clear_styles_cache()` | `CBD_TABLE_BLOCKS` |
| `class-cbd-admin.php` | Admin-Oberfläche und Menü | `add_admin_menu()`, `render_database_repair_page()` (zeigt die Tabellenspalten), `handle_database_repair()` — **legt `slug` an und füllt sie aus `name`; setzt dabei `cbd_db_version` auf 2.9.0 zurück** | `admin/*.php` |
| `class-cbd-ajax-handler.php` | AJAX-Endpunkte der Blockverwaltung | Speichern, Löschen, Standard-Design setzen | – |
| `class-cbd-content-importer.php` | **Markdown-Parser** (Editor-Import) | `parse_markdown_content()` (public, wird auch vom Seitenimport genutzt), `ajax_parse_import_file`, `ajax_get_style_mappings`, `parse_accordion_directive()`, `markdown_to_html()`, `strip_unsafe_html()` | `CBD_TABLE_BLOCKS` |
| `class-cbd-block-serializer.php` | **Markdown-Abschnitte → `post_content`** (seit 3.1.86) | `html_to_blocks()`, `to_block_array()`, `to_post_content()`, `erzeuge_stable_id()`. Zielmarkup gemessen, siehe `tools/fixtures/` | `serialize_block()` (WP-Kern), `tools/fixtures/referenz-markup.html` als Vorlage |
| `class-cbd-page-importer.php` | **Seitenimport** (seit 3.1.86) | Menü unter dem Theme-Slug `page-manager` (Priorität 20), `ajax_titel_pruefen`, `ajax_seiten_importieren` (nutzt `wp_slash()`!) | `class-cbd-block-serializer.php`, `class-cbd-content-importer.php`, `admin/page-import.php` |
| `class-cbd-design-transfer.php` | Designs exportieren/importieren (Markdown und JSON) | `to_markdown()`, `parse_markdown()`, `parse_file()`, `normalize_designs()` | `admin/design-transfer.php` |
| `class-cbd-icon-library.php` | Eigene SVG-Kachel-Bibliothek | scannt zur Laufzeit das Dateisystem, `parse_stored_value()` (**einzige** Stelle, die das Speicherformat deutet), `flush_cache()` | `assets/icons/`, `uploads/cbd-icons/` |
| `class-cbd-icon-manager.php` | Upload-Seite für eigene Icons | schreibt nur nach `uploads/cbd-icons/`, `sanitize_icon_name()` | `class-cbd-svg-sanitizer.php`, `admin/icon-manager.php` |
| `class-cbd-svg-sanitizer.php` | SVG-Bereinigung per Whitelist | lehnt DOCTYPE/ENTITY ab (XXE), entfernt Skript, Event-Handler, fremde `xlink:href` | – |
| `class-latex-parser.php` | LaTeX-Formeln im Blockinhalt erkennen | `parse_latex()`; Einbindung von KaTeX | `assets/js/latex-renderer.js` |
| `class-latex-bulk-cleanup.php` | Reparatur beschädigter Formeln über alle Beiträge | – | `class-latex-parser.php` |
| `class-cbd-pdf-generator.php` | PDF serverseitig erzeugen | mPDF mit TCPDF-Rückfall | `vendor/` |
| `class-cbd-classroom.php` | Klassenverwaltung, Zeichnungen, Schülerzugang | eigene Tabelle `cbd_classes`. **Geteilte Helfer (AP-2.1):** `zerlege_container_id()` — die **einzige** Stelle, die das Format `<stableId>:pN` deutet — `basis_container_id()` und `behandelte_container($class_id, $page_id)` | `admin/classroom.php` |
| `class-cbd-block-organizer.php` | Container-Blöcke zwischen Seiten kopieren/verschieben | – | `admin/block-organizer.php` |
| `class-cbd-block-reference.php` | Block „Block-Referenz" registrieren | – | `blocks/block-reference/` |
| `class-cbd-blocks-rest-api.php` | REST-Schnittstelle für die Block-Designs | – | – |
| `class-cbd-migration.php` | Stable-IDs für bestehende Container nachtragen | – | `admin/migration.php` |
| `functions.php` | Freistehende Hilfsfunktionen | `cbd_icon_scale_bounds()`, `cbd_sanitize_icon_scale()`, `cbd_get_icon_scale_css()`, `cbd_sanitize_icon_value()`, `cbd_parse_features_from_post()` | – |
| `user-capabilities.php` | Eigene Rechte | `cbd_edit_blocks`, `cbd_edit_styles`, `cbd_admin_blocks` | – |
| `php8-wordpress-compatibility.php` | Dämpft Deprecation-Warnungen von WP-Core unter PHP 8 | – | – |

## Datenbank (`includes/Database/`)

| Datei | Zweck | Wichtige Funktionen/Inhalte | Hängt ab von |
|---|---|---|---|
| `class-schema-manager.php` | Schema und Migrationen | `DB_VERSION` (3.1.61), `CREATE TABLE` für `cbd_blocks` und `cbd_classes`, `run_migrations()`. **Legt `slug` NICHT an** und benennt eine vorgefundene `slug`-Spalte nach `name` um — während `CBD_Admin::handle_database_repair()` `slug` als Pflichtspalte führt. Bekannte Unstimmigkeit, siehe `docs/PLAN-Seitenimport.md`, AP-1.0.fix1 | – |

## Admin-Ansichten (`admin/`)

| Datei | Zweck |
|---|---|
| `dashboard.php` | Einstiegsseite des Plugins |
| `blocks-list.php` | Übersicht aller Block-Designs |
| `new-block.php` / `edit-block.php` | Design anlegen und bearbeiten |
| `settings.php` | Einstellungen, u. a. Icon-Größe |
| `icon-manager.php` | Eigene Icons hochladen |
| `design-transfer.php` | Designs exportieren/importieren |
| `page-import.php` | **Seiten aus Markdown importieren** (seit 3.1.86) |
| `block-organizer.php` | Blöcke zwischen Seiten verschieben |
| `classroom.php` | Klassenverwaltung |
| `migration.php` | Stable-ID-Migration |
| `import-export.php` | **Nicht verlinkt** — in v3.1.50 abgeschaltet, durch `design-transfer.php` ersetzt. Liegt nur noch im Repo |

## Blöcke (`blocks/`)

| Datei | Zweck |
|---|---|
| `block-reference/block.json` + `index.js` + `render.php` + `view.js` + `*.css` | Block „Block-Referenz". **Muss im ZIP enthalten sein** — fehlte bis v3.1.76 und existierte deshalb auf Produktivinstallationen nicht |

## JavaScript (`assets/js/`)

Alle 22 Dateien sind eingebunden (geprüft 2026-08-10). Kein Build-Schritt —
IIFE, Zugriff über `wp.*`-Globale, `console.log` hinter `window.cbdDebug`.

| Datei | Zweck |
|---|---|
| `block-editor.js` | Registriert `container-block-designer/container`. **`ContainerBlockSave` ab Zeile 319 ist die Vorlage für den Serializer**; `generateId()` Zeile 83 erzeugt die `stableId` |
| `content-importer.js` | Import-Dialog im Editor; `insertBlocks()` ab Zeile 309 ist das Referenzverhalten des Serializers |
| `page-importer.js` | Oberfläche der Importseite (Dateiauswahl, Stil-Dialog, Fortschritt) |
| `block-recovery.js` | Repariert ungültige Blöcke beim Öffnen des Editors — Sicherheitsnetz für Markup-Abweichungen |
| `interactivity-store.js` / `interactivity-fallback.js` | Frontend-Verhalten (Aufklappen, Kopieren, Screenshot); Interactivity API mit jQuery-Rückfall |
| `block-numbering.js` | Nummerierung im Browser (WordPress rendert Blöcke nicht in Dokumentreihenfolge) |
| `latex-renderer.js` | KaTeX-Anbindung |
| `floating-pdf-button.js` | PDF-Knopf und Werkzeugleiste; **stylt inline per jQuery**, nicht über CSS |
| `pdf-server-side.js` / `html2pdf-loader.js` | PDF-Erzeugung |
| `icon-picker.js` | Icon-Auswahl im Admin |
| `board-mode.js` | Tafelmodus |
| `classroom-admin.js` / `classroom-frontend.js` / `classroom-page-filter.js` | Klassenverwaltung |
| `personal-notes-manager.js` | Persönliche Notizen |
| `admin.js`, `admin-common.js`, `admin-blocks-list.js`, `admin-import-export.js`, `admin-live-preview-fix.js` | Admin-Oberfläche |

## Gestaltung (`assets/css/`)

| Datei | Zweck |
|---|---|
| `cbd-frontend-clean.css` | **Die lebende Frontend-Datei.** Kopfzeilen-Icon, Icon-Größen je Bibliothek, `--cbd-icon-scale` als Rückfallwert |
| `custom-icons.css` | Eigene SVG-Kacheln und Nummern-Kacheln; hängt per Handle an `cbd-frontend-clean` |
| `block-base.css`, `block-responsive.css`, `editor-base.css`, `frontend-consolidated.css` | vom Style-Loader eingebunden |
| `block-editor.css` | Editor-Gestaltung (die Frontend-Skalierung erreicht ihn **nicht**) |
| `content-importer.css` | Import-Dialog im Editor |
| `page-importer.css` | Importseite (seit 3.1.86) |
| `latex-formulas.css`, `icon-picker.css`, `board-mode.css`, `interactivity-api.css`, `personal-notes-manager.css`, `classroom-*.css`, `admin*.css`, `edit-block-form.css`, `new-block-form.css` | jeweils zum gleichnamigen Bereich |
| ~~`frontend.css`~~, ~~`frontend-positioning.css`~~, ~~`unified-frontend.css`~~ | **TOTE DATEIEN.** In keinem `wp_enqueue_style()` referenziert (geprüft 2026-08-10), enthalten aber dieselben Selektoren wie die lebenden. Wer dort ändert, sieht nichts passieren |

## Werkzeuge und Tests (`tools/`)

**Wird bewusst NICHT ins Verteilungs-ZIP aufgenommen** (`create-plugin-zip.js`
listet nur `admin`, `assets`, `blocks`, `includes`, `vendor`, `languages`).

| Datei | Zweck |
|---|---|
| `check-php74.php` | Prüft alle Plugin-Dateien gegen PHP 7.4. **Zwingend**, weil lokal PHP 8.x läuft und `php -l` 8.0-Syntax nicht meldet, die Zielumgebung aber 7.4.33 ist. Läuft automatisch im ZIP-Bau |
| `test-block-serializer.php` | 71 Prüfungen in vier Gruppen: Fragmentebene, Dokumentebene, Markup-Treue gegen die Fixture, Delimiter-Bilanz |
| `test-design-transfer.php` | Beide Transferformate, Rundläufe, heikle Werte |
| `test-icon-library.php`, `test-icon-manager.php`, `test-icon-value.php`, `test-icon-scale.php` | Icon-Bestand, Dateinamen, Speicherformat, Größenskalierung |
| `test-svg-sanitizer.php` | 36 Prüfungen: Angriffsmuster raus, legitime Kacheln heil |
| `fixtures/referenz-markup.html` | **Gemessenes Zielmarkup** aus einer echten Editor-Speicherung — Vorlage für den Serializer |
| `fixtures/referenz-umgebung.md` | Herkunft, Versionen und was daraus abgelesen wurde |
| `test-classroom-gate.php` | Klassen-Durchlass: Zerlegung der `container_id`, behandelte Container je Seite und Klasse. Eine Prüfung wacht darüber, dass die Suffix-Regel `:pN` nur **einmal** im Code steht |

## Dokumentation (`docs/`)

| Datei | Zweck |
|---|---|
| `VERBESSERUNGSPLAN.md` … `-5.md` | Review-Runden 2026-07, Arbeitspakete mit Status |
| `AENDERUNGEN-UND-UPLOAD.md` | Gesamtübersicht plus Upload- und Recovery-Anleitung |
| `PLAN-Seitenimport.md` | Projektplan „Seitenimport + Sammelaktionen" mit Statustabelle und Testprotokoll |
| `ERWEITERUNGSANALYSE-Seitenimport.md` | Analyse, die zum Plan führte (Abschnitt 3.2 enthält eine Korrektur) |
| `pruefung-produktiv.js` | Konsolenskript: liest WordPress-Version, Tabellenspalten und Designs der Produktivinstallation |
| `pruefung-blockmarkup.js` | Konsolenskript: liest das Blockmarkup einer Seite über `getEditedPostContent()` — für das Neuerheben der Fixture nach einem Update |
| `archiv/` | historische Status-Notizen, nicht gepflegt |

## Sammelzeilen

| Pfad | Anmerkung |
|---|---|
| `vendor/` | Composer-Abhängigkeiten (mPDF, TCPDF). **Versioniert und im ZIP enthalten** — nicht mit `.gitignore` ausschließen |
| `languages/` | Übersetzungsdateien |
| `assets/icons/` | Ausgelieferte SVG-Kacheln (`kategorien/`, `zahlen/`, `ui/`). Bestand steht nicht im Code, `CBD_Icon_Library` scannt zur Laufzeit |
| `dist/` | Gebaute ZIPs, nicht versioniert |
| `node_modules/` | nur für die Bauwerkzeuge, nicht versioniert |
