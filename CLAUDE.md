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

**Bekannter Befund: `wp_kses_post()` zerstört LaTeX im Blocktitel (gefunden
2026-08-21, bei der Abnahme eines anderen Vorhabens).** Die Rolle hat kein
`unfiltered_html` — die Fähigkeit steht nicht in
`cbd_block_redakteur_capabilities()` (`includes/functions.php:318-349`).
WordPress schickt `post_content` beim Speichern für jede Rolle ohne diese
Fähigkeit durch `wp_filter_post_kses()`. **Gemessen** (Testserver, WordPress
7.0.4, direkter Aufruf von `wp_kses_post()` auf Blockmarkup):

| Eingabe im **Blocktitel** | Nach kses |
|---|---|
| `\frac{a}{b}` | `rac{a}{b}` |
| `\beta` | `eta` |
| `\cdot`, `\sum_{i=1}^{n}`, `\alpha` | Titel unlesbar zerstört |
| `\nabla`, `\tau`, `\rho` | unverändert |

**Dieselben Ausdrücke im Block-INHALT überleben unverändert** — betroffen
ist nur, was im HTML-Kommentar des Block-Trenners steht (Blockattribute,
darunter `blockTitle`). Der Effekt trifft jede Rolle ohne `unfiltered_html`,
nicht nur Block-Redakteur, und existiert unabhängig vom Seitenimporter, seit
es Container-Blöcke mit Titeln gibt — der Seitenimporter umgeht kses bewusst
nicht (siehe Kommentar bei `class-cbd-page-importer.php:248`), ist aber auch
nicht die Ursache. **Noch nicht gemessen:** ein Ende-zu-Ende-Nachweis mit
einem echten Block-Redakteur-Konto steht aus (auf dem Testserver existiert
keines). Folge für `docs/archiv/PLAN-Formeln-in-Blocktiteln.md`: Ein dort geplantes
Rendern von Formeln in Blocktiteln liefe für Block-Redakteure ins Leere,
solange der Titel beim Speichern bereits zerstört wird. Details:
`docs/archiv/PLAN-Importer-Elternseite.md`, Abschnitt 10.

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
alle Dateien → je Datei ein Seitenentwurf, alle mit derselben wählbaren
Elternseite (siehe unten; Vorgabe weiterhin oberste Ebene).

**Seitentitel** ist die erste `# `-Zeile; fehlt sie, der Dateiname ohne
Endung. Entspricht die erste Überschrift dem Seitentitel, entfällt sie im
Inhalt (sonst stünde der Titel doppelt).

**Dubletten** werden vor dem Import aufgelistet und sind abwählbar, aber
**nie überschrieben** — es entsteht ein weiterer Entwurf. Die Prüfung nutzt
`get_posts()`, nicht das seit WordPress 6.2 veraltete `get_page_by_title()`.

### Elternseite gilt für den ganzen Lauf (seit dem Vorhaben „Importer-Elternseite", 2026-08-21)

Im Dialog legt ein Feld (`admin/page-import.php`, Name/ID
`cbd-import-parent`) die Elternseite für **alle** Seiten eines Laufs fest —
nicht je Datei; eine Elternseite je Datei ist ausdrücklich ein Nicht-Ziel
des zugehörigen Plans, keine offene Frage. `assets/js/page-importer.js`
liest den Wert **einmal vor Beginn** des Laufs (`importStarten()`) und
schickt ihn bei **jedem** der `cbd_import_pages`-Aufrufe je Datei als
`parent_id` mit; während des Laufs ist das Feld `disabled`, danach wieder
frei. Würde der Wert je Datei neu gelesen, könnte eine Bedienung mitten im
Lauf die Zuordnung für die zweite Hälfte der Dateien ändern.

**Kaskadierende Auswahl statt Einzeldropdown (seit dem Vorhaben
„Seitenimporter-Kaskaden-Zielauswahl", 2026-08-25).** Das hier bis dahin
eingesetzte `wp_dropdown_pages()` ist ersetzt durch eine gestaffelte,
mit der echten Seitenhierarchie wachsende Reihe von Auswahlfeldern:
`admin/page-import.php` rendert nur noch ein verstecktes
`<input type="hidden" id="cbd-import-parent" name="cbd-import-parent">`
(unverändert dieselbe ID/dasselbe Name-Attribut — der gesamte nachgelagerte
Code, insbesondere `importStarten()` und `bereinige_elternseite()` unten,
liest weiterhin nur dieses eine Feld und bleibt unverändert) plus einen
leeren Container `#cbd-pi-kaskade`. `assets/js/page-importer.js` baut darin
beim Laden über `window.wp.apiFetch({ path: '/cbd/v1/seitenbaum?entwuerfe=1' })`
den erweiterten Seitenbaum auf (Route und Parameter siehe Abschnitt
„Blockreferenz als Textformat und hierarchische Zielauswahl" weiter unten,
Unterabschnitt „Vertrag B in der Praxis") — der opt-in Parameter
`entwuerfe=1` schließt Entwürfe ein und übernimmt damit denselben Zweck,
den vorher `wp_dropdown_pages(['post_status' => ['publish', 'draft']])`
hatte: ein frisch importiertes, nur als Entwurf angelegtes Kapitel muss bei
einem Folgeimport als Elternseite wählbar sein. Aus der Antwort entsteht je
Hierarchieebene ein `<select class="cbd-pi-kaskade-ebene">`; die nächste
Ebene erscheint erst, nachdem in der vorherigen eine Seite mit Unterseiten
gewählt wurde. Jede Auswahl setzt das versteckte Feld synchron auf die ID
der tiefsten gewählten Seite (oder `0`). Schlägt der Aufruf fehl, bleibt das
Feld auf `0` und der Dialog bleibt benutzbar — dieselbe Fallback-Philosophie
wie bei `bereinige_elternseite()` unten.

**Bereits gefundene und behobene Falle in dieser Logik (Review-Befund B1,
behoben in AP-1.fix1):** Die erste Option einer Zwischenebene, „— diese
Seite als Elternseite —", trägt als Wert die ID der bereits gewählten
Elternseite, nicht `0`. `kaskadeAuswahlGeaendert()` brach ursprünglich nur
bei `gewaehlteId === 0` ab und hängte beim Rücksprung auf genau diese
Option eine zweite, wortgleiche Ebene mit denselben Kindern an, statt die
Auswahl unverändert zu lassen — ein Verstoß gegen „Erneute Wahl in einer
höheren Ebene entfernt alle tieferen Ebenen" dem Sinn nach (das versteckte
Feld blieb dabei in jedem geprüften Ablauf korrekt gesetzt, es entstand
also keine falsche `post_parent`, nur eine überflüssige Doppel-Ebene). Der
Fix vergleicht zusätzlich gegen den Wert der eigenen ersten Option der
Ebene (`ausgewaehltesFeld.options[0].value`) — für die erste Ebene
weiterhin `0`, für tiefere Ebenen die schon gewählte Eltern-ID. Wer
`kaskadeAuswahlGeaendert()` künftig ändert, sollte diesen Vergleich
mitnehmen, statt nur auf `0` zu prüfen — sonst kehrt dieselbe Doppel-Ebene
zurück. Regressionsschutz: `tools/test-page-importer-kaskade.js`.

Serverseitig bereinigt die private Methode
`CBD_Page_Importer::bereinige_elternseite($roh): int`
(`includes/class-cbd-page-importer.php`) den Wert aus `$_POST['parent_id']`:
`wp_unslash()`, dann `filter_var($roh, FILTER_VALIDATE_INT)` — bewusst kein
`(int)`-Cast, der eine überlange Ziffernfolge ab PHP 8.1 mit einer Warnung
auf `PHP_INT_MAX` abbilden würde statt sie abzulehnen (dieselbe Regel mit
derselben Begründung wie in `class-cbd-design-transfer.php`,
`md_read_value()`). Das Ergebnis geht unverändert als `post_parent` an
`wp_insert_post()`.

**Jeder ungültige Wert fällt still auf `0` zurück** (oberste Ebene) — ohne
Fehlermeldung und ohne den Lauf abzubrechen: ein fehlender, leerer,
nicht-numerischer, negativer oder `0`-Wert ebenso wie die ID eines
Beitrags, einer gelöschten Seite oder einer nicht existierenden Seite.
**Begründung:** Der Import feuert einen AJAX-Aufruf je Datei; ein Abbruch
mitten im Lauf ließe die Hälfte der Seiten angelegt und die andere nicht —
ein schlechterer Zustand als eine Seite auf oberster Ebene, die sich im
Seitenmanager jederzeit nachträglich verschieben lässt.

Tests: `tools/test-page-importer.php`, 34 Prüfungen ohne WordPress.

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
| Positionierte Icons (alt, **wirkungslos**) | `cbd-frontend-clean.css` | `.cbd-icon`, `.cbd-icon-inside` | 24px, Dashicons 18px — Erzeuger `render_features()` ist seit Langem auskommentiert, matcht nichts |
| Icon-Position, Container-Ecke (seit 3.1.89) | `cbd-frontend-clean.css` | `.cbd-header-icon.cbd-icon-positioned`, `.cbd-icon-at-top-left` u. a. | Grundabstand 10px je Ecke; Feinversatz über `--cbd-icon-dx`/`--cbd-icon-dy` — Details im Abschnitt „Icon-Position: Kopfzeile oder Container-Ecke" |

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

- `.cbd-header-icon`s Grundlinien-Ausrichtung zum Titel — kein Größenwert.
  Bis 3.1.88 ein festes `transform: translateY(-6px)`; seit 3.1.89 rechnet
  `transform: translate(var(--cbd-icon-dx, 0px), calc(-6px + var(--cbd-icon-dy, 0px)))`
  zusätzlich den Feinversatz der Icon-Position ein (Abschnitt „Icon-Position:
  Kopfzeile oder Container-Ecke"). Bei stark abweichenden Prozentwerten kann
  die Ausrichtung zum Titel leicht verrutschen.
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

## Klassen-Durchlass für gesperrte Seiten (seit 3.1.87)

Das Theme kann Seiten sperren („nur für Lehrpersonen", Meta
`_simple_clean_nur_lehrpersonen`; siehe `Theme/CLAUDE.md`). Für nicht
angemeldete Besucher verschwinden sie überall und liefern beim Aufruf eine
Hinweisseite mit HTTP 403.

`includes/class-cbd-classroom-gate.php` öffnet diese Sperre in **einem** Fall:
gültige Klassensitzung **und** die Seite enthält Container, die für diese
Klasse als „behandelt" markiert sind. Gedacht für Lösungsseiten, deren Blöcke
die Lehrperson im Unterricht nach und nach freigibt.

### Die Naht zum Theme

```php
apply_filters('simple_clean_lehrerseite_freigeben', false, $post_id)
```

**Standardwert `false`.** `CBD_Classroom_Gate::seite_freigeben()` ist die
einzige Stelle, die ihn öffnet. Fehlt das Plugin, ist es abgeschaltet oder
greift der Filter nicht, bleibt die Seite gesperrt — ein Fehler in der Naht
zeigt zu wenig, nie zu viel.

Umgekehrt: Fehlt das Theme, gibt es keine Sperre. Alle Zugriffe auf
Theme-Funktionen laufen deshalb über `function_exists()`.

Das ist nach dem Menü-Slug `page-manager` die **zweite** Stelle, an der Theme
und Plugin zusammenwirken — anders als dort hängt hier Vertraulichkeit daran.

### Die Sitzung: der Transient entscheidet, nicht die URL

`CBD_Classroom_Gate::sitzung()` liest `?classroom=` und `?token=` und lädt den
Transient `cbd_classroom_<token>`. **Stimmt die `class_id` im Transient nicht
mit `?classroom=` überein, gilt die Sitzung als ungültig.** Sonst ließe sich
mit einem gültigen Token einer beliebigen Klasse die Freigabe einer anderen
erschleichen.

### Serverseitige Reduktion — der enge Geltungsbereich

`inhalt_reduzieren()` hängt an `the_content` mit **Priorität 8** (`do_blocks`
liegt auf 9, der Inhalt ist dort noch Blockmarkup). Erlaubte Blöcke gehen
einzeln durch `render_block()`; **bewusst kein `serialize_blocks()`**, damit
der Whitespace-Unterschied zwischen JavaScript- und PHP-Serializer (Abschnitt
„Block-Serializer") keine Rolle spielt.

**Alle vier Bedingungen müssen erfüllt sein:**

1. Ausgabe des Hauptinhalts einer einzelnen Seite (`is_singular('page')`,
   `in_the_loop()`, `is_main_query()`)
2. Besucher ist **nicht** angemeldet
3. die Seite ist gesperrt
4. es liegt eine gültige Klassensitzung vor

Fehlt eine davon, geht der Inhalt **unverändert** durch. **Eine Aufweichung
hier wäre der schwerste denkbare Fehler dieser Erweiterung** — sie ließe
Inhalte auf ganz normalen Seiten im laufenden Betrieb verschwinden. Auf nicht
gesperrten Seiten bleibt der Klassenmodus deshalb wie bisher rein
clientseitig; die Lehrperson behält dort ihre Vorschau.

### `block_erlaubt()`: Standard ist Ablehnung

Was kein Container-Block mit freigegebener `stableId` ist, entfällt — auch
freistehende Absätze und Überschriften. Auf einer Lösungsseite ist alles
Lösung, solange nichts anderes gesagt wurde.

**Der Rückfall auf `data-stable-id` im gespeicherten HTML ist Pflicht.**
Ältere Container tragen die Kennung nur dort, nicht in den Attributen —
dasselbe löst `CBD_Block_Registration::render_block()` (ab Zeile ~899). Ohne
den Rückfall verschwänden korrekt markierte Altbestände stillschweigend.

### Geteilte Helfer — nicht duplizieren

In `CBD_Classroom`:

| Methode | Zweck |
|---|---|
| `zerlege_container_id()` | **einzige** Deutung des Formats `<stableId>:pN` (mehrseitige Tafelbilder) |
| `basis_container_id()` | nur der Teil vor dem Suffix |
| `behandelte_container($class_id, $page_id)` | Basis-Bezeichner aller behandelten Container, jeder genau einmal |

`tools/test-classroom-gate.php` enthält eine Prüfung, die anschlägt, sobald die
Suffix-Regel ein zweites Mal im Code auftaucht. Beim Bauen hat sie genau das
gemeldet — der erste Anlauf ließ für den Seitenindex einen zweiten regulären
Ausdruck stehen.

### Der Browser-Filter kennt den Zustand

`cbdClassroomPageData.reduziert` (aus `CBD_Classroom::enqueue_frontend_assets()`)
sagt `classroom-page-filter.js`, dass der Server bereits gefiltert hat. Der
Filter unterdrückt dann seine Warnung „markierte Blöcke nicht gefunden" — auf
einer reduzierten Seite ist alles Vorhandene freigegeben, und freigegebene
Container **anderer** Seiten fehlen naturgemäß.

### Prüfharnisch

`php tools/test-classroom-gate.php` — 37 Prüfungen ohne WordPress.

**Beim Prüfen mit `curl` daran denken:** Die REST-Schnittstelle verlangt zur
Cookie-Anmeldung zusätzlich `X-WP-Nonce`; ohne den gilt die Anfrage als anonym.

## Klassenmodus: Klappbare Inhaltsverzeichnisse (Phase 1 von `PLAN-Inhaltsverzeichnisse.md`, seit 2026-08-26)

Zwei Ansichten zeigen im Klassenmodus dieselbe Seitenhierarchie: die
**Login-Seitenliste** nach dem Klasseneinstieg (`[cbd_classroom]`-Shortcode,
`renderClassroomContent()` in `assets/js/classroom-frontend.js`) und die
**Klassen-Seitenleiste**, die beim Navigieren innerhalb der Klasse den
Theme-Sidebar-Inhalt ersetzt (`injectClassroomSidebar()` in
`assets/js/classroom-page-filter.js`). Dieses Vorhaben hat beide Ansichten im
Darkmode lesbar gemacht (zuvor literale dunkle Textfarben ohne
Darkmode-Anpassung in `assets/css/classroom-frontend.css`, AP-1.1), die
Login-Liste von einer optisch eingerückten Flachliste auf eine echte,
verschachtelte Baumstruktur mit Klapp-Buttons umgebaut (AP-1.2, adaptiert vom
bereits vorhandenen Verfahren in `injectClassroomSidebar()`) und für beide
Ansichten den Klappzustand dauerhaft gespeichert (AP-1.3a/AP-1.3b).

### Der `localStorage`-Vertrag: `cbd_classroom_toc_collapsed`

Analog zum bereits dokumentierten Filter `simple_clean_lehrerseite_freigeben`
(Abschnitt „Klassen-Durchlass für gesperrte Seiten" oben) ist dieser
Schlüssel eine feste, dokumentierte Schnittstelle — hier nicht zwischen
Plugin und Theme, sondern zwischen den beiden genannten JavaScript-Dateien
desselben Plugins:

| Eigenschaft | Festlegung |
|---|---|
| Schlüssel | `localStorage['cbd_classroom_toc_collapsed']` |
| Wert | JSON-Array von WordPress-Seiten-IDs **als Strings** (z. B. `["338","391"]`) — die vom Nutzer explizit **zugeklappten** Knoten |
| Fehlt der Schlüssel, ist er leer oder ungültiges JSON | alles gilt als aufgeklappt (Standardzustand, keine Verhaltensänderung gegenüber dem Stand vor diesem Vorhaben) |
| Gespeichert wird die Ausnahme, nicht die Regel | zugeklappte statt aufgeklappte Knoten — sonst wäre jede künftig neu in den Baum aufgenommene Seite standardmäßig zugeklappt |
| Geteilt zwischen | `assets/js/classroom-frontend.js` (`renderClassroomContent()`) und `assets/js/classroom-page-filter.js` (`injectClassroomSidebar()`) |
| Zugriffsfunktionen | `cbdKlassenverzeichnisGeleseneCollapsedIds()` (liest, `try/catch` um `JSON.parse`, Rückgabe immer ein Array) und `cbdKlassenverzeichnisSchreibeCollapsedIds(idsArray)` (schreibt, `try/catch` um `localStorage.setItem`) — **je eine eigene, wortgleiche Kopie** in der IIFE jeder der beiden Dateien, kein gemeinsames Modul (das Plugin hat keinen Build-Prozess für JavaScript) |
| Laufen beide Dateien je gleichzeitig auf derselben Seite? | Nein — die Login-Seite (Klasseneinstieg per Shortcode) und normale Seiten innerhalb einer laufenden Klassensitzung (`?classroom=&token=`) sind unterschiedliche Seiten; die Namensgleichheit der beiden privaten Hilfsfunktionen ist deshalb unproblematisch |

Damit bleibt ein zugeklapptes Kapitel über `location.reload()`/erneuten Login
und über eine Navigation innerhalb der Klasse hinweg zugeklappt, und ein in
der einen Ansicht zugeklapptes Kapitel erscheint auch in der anderen
zugeklappt — beide Richtungen sind live gegeneinander geprüft
(AP-1.rev-Integrationstest).

**Das ID-Feld heißt `page.page_id`, nicht `page.id`.** Die AJAX-Antwort
`cbd_student_get_data` liefert die Seiten-ID unter `page.page_id`; ein
Zugriff auf das nicht existierende `page.id` hätte an jedem Knoten
`data-page-id="undefined"` erzeugt und mehrere Kapitel einen gemeinsamen
Klappzustand teilen lassen (Fund aus AP-1.3b, live am echten
Netzwerk-Response verifiziert).

### Bekannte, bewusst akzeptierte Einschränkungen

Aus dem unabhängigen Review AP-1.rev (`PLAN-Inhaltsverzeichnisse.md`,
Abschnitt 7 — kein kritischer oder mittlerer Befund, fünf geringe, kein
Korrektur-AP nötig):

1. **`classroom-frontend.css:24`** — `.cbd-classroom-wrapper h2` ist ein
   toter Selektor: Der reale Wrapper der Shortcode-Ausgabe heißt
   `.cbd-classroom-container` (`class-cbd-classroom.php:1125`), nicht
   `.cbd-classroom-wrapper`. Die Überschrift „Klassen-Zugang" bleibt trotzdem
   korrekt darkmode-fähig, weil sie `color` von `body` erbt
   (`Theme/style.css`, `color: var(--color-text-primary, #333)`). Kein
   sichtbares Symptom, gefunden in AP-1.1.
2. **`classroom-page-filter.js`** — `String(page.page_id)` ist ungeschützt
   gegen ein fehlendes Feld; fehlte es, entstünde `data-page-id="undefined"`
   und mehrere Kapitel teilten sich einen Klappzustand. Aktuell nie
   ausgelöst, da das Feld in der echten AJAX-Antwort immer vorhanden ist.
3. **`classroom-page-filter.js`, Toggle-Vergabe** — ein zugeklappt startender
   Knoten bekommt beim Aufbau kein explizites `aria-expanded="false"` (bleibt
   `null`, statt es zu setzen); die Login-Liste (`classroom-frontend.js`)
   macht das korrekt vor. Kein funktionaler Fehler, nur eine
   Konsistenzlücke zwischen beiden Ansichten.
4. **`classroom-frontend.css`, `.cbd-classroom-page-row:hover`** —
   verwendet `rgba(76,175,80,.15)` hartcodiert statt über eine CSS-Variable;
   formal kein Verstoß gegen die Hex-Wert-Konvention (kein Hex-Code), aus
   Bestandscode übernommen.
5. **`classroom-frontend.css`** — zwei sich widersprechende
   `.cbd-classroom-children { margin-left }`-Regeln stehen in der Datei; die
   spätere gewinnt, kein sichtbarer Fehler.

Details je Befund und die vollständigen Übergabenotizen der Phase-1-APs:
`PLAN-Inhaltsverzeichnisse.md`, Abschnitt 7. Datei-Referenzen:
`reference_file_map.md`, Zeilen zu `classroom-frontend.css` und zu
`classroom-frontend.js`/`classroom-page-filter.js`.

## Klassenmodus-Anmeldebaum: Theme-Akzentfarbe statt Grün (Phase 1 von `PLAN-Fragenwand.md`, seit 2026-08-28)

Die Randlinien der Kapitelkarten und Gruppenköpfe im Klassenmodus-Anmeldebaum
(`[cbd_classroom]`-Shortcode, `render_classroom_shortcode()` in
`class-cbd-classroom.php`) waren bislang hartcodiert grün (`#4caf50` und
Abstufungen). Seit diesem Vorhaben definiert `class-cbd-classroom.php` die
neue private Methode `classroom_accent_inline_css()`, die
`get_theme_mod('color_ui_surface', '#e24614')` liest und den Wert als
CSS-Custom-Property `--cbd-classroom-accent` per
`wp_add_inline_style('cbd-classroom-frontend', ...)` an allen drei
bestehenden `wp_enqueue_style('cbd-classroom-frontend', ...)`-Stellen setzt
(einmal in `render_classroom_shortcode()`, zweimal in
`enqueue_frontend_assets()`) — dasselbe Muster, das im Projekt bereits an
anderer Stelle etabliert ist (`Plugins/Eigene WP Blocks/CLAUDE.md`,
Abschnitt „Buttons mit Theme-Farben", Muster A). In `classroom-frontend.css`
sind genau sieben Randlinien-Fundstellen
(`.cbd-classroom-parent-title`, `.cbd-classroom-parent-header`,
`.cbd-classroom-page-item.cbd-level-0` bis `-5`,
`.cbd-classroom-page-item:hover` — nur die `border-color`-Zeile, nicht
`box-shadow`) von hartcodierten Grünwerten auf
`var(--cbd-classroom-accent, #e24614)` umgestellt. Details: `PLAN-Fragenwand.md`,
AP-1.1/AP-1.rev.

### Bekannte, bewusst akzeptierte Einschränkungen

Aus dem unabhängigen Review AP-1.rev (`PLAN-Fragenwand.md`, Abschnitt 7 —
kein kritischer oder mittlerer Befund, sechs geringe, kein Korrektur-AP
nötig):

1. **`classroom-frontend.css:874-881`** — der Kommentar über der Sektion
   „LOGIN-LISTE: KLAPPBARE BAUMSTRUKTUR" behauptet weiterhin fälschlich,
   `--cbd-classroom-accent` sei „bewusst nirgends definiert". Das war bis
   AP-1.1 korrekt, seither ist die Variable real gesetzt (siehe oben) — der
   Kommentar ist noch nicht nachgezogen. Reine Kommentarpflege, kein
   Funktionsrisiko.
2. **`classroom-frontend.css:946`** (`.cbd-classroom-page-row:hover`) — der
   Fallback bleibt `var(--cbd-classroom-accent, #4caf50)` statt `#e24614`;
   laut Plan zulässig, da der Fallback nur greift, wenn das Inline-Style aus
   Schritt 1 einmal fehlt.
3. Auf einer `[cbd_classroom]`-Seite wird die Inline-Style-Zeile durch zwei
   parallel laufende Enqueue-Zweige doppelt ausgegeben (zwei identische
   `<style>`-Anweisungen statt einer) — kosmetisch, kein Fehlverhalten.
4. `classroom_accent_inline_css()` sichert den Customizer-Farbwert mit
   `esc_attr()` statt `sanitize_hex_color()` ab. Entspricht dem im Projekt
   bereits dokumentierten Muster A (siehe oben); unkritisch, da der Wert aus
   dem eigenen Customizer stammt, nicht aus Nutzereingabe.
5. **`classroom-frontend.css:663`** (`.cbd-classroom-page-title`) und
   **`:686`** (`.cbd-drawing-overlay`) tragen weiterhin grüne Rahmenregeln
   (`#4caf50`) — waren nie Teil des AP-1.1-Scopes (siehe Nicht-Ziele in
   `PLAN-Fragenwand.md`, Abschnitt 2), offener Punkt für ein künftiges AP.
6. `CBD_VERSION` bleibt unverändert (`3.1.106`) — ein Versions-Bump für
   Cache-Busting der geänderten CSS-Datei ist noch nicht erfolgt; sollte
   spätestens beim finalen Plugin-ZIP-Bau berücksichtigt werden (siehe
   `PLAN-Fragenwand.md`, AP-3.doc/AP-4.doc).

Details und die vollständige Übergabenotiz: `PLAN-Fragenwand.md`, Abschnitt
7, AP-1.1/AP-1.rev. Datei-Referenz: `reference_file_map.md`, Zeile zu
`classroom-frontend.css`.

## Aktionsleiste: Sichtbarkeit, Verschachtelung, Behandelt-Dialog

Die Leiste oben rechts im Container (`.cbd-action-buttons`) erscheint per
`:hover`, `:focus-within` oder `.cbd-selected` und blendet sich seit 3.1.94
nach einer Sekunde von selbst wieder aus (`cbd-actions-verborgen`, gesetzt in
`assets/js/interactivity-store.js` **und** `interactivity-fallback.js` — je
Installation läuft nur eines der beiden). Anlass war das haftende `:hover` auf
Tablets. Plan: `docs/archiv/PLAN-Aktionsleiste-Autoausblenden.md`.

**Das gerenderte Markup trägt `.cbd-container` zweimal je Block** — einmal am
interaktiven Wurzelelement (`#cbd-container-N`, darin
`.cbd-container-block > .cbd-action-buttons`) und einmal am Blockinhalt
(`.cbd-container-content > .wp-block-…cbd-container`). Wer nur den Editor-Code
liest, vermutet das nicht. Zusammen mit dem Nachfahren-Selektor der
Einblend-Regel führte das bei **verschachtelten** Containern dazu, dass zwei
Leisten gleichzeitig erschienen: `:hover` gilt für alle Vorfahren, der äußere
Container war also immer mitbetroffen. Seit 3.1.95 hängen zwei zusätzliche
Regeln in `cbd-frontend-clean.css` an der Eigentümer-Kette
`.cbd-container > .cbd-container-block > .cbd-action-buttons` und stehen in
`@media (hover: hover)` — **der Touch-Block weiter oben hält die Leiste auf
Tablets bewusst dauerhaft sichtbar und wäre sonst geschlagen worden.**

**Der Tastaturfokus hängt an `:not(:focus-within)`, nicht an der
Spezifität.** Im Code stand lange die Behauptung, eine Ausblend-Regel ohne
`:focus-within` könne die Einblend-Regel „nie schlagen". Das ist falsch herum
gerechnet: 0-4-0 gegen 0-3-0, die Ausblend-Regel gewinnt. Getragen hat den
Schutz allein das JavaScript (`focusin` entfernt die Klasse). Wer die Regel
umbaut, muss `:not(:focus-within)` mitnehmen.

### Behandelt-Dialog: Knopf und Gestaltung liefen auseinander

Der Knopf „Als behandelt markieren" (Button 6 in
`class-cbd-block-registration.php`) wird gerendert, sobald das Klassensystem
eingeschaltet ist und eine angemeldete Lehrperson mit `cbd_edit_blocks` die
Seite sieht — **unabhängig vom Tafelmodus.** Der Dialog dazu entsteht in
`showClassSelectorDialog()` (`interactivity-store.js`) und hängt sich an
`document.body`.

Seine Gestaltung stand aber ausschließlich in `assets/css/board-mode.css`, und
die lädt `CBD_Style_Loader::enqueue_feature_styles()` nur, wenn das Block-Design
auf **dieser** Seite das Feature `boardMode` trägt. Überall sonst erschien der
Dialog ohne `position: fixed`, ohne Überdeckung und ohne `z-index` — als
Textblock am Seitenende. Seit 3.1.96 stehen die `.cbd-behandelt-*`-Regeln in
`cbd-frontend-clean.css`, die immer geladen wird.

**Muster, das sich wiederholt:** Ein Bedienelement wird unbedingt gerendert,
seine CSS-Datei aber bedingt geladen. Wer einen Knopf in die Aktionsleiste
hängt, prüft, in welcher Datei seine Regeln stehen und wann die geladen wird.

## LaTeX-Formeln: Renderpfad und Wiederholrendern (seit 3.1.88)

Formeln laufen durch zwei Filter, aber registriert wird beides an **einer**
Stelle: dem Konstruktor von `CBD_LaTeX_Parser`
(`includes/class-latex-parser.php`).

| Filter | Priorität | Sieht |
|---|---|---|
| `render_block` | 5 | jeden einzelnen Block samt Blockname — läuft **vor** `do_blocks()` (Kern-Priorität 9) |
| `the_content` | 11 | den fertigen Beitragsinhalt — läuft **nach** `do_blocks()`, `wpautop()` und `wptexturize()` (Kern-Priorität 9/10) |

### Priorität 11 ist ein Sicherheitsnetz, kein zweiter gleichwertiger Weg

WordPress schickt auch Freiform-Inhalt (klassischer Editor, kein Blockmarkup —
`blockName === null`) durch `render_block`. Der Filter auf Priorität 5 sieht
also praktisch **jeden** Inhalt und erledigt die Arbeit in aller Regel
vollständig, bevor `the_content` überhaupt läuft. Der Filter auf Priorität 11
greift damit fast nie produktiv — er fängt nur den schmalen Rest ab, den
Priorität 5 aus gutem Grund ausgelassen hat (siehe nächster Abschnitt).

**Der Doppelparse-Schutz ist seitenweit, nicht je Formel.** `parse_latex()`
prüft einmal, ob der übergebene Text bereits `cbd-latex-formula` enthält
(`:306`), und gibt ihn dann unverändert zurück — für den **ganzen** Text, nicht
nur für die eine Formel, die den Treffer ausgelöst hat. Auf `render_block`
(Priorität 5) betrifft das jeweils nur den einen Block. Auf `the_content`
(Priorität 11) ist der übergebene Text der **gesamte** Beitragsinhalt: Hat
irgendein Block auf der Seite bereits eine Formel über `render_block`
gerendert, enthält der Gesamttext `cbd-latex-formula`, und der komplette
`the_content`-Durchlauf entfällt — auch für Formeln in Blöcken, die auf
Priorität 5 übersprungen wurden. Das ist Absicht, siehe nächster Abschnitt.

### LaTeX im „Individuellen HTML"-Block: bewusst nicht immer gesetzt

Seit AP-1.fix2 überspringt `parse_latex_in_blocks()` die Blocktypen in
`CBD_LaTeX_Parser::KEIN_LATEX_BLOCK` (`core/html`, `core/code`,
`core/preformatted`, `core/freeform`) vollständig — dort sind `\(` und `\[`
gewöhnliche Zeichen, die in JavaScript-Regexen wie `/\(([^)]+)\)/g`
alltäglich vorkommen. Ohne diese Ausnahme hätte der Parser seit den in
AP-1.1 ergänzten Delimitern `\(…\)`/`\[…\]` jedes Skript in einem
„Individuelles HTML"-Block beim Rendern still zerstört.

Der `the_content`-Filter (Priorität 11) holt Formeln in einem solchen Block
**normalerweise** nach — er kennt keine Blocktypen, nur den fertigen Text.
**Außer** wenn auf derselben Seite bereits eine andere Formel über
`render_block` gerendert wurde: Dann greift der oben beschriebene
seitenweite Doppelparse-Schutz, und der komplette `the_content`-Durchlauf
entfällt, die Formel im HTML-Block bleibt Rohtext. Das ist ein bewusster
Tausch: **heile Skripte wiegen schwerer** als eine in jedem Fall gerenderte
Formel innerhalb eines HTML-Blocks. Eine zweite Schutzebene fängt den Fall
ab, dass ein `<script>`, `<pre>` oder `<code>` **nicht** in einem eigenen
HTML-Block steht, sondern mitten in einem gewöhnlichen Absatz oder in einem
Container-Block: `mask_protected_regions()` nimmt diese Bereiche vor allen
Delimiter-Mustern per Platzhalter aus dem Text, `restore_placeholders()`
tauscht sie am Ende zurück — **dieselbe** Platzhalter-Mechanik, die auch für
`$$…$$` und die übrigen Display-Formeln verwendet wird (kein zweiter,
separater Mechanismus).

### `normalize_formula_text()`: Die Reihenfolge ist zwingend

Weil `the_content` erst nach `wpautop()`/`wptexturize()` läuft, hat WordPress
den Formeltext zu diesem Zeitpunkt bereits angefasst — weiche Zeilenumbrüche
wurden zu `<br />`, gerade Anführungszeichen zu typografischen Entities. Drei
Schritte in **dieser** Reihenfolge machen das rückgängig:

1. `<br />` entfernen (wpautop)
2. die wptexturize-Ersetzungstabelle zurückdrehen (`&#8217;` → `'` usw.)
3. `html_entity_decode()` (alle übrigen Entities, u. a. `&amp;` → `&`, `&eacute;` → `é`)

**Vertauscht man Schritt 2 und 3, bricht die Ableitungsschreibweise:**
`html_entity_decode()` zuerst würde aus `&#8217;` bereits ein echtes
Anführungszeichen machen (`f’(x)`, U+2019) — die anschließende Tabelle sucht
aber nach der Entity-Schreibweise `&#8217;` und findet sie nicht mehr. KaTeX
bekäme also `f’(x)` mit dem typografischen Zeichen statt der Ableitung
`f'(x)`. Schritt 1 muss ebenfalls vor Schritt 3 laufen: Ein maskiertes
`&lt;br /&gt;` (jemand will das Tag als Text zeigen) würde durch ein vorab
ausgeführtes `html_entity_decode()` zu einem echten `<br />`, das dann fälschlich
von Schritt 1 entfernt würde.

### Formeln im Blocktitel: die Falle war das $-Zählen

Der Blocktitel steht im Attribut `blockTitle` und wird in
`class-cbd-block-registration.php` als `esc_html($block_title)` in ein
`<h3 class="cbd-block-title">` geschrieben. **Dort gehört kein LaTeX-Aufruf
hinein** — `CBD_LaTeX_Parser` hängt auf `render_block` (Priorität 5) und sieht
die fertige Ausgabe der Methode, das `<h3>` eingeschlossen. Titelformeln
rendert er also von selbst.

**Warum sie es zeitweise trotzdem nicht taten:** Bis 2026-08-21 stand in
`parse_latex_in_blocks()` eine Prüfung auf eine **ungerade Zahl von `$`** im
Block. Traf sie zu, gab die Methode den Block unverändert zurück, setzte eine
rote Warnbox darüber und hinterlegte jedes `$` rot. Eine Formel im Titel plus
ein einzelnes `$` im Text — „Das kostet 65$" — ergibt eine ungerade Bilanz.
Ergebnis: **weder Titel noch Inhalt gerendert**, dazu eine Fehlermeldung für
einen Text, an dem nichts falsch war. Das war die Ursache; sie ist mit der
Prüfung entfallen.

**Zwei Sackgassen, damit sie niemand erneut betritt:**

1. **Den Titel hier selbst vorrendern** (kurzlebig in 3.1.97/98, am selben Tag
   wieder entfernt). Die vorgerenderten `<span class="cbd-latex-formula">`
   ließen den damaligen Doppelparse-Schutz anschlagen; der Parser gab den
   ganzen Block unverändert heraus, und der **Inhalt** desselben Blocks blieb
   unformatiert. Gemessen: **8 Formeln ohne, 4 mit** dem Vorrendern.
   Der Doppelparse-Schutz ist inzwischen umgebaut (siehe nächster Absatz),
   aber überflüssig bleibt das Vorrendern: Der Parser sieht den Titel ohnehin.

### Doppelparse-Schutz: maskieren statt abbrechen (seit 2026-08-21)

`parse_latex()` begann früher mit einem Rundum-Abbruch: Fand sich irgendwo
`cbd-latex-formula`, kam der ganze Inhalt unverändert zurück. **Das ging bei
verschachtelten Blöcken schief.** `render_block` feuert für den **inneren**
Block zuerst. Enthält dessen Inhalt eine Formel, sieht der äußere Container
beim eigenen Durchlauf schon fertige Spans — und gibt auf, bevor er seinen
**eigenen Blocktitel** ansieht. Formeln im Titel blieben dann Text, aber
**nur** bei Containern, deren Inhalt ebenfalls eine Formel enthielt; ein
Container mit formelfreiem Inhalt war unauffällig. Genau das machte den Fehler
so schwer zu greifen.

Jetzt maskiert `mask_protected_regions()` fertige Formeln wie
`<script>`/`<pre>`/`<code>`: Sie laufen unverändert durch, alles daneben wird
trotzdem geparst. Das Muster ist zeichengenau auf die Ausgabe von
`build_inline_formula()` und `build_display_formula()` zugeschnitten — beide
erzeugen dieselbe zweistufige Form mit **leerem** inneren `<span>` (KaTeX füllt
es erst im Browser), weshalb es keine Verschachtelungsmehrdeutigkeit gibt.
**Wer dieses Markup ändert, muss das Muster mitziehen.**
2. **Auf eine gerade Zahl von `$` prüfen.** Ein Dollarzeichen im Fließtext ist
   normal, und die Anzahl sagt nichts über einen Fehler. Siehe die ausführliche
   Notiz an der Stelle, an der die Prüfung stand.

### Die Leerzeichenregel für `$…$` (seit 2026-08-21)

Was eine Formel ist, entscheidet allein das Inline-Muster in `parse_latex()`:

```
/\$(?!\s)([^\$]{1,500}?)(?<!\s)\$/s
```

Die beiden Umschauen sind der ganze Trick: **direkt hinter dem öffnenden und
direkt vor dem schließenden `$` darf kein Leerraum stehen.**

| Eingabe | Ergebnis |
|---|---|
| `$E=mc^2$` | Formel |
| `$Testformel $` | keine Formel — Leerzeichen vor dem Schluss-`$` |
| `$ Testformel$` | keine Formel |
| `Das kostet 65$` | keine Formel |
| `Zwischen 5$ und 10$ liegen` | keine Formel — das erste `$` hat Luft dahinter |

Vorher griff das Muster zwischen zwei **beliebigen** `$` und zog
„65$ und dann 30$" zu einer Formel „ und dann 30" zusammen. Genau dagegen war
die $-Zählung als Notbremse gedacht — sie richtete mehr Schaden an, als sie
verhinderte. Die Leerzeichenregel löst das Problem an der Wurzel, ist dieselbe,
die TeX anwendet, und für Schreibende leicht zu merken: **Formel ohne Luft an
den Rändern.**

`$$…$$` bleibt bewusst unberührt — dort sind Leerzeichen innen üblich, und
`$$` kommt im Fließtext nicht vor.

**Ob eine erkannte Formel dann richtig aussieht, entscheidet die
Sichtprüfung.** Der Parser urteilt darüber nicht; jede Heuristik in dieser
Richtung hat bisher Fließtext getroffen. Prüfungen dazu:
`tools/test-latex-parser.php`, Abschnitt „Leerzeichenregel".

### kses zerstört Blocktitel nicht — die Falle liegt woanders

Es hielt sich in diesem Projekt zeitweise die Annahme, `wp_kses_post()`
zerstöre LaTeX in Blocktiteln, sobald eine Rolle ohne `unfiltered_html`
speichert. **Das ist widerlegt** (Messung am 2026-08-21 mit dem Konto
`blockredakteur`, Belege in `docs/archiv/PLAN-Blocktrenner-vor-kses-schuetzen.md`,
Abschnitt 10). Zwei Kernmechanismen schützen den Trenner:

| Mechanismus | Was er tut |
|---|---|
| `serialize_block_attributes()` | ersetzt beim Serialisieren `\` durch `\u005c`, dazu `--`, `<`, `>`, `&` und `\"`. Im Trenner steht damit kein Zeichen, an dem kses sich stören könnte. Der Editor tut im Browser dasselbe |
| `pre_kses` → `wp_pre_kses_block_attributes()` → `filter_block_content()` | filtert Blockattribute blockweise und serialisiert danach neu, statt den Trenner als HTML-Kommentar zu behandeln |

Sichtbare Nebenwirkung des zweiten Mechanismus: Ein `&` im Titel steht danach
als `&amp;` im Attribut. **Das ist kein Schaden** — `esc_html()` kodiert nicht
doppelt (`_wp_specialchars(…, $double_encode = false)`), der Leser sieht ein
`&`, und wiederholtes Speichern verschlimmert nichts (über drei Durchläufe
gemessen).

**Woran die alte Fehlmessung lag:** Sie baute den Block-Trenner im Prüfskript
selbst zusammen und schrieb den Backslash roh ins JSON — solches Markup
entsteht nie im Editor. Das Schadensmuster verriet es: `\frac` → `rac` und
`\beta` → `eta` ist das Verschwinden von `\f` und `\b`, also von
**Escape-Folgen einer Zeichenkette im Prüfskript**. kses entfernt keine
Zeichenpaare dieser Art. **Wer Blockmarkup misst, erzeugt es über
`serialize_blocks()`** — sonst misst er sein eigenes Skript.

### `window.cbdRenderLatex(root)` — die Zusage an andere Skripte

`assets/js/latex-renderer.js` stellt eine öffentliche Funktion bereit, gegen
die auch andere Plugins programmieren dürfen:

| Eigenschaft | Festlegung |
|---|---|
| Aufruf | `window.cbdRenderLatex(root)` |
| Parameter `root` | `Element` oder `Document`. Optional, Vorgabe `document`. |
| Wirkung | Rendert alle `.cbd-latex-formula` innerhalb von `root`, die **kein** Attribut `data-cbd-latex-rendered="1"` tragen (bereits fehlgeschlagene tragen `data-cbd-latex-failed="1"` und werden nicht erneut versucht). Nach erfolgreichem Rendern wird `data-cbd-latex-rendered="1"` gesetzt. |
| Rückgabe | `Promise<number>` — Anzahl **neu** gerenderter Formeln. Löst erst auf, **nachdem** `document.fonts.ready` erfüllt ist, damit der Aufrufer danach zuverlässig Höhen messen kann. |
| KaTeX fehlt | Liefert `Promise.resolve(0)`, wirft **nicht**. |
| Verfügbarkeit | Existiert, sobald `latex-renderer.js` geladen ist (an KaTeX gekoppelt eingebunden). Aufrufer aus anderen Plugins müssen `typeof window.cbdRenderLatex === 'function'` prüfen — das CDB-Plugin kann abgeschaltet sein. |

Intern ruft `renderAllFormulas()` (Fallback bei `DOMContentLoaded`), ein
sofort beim Laden der Datei gestarteter `MutationObserver` (reagiert nur auf
**hinzugefügte** Knoten) und ein entprellter `resize`-Listener (150 ms)
dieselbe Funktion auf. Grund für den `resize`-Listener: Das Accordion in
„Eigene WP Blocks" feuert beim Aufklappen ein `resize`-Ereignis als
generisches „bitte neu vermessen"-Signal — damit greift der Fall auch dann,
wenn ein Aufrufer die Funktion nicht direkt nutzt.

### Warum Display-Formeln ein `<span>` sind, kein `<div>`

`render_display_formula()` gibt `<span class="cbd-latex-formula
cbd-latex-display">` aus, nie ein `<div>`. Grund: Ein `<div>` innerhalb eines
`<p>` zwingt den HTML-Parser des Browsers, den Absatz aufzuspalten — dabei
entstehen nackte Textknoten neben dem Absatz. Blöcke, die ihren Inhalt anhand
von `children` umsortieren (nicht `childNodes`), verlieren diese Textknoten
komplett; sie bleiben sichtbar außerhalb jeder Struktur stehen. Genau das
passierte im Accordion-Block, bevor AP-1.2 `buildRows()` auf `childNodes`
umgestellt hat. Die Blockdarstellung (zentriert, eigene Zeile) liefert
`display: block` in `latex-formulas.css` (`.cbd-latex-formula.cbd-latex-display`)
— das greift für ein `<span>` genauso wie für ein `<div>`, ohne dessen
Nebenwirkung auf den Absatz.

### Dritte Stelle, an der die beiden Plugins zusammenwirken

Neben dem Accordion-Import (Abschnitt „Content-Importer") und der
Klassen-Freigabe (Abschnitt „Klassen-Durchlass für gesperrte Seiten") ist
`window.cbdRenderLatex` die **dritte** Stelle, an der CDB-Designer und
„Eigene WP Blocks" über eine Schnittstelle zusammenwirken. Anders als bei der
Klassen-Freigabe ist diese Naht **einseitig optional**: `blocks/accordion/view.js`
prüft vor jedem Aufruf `typeof window.cbdRenderLatex === 'function'` und
verhält sich ohne die Funktion wie zuvor — das CDB-Plugin kann fehlen oder
abgeschaltet sein, ohne dass das Accordion bricht. Näheres zur
Accordion-Seite dieser Naht steht in `Plugins/Eigene WP Blocks/CLAUDE.md`.

### Offener Punkt: `stableId`-Extraktion existiert dreifach

Die Extraktion der `stableId` aus einem Eintrag von `parse_blocks()` bzw. aus
gespeichertem Markup steht an **drei** Stellen in **drei** unterschiedlichen
Fassungen:

| Datei | Methode | Technik |
|---|---|---|
| `includes/class-cbd-block-registration.php` | `render_block()` (Rückfall-Zweig, `:902`) | regulärer Ausdruck `/data-stable-id="([^"]+)"/` |
| `includes/class-cbd-classroom-gate.php` | `block_erlaubt()` (`:281`) | derselbe reguläre Ausdruck, ein zweites Mal geschrieben |
| `includes/class-cbd-blocks-rest-api.php` | `extract_stable_id()` (`:210-212`) | `WP_HTML_Tag_Processor` |

**Es gibt dafür keinen Duplikatswächter.** Der Prüfharnisch
`tools/test-classroom-gate.php` weist zwar nach, dass die Suffix-Regel `:pN`
(für mehrseitige Tafelbilder, `<stableId>:pN`) nur **einmal** im Code steht —
aber diese Zusicherung liest ausschließlich `class-cbd-classroom.php` und
prüft nur die Suffix-Regel, nicht die `data-stable-id`-Extraktion selbst und
nicht die beiden anderen Dateien. Driften die drei Fassungen auseinander
(z. B. weil eine künftige Änderung nur an einer Stelle nachgezogen wird),
bemerkt das kein Test. Vorschlag für ein künftiges AP: die Extraktion einmal
zentral ablegen (etwa als statische Methode auf `CBD_Classroom_Gate` oder in
einer eigenen Hilfsklasse) und die beiden anderen Stellen darauf umstellen.

### Prüfharnisch

`php tools/test-latex-parser.php` — 113 Prüfungen ohne WordPress (Stand nach
AP-1.fix2; ursprünglich 78, TDD-Runde für die Code-Block-Ausnahme und die
Entity-Auflösung kam mit 35 neuen Fällen dazu). Geprüft werden die
Filterprioritäten, alle fünf Delimiter, `<span>` statt `<div>`, der
Doppelparse-Schutz, das Lade-Gate `should_load_katex()`, die Folgen der
`the_content`-Priorität 11 (Spuren von `wpautop`/`wptexturize` in der Formel)
sowie — seit AP-1.fix2 — die Code-Block-Ausnahme und die
Entity-Auflösung.

## Icon-Position: Kopfzeile oder Container-Ecke (seit 3.1.89)

Die Position des Block-Icons ist wieder einstellbar: die bisherige Kopfzeile
neben dem Titel, oder eine der vier Container-Ecken mit Feinversatz in
Pixeln. Datenschicht (Grenzen, Bereinigung, CSS-Erzeugung) in
`includes/functions.php`; Rendering im Frontend in
`class-cbd-block-registration.php`/`cbd-frontend-clean.css`; Einstellung
samt Live-Vorschau in beiden Admin-Formularen (`admin/new-block.php`,
`admin/edit-block.php`).

### Die fünf Positionswerte und das Speicherformat

| Wert | Bedeutung |
|---|---|
| `header` | **Standard.** Icon bleibt Teil der Kopfzeile neben dem Titel — heutiges Aussehen |
| `container-top-left` | Container, oben links |
| `container-top-right` | Container, oben rechts |
| `container-bottom-left` | Container, unten links |
| `container-bottom-right` | Container, unten rechts |

Gespeichert wird **flach** im `features`-JSON, drei zusätzliche Schlüssel
unter `icon`:

| Schlüssel | Typ | Vorgabe |
|---|---|---|
| `icon.position` | string, einer der fünf Werte oben | `header` |
| `icon.offsetX` | int, geklemmt auf −200…200 | `0` |
| `icon.offsetY` | int, geklemmt auf −200…200 | `0` |

Flach, nicht als `icon.position.x` verschachtelt — aus demselben Grund wie
sonst im `features`-JSON: `CBD_Design_Transfer` serialisiert Designs nach
Markdown über flache Punkt-Pfade und begrenzt in `sanitize_json_field()` die
Verschachtelungstiefe (Abschnitt „Designs exportieren / importieren"); eine
zusätzliche Ebene könnte am Export scheitern.

### Die sechs Funktionen

Alle in `includes/functions.php`, nach dem Muster des Icon-Größen-Reglers
(Abschnitt „Icon-Größen: wo sie stehen") in `if (!function_exists(…))`
gewickelt:

```php
cbd_icon_position_defaults(): array
// ['positions' => [5 Werte], 'default' => 'header',
//  'offset_min' => -200, 'offset_max' => 200, 'offset_default' => 0]

cbd_sanitize_icon_position($raw): string
// wp_unslash(), trim, Kleinschreibung; alles außerhalb der Whitelist -> 'header'

cbd_sanitize_icon_offset($raw): int
// wp_unslash(); deutsches Komma -> Punkt; nicht numerisch -> 0; runden;
// auf [-200, 200] klemmen

cbd_get_icon_position_class(string $position): string
// 'header' -> ''; sonst 'cbd-icon-positioned cbd-icon-at-<ecke>'

cbd_get_icon_position_style(int $offset_x, int $offset_y): string
// beide 0 -> ''; sonst '--cbd-icon-dx:<x>px;--cbd-icon-dy:<y>px;' (nie Komma)

cbd_icon_position_preview(string $position, int $offset_x, int $offset_y): array
// lesbare Beschriftung => Text, für die Admin-Vorschau
```

`cbd_sanitize_icon_position()` und `cbd_sanitize_icon_offset()` werden
**zweimal** aufgerufen: einmal beim Speichern in
`cbd_parse_features_from_post()`, ein zweites Mal beim Rendern in
`class-cbd-block-registration.php`. Werte aus einem JSON-Feld der Datenbank
gelten grundsätzlich nicht als vertrauenswürdig — auch nicht die eigenen.

### Warum der Standardwert `header` heißt und die vier Altwerte darauf zurückfallen

Es gab bislang **keine** funktionierende Icon-Positionierung. Das
Eingabefeld war vor Längerem aus beiden Admin-Formularen entfernt worden; an
seiner Stelle stand nur ein Hinweistext („Das Icon wird automatisch in der
linken oberen Ecke … angezeigt."). `cbd_parse_features_from_post()` schrieb
den Wert trotzdem bei **jedem** Speichern ungeprüft auf `'top-left'` zurück —
die einzige lesende Stelle zielte zudem auf einen CSS-Selektor, der im
Markup nie vorkam. In **jedem** bestehenden Design steht deshalb heute
`"position":"top-left"` — ein Wert, den nie jemand bewusst gewählt hat, weil
das Feld dafür gar nicht zur Verfügung stand.

Würde `top-left` künftig „linke obere Container-Ecke" bedeuten, verlöre
jeder vorhandene Block sein Icon aus der Kopfzeile, ohne dass jemand das
Design angefasst hätte. Deshalb heißt der neue Standardwert `header`
(= heutiges Aussehen), die vier neuen Eck-Werte tragen zur Unterscheidung
das Präfix `container-`, und `cbd_sanitize_icon_position()` lässt **alles**
außerhalb der Fünferliste — insbesondere die Altwerte `top-left`,
`top-right`, `bottom-left`, `bottom-right`, dazu leere und bösartige
Eingaben — auf `header` zurückfallen.

Bei `position = header`, `offsetX = 0`, `offsetY = 0` — dem Zustand
**jedes** Bestandsdesigns — liefern `cbd_get_icon_position_class()` und
`cbd_get_icon_position_style()` beide einen leeren String. Es entsteht damit
weder eine zusätzliche CSS-Klasse noch ein `style`-Attribut am
Icon-`<span>`: Das gerenderte Markup bleibt in diesem Fall **zeichengleich**
mit dem Stand vor dieser Erweiterung.

### Warum kein serverseitiges `transform` gesetzt wird

`.cbd-header-icon` trägt in `cbd-frontend-clean.css` bereits ein
`transform: translateY(…)` zur Grundlinien-Ausrichtung am Titel — mit **je
eigenem Wert pro Breakpoint**: −6px Desktop, −4px ≤768px, −3px ≤480px. Ein
serverseitig gesetztes `transform`-Attribut hätte alle drei Regeln
überschrieben und das Icon auf Tablet und Handy verrutschen lassen.

Transportiert wird der Feinversatz deshalb über zwei CSS-Variablen —
`--cbd-icon-dx` und `--cbd-icon-dy`, als Inline-Style am Icon-`<span>` von
`cbd_get_icon_position_style()` erzeugt —, und jede der drei
Breakpoint-Regeln rechnet sie in ihren eigenen Basiswert ein, z. B. Desktop:

```css
transform: translate(var(--cbd-icon-dx, 0px), calc(-6px + var(--cbd-icon-dy, 0px)));
```

Ändert sich einer der drei Basiswerte künftig, müssen **alle drei Stellen**
(Desktop, ≤768px, ≤480px) mitgezogen werden. Im Eckmodus (`container-*`)
entfällt die Grundlinien-Ausrichtung zum Titel, die man erhalten müsste —
dort besteht das `transform` nur aus dem Versatz:
`translate(var(--cbd-icon-dx, 0px), var(--cbd-icon-dy, 0px))`. Bezugsrahmen
für die absolute Positionierung ist `.cbd-container-block` (der nächste
positionierte Vorfahr im Markup), nicht der äußere, unsichtbare
`.cbd-container`-Wrapper — die neuen CSS-Regeln stehen zwar unter dem
Selektor-Präfix `.cbd-container …`, das bestimmt aber nur die Spezifität,
nicht den Positionierungskontext.

### Bekannte, unbehobene Kollision mit der Aktionsleiste

`.cbd-action-buttons` (Klappen/Kopieren/Screenshot/PDF/Tafel/Behandelt) sitzt
mit `position: absolute !important; top: 10px !important; right: 10px
!important; z-index: 9999 !important` im selben Positionierungsrahmen wie
ein Icon in `container-top-right` — dessen Grundabstand ebenfalls
`top: 10px; right: 10px` beträgt, allerdings mit `z-index: 3`. Ein Icon in
dieser Ecke ohne Feinversatz wird beim Überfahren des Containers von der
dann eingeblendeten Knopfleiste verdeckt. **Bewusst nicht behoben** in
diesem Vorhaben — Abhilfe ist ein Feinversatz über `offsetX`/`offsetY`, der
das Icon aus dem Bereich der Knopfleiste herausschiebt.

### Fundstellen-Karte „Icon-Größen: wo sie stehen" ergänzt

Die Tabelle „Lebende Dateien (Frontend)" im gleichnamigen Abschnitt führt
jetzt zusätzlich die beiden Positionierungs-Selektoren
`.cbd-header-icon.cbd-icon-positioned` und `.cbd-icon-at-<ecke>` — beide in
`cbd-frontend-clean.css`, mit 10px Grundabstand je Ecke. Nicht zu
verwechseln mit den älteren `.cbd-icon`/`.cbd-icon-inside` in derselben
Datei: Deren Erzeuger `render_features()` ist seit Langem auskommentiert,
sie matchen nichts.

### Prüfharnisch

`php tools/test-icon-position.php` — 57 Prüfungen ohne WordPress, per TDD
entstanden (roter Commit vor dem grünen). Geprüft werden alle fünf
Positionswerte, der Rückfall der vier Altwerte auf `header`,
Groß-/Kleinschreibung und umgebender Whitespace, bösartige Eingaben, die
Pixel-Klemmung auf −200…200 inklusive deutschem Dezimalkomma (`12,5` → `13`),
ein Rundlauf mit vorab addierten Slashes wie aus `$_POST`, das Fehlen jedes
Kommas in `cbd_get_icon_position_style()`, sowie die Integration in
`cbd_parse_features_from_post()` (fehlende Felder → `header`/`0`/`0`,
Altwert `top-left` aus `$_POST` → `header`).

## Block-Referenz als Modul (seit 3.1.89)

Der Block „Block-Referenz" (`cbd/block-reference`) öffnet seinen Zielblock
standardmäßig in einem Overlay auf derselben Seite, statt zur Zielseite zu
springen. Editorfähigkeit und der Sprung selbst existierten bereits vorher;
dieser Abschnitt beschreibt den neu hinzugekommenen Modal-Modus.

### Das Attribut `displayMode`

`block.json` kennt seit diesem Stand ein neuntes Attribut:

| Attribut | Typ | Vorgabe | Werte |
|---|---|---|---|
| `displayMode` | string | `modal` | `modal`, `link` |

`link` entspricht dem ursprünglichen Verhalten (Sprung zum Zielblock bzw.
Navigation zur Zielseite). `modal` öffnet das Overlay. In **beiden** Modi
gibt `render.php` ein `<a href="…">` mit der vollständigen Ziel-URL aus,
**nie** ein `<button>`: Ohne JavaScript bleibt der Verweis ein gewöhnlicher
Link, das Modal entsteht erst durch `preventDefault()` in `view.js`
(fortschreitende Verbesserung).

### Rückwärtskompatibilitätsfolge

Bestehende, vor dieser Erweiterung gespeicherte Block-Referenz-Blöcke
enthalten `displayMode` nicht in ihrem gespeicherten Markup — das Attribut
gab es noch nicht. WordPress füllt fehlende Attribute beim Rendern aus dem
Vorgabewert der Blockdefinition auf
(`WP_Block_Type::prepare_attributes_for_render()`); für jeden dieser
Altbestände liest der Renderer deshalb `displayMode === 'modal'`.
**Bestehende Verweise öffnen künftig ein Overlay, statt zum Ziel zu
springen** — eine Verhaltensänderung ganz ohne Datenmigration. Das
gespeicherte Markup bleibt gültig, `href` unverändert; ohne JavaScript (oder
wenn das Skript nicht lädt) verhält sich der Verweis weiterhin wie ein
gewöhnlicher Link zur Zielstelle. Wer den Sprung ausdrücklich beibehalten
will, stellt im Editor „Verhalten beim Klick" auf „Zum Block springen".

### Die zwei Wege zum Inhalt

1. **DOM-Klon**, wenn der Zielblock auf **derselben** Seite liegt
   (`data-same-page="true"` am Verweis). Kein Netzverkehr, keine
   Autorisierung nötig — der Block liegt schon im DOM.
2. **Nachladen** über den Endpunkt unten, sonst.

**Verschärfung gegenüber der ursprünglichen Planung:** Der DOM-Pfad wird
ausschließlich über `data-same-page` entschieden, nicht durch eine bloße
Suche nach `[data-stable-id="…"]` auf der Seite. Grund: `CBD_Block_Organizer`
vergibt beim Kopieren eines Blocks (`copy_block()`) die `stableId` **nicht**
neu — dieselbe Kennung kann also auf zwei Seiten liegen. Ein ungeprüfter
DOM-Treffer könnte still der falsche Block sein (der auf der eigenen Seite
liegende Zwilling statt des tatsächlichen Ziels). Mit `data-same-page` als
Bedingung entscheidet stattdessen `render.php` serverseitig anhand der
`post_id`, ob Referenz und Ziel dieselbe Seite teilen — dort ist die Antwort
eindeutig.

Im DOM-Klon werden **alle** `id`-Attribute umbenannt (Präfix je Öffnung
eindeutig), nicht gelöscht — samt aller Verweise darauf (`aria-controls`,
`aria-labelledby`, `aria-describedby`, `aria-owns`, `aria-flowto`,
`aria-details`, `aria-errormessage`, `for`, `headers`, `list`, `form`,
`href="#…"`). Löschen hätte interne Bezüge im Modal (z. B. eines
verschachtelten Accordions) zerstört; ohne Umbenennung existierte jede ID
zweimal auf der Seite, und Sprungmarken oder `aria-controls` im **Original**
träfen je nach Fundreihenfolge plötzlich den Klon.

### Der Endpunkt `cbd/v1/block-html`

```
GET /wp-json/cbd/v1/block-html?post_id=<int>&stable_id=<string>[&classroom=<int>&token=<string>]
```

| Parameter | Pflicht | Zweck |
|---|---|---|
| `post_id` | ja | Seite, auf der der Block liegt. **Die Rechteprüfung hängt an diesem Wert**, nicht an `stable_id` — dieselbe Kennung kann nach `copy_block()` auf zwei Seiten liegen |
| `stable_id` | ja | Bezeichner des gesuchten Container-Blocks |
| `classroom` | nein | Klassen-ID einer laufenden Klassensitzung |
| `token` | nein | Token der Klassensitzung |

**Erfolg, HTTP 200:**
```json
{"html": "<div class=\"cbd-container\">…</div>", "title": "Titel des Blocks"}
```
`title` kommt aus den Blockattributen (`blockTitle`, sonst `title`), **nie**
aus dem Seitentitel oder dem Permalink.

**Jeder Fehlschlag, HTTP 404, immer zeichengleich:**
```json
{"code":"cbd_block_not_available","message":"Der Block ist nicht verfügbar."}
```
Dieselbe Antwort für: Seite existiert nicht, ist kein `publish`, ist
passwortgeschützt, ist gesperrt, Klassensitzung fehlt oder ist ungültig,
Block ist für die Klasse nicht freigegeben, Block existiert nicht,
`stable_id` gehört zu keinem Container-Block. **Absichtlich so knapp:**
Unterschiedliche Antworten ließen sich durch Durchprobieren von IDs zum
Kartieren der gesperrten Lösungsseiten nutzen — genau diesen Fehler hat das
Theme in `simple_clean_lehrerseite_kanonisch()` bereits einmal behoben.

Implementiert in `includes/class-cbd-block-content-api.php`
(`CBD_Block_Content_API`), **einer eigenen Klasse** neben
`class-cbd-blocks-rest-api.php`: Dort gilt „nur Redakteure"
(`current_user_can('edit_posts')`), hier „jeder, aber nur was er sehen darf"
— Schülerinnen und Schüler melden sich nie an, sie kommen über das
Klassenpasswort. Zwei derart gegensätzliche Sicherheitsmodelle in einer
Datei laden dazu ein, dass irgendwann eine Route den falschen
`permission_callback` bekommt. Der `permission_callback` dieser Route ist
deshalb `'__return_true'` — **die gesamte Autorisierung steckt im
Callback.**

#### Die Autorisierungskette, in dieser Reihenfolge

Jeder Fehlschlag endet sofort in der einheitlichen 404-Ablehnung von oben:

1. `nocache_headers()` — **immer und als Erstes**, ohne Bedingung. Dieselbe
   URL liefert für Lehrperson, Klassensitzung und anonymen Besucher
   unterschiedliche Inhalte; ein Cache dürfte sie nie verwechseln.
2. Die geteilten Helfer müssen existieren
   (`CBD_Classroom_Gate::block_erlaubt()`,
   `CBD_Classroom::basis_container_id()`) — sonst sofortige Ablehnung statt
   eines Fatal Errors.
3. `post_id` und `stable_id` müssen skalare, positive bzw. nicht-leere Werte
   sein.
4. Der Beitrag muss existieren, vom Typ `page` oder `post` sein und den
   Status `publish` tragen.
5. `post_password_required()` darf nicht zutreffen.
6. **Sichtbarkeit:** `simple_clean_seite_sichtbar($post_id)` —
   Theme-Funktion, hinter `function_exists()`. Sie ist die
   **Gesamtentscheidung** (deckt Lehrperson, Sperre samt Vererbung auf den
   Unterbaum und den Freigabe-Filter ab); ausdrücklich **nicht**
   `simple_clean_seite_nur_lehrpersonen()`, die kennt den Klassen-Durchlass
   nicht.
7. Ist die Seite zusätzlich gesperrt (`simple_clean_seite_nur_lehrpersonen()`
   ohne Lehrperson), muss eine gültige Klassensitzung vorliegen
   (`CBD_Classroom_Gate::sitzung()`, liest `classroom`/`token` aus denselben
   Query-Parametern) **und** der angefragte Block muss für diese Klasse
   freigegeben sein (`CBD_Classroom::behandelte_container()`). Standard ist
   Ablehnung: Was nicht in der Freigabeliste steht, gibt es für die Klasse
   nicht.
8. Der Block wird gesucht (rekursiv über `innerBlocks`) und muss zum
   Namensraum `container-block-designer/` gehören — die Suche übernimmt
   `CBD_Classroom_Gate::block_erlaubt()` (siehe unten), die
   Namensraumprüfung erfolgt **zusätzlich** noch einmal im Endpunkt selbst.
9. Gerendert wird mit `render_block($block)` bei temporär gesetztem
   `$GLOBALS['post']` (der vorherige Wert wird in jedem Fall
   wiederhergestellt) — **nicht** `do_blocks()` auf den ganzen Beitrag
   (rendert zu viel) und **nicht** `serialize_blocks()` mit eigener Ausgabe
   (der dokumentierte Whitespace-Unterschied zwischen JavaScript- und
   PHP-Serializer, Abschnitt „Block-Serializer"). Ein `Throwable` beim
   Rendern führt ebenfalls zur Ablehnung.

**Keine vierte Fassung der `stableId`-Extraktion:** Die Blocksuche ruft
`CBD_Classroom_Gate::block_erlaubt()` auf (Attribut `stableId`, sonst
Rückfall auf `data-stable-id` im gespeicherten Markup) statt eine eigene
Regel zu schreiben. Eine eigene Fassung wäre nach
`class-cbd-block-registration.php`, `class-cbd-classroom-gate.php` und
`class-cbd-blocks-rest-api.php` bereits die vierte Kopie (siehe Abschnitt
„Offener Punkt: `stableId`-Extraktion existiert dreifach" weiter oben — die
Zahl der Fassungen bleibt durch diesen Endpunkt unverändert bei drei).

### Die REST-Basis kommt aus `rest_url()`, nie aus dem JavaScript

`class-cbd-block-reference.php::localize_view_script()` übergibt dem
Frontend-Script die vollständige URL über
`esc_url_raw(rest_url('cbd/v1/block-html'))`, zusammen mit einem Nonce für
angemeldete Nutzer. `view.js` setzt daraus **nie selbst** einen Pfad
zusammen: Auf Installationen ohne hübsche Permalinks liefert `/wp-json/…`
einen Apache-404, dort funktioniert nur `?rest_route=/cbd/v1/block-html`.
Welche Form gilt, weiß ausschließlich `rest_url()` auf dem Server. **Der
Nonce ist keine Autorisierung** — er sorgt nur dafür, dass ein angemeldeter
Nutzer bei einem REST-Aufruf als angemeldet erkannt wird (ohne ihn gilt eine
Cookie-Anfrage als anonym). Die gesamte Rechteprüfung leistet ausschließlich
`CBD_Block_Content_API`.

### Das Modal ist eine Leseansicht

Die WordPress-Interactivity-API hydriert nur Markup, das beim Laden der
Seite bereits dastand — nachträglich eingefügtes, ob geklont oder
nachgeladen, bekommt keine Hydrierung. `view.js` entfernt deshalb aus dem
Modalinhalt die Aktionsleiste `.cbd-action-buttons` (Klappen, Kopieren,
Screenshot, PDF, Tafelmodus, Behandelt) vollständig, statt sie nur
unsichtbar zu machen: Jeder dieser Knöpfe hängt an
`data-wp-on--click`-Direktiven, die im Modal wirkungslos blieben — ein
sichtbarer, aber toter Knopf ist schlechter als keiner, und ein Screenreader
kündigte ihn trotzdem als bedienbar an. Eingeklappte Container werden
zusätzlich aufgeklappt, weil der entfernte Umschalter sie nicht mehr öffnen
könnte. Verweise **innerhalb** des Modals werden auf `displayMode = 'link'`
zurückgestuft — ein Modal im Modal ist damit ausgeschlossen.

Nach dem Einsetzen des Inhalts prüft `view.js`
`typeof window.cbdRenderLatex === 'function'` (Abschnitt „LaTeX-Formeln:
Renderpfad und Wiederholrendern") und rendert Formeln im Modalinhalt nach.
Geklonte Formeln tragen bereits `data-cbd-latex-rendered="1"` und werden
übersprungen; nachgeladene brauchen den Aufruf.

### Vierte Naht zwischen den Komponenten

Nach dem Menü-Slug `page-manager` (Abschnitt „Seiten aus Markdown erzeugen"
im Wurzel-`CLAUDE.md`), dem Filter `simple_clean_lehrerseite_freigeben`
(Abschnitt „Klassen-Durchlass für gesperrte Seiten") und dem LaTeX-Renderer
`window.cbdRenderLatex` (Abschnitt „LaTeX-Formeln: Renderpfad und
Wiederholrendern") ist dieser Endpunkt die **vierte** Stelle, an der Theme
und Plugins über eine Schnittstelle zusammenwirken — und die zweite (neben
der Klassen-Freigabe), an der das Plugin aktiv eine Theme-Funktion aufruft,
statt umgekehrt einen Filter zu bedienen. Fehlt das Theme, greift
`function_exists()`: Es gibt dann schlicht keine Sperre, Schritt 6 lehnt nie
ab — der Endpunkt stirbt aber auch nicht an einem unbekannten
Funktionsaufruf.

### Prüfharnisch

`php tools/test-block-content-api.php` — 84 Prüfungen ohne WordPress.
Geprüft werden: Fund über das Attribut `stableId` und über den
`data-stable-id`-Rückfall, Rekursion in `innerBlocks`, unbekannte
`stable_id` → Ablehnung, fremder Namensraum (z. B. `core/paragraph`) →
Ablehnung, die Theme-Funktion liefert `false` → Ablehnung, die
Theme-Funktion ist **nicht** definiert → kein Fatal Error, Ablehnung und
Nichtexistenz sind zeichengleich, keine Antwort nennt Seitentitel oder
Permalink, eine Ausnahme beim Rendern führt zu 404 statt eines Fatal Errors,
und der globale `$post` wird in jedem Fall wiederhergestellt.

## Blockreferenz als Textformat und hierarchische Zielauswahl (seit 3.1.93)

Ergänzt den Abschnitt „Block-Referenz als Modul" oben um zwei Erweiterungen aus
dem Vorhaben `docs/PLAN-Inline-Blockreferenz.md`: Ein Verweis auf einen
Container-Block lässt sich jetzt auch **mitten im Text** setzen (Textformat,
kein eigener Block), und die Zielauswahl filtert an **beiden** Stellen —
Seitenleiste des Blocks und im neuen Dialog — nach der Seitenhierarchie statt
einer flachen, alphabetischen Liste. Beide Erweiterungen benutzen das
bestehende Modal aus `blocks/block-reference/view.js` unverändert mit.

### Die fünf Verträge (Kurzfassung)

| Vertrag | Gegenstand |
|---|---|
| **A** | `GET cbd/v1/blocks` bekommt drei zusätzliche Felder je Eintrag (`postParent`, `menuOrder`, `postType`); die acht bestehenden bleiben, die Antwort bleibt eine nackte Liste |
| **B** | neue Route `GET cbd/v1/seitenbaum` → `{knoten, kinder, wurzeln}`, alle veröffentlichten Seiten (keine Beiträge), rohes `$wpdb`, Breitensuche ab Wurzel 0 |
| **C** | `window.cbdBlockAuswahl` — der eine gemeinsame Auswahlbaustein für Seitenleiste und Dialog |
| **D** | gespeichertes Markup des Inline-Verweises: `<a class="cbd-block-reference-inline">` mit genau fünf Attributen |
| **E** | `CBD_Inline_Reference::inhalt_auffrischen()` auf `the_content`, Priorität 12 — frischt drei Attribute serverseitig auf |

Vollständiger Wortlaut mit Beispieldaten: `docs/PLAN-Inline-Blockreferenz.md`,
Abschnitt 7, „Die fünf Verträge".

#### Vertrag B in der Praxis: Baum ohne teure Abfragen

Der Baum wird mit fünf Spalten (`ID`, `post_parent`, `post_title`,
`menu_order`, `post_type`) geladen, **kein** `post_content`, und per
Breitensuche ab Wurzel `0` aufgebaut (Vorbild
`Theme/includes/page-index.php:206-229`) — das liefert `tiefe` ohne erneutes
Auflösen der Elternkette, lässt verwaiste Knoten samt Unterbaum herausfallen
und macht Zyklen unerreichbar. Eine Tiefenbegrenzung von 20 ist ein Schutz
gegen verstümmelte Daten, keine fachliche Aussage (gemessene Tiefe des
Projekts: 3–4 Ebenen). `knoten` und `kinder` werden **erst in
`get_seitenbaum()`**, nicht in der reinen Aufbaufunktion `baue_seitenbaum()`,
per `(object)` gecastet — ein PHP-Array mit den Schlüsseln `0..n-1` würde
sonst als JSON-**Liste** ausgegeben und vom Client stillschweigend verworfen
(AP-3.fix3, Befund S1); ein Cast in `baue_seitenbaum()` hätte dagegen rund 60
Bestandsprüfungen zerstört, die mit Array-Syntax auf das Ergebnis zugreifen.

**Seit dem Vorhaben „Seitenimporter-Kaskaden-Zielauswahl" (2026-08-25) kennt
die Route zusätzlich den optionalen Query-Parameter `entwuerfe`** (Wert
`'1'` schließt Seiten mit `post_status = 'draft'` zusätzlich zu `publish`
ein). Ohne den Parameter — oder mit jedem anderen Wert als `'1'` — bleibt
das Verhalten exakt wie zuvor: eine rein additive Opt-in-Erweiterung, die
Rückwärtskompatibilität zu `assets/js/block-auswahl.js`
(`window.cbdBlockAuswahl`), das dieselbe Route weiterhin ohne diesen
Parameter aufruft, bleibt zwingend gewahrt. Der Innerhalb-einer-Anfrage-Cache
`self::$seitenbaum_cache` ist seither **parameterabhängig**: ein
assoziatives Array mit den Schlüsseln `'ohne_entwuerfe'`/`'mit_entwuerfe'`
statt eines einzelnen `WP_REST_Response|null`-Slots — ein zweiter Aufruf mit
anderem Parameterwert innerhalb derselben Anfrage bekäme sonst das falsch
gecachte Ergebnis des ersten Aufrufs zurück. `seitenbaum_cache_vergessen()`
leert beide Schlüssel gemeinsam (`self::$seitenbaum_cache = array();`).
Konsumiert wird der Parameter aktuell vom Seitenimporter
(`assets/js/page-importer.js`, `GET cbd/v1/seitenbaum?entwuerfe=1`, siehe
Abschnitt „Seitenimport" oben, Unterabschnitt „Elternseite gilt für den
ganzen Lauf").

Das Feld `gesperrt` kommt aus einer **dreistufigen** Kette, jede Stufe hinter
`function_exists()`: zuerst `simple_clean_gesperrte_seiten_mit_unterbaum()`
(eine memoisierte Theme-Funktion, die **alle** gesperrten Seiten samt
Unterbaum in höchstens zwei Abfragen liefert), sonst
`simple_clean_seite_nur_lehrpersonen()` je Seite (Rückfall für ein älteres
Theme, in Wirklichkeit O(n) Abfragen über `get_post_ancestors()`), sonst
durchgehend `false`. Der Rückfall wurde nötig, weil die ursprüngliche Fassung
(AP-3.1) die zweite Stufe für den Regelfall hielt: Auf einer Installation mit
258 Seiten und mindestens einer gesperrten Seite entstehen dort bis zu
mehrere hundert Einzelabfragen, weil die rohe `$wpdb`-Abfrage den
WordPress-Post-Cache nicht füllt (AP-3.fix1). Gemessen in der Wirklichkeit:
**≤ 4 Abfragen** bei 260 Seiten über die bevorzugte Stufe, **1** beim ersten
Editor-Aufruf und **0** beim zweiten (Memoisierung in
`window.cbdBlockAuswahl`) — die neue Route ist damit **nicht teurer** als das
bestehende `cbd/v1/blocks` (gemessen: 0,285–0,377 s gegen 0,291–0,362 s).

#### Vertrag C: die eine Auswahl für beide Stellen

`assets/js/block-auswahl.js` lädt Vertrag A und B **parallel und
memoisiert** — mehrere Aufrufer teilen dasselbe Promise, ein Fehler ergibt
leere Datensätze statt einer Ablehnung. `wp.element` wird beim Laden der
Datei nicht berührt, nur innerhalb der Komponente `HierarchieAuswahl` — nur
so lässt sich die reine Logik ohne WordPress testen. Die Kaskade wächst
dynamisch (ein Auswahlfeld je Ebene, das nächste erscheint erst nach einer
Wahl) statt fester vier Felder oder eines Aufklapp-Baums — bei der gemessenen
Tiefe von 3–4 Ebenen wäre beides unpassend. Suchfeld und Kaskade teilen
**einen** Zustand: Ein Suchtreffer stellt die Auswahlfelder auf den Pfad des
Treffers. Gesperrte Zielseiten werden **gekennzeichnet, nicht ausgeblendet**
— ein Verweis auf eine Lehrpersonen-Seite ist von einer anderen
Lehrpersonen-Seite aus legitim.

**Bekannte, bewusst akzeptierte Grenze:** `ladeDaten()` hat keine Möglichkeit,
die Memoisierung zu verwerfen. Legt eine Redakteurin in einem zweiten Tab
einen Container-Block an, bleibt die Liste in der laufenden Editor-Sitzung
veraltet, bis die Seite neu geladen wird; scheitert der erste Abruf, bleibt
die Auswahl die ganze Sitzung leer. Eine achte Eigenschaft am Vertrag hätte
dessen AK1 verletzt („keine weiteren öffentlichen Namen"), deshalb bewusst
nicht ergänzt. Der Nutzer hat beide Fälle bei der Abnahme (AP-4.3)
ausdrücklich als hinnehmbar beurteilt.

**AP-3.fix4:** Ein gelöschter Zielblock zeigte in der Blockstufe eine
Ersatzoption „(gespeichertes Ziel)" — deren Anklicken das gespeicherte Ziel
gelöscht hätte (`melde(null)`, obwohl Vertrag C `null` nur „beim Abwählen"
vorsieht). Seit dem Fix erscheint diese Option nur noch, wenn der Eintrag
tatsächlich noch in `bloecke` existiert, aber außerhalb des aktuell
sichtbaren Pfads liegt.

### Warum `href`, `data-same-page` und `aria-haspopup` serverseitig gesetzt werden

Ein Textformat friert seine Attribute beim Bearbeiten ein; `render.php` (der
Block) rechnet dagegen bei jedem Aufruf neu. `CBD_Inline_Reference::inhalt_auffrischen()`
macht für das Textformat dasselbe, auf `the_content`, Priorität 12:

- **`href`** wird aus `get_permalink()` neu gebildet. Frisch berechnet
  übersteht der Verweis eine Slug-Änderung der Zielseite; ein beim Bearbeiten
  eingefrorener Wert würde verrotten. Liefert `get_permalink()` nichts,
  bleibt der gespeicherte Wert stehen (fortschreitende Verbesserung ist
  besser als ein leerer Link).
- **`data-same-page`** entscheidet, ob das Modal per DOM-Klon (keine Anfrage)
  oder per Nachladen befüllt wird. `CBD_Block_Organizer::copy_block()`
  vergibt beim Kopieren eines Blocks die `stableId` **nicht** neu — dieselbe
  Kennung kann also auf zwei Seiten liegen. Nur zum Renderzeitpunkt lässt
  sich zuverlässig sagen, auf welcher Seite der Verweis gerade **ausgegeben**
  wird; ein beim Speichern festgehaltener Wert zeigte nach dem Kopieren eines
  Absatzes auf den falschen Zwilling.
- **`aria-haspopup="dialog"`** steht nicht in der ARIA-Whitelist von
  `wp_kses_post()`. Bei der Abnahme **gemessen** (AP-4.3): Ein
  Block-Redakteur ohne `unfiltered_html` verliert das Attribut beim
  Speichern, wenn es dort stünde. Serverseitig gesetzt ist es immer da,
  unabhängig von der Rolle, die den Verweis zuletzt gespeichert hat.

Zum Vergleich, aus Abschnitt 10a dieses Plans korrigiert: Ein **camelCase**-Name
wie `data-targetStableId` würde von `wp_kses_post()` nicht entfernt, sondern
nur **kleingeschrieben** — deshalb sind alle Attributnamen in Vertrag D von
Anfang an durchgehend klein mit Bindestrichen geschrieben, nicht weil sie
sonst verloren gingen, sondern damit sie nicht unter einem anderen Namen
ankommen als dem gespeicherten.

### Warum `tagName: 'a'`, nicht `span`

Die Glossar-Autoverlinkung des Themes (`the_content`, Priorität 10000)
überspringt bestehende `<a>`-Elemente korrekt. Bei einem `<span>` würde sie
ein `<a class="glossar-term">` **hinein**setzen — Klick (Modal) und Tooltip
(Glossar) konkurrierten dann um denselben Text, und ohne JavaScript wäre der
Verweis gar kein Link. Ein `<a>` schützt den markierten Text also vor der
eigenen Glossar-Funktion des Projekts.

### Warum eine eigene CSS-Klasse (`cbd-block-reference-inline`)

Die Klasse des Blocks, `cbd-block-reference-link`, trägt `display: block`
samt Karten-Layout und einen `transform` beim Überfahren (`style.css:9-14`)
— mitten in einem Absatz zerrisse das den Textfluss. `registerFormatType`
kann pro Format nur eine Klasse setzen; ein unterscheidendes Attribut
zusätzlich zur Blockklasse wäre ein Umweg gewesen, kein Ersatz für eine
eigene Klasse. Der Inline-Verweis sieht deshalb wie ein gewöhnlicher Link aus
(`display: inline`, Unterstreichung) und trägt nur einen kleinen
`::after`-Pfeil (`\2197`, als `inline-block`, sonst zieht der Vorfahre seine
Unterstreichung nicht über das Symbol).

### Warum das View-Script aus dem Inhaltsfilter eingebunden wird

`block.json` deklariert `viewScript`, aber WordPress reiht ein `viewScript`
nur ein, wenn der **Block** auf der Seite steht. Eine Seite mit
ausschließlich Inline-Verweisen enthält den Block „Block-Referenz" nirgends
— ein solches Skript würde also nie geladen. `inhalt_auffrischen()` weiß
dagegen bereits, dass mindestens ein Verweis bearbeitet wurde, und reiht
`view.js` von dort aus ein, statt eine zweite Inhalts-Prüfung auf
`wp_enqueue_scripts` zu betreiben (die eine Fallunterscheidung nach
`is_singular()` bräuchte und Auszüge, Widgets und Archive verfehlte). Das
trägt **nur**, weil `view.js` mit `$in_footer = true` registriert ist und
`the_content` vor `wp_footer` läuft — im Code als Kommentar festgehalten,
damit eine künftige Umstellung auf `$in_footer = false` den Inline-Verweis
nicht stillschweigend lähmt. Läuft der Filter in einem Auszug oder Archiv,
wird `view.js` ebenfalls eingereiht, obwohl der Verweis dort abgeschnitten
sein kann — gemessen als harmlos (ein Footer-Script kostet dort nichts
Sichtbares).

### Der Link-Wächter prüft den Bereich, nicht `getActiveFormat()`

**Die wichtigste Warnung dieses Abschnitts.** `format.js` verhindert
verschachtelte `<a>`, indem es prüft, ob auf der Markierung bereits ein
`core/link` liegt. Die naheliegende Prüfung, `getActiveFormat(wert,
'core/link')`, schlägt dafür **nicht** aus: Sie liefert nur dann etwas, wenn
das Format die **ganze** Markierung überspannt. Liegt ein Link nur
**innerhalb** der Markierung (z. B. ein ganzer Satz mit einer verlinkten
Quellenangabe) oder überlappt er nur einen Rand, ist der Rückgabewert
`undefined`, der Dialog öffnet sich, und `applyFormat()` legt den
Inline-Verweis außen um den bestehenden Link.

Gemessen mit den echten WordPress-7.0.4-Bündeln (`rich-text.js`,
`escape-html.js`): Fall „Link innerhalb der Markierung" und „Markierung
überlappt den Linkrand" erzeugten je **ein** verschachteltes `<a>`, bestätigt
durch `WP_HTML_Processor::normalize()` als ungültiges HTML (Roundtrip
scheitert). Wer den Format-Editor öffnet, sieht danach „Block enthält
unerwarteten oder ungültigen Inhalt" — und weil der Schaden im bereits
**gespeicherten** `post_content` liegt, ist es der **einzige Fehler dieses
gesamten Vorhabens, der sich nicht durch ein Plugin-Update reparieren
lässt.** Betroffene Absätze müssten von Hand korrigiert werden.

Der Fix (`linkImBereich()`) prüft stattdessen **jedes Zeichen** der
Markierung auf ein `core/link`-Format:

```js
function linkImBereich(wert) {
    if (!wert || !wert.formats) { return false; }
    var von = bereich.start, bis = bereich.end;
    for (var i = von; i < bis; i++) {
        var f = wert.formats[i];
        if (!f) { continue; }
        for (var j = 0; j < f.length; j++) {
            if (f[j] && LINK_FORMAT === f[j].type) { return true; }
        }
    }
    return false;
}
```

Wer diese Bereichsprüfung zurück auf `getActiveFormat()` umbaut, öffnet die
Lücke wieder. Zwei gleichartige Inline-Verweise im selben Bereich erzeugen
weiterhin **keine** Verschachtelung (`applyFormat()` filtert den eigenen Typ
vorher heraus), und ein Cursor **innerhalb** eines bestehenden
Inline-Verweises kann ihn weiterhin über die Werkzeugleiste entfernen
(`removeFormat()` weitet eine zusammengefallene Auswahl auf den ganzen
zusammenhängenden Lauf aus).

### Die Klassenzeichenkette `cbd-block-reference-inline` steht an vier Stellen

`CBD_Inline_Reference::KLASSE`, `format.js` (als wirksamer Wert **und** in
zwei Docblocks), `view.js` (Klick-Selektor) und `style.css` (fünf
Selektoren). `tools/test-inline-reference.php`, Gruppe 11, hält sie zusammen
— geprüft wird dabei der **wirksame Ausdruck** (`var KLASSE = '<wert>';` in
`format.js`, der vollständige Klick-Selektor in `view.js`, `.` + `KLASSE` in
`style.css`), nicht das bloße Vorkommen der Zeichenkette. Das ist keine
Selbstverständlichkeit: Die erste Fassung des Wächters prüfte nur mit
`strpos()`, ob die Zeichenkette **irgendwo** in der Datei steht — das blieb
grün, selbst wenn nur der wirksame Wert mutiert wurde, weil die beiden
Docblock-Kommentare weiterhin trafen (AP-4.fix2, Befund B2), und `style.css`
war anfangs von **keinem** Wächter erfasst (Befund B3). Dass Gruppe 11 heute
wirklich anschlägt, ist selbst per Mutation geprüft (eine Kopie im Speicher
wird verändert, nie die Datei auf der Platte) — die Prüfung ihres eigenen
Anschlagens ist Teil des Bestands, keine einmalige Anekdote.

### Die Grenzen des Formats

Die Schaltfläche neben dem Link-Knopf erscheint nicht in jedem Block. Gegen
den Gutenberg-Quelltext von WordPress 7.0.4 geprüft (AP-4.2): sichtbar in
`core/paragraph`, `core/heading`, `core/list-item`, in Tabellenzellen und in
der geteilten `Caption`-Komponente (gilt damit auch für Bild- und
Medien-Unterschriften). **Nicht** sichtbar in `core/button` und rund
fünfzehn weiteren Blöcken — Ursache ist `withoutInteractiveFormatting` bzw.
ein eingeschränktes `allowedFormats`-Array; `withoutInteractiveFormatting`
filtert jedes Format heraus, dessen `tagName` in `interactiveContentTags`
steht, und `a` gehört dazu. Das ist eine Eigenschaft von Gutenberg selbst,
kein Befund dieses Vorhabens.

### Prüfharnische

| Datei | Prüfungen | Schwerpunkt |
|---|---|---|
| `tools/test-seitenbaum.php` | **97** | Vertrag A + B: Baumaufbau, Sortierung, Tiefe, Zyklen, verwaiste Knoten, Beiträge außerhalb des Baums, JSON-Objektform, `gesperrt`-Kette, Abfragenzahl |
| `tools/test-block-auswahl.js` | **140** | Vertrag C: die sieben öffentlichen Namen und kein achter, Memoisierung, Kaskade mit Beschneidung, Zielverlust-Schutz (AP-3.fix4), reine Logik ohne `wp.element` beim Laden |
| `tools/test-inline-reference.php` | **181** (177 im „Doppel"-Betrieb gegen ein schmales `WP_HTML_Tag_Processor`-Double, 4 sichtbare Skips dort) | Vertrag D + E: Zeichengleichheit ohne Verweis, überlange Ziffernfolgen ohne PHP-Warnung, führende Nullen, Duplikatswächter Gruppe 11 |

### Öffentliche `window.cbd*`-Schnittstellen

`window.cbdBlockAuswahl` ist die **fünfte** öffentliche `window.cbd*`-Schnittstelle
des Plugins — und die **erste, die für den Editor gilt**. Die anderen vier
sind Frontend:

| Name | Datei | Geltungsbereich | Kurzbeschreibung |
|---|---|---|---|
| `cbdRenderLatex(root)` | `assets/js/latex-renderer.js` | Frontend | rendert `.cbd-latex-formula` in `root` nach, `Promise<number>` |
| `cbdPDFExportServerSide(elemente, modus)` | `assets/js/interactivity-store.js` | Frontend | serverseitiger Einzelblock-PDF-Export (u. a. Apple-Weiche) |
| `cbdPrepareFormulasForPDF(element)` | `assets/js/latex-renderer.js` | Frontend | bereitet gerenderte Formeln für den PDF-Export vor |
| `cbdRefreshDynamicStyles()` | `includes/class-cbd-style-loader.php` (Inline-Skript der Live-Vorschau) | Frontend/Live-Vorschau | erzeugt das dynamische Block-CSS neu, wenn sich die Blockzahl ändert |
| `cbdBlockAuswahl` | `assets/js/block-auswahl.js` | **Editor** | hierarchische Zielauswahl (Vertrag C), sieben Namen, s. o. |

## Screenshot auf Apple-Geräten (seit 3.1.89)

Auf iOS, iPadOS und macOS-Safari wird der Screenshot-Knopf eines
Container-Blocks zum **Einzelblock-PDF-Knopf**. Grund: Der Screenshot-Weg
(`html2canvas` + `navigator.clipboard.write()`/Web-Share) ist auf diesen
Geräten strukturell unzuverlässig — Safari verlangt den Aufruf von
`clipboard.write()` innerhalb derselben Nutzer-Aktivierung, die durch das
vorgelagerte `await html2canvas(...)` verloren geht, iOS begrenzt zusätzlich
die nutzbare Canvas-Fläche, und `<a download>` mit Data-URL wird von
iOS-Safari ignoriert. Diese Ursachen liegen außerhalb der Kontrolle des
Plugins und werden **nicht** repariert, sondern umgangen.

Umgangen wird über den bereits vorhandenen serverseitigen
Einzelblock-PDF-Export (`window.cbdPDFExportServerSide([...], 'visual')`,
`assets/js/interactivity-store.js`, sonst identisch zum regulären
PDF-Knopf): Er kommt für reine DOM-Blöcke ohne `html2canvas` aus und trifft
damit keine der genannten iOS-Klippen. Nutzer verlieren dadurch keine
Funktion — sie bekommen dieselbe Datei über einen anderen Weg.

### Erkennung: `istAppleGeraet()`

In `assets/js/interactivity-store.js` (und identisch nachgezogen in
`assets/js/interactivity-fallback.js`), einmal pro Seitenaufruf berechnet:

```js
function istAppleGeraet() {
	// iOS/iPadOS
	const isIOSDevice = /iPad|iPhone|iPod/.test(ua) ||
		(navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
	if (isIOSDevice) { return true; }

	// macOS-Safari
	return vendor.indexOf('Apple') !== -1 &&
		ua.indexOf('Safari') !== -1 &&
		ua.indexOf('Chrome') === -1 && ua.indexOf('Chromium') === -1 &&
		ua.indexOf('Edg') === -1 && ua.indexOf('OPR') === -1;
}
```

Auf Apple-Geräten schaltet `callbacks.onInit` jeden vorhandenen
`.cbd-screenshot`-Knopf beim Initialisieren optisch um (Dashicon
`dashicons-camera` → `dashicons-pdf`, `title`/`aria-label` → „Diesen Block
als PDF speichern", Attribut `data-cbd-apple-pdf="1"`), und
`actions.createScreenshot` leitet **vor jeder Berührung von `html2canvas`**
auf den PDF-Export um. Fehlt `window.cbdPDFExportServerSide` (z. B. weil das
Skript aus irgendeinem Grund nicht geladen wurde), versteckt der Code den
Knopf per `display: none !important` und gibt einmalig `console.warn` aus —
ein Knopf ohne Funktion gilt als schlechter als gar kein Knopf.

**Die Erkennung schließt macOS-Safari ein — anders als die ältere Erkennung
in `assets/js/pdf-server-side.js`:**

```js
/iPad|iPhone|iPod/.test(navigator.userAgent) ||
  (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1)
```

Dieses Muster (unverändert weiter in Gebrauch für den Canvas-Flächendeckel
in `createScreenshot()`) erkennt iOS und iPadOS, aber **keinen** Mac ohne
Touchscreen — ein Nutzer auf echtem macOS-Safari fiele durch dieses Raster.
`istAppleGeraet()` ergänzt deshalb die zweite, unabhängige Bedingung über
`navigator.vendor`/`navigator.userAgent`. Beide Erkennungen bestehen bewusst
nebeneinander: `pdf-server-side.js` betrifft ausschließlich die
Canvas-Flächenbegrenzung von `html2canvas` (dort ist macOS-Safari
irrelevant, weil `html2canvas` dort ohnehin funktioniert), `istAppleGeraet()`
entscheidet über die Knopf-Umschaltung insgesamt.

### Warum die Umschaltung clientseitig geschieht

Der Knopf selbst wird serverseitig erzeugt
(`class-cbd-block-registration.php`, gebunden an das Feature-Flag
`screenshot`) — **diese Datei bleibt in diesem Vorhaben unverändert.** Eine
Apple-Erkennung im gerenderten HTML hätte die Seitenausgabe vom User-Agent
abhängig gemacht und damit jeden Full-Page-Cache vergiftet: Ein und dieselbe
zwischengespeicherte Seite müsste dann für iPhone- und Windows-Besucher
unterschiedlich aussehen, was ein HTML-Cache nicht abbilden kann. Das
Plugin führt an anderer Stelle bereits eigene Cache-Logik
(`class-cbd-block-registration.php`); ein zweiter, User-Agent-abhängiger
Cache-Sonderfall dort wäre eine vermeidbare Fehlerquelle.

Der Knopf folgt weiterhin ausschließlich seinem Feature-Flag: Ist
„Screenshot" für ein Design abgeschaltet, existiert gar kein Knopf — weder
Screenshot- noch PDF-Symbol. Das entspricht der Projektentscheidung „Buttons
folgen Feature-Flags" (`docs/VERBESSERUNGSPLAN.md`, AP12).

## Darkmode (Phase 2 von `PLAN-Darkmode-Umschaltung.md`, abgeschlossen 2026-08-24)

Der Umschalt-Mechanismus selbst gehört dem Theme, nicht diesem Plugin:
Ein einziges Attribut `data-theme="dark"` auf `<html>`, gesetzt per
Toggle-Button und FOUC-Vermeidungsscript in `Theme/header.php`, rein
nutzergesteuert (**kein** `matchMedia`/`prefers-color-scheme`-Bezug).
Details, Persistenz (`localStorage`) und die zugehörigen CSS-Variablen:
`Theme/CLAUDE.md`, Abschnitt „Darkmode".

Phase 2 dieses Vorhabens hat die bis dahin noch systemabhängigen
`@media (prefers-color-scheme: dark)`-Blöcke im Frontend-Code dieses
Plugins auf den expliziten Toggle umgestellt:

| Datei | Umstellung |
|---|---|
| `assets/css/cbd-frontend-clean.css` | `@media (prefers-color-scheme: dark)` für `.cbd-container-block` → `[data-theme="dark"] .cbd-container-block`. Der Live-Test deckte dabei drei Kaskade-Bugs auf (Inline-Style ohne `!important`, eine aktive `transition` mit Vorrang selbst vor `!important`, eine Variablen-Kollision bei `--color-sidebar-border`) — Details in `reference_file_map.md` bei dieser Datei |
| `assets/css/latex-formulas.css` | `@media (prefers-color-scheme: dark)` für `.cbd-latex-fallback`/`.cbd-latex-error` → je eine eigene `[data-theme="dark"] …`-Regel. Dieselbe Variablen-Kollision wie oben, gefunden und behoben bei `.cbd-latex-fallback` |
| `assets/js/floating-pdf-button.js` | keine Selektor-Umstellung (Datei stylt inline, kein `@media`), aber ein bei der Sichtprüfung gefundener Kontrastfehler behoben: weiße Text-/Icon-Farbe auf der orangen Plastikfläche kam aus `--color-background` (im Darkmode dunkel) statt aus `--color-text-on-accent` (in beiden Modi `#ffffff`) |

`assets/css/frontend-consolidated.css` enthält weiterhin einen
`@media (prefers-color-scheme: dark)`-Block und wurde **bewusst nicht**
umgestellt: Der zugehörige Style-Handle wird laut AP-2.1 ausschließlich
über `enqueue_editor_styles()` am rein backendseitigen Hook
`enqueue_block_editor_assets` registriert und im öffentlichen Frontend
nie ausgeliefert (empirisch bestätigt, 0 Treffer im Seitenquelltext einer
echten Frontend-Seite) — Admin-/Editor-Oberflächen sind laut Plan
ausdrücklich außerhalb des Scopes.

**Pflicht-Konvention für neuen CSS-Code in diesem Plugin:** Neue CSS-Regeln,
die sich je nach Farbmodus unterscheiden sollen, verwenden
`[data-theme="dark"] .selektor` statt `@media (prefers-color-scheme: dark)`
– Letzteres ist für das Frontend dieses Projekts nicht mehr zulässig. Neuer
CSS-Code generell verwendet ausschließlich `var(--x, #fallback)`, nie
hartcodierte Hex-Werte.

**Behoben durch `docs/PLAN-PDF-Export-und-Tafelmodus-Fixes.md`
(abgeschlossen 2026-08-25):** Der hier vormals als offen geführte
Nebenbefund zu `.cbd-board-confirm-cancel:hover` ist korrigiert — siehe
den eigenen Abschnitt „Tafelmodus im Darkmode" weiter unten für den
vollständigen, seither darkmode-fähigen Zustand von `board-mode.css`/
`board-mode.js`.

### Tafelmodus im Darkmode (`docs/PLAN-PDF-Export-und-Tafelmodus-Fixes.md`,
abgeschlossen 2026-08-25)

`board-mode.css` war beim ursprünglichen Umbau auf den manuellen
Darkmode-Umschalter (oben) bewusst ausgenommen und blieb bis zu diesem
Vorhaben durchgehend hell. Seither passen sich Kopfzeile, Inhalt,
Werkzeugleiste (inkl. aller Buttons, Seiten-/Zoom-Anzeigen, Undo,
Werkzeugauswahl) und der — aktuell ungenutzte — Farbauswahl-Dialog über
`[data-theme="dark"] .selektor`-Regeln an; keine `@media
(prefers-color-scheme: dark)`-Blöcke ergänzt. `.cbd-board-header` ist
bewusst **fest** `#333333`/`#ffffff` in beiden Modi (kein `var()`-Bezug,
siehe Kommentar im CSS) — die ursprünglich verwendeten Variablen
`--color-text-primary`/`--color-background` kehren sich im Darkmode um und
hätten die Kopfleiste fälschlich hell gemacht.

**Automatische Invertierung der Zeichenfläche:** Eine „Eigene Notiz" ist
eine Rastergrafik (`<canvas>`-Ebenen). Im Darkmode invertiert die
Zeichenfläche automatisch per CSS-Filter (`filter: invert(1)` auf
`.cbd-board-canvas-container`, gesteuert von
`board-mode.js::updateDarkModeInversion()`) — **aber nur**, wenn die Tafel
auf der weißen Standardfarbe steht (`this.boardColor === '#ffffff'`). Eine
bewusst gewählte Grün- oder Schwarz-Tafel bleibt unangetastet.

**Bewusst akzeptierte Nebenwirkung, vom Nutzer nach Rückfrage bestätigt
(2026-08-25):** Der Filter ist eine reine Farbinvertierung ohne
Hue-Erhalt (z. B. Rot→Cyan, Blau→Gelb) und wirkt auf **alle** Ebenen des
Wrappers gemeinsam — Hintergrund, Gitter **und** Zeichnung, nicht nur die
eigentlich riskanten Schwarz-/Weiß-Töne. Die Stiftfarbpalette
(`.cbd-board-preset-colors`) sitzt strukturell außerhalb von
`.cbd-board-canvas-container` (in der Werkzeugleiste) und wird deshalb
selbst **nie** invertiert: Sie zeigt weiterhin die echte, gewählte Farbe,
während ein damit gezeichneter Strich auf der Tafel invertiert erscheint —
Palette-Vorschau und tatsächliches Zeichenergebnis laufen bei farbigen
Stiften (nicht bei Schwarz/Weiß) auseinander. Bei der Rückfrage wurden
zwei Alternativen erwogen (nur Hintergrund/Gitter invertieren — bricht dann
aber den eigentlichen Schwarz-auf-Schwarz-Kernfall, den dieses Feature
lösen soll; Farbpalette im Darkmode auf Schwarz/Weiß einschränken) und
bewusst verworfen zugunsten des bestehenden, einfacheren Verhaltens. Der
Filter wirkt rein auf die Darstellung; die in `localStorage`/serverseitig
gespeicherten Pixel bleiben unverändert, betrifft also gleichermaßen neu
gezeichnete wie bereits vorher gespeicherte Notizen.

**Bekannte, nicht behobene Einschränkung:** Der Darkmode-Toggle des Themes
(`Theme/header.php`) setzt `data-theme` ohne Seiten-Reload. Die reinen
CSS-Regeln oben reagieren dadurch live, die JS-gesteuerte
Invertierungsklasse aber nicht — wird der Toggle bei bereits offenem
Tafelmodus geklickt, greift die Notiz-Invertierung erst beim nächsten
Öffnen des Tafelmodus oder Farbwechsel. Bewusst nicht behoben, um den
Scope nicht zu sprengen (ein zusätzlicher Event-Listener auf den Toggle
wäre nötig).

## PDF-Export: Tafelbilder und eigene Notizen (Phase 2 von
`docs/archiv/PLAN-PDF-Notizen-und-Listenformeln.md`, abgeschlossen 2026-08-24)

Der serverseitige PDF-Export (`assets/js/pdf-server-side.js`) schließt neben
den bereits bestehenden lokalen „Eigenen Notizen" (`localStorage`) seit
diesem Vorhaben auch klassenweit **serverseitig** gespeicherte Tafelbilder
ein — steuerbar über einen einzigen Schalter im Export-Dialog.

### Mechanismus: Begleitschlüssel statt aktiver Sitzung

Für den PDF-Export muss bekannt sein, welcher Klasse ein Container zuletzt
zugeordnet war — eine exportierende Lehrperson ist dabei aber normalerweise
regulär angemeldet (`cbd_edit_blocks`), nicht in einer Schüler-Token-Sitzung,
aus der sich die Klasse ableiten ließe. Deshalb schreibt `board-mode.js`
(AP-2.2) bei jedem erfolgreichen `saveToServer()`/`loadFromServer()`
zusätzlich einen lokalen Begleitschlüssel

```
localStorage['cbd-board-' + containerId + '-classid'] = classId
```

— exakt nach demselben, bereits im Projekt etablierten Muster wie der
bestehende Begleitschlüssel `cbd-board-{id}-bgcolor`. **Wichtig beim
Schreiben:** Der Schlüsselname und der Wert müssen **synchron vor dem
`fetch()`-Aufruf** erfasst werden, nicht erst im `.then()`-Erfolgszweig —
`close()` setzt `this.classId` synchron auf `null`, sobald `saveDrawing()`/
`loadDrawing()` aufgerufen wurde, während die Anfrage noch läuft. Ein Lesen
im `.then()` hätte reproduzierbar die Zeichenkette `"null"` statt der
tatsächlichen Klassen-ID geschrieben (im Live-Test gefunden und korrigiert,
bevor AP-2.2 abgeschlossen wurde) — derselbe Grund, aus dem der bestehende
`bgcolor`-Schlüssel bereits synchron erfasst wird.

### Datenweg beim Export

1. `assets/js/floating-pdf-button.js` (AP-2.4): Checkbox
   `.cbd-pdf-drawings-check` im Export-Dialog, Default angehakt. Ihr Wert
   geht als 4. Parameter `includeDrawings` an
   `window.cbdPDFExportServerSide(containerBlocks, mode, quality,
   includeDrawings)`.
2. `assets/js/pdf-server-side.js` (AP-2.3): `includeDrawings` (Default
   `true` bei `undefined` — bestehende Aufrufer ohne den Parameter, z. B.
   die Apple-PDF-Weiche in `interactivity-store.js`, siehe Abschnitt
   „Screenshot auf Apple-Geräten", verhalten sich dadurch unverändert wie
   vor dieser Erweiterung) wird bis `processOneBlock()` durchgereicht und
   umschließt dort sowohl den bestehenden Aufruf von
   `injectDrawingsFromStorage()` (lokale Notizen) als auch den neuen
   `injectServerDrawings()`/`applyServerDrawings()`. Letztere liest je
   Container den Begleitschlüssel `cbd-board-<stableId>-classid` und lädt
   darüber – **gebündelt, ein AJAX-Aufruf je eindeutiger `class_id`, nicht
   je Container** (exportlaufweiter Cache `serverDrawingsCache`, da
   `processOneBlock()` pro ausgewähltem Container einzeln läuft) – die
   passenden Tafelbilder nach. Eingefügte Server-Bilder tragen im PDF das
   Label „Tafelbild", lokale weiterhin „Eigene Notiz".
3. `includes/class-cbd-classroom.php` (AP-2.1): neuer AJAX-Handler
   `ajax_get_page_drawings()` (Action `cbd_get_page_drawings`) liefert für
   eine `page_id` + `class_id` **alle** serverseitig gespeicherten
   Tafelbilder der Seite in einem einzigen Aufruf (Bulk statt eines
   Requests je Container — vermeidet N+1 bei Seiten mit vielen
   Containern). **Sicherheitskritisch:** `can_access_class($class_id)` wird
   nachweislich **vor** jeder Datenabfrage geprüft, zusätzlich zu Nonce
   (`cbd_classroom_nonce`) und Capability `cbd_edit_blocks` — dasselbe
   Muster wie die bestehende `ajax_load_drawing()`, bewusst **nicht** das
   Token-Modell von `ajax_get_page_classroom_data()` (Schüler-Sitzungen),
   da eine exportierende Lehrperson regulär angemeldet ist. Container ohne
   Zeichnung (`drawing_data IS NULL`) werden serverseitig gefiltert.

### Der Zusatzfund: zwei `cbdPDFData`-Localize-Stellen

`cbdPDFData` wird an **zwei unabhängigen** Stellen lokalisiert:
`class-cbd-classroom.php` (nur auf Seiten mit `[cbd_classroom]`-Shortcode,
enthält `pageId`/`classroomNonce`) und `class-cbd-block-registration.php`
(der Normalfall — jede gewöhnliche Seite mit Container-Block, ohne
Klassen-Shortcode; liefert dort nur `ajaxurl`/`resturl`/`nonce`/`restnonce`,
**kein** `pageId`/`classroomNonce`). Ohne Gegenmaßnahme lief
`injectServerDrawings()` auf einer gewöhnlichen Seite mit `page_id: 0` und
leerem Nonce gegen HTTP 403. Da `class-cbd-block-registration.php` außerhalb
des AP-Scopes lag, liest `pdf-server-side.js` stattdessen mit Rückfall:
`cbdPDFData.pageId || window.cbdClassroomData.pageId` (analog für den
Nonce) — `window.cbdClassroomData` wird bereits unverändert von
`class-cbd-block-registration.php` im `wp_footer` gesetzt, sobald
`cbd_edit_blocks` + eingeloggt.

### Direktdownload und Bildfehler-Diagnose (AP-1.3/AP-1.fix3, 2026-08-25)

`CBD_PDF_Generator::ensure_download_htaccess()` legt beim ersten Export in
`wp-content/uploads/cbd-temp-pdfs/` automatisch eine `.htaccess` mit
`Content-Disposition: attachment` für `*.pdf` an (idempotent, überlebt
Verzeichnis-Neuerstellung) — eine defensive Absicherung zusätzlich zur
bereits korrekten `<a download>`-Technik in `downloadPDF()`
(`pdf-server-side.js`). **Kann eine im Browser des Nutzers aktivierte
Einstellung „Vor jedem Download nachfragen, wo die Datei gespeichert
werden soll" nicht übersteuern** — das ist eine Browser-Entscheidung, keine
per HTTP-Header oder JavaScript aufhebbare.

`$mpdf->showImageErrors` ist an `WP_DEBUG` gekoppelt
(`if (defined('WP_DEBUG') && WP_DEBUG)`, siehe Abschnitt
„Debugging-Konventionen" unten), **nicht** dauerhaft aktiv: mPDF wirft bei
aktivem Flag bei jedem nicht dekodierbaren Bild eine `MpdfImageException`,
die den **gesamten** Export abbricht statt nur das eine Bild durch einen
stillen Platzhalter zu ersetzen. In Produktivumgebungen (WP_DEBUG i. d. R.
aus) bleibt deshalb der alte, sichere Rückfall erhalten; mit aktivem
WP_DEBUG steht die laute Diagnose für künftige Fehlersuche weiter zur
Verfügung — genau darüber wurde die Ursache oben (Einschränkung 3) erst
gefunden.

### Bekannte, bewusst akzeptierte Einschränkungen

1. **Ein Container mit Tafelbildern für MEHRERE Klassen exportiert nur die
   zuletzt genutzte Klassen-Zuordnung.** Der `-classid`-Begleitschlüssel
   hält je Container nur einen einzigen, zuletzt geschriebenen Wert — nicht
   eine Historie aller je genutzten Klassen. Wurde ein Container also
   nacheinander mit Tafelbildern für Klasse A und Klasse B bespielt, zeigt
   der Schlüssel nur noch auf B; ein PDF-Export liefert dann ausschließlich
   das Tafelbild der zuletzt aktiven Klasse, auch wenn für A weiterhin eins
   in der Datenbank liegt. Dieses Risiko steht bewusst akzeptiert im
   Risiko-Register des Plans (Abschnitt 5): kein Datenverlust, nur ein im
   Einzelfall fehlendes/falsches Bild im PDF, kein Absturz. Eine Historie
   je Klasse wäre eine deutlich größere Änderung (mehrere Begleitschlüssel
   oder ein Objekt-Wert) und war nicht Ziel dieses Vorhabens.
2. **Der `-classid`-Begleitschlüssel deckt nur die Hauptseite (Seite 0)
   eines Containers ab, nicht `:pN`-Zusatzseiten mehrseitiger Tafelbilder**
   (Format `<stableId>:pN`, siehe `zerlege_container_id()` in
   `class-cbd-classroom.php`). `board-mode.js` schreibt den Schlüssel unter
   `pageContainerId` (`getPageContainerId()`), `pdf-server-side.js` liest
   ihn aber nur unter dem reinen `stableId`. In der Praxis unkritisch, da
   Seite 0 eines mehrseitigen Tafelbilds immer zuerst bespielt wird; ist bei
   einem Container ausschließlich eine Zusatzseite bespielt, fehlt der
   Begleitschlüssel und es wird kein Server-Tafelbild eingefügt (bereits in
   der AP-2.2-Übergabenotiz dokumentierte Grenze). Ein künftiges AP, das
   alle Seiten abdecken will, müsste zusätzlich nach `:pN`-Suffix-Schlüsseln
   suchen.
3. **Behoben durch `docs/PLAN-PDF-Export-und-Tafelmodus-Fixes.md`
   (abgeschlossen 2026-08-25).** Der hier ursprünglich fehlende visuelle
   Ende-zu-Ende-Test einer echten PDF-Datei wurde in AP-1.1 nachgeholt und
   deckte die tatsächliche Ursache auf: mPDF konnte die eingebetteten
   `data:image`-Notizen/-Tafelbilder nicht dekodieren, weil
   `wp_kses_post()` das `data:`-Protokollpräfix aus dem Bild-URI entfernt
   (`wp_allowed_protocols()` enthält `data:` nicht) — sichtbar nur als
   mPDFs eigenes 14×16-Fehler-Platzhalterbild, ganz ohne Log-Ausgabe.
   AP-1.2 behebt das über eine neue Methode
   `CBD_Ajax_Handler::sanitize_pdf_block_html()`, die `data:image`-URIs vor
   `wp_kses_post()` per Platzhalter-Token maskiert und danach wiederherstellt
   (Muster wie `class-latex-parser.php::mask_protected_regions()`), plus
   zwei Nebenfunde: verlustbehaftete JPEG-Rekompression zerstörte
   Transparenz in Notizen-PNGs (jetzt `image/png` statt `image/jpeg` für
   Zeichnungen), und `collectCSSVariables()`/`replace_css_variables()`
   lasen falsch benannte CSS-Variablen. **Zusätzlich (AP-1.fix1, nach
   Nutzer-Feedback):** Ein PDF-Export bildet den Website-Darkmode
   grundsätzlich **nicht** ab — unabhängig vom angezeigten Website-Modus
   erscheint das PDF immer im Hellmodus-Farbschema
   (`collectCSSVariables()` liest `data-theme="dark"` temporär ab). Details:
   `docs/PLAN-PDF-Export-und-Tafelmodus-Fixes.md`, AP-1.1/AP-1.2/AP-1.fix1.

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

5. **LaTeX Parser Integration:** ~~LaTeX formulas parsed in `CBD_Block_Registration::render_block()` at line 850-853~~ — **überholt seit 3.1.88.** Das Parsen läuft nicht mehr im Block-Renderer, sondern global über zwei Filter, die `CBD_LaTeX_Parser` in seinem Konstruktor registriert: `render_block` (Priorität 5) für jeden Block und `the_content` (Priorität 11) als Sicherheitsnetz. `class-cbd-block-registration.php` sagt das an der alten Fundstelle inzwischen selbst („LaTeX parsing is now handled globally via render_block filter"). Vollständige Beschreibung im Abschnitt **„LaTeX-Formeln: Renderpfad und Wiederholrendern"**.

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

- **`reference_file_map.md`** — Datei-Map des Plugins: welche Datei wofür
  zuständig ist, inklusive der drei toten CSS-Dateien und der Hinweise zum
  ZIP-Bau. Navigationshilfe; die fachlichen Details stehen in dieser Datei.

- **`docs/VERBESSERUNGSPLAN.md` bis `-4.md`** — Review-Runden 2026-07 über das
  gesamte Website-Projekt (CDB, Modular-Plugin, Theme): 42 Arbeitspakete mit
  Problem, Fundstelle, Lösung, Verifikation und Erledigt-Status; inkl.
  dokumentierter Entscheidungen (Buttons folgen Feature-Flags, PHP 7.4 bleibt,
  keine CDN-Einbindungen).
- **`docs/AENDERUNGEN-UND-UPLOAD.md`** — verständliche Gesamtübersicht der
  Änderungen + Upload-/Recovery-Anleitung.
- **`docs/archiv/`** — historische Status-/Implementierungsnotizen früherer
  Entwicklungsphasen (nicht mehr gepflegt, teils veraltet — siehe README dort).
