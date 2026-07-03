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
3. **Nonce-Konsistenz (VERIFIZIERT):** `cbdAdmin.nonce` wird in
   `class-cbd-admin.php:529` mit `wp_create_nonce('cbd_admin')` erzeugt und in
   `admin-blocks-list.js:301 ff.` als `_wpnonce` gesendet. Der Handler oben prüft
   daher primär `cbd_admin` — das ist korrekt. Der zweite `check_ajax_referer`
   auf `cbd_admin_nonce` ist nur Fallback und kann entfallen.
   **Sicherheit:** Der Handler darf NICHT als `wp_ajax_nopriv_` registriert
   werden (nur eingeloggte Redakteure) — im Beispiel oben korrekt nur `wp_ajax_`.

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

## Aufgabe B — Import/Export ✅ ERLEDIGT: ausgeblendet (v3.1.50)

**Entscheidung getroffen (Variante 2):** Der Menüpunkt wurde ausgeblendet
(`add_submenu_page` für `cbd-import-export` in `class-cbd-admin.php` auskommentiert).
Grund: Die Seite verarbeitete Uploads serverseitig nie und bündelte drei riskante
Eingabewege — Datei-Upload inkl. ZIP (Zip-Bomb/Path-Traversal), eingefügtes JSON
und **URL-Fetch (SSRF-Risiko)**. Design-Übertragung erfolgt stattdessen sicher
über die Datenbank — Anleitung: [DESIGNS-UEBERTRAGEN.md](DESIGNS-UEBERTRAGEN.md).

Der render-Callback und die JS-Datei bleiben liegen, sind ohne Menü aber nicht
mehr erreichbar. Die folgende Beschreibung gilt nur, falls die Funktion später
DOCH implementiert werden soll (dann Variante 1 mit voller Sicherheits-Sektion).

### Problem (historisch, falls Reaktivierung gewünscht)
Die Import/Export-Seite (`admin/import-export.php`, im Menü über
`render_import_export_page()`, `class-cbd-admin.php:1067`) ist überwiegend UI:
- `cbd_preview_export` (JS, Zeile 527) hat keinen registrierten PHP-Handler.
- Die Import-Formulare (Nonce `cbd_import_blocks`, Zeile 148) werden serverseitig
  nicht ausgewertet — es gibt keinen Verarbeitungsblock, der den Upload liest.
- **Das Upload-Feld akzeptiert `.json,.zip`** (Zeile 153, `accept=".json,.zip"`).
  ZIP-Import ist eine erhebliche Angriffsfläche (siehe Sicherheits-Sektion).

### Entscheidung (MUSS vorab geklärt werden)
- **Variante 1:** Voll implementieren — **aber JSON-only** (ZIP-Support streichen,
  siehe Sicherheit). Export als JSON-Download, Import mit Datei-Upload +
  strikter Validierung + `CBD_Database::save_block`.
- **Variante 2 (empfohlen für schnellen sauberen Zustand):** Menüpunkt vorerst
  ausblenden, bis konkreter Bedarf besteht — entfernt zugleich die Angriffsfläche.

### ⚠️ Sicherheit — verbindliche Anforderungen für Variante 1
Import ist ein **authentifizierter Datei-Upload mit Datenverarbeitung** — die
klassische Stelle für Angriffe. Wenn implementiert, ALLE Punkte erfüllen:

1. **ZIP-Support entfernen.** Das `accept`-Attribut auf `.json` reduzieren UND
   serverseitig den MIME-/Endungs-Check auf JSON beschränken. Kein `ZipArchive`,
   kein `unzip` — ZIP-Uploads bergen Zip-Bombs (Ressourcenerschöpfung) und
   Path-Traversal beim Entpacken (`../../wp-config.php`). Nur wenn ZIP zwingend
   gebraucht wird: Pfade jedes Eintrags gegen `realpath()` in einem dedizierten
   Temp-Ordner validieren, entpackte Größe deckeln, niemals in web-erreichbare
   Verzeichnisse entpacken.
2. **Capability + Nonce** in JEDEM Handler: `current_user_can('cbd_admin_blocks')`
   und `check_admin_referer('cbd_import_blocks', 'cbd_import_nonce')` bzw.
   `'cbd_export_blocks'`. Handler NIE als `nopriv` registrieren.
3. **Upload-Validierung:** `wp_check_filetype_and_ext()` + explizite Größengrenze
   (z. B. `if ($_FILES['import_file']['size'] > 1 * MB_IN_BYTES) reject`).
   Inhalt mit `wp_json_file_decode()` / `json_decode()` lesen — bei `null`/Fehler
   sofort abweisen.
4. **Struktur strikt validieren:** Erwartete Keys prüfen (name, title, slug,
   config, styles, features, status). Unerwartete Felder verwerfen (Whitelist,
   keine Blacklist). `config`/`styles`/`features` per `json_decode`→sanitize→
   `wp_json_encode` roundtripen — NIE rohe JSON-Strings aus der Datei speichern.
5. **Kein `unserialize`** auf Upload-Daten (PHP-Object-Injection). Nur JSON.
5a. **URL-Import (SSRF!):** Das Formular bietet auch „von URL laden"
    (`import_url`, `import-export.php:166`). Wird das implementiert, ist es ein
    Server-Side-Request-Forgery-Risiko — der Server würde beliebige URLs abrufen
    (interne Dienste, Cloud-Metadaten wie `169.254.169.254`). Empfehlung: URL-
    Import GAR NICHT anbieten. Falls doch: nur `https`, DNS/IP gegen private/
    link-local-Bereiche prüfen, `wp_safe_remote_get()` (nicht `file_get_contents`)
    mit Größen-/Timeout-Limit.
6. **Schreiben ausschließlich über `CBD_Database::save_block()`** (sanitisiert +
   feuert `cbd_block_saved` für die Cache-Invalidierung). Slug-Kollisionen mit
   `block_slug_exists()` behandeln.
7. **Export:** Nur Block-Design-Daten ausgeben, KEINE Server-/Pfad-/Nutzerinfos.
   `admin_post_cbd_export_blocks` mit Nonce + Capability, dann
   `Content-Disposition: attachment` + `wp_json_encode(CBD_Database::get_blocks())`.

### Schritte für Variante 1 (implementieren)
1. **Export-Handler** registrieren:
   - `add_action('wp_ajax_cbd_preview_export', ...)` → gibt gefilterte Blocks als
     JSON-Preview zurück (Nonce `cbd_export_preview` prüfen, Capability!).
   - `add_action('admin_post_cbd_export_blocks', ...)` für den Download
     (Anforderung 7). *Hinweis: Es existieren aktuell KEINE `admin_post_`-Handler
     im Plugin — dieses Muster ist neu, sauber übernehmen.*
2. **Import-Handler** `admin_post_cbd_import_blocks` gemäß Sicherheits-Sektion
   (Punkte 1–6).
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

## Aufgabe C — new-block.php / edit-block.php entduplizieren (TEILWEISE erledigt)

### Stand v3.1.53
- ✅ **CSS ausgelagert** (Teil 1/2): beide `<style>`-Blöcke → `assets/css/new-block-form.css`
  bzw. `edit-block-form.css`, seiten-spezifisch enqueued; Kapazitäts-Bedingung über
  Body-Klasse `cbd-no-style-edit`. Byte-identisch je Seite (kein Merge), Templates
  ~1450 Zeilen kleiner, CSS jetzt cachebar.
- ⏸ **JS-Dedup zurückgestellt (Befund v3.1.53):** Analyse ergab: von den 6 gleichnamigen
  Funktionen sind **5 byte-identisch** (`populateIconGrid`, `filterIcons`,
  `createPlaceholder`, `toggleSticky`, `updateToggleVisibility`), nur `updateLivePreview`
  ist divergiert. ABER alle liegen **innerhalb der `jQuery(ready)`-Closure** und teilen
  lokale Variablen (`$`, `dashicons` …). Eine Auslagerung in eine gemeinsame Datei bricht
  den Scope → erfordert Umbau zu eigenständigen Funktionen (Parameter durchreichen) und
  ist nur mit laufendem Editor sicher verifizierbar. Bewusst NICHT blind ausgeführt.
  **Sicherer nächster Schritt (mit Test-Iteration):** die 5 identischen Helfer als
  parametrisierte Funktionen in `assets/js/admin-block-form.js` (global, `$`/`dashicons`
  als Argumente), aus beiden Inline-Skripten entfernen und dort aufrufen; `updateLivePreview`
  + dynamische Teile (Redirect/i18n/ajaxurl) seitenspezifisch belassen.

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
   `assets/css/admin-block-form.css` extrahieren. Einbinden in
   `enqueue_admin_assets()` (`class-cbd-admin.php:292`) im **bereits vorhandenen
   `switch ($page)`-Block** unter `case 'cbd-new-block': case 'cbd-edit-block':`
   (ab Zeile 319) — NICHT über `$hook`. Die Funktion liest die Seite aus
   `$page = sanitize_text_field($_GET['page'])` (Zeile 300); dort werden schon
   heute die seiten-spezifischen Assets geladen. Inline-`<style>` in beiden
   Dateien entfernen. → Testen: beide Seiten sehen unverändert aus.
3. **JS als Nächstes:** Die 6 Funktionen in `assets/js/admin-block-form.js`
   zusammenführen. Wo die Kopien divergieren, die **funktional korrektere/neuere**
   Variante wählen (in der Regel die aus new-block.php, da umfangreicher) —
   Auswahl im Commit begründen. Konfigurationsunterschiede (z. B. Icon-Listen,
   Default-Werte) über ein `wp_localize_script('cbd-admin-block-form', 'cbdBlockForm', array(...))`
   je Seite übergeben, nicht im JS hartkodieren.
4. Inline-`<script>` in beiden Dateien entfernen, stattdessen das neue Handle im
   selben `switch ($page)`-`case` (Schritt 2) per `wp_enqueue_script(..., true)`
   im Footer einbinden. **Hinweis:** Das entfernt zugleich die inline gesetzten
   `CBD_VERSION . '-' . time()`-Cache-Buster (falls vorhanden) — das feste
   `CBD_VERSION` als Handle-Version macht die Datei cachebar (Performance).
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

### Voranalyse v3.1.53 (vor Umsetzung)
- **Gut:** Die 8 `ajax_*`-Methoden sind self-contained (kein `$this->` auf andere
  CBD_Admin-Methoden, nur Globals `$wpdb`/`wp_send_json_*`/`do_action`) → für eine
  eigene `CBD_Admin_Ajax`-Klasse geeignet.
- **Achtung:** Die Methoden liegen NICHT zusammenhängend (z. B. `ajax_edit_save`
  ~2749, dann andere Methoden, `ajax_get_page_blocks` erst ~2951). Ein Verschieben
  betrifft mehrere getrennte Bereiche → in einer IDE mit „Move Method"/Refactoring-
  Tools machen, NICHT per blindem Text-Verschieben. Nach dem Move zwingend prüfen:
  alle `add_action('wp_ajax_…')` zeigen auf die neue Klasse, jeder Handler behält
  Nonce + Capability, php -l grün, jede AJAX-Aktion im Backend real getestet.
- **CSS-Delegation (freigegeben):** `generate_css_from_styles()` (~Z. 1773) an den
  `CBD_Style_Loader` delegieren. Vorher prüfen, ob der Style-Loader eine passende
  öffentliche Methode hat; sonst dort eine schaffen. Editor-Vorschau vorher/nachher
  visuell vergleichen (kleine Abweichung wurde als akzeptabel freigegeben).
- **Empfehlung:** Nur mit laufender WP-Instanz + Test nach jedem Teil-Move. Blind
  (nur php -l) nicht abnehmbar, da Verhalten/Registrierung nicht prüfbar.



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

## Querschnitt: Sicherheits-Checkliste (für JEDE Aufgabe verbindlich)

Diese Regeln schützen vor Angriffen von außen. Jeder neue oder geänderte Handler
MUSS sie erfüllen — ohne Ausnahme:

1. **Jeder AJAX-/POST-Handler prüft ZUERST Nonce, DANN Capability**, bevor
   irgendetwas gelesen/geschrieben wird. Muster:
   `check_ajax_referer(...)` bzw. `check_admin_referer(...)` → dann
   `current_user_can('cbd_admin_blocks')` (Design-Verwaltung) bzw.
   `'cbd_edit_blocks'` (Redakteur-Funktionen). Reihenfolge nicht vertauschen.
2. **Niemals `wp_ajax_nopriv_` für schreibende oder datenoffenlegende Aktionen.**
   (Der einzige bewusst öffentliche Endpoint ist die PDF-Generierung — nichts
   Neues ohne ausdrückliche Freigabe öffentlich machen.)
3. **Alle Eingaben sanitisieren, alle Ausgaben escapen.** `intval` für IDs,
   `sanitize_text_field`/`sanitize_textarea_field` für Text, `sanitize_hex_color`
   für Farben, `wp_kses_post` für erlaubtes HTML. Beim Echo in Admin-Templates:
   `esc_html`/`esc_attr`/`esc_url`.
4. **SQL nur über `$wpdb->prepare()`** bzw. `$wpdb->update/insert/delete` mit
   Format-Specifiern (`%d`/`%s`). Nie String-Konkatenation von Nutzereingaben in
   Queries. Tabellennamen nur aus den `CBD_TABLE_*`-Konstanten.
5. **Kein `unserialize`, kein `eval`, kein `extract`** auf Nutzerdaten. Strukturen
   ausschließlich als JSON (`json_decode`/`wp_json_encode`).
6. **Datei-Uploads** (nur Aufgabe B): Endung + MIME serverseitig prüfen,
   Größenlimit, kein ZIP/Entpacken (siehe Aufgabe-B-Sicherheit). Uploads nie in
   web-erreichbare Verzeichnisse schreiben.
7. **Keine internen Details ausgeben** (Pfade, Server-Config, Stacktraces,
   Roh-JSON von Designs) — weder in Responses noch in HTML-Kommentaren. Debug-
   Ausgaben immer hinter `if (defined('WP_DEBUG') && WP_DEBUG)`.
8. **Beim Refactoring bestehende Schutzmechanismen erhalten:** Verschobene
   Handler behalten Nonce + Capability. Nach dem Verschieben grep-prüfen, dass
   die Prüfungen mitgewandert sind.

## Querschnitt: Performance-Regeln

1. **Nichts Neues unkonditional im Frontend enqueuen.** Assets nur laden, wenn
   die Seite sie braucht — Muster: `frontend_has_container_block()` bzw. der
   `switch ($page)`-Block im Admin.
2. **Keine `time()`-/Zufalls-Cache-Buster.** Handle-Version immer `CBD_VERSION`,
   damit Browser/CDN cachen können.
3. **Extrahiertes Inline-JS/CSS wird cachebar** (eigene Datei + `CBD_VERSION`) —
   das ist ein Performance-GEWINN gegenüber Inline, zusätzlich zur Entduplizierung.
4. **Keine Queries in Schleifen.** Wenn ein Handler pro Element schreibt (Import),
   Bulk-Logik nutzen bzw. Query-Zahl im Blick behalten; Cache am Ende EINMAL via
   `do_action('cbd_block_saved')` invalidieren, nicht pro Element.
5. **Schreibpfade invalidieren den Cache** über `do_action('cbd_block_saved'/
   'cbd_block_deleted')` — jeder neue Schreib-Handler MUSS das feuern, sonst
   zeigt das Frontend veraltete Designs.
6. **Kein Verhalten „aus Versehen" verschlechtern:** Nach Refactorings prüfen,
   dass Assets weiterhin nur auf den Zielseiten laden (Network-Tab).

## Diskussionspunkte (vor Umsetzung zu klären)

Diese Fragen bestimmen, welche Varianten oben gelten. Jeweils mit Kontext,
Optionen, Empfehlung und Konsequenz — so lässt sich die Entscheidung führen.

### 1. Quick-Edit (Aufgabe A)
**Frage:** Soll das Schnell-Bearbeiten (Titel/Beschreibung/Status direkt in der
Blockliste) funktionieren, oder reicht die vollständige „Bearbeiten"-Seite?
**Kontext:** Das UI existiert bereits (per JS injiziert), nur der Server-Handler
fehlt — es war nie funktionsfähig.
**Optionen:** (1) Handler implementieren (~1 h, sicher umsetzbar, Beispielcode
liegt vor). (2) UI+JS entfernen (~30 min, weniger Code).
**Empfehlung:** Implementieren, wenn du oft nur den Status/Titel änderst — spart
Klicks. Sonst entfernen.
**Konsequenz bei „nichts tun":** Ein sichtbarer, aber toter „Speichern"-Button
bleibt — verwirrend für Redakteure.

### 2. Import/Export (Aufgabe B)
**Frage:** Wird der Austausch von Block-Designs zwischen Installationen
(z. B. Test → Produktiv, oder Kolleg:innen) gebraucht?
**Kontext:** Größte NEUE Angriffsfläche des ganzen Plans (authentifizierter
Datei-Upload). Das Feld akzeptiert aktuell auch `.zip` — gefährlich.
**Optionen:** (1) JSON-only implementieren mit voller Sicherheits-Checkliste
(~1 Tag). (2) Menüpunkt ausblenden (~15 min) — entfernt die Angriffsfläche ganz.
**Empfehlung:** **Ausblenden**, außer du brauchst den Austausch konkret. „Ein
Feature, das man nicht hat, kann man nicht angreifen."
**Konsequenz bei „nichts tun":** Toter Menüpunkt bleibt; das `.zip`-Upload-Feld
suggeriert eine (nicht vorhandene) Funktion — Verwirrung, kein akutes Risiko, da
serverseitig ohnehin nichts verarbeitet wird.

### 3. Reihenfolge C vs. D
**Frage:** Zuerst Formulare entduplizieren (C) oder `CBD_Admin` aufteilen (D)?
**Empfehlung:** **C zuerst.** C beseitigt aktiv divergierende Duplikate (echte
Bug-Quelle), D baut danach auf einem ruhigeren Stand auf. Beide brauchen Staging.
**Zu entscheiden:** Einverstanden mit C→D, oder andere Priorität?

### 4. CSS-Duplikat bei der Aufteilung (Aufgabe D)
**Frage:** Darf `generate_css_from_styles()` (in `CBD_Admin`, Z. 1773) entfernt
und an den `CBD_Style_Loader` delegiert werden (eine Quelle der CSS-Wahrheit)?
**Kontext:** Die Editor-Vorschau nutzt aktuell eine eigene CSS-Erzeugung, die die
des Style-Loaders dupliziert. Delegation reduziert Wartungslast, könnte die
Vorschau aber minimal anders aussehen lassen.
**Optionen:** (1) Delegieren (sauberer, kleines Risiko optischer Abweichung).
(2) Belassen (kein Risiko, Duplikat bleibt).
**Empfehlung:** Delegieren, mit optischem Vorher/Nachher-Vergleich im Editor.
**Zu entscheiden:** Ist eine minimale Vorschau-Abweichung akzeptabel?

### 5. Service-Container (Aufgabe E)
**Frage:** Reicht „Fassade behalten + `init_legacy_fallback`-Doppelpfad
entfernen" (Variante 1), oder größerer Umbau?
**Empfehlung:** **Variante 1.** Für den Plugin-Umfang bringt echte Dependency
Injection keinen Praxisnutzen; der Doppelpfad ist die eigentliche Altlast.
**Zu entscheiden:** Variante 1 ok, oder Container ganz entfernen (Variante 3)?

### 6. Test-Ressourcen (blockierend für C und D)
**Frage:** Steht eine WordPress-Staging-Umgebung mit Beispielinhalten bereit —
Seiten mit Container-Blöcken, wiederverwendbare Blöcke, eine Classroom-Klasse,
ein Block-Redakteur-Testnutzer und ein Admin?
**Warum wichtig:** C und D ändern funktionierende Admin-UI; ohne Laufzeit-Test
sind sie nicht sicher abnehmbar (php -l prüft nur Syntax, kein Verhalten).
**Konsequenz bei „nein":** C und D zurückstellen, bis Staging existiert. A, B
(Variante „ausblenden") und E sind auch ohne Staging vertretbar.

## Empfohlene Reihenfolge (gesamt)

1. ~~**B**~~ ✅ erledigt (ausgeblendet, v3.1.50)
2. **A** (klein, schneller Erfolg)
3. **E** (klein, entfernt Doppelpfad)
4. **C** (Duplikate — größter Wartbarkeitsgewinn, braucht Staging)
5. **D** (Gott-Klasse — baut auf ruhigem C-Stand auf, braucht Staging)
6. **F** nur bei konkretem Bedarf
