# Verbesserungsplan 3 (dritter Review-Durchgang, 2026-07-04)

Frischer Durchgang durch die in Runde 1+2 nie geöffneten Dateien:
CBD-Datenbank-Klasse, Content-Importer, Block-Reference, REST-API, Migration,
LaTeX-Bulk-Cleanup, Block-Organizer, Style-Loader (komplett), Admin-Templates,
restliches CDB-JS (html2pdf-loader, floating-pdf-button, classroom-JS),
ChemViz-Shortcodes, H5P-Import, svg-drawing sowie Theme-Templates
(sidebar, page-manager, clipboard-uploader). Nummerierung: AP30+.

## Rahmenbedingungen (wie Plan 1/2)

- CDB-Designer: **PHP 7.4**, vor ZIP `php tools/check-php74.php`
- Eigene WP Blocks: Block-Änderungen → `npm run build && npm run block-zips`;
  Core-Änderungen → zusätzlich `plugin-zip-empty`
- Debug-Ausgaben: PHP hinter `WP_DEBUG`, JS hinter `window.cbdDebug` (etablierte Muster)

---

## ✅ Was im dritten Durchgang positiv auffiel

- **Theme-Sidebar** ([sidebar.php](Theme/sidebar.php)): Kompletter Seitenbaum mit
  EINER Query + Parent-Children-Map (kein N+1), Swipe-Gesten, ESC, Click-outside,
  debouncter Resize-Handler, Auto-Scroll zur aktuellen Seite — vorbildlich.
- **Style-Loader-Seitencache** ([class-cbd-style-loader.php:566-601](Plugins/CDB-Designer/includes/class-cbd-style-loader.php)):
  Pro-Seite-Transient mit Signatur aus Styles-Version + post_modified; bei
  Cache-Treffer wird sogar `parse_blocks()` übersprungen.
- **Migration, LaTeX-Bulk-Cleanup, Block-Organizer, Content-Importer, REST-API:**
  Alle AJAX-/REST-Endpunkte haben Nonce- und Capability-Checks.
- **Theme-Admin-Werkzeuge** (page-manager, clipboard-uploader): sauber geguarded,
  page-manager prüft sogar `edit_page` pro Einzelseite.
- **svg-drawing-Block:** Sanitisiert sein SVG-Output serverseitig (Script-/Event-Handler-Entfernung).

---

## P1 — Funktionsfehler

### AP30: AJAX-Duplizieren kopiert `is_default` mit → zwei Standard-Blöcke

**Datei:** [Plugins/CDB-Designer/includes/class-cbd-ajax-handler.php:355-371](Plugins/CDB-Designer/includes/class-cbd-ajax-handler.php)

**Problem:** `duplicate_block()` holt die komplette Zeile (`SELECT *`), entfernt
nur `id` und fügt den Rest neu ein — **inklusive `is_default`**. Wer den
Standard-Block dupliziert, hat danach zwei Blöcke mit `is_default = 1`
(dasselbe Symptom, das AP19 gerade behoben hat, über anderen Weg).
Außerdem werden `created_at`/`updated_at` des Originals mitkopiert.

**Fix:**

```php
unset($original['id']);
$original['is_default'] = 0;
$original['created_at'] = current_time('mysql');
$original['updated_at'] = current_time('mysql');
```

Zusätzlich fehlt die Cache-Invalidierung: nach erfolgreichem Insert
`do_action('cbd_block_saved', $wpdb->insert_id);` aufrufen, damit der
`cbd_active_blocks`-Transient geleert wird (sonst erscheint das Duplikat
erst nach bis zu 24h im Frontend).

### AP31: Zwei parallele Duplizier-Implementierungen konsolidieren

**Dateien:** [class-cbd-ajax-handler.php:341-371](Plugins/CDB-Designer/includes/class-cbd-ajax-handler.php)
(AJAX-Pfad, Suffix „(Kopie)"/`-copy-{timestamp}`) und
[class-cbd-database.php:289-338](Plugins/CDB-Designer/includes/class-cbd-database.php)
(GET-Pfad über CBD_Admin, Suffix `_copy_N`, mit Eindeutigkeitsprüfung).

**Problem:** Zwei divergierende Codepfade für dieselbe Aktion mit
unterschiedlicher Namensgebung; nur die Database-Variante prüft
Namens-/Slug-Eindeutigkeit und feuert `cbd_block_saved` (via save_block).

**Fix:** AJAX-Handler auf `CBD_Database::duplicate_block($block_id)` umstellen
(ein Einzeiler statt eigener Query-Logik); AP30 erledigt sich dann von selbst,
weil save_block `is_default` gar nicht schreibt. Danach prüfen, dass die
Antwortstruktur (`id`) für das Admin-JS erhalten bleibt.

### AP32: Doppeltes view.js bei ChemViz-Shortcode + Block auf derselben Seite

**Dateien:** [Plugins/Eigene WP Blocks/includes/class-chemviz-shortcodes.php:76-82](Plugins/Eigene WP Blocks/includes/class-chemviz-shortcodes.php)
(molecule), ~Zeile 210 (chart); [blocks/molecule-viewer/view.js](Plugins/Eigene WP Blocks/blocks/molecule-viewer/view.js)

**Problem:** Der Shortcode enqueued `blocks/molecule-viewer/view.js` unter dem
eigenen Handle `chemviz-molecule-viewer`. Liegt auf derselben Seite zusätzlich
der **Block**, lädt WordPress dieselbe Datei nochmal über den
block.json-Handle (`modular-blocks-molecule-viewer-view-script`) → das IIFE
läuft zweimal, und molecule-viewer/view.js hat **keinen** Init-Guard →
doppelte Initialisierung/Observer. Gleiches Muster beim Chart-Shortcode.

**Fix (zweiteilig):**
1. `dataset.initialized`-Guard in `molecule-viewer/view.js` und
   `interactive-data-chart/view.js` nachrüsten (identisches Muster wie AP20).
2. Im Shortcode zuerst den Block-Handle wiederverwenden:

```php
if (wp_script_is('modular-blocks-molecule-viewer-view-script', 'registered')) {
    wp_enqueue_script('modular-blocks-molecule-viewer-view-script');
} else {
    wp_enqueue_script('chemviz-molecule-viewer', ...); // bisheriger Fallback
}
```

---

## P2 — Konsistenz, Logging-Nachzügler

### AP33: console.log-Nachzügler in 7 JS-Dateien (CDB)

Der AP25-Sweep hat nur 5 Dateien erfasst. Ungegated verbleiben:

| Datei | Anzahl | Kontext |
|---|---|---|
| assets/js/html2pdf-loader.js | **74** | Classroom/PDF, Frontend |
| classroom-page-filter.js | 17 | Classroom, Frontend |
| floating-pdf-button.js | 13 | **jede** Container-Seite |
| classroom-frontend.js | 9 | Classroom, Frontend |
| block-recovery.js | 1 | Editor |
| icon-picker.js | 1 | Admin |
| admin.js | 1 | Admin |

**Aktion:** Gleiches Muster wie AP25:
`sed -i 's/\bconsole\.log(/window.cbdDebug \&\& console.log(/g'` pro Datei,
danach Node-Syntax-Check (`new Function(readFileSync(...))`).

### AP34: CBD_Database — Logging-Nachzügler

**Datei:** [class-cbd-database.php:292-334](Plugins/CDB-Designer/includes/class-cbd-database.php)

6 ungegatete `error_log()` in `duplicate_block()`, davon 2× `print_r()` des
kompletten Blocks. Entfällt größtenteils mit AP31 (Methode wird schlanker);
verbleibende Fehlerfälle (`Duplicate save failed` + last_error) ungegated
lassen, Rest löschen oder gaten.

### AP35: Dritte Kopie der 3Dmol/Plotly-Enqueue-Logik

**Datei:** [class-chemviz-shortcodes.php:49-73 und ~181-205](Plugins/Eigene WP Blocks/includes/class-chemviz-shortcodes.php)

Die Lokal/CDN-Fallback-Logik existiert dreifach (ChemViz_Enqueue::enqueue_3dmol,
::enqueue_plotly und nochmal inline in beiden Shortcodes). Bei der nächsten
Versionsänderung (z. B. Plotly-Update) driftet das auseinander.

**Fix:** `enqueue_3dmol()`/`enqueue_plotly()` in `ModularBlocks_ChemViz_Enqueue`
auf `public static` umstellen und die Shortcodes darauf umstellen
(`ModularBlocks_ChemViz_Enqueue::enqueue_3dmol();`). Verhalten identisch.

### AP36: `save_block()` sanitisiert den Slug als Text

**Datei:** [class-cbd-database.php:144](Plugins/CDB-Designer/includes/class-cbd-database.php)

`'slug' => sanitize_text_field(...)` — der Admin nutzt überall
`sanitize_title()`. Ein Slug mit Leerzeichen/Umlauten würde hier durchrutschen
und im Frontend-Matching (`WHERE slug = %s`) nicht mehr gefunden.
**Fix:** `sanitize_title($data['slug'] ?? $data['name'] ?? '')`.

---

## P3 — Robustheit, Randfälle

### AP37: Seiten-CSS-Cache erkennt Änderungen an wiederverwendbaren Blöcken nicht

**Datei:** [class-cbd-style-loader.php:566-595](Plugins/CDB-Designer/includes/class-cbd-style-loader.php)

Die Cache-Signatur besteht aus Styles-Version + `post_modified` der Seite.
Ändert sich ein **wiederverwendbarer Block** (`wp_block`-Post), der Container
enthält, ändert sich die Seite selbst nicht → bis zu 24h veraltetes CSS.

**Fix (billig):** Beim Speichern eines `wp_block`-Posts die Styles-Version
bumpen — invalidiert alle Seiten-Caches auf einmal:

```php
add_action('save_post_wp_block', function() {
    update_option('cbd_styles_version', time());
});
```

(z. B. in `init_hooks()` des Style-Loaders registrieren.)

### AP38: Template-Funktionen in sidebar.php absichern

**Datei:** [Theme/sidebar.php:85-156](Theme/sidebar.php)

`get_root_page_id()` und `display_page_tree_item()` sind im Template definiert —
würde `get_sidebar()` je zweimal pro Request aufgerufen (z. B. künftiges
Zweit-Template), gäbe es einen Fatal „cannot redeclare".
**Fix:** Beide mit `if (!function_exists(...))` wrappen oder nach
functions.php/includes verschieben. Rein defensiv, aktuell kein Fehlverhalten.

---

## Abarbeitungs-Checkliste

| AP | Titel | Status |
|----|-------|--------|
| 30 | is_default beim AJAX-Duplizieren | ✅ ERLEDIGT (2026-07-04) — durch AP31 obsolet |
| 31 | Duplizier-Pfade konsolidieren | ✅ ERLEDIGT (2026-07-04) — AJAX-Endpunkt `cbd_duplicate_block` komplett entfernt: er hatte KEINEN Konsumenten (Admin dupliziert per GET-Link → CBD_Database). Einzige verbleibende Implementierung ist CBD_Database::duplicate_block |
| 32 | Shortcode/Block-Doppel-Load + Guards | ✅ ERLEDIGT (2026-07-04) — DOM-Guards in molecule-viewer & interactive-data-chart view.js (Map-Guards überleben Doppel-Load nicht); Molecule-Shortcode nutzt den Block-Handle, falls registriert. **Zusatzfund:** `[chemviz_chart]` war komplett tot (enqueued Dateien des gelöschten chart-block = 404, Markup ohne JS-Konsument, ewiger Spinner) → graceful degradiert: Redakteure sehen Hinweis, Besucher HTML-Kommentar; Original-Code als `chart_shortcode_disabled()` erhalten, Doku aktualisiert |
| 33 | console.log-Nachzügler | ✅ ERLEDIGT (2026-07-04) — 116 Aufrufe in 7 Dateien hinter `window.cbdDebug`, Node-Syntax-Checks grün |
| 34 | CBD_Database-Logging | ✅ ERLEDIGT (2026-07-04) — 5 Debug-Logs entfernt, Fehlerfall bleibt |
| 35 | Enqueue-Logik deduplizieren | ✅ ERLEDIGT (2026-07-04) — enqueue_3dmol/enqueue_plotly public static, Shortcodes nutzen sie |
| 36 | Slug-Sanitizing | ✅ ERLEDIGT (2026-07-04) — sanitize_title in CBD_Database::save_block |
| 37 | Styles-Version-Bump bei wp_block | ✅ ERLEDIGT (2026-07-04) — save_post_wp_block → bump_styles_version() |
| 38 | function_exists-Guards sidebar.php | ✅ ERLEDIGT (2026-07-04) |

Bekannt/akzeptiert: webpack-Warnung „svg-drawing/index.js 310 KiB" (fabric.js im
Editor-Bundle) ist vorbestehend und editor-only — bei Gelegenheit per Code-Splitting
oder externem fabric-Handle adressierbar.
