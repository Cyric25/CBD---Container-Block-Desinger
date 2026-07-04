# Verbesserungsplan 2 (zweiter Review-Durchgang, 2026-07-04)

Frischer Durchgang durch bisher nicht tief geprüfte Bereiche: AJAX-Handler,
LaTeX-Parser, PDF-Generator, Classroom, Service-Container, Frontend-JS,
Block-Implementierungen (render.php/view.js), Manager-Klassen und die hintere
Hälfte der Theme-functions.php. Nummerierung setzt VERBESSERUNGSPLAN.md fort (AP19+).

## Rahmenbedingungen (wie Plan 1)

- CDB-Designer: **PHP 7.4** (kein PHP-8-Syntax), vor ZIP `php tools/check-php74.php`
- Eigene WP Blocks: Core-Änderungen → `plugin-zip-empty` neu bauen; Block-Änderungen
  → `npm run build && npm run block-zips`, nur betroffene Block-ZIPs hochladen
- Vor jedem Commit: `php -l` über geänderte Dateien

---

## ✅ Was im zweiten Durchgang positiv auffiel

- **PDF-Pipeline:** Rate-Limiting (15/min/IP) auf beiden Endpunkten, saubere
  Base64-/HTML-Sanitisierung, Temp-Dateien-Cleanup (1h) bei jeder Generierung.
- **Theme-Passwortschutz:** Gehashtes Passwort, Brute-Force-Lockout (10 Versuche/15min/IP),
  abgeleiteter Cookie-Token statt Klartext, Nonce — vorbildlich.
- **SVG-Upload-Sanitizing** ([functions.php:3624-3704](Theme/functions.php)):
  DOM-basiert, SMIL-/Script-Entfernung, href-Whitelist — deutlich besser als übliche Lösungen.
- **board-mode.js:** Pointer-Events mit gebundenen Handlern und sauberem removeEventListener.
- **Alle geprüften AJAX-Endpunkte** (iframe-whitelist, h5p-import, classroom-cleanup)
  haben Nonce- und Capability-Checks.
- **Neuere Blöcke** (summary-block, drag-and-drop, interactive-data-chart) haben
  bereits `dataset.initialized`-Guards — das Muster existiert, ist nur nicht überall.

---

## P1 — Funktionsfehler

### AP19: `set_default_block()` setzt alte Defaults nie zurück

**Datei:** [Plugins/CDB-Designer/includes/class-cbd-ajax-handler.php:671-677](Plugins/CDB-Designer/includes/class-cbd-ajax-handler.php)

**Problem:** Das Zurücksetzen aller anderen Blöcke nutzt `$wpdb->update()` mit
**leerem WHERE-Array**:

```php
$wpdb->update($table_name, array('is_default' => 0), array(), array('%d'), array());
```

`wpdb::update()` erzeugt damit ungültiges SQL (`UPDATE ... WHERE `) und schlägt
still fehl → nach mehrmaligem "Als Standard setzen" haben **mehrere Blöcke
`is_default = 1`**. Welcher dann im Editor als Standard gilt, ist zufällig
(erste Zeile der Abfrage).

**Fix:**

```php
$wpdb->query("UPDATE {$table_name} SET is_default = 0");
```

**Verifikation:** Block A als Standard setzen, dann Block B; danach
`SELECT id, is_default FROM wp_cbd_blocks WHERE is_default = 1` → genau 1 Zeile.

### AP20: Doppelte Initialisierung in 5 interaktiven Blöcken

**Dateien:** `Plugins/Eigene WP Blocks/blocks/{drag-the-words, image-comparison,
multiple-choice, point-of-interest, statement-connector}/`

**Problem:** Diese Blöcke werden **zweimal** initialisiert:
1. `view.js` auto-initialisiert alle Blöcke auf `DOMContentLoaded` **und** per
   MutationObserver (z. B. [multiple-choice/view.js:349-358](Plugins/Eigene WP Blocks/blocks/multiple-choice/view.js)),
2. `render.php` enthält zusätzlich einen Inline-Bootstrap, der
   `window.init...(block)` für denselben Block nochmal aufruft
   (z. B. [multiple-choice/render.php:183-191](Plugins/Eigene WP Blocks/blocks/multiple-choice/render.php)).

Da diese fünf `view.js` **keinen** `dataset.initialized`-Guard haben, werden
alle Event-Handler doppelt gebunden — bei drag-the-words/statement-connector
kann das doppelte Drop-/Klick-Verarbeitung auslösen, bei multiple-choice
doppelte Toggle-Logik.

**Fix (pro Block, Muster aus [summary-block/view.js:47-48](Plugins/Eigene WP Blocks/blocks/summary-block/view.js)):**

```js
function initXyz(element) {
    if (!element || element.dataset.initialized === 'true') return;
    element.dataset.initialized = 'true';
    // ... bestehender Code
}
```

Zusätzlich die redundanten Inline-`<script>`-Bootstraps aus den `render.php`
entfernen (Auto-Init + MutationObserver in view.js decken alle Fälle ab,
auch AJAX-nachgeladene Inhalte).

**Danach:** `npm run build && npm run block-zips`, die 5 Block-ZIPs hochladen.

**Verifikation:** multiple-choice: Antwort anklicken → genau ein Toggle;
drag-the-words: Wort per Touch ziehen → landet genau einmal im Blank.

### AP21: Nummerierungs-Marker ignoriert das `numbering`-Feature

**Datei:** [Plugins/CDB-Designer/includes/class-cbd-block-registration.php:1005-1007](Plugins/CDB-Designer/includes/class-cbd-block-registration.php)

**Problem:** `$show_number = (self::$render_depth == 1);` — der schwarze
Nummern-Kreis wird für **jeden** Top-Level-Container gerendert, egal ob das
Feature im Block-Design aktiviert ist. Gleiches Muster wie der behobene
Icon-Bug (AP2). Zusätzlich läuft `block-numbering.js` (inkl. MutationObserver
auf dem gesamten Body) auf jeder Container-Seite.

**Fix:**

```php
$show_number = (self::$render_depth == 1) && !empty($features['numbering']['enabled']);
```

Optional dazu: `cbd-block-numbering` nur enqueuen, wenn ein aktives Design
`numbering` nutzt (Muster `get_required_icon_libraries()` aus AP10), und die
hartkodierten Inline-Styles des Markers (Zeile ~1009) in
`cbd-frontend-clean.css` verschieben.

---

## P2 — DSGVO, Performance, Aufräumen

### AP23: KaTeX lokal bundeln (DSGVO — letzter verbleibender CDN-Load)

**Datei:** [Plugins/CDB-Designer/includes/class-latex-parser.php:100-123](Plugins/CDB-Designer/includes/class-latex-parser.php)

**Problem:** KaTeX CSS + JS + auto-render laden von `cdn.jsdelivr.net` — nach
AP8 (Icon-Fonts) der letzte externe CDN-Request im Frontend.

**Schritte:**
1. Download nach `Plugins/CDB-Designer/assets/vendor/katex/`:
   `katex.min.css`, `katex.min.js`, `contrib/auto-render.min.js` **und den
   kompletten `fonts/`-Ordner** (katex.min.css referenziert `fonts/...` relativ —
   ohne die ~60 Font-Dateien rendern die Formeln falsch).
   Quelle: `https://cdn.jsdelivr.net/npm/katex@0.16.9/dist/`
2. Die drei Enqueues auf `CBD_PLUGIN_URL . 'assets/vendor/katex/...'` umstellen.
3. Prüfen, dass `assets/` im Plugin-ZIP landet (tut es laut create-plugin-zip.js).

**Verifikation:** Seite mit `$$E=mc^2$$` → Formel rendert, Network-Tab ohne
jsdelivr-Requests, Fonts laden lokal (kein 404 auf `fonts/KaTeX_*.woff2`).

### AP24: `list_webapps()` rechnet Verzeichnisgrößen bei jedem Editor-Load

**Dateien:** [Plugins/Eigene WP Blocks/includes/class-webapp-manager.php:263-309](Plugins/Eigene WP Blocks/includes/class-webapp-manager.php),
[modular-blocks-plugin.php:149-169](Plugins/Eigene WP Blocks/modular-blocks-plugin.php)

**Problem:** `enqueue_webapp_data()` ruft bei jedem Block-Editor-Aufruf
`list_webapps()` auf, das für **jede** Web-App per `RecursiveIteratorIterator`
die komplette Verzeichnisgröße summiert (`get_directory_size()`). Bei mehreren/
großen Web-Apps ist das ein spürbarer Filesystem-Walk pro Editor-Load —
und der Editor braucht die Größe gar nicht (nur name + url).

**Fix:** `list_webapps($with_size = false)` — Größe nur berechnen, wenn
angefordert; die Admin-Übersichtsseite (`webapps_page_callback`) ruft mit
`true` auf, `enqueue_webapp_data()` ohne.

### AP22: Toten Code im AJAX-Handler entfernen + Capability angleichen

**Datei:** [Plugins/CDB-Designer/includes/class-cbd-ajax-handler.php](Plugins/CDB-Designer/includes/class-cbd-ajax-handler.php)

1. `save_block()` (Zeile ~326) und `delete_block()` (Zeile ~370) sind **nie
   registriert** (Kommentar Zeile 37: CBD_Admin übernimmt das) — löschen.
   Sie hätten zudem schwächere Prüfungen (`edit_posts`, `json_encode($_POST[...])`
   ohne Struktur-Sanitizing) und sind eine Falle, falls sie jemand re-registriert.
2. `duplicate_block()` (Zeile ~414) verlangt nur `edit_posts`, während
   `set_default_block()` korrekt `cbd_admin_blocks` verlangt. Duplizieren ist
   eine Design-Verwaltungsaktion → auf `cbd_admin_blocks` anheben.
3. `get_blocks()` (Zeile ~229): 5 ungegatete `error_log()` gaten; die
   `SHOW TABLES`-Prüfung pro Request entfernen; statt eigener Query mit
   abweichender Statuslogik (`status IS NULL`) den vorhandenen Cache
   `CBD_Block_Registration::get_active_blocks()` verwenden (AP27 gleich miterledigt).

### AP25: Restliches Logging vereinheitlichen

- [class-cbd-classroom.php](Plugins/CDB-Designer/includes/class-cbd-classroom.php):
  31 `error_log()`-Aufrufe, viele ungegated in AJAX-Pfaden (z. B. Zeile 1398) —
  nach dem AP7-Muster gaten (Fehlerfälle dürfen bleiben).
- [class-cbd-admin.php](Plugins/CDB-Designer/includes/class-cbd-admin.php): 20 Aufrufe,
  u. a. `[CBD Edit Save]`-Debug bei jedem Speichern (Zeile ~2890) — gaten/löschen.
- Theme [functions.php:3104](Theme/functions.php): `error_log("AI Crawler blocked: ...")`
  feuert bei **jedem** Bot-Request — bei Bot-Traffic füllt das die Logs; hinter
  eine Option oder WP_DEBUG legen.
- Frontend-JS: `console.log` in block-numbering.js (3×), pdf-server-side.js (13×),
  block-editor.js (5×) — entfernen oder hinter ein `window.cbdDebug`-Flag.

---

## P3 — Robustheit, Kleinigkeiten

### AP26: Inline-`$...$`-Parsing entschärfen (False Positives)

**Datei:** [class-latex-parser.php:294-301](Plugins/CDB-Designer/includes/class-latex-parser.php)

Text wie „Kosten: $5 bis $10" wird als Formel gerendert (zwei `$` = Formel
„5 bis "). Optionen (eine wählen):
- Nur parsen, wenn zwischen den `$` kein Leerzeichen direkt nach dem ersten /
  vor dem letzten `$` steht (KaTeX-Konvention: `$x$` ja, `$ x $`/`$5 und $10` nein), oder
- Admin-Option „Inline-Formeln nur mit `\( ... \)`" ergänzen.
Da eine Chemie-Schulseite kaum Dollarbeträge enthält: niedrige Priorität,
aber dokumentieren.

### AP27: `get_blocks()` auf den Block-Cache umstellen

In AP22 (Punkt 3) enthalten — eigene DB-Query + `SHOW TABLES` durch
`CBD_Block_Registration::get_active_blocks()` ersetzen und das Ergebnis auf
das vom Editor erwartete Format mappen. Achtung: aktuelle Query nimmt auch
`status IS NULL`-Zeilen mit — vorher prüfen, ob solche Zeilen existieren
(`SELECT COUNT(*) FROM wp_cbd_blocks WHERE status IS NULL`), sonst Verhalten identisch halten.

### AP28: Service-Container: `admin`-Service gegen Frontend-Fatal absichern

**Datei:** [class-service-container.php:200-202](Plugins/CDB-Designer/includes/class-service-container.php)

`CBD_Admin` wird nur bei `is_admin()` geladen; ein `get('admin')` im Frontend
wäre ein Fatal Error. Factory absichern:

```php
$this->register('admin', function($container, $config) {
    if (!class_exists('CBD_Admin')) {
        throw new Exception('CBD_Admin ist nur im Admin-Kontext verfügbar');
    }
    return CBD_Admin::get_instance();
});
```

### AP29: block-numbering.js entschlacken

**Datei:** [assets/js/block-numbering.js](Plugins/CDB-Designer/assets/js/block-numbering.js)

Drei gestaffelte `setTimeout`-Aufrufe (100/500/1000ms) + MutationObserver sind
doppelt gemoppelt — ein Initial-Aufruf + Observer reicht. `console.log` (3×)
entfernen. Zusammen mit AP21 umsetzen (gleiche Baustelle).

---

## Abarbeitungs-Checkliste

| AP | Titel | Status |
|----|-------|--------|
| 19 | is_default-Reset repariert (leeres WHERE) | ✅ ERLEDIGT (2026-07-04) — direkter UPDATE-Query ohne WHERE |
| 20 | Doppel-Init in 5 Blöcken | ✅ ERLEDIGT (2026-07-04) — `dataset.initialized`-Guard in allen 5 view.js, Inline-Bootstraps aus render.php entfernt |
| 21 | Nummerierung an Feature-Flag koppeln | ✅ ERLEDIGT (2026-07-04) — Marker nur bei `numbering.enabled`; block-numbering.js nur enqueued, wenn ein Design das Feature nutzt (`any_active_block_has_feature()`) |
| 22 | Toter AJAX-Code, Capability, get_blocks | ✅ ERLEDIGT (2026-07-04) — save_block/delete_block gelöscht, duplicate_block auf `cbd_admin_blocks`, SHOW TABLES raus, Logs gegated |
| 23 | KaTeX lokal (inkl. fonts/) | ✅ ERLEDIGT (2026-07-04) — `assets/vendor/katex/` mit allen 60 Fonts (~1,1 MB). Offen gelassen: emoji-picker-element (jsdelivr) im Admin — lädt zur Laufzeit Emoji-Daten vom CDN nach, Lokalisierung wäre größerer Umbau; nur Admin-Nutzer betroffen |
| 24 | list_webapps ohne Größenberechnung | ✅ ERLEDIGT (2026-07-04) — `$with_size`-Parameter; nur die Admin-Übersicht rechnet noch Größen |
| 25 | Logging-Restbestand | ✅ ERLEDIGT (2026-07-04) — debug_log()-Helper in Classroom (26 gegated, 5 echte Fehler bleiben) und Admin (alle 20); AI-Blocker-Log hinter WP_DEBUG; console.log in 5 JS-Dateien hinter `window.cbdDebug` |
| 26 | Inline-$-False-Positives | ✅ ERLEDIGT (2026-07-04) — KaTeX-Konvention: kein Whitespace direkt nach/vor `$`. „$5 bis $10" bleibt Text; `$x^2$` unverändert. ACHTUNG: falls im Bestand `$ formel $` (Whitespace-gepolstert) existiert, rendert das nicht mehr — bei Beschwerden hier ansetzen |
| 27 | get_blocks auf Cache | ✅ TEILWEISE (2026-07-04) — SHOW TABLES entfernt, Logs gegated; bewusst NICHT auf get_active_blocks() umgestellt, weil der Endpunkt auch `status IS NULL`-Legacy-Zeilen liefert und das ohne DB-Zugriff nicht verifizierbar war |
| 28 | admin-Service-Guard | ✅ ERLEDIGT (2026-07-04) |
| 29 | block-numbering.js entschlacken | ✅ ERLEDIGT (2026-07-04) — Nachlauf-Timeouts raus, console.log raus |
