# Erweiterungsanalyse: Seitenimport aus Markdown + Bulk-Optionen im Seitenmanager

_Stand: 2026-08-10 · Grundlage: CDB-Designer 3.1.80, Theme 1.5.75_

## 1. Kurzbeschreibung

Zwei zusammengehörige, aber technisch unabhängige Erweiterungen:

1. **Seitenimporter:** Aus mehreren Markdown-Dateien entsteht je Datei **eine
   WordPress-Seite** im Status *Entwurf* auf der obersten Ebene
   (`post_parent = 0`). Der Seitentitel kommt aus der ersten `# `-Zeile, der
   Inhalt aus denselben Blöcken, die der bestehende Blockimporter im Editor
   erzeugen würde. Erreichbar als Untermenü **des Seitenmanagers**.
2. **Bulk-Optionen im Seitenmanager:** Mehrfachauswahl im Seitenbaum mit vier
   Sammelaktionen — Status umschalten, Papierkorb, Elternseite zuweisen,
   aus Inhaltsverzeichnis/Navigation ausnehmen.

Nutzen: Der heutige Weg ist „pro Kapitel eine leere Seite anlegen, Editor
öffnen, Menü ⋮ → Inhalt importieren, Stile zuweisen, speichern" — bei einem
Skriptum mit 30 Kapiteln also 30 Durchläufe. Künftig: 30 Dateien ablegen,
Stile **einmal** zuweisen, fertig. Die Bulk-Optionen bedienen die direkte
Folge davon: 30 frische Entwürfe wollen gemeinsam einsortiert und
veröffentlicht werden.

## 2. Verständnis des Ist-Projekts

**Projektzweck:** WordPress-Website für Unterrichtsskripten (Chemie/Biochemie).
Theme „FOS Online Schulbuch" + zwei Plugins (CDB-Designer für gestaltete
Container-Blöcke, „Eigene WP Blocks" für interaktive Lernbausteine).

**Berührte Module:**

| Modul | Ort | Rolle für diese Erweiterung |
|---|---|---|
| Content-Importer | `Plugins/CDB-Designer/includes/class-cbd-content-importer.php` | Markdown-Parser (PHP) — wird **unverändert** wiederverwendet |
| Content-Importer-UI | `Plugins/CDB-Designer/assets/js/content-importer.js` | Block-Erzeugung (JS) — dient als **Referenzverhalten**, wird nicht verändert |
| Container-Block | `Plugins/CDB-Designer/assets/js/block-editor.js` | Zielblock, **statisches `save()`** → Markup muss exakt nachgebildet werden |
| Accordion-Block | `Plugins/Eigene WP Blocks/blocks/accordion/` | optionaler Zielblock, `save()` gibt nur `InnerBlocks.Content` aus |
| Seitenmanager | `Theme/includes/admin/page-manager.php` + `src/js/page-manager.js` | Menü-Elternteil für den Import; Ort der Bulk-Optionen |
| Glossar-System | `Theme/functions.php` (`save_post`-Hooks) | reagiert auf neu angelegte Seiten — siehe Regressionsfläche |

**Geltende Konventionen, die eingehalten werden müssen:**

- CDB-Designer bleibt **PHP 7.4**-kompatibel; `tools/check-php74.php` läuft
  zwingend im ZIP-Bau. Kein `match`, keine Named Arguments, kein Nullsafe.
- ZIPs **nur** über `node create-plugin-zip.js` (Autoloader-Falle, siehe
  CDB-CLAUDE.md). Ausgeliefert werden `admin/`, `assets/`, `blocks/`,
  `includes/`, `vendor/`, `languages/` — `tools/` bewusst **nicht**.
- Testharnesse laufen headless als `tools/test-*.php` mit wenigen
  WordPress-Stubs (Muster: `test-design-transfer.php`, `test-icon-value.php`).
- Debug-Ausgaben hinter `WP_DEBUG` (PHP) bzw. `window.cbdDebug` (JS).
- AJAX: immer `check_ajax_referer()` + Capability, Rechteprüfung **je
  Einzelseite** (bestehendes Muster im Seitenmanager).
- Theme: JS/CSS über Vite aus `src/` nach `dist/`; `npm run build` erhöht die
  Patch-Version selbstständig. Neue Dateitypen müssen in
  `create-theme-zip.js` freigegeben werden.
- Deutschsprachige Oberfläche und Kommentare.

## 3. Einordnung in die Architektur

### 3.1 Der eigentliche Bruch: Block-Erzeugung liegt heute im Browser

Der Blockimporter ist zweigeteilt:

```
Markdown ──PHP──> sections/groups ──AJAX──> JS ──wp.blocks.createBlock──> Editor
           ^^^ wiederverwendbar          ^^^ NICHT wiederverwendbar
```

`insertBlocks()` in `content-importer.js` ruft `wp.blocks.createBlock()` und
schiebt die Blöcke über `dispatch('core/block-editor')` in den geöffneten
Editor. Beim Seitenimport gibt es **keinen Editor**: gebraucht wird ein
`post_content`-String mit serialisiertem Block-Markup.

**Entscheidung: ein PHP-Serializer.** Eine neue Klasse
`CBD_Block_Serializer` bildet `insertBlocks()` in PHP nach und liefert
Block-Markup. Begründung gegen die Alternative (Block-Registry im Browser auf
der Admin-Seite laden und `wp.blocks.serialize()` nutzen): Das erforderte
`wp-editor`/`wp-block-library` auf einer Nicht-Editor-Seite plus ein
künstliches Auslösen von `enqueue_block_editor_assets` — dabei würde auch
`content-importer.js` geladen, das `wp.editor || wp.editPost` destrukturiert
und `registerPlugin()` aufruft. Das ist mehr Fremdkörper als der Serializer
und im Fehlerfall schwerer zu diagnostizieren.

**Der Preis dieser Entscheidung** ist die Markup-Treue: Weicht das erzeugte
Markup von der `save()`-Ausgabe ab, zeigt der Editor „Dieser Block enthält
unerwarteten oder ungültigen Inhalt". Gegenmaßnahmen in Abschnitt 9.

### 3.2 Andockpunkt der Import-Seite

`add_submenu_page('page-manager', …)` in `CBD_Admin::add_admin_menu()` bzw.
einer eigenen kleinen Klasse. Damit erscheint der Import **im Seitenmanager-Menü**,
ohne dass das Theme angefasst werden muss — der gesamte Seitenimport liegt in
einem Repo und wird mit einem ZIP ausgeliefert.

**Falle, die zwingend beachtet werden muss:** Plugins laden **vor** der
`functions.php` des Themes. CDBs `admin_menu`-Callback wird deshalb zuerst
registriert und läuft zuerst — zu diesem Zeitpunkt existiert das Menü
`page-manager` noch nicht und `add_submenu_page()` scheitert **stillschweigend**
(kein Fehler, der Eintrag fehlt einfach). Die Registrierung muss daher auf
`admin_menu` mit **Priorität 20** (oder höher) laufen. Fehlt der Seitenmanager
ganz (Theme gewechselt), fällt der Eintrag auf das Menü
`container-block-designer` zurück.

### 3.3 Andockpunkt der Bulk-Optionen

Vollständig im Theme, entlang der bestehenden AJAX-Struktur des
Seitenmanagers: ein zusätzlicher Handler `page_manager_bulk_action` neben den
vier vorhandenen, dazu Auswahl-Logik in `src/js/page-manager.js` und Gestaltung
in `src/css/page-manager.css`. Keine neuen Vite-Einstiegspunkte nötig — die
beiden Dateien sind bereits Einstiegspunkte.

## 4. Betroffene Dateien

### CDB-Designer (Seitenimport)

| Datei | Rolle heute | Änderung |
|---|---|---|
| `includes/class-cbd-block-serializer.php` | – | **neu** — sections+Mapping → Block-Markup |
| `includes/class-cbd-page-importer.php` | – | **neu** — Menü, Asset-Einbindung, AJAX `cbd_import_pages` |
| `admin/page-import.php` | – | **neu** — View der Importseite |
| `assets/js/page-importer.js` | – | **neu** — Mehrfach-Dateiauswahl, Stil-Dialog, Fortschritt |
| `assets/css/page-importer.css` | – | **neu** — Gestaltung (kann `content-importer.css` als Vorlage nehmen) |
| `tools/test-block-serializer.php` | – | **neu** — headless Testharness |
| `container-block-designer.php` | Bootstrap, `load_dependencies()` | ändern — zwei `require_once` ergänzen |
| `includes/class-cbd-content-importer.php` | Markdown-Parser + AJAX | **nur lesen** — `parse_markdown_content()` und `attach_style_suggestions()` werden aufgerufen, nicht verändert |
| `assets/js/content-importer.js` | Block-Erzeugung im Editor | **nur lesen** — Referenzverhalten für den Serializer |
| `assets/js/block-editor.js` | Container-Block, `ContainerBlockSave` | **nur lesen** — Markup-Vorlage |
| `docs/ERWEITERUNGSANALYSE-Seitenimport.md` | – | diese Datei |
| `CLAUDE.md` | Arbeitsdoku | ändern — Abschnitt „Seitenimport" ergänzen |

### Theme (Bulk-Optionen)

| Datei | Rolle heute | Änderung |
|---|---|---|
| `includes/admin/page-manager.php` | Seitenbaum, 4 AJAX-Aktionen | ändern — Checkboxen, Bulk-Leiste, Handler `page_manager_bulk_action` |
| `src/js/page-manager.js` | Drag-Sortierung | ändern — Auswahl, Shift-Bereichsauswahl, Bestätigung |
| `src/css/page-manager.css` | Gestaltung | ändern — Checkbox-Spalte, Bulk-Leiste |
| `reference_file_map.md` | Datei-Map | ändern — geänderte Rollen nachziehen |
| `CLAUDE.md` | Arbeitsdoku | ändern — Bulk-Aktionen dokumentieren |
| `vite.config.js` | Build | **nur lesen** — Einstiegspunkte reichen aus |
| `create-theme-zip.js` | Verteilung | **nur lesen** — `includes/**/*.php`, `dist/**` bereits freigegeben |

## 5. Wiederverwendung statt Neubau

- `CBD_Content_Importer::parse_markdown_content($content, $designs)` — der
  komplette Markdown-Parser inklusive Accordion-Direktive, Titel-Fallback-Kette
  und Gruppenbildung. **Public**, direkt aufrufbar.
- AJAX `cbd_parse_import_file` — liefert bereits `sections`, `groups`,
  `stats` **inklusive** Stil-Vorschlägen. Die Import-Seite kann diesen Endpunkt
  unverändert je Datei aufrufen; nur der Nonce-Kontext (`cbd_content_import`)
  muss auf der neuen Seite lokalisiert werden.
- `attach_style_suggestions()` / `match_style_for_label()` — Namensabgleich
  H2 ↔ Block-Design (exakt / Stammform / Teilstring).
- `Simple_Clean_Page_Manager::would_create_circular_reference()` — Zirkelprüfung
  für die Bulk-Aktion „Elternseite zuweisen".
- `serialize_blocks()` (WordPress-Kern) — erzeugt Kommentar-Trenner und
  Attribut-JSON in genau der Form, die der Parser erwartet. Der Serializer baut
  nur die Block-Arrays, nicht die Delimiter.
- `assets/css/content-importer.css` — Gestaltungsvorlage für Dropzone,
  Stil-Zeilen und Badges.

## 6. Integrationspunkte & Schnittstellen

### Datenfluss Seitenimport

```
N × .md (Browser, FileReader)
   └─ je Datei ──> AJAX cbd_parse_import_file ──> sections + groups + Vorschläge
        └─ Zusammenführung aller Gruppen über alle Dateien (JS)
             └─ EIN Stil-Dialog (exakte Treffer vorbelegt)
                  └─ je Datei ──> AJAX cbd_import_pages
                       ├─ CBD_Block_Serializer::to_post_content(sections, mappings)
                       └─ wp_insert_post(post_type=page, post_status=draft, post_parent=0)
```

Ein AJAX-Aufruf **pro Datei** statt einem Sammelaufruf: der Fortschritt bleibt
sichtbar und ein PHP-Timeout bei 40 Dateien ist ausgeschlossen.

### Signatur des Serializers (Vorschlag)

```php
CBD_Block_Serializer::to_post_content(
    array $sections,      // aus parse_markdown_content()
    array $groups,        // aus parse_markdown_content() (Accordion-Optionen)
    array $style_mappings,// groupKey => Design-Slug | '__none__'
    array $accordion_opt_out = [] // groupKey => true, wenn abgewählt
): string
```

Die Abbildung folgt `insertBlocks()` Zeile für Zeile:

| Quelle (HTML aus dem Parser) | Zielblock |
|---|---|
| `<h3>`–`<h6>` | `core/heading` mit `level` |
| `<p>` | `core/paragraph` |
| `<ul>` / `<ol>` | `core/list` mit `core/list-item`-Kindern |
| `<table>` | `core/table` (`head`/`body`) |
| alles andere | `core/paragraph` |
| keine Blöcke erzeugbar | `core/freeform` mit dem Roh-HTML |
| Gruppe mit Stil | `container-block-designer/container` (`selectedBlock`, `blockTitle`) als Hülle |
| Gruppe ohne Stil | `core/heading` (Ebene 3) mit `blockTitle` + Inhalt direkt |
| Accordion-Gruppe | ein `modular-blocks/accordion`, je Abschnitt Überschrift + Inhalt |

**Bewusste Abweichung vom JS bei Listen:** `content-importer.js` erzeugt
`core/list` mit dem **veralteten** Attribut `values`; der Editor migriert das
beim Laden auf `core/list` + `core/list-item`. Der Serializer schreibt sofort
die migrierte Form — sonst stünde in der Datenbank Markup, das der Editor beim
ersten Öffnen für ungültig hält.

**Der Blocktyp `modular-blocks/accordion` darf nur erzeugt werden, wenn er
registriert ist** (`WP_Block_Type_Registry::get_instance()->is_registered()`).
Das ist das PHP-Gegenstück zu `isAccordionBlockAvailable()` im JS. Ein nicht
registrierter Blocktyp ergibt im Editor „Block enthält unerwarteten Inhalt" —
diese Regel steht ausdrücklich in der CDB-CLAUDE.md.

### Seitentitel und die H1-Doppelung

Der Titel ist die erste `# `-Zeile; fehlt sie, dient der Dateiname ohne Endung
als Titel. Zu beachten: Der Parser setzt bei einer H1 auch
`current_heading = topic` mit `titleSource = 'h1'`. Folgt Inhalt direkt unter
der H1 (ohne H2/H3), entsteht ein Abschnitt, dessen `blockTitle` **derselbe
Text wie der Seitentitel** ist — der Titel stünde doppelt auf der Seite.
Regel: Beim **ersten** Abschnitt mit `titleSource === 'h1'`, dessen
`blockTitle` dem Seitentitel entspricht, wird die Überschrift unterdrückt; der
Inhalt bleibt. Weitere H1 im selben Dokument bleiben normale Abschnitte —
es geht nichts verloren.

### Dublettenprüfung

`get_page_by_title()` ist seit WordPress 6.2 als veraltet markiert. Stattdessen
`get_posts(['post_type' => 'page', 'title' => $titel, 'post_status' => 'any',
'numberposts' => 1])`. Treffer werden im Dialog **vor** dem Import aufgelistet
und sind abwählbar; importiert wird trotzdem, überschrieben wird nie.

### Bulk-Aktionen

| Aktion | Umsetzung | Rechteprüfung je Seite |
|---|---|---|
| Status umschalten | `wp_update_post()` — **nicht** `$wpdb`, damit `save_post` feuert | `edit_page`, beim Veröffentlichen zusätzlich `publish_pages` |
| Papierkorb | `wp_trash_post()` | `delete_page` |
| Elternseite zuweisen | `$wpdb->update` + `clean_post_cache()` (Muster von `ajax_update_order()`) | `edit_page` + Zirkelprüfung |
| Aus Index/Navigation ausnehmen | `update_post_meta()` für `_simple_clean_hide_from_index` / `_simple_clean_hide_navigation` | `edit_page` |

Die Antwort meldet je Aktion Erfolge **und** Einzelfehler zurück — das
bestehende `ajax_update_order()` macht das bereits so (`errors`-Array).

## 7. Regressionsfläche (kritisch)

| Was könnte brechen | Warum | Nachweis nach jeder Phase |
|---|---|---|
| **Blockimporter im Editor** | teilt sich Parser und AJAX-Endpunkt mit dem Seitenimport | Editor → ⋮ → „Inhalt importieren": eine bekannte MD-Datei importieren, Blöcke müssen wie bisher entstehen |
| **Gültigkeit der erzeugten Blöcke** | statisches `save()` des Containers; abweichendes Markup ⇒ „ungültiger Inhalt" | Importierte Seite im Editor öffnen: **keine** Warnung, kein Wiederherstellungs-Hinweis |
| **Drag-Sortierung im Seitenmanager** | neue Checkboxen liegen in der Zeile, die jQuery-UI-Sortable zieht | Klick auf Checkbox startet **kein** Ziehen (`cancel`-Option von sortable); Sortieren funktioniert unverändert |
| **Glossar-Performance** | Seiten ohne `_glossar_scan_version` fallen auf **alle** Begriffe zurück (gemessen 1,998 s statt 0,058 s) | Nach dem Import: Meta `_glossar_scan_version` ist gesetzt; `?sc_perf=1` zeigt `fallback=0` |
| **Inhaltsverzeichnis-Block & Sidebar** | lesen den Seitenbaum; neue Seiten oberster Ebene ändern ihn | Entwürfe dürfen **nicht** erscheinen (beide fragen nur `publish` ab); nach Bulk-Veröffentlichung erscheinen sie korrekt |
| **Container-Design-Zuordnung** | Container mit unbekanntem Slug rendert „Block nicht gefunden" | Serializer prüft jeden Slug gegen die aktiven Designs und fällt sonst auf „ohne Container" zurück — wie das JS |
| **Rolle Block-Redakteur** | hat `edit_pages` und sähe die Importseite | bewusst entscheiden und im Plan festhalten |
| **PHP 7.4** | Zielumgebung 7.4.33, lokal läuft 8.x | `php tools/check-php74.php` läuft grün (erzwingt der ZIP-Bau ohnehin) |

## 8. Konventions-Konformität

- Neue PHP-Klassen als Singleton mit `get_instance()` und Initialisierung am
  Dateiende — Muster von `CBD_Content_Importer`, `CBD_Icon_Manager`,
  `CBD_Design_Transfer`.
- Views unter `admin/`, Logik unter `includes/` — bestehende Trennung.
- Headless-Testharness `tools/test-block-serializer.php` mit
  WordPress-Stubs (`add_action`, `__`, `esc_html`, `esc_attr`,
  `serialize_blocks`), Ausgabe „N Prüfungen, M Fehler" — Muster von
  `tools/test-design-transfer.php`.
- JS als IIFE ohne Build-Schritt (CDB hat keine Bündelung), Zugriff über
  `wp.*`-Globale, `console.log` hinter `window.cbdDebug`.
- Theme-JS/CSS über Vite; nach Änderungen `npm run build` (erhöht die
  Patch-Version automatisch) und Syntaxprüfung aller PHP-Dateien vor dem ZIP.

## 9. Risiken & offene Fragen

**Risiko 1 — Markup-Treue (das Hauptrisiko).** Weicht das erzeugte Markup von
der `save()`-Ausgabe ab, meldet der Editor ungültige Blöcke. Die Klassennamen
der Kernblöcke sind zudem WordPress-versionsabhängig
(`class="wp-block-heading"` gibt es erst ab 6.1).
*Gegenmaßnahmen, gestaffelt:*
1. **Grundwahrheit messen statt raten:** Auf dem Testserver
   (`C:\allinkl-testserver`) eine Seite mit je einem Beispiel jedes Zielblocks
   im Editor anlegen, speichern, `post_content` auslesen — dieses Markup ist
   die Vorlage. Damit ist die WordPress-Version der Zielinstallation
   automatisch berücksichtigt.
2. Delimiter und Attribut-JSON über `serialize_blocks()`, nicht selbst bauen.
3. Rundlauf-Test im Harness: erzeugtes Markup durch `parse_blocks()` und
   zurück, Struktur und Attribute prüfen.
4. Sicherheitsnetz vorhanden: `assets/js/block-recovery.js` repariert
   ungültige Blöcke beim Öffnen des Editors automatisch.
*Rollback:* Der Serializer ist eine eigenständige Datei ohne Eingriff in
bestehenden Code — im Ernstfall genügt es, die Importseite nicht auszuliefern.

**Risiko 2 — stilles Scheitern des Untermenüs.** `add_submenu_page()` auf ein
noch nicht registriertes Elternmenü liefert `false` ohne Fehlermeldung. Feste
Priorität 20 auf `admin_menu` plus Rückfallmenü; im Plan als eigenes
Akzeptanzkriterium prüfen („Eintrag erscheint unter Seitenmanager").

**Risiko 3 — große Importmengen.** 40 Dateien × Parsen + Einfügen. Entschärft
durch einen AJAX-Aufruf pro Datei mit Fortschrittsanzeige. Zusätzlich: Nach dem
Import zeigt die Seite eine Ergebnisliste (angelegt / übersprungen / Fehler) mit
Links zu den Entwürfen.

**Risiko 4 — Auswahl kollidiert mit Drag-and-drop.** Checkboxen sitzen in
derselben Zeile wie der Ziehgriff. Sortable muss die Checkbox über die
`cancel`-Option ausnehmen, sonst startet ein Klick das Ziehen.

**Doku-Lücke:** CDB-Designer hat als einzige Komponente **keine**
`reference_file_map.md` (Theme und „Eigene WP Blocks" haben eine). Die
Erweiterung fügt sechs neue Dateien hinzu und macht die Lücke spürbarer. Der
Plan sollte eine Datei-Map für CDB-Designer als Arbeitspaket vorsehen —
mindestens für die vom Import berührten Bereiche.

**Offene Frage (nicht blockierend, im Plan zu entscheiden):** Soll die Rolle
*Block-Redakteur* den Seitenimport nutzen dürfen? Sie hat `edit_pages` und
sähe das Untermenü. Vorschlag: ja, da nur Entwürfe entstehen und die Rolle
ohnehin Seiten bearbeiten darf — aber bewusst festhalten.

## 10. Grobzuschnitt für den projektplan-skill

**Mehrphasig** (drei fachliche Phasen plus Abschluss), weil der Serializer
eigenständig testbar ist, bevor irgendeine Oberfläche existiert, und weil die
Bulk-Optionen ein anderes Repo betreffen.

- **Phase 1 – Serializer (CDB, ohne UI).** Grundwahrheit-Markup auf dem
  Testserver erheben; `CBD_Block_Serializer` bauen; `tools/test-block-serializer.php`
  mit Rundlauf- und Abweichungsprüfungen. *Zwischenergebnis:* Harness grün,
  erzeugtes Markup deckungsgleich mit der Editor-Ausgabe. Nichts am
  Produktivverhalten geändert.
- **Phase 2 – Importseite (CDB).** Untermenü unter dem Seitenmanager
  (Priorität 20 + Rückfall), Mehrfach-Dateiauswahl, gemeinsamer Stil-Dialog,
  Dublettenwarnung, AJAX-Import je Datei, Ergebnisliste. *Zwischenergebnis:*
  Import läuft Ende zu Ende, Entwürfe auf oberster Ebene, im Editor ohne
  Gültigkeitswarnung.
- **Phase 3 – Bulk-Optionen (Theme).** Auswahlspalte, Bulk-Leiste, vier
  Aktionen, Rechteprüfung je Seite, Sortable-Verträglichkeit. Unabhängig von
  Phase 1 und 2. *Zwischenergebnis:* Sammelaktionen funktionieren, Drag-Sortierung
  unverändert.
- **Phase 4 – Doku, Datei-Map, Auslieferung.** `CLAUDE.md` beider Komponenten,
  `Theme/reference_file_map.md` nachziehen, Datei-Map für CDB-Designer anlegen,
  ZIPs bauen (CDB über `node create-plugin-zip.js`, Theme über `npm run build`),
  Reihenfolge beim Ausrollen festhalten.

Jede Phase braucht die Regressionsnachweise aus Abschnitt 7 als
Akzeptanzkriterien — insbesondere „Blockimporter im Editor läuft unverändert"
und „`fallback=0` in der Glossar-Messzeile".
