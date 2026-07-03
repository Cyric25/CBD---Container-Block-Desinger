# Umsetzungsplan: offene Punkte (Schritt-für-Schritt)

Detaillierte Arbeitsanleitung für die noch nicht umgesetzten Punkte des
[SANIERUNGSPLAN.md](SANIERUNGSPLAN.md). Stand: v3.1.48.

**Zielgruppe:** Umsetzung durch eine KI (z. B. Opus) oder Entwickler:in mit
laufender WordPress-Testumgebung. Jeder Abschnitt ist eigenständig und in einer
eigenen Version deploybar.

## Grundregeln für jede Aufgabe

1. **Branch:** `git checkout -b refactor/<kurzname>` (nie direkt auf `main`).
2. **Syntax-Check vor jedem Commit:**
   `for file in *.php includes/*.php includes/Database/*.php admin/*.php; do php -l "$file" || exit 1; done`
   JS: `node --check assets/js/<datei>.js`
3. **Version bumpen:** Das ZIP-Skript (`node create-plugin-zip.js`) erhöht die
   Patch-Version automatisch und schreibt sie in `container-block-designer.php`.
   Version NICHT manuell an mehreren Stellen ändern.
4. **Ein Thema = ein Commit** mit `Co-Authored-By`-Trailer.
5. **Nach dem Commit:** ZIP bauen, auf Staging installieren, Abnahmekriterien
   (unten je Aufgabe) durchgehen. Erst dann `main` mergen.
6. **Rollback-Anker:** Vor Beginn den aktuellen Commit-Hash notieren.

---

## Aufgabe A — Quick-Edit-Handler reparieren oder entfernen (klein, ~1 h)

### Problem
`assets/js/admin-blocks-list.js:300` (`saveQuickEdit`) sendet eine AJAX-Action
`cbd_save_block_quick`. Es gibt **keinen** PHP-Handler dafür (verifiziert per
Grep über das ganze Plugin). Das Quick-Edit in der Blockliste war also nie
funktionsfähig — ein Klick auf „Speichern" läuft ins Leere bzw. in einen
`0`-Response von admin-ajax.php.

### Entscheidung (MUSS vorab geklärt werden)
- **Variante 1 (empfohlen):** Handler implementieren — Quick-Edit soll funktionieren.
- **Variante 2:** Quick-Edit-UI und JS entfernen — Feature nicht gewünscht.

→ Siehe „Diskussionspunkte" am Ende. Ohne diese Entscheidung nicht starten.

### Schritte für Variante 1 (Handler implementieren)
1. In `includes/class-cbd-admin.php` im Konstruktor (bei den anderen
   `add_action('wp_ajax_cbd_...')`, Zeilen 59–68) registrieren:
   ```php
   add_action('wp_ajax_cbd_save_block_quick', array($this, 'ajax_save_block_quick'));
   ```
2. Neue Methode `ajax_save_block_quick()` nach `ajax_edit_save()` (ca. Zeile 2704)
   einfügen. Sie MUSS dem Sicherheitsmuster der Phase-1-Handler folgen:
   ```php
   public function ajax_save_block_quick() {
       // Nonce: JS sendet _wpnonce = cbdAdmin.nonce
       if (!check_ajax_referer('cbd_admin', '_wpnonce', false)
           && !check_ajax_referer('cbd_admin_nonce', '_wpnonce', false)) {
           wp_send_json_error(__('Sicherheitsüberprüfung fehlgeschlagen', 'container-block-designer'));
       }
       if (!current_user_can('cbd_admin_blocks')) {
           wp_send_json_error(__('Keine Berechtigung', 'container-block-designer'));
       }
       global $wpdb;
       $block_id = intval($_POST['block_id'] ?? 0);
       if (!$block_id) {
           wp_send_json_error(__('Ungültige Block-ID', 'container-block-designer'));
       }
       $data = array(
           'title'       => sanitize_text_field($_POST['title'] ?? ''),
           'description' => sanitize_textarea_field($_POST['description'] ?? ''),
           'status'      => (($_POST['status'] ?? '') === 'active') ? 'active' : 'inactive',
           'updated_at'  => current_time('mysql'),
       );
       $result = $wpdb->update(CBD_TABLE_BLOCKS, $data, array('id' => $block_id),
           array('%s','%s','%s','%s'), array('%d'));
       if ($result !== false) {
           do_action('cbd_block_saved', $block_id);   // WICHTIG: Cache invalidieren
           wp_send_json_success(array('message' => __('Gespeichert', 'container-block-designer')));
       }
       wp_send_json_error(__('Fehler beim Speichern', 'container-block-designer'));
   }
   ```
3. **Nonce-Konsistenz prüfen:** In `assets/js/admin-blocks-list.js` sendet der
   Code `_wpnonce: cbdAdmin.nonce`. Prüfen, mit welcher Action `cbdAdmin.nonce`
   erzeugt wird (`enqueue_admin_assets`, ca. Zeile 292, `wp_localize_script`).
   Der `check_ajax_referer`-Aufruf oben muss dazu passen — ggf. Action-Namen
   angleichen.

### Schritte für Variante 2 (entfernen)
1. In `assets/js/admin-blocks-list.js` die Methoden `saveQuickEdit`,
   `openQuickEdit`/`showQuickEdit` und die zugehörigen Event-Bindings entfernen.
2. In `admin/blocks-list.php` bzw. `render_simple_blocks_list()`
   (`class-cbd-admin.php:947`) die Quick-Edit-Button/Formular-Ausgabe entfernen.

### Abnahmekriterien
- Variante 1: In der Blockliste einen Block per Quick-Edit ändern → Titel/Status
  werden gespeichert und nach Reload korrekt angezeigt; Frontend zeigt Änderung
  sofort (Cache-Invalidierung greift).
- Variante 2: Kein Quick-Edit-Button mehr sichtbar; keine JS-Konsolenfehler.

---

## Aufgabe B — Import/Export funktionsfähig machen oder ausblenden (mittel, ~0,5–1 Tag)

### Problem
Die Import/Export-Seite (`admin/import-export.php`, im Menü über
`render_import_export_page()`, `class-cbd-admin.php:1067`) ist überwiegend UI:
- `cbd_preview_export` (JS, Zeile 527) hat keinen registrierten PHP-Handler.
- Die Import-Formulare (`cbd_import_nonce`, Zeile 148) werden serverseitig nicht
  ausgewertet — es gibt keinen Verarbeitungsblock, der den Upload liest und
  Blöcke anlegt.

### Entscheidung (MUSS vorab geklärt werden)
- **Variante 1:** Voll implementieren (Export als JSON-Download, Import mit
  Datei-Upload + Validierung + `CBD_Database::save_block`).
- **Variante 2 (empfohlen für schnellen sauberen Zustand):** Menüpunkt vorerst
  ausblenden, bis Bedarf besteht.

### Schritte für Variante 1 (implementieren)
1. **Export-Handler** in `class-cbd-admin.php` registrieren und implementieren:
   - `add_action('wp_ajax_cbd_preview_export', ...)` → gibt gefilterte Blocks als
     JSON zurück (nur Preview, kein Download).
   - Export-Download: eigener `admin_post`-Handler
     (`add_action('admin_post_cbd_export_blocks', ...)`), der
     `header('Content-Type: application/json')` +
     `header('Content-Disposition: attachment; filename=...')` setzt und
     `CBD_Database::get_blocks()` als JSON ausgibt. Nonce `cbd_export_blocks`
     prüfen, `current_user_can('cbd_admin_blocks')`.
2. **Import-Handler** (`admin_post_cbd_import_blocks`):
   - Nonce `cbd_import_blocks` + Capability prüfen.
   - `$_FILES['import_file']` via `wp_handle_upload` mit
     `array('mimes' => array('json' => 'application/json'))` annehmen.
   - JSON dekodieren, **strikt validieren** (erwartete Keys: name, title, slug,
     config, styles, features, status), pro Eintrag `CBD_Database::save_block()`.
   - Slug-Kollisionen behandeln (vorhandene `block_slug_exists()` nutzen).
   - Am Ende `do_action('cbd_block_saved', 0)` für Cache-Invalidierung.
   - **Sicherheit:** Kein `unserialize`, kein direktes Schreiben von `styles`/
     `config`-Strings ohne JSON-Roundtrip; Werte durchsanitisieren.
3. Erfolg/Fehler via `set_transient('cbd_admin_notice_...')` + Redirect zurück.

### Schritte für Variante 2 (ausblenden)
1. In `add_admin_menu()` (`class-cbd-admin.php:136`) den `add_submenu_page`-Aufruf
   für „Import/Export" auskommentieren/entfernen (Zeile im Menüblock suchen:
   Slug enthält `import-export`).
2. `render_import_export_page()`, `admin/import-export.php` und
   `assets/js/admin-import-export.js` bleiben liegen (kein toter Menüpunkt mehr),
   ODER konsequent löschen — dann auch das Enqueue in `enqueue_admin_assets`
   (Grep nach `admin-import-export`) entfernen.

### Abnahmekriterien
- Variante 1: Export erzeugt eine gültige JSON-Datei; erneuter Import derselben
  Datei auf einer frischen Installation stellt identische Block-Designs wieder
  her; ungültige/manipulierte Datei wird mit Fehlermeldung abgewiesen, ohne
  Fatal Error.
- Variante 2: Menüpunkt weg, keine 404/leere Seite, keine JS-Fehler.

---

## Aufgabe C — new-block.php / edit-block.php entduplizieren (groß, ~1 Tag)

### Problem (mit Zahlen)
Beide Dateien enthalten fast identisches Inline-JS und -CSS:
- `admin/new-block.php`: `<style>` Zeilen **1009–1801** (~790 Z.),
  `<script>` Zeilen **1803–2389** (~585 Z.).
- `admin/edit-block.php`: `<style>` Zeilen **731–1400** (~670 Z.),
  `<script>` Zeilen **1402–1834** (~430 Z.).
- Wortgleich (aber bereits divergiert!) vorhandene JS-Funktionen in beiden:
  `populateIconGrid`, `filterIcons`, `updateLivePreview`, `createPlaceholder`,
  `toggleSticky`, `updateToggleVisibility`.
  `updateLivePreview` ist in new-block ~220 Z., in edit-block ~114 Z. — die
  Kopien laufen also auseinander (klassische Fork-Bug-Quelle).

### Zielarchitektur
- `assets/js/admin-block-form.js` — gemeinsame JS-Logik (die 6 Funktionen + Init).
- `assets/css/admin-block-form.css` — gemeinsames CSS.
- `admin/partials/block-form.php` — gemeinsames Formular-Markup (falls auch das
  HTML dupliziert ist; zuerst per Diff prüfen).
- new/edit unterscheiden sich nur noch durch: vorbefüllte Werte, Ziel-Action
  (`cbd_save_block` vs. `cbd_edit_save`), Überschrift.

### Schritte (strikt inkrementell — nach JEDEM Schritt testen)
1. **Diff erstellen** als Referenz:
   `diff <(sed -n '1803,2389p' admin/new-block.php) <(sed -n '1402,1834p' admin/edit-block.php) > /tmp/js-diff.txt`
   Analog für die `<style>`-Blöcke. So wird sichtbar, welche Unterschiede echt
   (gewollt) und welche Drift (Bug) sind.
2. **CSS zuerst** (risikoärmer): Gemeinsames CSS nach
   `assets/css/admin-block-form.css` extrahieren, in `enqueue_admin_assets()`
   (`class-cbd-admin.php:292`) NUR auf den beiden Screens `cbd-new-block` und
   `cbd-edit-block` per `wp_enqueue_style` einbinden (Hook-Suffix prüfen). Inline-
   `<style>` in beiden Dateien entfernen. → Testen: beide Seiten sehen unverändert
   aus.
3. **JS als Nächstes:** Die 6 Funktionen in `assets/js/admin-block-form.js`
   zusammenführen. Wo die Kopien divergieren, die **funktional korrektere/neuere**
   Variante wählen (in der Regel die aus new-block.php, da umfangreicher) —
   Auswahl im Commit begründen. Konfigurationsunterschiede (z. B. Icon-Listen,
   Default-Werte) über ein `wp_localize_script('cbd-admin-block-form', 'cbdBlockForm', array(...))`
   je Seite übergeben, nicht im JS hartkodieren.
4. Inline-`<script>` in beiden Dateien entfernen, stattdessen das neue Handle
   enqueuen (nur auf den beiden Screens).
5. **HTML-Formular:** Erst wenn 2–4 stabil laufen, prüfen ob das Formular-Markup
   dupliziert ist. Falls ja: nach `admin/partials/block-form.php` extrahieren und
   in beiden Seiten via `include` mit vorbelegten Variablen einbinden.

### Abnahmekriterien (auf Staging, beide Seiten je einzeln)
- Neuen Block anlegen: Live-Vorschau aktualisiert sich beim Tippen/Ändern von
  Farben, Padding, Icon; Icon-Picker öffnet, filtert, wählt; Sticky-Toggle wirkt;
  Speichern legt Block korrekt an; Frontend zeigt ihn.
- Bestehenden Block bearbeiten: Werte sind vorbefüllt; Änderungen speichern und
  bleiben nach Reload erhalten.
- Keine JS-Konsolenfehler auf beiden Seiten.
- Diff-Gegencheck: `updateLivePreview` existiert nur noch einmal.

---

## Aufgabe D — CBD_Admin (2.985 Z.) aufteilen (groß, ~1–1,5 Tage)

### Problem
`includes/class-cbd-admin.php` vereint mind. 6 Verantwortlichkeiten. Vollständiges
Methoden-Inventar (Stand v3.1.48) zur Zuordnung:

| Verantwortung | Methoden (Zeilen) |
|---|---|
| Rollen/Setup | `ensure_roles_exist` (83), `create_block_redakteur_role` (124) |
| Menü/Routing/Assets | `add_admin_menu` (136), `enqueue_admin_assets` (292), `render_main_page` (545), `add_action_links` (2684) |
| POST-Verarbeitung | `process_admin_actions` (563), `admin_notices` (852) |
| Seiten-Renderer | `render_new_block_page` (904), `render_blocks_list_page` (919), `render_simple_blocks_list` (947), `render_edit_block_page` (1052), `render_import_export_page` (1067), `render_migration_page` (1085), `render_pdf_diagnose_page` (1103), `render_settings_page` (1237), `render_classroom_page` (1252), `render_block_organizer_page` (1267), `render_block_preview_page` (1287) |
| Block-Preview/CSS-Gen | `render_preview_block_card` (1609), `generate_block_preview_html` (1677), `get_active_features` (1754), `generate_css_from_styles` (1773), diverse `safe_*`-Helfer (1712–1919) |
| Reparatur-Tools | `render_database_repair_page` (1919), `render_roles_repair_page` (2002), `handle_roles_repair` (2158), `handle_add_user_to_role` (2267), `handle_database_repair` (2301), `create_default_blocks` (2408) |
| AJAX | `ajax_save_block` (2449), `ajax_delete_block` (2581), `ajax_toggle_status` (2620), `ajax_test` (2696), `ajax_edit_save` (2704), `ajax_get_page_blocks` (2906), `ajax_copy_block` (2930), `ajax_move_block` (2961) |

### Wichtige Randbedingung
`CBD_Admin` ist ein **Singleton** (`get_instance()`), das direkt in
`ContainerBlockDesigner::init()` und über den Service-Container geholt wird.
Andere Stellen erwarten diese Methoden ggf. weiterhin. → Nicht „hart" aufteilen,
sondern **Delegation**: `CBD_Admin` bleibt als Fassade bestehen und delegiert an
neue Klassen. So bleibt die öffentliche API stabil.

### Zielklassen (Vorschlag, in `includes/Admin/`)
- `CBD_Admin_Ajax` — die 8 `ajax_*`-Methoden + deren `add_action`-Registrierung.
- `CBD_Admin_Repair` — die Reparatur-/Setup-Tools + `create_default_blocks`.
- `CBD_Block_Preview_Renderer` — Preview/CSS-Generierung.
  **Wichtig:** `generate_css_from_styles` (1773) dupliziert Logik des
  `CBD_Style_Loader`. Prüfen, ob stattdessen an den Style-Loader delegiert werden
  kann (Single Source of Truth für CSS).
- Menü/Routing/Renderer bleiben vorerst in `CBD_Admin`.

### Schritte
1. **Reihenfolge nach Risiko:** zuerst `CBD_Admin_Ajax` (klar abgegrenzt, gut
   testbar), dann `CBD_Admin_Repair`, zuletzt Preview-Renderer.
2. Pro Zielklasse: neue Datei anlegen, Methoden **verschieben** (nicht kopieren),
   im `CBD_Admin`-Konstruktor die neue Klasse instanziieren. AJAX-Registrierungen
   in die neue Klasse mitnehmen und aus `CBD_Admin` entfernen.
3. Autoloader: Die neuen Klassen müssen ladbar sein. Entweder in
   `class-autoloader.php` mappen oder in `load_dependencies()`
   (`container-block-designer.php:99`) per `require_once` einbinden.
4. Nach jeder verschobenen Klasse: Syntax-Check + Staging-Test der betroffenen
   Funktion, bevor die nächste Klasse angefasst wird.

### Abnahmekriterien
- Alle Admin-Funktionen unverändert nutzbar: Block anlegen/bearbeiten/löschen/
  Status togglen, Blockliste, Reparatur-Seiten (DB + Rollen), Block-Organizer
  (copy/move), Preview-Seite.
- Kein Fatal Error beim Laden (Klassen auflösbar).
- `generate_css_from_styles`: entweder entfernt (delegiert an Style-Loader) oder
  bewusst belassen — Entscheidung dokumentiert.

---

## Aufgabe E — Service-Container-Entscheidung (mittel, ~0,5 Tag)

### Aktueller Stand (nach Phase 4)
Der Container (`includes/class-service-container.php`) ist nach Entfernen der
toten Services eine dünne, funktionierende Fassade über den Singletons. In
`container-block-designer.php` gibt es aber weiterhin einen doppelten Pfad:
`init()` (Zeile ~234) holt Services über den Container, mit `try/catch` und
`init_legacy_fallback()` (Zeile 333), das dieselben Singletons direkt zieht.
`CBD_Admin` wird ohnehin am Container vorbei per `get_instance()` initialisiert.

### Entscheidung (MUSS vorab geklärt werden)
- **Variante 1 (empfohlen, geringer Aufwand):** Container als reine Zugriffs-
  fassade behalten, aber den `init_legacy_fallback`-Doppelpfad entfernen — der
  Container delegiert bereits an dieselben Singletons, ein Fallback ist redundant.
- **Variante 2 (groß):** Container ernst nehmen — echte Dependency Injection,
  Singletons auflösen. Hoher Aufwand, wenig Praxisnutzen bei diesem Plugin-Umfang.
- **Variante 3:** Container ganz entfernen, überall `::get_instance()`.

### Schritte für Variante 1
1. In `init()` den `try/catch`-Block prüfen: Wenn der Container zuverlässig
   dieselben Singletons liefert, den `catch → init_legacy_fallback()`-Zweig durch
   ein einfaches `error_log` im WP_DEBUG-Fall ersetzen oder ganz entfernen.
2. `init_legacy_fallback()` (Zeile 333) löschen, wenn nicht mehr referenziert.
3. Sicherstellen, dass `cbd_get_service()` (jetzt einzig in `functions.php`)
   weiterhin der einzige öffentliche Zugriffsweg ist.

### Abnahmekriterien
- Plugin lädt ohne Fehler; alle Services (`style_loader`, `block_registration`,
  `ajax_handler`, `admin`) über `cbd_get_service()` erreichbar.
- Kein doppelter Initialisierungspfad mehr.

---

## Aufgabe F — Archiv-/Listenseiten-Assets (klein, optional)

### Problem
Das in Phase 2 eingebaute Asset-Gate lädt CBD-Assets nur bei `is_singular()`.
Auf Archiv-/Blog-Übersichtsseiten mit Container-Blöcken in Excerpts/Loops fehlen
Features/Styles. Risiko gering (Excerpts strippen Blöcke meist), aber bewusst.

### Schritte (falls Bedarf)
1. In `frontend_has_container_block()` (`class-cbd-block-registration.php`) und
   `should_load_katex()` (`class-latex-parser.php`) einen Zweig für
   `is_home()`/`is_archive()` ergänzen: über `have_posts()`/den Haupt-Query die
   Post-Inhalte der Loop auf das `wp:container-block-designer/`-Präfix prüfen.
2. Alternativ: bewusst so lassen und diese Einschränkung in der README
   dokumentieren.

### Abnahmekriterien
- Auf einer Archivseite mit vollständig ausgegebenen Container-Blöcken werden
  Styles/Features geladen; auf reinen Excerpt-Archiven bleibt es schlank.

---

## Diskussionspunkte (vor Umsetzung zu klären)

Diese Fragen bestimmen, welche Varianten oben gelten. Sinnvoll, sie vorab zu
entscheiden:

1. **Quick-Edit (Aufgabe A):** Soll das Schnell-Bearbeiten in der Blockliste
   funktionieren, oder ist es überflüssig (Bearbeiten-Seite reicht)?
2. **Import/Export (Aufgabe B):** Wird der Austausch von Block-Designs zwischen
   Installationen gebraucht? Falls nein → ausblenden.
3. **Reihenfolge C/D:** Beide sind groß. Empfehlung: **C zuerst** (weniger
   Risiko, sofort weniger Duplikat-Bugs), dann D. Einverstanden?
4. **CSS-Duplikat (Aufgabe D):** Darf die Block-Preview-CSS-Generierung an den
   Style-Loader delegiert werden (Single Source of Truth), auch wenn das Editor-
   Vorschau minimal verändern könnte?
5. **Service-Container (Aufgabe E):** Reicht „Fassade + Fallback entfernen"
   (Variante 1) oder ist ein größerer Umbau gewünscht?
6. **Test-Ressourcen:** Steht eine Staging-Umgebung mit Beispielinhalten
   (Container-Blöcke, wiederverwendbare Blöcke, Classroom-Klasse, Block-Redakteur-
   Nutzer) bereit? C und D sind ohne sie nicht sicher abnehmbar.

## Empfohlene Reihenfolge (gesamt)

1. **A** und **B** (klein, schaffen sauberen Zustand, schnelle Erfolge)
2. **E** (klein, entfernt Doppelpfad)
3. **C** (Duplikate — größter Wartbarkeitsgewinn)
4. **D** (Gott-Klasse — baut auf ruhigem C-Stand auf)
5. **F** nur bei konkretem Bedarf
