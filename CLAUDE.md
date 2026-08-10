# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Container Block Designer is a WordPress plugin that creates customizable container blocks for the Gutenberg Block Editor. It allows users to create, manage, and apply styled container blocks with features like collapsible sections, copy-to-clipboard, screenshots, and automatic numbering.

**Current Version:** 3.1.75
**WordPress Requirements:** 6.0+
**PHP Requirements:** 7.4+ (rückwärtskompatibel; getestet auf 7.4.33)
**Tested up to:** WordPress 6.4, PHP 8.4

## Architecture

### Core Plugin Structure

The plugin follows a **singleton pattern** with **service container architecture**:

1. **Main Plugin Class** (`container-block-designer.php`): `ContainerBlockDesigner` singleton that bootstraps the entire plugin
2. **Service Container** (`includes/class-service-container.php`): Dependency injection container managing all services
3. **Autoloader** (`includes/class-autoloader.php`): PSR-4 compatible fallback autoloader when Composer is unavailable

### Initialization Flow

1. `ContainerBlockDesigner::get_instance()` creates singleton
2. Service container initialized via `init_container()`
3. Dependencies loaded via `load_dependencies()`
4. Services registered through container: database, style_loader, block_registration, ajax_handler, admin
5. Block registration occurs on `init` hook through `CBD_Block_Registration::register_blocks()`

### Key Services (Service Container)

Access services via: `cbd_get_service('service_name')` or through the container

- **database**: Database operations wrapper
- **style_loader**: Dynamic CSS generation and caching (`CBD_Style_Loader`)
- **block_registration**: Registers blocks with WordPress (`CBD_Block_Registration`)
- **ajax_handler**: Handles AJAX requests (`CBD_Ajax_Handler`)
- **admin**: Admin interface management (`CBD_Admin`)
- **schema_manager**: Database schema and migrations (`CBD_Schema_Manager`)

### Database Schema

**Table:** `{$wpdb->prefix}cbd_blocks`

Columns:
- `id`: Auto-increment primary key
- `name`: Unique block identifier (slug format, e.g., 'basic-container')
- `title`: Display name
- `description`: Block description
- `config`: JSON configuration (allowInnerBlocks, maxWidth, minHeight)
- `styles`: JSON styles (padding, background, border, typography)
- `features`: JSON features (collapsible, icon, copyText, screenshot, numbering)
- `status`: 'active' or 'inactive'
- `created_at`, `updated_at`: Timestamps

### Block Registration System

Blocks are **dynamically registered** from database entries:

1. `CBD_Block_Registration::register_blocks()` queries active blocks from database
2. Each block registered as `container-block-designer/{sanitized-name}`
3. Blocks support nested InnerBlocks via Gutenberg
4. Render callback: `CBD_Block_Registration::render_block()`

**Important:** Block names in database must be lowercase, hyphenated (e.g., 'basic-container', 'card-container', 'hero-section')

### Frontend Rendering

- **Primary Renderer**: `CBD_Block_Registration::render_block()` (server-side block rendering)
- **Frontend JavaScript**: `assets/js/interactivity-fallback.js` handles interactive features (collapsible, copy, screenshot)
- **Interactivity API**: `assets/js/interactivity-store.js` uses WordPress Interactivity API for modern state management
- **Features Handled:**
  - Collapsible sections (with toggle buttons)
  - Copy text to clipboard
  - Screenshot generation (using html2canvas)
  - Automatic numbering of nested blocks
  - LaTeX math rendering via KaTeX

### Admin Interface

Admin pages (located in `admin/` directory):
- **Blocks List** (`blocks-list.php`): Overview of all container blocks
- **New Block** (`new-block.php`): Create new container block designs
- **Edit Block** (`edit-block.php`): Modify existing block configurations
- **Settings** (`settings.php`): Plugin settings
- **Import/Export** (`import-export.php`): Bulk operations for blocks

Admin menu registered via `CBD_Admin::add_admin_menu()`

### Custom User Roles

**Block-Redakteur Role:** Limited access role for content editors
- Can edit pages and use container blocks
- Cannot create/modify block designs
- Cannot access Posts menu (hidden via CSS/JS)
- Managed by `ContainerBlockDesigner::create_block_editor_role()`

**Capabilities:**
- `cbd_edit_blocks`: Use container blocks in editor
- `cbd_edit_styles`: Edit block styles (admins only)
- `cbd_admin_blocks`: Access admin interface (admins only)

## Development Commands

### PHP/WordPress

```bash
# Run WordPress CLI commands
wp core version

# Check database tables
wp db query "SHOW TABLES LIKE 'wp_cbd_blocks'"

# Clear plugin caches
wp cache flush
```

### Code Quality (Composer scripts)

```bash
# Run PHPUnit tests
composer test

# Run PHP CodeSniffer (WordPress Coding Standards)
composer cs

# Auto-fix coding standards issues
composer cbf

# Static analysis (PHPStan)
composer analyze
```

### Common Development Tasks

1. **Clearing Plugin Cache:**
   - Style cache stored in WordPress transients
   - Clear via: `CBD_Style_Loader::get_instance()->clear_styles_cache()`
   - Or flush all caches: `wp cache flush`

2. **Adding New Block Types:**
   - Insert into `{$wpdb->prefix}cbd_blocks` table
   - Or use admin interface: Admin → Container Blocks → Block hinzufügen

3. **Debugging Block Registration:**
   - Enable WP_DEBUG in wp-config.php
   - Check error logs at: `wp-content/debug.log`
   - Block registration logs prefixed with `[CBD Block Registration]`

### Plugin ZIP Creation

**CRITICAL: Always run syntax check before creating plugin ZIP!**

CDB-Designer uses pure PHP with vanilla JavaScript (no build process required), but syntax checking is mandatory.

**PHP 7.4-Kompatibilität (automatisch):** `node create-plugin-zip.js` führt vor dem
Versions-Bump/ZIP-Bau automatisch `tools/check-php74.php` aus (parst alle Plugin-Dateien
gezielt gegen PHP 7.4 via nikic/php-parser) und **bricht ab, wenn PHP-8.0-only-Syntax
gefunden wird**. Wichtig, weil lokal oft PHP 8.x läuft und `php -l` 8.0-Syntax NICHT als
Fehler meldet — die Zielumgebung ist aber PHP 7.4.33. Manuell prüfbar mit
`php tools/check-php74.php`.

**Create distributable plugin ZIP:**

```bash
# ALWAYS run syntax check first!
for file in *.php includes/*.php includes/Database/*.php; do php -l "$file" || exit 1; done && node create-plugin-zip.js
```

**Syntax Check (MANDATORY before ZIP creation):**

```bash
# Check all PHP files for syntax errors
for file in *.php includes/*.php includes/Database/*.php; do
  echo "Checking $file..."
  php -l "$file" || exit 1
done
```

**Complete workflow (recommended):**

```bash
# 1. Syntax check all PHP files
for file in *.php includes/*.php includes/Database/*.php; do
  php -l "$file" || exit 1
done

# 2. If no errors: Create plugin ZIP
node create-plugin-zip.js

# 3. Commit and push
git add .
git commit -m "Your commit message"
git push origin main
```

**Why this matters:**
- Prevents distributing broken PHP code
- Catches syntax errors in main plugin file, includes, and Database classes
- Ensures WordPress won't show fatal errors
- Required before every ZIP creation

**What gets checked:**
- Plugin main file: `container-block-designer.php`
- All files in `includes/` directory
- All files in `includes/Database/` directory
- Syntax validation via `php -l`
- Exit immediately on first error (`|| exit 1`)

**If syntax error found:**
- Fix the error
- Re-run syntax check
- Only then create ZIP

**ZIP output location:** `container-block-designer-v{version}.zip` (plugin root)

**Autoloader-Schutz (KRITISCH, seit v3.1.66):** Das ZIP schließt Composer-
Dev-Pakete (phpunit, wpcs, …) aus. `create-plugin-zip.js` führt deshalb vor
dem Zippen automatisch `composer dump-autoload --no-dev --optimize` aus und
stellt den Dev-Autoloader danach wieder her. **Diesen Schritt niemals
entfernen** — ein mit Dev-Paketen generierter Autoloader bindet phpunit-Dateien
fest ein → Fatal Error / HTTP 500 auf der Zielinstallation (passiert bei den
ZIPs v3.1.63–3.1.65). ZIPs daher NUR über `node create-plugin-zip.js` bauen,
nie manuell zippen. Verifikation nach dem Bau: ZIP entpacken und
`php -r 'define("ABSPATH","/"); require ".../vendor/autoload.php";'` muss
ohne Fatal laufen.

## Content-Importer (Markdown → Container-Blöcke)

Editor → Menü „⋮" → **Inhalt importieren (K1/K2/K3)**.
PHP: `includes/class-cbd-content-importer.php`, UI: `assets/js/content-importer.js`.

> Es gibt **zwei Wege in denselben Parser**: diesen Dialog im Editor und den
> Seitenimport (Abschnitt „Block-Serializer" weiter unten), der ganze Seiten
> aus Markdown-Dateien anlegt. `parse_markdown_content()` ist geteilt und darf
> nicht einseitig geändert werden — jede Änderung trifft beide Wege.

**Parser-Regeln (seit v3.1.70 strukturtolerant — es geht KEIN Inhalt verloren):**

| Markdown | Bedeutung |
|---|---|
| `# H1` | Thema (`topic`); dient als Titel-Fallback |
| `## H2` | **Stilname.** Jede H2 bildet eine eigene Gruppe (`h2-<slug>`) = einen Stil-Slot im Dialog. Die Kompetenz-Schlüsselwörter (`$section_keywords`) steuern nur noch Badge-Farbe und den Legacy-Vorschlag, NICHT die Gruppierung |
| `### H3` | Block-Titel (nicht im Inhalt) |
| `<!-- accordion: … -->` | **Accordion-Direktive** für die gesamte laufende H2-Gruppe (siehe unten) |
| alles andere | Inhalt |

### Accordion-Import (seit v3.1.76)

Eine Zeile der Form `<!-- accordion: level=3, numbering=true, multiple=false, openFirst=false, expandAll=false -->`
unter einer H2 bedeutet: Die `###`-Abschnitte dieser Gruppe werden **nicht** als
einzelne Container-Blöcke eingefügt, sondern als **ein** Block
`modular-blocks/accordion` (Plugin „Eigene WP Blocks"), in dem jeder Abschnitt
eine Klappzeile bildet — Abschnittstitel wird zur Überschrift der Ebene `level`,
Abschnittsinhalt zum Zeileninhalt. Ohne Optionen (`<!-- accordion -->`) gelten
die Defaults; `level` außerhalb 2–5 fällt auf 3 zurück; Wahrheitswerte werden
tolerant gelesen (`true`/`1`/`ja`/`yes`).

Die Direktivzeile wird aus dem Inhalt entfernt — sonst entstünde ein
Extra-Abschnitt, dessen Inhalt nur aus dem HTML-Kommentar besteht. Die Direktive
ist **gruppen-, nicht abschnittsgebunden** und wird bei der nächsten H2 (und bei
H1) zurückgesetzt.

Datenweg: `parse_accordion_directive()` → `$groups[$key]['accordion']`
(`null` oder Objekt mit `enabled`/`level`/`multiple`/`numbering`/`openFirst`/`expandAll`)
→ AJAX-Antwort unter `response.data.groups[i].accordion` → Einfüge-Zweig in
`assets/js/content-importer.js`.

**Kreuz-Plugin-Abhängigkeit (wichtig):** Der Zielblock stammt aus einem anderen
Plugin. Das JS erzeugt ihn nur, wenn `wp.blocks.getBlockType('modular-blocks/accordion')`
ihn kennt; fehlt er (Plugin nicht aktiv oder Block in „Einstellungen → Modulare
Blöcke" abgeschaltet), greift das alte Verhalten und der Dialog zeigt eine
erklärende Warnung. Niemals einen unregistrierten Blocktyp erzeugen — das ergibt
im Editor „Block enthält unerwarteten Inhalt".

**Kombination mit Stil:** Ist der Gruppe zusätzlich ein Container-Design
zugewiesen, liegt das Accordion **innerhalb** des Containers (Container außen,
`blockTitle` = H2-Text). Ohne Zuweisung kommt es direkt in die Seite.

Im Dialog zeigt jede Direktiv-Gruppe „Wird als Accordion importiert – N
Klappzeilen" plus eine vorbelegte Checkbox; Abwählen erzwingt das alte
Verhalten. Die Direktive ist also ein Vorschlag, keine Zwangsjacke.

Beim Ausrollen zählt die Reihenfolge: **erst** das Block-ZIP `accordion.zip`
(damit der Blocktyp existiert), **dann** das CDB-Plugin-ZIP.

Titel-Fallback-Kette: H3 → H2 → H1 → „Abschnitt N". Erfasst werden auch
Präambeln vor der ersten Überschrift, Inhalt direkt unter H2 und Dateien
**ganz ohne Überschriften** (früher gingen diese Fälle still verloren, weil
`save_block()` `topic && competence && title` verlangte).

**Gruppen & automatische Style-Zuordnung (v3.1.71):** Jede H2, die keine
Kompetenzstufe ist, wird zu einer eigenen Gruppe mit eigener Style-Zeile im
Dialog — `## Übungen` und `## Hinweise` sind also getrennt zuweisbar, nicht
mehr ein Sammeltopf. `attach_style_suggestions()` matcht den H2-Text gegen
Name/Slug der aktiven Block-Designs:

1. **exakt** normalisiert (`match_style_for_label()` → `normalize_key()`:
   lowercase, Umlaute → ae/oe/ue, Sonderzeichen → `-`), z. B. „Übungen" = `uebungen`
2. **Stammform** (`stem_key()`, Singular/Plural + Umlaut-Plural):
   „Hinweise" ≈ `hinweis`, „Merksätze" ≈ `merksatz`
3. **Teilstring** (ab 4 Zeichen, längster Treffer): „Übungen zum Kapitel" ⊃ `uebungen`

Vorschlagsreihenfolge: (1) exakter Name/Slug-Treffer → automatisch zuweisen;
(2) klassische Kompetenz-Überschrift („## Basiswissen") ohne gleichnamiges
Design → Legacy-Default `infotext_k1/k2/k3`/`quellen`; (3) nur unscharfer
Treffer → Hinweis ohne Zuweisung.

**Achtung Teilstring-Falle (Fix v3.1.75):** Schlüsselwörter werden nur erkannt,
wenn sie die ganze Überschrift oder deren erstes Wort sind. Vorher matchte
`strpos($heading,'k1')` auch `## aufgaben_k1` und `## hilfen_k1` → alle fielen
mit `## infotext_k1` in eine Gruppe. Zusätzlich gilt: entspricht die H2 dem
Namen/Slug eines Designs, ist sie immer eine Stil-Angabe.
`hasSubheadings` je Gruppe zeigt, ob `###`-Unterabschnitte folgen.

**Automatisch vorbelegt wird NUR Strategie 1 (exakte Namensgleichheit).**
Unscharfe Treffer (2/3) erscheinen ausschließlich als Hinweis am Select
(`similarStyle`) — die Zuweisung macht der Nutzer bewusst selbst; das Select
steht dann auf „ohne Container". Warn-Notice nennt alle Gruppen ohne exakten
Treffer. `other` = Abschnitte ganz ohne Überschrift.
`competence` bleibt für Farben/Rückwärtskompatibilität erhalten, für die
Style-Zuweisung ist `groupKey` maßgeblich.

**Import ohne (passendes) Block-Design:** Jedes Style-Select enthält
„— ohne Container (nur Inhalt) —" (JS-Konstante `NO_CONTAINER = '__none__'`).
Ist kein Design zugewiesen ODER der Slug existiert nicht in der DB, fügt das
UI den Abschnitt als Heading + Inhaltsblöcke ohne Container ein statt ihn zu
verwerfen. Ein Container mit unbekanntem Slug wird bewusst NIE erzeugt (würde
im Frontend „Block nicht gefunden" rendern).

**Inline-Formatierung:** `markdown_to_html()` schützt Inline-Code, `$…$`/`$$…$$`
und URLs per Platzhalter, BEVOR Bold/Italic-Regeln laufen. Ohne diesen Schutz
zerstörte `__` in Quellen-URLs die Links (`…gaschromatographie__autor__.pdf`
→ `<strong>`). Markdown-Links `[Text](URL)` werden zu `<a>` (nur
http/https/www//#/mailto). Beim Anpassen dieser Methode: Testharness-Muster
in `docs/VERBESSERUNGSPLAN-5.md` (Abschnitt AP43) verwenden — der Parser lässt
sich mit wenigen Stubs (`add_action`, `__`, `esc_html`, `esc_url`) headless
gegen die echten Dateien in `Inhalte/` ausführen.

## Block-Serializer (Markdown → post_content, seit v3.1.86)

`includes/class-cbd-block-serializer.php`. Gegenstück zum Content-Importer
oben: Der **baut Blöcke im geöffneten Editor** (JavaScript,
`assets/js/content-importer.js`, `insertBlocks()`), der Serializer baut
dieselben Blöcke **serverseitig** für Seiten, bei denen kein Editor offen ist —
Grundlage des Seitenimports.

Nur statische Methoden, kein Singleton:

```php
CBD_Block_Serializer::html_to_blocks($html) : array
CBD_Block_Serializer::to_block_array($sections, $groups, $style_mappings, $optionen = []) : array
CBD_Block_Serializer::to_post_content($sections, $groups, $style_mappings, $optionen = []) : string
```

`$sections` und `$groups` kommen unverändert aus
`CBD_Content_Importer::parse_markdown_content()`. `$optionen` kennt fünf
Schlüssel, alle mit Vorgabewert: `accordion_opt_out`, `page_title`,
`known_slugs` (null = aus der Datenbank), `accordion_available` (null = über
die Registry) und `stable_id_factory` (nur für Tests).

**Die Regeln, die vom JavaScript abweichen — alle mit Grund:**

| Regel | Warum |
|---|---|
| Listen als `core/list` + `core/list-item`, **nie** mit `values` | Das JS nutzt das veraltete Attribut und verlässt sich auf die Migration beim Laden. In der Datenbank muss sofort die migrierte Form stehen, sonst gilt der Block beim ersten Öffnen als ungültig |
| `modular-blocks/accordion` nur bei registriertem Blocktyp | Ein unregistrierter Blocktyp ergibt „Block enthält unerwarteten Inhalt" |
| Unbekannter Design-Slug → „ohne Container" | Ein Container mit unbekanntem Slug rendert im Frontend „Block nicht gefunden" |
| Erste H1-Überschrift entfällt, wenn sie dem Seitentitel gleicht | Sonst stünde der Titel doppelt auf der Seite |
| `stableId` wird selbst vergeben | Fehlt sie, ergänzt der Editor sie beim Öffnen und markiert die Seite sofort als geändert |
| Der Bezeichner wird aus der Spalte **`name`** gelesen | `slug` ist auf Altbeständen nur eine Kopie davon und fehlt frisch angelegten Tabellen ganz (siehe `CBD_Admin::handle_database_repair()`, Zeile 2469) |

**Zeilenumbrüche — der Unterschied, der leicht übersehen wird.** Der
JavaScript-Serializer setzt je einen Umbruch nach dem öffnenden und vor dem
schließenden Kommentar-Trenner und trennt Geschwisterblöcke mit einer
Leerzeile. `serialize_blocks()` in PHP tut beides **nicht** — es verkettet
`innerContent` roh. Der Serializer legt die Umbrüche deshalb selbst in
`innerContent` ab, und `serialisiere()` fügt die oberste Ebene mit `\n\n`
zusammen. Für die Gültigkeit wäre das gleichgültig, für ein zeichengleiches
Ergebnis nicht — sonst erzeugte jedes spätere Speichern einen Unterschied.

**`wp_slash()` beim Schreiben nicht vergessen.** `wp_insert_post()` erwartet
maskierte Daten und ruft intern `wp_unslash()` auf. Wer den Inhalt unmaskiert
übergibt, verliert **jeden Backslash**: `\cdot` wird zu `cdot`, `\sum` zu
`sum`. Jede LaTeX-Formel wäre still zerstört. Der Serializer selbst maskiert
bewusst nicht — das gehört an die Datenbankgrenze, also in den Aufrufer.
Dieselbe Fehlerfamilie wie beim Icon-Wert weiter unten, nur in der anderen
Richtung.

**Das Zielmarkup ist gemessen, nicht abgeleitet.**
`tools/fixtures/referenz-markup.html` stammt aus einer echten
Editor-Speicherung der Produktivseite; `tools/fixtures/referenz-umgebung.md`
hält fest, was daraus abgelesen wurde (unter anderem: Der Container hängt die
generierte Blockklasse **nicht** zusätzlich an, lässt `data-features` bei
leeren Features weg und führt im Attribut-JSON nur Nicht-Standardwerte).

**Nach jedem WordPress- oder Plugin-Update:** Fixture neu erheben, sonst
erzeugt der Serializer stillschweigend ungültige Blöcke. Dafür gibt es
`docs/pruefung-blockmarkup.js` — im Blockeditor einer Seite mit
Container-Blöcken ausführen, es liest `getEditedPostContent()` aus. Danach
`php tools/test-block-serializer.php` wieder grün machen (71 Prüfungen in vier
Gruppen: Fragmentebene, Dokumentebene, Markup-Treue gegen die Fixture,
Delimiter-Bilanz).

## Seitenimport (Markdown → Seiten, seit v3.1.86)

**Seitenmanager → Seiten importieren** (`admin.php?page=cbd-page-import`),
Capability `edit_pages` — also auch für die Rolle Block-Redakteur.
`includes/class-cbd-page-importer.php`, Ansicht in `admin/page-import.php`,
Oberfläche in `assets/js/page-importer.js`.

Ablauf: Mehrere `.md`-Dateien wählen oder ablegen → alle werden geparst und
ihre H2-Gruppen zu **einer** Liste zusammengeführt → **ein** Stil-Dialog für
alle Dateien → je Datei ein Seitenentwurf auf oberster Ebene.

**Seitentitel** ist die erste `# `-Zeile; fehlt sie, der Dateiname ohne
Endung. Entspricht die erste Überschrift dem Seitentitel, entfällt sie im
Inhalt (sonst stünde der Titel doppelt).

**Dubletten** werden vor dem Import aufgelistet und sind abwählbar, aber
**nie überschrieben** — es entsteht ein weiterer Entwurf. Die Prüfung nutzt
`get_posts()`, nicht das seit WordPress 6.2 veraltete `get_page_by_title()`.

### Das Menü hängt am Theme

Der Eintrag wird per `add_submenu_page('page-manager', …)` unter das
Seitenmanager-Menü des Themes gehängt — der Slug `page-manager` ist damit
eine **öffentliche Schnittstelle des Themes**. Wird er dort umbenannt, greift
hier der Rückfall und der Eintrag landet unter „Container Designer".

Registriert wird auf `admin_menu` mit **Priorität 20**. Das ist nicht
kosmetisch: Die Fallunterscheidung
`isset($GLOBALS['admin_page_hooks']['page-manager'])` entscheidet über
Elternmenü oder Rückfall und braucht dafür ein bereits registriertes
Theme-Menü. (`add_submenu_page()` selbst ist unkritisch — es prüft das
Elternmenü nie und gibt `false` nur bei fehlender Capability zurück. Eine
frühere Annahme, es scheitere hier stillschweigend, wurde am 2026-08-10
widerlegt.)

### Der Server parst neu — bewusst

`cbd_import_pages` bekommt den **Markdown-Rohtext**, nicht die im Browser
geparste Struktur, und ruft `parse_markdown_content()` selbst auf. Damit
gelangt kein clientseitig erzeugtes HTML in `post_content`. Das Parsen im
Browser dient nur der Vorschau und dem Stil-Dialog.

### Endpunkte

| Aktion | Nonce | Zweck |
|---|---|---|
| `cbd_parse_import_file` | `cbd_content_import` | **bestehend**, unverändert mitbenutzt — Markdown parsen |
| `cbd_get_style_mappings` | `cbd_content_import` | **bestehend** — Liste der Block-Designs |
| `cbd_check_page_titles` | `cbd_page_import` | neu — welche Titel existieren schon |
| `cbd_import_pages` | `cbd_page_import` | neu — eine Datei importieren |

**Ein Aufruf je Datei**, nicht ein Sammelaufruf: Der Fortschritt bleibt
sichtbar, ein PHP-Timeout bei vielen Dateien ist ausgeschlossen, und ein
Fehler betrifft nur eine Datei.

### Zwei Fallen beim Schreiben

1. **`wp_slash()` beim `wp_insert_post()`.** Ohne die Maskierung entfernt
   WordPress jeden Backslash — `\cdot` wird zu `cdot`, jede LaTeX-Formel wäre
   still zerstört.
2. **`wp_unslash()` vor jedem `json_decode()`.** `$_POST` kommt maskiert an;
   ohne Entfernen scheitert das Dekodieren stillschweigend. Genau dieser
   Fehler hat schon einmal die Icon-Werte zerstört (Abschnitt „Icon-Wert:
   kanonisches Parsen").

Der Markdown-Rohtext selbst wird **nur** durch `wp_unslash()` geführt — kein
`wp_kses_post()`, kein `sanitize_textarea_field()`, beide würden LaTeX
zerstören. Die Entschärfung des erzeugten HTML leistet der Parser über
`strip_unsafe_html()`.

## Eigene Icon-Bibliothek (SVG-Kacheln, seit v3.1.77)

Sechste Bibliothek im Icon-Picker neben Dashicons, Font Awesome, Material,
Lucide und Emoji — mehrfarbige SVG-Kacheln (Verlauf + Plastik-Effekt,
Basisfarbe `#e24614` = Theme-UI-Farbe).

**Der Bestand steht NICHT im Code.** `CBD_Icon_Library` (`includes/class-cbd-icon-library.php`)
scannt zur Laufzeit das Dateisystem. Ein Icon hinzufügen oder ersetzen heißt
also: SVG-Datei ablegen bzw. überschreiben — kein Code-Update, kein
Rebuild.

| Gruppe | Ordner | Verwendung |
|---|---|---|
| `kategorien` | `assets/icons/kategorien/` | Block-Icons (informationen, hinweise, experimente, aufgaben, kontext) |
| `zahlen` | `assets/icons/zahlen/` | Nummerierungs-Feature, 1–100 |
| `ui` | `assets/icons/ui/` | allgemeine Symbole |

**Zwei Quellen, Override gewinnt:**

1. `assets/icons/<gruppe>/<name>.svg` — mit dem Plugin ausgeliefert
2. `wp-content/uploads/cbd-icons/<gruppe>/<name>.svg` — überschreibt gleichnamige
   Plugin-Icons und kann neue ergänzen, **ohne neues Plugin-ZIP**

Weitere Quellen über den Filter `cbd_icon_library_sources`.

### Upload-Seite (Container Designer → Icons)

`CBD_Icon_Manager` (`includes/class-cbd-icon-manager.php`) + View in
`admin/icon-manager.php`. Schreibt **ausschließlich** nach
`uploads/cbd-icons/`; die Plugin-Icons unter `assets/icons/` werden nie
verändert. Löscht man ein hochgeladenes Icon, gilt wieder das Original —
der Button heißt in dem Fall „Original wiederherstellen".

Aktionen laufen über `admin-post.php` (`cbd_icon_upload`, `cbd_icon_delete`,
`cbd_icon_flush`), jeweils mit Nonce und Capability `cbd_admin_blocks`.

**Sicherheitskette pro Upload — nicht abkürzen:**

1. Capability + Nonce
2. Endung muss `.svg` sein, `is_uploaded_file()`
3. `sanitize_icon_name()` normalisiert auf `[a-z0-9_-]` (verhindert
   Verzeichnis-Ausbruch; `../../../wp-config` wird zu `wp-config`)
4. `CBD_SVG_Sanitizer::sanitize()` — **Whitelist**, keine Blacklist
5. Geschrieben wird das Ergebnis des Sanitizers, **nie** die Originaldatei
   (kein `move_uploaded_file`)
6. `.htaccess` im Zielverzeichnis verweigert ausführbare Endungen

Was der Sanitizer entfernt bzw. ablehnt, meldet die Seite dem Nutzer
zurück — ein stillschweigend kaputtes Icon wäre sonst schwer zu erklären.

**Warum überhaupt ein eigener Sanitizer:** SVG ist XML und darf `<script>`,
`on*`-Handler, `xlink:href` auf fremde Dokumente und `<foreignObject>`
enthalten. Beim direkten Aufruf der Datei-URL führt der Browser das aus —
deshalb blockiert WordPress SVG-Uploads standardmäßig. DOCTYPE/ENTITY werden
komplett abgelehnt (XXE), nicht repariert.

Tests: `php tools/test-svg-sanitizer.php` (36 Prüfungen: Angriffsmuster raus,
legitime Kacheln inkl. Verlauf/Filter heil) und
`php tools/test-icon-manager.php` (Dateinamen-Normalisierung, Ausbruchsversuche,
Rundlauf mit `parse_value()`).

**Cache:** Transient (1 Tag), Key enthält `CBD_VERSION`. Im Admin wird der
Cache bewusst umgangen und dabei neu geschrieben — ein Aufruf der
Block-Verwaltung macht neue Icons also auch im Frontend sichtbar. Manuell:
`CBD_Icon_Library::flush_cache()`. Cache-Busting pro Datei über `filemtime()`
als `?ver=`.

**Speicherformat:** wie die anderen Bibliotheken JSON im `features`-Feld —
`{"type":"custom","value":"kategorien/experimente"}`. Der Wert ist
`<gruppe>/<name>`; `parse_value()` lässt nur bekannte Gruppen und
`[a-z0-9_-]`-Namen durch (Schutz vor Verzeichnis-Traversal).

**Immer als `<img>`, nie inline.** Die generierten SVGs verwenden feste
IDs (`bg`, `gloss`, `plastic`, `lift`). Inline eingebunden würden sich
mehrere Kacheln auf einer Seite gegenseitig Verläufe und Filter überschreiben.
Als `<img>` ist jede Kachel ein eigenes Dokument. Konsequenz: `currentColor`
funktioniert nicht — die Farbe steckt in der Datei.

### Nummerierung als Kachel

Das `numbering`-Feature rendert Zahlenkacheln statt der schwarzen Textblase.
Die eigentliche Nummer setzt weiterhin `assets/js/block-numbering.js` im
Browser (WordPress rendert Blöcke nicht in Dokumentreihenfolge); PHP liefert
nur ein `<img>` ohne `src` plus die Daten in `window.cbdNumberIcons`.

Zwei Rückfallebenen, beide automatisch:
- Format `roman`/`alphabetic` → Textblase wie bisher (dafür gibt es keine Kacheln)
- mehr Blöcke als Kacheln → JS setzt `.cbd-number-fallback`, CSS reaktiviert die Textblase

Mehr Kacheln erzeugen: `python generate_iconset_local.py --max-number 200 --out …`
(Generator liegt in `Website/Icons/`).

**CSS-Fallstrick:** Die Ursprungsregeln für `.cbd-outside-number` in
`cbd-frontend-clean.css` arbeiten mit `!important` (u. a. `z-index: -1`).
`assets/css/custom-icons.css` neutralisiert sie deshalb ebenfalls mit
`!important` und ist per Handle von `cbd-frontend-clean` abhängig — die
Reihenfolge ist zwingend.

### Icons neu erzeugen

Generator: `Website/Icons/generate_iconset_local.py` (Python, braucht
`svgelements` + `pillow`, kein Cairo).

```bash
cd Website/Icons
python generate_iconset_local.py --out "../Plugins/CDB-Designer/assets/icons"
python generate_iconset_local.py --color "#71230a" --out …   # andere Basisfarbe
```

Prüfen: `php tools/test-icon-library.php` (Standalone-Harness ohne WordPress,
testet Bestand, Sortierung, URL-Bildung, Traversal-Schutz, Admin-Vorschau).

## Icon-Größen: wo sie stehen (Fundstellen-Karte)

Die Größen liegen verstreut, und **daneben liegen tote CSS-Dateien mit
denselben Selektoren**. Wer dort ändert, sieht nichts passieren. Diese Karte
existiert, damit das nicht nochmal jemandem passiert.

### Der eine Regler

Alle Frontend-Icongrößen sind `calc(<Basiswert> * var(--cbd-icon-scale))`.
Die Basiswerte bleiben stehen, damit erkennbar bleibt, wovon skaliert wird.

**Eingestellt wird der Faktor seit v3.1.80 in der Oberfläche:**
Container Designer → Einstellungen → **Icon-Größe**, Prozentwert,
50–200 %, Standard 110 %.

Kette und Zuständigkeiten:

| Stelle | Aufgabe |
|---|---|
| `includes/functions.php` | `cbd_icon_scale_bounds()` (Grenzen + Standard), `cbd_sanitize_icon_scale()`, `cbd_get_icon_scale_percent()`, `cbd_get_icon_scale_css()`, `cbd_icon_scale_preview()` |
| `admin/settings.php` | Feld `icon_scale`, Speichern über `cbd_sanitize_icon_scale()` |
| `class-cbd-block-registration.php` (nach dem `cbd-frontend-clean`-Enqueue) | `wp_add_inline_style(':root{--cbd-icon-scale:…}')` |
| `assets/css/cbd-frontend-clean.css` (`:root`) | nur noch **Rückfallwert** (`1.1`) |

**PHP ist die einzige Quelle.** Das Inline-CSS wird immer ausgegeben, auch
beim Standardwert. Andernfalls müssten der Standard in
`cbd_icon_scale_bounds()` und die `1.1` in der CSS-Datei dauerhaft
übereinstimmen — eine Änderung an nur einer der beiden Stellen liefe still
daneben. Der Wert in der CSS-Datei greift nur, falls der Enqueue-Zweig nicht
läuft.

Inline (nicht über den Style-Loader-Transient), damit die neue Größe sofort
nach dem Speichern wirkt, ohne Cache-Leerung.

**Die Handy-Ansicht bleibt angepasst.** Jeder Breakpoint hat seinen eigenen
Basiswert (32px Desktop / 28px Tablet / 24px Handy), und alle hängen an
derselben Variablen. Skaliert wird die ganze Treppe, nicht nur der
Desktopwert — Handy < Tablet < Desktop gilt auf jeder Stufe. Deshalb ist das
Maximum 200 %: darüber verdrängt das Icon auf dem Handy den nur 16px großen
Titel. `cbd_icon_scale_preview()` spiegelt die drei Basiswerte, um in den
Einstellungen die errechneten Pixelgrößen anzuzeigen — **ändern sich die
Basiswerte im CSS, muss diese Funktion mitgezogen werden.**

`custom-icons.css` nutzt `var(--cbd-icon-scale, 1)` mit Fallback: im Frontend
hängt die Datei per Handle an `cbd-frontend-clean` (Variable ist da), im Admin
wird sie für den Icon-Picker allein geladen — dort greift der Fallback 1.

Test: `php tools/test-icon-scale.php` (Bereinigung inkl. deutschem Komma und
Unsinnseingaben, Kappung, gültiges CSS über den ganzen Wertebereich, kaputte
Option in der DB, und die Zusicherung Handy < Tablet < Desktop auf jeder
Stufe).

### Lebende Dateien (Frontend)

| Was | Datei | Selektor | Basis |
|---|---|---|---|
| Kopfzeilen-Icon, Rahmen | `cbd-frontend-clean.css` | `.cbd-header-icon` | 32px (≤768px: 28px) |
| … Dashicons darin | `cbd-frontend-clean.css` | `.cbd-header-icon .dashicons` | 24px (≤768px: 20px) |
| … Material/FA/Lucide/Emoji darin | `cbd-frontend-clean.css` | `.cbd-header-icon .material-icons` u. a. | siehe unten |
| Eigene SVG-Kacheln | `custom-icons.css` | `.cbd-header-icon .cbd-custom-icon` | 32/28/24px |
| Nummerierungs-Kacheln | `custom-icons.css` | `.cbd-container-number.cbd-number-as-icon` | 34px (≤480px: 28px) |
| … deren Überstand | `custom-icons.css` | `.cbd-outside-number.cbd-number-as-icon` | top/left −14px (≤480px: −10px) |
| Positionierte Icons | `cbd-frontend-clean.css` | `.cbd-icon`, `.cbd-icon-inside` | 24px, Dashicons 18px |

### Tote Dateien — nicht anfassen

`assets/css/frontend-positioning.css`, `assets/css/unified-frontend.css` und
`assets/css/frontend.css` sind in **keinem** `wp_enqueue_style()` referenziert
(geprüft über alle `*.php`). Sie enthalten trotzdem `.cbd-header-icon
.dashicons { font-size: 18px }` (unified-frontend.css:157) und
`.cbd-container-icon.cbd-positioned { width: 32px }`
(frontend-positioning.css:28) — reine Attrappen.

### Jede Bibliothek wird anders groß

`CBD_Block_Registration::render_icon()` (Zeile ~2005) erzeugt je nach Typ
anderes Markup, und die Größe kommt entsprechend woanders her:

| Typ | Markup | Größe kam ursprünglich von |
|---|---|---|
| `dashicons` | `<span class="dashicons …">` | CDB-Regel (24px) |
| `custom` | `<img class="cbd-custom-icon">` | CDB-Regel (32px); die `width/height="24"`-Attribute im HTML sind nur Layout-Shift-Hinweis, CSS gewinnt |
| `material` | `<span class="material-icons">` | **Vendor-CSS** `material-icons.css:19` (24px) |
| `fontawesome` | `<i class="fa-solid fa-…">` | **geerbte** `font-size` — keine CDB-Regel |
| `lucide` | `<i class="lucide lucide-…">` | `lucide.css:13` = `font-size: inherit` |
| `emoji` | `<span class="cbd-emoji-icon" style="…">` | **Inline-Style** in `render_icon()` (`1.2em`) |

Konsequenz: Eine Skalierung, die nur die CDB-Regeln anfasst, vergrößert
Dashicons und Kacheln — Font Awesome, Lucide, Material und Emoji blieben
gleich. Deshalb gibt es in `cbd-frontend-clean.css` jetzt für jede Bibliothek
eine eigene Regel im Kopfzeilen-Kontext. Die erbenden Bibliotheken (FA,
Lucide) werden **relativ** skaliert (`calc(1em * var(--cbd-icon-scale))`),
nicht auf einen festen px-Wert gesetzt — sonst wäre es keine
10-%-Vergrößerung, sondern eine Neufestlegung.

Emoji ist der Sonderfall: Inline-Styles schlagen externes CSS. Der `calc()`
steht deshalb in `render_icon()` selbst, mit Fallback `var(--cbd-icon-scale, 1)`.

### Nicht mitskaliert

- `.cbd-header-icon { transform: translateY(-6px) }` — manuelle
  Grundlinien-Ausrichtung zum Titel, kein Größenwert. Bei stark abweichenden
  Prozentwerten kann die Ausrichtung zum Titel leicht verrutschen.
- Icon-Picker im Admin (`.cbd-icon-item .cbd-custom-icon-preview`, 32px) und
  Board-Modus-Werkzeugleiste (`board-mode.css`, 16–22px): Admin-UI.
- Gutenberg-Editor: `block-editor.css` ist eine eigene Datei; die
  Frontend-Skalierung erreicht ihn nicht.

## Plastischer Look von PDF-Button und PDF-Werkzeugleiste (v3.1.80)

Beide übernehmen die Optik der Icon-Kacheln (Verlauf 135°, radialer Glanz oben
links, Innenkante oben dunkel / unten hell, Schlagschatten). **Die Rezeptur und
die vollständige Fundstellen-Liste stehen in `Theme/CLAUDE.md`, Abschnitt
„Plastischer Look"** — dort liegt auch die Kopfleiste, die denselben Look hat.

Besonderheit hier: `assets/js/floating-pdf-button.js` stylt **inline per
jQuery**, nicht über eine CSS-Datei. Die Farbstufen werden als
`color-mix()`-Strings gebaut (`plasticDark`, `plasticShadow`,
`plasticBackground()`), damit die Customizer-Farbe durchschlägt.

**Falle beim Hover:** Der frühere Hover setzte `background` auf eine einzelne
Farbe. Bei einem Verlaufshintergrund löscht das die Verlaufsschichten und der
Knopf wird beim Überfahren flach — deshalb tauscht der Hover jetzt den ganzen
Verlauf aus statt nur die Farbe.

Die weiße `.cbd-pdf-go`-Schaltfläche in der Werkzeugleiste bleibt bewusst flach:
sie ist ein Umkehr-Knopf auf farbigem Grund und würde mit dem orangen
Kachel-Look in der Leiste verschwinden.

## Icon-Wert: kanonisches Parsen (Fix v3.1.78)

`CBD_Icon_Library::parse_stored_value()` ist die **einzige** Stelle, die das
Speicherformat des Icon-Werts interpretiert. `CBD_Block_Registration::parse_icon_value()`
und die Admin-Vorschau delegieren dorthin.

**Der Bug, der dahintersteckt:** `cbd_parse_features_from_post()` speicherte den
Wert mit `sanitize_text_field()` — aber ohne `wp_unslash()`. WordPress versieht
`$_POST` mit Slashes, in der DB landete also `{\"type\":\"custom\",…}`.
`json_decode` scheiterte, der Code fiel auf den Legacy-Zweig zurück und baute
daraus einen Dashicon-Klassennamen: `<span class="dashicons dashicons-{\"type\"…">`
— ein leeres Span. Betroffen war **jede** Bibliothek außer Dashicons (die haben
keine Anführungszeichen), also Font Awesome, Material, Lucide und die eigenen
Kacheln.

Zwei Konsequenzen, beide behoben:

1. Speichern läuft jetzt über `cbd_sanitize_icon_value()` — `wp_unslash()`,
   Typ-Whitelist, kanonisches Neukodieren
2. `parse_stored_value()` repariert **Altbestände** beim Lesen: schlägt
   `json_decode` fehl, folgt ein zweiter Versuch mit `stripslashes()`. Bereits
   gespeicherte Designs rendern dadurch wieder korrekt, ohne neu gespeichert
   werden zu müssen.

Nebenwirkung des Bugs war außerdem, dass `get_required_icon_libraries()` den Typ
`custom` nie sah und `custom-icons.css` deshalb nicht eingebunden wurde.

Test: `php tools/test-icon-value.php` (Rundlauf Picker → `$_POST` mit Slashes →
Speichern → Lesen → Rendern, plus Reparatur von Altbeständen).

## Designs exportieren / importieren (v3.1.78, Markdown seit v3.1.79)

Container Designer → **Export / Import**. `CBD_Design_Transfer`
(`includes/class-cbd-design-transfer.php`) + View in `admin/design-transfer.php`.

Ersetzt die in v3.1.50 abgeschaltete Seite `admin/import-export.php` (die
Datei liegt noch im Repo, ist aber nirgends verlinkt). Bewusst **nur ein**
Eingabeweg: eine hochgeladene Datei. Kein URL-Abruf (SSRF), kein ZIP —
genau die Gründe, warum die alte Seite entfernt wurde.

**Zweistufig:** Datei prüfen → Vorschau mit Konfliktliste → Import ausführen.
Ein Import kann bestehende Designs überschreiben, deshalb wird vorher gezeigt,
was passiert.

**Konfliktbehandlung** bei gleichem Slug: überspringen (Default), überschreiben
oder als Kopie mit neuem Slug anlegen.

Exportiert werden in beiden Formaten `name, title, description, config, styles,
features, status, is_default` — **ohne** `id` und Zeitstempel, damit nichts
kollidiert.

### Markdown (Standard)

Ein `##`-Abschnitt je Design, Werte als flache Punkt-Pfade:

```markdown
## Info-Box

- **Slug:** `info-box`
- **Status:** aktiv
- **Standard:** nein

Hinweiskasten für Merksätze.

### Stile

- `background.color`: #f5ede9
- `border.width`: 2

### Funktionen

- `icon.enabled`: true
- `icon.value`: {"type":"custom","value":"kategorien/hinweise"}
```

Regeln (`to_markdown()` / `parse_markdown()`):

- `###`-Überschriften werden über Schlüsselwörter zugeordnet
  (`konfig|config` → config, `stil|style` → styles, `funktion|feature` →
  features); unbekannte Abschnitte werden übergangen.
- **Stammdaten erkennt der Parser an der Fettschrift** (`- **Slug:** …`).
  Alles andere im Kopfbereich ist Beschreibung — dadurch überlebt eine
  Beschreibung, die selbst mit `- ` beginnt. Führende `#` in der Beschreibung
  werden beim Schreiben als `\#` maskiert, sonst zerschnitte die Zeile beim
  Wiedereinlesen den Abschnitt.
- **Der Wert ist der ganze Zeilenrest**, auch mit Doppelpunkten — sonst
  überlebte `icon.value: {"type":"custom",…}` den Rundlauf nicht.
- **Schreiben und Lesen können nicht auseinanderlaufen:** `md_write_value()`
  schreibt roh und setzt genau dann Anführungszeichen, wenn
  `md_read_value()` daraus nicht exakt denselben Text machen würde. Die Regel
  steht also nur einmal im Code.
- Typen: `true`/`false` (auch `ja`/`nein`) → bool, reine Zahlen → int/float,
  Rest → Text; `"true"` erzwingt Text. **Wichtig:** bool muss echt bool
  bleiben — der String `"false"` wäre in PHP truthy und würde ein
  abgeschaltetes Feature einschalten.
- **Bewusste Typänderung:** Zahlen, die in der DB als String liegen (aus
  `$_POST` kommt alles als String), stehen unquotiert in der Datei und kommen
  als echte Zahl zurück. `padding.top: "20"` wäre in einer lesbaren Datei nur
  Lärm; für die CSS-Erzeugung sind `"20"` und `20` dasselbe. Der Rückweg
  prüft trotzdem auf Exaktheit (`(string) $zahl === $text`), damit `"0.10"`
  Text bleibt; `filter_var` statt `(int)`, weil der Cast einer zu großen
  Ziffernfolge ab PHP 8.1 warnt.
- Schlüsselsegmente dürfen nur `[A-Za-z0-9_-]` enthalten. Zeilen, die das
  verletzen, werden **verworfen statt bereinigt** — sonst stünde unter einem
  Namen ein Wert, den niemand geschrieben hat. `unflatten()` bereinigt
  zusätzlich je Segment (Traversal-Schutz).

### JSON (weiterhin unterstützt)

`{"format":"cbd-designs","formatVersion":1,"designs":[…]}`, unverändert seit
3.1.78. `config`/`styles`/`features` liegen als echte Objekte statt als
JSON-in-JSON in der Datei.

**Die Formatweiche stellt der Inhalt, nicht die Endung** (`parse_file()`):
beginnt die Datei mit `{`, wird sie als JSON gelesen, sonst als Markdown.
Beide Wege münden in dieselbe Normalisierung (`normalize_designs()`) — die
Eingangsprüfung ist für beide Formate identisch, es gibt keinen zweiten,
schwächeren Weg in die Datenbank.

### Sonstiges

**Der Slug entscheidet.** Seiten referenzieren ihr Design über `name`. Import
unter abweichendem Slug lässt bestehende Container ungestylt — die Seite weist
darauf hin.

**`is_default` wird exportiert, aber nie geschrieben.** Pro Installation darf
es nur ein Standard-Design geben (`CBD_Ajax_Handler` setzt beim Wechsel alle
anderen auf 0); welches das ist, entscheidet die Zielinstallation. Die Angabe
in der Datei ist reine Information.

Nach dem Import werden Style- und Blocklisten-Cache geleert
(`CBD_Block_Registration::clear_blocks_cache()`), sonst greifen neue Designs
erst nach Ablauf der Transients.

Test: `php tools/test-design-transfer.php` (beide Formate: Ablehnung
ungültiger Dateien, Slug-Normalisierung inkl. Traversal, doppelte Slugs,
Markup-Entschärfung, Verwerfen unbekannter Felder, Rundläufe Export→Import
und JSON→Markdown, heikle Werte, Zahlentypen).

## Debugging-Konventionen

- **PHP:** Informations-Logs laufen über klasseneigene `debug_log()`-Helper
  bzw. `if (defined('WP_DEBUG') && WP_DEBUG)`-Gates. Echte Fehlerfälle
  (Failed/Error) loggen bewusst ungegatet via `error_log()`.
- **JavaScript (Frontend + Admin):** Alle `console.log`-Ausgaben sind hinter
  `window.cbdDebug` gegated. Zum Aktivieren in der Browser-Konsole:
  `window.cbdDebug = true` (dann Aktion wiederholen). `console.error/warn`
  bleiben immer aktiv.
- **Render-Schutz:** `CBD_Block_Registration::render_block()` fängt Throwables
  pro Block — ein kaputter Block wird mit HTML-Kommentar übersprungen statt
  die Seite mit HTTP 500 abzubrechen; die Fundstelle steht im PHP-Error-Log.

## Important Files

### Core PHP Files
- `container-block-designer.php` - Main plugin file, bootstrap
- `includes/class-service-container.php` - Dependency injection container
- `includes/class-cbd-block-registration.php` - Block registration logic (lines 51-80 for registration flow, 545-883 for rendering)
- `includes/class-cbd-style-loader.php` - Dynamic CSS generation
- `includes/class-cbd-admin.php` - Admin interface controller
- `includes/class-cbd-ajax-handler.php` - AJAX endpoint handler
- `includes/Database/class-schema-manager.php` - Database schema management
- `includes/class-latex-parser.php` - LaTeX math formula parsing (lines 114-170 for parsing logic)

### JavaScript Files
- `assets/js/block-editor.js` - Gutenberg block editor integration
- `assets/js/interactivity-store.js` - WordPress Interactivity API integration (modern)
- `assets/js/interactivity-fallback.js` - jQuery-based fallback for interactive features
- `assets/js/floating-pdf-button.js` - Floating PDF export button
- `assets/js/latex-renderer.js` - LaTeX rendering with KaTeX
- `assets/js/jspdf-loader.js` - PDF export functionality loader

### Admin Templates
- `admin/blocks-list.php` - Block management list view
- `admin/new-block.php` - Block creation form
- `admin/edit-block.php` - Block editing interface
- `admin/settings.php` - Plugin settings page
- `admin/import-export.php` - Import/export functionality

## Known Issues & Technical Debt

1. **Double Rendering Prevention:** Frontend renderer (`CBD_Consolidated_Frontend`) disabled to prevent conflicts with block registration system (lines 114-118, 244-247 in main plugin file)

2. **Block Isolation:** Nested container blocks are isolated to prevent interference (v2.7.7 fix)

3. **iOS Screenshot Compatibility:** Special handling for iOS devices documented in `IOS-SCREENSHOT-STRATEGY.md`

4. **PHP 8 Compatibility:** Compatibility layer in `includes/php8-wordpress-compatibility.php`

5. **LaTeX Parser Integration:** LaTeX formulas parsed in `CBD_Block_Registration::render_block()` at line 850-853 via `CBD_LaTeX_Parser::parse_latex()`

## Database Migrations

Managed by `CBD_Schema_Manager`:
- Current DB version: 3.1.61 (Konstante `CBD_Schema_Manager::DB_VERSION`)
- Migration history stored in `cbd_db_version` option
- Migrations run automatically on plugin activation/update
- Manual migration: `CBD_Schema_Manager::run_migrations()`

## Security Considerations

- Nonces required for all AJAX requests
- Capability checks via custom capabilities (cbd_edit_blocks, cbd_admin_blocks)
- SQL queries use `$wpdb->prepare()` for prepared statements
- Direct file access prevented via `ABSPATH` checks
- Upload directory protected with `.htaccess`

## Testing

- Test files should be in `tests/` directory (PSR-4: `ContainerBlockDesigner\Tests\`)
- Run tests via `composer test` (PHPUnit)
- Manual testing workflow:
  1. Create test block in admin
  2. Add block to page in editor
  3. Preview/publish page
  4. Test frontend features (collapse, copy, screenshot, numbering)
  5. Verify styles applied correctly

## Git Workflow

Current branch: `main` (also the main branch for PRs)

Recent fixes:
- v2.7.6: Numbering shows only top-level blocks
- v2.7.7: Fixed nested container block isolation
- v2.7.7: Repaired copy text and screenshot functions

## Additional Documentation

- **`docs/VERBESSERUNGSPLAN.md` bis `-4.md`** — Review-Runden 2026-07 über das
  gesamte Website-Projekt (CDB, Modular-Plugin, Theme): 42 Arbeitspakete mit
  Problem, Fundstelle, Lösung, Verifikation und Erledigt-Status; inkl.
  dokumentierter Entscheidungen (Buttons folgen Feature-Flags, PHP 7.4 bleibt,
  keine CDN-Einbindungen).
- **`docs/AENDERUNGEN-UND-UPLOAD.md`** — verständliche Gesamtübersicht der
  Änderungen + Upload-/Recovery-Anleitung.
- **`docs/archiv/`** — historische Status-/Implementierungsnotizen früherer
  Entwicklungsphasen (nicht mehr gepflegt, teils veraltet — siehe README dort).
