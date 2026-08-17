# Projektplan: Blockreferenz als Textformat + hierarchische Zielauswahl

_Erstellt am: 2026-08-17 · Letzte Aktualisierung: 2026-08-17 · **Planung abgeschlossen, Umsetzung offen**_

Fortsetzung von `docs/PLAN-Vier-Erweiterungen.md` (Phasen 1 und 2, Version
3.1.91). Die Nummerierung läuft deshalb mit **Phase 3 und 4** weiter — so
bleibt jede AP-ID im Git-Log dieses Repositorys eindeutig.

## 0. Anweisungen für den ausführenden Agenten

Du arbeitest nach diesem Plan. Er ist die einzige Wahrheitsquelle – du hast
keinen Zugriff auf das Gespräch, in dem er entstand. Halte dich an diese Regeln:

**Rollen und Modelle:**

A. Wird die Abarbeitung von einem Orchestrator koordiniert (Opus), gilt:
   Der Orchestrator delegiert APs an Subagenten und implementiert NIEMALS
   selbst. Er gibt jedem Subagenten nur dessen AP-Text plus die Abschnitte
   0–5 dieses Plans als Kontext, prüft jede Rückmeldung gegen die
   Akzeptanzkriterien des APs, bevor er abhängige APs freigibt, und pflegt
   die Statustabelle **selbst** — kein Subagent schreibt in Abschnitt 8, 9
   oder in `reference_file_map.md`, damit parallele APs sich nicht in
   dieselbe Zeile schreiben. Wer den Agenten diese Pflicht abnimmt, muss sie
   sich selbst aufschreiben (Lehre aus Phase 1/2, siehe dort Abschnitt 11).
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
3. Melde dem Orchestrator, dass du beginnst (Status ◐ setzt er).
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
8. Ergebnis in die Übergabenotiz; der Orchestrator trägt es ins
   Testprotokoll (Abschnitt 9) ein.
9. Bei Fehlschlag: Ursache in die Übergabenotiz, nicht mit abhängigen APs
   weitermachen.
10. Nach dem letzten Implementierungs-AP einer Phase zusätzlich:
    Integrationstest der Phase + Regressionscheck der vorherigen Phasen.
11. Danach folgt das Review-AP (`AP-<N>.rev`): Es wird von einem frischen
    Agenten ausgeführt, der KEINES der APs dieser Phase implementiert hat.
    Der Review-Agent arbeitet ausschließlich lesend und verändert keine
    Datei. Kritische Befunde führen zu Korrektur-APs (siehe Regel 15).

**Übergabe:**

12. Fülle die Übergabenotiz deines APs aus: was geändert wurde, getroffene
    Entscheidungen, was für Folge-APs relevant ist. Nenne dabei **jede**
    angelegte, verschobene oder wesentlich geänderte Datei mit einem Satz
    Zweckbeschreibung — der Orchestrator zieht daraus die
    `reference_file_map.md` nach.
13. Git: mindestens ein Commit mit AP-ID im Text, z. B.
    `AP-3.2: Gemeinsamer Auswahlbaustein für Zielblöcke`. Nach jedem
    abgeschlossenen AP den Phasen-Branch pushen. Phasen-Branches erst nach
    bestandenem Integrationstest UND Review in `main` mergen.
14. **Keine Anführungszeichen in Commit-Nachrichten.** Die Shell dieses
    Projekts (PowerShell) übergibt den Text sonst als Pathspec und der
    Commit scheitert. Anführungszeichen durch Bindestriche oder Weglassen
    ersetzen.

**Umplanung:**

15. Zeigt sich, dass der Plan nicht trägt, werden Korrektur-APs mit
    fortlaufender Nummer ergänzt (`AP-<N>.fix1`, …) und in Statustabelle und
    Testprotokoll aufgenommen. Bestehende APs und Übergabenotizen werden nie
    gelöscht, nur ergänzt.

**Projektspezifische Pflichtregeln – diese gelten für JEDES AP:**

16. **PHP 7.4 im CDB-Designer.** Die Zielumgebung ist PHP 7.4.33, lokal läuft
    PHP 8.x. `php -l` meldet 8.0-Syntax NICHT als Fehler. Nach jeder Änderung
    an einer PHP-Datei zwingend `php tools/check-php74.php` im
    Plugin-Verzeichnis ausführen und grün bekommen. Verboten: `match`,
    Nullsafe `?->`, Constructor Promotion, benannte Argumente,
    `str_contains`, `str_starts_with`, `str_ends_with`, `mixed`, Union Types.
    Erlaubt: `??`, `??=`, Arrow Functions, typisierte Properties.
17. **Kein Build-Schritt.** Der CDB-Designer liefert jede JS-Datei
    **unverändert** an den Browser aus. Kein JSX, kein `import`/`export`,
    keine Klassenfelder. Hausstil ist ES5 mit `var`/`function` und IIFE,
    Zugriff über `wp.*`-Globale, UI über `wp.element.createElement`. Es gibt
    keine `index.asset.php` — Abhängigkeiten werden von Hand deklariert.
18. **Keine CDN-Einbindungen.** Projektentscheidung (DSGVO).
19. **Debug-Ausgaben gaten.** JavaScript: `console.log` nur hinter
    `window.cbdDebug`. `console.error`/`console.warn` dürfen ungegatet bleiben.
    PHP: Informations-Logs hinter `if (defined('WP_DEBUG') && WP_DEBUG)`.
20. **Tote Dateien nicht anfassen.** `assets/css/frontend.css`,
    `assets/css/frontend-positioning.css`, `assets/css/unified-frontend.css`
    und alle `*.js.backup` sind in keinem `wp_enqueue_*()` referenziert und
    enthalten dieselben Selektoren wie die lebenden Dateien. Lebende
    Frontend-Datei ist `assets/css/cbd-frontend-clean.css`.
    `includes/class-cbd-style-loader.php` schreibt gegen ein nicht
    existierendes Präfix `.cbd-container-<slug>` — dort nichts ergänzen.
21. **`wp_unslash()` vor jedem `json_decode()`** von `$_POST`-Daten.
22. **`wp_slash()` vor `wp_insert_post()`/`wp_update_post()`.** Ohne die
    Maskierung entfernt WordPress jeden Backslash – jede LaTeX-Formel wäre
    still zerstört (`\cdot` wird zu `cdot`).
23. **Keine Versionsnummer erhöhen.** Weder in
    `container-block-designer.php` noch sonstwo. Das geschieht ausschließlich
    im Abnahme-AP (AP-4.3). Sonst kollidieren parallel laufende APs in
    derselben Zeile.
24. **Keine PowerShell-Lese-Schreib-Zyklen auf Markdown-Dateien.**
    `Get-Content` + `Set-Content -Encoding UTF8` doppelkodiert alle Umlaute
    (Mojibake) und ein Latin-1-„Reparaturversuch" verliert Zeichen
    unwiederbringlich. Für Änderungen an `*.md` das Edit-Werkzeug verwenden.

---

## 1. Projektziel

Zwei zusammenhängende Erweiterungen der Blockreferenz:

1. **Blockreferenz als Textformat.** Neben dem bestehenden Gutenberg-Block
   „Block-Referenz" soll ein Verweis auch **inmitten eines Textes** gesetzt
   werden können — Text markieren, Schaltfläche neben dem Link-Knopf in der
   RichText-Werkzeugleiste anklicken, im Dialog ein Ziel wählen. Der
   markierte Text wird zu einem Link, der den Zielblock als Modal auf
   derselben Seite öffnet. Zweck: flüssigeres Lernen — im Lesefluss
   nachschlagen, ohne die Seite zu verlassen.
2. **Hierarchische Zielauswahl an beiden Stellen.** Die Zielsuche filtert
   nach der Seitenhierarchie: erst die oberste Ebene, dann erscheint ein
   zweites Auswahlfeld mit den Unterseiten, dann die Blöcke. Das gilt
   ausdrücklich **auch für den bestehenden Block** in der
   Editor-Seitenleiste, nicht nur für den neuen Dialog.

Beides greift auf **einen** gemeinsamen Auswahlbaustein zu. Zwei
Implementierungen derselben Auswahl würden auseinanderlaufen — das Projekt
hat dafür ein warnendes Beispiel in den beiden Admin-Formularen
`admin/new-block.php` und `admin/edit-block.php`, die bis heute doppelt
gepflegt werden müssen.

## 2. Nicht-Ziele

- **Das Theme wird nicht geändert.** Kein `Theme/functions.php`, kein
  Vite-Build. Theme-Funktionen werden ausschließlich lesend und hinter
  `function_exists()` aufgerufen, so wie die bestehenden Nähte es vorsehen.
- **Das Plugin „Eigene WP Blocks" wird nicht geändert.** Kein `npm run
  build`, keine Block-ZIPs.
- **Der Endpunkt `cbd/v1/block-html` wird nicht geändert.** Er ist auf
  `post_id` + `stable_id` geschlüsselt und weiß nichts von Blöcken; ein
  Inline-Verweis nutzt ihn unverändert mit. Jede Änderung an dieser Datei ist
  außerhalb des Scopes — dort hängt die Vertraulichkeit gesperrter
  Lösungsseiten daran.
- **Das Modal wird nicht neu gebaut.** `blocks/block-reference/view.js`
  enthält es vollständig (DOM-Klon, Nachladen, ID-Umbenennung,
  Fokusfalle, LaTeX-Nachrendern). Der Inline-Verweis benutzt es mit; erlaubt
  ist genau **eine** Zeile Änderung daran (Erweiterung des Klick-Selektors).
- **Kein Sprungmodus für den Inline-Verweis.** Entscheidung des Nutzers: nur
  Modal. Das Attribut `displayMode` bleibt eine Eigenschaft des Blocks; der
  Inline-Verweis kennt es nicht als Einstellung.
- **Keine anderen Zielblöcke als Container-Blöcke.** Entscheidung des
  Nutzers. Der Namensraum bleibt auf `container-block-designer/` begrenzt.
- **Kein Aufräumen der dreifachen `stableId`-Extraktion.** Bekannte
  technische Schuld (siehe `CLAUDE.md`, „Offener Punkt"). Dieses Vorhaben
  fügt **keine vierte** Fassung hinzu, beseitigt die drei bestehenden aber
  auch nicht.
- **Kein Umbau der teuren Route `cbd/v1/blocks`.** Sie lädt alle
  veröffentlichten Beiträge und Seiten **samt `post_content`** und ruft je
  Beitrag `parse_blocks()`. Das ist vorbestehend und bleibt. Dieses Vorhaben
  verringert nur die **Anzahl** der Aufrufe (Memoisierung, AP-3.2) und fügt
  drei Felder hinzu, die nichts kosten.
- **Keine Migration bestehender Blockreferenz-Blöcke.** Der Block bleibt wie
  er ist; nur seine Zielauswahl in der Seitenleiste wird ersetzt.

## 3. Kontext & Constraints

- **Projekt:** WordPress-Website „FOS Online Schulbuch",
  `c:\Users\mtnhu\OneDrive - Bildungsdirektion\#Unterricht\Website`.
- **Betroffene Komponente:** ausschließlich **CDB-Designer** (v3.1.91,
  Branch `main`, Arbeitsverzeichnis sauber). Theme (v1.5.79) und „Eigene WP
  Blocks" (v1.1.8) bleiben unberührt.
- **Umgebung Produktiv:** All-inkl Shared Hosting, PHP 7.4.33, kein SSH,
  kein WP-CLI. WordPress 6.0+ deklariert.
- **`WP_HTML_Tag_Processor` ist optional, nicht garantiert.** Die Klasse
  existiert erst ab WordPress 6.2. Das Plugin nutzt sie in
  `class-cbd-blocks-rest-api.php` bereits, dort aber hinter
  `class_exists()` mit dokumentiertem Rückfall. Jede neue Nutzung folgt
  diesem Muster: fehlt die Klasse, entfällt die betreffende Verbesserung,
  nichts bricht.
- **Gemessene Hierarchietiefe:** Der Markdown-Bestand in `Inhalte/` spiegelt
  die Seitenstruktur und ist **drei bis vier Ebenen** tief
  (Klasse → Fach → Thema → Seite). Die Kaskade muss also mit vier
  Seitenebenen plus einem Block-Auswahlfeld umgehen können, ohne dass fünf
  Auswahlfelder gleichzeitig erschlagend wirken.
- **Bestehende Konventionen:** `CLAUDE.md` und `reference_file_map.md` des
  Plugins sowie die Wurzel-`CLAUDE.md`. Diese haben Vorrang – keine neuen
  Konventionen erfinden.
- **Testumgebung:** Lokaler All-inkl-Simulator unter `C:\allinkl-testserver`.
  - Start: `C:\allinkl-testserver\start-server.cmd`, Stopp: `stop-server.cmd`
  - WordPress 7.0.3 unter **http://fos.localhost:8080/**
    (per `curl` nur mit Kopfzeile `Host: fos.localhost` erreichbar)
  - Installationspfad: `C:\allinkl-testserver\www\htdocs\w0000001\fos`
  - Plugin: `…\fos\wp-content\plugins\container-block-designer`
  - WP-Admin: `admin` / `Testserver2026!`
  - Datenbank `d0000001` / Benutzer `d0000001` / Passwort `EBZvYRyrEM34gtfmv3Z8`,
    MariaDB-Client `C:\allinkl-testserver\mariadb\bin\mysql.exe`
  - `WP_DEBUG = true`, `WP_DEBUG_LOG = true` → `…\fos\wp-content\debug.log`
  - **Die Plugins liegen dort als Kopie, nicht als Verknüpfung.** Nach einer
    Änderung im Projektordner müssen die Dateien dorthin kopiert oder das
    ZIP installiert werden – sonst testet man den alten Stand.
  - Bestehende Prüfseiten: 43–47, 54, 55 (`phase-1-pruefseite`),
    62 (`ap27-pruefseite`), 64 (gesperrt), 65 (Entwurf).
    Designs `ap15_klappbar`, `ap27_icon_header`, `ap27_icon_tr`, `ap27_icon_bl`.
  - **Der WordPress-Auto-Updater hinterlässt gelegentlich eine
    `.maintenance`-Datei** → HTTP 503. Abhilfe: Datei und
    `wp-content/upgrade/wordpress-*` löschen.
- **PHP-CLI:** `php` im PATH ist **8.5.1**, der Testserver nutzt **8.3.32**
  (`C:\allinkl-testserver\php\8.3\php.exe`). Beide sind PHP 8 – deshalb ist
  `php tools/check-php74.php` nicht optional (siehe Regel 16).
- **Harte Grenzen:**
  - Rückwärtskompatibilität: Bestehende Seiteninhalte dürfen im Editor nicht
    als „Block enthält unerwarteten oder ungültigen Inhalt" erscheinen.
  - Bestehende Blockreferenz-Blöcke müssen unverändert funktionieren.
  - Der Filter `simple_clean_lehrerseite_freigeben` behält den Standardwert
    `false`. Ein Fehler in der Naht muss zu wenig zeigen, nie zu viel.
  - Antworten des Endpunkts `cbd/v1/block-html` bleiben zeichengleich —
    Ablehnung und Nichtexistenz dürfen nicht unterscheidbar werden.
- **Git-Strategie:** Branch pro Phase, Commit pro AP mit AP-ID im Text, Push
  nach jedem AP. Merge in `main` erst nach bestandenem Integrationstest und
  Review. Vor dem Merge wird der bisherige `main` getaggt.
  | Phase | Branch | Tag vor dem Merge |
  |---|---|---|
  | 3 | `phase-3-auswahl-grundlage` | `vor-phase-3` |
  | 4 | `phase-4-inline-referenz` | `vor-phase-4` |
- **Remote:** https://github.com/Cyric25/CBD---Container-Block-Desinger.git

## 4. Architekturentscheidungen

| Entscheidung | Begründung | Verworfene Alternative |
|---|---|---|
| **Die Auswahl liegt in einer eigenen Datei `assets/js/block-auswahl.js` und veröffentlicht `window.cbdBlockAuswahl`** | Zwei Konsumenten (Seitenleiste des Blocks, Inline-Dialog) brauchen identisches Verhalten. Ohne Build-Schritt gibt es kein Modulsystem; der Namensraum `window.cbd*` ist die etablierte Projektlösung (`window.cbdRenderLatex`, `window.cbdPDFExportServerSide`). Nebeneffekt: Der teure Abruf `cbd/v1/blocks` wird memoisiert und damit **seltener** aufgerufen als heute (heute pro Mount neu) | `schluessel()`, `passtZurSuche()` und den Optionsaufbau nach `format.js` kopieren: erzeugt genau die Doppelung, gegen die das Projekt an anderer Stelle Wächter geschrieben hat (`stableId`-Extraktion dreifach) |
| **Der Seitenbaum kommt aus einer neuen Route `cbd/v1/seitenbaum`, nicht aus `cbd/v1/blocks`** | Eine Seite ohne Container-Block fehlt in `cbd/v1/blocks`. Steht sie zwischen zwei Ebenen, reißt die Elternkette und ihre Kinder werden zu Waisen. Der Baum braucht **alle** Seiten. Getrennte Route, weil `cbd/v1/blocks` eine nackte JSON-**Liste** zurückgibt (`new WP_REST_Response($blocks, 200)`) — sie in ein Objekt zu verpacken wäre ein Bruch für jeden künftigen Leser | Antwortform von `cbd/v1/blocks` zu `{bloecke, baum}` ändern: bricht die dokumentierte Form. Elternkette im Browser aus `postParent` auflösen: scheitert an fehlenden Zwischenseiten |
| **Der Baum wird mit rohem `$wpdb` und fünf Spalten geladen, per Breitensuche ab Wurzel 0 aufgebaut** | Vorbild ist `Theme/includes/page-index.php:135-249` (`simple_clean_page_index_daten()`), das genau diese Entscheidung mit drei Gründen begründet: keine erneute Auflösung der Elternkette, verwaiste Knoten fallen samt Unterbaum heraus, Zyklen sind von der Wurzel aus unerreichbar. `get_pages()` lädt dagegen vollständige `WP_Post`-Objekte samt `post_content` | Theme-Funktion aufrufen: Sie ist Theme-Code, kein geteilter Vertrag. Das Plugin ruft Theme-Funktionen nur für **Sichtbarkeits**entscheidungen auf; ein Baumaufbau gehört nicht in diese Kategorie. Die Breitensuche sind ~25 Zeilen |
| **Das Beschneiden des Baums (Zweige ohne Zielblöcke ausblenden) geschieht im Browser, nicht auf dem Server** | Der Client hat beide Datensätze ohnehin. Der Server bliebe damit dumm und die Route unabhängig davon, was der Client anzeigen will | `hatBloecke` serverseitig berechnen: verlangt, dass die Baum-Route die Blockliste kennt — zwei Routen mit einer verdeckten Abhängigkeit |
| **Die Kaskade wächst dynamisch: ein Auswahlfeld je Ebene, das nächste erscheint erst nach einer Wahl** | Gemessene Tiefe 3–4 Ebenen. Feste vier Felder wären auf einer flachen Klasse leer und verwirrend; ein Aufklapp-Baum wäre bei dieser Tiefe unnötig aufwendig und passt nicht zur Formulierung des Nutzers („dann tauchen in einer zweiten Dropdown die Unterseiten auf") | Feste Zahl von Feldern: leere Felder bei flachen Zweigen. Aufklapp-Baum: mehr Code, mehr Zustand, kein Gewinn bei vier Ebenen |
| **Suchfeld und Kaskade teilen **einen** Zustand: Ein Suchtreffer stellt die Auswahlfelder auf den Pfad des Treffers** | Zwei nebeneinander stehende Auswahlmechanismen mit getrenntem Zustand widersprechen sich früher oder später — der Nutzer wählt im Baum, sucht dann, und weiß nicht mehr, was gilt. Mit einem Zustand sind Suche und Kaskade zwei Sichten auf dieselbe Wahl | Suche als eigene, konkurrierende Liste: klassische Quelle von „das Feld zeigt einen Wert, den es nicht kennt" |
| **Der Inline-Verweis speichert `href` und vier `data-`Attribute; `data-display-mode`, `data-same-page` und `aria-haspopup` setzt ein `the_content`-Filter serverseitig** | Ein Textformat friert seine Attribute beim Bearbeiten ein. `render.php` berechnet dagegen bei **jedem** Aufruf: `href` aus `get_permalink()` und `data-same-page` aus `get_the_ID()`. Frisch berechnet bleibt der Verweis nach einer Slug-Änderung heil und der DOM-Klon-Pfad kann nicht auf den falschen Zwilling zeigen. `aria-haspopup` steht nicht in der ARIA-Whitelist von kses und würde einem Block-Redakteur beim Speichern entfernt — serverseitig gesetzt ist es immer da | `data-same-page` mitspeichern: zeigt nach dem Kopieren eines Absatzes still den falschen Block. `aria-haspopup` per `wp_kses_allowed_html`-Filter erlauben: schwächt die Filterung projektweit. `aria-haspopup` in JS nachsetzen: eine vierte Stelle, die dasselbe weiß |
| **Der Filter setzt `href` nur neu, wenn `get_permalink()` einen Wert liefert; sonst bleibt der gespeicherte Wert stehen** | Der gespeicherte `href` ist die fortschreitende Verbesserung für den Fall ohne JavaScript. Ihn bei einem Fehlschlag zu leeren wäre schlechter als ein möglicherweise veralteter Link | `href` immer überschreiben: ein gelöschtes Ziel ergäbe `href=""` und damit einen Link auf die aktuelle Seite |
| **`view.js` wird über den `the_content`-Filter eingebunden, nicht über einen zweiten Inhalts-Scan auf `wp_enqueue_scripts`** | `block.json` deklariert `viewScript`; WordPress lädt es nur, wenn der **Block** auf der Seite steht. Der Filter hat den Inhalt schon in der Hand und weiß bereits, dass ein Inline-Verweis darin vorkommt — ein zweiter Scan an anderer Stelle wäre dieselbe Frage doppelt gestellt. `view.js` ist ein Footer-Script (`$in_footer = true`), also greift ein Enqueue aus dem Inhaltsfilter noch | Eigener Scan auf `wp_enqueue_scripts`: erfordert eine Fallunterscheidung nach `is_singular()` und verfehlt Auszüge, Widgets und Archive. `viewScript` in `block.json` entfernen und immer laden: lädt das Script auf jeder Seite des Auftritts |
| **Das Textformat trägt `tagName: 'a'`, nicht `span`** | Die Glossar-Autoverlinkung des Themes (`the_content`, Priorität 10000) überspringt `<a>`-Elemente korrekt. Bei einem `<span>` dürfte sie ein `<a class="glossar-term">` **hinein**setzen; Klick und Tooltip würden konkurrieren. Ein `<a>` schützt den markierten Text selbst | `span` + eigenes Klickverhalten: verschachtelte Verlinkung durch das Glossar, und ohne JavaScript wäre der Verweis gar kein Link |
| **Eigene CSS-Klasse `cbd-block-reference-inline` statt Mitbenutzung von `cbd-block-reference-link`** | Letztere trägt in `style.css:9-14` `display: block` samt Karten-Layout und `transform` beim Überfahren — mitten in einem Absatz zerreißt das den Textfluss. `registerFormatType` schreibt genau **eine** Klasse; ein unterscheidendes Attribut nachzuschieben wäre ein Umweg | Gleiche Klasse + `[data-inline]`-Selektor: spart eine Zeile in `view.js`, erkauft das mit einem CSS-Sonderfall in jeder bestehenden Regel |
| **Kein `displayMode`-Attribut am Inline-Format** | Nur Modal, Entscheidung des Nutzers. Ein Attribut mit genau einem erlaubten Wert ist Ballast — und der Filter setzt `data-display-mode="modal"` ohnehin serverseitig | Attribut mit Vorgabe `modal`: unnötiger Zustand im gespeicherten Markup |
| **Gesperrte Zielseiten werden in der Auswahl gekennzeichnet, nicht ausgeblendet** | Ein Verweis auf einen Block einer „nur für Lehrpersonen"-Seite öffnet für Schülerinnen und Schüler nichts (der Endpunkt lehnt zeichengleich ab). Ohne Hinweis kann die Redakteurin das nicht wissen — ein durch **diese** Erweiterung erst entstehendes Rätsel. Ausblenden wäre falsch: Auf einer Lehrerseite ist der Verweis legitim | Ohne Kennzeichnung: erzeugt vorhersehbare Rückfragen. Ausblenden: nimmt eine legitime Verwendung weg |
| **Phase 3 hat kein eigenes Dokumentations-AP** | Phase 3 liefert nichts, was auf einer Installation sichtbar wird — Datenvertrag, gemeinsamer Baustein, Filter ohne Erzeuger. Ein eigener Dokumentationsstand dafür beschriebe einen Zwischenzustand, den nie jemand installiert. `AP-3.rev` gibt es dagegen, weil der `the_content`-Filter in fremdes Markup schreibt | Doc-AP je Phase wie in Phasen 1/2: erzeugt hier eine Dokumentation, die zwei Tage später überschrieben wird |

## 5. Risiken & Rollback

| Risiko | Wahrscheinlichkeit | Auswirkung | Gegenmaßnahme / Rollback |
|---|---|---|---|
| **Der `the_content`-Filter beschädigt fremdes Markup** | mittel | **hoch** | Das schwerste Risiko dieses Vorhabens: Der Filter schreibt in gespeicherten Seiteninhalt. Drei Wächter, alle in AP-3.3 als Akzeptanzkriterium: (1) sofortiger Rückzug bei fehlender Klassenzeichenkette im Inhalt, (2) `WP_HTML_Tag_Processor` statt regulärer Ausdrücke, (3) Prüfharnisch mit Inhalten, die den Filter **nicht** betreffen — Ergebnis muss zeichengleich sein. `AP-3.rev` prüft den Filter gesondert |
| **Der Inline-Verweis wird von `wp_kses_post()` beschnitten** | mittel | mittel | Betrifft nur Nutzer ohne `unfiltered_html`, also die Rolle **Block-Redakteur** — nicht Administratoren. Die `data-*`-Namen müssen dem Muster `data(-[a-z0-9_]+)+` folgen (durchgehend klein, Bindestriche); `data-targetStableId` würde entfernt. `aria-haspopup` steht nicht in der ARIA-Whitelist und wird deshalb gar nicht gespeichert (siehe Abschnitt 4). AP-4.3 prüft ausdrücklich **als Block-Redakteur** und liest den Datenbankinhalt |
| **Das Textformat erscheint in manchen Blöcken nicht** | hoch | gering | Blöcke, die ihrer `RichText` ein `allowedFormats`-Array mitgeben, zeigen nur die dort genannten Formate; `withoutInteractiveFormatting` filtert Formate mit interaktivem `tagName` (`a`, `button`) heraus. Absatz, Überschrift und Liste sind unkritisch, `core/button`-Beschriftungen nicht. Das ist eine Eigenschaft von Gutenberg, keine Fehlfunktion — AP-4.2 hält fest, in welchen Blöcken es geprüft wurde, AP-4.4 dokumentiert es |
| **`view.js` fehlt auf Seiten mit ausschließlich Inline-Verweisen** | mittel | mittel | Der Fehler, der beim Testen auf einer Seite, die **auch** einen Blockreferenz-Block enthält, garantiert nicht auffällt. Der Verweis wäre dann ein stummer Link zum Ziel (also nicht kaputt, aber ohne Modal). AP-3.3 bindet das Script aus dem Inhaltsfilter ein; AP-4.3 prüft zwingend auf einer Seite **ohne** Blockreferenz-Block |
| **Ein bestehender `core/link` auf demselben Text ergibt verschachtelte `<a>`** | mittel | mittel | Ungültiges HTML, unvorhersehbares Klickverhalten. AP-4.2 fängt das im Dialog ab: liegt auf der Markierung ein aktives `core/link`, wird das Anwenden verweigert und begründet |
| **Blockgültigkeit bei nicht geladenem Format-Script** | niedrig | mittel | Ist das Script einmal nicht da (Plugin deaktiviert, JS-Fehler), liest RichText das `<a class="cbd-…">` als unregistriertes Format ein und schreibt es beim Speichern zurück. Das trägt in der Regel. Netz vorhanden: `assets/js/block-recovery.js`. AP-4.3 prüft den Fall durch Deaktivieren des Plugins auf einer Prüfseite |
| **Syntaxfehler in einer neuen JS-Datei legt den Editor lahm** | mittel | hoch | Die Werkzeugkette prüft **nur PHP** (`tools/check-php74.php`, `syntax-check.js`). Ein Tippfehler in `format.js` oder `block-auswahl.js` bricht den ganzen Editor-Zweig still. Jedes JS-ändernde AP führt `node --check <datei>` aus und hält das Ergebnis fest |
| **Die zusätzliche Route erhöht die Editor-Ladezeit** | niedrig | gering | Die Baum-Route ist billig (rohes `$wpdb`, fünf Spalten, kein `post_content`). Die teure Route bleibt `cbd/v1/blocks` — durch die Memoisierung in AP-3.2 wird sie jedoch **einmal** je Editor-Sitzung abgerufen statt einmal je Mount. Netto erwartete Verbesserung; AP-4.3 misst beide Routen mit `curl` und hält die Zeiten fest |
| **Kaskade mit vier Ebenen wirkt erschlagend** | mittel | gering | Auswahlfelder erscheinen nur, wenn die gewählte Ebene Kinder hat; das Suchfeld bleibt als schneller Weg erhalten und stellt die Kaskade auf den Trefferpfad. AP-4.3 hat die Beurteilung durch den Nutzer als ausdrücklichen Schritt |
| **PHP-8.0-Syntax gelangt ins Plugin** | mittel | hoch | Lokal PHP 8.5.1, Ziel 7.4.33. `php -l` meldet das nicht. Jedes PHP-ändernde AP hat `php tools/check-php74.php` als Akzeptanzkriterium; `create-plugin-zip.js` bricht zusätzlich ab |
| **Das Plugin-ZIP wird mit Dev-Autoloader gebaut → HTTP 500** | niedrig | hoch | `create-plugin-zip.js` führt `composer dump-autoload --no-dev --optimize` aus und stellt danach den Dev-Autoloader wieder her. **Diesen Schritt nie entfernen.** ZIPs ausschließlich über `node create-plugin-zip.js`, nie manuell zippen. Bricht der Bau ab: Fragment löschen, Versionsnummer und Autoloader-Zustand prüfen, erneut bauen (in Phase 2 einmal durch eine OneDrive-Dateisperre passiert) |
| **Änderungen wirken auf dem Testserver nicht** | hoch | gering | Die Plugins liegen dort als **Kopie**, nicht als Verknüpfung. AP-4.3 kopiert ausdrücklich bzw. installiert das ZIP und prüft danach die Versionsanzeige im Plugin-Menü |

**Generelle Rollback-Strategie:** Branch pro Phase. Vor dem Merge nach `main`
wird der bisherige `main`-Stand getaggt (`vor-phase-3`, `vor-phase-4`),
Rückweg ist `git reset --hard <tag>`. Ein Datenbank-Eingriff findet nicht
statt — das Schema bleibt unverändert. **Gespeicherte Seiteninhalte werden
von keinem AP verändert**; der Inline-Verweis entsteht nur, wenn eine
Redakteurin ihn setzt, und der `the_content`-Filter wirkt ausschließlich auf
die Ausgabe. Ein Rückbau der Plugin-Version macht Inline-Verweise damit zu
gewöhnlichen Links zur Zielseite — Inhalt geht nicht verloren.

## 6. Phasenübersicht

| Phase | Ziel | Lauffähiger Endzustand | APs |
|---|---|---|---|
| **3** | Datenvertrag und gemeinsame Bausteine | Die Editor-Routen liefern Hierarchiedaten; `window.cbdBlockAuswahl` steht bereit und ist testbar; der serverseitige Teil des Inline-Verweises (Filter, Script-Einbindung, Format-Registrierung) ist vorhanden und stört nichts, solange kein Verweis existiert. **Für den Nutzer sichtbar verändert sich nichts.** | AP-3.1, AP-3.2, AP-3.3, AP-3.rev |
| **4** | Oberfläche an beiden Stellen | Die Seitenleiste des Blocks filtert hierarchisch; das Textformat lässt sich anwenden und öffnet im Frontend das Modal; ZIP gebaut, auf dem Testserver abgenommen, dokumentiert | AP-4.1, AP-4.2, AP-4.3, AP-4.rev, AP-4.doc |

### Parallelisierung – verbindlich

**Phase 3: AP-3.1, AP-3.2 und AP-3.3 laufen gleichzeitig.** Ihre Dateimengen
sind disjunkt:

| AP | Dateien |
|---|---|
| AP-3.1 | `includes/class-cbd-blocks-rest-api.php`, `tools/test-seitenbaum.php` (neu) |
| AP-3.2 | `assets/js/block-auswahl.js` (neu), `includes/class-cbd-block-reference.php`, `tools/test-block-auswahl.js` (neu) |
| AP-3.3 | `includes/class-cbd-inline-reference.php` (neu), `container-block-designer.php`, `tools/test-inline-reference.php` (neu) |

Möglich ist das, weil die **drei Datenverträge in Abschnitt 7 vollständig
festgeschrieben** sind (Vertrag A, B, C, D, E). AP-3.2 programmiert gegen
Vertrag A und B, ohne auf AP-3.1 zu warten — genau das Verfahren, mit dem in
Phase 1 `window.cbdRenderLatex` gebaut wurde. Die Integration prüft AP-4.3.

**AP-3.3 ist das einzige AP dieses Vorhabens, das
`container-block-designer.php` anfasst** (zwei Zeilen: `require_once` und
`::init()`). Kein anderes AP darf diese Datei ändern.

**Nach AP-3.rev: AP-4.1 und die drei Korrektur-APs laufen gleichzeitig.**
AP-3.fix3/4/5 sind Nachträge aus dem Review; sie halten Welle 2 nicht auf,
weil ihre Dateimengen zu allem in Phase 4 disjunkt sind:

| AP | Dateien |
|---|---|
| AP-4.1 | `blocks/block-reference/index.js` |
| AP-3.fix3 | `includes/class-cbd-blocks-rest-api.php`, `tools/test-seitenbaum.php` |
| AP-3.fix4 | `assets/js/block-auswahl.js`, `tools/test-block-auswahl.js` |
| AP-3.fix5 | `includes/class-cbd-inline-reference.php`, `blocks/block-reference/render.php`, `tools/test-inline-reference.php` |

AP-4.1 benutzt den Auswahlbaustein, den AP-3.fix4 gleichzeitig ändert. Das ist
zulässig: AP-3.fix4 fasst **Vertrag C nicht an**, es behebt nur ein Verhalten
innerhalb der Komponente. AP-4.1 programmiert weiter gegen den Vertrag.

**AP-4.2 startet erst, wenn AP-3.fix5 fertig ist.** Beide brauchen
`tools/test-inline-reference.php` — AP-3.fix5 für die zwei Fälle zu den
führenden Nullen, AP-4.2 für den Duplikatswächter aus AK14. Zwei APs
gleichzeitig in derselben Testdatei ist genau der Fall, den Regel C verbietet.

Danach AP-4.3 (Abnahme), AP-4.rev (Review), AP-4.doc (Dokumentation) —
sequenziell in dieser Reihenfolge.

## 7. Arbeitspakete

Alle Pfadangaben sind relativ zu `Plugins/CDB-Designer/`, sofern nicht anders
angegeben.

---

### Die fünf Verträge

Diese Festlegungen sind **verbindlich** und ermöglichen die Parallelarbeit.
Wer einen Vertrag für falsch hält, ändert ihn nicht selbst, sondern meldet
das (Regel 15).

#### Vertrag A — `GET cbd/v1/blocks` (erweitert, Form unverändert)

Antwort bleibt eine **nackte JSON-Liste**. Je Eintrag kommen drei Felder
hinzu, die acht bestehenden bleiben unverändert:

```json
{
  "stableId": "cbd-container-abc123",
  "anchor": "",
  "blockId": "",
  "blockTitle": "Grundlagen der IR-Spektroskopie",
  "postId": 45,
  "postTitle": "IR-Spektroskopie",
  "postUrl": "http://…/ir-spektroskopie/",
  "blockType": "container-block-designer/basic-container",

  "postParent": 34,
  "menuOrder": 0,
  "postType": "page"
}
```

`postParent` und `menuOrder` sind `int`, `postType` ist `"page"` oder
`"post"`. Reihenfolge der Blöcke innerhalb einer Seite bleibt
Dokumentreihenfolge.

#### Vertrag B — `GET cbd/v1/seitenbaum` (neu)

`permission_callback`: `current_user_can('edit_posts')` — dasselbe
Sicherheitsmodell wie `cbd/v1/blocks`, daher dieselbe Klasse.

```json
{
  "knoten": {
    "12": {"id":12,"parent":0,"titel":"4. Klasse","menuOrder":0,"tiefe":0,"typ":"page","gesperrt":false},
    "34": {"id":34,"parent":12,"titel":"ACH","menuOrder":1,"tiefe":1,"typ":"page","gesperrt":false},
    "45": {"id":45,"parent":34,"titel":"IR-Spektroskopie","menuOrder":0,"tiefe":2,"typ":"page","gesperrt":true}
  },
  "kinder": { "0": [12], "12": [34], "34": [45] },
  "wurzeln": [12]
}
```

- Enthalten sind **alle** Seiten mit Status `publish`, auch solche ohne
  Container-Block. Beiträge (`post`) sind nicht hierarchisch und erscheinen
  **nicht** im Baum; der Client stellt sie als flache Zusatzebene dar
  (Vertrag C).
- `kinder` ist nach `menuOrder`, dann `titel` sortiert.
- `tiefe` ist die Distanz zur Wurzel, `0` für oberste Ebene.
- `gesperrt` ist `simple_clean_seite_nur_lehrpersonen($id)`, sofern die
  Theme-Funktion existiert — sonst durchgehend `false`. **Nicht**
  `simple_clean_seite_sichtbar()`: gefragt ist „ist diese Seite für
  Lehrpersonen reserviert", nicht „darf der aktuelle Nutzer sie sehen".
- Verwaiste Knoten (Elternteil nicht `publish`) fehlen samt Unterbaum. Das
  ist die dokumentierte Eigenschaft der Breitensuche, kein Fehler.
- **Tiefenbegrenzung 20** (in AP-3.1 ergänzt, hier nachgetragen nach Befund
  Anmerkung 1 aus AP-3.rev): Seiten jenseits von Ebene 22 fallen samt
  Unterbaum heraus, mit einem Eintrag im Log bei `WP_DEBUG`. Gemessene Tiefe
  des Projekts ist 3–4; die Grenze ist ein Schutz gegen verstümmelte Daten,
  keine fachliche Aussage. **Vertragsbestandteil, nicht zu entfernen.**
- `knoten` und `kinder` sind **JSON-Objekte**, auch wenn ihre Schlüssel
  zufällig `0..n-1` lauten (siehe `AP-3.fix3`, Befund S1). Ein Client, der
  eine Liste bekommt, verwirft sie.

#### Vertrag C — `window.cbdBlockAuswahl`

```js
window.cbdBlockAuswahl = {
    // Lädt Vertrag A und B parallel, MEMOISIERT: mehrere Aufrufer teilen
    // dasselbe Promise. Lehnt nie ab — bei Fehlern liefert sie leere
    // Datensätze und ein gesetztes `fehler`.
    ladeDaten: function () {},   // -> Promise<{bloecke: Array, baum: Object, fehler: string}>

    schluessel: function (eintrag) {},        // -> "<postId>|<stableId>"
    text: function (wert) {},                 // null-sichere Textumwandlung
    passtZurSuche: function (eintrag, begriff) {},  // Mehrwortfilter, alle Teile müssen passen

    // Vorfahrenkette einer Seite, Wurzel zuerst, einschließlich postId selbst.
    // Unbekannte postId -> [].
    pfadVon: function (baum, postId) {},      // -> Array<int>

    // Kaskadenstufen für einen Pfad: je Stufe die wählbaren Einträge und den
    // aktuellen Wert. Zweige ohne erreichbaren Zielblock sind beschnitten.
    // Die LETZTE Stufe ist die Block-Auswahl der gewählten Seite, sofern
    // diese Zielblöcke hat (siehe AK4).
    ebenen: function (baum, bloecke, pfad) {},  // -> Array<{tiefe, optionen, wert}>

    // wp.element-Komponente. Siehe Props unten.
    HierarchieAuswahl: function (props) {}
};
```

Props von `HierarchieAuswahl`:

| Prop | Typ | Bedeutung |
|---|---|---|
| `wert` | string | aktueller Schlüssel `"<postId>\|<stableId>"` oder `''` |
| `onWaehle` | function | `(eintrag \| null) => void`; `eintrag` ist ein Element aus `bloecke` (Vertrag A), `null` beim Abwählen |
| `suche` | bool | Suchfeld anzeigen, Vorgabe `true` |
| `hinweisGesperrt` | bool | Hinweis bei gesperrter Zielseite anzeigen, Vorgabe `true` |
| `beschriftung` | string | Überschrift des Auswahlbereichs, optional |

Zusicherungen der Komponente:

1. Sie ruft `ladeDaten()` selbst auf und zeigt bis dahin `Spinner`.
2. Sie wirft nie — fehlende oder kaputte Daten ergeben eine Meldung.
3. **Der aktuell gewählte Eintrag bleibt wählbar, auch wenn der Suchbegriff
   ihn herausfiltert.** Sonst zeigte das Auswahlfeld einen Wert, den es
   nicht kennt, und verwürfe ihn beim nächsten Rendern
   (`blocks/block-reference/index.js:180-190` löst das heute schon so).
4. Ein Suchtreffer stellt die Kaskade auf den Pfad des Treffers — Suche und
   Kaskade sind zwei Sichten auf **einen** Zustand.
5. Ein Auswahlfeld für die nächste Ebene erscheint nur, wenn die gewählte
   Seite Kinder mit erreichbaren Zielblöcken hat. Das Block-Auswahlfeld
   erscheint, sobald die gewählte Seite selbst Zielblöcke hat — beide dürfen
   gleichzeitig sichtbar sein.

**Präzisierungen, in AP-3.2 festgelegt** (Lücken des ursprünglichen Vertrags,
verbindlich für AP-4.1 und AP-4.2 — kein Befund für AP-3.rev):

1. Die Blockstufe aus `ebenen()` trägt `tiefe: null`; Seitenstufen tragen
   `tiefe` = Baumtiefe = Stufenindex. Ein vierter Schlüssel am Stufenobjekt
   wäre eine Abweichung gewesen.
2. Der `wert` der Blockstufe ist **immer** `''`. `ebenen()` kennt nur den
   Seitenpfad, nicht die Zielwahl; die Komponente setzt ihn aus ihrem Prop
   `wert`.
3. `ebenen()` akzeptiert als **erstes** Pfadelement zusätzlich die Marke
   `'beitraege'` („Beiträge gewählt, aber noch kein Beitrag"). `pfadVon()`
   liefert weiterhin strikt `Array<int>`.
4. Optionsobjekte sind `{value, label, gesperrt}`, in der Blockstufe
   zusätzlich `eintrag` (das Element aus Vertrag A). Ohne
   Platzhalter-Option — die setzt die Komponente.
5. Die Wurzeloption „Beiträge" sammelt **alles, was nicht im Baum steht** —
   also auch verwaiste Seiten und, falls `cbd/v1/seitenbaum` ausfällt,
   schlicht alle Ziele. Damit bleibt bei einem Baumausfall jedes Ziel
   wählbar, statt die Auswahl leer zu lassen.
6. CSS-Haken der Komponente, alle **ungestylt** (WordPress-Komponenten
   tragen ihre Optik selbst): `.cbd-block-auswahl`, `…__titel`, `…__leer`,
   `…__hinweis`, `…__fehler`, `.is-laedt`.

**Bekannte Grenze, für AP-4.3 zu beurteilen:** `ladeDaten()` hat **keine**
Möglichkeit, die Memoisierung zu verwerfen. Legt eine Redakteurin in einem
zweiten Tab einen Container-Block an, bleibt die Liste in der laufenden
Editor-Sitzung veraltet, bis die Seite neu geladen wird. Eine achte
Eigenschaft hätte AK1 verletzt, deshalb bewusst nicht ergänzt. Fällt es in
AP-4.3 als störend auf, gehört es als `AP-4.fixN` in den Vertrag — **nicht**
als stille Ergänzung.

**Kein `onGeladen`-Prop nötig:** AP-4.2 gibt „Übernehmen" frei, sobald ein
Ziel gewählt ist — und eine Wahl setzt geladene Daten voraus. Der Ladezustand
muss den Aufrufer also nicht erreichen.

#### Vertrag D — gespeichertes Markup des Inline-Verweises

```html
<a class="cbd-block-reference-inline"
   href="http://…/ir-spektroskopie/?cbd-ref=cbd-container-abc123"
   data-target-post="45"
   data-target-stable-id="cbd-container-abc123"
   data-target-anchor=""
   data-target-title="Grundlagen der IR-Spektroskopie">markierter Text</a>
```

**Nicht gespeichert** werden `data-display-mode`, `data-same-page` und
`aria-haspopup` — die setzt Vertrag E serverseitig. Alle Attributnamen
durchgehend klein mit Bindestrichen, damit `wp_kses_post()` sie durchlässt.

Format-Registrierung:

```js
wp.richText.registerFormatType('cbd/block-reference-inline', {
    title: …, tagName: 'a', className: 'cbd-block-reference-inline',
    attributes: {
        stableId: 'data-target-stable-id',
        postId:   'data-target-post',
        anchor:   'data-target-anchor',
        titel:    'data-target-title',
        href:     'href'
    },
    edit: …
});
```

#### Vertrag E — `CBD_Inline_Reference::inhalt_auffrischen($content)`

`the_content`, **Priorität 12** (nach dem LaTeX-Netz auf 11, weit vor der
Glossar-Autoverlinkung auf 10000).

Ablauf, in dieser Reihenfolge:

1. `false === strpos($content, 'cbd-block-reference-inline')` → `$content`
   **unverändert** zurück. Keine weitere Arbeit.
2. `!class_exists('WP_HTML_Tag_Processor')` → `$content` unverändert zurück.
   Der Verweis bleibt dann ein gewöhnlicher Link (fortschreitende
   Verbesserung, kein Fehler).
3. Für jedes `<a>` mit dieser Klasse und einem als positive Ganzzahl
   lesbaren `data-target-post`:
   - `data-display-mode` auf `modal` setzen
   - `data-same-page` auf `true` setzen, **genau dann** wenn
     `get_the_ID() === (int) $ziel_post`; andernfalls das Attribut entfernen
   - `aria-haspopup` auf `dialog` setzen
   - `href` neu berechnen: `get_permalink($ziel_post)`, daran `#<anchor>`
     falls `data-target-anchor` nicht leer ist, sonst
     `?cbd-ref=<stable_id>`. **Ist auch `stable_id` leer, bleibt es beim
     nackten Permalink** — kein `?cbd-ref=` ohne Wert (Wortlautlücke,
     nachgetragen nach AP-3.rev, Anmerkung 2; der Code tat es von Anfang an
     richtig, genau wie `render.php`). Liefert `get_permalink()` einen leeren
     oder falschen Wert, bleibt der gespeicherte `href` stehen.
4. Ein `<a>` ohne brauchbares `data-target-post` bleibt unverändert.
5. Wurde mindestens ein Verweis bearbeitet: das View-Script einbinden
   (`wp_enqueue_script(CBD_Block_Reference::view_script_handle())`) und den
   Blockstil (`style_handles` aus `WP_Block_Type_Registry`).

---

### Phase 3: Datenvertrag und gemeinsame Bausteine

### AP-3.1: Hierarchiedaten in den Editor-Routen

**Modell:** sonnet
**Abhängigkeiten:** keine
**Dateien:** `includes/class-cbd-blocks-rest-api.php`,
`tools/test-seitenbaum.php` (neu)

**Ziel:** Vertrag A und Vertrag B erfüllen.

**Ausgangslage:** `cbd/v1/blocks` (`:28-33`) liefert eine flache Liste mit
acht Feldern je Eintrag (`extract_cbd_block_data()`, `:166-175`). Weder
`post_parent` noch `menu_order` noch `post_type` sind darin — eine
Hierarchie ist aus der Antwort nicht baubar. Die Abfrage (`:52-58`) sortiert
nach `title` über Beiträge und Seiten gemischt. Einziger Konsument der Route
ist `blocks/block-reference/index.js:135`.

**Umsetzung:**

1. **Vertrag A:** In `extract_cbd_block_data()` die drei Felder
   `postParent`, `menuOrder`, `postType` aus dem vorhandenen `$post`-Objekt
   ergänzen — keine Zusatzabfrage nötig. In `get_cbd_blocks()` die
   Sortierung auf `'orderby' => 'menu_order title'` umstellen, damit die
   Reihenfolge innerhalb einer Ebene nicht willkürlich ist.
2. **Vertrag B:** Neue Route `cbd/v1/seitenbaum` in `register_routes()`,
   `permission_callback` ist der bestehende `check_permission()`. Neue
   Methode `get_seitenbaum()`.
3. Der Baum wird mit **rohem `$wpdb`** geladen — fünf Spalten (`ID`,
   `post_parent`, `post_title`, `menu_order`, `post_type`), **kein**
   `post_content`. Vorbild: `Theme/includes/page-index.php:146-151`.
4. Kindkarte `array<ElternID, ID[]>` aufbauen, je Ebene nach `menu_order`,
   dann `post_title` sortieren. Dann **Breitensuche ab Wurzel 0** — sie
   liefert `tiefe` ohne erneutes Auflösen der Elternkette, lässt verwaiste
   Knoten samt Unterbaum herausfallen und macht Zyklen unerreichbar.
   Vorbild: `page-index.php:206-229`.
5. `gesperrt` je Knoten über `simple_clean_seite_nur_lehrpersonen($id)`
   hinter `function_exists()`; fehlt die Funktion, durchgehend `false`.
   **Vor** der Schleife `update_meta_cache('post', $alle_ids)` aufrufen,
   sonst entsteht eine Abfrage je Seite.
6. Ergebnis in einer `static`-Variablen der Methode merken (mehrfache
   Aufrufe innerhalb einer Anfrage).

**Akzeptanzkriterien:**

- AK1: `cbd/v1/blocks` liefert je Eintrag zusätzlich `postParent` (int),
  `menuOrder` (int), `postType` (string); die acht bestehenden Felder sind
  unverändert vorhanden und die Antwort ist weiterhin eine **nackte Liste**,
  kein Objekt.
- AK2: `cbd/v1/seitenbaum` liefert `knoten`, `kinder`, `wurzeln` gemäß
  Vertrag B.
- AK3: Die Baum-Abfrage lädt **kein** `post_content`. Nachweis: Die
  SQL-Zeichenkette im Code nennt die fünf Spalten einzeln, es gibt kein
  `SELECT *`.
- AK4: Eine Seite, deren Elternteil ein Entwurf ist, fehlt samt Unterbaum
  im Baum (dokumentierte Eigenschaft, kein Fehler).
- AK5: Fehlt `simple_clean_seite_nur_lehrpersonen()`, ist jedes `gesperrt`
  `false` und es entsteht kein Fatal Error.
- AK6: Die Zahl der Datenbankabfragen für den Baum ist unabhängig von der
  Seitenzahl konstant (zwei Abfragen: Seiten + Meta-Cache).
- AK7: `php tools/check-php74.php` ist grün.

**Tests (TDD):** `tools/test-seitenbaum.php`, Prüfharnisch ohne WordPress
nach dem Muster von `tools/test-block-content-api.php` (CLI-Wächter +
Stubs). Zu prüfen: Baumaufbau aus einer flachen Seitenliste, Sortierung nach
`menuOrder` vor `titel`, Tiefenberechnung über vier Ebenen, verwaister
Knoten fällt heraus, Zyklus (A→B→A) ist von der Wurzel unerreichbar,
Beiträge erscheinen nicht im Baum, `gesperrt` mit und ohne Theme-Funktion,
und die drei neuen Felder in Vertrag A. Rote Tests zuerst committen.

**Übergabenotiz:** _(vom Agenten zu füllen)_

---

### AP-3.2: Gemeinsamer Auswahlbaustein `window.cbdBlockAuswahl`

**Modell:** opus
**Abhängigkeiten:** keine (programmiert gegen Vertrag A und B)
**Dateien:** `assets/js/block-auswahl.js` (neu),
`includes/class-cbd-block-reference.php`, `tools/test-block-auswahl.js` (neu)

**Ziel:** Vertrag C erfüllen — die hierarchische Zielauswahl **einmal**
bauen, sodass Seitenleiste (AP-4.1) und Inline-Dialog (AP-4.2) dasselbe
Verhalten zeigen.

**Ausgangslage:** Die Auswahl-Logik liegt heute vollständig im IIFE von
`blocks/block-reference/index.js` (`:13`/`:418`) und ist von außen nicht
erreichbar: `schluessel()` (`:62-67`), `text()` (`:69-71`),
`passtZurSuche()` (`:80-92`), der Optionsaufbau mit der Regel „gewählte
Option behalten" (`:159-199`), der Ladeblock mit Abbruchwächter
(`:125-153`). Für Editor-JS gibt es im Plugin bisher kein
`window.cbd*`-Muster; für das Frontend schon (`window.cbdRenderLatex`).

**Umsetzung:**

1. Neue Datei `assets/js/block-auswahl.js`, IIFE-Hausstil:
   `(function (window) { … })(window)`. **`wp.element` darf beim Laden der
   Datei nicht berührt werden**, nur innerhalb der Komponentenfunktion —
   sonst lässt sich die reine Logik nicht ohne WordPress testen (siehe
   Tests).
2. Die genannten Funktionen aus `index.js` **verschieben**, nicht kopieren.
   `index.js` selbst bleibt in diesem AP unverändert; AP-4.1 stellt es um.
   Solange beide Fassungen existieren, ist das hingenommene Übergangsdoppel
   — AP-4.1 hat das Entfernen als Akzeptanzkriterium.
3. `ladeDaten()`: `Promise.all` über `wp.apiFetch({path:'/cbd/v1/blocks'})`
   und `{path:'/cbd/v1/seitenbaum'}`. **Memoisiert**: das erzeugte Promise
   in einer Modulvariablen halten und bei jedem weiteren Aufruf
   zurückgeben. Lehnt nie ab — Fehler landen in `fehler` und die Datensätze
   sind leer.
4. `pfadVon(baum, postId)`: Vorfahrenkette über `knoten[id].parent`, Wurzel
   zuerst, `postId` eingeschlossen. Unbekannte `postId` → `[]`.
   Schleifenschutz über eine Besuchsmenge, auch wenn Vertrag B Zyklen
   ausschließt — die Daten kommen über das Netz.
5. `ebenen(baum, bloecke, pfad)`: Beschneidung zuerst — eine Seite ist nur
   wählbar, wenn sie selbst Zielblöcke hat **oder** ein Nachfahre welche
   hat. Diese Menge einmal berechnen (von den Blättern nach oben über
   `pfadVon`), dann je Stufe die Optionen bilden.
6. Beiträge (`postType === 'post'`) stehen nicht im Baum. Sie bilden eine
   zusätzliche, flache Wurzeloption „Beiträge", darunter die Beiträge mit
   Zielblöcken. So bleibt der Vertrag der Route einfach und kein Ziel ist
   unerreichbar.
7. `HierarchieAuswahl`: Funktionskomponente über `wp.element.useState` /
   `useEffect` (Hausstil von `index.js:22-23`, **keine** Klassenkomponente
   wie im Theme-Vorbild `Theme/src/js/glossar-editor.js:12`). Bausteine:
   `wp.components.TextControl` (Suche), je Ebene ein `SelectControl`, ein
   `SelectControl` für den Block, `Spinner` beim Laden, `Notice` für Fehler
   und für den Sperrhinweis.
8. Registrierung in `class-cbd-block-reference.php` nach dem Muster von
   `register_editor_script()` (`:161-188`): neue Konstante
   `AUSWAHL_HANDLE = 'cbd-block-auswahl'`, `wp_register_script()` mit
   `file_exists()`-Wächter, Cache-Busting über
   `CBD_VERSION . '.' . filemtime($pfad)`, Abhängigkeiten
   `wp-element`, `wp-components`, `wp-i18n`, `wp-api-fetch`. Das bestehende
   `EDITOR_HANDLE` bekommt `AUSWAHL_HANDLE` als zusätzliche Abhängigkeit.
9. Sperrhinweis: Ist die gewählte Zielseite `gesperrt`, zeigt die Komponente
   einen `Notice` (`status: 'warning'`, `isDismissible: false`) mit dem
   Sinngehalt: Diese Seite ist für Lehrpersonen reserviert; der Verweis
   öffnet für Schülerinnen und Schüler nur innerhalb einer Klassensitzung,
   in der der Block als behandelt markiert ist.

**Akzeptanzkriterien:**

- AK1: `window.cbdBlockAuswahl` bietet genau die in Vertrag C genannten
  Eigenschaften; keine weiteren öffentlichen Namen werden gesetzt.
- AK2: `ladeDaten()` ruft jede Route **einmal** ab, auch bei drei
  gleichzeitigen Aufrufen. Nachweis über den Prüfharnisch mit gezählten
  `apiFetch`-Aufrufen.
- AK3: `ladeDaten()` lehnt bei einem Netzfehler nicht ab, sondern liefert
  leere Datensätze und ein gesetztes `fehler`.
- AK4: `ebenen()` liefert für eine Seite mit Kindern **und** eigenen
  Zielblöcken beide Stufen (Unterseiten-Auswahl und Block-Auswahl).
- AK5: Ein Zweig ohne jeden Zielblock erscheint in keiner Stufe.
- AK6: Ein Beitrag mit Zielblock ist über die Wurzeloption „Beiträge"
  erreichbar.
- AK7: `pfadVon()` mit einer `postId`, die nicht im Baum steht, liefert `[]`
  und wirft nicht.
- AK8: Die Datei enthält keinen `import`/`export`, kein JSX, keine
  Klassenfelder. `node --check assets/js/block-auswahl.js` ist grün.
- AK9: `wp.element` wird beim Laden der Datei nicht berührt. Nachweis: der
  Prüfharnisch lädt die Datei mit einem `window`-Stub **ohne** `wp` und
  ruft die reinen Funktionen erfolgreich auf.
- AK10: `php tools/check-php74.php` ist grün (wegen der Änderung an
  `class-cbd-block-reference.php`).

**Tests (TDD):** `tools/test-block-auswahl.js`, mit `node` ausführbar, ohne
jsdom und ohne npm-Abhängigkeit. Verfahren: Datei einlesen und über
`new Function('window', quelle)` mit einem Stub-`window` ausführen, dann die
reinen Funktionen prüfen. Fälle: `schluessel`, `text` mit `null`/`undefined`/
Zahl, `passtZurSuche` mit mehreren Wörtern und unterschiedlicher
Groß-/Kleinschreibung, `pfadVon` über vier Ebenen und mit unbekannter ID,
`ebenen` mit Beschneidung, mit gleichzeitigen Unterseiten und Blöcken, mit
leerem Baum, Memoisierung von `ladeDaten` (Zähler), Fehlerpfad von
`ladeDaten`. **Die Komponente `HierarchieAuswahl` wird hier nicht getestet**
— ohne React-Umgebung nicht sinnvoll möglich; ihre Prüfung erfolgt in
AP-4.3 an der Oberfläche. Diese Grenze ausdrücklich in die Übergabenotiz.
Rote Tests zuerst committen.

**Übergabenotiz (erledigt, vom Orchestrator abgenommen):** 133 Prüfungen
grün, roter Commit `5d34cf2` enthält nur den Harnisch, grüner Commit
`5fb4e8c`. Die Vertragslücken sind oben bei Vertrag C nachgetragen.

**Abweichung, vom Orchestrator geprüft und angenommen:** Plan-Schritt 8
verlangte, `EDITOR_HANDLE` bekommt `AUSWAHL_HANDLE` als Abhängigkeit. Der
Agent hat das an `wp_script_is(AUSWAHL_HANDLE, 'registered')` gebunden, weil
`register_auswahl_script()` einen `file_exists()`-Wächter hat: Fehlte
`block-auswahl.js` in einem ZIP, wäre die Abhängigkeit unbekannt und
WordPress gäbe das Editor-Script **stillschweigend gar nicht** aus — der
Block „Block-Referenz" wäre komplett verschwunden, statt nur seine neue
Auswahl zu verlieren. Angenommen, weil die Reihenfolge trägt:
`register_auswahl_script()` läuft in `register_block()` **vor**
`register_editor_script()` (`class-cbd-block-reference.php:65-72`), im
Normalfall ist die Abhängigkeit also gesetzt. **Diese Reihenfolge ist damit
Vertragsbestandteil** — wer sie umstellt, verliert die Abhängigkeit
stillschweigend.

**Grenze der Prüfung:** `HierarchieAuswahl` ist inhaltlich **nicht** getestet
(ohne React-Umgebung nicht sinnvoll möglich); geprüft ist nur ihr Wachposten.
Kaskadenverhalten, Suchtreffer→Pfad, Sperrhinweis und Zusicherung 3 werden in
AP-4.3 an der Oberfläche abgenommen.

---

### AP-3.3: Serverseite des Inline-Verweises

**Modell:** opus
**Abhängigkeiten:** keine
**Dateien:** `includes/class-cbd-inline-reference.php` (neu),
`container-block-designer.php`, `tools/test-inline-reference.php` (neu)

**Ziel:** Vertrag E erfüllen und `format.js` registrieren, bevor es
existiert — damit AP-4.2 nur noch die JS-Datei und das CSS beitragen muss.

**Ausgangslage:** Ein Textformat hängt an keinem Block; `block.json` und
`editorScript` sind kein Weg. Das Frontend-Script `view.js` wird über
`viewScript` in `block.json:59` nur geladen, wenn der **Block** auf der
Seite steht (`class-cbd-block-reference.php:66-68` sagt das selbst).
`localize_view_script()` (`:121-149`) hängt die Daten bereits auf `init` an
das **registrierte** Handle — sie werden ausgegeben, sobald das Handle in
die Warteschlange kommt. Das Handle ermittelt `view_script_handle()`
(`:78-100`).

**Umsetzung:**

1. Neue Klasse `CBD_Inline_Reference` in
   `includes/class-cbd-inline-reference.php`, `init()` auf `plugins_loaded`
   oder `init` nach dem Muster der Nachbarklassen. Eine eigene Datei, nicht
   in `class-cbd-block-reference.php`: Diese Klasse hat mit dem Block nichts
   zu tun außer der geteilten Modal-Implementierung.
2. `register_format_script()` auf `enqueue_block_editor_assets`. Muster
   `class-cbd-block-reference.php:161-188` (Wächter `wp_script_is(…,
   'registered')`, `file_exists()`, Cache-Busting über
   `CBD_VERSION . '.' . filemtime()`), Handle
   `FORMAT_HANDLE = 'cbd-block-reference-format'`, Abhängigkeiten:

   | Handle | Wofür |
   |---|---|
   | `wp-rich-text` | `registerFormatType`, `applyFormat`, `removeFormat`, `getActiveFormat` — **im Plugin bisher nirgends deklariert** |
   | `wp-block-editor` | `RichTextToolbarButton` |
   | `wp-components` | `Modal`, `Button`, `Notice`, `Spinner` |
   | `wp-element` | `createElement`, `Fragment`, `useState` |
   | `wp-i18n` | `__`, `sprintf` |
   | `wp-api-fetch` | wird von `block-auswahl.js` gebraucht |
   | `CBD_Block_Reference::AUSWAHL_HANDLE` | Vertrag C |

   Die Abhängigkeit auf `AUSWAHL_HANDLE` über die Konstante der anderen
   Klasse referenzieren, nicht als Zeichenkette wiederholen. Fehlt
   `format.js` noch (Stand nach diesem AP), verhindert der
   `file_exists()`-Wächter jede Registrierung — die Wirkung ist null.
   `wp-rich-text` **muss** deklariert werden, auch wenn es über
   `wp-block-editor` meist schon geladen ist; das Plugin hat an genau dieser
   Auslassung schon einmal gelitten (`class-cbd-block-reference.php:155-158`).
3. `inhalt_auffrischen($content)` auf `the_content`, Priorität 12, genau
   nach Vertrag E. `WP_HTML_Tag_Processor` mit
   `next_tag(['tag_name' => 'A', 'class_name' => 'cbd-block-reference-inline'])`,
   `set_attribute()`, `remove_attribute()`. **Kein regulärer Ausdruck.**
4. Schritt 5 aus Vertrag E: Nur wenn mindestens ein Verweis bearbeitet
   wurde, `wp_enqueue_script(CBD_Block_Reference::view_script_handle())` und
   die Stil-Handles aus
   `WP_Block_Type_Registry::get_instance()->get_registered('cbd/block-reference')->style_handles`.
   Beide hinter Existenzprüfungen. Das trägt, weil `view.js` als
   Footer-Script registriert ist (`$in_footer = true`) und der
   Inhaltsfilter vor `wp_footer` läuft — **diese Annahme als Kommentar in
   den Code**, damit eine künftige Umstellung auf `$in_footer = false` nicht
   still den Inline-Verweis lähmt.
5. `require_once` und `CBD_Inline_Reference::init()` in
   `container-block-designer.php` ergänzen, nach dem Muster der
   Nachbarzeilen (dort liegt bereits `CBD_Block_Content_API`).

**Akzeptanzkriterien:**

- AK1: Inhalt **ohne** die Klassenzeichenkette kommt aus
  `inhalt_auffrischen()` **zeichengleich** zurück. Das gilt auch für
  Inhalte mit anderen `<a>`-Elementen, mit `<script>`, mit
  `cbd-block-reference-link` (dem Block) und mit Umlauten/LaTeX.
- AK2: Ein Verweis auf die **eigene** Seite erhält `data-same-page="true"`;
  ein Verweis auf eine andere Seite trägt das Attribut **nicht**.
- AK3: Jeder bearbeitete Verweis trägt danach `data-display-mode="modal"`
  und `aria-haspopup="dialog"`.
- AK4: Mit `data-target-anchor` wird `href` zu `<permalink>#<anchor>`, ohne
  Anker zu `<permalink>?cbd-ref=<stableId>`.
- AK5: Liefert `get_permalink()` `false` oder `''`, bleibt der gespeicherte
  `href` unverändert stehen.
- AK6: Ein `<a class="cbd-block-reference-inline">` ohne oder mit ungültigem
  `data-target-post` bleibt vollständig unverändert.
- AK7: Fehlt `WP_HTML_Tag_Processor`, kommt der Inhalt unverändert zurück
  und es entsteht kein Fatal Error.
- AK8: Die Priorität ist 12 — nachweislich nach dem LaTeX-Netz (11) und vor
  der Glossar-Autoverlinkung (10000).
- AK9: Solange `format.js` nicht existiert, registriert
  `register_format_script()` **nichts** und gibt keine Warnung aus.
- AK10: Das View-Script wird nur eingebunden, wenn mindestens ein Verweis
  bearbeitet wurde.
- AK11: `php tools/check-php74.php` ist grün.
- AK12: Der Endpunkt `cbd/v1/block-html` und die Datei
  `includes/class-cbd-block-content-api.php` sind **unverändert**.

**Tests (TDD):** `tools/test-inline-reference.php`, Prüfharnisch ohne
WordPress nach dem Muster von `tools/test-block-content-api.php`. Alle AK1–AK7
als Einzelfälle, dazu: mehrere Verweise in einem Inhalt, ein Verweis mit
zusätzlichen fremden Attributen (bleiben erhalten), ein Verweis, der schon
`data-same-page="true"` trägt, obwohl er auf eine andere Seite zeigt (muss
entfernt werden), und ein Inhalt, in dem die Klassenzeichenkette nur im
Fließtext vorkommt (kein `<a>` — Inhalt zeichengleich). Rote Tests zuerst
committen.

**Übergabenotiz (erledigt, vom Orchestrator abgenommen):** 119 Prüfungen
grün, und zwar **zweimal** — einmal gegen die echte
`WP_HTML_Tag_Processor`-Klasse (aus der Installation des Testservers geborgt,
ohne WordPress zu booten) und einmal gegen ein schmales Doppel
(`CBD_TEST_TAG_PROCESSOR=doppel`). Roter Commit `d2e0597`, grüner `0df80cd`.
AK12 nachgeprüft: `git diff vor-phase-3..HEAD -- includes/class-cbd-block-content-api.php`
ist leer. `container-block-designer.php` enthält genau die zwei funktionalen
Zeilen, beide hinter `class_exists()`.

**Fehler im Plantext, vom Orchestrator korrigiert:** Schritt 2 dieses APs
verlangte `register_format_script()` auf `enqueue_block_editor_assets` und
verwies gleichzeitig auf `register_editor_script()` als Muster — das aber
**nur registriert**, weil `block.json` das Handle unter `editorScript` nennt
und WordPress es deshalb selbst einreiht. Ein Textformat hängt an keinem
Block; niemand würde das Handle je einreihen, das Script wäre nie geladen.
Der Agent hat das erkannt und `register_format_script()` **registriert und
eingereiht**. Richtig so — dasselbe tut das Theme-Vorbild
(`Theme/functions.php:2317`, `wp_enqueue_script` auf
`enqueue_block_editor_assets`).

**Für AP-4.2 vorbereitet:** Handle `cbd-block-reference-format`, Quelle
`blocks/block-reference/format.js`, Abhängigkeiten in dieser Reihenfolge:
`wp-rich-text`, `wp-block-editor`, `wp-components`, `wp-element`, `wp-i18n`,
`wp-api-fetch`, `CBD_Block_Reference::AUSWAHL_HANDLE`. `$in_footer = true`.
**An der PHP-Seite ist für AP-4.2 nichts zu tun** — nur JS und CSS.

**Eine Sorge des Agenten ist gegenstandslos:** `render.php` schreibt
`data-same-page="false"`, der Filter **entfernt** das Attribut stattdessen
(so schreibt Vertrag E es vor). `view.js` prüft an beiden Stellen
(`:565`, `:816`) auf `=== 'true'` — Abwesenheit und `"false"` ergeben
gleichermaßen `false`. AP-4.2 muss dafür nichts tun.

**Drei Punkte für AP-3.rev, vom Agenten gemeldet:**

1. Die Klassenzeichenkette `cbd-block-reference-inline` steht ab AP-4.2 an
   **drei** Stellen: `CBD_Inline_Reference::KLASSE`, `className` in
   `format.js`, Klick-Selektor in `view.js`. Kein Duplikatswächter —
   dieselbe Familie wie die dreifache `stableId`-Extraktion. Der Kommentar
   an der Konstante nennt die beiden anderen Stellen.
2. `CBD_Inline_Reference::ziel_href()` und `render.php` bauen die Ziel-URL
   nach **derselben** Regel (Anker → Fragment, sonst
   `add_query_arg('cbd-ref', …)`) an zwei Stellen. Kandidat für eine
   geteilte statische Methode; `render.php` gehörte nicht zu diesem AP.
3. Der Filter läuft auch in Auszügen und Archiven und reiht dort `view.js`
   ein, obwohl der Verweis im Auszug abgeschnitten sein kann. Harmlos (ein
   Footer-Script), aber AP-4.3 soll es beim Messen der Ladezeit im Blick
   behalten.

**Test-Seam, angenommen:** `auswahl_handle($klasse)` und
`format_script_daten($relativ)` sind parametrisiert, damit der Harnisch alle
drei Zweige des Wächters prüfen kann, **ohne** `format.js` anzulegen — die
Datei gehört AP-4.2. Präzedenz: `stable_id_factory` im Block-Serializer.

**Lehre, die der Harnisch selbst gefangen hat:** Die erste Fassung
deklarierte das `CBD_Block_Reference`-Doppel unbedingt; PHP band es beim
Kompilieren (Hoisting), und der Nachweis „fehlender Vertragspartner erzeugt
keinen Fatal Error" wäre wertlos gewesen. Prüfung 3a.0 hat es gemeldet, das
Doppel steht jetzt in einem `eval()`. Dieselbe Fehlerfamilie wie der
`sidebar.php`-Fatal in Theme v1.5.57→58.

---

### AP-3.fix1: `gesperrt` ohne Abfrage je Seite ermitteln

**Modell:** sonnet
**Abhängigkeiten:** AP-3.1
**Dateien:** `includes/class-cbd-blocks-rest-api.php`, `tools/test-seitenbaum.php`
**Anlass:** Befund des Orchestrators bei der Abnahme von AP-3.1.

**Der Befund.** AP-3.1 erfüllt Vertrag B korrekt und AK6 ist im Prüfharnisch
grün — aber **AK6 war zu schwach formuliert.** Der Harnisch stubbt
`simple_clean_seite_nur_lehrpersonen()` und zählt deshalb nur die Abfragen
des Plugins, nicht die der Theme-Funktion. In der Wirklichkeit gilt:

- `simple_clean_seite_nur_lehrpersonen()`
  (`Theme/includes/sichtbarkeit.php:214-236`) prüft zuerst die eigene Meta
  (billig, durch `update_meta_cache()` vorgewärmt), dann
  `simple_clean_gesperrte_seiten()` (memoisiert, **eine** Abfrage) — und
  läuft danach, **sofern überhaupt eine Seite gesperrt ist**, für jede Seite
  durch `get_post_ancestors()` (`:229-233`).
- `get_post_ancestors()` löst die Elternkette über `get_post()` auf. Die
  rohe `$wpdb`-Abfrage aus AP-3.1 füllt den Post-Cache **nicht**. Auf einer
  Installation mit 258 Seiten und mindestens einer gesperrten Seite entstehen
  dadurch bis zu mehrere hundert Einzelabfragen — genau das, was AK6
  verhindern sollte.
- **Nebenbei berichtigt:** Die Übergabenotiz von AP-3.1 vermerkt, die
  Theme-Funktion prüfe „nur die Seite selbst, keine Vererbung über
  Vorfahren". Das ist falsch — `:229-233` vererbt die Sperre auf den
  Unterbaum. Vertrag B ist damit inhaltlich schon richtig, es gibt für
  AP-4.1 nichts zu klären, und der Sperrhinweis in der Auswahl ist korrekt.

**Die Abhilfe.** Das Theme hat für genau diesen Fall bereits eine Funktion:
`simple_clean_gesperrte_seiten_mit_unterbaum()`
(`Theme/includes/sichtbarkeit.php:142-190`) liefert `array<int,bool>` aller
gesperrten Seiten **einschließlich ihres gesamten Unterbaums**, statisch
memoisiert, und kostet insgesamt höchstens zwei Abfragen — unabhängig von der
Seitenzahl. Ist keine Seite gesperrt, entfällt der Baumaufbau dort ganz. Ein
`isset($karte[$id])` ist für Seiten inhaltlich dasselbe wie
`simple_clean_seite_nur_lehrpersonen($id)`.

**Umsetzung:**

1. In `baue_seitenbaum()` bzw. `get_seitenbaum()` die Ermittlung von
   `gesperrt` auf eine **dreistufige** Kette umstellen, jede Stufe hinter
   `function_exists()`:
   1. `simple_clean_gesperrte_seiten_mit_unterbaum()` vorhanden →
      **einmal** aufrufen, Ergebnis als Karte halten, je Knoten
      `isset($karte[$id])`.
   2. sonst `simple_clean_seite_nur_lehrpersonen($id)` je Knoten (heutiges
      Verhalten, als Rückfall erhalten — ein Theme älteren Stands hat die
      Karte womöglich nicht).
   3. sonst durchgehend `false`.
2. Die Wahl der Stufe als Kommentar begründen, mit Verweis auf die
   Abfragenzahl — sonst sieht eine künftige Änderung nur eine überflüssige
   Verzweigung.
3. `update_meta_cache()` bleibt: Stufe 2 braucht es weiterhin, und für
   Stufe 1 kostet es nichts.

**Akzeptanzkriterien:**

- AK1: Ist `simple_clean_gesperrte_seiten_mit_unterbaum()` vorhanden, wird
  sie **genau einmal** je Anfrage aufgerufen und
  `simple_clean_seite_nur_lehrpersonen()` **gar nicht**.
- AK2: Fehlt sie, aber `simple_clean_seite_nur_lehrpersonen()` existiert,
  gilt unverändert das Verhalten aus AP-3.1 (gleiche Ergebnisse).
- AK3: Fehlen beide, ist jedes `gesperrt` `false` und es entsteht kein Fatal
  Error.
- AK4: Eine Unterseite einer gesperrten Seite hat `gesperrt: true`, auch
  wenn sie selbst keine Meta trägt. **Dieser Fall fehlte im Harnisch von
  AP-3.1 vollständig** — er ist der eigentliche Grund, warum der Irrtum in
  der Übergabenotiz nicht aufgefallen ist.
- AK5: **AK6 aus AP-3.1 neu gefasst:** Die Zahl der Abfragen ist auch dann
  konstant, wenn die Sperrprüfung mitgezählt wird. Nachweis im Harnisch über
  einen Zähler auf **beiden** Theme-Funktionen, geprüft mit 5 und mit 50
  Seiten und mit mindestens einer gesperrten Seite.
- AK6: Die 63 bestehenden Prüfungen aus `tools/test-seitenbaum.php` bleiben
  grün und werden **nicht** abgeschwächt.
- AK7: `php tools/check-php74.php` ist grün.

**Tests (TDD):** Die neuen Fälle zu AK1–AK5 zuerst schreiben, Fehlschlag
bestätigen, roter Commit, dann umstellen. Der Stub für
`simple_clean_gesperrte_seiten_mit_unterbaum()` gehört in denselben Abschnitt
des Harnischs wie der bestehende Theme-Stub.

**Nebenbefund, nur zu notieren, nicht zu beheben:** Prüfung 3.0 des
Harnischs sucht das erste `$wpdb->get_results(` **in der ganzen Datei**, nicht
das in `get_seitenbaum()`. Heute gibt es nur eines; kommt später ein zweites
davor, prüft der Test die falsche Zeichenkette. Für dieses AP unerheblich.

**Übergabenotiz:** _(vom Agenten zu füllen)_

---

### AP-3.fix2: `ziel_post_id()` ohne `(int)`-Cast auf überlange Ziffernfolgen

**Modell:** sonnet
**Abhängigkeiten:** AP-3.3
**Dateien:** `includes/class-cbd-inline-reference.php`, `tools/test-inline-reference.php`
**Anlass:** Vorprüfung des Orchestrators mit einer Angriffssonde (40 Fälle,
siehe „Vorprüfung" unter AP-3.rev). Ein Fall schlug fehl.

**Der Befund.** `ziel_post_id()`
(`includes/class-cbd-inline-reference.php`, Zeile ~355) prüft korrekt mit
`ctype_digit()` und lehnt Text, Komma, Vorzeichen, Hex und Exponent
zuverlässig ab — geprüft, alle grün. Eine **20-stellige Ziffernfolge**
besteht `ctype_digit()` aber und läuft danach in `(int) $roh`:

```
data-target-post="99999999999999999999"
→ PHP Warning: The float-string "99999999999999999999" is not
  representable as an int, cast occurred        (PHP 8.1+)
→ $ziel = 9223372036854775807  (also NICHT < 1, gilt als gültig)
→ das Element wird bearbeitet, href zeigt auf eine unmögliche Seite
```

**Das Projekt hat dagegen bereits eine ausdrückliche Regel.**
`includes/class-cbd-design-transfer.php:911-915` begründet in einem
Kommentar, warum dort `filter_var(..., FILTER_VALIDATE_INT)` statt `(int)`
steht — genau wegen dieser Warnung ab PHP 8.1 und der „den Wertebereich
verstümmelten Zahl". AP-3.3 hat diese Lehre nicht mitbekommen.

**Warum es kein Blocker ist:** Der Wert kann nicht aus der Zielauswahl
kommen — die Beitrags-IDs stammen aus `cbd/v1/blocks`. Es braucht
handgeschriebenes Markup. Die Folge ist auch kein Sicherheitsproblem: In der
Wirklichkeit liefert `get_permalink()` für eine unmögliche ID `false`, der
gespeicherte `href` bleibt stehen, und der Modal-Endpunkt autorisiert
unabhängig. **Was bleibt, ist eine PHP-Warnung je Vorkommen im
`debug.log`** — und ein Log voller harmloser Warnungen verdeckt die echten.

**Umsetzung:**

1. In `ziel_post_id()` den `(int)`-Cast durch
   `filter_var($roh, FILTER_VALIDATE_INT)` ersetzen. Die Funktion liefert
   für Werte außerhalb des Wertebereichs `false` — die Ziffernfolge fällt
   damit auf `0` und das Element bleibt **zeichengleich**, was der
   eigentlich gewollten Zusicherung entspricht.
2. `ctype_digit()` **bleibt** als vorgeschaltete Prüfung stehen: Es lehnt
   `+45`, ` 45 ` und `4e2` ab, die `FILTER_VALIDATE_INT` teilweise
   akzeptieren würde. Die beiden Prüfungen ergänzen sich, die eine ersetzt
   die andere nicht.
3. Einen Kommentar setzen, der auf
   `class-cbd-design-transfer.php:911-915` verweist — damit die Regel beim
   nächsten Mal gefunden wird.

**Akzeptanzkriterien:**

- AK1: `data-target-post="99999999999999999999"` lässt das Element
  **zeichengleich** und erzeugt **keine** PHP-Warnung. Nachweis im Harnisch
  über einen eigenen `set_error_handler`, der jede Warnung zum Fehlschlag
  macht.
- AK2: Dasselbe für eine 30-stellige und eine 100-stellige Ziffernfolge.
- AK3: `data-target-post="9223372036854775807"` (genau `PHP_INT_MAX`) wird
  weiterhin als gültige Zahl gelesen — die Grenze wird nicht zu weit
  gezogen.
- AK4: Die Ablehnung von `+45`, `4e2`, `0x2d`, `4,5`, `  `, `-7`, `0`,
  `abc`, leer und fehlend bleibt unverändert.
- AK5: Die 119 bestehenden Prüfungen bleiben grün und werden **nicht**
  abgeschwächt.
- AK6: `php tools/check-php74.php` ist grün. `filter_var` mit
  `FILTER_VALIDATE_INT` ist in PHP 7.4 verfügbar.

**Tests (TDD):** Rote Fälle zuerst. **Zusätzlich in den Harnisch
übernehmen** — die Angriffssonde des Orchestrators hat sie geprüft, sie
gehören aber dauerhaft in den Bestand, nicht in ein Wegwerf-Skript:

| Fall | Erwartung |
|---|---|
| Klasse an einem `<span>` statt `<a>` | zeichengleich |
| `<a>` mit der Klasse **in** einem `<script>`-Block | zeichengleich |
| … in einem `<style>`-Block | zeichengleich |
| … in einem `<textarea>` | zeichengleich |
| Klasse in einem HTML-Kommentar | zeichengleich |
| Klasse als Wert eines fremden Attributs (`alt="… Klasse …"`) | zeichengleich |
| Klasse in Großschreibung | zeichengleich |
| unvollständiges Tag am Textende | zeichengleich |
| zwei Klassen am Element, Reihenfolge erhalten | bearbeitet, `class` unverändert |
| einfache Anführungszeichen / ganz ohne Anführungszeichen am Attribut | bearbeitet |
| verschachtelte `<a>` mit der Klasse | beide bearbeitet, Text heil |

**Übergabenotiz:** _(vom Agenten zu füllen)_

---

### AP-3.rev: Unabhängiges Review Phase 3

**Modell:** opus
**Abhängigkeiten:** AP-3.1, AP-3.2, AP-3.3
**Dateien:** keine — **ausschließlich lesend**

Ausgeführt von einem Agenten, der keines der Phase-3-APs implementiert hat.

**Prüfschwerpunkte, in dieser Reihenfolge:**

1. **Der `the_content`-Filter (Vertrag E).** Der schwerste Risikopunkt des
   Vorhabens — er schreibt in gespeicherten Seiteninhalt. Nachvollziehen,
   dass Inhalte ohne Inline-Verweis den Filter unverändert durchlaufen, und
   zwar auf dem **schnellsten** Weg. Prüfen, ob irgendein Zweig einen
   regulären Ausdruck auf Markup anwendet.
2. **Vertraulichkeit.** Liefert `cbd/v1/seitenbaum` irgendetwas, was
   `cbd/v1/blocks` nicht schon liefert und was eine Rolle ohne
   `edit_posts` sehen könnte? Ist der `permission_callback` gesetzt? Wird
   `simple_clean_seite_nur_lehrpersonen()` hinter `function_exists()`
   aufgerufen? **Ist `class-cbd-block-content-api.php` wirklich unverändert?**
   (`git diff vor-phase-3..HEAD -- includes/class-cbd-block-content-api.php`
   muss leer sein.)
3. **Lastverhalten.** Zahl der Datenbankabfragen der Baum-Route unabhängig
   von der Seitenzahl? Lädt sie `post_content`? Ist `ladeDaten()` wirklich
   memoisiert, oder erzeugt jeder Aufruf ein neues Promise?
4. **Verträge gegen Code.** Vertrag A, B, C, E Feld für Feld gegen die
   Implementierung halten. Abweichungen sind Befunde, auch kleine — AP-4.1
   und AP-4.2 programmieren dagegen.
5. **PHP 7.4.** `php tools/check-php74.php` selbst ausführen, nicht auf die
   Meldung der APs vertrauen.
6. **JS-Syntax.** `node --check` auf jede geänderte JS-Datei.
7. **Übergangsdoppel.** AP-3.2 verschiebt Funktionen aus `index.js`, ohne
   `index.js` zu ändern. Die Liste steht in der Übergabenotiz von AP-3.2 —
   prüfen, ob sie vollständig ist, damit AP-4.1 nichts stehen lässt.
8. **Die drei gemeldeten Doppelungen beurteilen** (Fundstellen in der
   Übergabenotiz von AP-3.3): die Klassenzeichenkette an drei Stellen, die
   URL-Bildungsregel in `ziel_href()` und `render.php`, und ob der Wert von
   `CBD_Block_Reference::AUSWAHL_HANDLE` mit dem Doppel im Harnisch von
   AP-3.3 übereinstimmt (`cbd-block-auswahl`) — beide entstanden zeitgleich
   in getrennten APs und haben sich vor dem Merge nie gesehen. Für jede
   Doppelung: lohnt ein Wächter, oder genügt der Kommentar? Der Plan
   verbietet ausdrücklich, in diesem Vorhaben die bestehende dreifache
   `stableId`-Extraktion aufzuräumen — aber eine **neue** Doppelung ohne
   Wächter ist ein Befund.
9. **Der Umfang von `AP-3.fix1`.** Es entstand erst bei der Abnahme von
   AP-3.1 und hat als einziges AP dieser Phase kein eigenes rotes
   Testfundament aus dem ursprünglichen Plan. Prüfen, ob die 63 Prüfungen
   aus AP-3.1 unverändert grün sind und **nicht** abgeschwächt wurden.

Befunde nach Schwere sortiert melden, je Befund: Fundstelle mit Zeilennummer,
Auswirkung, Vorschlag. Kritische Befunde führen zu `AP-3.fixN`.

#### Vorprüfung des Orchestrators (2026-08-17) — ersetzt dieses AP NICHT

Der erste Anlauf dieses APs brach am Sitzungslimit ab, bevor der Agent den
Plan gelesen hatte. Der Orchestrator hat daraufhin **Prüfschwerpunkt 1**
(der `the_content`-Filter) mit einer eigenen Angriffssonde vorgezogen:
40 Fälle, jeder mit `===` gegen die Eingabe bzw. gegen eine erwartete
Bedingung, gegen die **echte** `WP_HTML_Tag_Processor` aus der Installation
des Testservers. Wegwerf-Skript, absichtlich nicht im Repository.

**Das ist keine Erledigung dieses APs.** Der Orchestrator hat den Plan
geschrieben und die vier APs abgenommen — er ist damit nicht unabhängig, und
Regel 11 verlangt einen frischen Agenten. Die acht übrigen
Prüfschwerpunkte sind unberührt. Was die Sonde erbracht hat, ist ein
Zwischenstand, der Welle 2 nicht freigibt.

**Ergebnis: 39 von 40 Fällen grün.**

| Gruppe | Inhalt | Ergebnis |
|---|---|---|
| A (12) | Klassenzeichenkette vorhanden, aber **kein echter Verweis**: Fließtext, HTML-Kommentar, maskiertes Tag, Klasse an einem `<span>`, `<a>` innerhalb `<script>` / `<style>` / `<textarea>`, Klasse als Teilzeichenkette (`…-gross`, `extra-…`), Großschreibung, unvollständiges Tag am Textende, Klasse in einem `alt`-Attribut | **alle zeichengleich** |
| B (11) | echter Verweis ohne brauchbares Ziel: fehlend, leer, `abc`, `0`, `-7`, `4,5`, `1e3`, `0x2d`, `  `, `+45` | 10 zeichengleich, **1 Fehlschlag** → `AP-3.fix2` |
| C (8) | Verweis mit Ziel: nur die erwarteten Attribute geändert, Linktext, Titel und Umfeld unangetastet, `data-same-page` korrekt gesetzt bzw. entfernt | alle grün |
| D (8) | Struktur-Randfälle: verschachtelte `<a>`, zwei Klassen am Element, einfache und fehlende Anführungszeichen, gültiger neben ungültigem Verweis, Anker gewinnt gegen `cbd-ref`, LaTeX im Linktext, Umlaute und Entities im Umfeld | alle grün |
| E (2) | Masse und Kosten | siehe unten |

**Der eine Fehlschlag** ist der `(int)`-Cast auf eine 20-stellige
Ziffernfolge → `AP-3.fix2`, nicht blockierend.

**Zwei Messwerte, die die Entscheidungen aus Abschnitt 4 bestätigen:**

- Wächter 2 (`strpos` auf die Klassenzeichenkette) kostet auf **89 KB
  Inhalt ohne Verweis 0,02 ms**. Der billige Ausstieg für den häufigsten
  Fall trägt.
- 200 Verweise in einem Inhalt brauchen **73 ms**. Für die Praxis
  reichlich; 200 Inline-Verweise auf einer Seite sind unrealistisch.

**Eine Beobachtung ohne Befundcharakter:** `WP_HTML_Tag_Processor` setzt neue
Attribute **vor** die bestehenden — im Ergebnis steht `aria-haspopup` und
`data-display-mode` links von `class`. Für die Darstellung gleichgültig, und
gespeicherter Inhalt ist davon nicht betroffen (der Filter wirkt nur auf die
Ausgabe). Nur zu wissen, damit niemand eine feste Attributreihenfolge
erwartet.

**Übergabenotiz (erledigt, zweiter Anlauf):** Alle neun Prüfschwerpunkte
abgearbeitet. Selbst gefahren: alle 13 PHP-Harnische, beide Betriebsarten des
Inline-Harnischs, `node --check` auf beide geänderten JS-Dateien,
`check-php74.php`, dazu **zwei eigene Angriffssonden** (33 neue Fälle auf den
Filter, 20 auf `block-auswahl.js`). `git diff` auf
`class-cbd-block-content-api.php`: **0 Byte.**

**Urteil: Welle 2 darf starten**, unter der Bedingung, dass Befund B1 vor
AP-4.1 in den Plantext gezogen wird. **Das ist erledigt** — AP-4.1 Schritt 2
und 3 sind neu gefasst, AK4 verlangt jetzt fünf statt vier Stellen, und AK9
ist neu. Ohne diese Korrektur hätte AP-4.1 eine Doppelung stehen lassen und
im Editor „Keine Container-Blöcke gefunden" behauptet, während welche da
sind — beides mit grünem AK4.

**Aus dem Review in andere APs übernommen:** die Rückgabe von `wert` nach
AP-4.2 Schritt 6 samt AK13, der Duplikatswächter als AK14, die Hierarchie-
Prüfmenge in AK7 von AP-4.3, die Archivmessung und die ausdrückliche
Beurteilung der zwei bekannten Grenzen als Schritt 11 von AP-4.3.

**Bestätigungen, die Gewicht haben** (kein Befund, aber vorher unbewiesen):

- Der Filter ist **idempotent** — zweifache und dreifache Anwendung ist
  byte-identisch. Wichtig, weil `do_shortcode` auf Priorität 11 liegt und ein
  Shortcode `the_content` erneut anwenden kann.
- `<a>` mit der Klasse in `<iframe>`, `<title>`, unbeendetem und bedingtem
  HTML-Kommentar bleibt zeichengleich; in `<code>`, `<pre>`, `<noscript>`,
  `<template>` und `<svg><desc>` wird bearbeitet — richtig, dort ist ein
  `<a>` ein echtes Element.
- **Lastverhalten gemessen, nicht vermutet:** ≤ 4 Abfragen für
  `cbd/v1/seitenbaum` auf 260 Seiten mit gesperrter Seite,
  seitenzahlunabhängig. AP-3.fix1 hat den O(n)-Pfad wirklich beseitigt.
- `ladeDaten()` ist **echt** memoisiert (Objektidentität bei drei
  gleichzeitigen Aufrufen); das Merken geschieht synchron vor `Promise.all`,
  greift also auch bei echter Parallelität.
- Zusicherung 4 aus Vertrag C ist saubere Mechanik: Die manuelle Navigation
  liegt in einer Überschreibung, die genau dann verfällt, wenn `wert` sich
  ändert. Suche und Kaskade sind wirklich zwei Sichten auf einen Zustand.
- Die Zusicherung „genau eine Zeile in `view.js`" **trägt**:
  `entschaerfeInhalt()` arbeitet klassenagnostisch über
  `[data-display-mode="modal"]`, `imModal()` ist ein zweites Netz.
  Modal-in-Modal ist für den Inline-Verweis ohne weitere Änderung
  ausgeschlossen.
- Kein camelCase in irgendeinem `data-`Namen; alle erfüllen
  `data(-[a-z0-9_]+)+` und überleben `wp_kses_post()`.
- Phase 3 fügt **keine vierte** Fassung der `stableId`-Extraktion hinzu.

---

### AP-3.fix3: Antwortform, überflüssige Abfrage und Sortierung der Baum-Route

**Modell:** sonnet
**Abhängigkeiten:** AP-3.1, AP-3.fix1
**Dateien:** `includes/class-cbd-blocks-rest-api.php`, `tools/test-seitenbaum.php`
**Anlass:** Befunde S1, S2 und S5 aus AP-3.rev. Alle drei in derselben Datei,
deshalb ein AP. **Parallel zu Welle 2 zulässig** — kein AP der Phase 4 fasst
diese Dateien an.

**S1 — `kinder` und `knoten` können als JSON-*Liste* statt als Objekt
herausgehen.** `json_encode()` liefert für ein PHP-Array mit den Schlüsseln
`0..n-1` eine JSON-Liste. Gemessen im Review:

```
flach (nur Wurzeln) : {"kinder":[[43,44,45]]}          ← Liste, nicht Objekt
hierarchisch        : {"kinder":{"0":[12],"12":[34]}}  ← Objekt (Regelfall)
leerer Baum         : {"knoten":[],"kinder":[]}
```

`block-auswahl.js:189-191` (`normalisiereBaum`) lehnt Listen ab und ersetzt
sie **stillschweigend** durch `{}`. Vertrag B schreibt ausdrücklich Objekte
vor, und **keine der 82 Prüfungen sieht die JSON-Form** an. Der Fall braucht
`kinder`-Schlüssel von genau `0..n-1` und ist damit praktisch unerreichbar —
aber die Abhilfe ist ein Wort, und ein stiller Datenverlust an einer
Vertragsgrenze soll nicht von Zufall abhängen.

*Umsetzung:* `'knoten' => (object) $knoten` und
`'kinder' => (object) $kinder_gefiltert`. Prüfung ergänzen, die
`json_encode()` der Antwort für eine **flache** Seitenmenge gegen
`{"kinder":{"0":[…]}}` hält — also die Serialisierung prüft, nicht nur das
PHP-Array.

**S2 — `update_meta_cache()` ist in der genutzten Stufe 1 eine überflüssige
Abfrage, und der Kommentar behauptet das Gegenteil.**
`includes/class-cbd-blocks-rest-api.php:392-397` sagt „für Stufe 1 kostet er
nichts". Falsch: Es ist ein `SELECT … WHERE post_id IN (…260 IDs…)`, das
**alle** Postmeta aller Seiten in den Objektcache lädt — während Stufe 1
(`simple_clean_gesperrte_seiten_mit_unterbaum()`) überhaupt keine Meta liest.
Nicht O(n) Abfragen, aber O(n) Datenvolumen bei jedem Öffnen des Editors.

*Umsetzung:* `update_meta_cache()` in den `elseif`-Zweig (Stufe 2)
verschieben, wo es wirklich gebraucht wird. Den Kommentar berichtigen.
**Die Harnisch-Zusicherungen `10.4`, `F1-AK5.2` und `F1-AK5.6` zählen den
Aufruf und müssen mitgezogen werden** — sie erwarten ihn heute in Stufe 1.
Das ist der Grund, warum das ein eigenes AP ist und keine stille Änderung:
Wer nur den Code ändert, macht drei Prüfungen rot und ist versucht, sie
abzuschwächen. Sie sind **umzuformulieren, nicht zu entfernen**: „genau ein
`update_meta_cache()` in Stufe 2, **keiner** in Stufe 1".

*Ausdrücklich zu dokumentieren, nicht zu beheben:* Stufe 2 bleibt in der
Wirklichkeit **O(n) Abfragen** (`get_post_ancestors()` → `get_post()`, der
Post-Cache wird von der rohen Abfrage nicht gefüllt). Das ist laut AK2 von
AP-3.fix1 gewollt und betrifft nur ein Theme älteren Stands — es steht aber
nirgends, dass der Rückfallpfad teuer ist. Ein Kommentar genügt.

**S5 — `orderby => 'menu_order title'` verschlechtert die flache Trefferliste,
ohne seinen Zweck zu erfüllen.** `:76`. Begründet war die Änderung in AP-3.1
mit „damit die Reihenfolge innerhalb einer Ebene nicht willkürlich ist" — die
Ebenenreihenfolge liefert aber **Vertrag B** über `kinder`; `ebenen()` benutzt
die Reihenfolge von `bloecke` für Seiten überhaupt nicht. Wirksam wird die
Sortierung nur dort, wo die Liste **flach** gelesen wird: in der Trefferliste
der Suche (`block-auswahl.js:949`). Dort steht jetzt statt alphabetisch: erst
alle `menu_order = 0` alphabetisch, dann alle `menu_order = 1` usw. — quer
über Beiträge, Seiten und Hierarchieebenen. Für eine Suchtrefferliste ist das
schlechter als vorher.

*Umsetzung:* zurück auf `'orderby' => 'title'`. Die Begründung aus AP-3.1
war ein Denkfehler des Plans, kein Fehler des Agenten; das im Kommentar
festhalten, damit es nicht ein drittes Mal geändert wird.

**Akzeptanzkriterien:**

- AK1: `json_encode()` der Antwort liefert für `knoten` und `kinder` **immer**
  ein Objekt — geprüft für flache Seitenmenge, hierarchische Seitenmenge,
  einzelne Wurzel und leeren Baum.
- AK2: `update_meta_cache()` wird bei vorhandener Stufe 1 **gar nicht**
  aufgerufen, bei Stufe 2 **genau einmal**.
- AK3: Die Abfragenzahl bleibt in beiden Stufen seitenzahlunabhängig (5 und
  50 Seiten, mindestens eine gesperrt).
- AK4: `orderby` ist `title`; eine Prüfung hält die SQL-Zeichenkette darauf
  fest.
- AK5: Die 82 bestehenden Prüfungen bleiben grün. Die drei zu S2 genannten
  werden **umformuliert, nicht entfernt** — die Zahl der Prüfungen darf nicht
  sinken.
- AK6: `php tools/check-php74.php` grün.

**Tests (TDD):** rote Fälle zuerst.

**Übergabenotiz:** _(vom Agenten zu füllen)_

---

### AP-3.fix4: „(gespeichertes Ziel)" darf das Ziel nicht löschen

**Modell:** sonnet
**Abhängigkeiten:** AP-3.2
**Dateien:** `assets/js/block-auswahl.js`, `tools/test-block-auswahl.js`
**Anlass:** Befund S4 aus AP-3.rev. **Parallel zu Welle 2 zulässig** — kein
AP der Phase 4 fasst diese Dateien an.

**Der Befund.** `assets/js/block-auswahl.js:885-892` in Verbindung mit
`:839-845`. Ist `wert` gesetzt, der zugehörige Eintrag aber nicht mehr in
`bloecke` (der Zielblock wurde gelöscht), erscheint in der Blockstufe eine
Option `{label: '(gespeichertes Ziel)', value: wert}`. **Wählt der Nutzer
diese Option, wird sein Ziel gelöscht:** `waehleZiel(wert)` →
`eintragZuSchluessel()` → `null` → `melde(null)` → der Konsument leert das
Ziel. Vertrag C sagt aber: „`null` **beim Abwählen**".

Eine Option, die aussieht wie „das ist dein gespeichertes Ziel", und die beim
Anklicken genau dieses Ziel wegwirft, ist die unangenehmste Sorte Fehler —
sie zerstört Daten bei einer Handlung, die harmlos aussieht. Die Gegenstelle
im Suchfeld macht es richtig (`:969` prüft zusätzlich `aktuellerEintrag`).

*Umsetzung, zwei gleichwertige Wege — einen wählen und begründen:*
1. `:885` um `&& aktuellerEintrag` ergänzen. Dann verschwindet die
   inhaltsleere Option ganz. Vorteil: der Nutzer sieht keine Option, die
   nichts tun kann.
2. `waehleZiel()` bei einem nicht auflösbaren, aber nicht-leeren Schlüssel
   **nichts** melden lassen. Vorteil: die Option bleibt als Anzeige des
   gespeicherten Zustands sichtbar.

Der Weg 1 ist der einfachere; Weg 2 erhält dem Nutzer die Information, dass
überhaupt ein Ziel gespeichert ist. **Entscheidung des Agenten**, mit
Begründung in der Übergabenotiz.

**Akzeptanzkriterien:**

- AK1: Ein gesetzter `wert`, dessen Eintrag nicht in `bloecke` steht, führt
  bei **keiner** Bedienhandlung zu `melde(null)` — außer beim ausdrücklichen
  Abwählen der Leeroption.
- AK2: Das ausdrückliche Abwählen meldet weiterhin `null`.
- AK3: Der Fall „Eintrag ist vorhanden" verhält sich unverändert.
- AK4: `melde()` wird weiterhin **nur** aus `onChange` heraus gerufen, nie aus
  einem Effekt — ein gespeichertes Ziel kann nicht von selbst verloren gehen.
  Das hat AP-3.rev geprüft und ist die Grundlage von AK2/AK6 in AP-4.1; es
  muss so bleiben.
- AK5: Die 133 bestehenden Prüfungen bleiben grün und werden nicht
  abgeschwächt.
- AK6: `node --check` grün, Hausstil eingehalten (kein `import`/`export`,
  kein JSX, ES5).

**Tests (TDD):** rote Fälle zuerst. Der Fall braucht keine React-Umgebung:
`waehleZiel()` und die Optionsbildung sind über die reinen Funktionen und die
Stub-`window`-Technik des Harnischs erreichbar, sofern sie exportiert bzw.
über `ebenen()` erreichbar sind. Ist die Funktion nur intern, ist die
Prüfung über den beobachtbaren Vertrag zu führen — **nicht** durch
Aufweichen der Kapselung.

**Übergabenotiz:** _(vom Agenten zu füllen)_

---

### AP-3.fix5: Führende Nullen dokumentieren, URL-Regel beidseitig kommentieren

**Modell:** sonnet
**Abhängigkeiten:** AP-3.fix2
**Dateien:** `includes/class-cbd-inline-reference.php`,
`blocks/block-reference/render.php`, `tools/test-inline-reference.php`
**Anlass:** Befunde S3 und S7 aus AP-3.rev. **Parallel zu Welle 2 zulässig** —
kein AP der Phase 4 fasst `render.php` an. Vorsicht: AP-4.2 arbeitet im
selben Verzeichnis, aber an `format.js`, `style.css` und `view.js`.

**S3 — `ziel_post_id()` lehnt seit AP-3.fix2 führende Nullen ab, undokumentiert
und ungetestet.** `filter_var('045', FILTER_VALIDATE_INT)` ist **`false`**;
vor fix2 ergab `(int)'045'` = 45. Der Doc-Kommentar (`:338-343`) sagt „Nur
eine reine Ziffernfolge zählt" — `045` **ist** eine. AK4 von AP-3.fix2 listet
die Ablehnungen einzeln auf, `045` fehlt, und keine der 155 Prüfungen deckt
es ab.

Die Auswirkung ist praktisch null: Der Wert kann nicht aus der Zielauswahl
kommen, eine Beitrags-ID hat keine führenden Nullen. **Der Punkt ist nicht
die Auswirkung, sondern dass ein Korrektur-AP eine zweite, unbemerkte
Verhaltensänderung mitgebracht hat.** Genau so entsteht der Zustand, in dem
niemand mehr sagen kann, was eine Funktion eigentlich zusichert.

*Umsetzung:* Die Ablehnung **beibehalten** — sie ist inhaltlich richtig — und
den Doc-Kommentar berichtigen, sodass er sie nennt und begründet. Zwei
Prüfungen ergänzen: `045` und `00000000000000000045` bleiben zeichengleich.
Wer stattdessen normalisieren will (`ltrim($roh, '0')`), muss das begründen;
die Vorgabe ist Dokumentieren, nicht Ändern.

**S7 — die URL-Bildungsregel steht an zwei Stellen, kommentiert ist nur eine.**
`CBD_Inline_Reference::ziel_href()` (`:410-431`) und
`blocks/block-reference/render.php:64-71` bilden die Ziel-URL nach derselben
Regel (Anker gewinnt gegen `cbd-ref`, leerer Bezeichner → nackter Permalink).
`ziel_href()` verweist auf `render.php`; **`render.php` verweist nicht
zurück.** Wer künftig `render.php` ändert, findet die zweite Fassung nicht.

Ein Duplikatswächter lohnt hier **nicht** — er müsste rendern. Ein Kommentar
genügt, **aber nur, wenn er an beiden Stellen steht.**

*Umsetzung:* Eine Kommentarzeile in `render.php` über der Stelle, die auf
`CBD_Inline_Reference::ziel_href()` zeigt und sagt, dass beide Fassungen
zusammen zu ändern sind. Nichts am Verhalten von `render.php`.

**Zusätzlich, eine Wortlautlücke in Vertrag E** (Anmerkung 2 aus AP-3.rev):
Der Vertrag sagt „sonst `?cbd-ref=<stable_id>`". Bei **leerem** Bezeichner
setzt der Code korrekt den nackten Permalink — genau wie `render.php`. Der
Code hat recht, der Vertragstext ist unvollständig. **Diese Korrektur nimmt
der Orchestrator am Plan vor, nicht der Agent.**

**Akzeptanzkriterien:**

- AK1: `045` und `00000000000000000045` lassen das Element zeichengleich; je
  eine Prüfung.
- AK2: Der Doc-Kommentar an `ziel_post_id()` nennt die Ablehnung führender
  Nullen ausdrücklich.
- AK3: `render.php` trägt einen Kommentar, der auf `ziel_href()` zeigt.
  **Sonst ändert sich an `render.php` nichts** — Nachweis: der Diff enthält
  nur Kommentarzeilen.
- AK4: Die 155 Prüfungen bleiben grün, beide Betriebsarten.
- AK5: `php tools/check-php74.php` grün.

**Übergabenotiz:** _(vom Agenten zu füllen)_

---

### Phase 4: Oberfläche an beiden Stellen

### AP-4.1: Hierarchische Zielauswahl in der Seitenleiste des Blocks

**Modell:** sonnet
**Abhängigkeiten:** AP-3.1, AP-3.2, AP-3.rev
**Dateien:** `blocks/block-reference/index.js`

**Ziel:** Der Wunsch des Nutzers für den **bestehenden** Block: „auch beim
Block Blockreferenz möchte ich bei der Seitensuche die Hierarchie zum
filtern haben. Zuerst die erste Ebene, dann tauchen in einer zweiten
Dropdown die Unterseiten auf und dann die Blöcke."

**Ausgangslage:** Die Zielauswahl steht in der Seitenleiste
(`InspectorControls` → `PanelBody`, `:252-311`): ein Suchfeld
(`TextControl`, `:254-261`) und **ein** flaches `SelectControl` (`:263-273`)
mit Optionen `"<Seitentitel> → <Blocktitel>"` (`:192-199`). Datenladung im
`useEffect` (`:125-153`). Der Rest der Seitenleiste (`linkText`, `showIcon`,
`displayMode`) bleibt unberührt.

**Umsetzung:**

1. Das flache `SelectControl` samt eigenem Suchfeld durch
   `window.cbdBlockAuswahl.HierarchieAuswahl` ersetzen. `onWaehle` ruft die
   bestehende Funktion `waehleZiel()` (`:205-248`) mit dem gewählten
   Eintrag — die Zuordnung Listeneintrag → Blockattribute bleibt
   unverändert.
2. Den eigenen `useEffect`-Ladeblock (`:125-153`) entfernen; die Komponente
   lädt selbst. Ebenso `Placeholder`/`Spinner` als Ladeanzeige (`:315-322`),
   soweit sie nur die Zielliste betraf.

   **Und damit auch alles, was aus dem Ladeblock gespeist wurde** (Befund B1b
   aus AP-3.rev). Die Zustände `bloecke`, `laedt` und `fehler` (`:107-117`)
   verlieren ihre Quelle. `bloecke` wäre danach dauerhaft `[]` — und die
   Hinweiszeile im Canvas (`:333-336`) behauptete
   **„Keine Container-Blöcke gefunden", während reichlich vorhanden sind.**
   Betroffen sind mindestens `:268-270`, `:275-278`, `:313-337`. Entweder die
   Zeile ganz weglassen oder ihre Zahl aus der Komponente beziehen; einen
   Zustand stehen zu lassen, den niemand mehr füllt, ist keine Option.
3. **Die nach AP-3.2 doppelt vorhandenen Stellen aus `index.js` entfernen —
   es sind FÜNF, nicht vier** (Befund B1a aus AP-3.rev):

   | Fundstelle | Was | Verwendungen, die mitzuziehen sind |
   |---|---|---|
   | `:62-67` (Docblock ab `:55`) | `schluessel()` | `:170`, `:197`, `:224` |
   | `:69-71` | `text()` | `:98-103`, `:194-196`, `:235-240` — am einfachsten ein lokaler Alias `var text = window.cbdBlockAuswahl.text;` |
   | `:80-92` | `passtZurSuche()` | einziger Aufrufer `:166`, entfällt mit dem Optionsaufbau |
   | `:159-199` | Optionsaufbau, darin die Regel „gewählte Option behalten" (`:180-190`) | ersatzlos, die Komponente bringt beides mit |
   | **`:155-157`** | **`aktuellerWert`** — eine **vierte, wortgleiche Fassung der Schlüsselregel** `<postId>\|<stableId>`, geschrieben von Hand statt über `schluessel()` | wird Prop `wert` von `HierarchieAuswahl`; ersetzen durch `window.cbdBlockAuswahl.schluessel({postId: targetPostId, stableId: targetStableId})` |

   Die letzte Zeile ist der Grund, warum dieser Schritt neu gefasst wurde:
   Sie lag knapp außerhalb der ursprünglichen Liste, ist heute wertgleich mit
   `schluessel()` und hat **keinen Wächter**. AK4 wäre in der alten Fassung
   grün geworden, während die Doppelung stehen bleibt.
4. Wächter: `if (!window.cbdBlockAuswahl)` → eine `Notice` mit dem Hinweis,
   dass der Auswahlbaustein nicht geladen ist, statt eines Absturzes der
   Seitenleiste.
5. `normalisiereModus()` (`:50-53`), `autoLinkText()` (`:76-78`) und alles
   zu `displayMode` bleiben in `index.js` — sie gehören zum Block, nicht zur
   Auswahl.

**Akzeptanzkriterien:**

- AK1: Die Seitenleiste zeigt für eine Seite auf oberster Ebene ein
  Auswahlfeld; nach der Wahl einer Seite mit Unterseiten erscheint ein
  zweites, danach das Block-Auswahlfeld.
- AK2: Ein bereits gespeicherter Block zeigt sein Ziel beim Öffnen korrekt
  an, und die Kaskade steht auf dem Pfad dieses Ziels.
- AK3: Die Suche findet ein Ziel über Seiten- und Blocktitel und stellt die
  Kaskade auf den Trefferpfad.
- AK4: Alle **fünf** in Schritt 3 genannten Stellen existieren in `index.js`
  **nicht mehr**. Nachweis über `grep`, und zwar ausdrücklich auch nach der
  handgeschriebenen Schlüsselregel: `grep -n "'|'" blocks/block-reference/index.js`
  darf nichts liefern.
- AK9: Die Hinweiszeile im Canvas nennt eine **richtige** Zahl oder gar
  keine. Nachweis: Editor mit mindestens einem vorhandenen Container-Block
  öffnen — es darf nicht „Keine Container-Blöcke gefunden" dastehen. Kein
  Zustand (`bloecke`, `laedt`, `fehler`) bleibt zurück, den niemand mehr
  füllt; Nachweis über `grep` auf die drei Namen.
- AK5: `linkText`, `showIcon` und `displayMode` verhalten sich unverändert.
- AK6: Ein gespeicherter Block bleibt nach dem Öffnen und Schließen des
  Editors ohne Änderung gültig — kein „Block enthält unerwarteten Inhalt",
  keine ungewollte Änderungsmarkierung.
- AK7: `node --check blocks/block-reference/index.js` ist grün.
- AK8: Keine `console.log` außerhalb von `window.cbdDebug`.

**Tests:** Manuell im Editor des Testservers, protokolliert in AP-4.3. Für
dieses AP genügt: Editor öffnen, die Kaskade über vier Ebenen durchklicken,
einen Bestandsblock (Seite 55 oder 62) öffnen und die Vorbelegung prüfen,
speichern und erneut öffnen. Zusätzlich `node --check`.

**Übergabenotiz:** _(vom Agenten zu füllen)_

---

### AP-4.2: Blockreferenz als Textformat

**Modell:** opus
**Abhängigkeiten:** AP-3.2, AP-3.3, AP-3.rev
**Dateien:** `blocks/block-reference/format.js` (neu),
`blocks/block-reference/style.css`, `blocks/block-reference/view.js`

**Ziel:** Der Kernwunsch: Text markieren → Schaltfläche neben dem Link-Knopf
→ Dialog mit hierarchischer Auswahl → der markierte Text wird zu einem
Verweis, der den Zielblock als Modal öffnet.

**Ausgangslage und Vorbilder:**

- **Struktur:** `Theme/src/js/glossar-editor.js` ist der vollständige
  Präzedenzfall im Projekt — `registerFormatType` (`:263-268`),
  `RichTextToolbarButton` (`:135-151`), `wp.components.Modal` (`:154-159`),
  Markierung aus `this.props.value` lesen (`:31-53`), `applyFormat`
  (`:103-106`). Die gebaute Datei `Theme/dist/js/glossar-editor.js` beweist,
  dass Vite dort **nichts** hinzubündelt: alle Abhängigkeiten sind
  `wp.*`-Globale. Der Code ist fachlich unverändert übertragbar.
- **Zwei Dinge aus dem Vorbild nicht mitkopieren:** (a) `:36-41` setzt bei
  leerer Markierung einen Fehlerzustand und kehrt zurück, **ohne** den
  Dialog zu öffnen — der Fehler wird aber nur **innerhalb** des Modals
  gerendert (`:168-172`) und ist damit unsichtbar. (b) Die
  Klassenkomponente: hier Hooks, wie in `index.js:22-23`.
- **Modal:** `blocks/block-reference/view.js` bindet den Klick **delegiert**
  auf `document` (`:937`) und hängt ihn an
  `ziel.closest('.cbd-block-reference-link')` (`:857-859`) plus
  `data-display-mode === 'modal'` (`:870`). Es liest ausschließlich
  `data-`Attribute (`:562-566`) — kein Blockkontext. `textAusVerweis()`
  (`:546-553`) fällt auf `link.textContent` zurück, was beim Inline-Format
  genau der markierte Text ist. `entschaerfeInhalt()` (`:473-477`) stuft
  Verweise **im** Modal auf `link` zurück, womit Modal-in-Modal bereits
  ausgeschlossen ist.

**Umsetzung:**

1. Neue Datei `blocks/block-reference/format.js`, IIFE, ES5-Hausstil,
   `registerFormatType` genau nach Vertrag D.
2. `RichTextToolbarButton` mit einem eigenen Dashicon (nicht dem
   Link-Symbol, sonst ist es vom Link-Knopf nicht zu unterscheiden).
   Vorschlag: `dashicons-external` oder `dashicons-media-document`. `isActive`
   über `getActiveFormat(value, 'cbd/block-reference-inline')`.
3. Umschaltlogik wie im Vorbild (`glossar-editor.js:139-149`): ist das
   Format auf der Markierung aktiv, entfernt der Klick es (`removeFormat`);
   sonst öffnet er den Dialog.
4. **Leere Markierung:** Der Knopf ist `disabled`, wenn `value.start ===
   value.end`. Keine unsichtbare Fehlermeldung (Fehler (a) des Vorbilds).
5. **Verschachtelte Links verhindern:** Liegt auf der Markierung ein aktives
   `core/link`, wird der Dialog **nicht** geöffnet; stattdessen eine
   `wp.data.dispatch('core/notices').createNotice('warning', …)`-Meldung mit
   der Begründung. Ein `<a>` in einem `<a>` ist ungültiges HTML.
6. Dialog: `wp.components.Modal` (nicht `Popover`, nicht
   `wp.richText.useAnchor` — letzteres setzt eine WordPress-Version voraus,
   die nicht garantiert ist). Inhalt: `window.cbdBlockAuswahl.HierarchieAuswahl`
   plus zwei `Button` (`variant: 'tertiary'` Abbrechen / `'primary'`
   Übernehmen). `Übernehmen` ist deaktiviert, solange kein Ziel gewählt ist.

   **Der Dialog muss den Wert zurückgeben, den er bekommt** (Befund aus
   AP-3.rev, im Plan zuvor nicht gesagt): Den in `onWaehle` erhaltenen
   Eintrag in lokalen Zustand legen **und** denselben Zustand als Prop `wert`
   an `HierarchieAuswahl` zurückgeben. Die Komponente leitet den Kaskadenpfad
   aus `wert` ab und verwirft ihre manuelle Navigation genau dann, wenn `wert`
   sich ändert (`block-auswahl.js:766-768`, `:803-810`). Ein Dialog, der
   `wert` nicht nachzieht, lässt die Kaskade nach dem ersten Suchtreffer
   stehen — Zusicherung 4 aus Vertrag C hängt am Aufrufer, nicht an der
   Komponente.
7. Anwenden über `applyFormat(value, { type: 'cbd/block-reference-inline',
   attributes: { … } })` → `onChange(neuerWert)`. Die Attribute nach Vertrag
   D; `href` aus `postUrl` + Anker bzw. `?cbd-ref=`, `titel` aus
   `blockTitle`.
8. Wächter: fehlt `window.cbdBlockAuswahl`, wird das Format **nicht**
   registriert und einmalig `console.warn` ausgegeben. Ein Knopf, der einen
   leeren Dialog öffnet, ist schlechter als kein Knopf.
9. `style.css`: neuer Abschnitt für `.cbd-block-reference-inline`.
   **Die bestehenden Regeln `:5-72` bleiben unangetastet** — sie gehören dem
   Block. Der Inline-Verweis sieht wie ein Link aus (`display: inline`,
   Unterstreichung, Textfarbe) und trägt ein kleines unterscheidendes Symbol
   über `::after` — **kein zusätzliches Markup**, weil `registerFormatType`
   nur das `<a>` erzeugt. Kein `transform` beim Überfahren (ruckelt im
   Textfluss).
10. `view.js`: **genau eine Zeile.** Den Selektor `:857-859` auf
    `.cbd-block-reference-link, .cbd-block-reference-inline` erweitern.
    Nichts anderes in dieser Datei.

**Akzeptanzkriterien:**

- AK1: In einem Absatz erscheint neben dem Link-Knopf eine zusätzliche
  Schaltfläche mit eigenem Symbol.
- AK2: Bei leerer Markierung ist die Schaltfläche deaktiviert.
- AK3: Bei aktivem `core/link` auf der Markierung öffnet sich kein Dialog,
  sondern es erscheint eine Warnmeldung.
- AK4: Der Dialog zeigt die hierarchische Auswahl aus AP-3.2; „Übernehmen"
  ist ohne gewähltes Ziel deaktiviert.
- AK5: Nach dem Übernehmen trägt der markierte Text genau die fünf in
  Vertrag D genannten Attribute — und **nicht** `data-display-mode`,
  `data-same-page` oder `aria-haspopup`.
- AK6: Erneuter Klick auf die Schaltfläche bei aktivem Format entfernt den
  Verweis und lässt den Text stehen.
- AK7: Der Absatz bleibt nach dem Speichern und erneuten Öffnen gültig —
  kein „Block enthält unerwarteten oder ungültigen Inhalt".
- AK8: Im Frontend öffnet ein Klick auf den Inline-Verweis das Modal —
  sowohl für ein Ziel auf derselben Seite (DOM-Klon) als auch auf einer
  anderen (Nachladen).
- AK9: Der Inline-Verweis unterbricht den Textfluss nicht; er steht in der
  Zeile wie ein Link.
- AK10: `blocks/block-reference/view.js` hat gegenüber `vor-phase-4` **genau
  eine** geänderte Zeile. Nachweis:
  `git diff vor-phase-4..HEAD -- blocks/block-reference/view.js`.
- AK11: `node --check` auf `format.js` und `view.js` ist grün.
- AK12: In welchen Blocktypen die Schaltfläche erscheint, ist geprüft und
  festgehalten: Absatz, Überschrift, Listenelement, Tabellenzelle, Zitat,
  `core/button`-Beschriftung. Wo sie fehlt, ist der Grund benannt
  (`allowedFormats` bzw. `withoutInteractiveFormatting`) — das ist eine
  Eigenschaft von Gutenberg, kein Befund.
- AK13: Ein Suchtreffer im Dialog stellt die Kaskade auf den Pfad des
  Treffers. Das ist der Nachweis für die Rückgabe von `wert` aus Schritt 6 —
  ohne sie bleibt die Kaskade stehen und der Fehler sieht wie ein Fehler der
  Komponente aus.
- AK14: **Duplikatswächter für die Klassenzeichenkette** (Befund S8 aus
  AP-3.rev). `tools/test-inline-reference.php` bekommt eine Zusicherung, die
  anschlägt, wenn `CBD_Inline_Reference::KLASSE` **nicht** wortgleich in
  `blocks/block-reference/format.js` **und** in `blocks/block-reference/view.js`
  vorkommt. Die Zeichenkette steht ab diesem AP an drei Stellen; der Harnisch
  liest ohnehin Quelltext (Prüfung 1.5), der Wächter kostet drei Zeilen.
  Präzedenz: die `:pN`-Zusicherung in `tools/test-classroom-gate.php`, die
  beim Bauen genau so einen Fall gemeldet hat.

**Tests:** Manuell im Editor und Frontend des Testservers, protokolliert in
AP-4.3, plus `node --check`. Für dieses AP zusätzlich der Rundlauf:
Verweis setzen → speichern → Datenbankinhalt der Seite ansehen (Vertrag D
zeichenweise prüfen) → Frontend aufrufen → Quelltext ansehen (Vertrag E
zeichenweise prüfen) → klicken.

**Übergabenotiz:** _(vom Agenten zu füllen)_

---

### AP-4.3: Abnahme auf dem Testserver

**Modell:** opus
**Abhängigkeiten:** AP-4.1, AP-4.2
**Dateien:** `container-block-designer.php` (nur Versionsnummer),
Prüfseite auf dem Testserver, ZIP

**Ziel:** Nachweisen, dass die Erweiterung auf einer echten Installation
funktioniert, und ein auslieferbares ZIP bauen.

**Umsetzung:**

1. Beide Phasen-Branches nach `main` mergen (Tags `vor-phase-3`,
   `vor-phase-4` vorher setzen).
2. Versionsnummer in `container-block-designer.php` auf **3.1.92** setzen.
   Das ist die **einzige** Stelle, an der in diesem Vorhaben eine Version
   erhöht wird.
3. `php tools/check-php74.php` und `node --check` auf alle geänderten
   JS-Dateien.
4. Alle vier Prüfharnische laufen lassen und die Zahlen festhalten:
   `test-seitenbaum.php`, `test-inline-reference.php`,
   `test-block-auswahl.js`, plus die bestehenden `test-latex-parser.php`,
   `test-block-content-api.php`, `test-icon-position.php`,
   `test-classroom-gate.php` als Regressionscheck.
5. `node create-plugin-zip.js`. **Danach ins ZIP schauen:**
   `assets/js/block-auswahl.js`, `blocks/block-reference/format.js` und
   `includes/class-cbd-inline-reference.php` müssen darin liegen. Prüfen,
   dass der Dev-Autoloader danach wiederhergestellt ist.
6. Plugin auf dem Testserver installieren (nicht kopieren — das ZIP
   installieren, damit der Auslieferungsweg mitgeprüft wird) und die
   Versionsanzeige im Plugin-Menü kontrollieren.
7. **Zwei** Prüfseiten anlegen:
   - **Seite A:** enthält ausschließlich Inline-Verweise, **keinen**
     Blockreferenz-Block. Das ist der Fall, in dem eine fehlende
     Script-Einbindung auffällt.
   - **Seite B:** enthält beides, dazu einen Inline-Verweis auf einen Block
     **derselben** Seite und einen auf eine andere Seite, sowie einen auf
     die gesperrte Seite 64.
8. Eine **Klickliste** schreiben, die der Gliederung der Prüfseiten folgt —
   Abschnittsnummern der Seite, nicht eigene Prüfnummern. In Phase 2 ging
   genau das schief und die Liste passte nicht zur Seite.
9. Prüfen **als Administrator und als Block-Redakteur**. Für den
   Block-Redakteur: Verweis setzen, speichern, danach den Datenbankinhalt
   ansehen — überleben alle fünf Attribute `wp_kses_post()`?
10. Ladezeit beider Editor-Routen mit `curl` messen (Kopfzeile
    `Host: fos.localhost` und `X-WP-Nonce` nicht vergessen) und die Werte
    festhalten. **Zusätzlich eine Archivseite messen** (Befund aus AP-3.rev,
    Anmerkung 14): Der `the_content`-Filter läuft auch in
    `wp_trim_excerpt()` und reiht dort das View-Script ein, obwohl der
    Verweis im Auszug abgeschnitten sein kann. Erwartung: unauffällig — aber
    gemessen, nicht vermutet.
11. **Die zwei bekannten Grenzen ausdrücklich beurteilen** und das Urteil
    festhalten, statt sie zu übergehen:
    a) `ladeDaten()` merkt sich auch den **Fehlerpfad** dauerhaft. Scheitert
       der Abruf beim Editorstart, bleibt die Auswahl die ganze Sitzung leer
       (mit Fehlermeldung); Abhilfe ist ein Neuladen der Seite. Störend?
    b) Ein in einem zweiten Tab angelegter Container-Block erscheint in der
       schon offenen Auswahl erst nach einem Neuladen. Störend?
    Fällt eines von beidem als störend auf, gehört es als `AP-4.fixN` in den
    Vertrag — **nicht** als stille Ergänzung, sie würde AK1 von AP-3.2
    verletzen.
12. Plugin einmal deaktivieren, Frontend von Seite A aufrufen: Die
    Inline-Verweise müssen gewöhnliche Links zum Ziel sein, die Absätze im
    Editor gültig bleiben.

**Akzeptanzkriterien:**

- AK1: Alle Prüfharnische grün, Zahlen im Testprotokoll.
- AK2: Das ZIP enthält die drei neuen Dateien; der Dev-Autoloader ist
  wiederhergestellt; das entpackte `vendor/autoload.php` lädt ohne Fatal.
- AK3: Auf Seite A (ohne Block) öffnet der Inline-Verweis das Modal.
- AK4: Auf Seite B öffnen beide Verweise das Modal — der eine per DOM-Klon
  (kein Netzverkehr im Netzwerk-Tab), der andere per Nachladen.
- AK5: Der Verweis auf die gesperrte Seite 64 zeigt für einen abgemeldeten
  Besucher die Fehlermeldung des Modals, **nicht** den Inhalt. Der Hinweis
  in der Auswahl hat vorher darauf hingewiesen.
- AK6: Als Block-Redakteur gespeichert, stehen alle fünf Attribute aus
  Vertrag D unverändert in der Datenbank.
- AK7: Die Kaskade funktioniert an **beiden** Stellen über vier Ebenen.
  **Zwingend auf einer Prüfmenge mit echter Hierarchie abnehmen, nicht auf
  den flachen Bestandsseiten 43–47** (Befund S1 aus AP-3.rev): Bei einer
  durchweg flachen Seitenmenge hat die Kaskade nur eine Stufe, und ein
  Fehler in den Ebenen darunter fiele nicht auf. Also vorher eine Kette
  Klasse → Fach → Thema → Seite anlegen, mit Container-Blöcken auf mehr als
  einer Ebene.
- AK8: Bei deaktiviertem Plugin sind die Inline-Verweise gewöhnliche Links
  und die Absätze im Editor gültig.
- AK9: Eine Bestandsseite mit Blockreferenz-Block (55, 62) verhält sich
  unverändert.
- AK10: `debug.log` enthält keine neuen Warnungen oder Fehler.
- AK11: Der Nutzer hat die Kaskade beurteilt und die Klickliste
  abgearbeitet.

**Übergabenotiz:** _(vom Agenten zu füllen)_

---

### AP-4.rev: Unabhängiges Review Phase 4

**Modell:** opus
**Abhängigkeiten:** AP-4.3
**Dateien:** keine — **ausschließlich lesend**

Ausgeführt von einem Agenten, der keines der Phase-4-APs implementiert hat.

**Prüfschwerpunkte:**

1. **`view.js` wirklich nur eine Zeile?**
   `git diff vor-phase-4..HEAD -- blocks/block-reference/view.js`.
2. **`class-cbd-block-content-api.php` wirklich unverändert?**
   `git diff vor-phase-3..HEAD -- includes/class-cbd-block-content-api.php`
   muss leer sein. Antworten des Endpunkts weiterhin zeichengleich?
3. **Doppelung.** Existiert Auswahl-Logik nach AP-4.1 nur noch an **einer**
   Stelle? `grep` nach `passtZurSuche`, `schluessel`, `postId + '|'`.
4. **Gespeichertes Markup.** Enthält es `data-same-page`,
   `data-display-mode` oder `aria-haspopup`? Wenn ja: Befund — die frozen
   Werte waren der Grund für Vertrag E.
5. **Die Grenzen des Formats.** Stimmt die Liste aus AK12 von AP-4.2? Gibt
   es einen Blocktyp, in dem das Format erscheint, aber nicht funktioniert?
6. **Der `the_content`-Filter im Betrieb.** Auf einer Seite ohne
   Inline-Verweis: Wird er aufgerufen und kehrt er sofort zurück? Kostet er
   auf einer Seite mit 40 Verweisen messbar Zeit?
7. **Sicherheitsnetze.** Was passiert bei fehlendem
   `window.cbdBlockAuswahl`, fehlendem `WP_HTML_Tag_Processor`, fehlendem
   Theme, gelöschter Zielseite, gelöschtem Zielblock (Seite existiert, aber
   `stableId` nicht mehr)?
8. **Regeln 16–24** aus Abschnitt 0 stichprobenweise gegen den Code.

Befunde nach Schwere sortiert, je Befund Fundstelle mit Zeilennummer,
Auswirkung, Vorschlag. Kritische Befunde führen zu `AP-4.fixN`.

**Übergabenotiz:** _(vom Agenten zu füllen)_

---

### AP-4.doc: Dokumentation und Projektabschluss

**Modell:** sonnet
**Abhängigkeiten:** AP-4.rev
**Dateien:** `CLAUDE.md`, `reference_file_map.md`,
`docs/PLAN-Inline-Blockreferenz.md` (Abschnitte 8–11),
Wurzel-`CLAUDE.md` (nur falls eine neue Naht entstand)

**Umsetzung:**

1. **`Plugins/CDB-Designer/CLAUDE.md`:** Neuer Abschnitt
   „Blockreferenz als Textformat und hierarchische Zielauswahl". Inhalt:
   die fünf Verträge in Kurzform, warum `data-same-page`/`href`/`aria-haspopup`
   serverseitig gesetzt werden, warum `tagName: 'a'` (Glossar-Kollision),
   warum eine eigene CSS-Klasse, warum das View-Script aus dem
   Inhaltsfilter kommt, die Grenzen des Formats (`allowedFormats`,
   `withoutInteractiveFormatting`), und die Prüfharnische mit Fallzahlen.
   Den bestehenden Abschnitt „Block-Referenz als Modul" ergänzen, nicht
   ersetzen — er beschreibt weiterhin den Block.
2. **`window.cbdBlockAuswahl` als fünfte öffentliche Schnittstelle** neben
   `cbdRenderLatex`, `cbdPDFExportServerSide`, `cbdPrepareFormulasForPDF`,
   `cbdRefreshDynamicStyles` in die entsprechende Tabelle aufnehmen — mit
   dem Hinweis, dass sie die **erste** ist, die für den **Editor** gilt.
3. **`reference_file_map.md`:** Zeilen für die drei neuen Dateien plus die
   zwei neuen Prüfharnische. **Mit dem Edit-Werkzeug, nicht per
   PowerShell** (Regel 24).
4. **Wurzel-`CLAUDE.md`:** nur ergänzen, falls eine neue Naht zwischen
   Theme und Plugin entstanden ist. Der Aufruf von
   `simple_clean_seite_nur_lehrpersonen()` in der Baum-Route **ist** eine:
   die dritte Stelle, an der das Plugin aktiv eine Theme-Funktion aufruft
   (nach Klassen-Freigabe und Sichtbarkeitsprüfung im Modal-Endpunkt). Als
   solche benennen.
5. **Abschnitt 8–11 dieses Plans** vervollständigen: Statustabelle, alle
   Testprotokoll-Zeilen, und Abschnitt 11 „Rückblick" mit den vier
   Unterabschnitten (wo der Plan falsch lag, was der Prozess falsch machte,
   was überraschend war, bewusst nicht behoben).
6. **Ausrollreihenfolge** festhalten: Nur ein ZIP (CDB-Designer 3.1.92).
   Kein Block-ZIP, keine Theme-Änderung. Also keine Reihenfolge zu beachten
   — das ausdrücklich sagen, weil die Vorgängerphasen eine hatten.

**Akzeptanzkriterien:**

- AK1: Jede der drei neuen Dateien ist in `reference_file_map.md` verzeichnet.
- AK2: `CLAUDE.md` erklärt alle fünf Verträge und **jede** Entscheidung aus
  Abschnitt 4 dieses Plans, die im Code nicht selbsterklärend ist.
- AK3: Abschnitt 8 und 9 dieses Plans sind vollständig gefüllt — keine
  offenen ◐, keine leeren Testprotokollzeilen.
- AK4: Abschnitt 11 nennt mindestens die bewusst nicht behobenen Punkte.
- AK5: Keine Mojibake in einer der geänderten Markdown-Dateien. Nachweis:
  `grep -c 'Ã\|â€' <datei>` liefert 0 für `CLAUDE.md` und
  `reference_file_map.md`. **Für diesen Plan selbst liefert die Prüfung 1** —
  die Fundstelle ist dieses Akzeptanzkriterium, das das Suchmuster als Text
  enthält. Kein Befund; nicht „reparieren".

**Übergabenotiz:** _(vom Agenten zu füllen)_

---

## 8. Status

Legende: ☐ offen · ◐ in Arbeit · ☑ fertig · ✗ blockiert

| AP | Titel | Modell | Abhängig von | Status |
|---|---|---|---|---|
| AP-3.1 | Hierarchiedaten in den Editor-Routen | sonnet | – | ☑ |
| AP-3.2 | Gemeinsamer Auswahlbaustein `window.cbdBlockAuswahl` | opus | – | ☑ |
| AP-3.3 | Serverseite des Inline-Verweises | opus | – | ☑ |
| AP-3.fix1 | `gesperrt` ohne Abfrage je Seite ermitteln | sonnet | 3.1 | ☑ |
| AP-3.fix2 | `ziel_post_id()` ohne `(int)`-Cast auf überlange Ziffernfolgen | sonnet | 3.3 | ☑ |
| AP-3.rev | Unabhängiges Review Phase 3 | opus | 3.1, 3.2, 3.3, 3.fix1, 3.fix2 | ☑ (2. Anlauf) |
| AP-3.fix3 | Antwortform, überflüssige Abfrage und Sortierung der Baum-Route | sonnet | 3.1, 3.fix1 | ☐ |
| AP-3.fix4 | „(gespeichertes Ziel)" darf das Ziel nicht löschen | sonnet | 3.2 | ☐ |
| AP-3.fix5 | Führende Nullen dokumentieren, URL-Regel beidseitig kommentieren | sonnet | 3.fix2 | ☐ |
| AP-4.1 | Hierarchische Zielauswahl in der Seitenleiste | sonnet | 3.1, 3.2, 3.rev | ☐ |
| AP-4.2 | Blockreferenz als Textformat | opus | 3.2, 3.3, 3.rev | ☐ |
| AP-4.3 | Abnahme auf dem Testserver | opus | 4.1, 4.2 | ☐ |
| AP-4.rev | Unabhängiges Review Phase 4 | opus | 4.3 | ☐ |
| AP-4.doc | Dokumentation und Projektabschluss | sonnet | 4.rev | ☐ |

## 9. Testprotokoll

| AP | Test | Ergebnis | Datum |
|---|---|---|---|
| AP-3.1 | `php tools/test-seitenbaum.php` | **63/63 bestanden** (vom Orchestrator nachgeprüft) | 2026-08-17 |
| AP-3.1 | `php tools/check-php74.php` | grün, 567 Dateien | 2026-08-17 |
| AP-3.1 | Rot-vor-Grün nachweisbar (`85e1bc9` → `3a50704`) | ja; Teständerung dazwischen betraf nur Prüfgruppe 3, vom Orchestrator im Diff geprüft und als Präzisierung auf den Wortlaut von AK3 anerkannt | 2026-08-17 |
| AP-3.1 | SQL lädt kein `post_content` (unabhängig geprüft) | bestätigt, fünf Spalten einzeln, `:281` | 2026-08-17 |
| AP-3.fix1 | `php tools/test-seitenbaum.php` | **82/82 bestanden** (63 + 19 neue). Vom Orchestrator nachgeprüft: der Diff gegen `3a50704` enthält **null Löschungen** — die 63 Bestandsprüfungen sind wörtlich unangetastet | 2026-08-17 |
| AP-3.fix1 | Rot-vor-Grün nachweisbar (`1194e2b` → `e933ee0`) | ja; roter Commit enthält nur den Harnisch, 7 gemeldete Fehlschläge | 2026-08-17 |
| **Phase 3** | Regressionslauf **aller 13** Prüfharnische des Plugins | alle grün (vom Orchestrator gefahren). `test-block-serializer.php` meldet „71 Prüfungen, 0 Fehler" in anderer Formulierung — Exitcode 0, kein Fehlschlag | 2026-08-17 |
| **Phase 3** | `php tools/check-php74.php` nach allen vier APs | grün, 568 Dateien | 2026-08-17 |
| AP-3.2 | `node tools/test-block-auswahl.js` | **133/133 bestanden** (vom Orchestrator nachgeprüft) | 2026-08-17 |
| AP-3.2 | `node --check assets/js/block-auswahl.js` | grün | 2026-08-17 |
| AP-3.2 | Rot-vor-Grün nachweisbar (`5d34cf2` → `5fb4e8c`) | ja; roter Commit enthält **nur** den Harnisch, kein Test nachträglich geändert | 2026-08-17 |
| AP-3.2 | Öffentliche Namen = genau die sieben aus Vertrag C | bestätigt, `block-auswahl.js:1054-1062` | 2026-08-17 |
| AP-3.2 | `blocks/block-reference/index.js` unverändert | bestätigt, `git diff` leer | 2026-08-17 |
| AP-3.2 | Registrierungsreihenfolge trägt die Abweichung | bestätigt: `register_auswahl_script()` läuft vor `register_editor_script()`, `:65-72` | 2026-08-17 |
| AP-3.3 | `php tools/test-inline-reference.php` | **119/119 bestanden**, und zwar zweimal: gegen die echte `WP_HTML_Tag_Processor` und gegen das Doppel (`CBD_TEST_TAG_PROCESSOR=doppel`). Vom Orchestrator beide Wege nachgeprüft | 2026-08-17 |
| AP-3.3 | `php tools/check-php74.php` | grün, 568 Dateien (vom Orchestrator nachgeprüft) | 2026-08-17 |
| AP-3.3 | Rot-vor-Grün nachweisbar (`d2e0597` → `0df80cd`) | ja; roter Commit enthält nur den Harnisch | 2026-08-17 |
| AP-3.3 | AK12: `class-cbd-block-content-api.php` unverändert | bestätigt, `git diff` leer | 2026-08-17 |
| AP-3.3 | `container-block-designer.php` nur zwei funktionale Zeilen | bestätigt, beide hinter `class_exists()` | 2026-08-17 |
| AP-3.3 | `view.js` verkraftet fehlendes `data-same-page` | bestätigt, `=== 'true'` an `:565` und `:816` — keine Anpassung nötig | 2026-08-17 |
| AP-3.rev | Review-Befunde | **✗ erster Anlauf am Sitzungslimit abgebrochen**, bevor der Agent den Plan gelesen hatte | 2026-08-17 |
| AP-3.rev | Review, zweiter Anlauf: alle neun Prüfschwerpunkte | **☑ Welle 2 freigegeben.** 1 blockierender Befund (B1, Plantext — erledigt), 8 „sollte" (→ AP-3.fix3/4/5 und AKs in Welle 2), 14 Anmerkungen. Selbst gefahren: 13 PHP-Harnische, beide Betriebsarten, `node --check`, `check-php74`, plus zwei eigene Angriffssonden (33 + 20 neue Fälle) | 2026-08-17 |
| AP-3.rev | Idempotenz des Filters (zwei- und dreifache Anwendung) | byte-identisch — wichtig wegen `do_shortcode` auf Priorität 11 | 2026-08-17 |
| AP-3.rev | Abfragenzahl `cbd/v1/seitenbaum` in der Wirklichkeit | **≤ 4, seitenzahlunabhängig** bei 260 Seiten mit gesperrter Seite. AP-3.fix1 hat den O(n)-Pfad wirklich beseitigt | 2026-08-17 |
| AP-3.fix3 | S1/S2/S5: JSON-Form, Meta-Cache, Sortierung | – | – |
| AP-3.fix4 | S4: gespeichertes Ziel wird nicht gelöscht | – | – |
| AP-3.fix5 | S3/S7: führende Nullen, URL-Regel beidseitig | – | – |
| AP-3.rev | Vorprüfung des Orchestrators zu Schwerpunkt 1 (Angriffssonde, 40 Fälle) | **39/40**; der eine Fehlschlag → `AP-3.fix2`. Gruppe A (Zeichengleichheit, 12 Fälle) vollständig grün. Ersetzt das AP **nicht** — acht Schwerpunkte offen | 2026-08-17 |
| AP-3.fix2 | `php tools/test-inline-reference.php` | **155/155**, im Doppel-Modus **151/151** mit **4 sichtbar** übersprungenen Fällen. Beides vom Orchestrator nachgeprüft | 2026-08-17 |
| AP-3.fix2 | Angriffssonde des Orchestrators erneut gefahren | **40/40** — der Fehlschlag B9 ist behoben | 2026-08-17 |
| AP-3.fix2 | Die 119 Bestandsprüfungen unverändert | bestätigt: die einzige Löschung im Diff ist die Zusammenfassungszeile, die um den Skip-Zähler erweitert wurde — kein entschärfter Test | 2026-08-17 |
| AP-3.fix2 | `php tools/check-php74.php` | grün, 568 Dateien | 2026-08-17 |
| AP-4.1 | `node --check blocks/block-reference/index.js` | – | – |
| AP-4.1 | Kaskade über vier Ebenen, Bestandsblock | – | – |
| AP-4.2 | `node --check format.js`, `view.js` | – | – |
| AP-4.2 | Rundlauf Vertrag D und E zeichenweise | – | – |
| AP-4.3 | Alle sieben Prüfharnische (Regression) | – | – |
| AP-4.3 | ZIP-Inhalt und Autoloader | – | – |
| AP-4.3 | Klickliste Seite A und B | – | – |
| AP-4.3 | Block-Redakteur: kses-Rundlauf | – | – |
| AP-4.3 | Ladezeit beider Editor-Routen | – | – |
| AP-4.rev | Review-Befunde | – | – |

## 10. Dokumentation

Zu pflegen sind:

- `Plugins/CDB-Designer/CLAUDE.md` — neuer Abschnitt; bestehenden Abschnitt
  „Block-Referenz als Modul" ergänzen
- `Plugins/CDB-Designer/reference_file_map.md` — drei neue Dateien, zwei
  neue Prüfharnische
- `CLAUDE.md` (Wurzel) — nur die neue Theme-Naht
- dieser Plan, Abschnitte 8–11

**Nicht** zu pflegen: `Theme/CLAUDE.md`,
`Plugins/Eigene WP Blocks/CLAUDE.md` — beide Komponenten bleiben unberührt.

## 11. Rückblick

_(nach Abschluss von AP-4.rev durch AP-4.doc zu füllen: wo dieser Plan
selbst falsch lag, was der Prozess falsch gemacht hat, was überraschend war,
Ausrollreihenfolge, bewusst nicht behoben, was gut lief)_
