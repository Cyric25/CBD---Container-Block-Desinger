# Projektplan: Vier Erweiterungen (Formeln, Block-Referenz, Icon-Position, Screenshot)

_Erstellt am: 2026-08-16 · Letzte Aktualisierung: 2026-08-16_

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
C. Unabhängige APs derselben Phase dürfen parallel bearbeitet werden – in
   getrennten Git-Worktrees mit je eigenem Branch. Welche APs parallel
   dürfen, steht ausdrücklich in Abschnitt 6. **APs, die dieselben Dateien
   ändern, nie parallel ausführen.**

**Arbeitsweise:**

1. Bearbeite genau EIN Arbeitspaket pro Auftrag, sofern nicht anders beauftragt.
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
   falsch, dokumentiere das in der Übergabenotiz und stoppe.
8. Ergebnis ins Testprotokoll (Abschnitt 9) eintragen.
9. Erst dann Status auf ☑. Bei Fehlschlag: Status ✗ (blockiert), Ursache in
   die Übergabenotiz, nicht mit abhängigen APs weitermachen.
10. Nach dem letzten Implementierungs-AP einer Phase zusätzlich:
    Integrationstest der Phase + Regressionscheck aller vorherigen Phasen.
    Eintrag ins Testprotokoll.
11. Danach folgt das Review-AP (`AP-<N>.rev`): Es wird von einem frischen
    Agenten ausgeführt, der KEINES der APs dieser Phase implementiert hat.
    Der Review-Agent arbeitet ausschließlich lesend und verändert keine
    Datei. Kritische Befunde führen zu Korrektur-APs (siehe Regel 16).

**Übergabe:**

12. Fülle die Übergabenotiz deines APs aus: was geändert wurde, getroffene
    Entscheidungen, was für Folge-APs relevant ist.
13. Hat dein AP Dateien angelegt, verschoben oder wesentlich geändert:
    aktualisiere deren Zeilen in der `reference_file_map.md` der betroffenen
    Komponente (`Plugins/CDB-Designer/reference_file_map.md` bzw.
    `Plugins/Eigene WP Blocks/reference_file_map.md`).
14. Aktualisiere „Letzte Aktualisierung" im Dateikopf dieses Plans.
15. Git: mindestens ein Commit mit AP-ID im Text, z. B.
    `AP-1.2: Accordion verliert keine Textknoten mehr`. Nach jedem
    abgeschlossenen AP den Phasen-Branch pushen. Phasen-Branches erst nach
    bestandenem Integrationstest UND Review in `main` mergen.

**Umplanung:**

16. Zeigt sich, dass der Plan nicht trägt, werden Korrektur-APs mit
    fortlaufender Nummer ergänzt (`AP-<N>.fix1`, …) und in Statustabelle und
    Testprotokoll aufgenommen. Bestehende APs und Übergabenotizen werden nie
    gelöscht, nur ergänzt.

**Projektspezifische Pflichtregeln – diese gelten für JEDES AP:**

17. **PHP 7.4 im CDB-Designer.** Die Zielumgebung ist PHP 7.4.33, lokal läuft
    PHP 8.x. `php -l` meldet 8.0-Syntax NICHT als Fehler. Nach jeder Änderung
    an einer PHP-Datei im CDB-Designer zwingend
    `php tools/check-php74.php` im Plugin-Verzeichnis ausführen und grün
    bekommen. Verboten: `match`, Nullsafe `?->`, Constructor Promotion,
    benannte Argumente, `str_contains`, `str_starts_with`, `str_ends_with`,
    `mixed`, Union Types. Erlaubt: `??`, `??=`, Arrow Functions, typisierte
    Properties.
18. **Keine CDN-Einbindungen.** Projektentscheidung (DSGVO). Alle
    Fremdbibliotheken liegen lokal. Keine neue externe Ressource einführen.
19. **Debug-Ausgaben gaten.** JavaScript: `console.log` nur hinter
    `window.cbdDebug`. `console.error`/`console.warn` dürfen ungegatet bleiben.
    PHP: Informations-Logs hinter `if (defined('WP_DEBUG') && WP_DEBUG)`.
20. **Tote CSS-Dateien nicht anfassen.** `assets/css/frontend.css`,
    `assets/css/frontend-positioning.css` und `assets/css/unified-frontend.css`
    sind in KEINEM `wp_enqueue_style()` referenziert. Sie enthalten dieselben
    Selektoren wie die lebenden Dateien. Wer dort ändert, sieht nichts
    passieren. Lebende Frontend-Datei ist `assets/css/cbd-frontend-clean.css`,
    dazu `assets/css/custom-icons.css` (hängt per Handle an
    `cbd-frontend-clean`). Gleiches gilt für alle `*.js.backup`-Dateien.
21. **`wp_unslash()` vor jedem `json_decode()`** von `$_POST`-Daten. WordPress
    slasht `$_POST`; ohne Entfernen scheitert das Dekodieren stillschweigend.
    Dieser Fehler hat schon einmal alle Icon-Werte zerstört (v3.1.78).
22. **`wp_slash()` vor `wp_insert_post()`/`wp_update_post()`.** Ohne die
    Maskierung entfernt WordPress jeden Backslash – jede LaTeX-Formel wäre
    still zerstört (`\cdot` wird zu `cdot`).
23. **Keine Versionsnummer erhöhen.** Weder in
    `container-block-designer.php` noch in `modular-blocks-plugin.php` oder
    `package.json`. Das geschieht ausschließlich in den Abnahme-APs
    (AP-1.5, AP-2.7) bzw. automatisch durch die ZIP-Bauskripte. Sonst
    kollidieren parallel laufende APs in derselben Zeile.

---

## 1. Projektziel

Vier voneinander unabhängige Erweiterungen an den beiden Plugins des Projekts:

1. **Formeln in Accordions** werden korrekt dargestellt – kein verlorener
   Text neben den Klappzeilen, korrekte Höhe beim Aufklappen, KaTeX wird
   zuverlässig geladen.
2. **Block-Referenz als Modul:** Ein Verweis auf einen Container-Block öffnet
   diesen in einem Overlay auf derselben Seite, sodass man ohne Seitenwechsel
   nachlesen kann. Liegt der Block auf derselben Seite, wird er aus dem DOM
   geklont; sonst über einen abgesicherten Endpunkt nachgeladen.
3. **Icon-Position** wird wieder einstellbar: vier Container-Ecken plus
   Feinversatz in Pixeln, mit Live-Vorschau im Admin-Formular.
4. **Screenshot auf Apple-Geräten:** Der Screenshot-Knopf wird auf
   iOS/iPadOS/macOS-Safari zum Einzelblock-PDF-Knopf umgeschaltet, weil der
   Screenshot-Weg dort nicht zuverlässig funktioniert.

## 2. Nicht-Ziele

- **Das Theme wird nicht geändert.** Weder `Theme/functions.php` noch
  `Theme/includes/sichtbarkeit.php` noch Theme-CSS/JS. Der neue
  REST-Endpunkt greift ausschließlich lesend über `function_exists()` auf
  `simple_clean_seite_sichtbar()` zu – so, wie es die bestehende Naht
  vorsieht. Ein Vite-Build im Theme wird nicht nötig.
- **Kein serverseitiger PNG-Export.** mPDF/TCPDF erzeugen nur PDF; PDF→PNG
  bräuchte Imagick+Ghostscript oder Headless Chrome, was auf dem
  Shared Hosting nicht verfügbar ist.
- **Der Screenshot-Weg wird für Apple nicht repariert**, nur umgeleitet. Die
  bekannten iOS-Klippen (User-Gesture vor `clipboard.write`, Canvas-Flächen-
  limit, `<a download>` mit Data-URL) werden nicht einzeln angegangen.
- **Kein Build-Schritt für den CDB-Designer.** Das Plugin arbeitet bewusst
  ohne Webpack/Babel: IIFE, Zugriff über `wp.*`-Globale. `blocks/block-reference/index.js`
  wird auf dieses Muster umgeschrieben, nicht ein Build eingeführt.
- **Der Gutenberg-Editor zeigt weiterhin kein Container-Icon.** Die
  Icon-Position wirkt im Frontend und in der Admin-Vorschau, nicht im
  Block-Editor. Dort existiert heute kein Icon-Element, das man positionieren
  könnte.
- **Kein Zwischenspeicher (Transient/Option) für den neuen REST-Endpunkt.**
  Dieselbe URL liefert für Lehrperson, Klassensitzung und anonymen Besucher
  unterschiedliche Inhalte.
- **Die Delimiter `\(…\)` und `\[…\]` werden serverseitig ergänzt, aber
  `renderMathInElement` wird nicht aktiviert.** Ein zweiter, parallel
  laufender Renderpfad würde doppelt rendern.
- **Kein Umbau der doppelten Admin-Formulare.** `admin/new-block.php` und
  `admin/edit-block.php` enthalten dieselbe Vorschau-Logik zweimal. Das ist
  bekannte technische Schuld; sie wird in diesem Vorhaben gepflegt, nicht
  beseitigt.
- **Keine Bereinigung der toten Dateien.** `assets/css/frontend*.css`,
  `assets/lib/modern-screenshot.min.js`, `admin/import-export.php` und die
  `*.js.backup`-Dateien bleiben liegen. Sie werden in Abschnitt 8 der
  Dokumentation als offener Punkt vermerkt.

## 3. Kontext & Constraints

- **Projekt:** WordPress-Website „FOS Online Schulbuch",
  `c:\Users\mtnhu\OneDrive - Bildungsdirektion\#Unterricht\Website`.
  Drei Komponenten: Theme, Plugin CDB-Designer, Plugin „Eigene WP Blocks".
- **Betroffene Komponenten:** CDB-Designer (v3.1.87, `main`) und
  „Eigene WP Blocks" (v1.1.8). Das Theme (v1.5.79) bleibt unberührt.
- **Umgebung Produktiv:** All-inkl Shared Hosting, PHP 7.4.33, kein SSH,
  kein WP-CLI. WordPress 6.0+.
- **Sprachversionen:**
  | Komponente | PHP | Build |
  |---|---|---|
  | CDB-Designer | **7.4+** (Ziel 7.4.33) | kein Build, IIFE + `wp.*`-Globale |
  | Eigene WP Blocks | 8.0+ | `npm run build` (webpack), Blöcke als Einzel-ZIPs |
  | Theme | 7.4+ | Vite – **wird nicht angefasst** |
- **Bestehende Konventionen:** `CLAUDE.md` und `reference_file_map.md` je
  Komponente sowie die Wurzel-`CLAUDE.md`. Diese haben Vorrang – keine neuen
  Konventionen erfinden.
- **Testumgebung:** Lokaler All-inkl-Simulator unter `C:\allinkl-testserver`.
  - Start: `C:\allinkl-testserver\start-server.cmd`, Stopp: `stop-server.cmd`
  - WordPress 7.0.3 unter **http://fos.localhost:8080/**
  - Installationspfad: `C:\allinkl-testserver\www\htdocs\w0000001\fos`
  - Plugins: `…\fos\wp-content\plugins\container-block-designer` und
    `…\fos\wp-content\plugins\modular-blocks-plugin`
  - Theme: `…\fos\wp-content\themes\fos-online-schulbuch` (aktiv)
  - `WP_DEBUG = true`, `WP_DEBUG_LOG = true`, `WP_DEBUG_DISPLAY = false`
    → Fehlerlog unter `…\fos\wp-content\debug.log`
  - Tabellenpräfix `wp_` → Block-Designs in `wp_cbd_blocks`
  - Datenbank `d0000001` / Benutzer `d0000001` / Passwort `EBZvYRyrEM34gtfmv3Z8`,
    phpMyAdmin unter http://pma.localhost:8080/
  - **Die Plugins liegen dort als Kopie, nicht als Verknüpfung.** Nach einer
    Änderung im Projektordner müssen die Dateien dorthin kopiert oder das
    ZIP installiert werden – sonst testet man den alten Stand.
- **PHP-CLI:** `php` im PATH ist **8.5.1**, der Testserver nutzt **8.3.32**
  (`C:\allinkl-testserver\php\8.3\php.exe`). Beide sind PHP 8 – deshalb ist
  `php tools/check-php74.php` im CDB-Designer nicht optional (siehe Regel 17).
- **Harte Grenzen:**
  - Keine CDN-Einbindungen (DSGVO-Entscheidung des Projekts).
  - Rückwärtskompatibilität: Bestehende Block-Designs in `wp_cbd_blocks`
    dürfen ihr Aussehen durch dieses Vorhaben **nicht** ändern.
  - Bestehende Seiteninhalte dürfen nicht als „ungültiger Block" im Editor
    erscheinen. Änderungen am gespeicherten Markup sind zu vermeiden;
    Änderungen am gerenderten Markup sind erlaubt.
  - Der Filter `simple_clean_lehrerseite_freigeben` behält den Standardwert
    `false`. Ein Fehler in der Naht muss zu wenig zeigen, nie zu viel.
- **Git-Strategie:** Branch pro Phase und Repository, Commit pro AP mit
  AP-ID im Text, Push nach jedem AP. Merge in `main` erst nach bestandenem
  Integrationstest und Review.
  | Repository | Branch Phase 1 | Branch Phase 2 |
  |---|---|---|
  | CDB-Designer | `phase-1-reparaturen` | `phase-2-funktionen` |
  | Eigene WP Blocks | `phase-1-latex-accordion` | – (nicht betroffen) |
- **Remote-Repositories** (bestehen bereits, alle Arbeitsverzeichnisse sauber):
  - CDB-Designer: https://github.com/Cyric25/CBD---Container-Block-Desinger.git
  - Eigene WP Blocks: https://github.com/Cyric25/modular-blocks-plugin.git
  - Theme: https://github.com/Cyric25/FOS_Skripten_Website_Design.git (unberührt)
- **Vorbelastung im Repository „Eigene WP Blocks":** Der Branch
  `phase-1-accordion-grundlage` steht **14 Commits vor `main`** und ist nicht
  gemergt. Der Accordion-Block existiert in seiner heutigen Form nur dort.
  `PLAN-accordion-block.md` führt AP-1.4 und AP-2.4n auf ◐ („wartet auf
  Nutzer-Checkliste"), die Phasen 3 und 4 sind offen. **Entscheidung des
  Nutzers:** Der Branch wird in AP-1.0 nach `main` gemergt, bevor AP-1.2
  beginnt.

## 4. Architekturentscheidungen

| Entscheidung | Begründung | Verworfene Alternative |
|---|---|---|
| **Der LaTeX-Renderer bekommt eine öffentliche Funktion `window.cbdRenderLatex(root)`, die das Accordion nach dem Aufklappen aufruft** | `blocks/accordion/view.js:559` feuert in `finishOpen()` bereits ein `resize`-Ereignis als generisches „bitte neu vermessen"-Signal; der LaTeX-Renderer hört nur nicht zu. Eine benannte Funktion ist eindeutiger als ein Ereignis und erlaubt dem Accordion, die Höhenmessung **nach** dem Rendern zu wiederholen | Nur einen `resize`-Listener ergänzen: Der Renderer wüsste dann nicht, welcher Teilbaum betroffen ist, und die Höhenmessung liefe weiter vor dem Rendern. Verworfen |
| **Display-Formeln werden als `<span>` statt `<div>` ausgegeben** (`class-latex-parser.php:356`) | Ein `<div>` innerhalb eines `<p>` zwingt den HTML-Parser des Browsers, den Absatz aufzuspalten. Dabei entstehen nackte Textknoten, die `buildRows()` in `accordion/view.js:330` (iteriert über `content.children`) niemals in eine Klappzeile verschiebt – sie bleiben sichtbar daneben stehen. Die Block-Darstellung erreicht man mit `display:block` am `<span>` genauso | Nur den Accordion-Code auf `childNodes` umstellen: behebt das Symptom, lässt aber überall sonst zerrissene Absätze zurück. Beides wird gemacht, aber die Ursache liegt hier |
| **Die Icon-Position wird über zwei CSS-Variablen `--cbd-icon-dx` / `--cbd-icon-dy` transportiert, nicht über ein fertiges `transform`** | `.cbd-header-icon` trägt in `cbd-frontend-clean.css` ein `transform: translateY(-6px)` zur Grundlinien-Ausrichtung, mit **je eigenem Wert pro Breakpoint** (−6px Desktop, −4px ≤768px, −3px ≤480px). Ein serverseitig gesetztes `transform` würde alle drei löschen. Mit Variablen bleibt jeder Breakpoint bei seinem Basiswert und addiert nur den Versatz | Inline-`transform` aus PHP: bricht die Handy-Ausrichtung. Eigene Klasse je Pixelwert: unbrauchbar viele Klassen |
| **Der Positionswert `header` ist der neue Standard; die vier Altwerte `top-left`…`bottom-right` fallen beim Lesen auf `header` zurück** | In **jedem** bestehenden Design steht heute `"position":"top-left"` – ein Wert, den `cbd_parse_features_from_post()` bei jedem Speichern erzwingt und den danach niemand liest. Würde `top-left` künftig „linke obere Container-Ecke" bedeuten, verlöre jeder vorhandene Block beim Update sein Icon aus der Kopfzeile. Die neuen Werte heißen deshalb `container-top-left` usw. | Altwerte direkt umdeuten: ändert das Aussehen aller Bestandsblöcke. Migrationsskript: unnötig, da der Rückfall dasselbe leistet |
| **Die Positionsfelder liegen flach im `features`-JSON (`icon.position`, `icon.offsetX`, `icon.offsetY`), nicht verschachtelt** | `CBD_Design_Transfer` serialisiert Designs nach Markdown mit flachen Punkt-Pfaden und begrenzt in `sanitize_json_field()` die Verschachtelungstiefe. Ein `icon.position.x` läge eine Ebene tiefer und könnte am Export scheitern | Verschachteltes Objekt `icon.position = {mode, x, y}`: risikoreicher beim Design-Export, ohne Gewinn |
| **Der neue REST-Endpunkt bekommt `permission_callback => '__return_true'` und leistet die gesamte Autorisierung im Callback** | Der bestehende Endpunkt `cbd/v1/blocks` nutzt `current_user_can('edit_posts')`. Das ist ein Redakteursrecht – Schülerinnen und Schüler melden sich nie an, sie kommen über das Klassenpasswort. Ein Modal für sie kann diesen Callback nicht verwenden | `edit_posts` beibehalten: Modal funktionierte nur für Redakteure. Nonce als Ersatz: Für nicht Angemeldete ist `wp_create_nonce` für alle gleich, also keine Autorisierung |
| **Der Endpunkt liegt in einer eigenen Klasse `CBD_Block_Content_API`, nicht in `CBD_Blocks_REST_API`** | Die beiden Sicherheitsmodelle dürfen nicht vermischt werden: Dort gilt „nur Redakteure", hier „jeder, aber nur was er sehen darf". Zwei Routen mit gegensätzlichen Annahmen in einer Datei laden zum Fehler ein | Gemeinsame Klasse: erhöht die Wahrscheinlichkeit, dass eine Route versehentlich den falschen Callback bekommt |
| **Der Endpunkt verlangt `post_id` UND `stable_id`; die Berechtigung hängt an `post_id`** | `CBD_Block_Organizer::should_regenerate_id()` regeneriert beim Kopieren die Schlüssel `uniqueId`, `blockId`, `clientId`, `containerId` – **nicht `stableId`**. Nach `copy_block()` existiert dieselbe `stableId` auf zwei Seiten. Eine Suche allein nach `stableId` fände den falschen Block, womöglich auf einer gesperrten Seite | Suche nur nach `stableId`: Kollisionsgefahr und keine saubere Rechteprüfung |
| **Ablehnung und Nichtexistenz liefern dieselbe Antwort (404, identischer Text)** | Unterschiedliche Antworten ließen sich durch Durchprobieren von IDs zum Kartieren der gesperrten Lösungsseiten nutzen. Genau diesen Fehler hat das Theme in `simple_clean_lehrerseite_kanonisch()` bereits einmal behoben | Sprechende Fehlermeldungen: bequemer für Entwickler, aber ein Informationsleck |
| **Das Modal klont zuerst aus dem DOM und lädt nur nach, wenn der Block nicht auf der Seite liegt** | Entscheidung des Nutzers. Der DOM-Pfad braucht keinen Netzverkehr, keine Autorisierung und funktioniert auch offline; der Server-Pfad deckt den kapitelübergreifenden Fall ab | Immer nachladen: unnötige Anfragen und Latenz im häufigsten Fall |
| **Der Screenshot-Knopf wird clientseitig umgeschaltet, nicht serverseitig ausgeblendet** | Eine Apple-Erkennung im gerenderten HTML macht die Seitenausgabe vom User-Agent abhängig und vergiftet damit jeden Full-Page-Cache. Das Plugin hat eigene Cache-Logik (`class-cbd-block-registration.php:420-446`) | `if`-Bedingung in `class-cbd-block-registration.php:1155` erweitern: verworfen wegen Cache |
| **Auf Apple ersetzt der bestehende Einzelblock-PDF-Export den Screenshot** | Der Code steht bereits fertig in `assets/js/interactivity-store.js:403-424` (`window.cbdPDFExportServerSide([...], 'visual')`) und kommt für reine DOM-Blöcke ohne html2canvas aus – also ohne jede der iOS-Klippen. Nutzer verlieren keine Funktion | Knopf ersatzlos entfernen: einfacher, aber die Funktion fehlt dann auf allen Apple-Geräten |
| **Der Generator-Fehler wird als eigenes AP vor der Apple-Weiche behoben** | `interactivity-store.js:302` schreibt `yield tryWebShare(...)`, aber `tryWebShare` ist ein `function*` (`:306`). Der Aufruf liefert ein Generator-Objekt, kein Thenable – die Laufzeit gibt es unverändert zurück, der Rumpf läuft nie. Sobald die Zwischenablage scheitert, passiert gar nichts und der Nutzer sieht trotzdem den grünen Haken. Das betrifft **alle** Browser, nicht nur Apple | Zusammen mit der Apple-Weiche erledigen: beide ändern dieselbe Datei, sind aber getrennt prüfbar. Getrennt hält das Testprotokoll aussagekräftig |

## 5. Risiken & Rollback

| Risiko | Wahrscheinlichkeit | Auswirkung | Gegenmaßnahme / Rollback |
|---|---|---|---|
| **Der Merge des Accordion-Branches (AP-1.0) bringt unfertige Funktionalität nach `main`** | mittel | mittel | `PLAN-accordion-block.md` führt AP-1.4 und AP-2.4n auf ◐. AP-1.0 führt vor dem Merge den vorhandenen jsdom-Prüfharnisch aus und dokumentiert das Ergebnis. `main` bleibt als Tag `pre-latex-merge` markiert; Rückweg: `git reset --hard pre-latex-merge` |
| **`<div>` → `<span>` bei Display-Formeln verändert das Aussehen bestehender Formeln** | mittel | mittel | Das `<span>` bekommt in `latex-formulas.css` `display:block`, damit die Darstellung gleich bleibt. AP-1.5 prüft eine Bestandsseite mit Display-Formeln im Vorher/Nachher-Vergleich |
| **Änderung der `the_content`-Priorität im LaTeX-Parser bricht andere Inhalte** | mittel | hoch | Der Filter läuft heute auf Priorität 5, also **vor** `do_blocks()` (Priorität 9) – er sieht rohes Blockmarkup samt `<!-- wp:… -->`. AP-1.1 entfernt ihn, weil `render_block` (ebenfalls Priorität 5) dieselbe Arbeit bereits je Block leistet. Prüfschritt in AP-1.1: eine Seite mit Formeln **außerhalb** jedes Blocks (klassischer Editor) muss weiterhin funktionieren; tut sie es nicht, wird der Filter stattdessen auf Priorität 11 verschoben statt entfernt |
| **Der neue REST-Endpunkt gibt gesperrte Inhalte preis** | niedrig | **sehr hoch** | Das schwerwiegendste Risiko des Vorhabens. Der Endpunkt geht an allen vier Durchsetzungsstellen der Lehrersperre vorbei (`template_redirect` Prio 20, `redirect_canonical`, `pre_get_posts`, `rest_pre_dispatch`). AP-2.4 ist als opus-AP mit eigenem Prüfharnisch geplant; AP-2.rev prüft ihn gesondert. Grundsatz: **Standard ist Ablehnung.** Fällt eine Prüfung aus (Theme fehlt, Funktion unbekannt), wird abgelehnt, nicht durchgelassen |
| **Icon-Positionierung ändert das Aussehen bestehender Blöcke** | mittel | mittel | Der Standardwert `header` erzeugt exakt das heutige Markup und CSS. AP-2.1 hat als Akzeptanzkriterium, dass ein Design ohne die neuen Felder unverändert `header` liefert; AP-2.7 vergleicht eine Bestandsseite vorher/nachher |
| **Die beiden Admin-Formulare driften weiter auseinander** | hoch | gering | AP-2.3 ändert `admin/new-block.php` und `admin/edit-block.php` **im selben AP** und hat als Akzeptanzkriterium, dass die Icon-Abschnitte beider Dateien zeichengleich sind (bis auf die vorbelegten Werte) |
| **PHP-8.0-Syntax gelangt in den CDB-Designer** | mittel | hoch | Lokal läuft PHP 8.5.1, Zielumgebung 7.4.33. `php -l` meldet das nicht. Jedes PHP-ändernde AP hat `php tools/check-php74.php` als Akzeptanzkriterium; `create-plugin-zip.js` bricht zusätzlich ab |
| **Das Plugin-ZIP wird mit Dev-Autoloader gebaut → HTTP 500** | niedrig | hoch | `create-plugin-zip.js` führt `composer dump-autoload --no-dev --optimize` aus und stellt danach den Dev-Autoloader wieder her. **Diesen Schritt nie entfernen.** ZIPs ausschließlich über `node create-plugin-zip.js` bauen, nie manuell zippen. Prüfung nach dem Bau in AP-1.5/AP-2.7 |
| **Falsche Ausrollreihenfolge der ZIPs** | mittel | mittel | Erst das Block-ZIP `accordion.zip` aus „Eigene WP Blocks", **dann** das CDB-Plugin-ZIP. Grund: Der Content-Importer erzeugt `modular-blocks/accordion` nur, wenn der Blocktyp registriert ist. In AP-1.5 und AP-2.7 als Schritt festgehalten |
| **Modal-in-Modal / Selbstreferenz** | niedrig | mittel | Ein referenzierter Container kann selbst einen Block-Referenz-Block enthalten. `CBD_Block_Registration::render_block()` hat serverseitig eine `render_depth`-Bremse, die aber nur innerhalb einer Anfrage greift. AP-2.5 begrenzt die Verschachtelung im Browser auf eine Ebene |
| **Änderungen wirken auf dem Testserver nicht** | hoch | gering | Die Plugins liegen dort als **Kopie**, nicht als Verknüpfung. Jedes Abnahme-AP kopiert die Dateien ausdrücklich bzw. installiert das ZIP und prüft danach die Versionsanzeige im Plugin-Menü |

**Generelle Rollback-Strategie:** Branch pro Phase und Repository. Vor dem
Merge nach `main` wird der bisherige `main`-Stand getaggt
(`vor-phase-1`, `vor-phase-2`), Rückweg ist `git reset --hard <tag>`.
Zusätzlich vor AP-1.0 im Repository „Eigene WP Blocks" der Tag
`pre-latex-merge`. Ein Datenbank-Eingriff findet in diesem Vorhaben nicht
statt – das Schema bleibt unverändert, es kommen nur neue Schlüssel im
bestehenden `features`-JSON hinzu.

## 6. Phasenübersicht

Jede Phase endet mit `AP-<N>.rev` (unabhängiges Review) und `AP-<N>.doc`
(Dokumentation) – in dieser Reihenfolge nach den Implementierungs-APs.

| Phase | Ziel | Lauffähiger Endzustand | APs |
|---|---|---|---|
| **1** | Reparieren, was heute nicht funktioniert | Formeln in Accordions stimmen (kein verlorener Text, richtige Höhe, KaTeX lädt zuverlässig); der Block „Block-Referenz" lässt sich im Editor einfügen und springt im Frontend korrekt zum Ziel; der Screenshot liefert außerhalb von Apple wieder eine Datei statt eines grünen Hakens ohne Ergebnis | AP-1.0, AP-1.1, AP-1.2, AP-1.3, AP-1.4, AP-1.5, AP-1.rev, AP-1.doc |
| **2** | Die drei neuen Funktionen bauen | Block-Referenz öffnet ein Modal (DOM-Klon bzw. Nachladen); Icon-Position mit Koordinaten und Vorschau einstellbar; Screenshot-Knopf wird auf Apple-Geräten zum Einzelblock-PDF-Knopf; ZIPs gebaut und Ausrollreihenfolge dokumentiert | AP-2.1, AP-2.2, AP-2.3, AP-2.4, AP-2.5, AP-2.6, AP-2.7, AP-2.rev, AP-2.doc |

### Parallelisierung – verbindlich

**Phase 1:** AP-1.0 zuerst allein (Repository „Eigene WP Blocks").
Danach laufen **AP-1.1, AP-1.2, AP-1.3 und AP-1.4 gleichzeitig** – ihre
Dateimengen sind disjunkt:

| AP | Repository | Dateien |
|---|---|---|
| AP-1.1 | CDB-Designer | `includes/class-latex-parser.php`, `assets/js/latex-renderer.js`, `assets/css/latex-formulas.css` |
| AP-1.2 | Eigene WP Blocks | `blocks/accordion/view.js`, `blocks/accordion/style.css` |
| AP-1.3 | CDB-Designer | `blocks/block-reference/*`, `includes/class-cbd-block-reference.php`, `includes/class-cbd-blocks-rest-api.php` |
| AP-1.4 | CDB-Designer | `assets/js/interactivity-store.js`, `assets/js/interactivity-fallback.js` |

AP-1.1 und AP-1.2 greifen nur über eine Schnittstelle ineinander, die in
AP-1.1 **exakt festgeschrieben** ist (`window.cbdRenderLatex`, siehe dort).
AP-1.2 programmiert gegen diese Zusage und braucht nicht auf AP-1.1 zu
warten; die Integration prüft AP-1.5.

**Phase 2:** Zuerst laufen **AP-2.1, AP-2.4 und AP-2.6 gleichzeitig**.
Danach **AP-2.2, AP-2.3 und AP-2.5 gleichzeitig**:

| AP | Dateien (alle im CDB-Designer) |
|---|---|
| AP-2.1 | `includes/functions.php`, `tools/test-icon-position.php` (neu) |
| AP-2.4 | `includes/class-cbd-block-content-api.php` (neu), `container-block-designer.php`, `tools/test-block-content-api.php` (neu) |
| AP-2.6 | `assets/js/interactivity-store.js`, `assets/js/interactivity-fallback.js` |
| AP-2.2 | `includes/class-cbd-block-registration.php`, `assets/css/cbd-frontend-clean.css` |
| AP-2.3 | `admin/new-block.php`, `admin/edit-block.php`, `assets/js/icon-picker.js`, `assets/css/new-block-form.css`, `assets/css/edit-block-form.css` |
| AP-2.5 | `blocks/block-reference/render.php`, `view.js`, `style.css`, `block.json`, `index.js` |

**AP-2.4 ist das einzige AP der Phase 2, das `container-block-designer.php`
anfasst** (eine `require_once`-Zeile). Kein anderes AP darf diese Datei
ändern.

## 7. Arbeitspakete

Alle Pfadangaben sind relativ zum Projektstamm
`c:\Users\mtnhu\OneDrive - Bildungsdirektion\#Unterricht\Website`, sofern
nicht anders angegeben. „CDB" steht für `Plugins/CDB-Designer/`,
„EWB" für `Plugins/Eigene WP Blocks/`.

### Phase 1: Reparaturen

---

### AP-1.0: Accordion-Branch nach `main` mergen

**Status:** ☐ offen
**Umfang:** S
**Modell:** sonnet
**Abhängigkeiten:** keine

**Ziel & Kontext:**
Im Repository „Eigene WP Blocks" (`Plugins/Eigene WP Blocks/`) steht der
Branch `phase-1-accordion-grundlage` **14 Commits vor `main`** und ist nicht
gemergt. Der Accordion-Block existiert in seiner heutigen Form – Zeilen
werden zur Laufzeit aus Überschriften gebaut – ausschließlich auf diesem
Branch. Auf `main` liegt noch die überholte Zwei-Block-Architektur mit einem
separaten Kind-Block `accordion-row`, den es nicht mehr gibt.

AP-1.2 dieses Plans ändert `blocks/accordion/view.js`. Ohne diesen Merge
würde AP-1.2 gegen eine Datei arbeiten, die auf `main` gar nicht in dieser
Fassung existiert. Der Nutzer hat entschieden, den Branch vor Beginn der
Arbeit zu mergen.

Der Plan `Plugins/Eigene WP Blocks/PLAN-accordion-block.md` führt in seiner
Statustabelle AP-1.4 und AP-2.4n auf ◐ („Gates bestanden, wartet auf
Nutzer-Checkliste"). Diese offenen Punkte werden durch den Merge **nicht**
als erledigt erklärt, sondern ehrlich vermerkt.

**Betroffene Dateien:**
- `Plugins/Eigene WP Blocks/PLAN-accordion-block.md` (ändern – nur Statustabelle und ein Vermerk)
- Git-Zustand des Repositories `Plugins/Eigene WP Blocks` (Branch, Tag, Merge)

**Vorgehen:**
1. In `Plugins/Eigene WP Blocks` wechseln. Mit `git status` sicherstellen,
   dass das Arbeitsverzeichnis sauber ist. Ist es das nicht: abbrechen und
   melden.
2. Den vorhandenen Prüfharnisch des Accordion-Blocks ausführen und das
   Ergebnis wörtlich notieren. Suche ihn mit
   `git ls-files | findstr /I "test"` bzw. im Abschnitt „Testprotokoll" von
   `PLAN-accordion-block.md` (dort ist von „104 jsdom-Zusicherungen" bei
   AP-2.3n die Rede). Lässt er sich nicht ausführen, das in der
   Übergabenotiz festhalten und trotzdem fortfahren – der Merge ist eine
   Nutzerentscheidung.
3. `npm run build` ausführen. Der Build muss ohne Fehler durchlaufen.
4. Auf `main` wechseln und den bisherigen Stand taggen:
   `git checkout main` und `git tag pre-latex-merge`.
5. `git merge phase-1-accordion-grundlage --no-ff -m "Merge phase-1-accordion-grundlage: Accordion-Grundlage nach main"`
   ausführen. Bei Konflikten: abbrechen (`git merge --abort`), Status auf ✗
   setzen und melden – nicht raten.
6. `git push origin main` und `git push origin pre-latex-merge`.
7. In `PLAN-accordion-block.md` die Statustabelle ergänzen: Bei AP-1.4 und
   AP-2.4n in der Notizspalte anfügen:
   `Nach main gemergt am 2026-08-16 durch AP-1.0 des Plans PLAN-Vier-Erweiterungen.md; Nutzer-Checkliste weiterhin offen`.
   Den Status ◐ **nicht** auf ☑ ändern.
8. Neuen Branch für Phase 1 anlegen: `git checkout -b phase-1-latex-accordion`
   und mit `git push -u origin phase-1-latex-accordion` veröffentlichen.

**Akzeptanzkriterien:**
- [ ] `git diff --stat main phase-1-accordion-grundlage` gibt **nichts** aus
      (beide Branches haben denselben Dateibaum).
      **Korrigiert am 2026-08-16 (AP-1.0, 2. Anlauf).** Ursprünglich stand
      hier `git rev-list --left-right --count main...phase-1-accordion-grundlage`
      → `0	0`. Das ist mit dem in Schritt 5 vorgeschriebenen `--no-ff`-Merge
      **mathematisch nie erreichbar**: Der Merge-Commit selbst existiert nur
      auf `main` und wird immer als `1	0` gezählt. Ein Baumvergleich prüft
      das Gemeinte. Wer dieses Muster in einem anderen AP wiederverwendet,
      nimmt den Baumvergleich, nicht `rev-list`.
- [ ] Der Tag `pre-latex-merge` existiert lokal und auf dem Remote
      (`git ls-remote --tags origin` enthält ihn).
- [ ] `Plugins/Eigene WP Blocks/blocks/accordion/view.js` existiert auf
      `main` und enthält die Funktion `buildRows` (Prüfung:
      `git show main:blocks/accordion/view.js | findstr buildRows`).
- [ ] Ein Verzeichnis `blocks/accordion-row/` existiert **nicht** mehr.
- [ ] `npm run build` läuft ohne Fehler durch.
- [ ] Der Branch `phase-1-latex-accordion` existiert und ist auf dem Remote.
- [ ] In `PLAN-accordion-block.md` tragen AP-1.4 und AP-2.4n den Merge-Vermerk
      und stehen weiterhin auf ◐.

**Tests:**
- Smoke-Test: `git log --oneline -3` auf `main` zeigt den Merge-Commit.
- Prüfschritt: `npm run build` ausführen; die Ausgabe enthält kein „ERROR"
  und das Verzeichnis `build/blocks/accordion/` enthält `view.js`,
  `index.js`, `style-index.css`.
- Prüfschritt: Den Prüfharnisch aus Schritt 2 ausführen und das Ergebnis
  (Anzahl bestandener/fehlgeschlagener Zusicherungen) ins Testprotokoll
  eintragen.
- Regressionsrelevanz: Bestehende Seiten mit Accordion-Blöcken werden erst in
  AP-1.5 geprüft; dieses AP ändert keinen Produktivcode.

**Übergabenotiz:**

---

### AP-1.1: LaTeX-Renderer öffnen und Parser härten

**Status:** ☐ offen
**Umfang:** M
**Modell:** opus
**Abhängigkeiten:** keine

**Ziel & Kontext:**
Formeln in Accordion-Klappzeilen werden falsch dargestellt. Vier Ursachen
wirken zusammen; dieses AP behebt die drei, die im CDB-Designer liegen. Die
vierte (Accordion verliert Textknoten) behebt AP-1.2 im anderen Plugin.

**Ursache 1 – KaTeX rendert ins Unsichtbare und nie wieder.**
`CDB/assets/js/latex-renderer.js` rendert genau einmal bei `DOMContentLoaded`
(`:194-199`) über den festen Selektor `document.querySelectorAll('.cbd-latex-formula')`
(`:32-38`). Der Accordion-Block baut seine Klappzeilen aber **schon vorher**
(sein `view.js` läuft sofort, nicht bei `DOMContentLoaded`) und setzt die
Panels auf `hidden`. KaTeX rendert also in einen `display:none`-Teilbaum.
Folge: Der Browser lädt die KaTeX-Webfonts für diesen Teilbaum nicht, und
wenn das Accordion später die Inhaltshöhe misst, misst es die Ersatzschrift –
die Aufklapp-Animation läuft auf eine falsche Zielhöhe.
Der vorhandene MutationObserver (`:84-113`) hilft nicht: Er startet erst
innerhalb von `initLaTeXRendering()` (`:26`), also ebenfalls zu spät, und
reagiert nur auf **hinzugefügte** Formelknoten, nicht auf Sichtbarkeitswechsel.

**Ursache 2 – Display-Formeln zerreißen Absätze.**
`CDB/includes/class-latex-parser.php:355-362` (`render_display_formula()`)
erzeugt ein `<div>`. Steht die Formel in einem Absatz, spaltet der
HTML-Parser des Browsers den `<p>` auf. Dabei entstehen nackte Textknoten,
die der Accordion-Code nicht in eine Klappzeile verschieben kann – sie
bleiben sichtbar daneben stehen.

**Ursache 3 – KaTeX wird sporadisch gar nicht geladen.**
`should_load_katex()` (`:66-89`) liest den Beitrag über `get_post()` (`:78`).
Dasselbe Plugin warnt an `includes/class-cbd-block-registration.php:417-430`
ausdrücklich davor: Das globale `$post` ist zur `wp_enqueue_scripts`-Zeit je
nach Theme noch nicht gesetzt; `get_queried_object_id()` ist zuverlässiger.
Zusätzlich beschränkt `:77` das Laden auf `is_singular()`.

**Ursache 4 – Delimiter.** Erkannt werden `$$…$$` (`:262`), `[latex]…[/latex]`
(`:278`) und `$…$` (`:296`). `\(…\)` und `\[…\]` bleiben Rohtext und lösen
auch das Lade-Gate nicht aus.

**Die Schnittstelle zu AP-1.2 – verbindlich, exakt so umsetzen:**

```js
window.cbdRenderLatex(root)
```

| Eigenschaft | Festlegung |
|---|---|
| Parameter `root` | `Element` oder `Document`. Optional, Vorgabe `document`. |
| Wirkung | Rendert alle `.cbd-latex-formula` innerhalb von `root`, die **kein** Attribut `data-cbd-latex-rendered="1"` tragen. Nach erfolgreichem Rendern wird dieses Attribut gesetzt. |
| Rückgabe | `Promise<number>` – Anzahl neu gerenderter Formeln. Das Promise löst erst auf, **nachdem** `document.fonts.ready` erfüllt ist, damit der Aufrufer danach zuverlässig messen kann. |
| KaTeX fehlt | Gibt `Promise.resolve(0)` zurück und wirft **nicht**. |
| Verfügbarkeit | Existiert, sobald `latex-renderer.js` geladen ist. Aufrufer aus anderen Plugins müssen `typeof window.cbdRenderLatex === 'function'` prüfen, weil das CDB-Plugin abgeschaltet sein kann. |

**Betroffene Dateien:**
- `CDB/assets/js/latex-renderer.js` (ändern)
- `CDB/includes/class-latex-parser.php` (ändern)
- `CDB/assets/css/latex-formulas.css` (ändern)

**Vorgehen:**

1. **`latex-renderer.js` – öffentliche Funktion.** `window.cbdRenderLatex`
   genau nach der Tabelle oben implementieren. Die vorhandene
   `renderFormula()` (`:43-79`) wiederverwenden; sie ruft
   `katex.render(latex, contentSpan, {displayMode: isDisplay, output:'html', …})`.
   Nach dem Rendern `data-cbd-latex-rendered="1"` auf dem
   `.cbd-latex-formula`-Element setzen.
2. **`latex-renderer.js` – Mehrfachrendern verhindern.** `renderAllFormulas()`
   (`:32-38`) so umbauen, dass sie `window.cbdRenderLatex(document)` aufruft.
   Das Attribut aus Schritt 1 sorgt dafür, dass eine Formel nie zweimal
   gerendert wird.
3. **`latex-renderer.js` – MutationObserver früher starten.** Den Observer
   (`:84-113`) aus `initLaTeXRendering()` (`:26`) herauslösen und **sofort
   beim Laden der Datei** starten, nicht erst bei `DOMContentLoaded`. Er soll
   weiterhin nur auf hinzugefügte Knoten reagieren.
4. **`latex-renderer.js` – `resize`-Listener.** Einen entprellten
   (Debounce 150 ms) `window.addEventListener('resize', …)` ergänzen, der
   `window.cbdRenderLatex(document)` aufruft. Das Accordion feuert in
   `finishOpen()` bereits ein `resize`-Ereignis; damit greift der Fall auch
   dann, wenn ein Aufrufer die Funktion nicht direkt nutzt.
5. **`class-latex-parser.php` – `<div>` → `<span>`.** In
   `render_display_formula()` (`:355-362`) das äußere `<div>` durch ein
   `<span>` ersetzen. Die Klassen `cbd-latex-formula cbd-latex-display` und
   das Attribut `data-latex` bleiben unverändert. **Das innere
   `<span class="cbd-latex-content"></span>` bleibt wie es ist** – der
   Renderer schreibt dort hinein.
6. **`latex-formulas.css` – Darstellung erhalten.** Damit sich das Aussehen
   nicht ändert, muss die Regel für `.cbd-latex-display` weiterhin
   `display: block` setzen (heute steht dort `display:block !important`,
   `:19-27`). Prüfe, ob die Regel auch für ein `<span>` greift (sie ist
   klassenbasiert, greift also) – kein `div`-Elementselektor darf übrig
   bleiben. Suche dazu in der Datei nach `div.cbd-latex` und nach
   `div .cbd-latex`.
7. **`class-latex-parser.php` – `should_load_katex()` härten** (`:66-89`):
   - Die Beitrags-ID über `get_queried_object_id()` ermitteln statt über
     `get_post()`. Ist sie 0, auf `get_post()` zurückfallen.
   - Die Beschränkung auf `is_singular()` (`:77`) aufheben: Auch auf
     Archiven, der Startseite und in Template-Teilen sollen Formeln
     funktionieren. Ist keine Beitrags-ID ermittelbar, KaTeX laden, wenn
     `is_singular()` **oder** `is_home()` **oder** `is_archive()` zutrifft.
   - Die Inhaltsprüfung (`:80-86`) um die Marker `\(` und `\[` erweitern.
8. **`class-latex-parser.php` – neue Delimiter.** In `parse_latex()`
   zusätzlich zu den bestehenden Mustern erkennen:
   - `\[…\]` → Display-Formel, behandelt wie `$$…$$` (also **vor** den
     Inline-Mustern, mit Platzhalter-Mechanik wie bei `:261-273`)
   - `\(…\)` → Inline-Formel, behandelt wie `$…$`
   Die Reihenfolge ist wichtig: erst Display, dann Inline. Der bestehende
   Doppelparse-Schutz (`:220`, prüft auf `cbd-latex-formula`) bleibt.
9. **`class-latex-parser.php` – `the_content`-Filter.** Der Filter ist heute
   auf Priorität 5 registriert (`:57`), also **vor** `do_blocks()`
   (Kern-Priorität 9). Er arbeitet damit auf rohem Blockmarkup einschließlich
   `<!-- wp:… -->`-Kommentaren. Der Filter `render_block` (`:53`, ebenfalls
   Priorität 5) leistet dieselbe Arbeit bereits je Block.
   **Vorgehen:** Den `the_content`-Filter von Priorität 5 auf **Priorität 11**
   verschieben (also nach `do_blocks()`). Der Doppelparse-Schutz (`:220`)
   verhindert, dass bereits gerenderte Formeln erneut angefasst werden. So
   funktionieren weiterhin auch Formeln in Inhalten ohne Blöcke (klassischer
   Editor), ohne dass rohes Blockmarkup verändert wird.
10. `php tools/check-php74.php` im Verzeichnis `Plugins/CDB-Designer`
    ausführen und grün bekommen.

**Akzeptanzkriterien:**
- [ ] `window.cbdRenderLatex` ist eine Funktion, akzeptiert ein optionales
      Wurzelelement und gibt ein Promise zurück, das eine Zahl liefert.
- [ ] Ein zweiter Aufruf von `window.cbdRenderLatex(document)` unmittelbar
      nach dem ersten liefert `0` (keine Doppelrendrung).
- [ ] Ohne geladenes KaTeX liefert `window.cbdRenderLatex()` `0` und wirft
      keine Ausnahme.
- [ ] `render_display_formula()` gibt ein `<span class="cbd-latex-formula cbd-latex-display" data-latex="…">`
      zurück; im Rückgabewert kommt kein `<div` vor.
- [ ] `should_load_katex()` verwendet `get_queried_object_id()`.
- [ ] Eine Formel `\[x^2\]` und eine Formel `\(y_1\)` werden in
      `.cbd-latex-formula`-Markup umgewandelt.
- [ ] Der `the_content`-Filter ist mit Priorität 11 registriert.
- [ ] `php tools/check-php74.php` meldet keinen Fehler.
- [ ] Alle neuen `console.log` liegen hinter `window.cbdDebug`.
- [ ] `Plugins/CDB-Designer/reference_file_map.md` ist für die drei
      geänderten Dateien aktualisiert.

**Tests:**
- Smoke-Test: `php -l includes/class-latex-parser.php` läuft ohne Fehler;
  anschließend `php tools/check-php74.php`.
- Prüfschritt A (Server): Ein kurzes CLI-Skript in `tools/` anlegen, das
  `class-latex-parser.php` mit den nötigen WordPress-Stubs lädt (Muster:
  vorhandene Harnische wie `tools/test-icon-scale.php` – CLI-Guard,
  `define('ABSPATH','/')`, Stubs für `add_action`, `add_filter`, `__`,
  `esc_html`, `esc_attr`, `esc_url`). Prüfe damit:
  `$$a+b$$` → Ausgabe enthält `cbd-latex-display` und **kein** `<div`;
  `\[a+b\]` → dasselbe; `$x$` → `cbd-latex-inline`; `\(x\)` → dasselbe;
  Text ohne Formeln bleibt unverändert; ein bereits gerenderter Inhalt
  (enthält `cbd-latex-formula`) wird nicht erneut angefasst.
  Das Skript als `tools/test-latex-parser.php` behalten und ins Repo
  aufnehmen.
- Prüfschritt B (Browser): Auf dem Testserver
  (http://fos.localhost:8080/) eine Seite mit einer Display-Formel in einem
  Absatz anlegen. Im Frontend prüfen: Der Absatz ist **nicht** in mehrere
  Absätze zerfallen (im Elementinspektor darf zwischen dem öffnenden und
  schließenden `<p>` kein zweites `<p>` liegen). Browser-Konsole ohne Error.
- Prüfschritt C (Konsole): In der Browser-Konsole
  `window.cbdRenderLatex().then(n => console.log('neu gerendert:', n))`
  ausführen → gibt `0` aus, weil beim Laden schon alles gerendert wurde.
- Log-Check: `C:\allinkl-testserver\www\htdocs\w0000001\fos\wp-content\debug.log`
  nach dem Seitenaufruf frei von neuen Notices/Warnings/Deprecations.
- Regressionsrelevanz: Eine Bestandsseite mit Formeln **außerhalb** von
  Accordions aufrufen – die Formeln müssen unverändert erscheinen. Das ist
  wegen der Prioritätsänderung in Schritt 9 der wichtigste Regressionstest.

**Übergabenotiz:**

---

### AP-1.2: Accordion verliert keine Textknoten mehr und misst nach dem Rendern

**Status:** ☐ offen
**Umfang:** M
**Modell:** opus
**Abhängigkeiten:** AP-1.0 (Accordion-Stand liegt auf `main`)

**Ziel & Kontext:**
Im Plugin „Eigene WP Blocks" (`Plugins/Eigene WP Blocks/`) baut der Block
`modular-blocks/accordion` seine Klappzeilen **zur Laufzeit im Browser** aus
Überschriften. Der Server gibt eine flache Folge von `<h3>`, `<p>` usw. aus
(`blocks/accordion/render.php:102-106` gibt `$block_content` direkt aus);
erst `blocks/accordion/view.js` gruppiert das in Zeilen.

Es gibt **keinen** Block `modular-blocks/accordion-row` mehr. Die Datei
`Plugins/Eigene WP Blocks/reference_file_map.md` beschreibt in den Zeilen
60–75 noch die alte Zwei-Block-Architektur – das ist überholt und wird in
diesem AP mitkorrigiert.

**Fehler 1 – Textknoten gehen verloren.** `buildRows()` iteriert über
`content.children` (`view.js:330`) und verschiebt in `:404-406` nur Elemente
in die Panels. **Textknoten werden nie verschoben.** Sie bleiben in
`.mb-accordion__content` außerhalb aller Klappzeilen sichtbar stehen. Das
passiert immer dann, wenn im Inhalt ein Block-Element in einem Absatz steckt
und der Browser den Absatz aufspaltet – bei Display-Formeln ist das der
Regelfall.

**Fehler 2 – Höhe wird vor dem Formelrendern gemessen.** `openRow()`
(`:528-572`) misst die Inhaltshöhe über `measureContentHeight()` (`:240-246`,
aufgerufen `:565`) und animiert 250 ms darauf zu. Zu diesem Zeitpunkt hat
KaTeX in dem bis eben versteckten Panel noch nicht mit den echten Webfonts
gerendert – gemessen wird die Ersatzschrift. Sichtbar als Springen oder
Abschneiden beim Öffnen. `finishOpen()` (`:554-560`) feuert bereits
`window.dispatchEvent(new Event('resize'))` als generisches
Neu-Vermessen-Signal.

**Die Schnittstelle, gegen die du programmierst** (wird von AP-1.1 im
CDB-Designer geliefert; du brauchst AP-1.1 **nicht** abzuwarten):

```js
window.cbdRenderLatex(root)   // root: Element|Document, optional (Vorgabe: document)
                              // → Promise<number>, löst nach document.fonts.ready auf
                              // → 0, wenn KaTeX fehlt; wirft nie
```

Das CDB-Plugin kann abgeschaltet sein. **Immer**
`typeof window.cbdRenderLatex === 'function'` prüfen und ohne die Funktion
das bisherige Verhalten beibehalten.

**Betroffene Dateien:**
- `EWB/blocks/accordion/view.js` (ändern)
- `EWB/blocks/accordion/style.css` (ändern)
- `EWB/reference_file_map.md` (ändern – Zeilen 60–75 auf die tatsächliche Architektur bringen)

**Vorgehen:**

1. **`view.js` – `buildRows()` auf `childNodes` umstellen.** In `:330` und in
   der Verschiebeschleife `:404-406` statt `content.children` die
   vollständige Knotenliste `content.childNodes` verwenden.
   - Als Zeilentrenner gelten weiterhin **nur** Überschriftenelemente der
     eingestellten Ebene. Ein Textknoten ist nie ein Zeilenkopf.
   - Textknoten, die **nur** aus Leerraum bestehen (`node.textContent.trim() === ''`),
     werden verworfen statt verschoben – sonst wandert Einrückungs-Leerraum
     in die Panels.
   - Alle übrigen Knoten (Elemente **und** nicht-leere Textknoten) wandern in
     das Panel der zuletzt eröffneten Zeile.
   - Knoten **vor** der ersten Überschrift bleiben wie bisher außerhalb der
     Zeilen stehen (Vorspann). Das bestehende Verhalten dafür nicht ändern.
   - Da sich die Knotenliste beim Verschieben ändert, vorher eine feste Kopie
     anlegen (`Array.prototype.slice.call(content.childNodes)`), sonst
     überspringt die Schleife Knoten.
2. **`view.js` – nach dem Aufklappen neu rendern und neu messen.** In
   `finishOpen()` (`:554-560`), **vor** dem bestehenden
   `window.dispatchEvent(new Event('resize'))`:
   - Wenn `typeof window.cbdRenderLatex === 'function'`: die Funktion mit dem
     gerade geöffneten Panel als Wurzel aufrufen. Im `.then()` des
     zurückgegebenen Promise die Höhe über `measureContentHeight()` neu
     ermitteln und die Panelhöhe setzen, **falls die Zeile dann noch offen
     ist** (der Nutzer könnte inzwischen wieder zugeklappt haben – das über
     das vorhandene Zustandsmerkmal der Zeile prüfen).
   - Fehler aus dem Promise abfangen (`.catch()`), damit ein Problem im
     LaTeX-Renderer das Accordion nicht blockiert.
   - Ohne die Funktion: unverändertes bisheriges Verhalten.
3. **`view.js` – Schriften abwarten.** Zusätzlich nach `document.fonts.ready`
   (falls vorhanden) die Höhe der aktuell offenen Zeilen einmalig neu
   ermitteln. Das deckt den Fall ab, dass eine Zeile beim Laden bereits offen
   ist (Option „erste Zeile offen").
4. **`style.css` – breite Formeln nicht abschneiden.** `.mb-accordion` hat
   `overflow: hidden` (`:16`), `.mb-accordion-row__panel` ebenfalls (`:114`).
   Ergänze eine Regel, die den **Inhaltsbereich** des Panels waagerecht
   scrollbar macht, statt zu beschneiden – z. B. `overflow-x: auto` auf dem
   inneren Inhaltselement des Panels. Das `overflow: hidden` am Panel selbst
   bleibt, weil die Aufklapp-Animation es braucht.
5. **`style.css` – Formeln im Zeilenkopf.** Der Zeilenkopf ist ein
   `<button>` mit `display:flex` (`:63-78`). Eine Display-Formel darin würde
   zum Flex-Element und den Kopf sprengen. Ergänze eine Regel, die
   `.cbd-latex-display` innerhalb von `.mb-accordion-row__title` auf
   `display: inline-block` zurücknimmt und `margin: 0` setzt.
6. **`reference_file_map.md` korrigieren.** Die Zeilen 60–75 beschreiben den
   nicht mehr existierenden Block `modular-blocks/accordion-row`. Entferne
   die `accordion-row`-Zeile aus der Blocktabelle und schreibe den Abschnitt
   „Accordion-Block im Detail" auf die tatsächliche Architektur um: **ein**
   Block, Zeilen entstehen zur Laufzeit in `view.js` aus Überschriften.
   Vermerke dort auch die neue Abhängigkeit zu `window.cbdRenderLatex`
   aus dem CDB-Plugin (optional, mit `typeof`-Prüfung).
7. `npm run build` ausführen. **Ohne Build wirkt die Änderung nicht** –
   `includes/class-block-manager.php` bevorzugt das Verzeichnis `build/`.
8. `npm run block-zips` ausführen, damit `plugin-zips/accordion.zip` auf dem
   neuen Stand ist.

**Akzeptanzkriterien:**
- [ ] `buildRows()` verwendet `childNodes`, nicht `children`.
- [ ] Ein nicht-leerer Textknoten zwischen zwei Überschriften landet im Panel
      der vorangehenden Zeile.
- [ ] Reine Leerraum-Textknoten landen in keinem Panel.
- [ ] `finishOpen()` ruft `window.cbdRenderLatex` nur auf, wenn sie eine
      Funktion ist, und misst die Höhe im `.then()` neu.
- [ ] Ist das CDB-Plugin abgeschaltet, funktioniert das Accordion
      unverändert (kein JavaScript-Fehler in der Konsole).
- [ ] `npm run build` läuft fehlerfrei; `build/blocks/accordion/view.js`
      trägt ein aktuelles Änderungsdatum.
- [ ] `plugin-zips/accordion.zip` ist neu erzeugt.
- [ ] `EWB/reference_file_map.md` beschreibt keine `accordion-row` mehr.

**Tests:**
- Smoke-Test: `npm run build` ohne Fehler; im Browser eine Seite mit
  Accordion laden, Zeilen auf- und zuklappen – Konsole ohne Error.
- Prüfschritt A (Textknoten): Auf dem Testserver
  (http://fos.localhost:8080/) eine Seite mit einem Accordion anlegen. In
  einer Klappzeile einen Absatz einfügen, der Text **und** eine
  Display-Formel enthält, etwa: `Der Zusammenhang $$E = mc^2$$ gilt allgemein.`
  Erwartung im Frontend: Zwischen den Klappzeilen steht **kein** loser Text.
  Der ganze Absatz inklusive „gilt allgemein" liegt im Panel und ist nur
  sichtbar, wenn die Zeile offen ist.
- Prüfschritt B (Höhe): Dieselbe Zeile zuklappen und wieder öffnen. Erwartung:
  Die Formel wird nicht abgeschnitten, das Panel springt nach dem Öffnen
  nicht nach.
- Prüfschritt C (ohne CDB): Im Testserver-Backend das Plugin
  „Container Block Designer" vorübergehend deaktivieren, dieselbe Seite
  laden, Zeilen auf- und zuklappen. Erwartung: Accordion funktioniert,
  Konsole ohne Error (die Formel bleibt dann verständlicherweise Rohtext).
  Plugin danach wieder aktivieren.
- Prüfschritt D (Zeilenkopf): Eine Klappzeile anlegen, deren Überschrift eine
  Formel enthält. Erwartung: Der Zeilenkopf behält seine Höhe, die Formel
  steht in der Kopfzeile, das Aufklapp-Symbol bleibt rechts.
- Log-Check: `…\fos\wp-content\debug.log` ohne neue Einträge.
- Regressionsrelevanz: Ein Accordion **ohne** Formeln muss sich unverändert
  verhalten – gleiche Anzahl Klappzeilen wie vorher, gleiche Titel.

**Übergabenotiz:**

---

### AP-1.3: Block „Block-Referenz" editorfähig machen und auf `stableId` umstellen

**Status:** ☐ offen
**Umfang:** M
**Modell:** opus
**Abhängigkeiten:** keine

**Ziel & Kontext:**
Der Block `cbd/block-reference` (Registrierung in
`CDB/includes/class-cbd-block-reference.php`, Dateien in
`CDB/blocks/block-reference/`) ist die Grundlage für das Modal aus AP-2.5.
Vorher müssen zwei Fehler weg, die ihn heute praktisch unbenutzbar machen.

**Fehler 1 – Der Editor-Code läuft nicht.** `blocks/block-reference/block.json`
verweist mit `editorScript: file:./index.js` direkt auf eine Quelldatei in
JSX/ESM-Syntax (`import { registerBlockType } from '@wordpress/blocks';` plus
JSX). **Das CDB-Plugin hat keinen Build-Schritt** – laut
`CDB/reference_file_map.md:85-86` gilt dort: „Kein Build-Schritt — IIFE,
Zugriff über `wp.*`-Globale". Der Browser bekommt also unkompiliertes JSX und
wirft einen `SyntaxError`; `registerBlockType` läuft nie, der Block erscheint
im Editor nicht oder als nicht unterstützt.

**Fehler 2 – Das Ziel wird über den falschen Bezeichner gesucht.** Das
Attribut `targetBlockId` wird aus `attrs['id'] ?? attrs['blockId']` gefüllt
(`includes/class-cbd-blocks-rest-api.php`, Methode
`extract_cbd_block_data()`); schlägt das fehl, greift ein Regex
`/id="([^"]+)"/` auf das gespeicherte Markup, der das *erste beliebige*
`id`-Attribut trifft – womöglich das eines Innenblocks. Findet er nichts,
wird der Block per `return null` **komplett aus der Auswahlliste geworfen**.

Der tatsächliche, immer vorhandene Bezeichner eines Container-Blocks ist die
**`stableId`**:

| Ebene | Träger |
|---|---|
| Erzeugung | `CDB/assets/js/block-editor.js`, `generateId()` (ca. Zeile 83) |
| Blockattribut | `stableId` (registriert in `includes/class-cbd-block-registration.php:175` und `:278`) |
| Gespeichertes Markup | `data-stable-id="…"` (`assets/js/block-editor.js:343-345`) |
| Frontend-DOM | `data-stable-id` am `.cbd-container`-Wrapper (`class-cbd-block-registration.php:1025`) |
| Rückfall Altbestand | Regex `/data-stable-id="([^"]+)"/` auf dem Blockinhalt (`class-cbd-block-registration.php:899-909`) |

Ein HTML-Anker (`anchor`) existiert daneben, ist aber optional und wird vom
Redakteur gesetzt. Der bisherige Sprunglink `#<targetBlockId>` funktioniert
deshalb nur bei manuell gesetztem Anker.

**Nicht Teil dieses APs:** Das Modal. Dieses AP stellt nur her, dass der Block
im Editor auswählbar ist und im Frontend als Sprunglink korrekt zum Ziel
führt. Das Modal baut AP-2.5 darauf auf.

**Betroffene Dateien:**
- `CDB/blocks/block-reference/index.js` (ändern – vollständig neu schreiben)
- `CDB/blocks/block-reference/block.json` (ändern)
- `CDB/blocks/block-reference/render.php` (ändern)
- `CDB/blocks/block-reference/view.js` (ändern)
- `CDB/includes/class-cbd-block-reference.php` (ändern)
- `CDB/includes/class-cbd-blocks-rest-api.php` (ändern)

**Vorgehen:**

1. **`class-cbd-blocks-rest-api.php` – `stableId` ausliefern.** In
   `extract_cbd_block_data()` den Bezeichner in dieser Reihenfolge ermitteln:
   1. `$block['attrs']['stableId']`
   2. Rückfall: `preg_match('/data-stable-id="([^"]+)"/', …)` auf dem
      zusammengesetzten `innerHTML`/`innerContent` des Blocks – **genau so,
      wie es `CBD_Classroom_Gate::block_erlaubt()` in
      `includes/class-cbd-classroom-gate.php` bereits tut.** Schau dort nach
      und übernimm die Logik wortgleich, statt eine dritte Variante zu bauen.

   Das Ergebnis unter dem **neuen** Schlüssel `stableId` in die Antwort
   aufnehmen. Der bisherige Schlüssel `blockId` bleibt zusätzlich erhalten
   (Rückwärtskompatibilität). Blöcke **ohne** ermittelbare `stableId` werden
   weiterhin ausgelassen, aber die Fundquote steigt dadurch erheblich.
   Ergänze außerdem den Anker (`$block['attrs']['anchor']`) als Schlüssel
   `anchor` (leer, wenn nicht gesetzt) – `render.php` braucht ihn für den
   Sprunglink.
2. **`index.js` vollständig neu schreiben – ohne JSX, ohne `import`.** Muster
   ist `CDB/assets/js/block-editor.js`: eine IIFE, Zugriff über `wp.*`-Globale,
   Elemente über `wp.element.createElement` (üblicherweise als `el` abgekürzt).
   Inhalt:
   - `wp.blocks.registerBlockType('cbd/block-reference', { edit: …, save: function () { return null; } })`
   - `edit`: `wp.blockEditor.InspectorControls` mit
     - einem **Suchfeld** (`wp.components.TextControl`), das die Liste der
       Blöcke nach Seitentitel und Blocktitel filtert – die heutige
       ungefilterte `SelectControl`-Liste über alle Blöcke aller Seiten ist
       bei vielen Seiten unbrauchbar,
     - einer `SelectControl` mit den gefilterten Treffern,
     - `TextControl` für `linkText`, `ToggleControl` für `showIcon`.
   - Daten über `wp.apiFetch({ path: '/cbd/v1/blocks' })` in einem
     `wp.element.useEffect`, Zustand über `wp.element.useState`.
   - Beim Auswählen die Attribute `targetStableId`, `targetPostId`,
     `targetBlockTitle`, `targetPostTitle`, `targetAnchor` setzen.
   - Im Canvas eine Vorschaukarte über `wp.blockEditor.useBlockProps`.
3. **Editor-Script mit Abhängigkeiten registrieren.** Weil es keine
   `index.asset.php` gibt, registriert WordPress das Script sonst ohne
   Abhängigkeiten und `wp.blocks` ist beim Ausführen womöglich noch nicht da.
   Deshalb in `class-cbd-block-reference.php` das Editor-Script **von Hand**
   registrieren – schau dir an, wie `assets/js/block-editor.js` in
   `includes/class-cbd-block-registration.php` eingebunden wird, und übernimm
   dasselbe Muster. Benötigte Abhängigkeiten:
   `array('wp-blocks','wp-element','wp-block-editor','wp-components','wp-i18n','wp-api-fetch')`.
   Version über `filemtime()` für Cache-Busting.
   In `block.json` die Zeile `editorScript` entsprechend auf das registrierte
   Handle umstellen (`"editorScript": "cbd-block-reference-editor"`).
4. **`block.json` – Attribute.** Ergänzen:
   - `targetStableId` (string, Vorgabe `""`)
   - `targetAnchor` (string, Vorgabe `""`)
   `targetBlockId` bleibt für Bestandsinhalte erhalten und wird nicht
   entfernt. Kein Attribut löschen – sonst gelten gespeicherte Blöcke als
   ungültig.
5. **`render.php` – Sprungziel richtig bilden.** Die Sprungmarke in dieser
   Reihenfolge wählen:
   1. `targetAnchor`, falls gesetzt (echter HTML-Anker, liegt am inneren
      `.cbd-container-block`)
   2. sonst kein Fragment – stattdessen `data-target-stable-id` am Link
      ausgeben, damit `view.js` das Ziel über
      `[data-stable-id="…"]` findet
   Weiterhin ausgeben: `data-same-page` (Vergleich `targetPostId` mit
   `get_the_ID()`), `data-target-post`. Der Abbruch bei fehlendem Ziel bleibt.
   Alle Ausgaben über `esc_attr()`/`esc_html()`/`esc_url()`.
6. **`view.js` – Ziel über `stableId` finden.** Die bestehende Sprunglogik
   (`preventDefault`, `scrollIntoView({behavior:'smooth'})`,
   `highlightBlock()` mit der Klasse `.cbd-block-reference-highlight`,
   `handleHashNavigation()`) beibehalten, aber die Zielsuche erweitern:
   erst `document.getElementById(anchor)`, sonst
   `document.querySelector('[data-stable-id="' + CSS.escape(stableId) + '"]')`.
   Gibt es `CSS.escape` nicht, auf eine einfache Anführungszeichen-Maskierung
   zurückfallen.
7. **`style.css` – zu breite Regel eingrenzen.** Die Datei enthält
   `[id] { scroll-margin-top: 80px; }` – das gilt für **jedes** Element mit
   `id` auf der ganzen Seite. Auf `.cbd-container, .cbd-container-block`
   eingrenzen.
8. `php tools/check-php74.php` ausführen und grün bekommen.

**Akzeptanzkriterien:**
- [ ] `blocks/block-reference/index.js` enthält kein `import`, kein `export`
      und keine JSX-Syntax (Prüfung: die Datei enthält weder `import ` noch
      ein `<` unmittelbar gefolgt von einem Großbuchstaben).
- [ ] Der Block erscheint im Block-Editor unter „Alle durchsuchen" und lässt
      sich einfügen, ohne dass die Browser-Konsole einen Fehler zeigt.
- [ ] Die Antwort von `/wp-json/cbd/v1/blocks` enthält für jeden Eintrag die
      Schlüssel `stableId` und `anchor`.
- [ ] Ein Container-Block **ohne** manuellen HTML-Anker erscheint in der
      Auswahlliste (heute nicht der Fall).
- [ ] Das Suchfeld filtert die Auswahlliste nach Seiten- und Blocktitel.
- [ ] Ein Verweis auf einen Block **derselben** Seite scrollt weich zum Ziel
      und hebt es kurz hervor.
- [ ] Ein Verweis auf einen Block einer **anderen** Seite führt zu dieser
      Seite und landet beim Ziel.
- [ ] Die Regel `[id] { scroll-margin-top: … }` ist auf Container-Klassen
      eingegrenzt.
- [ ] `php tools/check-php74.php` meldet keinen Fehler.
- [ ] `CDB/reference_file_map.md` ist für die geänderten Dateien aktualisiert;
      insbesondere trägt die Zeile zu `class-cbd-block-reference.php` jetzt
      die tatsächlichen Aufgaben (Registrierung **und** Editor-Script).

**Tests:**
- Smoke-Test: Plugin auf dem Testserver aktualisieren (Dateien kopieren),
  Backend aufrufen – keine weiße Seite, kein Fatal Error.
- Prüfschritt A (Editor): Auf http://fos.localhost:8080/wp-admin/ eine Seite
  bearbeiten, Block „Block-Referenz" einfügen. Erwartung: Der Block erscheint
  im Einfügen-Menü, das Seitenleisten-Panel zeigt Suchfeld und Auswahlliste,
  Konsole ohne Error.
- Prüfschritt B (Auswahlliste): Vorher im Testserver einen Container-Block
  ohne HTML-Anker auf einer Seite anlegen und speichern. Erwartung: Er
  erscheint in der Auswahlliste des Referenz-Blocks.
- Prüfschritt C (REST): Als angemeldeter Administrator
  `http://fos.localhost:8080/wp-json/cbd/v1/blocks` im Browser aufrufen.
  Erwartung: JSON-Liste, jeder Eintrag hat `stableId`.
- Prüfschritt D (Frontend, gleiche Seite): Referenz und Zielblock auf
  derselben Seite. Klick → weiches Scrollen, kurze Hervorhebung, Konsole ohne
  Error.
- Prüfschritt E (Frontend, andere Seite): Referenz auf Seite A, Ziel auf
  Seite B. Klick → Seite B wird geladen und beim Zielblock positioniert.
- Log-Check: `…\fos\wp-content\debug.log` ohne neue Einträge.
- Regressionsrelevanz: Eine Seite mit einem **bestehenden**, vor der Änderung
  gespeicherten Block-Referenz-Block öffnen. Erwartung: kein „Dieser Block
  enthält unerwarteten Inhalt" im Editor.

**Übergabenotiz:**

---

### AP-1.4: Screenshot liefert wieder eine Datei (Generator-Fehler beheben)

**Status:** ☐ offen
**Umfang:** S
**Modell:** sonnet
**Abhängigkeiten:** keine

**Ziel & Kontext:**
Der Screenshot-Knopf eines Container-Blocks meldet Erfolg, ohne eine Datei zu
liefern, sobald der erste Weg (Zwischenablage) scheitert. Ursache ist ein
Fehler in `CDB/assets/js/interactivity-store.js`.

Der Ablauf dort (`actions.createScreenshot`, ca. `:203-378`) ist dreistufig:
Stufe 1 Zwischenablage (`ClipboardItem` + `navigator.clipboard.write`,
ca. `:279-291`), Stufe 2 Web Share, Stufe 3 `<a download>` – Stufe 2 und 3
stecken beide in der Hilfsfunktion `tryWebShare` (ca. `:306-340`).

**Der Fehler:** Zeile ~302 lautet `yield tryWebShare(blob, canvas, context);`,
aber `tryWebShare` ist als **`function*`** deklariert (`:306`). Der Aufruf
liefert damit ein Generator-Objekt, kein Promise. Die Laufzeit der
Interactivity-API löst geyieldete Werte per `await` auf – ein Generator-Objekt
ist kein Thenable und wird unverändert zurückgegeben. **Der Rumpf von
`tryWebShare` läuft nie.** Korrekt wäre `yield*` (Delegation an den
Untergenerator). Danach springt der Code direkt weiter zum Erfolgs-Feedback
(grüner Haken, ca. `:347-361`) – der Nutzer sieht Erfolg ohne Datei.

Derselbe Fehler steht in `assets/js/interactivity-store.js.backup` – das ist
eine **tote Datei**, die nicht angefasst wird.

**Zwei Folgefehler im selben Bereich:**
- Der Haupt-`catch` (ca. `:363-377`) setzt das Knopf-Icon über
  `element.ref.querySelector('.cbd-screenshot .dashicons')` zurück. `element.ref`
  **ist** bereits der `.cbd-screenshot`-Knopf (vgl. `:218`, wo korrekt
  `element.ref.querySelector('.dashicons')` steht). Die Abfrage liefert `null`,
  das Icon bleibt als Spinner hängen und dreht sich endlos.
- Der `catch` beim Zwischenablage-Versuch (ca. `:289-291`) ist leer und der
  Haupt-`catch` meldet dem Nutzer nichts.

**Welcher Pfad läuft überhaupt?** `includes/class-cbd-block-registration.php:465-494`
lädt auf WordPress 6.5+ **nur** `interactivity-store.js` als ESM-Modul;
`interactivity-fallback.js` wird dann nicht geladen. Der Store-Pfad ist auf
dem Testserver (WordPress 7.0.3) also der aktive. Die Fallback-Datei enthält
dieselbe Dreistufigkeit mit normalen Callbacks (ca. `:214-427`) und muss
**inhaltlich gleichgehalten** werden, auch wenn sie faktisch nicht lädt.

**Betroffene Dateien:**
- `CDB/assets/js/interactivity-store.js` (ändern)
- `CDB/assets/js/interactivity-fallback.js` (ändern)

**Vorgehen:**

1. **`interactivity-store.js` – Delegation korrigieren.** Den Aufruf
   `yield tryWebShare(...)` auf `yield* tryWebShare(...)` ändern.
2. **`interactivity-store.js` – Icon-Selektor korrigieren.** Im Haupt-`catch`
   `element.ref.querySelector('.cbd-screenshot .dashicons')` durch
   `element.ref.querySelector('.dashicons')` ersetzen – identisch zu der
   bereits korrekten Stelle bei `:218`.
3. **`interactivity-store.js` – Fehler sichtbar machen.** Schlägt der gesamte
   Vorgang fehl, muss der Nutzer das erkennen: Das Knopf-Icon auf ein
   Warn-Dashicon setzen und nach 3 Sekunden auf das Ausgangs-Icon
   zurückstellen (dasselbe Muster wie beim Erfolgs-Haken bei `:347-361`).
   Zusätzlich `console.error` mit der Ursache – `console.error` darf ungegatet
   bleiben.
4. **`interactivity-store.js` – Zwischenablage-Fehler nicht schlucken.** Den
   leeren `catch` bei `:289-291` so ergänzen, dass die Ursache in einer
   Variablen festgehalten wird, damit Schritt 3 sie ausgeben kann. Der
   Ablauf muss weiterhin auf Stufe 2 weitergehen.
5. **Canvas-Fläche deckeln.** Der Aufruf von `html2canvas` verwendet fest
   `scale: 2` (ca. `:263`). Bei einem hohen Block sprengt das die
   Flächengrenze mancher Browser, `toBlob` liefert dann `null`. Übernimm das
   Muster, das im PDF-Pfad bereits existiert: Sieh dir in
   `CDB/assets/js/pdf-server-side.js` die Zeilen um `:26-27` (Geräteerkennung),
   `:787` (`var maxPixels = isIOS ? 16000000 : 64000000;`) und `:838-842`
   (Herunterskalieren) an. Berechne vor dem Aufruf aus der Elementgröße den
   höchstmöglichen `scale`, sodass `breite * hoehe * scale²` unter der Grenze
   bleibt, und begrenze `scale` auf höchstens 2.
6. **`backgroundColor`.** Der Screenshot nutzt `backgroundColor: null`
   (ca. `:265`), also ein durchsichtiges PNG. Der PDF-Pfad nutzt bewusst
   `'#ffffff'` (`pdf-server-side.js:849`). Auf `'#ffffff'` umstellen.
7. **`interactivity-fallback.js` gleichziehen.** Dieselben Änderungen aus den
   Schritten 2 bis 6 sinngemäß im Callback-Stil übertragen (ca. `:214-427`).
   Der Generator-Fehler aus Schritt 1 existiert dort nicht.
8. Die Dateien enthalten keinen PHP-Code – `check-php74.php` entfällt.

**Akzeptanzkriterien:**
- [ ] In `interactivity-store.js` kommt `yield tryWebShare` nicht mehr vor;
      stattdessen `yield* tryWebShare`.
- [ ] Der Selektor `'.cbd-screenshot .dashicons'` kommt in
      `interactivity-store.js` nicht mehr vor.
- [ ] Schlägt der Screenshot fehl, zeigt der Knopf ein Warn-Icon und stellt
      sich nach 3 Sekunden zurück – der Spinner bleibt nicht hängen.
- [ ] Der `scale`-Wert wird aus der Elementgröße berechnet und ist nie
      größer als 2.
- [ ] `backgroundColor` ist `'#ffffff'`.
- [ ] `assets/js/interactivity-store.js.backup` wurde **nicht** verändert.
- [ ] Alle neuen `console.log` liegen hinter `window.cbdDebug`;
      `console.error` darf ungegatet sein.
- [ ] `CDB/reference_file_map.md` ist für beide Dateien aktualisiert.

**Tests:**
- Smoke-Test: Auf dem Testserver eine Seite mit einem Container-Block
  aufrufen, dessen Screenshot-Feature aktiv ist. Knopf klicken. Erwartung:
  Bild landet in der Zwischenablage, grüner Haken, Konsole ohne Error.
- Prüfschritt A (Stufe 2/3 erzwingen): In der Browser-Konsole vor dem Klick
  `navigator.clipboard.write = function () { return Promise.reject(new Error('Test')); };`
  ausführen, dann den Screenshot-Knopf klicken. **Erwartung nach dem Fix:**
  Es passiert etwas Sichtbares – entweder öffnet sich das Teilen-Fenster oder
  ein Download startet. **Vor dem Fix** erschien hier nur der grüne Haken
  ohne Datei; dieser Unterschied ist der eigentliche Nachweis. Ergebnis
  wörtlich ins Testprotokoll.
- Prüfschritt B (Fehlerfall): In der Konsole zusätzlich
  `window.html2canvas = function () { return Promise.reject(new Error('Test')); };`
  setzen und klicken. Erwartung: Warn-Icon erscheint, verschwindet nach ~3 s,
  `console.error` nennt die Ursache, der Spinner bleibt nicht hängen.
- Prüfschritt C (langer Block): Einen Container-Block mit sehr viel Inhalt
  (Höhe > 5000 px) anlegen und den Screenshot auslösen. Erwartung: Es kommt
  ein Bild an, nicht `null`. In der Konsole mit aktivem `window.cbdDebug = true`
  prüfen, dass ein `scale` kleiner als 2 gewählt wurde.
- Regressionsrelevanz: Die übrigen Container-Funktionen (Aufklappen,
  Text kopieren, PDF) müssen unverändert funktionieren – beide Dateien
  enthalten auch deren Logik.

**Übergabenotiz:**

---

### AP-1.5: Abnahme Phase 1 auf dem Testserver

**Status:** ☐ offen
**Umfang:** M
**Modell:** sonnet
**Abhängigkeiten:** AP-1.0, AP-1.1, AP-1.2, AP-1.3, AP-1.4

**Ziel & Kontext:**
Integrationstest der Phase 1. Die vier Reparaturen wurden einzeln geprüft;
hier wird nachgewiesen, dass sie zusammen funktionieren – insbesondere das
Zusammenspiel von AP-1.1 (`window.cbdRenderLatex`) und AP-1.2 (Aufruf aus
dem Accordion), das absichtlich nicht durch eine AP-Abhängigkeit erzwungen
wurde.

Zusätzlich werden die Verteilungspakete gebaut und auf dem Testserver
installiert – so, wie es später auf der Produktivinstallation abläuft.

**Betroffene Dateien:**
- `CDB/container-block-designer.php` (ändern – Version, **nur hier**)
- `EWB/package.json` (ändern – Version, durch das Bauskript)
- Verteilungspakete in `CDB/dist/` und `EWB/plugin-zips/`

**Vorgehen:**

1. Sicherstellen, dass beide Phasen-Branches alle APs enthalten und gepusht
   sind (`phase-1-reparaturen` im CDB-Designer, `phase-1-latex-accordion` in
   „Eigene WP Blocks").
2. Im CDB-Designer `php tools/check-php74.php` ausführen – muss grün sein.
3. Im CDB-Designer die vorhandenen Prüfharnische ausführen und die Ergebnisse
   notieren: `php tools/test-icon-library.php`, `php tools/test-icon-value.php`,
   `php tools/test-icon-scale.php`, `php tools/test-icon-manager.php`,
   `php tools/test-svg-sanitizer.php`, `php tools/test-block-serializer.php`,
   `php tools/test-design-transfer.php`, `php tools/test-classroom-gate.php`
   sowie den in AP-1.1 neu angelegten `php tools/test-latex-parser.php`.
   **Alle müssen bestehen.** Ein bereits vor der Phase fehlschlagender Test
   wird als solcher vermerkt, blockiert aber nicht.
4. In „Eigene WP Blocks": `npm run build`, dann `npm run block-zips`.
5. Im CDB-Designer: `node create-plugin-zip.js`. **Nie manuell zippen** – das
   Skript stellt vor dem Packen `composer dump-autoload --no-dev --optimize`
   her und danach den Dev-Autoloader wieder. Ohne diesen Schritt bindet der
   Autoloader phpunit ein und die Zielinstallation antwortet mit HTTP 500.
6. Das erzeugte ZIP entpacken und prüfen, dass der Autoloader sauber ist:
   `php -r "define('ABSPATH','/'); require '<entpackt>/vendor/autoload.php'; echo 'ok';"`
   muss `ok` ausgeben, ohne Fatal Error.
7. Prüfen, dass `blocks/block-reference/` im CDB-ZIP enthalten ist – dieser
   Ordner fehlte bis v3.1.76 und existierte deshalb auf Produktivinstallationen
   nicht.
8. **Ausrollreihenfolge einhalten:** Auf dem Testserver
   (http://fos.localhost:8080/wp-admin/) **erst** das Block-ZIP
   `accordion.zip` hochladen (Einstellungen → Modulare Blöcke → Block
   hochladen), **dann** das CDB-Plugin-ZIP (Plugins → Installieren →
   Hochladen, vorhandenes überschreiben). Grund: Der Content-Importer erzeugt
   `modular-blocks/accordion` nur, wenn der Blocktyp registriert ist.
9. Nach der Installation die Versionsanzeige im Plugin-Menü prüfen – steht
   dort die alte Version, wurde nicht wirklich installiert.
10. Die Integrationsprüfungen aus dem Testabschnitt durchführen.
11. `debug.log` vor den Prüfungen leeren und danach auswerten.

**Akzeptanzkriterien:**
- [ ] `php tools/check-php74.php` meldet keinen Fehler.
- [ ] Alle in Schritt 3 genannten Prüfharnische bestehen (oder ein
      vorbestehender Fehlschlag ist dokumentiert).
- [ ] `node create-plugin-zip.js` läuft durch; das entpackte ZIP lädt seinen
      Autoloader ohne Fatal Error.
- [ ] Das CDB-ZIP enthält `blocks/block-reference/block.json`, `index.js`,
      `render.php`, `view.js`.
- [ ] Beide Pakete sind auf dem Testserver in der richtigen Reihenfolge
      installiert; die Versionsanzeigen im Backend stimmen.
- [ ] Alle Integrationsprüfungen aus dem Testabschnitt bestanden.
- [ ] `…\fos\wp-content\debug.log` enthält nach den Prüfungen keine neuen
      Notices, Warnings, Deprecations oder Fatal Errors.
- [ ] Der lauffähige Endzustand der Phase 1 (Abschnitt 6) ist erreicht.

**Tests:**
- **Integration 1 – Formel im Accordion (der Kernnachweis):** Eine Testseite
  mit einem Accordion anlegen. Klappzeile 1 enthält den Absatz
  `Der Zusammenhang $$E = mc^2$$ gilt allgemein.`, Klappzeile 2 eine
  Inline-Formel `Die Größe $\Delta H$ ist negativ.`, Klappzeile 3 eine Formel
  in neuer Schreibweise `\[a^2 + b^2 = c^2\]`.
  Erwartung im Frontend:
  (a) Zwischen den Klappzeilen steht **kein** loser Text;
  (b) alle drei Zeilen sind zunächst zu;
  (c) beim Öffnen erscheint die Formel gesetzt (nicht als `$$E = mc^2$$`);
  (d) das Panel springt nach dem Öffnen nicht nach und schneidet die Formel
  nicht ab;
  (e) Konsole ohne Error.
- **Integration 2 – Accordion im Container:** Dasselbe Accordion in einen
  Container-Block des CDB-Designers legen, den Container auf „einklappbar"
  stellen. Container zuklappen, aufklappen, dann eine Accordion-Zeile öffnen.
  Erwartung: wie Integration 1.
- **Integration 3 – Block-Referenz:** Auf derselben Seite einen
  Block-Referenz-Block anlegen, der auf einen Container-Block **ohne**
  HTML-Anker zeigt. Klick → weiches Scrollen zum Ziel, kurze Hervorhebung.
- **Integration 4 – Screenshot:** Screenshot eines Container-Blocks auslösen
  (Chrome oder Edge). Erwartung: Bild in der Zwischenablage, grüner Haken.
  Danach mit der Konsolenzeile
  `navigator.clipboard.write = function () { return Promise.reject(new Error('Test')); };`
  erneut auslösen → sichtbares Ergebnis (Teilen-Fenster oder Download).
- **Regression 1 – Formeln außerhalb von Accordions:** Eine bestehende Seite
  mit Formeln in gewöhnlichen Absätzen und in Container-Blöcken aufrufen.
  Erwartung: unverändertes Aussehen. Dies prüft die Prioritätsänderung des
  `the_content`-Filters aus AP-1.1.
- **Regression 2 – Accordion ohne Formeln:** Ein Accordion ohne jede Formel
  öffnen und schließen. Erwartung: gleiche Zeilenanzahl und Titel wie vor der
  Phase.
- **Regression 3 – Container-Funktionen:** An einem Container-Block
  Aufklappen, Text kopieren und PDF-Export auslösen. Erwartung: alle drei
  funktionieren.
- **Regression 4 – Klassenansicht:** Eine Seite mit
  `?classroom=<id>&token=<token>` aufrufen, sofern eine Klasse eingerichtet
  ist. Erwartung: Die Klassen-Kopfleiste und die ersetzte Seitenleiste
  erscheinen wie bisher. Ist keine Klasse eingerichtet, diesen Punkt als
  „nicht prüfbar" vermerken.

**Übergabenotiz:**

---

### AP-1.rev: Unabhängiges Review Phase 1

**Status:** ☐ offen
**Umfang:** M
**Modell:** opus
**Abhängigkeiten:** AP-1.0, AP-1.1, AP-1.2, AP-1.3, AP-1.4, AP-1.5

**Ziel & Kontext:**
Unabhängige Qualitätsprüfung der Phase 1 durch einen Agenten, der an keiner
Implementierung beteiligt war. **Nur lesend arbeiten** (Read/Grep/Glob) –
KEINE Datei verändern.

**Vorgehen:**
1. Für jedes Implementierungs-AP der Phase (AP-1.0 bis AP-1.5): Code gegen
   dessen Akzeptanzkriterien prüfen. Stichproben im Quelltext nehmen, nicht
   nur den Übergabenotizen glauben.
2. Phasen-Endzustand prüfen: Ist der in Abschnitt 6 definierte lauffähige
   Endzustand tatsächlich erreicht?
3. Scope-Check gegen Abschnitt 2 (Nicht-Ziele). Besonders prüfen:
   - Wurde am Theme (`Theme/`) etwas geändert? Das wäre eine Verletzung.
   - Wurde eine Versionsnummer außerhalb von AP-1.5 erhöht?
   - Wurde eine der toten Dateien angefasst (`assets/css/frontend.css`,
     `frontend-positioning.css`, `unified-frontend.css`, `*.js.backup`)?
   - Wurde ein Build-Schritt in den CDB-Designer eingeführt?
4. Qualitäts-Check:
   - Enthält eine PHP-Datei des CDB-Designers PHP-8.0-Syntax? Suche nach
     `match(`, `?->`, `str_contains`, `str_starts_with`, `str_ends_with`
     sowie nach Union Types in Signaturen.
   - Sind alle Ausgaben in `render.php` und den PHP-Dateien maskiert
     (`esc_attr`, `esc_html`, `esc_url`)?
   - Liegen neue `console.log` hinter `window.cbdDebug`?
   - Gibt es tote Verweise (auf gelöschte Funktionen oder Dateien)?
   - **Besonders wichtig:** Prüfe, ob die `data-stable-id`-Extraktion jetzt
     mehrfach im Code steht. `CBD_Classroom_Gate::block_erlaubt()` in
     `includes/class-cbd-classroom-gate.php` enthält sie bereits; AP-1.3
     sollte sie wiederverwenden, nicht kopieren. Eine dritte Fassung wäre ein
     mittlerer Befund.
5. Befunde als Bericht in die Übergabenotiz: je Befund Schweregrad
   (kritisch / mittel / gering), betroffenes AP, Datei und Fundstelle.

**Akzeptanzkriterien:**
- [ ] Jedes Implementierungs-AP der Phase wurde gegen seine
      Akzeptanzkriterien geprüft.
- [ ] Alle Befunde mit Schweregrad, Datei und Fundstelle dokumentiert.
- [ ] Keine Datei wurde verändert (`git status` in beiden Repositories ist
      unverändert gegenüber dem Stand vor dem Review).

**Tests:**
- entfällt (Review-AP; das Ergebnis ist der Bericht).

**Übergabenotiz:**

---

### AP-1.doc: Dokumentation Phase 1

**Status:** ☐ offen
**Umfang:** S
**Modell:** sonnet
**Abhängigkeiten:** AP-1.rev

**Ziel & Kontext:**
Die Dokumentation auf den Stand nach Phase 1 bringen, damit das Projekt ohne
Kenntnis dieses Plans erweiterbar bleibt. Das Projekt hat eine gewachsene
Doku-Konvention – **diese erweitern, keine Parallelstruktur aufbauen.**

**Betroffene Dateien:**
- `CDB/CLAUDE.md` (ändern)
- `CDB/reference_file_map.md` (ändern)
- `EWB/CLAUDE.md` (ändern)
- `EWB/reference_file_map.md` (ändern)
- `DOKUMENTATION.md` im Projektstamm (ändern)

**Vorgehen:**
1. Alle Übergabenotizen der Phase 1 durchgehen.
2. In `CDB/CLAUDE.md` einen neuen Abschnitt „LaTeX-Formeln: Renderpfad und
   Wiederholrendern" anlegen, nach dem Vorbild des Abschnitts
   „Klassen-Durchlass für gesperrte Seiten" (mit Unterabschnitten zur Naht,
   zu geteilten Helfern und zum Prüfharnisch). Inhalt:
   - Wo die Registrierung liegt (`class-latex-parser.php`, Konstruktor),
     mit welchen Prioritäten (`render_block` 5, `the_content` **11**).
   - Die öffentliche Funktion `window.cbdRenderLatex(root)` mit ihrer
     vollständigen Zusage (Parameter, Rückgabe, Verhalten ohne KaTeX).
   - **Warum Display-Formeln ein `<span>` und kein `<div>` sind** – sonst
     zerreißt der Browser Absätze und Textknoten gehen in Blöcken verloren,
     die ihren Inhalt umsortieren.
   - Dass dies die **dritte** Stelle ist, an der die beiden Plugins
     zusammenwirken (neben dem Accordion-Import und der
     Klassen-Freigabe) – und dass die Naht einseitig optional ist
     (`typeof`-Prüfung im Accordion).
3. In `CDB/CLAUDE.md` die veraltete Aussage korrigieren, LaTeX werde in
   `CBD_Block_Registration::render_block()` bei Zeile 850-853 geparst
   (Abschnitt „Known Issues & Technical Debt", Punkt 5). Das stimmt nicht
   mehr: Das Parsen läuft über den globalen `render_block`-Filter.
4. In `EWB/CLAUDE.md` beim Accordion-Block vermerken, dass Klappzeilen zur
   Laufzeit aus Überschriften entstehen und dass `buildRows()` deshalb über
   `childNodes` iterieren **muss** – mit `children` gehen Textknoten
   verloren, sobald ein Block-Element einen Absatz aufspaltet.
5. Beide `reference_file_map.md` gegen den tatsächlichen Dateibestand der
   geänderten Ordner abgleichen. In `EWB/reference_file_map.md` sicherstellen,
   dass die `accordion-row`-Beschreibung wirklich entfernt ist (AP-1.2).
6. In `DOKUMENTATION.md` im Projektstamm einen Eintrag für dieses Vorhaben
   ergänzen, im Stil der bestehenden Einträge („Vorhaben ‚…' (2026-08)"), mit
   Verweis auf `Plugins/CDB-Designer/docs/PLAN-Vier-Erweiterungen.md`.
7. „Stand"-Datum in beiden Datei-Maps aktualisieren.

**Akzeptanzkriterien:**
- [ ] Jede in Phase 1 neue oder geänderte Datei hat eine aktuelle Zeile in
      der Datei-Map ihrer Komponente.
- [ ] `CDB/CLAUDE.md` beschreibt `window.cbdRenderLatex` vollständig genug,
      dass ein fremder Entwickler die Funktion ohne Blick in den Quelltext
      aufrufen könnte.
- [ ] Die veraltete Aussage zum LaTeX-Parsing in `render_block()` ist
      korrigiert.
- [ ] `EWB/reference_file_map.md` erwähnt `accordion-row` nicht mehr.
- [ ] `DOKUMENTATION.md` verweist auf diesen Plan.
- [ ] Kein Verweis in der Dokumentation zeigt auf nicht mehr existierende
      Dateien oder Funktionen.

**Tests:**
- Stichprobe: Zwei zufällige Zeilen jeder geänderten Datei-Map gegen den
  echten Dateiinhalt prüfen (Zweck und genannte Funktionen stimmen).
- Prüfschritt: Jeden in Phase 1 neu gesetzten Dateiverweis in der
  Dokumentation öffnen – die Datei muss existieren.

**Übergabenotiz:**

---

### Phase 2: Neue Funktionen

---

### AP-2.1: Datenmodell und Sanitizer für die Icon-Position

**Status:** ☐ offen
**Umfang:** M
**Modell:** sonnet
**Abhängigkeiten:** keine

**Ziel & Kontext:**
Die Icon-Position eines Container-Block-Designs soll wieder einstellbar
werden: vier Container-Ecken plus ein Feinversatz in Pixeln. Dieses AP baut
ausschließlich die **Datenschicht** – Grenzen, Bereinigung, CSS-Erzeugung und
einen Prüfharnisch. Das Rendering baut AP-2.2, das Admin-Formular AP-2.3.
Beide bauen auf den hier festgelegten Funktionssignaturen auf.

**Ausgangslage – wichtig zu verstehen.** Es gibt heute **keine** funktionierende
Icon-Positionierung, die man erweitern könnte:
- `CDB/includes/functions.php`, Funktion `cbd_parse_features_from_post()`
  (ca. `:261-301`), schreibt `'position' => sanitize_text_field($f['icon']['position'] ?? 'top-left')`
  – **ohne Whitelist**.
- Das Positionsfeld wurde aus beiden Admin-Formularen entfernt. In
  `CDB/admin/new-block.php` (ca. `:716-722`) und `CDB/admin/edit-block.php`
  (ca. `:390-396`) steht an seiner Stelle der Kommentar
  „Icon is now fixed in top-left position with title". Der Wert kommt aus dem
  Formular also nie an und wird bei jedem Speichern auf `'top-left'`
  zurückgeschrieben.
- Gelesen wird er nur in `CDB/includes/class-cbd-style-loader.php`
  (ca. `:1870-1882`), wo er CSS gegen `.cbd-container-<slug> .cbd-icon`
  erzeugt. **Das ist doppelt wirkungslos:** Der Wrapper heißt
  `cbd-container cbd-block-<slug>`, niemals `cbd-container-<slug>`, und die
  Klasse `.cbd-icon` wird von keiner Stelle gerendert.

**Folge für die Rückwärtskompatibilität:** In **jedem** bestehenden Design
steht `"position":"top-left"` – ein Wert, den nie jemand gewählt hat. Würde
`top-left` künftig „linke obere Container-Ecke" bedeuten, verlöre jeder
vorhandene Block beim Update sein Icon aus der Kopfzeile. Deshalb heißt der
Standardwert **`header`**, die neuen Werte tragen das Präfix `container-`,
und alles Unbekannte fällt auf `header` zurück.

**Verbindliche Schnittstelle – AP-2.2 und AP-2.3 programmieren dagegen:**

Alle Funktionen kommen nach `CDB/includes/functions.php` und werden wie die
dortigen Nachbarn in `if (!function_exists(…))` gewickelt.

```php
cbd_icon_position_defaults(): array
// [
//   'positions'      => ['header','container-top-left','container-top-right',
//                        'container-bottom-left','container-bottom-right'],
//   'default'        => 'header',
//   'offset_min'     => -200,
//   'offset_max'     => 200,
//   'offset_default' => 0,
// ]

cbd_sanitize_icon_position($raw): string
// wp_unslash, Trim, Kleinschreibung; Wert muss in 'positions' stehen,
// sonst 'header'. Insbesondere fallen die Altwerte 'top-left',
// 'top-right', 'bottom-left', 'bottom-right' auf 'header' zurück.

cbd_sanitize_icon_offset($raw): int
// wp_unslash; deutsches Dezimalkomma zu Punkt (str_replace(',', '.', …));
// nicht numerisch -> 0; runden; auf [offset_min, offset_max] klemmen.

cbd_get_icon_position_class(string $position): string
// 'header'                 -> ''
// 'container-top-left'     -> 'cbd-icon-positioned cbd-icon-at-top-left'
// (entsprechend für die drei anderen Ecken)

cbd_get_icon_position_style(int $offset_x, int $offset_y): string
// Beide 0 -> '' (kein style-Attribut nötig)
// sonst   -> '--cbd-icon-dx:12px;--cbd-icon-dy:-4px;'
// Zahlen immer locale-frei formatieren, nie mit Komma.

cbd_icon_position_preview(string $position, int $offset_x, int $offset_y): array
// Beschriftung => lesbarer Text, für die Anzeige im Admin-Formular, z. B.
// ['Position' => 'Kopfzeile neben dem Titel', 'Versatz' => '12 px rechts, 4 px hoch']
```

**Speicherformat im `features`-JSON – bewusst flach:**

| Schlüssel | Typ | Vorgabe |
|---|---|---|
| `icon.position` | string aus der Whitelist | `header` |
| `icon.offsetX` | int | `0` |
| `icon.offsetY` | int | `0` |

Flach, weil `CBD_Design_Transfer` Designs mit flachen Punkt-Pfaden nach
Markdown serialisiert und in `sanitize_json_field()` die Verschachtelungstiefe
begrenzt. Ein `icon.position.x` läge eine Ebene tiefer und könnte am Export
scheitern.

**Betroffene Dateien:**
- `CDB/includes/functions.php` (ändern)
- `CDB/tools/test-icon-position.php` (neu)

**Vorgehen:**

Dieses AP wird **nach TDD** umgesetzt: erst der Prüfharnisch, dann die
Implementierung.

1. **Prüfharnisch zuerst schreiben:** `CDB/tools/test-icon-position.php`
   anlegen. Baue ihn nach dem Muster von `CDB/tools/test-icon-scale.php`:
   CLI-Guard (`if (PHP_SAPI !== 'cli') exit;`), `define('ABSPATH','/')`,
   Stubs für `__()`, `wp_unslash()`, `sanitize_text_field()`,
   `get_option()`/`update_option()` gegen ein Array in `$GLOBALS`, dann
   `require_once` von `includes/functions.php`. Eigene
   `check($label, $cond, $actual)`-Funktion, Fehlerzähler in
   `$GLOBALS['fails']`, am Ende `exit(0)` bzw. `exit(1)`.
   Testfälle, mindestens:
   - `cbd_icon_position_defaults()` liefert genau fünf Positionen, Vorgabe
     `header`, Grenzen −200/200.
   - `cbd_sanitize_icon_position('container-top-left')` → `container-top-left`
   - `cbd_sanitize_icon_position('top-left')` → **`header`** (Altwert)
   - `cbd_sanitize_icon_position('bottom-right')` → `header` (Altwert)
   - `cbd_sanitize_icon_position('')` → `header`
   - `cbd_sanitize_icon_position('<script>x</script>')` → `header`
   - `cbd_sanitize_icon_position(' CONTAINER-TOP-RIGHT ')` → `container-top-right`
   - `cbd_sanitize_icon_offset('12')` → `12`
   - `cbd_sanitize_icon_offset('-14')` → `-14`
   - `cbd_sanitize_icon_offset('12,5')` → `13` (deutsches Komma, gerundet)
   - `cbd_sanitize_icon_offset('12.4')` → `12`
   - `cbd_sanitize_icon_offset('abc')` → `0`
   - `cbd_sanitize_icon_offset('')` → `0`
   - `cbd_sanitize_icon_offset('9999')` → `200` (geklemmt)
   - `cbd_sanitize_icon_offset('-9999')` → `-200` (geklemmt)
   - `cbd_get_icon_position_class('header')` → `''`
   - `cbd_get_icon_position_class('container-bottom-right')` enthält
     `cbd-icon-positioned` **und** `cbd-icon-at-bottom-right`
   - `cbd_get_icon_position_style(0, 0)` → `''`
   - `cbd_get_icon_position_style(12, -4)` enthält `--cbd-icon-dx:12px` und
     `--cbd-icon-dy:-4px`, und **kein Komma**
   - Rundlauf mit Slashes: Der Wert wird wie aus `$_POST` mit
     `addslashes()` vorbehandelt und muss trotzdem korrekt ankommen
     (Muster: `as_post()` in `tools/test-icon-value.php`).
2. Prüfharnisch ausführen – **alle Prüfungen müssen fehlschlagen**, weil die
   Funktionen noch nicht existieren. Diesen roten Zustand committen
   (`AP-2.1: Rote Tests für die Icon-Position`).
3. Die sechs Funktionen in `CDB/includes/functions.php` implementieren, bis
   der Harnisch grün ist. **Den Harnisch dabei nicht anfassen.**
4. `cbd_parse_features_from_post()` (ca. `:261-301`) erweitern: Der
   Icon-Zweig schreibt zusätzlich zu `enabled` und `value` jetzt
   - `'position' => cbd_sanitize_icon_position($f['icon']['position'] ?? '')`
   - `'offsetX'  => cbd_sanitize_icon_offset($f['icon']['offsetX'] ?? 0)`
   - `'offsetY'  => cbd_sanitize_icon_offset($f['icon']['offsetY'] ?? 0)`
   Der Aufruf von `sanitize_text_field()` für `position` entfällt dabei –
   `cbd_sanitize_icon_position()` übernimmt die Bereinigung vollständig.
5. Ergänze im Harnisch drei weitere Prüfungen für
   `cbd_parse_features_from_post()`: Ein `$_POST` ohne Positionsfelder liefert
   `position === 'header'`, `offsetX === 0`, `offsetY === 0`; ein `$_POST` mit
   `position = 'top-left'` (Altwert) liefert ebenfalls `header`.
6. `php tools/check-php74.php` ausführen und grün bekommen.

**Akzeptanzkriterien:**
- [ ] `php tools/test-icon-position.php` läuft und meldet null Fehlschläge.
- [ ] Der Commit-Verlauf zeigt zuerst die roten Tests, dann die
      Implementierung (TDD-Nachweis).
- [ ] Alle sechs Funktionen sind in `if (!function_exists(…))` gewickelt.
- [ ] `cbd_sanitize_icon_position('top-left')` liefert `header` – der
      Altwert ändert das Aussehen bestehender Designs nicht.
- [ ] `cbd_get_icon_position_style()` erzeugt nie ein Dezimalkomma.
- [ ] `cbd_parse_features_from_post()` schreibt die drei neuen Schlüssel.
- [ ] `php tools/check-php74.php` meldet keinen Fehler.
- [ ] `CDB/reference_file_map.md` führt `tools/test-icon-position.php` und
      nennt die neuen Funktionen bei `includes/functions.php`.

**Tests:**
- Smoke-Test: `php -l includes/functions.php` ohne Fehler.
- Prüfschritt A: `php tools/test-icon-position.php` – Ausgabe und Exitcode
  ins Testprotokoll.
- Prüfschritt B (Regression): `php tools/test-icon-value.php` und
  `php tools/test-icon-scale.php` ausführen – beide betreffen dieselbe Datei
  und müssen weiterhin bestehen.
- Prüfschritt C (Bestandsdaten): Auf dem Testserver ein vorhandenes
  Block-Design im Backend öffnen und ohne Änderung speichern. Danach in
  phpMyAdmin (http://pma.localhost:8080/) in der Tabelle `wp_cbd_blocks` die
  Spalte `features` dieses Designs ansehen. Erwartung: `icon.position` steht
  auf `header`, `offsetX` und `offsetY` auf `0`. Die Seite mit diesem Design
  im Frontend aufrufen – das Icon steht unverändert neben dem Titel.

**Übergabenotiz:**

---

### AP-2.2: Icon-Position im Frontend rendern

**Status:** ☐ offen
**Umfang:** M
**Modell:** opus
**Abhängigkeiten:** AP-2.1

**Ziel & Kontext:**
Die in AP-2.1 gespeicherte Position und der Versatz sollen im Frontend
wirken. Dieses AP ändert das gerenderte Markup und das lebende Frontend-CSS.

**Wo das Icon heute entsteht.** In
`CDB/includes/class-cbd-block-registration.php`, ca. `:1227-1249`:

```php
$has_icon = !empty($features['icon']['enabled']);
if ($has_icon || !empty($block_title)) {
    $html .= '<div class="cbd-block-header" style="margin-bottom: 15px; padding: 12px 20px;">';
    if ($has_icon) {
        $html .= '<span class="cbd-header-icon' . $this->custom_icon_class($icon_value) . '">';
        $html .= $this->render_icon($icon_value, $icon_color);
        $html .= '</span>';
    }
    …
}
```

`render_icon()` (ca. `:2027-2092`) kennt die Position **nicht** und setzt als
einzigen Inline-Style die Farbe. Das ist gut so – dieses AP fasst
`render_icon()` nicht an, sondern nur den umgebenden `<span class="cbd-header-icon">`.

**Die Falle, an der das schon einmal gescheitert wäre.**
`.cbd-header-icon` trägt in `CDB/assets/css/cbd-frontend-clean.css`
(ca. `:1158-1168`) ein `transform: translateY(-6px)` zur Grundlinien-
Ausrichtung am Titel – und zwar mit **je eigenem Wert pro Breakpoint**:
−6px (Desktop), −4px (≤768px, ca. `:1221-1232`), −3px (≤480px, ca. `:1239-1250`).
Ein serverseitig gesetztes `transform` würde alle drei löschen. Deshalb
transportiert PHP nur zwei **CSS-Variablen**, und das CSS rechnet sie in
sein je eigenes `translate()` ein.

`.cbd-container` ist bereits `position: relative` (ca. `:34-35` und nochmals
`:649-655`) – der Bezugsrahmen für die Eckpositionierung existiert also schon.
`.cbd-block-header` ist dagegen `position: static` mit `display:flex`
(ca. `:1143-1155`); ein absolut positioniertes Icon wird daraus
herausgenommen, sodass im Kopf nur der Titel bleibt. Genau das ist gewollt.

**Betroffene Dateien:**
- `CDB/includes/class-cbd-block-registration.php` (ändern)
- `CDB/assets/css/cbd-frontend-clean.css` (ändern)

**Vorgehen:**

1. **Markup ergänzen.** An der Stelle ca. `:1238`, wo der
   `<span class="cbd-header-icon…">` gebaut wird:
   - Position und Versatz aus `$features['icon']` lesen, jeweils durch die
     Funktionen aus AP-2.1 (`cbd_sanitize_icon_position()`,
     `cbd_sanitize_icon_offset()`) – **nicht** roh vertrauen, die Werte
     stammen aus einem JSON-Feld der Datenbank.
   - Klassen über `cbd_get_icon_position_class($position)` anhängen.
   - Inline-Style über `cbd_get_icon_position_style($x, $y)` erzeugen und,
     falls nicht leer, als `style="…"` mit `esc_attr()` ausgeben.
   - Bei `header` entstehen weder zusätzliche Klasse noch `style` – das
     Markup ist dann **zeichengleich mit heute**.
2. **CSS – Versatz im Kopfzeilen-Modus.** In `cbd-frontend-clean.css` die
   drei `transform`-Regeln von `.cbd-header-icon` so umschreiben, dass sie
   die Variablen einrechnen, z. B. für den Desktop-Breakpoint:
   `transform: translate(var(--cbd-icon-dx, 0px), calc(-6px + var(--cbd-icon-dy, 0px)));`
   Die Breakpoints ≤768px und ≤480px bekommen dieselbe Form mit ihrem eigenen
   Basiswert (−4px bzw. −3px). **Alle drei Stellen ändern** – wird eine
   vergessen, verrutscht das Icon nur auf einer Bildschirmgröße.
3. **CSS – Eckpositionierung.** Neue Regeln im Abschnitt „BLOCK HEADER SYSTEM"
   (ab ca. `:1138`) anlegen:
   - `.cbd-container .cbd-header-icon.cbd-icon-positioned` →
     `position: absolute; z-index: 3;` und `transform` nur aus den Variablen
     (`translate(var(--cbd-icon-dx, 0px), var(--cbd-icon-dy, 0px))`) – im
     Eckmodus gibt es keine Grundlinien-Ausrichtung zum Titel, die man
     erhalten müsste.
   - Je Ecke die Verankerung mit einem Grundabstand von 10px:
     `.cbd-icon-at-top-left { top: 10px; left: 10px; }` und entsprechend für
     `top-right`, `bottom-left`, `bottom-right`.
   - `margin-right` (heute 12px für den Abstand zum Titel) im Eckmodus auf 0
     zurücksetzen.
4. **Nicht anfassen:** `assets/css/frontend-positioning.css` enthält ein
   verlockend fertiges 9-Positionen-Raster mit `.cbd-positioned.cbd-inside.*`.
   Die Datei ist in **keinem** `wp_enqueue_style()` referenziert und damit
   wirkungslos. Ebenso die Selektoren `.cbd-icon`, `.cbd-icon-inside` und
   `.cbd-positioned` **innerhalb** von `cbd-frontend-clean.css` – sie sehen
   lebendig aus, matchen aber nichts, weil die erzeugende Methode
   `render_features()` seit Langem auskommentiert ist
   (`class-cbd-block-registration.php`, ca. `:1251-1254`). Verwende
   ausschließlich die neuen Klassennamen aus Schritt 3.
5. **Style-Loader nicht erweitern.** Der Feature-CSS-Zweig in
   `includes/class-cbd-style-loader.php` (ca. `:1853-1885`) schreibt gegen
   den Präfix `.cbd-container-<slug>`, den es nicht gibt. Dort **nichts**
   ergänzen – neues CSS würde ebenfalls ins Leere laufen und zusätzlich den
   Transient-Zwischenspeicher füllen, sodass der Fehler erst nach einer
   Cache-Leerung sichtbar würde.
6. `php tools/check-php74.php` ausführen und grün bekommen.

**Akzeptanzkriterien:**
- [ ] Bei `position = 'header'`, `offsetX = 0`, `offsetY = 0` ist das
      erzeugte Icon-Markup zeichengleich mit dem vor der Änderung – weder
      eine zusätzliche Klasse noch ein `style`-Attribut.
- [ ] Bei `position = 'container-top-right'` trägt der Icon-Span die Klassen
      `cbd-icon-positioned` und `cbd-icon-at-top-right`.
- [ ] Bei `offsetX = 12`, `offsetY = -4` trägt der Icon-Span
      `style="--cbd-icon-dx:12px;--cbd-icon-dy:-4px;"` (maskiert).
- [ ] Alle **drei** `transform`-Regeln von `.cbd-header-icon` (Desktop,
      ≤768px, ≤480px) rechnen `--cbd-icon-dy` in ihren eigenen Basiswert ein.
- [ ] `render_icon()` wurde nicht verändert.
- [ ] In `assets/css/frontend-positioning.css`, `frontend.css` und
      `unified-frontend.css` wurde nichts geändert.
- [ ] In `includes/class-cbd-style-loader.php` wurde nichts ergänzt.
- [ ] `php tools/check-php74.php` meldet keinen Fehler.
- [ ] `CDB/reference_file_map.md` ist aktualisiert.

**Tests:**
- Smoke-Test: `php -l includes/class-cbd-block-registration.php`; Seite mit
  Container-Block auf dem Testserver aufrufen – keine weiße Seite.
- Prüfschritt A (Bestand unverändert): Vor der Änderung den Quelltext des
  Icon-Bereichs einer Seite mit Container-Block sichern (Rechtsklick →
  Seitenquelltext, Abschnitt `cbd-block-header` kopieren). Nach der Änderung
  denselben Abschnitt vergleichen. Erwartung: **zeichengleich**.
- Prüfschritt B (Ecke): In phpMyAdmin (http://pma.localhost:8080/,
  Datenbank `d0000001`, Tabelle `wp_cbd_blocks`) bei einem Testdesign im
  Feld `features` `icon.position` auf `container-top-right` setzen. Seite neu
  laden. Erwartung: Das Icon sitzt in der rechten oberen Ecke des Containers,
  der Titel steht allein in der Kopfzeile.
- Prüfschritt C (Versatz): Bei demselben Design `offsetX` auf `20` und
  `offsetY` auf `10` setzen. Erwartung: Das Icon rückt 20px nach rechts und
  10px nach unten.
- Prüfschritt D (Breakpoints): Im Kopfzeilen-Modus mit `offsetY = 5` das
  Browserfenster auf unter 768px und unter 480px verkleinern. Erwartung: Das
  Icon bleibt in allen drei Größen am Titel ausgerichtet (nur um 5px
  verschoben), springt nicht.
- Prüfschritt E (unbekannter Wert): `icon.position` in der Datenbank von Hand
  auf `quatsch` setzen. Erwartung: Das Icon steht in der Kopfzeile wie bei
  `header`, keine PHP-Warnung im `debug.log`.
- Log-Check: `…\fos\wp-content\debug.log` ohne neue Einträge.
- Regressionsrelevanz: Die Icon-**Größe** (Einstellungen → Icon-Größe) muss
  weiterhin wirken. Faktor auf 150 % stellen und prüfen, dass das Icon in
  beiden Modi größer wird.

**Übergabenotiz:**

---

### AP-2.3: Icon-Position im Admin-Formular einstellen und vorschauen

**Status:** ☐ offen
**Umfang:** M
**Modell:** sonnet
**Abhängigkeiten:** AP-2.1

**Ziel & Kontext:**
Die Position und der Versatz sollen in den beiden Block-Design-Formularen
eingestellt und dort sofort vorgeschaut werden können. Dieses AP hängt nur
an AP-2.1 (den Funktionen), **nicht** an AP-2.2 – beide dürfen parallel
laufen.

**Wo die Felder hingehören.** Beide Formulare haben einen Icon-Abschnitt in
der Features-Sektion:
- `CDB/admin/new-block.php`: Checkbox `features[icon][enabled]` (ca. `:700`),
  verstecktes Feld `features[icon][value]` mit `id="icon_value"` (ca. `:706`),
  danach ab ca. `:716` ein Hinweiskasten mit dem Text „Das Icon wird
  automatisch in der linken oberen Ecke neben dem Titel angezeigt."
- `CDB/admin/edit-block.php`: dieselben Elemente bei ca. `:373`, `:381`,
  `:390`.

Der Hinweiskasten wird durch die neuen Felder ersetzt.

**Die drei Fallen dieses APs:**

1. **Die beiden Formulare sind Duplikate und driften.** Picker-Modal und
   Vorschau-Logik stehen in jeder Datei einmal komplett (~200 Zeilen
   JavaScript je Datei). Beide müssen **im selben AP** und inhaltlich gleich
   geändert werden.
2. **Der Icon-Picker feuert kein Ereignis.** `CDB/assets/js/icon-picker.js`
   schreibt den gewählten Wert bei ca. `:393` mit `$('#icon_value').val(jsonValue);`
   ins Formular – **ohne** `.trigger('change')`. Die vorhandene
   Vorschau-Bindung hängt an `input change`; bei einem reinen Icon-Wechsel
   passiert deshalb nichts.
3. **Zwei Vorschau-Skripte streiten um dasselbe `style`-Attribut.** Die
   Inline-Funktion `updateLivePreview()` (in `new-block.php` ca. `:1274-1478`,
   in `edit-block.php` ca. `:983-1080`) setzt Stile per `.css({…})`;
   `CDB/assets/js/admin-live-preview-fix.js` (ca. `:66`) setzt danach
   `$preview.attr('style', css + …)` und **löscht damit alles**. Die neue
   Icon-Vorschau darf deshalb **nicht** in `.cbd-preview-content` schreiben,
   sondern muss ein eigenes Element bekommen.

**Betroffene Dateien:**
- `CDB/admin/new-block.php` (ändern)
- `CDB/admin/edit-block.php` (ändern)
- `CDB/assets/js/icon-picker.js` (ändern)
- `CDB/assets/css/new-block-form.css` (ändern)
- `CDB/assets/css/edit-block-form.css` (ändern)

**Vorgehen:**

1. **Formularfelder in beiden Dateien.** Den Hinweiskasten ersetzen durch:
   - `<select name="features[icon][position]">` mit fünf Einträgen. Die
     Optionen **aus `cbd_icon_position_defaults()['positions']` erzeugen**,
     nicht von Hand aufzählen – dann bleiben Feld und Whitelist automatisch
     synchron. Beschriftungen auf Deutsch:
     `header` → „Kopfzeile, neben dem Titel (Standard)",
     `container-top-left` → „Container, links oben",
     `container-top-right` → „Container, rechts oben",
     `container-bottom-left` → „Container, links unten",
     `container-bottom-right` → „Container, rechts unten".
   - Zwei Zahlenfelder `<input type="number" name="features[icon][offsetX]">`
     und `…[offsetY]`, mit `min`/`max` aus
     `cbd_icon_position_defaults()['offset_min'|'offset_max']` und
     `step="1"`. Beschriftungen „Versatz waagerecht (px)" und
     „Versatz senkrecht (px)", dazu ein kurzer Hinweis, dass positive Werte
     nach rechts bzw. nach unten verschieben.
   - Vorbelegung aus dem gespeicherten Design über
     `cbd_sanitize_icon_position()` und `cbd_sanitize_icon_offset()`; in
     `new-block.php` die Vorgaben aus `cbd_icon_position_defaults()`.
2. **Vorschaufläche in beiden Dateien.** In der Sidebar-Sektion „Live Preview"
   (`new-block.php` ca. `:680`, `edit-block.php` ca. `:656`) einen **eigenen**
   Vorschaublock ergänzen, der nicht in `.cbd-preview-content` liegt:
   ein Element `.cbd-icon-preview-stage` (stellt den Container dar,
   `position: relative`, feste Höhe ~120px, sichtbarer Rahmen) mit einem
   Kind `.cbd-icon-preview-header` (Kopfzeile mit Beispieltitel) und darin
   bzw. darüber `.cbd-icon-preview-icon` (das Icon selbst).
3. **Vorschau-JavaScript in beiden Dateien.** Eine neue Funktion
   `updateIconPreview()` ergänzen und an `input change` auf
   `select[name="features[icon][position]"]`,
   `input[name="features[icon][offsetX]"]`,
   `input[name="features[icon][offsetY]"]`,
   `input[name="features[icon][enabled]"]` sowie `#icon_value` binden.
   Sie soll:
   - bei `header` das Icon in die Kopfzeile setzen (statisch, neben den
     Titel) und `--cbd-icon-dx`/`--cbd-icon-dy` als Variablen am Icon setzen,
   - bei einem `container-*`-Wert das Icon absolut in die entsprechende Ecke
     der Vorschaufläche setzen (10px Grundabstand) und die beiden Variablen
     ebenso setzen,
   - bei abgeschaltetem Icon-Feature die Vorschaufläche ausblenden,
   - das tatsächlich gewählte Icon anzeigen, nicht ein Platzhaltersymbol.
     Der aktuelle Wert steht in `#icon_value` als JSON; die bereits
     vorhandene Anzeige in `.cbd-selected-icon` zeigt das gewählte Icon –
     übernimm deren HTML per `.html()` in die Vorschau, statt die Icon-Typen
     erneut auszuwerten.
   `updateIconPreview()` einmal beim Laden aufrufen, damit die Vorschau
   sofort stimmt.
4. **`icon-picker.js` – Ereignis auslösen.** Bei ca. `:393` nach
   `$('#icon_value').val(jsonValue);` ein `.trigger('change')` ergänzen.
   Prüfe, dass dadurch keine ungewollte Doppelausführung entsteht (die
   bestehende Bindung `input[name^="features"]` greift für `#icon_value`
   nicht, weil dort über die ID gebunden wird – siehe Schritt 3).
5. **CSS.** Die Vorschaufläche in `assets/css/new-block-form.css` und
   `assets/css/edit-block-form.css` gestalten. **Hinweis:**
   `new-block-form.css` enthält bei ca. `:591` bereits eine verwaiste Klasse
   `.cbd-icon-position-controls` aus einem früheren Anlauf – die darf
   wiederverwendet werden, wenn sie passt, sonst entfernen.
6. **Icon-Größe in der Vorschau.** Auf den Formularseiten wird
   `assets/css/cbd-frontend-clean.css` **nicht** geladen (nur auf der Seite
   `cbd-block-preview`, siehe `includes/class-cbd-admin.php` ca. `:541`).
   Die Variable `--cbd-icon-scale` fehlt dort also, und `custom-icons.css`
   fällt auf `1` zurück. Damit die Vorschau maßstabsgetreu ist, den Faktor
   auf der Formularseite selbst setzen: über `wp_add_inline_style()` mit
   `cbd_get_icon_scale_css()` (die Funktion existiert bereits in
   `includes/functions.php`). Als Ziel-Handle das Stylesheet des jeweiligen
   Formulars verwenden.
7. `php tools/check-php74.php` ausführen und grün bekommen.

**Akzeptanzkriterien:**
- [ ] Beide Formulare enthalten Auswahlfeld und zwei Zahlenfelder; die
      Icon-Abschnitte beider Dateien sind inhaltlich gleich (bis auf die
      Vorbelegung aus dem gespeicherten Design).
- [ ] Die Auswahloptionen werden aus `cbd_icon_position_defaults()` erzeugt,
      nicht hart aufgezählt.
- [ ] Die Vorschau reagiert ohne Neuladen auf Positionswechsel,
      Versatzänderung, Icon-Wechsel und das Ein-/Ausschalten des Features.
- [ ] Die Vorschau zeigt das tatsächlich gewählte Icon.
- [ ] `icon-picker.js` löst nach dem Setzen von `#icon_value` ein
      `change`-Ereignis aus.
- [ ] Die Vorschau schreibt **nicht** in `.cbd-preview-content` – die
      bestehende Stil-Vorschau funktioniert unverändert.
- [ ] Ein gespeichertes Design behält Position und Versatz nach dem
      Neuladen des Formulars.
- [ ] `php tools/check-php74.php` meldet keinen Fehler.
- [ ] `CDB/reference_file_map.md` ist aktualisiert.

**Tests:**
- Smoke-Test: `php -l admin/new-block.php` und `php -l admin/edit-block.php`;
  beide Seiten im Backend aufrufen – keine weiße Seite, Konsole ohne Error.
- Prüfschritt A (Neuanlage): Auf
  http://fos.localhost:8080/wp-admin/ ein neues Block-Design anlegen, Icon
  aktivieren, ein Icon wählen, Position auf „Container, rechts unten" und
  Versatz auf −10 / −10 stellen. Erwartung: Die Vorschau zeigt das Icon rechts
  unten, um 10px nach links und oben verschoben. Speichern.
- Prüfschritt B (Rundlauf): Dasselbe Design erneut zum Bearbeiten öffnen.
  Erwartung: Auswahlfeld steht auf „Container, rechts unten", beide
  Zahlenfelder auf −10, die Vorschau stimmt sofort.
- Prüfschritt C (Icon-Wechsel): Im Bearbeiten-Formular über den Icon-Picker
  ein anderes Icon wählen. Erwartung: Die Vorschau zeigt sofort das neue
  Icon, ohne dass ein anderes Feld angefasst wird. Dies prüft Schritt 4.
- Prüfschritt D (Stil-Vorschau unbeschädigt): Im selben Formular eine
  Hintergrundfarbe ändern. Erwartung: Die bestehende Stil-Vorschau
  aktualisiert sich weiterhin, die Icon-Vorschau bleibt bestehen.
- Prüfschritt E (Grenzwerte): In das Feld „Versatz waagerecht" `9999`
  eintippen und speichern. Erwartung: Nach dem Neuladen steht dort `200`.
  `abc` eingeben → nach dem Speichern `0`.
- Log-Check: `…\fos\wp-content\debug.log` ohne neue Einträge.
- Regressionsrelevanz: Ein bestehendes Design ohne Änderung speichern und im
  Frontend prüfen – das Icon steht unverändert neben dem Titel.

**Übergabenotiz:**

---

### AP-2.4: REST-Endpunkt `cbd/v1/block-html` mit vollständiger Autorisierung

**Status:** ☐ offen
**Umfang:** M
**Modell:** opus
**Abhängigkeiten:** keine

**Ziel & Kontext:**
Das Modal aus AP-2.5 soll einen Container-Block auch dann anzeigen können,
wenn er auf einer **anderen** Seite liegt. Dafür braucht es einen Endpunkt,
der das gerenderte HTML eines einzelnen Blocks liefert.

**Dies ist das sicherheitskritischste Arbeitspaket des Vorhabens.** Das Theme
sperrt einzelne Seiten für nicht angemeldete Besucher („nur für
Lehrpersonen", Meta `_simple_clean_nur_lehrpersonen`). Diese Sperre ist an
vier Stellen durchgesetzt: `template_redirect` (Priorität 20),
`redirect_canonical`, `pre_get_posts` und `rest_pre_dispatch` für
`/wp/v2/pages/<id>`. **Ein neuer `cbd/v1`-Endpunkt geht an allen vieren
vorbei.** Das Theme dokumentiert genau diesen Fehlertyp in
`Theme/includes/sichtbarkeit.php`: „`rest_page_query` filtert nur
Sammlungen … die Sperre wäre mit einer einzigen URL auszuhebeln."

**Warum nicht der bestehende Endpunkt.** `CDB/includes/class-cbd-blocks-rest-api.php`
registriert `cbd/v1/blocks` mit `permission_callback => current_user_can('edit_posts')`
– ein Redakteursrecht. Schülerinnen und Schüler melden sich nie an; sie kommen
über das Klassenpasswort. Der neue Endpunkt braucht deshalb
`'__return_true'` als `permission_callback` **und leistet die gesamte
Autorisierung im Callback selbst**. Weil die beiden Sicherheitsmodelle
gegensätzlich sind, kommt der neue Endpunkt in eine **eigene Klasse**.

**Verbindliche Schnittstelle – AP-2.5 programmiert dagegen:**

```
GET /wp-json/cbd/v1/block-html
```

| Parameter | Typ | Pflicht | Zweck |
|---|---|---|---|
| `post_id` | int | ja | Seite, auf der der Block liegt. **Die Rechteprüfung hängt hieran.** |
| `stable_id` | string | ja | Bezeichner des Container-Blocks |
| `classroom` | int | nein | Klassen-ID einer laufenden Klassensitzung |
| `token` | string | nein | Token der Klassensitzung |

**Erfolg (HTTP 200):** `{"html": "<div class=\"cbd-container\">…</div>", "title": "Titel des Blocks"}`

**Jeder Fehlschlag (HTTP 404), immer identisch:**
`{"code":"cbd_block_not_available","message":"Der Block ist nicht verfügbar."}`

Dieselbe Antwort für: Seite existiert nicht, ist kein `publish`, ist
passwortgeschützt, ist gesperrt, Block existiert nicht, Block ist für die
Klasse nicht freigegeben. Unterschiedliche Antworten ließen sich durch
Durchprobieren zum Kartieren der gesperrten Lösungsseiten nutzen – genau
diesen Fehler hat das Theme in `simple_clean_lehrerseite_kanonisch()` bereits
einmal behoben.

**Vorhandene Bausteine – wiederverwenden, nicht nachbauen:**

| Funktion | Datei | Wofür |
|---|---|---|
| `simple_clean_seite_sichtbar($post_id)` | Theme, `includes/sichtbarkeit.php` | **Die Gesamtentscheidung.** Deckt Lehrperson, Sperre samt Vererbung auf den Unterbaum und den Freigabe-Filter ab |
| `CBD_Classroom_Gate::sitzung()` | `CDB/includes/class-cbd-classroom-gate.php` | Klassensitzung prüfen – **der Transient entscheidet, nicht der URL-Parameter** |
| `CBD_Classroom::behandelte_container($class_id, $page_id)` | `CDB/includes/class-cbd-classroom.php` | Basis-Bezeichner der für eine Klasse freigegebenen Container |
| `CBD_Classroom_Gate::block_erlaubt($block, $freigegeben)` | `CDB/includes/class-cbd-classroom-gate.php` | `stableId` aus einem `parse_blocks()`-Eintrag ziehen (inkl. `data-stable-id`-Rückfall) und Freigabe prüfen |
| `CBD_Classroom::basis_container_id()` | `CDB/includes/class-cbd-classroom.php` | Suffix `:pN` abschneiden – **die einzige Stelle, die dieses Format deuten darf** |

**Betroffene Dateien:**
- `CDB/includes/class-cbd-block-content-api.php` (neu)
- `CDB/container-block-designer.php` (ändern – **nur** eine `require_once`-Zeile und ein `::init()`-Aufruf; dies ist das einzige AP der Phase 2, das diese Datei anfassen darf)
- `CDB/tools/test-block-content-api.php` (neu)

**Vorgehen:**

1. **Klasse anlegen.** `CBD_Block_Content_API` in
   `includes/class-cbd-block-content-api.php`, nach dem Muster von
   `includes/class-cbd-blocks-rest-api.php`: statische `init()`, die
   `rest_api_init` mit `register_routes()` verbindet.
2. **Route registrieren** mit `permission_callback => '__return_true'` und
   einer `args`-Definition, die `post_id` und `stable_id` als `required`
   führt und über `sanitize_callback` bereinigt (`absint` bzw.
   `sanitize_text_field`).
3. **Autorisierungskette im Callback, genau in dieser Reihenfolge.** Bei
   jedem Fehlschlag sofort die einheitliche 404-Antwort zurückgeben:
   1. `nocache_headers()` aufrufen – **immer, als Erstes.** Dieselbe URL
      liefert für Lehrperson, Klassensitzung und Anonymen unterschiedliche
      Inhalte; ein Cache dürfte sie nie verwechseln.
   2. `$post = get_post($post_id)`. Kein Post, kein `page`/`post`-Typ oder
      `post_status !== 'publish'` → ablehnen.
   3. `post_password_required($post)` → ablehnen.
   4. **Sichtbarkeit:** `if (function_exists('simple_clean_seite_sichtbar') && !simple_clean_seite_sichtbar($post_id))`
      → ablehnen. Die `function_exists()`-Hülle ist Pflicht (Konvention aus
      `class-cbd-classroom-gate.php`); fehlt das Theme, gibt es keine Sperre.
      **Nicht** `simple_clean_seite_nur_lehrpersonen()` verwenden – die kennt
      den Klassen-Durchlass nicht.
   5. **Klassensitzung:** Ist die Seite gesperrt und liegt eine gültige
      Sitzung vor, muss zusätzlich der einzelne Block freigegeben sein.
      `CBD_Classroom_Gate::sitzung()` liest `?classroom=` und `?token=` heute
      aus `$_GET`. **Reiche die beiden Werte deshalb als echte
      Query-Parameter an den Endpunkt weiter** (siehe Schnittstelle oben) –
      dann funktioniert die vorhandene Methode unverändert. Baue **keine**
      zweite Fassung der Token-Prüfung; eine zweite Stelle, die Tokens deutet,
      ist genau die Art Fehler, gegen die der vorhandene Prüfharnisch
      `tools/test-classroom-gate.php` wacht.
   6. **Blockweise Freigabe:** Liegt eine gültige Sitzung vor, über
      `CBD_Classroom::behandelte_container($class_id, $post_id)` die
      freigegebenen Bezeichner holen und den angefragten Block dagegen prüfen.
      **Standard ist Ablehnung** – ist der Block nicht in der Liste,
      ablehnen.
4. **Block finden und rendern.**
   - `parse_blocks($post->post_content)` und rekursiv (über `innerBlocks`)
     nach dem Block mit passender `stableId` suchen. Für die Extraktion des
     Bezeichners **dieselbe Logik verwenden wie
     `CBD_Classroom_Gate::block_erlaubt()`** (Attribut `stableId`, sonst
     Regex-Rückfall auf `data-stable-id` im gespeicherten Markup) – sieh dort
     nach und rufe die vorhandene Methode auf bzw. lagere sie in eine
     gemeinsame öffentliche Hilfsmethode aus. Eine dritte Kopie dieses Regex
     ist ein Fehler.
   - Nur Blöcke mit dem Namensraum `container-block-designer/` ausliefern.
   - Gerendert wird mit **`render_block($block)`** – so, wie es
     `CBD_Classroom_Gate::inhalt_reduzieren()` tut. **Nicht** `do_blocks()`
     auf den ganzen Beitrag (rendert zu viel) und **nicht**
     `serialize_blocks()` mit eigener Ausgabe (der Whitespace-Unterschied
     zwischen JavaScript- und PHP-Serializer ist im Projekt dokumentiert).
   - Als `title` den Blocktitel aus den Attributen zurückgeben, sonst einen
     leeren String. **Niemals** den Seitentitel oder den Permalink zurückgeben.
5. **In `container-block-designer.php` einbinden:** eine `require_once`-Zeile
   bei den übrigen `require_once` (dort steht bereits eine für
   `class-cbd-block-reference.php`) und ein `CBD_Block_Content_API::init();`
   bei den übrigen `::init()`-Aufrufen. **Sonst nichts** an dieser Datei
   ändern – insbesondere keine Versionsnummer.
6. **Prüfharnisch schreiben:** `tools/test-block-content-api.php` nach dem
   Muster von `tools/test-classroom-gate.php` (CLI-Guard, Stubs, `check()`,
   Fehlerzähler, Exitcode). Prüfe mindestens:
   - Block wird gefunden über das Attribut `stableId`.
   - Block wird gefunden über den `data-stable-id`-Rückfall (Altbestand ohne
     Attribut).
   - Ein Block in `innerBlocks` wird gefunden (Rekursion).
   - Unbekannte `stable_id` → Ablehnung.
   - Ein Block mit fremdem Namensraum (z. B. `core/paragraph`) mit passender
     ID → Ablehnung.
   - Ist `simple_clean_seite_sichtbar` als Stub definiert und liefert `false`
     → Ablehnung.
   - Ist `simple_clean_seite_sichtbar` **nicht** definiert (Theme fehlt) →
     kein Fatal Error.
   - Ablehnung und Nichtexistenz liefern **denselben** Code und dieselbe
     Nachricht.
7. `php tools/check-php74.php` ausführen und grün bekommen.

**Akzeptanzkriterien:**
- [ ] `php tools/test-block-content-api.php` läuft und meldet null
      Fehlschläge.
- [ ] Der `permission_callback` ist `'__return_true'`, und der Callback
      selbst führt alle sechs Prüfschritte aus Schritt 3 durch.
- [ ] `nocache_headers()` wird in **jedem** Antwortpfad aufgerufen, auch bei
      Ablehnung.
- [ ] Ablehnung und Nichtexistenz liefern zeichengleiche Antworten mit
      HTTP 404.
- [ ] Die Antwort enthält **nie** Seitentitel oder Permalink.
- [ ] Jeder Aufruf einer Theme-Funktion ist mit `function_exists()`
      abgesichert.
- [ ] Die `data-stable-id`-Extraktion existiert nach diesem AP **nicht**
      öfter im Code als vorher (Nachweis:
      `grep -r "data-stable-id" includes/` zählen und mit dem Stand vor dem
      AP vergleichen; die Fundstellen dürfen nicht zunehmen).
- [ ] Gerendert wird mit `render_block()`, nicht mit `do_blocks()` oder
      `serialize_blocks()`.
- [ ] `php tools/check-php74.php` meldet keinen Fehler.
- [ ] `CDB/reference_file_map.md` führt beide neuen Dateien.

**Tests:**
- Smoke-Test: `php -l includes/class-cbd-block-content-api.php`; Testserver
  aufrufen – Plugin aktiv, keine weiße Seite.
- Prüfschritt A (Erfolg): Auf dem Testserver eine veröffentlichte,
  **nicht** gesperrte Seite mit einem Container-Block anlegen. Dessen
  `stableId` aus dem Seitenquelltext ablesen (`data-stable-id="…"`). Dann
  `http://fos.localhost:8080/wp-json/cbd/v1/block-html?post_id=<id>&stable_id=<sid>`
  **abgemeldet** (privates Browserfenster) aufrufen. Erwartung: HTTP 200,
  JSON mit `html`.
- Prüfschritt B (gesperrte Seite – der wichtigste Test): Dieselbe Seite im
  Backend über die Meta-Box auf „nur für Lehrpersonen" stellen. Aufruf
  erneut **abgemeldet**. Erwartung: **HTTP 404** mit
  `cbd_block_not_available`. Der Seitentitel darf in der Antwort nicht
  vorkommen.
- Prüfschritt C (angemeldet): Denselben Aufruf als angemeldeter
  Administrator wiederholen. Erwartung: HTTP 200. **Hinweis:** Die
  REST-Schnittstelle verlangt zur Cookie-Anmeldung zusätzlich den Kopf
  `X-WP-Nonce`; ohne ihn gilt die Anfrage als anonym. Am einfachsten im
  Browser als angemeldeter Nutzer aufrufen.
- Prüfschritt D (unbekannte ID): `stable_id=gibtesnicht` aufrufen.
  Erwartung: HTTP 404, **zeichengleiche** Antwort wie in Prüfschritt B.
  Beide Antworten nebeneinander legen und vergleichen.
- Prüfschritt E (Entwurf): Die Seite auf Entwurf stellen und abgemeldet
  aufrufen. Erwartung: HTTP 404.
- Prüfschritt F (fremder Block): Die `stableId` eines Absatzes oder eines
  Blocks aus „Eigene WP Blocks" verwenden. Erwartung: HTTP 404.
- Log-Check: `…\fos\wp-content\debug.log` ohne neue Einträge.
- Regressionsrelevanz: `http://fos.localhost:8080/wp-json/cbd/v1/blocks` als
  Administrator aufrufen – der bestehende Endpunkt muss unverändert
  funktionieren. Abgemeldet aufrufen → muss weiterhin abgelehnt werden.

**Übergabenotiz:**

---

### AP-2.5: Block-Referenz öffnet ein Modal

**Status:** ☐ offen
**Umfang:** M
**Modell:** opus
**Abhängigkeiten:** AP-1.3, AP-2.4

**Ziel & Kontext:**
Ein Verweis auf einen Container-Block soll diesen in einem Overlay auf
derselben Seite zeigen, damit man ohne Seitenwechsel nachlesen kann.

**Entscheidung des Nutzers zur Reichweite:** Liegt der Zielblock auf
derselben Seite, wird er **aus dem DOM geklont** – kein Netzverkehr, keine
Autorisierung nötig. Nur wenn er dort nicht zu finden ist, wird er über den
Endpunkt aus AP-2.4 nachgeladen.

Nach AP-1.3 ist der Block editorfähig, kennt das Attribut `targetStableId`
und findet sein Ziel im Frontend über `[data-stable-id="…"]`. Der Sprunglink
funktioniert. Dieses AP ergänzt den Modal-Modus.

**Verbindliche Schnittstelle zum Endpunkt aus AP-2.4:**

```
GET /wp-json/cbd/v1/block-html?post_id=<int>&stable_id=<string>[&classroom=<int>&token=<string>]
→ 200: {"html": "<div class=\"cbd-container\">…</div>", "title": "…"}
→ 404: {"code":"cbd_block_not_available","message":"Der Block ist nicht verfügbar."}
```

Die Parameter `classroom` und `token` müssen mitgeschickt werden, wenn die
aktuelle Seite mit `?classroom=…&token=…` aufgerufen wurde – sonst kann der
Endpunkt die Klassensitzung nicht prüfen und lehnt zu Recht ab. Beide Werte
aus `window.location.search` übernehmen.

**Wiederverwendbare Muster – nichts neu erfinden:**

| Muster | Fundstelle | Was daraus zu übernehmen ist |
|---|---|---|
| Dialog mit Overlay | `CDB/assets/js/interactivity-store.js`, `showClassSelectorDialog()` ab ca. `:15` | Overlay-Element anlegen, an `document.body` hängen, Klick auf das Overlay schließt, Aufräumen per `removeChild`, Fokus auf das erste Bedienelement. **Achtung: Diese Funktion existiert in derselben Datei bereits zweimal (ca. `:15` und ca. `:640`). Lege keine dritte Kopie an – lies sie als Vorbild, schreibe deinen Code in `blocks/block-reference/view.js`.** |
| Overlay-Gestaltung | `CDB/assets/css/board-mode.css` ca. `:1108-1160` | `.cbd-behandelt-selector-dialog` (`position:fixed; inset:0; z-index:1000000; display:flex; align-items:center`), Overlay `rgba(0,0,0,.5)` mit `backdrop-filter: blur(4px)`, weiße Karte mit `border-radius:12px` und Einblend-Animation |
| Barrierefreiheit | `Theme/src/js/main.js` ab ca. `:18` (`buildOverlay`, `openLightbox`, `closeLightbox`) | `role="dialog"`, `aria-modal="true"`, `aria-label`, Schließen per Escape, das auslösende Element merken und den Fokus dorthin zurückgeben. **Nur lesen – das Theme wird nicht geändert.** |
| Z-Ordnung | `CDB/assets/css/board-mode.css` ca. `:1281` | Dort ist bereits geregelt, dass Glossar-Tooltip und -Seitenleiste über dem Tafelmodus liegen. Das neue Overlay tritt in dieselbe Konkurrenz – wähle den z-index bewusst und vermerke ihn in der Übergabenotiz |

**Betroffene Dateien:**
- `CDB/blocks/block-reference/block.json` (ändern)
- `CDB/blocks/block-reference/index.js` (ändern)
- `CDB/blocks/block-reference/render.php` (ändern)
- `CDB/blocks/block-reference/view.js` (ändern)
- `CDB/blocks/block-reference/style.css` (ändern)
- `CDB/blocks/block-reference/editor.css` (ändern)
- `CDB/includes/class-cbd-block-reference.php` (ändern)

**Vorgehen:**

1. **`block.json` – neues Attribut** `displayMode` (string, Vorgabe
   `"modal"`), zulässige Werte `"modal"` und `"link"`. Kein bestehendes
   Attribut entfernen.
2. **`index.js` – Umschalter im Editor.** Ein
   `wp.components.RadioControl` oder `SelectControl` „Verhalten beim Klick"
   mit den Einträgen „Als Modul öffnen (Standard)" und „Zum Block springen".
   Weiterhin ohne JSX und ohne `import` (siehe AP-1.3).
3. **`render.php` – Markup.**
   - Bei `displayMode === 'link'`: unverändertes Verhalten aus AP-1.3.
   - Bei `displayMode === 'modal'`: den Verweis weiterhin als `<a href="…">`
     mit der vollständigen Ziel-URL ausgeben – **nicht** als `<button>`.
     Grund: Ohne JavaScript funktioniert der Verweis dann immer noch als
     Sprung zur Zielseite (fortschreitende Verbesserung). Zusätzlich
     ausgeben: `data-display-mode="modal"`, `aria-haspopup="dialog"`,
     `data-target-stable-id`, `data-target-post`, `data-same-page`.
   - Alle Ausgaben maskieren.
4. **`view.js` – Modal-Logik.** Bei einem Klick auf einen Verweis mit
   `data-display-mode="modal"`:
   1. `event.preventDefault()`.
   2. **DOM-Pfad zuerst:** `document.querySelector('[data-stable-id="…"]')`.
      Gefunden → `cloneNode(true)` und in das Modal einsetzen.
      **Wichtig:** Vor dem Einsetzen im Klon alle `id`-Attribute entfernen
      oder mit einem Präfix versehen – sonst existiert jede ID zweimal auf
      der Seite, was Sprungmarken und `aria-controls`-Bezüge im Original
      zerstört.
   3. **Server-Pfad sonst:** `fetch()` auf den Endpunkt aus AP-2.4, mit
      `post_id`, `stable_id` und – falls in der aktuellen URL vorhanden –
      `classroom` und `token`. Währenddessen einen Ladehinweis im Modal
      zeigen. Bei HTTP 404 eine freundliche Meldung „Dieser Block ist nicht
      verfügbar." anzeigen, **nicht** die technische Antwort.
   4. Das Modal nach dem Muster oben aufbauen: `role="dialog"`,
      `aria-modal="true"`, Titel aus `title` bzw. dem Verweistext,
      Schließen-Knopf, Schließen per Escape und per Klick auf das Overlay,
      Fokus beim Öffnen in den Dialog und beim Schließen zurück auf den
      auslösenden Verweis.
   5. **Verschachtelung begrenzen:** Enthält der angezeigte Block selbst
      Block-Referenz-Verweise, dürfen diese **kein** weiteres Modal öffnen.
      Setze im Modalinhalt bei allen `[data-display-mode="modal"]` das
      Attribut auf `link`, sodass sie zum Sprunglink werden. Ein Modal im
      Modal ist damit ausgeschlossen.
   6. **Formeln nachrendern:** Nachdem der Inhalt im Modal steht, prüfen, ob
      `typeof window.cbdRenderLatex === 'function'` (die Funktion stammt aus
      AP-1.1), und sie mit dem Modalinhalt als Wurzel aufrufen. Sonst blieben
      Formeln im nachgeladenen Block ungerendert, und im geklonten Block
      wären sie zwar gerendert, aber der Klon trägt bereits
      `data-cbd-latex-rendered="1"` – das ist der gewünschte Fall, die
      Funktion tut dann nichts.
   7. Nur **ein** Modal gleichzeitig: Ist bereits eines offen, den Inhalt
      austauschen statt ein zweites anzulegen.
5. **`style.css` – Gestaltung** des Overlays nach dem Vorbild aus der Tabelle
   oben. Das Overlay muss auf schmalen Bildschirmen (≤480px) fast die volle
   Fläche einnehmen und der Inhalt senkrecht scrollbar sein.
6. **`editor.css`** – die Vorschaukarte im Editor kennzeichnet den
   Modal-Modus (z. B. ein kleiner Hinweistext).
7. **`class-cbd-block-reference.php`** – dem `viewScript` die nötigen Daten
   mitgeben: die REST-Wurzel (`rest_url('cbd/v1/block-html')`) und, für
   angemeldete Nutzer, `wp_create_nonce('wp_rest')`. Über
   `wp_localize_script()` bzw. `wp_add_inline_script()`. **Der Nonce ist
   keine Autorisierung** – er dient nur dazu, dass angemeldete Nutzer bei
   REST-Aufrufen als angemeldet erkannt werden. Die Autorisierung leistet
   ausschließlich AP-2.4.
8. `php tools/check-php74.php` ausführen und grün bekommen.

**Akzeptanzkriterien:**
- [ ] Ein Verweis mit `displayMode = 'modal'` auf einen Block **derselben**
      Seite öffnet ein Overlay mit dem Blockinhalt, **ohne** eine
      Netzwerkanfrage auszulösen (Nachweis im Netzwerk-Tab der
      Entwicklerwerkzeuge).
- [ ] Ein Verweis auf einen Block einer **anderen** Seite lädt den Inhalt
      über `cbd/v1/block-html` nach und zeigt ihn im Overlay.
- [ ] Der geklonte Block trägt keine `id`-Attribute, die im Original
      ebenfalls vorkommen.
- [ ] Das Overlay trägt `role="dialog"` und `aria-modal="true"`, schließt per
      Escape und per Klick auf den Hintergrund, und gibt den Fokus an den
      auslösenden Verweis zurück.
- [ ] Verweise **innerhalb** des Modals öffnen kein zweites Modal.
- [ ] Bei HTTP 404 erscheint eine verständliche Meldung, keine technische
      Fehlerausgabe.
- [ ] Ohne JavaScript funktioniert der Verweis weiterhin als Sprung zur
      Zielseite (Nachweis: JavaScript im Browser abschalten, Verweis klicken).
- [ ] Mit `displayMode = 'link'` verhält sich der Block wie nach AP-1.3.
- [ ] Formeln im Modalinhalt sind gesetzt, nicht als `$$…$$` sichtbar.
- [ ] Alle neuen `console.log` liegen hinter `window.cbdDebug`.
- [ ] `php tools/check-php74.php` meldet keinen Fehler.
- [ ] `CDB/reference_file_map.md` ist aktualisiert.

**Tests:**
- Smoke-Test: Seite mit Block-Referenz im Frontend laden – Konsole ohne
  Error, Verweis sichtbar.
- Prüfschritt A (gleiche Seite): Referenz und Ziel auf derselben Seite,
  Modus „Als Modul öffnen". Entwicklerwerkzeuge → Netzwerk öffnen, Verweis
  klicken. Erwartung: Overlay mit dem Blockinhalt, **keine** Anfrage an
  `block-html` im Netzwerk-Tab.
- Prüfschritt B (andere Seite): Referenz auf Seite A, Ziel auf Seite B.
  Verweis klicken. Erwartung: Overlay mit dem Inhalt aus Seite B, im
  Netzwerk-Tab eine Anfrage an `cbd/v1/block-html` mit HTTP 200. Die
  Adresszeile ändert sich nicht.
- Prüfschritt C (Tastatur): Overlay öffnen, `Tab` mehrfach drücken →
  der Fokus bleibt im Dialog. `Escape` drücken → Overlay schließt, der Fokus
  steht wieder auf dem Verweis.
- Prüfschritt D (Verschachtelung): Ein Zielblock, der selbst einen
  Block-Referenz-Verweis enthält. Modal öffnen, den inneren Verweis klicken.
  Erwartung: **kein** zweites Overlay; stattdessen Sprung bzw. Navigation.
- Prüfschritt E (gesperrte Seite): Die Zielseite auf „nur für Lehrpersonen"
  stellen, die Referenzseite nicht. Im privaten Browserfenster (abgemeldet)
  den Verweis klicken. Erwartung: Meldung „Dieser Block ist nicht
  verfügbar." – **kein** Inhalt, kein Seitentitel.
- Prüfschritt F (Formeln): Ein Zielblock mit einer Display-Formel. Modal
  öffnen. Erwartung: Die Formel ist gesetzt.
- Prüfschritt G (ohne JavaScript): JavaScript im Browser abschalten, Seite
  laden, Verweis klicken. Erwartung: Navigation zur Zielseite.
- Prüfschritt H (schmaler Bildschirm): Fenster auf 400px Breite verkleinern,
  Modal öffnen. Erwartung: Das Overlay füllt die Fläche, der Inhalt ist
  scrollbar, der Schließen-Knopf ist erreichbar.
- Log-Check: `…\fos\wp-content\debug.log` ohne neue Einträge.
- Regressionsrelevanz: Ein Block-Referenz-Block im Modus „Zum Block springen"
  muss sich unverändert wie nach AP-1.3 verhalten.

**Übergabenotiz:**

---

### AP-2.6: Screenshot-Knopf wird auf Apple-Geräten zum Einzelblock-PDF

**Status:** ☐ offen
**Umfang:** S
**Modell:** sonnet
**Abhängigkeiten:** AP-1.4

**Ziel & Kontext:**
Auf Apple-Geräten funktioniert der Screenshot-Weg nicht zuverlässig. Die
Ursachen liegen außerhalb unseres Einflusses (Safari verlangt den Aufruf von
`navigator.clipboard.write()` innerhalb der User-Aktivierung, die durch das
vorgelagerte `await html2canvas(...)` verloren geht; iOS begrenzt die
Canvas-Fläche; `<a download>` mit Data-URL wird von iOS-Safari ignoriert).

**Entscheidung des Nutzers:** nicht reparieren, sondern umleiten. Auf
Apple-Geräten wird der Screenshot-Knopf zum **Einzelblock-PDF-Knopf**. Der
Code dafür steht bereits fertig in `CDB/assets/js/interactivity-store.js`
ca. `:403-424` und ruft
`window.cbdPDFExportServerSide([$(mainContainer)], 'visual')` auf. Dieser Weg
erzeugt das PDF serverseitig über mPDF und kommt für reine DOM-Blöcke ohne
html2canvas aus – also ohne jede der genannten iOS-Klippen. Nutzer verlieren
dadurch keine Funktion.

**Warum clientseitig und nicht in PHP.** Der Knopf wird zwar serverseitig
erzeugt (`CDB/includes/class-cbd-block-registration.php` ca. `:1154-1168`,
gebunden an das Feature-Flag `screenshot`), aber eine Apple-Erkennung im
gerenderten HTML machte die Seitenausgabe vom User-Agent abhängig und
vergiftete damit jeden Full-Page-Cache. Das Plugin hat eigene Cache-Logik
(ca. `:420-446`). **`class-cbd-block-registration.php` wird in diesem AP
nicht angefasst.**

**Die vorhandene Geräteerkennung reicht nicht.** In
`CDB/assets/js/pdf-server-side.js` ca. `:26-27` steht:

```js
/iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1)
```

Das erkennt iOS und iPadOS, **nicht aber macOS-Safari** (ein Mac ohne
Touchscreen). Für „Apple gesamt" muss zusätzlich Safari auf macOS erkannt
werden: `navigator.vendor` enthält `Apple`, und der User-Agent enthält
`Safari`, aber weder `Chrome` noch `Chromium` noch `Edg` noch `OPR`.

**Betroffene Dateien:**
- `CDB/assets/js/interactivity-store.js` (ändern)
- `CDB/assets/js/interactivity-fallback.js` (ändern)

**Vorgehen:**

1. **Erkennungsfunktion.** In `interactivity-store.js` eine Funktion
   `istAppleGeraet()` ergänzen, die `true` liefert für:
   - iOS/iPadOS: `/iPad|iPhone|iPod/.test(navigator.userAgent)` oder
     `navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1`
   - macOS-Safari: `navigator.vendor` enthält `'Apple'` **und**
     `navigator.userAgent` enthält `'Safari'` **und** enthält **nicht**
     `'Chrome'`, `'Chromium'`, `'Edg'` oder `'OPR'`
   Das Ergebnis einmal berechnen und in einer Variablen halten.
2. **Umschaltung beim Initialisieren.** Im bestehenden Init-Callback des
   Containers (er ist über `data-wp-init` gebunden, siehe
   `class-cbd-block-registration.php` ca. `:983`) für jeden vorhandenen
   `.cbd-screenshot`-Knopf, sofern `istAppleGeraet()`:
   - Das Icon auf ein PDF-Symbol umstellen (Dashicon `dashicons-pdf` oder
     `dashicons-media-document`).
   - `title` und `aria-label` auf „Diesen Block als PDF speichern" setzen.
   - Ein Merkmal setzen, das die Aktion erkennt, z. B. das Attribut
     `data-cbd-apple-pdf="1"`.
   Der Knopf bleibt an sein Feature-Flag gebunden: Ist das Screenshot-Feature
   für das Design abgeschaltet, existiert der Knopf gar nicht und es passiert
   nichts. Das entspricht der Projektentscheidung „Buttons folgen
   Feature-Flags" (dokumentiert in `CDB/docs/VERBESSERUNGSPLAN.md`, AP12).
3. **Aktion umleiten.** Ganz am Anfang von `actions.createScreenshot`: Wenn
   `istAppleGeraet()`, statt des Screenshot-Ablaufs den vorhandenen
   Einzelblock-PDF-Weg auslösen – denselben Aufruf, der bei ca. `:403-424`
   bereits steht. Danach zurückkehren, ohne html2canvas anzufassen.
   Steht `window.cbdPDFExportServerSide` nicht zur Verfügung, den Knopf
   verstecken und `console.warn` ausgeben – dann ist ein Knopf ohne Funktion
   schlechter als keiner.
4. **`interactivity-fallback.js` gleichziehen.** Dieselbe Erkennung und
   dieselbe Umleitung im Callback-Stil. Die Datei wird auf aktuellen
   WordPress-Versionen zwar nicht geladen (siehe AP-1.4), muss aber
   inhaltlich gleich bleiben.
5. **Nichts an PHP ändern.** Weder `class-cbd-block-registration.php` noch
   eine CSS-Datei. Falls für das umgestellte Icon eine Regel nötig wird, in
   `assets/css/cbd-frontend-clean.css` – **aber nur, wenn AP-2.2 diese Datei
   bereits abgeschlossen hat.** Ist AP-2.2 noch offen, das Icon über den
   Dashicon-Klassentausch lösen, ohne CSS anzufassen; die beiden APs dürfen
   sich nicht in dieselbe Datei schreiben.

**Akzeptanzkriterien:**
- [ ] `istAppleGeraet()` liefert `true` für iPhone-, iPad- und
      macOS-Safari-User-Agents und `false` für Chrome unter Windows, Firefox
      unter Windows und **Chrome unter macOS**.
- [ ] Auf einem Apple-Gerät zeigt der Knopf ein PDF-Symbol und die
      Beschriftung „Diesen Block als PDF speichern".
- [ ] Ein Klick darauf löst den serverseitigen PDF-Export für genau diesen
      Block aus; html2canvas wird dabei nicht aufgerufen.
- [ ] Auf Nicht-Apple-Geräten ist das Verhalten unverändert gegenüber AP-1.4.
- [ ] Ist das Screenshot-Feature eines Designs abgeschaltet, existiert kein
      Knopf – weder Screenshot noch PDF.
- [ ] `class-cbd-block-registration.php` wurde **nicht** geändert.
- [ ] Alle neuen `console.log` liegen hinter `window.cbdDebug`.
- [ ] `CDB/reference_file_map.md` ist aktualisiert.

**Tests:**
- Smoke-Test: Seite mit Container-Block in Chrome unter Windows laden.
  Erwartung: Screenshot-Knopf wie gewohnt, Konsole ohne Error.
- Prüfschritt A (Erkennung ohne Apple-Gerät): In den Entwicklerwerkzeugen
  von Chrome die Geräteemulation auf „iPhone" stellen und die Seite neu
  laden. Erwartung: Der Knopf zeigt das PDF-Symbol. Emulation zurückstellen,
  neu laden → Screenshot-Symbol.
- Prüfschritt B (Erkennungslogik direkt): In der Konsole die vier
  User-Agent-Fälle gegen die Funktion prüfen, sofern sie erreichbar ist;
  andernfalls die Bedingung von Hand mit den vier Zeichenketten nachrechnen
  und das Ergebnis ins Testprotokoll schreiben:
  iPhone-UA → `true`; iPad-UA (`MacIntel` + `maxTouchPoints > 1`) → `true`;
  macOS-Safari-UA (`vendor` „Apple Computer, Inc.", UA mit `Safari`, ohne
  `Chrome`) → `true`; macOS-Chrome-UA (`vendor` „Google Inc.", UA mit
  `Chrome`) → `false`.
- Prüfschritt C (PDF-Weg): In der Geräteemulation „iPad" den Knopf klicken.
  Erwartung: Der serverseitige PDF-Export läuft an (im Netzwerk-Tab eine
  Anfrage an `cbd/v1/generate-pdf` oder `admin-ajax.php?action=cbd_generate_pdf`),
  am Ende steht eine PDF-Datei bereit. **Keine** Anfrage an html2canvas-Logik.
- Prüfschritt D (Feature abgeschaltet): Bei einem Design das
  Screenshot-Feature abschalten und die Seite in der iPhone-Emulation laden.
  Erwartung: kein Knopf.
- Prüfschritt E (echtes Gerät, falls vorhanden): Auf einem iPhone oder iPad
  im selben Netz `http://<Rechnername>:8080/` aufrufen und den Knopf testen.
  Ist kein Gerät verfügbar, das im Testprotokoll als „nicht geprüft"
  vermerken.
- Regressionsrelevanz: Der Screenshot unter Chrome/Windows muss weiterhin
  funktionieren (Ergebnis aus AP-1.4).

**Übergabenotiz:**

---

### AP-2.7: Abnahme Phase 2 auf dem Testserver

**Status:** ☐ offen
**Umfang:** M
**Modell:** sonnet
**Abhängigkeiten:** AP-2.1, AP-2.2, AP-2.3, AP-2.4, AP-2.5, AP-2.6

**Ziel & Kontext:**
Integrationstest der Phase 2 und Erzeugung der Verteilungspakete. Nach
diesem AP sind alle vier Vorhaben des Plans umgesetzt und lieferbar.

**Betroffene Dateien:**
- `CDB/container-block-designer.php` (ändern – Version, **nur hier**)
- Verteilungspakete in `CDB/dist/`

**Vorgehen:**

1. Sicherstellen, dass der Branch `phase-2-funktionen` alle APs enthält und
   gepusht ist. „Eigene WP Blocks" ist in dieser Phase nicht betroffen.
2. `php tools/check-php74.php` ausführen – muss grün sein.
3. Alle Prüfharnische ausführen und die Ergebnisse notieren:
   `php tools/test-icon-library.php`, `test-icon-value.php`,
   `test-icon-scale.php`, `test-icon-manager.php`, `test-icon-position.php`,
   `test-svg-sanitizer.php`, `test-block-serializer.php`,
   `test-design-transfer.php`, `test-classroom-gate.php`,
   `test-latex-parser.php`, `test-block-content-api.php`. **Alle müssen
   bestehen.**
4. `node create-plugin-zip.js` ausführen. Nie manuell zippen (siehe AP-1.5,
   Schritt 5).
5. Das ZIP entpacken und den Autoloader prüfen:
   `php -r "define('ABSPATH','/'); require '<entpackt>/vendor/autoload.php'; echo 'ok';"`.
6. Prüfen, dass `blocks/block-reference/` und
   `includes/class-cbd-block-content-api.php` im ZIP enthalten sind.
7. Das ZIP auf dem Testserver installieren (vorhandenes überschreiben) und
   die Versionsanzeige prüfen.
8. `debug.log` leeren, dann die Integrationsprüfungen durchführen.
9. **Ausrollreihenfolge für die Produktivinstallation dokumentieren** – in
   die Übergabenotiz und in AP-2.doc: erst `accordion.zip` aus „Eigene WP
   Blocks" (aus Phase 1), dann das CDB-Plugin-ZIP.

**Akzeptanzkriterien:**
- [ ] `php tools/check-php74.php` meldet keinen Fehler.
- [ ] Alle elf Prüfharnische aus Schritt 3 bestehen.
- [ ] Das entpackte ZIP lädt seinen Autoloader ohne Fatal Error.
- [ ] Das ZIP enthält `blocks/block-reference/` und
      `includes/class-cbd-block-content-api.php`.
- [ ] Alle Integrationsprüfungen bestanden.
- [ ] `…\fos\wp-content\debug.log` ohne neue Notices, Warnings,
      Deprecations oder Fatal Errors.
- [ ] Der lauffähige Endzustand der Phase 2 (Abschnitt 6) ist erreicht.

**Tests:**
- **Integration 1 – Modal mit Formel und Icon:** Ein Block-Design mit Icon in
  der Position „Container, rechts oben" und Versatz 10/10 anlegen. Einen
  Container-Block dieses Designs auf Seite B anlegen, der eine Display-Formel
  und ein Accordion enthält. Auf Seite A einen Block-Referenz-Block im
  Modal-Modus darauf zeigen lassen. Erwartung beim Klick: Overlay öffnet
  sich, Icon sitzt rechts oben mit Versatz, Formel ist gesetzt, das Accordion
  im Modal lässt sich öffnen und die Formel darin bleibt korrekt.
- **Integration 2 – Modal auf gesperrter Zielseite:** Seite B auf „nur für
  Lehrpersonen" stellen. Im privaten Fenster (abgemeldet) Seite A aufrufen
  und den Verweis klicken. Erwartung: Meldung „Dieser Block ist nicht
  verfügbar.", kein Inhalt, kein Titel. Angemeldet dagegen: Inhalt erscheint.
- **Integration 3 – Apple-Weiche:** In der iPhone-Emulation eine Seite mit
  Container-Block laden. Erwartung: PDF-Symbol statt Screenshot-Symbol; Klick
  erzeugt ein PDF.
- **Integration 4 – Icon-Vorschau bis ins Frontend:** Im Admin-Formular
  Position und Versatz eines Designs ändern, speichern, Frontend neu laden.
  Erwartung: Die Darstellung im Frontend entspricht der Vorschau im Formular.
- **Regression 1 – Phase 1 unbeschädigt:** Alle vier Integrationsprüfungen
  aus AP-1.5 wiederholen (Formel im Accordion, Accordion im Container,
  Block-Referenz als Sprunglink, Screenshot unter Chrome).
- **Regression 2 – Bestandsdesigns:** Eine Seite mit einem Design, das nie
  angefasst wurde, im Frontend aufrufen. Erwartung: Icon steht unverändert
  neben dem Titel.
- **Regression 3 – Design-Export/-Import:** Über Container Designer →
  Export/Import ein Design als Markdown exportieren, die Datei ansehen
  (die neuen Schlüssel `icon.position`, `icon.offsetX`, `icon.offsetY` müssen
  darin stehen) und wieder importieren. Erwartung: Rundlauf ohne Verlust.
- **Regression 4 – Klassenansicht:** Sofern eine Klasse eingerichtet ist,
  eine Seite mit `?classroom=<id>&token=<token>` aufrufen. Erwartung:
  unveränderte Klassenansicht. Sonst als „nicht prüfbar" vermerken.
- **Regression 5 – Seitenimport:** Container Designer → Seiten importieren
  mit einer kleinen Markdown-Datei, die eine LaTeX-Formel mit Backslash
  enthält (z. B. `$\cdot$`). Erwartung: Die Seite entsteht und der Backslash
  ist erhalten.

**Übergabenotiz:**

---

### AP-2.rev: Unabhängiges Review Phase 2

**Status:** ☐ offen
**Umfang:** M
**Modell:** opus
**Abhängigkeiten:** AP-2.1, AP-2.2, AP-2.3, AP-2.4, AP-2.5, AP-2.6, AP-2.7

**Ziel & Kontext:**
Unabhängige Qualitätsprüfung der Phase 2 durch einen Agenten, der an keiner
Implementierung beteiligt war. **Nur lesend arbeiten** – KEINE Datei
verändern.

**Vorgehen:**
1. Für jedes Implementierungs-AP der Phase (AP-2.1 bis AP-2.7): Code gegen
   dessen Akzeptanzkriterien prüfen. Stichproben im Quelltext nehmen.
2. **Schwerpunkt: der REST-Endpunkt aus AP-2.4.** Er ist das
   sicherheitskritischste Ergebnis des Vorhabens. Prüfe zeilenweise:
   - Wird `nocache_headers()` in **jedem** Antwortpfad aufgerufen, auch bei
     jeder Ablehnung?
   - Ist jede Ablehnung zeichengleich – gleicher Code, gleiche Nachricht,
     gleicher HTTP-Status? Gibt es einen Pfad, der mehr verrät (z. B. eine
     abweichende Meldung bei „Seite existiert nicht")?
   - Kann eine Antwort jemals Seitentitel, Permalink oder Slug enthalten?
   - Ist jeder Aufruf einer Theme-Funktion mit `function_exists()`
     abgesichert? Was passiert, wenn das Theme fehlt – wird dann abgelehnt
     oder durchgelassen? (Durchlassen ist hier korrekt, weil es dann keine
     Sperre gibt – prüfe, ob die Begründung im Code steht.)
   - Wird `simple_clean_seite_sichtbar()` verwendet und **nicht**
     `simple_clean_seite_nur_lehrpersonen()`? Letztere kennt den
     Klassen-Durchlass nicht.
   - Hängt die Rechteprüfung an `post_id` und wird `stable_id` nur zum Finden
     benutzt? Eine Suche allein nach `stable_id` wäre wegen möglicher
     Kollisionen nach `CBD_Block_Organizer::copy_block()` ein Fehler.
   - Wird ausschließlich der Namensraum `container-block-designer/`
     ausgeliefert?
   - Wird mit `render_block()` gerendert, nicht mit `do_blocks()` oder
     `serialize_blocks()`?
3. **Prüfe die Nichtvermehrung geteilter Logik.** Zähle die Fundstellen von
   `data-stable-id` und des Suffix-Musters `:pN` in `includes/`. Sie dürfen
   gegenüber dem Stand vor der Phase nicht zugenommen haben. Der vorhandene
   Prüfharnisch `tools/test-classroom-gate.php` enthält eine Prüfung, die
   anschlägt, sobald die Suffix-Regel ein zweites Mal auftaucht – prüfe, ob
   sie noch greift.
4. Phasen-Endzustand prüfen (Abschnitt 6).
5. Scope-Check gegen Abschnitt 2 (Nicht-Ziele). Besonders:
   - Wurde am Theme etwas geändert?
   - Wurde `class-cbd-style-loader.php` erweitert? Das war ausdrücklich
     untersagt (AP-2.2, Schritt 5).
   - Wurde eine tote CSS-Datei angefasst?
   - Wurde eine Versionsnummer außerhalb von AP-2.7 erhöht?
   - Hat ein anderes AP als AP-2.4 `container-block-designer.php` geändert?
6. Qualitäts-Check:
   - PHP-8.0-Syntax im CDB-Designer (`match(`, `?->`, `str_contains`,
     `str_starts_with`, `str_ends_with`, Union Types)?
   - Sind alle Ausgaben maskiert?
   - Wird im Modal fremdes HTML per `innerHTML` eingesetzt? Das ist der
     vorgesehene Weg, aber prüfe, dass der Inhalt ausschließlich aus
     `render_block()` eigener Container stammt und nie aus einer
     Nutzereingabe.
   - Liegen neue `console.log` hinter `window.cbdDebug`?
   - Wurde in `interactivity-store.js` eine dritte Kopie von
     `showClassSelectorDialog()` angelegt?
7. Befunde als Bericht in die Übergabenotiz: je Befund Schweregrad
   (kritisch / mittel / gering), betroffenes AP, Datei und Fundstelle.

**Akzeptanzkriterien:**
- [ ] Jedes Implementierungs-AP der Phase wurde gegen seine
      Akzeptanzkriterien geprüft.
- [ ] Der REST-Endpunkt wurde gegen alle neun Punkte aus Schritt 2 geprüft
      und das Ergebnis je Punkt dokumentiert.
- [ ] Alle Befunde mit Schweregrad, Datei und Fundstelle dokumentiert.
- [ ] Keine Datei wurde verändert.

**Tests:**
- entfällt (Review-AP; das Ergebnis ist der Bericht).

**Übergabenotiz:**

---

### AP-2.doc: Dokumentation Phase 2 und Projektabschluss

**Status:** ☐ offen
**Umfang:** M
**Modell:** sonnet
**Abhängigkeiten:** AP-2.rev

**Ziel & Kontext:**
Die Dokumentation auf den Endstand bringen. Bestehende Doku-Konventionen
erweitern, keine Parallelstruktur aufbauen.

**Betroffene Dateien:**
- `CDB/CLAUDE.md` (ändern)
- `CDB/reference_file_map.md` (ändern)
- `DOKUMENTATION.md` im Projektstamm (ändern)
- `CLAUDE.md` im Projektstamm (ändern)
- Dieser Plan (ändern – Abschnitt 11 ergänzen)

**Vorgehen:**
1. Alle Übergabenotizen der Phase 2 durchgehen.
2. In `CDB/CLAUDE.md` drei neue Abschnitte anlegen, im Stil der bestehenden
   (mit Unterabschnitten, Tabellen und einem Absatz „Prüfharnisch"):
   - **„Icon-Position: Kopfzeile oder Container-Ecke"** – die fünf
     Positionswerte, die drei flachen Schlüssel im `features`-JSON, die sechs
     Funktionen mit Signatur, und **warum der Standardwert `header` heißt und
     die Altwerte darauf zurückfallen** (sonst verlöre jedes Bestandsdesign
     sein Icon aus der Kopfzeile). Dazu der Hinweis auf die zwei
     CSS-Variablen `--cbd-icon-dx`/`--cbd-icon-dy` und **warum kein fertiges
     `transform` gesetzt wird** (drei Breakpoints mit je eigenem Basiswert).
     Ergänze die bestehende Fundstellen-Karte „Icon-Größen: wo sie stehen"
     um die Positionierung.
   - **„Block-Referenz als Modul"** – Attribute, die zwei Wege (DOM-Klon
     zuerst, Server-Abruf sonst), die vollständige Beschreibung des
     Endpunkts `cbd/v1/block-html` samt Parametern und Antwortformat, und
     **die Autorisierungskette in ihrer Reihenfolge**. Ausdrücklich
     festhalten: Ablehnung und Nichtexistenz antworten gleich, und warum.
     Vermerken, dass dies neben `page-manager`, dem Filter
     `simple_clean_lehrerseite_freigeben` und dem LaTeX-Renderer die nächste
     Naht zwischen Komponenten ist.
   - **„Screenshot auf Apple-Geräten"** – dass der Knopf dort zum
     Einzelblock-PDF wird, warum die Umschaltung clientseitig geschieht
     (Cache), und dass die Erkennung macOS-Safari einschließt, die ältere
     Erkennung in `pdf-server-side.js` dagegen nicht.
3. In `CDB/reference_file_map.md` die neuen Dateien aufnehmen
   (`includes/class-cbd-block-content-api.php`,
   `tools/test-icon-position.php`, `tools/test-block-content-api.php`,
   `tools/test-latex-parser.php`) und die geänderten Zeilen aktualisieren.
   Im Abschnitt „Blöcke (`blocks/`)" die Beschreibung des Block-Referenz-
   Blocks auf den neuen Stand bringen.
4. In `CLAUDE.md` im Projektstamm den Abschnitt zur Plugin-Kompatibilität
   ergänzen: Der LaTeX-Renderer des CDB-Designers wird jetzt vom
   Accordion-Block aus „Eigene WP Blocks" aufgerufen – eine **dritte**
   Stelle, an der die Komponenten zusammenwirken. Einseitig optional
   (`typeof`-Prüfung).
5. In `DOKUMENTATION.md` im Projektstamm den Eintrag zu diesem Vorhaben
   vervollständigen (aus AP-1.doc), mit Verweis auf diesen Plan und die
   wichtigsten Erkenntnisse.
6. **Abschnitt 11 „Rückblick" in diesem Plan anlegen** – nach dem Vorbild der
   anderen Pläne des Projekts (`docs/PLAN-Seitenimport.md`,
   `Theme/docs/PLAN-Seitenindex.md`, `Theme/docs/PLAN-Lehrerseiten.md`
   haben alle einen solchen Abschnitt). Festhalten:
   - Welche Annahmen der Planung sich als falsch erwiesen haben.
   - Was während der Umsetzung überraschend war.
   - Welche Befunde aus den beiden Review-APs nicht behoben, sondern als
     bekannte Einschränkung übernommen wurden.
   - Die **Ausrollreihenfolge** für die Produktivinstallation: erst
     `accordion.zip`, dann das CDB-Plugin-ZIP.
   - Die bewusst liegen gelassenen offenen Punkte aus Abschnitt 2
     (tote Dateien, doppelte Admin-Formulare, Editor zeigt kein Icon).
7. „Stand"-Datum in den Datei-Maps aktualisieren.

**Akzeptanzkriterien:**
- [ ] Jede in Phase 2 neue oder geänderte Datei hat eine aktuelle Zeile in
      `CDB/reference_file_map.md`.
- [ ] `CDB/CLAUDE.md` enthält die drei neuen Abschnitte.
- [ ] Der Endpunkt `cbd/v1/block-html` ist so beschrieben, dass ein fremder
      Entwickler ihn ohne Blick in den Quelltext aufrufen könnte – inklusive
      der Autorisierungskette.
- [ ] Die Wurzel-`CLAUDE.md` nennt den LaTeX-Renderer als dritte Naht
      zwischen den Komponenten.
- [ ] Abschnitt 11 dieses Plans ist ausgefüllt und enthält die
      Ausrollreihenfolge.
- [ ] Kein Verweis in der Dokumentation zeigt auf nicht mehr existierende
      Dateien oder Funktionen.

**Tests:**
- Stichprobe: Drei zufällige Zeilen der Datei-Map gegen den echten
  Dateiinhalt prüfen.
- Prüfschritt: Jede in der Dokumentation genannte Funktionssignatur gegen
  den Quelltext prüfen (Name und Parameterzahl stimmen).
- Prüfschritt: Jeden neu gesetzten Dateiverweis öffnen – die Datei existiert.

**Übergabenotiz:**

## 8. Status

Wird während der Ausführung gepflegt.
Legende: ☐ offen · ◐ in Arbeit · ☑ erledigt · ✗ blockiert

| AP | Titel | Modell | Status | Abhängig von | Notiz |
|---|---|---|---|---|---|
| AP-1.0 | Accordion-Branch nach `main` mergen | sonnet | ☑ | – | Merge-Commit `626c6f8` auf `main`, Tag `pre-latex-merge` (= alter Stand `f6826a5`) lokal+remote, Branch `phase-1-latex-accordion` angelegt. **AK1 war fehlerhaft formuliert und wurde korrigiert** (siehe AP-1.0). **Befund: Den jsdom-Prüfharnisch mit „104 Zusicherungen" aus `PLAN-accordion-block.md` AP-2.3n gibt es nicht** — nie committet, in der ganzen Historie nicht auffindbar |
| AP-1.1 | LaTeX-Renderer öffnen und Parser härten | opus | ☑ | – | Commit `70c77bc`. `tools/test-latex-parser.php` neu, 78 Prüfungen grün. **Zusatzbefund:** Auf Priorität 11 laufen `wpautop`/`wptexturize` vorher und tragen `<br />` und Entities in die Formeln — deshalb neu `normalize_formula_text()`. Zwei Bestandsfehler in `latex-formulas.css` gefunden, bewusst nicht behoben (siehe Übergabenotiz) |
| AP-1.2 | Accordion verliert keine Textknoten mehr | opus | ☐ | AP-1.0 | entsperrt; Branch `phase-1-latex-accordion` |
| AP-1.3 | Block-Referenz editorfähig, auf `stableId` | opus | ◐ | – | 1. und 2. Anlauf 2026-08-16 je durch Sitzungslimit abgebrochen. **Teilstand im Arbeitsverzeichnis, nicht committet:** alle sieben Zieldateien geändert (~830 Zeilen), Abbruch beim Eingrenzen der `[id]`-Regel in `style.css`. **Ungeprüft** — weder `php -l` noch `check-php74.php` noch Akzeptanzkriterien gelaufen |
| AP-1.4 | Screenshot liefert wieder eine Datei | sonnet | ☑ | – | Commit `aa98770`. `yield*` gesetzt, `interactivity-fallback.js` nachgezogen (dort fehlten Canvas-Deckel und `backgroundColor` ganz). Browserprüfungen an AP-1.5 verwiesen |
| AP-1.5 | Abnahme Phase 1 auf dem Testserver | sonnet | ☐ | AP-1.0–AP-1.4 | einzige Stelle mit Versionsbump |
| AP-1.rev | Unabhängiges Review Phase 1 | opus | ☐ | AP-1.0–AP-1.5 | nur lesend |
| AP-1.doc | Dokumentation Phase 1 | sonnet | ☐ | AP-1.rev | Merge in `main` |
| AP-2.1 | Datenmodell und Sanitizer Icon-Position | sonnet | ☐ | – | TDD; parallel zu 2.4, 2.6 |
| AP-2.2 | Icon-Position im Frontend rendern | opus | ☐ | AP-2.1 | parallel zu 2.3, 2.5 |
| AP-2.3 | Icon-Position im Admin-Formular | sonnet | ☐ | AP-2.1 | parallel zu 2.2, 2.5 |
| AP-2.4 | REST-Endpunkt `cbd/v1/block-html` | opus | ☐ | – | sicherheitskritisch; parallel zu 2.1, 2.6 |
| AP-2.5 | Block-Referenz öffnet ein Modal | opus | ☐ | AP-1.3, AP-2.4 | parallel zu 2.2, 2.3 |
| AP-2.6 | Screenshot auf Apple → Einzelblock-PDF | sonnet | ☐ | AP-1.4 | parallel zu 2.1, 2.4 |
| AP-2.7 | Abnahme Phase 2 auf dem Testserver | sonnet | ☐ | AP-2.1–AP-2.6 | einzige Stelle mit Versionsbump |
| AP-2.rev | Unabhängiges Review Phase 2 | opus | ☐ | AP-2.1–AP-2.7 | nur lesend; Schwerpunkt AP-2.4 |
| AP-2.doc | Dokumentation Phase 2 und Abschluss | sonnet | ☐ | AP-2.rev | Merge in `main`, Abschnitt 11 |

## 9. Testprotokoll

Wird während der Ausführung gepflegt. Ein Eintrag pro abgeschlossenem AP und
pro Phasenabschluss.

| Datum | AP / Phase | Getestet | Ergebnis | Getestet von |
|---|---|---|---|---|
| 2026-08-16 | AP-1.0 | `npm run build` (webpack 5.102.0); Merge konfliktfrei, 8 Dateien; Baumvergleich `git diff --stat main phase-1-accordion-grundlage`; Tag und Branch lokal+remote | bestanden. **Prüfharnisch nicht ausführbar** — die jsdom-Testumgebung aus AP-2.3n des Accordion-Plans existiert im Repo nicht und findet sich auch in der Historie nicht. Merge trotzdem ausgeführt (ausdrückliche Nutzerentscheidung) | AP-1.0-Agent, nachgeprüft durch Orchestrator |
| 2026-08-16 | AP-1.1 | `php tools/test-latex-parser.php` (78 Prüfungen); `php -l`; `php tools/check-php74.php` (562 Dateien); `node --check assets/js/latex-renderer.js`; zusätzlich 28 jsdom-Zusicherungen zum API-Vertrag | alle bestanden, Exit 0. Browserprüfungen (Absatz nicht zerrissen, Konsolen-Rundlauf, `debug.log`) **offen → AP-1.5** | AP-1.1-Agent, Harnisch und `php -l` vom Orchestrator nachgefahren |
| | AP-1.2 | | | |
| | AP-1.3 | | | |
| 2026-08-16 | AP-1.4 | `node --input-type=module --check` (store), `node --check` (fallback); Grep-Belege für `yield*` und den entfernten Icon-Selektor | bestanden, Exit 0. Browserprüfungen (Zwischenablage-Fehlschlag erzwingen, Warn-Icon, langer Block) **offen → AP-1.5** | AP-1.4-Agent, Syntaxprüfung und Grep vom Orchestrator nachgefahren |
| | AP-1.5 | | | |
| | **Phase 1 abgeschlossen** | | | |
| | AP-1.rev | | | |
| | AP-1.doc | | | |
| | AP-2.1 | | | |
| | AP-2.2 | | | |
| | AP-2.3 | | | |
| | AP-2.4 | | | |
| | AP-2.5 | | | |
| | AP-2.6 | | | |
| | AP-2.7 | | | |
| | **Phase 2 abgeschlossen** | | | |
| | AP-2.rev | | | |
| | AP-2.doc | | | |

## 10. Dokumentation

Das Projekt hat eine gewachsene Doku-Konvention. **Diese wird erweitert, es
wird keine Parallelstruktur aufgebaut.**

- **Architektur- und Arbeitsdoku je Komponente:**
  `Plugins/CDB-Designer/CLAUDE.md`,
  `Plugins/Eigene WP Blocks/CLAUDE.md`,
  `CLAUDE.md` im Projektstamm.
  Hier stehen die fachlichen Details, Warnabschnitte und bekannten Fallen.
  Wird in AP-1.doc und AP-2.doc fortgeschrieben.
- **Datei-Maps:** `Plugins/CDB-Designer/reference_file_map.md` und
  `Plugins/Eigene WP Blocks/reference_file_map.md` – tabellarische Übersicht
  (Datei | Zweck | wichtige Funktionen | hängt ab von). **Jedes AP, das
  Dateien anlegt oder wesentlich ändert, pflegt die betroffenen Zeilen
  selbst** (Regel 13 in Abschnitt 0), nicht erst das Dokumentations-AP.
- **Wegweiser:** `DOKUMENTATION.md` im Projektstamm – sagt, wo welche
  Dokumentation liegt. Bekommt einen Eintrag zu diesem Vorhaben.
- **Dieser Plan:** `Plugins/CDB-Designer/docs/PLAN-Vier-Erweiterungen.md` –
  Statustabelle, Testprotokoll und ab AP-2.doc ein Abschnitt 11 „Rückblick"
  nach dem Vorbild der übrigen Pläne des Projekts.

**Eine eigene `DOKUMENTATION.md` mit Architektur- und Datenmodellkapiteln
wird bewusst NICHT angelegt.** Diese Rolle erfüllen im Projekt die
`CLAUDE.md`-Dateien der Komponenten; eine zweite Struktur daneben würde
sofort auseinanderlaufen.