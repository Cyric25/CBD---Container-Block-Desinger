# Verbesserungsplan (Handoff für Opus)

Basierend auf dem Code-Review vom 2026-07-04. Jedes Arbeitspaket (AP) ist unabhängig
umsetzbar und sollte einzeln committet werden. Reihenfolge = Priorität.

## Rahmenbedingungen (unbedingt einhalten)

- **CDB-Designer** zielt auf **PHP 7.4** (Produktivumgebung 7.4.33). Vor jedem ZIP:
  `php tools/check-php74.php` bzw. `node create-plugin-zip.js` (bricht bei 8.0-Syntax ab).
  Keine PHP-8-only-Syntax (Named Args, `match`, Nullsafe `?->`, Union Types) in diesem Plugin.
- **Eigene WP Blocks**: Nach Änderungen `npm run build && npm run block-zips`.
  NICHT `npm run plugin-zip` verwenden, NICHT die leere Plugin-Basis neu bauen.
- **Syntax-Check vor jedem Commit**: `for file in *.php includes/*.php; do php -l "$file" || exit 1; done`
- Feature-Daten liegen als JSON in der DB-Tabelle `{prefix}cbd_blocks.features` —
  Schemaänderungen brauchen eine Migration für Bestandsdaten (siehe AP1).

---

## P1 — Funktionsfehler (zuerst beheben)

### AP1: Feature-Key `collapsible`/`copy` → `collapse`/`copyText` vereinheitlichen

**Problem:** Der Admin speichert die Keys `collapse` und `copyText`
([admin/edit-block.php:466,598](Plugins/CDB-Designer/admin/edit-block.php),
[includes/class-cbd-admin.php:723-735](Plugins/CDB-Designer/includes/class-cbd-admin.php)),
der Renderer liest `collapse` — aber der Style-Loader prüft `collapsible` und `copy`.
Folge: `collapsible.css` / `copy.css` und das generierte Feature-CSS werden für
admin-gespeicherte Blöcke **nie** geladen. Die bei Aktivierung angelegten
Default-Blöcke speichern umgekehrt `collapsible` und sind damit im Renderer wirkungslos.

**Kanonische Keys (Ziel):** `collapse`, `copyText` (so schreibt sie der Admin).

**Zu ändern:**

1. `Plugins/CDB-Designer/includes/class-cbd-style-loader.php`
   - Zeile ~177: `in_array('collapsible', $active_features)` → `in_array('collapse', ...)`
   - Zeile ~187: `in_array('copy', $active_features)` → `in_array('copyText', ...)`
   - Zeile ~1924: `$features['collapsible']['enabled']` → `$features['collapse']['enabled']`
   - Zeile ~1941: `$features['copy']['enabled']` → `$features['copyText']['enabled']`
   - Zeile ~1992: `$has_features['collapsible']` → `$has_features['collapse']`
   - Danach im ganzen Plugin gegenprüfen: `grep -rn "collapsible\|'copy'" includes/ admin/ --include="*.php"`
     — jede verbleibende Fundstelle bewusst entscheiden (CSS-Klassennamen wie
     `.cbd-collapsible` sind OK und bleiben; nur die **Feature-JSON-Keys** ändern).
2. `Plugins/CDB-Designer/container-block-designer.php` Zeile ~573 (Default-Block
   `hero-section`): `'collapsible' => array(...)` → `'collapse' => array(...)`.
3. **Datenmigration** für Bestandsblöcke in der DB: In `includes/Database/class-schema-manager.php`
   (bzw. `CBD_Migration`) eine Migration ergänzen, die in `features` jeder Zeile
   `collapsible` → `collapse` und `copy` → `copyText` umbenennt (JSON decodieren,
   Key umhängen falls Ziel-Key noch nicht existiert, encodieren, updaten).
   Danach `delete_transient('cbd_active_blocks')` und Style-Cache leeren
   (`CBD_Style_Loader::clear_styles_cache()`).
4. DB-Version-Konstante hochzählen, damit die Migration beim Update läuft.

**Verifikation:** Block-Design mit aktivem „Klappbar" anlegen → Frontend: Wrapper hat
`cbd-collapsible`-Klasse UND `assets/css/features/collapsible.css` wird geladen
(Network-Tab). Default-Block „Hero Section" klappt nach Re-Aktivierung.

### AP2: Debug-Überbleibsel entfernen — erzwungenes Icon

**Problem:** [includes/class-cbd-block-registration.php:1115-1118](Plugins/CDB-Designer/includes/class-cbd-block-registration.php)

```php
$has_icon = !empty($features['icon']['enabled']);
// DEBUG: Always show icon for testing until we fix feature detection
$has_icon = true; // Force icon display for now
```

**Aktion:** Zeile `$has_icon = true;` (und den DEBUG-Kommentar) löschen.
Der Icon-Header erscheint dann nur noch, wenn das Feature aktiviert ist oder ein
`blockTitle` gesetzt wurde.

**Verifikation:** Block-Design ohne Icon-Feature → Frontend zeigt keinen
`.cbd-header-icon`; mit Icon-Feature → Icon erscheint.

### AP3: Riskante DELETE-Query in `create_default_blocks()` entschärfen

**Problem:** [container-block-designer.php:473](Plugins/CDB-Designer/container-block-designer.php)
löscht per `DELETE ... WHERE name LIKE '%Container%' OR name LIKE '%Info-Box%' OR name LIKE '%Klappbar%'`
— trifft auch benutzerdefinierte Blöcke (z. B. „Aufgaben-Container"). Läuft bei
Aktivierung und beim Update von Versionen < 2.6.1.

**Aktion:** LIKE-Query ersetzen durch exakte Alt-Namen der früheren deutschen
Default-Blöcke, z. B.:

```php
$wpdb->query($wpdb->prepare(
    "DELETE FROM " . CBD_TABLE_BLOCKS . " WHERE name IN (%s, %s, %s)",
    'Einfacher Container', 'Info-Box', 'Klappbarer Container'
));
```

Falls die exakten Alt-Namen unklar sind: Löschung komplett streichen — die Funktion
kehrt ohnehin früh zurück, wenn die 3 korrekten Default-Blöcke existieren; Duplikate
mit alten Namen sind harmloser als gelöschte User-Blöcke.

### AP4: Anchor darf die Container-ID nicht ersetzen

**Problem:** [includes/class-cbd-block-registration.php:949-951](Plugins/CDB-Designer/includes/class-cbd-block-registration.php):
`$wrapper_attributes['id'] = $anchor;` überschreibt die zuvor gesetzte eindeutige
`$container_id`. Der Content-Wrapper heißt aber weiter `{$container_id}-content` und
`context.containerId` im Interactivity-Kontext zeigt ebenfalls auf die alte ID —
JS-Zugriffe über die ID laufen bei gesetztem Anchor ins Leere.

**Aktion (empfohlen):** Anchor NICHT auf den Wrapper legen, sondern als zusätzliches
Attribut auf den inneren `.cbd-container-block`:

```php
if (!empty($anchor)) {
    $content_attributes['id'] = $anchor;   // statt $wrapper_attributes['id']
}
```

(Alternative: `$container_id = $anchor;` VOR dem Aufbau von `$local_context` und
`$content_wrapper_id` setzen — dann bleibt alles konsistent. Eine der beiden
Varianten wählen, nicht beide.)

**Verifikation:** Container mit Anchor „test" anlegen → Collapse/Copy/Screenshot
funktionieren weiterhin; `#test`-Sprunglink funktioniert.

### AP5: ChemViz — veraltete Blocknamen + Reusable-Block-Lücke

**Problem:** `blocks/chart-block/` existiert nicht mehr (nur `interactive-data-chart`).
[includes/class-chemviz-enqueue.php:36](Plugins/Eigene WP Blocks/includes/class-chemviz-enqueue.php)
prüft trotzdem `modular-blocks/chart-block`; `has_chemviz_blocks()` (Zeile 162-177)
kennt `interactive-data-chart` gar nicht. Außerdem findet `has_block()` keine Blöcke
in wiederverwendbaren Blöcken (`core/block`) → 3Dmol/Plotly fehlen dort.

**Aktionen in `class-chemviz-enqueue.php`:**

1. `enqueue_chemviz_assets()`: Toten Check `$has_chart_block` entfernen,
   `interactive-data-chart` behalten.
2. `has_chemviz_blocks()`: Liste auf `['modular-blocks/molecule-viewer', 'modular-blocks/interactive-data-chart']` korrigieren.
3. Reusable-Block-Fallback ergänzen (gleiches Muster wie CDB,
   [class-cbd-block-registration.php:431-435](Plugins/CDB-Designer/includes/class-cbd-block-registration.php)):

```php
$has_reusable = strpos($post->post_content, '<!-- wp:block ') !== false;
if ($has_molecule_viewer || $has_reusable) { $this->enqueue_3dmol(); }
if ($has_interactive_data_chart || $has_reusable) { $this->enqueue_plotly(); }
```

4. Gleiche Bereinigung in `includes/class-block-manager.php` Zeilen ~177 und ~223:
   `$chart_blocks = ['chart-block', 'interactive-data-chart'];` → nur noch
   `['interactive-data-chart']`.

**Verifikation:** Seite mit interactive-data-chart → Plotly lädt; Chart in einem
wiederverwendbaren Block → Plotly lädt ebenfalls; Seite ohne ChemViz → weder
Plotly noch 3Dmol im Network-Tab.

---

## P2 — Touch, Performance, DSGVO

### AP6: Action-Buttons touch-erreichbar machen

**Problem:** `.cbd-action-buttons` sind per Default `opacity:0; pointer-events:none`
und erscheinen nur bei `:hover`/`:focus-within`/`.cbd-selected`
([assets/css/cbd-frontend-clean.css:1033-1062](Plugins/CDB-Designer/assets/css/cbd-frontend-clean.css)).
Auf Touch-Geräten gibt es kein Hover → Buttons sind faktisch unerreichbar. Die
vorhandene Touch-Media-Query (Zeile 948-965) stylt nur das nicht mehr gerenderte
`.cbd-selection-menu`. Buttons sind zudem 32×32 px (< 44 px Empfehlung).

**Aktion:** In `cbd-frontend-clean.css` die Touch-Media-Query erweitern:

```css
@media (hover: none) and (pointer: coarse) {
    .cbd-action-buttons {
        opacity: 1 !important;
        visibility: visible !important;
        transform: none !important;
        pointer-events: auto !important;
        background: rgba(0, 0, 0, 0.06) !important;
    }
    .cbd-action-buttons button {
        width: 44px !important;
        height: 44px !important;
    }
}
```

(`!important` ist hier leider nötig, weil die Basisregeln selbst `!important` nutzen —
Bestandscode-Stil.) Die alten `.cbd-selection-menu`-Regeln in der Media-Query können
bei der Gelegenheit entfernt werden, wenn `grep -rn "cbd-selection-menu" includes/`
bestätigt, dass das Markup nirgends mehr erzeugt wird.

**Verifikation:** Chrome DevTools → Device-Emulation (Touch) → Buttons dauerhaft
sichtbar und tappbar; Desktop-Verhalten (Hover-Einblendung) unverändert.

### AP7: `error_log`-Spam entfernen

**Problem:** [includes/class-block-manager.php](Plugins/Eigene WP Blocks/includes/class-block-manager.php)
ruft 27× ungegatet `error_log()` auf — bei **jedem** Request (init-Hook), inkl.
`print_r()` des Options-Arrays (Zeile ~327). Der vorhandene Helper
`modular_blocks_debug_log()` (modular-blocks-plugin.php:34-40) wird nicht genutzt.
CDB hat 9 ungegatete Aufrufe in
[includes/class-cbd-block-registration.php](Plugins/CDB-Designer/includes/class-cbd-block-registration.php)
(Zeilen ~221-223, 247, 298).

**Aktion:**
1. In `class-block-manager.php` alle `error_log('Modular Blocks Plugin: ...')` durch
   `modular_blocks_debug_log(...)` ersetzen. Rein repetitive Meldungen
   (z. B. „Checking item", „Enabled blocks array" mit print_r) ganz löschen.
2. In `class-cbd-block-registration.php` die ungegateten `error_log()` mit
   `if (defined('WP_DEBUG') && WP_DEBUG)` gaten (Muster existiert dort bereits) —
   Fehlerfälle („Failed to register") dürfen ungegatet bleiben.
3. Auch `modular-blocks-plugin.php:215-221` (Initialisierungs-Logs auf Dateiebene) löschen.

### AP8: CDN-Icon-Fonts lokal bundeln (DSGVO)

**Problem:** [includes/class-cbd-block-registration.php:536-555](Plugins/CDB-Designer/includes/class-cbd-block-registration.php)
lädt auf jeder Container-Seite Font Awesome (cdnjs), **Google Fonts** Material Icons
und Lucide (unpkg). Für eine österreichische Schul-Website datenschutzrechtlich
heikel (insb. Google Fonts) und im Widerspruch zur Lokal-Strategie des anderen
Plugins (3Dmol/Plotly werden lokal gebündelt).

**Aktion:**
1. Die drei Bibliotheken herunterladen nach `Plugins/CDB-Designer/assets/vendor/`:
   - Font Awesome 6.5.1 (Web-Paket: `css/all.min.css` + `webfonts/`)
   - Material Icons (WOFF2 + lokales CSS mit `@font-face`, z. B. via google-webfonts-helper)
   - Lucide Static 0.454.0 (`font/lucide.css` + Fontdateien)
2. Die drei `wp_enqueue_style()`-Aufrufe auf `CBD_PLUGIN_URL . 'assets/vendor/...'` umstellen.
3. Optional (Performance): Nur die Bibliothek laden, deren Präfix im konfigurierten
   Icon vorkommt (`fa-`/`material-icons`/`lucide-`) — die Icon-Werte stehen in den
   gecachten `get_active_blocks()`-Daten (`features.icon.value`).
4. `create-plugin-zip.js` prüfen: `assets/vendor/` muss im ZIP landen.

**Verifikation:** Network-Tab einer Container-Seite: keine Requests an
cdnjs.cloudflare.com, fonts.googleapis.com/gstatic.com oder unpkg.com; Icons aller
drei Bibliotheken rendern weiterhin.

### AP9: ChemViz-Editor-Loading konditionalisieren

**Problem:** [includes/class-chemviz-enqueue.php:140-157](Plugins/Eigene WP Blocks/includes/class-chemviz-enqueue.php)
lädt 3Dmol UND Plotly (~3,5 MB) auf **jeder** Editor-Seite, egal ob der Beitrag
ChemViz-Blöcke enthält.

**Aktion:** In `enqueue_admin_assets()` vor dem Enqueue prüfen:

```php
$content = $post->post_content ?? '';
$has_reusable = strpos($content, '<!-- wp:block ') !== false;
if (has_block('modular-blocks/molecule-viewer', $post) || $has_reusable) {
    $this->enqueue_3dmol();
}
if (has_block('modular-blocks/interactive-data-chart', $post) || $has_reusable) {
    $this->enqueue_plotly();
}
```

Hinweis: Beim ERSTEN Einfügen eines ChemViz-Blocks in einen Beitrag ohne bestehenden
Block ist die Library dann erst nach dem Speichern/Neuladen da. Wenn das stört,
alternativ die Libraries im Editor per `wp_register_script` nur registrieren und vom
Block-`editorScript` als Dependency anfordern lassen (sauberste Lösung, mehr Aufwand).

### AP10: Feature-abhängiges Frontend-Loading im CDB

**Problem:** Auf jeder Container-Seite laden pauschal: dashicons, 3 Icon-Bibliotheken,
html2canvas (~195 KB), `cbd-pdf-server-side`, jQuery
([class-cbd-block-registration.php:444-588](Plugins/CDB-Designer/includes/class-cbd-block-registration.php)).

**Aktion:** Analog zum bereits vorhandenen Muster in
`CBD_Style_Loader::enqueue_feature_styles()` (Zeilen 150-200, nutzt
`extract_active_features_from_blocks()` der Seite):
- `html2canvas` nur enqueuen, wenn Feature `screenshot` auf der Seite aktiv ist
  (oder PDF-Export es braucht — Abhängigkeit von `cbd-pdf-server-side` beachten,
  das html2canvas als Dependency deklariert).
- Icon-Bibliotheken (nach AP8 lokal) nur bei aktivem `icon`-Feature.
- ACHTUNG: Die „ALWAYS visible"-Buttons (AP12) machen Screenshot/PDF derzeit auf
  jeder Seite nutzbar — AP10 daher erst NACH der Entscheidung in AP12 umsetzen.

### AP11: Block-Discovery cachen (Eigene WP Blocks)

**Problem:** `scan_block_directories()` scannt den `blocks/`-Ordner bei jedem
`init` per `scandir` + `file_exists` pro Block
([class-block-manager.php:76-114](Plugins/Eigene WP Blocks/includes/class-block-manager.php)).

**Aktion:** Ergebnis (Array der Blockverzeichnis-Namen) in einem Transient
`modular_blocks_dir_cache` (z. B. 12 h) halten. Invalidieren:
1. im Admin-Upload-Handler nach erfolgreichem Block-Upload/-Löschung
   (`includes/class-admin-manager.php`),
2. bei Plugin-Aktivierung,
3. per `delete_transient` wenn `MODULAR_BLOCKS_PLUGIN_VERSION` sich ändert.
Niedrige Priorität — erst nach AP7, da das Log-Spam-Problem denselben Codepfad betrifft.

---

## P3 — Konsistenz, Architektur, Doku

### AP12: Entscheidung — Buttons vs. Feature-Flags

**Problem:** Der Renderer gibt Collapse/Copy/Screenshot/PDF-Buttons IMMER aus
(„ALWAYS visible", [class-cbd-block-registration.php:1015-1070](Plugins/CDB-Designer/includes/class-cbd-block-registration.php)),
obwohl der Admin Features pro Design konfigurierbar macht und `data-wp-context`
die Flags korrekt mitführt.

**Aktion:** Produktentscheidung dokumentieren und Code angleichen — Empfehlung:
Buttons an Feature-Flags koppeln (Collapse-Button nur bei `collapse.enabled` usw.,
gleiches `if`-Muster wie beim Board-Mode-Button Zeile 1073). Falls stattdessen
„immer alle Buttons" gewollt ist: die Feature-Checkboxen für Copy/Screenshot im
Admin entfernen und `has_interactive_features()` (Zeile 734-738) löschen.
Erst danach AP10 finalisieren.

### AP13: Duplizierte Feature-Save-Logik zentralisieren (CDB)

**Problem:** Identisches `$_POST['features']`-Parsing existiert 4×:
[class-cbd-admin.php:723](Plugins/CDB-Designer/includes/class-cbd-admin.php),
`:2576`, `:2888` und [admin/new-block.php:117](Plugins/CDB-Designer/admin/new-block.php).
Genau diese Duplikation hat den `collapse`/`collapsible`-Drift erzeugt.

**Aktion:** Statische Methode `CBD_Admin::parse_features_from_post(array $post): array`
erstellen (eine Quelle für Keys + Sanitizing), alle 4 Stellen darauf umstellen.
Verhalten identisch halten (gleiche Defaults: `defaultState='expanded'`,
`buttonText='Text kopieren'` usw.).

### AP14: Versions- und Doku-Drift bereinigen

Alle Angaben auf den Ist-Stand bringen:

| Datei | Ist | Soll |
|---|---|---|
| [Plugins/CDB-Designer/composer.json](Plugins/CDB-Designer/composer.json) `version` | 3.1.45 | 3.1.60 (bzw. Feld entfernen, Composer braucht es nicht) |
| [Plugins/CDB-Designer/CLAUDE.md](Plugins/CDB-Designer/CLAUDE.md) „Current Version" | 3.1.45 | 3.1.60 |
| [Plugins/Eigene WP Blocks/package.json](Plugins/Eigene WP Blocks/package.json) `version` | 1.1.3 | 1.1.8 |
| [CLAUDE.md](CLAUDE.md) (Root) Blockliste | demo-card, html-sandbox, chart-block, … | tatsächliche Ordner: drag-and-drop, drag-the-words, iframe-whitelist, image-comparison, image-overlay, interactive-data-chart, molecule-viewer, multiple-choice, point-of-interest, statement-connector, statement-summary, summary-block, svg-drawing |
| [Theme/CLAUDE.md](Theme/CLAUDE.md) | „Simple Clean Theme v1.0" | „FOS Online Schulbuch" 1.5.55, Beschreibung der tatsächlichen Features (Glossar, Customizer-Farben, Passwortschutz, SVG-Support) |
| PHP-Anforderung | Root-CLAUDE.md „PHP 8.0+", CDB-Header „7.4" | Klären: Wenn beide Plugins auf derselben Site laufen, gilt faktisch 8.0. Entweder CDB offiziell auf 8.0 heben (dann `tools/check-php74.php`-Gate entfernen) oder Root-Doku auf „CDB: 7.4 / Modular: 8.0" präzisieren. **Nicht eigenmächtig entscheiden — Rückfrage an Martin.** |

### AP15: TCPDF aus den harten Composer-Requirements nehmen

**Problem:** [composer.json](Plugins/CDB-Designer/composer.json) verlangt `mpdf/mpdf`
UND `tecnickcom/tcpdf`. TCPDF ist laut
[class-cbd-pdf-generator.php:55-59](Plugins/CDB-Designer/includes/class-cbd-pdf-generator.php)
nur Fallback, der nie greift, solange mPDF via Composer installiert ist.

**Aktion:** `tecnickcom/tcpdf` von `require` nach `suggest` verschieben,
`composer update tecnickcom/tcpdf` ausführen, prüfen dass der TCPDF-Fallback-Code
weiter nur hinter `class_exists('TCPDF')` hängt (tut er). ZIP-Größe vorher/nachher
notieren. Der Fallback-Code selbst kann bleiben.

### AP16: Doppelten Menü-Toggle im Theme entfernen

**Problem:** Menü-Toggle existiert zweimal: Inline-Script in
[Theme/header.php:36-60](Theme/header.php) und gebündelt in `Theme/src/js/main.js`
(mit ARIA + ESC + Click-outside). In Theme/CLAUDE.md als Known Issue vermerkt.

**Aktion:** Inline-`<script>`-Block aus `header.php` löschen. Danach
`npm run build` im Theme-Ordner, mobil testen (Hamburger öffnet/schließt, ESC,
Click-outside). Known-Issue-Abschnitt in Theme/CLAUDE.md entfernen.

### AP17: `Throwable` statt `Exception` im Block-Renderer (Eigene WP Blocks)

**Problem:** [class-block-manager.php:304-320](Plugins/Eigene WP Blocks/includes/class-block-manager.php)
fängt nur `Exception`. Ein PHP-`Error` (z. B. TypeError in einer `render.php`)
erzeugt einen White-Screen statt Log + leerem Block. Achtung: `ob_start()` bleibt
im Fehlerfall außerdem offen.

**Aktion:**

```php
try {
    include $render_file;
} catch (Throwable $e) {          // Plugin verlangt PHP 8.0 — Throwable ist ok
    ob_end_clean();               // offenen Buffer schließen!
    modular_blocks_debug_log("Error rendering block {$block_name}: " . $e->getMessage());
    return '';
}
return ob_get_clean();
```

### AP18: Nummerierungsformate robust machen (CDB, klein)

**Problem:** [class-cbd-block-registration.php:1241-1254](Plugins/CDB-Designer/includes/class-cbd-block-registration.php):
`alphabetic` liefert ab Block 27 Sonderzeichen (`chr(64+27)`), `roman` fällt ab XI
auf die Zahl zurück.

**Aktion:** `alphabetic`: nach Z mit AA, AB … fortsetzen (Excel-Spaltenlogik);
`roman`: echte Konvertierungsfunktion (Subtraktionsregel) statt Lookup-Array.

---

## Abarbeitungs-Checkliste

| AP | Titel | Status |
|----|-------|--------|
| 1 | Feature-Key-Vereinheitlichung + Migration | ✅ ERLEDIGT (2026-07-04) — Migration `migrate_feature_keys_to_3_1_61`, DB_VERSION 3.1.61 |
| 2 | `$has_icon = true` entfernen | ✅ ERLEDIGT (2026-07-04) |
| 3 | DELETE-LIKE entschärfen | ✅ ERLEDIGT (2026-07-04) — exakte Alt-Namen statt LIKE |
| 4 | Anchor-ID-Konflikt | ✅ ERLEDIGT (2026-07-04) — Anchor liegt jetzt auf `.cbd-container-block` |
| 5 | ChemViz Blocknamen + Reusable-Fallback | ✅ ERLEDIGT (2026-07-04) |
| 6 | Touch-CSS für Action-Buttons | ✅ ERLEDIGT (2026-07-04) |
| 7 | error_log-Spam | ✅ ERLEDIGT (2026-07-04) |
| 8 | Icon-Fonts lokal (DSGVO) | ✅ ERLEDIGT (2026-07-04) — FA/Material/Lucide in `assets/vendor/`, jsPDF in `assets/lib/`; Lucide-CSS auf `.lucide-*`-Klassen umgeschrieben (waren mit CDN-CSS kaputt) |
| 9 | ChemViz-Editor konditional | ✅ ERLEDIGT (2026-07-04) |
| 10 | Feature-abhängiges Frontend-Loading | ✅ ERLEDIGT (2026-07-04) — Icon-Libs per `get_required_icon_libraries()` gegated; html2canvas bleibt (Dependency des immer verfügbaren PDF-Buttons), dashicons bleibt (Buttons selbst) |
| 11 | Block-Discovery-Cache | ✅ ERLEDIGT (2026-07-04) — Transient `modular_blocks_dir_cache`, Invalidierung bei Upload/Löschen/Anlegen/Aktivierung |
| 12 | Buttons vs. Feature-Flags | ✅ ERLEDIGT (2026-07-04) — Entscheidung: Flags respektieren; Collapse/Copy/Screenshot nur bei aktivem Feature, PDF immer (kein Flag vorhanden) |
| 13 | Save-Logik zentralisieren | ✅ ERLEDIGT (2026-07-04) — `cbd_parse_features_from_post()` in includes/functions.php, alle 4 Stellen umgestellt |
| 14 | Versions-/Doku-Drift | ✅ ERLEDIGT (2026-07-04) — Entscheidung: CDB bleibt PHP 7.4; Doku präzisiert |
| 15 | TCPDF → suggest | ✅ ERLEDIGT (2026-07-04) — zusätzlich Lock 7.4-konsistent neu aufgelöst (psr/log 3.0.2→1.1.4 war PHP-8-only und hätte PDF auf 7.4 gebrochen); mpdf auf ~8.2.0 gepinnt |
| 16 | Doppelter Menü-Toggle | ✅ OBSOLET (2026-07-04) — main.js enthält keinen Toggle mehr; Inline-Script in header.php ist die einzige (vollwertige) Implementierung. Nur Doku korrigiert |
| 17 | Throwable im Renderer | ✅ ERLEDIGT (2026-07-04) — inkl. ob_end_clean() im Fehlerfall |
| 18 | Nummerierungsformate | ✅ ERLEDIGT (2026-07-04) — Excel-Spaltenlogik + echte Römisch-Konvertierung |

**Pro AP:** eigener Commit mit Verweis auf die AP-Nummer, vorher `php -l`-Durchlauf,
bei CDB zusätzlich `php tools/check-php74.php`, bei Eigene WP Blocks
`npm run build && npm run block-zips`, beim Theme `npm run build`.

**Offene Rückfragen an Martin (blockieren nur die genannten APs):**
1. AP12: Sollen die Action-Buttons den Feature-Flags gehorchen oder immer sichtbar sein?
2. AP14: CDB offiziell auf PHP 8.0 heben oder bei 7.4 bleiben?
3. AP3: Gab es früher Default-Blöcke mit anderen deutschen Namen als
   „Einfacher Container" / „Info-Box" / „Klappbarer Container"? (Für die exakte DELETE-Liste.)
