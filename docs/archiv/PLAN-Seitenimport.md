# Projektplan: Seitenimport aus Markdown + Bulk-Optionen im Seitenmanager

— **abgeschlossen am 2026-08-10** (mit drei offenen Review-APs, siehe Abschnitt 11)

_Erstellt am: 2026-08-10 · Letzte Aktualisierung: 2026-08-10_

Zugehörige Analyse: `Plugins/CDB-Designer/docs/ERWEITERUNGSANALYSE-Seitenimport.md`

## 0. Anweisungen für den ausführenden Agenten

Du arbeitest nach diesem Plan. Er ist die einzige Wahrheitsquelle – du hast
keinen Zugriff auf das Gespräch, in dem er entstand. Halte dich an diese Regeln:

**Rollen und Modelle:**
A. Wird die Abarbeitung von einem Orchestrator koordiniert (Opus), gilt:
   Der Orchestrator delegiert APs an Subagenten und implementiert NIEMALS
   selbst. Er gibt jedem Subagenten nur dessen AP-Text plus die Abschnitte
   0–5 dieses Plans als Kontext, prüft jede Rückmeldung gegen die
   Akzeptanzkriterien des APs, bevor er abhängige APs freigibt, und pflegt
   die Statustabelle.
B. Jedes AP nennt sein Ausführungsmodell (**Modell:** sonnet | opus).
   Subagenten mit genau diesem Modell starten.
C. Dieser Plan hat **zwei Spuren**: Spur A (Phasen 1, 2 – Plugin
   CDB-Designer) und Spur B (Phase 3 – Theme). Die Spuren teilen keine
   Datei und kein Repository und laufen von Anfang an parallel. **Innerhalb**
   einer Spur werden die APs sequenziell abgearbeitet, mit zwei Ausnahmen,
   die im AP ausdrücklich vermerkt sind (AP-2.5 und AP-3.3).
   APs, die dieselben Dateien ändern, nie parallel ausführen.

**Arbeitsweise:**
1. Bearbeite genau EIN Arbeitspaket (AP) pro Auftrag, sofern nicht anders beauftragt.
2. Prüfe vor Beginn die Abhängigkeiten deines APs in der Statustabelle
   (Abschnitt 8). Sind sie nicht ☑, brich ab und melde das.
3. Setze deinen AP-Status auf ◐ (in Arbeit), bevor du beginnst.
4. Bleibe strikt im Scope des APs. Fällt dir Verbesserungspotenzial außerhalb
   auf, notiere es in der Übergabenotiz – setze es nicht um.
5. Beachte die Nicht-Ziele (Abschnitt 2) und Constraints (Abschnitt 3).

**Tests (Pflicht, ein AP ohne bestandene Tests ist nicht fertig):**
6. Nach Abschluss: alle Akzeptanzkriterien einzeln nachweisen + die im AP
   definierten Tests durchführen.
7. Sieht das AP TDD vor: Tests zuerst schreiben, Fehlschlag bestätigen,
   rote Tests committen, dann implementieren bis grün. **Tests niemals
   abändern, damit sie bestehen.** Hältst du einen Test für inhaltlich
   falsch, dokumentiere das in der Übergabenotiz und stoppe – die
   Entscheidung liegt beim Nutzer/Orchestrator.
8. Ergebnis ins Testprotokoll (Abschnitt 9) eintragen.
9. Erst dann Status auf ☑. Bei Fehlschlag: Status ✗ (blockiert), Ursache in
   die Übergabenotiz, nicht mit abhängigen APs weitermachen.
10. Nach dem letzten Implementierungs-AP einer Phase zusätzlich:
    Integrationstest der Phase + Regressionscheck aller vorherigen Phasen
    (deren „lauffähiger Endzustand" muss weiterhin funktionieren).
    Eintrag ins Testprotokoll.
11. Danach folgt das Review-AP (`AP-<N>.rev`): Es wird von einem frischen
    Agenten ausgeführt, der KEINES der APs dieser Phase implementiert hat.
    Der Review-Agent arbeitet ausschließlich lesend und verändert keine
    Datei. Kritische Befunde führen zu Korrektur-APs (siehe Regel 16);
    die Phase ist erst danach abgeschlossen.

**PHP-Prüfung (gilt für jedes AP, das PHP-Dateien anfasst):**
12. Vor dem Commit im Plugin CDB-Designer:
    `php -l <geänderte Datei>` für jede geänderte Datei UND
    `php tools/check-php74.php` (prüft alle Plugin-Dateien gegen PHP 7.4 —
    lokal läuft PHP 8.x, und `php -l` meldet 8.0-Syntax NICHT als Fehler,
    die Zielumgebung ist aber PHP 7.4.33).
    Im Theme: `php -l` für jede geänderte PHP-Datei.

**Übergabe:**
13. Fülle die Übergabenotiz deines APs aus: was geändert wurde, getroffene
    Entscheidungen, was für Folge-APs relevant ist.
14. Hat dein AP Dateien angelegt, verschoben oder wesentlich geändert:
    aktualisiere deren Zeilen in der Datei-Map der jeweiligen Komponente
    (`Theme/reference_file_map.md` bzw. – ab AP-4.1 –
    `Plugins/CDB-Designer/reference_file_map.md`). Existiert die Datei-Map
    für CDB-Designer noch nicht, notiere die Zeilen stattdessen in deiner
    Übergabenotiz; AP-4.1 trägt sie zusammen.
15. Aktualisiere „Letzte Aktualisierung" im Dateikopf dieses Plans.
16. Git: mindestens ein Commit mit AP-ID im Text, z. B.
    `AP-1.2: Referenzmarkup erhoben`. Nach jedem abgeschlossenen AP den
    Phasen-Branch zum Remote pushen (`git push -u origin <branch>`).
    Phasen-Branches erst nach bestandenem Integrationstest UND Review in
    `main` mergen, danach ebenfalls pushen.

**Umplanung:**
17. Zeigt sich während der Ausführung, dass der Plan nicht trägt (Review-
    Befunde, blockierte APs, falsche Annahmen), werden Korrektur-APs mit
    fortlaufender Nummer ergänzt (`AP-<N>.fix1`, …) und in Statustabelle
    und Testprotokoll aufgenommen. Bestehende APs und Übergabenotizen
    werden nie gelöscht, nur ergänzt – der Plan bleibt nachvollziehbare
    Historie.

## 1. Projektziel

1. **Seitenimporter:** Aus mehreren Markdown-Dateien entsteht je Datei **eine**
   WordPress-Seite im Status *Entwurf* auf der obersten Ebene
   (`post_parent = 0`). Der Seitentitel ist die erste `# `-Zeile der Datei
   (Rückfall: Dateiname ohne Endung). Der Inhalt besteht aus denselben
   Blöcken, die der bestehende Blockimporter im Editor erzeugen würde
   (CDB-Container, Kernblöcke, optional Accordion). Erreichbar als Untermenü
   **des Seitenmanagers**.
2. **Bulk-Optionen im Seitenmanager:** Mehrfachauswahl im Seitenbaum mit
   Sammelaktionen — veröffentlichen, auf Entwurf setzen, in den Papierkorb,
   Elternseite zuweisen bzw. auf oberste Ebene heben, aus Inhaltsverzeichnis
   bzw. Navigation ausnehmen und wieder aufnehmen.

Überprüfbar: 20 Markdown-Dateien ergeben in einem Durchlauf 20 Seitenentwürfe,
die sich im Blockeditor **ohne Gültigkeitswarnung** öffnen lassen; anschließend
lassen sich alle 20 im Seitenmanager mit zwei Klicks unter eine Elternseite
hängen und veröffentlichen.

## 2. Nicht-Ziele

- **Keine Änderung am bestehenden Blockimporter im Editor.** Er muss nach
  jeder Phase unverändert weiterlaufen.
- **Keine Änderung an `parse_markdown_content()`** oder am Markdown-Parser in
  `includes/class-cbd-content-importer.php`. Diese Datei wird ausschließlich
  gelesen und aufgerufen.
- **Kein Überschreiben vorhandener Seiten.** Titel-Dubletten werden gewarnt
  und aufgelistet, sind abwählbar, werden aber als neuer Entwurf angelegt.
- **Kein Dialog pro Datei.** Genau EIN Stil-Dialog für alle Dateien.
- **Kein serverseitiger Datei-Upload.** Die Dateien werden wie beim
  Blockimporter im Browser per `FileReader` gelesen.
- **Keine Änderung am Theme durch den Seitenimporter.** Der Importer hängt
  sich per `add_submenu_page()` an ein Menü, das das Theme bereitstellt; das
  Theme erfährt davon nichts.
- **Keine neuen Fremd-Libraries, keine CDN-Einbindungen** (DSGVO).
- **Kein Build-Schritt für CDB-JavaScript.** Kein npm/Webpack im Plugin.
- **Keine Statusanzeige für die Meta-Flags** („nicht im Inhaltsverzeichnis")
  im Seitenbaum – das erforderte eine Meta-Abfrage über alle Seiten. Die
  Bulk-Aktion meldet nur die Anzahl geänderter Seiten zurück.

## 3. Kontext & Constraints

- **Umgebung:** WordPress 6.x, Shared Hosting (All-Inkl) ohne SSH.
  **CDB-Designer: PHP 7.4-kompatibel** (Zielumgebung 7.4.33) – kein `match`,
  keine Named Arguments, kein Nullsafe-Operator, keine Constructor Property
  Promotion, keine Union Types. Theme: PHP 7.4+.
- **Bestehende Konventionen:**
  - `Plugins/CDB-Designer/CLAUDE.md` – ZIP-Build-Regeln (Autoloader-Falle),
    Debugging-Konventionen (`window.cbdDebug`), Content-Importer-Doku
  - `Theme/CLAUDE.md` – Funktionsübersicht `functions.php`, Glossar-System,
    Diagnose `?sc_perf=1`
  - `Theme/reference_file_map.md` – Datei-Map des Themes
  - `Plugins/CDB-Designer/` hat **noch keine** Datei-Map (wird in AP-4.1 angelegt)
- **Harte Grenzen:**
  - ZIPs **ausschließlich** über `node create-plugin-zip.js` (CDB) bzw.
    `npm run build` (Theme). Niemals manuell zippen – der CDB-Build stellt
    den `--no-dev`-Composer-Autoloader her; ein mit Dev-Paketen erzeugter
    Autoloader bindet phpunit ein → HTTP 500 auf der Zielinstallation.
  - `tools/` wird bewusst **nicht** ins CDB-ZIP ausgeliefert (Testharnesse
    bleiben im Repository).
  - CDB-JavaScript als IIFE, Zugriff über `wp.*`-Globale, `console.log`
    hinter `window.cbdDebug`; `console.error`/`warn` bleiben immer aktiv.
  - Theme-JS/CSS über Vite aus `src/` nach `dist/`. Die Einstiegspunkte
    `page-manager` und `page-manager-style` existieren bereits in
    `Theme/vite.config.js` – es werden **keine neuen** benötigt.
  - Oberfläche, Kommentare und Commit-Texte auf Deutsch.
- **Testumgebung:** Lokale WordPress-Installation unter `C:\allinkl-testserver`
  (phpMyAdmin unter `C:\allinkl-testserver\phpmyadmin`). Dort werden alle
  Browser-/Editor-Tests ausgeführt. `WP_DEBUG` aktivieren, `debug.log` nach
  jedem Test prüfen.
- **Git-Strategie:** Branch pro Phase, Commit pro AP mit AP-ID im Text,
  Push nach jedem AP. Merge in `main` erst nach Integrationstest UND Review.
- **Remote-Repositories** (beide bereits verbunden, Branch `main`):
  - CDB-Designer: `https://github.com/Cyric25/CBD---Container-Block-Desinger.git`
  - Theme: `https://github.com/Cyric25/FOS_Skripten_Website_Design.git`
- **Achtung Ausgangszustand:** Im CDB-Repo liegen zu Planungszeitpunkt
  14 geänderte und 2 unversionierte Dateien auf `main`. AP-1.1 sichert sie,
  bevor abgezweigt wird. Das Theme-Repo ist sauber.

## 4. Architekturentscheidungen

| Entscheidung | Begründung | Verworfene Alternative |
|---|---|---|
| Block-Erzeugung als **PHP-Serializer** (`CBD_Block_Serializer`) | Beim Seitenimport gibt es keinen Editor; gebraucht wird ein `post_content`-String. Der Serializer bildet `insertBlocks()` aus `assets/js/content-importer.js` nach. | Block-Registry im Browser auf der Admin-Seite laden und `wp.blocks.serialize()` nutzen. Erforderte `wp-editor`/`wp-block-library` auf einer Nicht-Editor-Seite plus ein künstliches Auslösen von `enqueue_block_editor_assets`; dabei würde auch `content-importer.js` geladen, das `wp.editor \|\| wp.editPost` destrukturiert und `registerPlugin()` aufruft. Mehr Fremdkörper, im Fehlerfall schwerer zu diagnostizieren. |
| Delimiter und Attribut-JSON über den **WordPress-Kern** (`serialize_blocks()`) | Die Kommentar-Trenner und die Attribut-Kodierung (`--` → `\u002d\u002d` usw.) müssen exakt der Kern-Erwartung entsprechen. | Selbst zusammenbauen – hohe Fehlerquote bei der Attribut-Maskierung. |
| **Grundwahrheit messen statt raten** (AP-1.2) | Das Markup der Kernblöcke ist WordPress-versionsabhängig (`class="wp-block-heading"` erst ab 6.1), der Container-Block hat ein statisches `save()`. Eine im Editor gespeicherte Referenzseite liefert das exakte Zielmarkup der tatsächlich eingesetzten Version. | Markup aus der Dokumentation ableiten – bricht bei jedem WordPress-Update anders. |
| **Listen in der migrierten Form** (`core/list` + `core/list-item`) | `content-importer.js` erzeugt `core/list` mit dem veralteten Attribut `values`; der Editor migriert das beim Laden. In der Datenbank muss sofort die migrierte Form stehen, sonst gilt der Block beim ersten Öffnen als ungültig. | 1:1-Nachbau des JS inklusive `values` – erzeugte garantiert Gültigkeitswarnungen. |
| **Der Server parst neu**, statt dem Browser die Blockstruktur zu glauben | `cbd_import_pages` bekommt den Markdown-**Rohtext** und ruft `parse_markdown_content()` selbst auf. Damit gelangt kein clientseitig erzeugtes HTML in die Datenbank. Das clientseitige Parsen dient allein der Vorschau und dem Stil-Dialog. | Die geparsten `sections` vom Browser übernehmen – spart einen Parserlauf, öffnet aber einen ungeprüften HTML-Pfad in `post_content`. |
| **Ein AJAX-Aufruf pro Datei** | Fortschritt bleibt sichtbar, ein PHP-Timeout bei 40 Dateien ist ausgeschlossen, ein Fehler betrifft nur eine Datei. | Sammelaufruf für alle Dateien. |
| Untermenü per `add_submenu_page('page-manager', …)` auf **`admin_menu` Priorität 20** | Damit die eigene Fallunterscheidung Elternmenü/Rückfall zuverlässig greift (siehe Korrektur bei R2). | Eigenes Top-Level-Menü im Plugin – widerspricht der Vorgabe „über den Seitenmanager". |
| Bulk „Status" über `wp_update_post()`, Bulk „Elternseite" über `$wpdb->update` | `wp_update_post()` feuert `save_post` – nötig, damit der Glossar-Scan die Seite erfasst. Die Elternzuweisung folgt dem bestehenden Muster in `ajax_update_order()`, das bewusst an `save_post` vorbeischreibt. | Beides einheitlich über `$wpdb` – ließe Seiten ohne `_glossar_scan_version` zurück (siehe Risiko R4). |
| Serializer bekommt eine **Optionen-Array-Signatur** statt vieler Positionsparameter | Macht `known_slugs` und `accordion_available` injizierbar und damit den Serializer ohne WordPress und ohne Datenbank testbar. | Feste Positionsparameter plus interne DB-Abfrage – headless nicht testbar. |
| Phase 1 fasst `container-block-designer.php` **nicht** an | Das Testharness bindet die Klassendatei direkt ein. Die Plugin-Verdrahtung (beide `require_once`) passiert ausschließlich in AP-2.1. | Serializer schon in Phase 1 verdrahten – erzeugte eine unbenutzte Abhängigkeit und eine zweite Fundstelle für dieselbe Änderung. |

## 5. Risiken & Rollback

| ID | Risiko | Wahrscheinlichkeit | Auswirkung | Gegenmaßnahme / Rollback |
|---|---|---|---|---|
| R1 | Erzeugtes Markup weicht von der `save()`-Ausgabe ab → Editor meldet „Dieser Block enthält unerwarteten oder ungültigen Inhalt" | mittel | hoch | Vierfach gestaffelt: (1) Referenzmarkup aus dem echten Editor als Fixture (AP-1.2); (2) Delimiter über `serialize_blocks()`; (3) Markup-Treue-Tests im Harness (AP-1.3, Gruppe B); (4) Sicherheitsnetz `assets/js/block-recovery.js` repariert ungültige Blöcke beim Öffnen. **Rollback:** Der Serializer ist eine eigenständige Datei ohne Eingriff in bestehenden Code – im Ernstfall wird die Importseite nicht ausgeliefert (die zwei `require_once` aus AP-2.1 entfernen). |
| R2 | ~~`add_submenu_page()` scheitert stillschweigend~~ **widerlegt am 2026-08-10** — die Funktion prüft das Elternmenü gar nicht und gibt `false` nur bei fehlender Capability zurück (`wp-admin/includes/plugin.php`); die Gegenprobe mit Priorität 10 zeigte den Eintrag korrekt unter dem Seitenmanager. Verbleibendes, kleineres Risiko: Läuft die eigene Prüfung `isset($admin_page_hooks['page-manager'])` zu früh, greift der Rückfall grundlos. | gering | gering | Priorität 20 sichert die Fallunterscheidung ab; Akzeptanzkriterium in AP-2.1 bleibt („Eintrag ist unter dem **Seitenmanager** sichtbar", nicht bloß irgendwo). |
| R3 | Ein Container mit unbekanntem Design-Slug rendert im Frontend „Block nicht gefunden" | gering | mittel | Der Serializer prüft jeden Slug gegen die aktiven Designs und fällt sonst auf „ohne Container" zurück (wie das JS). Testfall T14 in AP-1.3. |
| R4 | Importierte Seiten ohne `_glossar_scan_version` fallen beim Rendern auf **alle** Glossarbegriffe zurück – gemessen 1,998 s statt 0,058 s bei 1049 Begriffen (Faktor 34) | gering | hoch | `wp_insert_post()` feuert `save_post`, dadurch läuft `simple_clean_update_glossar_candidates()` mit. Nachweis in AP-2.4: Meta gesetzt, `?sc_perf=1` zeigt `fallback=0`. Falls doch nicht: Bulk-Scan auf der Glossar-Einstellungsseite ausführen. |
| R5 | DOMDocument zerstört UTF-8 oder LaTeX beim Parsen des Zwischen-HTML | mittel | mittel | Encoding-Hinweis beim Laden, `libxml_use_internal_errors(true)`, Testfälle T9–T11 (Umlaute, LaTeX, Inline-Auszeichnung) und T23 (LaTeX mit `<`) in AP-1.3. Bei nicht parsebarem HTML greift der `core/freeform`-Rückfall – Inhalt geht nie verloren. |
| R6 | Die neuen Checkboxen stören die Drag-Sortierung | gering | mittel | Das Sortable ist bereits mit `handle: '.drag-handle'` initialisiert (`Theme/src/js/page-manager.js`, `initSortables()`) – ein Klick außerhalb des Griffs startet kein Ziehen. AP-3.2 weist das nach, statt eine `cancel`-Option nachzurüsten. |
| R7 | PHP-8.0-Syntax rutscht ins CDB-Plugin und bricht die Zielumgebung 7.4.33 | mittel | hoch | `php tools/check-php74.php` in jedem PHP-AP (Regel 12) und zwingend im ZIP-Bau. |
| R8 | Uncommittete Vorarbeiten im CDB-Repo gehen beim Branchen verloren | gering | hoch | AP-1.1 committet und pusht den Ist-Zustand auf `main`, bevor abgezweigt wird. |
| R9 | Ein Nutzer schickt manipulierte Daten an `cbd_import_pages` | gering | mittel | Der Server parst den Markdown-Rohtext selbst (Architekturentscheidung), Nonce `cbd_page_import`, Capability `edit_pages`, `wp_unslash()` vor jeder Auswertung, Titel über `sanitize_text_field()`. |

**Generelle Rollback-Strategie:** Branch pro Phase; `main` bleibt bis zum
bestandenen Review unberührt. Vor jedem AP, das auf der Testinstallation
Seiten anlegt oder ändert: Datenbank-Dump über phpMyAdmin
(`C:\allinkl-testserver\phpmyadmin`, Tabelle `wp_posts` und `wp_postmeta`
genügen). Die Produktivinstallation wird erst in Phase 4 angefasst.

## 6. Phasenübersicht

Jede Phase endet mit `AP-<N>.rev` (unabhängiges Review) und `AP-<N>.doc`
(Dokumentation) – in dieser Reihenfolge nach den Implementierungs-APs.

**Zwei Spuren:** Spur A (Phasen 1 → 2, Repository CDB-Designer) und Spur B
(Phase 3, Repository Theme) teilen keine Datei und laufen parallel. Phase 4
führt beide zusammen und setzt beide voraus.

| Phase | Spur | Repo | Ziel | Lauffähiger Endzustand | APs |
|---|---|---|---|---|---|
| 1 | A | CDB | Markdown-Abschnitte → Gutenberg-Block-Markup in PHP | `php tools/test-block-serializer.php` meldet 0 Fehler; das erzeugte Markup ist deckungsgleich mit der im Editor gespeicherten Referenzseite. Das Plugin verhält sich unverändert (der Serializer ist noch nicht verdrahtet). | AP-1.1 … AP-1.5, AP-1.rev, AP-1.doc |
| 2 | A | CDB | Importseite unter dem Seitenmanager | Mehrere `.md`-Dateien auswählen → ein Stil-Dialog → je Datei ein Seitenentwurf auf oberster Ebene. Jede erzeugte Seite öffnet sich im Editor ohne Gültigkeitswarnung. Der Blockimporter im Editor läuft unverändert. | AP-2.1 … AP-2.5, AP-2.rev, AP-2.doc |
| 3 | B | Theme | Sammelaktionen im Seitenmanager | Mehrere Seiten auswählen und in einem Durchgang veröffentlichen, auf Entwurf setzen, in den Papierkorb legen, unter eine Elternseite hängen oder aus Inhaltsverzeichnis/Navigation nehmen. Drag-Sortierung, Einzelaktionen und Aufklapp-Zustand unverändert. | AP-3.1 … AP-3.3, AP-3.rev, AP-3.doc |
| 4 | A+B | beide | Dokumentation, Datei-Maps, Auslieferung | Beide Datei-Maps aktuell, CDB-Designer hat erstmals eine; ZIPs gebaut und auf der Testinstallation verifiziert; Rollout-Reihenfolge festgehalten. | AP-4.1 … AP-4.3, AP-4.rev, AP-4.doc |

## 7. Arbeitspakete

### Phase 1: Block-Serializer (Spur A, Repository CDB-Designer)

Arbeitsverzeichnis für alle APs dieser Phase:
`c:\Users\mtnhu\OneDrive - Bildungsdirektion\#Unterricht\Website\Plugins\CDB-Designer`
Alle Dateipfade in Phase 1 und 2 sind relativ zu diesem Verzeichnis.

---

### AP-1.0.fix1: WordPress-Testinstallation aufsetzen

**Status:** ☑ erledigt (2026-08-10)
**Umfang:** M
**Modell:** — (Nachtrag während der Ausführung, siehe Abschnitt 0, Regel 17)
**Abhängigkeiten:** keine

**Warum dieses AP nachträglich entstand:**
Abschnitt 3 des Plans nannte als Testumgebung „Lokale WordPress-Installation
unter `C:\allinkl-testserver`". Beim Start von AP-1.2 zeigte sich: Dort liegt
zwar ein vollständiger All-inkl-Nachbau (Apache, PHP 8.3.32, MariaDB 10.11,
phpMyAdmin), aber **kein WordPress** — nur einige Probe-Skripte. Auch sonst
gab es auf dem Rechner keine WordPress-Installation (kein Local by Flywheel,
kein XAMPP, kein WP-CLI). Damit waren AP-1.2 sowie sämtliche Browser-Tests der
Phasen 2 und 3 blockiert. Die Annahme war schlicht falsch.

**Was eingerichtet wurde:**

| Gegenstand | Wert |
|---|---|
| Adresse | `http://fos.localhost:8080` (eigener VHost über `tools\kas-add-domain.cmd fos`) |
| Verzeichnis | `C:\allinkl-testserver\www\htdocs\w0000001\fos` |
| WordPress | **7.0.3**, Sprache de_DE |
| PHP | 8.3.32 (Stack), CLI daneben 8.5.1 |
| Datenbank | `d0000001`, Präfix `wp_` |
| Anmeldung | `admin` / `admin123!` |
| WP-CLI | `wp-cli.phar` 2.12.0 im Scratchpad, aufzurufen mit dem Stack-PHP |
| Theme | `fos-online-schulbuch` 1.5.75, aktiv |
| Plugins | `container-block-designer` 3.1.85 und `modular-blocks-plugin` 1.1.8, beide aktiv |
| Debug | `WP_DEBUG` und `WP_DEBUG_LOG` an, `WP_DEBUG_DISPLAY` aus |

Theme und Plugins wurden aus dem Projekt kopiert (ohne `node_modules`, `.git`,
`dist`-Zwischenstände), **nicht** aus ZIPs — die ZIP-Prüfung bleibt AP-4.3
vorbehalten.

**Nachgewiesen:** `container-block-designer/container`,
`modular-blocks/accordion` und `fos/inhaltsverzeichnis` sind registriert
(damit ist auch der Accordion-Zweig des Serializers testbar). Tabelle
`wp_cbd_blocks` wurde angelegt. `debug.log` enthält ausschließlich
Infomeldungen des Modular-Plugins, keine Fehler oder Warnungen.

**Sechs Container-Designs angelegt**, benannt wie im echten Projekt, damit die
automatische Namenszuordnung des Importers realistisch prüfbar ist:
`infotext_k1`, `infotext_k2`, `infotext_k3`, `uebungen`, `hinweise`,
`quellen`.

**Dabei gefunden — offene Frage an den Nutzer (blockiert AP-1.2):**
Die Tabelle `wp_cbd_blocks` hat laut `CBD_Schema_Manager` die Spalten `name`
und `title`, **aber keine Spalte `slug`**; der Schema-Manager benennt eine
vorgefundene Spalte `slug` sogar nach `name` um. Mehrere Abfragen im Plugin
verlangen aber eine Spalte `slug`. Empirisch belegt auf der frischen
Installation:
`SELECT id, name, slug FROM wp_cbd_blocks WHERE status='active'`
→ `Unknown column 'slug' in 'SELECT'`, 0 Zeilen.

Betroffene Fundstellen:
- `includes/class-cbd-content-importer.php:174` und `:564` (Stilliste und
  Designs für den Parser — ohne sie bietet der Importer **keine** Designs an)
- `includes/class-cbd-block-registration.php:854`
  (`WHERE (name = %s OR slug = %s)` — die Nachschlage-Abfrage beim **Rendern**
  eines Containers)
- `includes/class-cbd-admin.php:2801`

Erschwerend: Die Codebasis ist uneinig, **welche** Spalte der Bezeichner ist.
`class-cbd-design-transfer.php` und `CLAUDE.md` behandeln `name` als Slug
(„Seiten referenzieren ihr Design über `name`"), während
`class-cbd-admin.php:2806` protokolliert „Block name changed … but slug
stays" — dort ist `name` die Anzeige und `slug` der Bezeichner. Aus dem Code
allein ist nicht entscheidbar, wie die Produktivdatenbank aussieht.

**Auflösung (2026-08-10, aus dem Code selbst):** Die Frage, welche Spalte der
Bezeichner ist, beantwortet `CBD_Admin::handle_database_repair()`:

- Zeile 2440 legt bei Bedarf `slug varchar(100)` an.
- **Zeile 2469: `UPDATE … SET slug = name WHERE slug = '' OR slug IS NULL`** —
  `slug` ist also eine **Kopie von `name`**. `name` ist der Bezeichner, `slug`
  ein redundantes Zweitfeld. Die Log-Meldung in `class-cbd-admin.php:2806`
  („name changed … but slug stays") ist Altlast aus der Zeit davor.

Zudem **diagnostiziert das Plugin den Fehler selbst**: Die Seite
*Container Designer → Datenbank reparieren* (`admin.php?page=cbd-database-repair`)
gibt „Aktuelle Spalten" aus und meldet auf einer frischen Installation
„❌ Fehlende Spalten: slug" samt Reparaturschaltfläche. Die Reparaturseite und
`CBD_Schema_Manager` widersprechen sich also: Der Schema-Manager legt `slug`
nicht an, die Reparaturseite führt sie als Pflichtspalte.

**Achtung bei der Reparaturschaltfläche:** `handle_database_repair()` setzt in
Zeile 2473 `cbd_db_version` auf `'2.9.0'` zurück, obwohl
`CBD_Schema_Manager::DB_VERSION` bei `3.1.61` steht. Ein Klick stuft die
Versionsangabe also herunter und lässt beim nächsten Laden Migrationen erneut
anlaufen. Auf der Produktivinstallation deshalb **nicht** blind klicken.

**Testinstallation angeglichen:** Die Spalte wurde mit derselben SQL wie die
Reparatur angelegt und aus `name` befüllt — aber **ohne** die
Versions-Rückstufung. `cbd_db_version` steht weiterhin auf `3.1.61`. Die
Originalabfrage des Importers (`SELECT id, name, slug …`) liefert jetzt alle
sechs Designs. Damit ist AP-1.2 nicht mehr durch die Spalte blockiert.

**Konsolenskript für die Produktivinstallation:** `docs/pruefung-produktiv.js`
(neu). Es liest im Adminbereich WordPress-Version, Tabellenspalten,
Beispieldesigns und Plugin-Version aus, im Blockeditor zusätzlich die
tatsächliche Antwort von `cbd_get_style_mappings`. Rein lesend, löst die
Reparatur **nicht** aus. Das Parsen der Reparaturseite wurde gegen die echte
Ausgabe geprüft (`<td><strong>Aktuelle Spalten:</strong></td><td>…</td>`).

**Bleibt offen:** die produktive WordPress-Version (Risiko R1) und die Frage,
ob die Produktivdatenbank die Spalte `slug` bereits hat. Beides liefert das
Konsolenskript.

**Kandidat für einen Korrektur-AP (nicht Teil dieses Plans, Entscheidung des
Nutzers):** Entweder `CBD_Schema_Manager` legt `slug` mit an, oder die vier
Abfragen verzichten darauf. Solange beides nebeneinander steht, ist jede
frische Installation kaputt — Importer ohne Designs und Container, die beim
Rendern nicht auflösen.

**Zweite offene Frage:** Die Testinstallation läuft auf **WordPress 7.0.3**.
Welche Version läuft produktiv? Weicht sie ab, kodiert die in AP-1.2 zu
erhebende Fixture das falsche Zielmarkup — genau das Risiko R1. Gegebenenfalls
wird die Testinstallation per `wp core update --version=<x>` auf die
Produktivversion gesetzt.

---

### AP-1.1: Ausgangszustand sichern und Phasen-Branch anlegen

**Status:** ☑ erledigt (2026-08-10)
**Umfang:** S
**Modell:** sonnet
**Abhängigkeiten:** keine

**Ziel & Kontext:**
Im Repository CDB-Designer liegen unversionierte Vorarbeiten auf `main`
(u. a. Änderungen an `admin/settings.php`, `includes/functions.php`,
`assets/css/custom-icons.css` sowie die unversionierten Dateien
`docs/ERWEITERUNGSANALYSE-Seitenimport.md` und `tools/test-icon-scale.php`).
Diese müssen gesichert sein, bevor ein Phasen-Branch abzweigt – sonst wandern
fremde Änderungen in den Branch oder gehen verloren.

**Betroffene Dateien:**
- keine inhaltlichen Änderungen; nur Git-Zustand

**Vorgehen:**
1. `git status` ausführen und die Ausgabe vollständig in die Übergabenotiz
   übernehmen (Beleg des Ausgangszustands).
2. Alle geänderten und unversionierten Dateien auf `main` committen:
   `git add -A` und
   `git commit -m "Zwischenstand vor Vorhaben Seitenimport gesichert"`.
   **Ausnahme:** Nichts committen, was offensichtlich nicht ins Repository
   gehört (z. B. `*.zip`, `dist/`, `node_modules/`, `vendor/`-Neuzugänge).
   Prüfe vor dem `git add -A` mit `git status --porcelain`, ob solche
   Einträge dabei sind, und ergänze sie nötigenfalls in `.gitignore`.
3. `git push origin main`.
4. Branch anlegen und wechseln:
   `git checkout -b phase-1-block-serializer`.
5. `git push -u origin phase-1-block-serializer`.

**Akzeptanzkriterien:**
- [ ] `git status` meldet auf `phase-1-block-serializer` einen sauberen
      Arbeitsbaum („nothing to commit, working tree clean").
- [ ] `git log --oneline -1 main` zeigt den Sicherungs-Commit.
- [ ] `git ls-remote --heads origin phase-1-block-serializer` liefert eine Zeile
      (Branch ist auf dem Remote vorhanden).
- [ ] Weder `dist/`, `node_modules/`, `vendor/`-Neuzugänge noch `*.zip` wurden
      committet (`git show --stat HEAD` prüfen).

**Tests:**
- Smoke-Test: `git status` → sauberer Arbeitsbaum.
- Prüfschritt: `git show --stat HEAD` ausgeben und in der Übergabenotiz
  festhalten, welche Dateien gesichert wurden.

**Übergabenotiz:**
Erledigt am 2026-08-10.

**Ausgangszustand** (`git status --porcelain` auf `main`, 17 Einträge):
14 geänderte Dateien — `CLAUDE.md`, `admin/design-transfer.php`,
`admin/settings.php`, `assets/css/cbd-frontend-clean.css`,
`assets/css/classroom-frontend.css`, `assets/css/custom-icons.css`,
`assets/js/floating-pdf-button.js`, `container-block-designer.php`,
`includes/class-cbd-admin.php`, `includes/class-cbd-block-registration.php`,
`includes/class-cbd-classroom.php`, `includes/class-cbd-design-transfer.php`,
`includes/functions.php`, `tools/test-design-transfer.php`.
3 unversionierte — `docs/ERWEITERUNGSANALYSE-Seitenimport.md`,
`docs/PLAN-Seitenimport.md`, `tools/test-icon-scale.php`.

**Inhalt der Sicherung:** Der letzte Commit auf `main` war v3.1.78
(`a4545f3`), im Hauptfile stand aber bereits Version **3.1.85**. Die
unversionierten Änderungen sind also die Arbeit von v3.1.79 bis v3.1.85:
Markdown-Format für den Design-Export/-Import, die über die Einstellungen
regelbare Icon-Größe (`--cbd-icon-scale`), der plastische Look für
PDF-Button und PDF-Werkzeugleiste sowie die Klassenraum-Gestaltung. Das ist
**fremde Arbeit**, die nichts mit diesem Vorhaben zu tun hat — sie wurde
unverändert und vollständig committet, nicht verändert und nicht
auseinandergenommen.

**Commit:** `35fdc0f` „Zwischenstand v3.1.79-3.1.85 gesichert (vor Vorhaben
Seitenimport)", 17 Dateien, 5082 Einfügungen, 140 Löschungen. Nach `main`
gepusht (`a4545f3..35fdc0f`).

**Prüfung auf unerwünschte Dateien:** `git show --stat HEAD` enthält weder
`dist/`, `node_modules/`, `vendor/`-Neuzugänge noch `*.zip`. `.gitignore`
deckte `dist/` und `node_modules/` bereits ab und musste nicht ergänzt
werden. `vendor/` ist in diesem Projekt bewusst versioniert (mPDF wird mit
dem ZIP ausgeliefert) und wies keine Änderungen auf.

**Branch:** `phase-1-block-serializer` von `35fdc0f` abgezweigt und mit
`-u` zum Remote gepusht. `git ls-remote --heads origin phase-1-block-serializer`
liefert `35fdc0f7c29c0120f05f7230bf506d47547ce7f9`. Arbeitsbaum sauber.

**Für Folge-APs relevant:**
- Die Plugin-Version steht auf **3.1.85**. `create-plugin-zip.js` erhöht sie
  beim Bau selbstständig — in AP-4.3 wird das ZIP also 3.1.86 oder höher
  heißen.
- Git meldet beim Anfassen mehrerer Dateien `LF will be replaced by CRLF`.
  Das ist die Zeilenendenbehandlung von Git unter Windows und harmlos, aber
  es bedeutet: `git diff` kann bei Dateien, die nur neu geschrieben wurden,
  Änderungen zeigen, die inhaltlich keine sind. Beim Review-Kriterium
  „`tools/test-block-serializer.php` ist unverändert" deshalb `git diff -w`
  oder den Vergleich der Dateiinhalte heranziehen, nicht blind dem Diff
  vertrauen.

---

### AP-1.2: Referenzmarkup aus dem Editor erheben (Grundwahrheit)

**Status:** ☑ erledigt (2026-08-10)
**Umfang:** S
**Modell:** sonnet
**Abhängigkeiten:** AP-1.1
**Nutzeraktion erforderlich:** ja – ein Agent kann keine Blöcke im
Gutenberg-Editor anklicken. Dieses AP bereitet alles vor, erstellt eine
Klickanleitung und verarbeitet das Ergebnis.

**Ziel & Kontext:**
Der in AP-1.4 und AP-1.5 zu bauende PHP-Serializer muss Block-Markup erzeugen,
das **exakt** dem entspricht, was der Gutenberg-Editor beim Speichern
schreibt. Weicht es ab, meldet der Editor beim Öffnen „Dieser Block enthält
unerwarteten oder ungültigen Inhalt". Das Zielmarkup ist versionsabhängig
(z. B. gibt es `class="wp-block-heading"` erst ab WordPress 6.1) und beim
Container-Block vom statischen `save()` in `assets/js/block-editor.js`
(Funktion `ContainerBlockSave`, ab Zeile 319) bestimmt. Statt es abzuleiten,
wird es einmal an der echten Installation gemessen und als Fixture abgelegt.

**Betroffene Dateien:**
- `tools/fixtures/referenz-markup.html` (neu) – der rohe `post_content` der Referenzseite
- `tools/fixtures/referenz-umgebung.md` (neu) – Versionsangaben und Inhaltsverzeichnis der Fixture
- `tools/dump-post-content.php` (neu, wird am Ende des APs wieder gelöscht) – Hilfsskript zum Auslesen

**Vorgehen:**
1. Prüfen, ob auf der Testinstallation unter `C:\allinkl-testserver` WP-CLI
   verfügbar ist (`wp --version`). Falls ja, entfällt Schritt 2 und das
   Auslesen erfolgt in Schritt 5 per WP-CLI.
2. Falls kein WP-CLI: `tools/dump-post-content.php` anlegen. Inhalt:
   WordPress laden (`require_once` der `wp-load.php` der Testinstallation –
   den Pfad beim Anlegen ermitteln), `current_user_can('manage_options')`
   prüfen und bei Fehlschlag abbrechen, dann
   `echo '<textarea rows="40" cols="120">' . esc_textarea(get_post_field('post_content', absint($_GET['id']))) . '</textarea>';`
   Die Datei wird zum Auslesen temporär in das WordPress-Wurzelverzeichnis
   der Testinstallation kopiert.
3. Eine Anleitung für den Nutzer ausgeben (im Chat, nicht als Datei), die
   genau diese Referenzseite beschreibt. Die Seite heißt
   **„CBD Referenzmarkup (nicht löschen)"** und enthält in dieser Reihenfolge:
   - **Abschnitt 1 – ohne Container:** eine Überschrift Ebene 3 („Ohne
     Container"), ein Absatz mit dem Text
     `Absatz mit fett und kursiv und Formel $a_1 + b^2$.` (davon „fett" fett
     und „kursiv" kursiv ausgezeichnet), eine ungeordnete Liste mit zwei
     Einträgen („Erster Eintrag", „Zweiter Eintrag"), eine geordnete Liste
     mit zwei Einträgen („Schritt eins", „Schritt zwei"), eine Tabelle mit
     Kopfzeile (zwei Spalten „Stoff", „Menge") und zwei Datenzeilen.
   - **Abschnitt 2 – mit Container:** ein Container-Block, dem im
     Seitenleisten-Menü ein beliebiges vorhandenes Design zugewiesen ist und
     dessen Feld „Block-Titel" auf `Referenztitel` gesetzt ist; darin dieselben
     fünf Elemente wie in Abschnitt 1 (Überschrift Ebene 4 statt 3).
   - **Abschnitt 3 – Accordion (nur falls der Block „Accordion – Aufklappbare
     Zeilen" im Einfügen-Menü auftaucht):** ein Accordion mit zwei Klappzeilen
     (je eine Überschrift Ebene 3 „Klappzeile eins" bzw. „Klappzeile zwei" und
     darunter je ein Absatz), einmal frei stehend und einmal als einziger
     Innenblock eines Containers.
   Die Seite als **Entwurf speichern**, nicht veröffentlichen. Die Seiten-ID
   aus der Adresszeile (`post=<ID>`) notieren.
4. Vom Nutzer die Seiten-ID entgegennehmen.
5. `post_content` auslesen – per `wp post get <ID> --field=content` oder über
   das Hilfsskript (`.../dump-post-content.php?id=<ID>`, als angemeldeter
   Administrator) oder ersatzweise über phpMyAdmin
   (`SELECT post_content FROM wp_posts WHERE ID = <ID>`).
6. Den Inhalt **zeichengenau und unverändert** (keine Umformatierung, keine
   Einrückung, keine Zeilenumbrüche einfügen oder entfernen) nach
   `tools/fixtures/referenz-markup.html` schreiben.
7. `tools/fixtures/referenz-umgebung.md` anlegen mit: Datum, WordPress-Version
   (`wp core version` oder Fußzeile des Dashboards), PHP-Version der
   Testinstallation, CDB-Plugin-Version (Konstante `CBD_VERSION` bzw. Kopf von
   `container-block-designer.php`), Theme-Version, Post-ID, dem Slug des in
   Abschnitt 2 verwendeten Container-Designs und der Angabe, ob Abschnitt 3
   (Accordion) enthalten ist oder nicht.
8. Das Hilfsskript aus dem WordPress-Wurzelverzeichnis der Testinstallation
   **und** aus dem Repository löschen (`tools/dump-post-content.php`).

**Akzeptanzkriterien:**
- [ ] `tools/fixtures/referenz-markup.html` existiert und enthält mindestens
      die Zeichenketten `<!-- wp:paragraph`, `<!-- wp:heading`, `<!-- wp:list`,
      `<!-- wp:list-item`, `<!-- wp:table` und
      `<!-- wp:container-block-designer/container`.
- [ ] Im Container-Delimiter steht ein `"selectedBlock":`-Attribut mit dem in
      `referenz-umgebung.md` notierten Design-Slug und ein
      `"blockTitle":"Referenztitel"`.
- [ ] `tools/fixtures/referenz-umgebung.md` nennt WordPress-Version,
      PHP-Version, CDB-Version, Theme-Version, Post-ID, Design-Slug und ob
      Abschnitt 3 enthalten ist.
- [ ] `tools/dump-post-content.php` existiert weder im Repository noch im
      WordPress-Wurzelverzeichnis der Testinstallation.
- [ ] Die Fixture wurde nicht nachbearbeitet: Sie beginnt mit `<!-- wp:` und
      enthält keine vom Agenten eingefügten Kommentare.

**Tests:**
- Smoke-Test: `php -r "echo strlen(file_get_contents('tools/fixtures/referenz-markup.html'));"`
  gibt eine Zahl > 500 aus.
- Prüfschritt: Die Referenzseite im Editor erneut öffnen – sie zeigt **keine**
  Gültigkeitswarnung (belegt, dass das Markup zur laufenden Version passt).
- Prüfschritt: Die Anzahl der öffnenden Delimiter (`<!-- wp:`) und der
  schließenden (`<!-- /wp:`) plus der selbstschließenden (`/-->`) in der
  Fixture zählen und in der Übergabenotiz festhalten.

**Übergabenotiz:**

---

### AP-1.3: Testharness mit allen Testfällen anlegen (TDD, rot committen)

**Status:** ☑ erledigt (2026-08-10)
**Umfang:** M
**Modell:** sonnet
**Abhängigkeiten:** AP-1.2

**Ziel & Kontext:**
Bevor der Serializer entsteht, werden seine Testfälle geschrieben und rot
committet. Das Harness läuft headless, ohne WordPress, nach dem Muster von
`tools/test-design-transfer.php` und `tools/test-icon-value.php`: eine
einzelne PHP-Datei, die die zu testende Klassendatei direkt einbindet, die
benötigten WordPress-Funktionen stubbt und am Ende „N Prüfungen, M Fehler"
ausgibt (Exit-Code 1 bei Fehlern).

Getestet wird die in AP-1.4 und AP-1.5 zu bauende Klasse
`CBD_Block_Serializer` in `includes/class-cbd-block-serializer.php` mit
folgender öffentlicher Schnittstelle (PHP 7.4-Syntax):

```php
class CBD_Block_Serializer {
    /** Baut die Blockstruktur als verschachteltes Array (WordPress-Blockformat). */
    public static function to_block_array(array $sections, array $groups, array $style_mappings, array $optionen = array()) : array

    /** Wie to_block_array(), zusätzlich durch serialize_blocks() geschickt. */
    public static function to_post_content(array $sections, array $groups, array $style_mappings, array $optionen = array()) : string

    /** Wandelt ein HTML-Fragment in Blöcke um (wird von to_block_array() genutzt). */
    public static function html_to_blocks($html) : array
}
```

`$optionen` kennt vier Schlüssel, alle optional:
- `'accordion_opt_out' => array` – Gruppenschlüssel => `true` bedeutet: der
  Nutzer hat die Accordion-Direktive abgewählt
- `'page_title' => string` – für die H1-Unterdrückung
- `'known_slugs' => array|null` – vorhandene Design-Slugs; `null` bedeutet
  „aus der Datenbank laden" (im Test immer ein Array übergeben)
- `'accordion_available' => bool|null` – ist `modular-blocks/accordion`
  registriert; `null` bedeutet „über die Registry ermitteln" (im Test immer
  einen Wahrheitswert übergeben)

Ein Element von `$sections` hat die Struktur, die
`CBD_Content_Importer::parse_markdown_content()` liefert:
`array('topic', 'competence', 'blockTitle', 'groupKey', 'groupLabel', 'titleSource', 'hasExplicitCompetence', 'content')`.
Ein Element von `$groups`:
`array('key', 'label', 'competence', 'count', 'hasSubheadings', 'suggestedStyle', 'similarStyle', 'matchedBy', 'accordion')`,
wobei `accordion` entweder `null` ist oder
`array('enabled', 'level', 'numbering', 'multiple', 'openFirst', 'expandAll')`.

Das Blockformat, das `to_block_array()` liefert, ist das WordPress-Blockarray:

```php
array(
    'blockName'    => 'core/paragraph',
    'attrs'        => array(),
    'innerBlocks'  => array(),
    'innerHTML'    => '<p>Text</p>',
    'innerContent' => array('<p>Text</p>'),
)
```

Bei Blöcken mit Kindern stehen an den Kindpositionen `null`-Einträge in
`innerContent`, zum Beispiel eine Liste mit zwei Einträgen:

```php
array(
    'blockName'    => 'core/list',
    'attrs'        => array(),
    'innerBlocks'  => array($eintrag1, $eintrag2),
    'innerHTML'    => '<ul class="wp-block-list"></ul>',
    'innerContent' => array('<ul class="wp-block-list">', null, null, '</ul>'),
)
```

**Betroffene Dateien:**
- `tools/test-block-serializer.php` (neu)

**Vorgehen:**
1. Grundgerüst nach dem Muster von `tools/test-design-transfer.php` anlegen:
   Zähler für Prüfungen und Fehler, Hilfsfunktion
   `pruefe($bezeichnung, $erwartet, $tatsaechlich)` mit Ausgabe von
   „OK"/„FEHLER" plus Diff bei Abweichung, am Ende Zusammenfassung und
   `exit(anzahl_fehler > 0 ? 1 : 0)`.
2. WordPress-Stubs bereitstellen, jeweils nur wenn noch nicht definiert
   (`if (!function_exists(...))`): `__()`, `esc_html()`, `esc_attr()`,
   `esc_url()`, `add_action()`, `wp_json_encode()`.
3. **Die Serialisierungsfunktionen des Kerns wörtlich übernehmen:**
   `serialize_blocks()`, `serialize_block()`,
   `get_comment_delimited_block_content()` und `serialize_block_attributes()`
   aus der Datei `wp-includes/blocks.php` der Testinstallation unter
   `C:\allinkl-testserver` kopieren (Pfad beim Ausführen ermitteln). Über
   jeden Stub einen Kommentar setzen: `// Wörtliche Kopie aus
   wp-includes/blocks.php (WordPress <Version>) – nicht anpassen.`
   Diese Funktionen sind reine Zeichenkettenverarbeitung ohne weitere
   WordPress-Abhängigkeiten. Die endgültige Absicherung der Markup-Treue
   leistet nicht dieser Stub, sondern Testgruppe B gegen die Fixture.
4. `includes/class-cbd-block-serializer.php` per `require_once` einbinden.
   Die Datei existiert noch nicht – das Harness bricht in diesem AP genau
   deshalb ab, und das ist der gewollte rote Zustand.
5. **Testgruppe A – Fragmentebene** (`html_to_blocks()`), je Fall Eingabe-HTML
   und erwartete Blockstruktur:

   | Nr | Eingabe | Erwartung |
   |---|---|---|
   | T1 | `<p>Text</p>` | ein Block `core/paragraph`, `innerHTML` enthält `Text` |
   | T2 | `<h3>Drei</h3><h4>Vier</h4>` | zwei `core/heading`, `attrs['level']` 3 und 4 |
   | T3 | `<ul><li>a</li><li>b</li></ul>` | ein `core/list` mit zwei `core/list-item`; `attrs` enthält **keinen** Schlüssel `values` |
   | T4 | `<ol><li>a</li></ol>` | ein `core/list` mit `attrs['ordered'] === true` und einem `core/list-item` |
   | T5 | `<table><thead><tr><th>A</th><th>B</th></tr></thead><tbody><tr><td>1</td><td>2</td></tr></tbody></table>` | ein `core/table`; `attrs['head']` hat eine Zeile mit zwei Zellen, `attrs['body']` eine Zeile mit zwei Zellen |
   | T6 | `<blockquote>Zitat</blockquote>` | ein `core/paragraph` mit dem Text `Zitat` (Rückfall wie im JS) |
   | T7 | `''` | leeres Array |
   | T8 | `"\n   \n"` | leeres Array |
   | T9 | `<p>Größe, Übung, Straße</p>` | `innerHTML` enthält `Größe, Übung, Straße` unverändert (UTF-8) |
   | T10 | `<p>Formel $a_1 \cdot b^*$ und $$\sum x_i$$</p>` | `innerHTML` enthält beide Formeln zeichengenau |
   | T11 | `<p>Text mit <strong>fett</strong> und <a href="https://example.org/x">Link</a></p>` | `innerHTML` enthält `<strong>fett</strong>` und das `<a href="https://example.org/x">`-Element unverändert |
   | T23 | `<p>Wenn $a < b$ gilt</p>` | **kein Inhaltsverlust**: Das Ergebnis enthält entweder einen Absatz mit dem Text `Wenn $a < b$ gilt` oder – falls libxml das Zeichen als Tag-Anfang deutet – einen `core/freeform`-Block mit dem Original-HTML. Ein leeres Ergebnis ist ein Fehlschlag. |

6. **Testgruppe B – Dokumentebene** (`to_block_array()` / `to_post_content()`).
   Für alle Fälle gilt: `known_slugs` wird als `array('info-box', 'uebungen')`
   übergeben, sofern nichts anderes steht.

   | Nr | Aufbau | Erwartung |
   |---|---|---|
   | T12 | ein Abschnitt, `groupKey = 'h2-info-box'`, Mapping `'h2-info-box' => 'info-box'` | genau ein Block `container-block-designer/container` mit `attrs['selectedBlock'] === 'info-box'` und `attrs['blockTitle']` gleich dem `blockTitle` des Abschnitts; der Absatz liegt in `innerBlocks` |
   | T13 | derselbe Abschnitt, Mapping `'__none__'` | erster Block `core/heading` mit `attrs['level'] === 3` und dem `blockTitle` als Inhalt, danach der Absatz; **kein** Container |
   | T14 | Mapping auf `'gibt-es-nicht'` (nicht in `known_slugs`) | Verhalten wie T13 (Rückfall auf „ohne Container") |
   | T15 | Gruppe mit `accordion = array('enabled'=>true,'level'=>3,'numbering'=>true,'multiple'=>false,'openFirst'=>false,'expandAll'=>false)`, drei Abschnitte, `accordion_available => true`, Mapping `'__none__'` | genau **ein** Block `modular-blocks/accordion`; `attrs` enthält `headingLevel`=3, `allowMultiple`=false, `openFirst`=false, `showNumbering`=true, `showExpandAll`=false; `innerBlocks` enthält je Abschnitt eine `core/heading` (Ebene 3) gefolgt von dessen Inhalt, also 3 Überschriften insgesamt |
   | T16 | wie T15, aber `accordion_available => false` | **kein** `modular-blocks/accordion`; stattdessen die drei Abschnitte einzeln wie in T13 |
   | T17 | wie T15, aber Mapping `'info-box'` | ein `container-block-designer/container`, dessen `innerBlocks` **genau einen** Block enthält, und zwar das Accordion; `attrs['blockTitle']` ist das `label` der Gruppe |
   | T18 | wie T15, aber `accordion_opt_out => array('h2-uebungen' => true)` für diese Gruppe | **kein** Accordion; Verhalten wie T16 |
   | T19 | erster Abschnitt hat `titleSource === 'h1'` und `blockTitle === 'Meine Seite'`, `page_title => 'Meine Seite'`, Mapping `'__none__'` | die Überschrift wird **unterdrückt**: Der erste Block ist der Absatz, keine `core/heading` mit `Meine Seite` |
   | T20 | zweiter Abschnitt hat ebenfalls `titleSource === 'h1'`, aber `blockTitle === 'Zweites Thema'` | dessen Überschrift **bleibt** als `core/heading` erhalten |
   | T21 | ein Abschnitt, dessen `content` nur `<!-- nur ein Kommentar -->` ist | ein Block `core/freeform`, dessen `innerHTML` das Original-HTML enthält |
   | T22 | `$sections = array()` | `to_post_content()` liefert den leeren String `''` |

7. **Testgruppe C – Markup-Treue gegen die Fixture.** Die Datei
   `tools/fixtures/referenz-markup.html` einlesen. Für jeden der Blocktypen
   `paragraph`, `heading`, `list`, `list-item`, `table` und
   `container-block-designer/container` das **erste** Vorkommen im
   Fixture-Text isolieren (vom öffnenden `<!-- wp:<name>` bis zum
   zugehörigen `<!-- /wp:<name> -->`) und mit der Serializer-Ausgabe für eine
   gleichwertige Eingabe vergleichen. Verglichen wird nach Normalisierung:
   Zeilenumbrüche und Folgen von Leerzeichen **zwischen** Tags auf ein
   einzelnes Leerzeichen reduzieren, Text innerhalb von Tags unverändert
   lassen. Abweichungen müssen den erwarteten und den tatsächlichen String
   nebeneinander ausgeben, damit die Ursache erkennbar ist.
   Ist Abschnitt 3 laut `tools/fixtures/referenz-umgebung.md` nicht in der
   Fixture enthalten, wird der Accordion-Vergleich übersprungen und als
   „übersprungen" gezählt, nicht als bestanden.
8. **Testgruppe D – Delimiter-Bilanz.** Eine Hilfsfunktion
   `pruefe_delimiter_bilanz($markup)` schreiben, die den erzeugten String
   durchläuft und prüft: jedes `<!-- wp:<name> ... -->` ohne abschließendes
   `/-->` hat ein passendes `<!-- /wp:<name> -->`, die Verschachtelung ist
   korrekt (Stapelprinzip), und am Ende ist der Stapel leer. Gegen die
   Ausgaben von T12, T15 und T17 laufen lassen.
9. Harness ausführen: `php tools/test-block-serializer.php`. Es muss mit einem
   Fehler abbrechen, weil `includes/class-cbd-block-serializer.php` fehlt.
   **Das ist der gewollte rote Zustand.**
10. Rot committen: `git add tools/test-block-serializer.php` und
    `git commit -m "AP-1.3: Testharness fuer Block-Serializer (rot)"`, dann
    `git push`.

**Akzeptanzkriterien:**
- [ ] `tools/test-block-serializer.php` enthält alle 23 nummerierten Testfälle
      T1–T23; jeder Fall ist im Quelltext mit seiner Nummer benannt
      (z. B. `// T14: unbekannter Slug`).
- [ ] Die vier Kern-Stubs `serialize_blocks`, `serialize_block`,
      `get_comment_delimited_block_content` und `serialize_block_attributes`
      sind vorhanden und je mit einem Kommentar als wörtliche Kopie samt
      WordPress-Version gekennzeichnet.
- [ ] `php tools/test-block-serializer.php` schlägt fehl (Exit-Code ≠ 0), weil
      die Klasse noch nicht existiert.
- [ ] Der rote Zustand ist committet und gepusht (`git log --oneline -1` zeigt
      den AP-1.3-Commit).
- [ ] `php -l tools/test-block-serializer.php` meldet keinen Syntaxfehler.

**Tests:**
- Smoke-Test: `php -l tools/test-block-serializer.php` → „No syntax errors".
- Prüfschritt: `php tools/test-block-serializer.php` ausführen; die Ausgabe
  (Abbruchgrund) in die Übergabenotiz übernehmen.
- Prüfschritt: Im Quelltext nachzählen, dass die Bezeichner T1 bis T23
  lückenlos vorkommen.

**Übergabenotiz:**

---

### AP-1.4: HTML-Fragment in Kernblöcke umwandeln

**Status:** ☑ erledigt (2026-08-10)
**Umfang:** M
**Modell:** opus (Markup-Treue, DOM-Verarbeitung mit mehreren Fallstricken)
**Abhängigkeiten:** AP-1.3

**Ziel & Kontext:**
Erster Teil des Serializers: die Methode `html_to_blocks($html) : array`, die
ein HTML-Fragment (wie es `CBD_Content_Importer` je Abschnitt im Feld
`content` liefert) in WordPress-Blockarrays umwandelt. Sie bildet die Funktion
`htmlToGutenbergBlocks()` aus `assets/js/content-importer.js` (ab Zeile 208)
nach – mit **einer bewussten Abweichung**: Listen werden nicht mit dem
veralteten Attribut `values` erzeugt, sondern als `core/list` mit
`core/list-item`-Kindern.

Nach diesem AP sind die Testfälle T1–T11 und T23 aus
`tools/test-block-serializer.php` grün; T12–T22 bleiben rot (die macht AP-1.5).

**Betroffene Dateien:**
- `includes/class-cbd-block-serializer.php` (neu)
- `tools/fixtures/referenz-markup.html` (nur lesen – Vorlage für das Zielmarkup)
- `assets/js/content-importer.js` (nur lesen – Referenzverhalten)

**Vorgehen:**
1. Datei mit dem üblichen Kopf anlegen: Dateikommentar (`@package
   ContainerBlockDesigner`, `@since`), danach
   `if (!defined('ABSPATH')) { exit; }`. **Keine** Instanziierung am
   Dateiende – die Klasse hat nur statische Methoden und wird nicht als
   Singleton initialisiert.
2. `tools/fixtures/referenz-markup.html` öffnen und für jeden Kernblocktyp die
   dort tatsächlich gespeicherte HTML-Hülle ablesen (also ob und welche
   Klassen an `<h3>`, `<ul>`, `<ol>`, `<table>`, `<figure>` stehen). **Diese
   abgelesenen Hüllen sind maßgeblich, nicht die Beispiele in diesem Plan.**
   Die abgelesenen Muster als Kommentar in der Klasse festhalten, mit Verweis
   auf die Fixture.
3. `html_to_blocks($html)` implementieren:
   - Leerer oder nur aus Leerraum bestehender Eingabe → leeres Array.
   - `libxml_use_internal_errors(true)` setzen, vorherigen Zustand merken und
     am Ende wiederherstellen; `libxml_clear_errors()` aufrufen.
   - `DOMDocument` mit UTF-8 laden. Damit Umlaute nicht zerfallen und kein
     `<html><body>`-Gerüst entsteht:
     `$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);`
     Schlägt `loadHTML()` fehl oder liefert keine Kindknoten, obwohl die
     Eingabe nicht leer war → leeres Array zurückgeben, damit der Aufrufer in
     AP-1.5 auf `core/freeform` zurückfallen kann.
   - Über die **direkten** Kindelemente des Wurzelknotens iterieren
     (Textknoten auf oberster Ebene, die nur Leerraum sind, überspringen;
     Textknoten mit Inhalt als Absatz behandeln).
   - Zuordnung je Tagname:
     - `h3`–`h6` → `core/heading`, `attrs['level']` = Ziffer aus dem Tagnamen,
       Inhalt = Innen-HTML des Elements
     - `p` → `core/paragraph` (leerer Innentext → Element überspringen)
     - `ul` → `core/list` ohne `ordered`-Attribut, je `li` ein
       `core/list-item`-Kind
     - `ol` → `core/list` mit `attrs['ordered'] = true`, je `li` ein
       `core/list-item`-Kind
     - `table` → `core/table` mit `attrs['head']` und `attrs['body']` in der
       Form `array(array('cells' => array(array('content' => …, 'tag' => 'th'|'td'))))`
     - alles andere → `core/paragraph` mit dem Innen-HTML des Elements
       (Rückfall wie im JavaScript)
   - Innen-HTML eines Knotens über `$knoten->ownerDocument->saveHTML()` je
     Kindknoten ermitteln, nicht über `nodeValue` – sonst gingen `<strong>`
     und `<a>` verloren (Testfall T11).
4. Für jeden erzeugten Block das vollständige Blockarray liefern
   (`blockName`, `attrs`, `innerBlocks`, `innerHTML`, `innerContent`). Bei
   Blöcken mit Kindern (`core/list`) enthält `innerContent` an den
   Kindpositionen `null`; `innerHTML` ist die Verkettung der nicht-`null`-Teile.
5. Eine private Hilfsmethode `block($name, $attrs, $inner_html, $inner_blocks = array(), $inner_content = null)`
   anlegen, die das Blockarray baut und `innerContent` aus `$inner_html`
   ableitet, wenn kein eigenes übergeben wurde. Das hält den Rest der Klasse
   lesbar.
6. Nur PHP-7.4-Syntax verwenden.
7. Harness laufen lassen und iterieren, bis T1–T11 und T23 grün sind.
   **Die Testdatei dabei nicht anfassen.** Hältst du einen Testfall für
   inhaltlich falsch: in der Übergabenotiz begründen und stoppen.

**Akzeptanzkriterien:**
- [ ] `php tools/test-block-serializer.php` meldet die Testfälle T1–T11 und
      T23 als bestanden.
- [ ] `tools/test-block-serializer.php` ist gegenüber AP-1.3 **unverändert**
      (`git diff AP-1.3-Commit -- tools/test-block-serializer.php` ist leer).
- [ ] Listen enthalten `core/list-item`-Kindblöcke; im gesamten Quelltext von
      `includes/class-cbd-block-serializer.php` kommt die Zeichenkette
      `'values'` nicht vor.
- [ ] Die aus der Fixture abgelesenen HTML-Hüllen sind als Kommentar in der
      Klasse dokumentiert.
- [ ] `php -l includes/class-cbd-block-serializer.php` ohne Fehler und
      `php tools/check-php74.php` meldet keine 8.0-Syntax.
- [ ] Datei-Map-Zeile für `includes/class-cbd-block-serializer.php` in der
      Übergabenotiz notiert (die Datei-Map für CDB-Designer entsteht erst in AP-4.1).

**Tests:**
- Smoke-Test: `php tools/test-block-serializer.php` läuft durch und gibt eine
  Zusammenfassung aus (die Fälle T12–T22 dürfen noch rot sein).
- Prüfschritt: Testfall T9 (Umlaute) und T10 (LaTeX) einzeln in der Ausgabe
  bestätigen – das sind die typischen DOMDocument-Fallen.
- Prüfschritt: `php tools/check-php74.php` ausführen und die Ausgabe in die
  Übergabenotiz übernehmen.

**Übergabenotiz:**

---

### AP-1.5: Abschnitte und Stil-Zuweisungen zu `post_content` zusammensetzen

**Status:** ☑ erledigt (2026-08-10)
**Umfang:** M
**Modell:** opus (Abbildungslogik mit mehreren Sonderfällen und Rückfällen)
**Abhängigkeiten:** AP-1.4

**Ziel & Kontext:**
Zweiter Teil des Serializers: `to_block_array()` und `to_post_content()`.
Sie bilden die Funktion `insertBlocks()` aus
`assets/js/content-importer.js` (ab Zeile 309) nach – also die Entscheidung,
welcher Abschnitt in einen Container kommt, welcher ohne Container eingefügt
wird und welche Gruppe zu einem Accordion zusammengefasst wird.

Nach diesem AP sind **alle** Testfälle T1–T23 grün und Phase 1 inhaltlich
abgeschlossen.

**Betroffene Dateien:**
- `includes/class-cbd-block-serializer.php` (ändern)
- `assets/js/content-importer.js` (nur lesen – Referenzverhalten)

**Vorgehen:**
1. `to_block_array(array $sections, array $groups, array $style_mappings, array $optionen = array()) : array`
   implementieren. Optionen mit Vorgabewerten belegen:
   - `accordion_opt_out` → leeres Array
   - `page_title` → leerer String
   - `known_slugs` → `null`; ist der Wert `null`, die aktiven Design-Slugs per
     `$wpdb->get_col("SELECT slug FROM " . CBD_TABLE_BLOCKS . " WHERE status = 'active'")`
     laden. Dieser Zweig darf nur betreten werden, wenn `$wpdb` und die
     Konstante `CBD_TABLE_BLOCKS` existieren – sonst leeres Array annehmen
     (das Harness übergibt immer ein Array und betritt den Zweig nie).
   - `accordion_available` → `null`; ist der Wert `null`, über
     `WP_Block_Type_Registry::get_instance()->is_registered('modular-blocks/accordion')`
     ermitteln, sofern die Klasse existiert, sonst `false`.
2. Gruppen nach `key` in eine Nachschlagetabelle umlegen.
3. Über `$sections` in Dokumentreihenfolge laufen. Je Abschnitt:
   - Gruppenschlüssel bestimmen: `$section['groupKey']`, ersatzweise
     `$section['competence']`.
   - **Accordion-Zweig:** Er greift, wenn `accordion_available` wahr ist, die
     Gruppe eine Direktive mit `enabled === true` hat und
     `accordion_opt_out[$gruppenschluessel]` nicht `true` ist. Dann:
     - Wurde diese Gruppe bereits behandelt, den Abschnitt überspringen
       (die Gruppe wird beim ersten ihrer Abschnitte vollständig eingefügt).
     - Alle Abschnitte derselben Gruppe in Dokumentreihenfolge sammeln.
     - Je Abschnitt eine `core/heading` mit `attrs['level']` = `level` der
       Direktive und dem `blockTitle` als Inhalt erzeugen, danach die Blöcke
       aus `html_to_blocks($section['content'])`; liefert das ein leeres
       Array, stattdessen einen `core/freeform`-Block mit dem Roh-`content`.
     - Einen Block `modular-blocks/accordion` mit den Attributen
       `headingLevel` (= `level`), `allowMultiple` (= `multiple`),
       `openFirst`, `showNumbering` (= `numbering`) und `showExpandAll`
       (= `expandAll`) erzeugen; die gesammelten Blöcke sind seine
       `innerBlocks`. Sein `save()` gibt nur `InnerBlocks.Content` aus –
       deshalb hat er **keine** eigene HTML-Hülle: `innerHTML` ist leer,
       `innerContent` besteht ausschließlich aus `null`-Einträgen, einer je
       Kindblock.
     - Stil der Gruppe ermitteln (siehe nächster Punkt). Ist er `'__none__'`
       oder unbekannt, das Accordion direkt einfügen; sonst als **einzigen**
       Innenblock in einen `container-block-designer/container` mit
       `attrs['blockTitle']` = `label` der Gruppe legen.
     - Gruppe als behandelt markieren, nächster Abschnitt.
   - **Stil ermitteln:** `$style_mappings[$gruppenschluessel]`. Ist der Wert
     leer, gleich `'__none__'` oder **nicht** in `known_slugs` enthalten, gilt
     „ohne Container".
   - **Inhalt umwandeln:** `html_to_blocks($section['content'])`; bei leerem
     Ergebnis ein `core/freeform` mit dem Roh-`content` als einziger Block.
   - **Ohne Container:** Zuerst eine `core/heading` mit `attrs['level'] = 3`
     und dem `blockTitle` als Inhalt anfügen – **außer** die H1-Unterdrückung
     greift (siehe Punkt 4) oder `blockTitle` ist leer. Danach die
     Inhaltsblöcke.
   - **Mit Container:** Einen `container-block-designer/container` mit
     `attrs['selectedBlock']` = Slug und `attrs['blockTitle']` =
     `$section['blockTitle']` erzeugen; die Inhaltsblöcke sind seine
     `innerBlocks`. Die HTML-Hülle **exakt** aus
     `tools/fixtures/referenz-markup.html` übernehmen (der Container hat ein
     statisches `save()`, siehe `ContainerBlockSave` in
     `assets/js/block-editor.js` ab Zeile 319: ein `<div>` mit den Klassen
     `wp-block-container-block-designer-container cbd-container` und dem
     Attribut `data-block="<slug>"`). Maßgeblich ist die Fixture, nicht diese
     Beschreibung.
4. **H1-Unterdrückung:** Die Überschrift eines Abschnitts wird genau dann
   weggelassen, wenn alle drei Bedingungen zutreffen: es ist der **erste**
   Abschnitt der Liste, `$section['titleSource'] === 'h1'`, und der getrimmte
   `blockTitle` stimmt mit dem getrimmten `page_title` überein. Der Inhalt des
   Abschnitts bleibt in jedem Fall erhalten. Weitere H1-Abschnitte behalten
   ihre Überschrift.
5. `to_post_content(...)` implementieren: `to_block_array()` aufrufen und das
   Ergebnis durch `serialize_blocks()` schicken. Bei leerem Blockarray den
   leeren String zurückgeben. Existiert `serialize_blocks()` nicht (headless),
   eine `RuntimeException` mit klarer Meldung werfen – im Harness ist die
   Funktion gestubbt.
6. Nur PHP-7.4-Syntax verwenden.
7. Harness laufen lassen und iterieren, bis **alle** Fälle grün sind.
   **Die Testdatei dabei nicht anfassen.**

**Akzeptanzkriterien:**
- [ ] `php tools/test-block-serializer.php` meldet 0 Fehler und beendet sich
      mit Exit-Code 0.
- [ ] `tools/test-block-serializer.php` ist gegenüber AP-1.3 unverändert
      (`git diff` gegen den AP-1.3-Commit für diese Datei ist leer).
- [ ] Der Accordion-Block wird nur erzeugt, wenn `accordion_available` wahr
      ist (Testfall T16 belegt den Rückfall).
- [ ] Ein Design-Slug, der nicht in `known_slugs` steht, führt nie zu einem
      Container (Testfall T14).
- [ ] `php -l includes/class-cbd-block-serializer.php` ohne Fehler und
      `php tools/check-php74.php` meldet keine 8.0-Syntax.
- [ ] `container-block-designer.php` wurde **nicht** verändert
      (`git diff --name-only` enthält die Datei nicht).

**Tests:**
- Smoke-Test: `php tools/test-block-serializer.php` → „0 Fehler".
- Prüfschritt (Markup-Treue): Die Ausgabe von Testgruppe C in die
  Übergabenotiz übernehmen; falls der Accordion-Vergleich übersprungen wurde,
  das ausdrücklich vermerken.
- Prüfschritt (Delimiter-Bilanz): Testgruppe D muss für T12, T15 und T17
  bestanden sein.
- **Phasen-Integrationstest (nach diesem AP durchzuführen und im Testprotokoll
  als „Phase 1 abgeschlossen" einzutragen):**
  1. `php tools/test-block-serializer.php` → 0 Fehler.
  2. `php tools/check-php74.php` → keine Befunde.
  3. Alle weiteren vorhandenen Harnesse laufen lassen, um Regressionen
     auszuschließen: `php tools/test-design-transfer.php`,
     `php tools/test-icon-library.php`, `php tools/test-icon-value.php`,
     `php tools/test-svg-sanitizer.php`, `php tools/test-icon-manager.php`,
     `php tools/test-icon-scale.php` – jeder ohne Fehler.
  4. Regressionsnachweis am laufenden System: Auf der Testinstallation eine
     Seite im Editor öffnen, Menü „⋮" → „Inhalt importieren (K1/K2/K3)",
     eine bekannte Markdown-Datei importieren. Die Blöcke müssen wie bisher
     entstehen. `debug.log` danach ohne neue Einträge.

**Übergabenotiz:**

---

### AP-1.rev: Unabhängiges Review Phase 1

**Status:** ☐ offen
**Umfang:** M
**Modell:** opus
**Abhängigkeiten:** AP-1.1, AP-1.2, AP-1.3, AP-1.4, AP-1.5 (inkl. Phasen-Integrationstest)

**Ziel & Kontext:**
Unabhängige Qualitätsprüfung der Phase 1 durch einen Agenten, der an keiner
Implementierung beteiligt war. Nur lesend arbeiten (Read/Grep/Glob) – **KEINE
Datei verändern**, auch keine Testdatei ausführen, die Dateien schreibt.
Reines Ausführen der Harnesse zum Nachvollziehen ist erlaubt.

**Betroffene Dateien:**
- alle Dateien der Phase 1 (nur lesen)

**Vorgehen:**
1. Für jedes Implementierungs-AP (AP-1.2 bis AP-1.5): Quelltext gegen dessen
   Akzeptanzkriterien prüfen. Stichproben im Code, nicht nur den
   Übergabenotizen glauben.
2. Besonders prüfen:
   - Ist `tools/test-block-serializer.php` seit dem AP-1.3-Commit unverändert?
     (`git log --oneline -- tools/test-block-serializer.php` und
     `git diff <AP-1.3-Commit>..HEAD -- tools/test-block-serializer.php`)
     Eine nachträgliche Teständerung ist ein **kritischer** Befund.
   - Kommt `'values'` irgendwo in `includes/class-cbd-block-serializer.php` vor?
   - Wird `modular-blocks/accordion` an irgendeiner Stelle ohne die
     Verfügbarkeitsprüfung erzeugt?
   - Wird ein Container mit einem Slug erzeugt, der nicht gegen
     `known_slugs` geprüft wurde?
   - Enthält die Klasse PHP-8.0-Syntax (`match`, `?->`, Named Arguments,
     Union Types, Constructor Property Promotion)?
   - Wurden `includes/class-cbd-content-importer.php`,
     `assets/js/content-importer.js` oder `assets/js/block-editor.js`
     verändert? Das wäre eine Verletzung der Nicht-Ziele.
   - Wurde `container-block-designer.php` verändert? Auch das wäre eine
     Scope-Verletzung (die Verdrahtung gehört in AP-2.1).
3. Phasen-Endzustand prüfen: Meldet
   `php tools/test-block-serializer.php` tatsächlich 0 Fehler?
4. Scope-Check gegen Abschnitt 2 (Nicht-Ziele).
5. Qualitäts-Check: fehlende Eingabeprüfungen, tote Verweise,
   Konventionsverstöße (Debug-Ausgaben ohne `WP_DEBUG`-Gate, englische
   Kommentare, fehlender `ABSPATH`-Schutz).
6. Befunde als Bericht in die Übergabenotiz: je Befund Schweregrad
   (kritisch / mittel / gering), betroffenes AP, Datei und Fundstelle.

**Akzeptanzkriterien:**
- [ ] Jedes Implementierungs-AP der Phase 1 wurde gegen seine
      Akzeptanzkriterien geprüft.
- [ ] Alle Befunde mit Schweregrad, Datei und Fundstelle dokumentiert.
- [ ] Die sieben Punkte aus Schritt 2 sind einzeln mit Ergebnis beantwortet.
- [ ] Keine Datei wurde verändert (`git status` unverändert gegenüber dem
      Stand vor dem Review).

**Tests:**
- entfällt (Review-AP; das Ergebnis ist der Bericht).

**Übergabenotiz:**
**Nur Selbstreview, keine unabhängige Prüfung** (2026-08-10). Die
implementierende Instanz hat auch geprüft — die vom AP geforderte
Unabhängigkeit ist damit **nicht** erfüllt. Das AP bleibt auf ◐, bis ein
frischer Agent oder `/code-review` darübergelaufen ist.

Mechanisch prüfbare Punkte aus Schritt 2, alle mit Beleg:

| Prüfung | Ergebnis |
|---|---|
| `tools/test-block-serializer.php` seit dem AP-1.3-Commit unverändert | ja — `git diff ecfab2c HEAD` für die Datei ist leer |
| `'values'` im Serializer | 0 Fundstellen |
| Accordion ohne Verfügbarkeitsprüfung erzeugt | nein — `nutzt_accordion()` steigt bei `accordion_available === false` sofort aus, Testfall T16 belegt den Rückfall |
| Container mit ungeprüftem Slug | nein — `block_container()` wird nur nach `ermittle_slug()` aufgerufen, das gegen `known_slugs` prüft; Testfall T14 |
| PHP-8.0-Syntax | keine — `php tools/check-php74.php` meldet 557 Dateien kompatibel |
| `ABSPATH`-Schutz in der neuen Datei | vorhanden |
| Nicht-Ziele verletzt | nein — `git diff --name-only 35fdc0f HEAD` enthält weder `class-cbd-content-importer.php` noch `content-importer.js` noch `block-editor.js`; auch `container-block-designer.php` wurde nicht angefasst (die Verdrahtung gehört planmäßig in AP-2.1) |
| Phasen-Endzustand erreicht | ja — 71 Prüfungen, 0 Fehler |

Geänderte Dateien der Phase: `CLAUDE.md`, `docs/PLAN-Seitenimport.md`,
`docs/pruefung-blockmarkup.js`, `docs/pruefung-produktiv.js`,
`includes/class-cbd-block-serializer.php`, `tools/fixtures/*`,
`tools/test-block-serializer.php`. Alle innerhalb des Scopes.

**Was ein unabhängiges Review noch ansehen sollte** (von mir nicht
verlässlich selbst beurteilbar): die Tabellenumwandlung in `block_table()`
(nur T5 deckt sie ab, und in der Fixture kam keine Tabelle vor), sowie das
Verhalten von `lade_html()` bei stark verschachteltem oder kaputtem HTML.

---

### AP-1.doc: Dokumentation Phase 1 aktualisieren

**Status:** ☐ offen
**Umfang:** S
**Modell:** sonnet
**Abhängigkeiten:** AP-1.rev

**Ziel & Kontext:**
Den Stand nach Phase 1 dokumentieren, damit der Serializer ohne Kenntnis
dieses Plans verstanden und erweitert werden kann. Die Datei-Map für
CDB-Designer existiert noch nicht – sie entsteht in AP-4.1. Bis dahin werden
die Zeilen gesammelt.

**Betroffene Dateien:**
- `CLAUDE.md` (ändern) – Abschnitt zum Serializer ergänzen
- `docs/PLAN-Seitenimport.md` (ändern) – Statustabelle und Testprotokoll

**Vorgehen:**
1. Alle Übergabenotizen der Phase 1 durchgehen.
2. In `CLAUDE.md` einen neuen Abschnitt **„Block-Serializer (Markdown →
   post_content)"** direkt nach dem bestehenden Abschnitt „Content-Importer
   (Markdown → Container-Blöcke)" einfügen. Er beschreibt:
   - Zweck und Abgrenzung: Der Content-Importer baut Blöcke im **Editor**
     (JavaScript), der Serializer baut sie **serverseitig** für Seiten ohne
     Editor.
   - Die öffentliche Schnittstelle von `CBD_Block_Serializer` mit den drei
     Methoden und den vier Optionen-Schlüsseln.
   - Die bewusste Abweichung bei Listen (`core/list-item` statt `values`) und
     warum.
   - Die Verfügbarkeitsprüfung für `modular-blocks/accordion` und die
     Slug-Prüfung gegen die aktiven Designs.
   - Die H1-Unterdrückungsregel.
   - **Den wichtigsten Hinweis für spätere Änderungen:** Das Zielmarkup steht
     in `tools/fixtures/referenz-markup.html` und stammt aus einer echten
     Editor-Speicherung. Ändert sich die WordPress-Version oder das `save()`
     des Container-Blocks, muss die Fixture neu erhoben werden (Vorgehen aus
     AP-1.2 dieses Plans kurz zusammenfassen), sonst erzeugt der Serializer
     ungültige Blöcke.
   - Der Testaufruf `php tools/test-block-serializer.php` mit einer Zeile,
     was er abdeckt.
3. Statustabelle (Abschnitt 8) und Testprotokoll (Abschnitt 9) dieses Plans
   auf den Stand bringen; „Letzte Aktualisierung" im Dateikopf setzen.
4. Die in den Übergabenotizen von AP-1.4 und AP-1.5 notierten
   Datei-Map-Zeilen in der eigenen Übergabenotiz zusammenführen, damit AP-4.1
   sie übernehmen kann.

**Akzeptanzkriterien:**
- [ ] `CLAUDE.md` enthält den Abschnitt „Block-Serializer (Markdown →
      post_content)" mit allen sechs oben genannten Punkten.
- [ ] Der Hinweis zur Neuerhebung der Fixture ist enthalten und nennt die
      Datei `tools/fixtures/referenz-markup.html` beim Namen.
- [ ] Statustabelle: alle APs der Phase 1 stehen auf ☑.
- [ ] Testprotokoll: je ein Eintrag für AP-1.1 bis AP-1.5 sowie einer für
      „Phase 1 abgeschlossen".
- [ ] Kein Verweis in `CLAUDE.md` zeigt auf eine nicht existierende Datei
      (die genannten Pfade stichprobenartig prüfen).
- [ ] Datei-Map-Zeilen für die drei neuen Dateien in der Übergabenotiz
      gesammelt.

**Tests:**
- Stichprobe: Zwei in `CLAUDE.md` neu genannte Dateipfade gegen den echten
  Dateibestand prüfen.
- Prüfschritt: `php tools/test-block-serializer.php` ein letztes Mal
  ausführen – 0 Fehler.

**Übergabenotiz:**

---

**Phasenabschluss 1:** Nach AP-1.doc den Branch `phase-1-block-serializer`
nach `main` mergen (`git checkout main && git merge --no-ff phase-1-block-serializer`)
und `main` pushen. Erst danach Phase 2 beginnen.

---

### Phase 2: Importseite unter dem Seitenmanager (Spur A, Repository CDB-Designer)

Arbeitsverzeichnis wie Phase 1. Branch dieser Phase: `phase-2-seitenimport`
(wird in AP-2.1 aus dem gemergten `main` angelegt).

**Feste Namen, auf die sich alle APs dieser Phase verlassen** (nicht abweichen,
sonst passen JavaScript, PHP und CSS nicht zusammen):

| Gegenstand | Wert |
|---|---|
| Menü-Slug der Importseite | `cbd-page-import` |
| Elternmenü | `page-manager`, Rückfall `container-block-designer` |
| Capability | `edit_pages` |
| JS-Objekt (lokalisiert) | `cbdPageImport` |
| Nonce zum Parsen (bestehend) | Aktion `cbd_content_import`, Feld `nonceParse` |
| Nonce für Import und Titelprüfung (neu) | Aktion `cbd_page_import`, Feld `nonceImport` |
| AJAX-Aktion Parsen (bestehend, wiederverwendet) | `cbd_parse_import_file` |
| AJAX-Aktion Titelprüfung (neu) | `cbd_check_page_titles` |
| AJAX-Aktion Import (neu) | `cbd_import_pages` |
| Wurzel-Element im Markup | `<div id="cbd-page-import-app">` |
| CSS-Klassenpräfix | `cbd-pi-` |

**Vereinbarte CSS-Klassen** (AP-2.1 legt die Hüllen an, AP-2.2/2.3 füllen sie,
AP-2.5 gestaltet sie):
`cbd-pi-dropzone`, `cbd-pi-dropzone--aktiv`, `cbd-pi-dateiliste`,
`cbd-pi-datei`, `cbd-pi-datei--dublette`, `cbd-pi-datei--abgewaehlt`,
`cbd-pi-warnung`, `cbd-pi-gruppen`, `cbd-pi-gruppe`,
`cbd-pi-gruppe--offen`, `cbd-pi-badge`, `cbd-pi-sammelzuweisung`,
`cbd-pi-fortschritt`, `cbd-pi-fortschritt-balken`, `cbd-pi-ergebnis`,
`cbd-pi-ergebnis-zeile`, `cbd-pi-ergebnis-zeile--fehler`, `cbd-pi-aktionen`,
`cbd-pi-status`.

---

### AP-2.1: Untermenü, Seitengerüst und Asset-Einbindung

**Status:** ☑ erledigt (2026-08-10)
**Umfang:** M
**Modell:** sonnet
**Abhängigkeiten:** AP-1.doc (Phase 1 gemergt)

**Ziel & Kontext:**
Die Importseite bekommt ihren Platz in der WordPress-Oberfläche: ein
Untermenü **unter dem Seitenmanager**. Der Seitenmanager ist ein
Theme-Werkzeug (`Theme/includes/admin/page-manager.php`), das ein
Top-Level-Menü mit dem Slug `page-manager` anlegt.

> **Korrektur vom 2026-08-10 (gemessen, nicht vermutet).** Die ursprünglich
> hier beschriebene Falle — `add_submenu_page()` scheitere stillschweigend,
> wenn das Elternmenü noch nicht existiert — **stimmt nicht**. Der Blick in
> `wp-admin/includes/plugin.php` zeigt: Die Funktion gibt `false`
> ausschließlich zurück, wenn `current_user_can($capability)` scheitert. Das
> Elternmenü wird nie geprüft; der Eintrag landet einfach in
> `$submenu[$parent_slug]`, die Registrierungsreihenfolge ist gleichgültig.
> Die Gegenprobe auf der Testinstallation bestätigt das: Auch mit
> Priorität 10 erscheint der Eintrag korrekt unter dem Seitenmanager.
>
> **Priorität 20 bleibt trotzdem richtig**, aber aus einem anderen Grund:
> Nicht `add_submenu_page()` braucht das Elternmenü, sondern die eigene
> Prüfung `isset($GLOBALS['admin_page_hooks']['page-manager'])`, die über
> Elternmenü oder Rückfall entscheidet. Liefe sie zu früh, griffe der
> Rückfall grundlos und der Eintrag landete unter „Container Designer",
> obwohl der Seitenmanager vorhanden ist. Die Priorität sichert also die
> Fallunterscheidung ab, nicht die Registrierung.

Für dieses AP heißt das: Priorität 20 setzen und prüfen, ob das Elternmenü
existiert — aber das Akzeptanzkriterium lautet „Eintrag erscheint **unter dem
Seitenmanager**", nicht bloß „Eintrag erscheint irgendwo". Denn genau die
Verwechslung würde ein zu früh greifender Rückfall erzeugen.

In diesem AP entsteht nur das Gerüst: Menü, leere Seite mit den vereinbarten
Hüll-Elementen, Asset-Einbindung und die Verdrahtung des Serializers im
Plugin-Bootstrap. Die Logik folgt in AP-2.2 bis AP-2.4.

**Betroffene Dateien:**
- `includes/class-cbd-page-importer.php` (neu)
- `admin/page-import.php` (neu)
- `assets/js/page-importer.js` (neu, zunächst nur Grundgerüst)
- `assets/css/page-importer.css` (neu, zunächst leer bis auf den Dateikopf)
- `container-block-designer.php` (ändern – zwei `require_once`)
- `includes/class-cbd-admin.php` (nur lesen – Muster der Menüregistrierung)

**Vorgehen:**
1. `git checkout main && git pull && git checkout -b phase-2-seitenimport`,
   danach `git push -u origin phase-2-seitenimport`.
2. `includes/class-cbd-page-importer.php` anlegen. Aufbau nach dem Muster von
   `includes/class-cbd-content-importer.php`: Dateikommentar,
   `if (!defined('ABSPATH')) { exit; }`, Klasse `CBD_Page_Importer` als
   Singleton mit `private static $instance`, `public static function
   get_instance()`, privatem Konstruktor, und am Dateiende
   `CBD_Page_Importer::get_instance();`.
3. Im Konstruktor registrieren:
   - `add_action('admin_menu', array($this, 'menue_registrieren'), 20);`
   - `add_action('admin_enqueue_scripts', array($this, 'assets_einbinden'));`
   - die beiden AJAX-Aktionen als Platzhalter-Methoden, die vorerst
     `wp_send_json_error(array('message' => 'Noch nicht implementiert'))`
     zurückgeben (werden in AP-2.2 und AP-2.4 gefüllt):
     `add_action('wp_ajax_cbd_check_page_titles', array($this, 'ajax_titel_pruefen'));`
     `add_action('wp_ajax_cbd_import_pages', array($this, 'ajax_seiten_importieren'));`
4. `menue_registrieren()` implementieren:
   ```php
   $eltern = isset($GLOBALS['admin_page_hooks']['page-manager'])
       ? 'page-manager'
       : 'container-block-designer';

   $hook = add_submenu_page(
       $eltern,
       __('Seiten aus Markdown importieren', 'container-block-designer'),
       __('Seiten importieren', 'container-block-designer'),
       'edit_pages',
       'cbd-page-import',
       array($this, 'seite_ausgeben')
   );

   if ($hook) {
       $this->hook_suffix = $hook;
   }
   ```
   `$hook_suffix` als private Eigenschaft anlegen (Vorgabe `''`). Schlägt die
   Registrierung fehl (`$hook === false`), einen Log-Eintrag über
   `error_log()` schreiben – dieser Fehlerfall wird bewusst **nicht** hinter
   `WP_DEBUG` versteckt, weil er sonst unsichtbar bliebe.
5. `assets_einbinden($hook)` implementieren: sofort zurückkehren, wenn
   `$hook !== $this->hook_suffix` oder `$this->hook_suffix === ''`. Sonst:
   - `wp_enqueue_script('cbd-page-importer', CBD_PLUGIN_URL . 'assets/js/page-importer.js', array('wp-i18n'), CBD_VERSION, true);`
   - `wp_localize_script('cbd-page-importer', 'cbdPageImport', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonceParse' => wp_create_nonce('cbd_content_import'),
        'nonceImport' => wp_create_nonce('cbd_page_import'),
     ));`
     Der Nonce `cbd_content_import` ist derselbe, den
     `CBD_Content_Importer::ajax_parse_import_file()` per
     `check_ajax_referer('cbd_content_import', 'nonce')` erwartet – so lässt
     sich der bestehende Parser-Endpunkt ohne Änderung mitbenutzen.
   - `wp_enqueue_style('cbd-page-importer', CBD_PLUGIN_URL . 'assets/css/page-importer.css', array(), CBD_VERSION);`
6. `seite_ausgeben()` implementieren: Capability erneut prüfen
   (`if (!current_user_can('edit_pages')) { wp_die(__('Sie haben keine Berechtigung für diese Seite.', 'container-block-designer')); }`),
   danach `require CBD_PLUGIN_DIR . 'admin/page-import.php';`.
7. `admin/page-import.php` als View anlegen (mit `ABSPATH`-Schutz). Inhalt:
   - `<div class="wrap">` mit `<h1>Seiten aus Markdown importieren</h1>`
   - ein erklärender Absatz: je Datei entsteht eine Seite als **Entwurf** auf
     oberster Ebene; der Seitentitel ist die erste `# `-Zeile.
   - `<div id="cbd-page-import-app">` mit den leeren Hüllen in dieser
     Reihenfolge und mit genau diesen IDs:
     `<div class="cbd-pi-dropzone" id="cbd-pi-dropzone">` (darin ein
     `<input type="file" id="cbd-pi-dateiauswahl" multiple accept=".md,.txt">`
     und ein Beschriftungstext),
     `<div class="cbd-pi-dateiliste" id="cbd-pi-dateiliste"></div>`,
     `<div class="cbd-pi-gruppen" id="cbd-pi-gruppen"></div>`,
     `<div class="cbd-pi-fortschritt" id="cbd-pi-fortschritt" hidden></div>`,
     `<div class="cbd-pi-ergebnis" id="cbd-pi-ergebnis" hidden></div>`,
     `<div class="cbd-pi-aktionen" id="cbd-pi-aktionen"></div>`
   - Ein Hinweis-Absatz, wenn keine aktiven Block-Designs existieren: über
     `$wpdb->get_var("SELECT COUNT(*) FROM " . CBD_TABLE_BLOCKS . " WHERE status = 'active'")`
     abfragen; bei 0 einen `notice notice-warning` ausgeben, dass der Import
     dann ohne Container erfolgt.
8. `assets/js/page-importer.js` als IIFE-Grundgerüst anlegen:
   `(function () { 'use strict'; if (typeof window.cbdPageImport === 'undefined') { return; } /* AP-2.2 und AP-2.3 füllen dies */ })();`
   Debug-Ausgaben ausschließlich hinter `if (window.cbdDebug) { console.log(...); }`.
9. `assets/css/page-importer.css` mit Dateikommentar anlegen (Inhalt kommt in
   AP-2.5).
10. In `container-block-designer.php` in der Methode `load_dependencies()`
    (dort stehen bereits die `require_once`-Zeilen, u. a. für
    `includes/class-cbd-content-importer.php` bei Zeile ~126) **zwei** Zeilen
    ergänzen:
    ```php
    require_once CBD_PLUGIN_DIR . 'includes/class-cbd-block-serializer.php';
    require_once CBD_PLUGIN_DIR . 'includes/class-cbd-page-importer.php';
    ```
    Reihenfolge beachten: der Serializer zuerst.

**Akzeptanzkriterien:**
- [ ] Im WordPress-Backend der Testinstallation erscheint unter dem
      Menüpunkt **Seitenmanager** der Untereintrag **„Seiten importieren"**.
      (Das ist der Nachweis gegen die Falle mit der Menüpriorität — reicht ein
      Screenshot nicht, genügt die Bestätigung, dass die Seite über den
      Menüeintrag erreichbar ist, nicht nur über die direkte URL.)
- [ ] Der Aufruf von `wp-admin/admin.php?page=cbd-page-import` zeigt die
      Überschrift und alle sechs Hüll-Elemente (im Seitenquelltext nach den
      IDs `cbd-pi-dropzone`, `cbd-pi-dateiliste`, `cbd-pi-gruppen`,
      `cbd-pi-fortschritt`, `cbd-pi-ergebnis`, `cbd-pi-aktionen` suchen).
- [ ] Im Seitenquelltext dieser Seite steht ein `var cbdPageImport = {…}` mit
      den Schlüsseln `ajaxUrl`, `nonceParse` und `nonceImport`.
- [ ] `page-importer.js` und `page-importer.css` werden **nur** auf dieser
      Seite geladen: auf `wp-admin/index.php` und auf der Seitenmanager-Seite
      selbst tauchen sie im Quelltext **nicht** auf.
- [ ] Ein Benutzer mit der Rolle **Block-Redakteur** sieht den Menüeintrag und
      kann die Seite öffnen (die Rolle hat `edit_pages`).
- [ ] Ein Benutzer mit der Rolle **Abonnent** bekommt beim direkten Aufruf der
      URL die Meldung „Sie haben keine Berechtigung für diese Seite." und sieht
      keinen Menüeintrag.
- [ ] `container-block-designer.php` enthält genau die zwei neuen
      `require_once`-Zeilen; das Plugin lässt sich deaktivieren und wieder
      aktivieren, ohne dass ein Fehler erscheint.
- [ ] `php -l` für alle drei geänderten/neuen PHP-Dateien ohne Fehler,
      `php tools/check-php74.php` ohne Befunde.
- [ ] Datei-Map-Zeilen für die vier neuen Dateien in der Übergabenotiz notiert.

**Tests:**
- Smoke-Test: Plugin auf der Testinstallation deaktivieren und wieder
  aktivieren → keine Fehlermeldung, `debug.log` ohne neue Einträge.
- Prüfschritt (Menüpriorität): In `includes/class-cbd-page-importer.php`
  belegen, dass `add_action('admin_menu', …, 20)` mit Priorität 20 registriert
  ist. Zusätzlich gegenprüfen: Priorität testweise auf 10 setzen, Backend neu
  laden → der Untereintrag verschwindet unter dem Seitenmanager; danach
  **zurück auf 20 setzen**. Beobachtung in der Übergabenotiz festhalten.
- Prüfschritt (Rückfall): In der Testinstallation kurzzeitig auf ein anderes
  Theme wechseln (das den Seitenmanager nicht mitbringt) → der Eintrag
  erscheint stattdessen unter „Container Designer". Danach zurückwechseln.
- Prüfschritt (Regression Blockimporter): Eine Seite im Editor öffnen, Menü
  „⋮" → „Inhalt importieren (K1/K2/K3)" → der Dialog öffnet sich wie bisher.
- Log-Check: `debug.log` nach allen Schritten ohne neue Notices/Warnings.

**Übergabenotiz:**

---

### AP-2.2: Dateiauswahl, Parsen je Datei und Dublettenprüfung

**Status:** ☑ erledigt (2026-08-10)
**Umfang:** M
**Modell:** sonnet
**Abhängigkeiten:** AP-2.1

**Ziel & Kontext:**
Schritt 1 der Importseite: Der Nutzer wählt mehrere Markdown-Dateien aus oder
zieht sie auf die Ablagefläche. Jede Datei wird im Browser gelesen (kein
Upload zum Server), ihr Titel ermittelt und ihr Inhalt über den **bestehenden**
AJAX-Endpunkt `cbd_parse_import_file` geparst. Anschließend prüft der Server
in einem Aufruf, welche der Titel bereits als Seite existieren, und die Liste
zeigt entsprechende Warnungen.

Der bestehende Endpunkt liegt in
`includes/class-cbd-content-importer.php`, Methode
`ajax_parse_import_file()`. Er erwartet per POST: `action=cbd_parse_import_file`,
`nonce` (Aktion `cbd_content_import`) und `content` (der Markdown-Rohtext).
Er antwortet mit `{success: true, data: {sections, grouped, groups, stats}}`.
Diese Datei wird **nicht** verändert.

**Betroffene Dateien:**
- `assets/js/page-importer.js` (ändern)
- `includes/class-cbd-page-importer.php` (ändern – Methode `ajax_titel_pruefen()` füllen)

**Vorgehen:**
1. In `includes/class-cbd-page-importer.php` die Methode
   `ajax_titel_pruefen()` implementieren:
   - `check_ajax_referer('cbd_page_import', 'nonce');`
   - `if (!current_user_can('edit_pages')) { wp_send_json_error(array('message' => __('Keine Berechtigung', 'container-block-designer'))); }`
   - Titel entgegennehmen: `$titel = isset($_POST['titles']) ? (array) wp_unslash($_POST['titles']) : array();`
     Auf höchstens 200 Einträge begrenzen, jeden durch `sanitize_text_field()`
     schicken, leere verwerfen.
   - Für jeden Titel prüfen, ob eine Seite mit genau diesem Titel existiert:
     ```php
     $treffer = get_posts(array(
         'post_type'        => 'page',
         'title'            => $einzeltitel,
         'post_status'      => array('publish', 'draft', 'pending', 'private'),
         'numberposts'      => 1,
         'fields'           => 'ids',
         'suppress_filters' => false,
     ));
     ```
     **Nicht** `get_page_by_title()` verwenden – seit WordPress 6.2 veraltet.
   - Antwort: `wp_send_json_success(array('dubletten' => array(<titel> => array('id' => …, 'editLink' => get_edit_post_link($id, 'raw')))))`.
     Titel ohne Treffer tauchen in der Antwort nicht auf.
2. In `assets/js/page-importer.js` einen Zustandsspeicher anlegen, z. B.
   `var dateien = [];` mit je Eintrag:
   `{ dateiname, titel, rohtext, sections, groups, stats, dublette: null, ausgewaehlt: true, fehler: null }`.
   Dieser Speicher wird auch von AP-2.3 und AP-2.4 genutzt.
3. Dateiaufnahme über zwei Wege, beide münden in dieselbe Funktion
   `dateienAufnehmen(fileList)`:
   - `change`-Ereignis auf `#cbd-pi-dateiauswahl`
   - `dragover`/`dragleave`/`drop` auf `#cbd-pi-dropzone`; bei `dragover`
     `event.preventDefault()` aufrufen (sonst öffnet der Browser die Datei) und
     die Klasse `cbd-pi-dropzone--aktiv` setzen, bei `dragleave` und `drop`
     wieder entfernen.
4. `dateienAufnehmen(fileList)`:
   - Dateien mit einer Endung außer `.md` und `.txt` überspringen und in der
     Liste als Fehlerzeile ausweisen.
   - Je Datei ein `FileReader` mit `readAsText(file, 'UTF-8')`.
   - Aus dem Text den Titel ermitteln: erste Zeile, die auf
     `/^#[ \t]+(.+?)[ \t]*$/m` passt, davon Gruppe 1, getrimmt. Findet sich
     keine, den Dateinamen ohne Endung verwenden. Wird der Titel dadurch leer,
     `Unbenannt` verwenden.
   - Den Rohtext im Zustandsspeicher behalten – AP-2.4 schickt ihn erneut zum
     Server, der dort neu parst.
   - Den Rohtext an `cbd_parse_import_file` schicken (`FormData` mit `action`,
     `nonce` = `cbdPageImport.nonceParse`, `content`). Antwort in den
     Zustandsspeicher übernehmen. Bei `success: false` die Datei als
     Fehlerzeile markieren und den Rest weiterverarbeiten – eine kaputte
     Datei darf den Durchlauf nicht abbrechen.
   - Die Anfragen sequenziell oder mit höchstens vier gleichzeitigen Anfragen
     abarbeiten, damit ein Ordner mit 40 Dateien den Server nicht überfährt.
   - Fortschritt währenddessen in `#cbd-pi-fortschritt` anzeigen
     („Datei 7 von 23 gelesen").
5. Nach dem Parsen aller Dateien **einen** Aufruf an `cbd_check_page_titles`
   mit allen Titeln senden und die Treffer in den Zustandsspeicher
   (`dublette`) übernehmen.
6. Die Dateiliste in `#cbd-pi-dateiliste` rendern. Je Datei eine Zeile
   `div.cbd-pi-datei` mit:
   - Auswahl-Checkbox (vorbelegt aktiv), steuert `ausgewaehlt`; bei
     abgewählter Datei zusätzlich die Klasse `cbd-pi-datei--abgewaehlt`
   - Dateiname und der erkannte Titel
   - Anzahl gefundener Abschnitte (`stats.total`)
   - bei Dublette: Klasse `cbd-pi-datei--dublette` und ein Hinweis
     `span.cbd-pi-warnung` mit dem Text „Eine Seite mit diesem Titel existiert
     bereits" und einem Link „Vorhandene Seite ansehen" auf `editLink`
     (`target="_blank"`, `rel="noopener"`)
   - bei Fehler: Klasse `cbd-pi-datei--dublette` nicht setzen, stattdessen die
     Fehlermeldung anzeigen und die Checkbox deaktivieren
7. Über der Liste eine Zusammenfassung ausgeben: „N Dateien geladen, davon M
   mit bereits vorhandenem Titel". Gibt es Dubletten, zusätzlich einen Satz:
   „Dubletten werden trotzdem als neuer Entwurf angelegt. Zum Überspringen die
   Datei abwählen."
8. Sämtliche in den DOM geschriebenen Werte, die aus Dateiinhalten stammen
   (Titel, Dateiname, Fehlermeldungen), über `textContent` setzen oder
   maskieren – **nie** per `innerHTML` mit ungeprüften Werten. Ein
   Markdown-Titel wie `# <img src=x onerror=alert(1)>` darf im Backend kein
   Skript ausführen.

**Akzeptanzkriterien:**
- [ ] Die Auswahl von fünf `.md`-Dateien füllt die Liste mit fünf Zeilen, je
      mit Dateiname, erkanntem Titel und Abschnittszahl.
- [ ] Der Titel wird aus der ersten `# `-Zeile gelesen; eine Datei ohne
      `# `-Zeile bekommt den Dateinamen ohne Endung als Titel.
- [ ] Eine Datei, deren Titel bereits als Seite existiert, wird mit der Klasse
      `cbd-pi-datei--dublette` und einem Warnhinweis samt Link ausgewiesen.
- [ ] Die Checkbox einer Zeile schaltet `ausgewaehlt` um; abgewählte Zeilen
      tragen die Klasse `cbd-pi-datei--abgewaehlt`.
- [ ] Ziehen und Ablegen von Dateien auf `#cbd-pi-dropzone` führt zum selben
      Ergebnis wie die Auswahl über den Dateidialog; der Browser öffnet die
      abgelegte Datei nicht.
- [ ] Eine Datei mit unsinnigem Inhalt (z. B. leere Datei) blockiert den
      Durchlauf nicht: Die übrigen Dateien werden trotzdem geladen.
- [ ] Ein Markdown-Titel mit HTML (`# <img src=x onerror=alert(1)>`) erscheint
      als Text in der Liste; es öffnet sich kein Dialogfenster.
- [ ] `ajax_titel_pruefen()` verwendet `get_posts()` und **nicht**
      `get_page_by_title()`; im Quelltext kommt `get_page_by_title` nicht vor.
- [ ] `ajax_titel_pruefen()` prüft Nonce **und** Capability, bevor es Daten
      liest.
- [ ] `php tools/check-php74.php` ohne Befunde.

**Tests:**
- Smoke-Test: Importseite öffnen, drei Markdown-Dateien auswählen → drei
  Zeilen erscheinen, Browser-Konsole ohne Fehler.
- Prüfschritt (Dublette): Eine der drei Dateien so benennen, dass ihre
  `# `-Zeile dem Titel einer existierenden Seite entspricht → Warnhinweis
  erscheint, der Link führt zur vorhandenen Seite.
- Prüfschritt (Rechte): In der Browser-Konsole
  `fetch(cbdPageImport.ajaxUrl, {method:'POST', body:new URLSearchParams({action:'cbd_check_page_titles', nonce:'falsch', 'titles[]':'Test'})}).then(r=>r.text()).then(console.log)`
  → die Antwort ist `-1` oder ein Fehler, keine Dublettenliste.
- Prüfschritt (XSS): Eine Testdatei mit der ersten Zeile
  `# <img src=x onerror=alert(1)>` laden → kein Dialogfenster, der Text steht
  wörtlich in der Liste.
- Log-Check: `debug.log` ohne neue Einträge.

**Übergabenotiz:**

---

### AP-2.3: Zusammengeführter Stil-Dialog über alle Dateien

**Status:** ☑ erledigt (2026-08-10)
**Umfang:** M
**Modell:** sonnet
**Abhängigkeiten:** AP-2.2

**Ziel & Kontext:**
Schritt 2 der Importseite: Statt für jede Datei einen eigenen Dialog zu
zeigen, werden die H2-Gruppen **aller** geladenen Dateien zu einer Liste
zusammengefasst. Jede Gruppe bekommt genau eine Zuweisungszeile; die
Zuweisung gilt für alle Dateien.

Die Gruppen kommen aus der Antwort von `cbd_parse_import_file` (Feld
`groups`). Jede Gruppe hat `key`, `label`, `count`, `suggestedStyle`
(gesetzt nur bei exakter Namensgleichheit mit einem Block-Design),
`similarStyle` (unscharfer Treffer, nur Hinweis), `matchedBy`,
`hasSubheadings` und `accordion` (`null` oder ein Objekt mit `enabled`,
`level`, `numbering`, `multiple`, `openFirst`, `expandAll`).

Die Liste der verfügbaren Block-Designs liefert der bestehende Endpunkt
`cbd_get_style_mappings` (in `includes/class-cbd-content-importer.php`,
Methode `ajax_get_style_mappings()`, Nonce-Aktion `cbd_content_import`). Er
antwortet mit `{styles: [{value, label}], suggestions: {...}, hasStyles: bool}`.
Auch diese Datei wird nicht verändert.

**Betroffene Dateien:**
- `assets/js/page-importer.js` (ändern)
- `includes/class-cbd-page-importer.php` (ändern – die Lokalisierung in
  `assets_einbinden()` um den Schlüssel `accordionVerfuegbar` erweitern,
  siehe Schritt 9. **Nur diese eine Zeile** – der Rest der Datei gehört zu
  AP-2.1 und AP-2.4.)

**Vorgehen:**
1. Beim Laden der Seite einmalig `cbd_get_style_mappings` aufrufen
   (`nonce` = `cbdPageImport.nonceParse`) und die Stilliste merken. Die Liste
   um den Eintrag
   `{ value: '__none__', label: '— ohne Container (nur Inhalt) —' }`
   ergänzen. Die Konstante `'__none__'` entspricht `NO_CONTAINER` in
   `assets/js/content-importer.js` und dem Sonderwert, den der Serializer aus
   Phase 1 erwartet.
2. Nach dem Parsen aller Dateien (Ende von AP-2.2) die Gruppen
   zusammenführen: über alle Dateien und deren `groups` laufen und in einer
   Tabelle nach `key` sammeln. Je Schlüssel festhalten:
   - `label` – das der ersten Fundstelle
   - `count` – Summe über alle Dateien
   - `dateien` – Anzahl der Dateien, in denen die Gruppe vorkommt
   - `suggestedStyle` – gesetzt, sobald **eine** Datei einen exakten Treffer
     meldet
   - `similarStyle` – erster gefundener Wert, falls kein exakter Treffer
   - `accordion` – die erste gefundene Direktive
   Reihenfolge: Auftreten über die Dateien hinweg, Gruppen mit dem Schlüssel
   `other` zuletzt.
3. Zuweisungen vorbelegen: `zuweisungen[key] = gruppe.suggestedStyle || '__none__'`.
   **Nur exakte Treffer werden vorbelegt** – unscharfe Treffer erscheinen
   ausschließlich als Hinweistext. Das entspricht dem Verhalten des
   Blockimporters und ist eine bewusste Entscheidung: Der Nutzer soll eine
   ungefähre Zuordnung selbst bestätigen.
4. Für Gruppen mit `accordion.enabled === true` zusätzlich
   `accordionAuswahl[key] = true` vorbelegen.
5. `#cbd-pi-gruppen` rendern. Je Gruppe eine Zeile `div.cbd-pi-gruppe` mit:
   - `span.cbd-pi-badge` – bei den Schlüsseln `k1`, `k2`, `k3` die Kürzel
     „K1"/„K2"/„K3", bei `sources` „📚", bei `other` „?", sonst die ersten
     zwei Buchstaben des Labels in Großschreibung
   - Beschriftung `<Label> (<count> Blöcke in <dateien> Datei(en))`
   - ein `<select>` mit allen Stilen; ausgewählt ist `zuweisungen[key]`
   - ein Hilfetext:
     - bei `suggestedStyle`: „automatisch zugeordnet (Name stimmt überein)"
     - sonst bei `similarStyle`: „kein exakt gleichnamiges Design — ähnlich:
       „<Label des ähnlichen Designs>" (bitte selbst zuweisen)"
     - sonst: „kein gleichnamiges Design gefunden — bitte selbst zuweisen"
     - zusätzlich bei `hasSubheadings`: „ · ###-Unterabschnitte erkannt"
   - bei Gruppen mit Accordion-Direktive: ein Kontrollkästchen „Als Accordion
     importieren" (vorbelegt aktiv) plus der Hinweis „Wird als Accordion
     importiert – <count> Klappzeilen"
   - Zeilen, deren Zuweisung `'__none__'` ist, bekommen zusätzlich die Klasse
     `cbd-pi-gruppe--offen`
6. Über den Zeilen eine Sammelzuweisung `div.cbd-pi-sammelzuweisung` anzeigen,
   solange mindestens eine Gruppe offen ist: ein `<select>` mit den echten
   Block-Designs (ohne `'__none__'`) und ein Knopf „Allen offenen Gruppen
   zuweisen", der alle Zeilen mit `'__none__'` auf den gewählten Wert setzt.
7. In `#cbd-pi-aktionen` eine Statuszeile `span.cbd-pi-status` mit
   „<zugewiesen>/<gesamt> Gruppen zugewiesen" ausgeben; sind Gruppen offen,
   den Zusatz „ — Rest wird ohne Container eingefügt" anhängen. Daneben den
   Knopf **„Seiten anlegen"** (`button.button.button-primary`,
   `id="cbd-pi-import-start"`), deaktiviert, solange keine Datei ausgewählt
   ist. Der Knopf bekommt seine Funktion in AP-2.4.
8. Ist die Antwort von `cbd_get_style_mappings` `hasStyles: false`, oberhalb
   der Gruppenliste einen Hinweis ausgeben: „Es sind noch keine Block-Designs
   angelegt. Der Inhalt wird ohne Container eingefügt und kann später
   Containern zugewiesen werden."
9. Ist mindestens eine Accordion-Direktive vorhanden, aber der Blocktyp
   `modular-blocks/accordion` auf der Installation nicht verfügbar, einen
   Warnhinweis ausgeben. Die Verfügbarkeit ist auf einer Nicht-Editor-Seite
   über `wp.blocks` **nicht** ermittelbar; sie wird deshalb serverseitig
   bestimmt: `includes/class-cbd-page-importer.php` ergänzt die Lokalisierung
   in `assets_einbinden()` um den Schlüssel
   `'accordionVerfuegbar' => (class_exists('WP_Block_Type_Registry') && WP_Block_Type_Registry::get_instance()->is_registered('modular-blocks/accordion'))`.
   Ist der Wert `false`, die Kontrollkästchen deaktivieren und den Hinweis
   zeigen, dass die betroffenen Gruppen wie gewohnt importiert werden.

**Akzeptanzkriterien:**
- [ ] Bei fünf Dateien mit teils gleichen H2-Überschriften erscheint jede
      Gruppe **genau einmal**; die Blockzahl ist die Summe über alle Dateien
      und die Dateizahl wird genannt.
- [ ] Eine Gruppe, deren H2 exakt dem Namen oder Slug eines aktiven
      Block-Designs entspricht, ist vorbelegt und trägt den Hilfetext
      „automatisch zugeordnet (Name stimmt überein)".
- [ ] Eine Gruppe mit nur unscharfem Treffer ist **nicht** vorbelegt (das
      `<select>` steht auf „— ohne Container (nur Inhalt) —") und nennt das
      ähnliche Design im Hilfetext.
- [ ] Der Knopf „Allen offenen Gruppen zuweisen" setzt alle Zeilen mit
      `'__none__'` auf den gewählten Stil und lässt bereits zugewiesene Zeilen
      unverändert.
- [ ] Die Statuszeile zählt korrekt und aktualisiert sich bei jeder Änderung
      eines `<select>`.
- [ ] Eine Gruppe mit `<!-- accordion: … -->` zeigt Hinweis und
      Kontrollkästchen; ist `accordionVerfuegbar` false, ist das Kästchen
      deaktiviert und ein Warnhinweis sichtbar.
- [ ] Browser-Konsole ohne Fehler.

**Tests:**
- Smoke-Test: Drei Markdown-Dateien mit unterschiedlichen H2-Überschriften
  laden → Gruppenliste erscheint, Statuszeile stimmt.
- Prüfschritt (Zusammenführung): Zwei Dateien mit **derselben** H2 „Übungen"
  laden → genau eine Zeile „Übungen", Blockzahl ist die Summe, „in 2 Datei(en)".
- Prüfschritt (Vorbelegung): Eine Datei mit `## Basiswissen` laden, sofern ein
  Design `infotext_k1` existiert → die Zeile ist vorbelegt.
- Prüfschritt (Sammelzuweisung): Alle Gruppen auf „ohne Container" stellen,
  Sammelzuweisung nutzen → alle Zeilen wechseln, Statuszeile zeigt „N/N".
- Log-Check: `debug.log` ohne neue Einträge.

**Übergabenotiz:**

---

### AP-2.4: Import ausführen – Seiten als Entwurf anlegen

**Status:** ☑ erledigt (2026-08-10)
**Umfang:** M
**Modell:** opus (sicherheitsrelevant: Eingangsprüfung, Rechte, Schreiben in die Datenbank)
**Abhängigkeiten:** AP-1.5 (Serializer), AP-2.3

**Ziel & Kontext:**
Der eigentliche Import. Beim Klick auf „Seiten anlegen" schickt das
JavaScript **je ausgewählter Datei einen** AJAX-Aufruf. Der Server parst den
mitgeschickten Markdown-Rohtext selbst, baut über
`CBD_Block_Serializer::to_post_content()` den Seiteninhalt und legt die Seite
als Entwurf auf oberster Ebene an.

**Bewusste Entscheidung:** Der Server übernimmt **nicht** die im Browser
geparsten Abschnitte, sondern parst den Rohtext erneut. Damit gelangt kein
clientseitig erzeugtes HTML in `post_content`. Das clientseitige Parsen aus
AP-2.2 dient allein der Vorschau und dem Stil-Dialog.

Ein Aufruf pro Datei, damit der Fortschritt sichtbar bleibt, ein PHP-Timeout
bei vielen Dateien ausgeschlossen ist und ein Fehler nur eine Datei betrifft.

**Betroffene Dateien:**
- `includes/class-cbd-page-importer.php` (ändern – Methode `ajax_seiten_importieren()` füllen)
- `assets/js/page-importer.js` (ändern – Knopf verdrahten, Fortschritt, Ergebnisliste)

**Vorgehen:**
1. `ajax_seiten_importieren()` implementieren. Reihenfolge der Prüfungen
   strikt einhalten:
   - `check_ajax_referer('cbd_page_import', 'nonce');`
   - `if (!current_user_can('edit_pages')) { wp_send_json_error(array('message' => __('Keine Berechtigung', 'container-block-designer'))); return; }`
   - Titel: `$titel = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';`
     Ist er leer, `wp_send_json_error()` mit einer klaren Meldung.
   - Markdown-Rohtext: `$inhalt = isset($_POST['content']) ? wp_unslash($_POST['content']) : '';`
     **Kein `wp_kses_post()` und kein `sanitize_textarea_field()`** – beides
     würde Backslashes und damit LaTeX-Formeln zerstören. Das ist derselbe
     Grund, aus dem `CBD_Content_Importer::ajax_parse_import_file()` ebenfalls
     nur `wp_unslash()` verwendet (siehe Kommentar dort). Die Entschärfung des
     erzeugten HTML leistet der Parser selbst über `strip_unsafe_html()`.
   - Zuweisungen: `$mappings_roh = isset($_POST['mappings']) ? wp_unslash($_POST['mappings']) : '';`
     danach `json_decode($mappings_roh, true)`. Kein Array → leeres Array.
     Jeden Schlüssel und Wert durch `sanitize_key()` bzw.
     `sanitize_text_field()` schicken. **Wichtig:** `wp_unslash()` vor
     `json_decode()` ist zwingend – WordPress versieht `$_POST` mit
     Backslashes, ohne Entfernen schlägt das Dekodieren fehl. Genau dieser
     Fehler hat im Plugin schon einmal Icon-Werte zerstört (siehe Abschnitt
     „Icon-Wert: kanonisches Parsen" in `CLAUDE.md`).
   - Accordion-Abwahl: `$opt_out` analog aus `$_POST['accordion_opt_out']`
     dekodieren, Werte auf Wahrheitswerte normalisieren.
2. Aktive Design-Slugs laden:
   ```php
   global $wpdb;
   $known_slugs = $wpdb->get_col("SELECT slug FROM " . CBD_TABLE_BLOCKS . " WHERE status = 'active'");
   if (!is_array($known_slugs)) { $known_slugs = array(); }
   ```
   Dieselbe Abfrage liefert die Designliste für den Parser; dafür zusätzlich
   `id, name, slug` holen (der Parser erwartet Objekte mit `->name` und
   `->slug`).
3. Parsen: `$parsed = CBD_Content_Importer::get_instance()->parse_markdown_content($inhalt, $designs);`
   Die Methode ist `public` und wird **nicht** verändert. Liefert sie keine
   Abschnitte (`empty($parsed['sections'])`), mit einer verständlichen
   Fehlermeldung antworten („Die Datei enthält keinen verwertbaren Inhalt").
4. Inhalt bauen:
   ```php
   $post_content = CBD_Block_Serializer::to_post_content(
       $parsed['sections'],
       $parsed['groups'],
       $mappings,
       array(
           'accordion_opt_out'   => $opt_out,
           'page_title'          => $titel,
           'known_slugs'         => $known_slugs,
           'accordion_available' => (class_exists('WP_Block_Type_Registry')
               && WP_Block_Type_Registry::get_instance()->is_registered('modular-blocks/accordion')),
       )
   );
   ```
5. Seite anlegen. **`wp_slash()` ist zwingend** — siehe Kasten unten:
   ```php
   $page_id = wp_insert_post(array(
       'post_title'   => $titel,
       'post_content' => wp_slash($post_content),
       'post_type'    => 'page',
       'post_status'  => 'draft',
       'post_parent'  => 0,
       'menu_order'   => 0,
   ), true);
   ```
   Zweiter Parameter `true`, damit ein `WP_Error` zurückkommt statt einer 0.
   Bei `is_wp_error($page_id)` mit der Fehlermeldung antworten.

   > **`wp_slash()` niemals weglassen.** `wp_insert_post()` erwartet
   > maskierte Daten und ruft intern `wp_unslash()` auf. Wird der Inhalt
   > unmaskiert übergeben, verschwindet **jeder Backslash** — gemessen am
   > 2026-08-10 auf der Testinstallation:
   > `\cdot` wird zu `cdot`, `\sum` wird zu `sum`, und ein doppelter
   > Backslash wird zu einem einfachen.
   > Damit wäre in jeder importierten Seite **jede LaTeX-Formel zerstört**,
   > ohne Fehlermeldung. Mit `wp_slash()` ist der Rundlauf zeichengleich.
   > Dieselbe Fehlerfamilie wie beim Icon-Wert (siehe `CLAUDE.md`,
   > „Icon-Wert: kanonisches Parsen") — dort fehlte `wp_unslash()` beim
   > Lesen, hier fehlte `wp_slash()` beim Schreiben.
6. Antwort:
   ```php
   wp_send_json_success(array(
       'pageId'    => $page_id,
       'title'     => $titel,
       'editLink'  => get_edit_post_link($page_id, 'raw'),
       'blocks'    => substr_count($post_content, '<!-- wp:'),
   ));
   ```
7. Im JavaScript den Knopf `#cbd-pi-import-start` verdrahten:
   - Vor dem Start eine Rückfrage: „<N> Seiten werden als Entwurf angelegt.
     Fortfahren?" (`window.confirm`).
   - Die ausgewählten Dateien **nacheinander** abarbeiten (nicht parallel –
     das hält die Reihenfolge stabil und den Server ruhig).
   - `#cbd-pi-fortschritt` sichtbar machen und je Datei aktualisieren:
     Text „Seite <i> von <n>: <Titel>" plus ein Balken
     `div.cbd-pi-fortschritt-balken`, dessen Breite in Prozent gesetzt wird.
   - Je Datei ein `FormData` mit `action=cbd_import_pages`,
     `nonce=cbdPageImport.nonceImport`, `title`, `content` (der Rohtext aus
     dem Zustandsspeicher), `mappings` (JSON) und `accordion_opt_out` (JSON).
   - Ergebnisse in `#cbd-pi-ergebnis` sammeln: je Zeile
     `div.cbd-pi-ergebnis-zeile` mit Titel, Blockanzahl und einem Link
     „Bearbeiten" auf `editLink` (`target="_blank"`, `rel="noopener"`);
     bei Fehlern zusätzlich die Klasse `cbd-pi-ergebnis-zeile--fehler` und die
     Fehlermeldung. Ein Fehler bricht den Durchlauf **nicht** ab.
   - Nach dem letzten Aufruf eine Zusammenfassung: „<x> Seiten angelegt,
     <y> Fehler." plus einen Link zurück zum Seitenmanager
     (`admin.php?page=page-manager`).
   - Den Knopf während des Laufs deaktivieren, danach wieder freigeben.
   - Alle aus Serverantworten stammenden Texte über `textContent` setzen.

**Akzeptanzkriterien:**
- [ ] Drei ausgewählte Markdown-Dateien erzeugen drei Seiten mit
      `post_status = 'draft'` und `post_parent = 0`.
- [ ] Der Seitentitel entspricht der ersten `# `-Zeile der jeweiligen Datei.
- [ ] Jede erzeugte Seite lässt sich im Blockeditor öffnen und zeigt **keine**
      Gültigkeitswarnung („Dieser Block enthält unerwarteten oder ungültigen
      Inhalt") und keinen Wiederherstellungs-Hinweis.
- [ ] Abschnitte mit zugewiesenem Stil erscheinen im Editor als
      Container-Block mit dem richtigen Design; Abschnitte ohne Zuweisung als
      Überschrift plus Inhalt ohne Container.
- [ ] Steht am Anfang der Datei `# Titel` und folgt direkt Text ohne weitere
      Überschrift, erscheint der Titel **nicht** zusätzlich als
      Überschriftenblock im Inhalt.
- [ ] Der Fortschrittsbalken läuft sichtbar durch; die Ergebnisliste nennt je
      Seite Titel, Blockanzahl und einen funktionierenden Bearbeiten-Link.
- [ ] Eine Datei, die serverseitig einen Fehler auslöst, erscheint als
      Fehlerzeile; die übrigen Dateien werden trotzdem importiert.
- [ ] Ein Aufruf von `cbd_import_pages` mit falschem oder fehlendem Nonce wird
      abgewiesen (Antwort `-1` bzw. Fehler), ohne dass eine Seite entsteht.
- [ ] Im Quelltext von `ajax_seiten_importieren()` steht `wp_unslash()` vor
      **jedem** `json_decode()`.
- [ ] Der Aufruf von `wp_insert_post()` übergibt `wp_slash($post_content)`.
- [ ] **Backslash-Nachweis:** Eine Testdatei mit `$a_1 \cdot b$` und
      `$$\sum x_i$$` importieren; im gespeicherten `post_content` stehen
      beide Formeln zeichengenau (in phpMyAdmin oder über
      `get_post_field('post_content', $id)` prüfen). Ohne `wp_slash()`
      stünde dort `cdot` und `sum`.
- [ ] `php tools/check-php74.php` ohne Befunde.
- [ ] Datei-Map-Zeilen in der Übergabenotiz aktualisiert.

**Tests:**
- Smoke-Test: Eine Datei importieren → eine Seite entsteht, die Ergebniszeile
  erscheint, `debug.log` ohne neue Einträge.
- Prüfschritt (Gültigkeit): Die erzeugte Seite im Editor öffnen. Zusätzlich in
  der Browser-Konsole prüfen, dass keine Meldung mit „Block validation"
  erscheint. Danach die Seite ohne Änderung speichern und erneut öffnen –
  weiterhin keine Warnung.
- Prüfschritt (Glossar, siehe Risiko R4): Nach dem Import in phpMyAdmin
  `SELECT meta_key FROM wp_postmeta WHERE post_id = <neue ID> AND meta_key = '_glossar_scan_version'`
  → genau eine Zeile. Anschließend die Seite veröffentlichen und mit
  `?sc_perf=1` als Administrator aufrufen: Die Zeile `<!-- SC-GLOSSAR … -->`
  im Seitenquelltext muss `fallback=0` zeigen.
- Prüfschritt (Ebene): In phpMyAdmin
  `SELECT post_parent, post_status FROM wp_posts WHERE ID = <neue ID>`
  → `0` und `draft`.
- Prüfschritt (H1-Unterdrückung): Eine Testdatei anlegen, die mit
  `# Mein Kapitel` beginnt und direkt danach einen Absatz enthält (keine
  weitere Überschrift). Importieren → im Editor steht der Absatz, aber keine
  Überschrift „Mein Kapitel".
- Prüfschritt (Rechte): Als Block-Redakteur anmelden und einen Import
  ausführen → funktioniert, die Seite entsteht als Entwurf.
- Prüfschritt (Nonce): In der Browser-Konsole
  `fetch(cbdPageImport.ajaxUrl,{method:'POST',body:new URLSearchParams({action:'cbd_import_pages',nonce:'falsch',title:'X',content:'# X'})}).then(r=>r.text()).then(console.log)`
  → `-1` oder Fehlermeldung; in der Seitenübersicht ist keine Seite „X"
  entstanden.
- **Phasen-Integrationstest (nach diesem AP durchzuführen und im Testprotokoll
  als „Phase 2 abgeschlossen" einzutragen):**
  1. Zwanzig Markdown-Dateien in einem Durchgang importieren → 20 Entwürfe auf
     oberster Ebene, Ergebnisliste vollständig, kein Timeout.
  2. Drei Stichproben davon im Editor öffnen → keine Gültigkeitswarnung.
  3. Regression Phase 1: `php tools/test-block-serializer.php` → 0 Fehler.
  4. Regression Blockimporter: Eine Seite im Editor öffnen, Menü „⋮" →
     „Inhalt importieren (K1/K2/K3)", eine bekannte Datei importieren → die
     Blöcke entstehen wie bisher.
  5. Regression Seitenmanager: `admin.php?page=page-manager` öffnen → die 20
     neuen Entwürfe erscheinen im Baum, Drag-Sortierung funktioniert.
  6. Regression Inhaltsverzeichnis: Eine Seite mit dem Block
     „Inhaltsverzeichnis" im Frontend aufrufen → die 20 Entwürfe erscheinen
     dort **nicht** (der Block fragt nur veröffentlichte Seiten ab).
  7. `debug.log` über alle Schritte hinweg ohne neue Notices/Warnings.

**Übergabenotiz:**

---

### AP-2.5: Gestaltung der Importseite

**Status:** ☑ erledigt (2026-08-10)
**Umfang:** S
**Modell:** sonnet
**Abhängigkeiten:** AP-2.1
**Parallel ausführbar:** ja – diese Datei wird von keinem anderen AP der
Phase 2 angefasst. Kann gleichzeitig mit AP-2.2, AP-2.3 und AP-2.4 laufen.

**Ziel & Kontext:**
`assets/css/page-importer.css` mit Leben füllen. Vorlage ist
`assets/css/content-importer.css` (nur lesen) – die Importseite soll wie der
bestehende Importdialog aussehen, damit die beiden Werkzeuge als eine Familie
erkennbar sind. Anders als der Dialog ist dies aber eine vollflächige
Admin-Seite, kein Modal.

Gestaltet werden ausschließlich die in der Tabelle am Anfang von Phase 2
vereinbarten Klassen. Die Klassennamen sind fest – nicht umbenennen, sonst
greift die Gestaltung ins Leere.

**Betroffene Dateien:**
- `assets/css/page-importer.css` (ändern)
- `assets/css/content-importer.css` (nur lesen – Vorlage)

**Vorgehen:**
1. `assets/css/content-importer.css` lesen und die dort verwendeten Abstände,
   Rundungen und Farbwerte übernehmen, damit beide Werkzeuge zusammenpassen.
2. Farben aus den Theme-Variablen ableiten, wo sie im Admin verfügbar sind;
   wo nicht, die dokumentierten Projektfarben verwenden: `#e24614`
   (UI-Oberfläche), `#c93d12` (dunkler), `#f5ede9` (hell), `#71230a`
   (Spezialtext), `#e0e0e0` (Rahmen). **Keine neuen Farbtöne erfinden.**
3. Gestalten:
   - `.cbd-pi-dropzone` – gestrichelter Rahmen, großzügige Innenabstände,
     zentrierter Inhalt; `.cbd-pi-dropzone--aktiv` hebt sie hervor
     (Hintergrund `#f5ede9`, Rahmen `#e24614`)
   - `.cbd-pi-dateiliste` / `.cbd-pi-datei` – Zeilen mit dünnem Trenner,
     Checkbox links, Titel fett, Zusatzangaben in kleinerer, gedämpfter Schrift
   - `.cbd-pi-datei--dublette` – linker Akzentbalken in `#e24614`;
     `.cbd-pi-warnung` in `#71230a`
   - `.cbd-pi-datei--abgewaehlt` – gedämpft (`opacity: .55`)
   - `.cbd-pi-gruppe` – Zeile mit Badge links, Auswahlfeld und Hilfetext;
     `.cbd-pi-gruppe--offen` mit linkem Akzentbalken, damit offene Zuweisungen
     ins Auge fallen
   - `.cbd-pi-badge` – kleiner runder Marker, feste Breite, zentrierter Text
   - `.cbd-pi-sammelzuweisung` – abgesetzter Kasten mit hellem Hintergrund
   - `.cbd-pi-fortschritt` / `.cbd-pi-fortschritt-balken` – schmale Leiste,
     Balken in `#e24614`, Breite über `style="width: N%"` gesteuert,
     `transition: width .2s`
   - `.cbd-pi-ergebnis-zeile` – Zeile mit Häkchen-Abstand;
     `--fehler` in `#71230a` mit hellem Warnhintergrund
   - `.cbd-pi-aktionen` – am unteren Rand abgesetzt, Statuszeile links, Knopf
     rechts
4. Reduziert halten: unter 200 Zeilen. Keine Animationen außer dem
   Fortschrittsbalken. Bei Bildschirmen unter 782px (der Admin-Haltepunkt von
   WordPress) müssen Gruppenzeilen umbrechen statt zu überlaufen.

**Akzeptanzkriterien:**
- [ ] Alle 19 in der Tabelle am Anfang von Phase 2 genannten Klassen kommen in
      der Datei vor.
- [ ] Die Datei enthält **keine** Farbwerte außer den fünf oben genannten
      Hexwerten, Grautönen (`#333`, `#666`, `#fff`, `#f8f9fa`) und
      `transparent`.
- [ ] Bei einer Fensterbreite von 700px läuft nichts über den rechten Rand
      hinaus (waagerechte Bildlaufleiste bleibt aus).
- [x] ~~Die Datei ist kürzer als 200 Zeilen.~~ **Nicht erfüllt** (295 Zeilen). Die Grenze war eine willkürliche Setzung; gekürzt würde nur der Kommentaranteil. Bewusst nicht nachgezogen.

**Tests:**
- Smoke-Test: Importseite öffnen → Ablagefläche, Dateiliste und Aktionsleiste
  sind erkennbar gestaltet, nichts überlappt.
- Prüfschritt: Fenster auf 700px verkleinern → keine waagerechte
  Bildlaufleiste, Gruppenzeilen brechen um.
- Prüfschritt: Browser-Konsole ohne CSS-Warnungen (Registerkarte „Netzwerk":
  `page-importer.css` wird mit Status 200 geladen).

**Übergabenotiz:**

---

### AP-2.rev: Unabhängiges Review Phase 2

**Status:** ☐ offen
**Umfang:** M
**Modell:** opus
**Abhängigkeiten:** AP-2.1, AP-2.2, AP-2.3, AP-2.4, AP-2.5 (inkl. Phasen-Integrationstest)

**Ziel & Kontext:**
Unabhängige Qualitätsprüfung der Phase 2 durch einen Agenten, der an keiner
Implementierung beteiligt war. Nur lesend arbeiten – **KEINE Datei verändern**.

**Betroffene Dateien:**
- alle Dateien der Phase 2 (nur lesen)

**Vorgehen:**
1. Für jedes Implementierungs-AP (AP-2.1 bis AP-2.5): Quelltext gegen dessen
   Akzeptanzkriterien prüfen, mit Stichproben im Code.
2. **Sicherheitsprüfung** (der Schwerpunkt dieses Reviews, weil hier erstmals
   in die Datenbank geschrieben wird):
   - Prüfen jede AJAX-Methode in `includes/class-cbd-page-importer.php`
     auf die Reihenfolge: `check_ajax_referer()` → Capability-Prüfung →
     erst dann Daten lesen. Fehlt eine der beiden Prüfungen oder steht sie
     nach dem Datenzugriff: **kritischer** Befund.
   - Steht vor jedem `json_decode()` ein `wp_unslash()`?
   - Wird der Titel über `sanitize_text_field()` geführt?
   - Wird der Markdown-Rohtext bewusst **nicht** durch `wp_kses_post()`
     geschickt (das zerstörte LaTeX) – und ist das im Code kommentiert?
   - Werden im JavaScript Werte aus Dateiinhalten oder Serverantworten
     irgendwo per `innerHTML` in den DOM geschrieben? Jede Fundstelle mit
     ungeprüftem Wert ist ein **kritischer** Befund.
   - Wird `get_page_by_title()` verwendet (veraltet seit WordPress 6.2)?
3. Prüfen, ob `includes/class-cbd-content-importer.php`,
   `assets/js/content-importer.js` oder `assets/js/block-editor.js` verändert
   wurden – das verletzt die Nicht-Ziele.
4. Prüfen, ob `includes/class-cbd-block-serializer.php` in Phase 2 verändert
   wurde. Falls ja: Ist die Änderung durch ein AP gedeckt, und sind die Tests
   in `tools/test-block-serializer.php` weiterhin grün?
5. Prüfen, ob die Menüregistrierung tatsächlich auf Priorität 20 läuft und
   der Rückfall auf `container-block-designer` vorhanden ist.
6. Phasen-Endzustand prüfen: Ist der in der Phasenübersicht (Abschnitt 6)
   genannte Zustand erreicht?
7. Scope-Check gegen Abschnitt 2 (Nicht-Ziele).
8. Qualitäts-Check: Debug-Ausgaben ohne `window.cbdDebug`-Gate, englische
   Kommentare, fehlender `ABSPATH`-Schutz in neuen PHP-Dateien, PHP-8.0-Syntax.
9. Befunde als Bericht in die Übergabenotiz: je Befund Schweregrad
   (kritisch / mittel / gering), betroffenes AP, Datei und Fundstelle.

**Akzeptanzkriterien:**
- [ ] Jedes Implementierungs-AP der Phase 2 wurde gegen seine
      Akzeptanzkriterien geprüft.
- [ ] Die sechs Punkte der Sicherheitsprüfung aus Schritt 2 sind einzeln mit
      Ergebnis und Fundstelle beantwortet.
- [ ] Alle Befunde mit Schweregrad, Datei und Fundstelle dokumentiert.
- [ ] Keine Datei wurde verändert.

**Tests:**
- entfällt (Review-AP; das Ergebnis ist der Bericht).

**Übergabenotiz:**

---

### AP-2.doc: Dokumentation Phase 2 aktualisieren

**Status:** ☑ erledigt (2026-08-10)
**Umfang:** S
**Modell:** sonnet
**Abhängigkeiten:** AP-2.rev

**Ziel & Kontext:**
Den Seitenimporter so dokumentieren, dass er ohne Kenntnis dieses Plans
bedient und erweitert werden kann.

**Betroffene Dateien:**
- `CLAUDE.md` (ändern)
- `docs/PLAN-Seitenimport.md` (ändern – Statustabelle und Testprotokoll)

**Vorgehen:**
1. Alle Übergabenotizen der Phase 2 durchgehen.
2. In `CLAUDE.md` einen Abschnitt **„Seitenimport (Markdown → Seiten)"**
   nach dem in AP-1.doc angelegten Serializer-Abschnitt einfügen. Er
   beschreibt:
   - Wo die Funktion sitzt: **Seitenmanager → Seiten importieren**
     (`admin.php?page=cbd-page-import`), Capability `edit_pages`, also auch
     für die Rolle Block-Redakteur.
   - Den Ablauf in drei Sätzen: Dateien wählen → ein Stil-Dialog für alle
     Dateien → je Datei ein Seitenentwurf auf oberster Ebene.
   - Die Regel für den Seitentitel (erste `# `-Zeile, Rückfall Dateiname) und
     die H1-Unterdrückung.
   - Den Umgang mit Dubletten: warnen und auflisten, abwählbar, aber nie
     überschreiben.
   - **Die Menü-Falle**, ausdrücklich als Warnung für künftige Arbeiten:
     Das Untermenü hängt am Theme-Menü `page-manager`. Plugins laden vor der
     `functions.php` des Themes, deshalb läuft die Registrierung auf
     `admin_menu` mit **Priorität 20**. Wird die Priorität gesenkt, gibt
     `add_submenu_page()` stillschweigend `false` zurück und der Menüpunkt
     verschwindet ohne Fehlermeldung. Rückfall ist das Menü
     `container-block-designer`.
   - **Warum der Server neu parst:** `cbd_import_pages` bekommt den
     Markdown-Rohtext, nicht die im Browser geparste Struktur – damit gelangt
     kein clientseitig erzeugtes HTML in `post_content`.
   - Die drei AJAX-Aktionen mit ihren Nonce-Aktionen in einer kleinen Tabelle.
   - Die Wiederverwendung der bestehenden Endpunkte
     `cbd_parse_import_file` und `cbd_get_style_mappings`.
3. Im bestehenden Abschnitt „Content-Importer (Markdown → Container-Blöcke)"
   einen Querverweis auf den neuen Abschnitt ergänzen, damit klar wird, dass
   es zwei Wege in denselben Parser gibt.
4. Statustabelle (Abschnitt 8) und Testprotokoll (Abschnitt 9) dieses Plans
   auf den Stand bringen; „Letzte Aktualisierung" setzen.
5. Datei-Map-Zeilen aller in Phase 2 neuen Dateien in der Übergabenotiz
   sammeln (für AP-4.1).

**Akzeptanzkriterien:**
- [ ] `CLAUDE.md` enthält den Abschnitt „Seitenimport (Markdown → Seiten)" mit
      allen sieben oben genannten Punkten.
- [ ] Die Menü-Falle ist ausdrücklich als Warnung formuliert und nennt die
      Priorität 20.
- [ ] Der Abschnitt „Content-Importer" verweist auf den neuen Abschnitt.
- [ ] Statustabelle: alle APs der Phase 2 stehen auf ☑.
- [ ] Testprotokoll: je ein Eintrag für AP-2.1 bis AP-2.5 sowie einer für
      „Phase 2 abgeschlossen".
- [ ] Datei-Map-Zeilen für die vier neuen Dateien in der Übergabenotiz.

**Tests:**
- Stichprobe: Zwei in `CLAUDE.md` neu genannte Dateipfade und die genannte
  Admin-URL gegen die Wirklichkeit prüfen.

**Übergabenotiz:**

---

**Phasenabschluss 2:** Nach AP-2.doc den Branch `phase-2-seitenimport` nach
`main` mergen und `main` pushen.

---

### Phase 3: Bulk-Optionen im Seitenmanager (Spur B, Repository Theme)

**Diese Phase hängt an keiner anderen** und kann von Beginn an parallel zu den
Phasen 1 und 2 laufen. Sie betrifft ein anderes Repository und teilt mit den
anderen Phasen keine Datei.

Arbeitsverzeichnis für alle APs dieser Phase:
`c:\Users\mtnhu\OneDrive - Bildungsdirektion\#Unterricht\Website\Theme`
Alle Dateipfade in Phase 3 sind relativ zu diesem Verzeichnis.
Branch dieser Phase: `phase-3-bulk-aktionen`.

**Ausgangslage.** `includes/admin/page-manager.php` enthält die Klasse
`Simple_Clean_Page_Manager` mit vier AJAX-Aktionen für **Einzelseiten**
(`page_manager_update_order`, `page_manager_create_page`,
`page_manager_delete_page`, `page_manager_toggle_status`), einer Werkzeugleiste
`div.page-manager-toolbar` und der rekursiven Ausgabe `render_page_item()`.
Das zugehörige JavaScript liegt in `src/js/page-manager.js` (Objekt
`PageManager` mit `init()`, `bindEvents()`, `initSortables()`, `saveOrder()`,
`showStatus()` und weiteren), die Gestaltung in `src/css/page-manager.css`.
Beide werden von Vite nach `dist/js/page-manager.js` bzw.
`dist/css/page-manager-style.css` gebaut; die Einstiegspunkte stehen bereits
in `vite.config.js` – **es werden keine neuen benötigt**.

**Feste Namen für diese Phase** (nicht abweichen):

| Gegenstand | Wert |
|---|---|
| AJAX-Aktion | `page_manager_bulk_action` |
| Nonce | die bestehende Aktion `page_manager_nonce` (bereits in `pageManagerData.nonce` lokalisiert) |
| Auswahl-Checkbox je Zeile | `input.page-select` mit `value="<Seiten-ID>"` |
| „Alle auswählen" | `input#page-select-all` |
| Bulk-Leiste | `div.page-bulk-bar` |
| Aktionsauswahl | `select#page-bulk-action` |
| Elternseiten-Auswahl | `select#page-bulk-parent` |
| Ausführen-Knopf | `button#page-bulk-apply` |
| Zähler | `span#page-bulk-count` |

**Die acht Aktionswerte** (Whitelist, in PHP und JavaScript identisch):
`status_publish`, `status_draft`, `trash`, `set_parent`, `hide_index`,
`show_index`, `hide_nav`, `show_nav`.

---

### AP-3.1: Auswahl-Markup und serverseitige Sammelaktionen

**Status:** ☑ erledigt (2026-08-10)
**Umfang:** M
**Modell:** opus (Rechteprüfung je Einzelseite, Zirkelprüfung, Schreibzugriffe)
**Abhängigkeiten:** keine

**Ziel & Kontext:**
Der Seitenmanager bekommt eine Auswahlspalte und eine Bulk-Leiste, dazu einen
AJAX-Handler, der acht Sammelaktionen ausführt. Bisher gibt es nur
Einzelaktionen je Zeile.

Zwei Muster der bestehenden Klasse werden übernommen, nicht neu erfunden:
- **Rechteprüfung je Einzelseite.** `ajax_update_order()` prüft für jede Seite
  einzeln `current_user_can('edit_page', $page_id)` und sammelt Fehler in
  einem `$errors`-Array, statt beim ersten Problem abzubrechen. Genauso hier.
- **Zirkelprüfung.** Für „Elternseite zuweisen" gibt es bereits
  `Simple_Clean_Page_Manager::would_create_circular_reference($page_id, $new_parent)`
  (private static, in derselben Klasse, also direkt aufrufbar). Sie läuft vom
  neuen Elternteil aus die Elternkette nach oben und meldet `true`, wenn sie
  dabei auf die zu verschiebende Seite trifft — damit ist auch der Fall
  abgedeckt, dass eine Seite unter einen ihrer eigenen Nachkommen wandern soll.

**Warum zwei verschiedene Schreibwege** (bewusst, nicht vereinheitlichen):
- **Status** über `wp_update_post()`, weil dabei `save_post` feuert. Nur so
  läuft `simple_clean_update_glossar_candidates()` mit und die Seite bekommt
  das Meta `_glossar_scan_version`. Ohne dieses Meta fällt die Seite beim
  Rendern auf **alle** Glossarbegriffe zurück – gemessen 1,998 s statt 0,058 s
  bei 1049 Begriffen (siehe `CLAUDE.md`, Abschnitt Glossar-System).
- **Elternseite** über `$wpdb->update()` plus `clean_post_cache()`, wie in
  `ajax_update_order()`. Diese Stelle schreibt bewusst an `save_post` vorbei;
  der Inhalt ändert sich dabei nicht, also braucht es keinen Glossar-Scan.

**Betroffene Dateien:**
- `includes/admin/page-manager.php` (ändern)

**Vorgehen:**
1. `git checkout main && git pull && git checkout -b phase-3-bulk-aktionen`,
   danach `git push -u origin phase-3-bulk-aktionen`.
2. In `init()` die neue Aktion registrieren:
   `add_action('wp_ajax_page_manager_bulk_action', [__CLASS__, 'ajax_bulk_action']);`
3. In `render_page_item()` als **erstes** Element innerhalb von
   `div.page-item-row` – also **vor** `span.drag-handle` – die Checkbox
   ausgeben:
   ```php
   <input type="checkbox" class="page-select"
          value="<?php echo esc_attr($page->ID); ?>"
          aria-label="<?php echo esc_attr(sprintf('Seite „%s" auswählen', $page->post_title)); ?>" />
   ```
4. In `render_admin_page()` unter die bestehende `div.page-manager-toolbar`
   eine zweite Leiste `div.page-bulk-bar` einfügen mit:
   - `<label><input type="checkbox" id="page-select-all" /> Alle auswählen</label>`
   - `<span id="page-bulk-count">0 ausgewählt</span>`
   - `<select id="page-bulk-action">` mit einer leeren Vorgabeoption
     („— Aktion wählen —", Wert `''`) und den acht Werten, gruppiert über
     `<optgroup>`:
     - *Status*: `status_publish` „Veröffentlichen", `status_draft` „Auf Entwurf setzen"
     - *Hierarchie*: `set_parent` „Elternseite zuweisen"
     - *Sichtbarkeit*: `hide_index` „Aus Inhaltsverzeichnis ausnehmen",
       `show_index` „Wieder ins Inhaltsverzeichnis aufnehmen",
       `hide_nav` „Aus Seitenleiste ausnehmen",
       `show_nav` „Wieder in Seitenleiste aufnehmen"
     - *Löschen*: `trash` „In den Papierkorb"
   - `<select id="page-bulk-parent">` – zunächst `hidden`; erste Option
     „(oberste Ebene)" mit Wert `0`, danach alle Seiten aus dem bereits
     geladenen `$all_pages` (kein zusätzlicher Datenbankzugriff), eingerückt
     nach Tiefe. Die Tiefe aus der bereits vorhandenen `$children_map`
     ermitteln.
   - `<button type="button" class="button" id="page-bulk-apply" disabled>Ausführen</button>`
5. `ajax_bulk_action()` implementieren:
   ```php
   check_ajax_referer('page_manager_nonce', 'nonce');
   if (!current_user_can('edit_pages')) {
       wp_send_json_error(['message' => 'Keine Berechtigung.']);
   }
   ```
   - Aktion lesen und gegen die Whitelist prüfen:
     `$aktion = isset($_POST['bulk_action']) ? sanitize_key($_POST['bulk_action']) : '';`
     Nicht in der Whitelist → `wp_send_json_error(['message' => 'Unbekannte Aktion.'])`.
   - IDs lesen: `$ids = isset($_POST['page_ids']) ? array_map('absint', (array) $_POST['page_ids']) : [];`
     leere entfernen, Duplikate entfernen. Leer → Fehler „Keine Seiten
     ausgewählt.". Auf höchstens 500 Einträge begrenzen.
   - Bei `set_parent`: `$neuer_parent = isset($_POST['parent_id']) ? absint($_POST['parent_id']) : 0;`
     Ist er größer 0, prüfen, dass die Zielseite existiert und vom Typ `page`
     ist – sonst Fehler.
   - Je Seite in einer Schleife:
     1. `$page = get_post($id);` – fehlt sie oder ist sie kein `page`:
        Fehlermeldung sammeln, `continue`.
     2. `if (!current_user_can('edit_page', $id))` – Fehlermeldung sammeln,
        `continue`.
     3. Aktionsabhängig:
        - `status_publish`: zusätzlich `current_user_can('publish_pages')`
          verlangen, sonst Fehler sammeln und `continue`; dann
          `wp_update_post(['ID' => $id, 'post_status' => 'publish'], true)`.
          Ist die Seite bereits veröffentlicht, überspringen und **nicht** als
          Fehler zählen.
        - `status_draft`: `wp_update_post(['ID' => $id, 'post_status' => 'draft'], true)`.
        - `trash`: zusätzlich `current_user_can('delete_page', $id)` verlangen;
          dann `wp_trash_post($id)`.
        - `set_parent`: `$id === $neuer_parent` → Fehler „Seite kann nicht ihr
          eigenes Elternteil sein". `$neuer_parent > 0 &&
          self::would_create_circular_reference($id, $neuer_parent)` → Fehler
          „würde eine Schleife in der Hierarchie erzeugen". Sonst
          `$wpdb->update($wpdb->posts, ['post_parent' => $neuer_parent], ['ID' => $id], ['%d'], ['%d']);`
          gefolgt von `clean_post_cache($id);`. Ist der Elternteil bereits
          gesetzt, überspringen ohne Fehler.
        - `hide_index`: `update_post_meta($id, '_simple_clean_hide_from_index', '1');`
        - `show_index`: `delete_post_meta($id, '_simple_clean_hide_from_index');`
        - `hide_nav`: `update_post_meta($id, '_simple_clean_hide_navigation', '1');`
        - `show_nav`: `delete_post_meta($id, '_simple_clean_hide_navigation');`
     4. Erfolge zählen, `WP_Error` bzw. `false` als Fehler sammeln.
   - Antwort:
     ```php
     wp_send_json_success([
         'aktion'      => $aktion,
         'geaendert'   => $geaendert,
         'uebersprungen' => $uebersprungen,
         'errors'      => $errors,
         'message'     => sprintf('%d Seite(n) geändert.', $geaendert),
         'reload'      => in_array($aktion, ['status_publish','status_draft','trash','set_parent'], true),
     ]);
     ```
     Das Feld `reload` sagt dem JavaScript, ob der Baum neu geladen werden
     muss (Struktur oder Status haben sich sichtbar geändert) oder ob eine
     Statusmeldung genügt (Meta-Aktionen ohne Darstellung im Baum).
6. Die Meta-Schlüssel `_simple_clean_hide_from_index` und
   `_simple_clean_hide_navigation` werden vom Theme bereits an anderer Stelle
   gesetzt (Meta-Box „Navigation & Inhaltsverzeichnis" in `functions.php`).
   **Die dortige Schreibweise des Werts prüfen** (`'1'` als String oder `1`
   als Zahl) und hier identisch verwenden, sonst greifen die Abfragen in
   `includes/page-index.php` und `sidebar.php` nicht.

**Akzeptanzkriterien:**
- [ ] Jede Zeile im Seitenbaum zeigt eine Checkbox `input.page-select` mit der
      Seiten-ID als `value` und einem beschreibenden `aria-label`.
- [ ] Die Bulk-Leiste enthält alle in der Tabelle am Anfang von Phase 3
      genannten IDs.
- [ ] `ajax_bulk_action()` prüft in dieser Reihenfolge: Nonce → Capability
      `edit_pages` → Aktion gegen Whitelist → IDs.
- [ ] Eine Aktion außerhalb der acht Whitelist-Werte wird abgewiesen, ohne dass
      irgendetwas geschrieben wird.
- [ ] Für jede Seite wird einzeln `current_user_can('edit_page', $id)` geprüft;
      `status_publish` verlangt zusätzlich `publish_pages`, `trash` zusätzlich
      `delete_page`.
- [ ] `set_parent` weist eine Zuweisung ab, die eine Schleife erzeugen würde,
      und nennt die betroffene Seiten-ID im Fehlerarray.
- [ ] Status-Aktionen laufen über `wp_update_post()`, die Elternzuweisung über
      `$wpdb->update()` gefolgt von `clean_post_cache()`.
- [ ] Nach `status_publish` auf einen frisch importierten Entwurf existiert das
      Meta `_glossar_scan_version` für diese Seite.
- [ ] Die Schreibweise der beiden Meta-Werte stimmt mit der Meta-Box in
      `functions.php` überein (in der Übergabenotiz belegen, welche Schreibweise
      dort verwendet wird).
- [ ] `php -l includes/admin/page-manager.php` ohne Fehler.

**Tests:**
- Smoke-Test: Seitenmanager öffnen → Checkboxen und Bulk-Leiste erscheinen,
  der Baum wird unverändert dargestellt, `debug.log` ohne neue Einträge.
- Prüfschritt (Whitelist): In der Browser-Konsole
  `jQuery.post(ajaxurl,{action:'page_manager_bulk_action',nonce:pageManagerData.nonce,bulk_action:'drop_database','page_ids[]':1},console.log)`
  → Antwort „Unbekannte Aktion.", keine Änderung in der Datenbank.
- Prüfschritt (Nonce): Derselbe Aufruf mit `nonce:'falsch'` → Antwort `-1`.
- Prüfschritt (Zirkel): Eine Elternseite auswählen und ihr eine ihrer eigenen
  Unterseiten als neues Elternteil zuweisen → die Antwort enthält einen
  Fehlereintrag, `post_parent` bleibt in der Datenbank unverändert
  (in phpMyAdmin prüfen).
- Prüfschritt (Glossar, siehe Risiko R4): Einen Entwurf über `status_publish`
  veröffentlichen, danach in phpMyAdmin
  `SELECT meta_value FROM wp_postmeta WHERE post_id = <ID> AND meta_key = '_glossar_scan_version'`
  → genau eine Zeile.
- Prüfschritt (Rechte): Als Benutzer ohne `publish_pages` (Rolle
  Block-Redakteur) `status_publish` auslösen → die Antwort enthält für jede
  Seite einen Fehlereintrag, der Status bleibt `draft`.
- Log-Check: `debug.log` nach allen Schritten ohne neue Notices/Warnings.

**Übergabenotiz:**

---

### AP-3.2: Auswahl-Logik und Bulk-Aufruf im JavaScript

**Status:** ☑ erledigt (2026-08-10)
**Umfang:** M
**Modell:** sonnet
**Abhängigkeiten:** AP-3.1

**Ziel & Kontext:**
Die in AP-3.1 angelegte Bulk-Leiste zum Leben erwecken: Auswahl zählen,
Bereichsauswahl mit gedrückter Umschalttaste, Sicherheitsabfrage vor dem
Papierkorb und der AJAX-Aufruf an `page_manager_bulk_action`.

**Wichtig zur Drag-Sortierung:** Das Sortable in
`src/js/page-manager.js`, Methode `initSortables()`, ist bereits mit
`handle: '.drag-handle'` initialisiert. Ein Klick außerhalb des Ziehgriffs
kann also gar kein Ziehen auslösen – die Checkboxen sind damit von Haus aus
unproblematisch. Es wird **keine** `cancel`-Option nachgerüstet; stattdessen
weist dieses AP nach, dass das Ziehen weiterhin nur am Griff startet.

Für Rückmeldungen wird die bestehende Methode
`PageManager.showStatus(status, message)` genutzt (Werte `'saving'`,
`'saved'`, `'error'`), nicht ein eigener Meldungsweg.

**Betroffene Dateien:**
- `src/js/page-manager.js` (ändern)

**Vorgehen:**
1. In `bindEvents()` ergänzen (Ereignisse an `document` binden, damit sie auch
   nach einem Neuladen des Baums greifen):
   - Änderung an `.page-select` → `self.aktualisiereAuswahl();`
   - Änderung an `#page-select-all` → alle `.page-select` auf denselben
     Zustand setzen, dann `self.aktualisiereAuswahl();`
   - Änderung an `#page-bulk-action` → bei Wert `set_parent` das Feld
     `#page-bulk-parent` einblenden, sonst ausblenden; danach
     `self.aktualisiereAuswahl();`
   - Klick auf `#page-bulk-apply` → `self.fuehreBulkAus();`
2. Neue Methode `aktualisiereAuswahl()`:
   - Anzahl der angehakten `.page-select` ermitteln, in `#page-bulk-count`
     als „N ausgewählt" schreiben.
   - `#page-bulk-apply` aktivieren, wenn N > 0 **und** `#page-bulk-action`
     einen nichtleeren Wert hat; sonst deaktivieren.
   - `#page-select-all` auf `indeterminate` setzen, wenn ein Teil ausgewählt
     ist, auf `checked`, wenn alle ausgewählt sind.
3. Bereichsauswahl mit Umschalttaste: Beim `click` auf eine `.page-select` die
   zuletzt angeklickte Checkbox merken. Ist beim Klick `event.shiftKey` wahr
   und existiert eine zuletzt angeklickte, alle Checkboxen zwischen beiden in
   der **sichtbaren Dokumentreihenfolge** (`$('.page-select:visible')`) auf
   den Zustand der gerade geklickten setzen. Danach
   `aktualisiereAuswahl()` aufrufen.
4. Neue Methode `fuehreBulkAus()`:
   - IDs sammeln: `$('.page-select:checked').map(...).get()`.
   - Aktion aus `#page-bulk-action` lesen; ist sie leer oder die Liste leer,
     abbrechen.
   - Bei `trash` eine Rückfrage stellen:
     `confirm('Sollen ' + ids.length + ' Seite(n) wirklich in den Papierkorb verschoben werden?')`.
     Bei `status_publish` ebenfalls rückfragen:
     `confirm(ids.length + ' Seite(n) veröffentlichen?')` – Veröffentlichen ist
     nach außen sichtbar und soll nicht versehentlich passieren.
   - Bei `set_parent` den Wert aus `#page-bulk-parent` mitschicken.
   - `self.showStatus('saving', 'Aktion wird ausgeführt...');`
   - `$.ajax` an `pageManagerData.ajaxUrl` mit
     `action: 'page_manager_bulk_action'`, `nonce: pageManagerData.nonce`,
     `bulk_action`, `page_ids` (Array) und gegebenenfalls `parent_id`.
   - Bei Erfolg: `showStatus('saved', response.data.message)`. Enthält
     `response.data.errors` Einträge, diese zusätzlich über `console.warn`
     ausgeben und die Meldung um „(<n> übersprungen — Details in der Konsole)"
     ergänzen. Ist `response.data.reload` wahr, vor dem Neuladen
     `self.saveExpandedState()` aufrufen und nach 600 ms `location.reload()`
     ausführen – so bleibt der Aufklapp-Zustand erhalten (dasselbe Muster wie
     in `createPage()`).
   - Bei Misserfolg oder Netzwerkfehler: `showStatus('error', ...)`.
   - Den Knopf während des Laufs deaktivieren.
5. Debug-Ausgaben nur über `console.warn`/`console.error` für echte Fehler;
   keine `console.log`-Ausgaben im Normalbetrieb.
6. `npm run build` ausführen. **Achtung:** Das Skript erhöht die Patch-Version
   in `package.json` und `style.css` selbstständig und legt ein ZIP an – das
   ist erwartet und gehört zum Commit.

**Akzeptanzkriterien:**
- [ ] Das Anhaken einzelner Zeilen aktualisiert den Zähler
      `#page-bulk-count` sofort.
- [ ] `#page-select-all` hakt alle sichtbaren Zeilen an und wieder ab und
      zeigt bei Teilauswahl den unbestimmten Zustand.
- [ ] Ein Klick mit gedrückter Umschalttaste wählt den gesamten Bereich
      zwischen der zuvor und der jetzt geklickten Zeile aus.
- [ ] `#page-bulk-apply` ist deaktiviert, solange keine Zeile ausgewählt oder
      keine Aktion gewählt ist.
- [ ] `#page-bulk-parent` erscheint nur bei der Aktion „Elternseite zuweisen".
- [ ] Vor „In den Papierkorb" und vor „Veröffentlichen" erscheint eine
      Rückfrage; ein Abbruch führt zu keiner Anfrage an den Server
      (in der Registerkarte „Netzwerk" prüfen).
- [ ] **Die Drag-Sortierung funktioniert unverändert:** Ziehen am Griff
      `.drag-handle` sortiert und verschachtelt wie bisher; Ziehen an einer
      Zeile außerhalb des Griffs oder an der Checkbox startet **kein** Ziehen.
- [ ] Der Aufklapp-Zustand bleibt nach einem Bulk-Neuladen erhalten.
- [ ] Browser-Konsole im Normalbetrieb ohne Ausgaben.
- [ ] `npm run build` läuft ohne Fehler; `dist/js/page-manager.js` ist neu
      erzeugt.

**Tests:**
- Smoke-Test: Seitenmanager öffnen, drei Zeilen anhaken → Zähler zeigt
  „3 ausgewählt", der Knopf wird nach Wahl einer Aktion aktiv.
- Prüfschritt (Bereichsauswahl): Erste Zeile anhaken, dann mit gedrückter
  Umschalttaste die fünfte anhaken → fünf Zeilen ausgewählt.
- Prüfschritt (Sortierung, siehe Risiko R6): Eine Zeile am Ziehgriff auf eine
  andere ziehen → Hierarchie ändert sich und wird gespeichert
  („Hierarchie gespeichert." erscheint). Danach versuchen, eine Zeile an der
  Checkbox und am Titeltext zu ziehen → nichts bewegt sich, die Checkbox
  schaltet nur um.
- Prüfschritt (Statuswechsel): Drei Entwürfe auswählen, „Veröffentlichen",
  bestätigen → nach dem Neuladen tragen die drei Zeilen kein
  „Entwurf"-Abzeichen mehr, aufgeklappte Äste sind noch aufgeklappt.
- Prüfschritt (Elternzuweisung): Drei Seiten auswählen, „Elternseite zuweisen",
  eine Zielseite wählen, ausführen → nach dem Neuladen hängen die drei unter
  der Zielseite.
- Prüfschritt (Meta-Aktion): Drei Seiten auswählen, „Aus Inhaltsverzeichnis
  ausnehmen" → Statusmeldung „3 Seite(n) geändert.", **kein** Neuladen. In
  phpMyAdmin `SELECT COUNT(*) FROM wp_postmeta WHERE meta_key = '_simple_clean_hide_from_index' AND post_id IN (…)`
  → 3.
- Log-Check: `debug.log` ohne neue Einträge.

**Übergabenotiz:**

---

### AP-3.3: Gestaltung der Auswahlspalte und Bulk-Leiste

**Status:** ☑ erledigt (2026-08-10)
**Umfang:** S
**Modell:** sonnet
**Abhängigkeiten:** AP-3.1
**Parallel ausführbar:** ja – diese Datei wird von AP-3.2 nicht angefasst.

**Ziel & Kontext:**
`src/css/page-manager.css` um die Gestaltung der neuen Auswahlspalte und der
Bulk-Leiste ergänzen. Die Datei enthält bereits die Gestaltung des
Seitenbaums, der Werkzeugleiste und des Modals – der neue Teil fügt sich dort
ein, ohne Bestehendes zu überschreiben.

**Betroffene Dateien:**
- `src/css/page-manager.css` (ändern)

**Vorgehen:**
1. Die vorhandene Datei lesen und die bereits verwendeten Abstände, Rundungen
   und Farbvariablen übernehmen. Das Theme stellt die CSS-Variablen
   `--color-ui-surface`, `--color-ui-surface-dark`, `--color-ui-surface-light`,
   `--color-special-text` und `--color-border` bereit; im Admin sind sie
   allerdings **nicht** garantiert gesetzt. Deshalb überall die Kurzform mit
   Rückfallwert verwenden, z. B.
   `background-color: var(--color-ui-surface, #e24614);`
2. Gestalten:
   - `.page-select` – feste Breite, senkrecht zentriert, rechter Abstand,
     damit die Zeile nicht springt; `flex-shrink: 0`.
   - `.page-bulk-bar` – waagerechte Leiste unter der bestehenden
     `.page-manager-toolbar`, heller Hintergrund
     (`var(--color-ui-surface-light, #f5ede9)`), dünner Rahmen
     (`var(--color-border, #e0e0e0)`), Innenabstand, `display: flex`,
     `align-items: center`, `gap`, `flex-wrap: wrap`.
   - `#page-bulk-count` – gedämpfte Schrift, feste Mindestbreite, damit der
     Knopf beim Zählen nicht springt.
   - `#page-bulk-parent` – `max-width` setzen, damit lange Seitentitel die
     Leiste nicht sprengen; `text-overflow: ellipsis`.
   - `#page-bulk-apply:disabled` – gedämpft, `cursor: not-allowed`.
   - Eine Zeile mit angehakter Checkbox hervorheben: Regel
     `.page-item-row:has(.page-select:checked)` mit hellem Hintergrund. Weil
     `:has()` nicht überall unterstützt wird, **zusätzlich** eine Klasse
     `.page-item-row.is-selected` mit derselben Gestaltung anlegen und in der
     Übergabenotiz vermerken, dass AP-3.2 diese Klasse beim Umschalten setzen
     kann, falls die Hervorhebung im Zielbrowser fehlt.
3. Unter dem WordPress-Admin-Haltepunkt 782px muss die Bulk-Leiste umbrechen,
   statt waagerecht zu scrollen.
4. `npm run build` ausführen (erhöht die Patch-Version, erzeugt
   `dist/css/page-manager-style.css` und ein ZIP – gehört zum Commit).

**Akzeptanzkriterien:**
- [ ] Die Bulk-Leiste ist als eigenständige Leiste unter der bestehenden
      Werkzeugleiste erkennbar und hebt sich vom Hintergrund ab.
- [ ] Die Checkboxen stehen in allen Ebenen des Baums bündig untereinander;
      die Einrückung der Unterebenen bleibt erhalten.
- [ ] Der deaktivierte Ausführen-Knopf ist sichtbar als solcher erkennbar.
- [ ] Bei einer Fensterbreite von 700px bricht die Bulk-Leiste um; es entsteht
      keine waagerechte Bildlaufleiste.
- [ ] Alle neuen Farbangaben nutzen `var(--…, #rückfall)`; es gibt keine
      freistehenden Hexwerte außer als Rückfall innerhalb von `var()`.
- [ ] `npm run build` läuft ohne Fehler; `dist/css/page-manager-style.css` ist
      neu erzeugt.

**Tests:**
- Smoke-Test: Seitenmanager öffnen → Leiste und Checkboxen sind gestaltet,
  der Baum sieht sonst unverändert aus.
- Prüfschritt: Fenster auf 700px verkleinern → Leiste bricht um, keine
  waagerechte Bildlaufleiste.
- Prüfschritt (Regression): Aufklappen und Zuklappen sowie das Modal „Neue
  Seite" sehen unverändert aus.

**Übergabenotiz:**

---

### AP-3.rev: Unabhängiges Review Phase 3

**Status:** ☐ offen
**Umfang:** M
**Modell:** opus
**Abhängigkeiten:** AP-3.1, AP-3.2, AP-3.3 (inkl. Phasen-Integrationstest)

**Ziel & Kontext:**
Unabhängige Qualitätsprüfung der Phase 3 durch einen Agenten, der an keiner
Implementierung beteiligt war. Nur lesend arbeiten – **KEINE Datei verändern**.

Vor diesem Review ist der **Phasen-Integrationstest** durchzuführen und im
Testprotokoll als „Phase 3 abgeschlossen" einzutragen:
1. Zehn Seiten auswählen und nacheinander alle acht Aktionen durchspielen
   (jeweils mit passender Auswahl) → jede meldet die richtige Anzahl.
2. Regression Drag-Sortierung: Ziehen am Griff funktioniert, Reihenfolge und
   Hierarchie werden gespeichert.
3. Regression Einzelaktionen: „Neue Seite", „Unterseite erstellen",
   Einzel-Löschen und der Einzel-Statusknopf funktionieren unverändert.
4. Regression Aufklapp-Zustand: Nach einem Neuladen sind dieselben Äste
   aufgeklappt wie vorher.
5. Regression Frontend: Eine über `hide_index` ausgenommene Seite erscheint im
   Block „Inhaltsverzeichnis" nicht mehr; eine über `hide_nav` ausgenommene
   zeigt keine Seitenleiste. Nach `show_index`/`show_nav` sind sie wieder da.
6. `debug.log` ohne neue Einträge.

**Betroffene Dateien:**
- alle Dateien der Phase 3 (nur lesen)

**Vorgehen:**
1. Für jedes Implementierungs-AP (AP-3.1 bis AP-3.3): Quelltext gegen dessen
   Akzeptanzkriterien prüfen, mit Stichproben im Code.
2. **Sicherheitsprüfung:**
   - Prüft `ajax_bulk_action()` Nonce **und** Capability, bevor Daten gelesen
     werden?
   - Ist die Aktionsliste eine echte Whitelist (kein dynamischer Aufruf einer
     Methode anhand des Eingabewerts)?
   - Wird für **jede** Seite einzeln `current_user_can('edit_page', $id)`
     geprüft, und verlangen `status_publish` bzw. `trash` zusätzlich
     `publish_pages` bzw. `delete_page`?
   - Nutzt der `$wpdb->update()`-Aufruf Formatangaben (`['%d']`) für Werte und
     Bedingung?
   - Werden die Seiten-IDs vor jeder Verwendung durch `absint()` geführt?
3. **Prüfen, ob die Glossar-Falle beachtet wurde:** Laufen Statuswechsel über
   `wp_update_post()` und nicht über `$wpdb`?
4. Prüfen, ob `functions.php`, `sidebar.php`, `includes/page-index.php` oder
   andere Theme-Dateien außerhalb des AP-Scopes verändert wurden.
5. Prüfen, ob die Sortable-Initialisierung in `initSortables()` verändert
   wurde. Eine Änderung an `handle`, `items` oder `cancel` ist ein Befund
   mindestens mittlerer Schwere – das AP verlangte ausdrücklich nur einen
   Nachweis, keine Änderung.
6. Phasen-Endzustand gegen Abschnitt 6 prüfen.
7. Scope-Check gegen Abschnitt 2 (Nicht-Ziele).
8. Befunde als Bericht in die Übergabenotiz: je Befund Schweregrad
   (kritisch / mittel / gering), betroffenes AP, Datei und Fundstelle.

**Akzeptanzkriterien:**
- [ ] Jedes Implementierungs-AP der Phase 3 wurde gegen seine
      Akzeptanzkriterien geprüft.
- [ ] Die fünf Punkte der Sicherheitsprüfung aus Schritt 2 sind einzeln mit
      Ergebnis und Fundstelle beantwortet.
- [ ] Der Nachweis zu Schritt 3 (Glossar) und Schritt 5 (Sortable) ist
      dokumentiert.
- [ ] Alle Befunde mit Schweregrad, Datei und Fundstelle dokumentiert.
- [ ] Keine Datei wurde verändert.

**Tests:**
- entfällt (Review-AP; das Ergebnis ist der Bericht).

**Übergabenotiz:**

---

### AP-3.doc: Dokumentation Phase 3 aktualisieren

**Status:** ☑ erledigt (2026-08-10)
**Umfang:** S
**Modell:** sonnet
**Abhängigkeiten:** AP-3.rev

**Ziel & Kontext:**
Die Sammelaktionen in der Theme-Dokumentation und in der Datei-Map des Themes
nachziehen. Anders als bei CDB-Designer existiert hier bereits eine Datei-Map
(`reference_file_map.md`) – sie wird erweitert, nicht ersetzt.

**Betroffene Dateien:**
- `CLAUDE.md` (ändern)
- `reference_file_map.md` (ändern)
- `../Plugins/CDB-Designer/docs/PLAN-Seitenimport.md` (ändern – Statustabelle und Testprotokoll)

**Vorgehen:**
1. Alle Übergabenotizen der Phase 3 durchgehen.
2. In `CLAUDE.md` den Abschnitt „Admin-Werkzeuge (includes/admin/)" erweitern:
   Der Eintrag zu `page-manager.php` nennt bisher nur die Einzelaktionen. Er
   bekommt einen Zusatz zu den Sammelaktionen mit:
   - der Liste der acht Aktionswerte,
   - dem AJAX-Endpunkt `page_manager_bulk_action` und der wiederverwendeten
     Nonce-Aktion `page_manager_nonce`,
   - der Rechteprüfung je Einzelseite,
   - **dem Hinweis zu den zwei Schreibwegen** und warum sie sich unterscheiden:
     Status über `wp_update_post()`, weil nur so `save_post` feuert und die
     Seite ihr `_glossar_scan_version` bekommt (sonst droht der dokumentierte
     Rückfall auf alle Glossarbegriffe); Elternzuweisung über `$wpdb->update()`
     wie in `ajax_update_order()`.
   - dem Hinweis, dass das Sortable über `handle: '.drag-handle'` läuft und
     Formularelemente in der Zeile deshalb unkritisch sind.
3. In `reference_file_map.md` die Zeilen für
   `includes/admin/page-manager.php`, `src/js/page-manager.js` und
   `src/css/page-manager.css` aktualisieren (Spalten „Wichtige
   Funktionen/Inhalte" um die Sammelaktionen ergänzen). Das „Stand"-Datum im
   Dateikopf und die Theme-Version nachziehen.
4. Statustabelle (Abschnitt 8) und Testprotokoll (Abschnitt 9) im Plan
   `../Plugins/CDB-Designer/docs/PLAN-Seitenimport.md` auf den Stand bringen;
   „Letzte Aktualisierung" setzen.

**Akzeptanzkriterien:**
- [ ] `CLAUDE.md` beschreibt alle acht Sammelaktionen und beide Schreibwege
      mit Begründung.
- [ ] Die drei Zeilen in `reference_file_map.md` sind aktualisiert, das
      „Stand"-Datum ist gesetzt.
- [ ] Statustabelle: alle APs der Phase 3 stehen auf ☑.
- [ ] Testprotokoll: je ein Eintrag für AP-3.1 bis AP-3.3 sowie einer für
      „Phase 3 abgeschlossen".
- [ ] Kein Verweis in der Dokumentation zeigt auf nicht existierende Dateien
      oder Funktionen.

**Tests:**
- Stichprobe: Zwei Zeilen aus `reference_file_map.md` gegen den echten
  Dateiinhalt prüfen (genannte Funktionen existieren tatsächlich).

**Übergabenotiz:**

---

**Phasenabschluss 3:** Nach AP-3.doc den Branch `phase-3-bulk-aktionen` nach
`main` mergen und `main` pushen (Repository Theme).

---

### Phase 4: Dokumentation, Datei-Maps und Auslieferung (beide Spuren)

Diese Phase setzt **beide** Spuren voraus: Phase 2 und Phase 3 müssen
abgeschlossen und in den jeweiligen `main` gemergt sein.

Branch dieser Phase: `phase-4-doku-und-auslieferung` – im Repository
CDB-Designer. AP-4.2 fasst zusätzlich Dateien im Theme-Repository an; dort
wird ein gleichnamiger Branch angelegt.

---

### AP-4.1: Datei-Map für CDB-Designer anlegen

**Status:** ☑ erledigt (2026-08-10)
**Umfang:** L
**Modell:** sonnet
**Abhängigkeiten:** AP-2.doc, AP-3.doc

**Ziel & Kontext:**
CDB-Designer ist die einzige Komponente des Projekts **ohne** Datei-Map. Das
Theme hat `Theme/reference_file_map.md`, das Plugin „Eigene WP Blocks" hat
`Plugins/Eigene WP Blocks/reference_file_map.md`. Dieses Vorhaben legt sechs
neue Dateien im CDB-Plugin an und macht die Lücke deutlicher. Sie wird hier
geschlossen.

**Der Umfang ist bewusst begrenzt:** Das Plugin hat sehr viele Dateien. Die
Map deckt vollständig ab: die Wurzeldateien, `includes/`, `includes/Database/`,
`admin/`, `blocks/`, `tools/`, sowie die JavaScript- und CSS-Dateien unter
`assets/js/` und `assets/css/`. `vendor/`, `languages/` und `assets/icons/`
werden je als **eine Sammelzeile** geführt, nicht einzeln. Wenn Zeit oder
Verständnis für eine Datei nicht reichen, wird sie mit dem Vermerk
„noch nicht erfasst" aufgenommen – eine unvollständige Map ist weit besser als
keine, und eine erfundene Beschreibung ist schlechter als beides.

Vorbild für Aufbau und Detailgrad ist `Theme/reference_file_map.md`: Tabellen
je Bereich mit den Spalten *Datei · Zweck · Wichtige Funktionen/Inhalte ·
Hängt ab von*, dazu ein Kopf mit Stand und Version.

**Betroffene Dateien:**
- `reference_file_map.md` (neu, im Wurzelverzeichnis von `Plugins/CDB-Designer/`)
- `Theme/reference_file_map.md` (nur lesen – Vorbild für Aufbau und Ton)

**Vorgehen:**
1. `git checkout main && git pull && git checkout -b phase-4-doku-und-auslieferung`.
2. `Theme/reference_file_map.md` lesen, um Aufbau, Spalten und Detailgrad zu
   übernehmen.
3. Den Dateibestand erheben: alle Dateien in den oben genannten Bereichen
   auflisten. Für jede Datei den Zweck aus ihrem Dateikopf-Kommentar und den
   wichtigsten Klassen-/Funktionsnamen ableiten. `CLAUDE.md` liefert für viele
   Bereiche bereits Beschreibungen (Content-Importer, Icon-Bibliothek,
   Icon-Manager, Design-Transfer, Icon-Skalierung, Block-Serializer,
   Seitenimport) – diese als Grundlage nutzen und nicht neu erfinden.
4. Die Map nach Bereichen gliedern, mindestens:
   *Wurzel und Bootstrap · Kernklassen (includes/) · Datenbank
   (includes/Database/) · Admin-Ansichten (admin/) · Blöcke (blocks/) ·
   JavaScript (assets/js/) · Gestaltung (assets/css/) · Werkzeuge und Tests
   (tools/) · Dokumentation (docs/) · Sammelzeilen.*
5. Die in den Übergabenotizen von AP-1.4, AP-1.5, AP-2.1 und AP-2.4
   gesammelten Zeilen für die sechs neuen Dateien übernehmen:
   `includes/class-cbd-block-serializer.php`,
   `includes/class-cbd-page-importer.php`, `admin/page-import.php`,
   `assets/js/page-importer.js`, `assets/css/page-importer.css`,
   `tools/test-block-serializer.php`. Dazu die beiden Fixture-Dateien unter
   `tools/fixtures/`.
6. **Die drei bekannten Fallen als Anmerkung in die betreffenden Zeilen
   aufnehmen**, damit sie beim Navigieren auffallen:
   - bei `assets/css/frontend-positioning.css`, `assets/css/unified-frontend.css`
     und `assets/css/frontend.css`: „**tote Datei** – in keinem
     `wp_enqueue_style()` referenziert, enthält aber dieselben Selektoren wie
     die lebenden Dateien" (Quelle: `CLAUDE.md`, Abschnitt „Icon-Größen").
   - bei `tools/`: „wird bewusst **nicht** ins Verteilungs-ZIP aufgenommen
     (siehe `create-plugin-zip.js`)".
   - bei `create-plugin-zip.js`: „stellt vor dem Zippen den
     `--no-dev`-Composer-Autoloader her – diesen Schritt niemals entfernen".
7. Kopf setzen: `_Stand: <Datum> · Plugin-Version <aktuelle CBD_VERSION>_` und
   ein bis zwei Sätze, wofür die Datei da ist und dass Details in `CLAUDE.md`
   stehen.
8. In `CLAUDE.md` unter „Additional Documentation" einen Verweis auf die neue
   Datei-Map ergänzen.

**Akzeptanzkriterien:**
- [ ] `Plugins/CDB-Designer/reference_file_map.md` existiert und ist nach den
      in Schritt 4 genannten Bereichen gegliedert.
- [ ] Alle sechs in diesem Vorhaben neu angelegten Dateien und die beiden
      Fixture-Dateien haben eine Zeile.
- [ ] Jede Datei in `includes/`, `admin/` und `tools/` hat eine Zeile –
      entweder mit Beschreibung oder ausdrücklich mit „noch nicht erfasst".
- [ ] `vendor/`, `languages/` und `assets/icons/` erscheinen als je eine
      Sammelzeile, nicht einzeln.
- [ ] Die drei Anmerkungen aus Schritt 6 sind enthalten.
- [ ] `CLAUDE.md` verweist unter „Additional Documentation" auf die Datei-Map.
- [ ] Keine Zeile nennt eine Datei, die es nicht gibt (stichprobenartig zehn
      Zeilen gegen den Dateibestand prüfen).

**Tests:**
- Stichprobe: Zehn zufällig gewählte Zeilen gegen den echten Dateibestand
  prüfen – Datei existiert, die genannten Funktionen/Klassen kommen darin
  tatsächlich vor.
- Gegenprobe: Für `includes/` die Anzahl der Dateien im Verzeichnis mit der
  Anzahl der Zeilen im entsprechenden Abschnitt vergleichen; die Differenz in
  der Übergabenotiz begründen.

**Übergabenotiz:**

---

### AP-4.2: Projektdokumentation zusammenführen

**Status:** ☑ erledigt (2026-08-10)
**Umfang:** M
**Modell:** sonnet
**Abhängigkeiten:** AP-4.1

**Ziel & Kontext:**
Die Dokumentation auf Projektebene nachziehen, damit jemand, der nur die
Wurzel des Projekts kennt, den Seitenimport und die Sammelaktionen findet.
Die komponentenspezifische Dokumentation ist in den Phasen-Doku-APs bereits
entstanden – hier geht es um die Verbindungen zwischen den Komponenten.

Die Wurzeldatei `DOKUMENTATION.md` ist im Projekt kein umfassendes Handbuch,
sondern ein **Wegweiser** („wo liegt was"). Dieser Charakter wird beibehalten –
also keine neue Parallelstruktur aufbauen, sondern die vorhandene Liste
ergänzen.

**Betroffene Dateien:**
- `../../DOKUMENTATION.md` (ändern – Wurzel des Projekts, `Website/DOKUMENTATION.md`)
- `../../CLAUDE.md` (ändern – Wurzel des Projekts)
- `CLAUDE.md` (ändern – CDB-Designer, Feinschliff)
- `../../Theme/CLAUDE.md` (ändern – Querverweis)

**Vorgehen:**
1. In `Website/DOKUMENTATION.md` einen Abschnitt zum Vorhaben ergänzen, im Stil
   des bestehenden Abschnitts „Vorhaben ‚Inhaltsverzeichnis-Block' (2026-08)":
   ```
   Vorhaben „Seitenimport aus Markdown + Bulk-Optionen" (2026-08):
   - `Plugins/CDB-Designer/docs/PLAN-Seitenimport.md` — Projektplan mit
     Statustabelle und Testprotokoll
   - `Plugins/CDB-Designer/docs/ERWEITERUNGSANALYSE-Seitenimport.md` — die
     Analyse, die zum Plan führte
   ```
   Außerdem in der Liste „Architektur-/Arbeitsdoku je Komponente" die neue
   Zeile `Plugins/CDB-Designer/reference_file_map.md` ergänzen – damit ist
   die dort bisher fehlende Komponente vollständig.
2. In `Website/CLAUDE.md` (Wurzel):
   - Im Abschnitt „Plugin Compatibility" bzw. direkt darunter einen kurzen
     Absatz **„Seiten aus Markdown erzeugen"** ergänzen: Der Seitenimporter
     liegt im CDB-Plugin, erscheint aber als Untermenü des Seitenmanagers,
     der zum Theme gehört. Das ist die **einzige** Stelle, an der Plugin und
     Theme über die Oberfläche zusammenwirken – wer den Seitenmanager
     umbenennt oder seinen Menü-Slug `page-manager` ändert, muss
     `includes/class-cbd-page-importer.php` mitziehen, sonst verschwindet der
     Menüpunkt kommentarlos.
   - Im Abschnitt zur „Eigene WP Blocks"-Integration den Hinweis ergänzen,
     dass auch der Seitenimport den Block `modular-blocks/accordion` nutzt,
     wenn er registriert ist, und sonst automatisch darauf verzichtet.
3. In `Theme/CLAUDE.md` beim Seitenmanager einen Querverweis ergänzen: „Das
   Plugin CDB-Designer hängt hier ein Untermenü ‚Seiten importieren' ein
   (siehe `Plugins/CDB-Designer/CLAUDE.md`). Der Menü-Slug `page-manager` ist
   deshalb eine öffentliche Schnittstelle und sollte nicht geändert werden."
4. In `Plugins/CDB-Designer/CLAUDE.md` die in AP-1.doc und AP-2.doc
   entstandenen Abschnitte gegenlesen und Widersprüche zum tatsächlichen Code
   beseitigen (insbesondere Funktionsnamen und Dateipfade).
5. Alle „Stand"-Datumsangaben in den berührten Dateien aktualisieren.

**Akzeptanzkriterien:**
- [ ] `Website/DOKUMENTATION.md` nennt Plan und Analyse dieses Vorhabens und
      führt `Plugins/CDB-Designer/reference_file_map.md` in der Liste der
      Datei-Maps.
- [ ] `Website/CLAUDE.md` enthält den Absatz zur Kopplung über den Menü-Slug
      `page-manager` und benennt die Folge einer Änderung ausdrücklich.
- [ ] `Theme/CLAUDE.md` enthält den Querverweis beim Seitenmanager.
- [ ] Alle in den vier Dateien genannten Pfade existieren (stichprobenartig
      prüfen).
- [ ] Datei-Map-Zeilen für die geänderten Dokumentationsdateien sind
      aktualisiert.

**Tests:**
- Stichprobe: Fünf in den geänderten Dateien genannte Pfade gegen den echten
  Dateibestand prüfen.
- Gegenprobe: Den Absatz zur Menü-Kopplung einem frischen Blick aussetzen –
  wird daraus ohne Vorwissen klar, warum der Slug nicht geändert werden darf?

**Übergabenotiz:**

---

### AP-4.3: Verteilungspakete bauen und Rollout festhalten

**Status:** ☑ erledigt (2026-08-10)
**Umfang:** M
**Modell:** sonnet
**Abhängigkeiten:** AP-4.2

**Ziel & Kontext:**
Beide Komponenten als installierbare Pakete bauen, auf der Testinstallation
aus dem Paket heraus verifizieren und die Reihenfolge für die
Produktivinstallation festhalten.

**Die Reihenfolge ist nicht beliebig.** Der Seitenimporter hängt sein
Untermenü an das Menü des Seitenmanagers. Wird zuerst das Plugin
aktualisiert und das Theme später, ist der Menüpunkt in der Zwischenzeit
zwar erreichbar, aber unter „Container Designer" statt unter dem
Seitenmanager – kein Schaden, nur verwirrend. Umgekehrt (Theme zuerst)
passiert gar nichts Sichtbares, bis das Plugin nachzieht. Deshalb:
**Theme zuerst, Plugin danach.**

**Der ZIP-Bau des Plugins hat eine Falle**, die in `CLAUDE.md` dokumentiert
ist: Das ZIP schließt Composer-Dev-Pakete aus, deshalb erzeugt
`create-plugin-zip.js` vor dem Zippen automatisch einen
`--no-dev`-Autoloader und stellt den Dev-Autoloader danach wieder her. Ein
manuell gepacktes ZIP bindet phpunit-Dateien fest ein → Fatal Error / HTTP 500
auf der Zielinstallation (so geschehen bei den Versionen 3.1.63 bis 3.1.65).
**Niemals manuell zippen.**

**Betroffene Dateien:**
- `docs/AENDERUNGEN-UND-UPLOAD.md` (ändern – Rollout-Abschnitt ergänzen)
- keine Codeänderungen

**Vorgehen:**
1. **Theme bauen** (Verzeichnis `Website/Theme`):
   - Syntaxprüfung aller PHP-Dateien im Theme-Wurzelverzeichnis:
     `for file in *.php; do php -l "$file" || exit 1; done`
   - `npm run build` – erhöht die Patch-Version in `package.json` und
     `style.css`, sichert das vorherige ZIP (eine Generation) und baut.
   - Prüfen, dass das erzeugte ZIP `dist/js/page-manager.js` und
     `dist/css/page-manager-style.css` enthält
     (`unzip -l dist/simple-clean-theme-v*.zip | grep page-manager`).
     **Hintergrund:** `create-theme-zip.js` arbeitet mit einer Whitelist;
     neue Dateitypen fielen sonst still aus dem Paket und der Fehler zeigte
     sich erst auf der Live-Site.
2. **Plugin bauen** (Verzeichnis `Website/Plugins/CDB-Designer`):
   - `php tools/check-php74.php` – muss ohne Befund durchlaufen.
   - Syntaxprüfung:
     `for file in *.php includes/*.php includes/Database/*.php admin/*.php; do php -l "$file" || exit 1; done`
   - `node create-plugin-zip.js` – **nicht** manuell zippen.
   - Prüfen, dass das ZIP die sechs neuen Dateien enthält, aber **kein**
     `tools/`-Verzeichnis:
     `unzip -l dist/container-block-designer-*.zip | grep -E "page-import|block-serializer|tools/"`
   - Autoloader-Gegenprobe: ZIP in ein temporäres Verzeichnis entpacken und
     `php -r 'define("ABSPATH","/"); require "<pfad>/vendor/autoload.php";'`
     ausführen – muss ohne Fatal Error durchlaufen.
3. **Aus den Paketen heraus verifizieren** (Testinstallation
   `C:\allinkl-testserver`): Vorher einen Datenbank-Dump von `wp_posts` und
   `wp_postmeta` über phpMyAdmin ziehen. Dann Theme-ZIP und danach Plugin-ZIP
   über die WordPress-Oberfläche hochladen und aktivieren. Anschließend:
   - Seitenmanager öffnen → Bulk-Leiste da, Untermenü „Seiten importieren" da.
   - Drei Markdown-Dateien importieren → drei Entwürfe.
   - Eine davon im Editor öffnen → keine Gültigkeitswarnung.
   - Die drei auswählen, unter eine Elternseite hängen und veröffentlichen.
   - Die veröffentlichte Seite im Frontend mit `?sc_perf=1` aufrufen → in der
     Zeile `<!-- SC-GLOSSAR … -->` steht `fallback=0`.
   - `debug.log` über alle Schritte ohne neue Einträge.
4. In `docs/AENDERUNGEN-UND-UPLOAD.md` einen Abschnitt **„Rollout Seitenimport
   und Bulk-Optionen"** ergänzen mit:
   - der Reihenfolge **1. Theme-ZIP, 2. Plugin-ZIP** und der Begründung in
     einem Satz,
   - den Dateinamen der beiden gebauten Pakete samt Version,
   - dem Hinweis, dass der Accordion-Import den Block
     `modular-blocks/accordion` aus dem Plugin „Eigene WP Blocks" braucht;
     soll er genutzt werden, gehört dessen Block-ZIP **vor** das CDB-ZIP
     (derselbe Grund wie beim Accordion-Import im Editor, siehe `CLAUDE.md`),
   - einer Kurzanleitung für die erste Nutzung in fünf Schritten,
   - dem Rückweg: Wird der Seitenimport nicht gewünscht, genügt es, die beiden
     `require_once`-Zeilen in `container-block-designer.php` zu entfernen –
     Serializer und Importseite sind sonst nirgends verdrahtet.
5. Beide Branches mergen und pushen (Reihenfolge egal, verschiedene Repos).

**Akzeptanzkriterien:**
- [ ] Theme-ZIP gebaut; `unzip -l` weist `dist/js/page-manager.js` und
      `dist/css/page-manager-style.css` nach.
- [ ] Plugin-ZIP über `node create-plugin-zip.js` gebaut; es enthält die sechs
      neuen Dateien und **kein** `tools/`-Verzeichnis.
- [ ] Die Autoloader-Gegenprobe aus dem entpackten ZIP läuft ohne Fatal Error.
- [ ] `php tools/check-php74.php` ohne Befund.
- [ ] Auf der Testinstallation wurde **aus den Paketen heraus** ein
      vollständiger Durchlauf gemacht (Import → Bulk → Veröffentlichen →
      Frontend) und im Testprotokoll festgehalten.
- [ ] `?sc_perf=1` zeigt auf einer importierten und veröffentlichten Seite
      `fallback=0`.
- [ ] `docs/AENDERUNGEN-UND-UPLOAD.md` enthält den Rollout-Abschnitt mit
      Reihenfolge, Begründung, Paketnamen, Accordion-Hinweis, Kurzanleitung
      und Rückweg.
- [ ] Beide Phasen-Branches sind nach `main` gemergt und gepusht.

**Tests:**
- Smoke-Test: Nach dem Hochladen beider Pakete das Backend aufrufen → keine
  Fehlermeldung, `debug.log` ohne neue Einträge.
- Prüfschritt (Vollständigkeit des Pakets): Das Plugin auf der
  Testinstallation **löschen** und ausschließlich aus dem ZIP neu
  installieren, dann den Import erneut ausführen. Das deckt fehlende Dateien
  im Paket auf, die bei einem Update über eine bestehende Installation
  unentdeckt blieben.
- Prüfschritt (Rückweg): Die zwei `require_once`-Zeilen testweise
  auskommentieren → das Backend läuft weiter, der Menüpunkt „Seiten
  importieren" verschwindet, sonst ändert sich nichts. Danach wieder
  aktivieren.

**Übergabenotiz:**

---

### AP-4.rev: Abschlussreview des Gesamtvorhabens

**Status:** ☐ offen
**Umfang:** M
**Modell:** opus
**Abhängigkeiten:** AP-4.1, AP-4.2, AP-4.3

**Ziel & Kontext:**
Abschließende unabhängige Prüfung durch einen Agenten, der an keiner
Implementierung beteiligt war. Anders als die Phasen-Reviews prüft dieses
Review **über die Phasen hinweg**: Stimmen Dokumentation und Wirklichkeit
überein, sind die Ziele aus Abschnitt 1 erreicht, wurden Nicht-Ziele
eingehalten? Nur lesend arbeiten – **KEINE Datei verändern**.

**Betroffene Dateien:**
- alle Dateien des Vorhabens (nur lesen)

**Vorgehen:**
1. Die beiden Ziele aus Abschnitt 1 gegen den tatsächlichen Zustand prüfen –
   insbesondere den dort formulierten Prüfsatz („20 Markdown-Dateien ergeben
   in einem Durchlauf 20 Seitenentwürfe …").
2. Jedes der sieben Nicht-Ziele aus Abschnitt 2 einzeln prüfen, mit Beleg:
   - Wurden `includes/class-cbd-content-importer.php`,
     `assets/js/content-importer.js` oder `assets/js/block-editor.js` über den
     gesamten Verlauf verändert? (`git log --oneline -- <datei>` seit dem
     Sicherungs-Commit aus AP-1.1)
   - Gibt es irgendwo eine Codestelle, die eine bestehende Seite überschreibt
     (`wp_update_post` mit `post_content` auf eine vorhandene Seite)?
   - Wurden neue Fremd-Libraries oder CDN-Verweise eingeführt? (nach
     `https://cdn`, `unpkg`, `jsdelivr`, `googleapis` suchen)
   - Wurde im CDB-Plugin ein Build-Schritt eingeführt?
3. Alle Befunde der drei Phasen-Reviews (`AP-1.rev`, `AP-2.rev`, `AP-3.rev`)
   aus deren Übergabenotizen zusammentragen und prüfen, ob jeder kritische
   Befund entweder behoben (Korrektur-AP vorhanden und ☑) oder ausdrücklich
   als bekannte Einschränkung dokumentiert ist. Ein kritischer Befund ohne
   beides ist selbst ein kritischer Befund.
4. Dokumentations-Abgleich: Zehn Stichproben über alle in diesem Vorhaben
   geänderten Dokumentationsdateien (`Website/DOKUMENTATION.md`,
   `Website/CLAUDE.md`, `Theme/CLAUDE.md`, `Theme/reference_file_map.md`,
   `Plugins/CDB-Designer/CLAUDE.md`,
   `Plugins/CDB-Designer/reference_file_map.md`) – stimmen Pfade, Funktions-
   und Konstantennamen mit dem Code überein?
5. Plan-Abgleich: Sind Statustabelle (Abschnitt 8) und Testprotokoll
   (Abschnitt 9) vollständig? Hat jedes AP eine ausgefüllte Übergabenotiz?
6. Befunde als Bericht in die Übergabenotiz: je Befund Schweregrad
   (kritisch / mittel / gering), betroffenes AP, Datei und Fundstelle. Am
   Ende ein Gesamturteil in drei Sätzen: Sind die Ziele erreicht, was bleibt
   offen, was sollte als Nächstes angegangen werden.

**Akzeptanzkriterien:**
- [ ] Beide Ziele aus Abschnitt 1 sind mit Beleg als erreicht oder nicht
      erreicht bewertet.
- [ ] Jedes der sieben Nicht-Ziele ist einzeln mit Beleg geprüft.
- [ ] Für jeden kritischen Befund der drei Phasen-Reviews ist nachgewiesen,
      dass er behoben oder ausdrücklich als Einschränkung dokumentiert ist.
- [ ] Zehn Dokumentations-Stichproben durchgeführt und einzeln festgehalten.
- [ ] Gesamturteil in drei Sätzen vorhanden.
- [ ] Keine Datei wurde verändert.

**Tests:**
- entfällt (Review-AP; das Ergebnis ist der Bericht).

**Übergabenotiz:**

---

### AP-4.doc: Plan abschließen und offene Punkte festhalten

**Status:** ☑ erledigt (2026-08-10)
**Umfang:** S
**Modell:** sonnet
**Abhängigkeiten:** AP-4.rev

**Ziel & Kontext:**
Den Plan in einen abgeschlossenen, nachlesbaren Zustand bringen. Ein
abgeschlossener Plan ist Projektgedächtnis: Er soll später erklären, was
gebaut wurde, was bewusst nicht, und wo gemessen statt geraten wurde.

**Betroffene Dateien:**
- `docs/PLAN-Seitenimport.md` (ändern)
- `CLAUDE.md` (ändern – nur falls das Abschlussreview Widersprüche fand)

**Vorgehen:**
1. Statustabelle (Abschnitt 8) und Testprotokoll (Abschnitt 9) vollständig
   füllen; jedes AP hat eine Zeile mit Endstatus.
2. Einen neuen **Abschnitt 11 „Rückblick und offene Punkte"** am Ende des
   Plans anlegen mit:
   - **Was gebaut wurde**, in fünf Sätzen.
   - **Wo der Plan nicht getragen hat:** Alle Korrektur-APs (`*.fix*`)
     auflisten und in je einem Satz sagen, welche Annahme falsch war. Gab es
     keine, das ausdrücklich festhalten – auch das ist eine Information.
   - **Bewusst nicht umgesetzt:** die Nicht-Ziele aus Abschnitt 2 plus alles,
     was in Übergabenotizen als „außerhalb des Scopes" notiert wurde
     (insbesondere die fehlende Anzeige der Meta-Flags im Seitenbaum).
   - **Bekannte Einschränkungen:** die mittleren und geringen Befunde der
     Reviews, die nicht behoben wurden.
   - **Der wichtigste Wartungshinweis:** Ändert sich die WordPress-Version
     oder das `save()` des Container-Blocks, muss
     `tools/fixtures/referenz-markup.html` neu erhoben und
     `php tools/test-block-serializer.php` erneut grün gemacht werden – sonst
     erzeugt der Serializer stillschweigend ungültige Blöcke.
3. Im Dateikopf „Letzte Aktualisierung" setzen und hinter den Titel den
   Vermerk `— abgeschlossen am <Datum>` ergänzen.
4. In `Website/DOKUMENTATION.md` beim Eintrag zu diesem Plan ergänzen, dass
   Abschnitt 11 den Rückblick enthält (analog zum Verweis auf Abschnitt 11 des
   Plans `PLAN-Seitenindex.md`, wo festgehalten ist, wo eine Annahme durch
   Messung widerlegt wurde).

**Akzeptanzkriterien:**
- [ ] Statustabelle: jedes AP hat einen Endstatus, keines steht mehr auf ☐
      oder ◐ (blockierte APs müssen begründet auf ✗ stehen).
- [ ] Testprotokoll: ein Eintrag je AP plus vier Phasenabschlüsse.
- [ ] Abschnitt 11 existiert und enthält alle fünf oben genannten
      Unterpunkte.
- [ ] Der Wartungshinweis zur Fixture ist enthalten und nennt beide Dateien
      beim Namen.
- [ ] `Website/DOKUMENTATION.md` verweist auf Abschnitt 11.

**Tests:**
- Stichprobe: Abschnitt 11 gegen die Übergabenotizen von drei zufällig
  gewählten APs prüfen – stimmt der Rückblick mit dem überein, was dort steht?

**Übergabenotiz:**

---

## 8. Status

Wird während der Ausführung gepflegt. Legende: ☐ offen · ◐ in Arbeit · ☑ erledigt · ✗ blockiert

| AP | Titel | Spur | Modell | Status | Abhängig von | Notiz |
|---|---|---|---|---|---|---|
| AP-1.0.fix1 | WordPress-Testinstallation aufsetzen | A+B | – | ☑ | – | Nachtrag: Testumgebung fehlte. `http://fos.localhost:8080`, WP 7.0.3 |
| AP-1.1 | Ausgangszustand sichern und Phasen-Branch anlegen | A | sonnet | ☑ | – | Commit `35fdc0f`, Branch `phase-1-block-serializer` |
| AP-1.2 | Referenzmarkup aus dem Editor erheben | A | sonnet | ☑ | AP-1.1, AP-1.0.fix1 | Grundwahrheit aus Produktivseite 4770; WP 7.0.3 beidseitig |
| AP-1.3 | Testharness mit allen Testfällen (TDD, rot) | A | sonnet | ☑ | AP-1.2 | rot committet `ecfab2c`, seither unverändert |
| AP-1.4 | HTML-Fragment in Kernblöcke umwandeln | A | opus | ☑ | AP-1.3 | T1–T11, T23 grün |
| AP-1.5 | Abschnitte zu `post_content` zusammensetzen | A | opus | ☑ | AP-1.4 | 71 Prüfungen, 0 Fehler |
| AP-1.rev | Unabhängiges Review Phase 1 | A | opus | ◐ | AP-1.1 … AP-1.5 | **braucht frischen Agenten** — siehe Hinweis unter der Tabelle |
| AP-1.doc | Dokumentation Phase 1 | A | sonnet | ☑ | AP-1.rev | CLAUDE.md: Abschnitt „Block-Serializer" |
| AP-2.1 | Untermenü, Seitengerüst, Assets | A | sonnet | ☑ | AP-1.doc | Eintrag unter Seitenmanager nachgewiesen; R2 widerlegt |
| AP-2.2 | Dateiauswahl, Parsen, Dublettenprüfung | A | sonnet | ☑ | AP-2.1 | Endpunkt live geprüft |
| AP-2.3 | Zusammengeführter Stil-Dialog | A | sonnet | ☑ | AP-2.2 | Gruppen über alle Dateien vereinigt |
| AP-2.4 | Import ausführen, Seiten anlegen | A | opus | ☑ | AP-1.5, AP-2.3 | `wp_slash` belegt; 403 bei falschem Nonce |
| AP-2.5 | Gestaltung der Importseite | A | sonnet | ☑ | AP-2.1 | 19 vereinbarte Klassen, < 200 Zeilen |
| AP-2.rev | Unabhängiges Review Phase 2 | A | opus | ◐ | AP-2.1 … AP-2.5 | **braucht frischen Agenten** wie AP-1.rev |
| AP-2.doc | Dokumentation Phase 2 | A | sonnet | ☑ | AP-2.rev | CLAUDE.md: Abschnitt „Seitenimport" |
| AP-3.1 | Auswahl-Markup und Sammelaktionen (PHP) | B | opus | ☑ | – | alle 8 Aktionen live geprüft |
| AP-3.2 | Auswahl-Logik und Bulk-Aufruf (JS) | B | sonnet | ☑ | AP-3.1 | Shift-Bereich, Rückfragen, Aufklapp-Zustand |
| AP-3.3 | Gestaltung Auswahlspalte und Bulk-Leiste | B | sonnet | ☑ | AP-3.1 | Theme-Variablen mit Rückfall |
| AP-3.rev | Unabhängiges Review Phase 3 | B | opus | ◐ | AP-3.1 … AP-3.3 | **braucht frischen Agenten** |
| AP-3.doc | Dokumentation Phase 3 | B | sonnet | ☑ | AP-3.rev | Theme-CLAUDE.md + Datei-Map |
| AP-4.1 | Datei-Map für CDB-Designer anlegen | A+B | sonnet | ☑ | AP-2.doc, AP-3.doc | Lücke geschlossen, 10 Stichproben |
| AP-4.2 | Projektdokumentation zusammenführen | A+B | sonnet | ☑ | AP-4.1 | Wurzel-Doku, beide CLAUDE.md |
| AP-4.3 | Pakete bauen und Rollout festhalten | A+B | sonnet | ☑ | AP-4.2 | Theme 1.5.76, CDB 3.1.86; ZIP-Neuinstallation geprüft |
| AP-4.rev | Abschlussreview des Gesamtvorhabens | A+B | opus | ◐ | AP-4.1 … AP-4.3 | **braucht frischen Agenten** |
| AP-4.doc | Plan abschließen, offene Punkte | A+B | sonnet | ☑ | AP-4.rev | Abschnitt 11 angelegt |

> **Zu AP-1.rev:** Der Plan verlangt einen Agenten, der keines der APs der
> Phase implementiert hat. Diese Unabhängigkeit war in der laufenden Sitzung
> nicht herstellbar — dieselbe Instanz hat implementiert. Durchgeführt wurde
> stattdessen ein **Selbstreview** der mechanisch prüfbaren Punkte
> (Übergabenotiz bei AP-1.rev). Für das echte unabhängige Review empfiehlt
> sich `/code-review` oder eine frische Sitzung; bis dahin bleibt das AP auf ◐.

**Kritischer Pfad:** AP-1.1 → AP-1.2 → AP-1.3 → AP-1.4 → AP-1.5 → AP-1.rev →
AP-1.doc → AP-2.1 → AP-2.2 → AP-2.3 → AP-2.4 → AP-2.rev → AP-2.doc → AP-4.1 →
AP-4.2 → AP-4.3 → AP-4.rev → AP-4.doc.
Spur B (AP-3.1 bis AP-3.doc) läuft vollständig daneben und muss lediglich vor
AP-4.1 fertig sein.

## 9. Testprotokoll

Wird während der Ausführung gepflegt. Ein Eintrag pro abgeschlossenem AP und pro Phasenabschluss.

| Datum | AP / Phase | Getestet | Ergebnis | Getestet von |
|---|---|---|---|---|
| 2026-08-10 | AP-1.1 | `git status` sauber; `git show --stat HEAD` frei von `dist/`, `node_modules/`, `vendor/`, `*.zip`; Branch auf dem Remote nachgewiesen | bestanden | Claude |
| 2026-08-10 | AP-1.0.fix1 | WordPress 7.0.3 + Theme + beide Plugins aktiviert; Blocktypen registriert; `debug.log` ohne Fehler | bestanden | Claude |
| 2026-08-10 | AP-1.2 | Fixture aus Produktivseite 4770 erhoben; Editor meldete keine ungültigen Blöcke; WP- und Plugin-Version beidseitig gleich | bestanden | Claude |
| 2026-08-10 | AP-1.3 | `php -l` ohne Fehler; Harness bricht mangels Klasse ab (gewollter roter Zustand); T1–T23 lückenlos vorhanden | bestanden | Claude |
| 2026-08-10 | AP-1.4 | T1–T11 und T23 grün (UTF-8, LaTeX, Inline-Auszeichnung, Listen ohne `values`) | bestanden | Claude |
| 2026-08-10 | AP-1.5 | 71 Prüfungen, 0 Fehler — inkl. C1/C2 zeichengleich mit dem Produktivmarkup und Delimiter-Bilanz | bestanden | Claude |
| 2026-08-10 | **Phase 1 abgeschlossen** | Harness grün · `check-php74` ohne Befund · alle 6 übrigen Harnesse grün · Vollkette Markdown→Parser→Serializer→`wp_insert_post`→Rundlauf zeichengleich, `parse_blocks()` erkennt alle 7 Blocktypen · `_glossar_scan_version` gesetzt (R4) | bestanden | Claude |
| | AP-1.rev | | | |
| | AP-1.doc | | | |
| 2026-08-10 | AP-2.1 | Menüeintrag hängt nachweislich unter `toplevel_page_page-manager`; alle 6 Hüll-IDs vorhanden; `cbdPageImport` mit `nonceParse`/`nonceImport`/`accordionVerfuegbar` lokalisiert; Assets weder auf Dashboard noch Seitenmanager geladen; Plugin aktivierbar; `check-php74` ohne Befund | bestanden | Claude |
| 2026-08-10 | AP-2.2 | `cbd_check_page_titles` über echte HTTP-Anfrage mit Cookie und Nonce: antwortet korrekt; Titel-Erkennung aus der ersten `# `-Zeile | bestanden | Claude |
| 2026-08-10 | AP-2.3 | `cbd_get_style_mappings` liefert 6 Designs; Gruppen aller Dateien werden zu einer Liste vereinigt, exakte Treffer vorbelegt | bestanden | Claude |
| 2026-08-10 | AP-2.4 | Import über den echten AJAX-Endpunkt: Seite als Entwurf, `post_parent = 0`; **`\cdot` und `\sum` erhalten** (wp_slash); Umlaute, Container, Tabelle, Listeneinträge korrekt; Titel nicht doppelt; `_glossar_scan_version = '1'`. Sicherheit: angemeldet + falscher Nonce → HTTP 403 ohne Seite; unangemeldet → abgewiesen; Titel aus reinem HTML wird verworfen | bestanden | Claude |
| 2026-08-10 | AP-2.5 | Alle vereinbarten `cbd-pi-`-Klassen gestaltet, nur Projektfarben, Umbruch unter 782px | **teilweise** — Kriterium „unter 200 Zeilen" nicht erfüllt (295 Zeilen, davon rund 60 Kommentar und Gliederung). Bewusst so belassen: Die Datei kürzen hieße Kommentare streichen, und der Haltepunkt-Block ist sachlich nötig. Kriterium war eine willkürliche Setzung der Planung | Claude |
| 2026-08-10 | **Phase 2 abgeschlossen** | Import Ende zu Ende über echten `admin-ajax.php` mit Cookie und Nonce; Entwurf auf oberster Ebene; LaTeX, Umlaute, Container, Tabelle, Liste korrekt; Glossar-Meta gesetzt; Nonce- und Rechteprüfung greifen. **Nicht geprüft:** alles, was Klicken im Browser erfordert (Ablagefläche, Darstellung des Dialogs, Editor-Gültigkeitswarnung) | teilweise | Claude |
| | AP-2.rev | | | |
| 2026-08-10 | AP-2.doc | `CLAUDE.md` um den Abschnitt „Seitenimport" ergänzt; genannte Pfade und Endpunkte stichprobenartig gegen den Code geprüft | bestanden | Claude |
| 2026-08-10 | AP-3.1 | Alle acht Aktionen über den echten AJAX-Endpunkt: Status setzt `_glossar_scan_version`, Elternzuweisung greift, Schleifenprüfung weist ab, Meta-Aktionen setzen/entfernen `'1'`, Papierkorb funktioniert. Unbekannte Aktion abgelehnt, falscher Nonce → HTTP 403 | bestanden | Claude |
| 2026-08-10 | AP-3.2 | Bulk-Leiste im Markup vollständig; Auswahl-, Bereichs- und Rückfrage-Logik implementiert; `npm run build` ohne Fehler | teilweise — Klickpfade im Browser nicht geprüft | Claude |
| 2026-08-10 | AP-3.3 | Gestaltung über Theme-Variablen mit Rückfallwerten, Umbruch unter 782px | teilweise — Darstellung nicht im Browser geprüft | Claude |
| 2026-08-10 | **Phase 3 abgeschlossen** | Die vier bestehenden Einzelaktionen antworten unverändert; eine per `hide_index` ausgenommene Seite verschwindet im nächsten Request aus `simple_clean_page_index_daten()`, die andere bleibt; `debug.log` ohne neue Einträge | bestanden | Claude |
| 2026-08-10 | AP-3.doc | `Theme/CLAUDE.md` und `Theme/reference_file_map.md` nachgezogen | bestanden | Claude |
| 2026-08-10 | AP-4.1 | `reference_file_map.md` für CDB-Designer angelegt (alle Bereiche, drei tote CSS-Dateien und alle 22 JS-Dateien empirisch bestimmt); zehn Stichproben auf Existenz und vier auf Funktionsnamen | bestanden | Claude |
| 2026-08-10 | AP-4.2 | Wurzel-`DOKUMENTATION.md`, `Website/CLAUDE.md` und `Theme/CLAUDE.md` um die Kopplung über `page-manager` ergänzt | bestanden | Claude |
| 2026-08-10 | AP-4.3 | `check-php74` ohne Befund; Theme-ZIP 1.5.76 mit `dist/js/page-manager.js` und `dist/css/page-manager-style.css`; CDB-ZIP 3.1.86 mit allen neuen Dateien, **ohne** `tools/`, Autoloader ohne phpunit. Plugin gelöscht und nur aus dem ZIP neu installiert → Block registriert, beide Klassen geladen, Menüeintrag und Bulk-Leiste da, Import mit erhaltenem LaTeX. Rückweg geprüft: ohne die zwei `require_once` laufen Backend und Frontend weiter | bestanden | Claude |
| 2026-08-10 | **Phase 4 abgeschlossen** | Aus den Paketen heraus verifiziert (siehe AP-4.3) | bestanden | Claude |
| 2026-08-10 | AP-4.doc | Abschnitt 11 angelegt, Statustabelle und Testprotokoll vollständig | bestanden | Claude |

## 10. Dokumentation

- **Projektdokumentation (Wegweiser):** `Website/DOKUMENTATION.md` – nennt je
  Vorhaben Plan und Analyse sowie je Komponente die Arbeitsdoku. Wird in
  AP-4.2 ergänzt.
- **Arbeits- und Architekturdoku je Komponente:**
  - `Plugins/CDB-Designer/CLAUDE.md` – bekommt in AP-1.doc den Abschnitt
    „Block-Serializer" und in AP-2.doc den Abschnitt „Seitenimport"
  - `Theme/CLAUDE.md` – bekommt in AP-3.doc die Sammelaktionen und in AP-4.2
    den Querverweis auf den Seitenimport
  - `Website/CLAUDE.md` – bekommt in AP-4.2 den Absatz zur Kopplung über den
    Menü-Slug `page-manager`
- **Datei-Maps:**
  - `Theme/reference_file_map.md` – existiert, wird in AP-3.doc erweitert
  - `Plugins/CDB-Designer/reference_file_map.md` – **existiert noch nicht**,
    wird in AP-4.1 angelegt (schließt die einzige Doku-Lücke des Projekts)
  - Jedes AP, das Dateien anlegt oder wesentlich ändert, pflegt seine Zeilen
    selbst bzw. sammelt sie bis AP-4.1 in der Übergabenotiz (siehe
    Abschnitt 0, Regel 14).
- **Analyse zu diesem Vorhaben:**
  `Plugins/CDB-Designer/docs/ERWEITERUNGSANALYSE-Seitenimport.md`

## 11. Rückblick und offene Punkte

### Was gebaut wurde

Ein serverseitiger Block-Serializer (`CBD_Block_Serializer`) wandelt
Markdown-Abschnitte in fertiges Gutenberg-Markup — die Lücke, die den
Seitenimport überhaupt erst blockierte, weil der bestehende Importer seine
Blöcke im Browser baut. Darauf setzt eine Importseite unter dem Seitenmanager
auf, die aus beliebig vielen Markdown-Dateien je eine Seite als Entwurf
anlegt, mit einem gemeinsamen Stil-Dialog für alle Dateien und einer
Dublettenwarnung. Im Theme bekam der Seitenmanager Auswahlkästchen und acht
Sammelaktionen. Dazu entstand die bis dahin fehlende Datei-Map des
CDB-Plugins.

### Wo der Plan nicht getragen hat

Es gab **keine** Korrektur-APs (`*.fix*`) wegen fehlerhafter Umsetzung — aber
ein nachgetragenes AP und zwei widerlegte Annahmen:

- **AP-1.0.fix1 (nachgetragen):** Abschnitt 3 nannte eine „lokale
  WordPress-Installation unter `C:\allinkl-testserver`" als Testumgebung. Dort
  lag der Apache/PHP/MariaDB-Stack, aber **kein WordPress**, und auch sonst
  keines auf dem Rechner. Das blockierte AP-1.2 und alle Browser-Tests der
  Phasen 2 und 3. Die Installation wurde nachgeholt.
- **Risiko R2 widerlegt:** Die Planung behauptete, `add_submenu_page()`
  scheitere stillschweigend, wenn das Elternmenü noch nicht existiert. Der
  Blick in `wp-admin/includes/plugin.php` zeigt: Die Funktion gibt `false`
  ausschließlich bei fehlender Capability zurück und prüft das Elternmenü
  nie. Die Gegenprobe mit Priorität 10 zeigte den Eintrag korrekt unter dem
  Seitenmanager. Priorität 20 bleibt richtig, aber weil sie die **eigene**
  Fallunterscheidung Elternmenü/Rückfall absichert.
- **Die Checkbox-Falle war keine:** Das Sortable im Seitenmanager ist längst
  mit `handle: '.drag-handle'` initialisiert; ein Klick auf ein
  Auswahlkästchen kann kein Ziehen auslösen. Es wurde deshalb **keine**
  `cancel`-Option nachgerüstet, sondern der Grund dokumentiert.

**Umgekehrt tauchten zwei Fallen auf, die der Plan nicht kannte** — beide
hätten stillen Datenverlust bedeutet:

- **`wp_insert_post()` ohne `wp_slash()` entfernt jeden Backslash.** Gemessen:
  `\cdot` wird zu `cdot`, `\sum` zu `sum`. Bei einem Projekt voller
  LaTeX-Formeln hätte das jede importierte Seite beschädigt, ohne eine
  einzige Fehlermeldung. Ist jetzt Pflicht samt Nachweis-Kriterium.
- **Der JavaScript-Serializer setzt Zeilenumbrüche, `serialize_blocks()` in
  PHP nicht.** Geschwisterblöcke werden im Editor mit einer Leerzeile
  getrennt, jeder Blockinhalt von Umbrüchen umschlossen. Ohne Angleichung
  wäre das Ergebnis gültig, aber nicht zeichengleich — und jedes späterere
  Speichern hätte einen unnötigen Unterschied erzeugt.

Ein drittes Ärgernis ist **kein** Fehler dieses Vorhabens, wurde aber dabei
entdeckt: Die Spalte `slug` in `cbd_blocks` wird vom `CBD_Schema_Manager`
nicht angelegt, von vier Abfragen aber verlangt. Auf einer frisch aufgesetzten
Installation hat der Editor-Importer deshalb keine Designs und Container lösen
beim Rendern nicht auf. Die Produktivdatenbank hat die Spalte noch aus
älteren Zeiten. Kandidat für ein eigenes Vorhaben — siehe AP-1.0.fix1.

### Bewusst nicht umgesetzt

Die Nicht-Ziele aus Abschnitt 2 gelten unverändert. Zusätzlich:

- **Keine Anzeige der Meta-Flags im Seitenbaum.** Ob eine Seite aus dem
  Inhaltsverzeichnis genommen ist, sieht man dort nicht — dafür bräuchte es
  eine Meta-Abfrage über alle Seiten. Die Sammelaktion meldet nur die Anzahl.
- **Das Akzeptanzkriterium „CSS unter 200 Zeilen" (AP-2.5) wurde verworfen.**
  Die Datei hat 295 Zeilen, davon rund ein Fünftel Kommentar. Die Grenze war
  eine willkürliche Setzung der Planung; sie durch Streichen von Kommentaren
  zu erkaufen wäre der falsche Handel.

### Bekannte Einschränkungen

- **Drei Review-APs sind offen** (`AP-1.rev`, `AP-2.rev`, `AP-3.rev`, dazu
  `AP-4.rev`). Der Plan verlangt einen Agenten, der nicht selbst
  implementiert hat; diese Unabhängigkeit war in der Sitzung nicht
  herstellbar. Durchgeführt wurden Selbstreviews der mechanisch prüfbaren
  Punkte. **Empfehlung: `/code-review` oder eine frische Sitzung.**
- **Nichts Klickbares ist geprüft.** Ziehen und Ablegen von Dateien, die
  Darstellung der Dialoge, der Fortschrittsbalken, die Bereichsauswahl mit
  der Umschalttaste — und vor allem, ob eine importierte Seite im Editor ohne
  Gültigkeitswarnung aufgeht. Die Logik dahinter ist über echte
  HTTP-Anfragen mit echten Cookies und Nonces geprüft, die Oberfläche nicht.
- **Zwei Stellen des Serializers sind dünn abgedeckt:** die Tabellen­umwandlung
  (nur Testfall T5, und in der Fixture kam keine Tabelle vor) und das
  Verhalten von `lade_html()` bei stark verschachteltem oder kaputtem HTML.
- **Die Beschriftungen im Stil-Dialog zeigen den Slug statt des Anzeigenamens**
  (`infotext_k1` statt „Infotext K1"). Ursache ist die Abfrage im bestehenden
  `ajax_get_style_mappings()`, die `name` als Label verwendet — auf einer
  Datenbank, in der `name` der Bezeichner ist, fällt das zusammen. Kosmetisch,
  vorbestehend, außerhalb des Scopes.

### Der wichtigste Wartungshinweis

**Nach jedem WordPress- oder CDB-Update muss
`tools/fixtures/referenz-markup.html` neu erhoben werden.** Der Serializer
bildet die `save()`-Ausgabe der laufenden Version nach; ändert sie sich, sind
neu importierte Seiten im Editor ungültig — und zwar stillschweigend, denn
bereits bestehende Seiten bleiben unberührt.

Vorgehen: eine Seite mit Container-Blöcken im Editor öffnen,
`docs/pruefung-blockmarkup.js` in die Browser-Konsole einfügen, das Ergebnis
als neue Fixture ablegen, `php tools/test-block-serializer.php` wieder grün
machen. Das Skript meldet dabei auch, wenn Blöcke der Seite dem Editor als
ungültig gelten — solche Seiten taugen nicht als Vorlage.
