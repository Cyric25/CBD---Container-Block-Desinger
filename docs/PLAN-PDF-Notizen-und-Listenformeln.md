# Projektplan: PDF-Tafelbilder/Notizen + LaTeX-Formeln in Listen (Accordion)

_Erstellt am: 2026-08-24 · Letzte Aktualisierung: 2026-08-24_

## 0. Anweisungen für den ausführenden Agenten

Du arbeitest nach diesem Plan. Er ist die einzige Wahrheitsquelle – du hast
keinen Zugriff auf das Gespräch, in dem er entstand. Halte dich an diese Regeln:

**Rollen und Modelle:**

A. Wird die Abarbeitung von einem Orchestrator koordiniert (Opus), gilt:
   Der Orchestrator delegiert APs an Subagenten und implementiert NIEMALS
   selbst. Er gibt jedem Subagenten nur dessen AP-Text plus die Abschnitte
   0–4 dieses Plans als Kontext, prüft jede Rückmeldung gegen die
   Akzeptanzkriterien des APs, bevor er abhängige APs freigibt, und pflegt
   die Statustabelle (Abschnitt 8).
B. Jedes AP nennt sein Ausführungsmodell (**Modell:** sonnet | opus).
   Subagenten mit genau diesem Modell starten.
C. **Parallelisierung:** Unabhängige APs (keine gemeinsamen Abhängigkeiten,
   disjunkte Dateien) dürfen parallel bearbeitet werden – in Claude Code
   idealerweise in getrennten Git-Worktrees mit je eigenem Branch (siehe
   Abschnitt 3, Git-Strategie). APs, die dieselbe Datei anfassen, NIE
   parallel vergeben, auch wenn sie inhaltlich unabhängig wären.
D. **Phase 1 und Phase 2 sind voneinander unabhängig** und dürfen von Anfang
   an parallel laufen (siehe Abschnitt 3, „Abweichung von strikter
   Phasenreihenfolge") – sie betreffen unterschiedliche Repositories und
   überschneidungsfreie Dateien.

**Arbeitsweise:**

1. Bearbeite genau EIN Arbeitspaket (AP) pro Auftrag, sofern nicht anders beauftragt.
2. Prüfe vor Beginn die Abhängigkeiten deines APs in der Statustabelle
   (Abschnitt 8). Sind sie nicht ☑, brich ab und melde das.
3. Setze deinen AP-Status auf ◐ (in Arbeit), bevor du beginnst.
4. Bleibe strikt im Scope des APs. Fällt dir Verbesserungspotenzial außerhalb
   auf, notiere es in der Übergabenotiz – setze es nicht um.
5. Beachte die Nicht-Ziele (Abschnitt 2) und Constraints (Abschnitt 3).

**Dateicheckliste – Pflicht bei jedem AP mit mehr als einer Datei:**

6. Jedes AP mit mehreren betroffenen Dateien enthält eine **Dateicheckliste**
   (Checkbox je Datei) direkt im AP-Text. Hake eine Datei SOFORT ab, sobald
   sie fertig UND einzeln getestet ist – **nicht erst am Ende des ganzen
   APs.** Das ist der Mechanismus, der nahtlose Fortsetzung über mehrere
   Sitzungen hinweg ermöglicht.
7. **Wird die Bearbeitung unterbrochen** (Sitzungs-/Nutzungslimit, Absturz,
   Abbruch) und später fortgesetzt – gilt zwingend:
   - Lies NUR die Dateicheckliste des laufenden APs (nicht den ganzen
     bisherigen Chatverlauf, den es ohnehin nicht gibt).
   - Öffne, lies, prüfe oder fasse KEINE bereits abgehakte Datei erneut
     zusammen – das ist bereits erledigte, geprüfte Arbeit. Das spart Tokens.
   - Beginne exakt bei der ERSTEN nicht abgehakten Datei der Liste. Keine
     Rückfrage an den Nutzer nötig, ob fortgesetzt werden soll – einfach
     fortsetzen.
8. **Kein unnötiger Tokenverbrauch:** Fasse beim Fortsetzen bereits erledigte
   APs oder bereits abgehakte Dateien NICHT erneut zusammen, kommentiere sie
   nicht erneut und lies ihren Inhalt nicht zur Kontrolle nach, außer ein
   Test dieser Phase verlangt es ausdrücklich. Die Statustabelle und die
   Dateicheckliste sind die einzige nötige Gedächtnisstütze.

**Tests (Pflicht, ein AP ohne bestandene Tests ist nicht fertig):**

9. Nach Abschluss JEDER Einzeldatei: den für diese Datei im AP genannten
   Prüfschritt durchführen, dann erst abhaken (Regel 6).
10. Nach Abschluss des GESAMTEN APs: alle Akzeptanzkriterien einzeln
    nachweisen + den im AP definierten Gesamt-Smoke-Test durchführen.
11. Ergebnis ins Testprotokoll (Abschnitt 9) eintragen.
12. Erst dann Status auf ☑. Bei Fehlschlag: Status ✗ (blockiert), Ursache in
    die Übergabenotiz, nicht mit abhängigen APs weitermachen.
13. Nach dem letzten Implementierungs-AP einer Phase zusätzlich:
    Integrationstest der Phase + Regressionscheck der jeweils anderen Phase
    (nichts Bestehendes darf kaputtgegangen sein). Eintrag ins Testprotokoll.
14. Danach folgt das Review-AP (`AP-<N>.rev`): frischer, rein lesender Agent,
    der kein AP dieser Phase implementiert hat. Kritische Befunde erzeugen
    Korrektur-APs (`AP-<N>.fix1`, …); die Phase ist erst danach abgeschlossen.

**Übergabe:**

15. Fülle die Übergabenotiz deines APs aus: was geändert wurde, getroffene
    Entscheidungen, was für Folge-APs relevant ist.
16. Hat dein AP Dateien angelegt, verschoben oder wesentlich geändert:
    aktualisiere deren Zeilen in der jeweiligen `reference_file_map.md`
    (`Plugins/CDB-Designer/reference_file_map.md` oder
    `Plugins/Eigene WP Blocks/reference_file_map.md` – je nachdem, welche
    Komponente betroffen ist).
17. Aktualisiere „Letzte Aktualisierung" im Dateikopf dieses Plans.
18. **Git, pro betroffener Komponente (ZWEI getrennte Repos!):**
    CDB-Designer und „Eigene WP Blocks" sind zwei unabhängige
    Git-Repositories (Details Abschnitt 3). Committe im jeweils richtigen
    Repo, mindestens ein Commit je abgeschlossenem AP mit AP-ID im Text,
    z. B. `AP-2.1: Bulk-Endpoint für Seiten-Tafelbilder`. Nach jedem AP den
    Phasen-Branch des betroffenen Repos zum Remote pushen. Phasen-Branches
    erst nach bestandenem Integrationstest UND Review in den Hauptbranch
    (`main`) mergen, danach ebenfalls pushen.
19. **Bekannter Betriebshinweis (aus dem Vorgänger-Plan, ggf. erneut
    prüfen):** Git-Operationen im CDB-Designer-Repo (`git status`, `git diff`
    u. Ä.) können auf manchen Maschinen unabhängig vom Inhalt hängen bleiben
    (vermutlich OneDrive-Cloud-Platzhalter in `.git/objects`), während
    `git rev-parse`/`git ls-remote` funktionieren. Tritt das auf: mit
    `git rev-parse`/`git ls-remote`/gezielten `git add <Einzeldatei>` +
    `git commit` statt `git status`/`git diff` arbeiten; hilft das nicht,
    pausieren und den Befund dem Nutzer melden statt zu erzwingen.

**Umplanung:**

20. Zeigt sich während der Ausführung, dass der Plan nicht trägt (Review-
    Befunde, blockierte APs, falsche Annahmen), werden Korrektur-APs mit
    fortlaufender Nummer ergänzt (`AP-<N>.fix1`, …) und in Statustabelle und
    Testprotokoll aufgenommen. Bestehende APs und Übergabenotizen werden nie
    gelöscht, nur ergänzt – der Plan bleibt nachvollziehbare Historie.

## 1. Projektziel

Zwei unabhängige Erweiterungen an der Website (Plugins CDB-Designer und
„Eigene WP Blocks"): (a) LaTeX-Formeln, die in Listen innerhalb eines
`modular-blocks/accordion`-Panels weiß/unsichtbar dargestellt werden, sind
nach Abschluss korrekt lesbar – geprüft über eine repräsentative Auswahl an
Gutenberg-Blocktypen innerhalb des Accordions. (b) Der serverseitige
PDF-Export von Container-Blöcken (CDB-Designer) schließt sowohl lokale
„Eigene Notizen" (bereits vorhanden) als auch klassenweit serverseitig
gespeicherte „Tafelbilder" ein, gesteuert über einen Schalter im
PDF-Export-Dialog.

## 2. Nicht-Ziele

- Keine Änderung an der Tafelmodus-Zeichenfunktion selbst (`board-mode.js`,
  Zeichenwerkzeuge, Undo, Seiten-Navigation) – nur an der Persistenz-Stelle,
  die für die spätere PDF-Zuordnung nötig ist, und am PDF-Export-Lesepfad.
- Keine Einzelauswahl pro Bild/Container im PDF-Export-Dialog – ein
  einzelner Schalter gilt für den gesamten Export-Lauf (mit dem Nutzer
  geklärt).
- Keine Darkmode-spezifischen Farbkorrekturen am Accordion – laut
  `PLAN-Darkmode-Umschaltung.md`, Phase 3, ist der Accordion-Block bereits
  sichtgeprüft und im Darkmode fehlerfrei. Dieser Plan behandelt
  ausschließlich den hier neu gemeldeten Listen-Fehler, der auch im
  Lightmode auftritt.
- Keine Änderung an `class-latex-parser.php`s Delimiter-Erkennung oder am
  Doppelparse-Schutz – nur an der Textfarbe, falls die Diagnose das als
  Ursache bestätigt.
- Keine neue Fremdbibliothek, kein neuer Build-Prozess-Schritt.
- Kein Login-Test im WordPress-Admin (`wp-admin`) – Passworteingabe ist
  grundsätzlich untersagt. Tests, die einen angemeldeten Nutzer (Capability
  `cbd_edit_blocks`) brauchen, laufen über ein temporäres PHP-Testskript im
  Webroot des lokalen Testservers (`wp-load.php`-Bootstrap +
  `wp_set_current_user()`), niemals über einen `wp-admin`-Login. Nach dem
  Test wird das Skript wieder gelöscht.

## 3. Kontext & Constraints

- **Umgebung:** WordPress 6.0+, CDB-Designer PHP 7.4+ (Zielumgebung 7.4.33,
  `tools/check-php74.php`), Eigene WP Blocks PHP 8.0+.
- **Bestehende Konventionen:** `CLAUDE.md` (Root und je Plugin),
  `DOKUMENTATION.md` (Root), je Plugin eine `reference_file_map.md`. Diese
  haben Vorrang und werden erweitert, nicht ersetzt.
- **Zwei getrennte Git-Repositories, nicht eines:**
  - `Plugins/CDB-Designer/` → `https://github.com/Cyric25/CBD---Container-Block-Desinger`, Branch `main`
  - `Plugins/Eigene WP Blocks/` → `https://github.com/Cyric25/modular-blocks-plugin.git`, Branch `main`
  Phase 1 betrifft primär „Eigene WP Blocks" (Accordion) und ggf. zusätzlich
  CDB-Designer (`latex-formulas.css`), falls die Diagnose in AP-1.1 das
  zeigt. Phase 2 betrifft ausschließlich CDB-Designer.
- **Testumgebung:** Lokale WP-Installation unter `fos.localhost:8080`
  (All-Inkl-Testserver-Simulation, Start/Stop über
  `C:\allinkl-testserver\start-server.cmd` / `stop-server.cmd`). Node.js,
  npm und PHP-CLI sind auf der Entwicklungsmaschine im PATH eingerichtet
  (PHP 8.3 aus dem Testserver-Bundle unter `C:\allinkl-testserver\php\8.3`
  – für den PHP-7.4-Kompatibilitätscheck bleibt `tools/check-php74.php`
  maßgeblich, nicht die lokale PHP-CLI-Version).
  - Der Testserver bedient eine **eigenständige Dateikopie**
    (`C:\allinkl-testserver\www\htdocs\w0000001\fos\wp-content\plugins\...`),
    **keine** Verknüpfung zum Git-Repo. Nach Code-Änderungen vor jedem
    Browser-Test die geänderten Dateien in den entsprechenden Unterordner
    unter `wp-content/plugins/` kopieren.
  - **Wichtig für „Eigene WP Blocks":** Der Block `accordion` registriert
    sein Stylesheet als Build-Artefakt `style-index.css` (`block.json`,
    Feld `"style"`). Vor jedem Test mit
    `grep '"style"' "Plugins/Eigene WP Blocks/blocks/accordion/block.json"`
    prüfen: Steht dort `style-index.css`, ZWINGEND vorher `npm run build`
    ausführen und die Datei `blocks/accordion/style-index.css` (NICHT
    `build/blocks/accordion/...` – der Testserver hat keinen eigenen
    `build/`-Ordner) neu auf den Testserver kopieren. Sonst zeigt der Test
    fälschlich den alten Stand als „bestanden".
  - **`curl`/Kommandozeile lösen `fos.localhost` NICHT auf** – ein echter
    Browser (auch das `mcp__Claude_Browser`-Werkzeug) löst `*.localhost`
    selbst auf. Für Kommandozeilen-Prüfungen ersatzweise
    `curl --resolve fos.localhost:8080:127.0.0.1 http://fos.localhost:8080/`
    verwenden.
  - **Kein `wp-admin`-Login möglich/erlaubt** (siehe Nicht-Ziele). Für
    Tests, die `cbd_edit_blocks` oder eine Klassensitzung brauchen: ein
    temporäres PHP-Skript im Webroot anlegen
    (`C:\allinkl-testserver\www\htdocs\w0000001\fos\ap-<id>-test.php`), das
    `require __DIR__ . '/wp-load.php';` einbindet und Funktionen/Methoden
    bzw. `$wpdb`-Abfragen direkt aufruft. Nach dem Test die Datei WIEDER
    LÖSCHEN – niemals im Webroot liegen lassen.
  - **Für Phase 2 (Tafelbilder) zusätzlich nötig:** Auf dem Testserver muss
    mindestens eine Testklasse mit mindestens einer zugeordneten Seite
    existieren, sonst lässt sich der Server-Pfad nicht live prüfen. Existiert
    keine, legt das jeweils erste AP, das eine Klasse braucht (AP-2.1), sie
    über ein temporäres PHP-Testskript an (direkte `$wpdb->insert()` in
    `CBD_TABLE_CLASSES`/`CBD_TABLE_CLASS_PAGES`, siehe
    `includes/class-cbd-classroom.php`) und vermerkt das in der
    Übergabenotiz, damit Folge-APs sie wiederverwenden können, statt erneut
    anzulegen.
- **Git-Strategie:** Branch pro Phase im jeweils betroffenen Repo
  (`phase-1-latex-listen` in „Eigene WP Blocks" und ggf. CDB-Designer,
  `phase-2-pdf-tafelbilder` in CDB-Designer). Commit pro AP mit AP-ID im
  Text. Parallele APs derselben Phase in getrennten Git-Worktrees desselben
  Repos/Branches, Merge in `main` erst nach Phasen-Review.
- **Abweichung von strikter Phasenreihenfolge (bewusst):** Phase 1 und
  Phase 2 haben keine inhaltliche Abhängigkeit voneinander und dürfen von
  Anfang an parallel laufen. Innerhalb Phase 2 sind AP-2.1 bis AP-2.4
  bewusst gegen fest vorgegebene Schnittstellen (siehe Abschnitt 4)
  geplant, sodass sie ebenfalls von Anfang an parallel beginnen dürfen –
  keines braucht den fertigen Code eines anderen, nur dessen hier bereits
  festgelegten Vertrag. Innerhalb Phase 1 ist AP-1.1 (Diagnose) dagegen eine
  echte Voraussetzung für AP-1.2/AP-1.3, weil die konkrete Fehlerursache vor
  Planungsende nicht bekannt ist.
- **Harte Grenzen:**
  - Keine CDN-Einbindungen (DSGVO), keine neuen Fremdbibliotheken.
  - CSS-Konvention: neue/geänderte Regeln nutzen `var(--x, #fallback)`, nie
    hartcodierte Hex-Werte; `[data-theme="dark"]` statt
    `@media (prefers-color-scheme: dark)`.
  - Lightmode-Aussehen des Accordions darf sich durch den Phase-1-Fix nur an
    der weißen/unsichtbaren Stelle ändern – alle anderen bereits korrekten
    Farben (Zeilenkopf-Weißschrift bei offener Zeile, `data-color-*`) bleiben
    unangetastet.
  - Bestehendes Verhalten des PDF-Exports für lokale „Eigene Notizen" darf
    sich bei eingeschaltetem Schalter nicht ändern (Regressionsschutz).
  - CDB-Designer: PHP 7.4-Kompatibilität zwingend, IMMER
    `tools/check-php74.php` vor jedem ZIP-Bau, ZIP nur über
    `node create-plugin-zip.js`.
  - Eigene WP Blocks: modulare Block-ZIP-Distribution
    (`npm run build && npm run block-zips`), NICHT `npm run plugin-zip`.
  - Ausrollreihenfolge bei Änderungen, die beide Plugins betreffen (nur
    falls AP-1.3 tatsächlich ausgeführt wird): erst `accordion.zip`
    (Eigene WP Blocks), dann das CDB-Plugin-ZIP.

## 4. Architekturentscheidungen

| Entscheidung | Begründung | Verworfene Alternative |
|---|---|---|
| Phase 1: Erst Live-Diagnose (AP-1.1) mit dem bestehenden Skript `Plugins/CDB-Designer/docs/pruefung-formelfarbe.js`, danach Fix (AP-1.2) | Projektkonvention „gemessen, nicht abgeleitet"; das Skript hat einen fast identischen Darkmode-Bug bereits einmal korrekt lokalisiert (siehe DOKUMENTATION.md, Vorhaben „Vier Erweiterungen") | Blind eine der beiden naheliegenden Ursachen (Spezifität vs. Elternkontext) fixen – hohes Risiko, das falsche Symptom zu behandeln |
| Phase 1: Bevorzugte Zielarchitektur ist eine EINMALIGE Grundfarbe auf dem Panel-Wrapper (`.mb-accordion-row__panel-inner` o. Ä.), von der alle Kindelemente erben, statt die bestehende Tag-Enumeration (`h1..h6, p, li, blockquote`) um weitere Tags zu ergänzen | Die Tag-Enumeration hat bereits einmal genau dieses Symptom verursacht (Kommentar in `Plugins/Eigene WP Blocks/CLAUDE.md`, Abschnitt „Farben kommen aus data-color-*"); eine Wrapper-Grundfarbe ist strukturell immun gegen jeden zukünftigen, noch nicht enumerierten Blocktyp. AP-1.2 muss diese Entscheidung anhand des AP-1.1-Befunds final bestätigen oder begründet verwerfen (z. B. falls die Diagnose eine andere Ursache zeigt, die eine Enumeration gar nicht betrifft) | Enumeration um weitere Tags erweitern – schneller, aber löst dieselbe Fehlerklasse nicht grundsätzlich |
| Phase 2: Klassen-Zuordnung eines Containers für den PDF-Export über einen lokalen Begleitschlüssel `localStorage['cbd-board-' + containerId + '-classid']`, geschrieben von `board-mode.js` bei jedem erfolgreichen `saveToServer()`/`loadFromServer()` | Dasselbe, bereits im Projekt etablierte Muster wie der existierende Begleitschlüssel `cbd-board-{id}-bgcolor` (`board-mode.js`, Zeile ~1648-1653); bildet zuverlässig ab, welche Klasse zuletzt tatsächlich mit diesem Container assoziiert war, unabhängig davon, ob beim PDF-Export eine aktive Schüler-Session besteht (die gibt es bei einer exportierenden Lehrperson i. d. R. nicht) | Aktive Seiten-Klassensitzung zum Exportzeitpunkt auslesen – unzuverlässig, weil eine Lehrperson beim PDF-Export meist nur als normaler `cbd_edit_blocks`-Nutzer angemeldet ist, nicht in einer Schüler-Token-Sitzung |
| Phase 2: Neuer AJAX-Handler `wp_ajax_cbd_get_page_drawings` (Action `cbd_get_page_drawings`) statt Wiederverwendung von `ajax_get_page_classroom_data()` | Der bestehende Endpunkt prüft einen Schüler-Transient-Token, keine `cbd_edit_blocks`-Capability – für den PDF-Export (Lehrperson, regulär angemeldet) ist das falsche Sicherheitsmodell. Das SQL-Abfragemuster (alle Drawings einer Seite in einem Query) wird aber unverändert übernommen | `ajax_get_page_classroom_data()` um einen zweiten Auth-Zweig erweitern – hätte zwei Sicherheitsmodelle in einer Methode vermischt, genau das Muster, das das Projekt bei `class-cbd-block-content-api.php` bewusst vermeidet (siehe CLAUDE.md, „eigene Klasse neben class-cbd-blocks-rest-api.php") |
| Phase 2: Signatur `window.cbdPDFExportServerSide(containerBlocks, mode, quality, includeDrawings)` – neuer 4. Parameter, Default `true` wenn `undefined`/nicht übergeben | Rückwärtskompatibel zu bestehenden Aufrufern, die den Parameter (noch) nicht kennen; Default `true` erhält das bisherige unconditional Verhalten für lokale Notizen | Neuer Options-Objekt-Parameter statt Positions-Parameter – wäre sauberer, hätte aber alle bestehenden Aufrufstellen (`floating-pdf-button.js`, Apple-Weiche in `interactivity-store.js`) anfassen müssen; hier bewusst minimal-invasiv |
| Phase 2: EIN Schalter im Export-Dialog für den ganzen Export-Lauf, Default AN | Mit dem Nutzer geklärt (keine Einzelauswahl pro Bild/Container); Default AN bewahrt das bisherige unconditional Verhalten für lokale Notizen als Normalfall | Einzelauswahl pro Container – abgelehnt, da höherer UI-Aufwand ohne klaren Zusatznutzen für den Regelfall |
| Phase 2: Bulk-Abfrage statt ein AJAX-Aufruf pro Container | Eine Seite kann viele Container haben; ein Request pro Container skaliert schlecht (vgl. bereits im Projekt dokumentierte N+1-Vermeidung bei `class-cbd-blocks-rest-api.php`, AP-3.fix1 aus `PLAN-Inline-Blockreferenz.md`) | Ein `cbd_load_drawing`-Aufruf je Container (bereits vorhandener Endpunkt) – einfacher, aber bei vielen Containern langsam und nicht das im Projekt etablierte Muster |

## 5. Risiken & Rollback

| Risiko | Wahrscheinlichkeit | Auswirkung | Gegenmaßnahme / Rollback |
|---|---|---|---|
| AP-1.1 findet eine Ursache, die NICHT durch eine CSS-Änderung an `blocks/accordion/style.css` lösbar ist (z. B. eine Diskrepanz im gerenderten Markup selbst) | gering bis mittel | mittel (AP-1.2 lässt sich nicht wie geplant umsetzen) | AP-1.2 dokumentiert in der Übergabenotiz, falls die vorgesehene Lösung nicht trägt, und der Orchestrator legt ein `AP-1.fix1` mit angepasstem Vorgehen an (Regel 20) |
| Der Wrapper-Grundfarbe-Ansatz (Abschnitt 4) verändert versehentlich die Zeilenkopf-Weißschrift oder andere `data-color-*`-gesteuerte Farben | gering | mittel (sichtbare Regression an einer bereits korrekten Stelle) | AP-1.2 hat einen expliziten Vorher/Nachher-Test des Zeilenkopfs als Akzeptanzkriterium; CSS-Spezifität so wählen, dass der Wrapper nur den PANEL-Inhalt betrifft, nicht den Header |
| Ein Container hat ein Tafelbild für mehrere Klassen, aber der `-classid`-Begleitschlüssel zeigt nur auf die zuletzt genutzte – PDF exportiert dann evtl. nicht die vom Nutzer gemeinte Klasse | mittel | gering (kein Datenverlust, nur falsches/fehlendes Bild im PDF) | Als bekannte Einschränkung in AP-2.doc dokumentieren; PDF-Export bleibt bei fehlendem/falschem Begleitschlüssel beim bisherigen Verhalten (keine Tafelbilder), kein Absturz |
| Bulk-Endpoint `cbd_get_page_drawings` liefert bei fehlerhafter Capability-/Nonce-Prüfung Zeichnungen einer falschen Klasse aus | gering | hoch (Datenschutz – fremde Klassenzeichnung sichtbar) | AP-2.1 hat als Akzeptanzkriterium einen expliziten Negativtest (falscher/fehlender Nonce, fremde `class_id` ohne Zugriff über `can_access_class()`) vor Abschluss; AP-2.rev prüft das gezielt nach |
| Der neue Parameter `includeDrawings` wird an einer bestehenden Aufrufstelle vergessen (z. B. Apple-PDF-Weiche in `interactivity-store.js`) und verhält sich dort abweichend vom Dialog-Schalter | gering | gering (Default `true` erhält dort ohnehin das bisherige Verhalten) | Default-Wert `true` bei fehlendem Parameter deckt das strukturell ab; AP-2.3 listet alle bekannten Aufrufstellen von `cbdPDFExportServerSide` explizit auf und prüft sie |
| Git-Hang im CDB-Designer-Repo (Betriebshinweis Abschnitt 3) blockiert Phase 2 | gering bis mittel (maschinenabhängig) | mittel | Regel 19 in Abschnitt 0; im Zweifel pausieren und dem Nutzer melden statt zu erzwingen |

**Generelle Rollback-Strategie:** Git-Branch pro Phase in jedem betroffenen
Repo; jeder Commit trägt die AP-ID. Ein Fehlschlag führt zum Zurücksetzen des
einzelnen AP-Commits (`git revert`), nicht des ganzen Branches.

## 6. Phasenübersicht

Jede Phase endet mit `AP-<N>.rev` (unabhängiges Review) und `AP-<N>.doc`
(Dokumentation) – in dieser Reihenfolge nach den Implementierungs-APs.

| Phase | Repo(s) | Ziel | Lauffähiger Endzustand | APs |
|---|---|---|---|---|
| 1 | Eigene WP Blocks (+ ggf. CDB-Designer) | LaTeX-Formeln in Listen im Accordion korrekt lesbar, weitere Blocktypen geprüft | Formeln in Listen UND in mind. 5 weiteren getesteten Gutenberg-Blocktypen sind im offenen Accordion-Panel lesbar (kein weißer/unsichtbarer Text) | AP-1.1 … AP-1.4, AP-1.rev, AP-1.doc |
| 2 | CDB-Designer | PDF-Export schließt Tafelbilder (Server) UND eigene Notizen (lokal) ein, steuerbar über einen Schalter | Ein Container mit lokaler Notiz UND einer serverseitigen Klassen-Zeichnung erzeugt bei eingeschaltetem Schalter ein PDF mit beiden Bildern; bei ausgeschaltetem Schalter keines von beiden | AP-2.1 … AP-2.4, AP-2.rev, AP-2.doc |

## 7. Arbeitspakete

### Phase 1: LaTeX-Formeln in Listen im Accordion-Block

#### AP-1.1: Live-Diagnose der Farbkette (Formel in Liste vs. Absatz)

**Status:** ☑ erledigt
**Umfang:** M
**Modell:** opus (Ursachenanalyse, kein vorgezeichneter Lösungsweg)
**Abhängigkeiten:** keine

**Ziel & Kontext:**
Formeln in Listen (`<li>`) innerhalb eines geöffneten
`modular-blocks/accordion`-Panels werden weiß/unsichtbar dargestellt;
dieselbe Formel in einem Absatz (`<p>`) im selben Panel ist korrekt lesbar.
`Plugins/Eigene WP Blocks/blocks/accordion/style.css`, Zeilen 235-244, setzt
die Panel-Textfarbe eigentlich für `h1..h6, p, li, blockquote` gleichermaßen
(`.mb-accordion .mb-accordion__content li { color: var(--color-text-primary,
#333333); }` ist bereits vorhanden). Die Ursache ist daher vermutlich eine
Spezifitäts-/Kaskadenfrage oder eine strukturelle Diskrepanz zwischen der
DOM-Verschachtelung in `<p>` und `<li>` – nicht das Fehlen der Regel selbst.
Bevor irgendetwas verändert wird, muss die tatsächlich gewinnende CSS-Regel
gemessen werden (Projektkonvention „gemessen, nicht abgeleitet"). Das
Diagnoseskript `Plugins/CDB-Designer/docs/pruefung-formelfarbe.js` existiert
bereits, klappt Accordion-Zeilen automatisch auf und meldet für die erste
gefundene „blasse" (auch: weiße) Formel die vollständige Farbkette
inklusive der gewinnenden CSS-Regel je Element. Es muss zum Ausführen nicht
zwingend verändert werden.

**Betroffene Dateien:**
- Testinhalt auf dem Testserver (kein Repo-Datei-Commit nötig, siehe
  Vorgehen Schritt 1) – falls dafür ein neuer Container-Block/Seite auf dem
  Testserver angelegt wird, ist das reine Testserver-Konfiguration, keine
  Code-Datei.
- `Plugins/CDB-Designer/docs/diagnose-latex-listen-<Datum>.md` (neu) –
  Ergebnisbericht der Diagnose, als Grundlage für AP-1.2.

**Vorgehen:**
1. Auf dem Testserver (`fos.localhost:8080`) eine Seite mit einem
   `container-block-designer/container`-Block öffnen (oder anlegen), der
   darin einen `modular-blocks/accordion`-Block enthält. Im Accordion-Panel
   EINE Zeile mit: einem Absatz mit der Formel `$V = 20{,}5\,\text{mL}$` UND
   direkt darunter einer Liste (`core/list`) mit einem Listenpunkt, der
   dieselbe Formel `$V = 20{,}5\,\text{mL}$` enthält. Veröffentlichen.
2. Seite im Browser öffnen (`mcp__Claude_Browser`-Werkzeug verwenden),
   Browser-Konsole öffnen, Inhalt von
   `Plugins/CDB-Designer/docs/pruefung-formelfarbe.js` einfügen und
   ausführen.
3. Das Skript öffnet das Accordion automatisch, wartet auf
   `document.fonts.ready`, misst alle gefundenen `.cbd-latex-formula` und
   gibt für die erste „blasse" (Kriterium: Opacity < 0.9 ODER Farbe im
   Bereich rgb(2XX,2XX,2XX), was auch reines Weiß rgb(255,255,255)
   einschließt) Formel die vollständige Farbkette samt gewinnender
   CSS-Regel je Element aus (Konsolenausgabe + `window.formelBericht` +
   Zwischenablage, falls verfügbar).
4. Ergebnis auswerten: Welches Element in der Kette „springt" auf eine
   andere Farbe um (`>>> HIER SPRINGT DIE FARBE UM` in der Ausgabe), welche
   CSS-Regel (Datei + Selektor) gewinnt dort, und warum unterscheidet sich
   das vom `<p>`-Fall? Falls die Formel im Absatz zufällig NICHT als erste
   „blasse" Formel erkannt wird (weil sie korrekt dunkel ist), das Skript
   zusätzlich manuell für die Absatz-Formel ausführen, indem
   `document.querySelectorAll('.cbd-latex-formula')` im Browser auf die
   Absatz-Formel eingegrenzt wird (z. B. per DevTools-Elementauswahl), um
   deren Farbkette zum Vergleich ebenfalls zu dokumentieren.
5. Befund in `Plugins/CDB-Designer/docs/diagnose-latex-listen-2026-08-24.md`
   festhalten: exakte gewinnende Regel (Datei, Zeile, Selektor, Wert) für
   BEIDE Fälle (Liste und Absatz), die daraus folgende Fehlerursache in
   einem Satz, und eine konkrete Empfehlung für AP-1.2 (welche Datei,
   welcher Selektor muss sich ändern, und ob der in Abschnitt 4
   vorgeschlagene Wrapper-Grundfarbe-Ansatz dazu passt oder ob die Diagnose
   etwas anderes nahelegt).

**Akzeptanzkriterien:**
- [ ] Der Diagnosebericht liegt unter
      `Plugins/CDB-Designer/docs/diagnose-latex-listen-2026-08-24.md` und
      enthält für sowohl die Listen- als auch die Absatz-Formel die
      vollständige Farbkette mit gewinnender Regel (Datei + Selektor +
      Wert).
- [ ] Der Bericht benennt konkret, welche Datei und welcher Selektor in
      AP-1.2 geändert werden muss.
- [ ] Der Bericht bewertet explizit, ob der Wrapper-Grundfarbe-Ansatz aus
      Abschnitt 4 zur gefundenen Ursache passt.

**Tests:**
- Smoke-Test: Das Diagnoseskript läuft ohne JavaScript-Fehler in der
  Konsole durch und gibt „=== Ende ===" aus.
- Prüfschritt: Der Bericht wird gegenprüft, indem die im Bericht genannte
  CSS-Datei geöffnet und die genannte Zeile/der genannte Selektor manuell
  verifiziert wird (steht die Regel wirklich dort und passt der Wert zur
  Konsolenausgabe?).

**Übergabenotiz:**

**ÜBERRASCHENDES ERGEBNIS — Umplanung nötig (Regel 20):** Der gemeldete
Fehler ist auf dem aktuellen Stand beider Repositories **nicht
reproduzierbar** — weder in Listen noch in Absätzen, in keinem von 12
gemessenen Formelvorkommen über 8 Blocktypen (zusätzlich auf einer echten
Produktivseite mit 22 Formeln bestätigt). Der Fehler ist identisch mit
einem bereits behobenen Befund: `AP-1.fix1` aus
`docs/PLAN-Vier-Erweiterungen.md`, behoben am 2026-08-16 mit Commit
`b854060` im Repo „Eigene WP Blocks". Ursache lag in
`assets/css/blocks.css` (weit gefasster Selektor `[class*="content"]`
überschrieb `.cbd-latex-content`s `color: inherit`), **nicht** in
`blocks/accordion/style.css` — die dortige Tag-Aufzählung (Zeilen 235-244)
war nie die Ursache und deckt `li` bereits korrekt ab.

**Der im AP-1.1-Text vermutete Unterschied „Liste kaputt, Absatz in
Ordnung" besteht nicht:** Im rekonstruierten Fehlerzustand sind Absatz UND
Listenpunkt gleichermaßen betroffen — nur die Formel selbst wird weiß,
nicht ihr umgebender Text. Die Wahrnehmung „nur in Listen" erklärt sich
vermutlich daraus, dass eine fehlende Formel in einem kurzen Listenpunkt
auffälliger wirkt als in einem Fließtext-Absatz.

**Warum der Fehler beim Nutzer trotzdem noch auftreten kann:**
`assets/css/blocks.css` liegt in der Plugin-BASIS von „Eigene WP Blocks",
nicht in einem einzelnen Block-ZIP. Die Projekt-Ausrollkonvention sieht vor,
im Regelbetrieb nur Block-ZIPs zu aktualisieren; eine Installation, die seit
dem 2026-08-16 `accordion.zip` aber keine neue Plugin-Basis erhalten hat,
liefert weiterhin den alten, fehlerhaften `blocks.css`-Stand aus — sichtbar
bei jedem Besucher mit dunklem Systemdesign, unabhängig vom Theme-Umschalter
der Seite. **Das ist mit hoher Wahrscheinlichkeit die eigentliche Ursache
der Nutzermeldung — ein Deployment-Rückstand, kein Code-Fehler.**

Vollständiger Bericht mit allen Messwerten, Farbketten und der
Fehler-Rekonstruktion:
`Plugins/CDB-Designer/docs/diagnose-latex-listen-2026-08-24.md`.

**Empfehlung, umgesetzt in AP-1.2/AP-1.3/AP-1.4 (siehe dort):** Beide
geplanten Code-Fixes entfallen, da kein Fehler im Quellstand vorliegt.
AP-1.4s Prüfprotokoll ist durch die 12 hier gemessenen Fälle bereits
erfüllt. Empfohlene tatsächliche Handlung liegt außerhalb des
Code-Scopes dieses Plans: Redeploy der Plugin-Basis „Eigene WP Blocks" auf
der Produktivinstallation (siehe Bericht, Abschnitt 6.2, inkl.
Verifikations-Snippet für die Browser-Konsole). Der Orchestrator hat dies
dem Nutzer gemeldet, statt eigenmächtig auf der Produktivseite zu deployen.

Der in Abschnitt 4 vorgeschlagene Wrapper-Grundfarbe-Ansatz passt laut
Bericht (Abschnitt 6.3) **nicht** zur gemessenen Ursache: Die Vererbung
wäre im Fehlerfall ohnehin unterbrochen, weil `.cbd-latex-content` eine
eigene, direkte Farbzuweisung von `blocks.css` bekommt — eine geerbte
Wrapper-Farbe verliert grundsätzlich gegen jede direkte Zuweisung am
Element selbst.

Git: Branch `ap-1.1-diagnose-formelfarbe` (Commit `7121767`), gemerged nach
`phase-1-latex-listen` (neuer Branch, Basis `main`, Merge-Commit `4a188e8`)
und zu `origin` gepusht. Testseite 5595 auf dem Testserver bleibt bestehen
(Grundlage für etwaige Nachprüfungen).

---

#### AP-1.2: CSS-Fix in `blocks/accordion/style.css`

**Status:** ☑ erledigt (entfällt)
**Umfang:** S
**Modell:** opus (Architekturentscheidung: Wrapper-Grundfarbe bestätigen
oder begründet verwerfen, siehe Abschnitt 4)
**Abhängigkeiten:** AP-1.1 (Diagnosebericht liegt vor)

**Ziel & Kontext:**
Basierend auf dem Diagnosebericht aus AP-1.1
(`Plugins/CDB-Designer/docs/diagnose-latex-listen-2026-08-24.md`) den
CSS-Fehler in `Plugins/Eigene WP Blocks/blocks/accordion/style.css` beheben.
Bevorzugter Zielansatz laut Abschnitt 4: Statt die bestehende
Tag-Enumeration (Zeilen 235-244: `.mb-accordion .mb-accordion__content h1,
... li, ... blockquote { color: var(--color-text-primary, #333333); }`) um
weitere Fälle zu ergänzen, die Grundfarbe EINMAL auf dem Panel-Inhalts-
Wrapper (`.mb-accordion-row__panel-inner`, Zeile ~165, oder
`.mb-accordion__content`) setzen und generisch erben lassen – das macht die
Regel robust gegen jeden nicht enumerierten Blocktyp. Falls der
Diagnosebericht aus AP-1.1 eine andere Ursache zeigt (z. B. eine konkrete
konkurrierende Regel mit höherer Spezifität, die NICHT durch einen
Wrapper-Ansatz gelöst wird), stattdessen die im Bericht empfohlene Lösung
umsetzen und diese Abweichung von Abschnitt 4 in der Übergabenotiz
begründen.

**Betroffene Dateien:**
- `Plugins/Eigene WP Blocks/blocks/accordion/style.css` (ändern)

**Vorgehen:**
1. Diagnosebericht aus AP-1.1 lesen, gewinnende Regel identifizieren.
2. Bei Bestätigung des Wrapper-Ansatzes: `color: var(--color-text-primary,
   #333333);` auf `.mb-accordion .mb-accordion-row__panel-inner` (oder die
   im Bericht genannte konkrete Elternebene) setzen; die bestehende
   Tag-Enumeration (Zeilen 235-244) danach entfernen ODER – falls die
   Enumeration aus anderen Gründen (z. B. Spezifität gegen eine externe
   Regel) weiterhin nötig ist – als zusätzliche Absicherung stehen lassen
   und das in der Übergabenotiz begründen.
3. Sicherstellen, dass die neue Regel NICHT den Zeilenkopf
   (`.mb-accordion-row__header`/`.mb-accordion-row__heading`, weiße Schrift
   bei offener Zeile) beeinflusst – Selektor muss auf
   `.mb-accordion-row__panel-inner` bzw. dessen Nachfahren beschränkt
   bleiben.
4. `var(--color-text-primary, #333333)` verwenden (Projektkonvention, kein
   hartcodierter Hex-Wert).
5. Falls die Diagnose zusätzlich eine Ursache in
   `Plugins/CDB-Designer/assets/css/latex-formulas.css` oder
   `Plugins/CDB-Designer/includes/class-latex-parser.php` zeigt: das NICHT
   in diesem AP beheben, sondern in der Übergabenotiz für AP-1.3 vermerken
   (AP-1.3 ist genau dafür vorgesehen).

**Akzeptanzkriterien:**
- [ ] Auf dem Testserver: Formel im Listenpunkt (aus dem AP-1.1-Testinhalt)
      ist bei geöffneter Accordion-Zeile lesbar dunkel
      (`getComputedStyle` liefert `rgb(51, 51, 51)` oder den aktuellen Wert
      von `--color-text-primary`, NICHT Weiß).
- [ ] Formel im Absatz bleibt weiterhin lesbar dunkel (keine Regression).
- [ ] Zeilenkopf-Weißschrift bei geöffneter Zeile ist unverändert weiß
      (Vorher/Nachher-Vergleich per `getComputedStyle` auf
      `.mb-accordion-row__header`).
- [ ] Kein hartcodierter Hex-Wert in der geänderten/neuen Regel.

**Tests:**
- Smoke-Test: `Plugins/Eigene WP Blocks/blocks/accordion/style.css` lässt
  sich ohne CSS-Syntaxfehler parsen (z. B. `npx postcss-cli
  blocks/accordion/style.css --no-map -o /tmp/out.css` oder ein
  vergleichbarer Parse-Check).
- Prüfschritt: Geänderte Datei auf den Testserver kopieren (Pfad aus
  Abschnitt 3 beachten – falls `block.json` `style-index.css` referenziert,
  vorher `npm run build` und die gebaute Datei kopieren), Testseite aus
  AP-1.1 neu laden, Accordion-Zeile öffnen, Liste UND Absatz per
  `getComputedStyle` auf die tatsächliche Textfarbe der Formel prüfen.
- Regressionstest: Zeilenkopf-Farbe (offen/geschlossen) und mindestens ein
  weiterer, nicht von diesem Fix berührter Accordion-Testfall (z. B. eine
  Formel außerhalb jeder Liste in einer Überschrift) bleiben unverändert
  korrekt.

**Übergabenotiz:**

**Entfällt** — Diagnosebericht AP-1.1
(`docs/diagnose-latex-listen-2026-08-24.md`, Abschnitt 6.1) weist
nach: `blocks/accordion/style.css` enthält keinen Fehler. Die
Tag-Aufzählung `style.css:235-244` deckt `li` bereits ab, gewinnt in
beiden Fällen (Liste und Absatz), ihr gemessener Wert ist korrekt
(`rgb(51,51,51)`). Ein Fix hier würde ein Symptom behandeln, das an dieser
Stelle nicht entsteht — die tatsächliche Ursache lag in
`assets/css/blocks.css` (Repo „Eigene WP Blocks") und ist dort bereits seit
2026-08-16 behoben (Commit `b854060`). Kein Commit in diesem AP. Der
Wrapper-Grundfarbe-Ansatz aus Abschnitt 4 wurde geprüft und als zur
Ursache nicht passend verworfen (AP-1.1-Bericht, Abschnitt 6.3) — er würde
nichts beheben, weil die Vererbung im Fehlerfall bereits eine Ebene tiefer
(direkte Zuweisung auf `.cbd-latex-content`) unterbrochen wäre.

---

#### AP-1.3: Bedingter Fix in CDB-Designer (nur falls AP-1.1 das zeigt)

**Status:** ☑ erledigt (entfällt)
**Umfang:** S
**Modell:** opus (abhängig von Diagnosebefund, kein vorgezeichneter Weg)
**Abhängigkeiten:** AP-1.1, AP-1.2

**Ziel & Kontext:**
Nur relevant, falls der Diagnosebericht aus AP-1.1 (bzw. die
Übergabenotiz von AP-1.2) zeigt, dass die Ursache – ganz oder teilweise –
in CDB-Designer liegt, konkret in
`Plugins/CDB-Designer/assets/css/latex-formulas.css` oder
`Plugins/CDB-Designer/includes/class-latex-parser.php`. Zeigt die Diagnose
das NICHT, entfällt dieses AP ersatzlos – Status dann direkt auf ☑ mit
Übergabenotiz „entfällt, Ursache lag ausschließlich in
blocks/accordion/style.css (siehe AP-1.1/AP-1.2)".

**Betroffene Dateien:**
- `Plugins/CDB-Designer/assets/css/latex-formulas.css` (ändern, falls nötig)

**Vorgehen:**
1. Übergabenotizen von AP-1.1 und AP-1.2 lesen.
2. Entfällt das AP (siehe Ziel & Kontext), Status direkt auf ☑ setzen,
   Übergabenotiz ausfüllen, keine weiteren Schritte.
3. Andernfalls: die im Diagnosebericht benannte Regel in
   `latex-formulas.css` gemäß der dort dokumentierten Empfehlung anpassen,
   mit denselben Prinzipien wie AP-1.2 (`var(--x, #fallback)`, keine
   Kollision mit der Zeilenkopf-Weißschrift des Accordions – diese Datei
   gehört zu CDB-Designer und wird auch außerhalb von Accordions verwendet,
   also zusätzlich prüfen, dass eine Formel AUSSERHALB eines Accordions
   (z. B. in einem normalen Container-Block) weiterhin korrekt aussieht).
4. Ausrollreihenfolge beachten (Abschnitt 3): Diese Änderung gehört zu
   CDB-Designer und ist unabhängig vom `accordion.zip`-Build aus AP-1.2.

**Akzeptanzkriterien:**
- [ ] Entweder: AP als „entfällt" mit Begründung abgeschlossen, ODER:
- [ ] Formel im Listenpunkt innerhalb des Accordions ist lesbar dunkel
      (zusätzlich zum Fix aus AP-1.2, falls dieser allein nicht ausreichte).
- [ ] Formel in einem normalen Container-Block AUSSERHALB des Accordions
      ist weiterhin korrekt lesbar (Regressionstest).
- [ ] Kein hartcodierter Hex-Wert in der geänderten Regel.

**Tests:**
- Smoke-Test (nur falls nicht „entfällt"): `php -l` auf jede geänderte
  PHP-Datei; CSS-Parse-Check wie in AP-1.2.
- Prüfschritt: Testseite aus AP-1.1 erneut prüfen; zusätzlich eine
  bestehende Seite mit einer Formel in einem normalen Container-Block
  (außerhalb jedes Accordions) auf unveränderte korrekte Darstellung
  prüfen.

**Übergabenotiz:**

**Entfällt** — Diagnosebericht AP-1.1, Abschnitt 6.4: In
`assets/css/latex-formulas.css` und `includes/class-latex-parser.php`
wurde kein Fehler gemessen. `.cbd-latex-content`s `color: inherit`
(`latex-formulas.css:56,61`) ist genau richtig — es war das **Opfer** der
fremden Regel aus `blocks.css`, nicht deren Ursache. Eine Härtung
(`color: inherit !important`) wäre denkbar, aber laut Bericht bewusst
nicht empfohlen (Wettrüsten mit `!important` widerspricht der bestehenden
Projektkonvention, siehe Kommentar in `accordion/style.css:211-220`) — bei
Bedarf ein eigenes AP mit eigener Abwägung. Kein Commit in diesem AP.

---

#### AP-1.4: Weitere Gutenberg-Blocktypen im Accordion systematisch prüfen

**Status:** ☑ erledigt (durch AP-1.1 miterledigt)
**Umfang:** M
**Modell:** sonnet (Prüfprotokoll ist unten vollständig vorgegeben, keine
offene Designentscheidung)
**Abhängigkeiten:** AP-1.2 (und AP-1.3, falls nicht „entfällt")

**Ziel & Kontext:**
Verifizieren, dass der Fix aus AP-1.2 (und ggf. AP-1.3) nicht nur den
gemeldeten Listen-Fall behebt, sondern generisch für weitere
Gutenberg-Blocktypen innerhalb eines Accordion-Panels wirkt. Falls der
Wrapper-Grundfarbe-Ansatz umgesetzt wurde, sollte dieser Test praktisch
überall erfolgreich sein; findet sich dennoch ein weiterer weißer/
unsichtbarer Fall, ist das ein echter Zusatzbefund und KEIN Grund, selbst
weiter an der CSS zu schrauben – stattdessen Status ✗ setzen und den Fund
präzise in der Übergabenotiz festhalten (Regel 12 aus Abschnitt 0), damit
der Nutzer/Orchestrator ein `AP-1.fix1` einplant.

**Betroffene Dateien:**
- keine Code-Änderung in diesem AP (reiner Test); ggf. Testinhalt auf dem
  Testserver ergänzen.

**Dateicheckliste (Blocktypen, je einzeln zu prüfen und abzuhaken):**
- [x] `core/quote` (Zitat) mit einer Formel im Zitattext — erledigt via AP-1.1 (Fall 6)
- [x] `core/table` (Tabelle) mit einer Formel in einer Tabellenzelle — erledigt via AP-1.1 (Fall 7)
- [x] `core/heading` (Überschrift, NICHT die Accordion-eigene Zeilenüberschrift)
      mit einer Formel im Überschriftentext, innerhalb eines Panels — erledigt via AP-1.1 (Fall 9, H4)
- [x] `core/columns`/`core/column` mit einer Formel in einer Spalte — erledigt via AP-1.1 (Fall 10)
- [x] verschachtelte Liste (`core/list` mit einer inneren `core/list`
      als Unterpunkt) mit einer Formel im verschachtelten Listenpunkt — erledigt via AP-1.1 (Fall 2/3)
- [x] Bild-Unterschrift (`core/image` mit `Caption`) mit einer Formel im
      Bildunterschrift-Text — erledigt via AP-1.1 (Fall 8, `figcaption`)
- [x] Negativkontrolle `core/code`/`core/preformatted` mit dem Text
      `\(x\)` – hier MUSS laut `class-latex-parser.php`
      (`KEIN_LATEX_BLOCK`) unverändert `\(x\)` als Text erscheinen, KEIN
      gerendertes LaTeX. Dieser Fall prüft, dass der Fix aus AP-1.2/AP-1.3
      die bewusste Ausnahme für Code-Blöcke nicht versehentlich aufhebt.
      — erledigt via AP-1.1 (Negativkontrolle bestanden: kein
      `.cbd-latex-formula`-Element entstanden, Text unverändert)

**Vorgehen:**
1. Für jeden Blocktyp der Dateicheckliste: im Accordion-Panel der
   Testseite aus AP-1.1 eine neue Zeile anlegen (oder die bestehende Zeile
   um den jeweiligen Block ergänzen), Formel `$V = 20{,}5\,\text{mL}$`
   einfügen (außer bei der Negativkontrolle: dort `\(x\)` als reinen Text
   im Code-Block).
2. Seite veröffentlichen, im Browser laden, Accordion-Zeile öffnen.
3. Formelfarbe per `getComputedStyle` auf das gerenderte `.cbd-latex-formula`-
   Element prüfen: erwartet `rgb(51, 51, 51)` (bzw. aktueller
   `--color-text-primary`-Wert), NICHT Weiß oder eine andere zu helle
   Farbe.
4. Bei der Negativkontrolle (Code-Block) stattdessen prüfen: Es existiert
   KEIN `.cbd-latex-formula`-Element innerhalb dieses Code-Blocks, der
   Text `\(x\)` erscheint unverändert als Text.
5. Jeden Punkt der Dateicheckliste erst abhaken, wenn der jeweilige Test
   bestanden ist.
6. Ergebnis je Blocktyp (bestanden/fehlgeschlagen, mit
   `getComputedStyle`-Wert) in der Übergabenotiz auflisten.

**Akzeptanzkriterien:**
- [ ] Alle sieben Punkte der Dateicheckliste einzeln geprüft und
      dokumentiert.
- [ ] Mindestens die ersten sechs (alle außer der Negativkontrolle) zeigen
      lesbaren, dunklen Formeltext. Zeigt einer davon weiterhin weiß/
      unsichtbaren Text: AP-Status auf ✗, präziser Befund (Blocktyp,
      gemessene Farbe, Vermutung zur Ursache) in der Übergabenotiz.
- [ ] Die Negativkontrolle (Code-Block) zeigt weiterhin unverändertes
      `\(x\)` als Text, kein gerendertes LaTeX.

**Tests:**
- Smoke-Test: Testseite lädt ohne JavaScript-Fehler in der Konsole.
- Die sieben Einzelprüfschritte aus dem Vorgehen SIND die Tests dieses APs.

**Übergabenotiz:**

**Umplanung (Regel 20):** Kein eigener Agentenlauf nötig. AP-1.1 hat auf der
eigens angelegten Testseite (ID 5595) bereits 12 Formelvorkommen über 8
Blocktypen gemessen — eine Obermenge dieser Dateicheckliste, inklusive
verschachtelter und nummerierter Listen sowie Display-Formeln
(`$$…$$`) in Absatz und Listenpunkt zusätzlich zu den hier geforderten
sieben Fällen. Alle bestanden: `getComputedStyle` lieferte durchgehend
`rgb(51, 51, 51)` (hell) bzw. `rgb(232, 232, 232)` (dunkel, korrekt
gemeinsam mit der Fläche umgeschaltet), keine weiße/unsichtbare Formel.
Die Negativkontrolle (`core/code`/`core/preformatted` mit `\(x\)`) bestand
ebenfalls: kein `.cbd-latex-formula`-Element entstanden, Text unverändert.
Zusätzlich auf einer echten Produktivseite (5422, 22 Formeln) bestätigt.
Da kein Fix aus AP-1.2/AP-1.3 existiert, gibt es auch nichts, dessen
Generalität AP-1.4 gesondert hätte nachweisen müssen — die Messung war
ohnehin bereits eine Ist-Zustands-Prüfung. Vollständige Tabelle:
`docs/diagnose-latex-listen-2026-08-24.md`, Abschnitt 2.

---

#### AP-1.rev: Unabhängiges Review Phase 1

**Status:** ☑ erledigt
**Umfang:** M
**Modell:** opus
**Abhängigkeiten:** AP-1.1, AP-1.2, AP-1.3, AP-1.4

**Ziel & Kontext:**
Unabhängige Qualitätsprüfung der Phase 1 durch einen Agenten, der an keiner
Implementierung beteiligt war. Nur lesend arbeiten – KEINE Datei verändern.

**Vorgehen:**
1. Diagnosebericht (AP-1.1) gegen die tatsächliche CSS-Änderung (AP-1.2)
   prüfen: Passt der Fix wirklich zur gemessenen Ursache?
2. `Plugins/Eigene WP Blocks/blocks/accordion/style.css` lesen: Wurde die
   Zeilenkopf-Weißschrift versehentlich mitgeändert? Enthält die
   Änderung hartcodierte Hex-Werte statt `var(--x, #fallback)`?
3. Falls AP-1.3 nicht „entfällt": dieselbe Prüfung für die dortige
   CDB-Designer-Änderung, zusätzlich Regressionscheck außerhalb von
   Accordions.
4. AP-1.4s Übergabenotiz gegen die Dateicheckliste prüfen: Sind
   tatsächlich alle sieben Punkte geprüft, nicht nur behauptet?
5. Scope-Check: Wurden Nicht-Ziele aus Abschnitt 2 verletzt (z. B.
   Darkmode-Änderungen, Änderungen an der Delimiter-Erkennung)?
6. Befunde als Review-Bericht in die Übergabenotiz: je Befund
   Schweregrad (kritisch/mittel/gering), betroffenes AP, Datei und
   Fundstelle.

**Akzeptanzkriterien:**
- [ ] Jedes Implementierungs-AP der Phase (AP-1.1–AP-1.4) wurde gegen
      seine Akzeptanzkriterien geprüft.
- [ ] Alle Befunde mit Schweregrad, Datei und Fundstelle dokumentiert.
- [ ] Keine Datei wurde verändert.

**Tests:**
- entfällt (Review-AP; das Ergebnis ist der Bericht).

**Übergabenotiz:**

**Gesamturteil: Die Umplanung trägt — mit einer Einschränkung.** AP-1.2 und
AP-1.3 entfallen zu Recht (unabhängig nachgemessen: kein Fehler in
`blocks/accordion/style.css`, `latex-formulas.css`,
`class-latex-parser.php`), AP-1.4 ist durch AP-1.1 inhaltlich abgedeckt
(alle sieben Checklistenfälle plus Negativkontrolle live auf Seite 5595
nachgewiesen). Commit `b854060` unabhängig verifiziert: existiert,
2026-08-16 20:26, ändert `assets/css/blocks.css` genau wie im Bericht
beschrieben. Fehler-Rekonstruktion selbst nachgebaut (11/12 Formeln weiß,
`accordion/style.css` dabei nachweislich unangetastet). Eigene Live-Messung
auf Testseite 5595 UND Produktivseite 5422 bestätigt: keine blasse Formel
im Ist-Zustand. Scope-Check bestanden (`git diff main...phase-1-latex-
listen` = eine Dokumentationsdatei, null Codezeilen).

**Aber: kritischer Fund B1.** Der in AP-1.1 (Bericht Abschnitt 6.2)
empfohlene nächste Schritt — „Redeploy der Plugin-Basis" — würde den
Fehler **wieder installieren**: Das einzige vorhandene Basis-ZIP
(`plugin-zips/modular-blocks-plugin-empty-1.0.6.zip`, Eintrag
`assets/css/blocks.css` vom 2025-09-25) enthält noch den ALTEN Stand
— ohne `:not([class*="cbd-"])`, mit dem `prefers-color-scheme`-Block, der
`--modular-blocks-text` auf Weiß setzte. Dasselbe gilt für
`modular-blocks-plugin-framework-update.zip`. **Der AP-1.1-Bericht hat nie
geprüft, ob überhaupt ein fehlerfreies Ausroll-Artefakt existiert.**
Abhilfe vorhanden und verifiziert: `npm run plugin-zip-empty` →
`create-empty-plugin-zip.js:112` kopiert `assets/` aus dem aktuellen
Arbeitsbaum (der den Fix bereits enthält) und würde ein korrektes
`modular-blocks-plugin-empty-1.1.8.zip` erzeugen. → **AP-1.fix1**
angelegt.

**Weiterer Fund (mittel, B2):** Die Fehler-Rekonstruktion in AP-1.1
(Bericht Abschnitt 4) stellte nur EINE von vier ursprünglich zu weit
gefassten Regeln in `blocks.css` nach. Drei weitere — u. a.
`[class*="title"]`, das ebenfalls eine Farbe setzt — bekamen ihre
Ausnahme erst mit dem SPÄTEREN Commit `a2737ff` (2026-08-16 21:25). Auf
einer Produktivinstallation mit altem Basisstand ist deshalb ein
zweites, im AP-1.1-Bericht nirgends genanntes Symptom zu erwarten: Der
Titel eines CDB-Container-Blocks INNERHALB eines Accordion-Panels würde
ebenfalls weiß erscheinen. Ändert nichts an der Diagnose (im Quellstand
sind alle vier Regeln repariert), gehört aber zum Fehlerbild, das ein
Redeploy beheben soll — für AP-1.fix1s Verifikationsschritt relevant.

**Geringfügige Funde (B3–B7, dokumentarisch, kein Handlungsbedarf):**
AP-1.1s Behauptung „durchgehend rgb(51,51,51)" übersieht, dass
`figcaption` korrekt, aber abweichend `rgb(102,102,102)`/dunkel
`rgb(160,160,160)` misst (B3); eine Tabellenzeile im Diagnosebericht nennt
für „außerhalb des Accordions, dunkel" fälschlich `rgb(51,51,51)` statt
gemessener `rgb(232,232,232)` (B4); AP-1.4-Checkliste Punkt 6 forderte
`core/image`-Caption, umgesetzt wurde eine Tabellen-Caption über dieselbe
CSS-Klasse — vertretbar, aber nicht als Ersetzung kenntlich gemacht (B5);
die im Bericht gezeigten Farbketten lassen drei Zwischenebenen aus, die
aber nachweislich keine eigene Regel tragen (B6). Außerhalb dieser Phase
gefunden: ein liegengebliebenes Test-PHP-Skript im Webroot aus AP-3.2 von
`PLAN-Darkmode-Umschaltung.md` (B7, nicht Teil dieses Plans, nur gemeldet).

**Rest-Zweifel ohne Handlungsbedarf:** Ein Accordion ohne umgebenden
CDB-Container und eine Formel in der Zeilenüberschrift selbst wurden nicht
gemessen; beide mit begründet vernachlässigbarem Risiko, kein Code-Fix
nahegelegt.

**Nachtrag (derselbe Review-Lauf, nach dem obigen Bericht eingetroffen) —
B1 verschärft:** Auch die entpackte Staging-Kopie
`plugin-zips/modular-blocks-plugin/assets/css/blocks.css` ist der alte
Stand (5574 B, 25.09.2025, null `:not`-Ausnahmen) — es existiert im Repo
**kein einziges** Basis-Artefakt mit korrigiertem CSS, weder gepackt noch
entpackt. Erschwerend: `Eigene WP Blocks/CLAUDE.md`, Abschnitt „Plugin
Distribution Strategy" Punkt 1, nennt genau `modular-blocks-plugin-
empty-1.0.6.zip` NAMENTLICH als die Basis mit dem Zusatz „Never needs to be
updated unless core functionality changes" — ein Redeploy „nach Vorschrift"
installiert den Fehler damit nicht versehentlich, sondern zwangsläufig.
**AP-1.doc muss diesen `CLAUDE.md`-Verweis auf die in AP-1.fix1 neu gebaute
Version nachziehen**, sonst greift beim nächsten Redeploy derselbe
Irrläufer erneut.

**Nachtrag — B2 belegt statt nur plausibel:** `blocks/accordion/
view.js:153-159` dokumentiert die Regelfamilie bereits im Code —
Kommentar: „Das globale Stylesheet des Plugins (assets/css/blocks.css)
faerbt alle Elemente mit ‚title' im Klassennamen im Dunkelmodus weiss. Ohne
Inline-Wichtigkeit waeren geschlossene Titel auf hellem Kopf unlesbar." Das
Accordion verteidigt sich also schon heute mit Inline-`!important` gegen
genau die `[class*="title"]`-Regel, die erst `a2737ff` entschärft hat — das
zweite Symptom auf Altbestands-Installationen ist damit belegt, nicht nur
hergeleitet.

**Neuer Befund B8 (gering, dokumentarisch):** `Eigene WP Blocks/
CLAUDE.md`, Abschnitt „Farben kommen aus data-color-*": Die Anweisung
„Wer dort ein Element ergänzt, das Text zeigt, muss es in diese Aufzählung
aufnehmen" ist auf dem heutigen Stand messbar überholt — die Aufzählung in
`accordion/style.css` wurde zur Laufzeit abgeschaltet, alle 12 Formeln
blieben unverändert, weil `blocks.css:106-110` die Grundfarbe bereits eine
Ebene weiter außen stellt. Kein Fehler, aber eine Konvention, die künftige
Agenten zu unnötigen Enumerationserweiterungen anleiten würde. **Gehört in
AP-1.doc**, kein eigener Fix nötig.

**Fazit:** Phase 1 ist erst abgeschlossen, wenn AP-1.fix1 erledigt ist —
sonst bliebe der empfohlene nächste Schritt in seiner ursprünglichen Form
schädlich und Projektziel 1a auf der Produktivinstallation unerfüllt.
AP-1.doc trägt zusätzlich zwei Dokumentationskorrekturen: den
`CLAUDE.md`-Versionsverweis (B1-Nachtrag) und den überholten
Enumerations-Hinweis (B8). Keine Datei wurde während dieses Reviews
verändert.

---

#### AP-1.fix1: Basis-ZIP von „Eigene WP Blocks" mit korrigiertem `blocks.css` neu bauen

**Status:** ☑ erledigt (nach Korrektur durch den Orchestrator)
**Umfang:** S
**Modell:** sonnet (Build-Skript ausführen und Inhalt verifizieren, klar
vorgezeichnet durch den AP-1.rev-Befund)
**Abhängigkeiten:** AP-1.rev (Befund B1)

**Ziel & Kontext:**
AP-1.rev hat festgestellt, dass das einzige vorhandene, ausrollbare
Basis-ZIP von „Eigene WP Blocks"
(`Plugins/Eigene WP Blocks/plugin-zips/modular-blocks-plugin-empty-1.0.6.zip`)
noch den alten, fehlerhaften Stand von `assets/css/blocks.css` enthält —
ohne die Ausnahme `:not([class*="cbd-"])` in den `[class*="content"]`- UND
`[class*="title"]`-Regeln, mit dem `@media (prefers-color-scheme:
dark)`-Block, der `--modular-blocks-text` auf `#ffffff` setzte. Der aktuelle
Quellstand (`main`-Branch) enthält den Fix bereits vollständig (Commits
`b854060` und `a2737ff` vom 2026-08-16). Dieses AP baut ein neues,
korrektes Basis-ZIP aus dem aktuellen Quellstand und verifiziert dessen
Inhalt — **es lädt NICHTS auf die Produktivinstallation hoch**, das bleibt
eine bewusste, separate Entscheidung des Nutzers (Nicht-Ziel: kein
eigenmächtiges Produktiv-Deployment).

**Betroffene Dateien:**
- `Plugins/Eigene WP Blocks/plugin-zips/modular-blocks-plugin-empty-1.1.8.zip` (neu, generiert)

**Vorgehen:**
1. Im Verzeichnis `Plugins/Eigene WP Blocks/`: `npm run plugin-zip-empty`
   ausführen (nutzt `create-empty-plugin-zip.js`, das laut AP-1.rev-Befund
   `assets/` aus dem aktuellen Arbeitsbaum in das ZIP kopiert).
2. Das erzeugte ZIP öffnen (z. B. `unzip -p
   modular-blocks-plugin-empty-1.1.8.zip '*/assets/css/blocks.css'` oder
   gleichwertig) und den Inhalt von `assets/css/blocks.css` darin
   gegenprüfen:
   - Die Regeln um Zeile 106-110 UND die `[class*="title"]`-Regel
     (siehe AP-1.rev-Befund B2, Commit `a2737ff`) tragen die Ausnahme
     `:not([class*="cbd-"])`.
   - Es existiert **kein** `@media (prefers-color-scheme: dark)`-Block, der
     `--modular-blocks-text` setzt (dieser wurde mit `b854060` entfernt).
   - `--modular-blocks-text` folgt via `var(--color-text-primary, …)` dem
     Theme (Zeile ~9), nicht einem hartcodierten Weißwert.
3. Zusätzlich `php -l` bzw. eine Kurzprüfung der Plugin-Hauptdatei und
   `includes/class-block-manager.php` im ZIP (Basisdateien, sollten sich
   gegenüber `1.0.6` nur in unwesentlichen Details unterscheiden — kein
   funktionaler Regressionscheck nötig, da nur `assets/` sich fachlich
   ändert).
4. **Nicht hochladen, nicht deployen.** Das ZIP bleibt im Repo unter
   `plugin-zips/` liegen, exakt wie die bestehenden älteren ZIPs dort auch
   liegen (siehe `reference_file_map.md`, Abschnitt „Kern und
   Infrastruktur": „plugin-zips/ – erzeugte Auslieferungs-ZIPs").

**Akzeptanzkriterien:**
- [ ] `modular-blocks-plugin-empty-1.1.8.zip` existiert unter
      `Plugins/Eigene WP Blocks/plugin-zips/`.
- [ ] `assets/css/blocks.css` im ZIP enthält `:not([class*="cbd-"])` sowohl
      bei der `[class*="content"]`- als auch bei der
      `[class*="title"]`-Regel.
- [ ] `assets/css/blocks.css` im ZIP enthält KEINEN
      `@media (prefers-color-scheme: dark)`-Block mit
      `--modular-blocks-text: #ffffff` (bzw. gar keinen
      `prefers-color-scheme`-Block, der diese Variable setzt).
- [ ] Keine Produktionsumgebung wurde berührt (kein Upload, kein Deploy).

**Tests:**
- Smoke-Test: `npm run plugin-zip-empty` läuft ohne Fehler durch, ZIP-Datei
  entsteht mit plausibler Größe (vergleichbar mit dem alten `1.0.6`-ZIP).
- Prüfschritt: Inhalt von `assets/css/blocks.css` aus dem neuen ZIP mit dem
  aktuellen Arbeitsbaum-Stand derselben Datei per `diff` vergleichen —
  muss identisch sein (das ZIP wurde ja direkt daraus gebaut).
- Kein Live-Test auf dem Testserver nötig für dieses AP (reine
  Build-Verifikation); ein etwaiger Produktiv-Rollout ist ausdrücklich
  NICHT Teil dieses APs.

**Übergabenotiz:**

**Zwei Planungsannahmen widerlegt, beide vom Subagenten korrekt gemeldet
statt stillschweigend „passend gemacht":**

1. **Dateiname bleibt `-1.0.6.zip`, nicht `-1.1.8.zip`.**
   `create-empty-plugin-zip.js` hat die Versionsnummer hart im Skript
   codiert (`VERSION = '1.0.6'`), nicht aus `package.json` (dort: 1.1.8)
   abgeleitet. Das Skript erzeugt also weiterhin eine Datei namens
   `modular-blocks-plugin-empty-1.0.6.zip` — nur mit jetzt korrigiertem
   Inhalt. Der `CLAUDE.md`-Verweis auf genau diesen Dateinamen (siehe
   AP-1.doc unten) bleibt damit **namentlich korrekt** und muss NICHT auf
   „1.1.8" geändert werden — die AP-1.rev-Annahme dazu war falsch, hiermit
   richtiggestellt. Separater, nicht in diesem AP behobener Befund: Der
   irreführende Versionsstillstand im Dateinamen (Inhalt stammt faktisch
   aus Quellstand 1.1.8) ist ein eigenständiges, künftiges Aufräum-AP wert
   (`VERSION` aus `package.json` ableiten).
2. **Der Subagent baute zunächst in einem isolierten Git-Worktree — dort
   fehlt `assets/js/vendor/` (ChemViz-Fremdbibliotheken `3Dmol-min.js`,
   `plotly-2.27.1.min.js`, `imagetracer.js`, zusammen ≈4,17 MB), weil dieses
   Verzeichnis per `.gitignore` nicht versioniert ist und nur lokal im
   Haupt-Arbeitsverzeichnis existiert.** Das erste ZIP war dadurch nur
   49.070 Bytes statt der erwarteten ~1,25 MB — der im Plan geforderte
   Smoke-Test „plausible Größe" schlug entsprechend fehl, was der Subagent
   korrekt als Befund meldete, statt es zu verschweigen. **Der
   Orchestrator hat das ZIP daraufhin selbst im Haupt-Arbeitsverzeichnis
   neu gebaut** (dort ist `assets/js/vendor/` bereits lokal vorhanden,
   keine externen CDN-Downloads nötig): `npm run plugin-zip-empty` in
   `Plugins/Eigene WP Blocks/` → `modular-blocks-plugin-empty-1.0.6.zip`,
   **1,25 MB**, verifiziert per `diff`: `assets/css/blocks.css` im ZIP
   zeichengleich mit dem Quellstand, 4 Treffer für
   `:not([class*="cbd-"])`, `vendor/`-Verzeichnis vollständig enthalten
   (3Dmol-min.js, plotly-2.27.1.min.js, imagetracer.js, README.md).

**Ergebnis:** `Plugins/Eigene WP Blocks/plugin-zips/modular-blocks-plugin-
empty-1.0.6.zip` (1,25 MB, Stand 2026-08-24) ist jetzt ein vollständiges,
korrektes, lokal verfügbares Basis-Artefakt — CSS-Fix UND ChemViz-Vendor-
Bibliotheken enthalten. Es ist **nicht** in Git versioniert (Projekt-
konvention: `plugin-zips/` ist reines, generiertes Ausgabeverzeichnis,
siehe `reference_file_map.md`) und wurde **nicht** hochgeladen/deployt.
Der vom Subagenten im Worktree `C:\tmp\wt-ap1fix1` erzeugte, unvollständige
Zwischenstand (Branch `ap-1.fix1-basis-zip`, Commit `d340be1`) wurde
NICHT gemerged — er diente nur der CSS-Inhaltsverifikation, die bereits im
finalen, vollständigen ZIP erneut bestätigt ist.

---

#### AP-1.doc: Dokumentation Phase 1 aktualisieren

**Status:** ☐ offen
**Umfang:** S
**Modell:** sonnet
**Abhängigkeiten:** AP-1.fix1

**Ziel & Kontext:**
`Plugins/Eigene WP Blocks/reference_file_map.md`,
`Plugins/Eigene WP Blocks/CLAUDE.md`, ggf.
`Plugins/CDB-Designer/reference_file_map.md`/`CLAUDE.md` (falls AP-1.3
ausgeführt wurde) und das Root-`DOKUMENTATION.md` auf den Stand nach
Phase 1 bringen.

**Betroffene Dateien:**
- `Plugins/Eigene WP Blocks/reference_file_map.md` (ändern – Zeile zu
  `blocks/accordion/style.css` im Abschnitt „Accordion-Block im Detail")
- `Plugins/Eigene WP Blocks/CLAUDE.md` (ändern – Abschnitt „Farben kommen
  aus data-color-*, nicht aus dem Stylesheet" um den behobenen Fund
  ergänzen)
- `Plugins/CDB-Designer/reference_file_map.md` (ändern, nur falls AP-1.3
  nicht „entfällt")
- `DOKUMENTATION.md` (Root, ändern – neuer Eintrag für dieses Vorhaben nach
  dem Muster der bestehenden Einträge „Vorhaben Vier Erweiterungen" /
  „Vorhaben Darkmode-Umschaltung")

**Dateicheckliste:**
- [ ] `Plugins/Eigene WP Blocks/reference_file_map.md`
- [ ] `Plugins/Eigene WP Blocks/CLAUDE.md`
- [ ] `Plugins/CDB-Designer/reference_file_map.md` (nur falls AP-1.3 nicht
      entfällt – sonst hier direkt abhaken mit Vermerk „entfällt")
- [ ] `DOKUMENTATION.md`

**Vorgehen:**
1. Übergabenotizen von AP-1.1 bis AP-1.4, AP-1.rev (inkl. Nachtrag) und
   AP-1.fix1 durchgehen.
2. In `Plugins/Eigene WP Blocks/reference_file_map.md`, Abschnitt
   „Accordion-Block im Detail", die Zeile zu `blocks/accordion/style.css`
   NICHT als „Fix", sondern als Ergebnis der Diagnose dokumentieren: kein
   Fehler in dieser Datei; die tatsächliche Ursache und ihr historischer
   Fix lagen in `assets/css/blocks.css` (Commits `b854060`, `a2737ff`,
   2026-08-16), Details siehe `docs/diagnose-latex-listen-2026-08-24.md`.
3. **KORRIGIERT nach AP-1.fix1s Übergabenotiz:** Der Dateiname bleibt
   `modular-blocks-plugin-empty-1.0.6.zip` (die Versionsnummer im
   Dateinamen ist im Build-Skript `create-empty-plugin-zip.js` hart
   codiert, nicht aus `package.json` abgeleitet) — der `CLAUDE.md`-Verweis
   in „Plugin Distribution Strategy" Punkt 1 auf genau diesen Dateinamen
   ist damit **weiterhin korrekt** und muss NICHT geändert werden. Prüfe
   stattdessen NUR, ob der begleitende Hinweis „Never needs to be updated
   unless core functionality changes" noch sinnvoll ist, nachdem der
   Orchestrator das ZIP bereits einmal wegen eines CSS-Fehlers neu bauen
   musste — ergänze ggf. eine kurze Fußnote, dass das Basis-ZIP am
   2026-08-24 mit korrigiertem `assets/css/blocks.css` neu gebaut wurde
   (Datei liegt lokal unter `plugin-zips/`, nicht versioniert, siehe
   `docs/PLAN-PDF-Notizen-und-Listenformeln.md`). Erwäge zusätzlich, den
   in AP-1.fix1s Übergabenotiz gemeldeten Nebenbefund (Versionsnummer im
   Dateinamen ist hart codiert statt aus `package.json` abgeleitet) als
   offenen Punkt in `DOKUMENTATION.md` Abschnitt 8 aufzunehmen.
4. **Im selben `CLAUDE.md`, Abschnitt „Farben kommen aus data-color-*,
   nicht aus dem Stylesheet": den Satz „Wer dort ein Element ergänzt, das
   Text zeigt, muss es in diese Aufzählung aufnehmen" korrigieren/
   relativieren** (AP-1.rev-Befund B8): Die Aufzählung in
   `accordion/style.css` ist auf dem heutigen Stand nachweislich
   redundant, weil `assets/css/blocks.css:106-110` die Grundfarbe bereits
   eine Ebene weiter außen für den gesamten Panel-Inhalt setzt (per
   Laufzeittest bestätigt: Aufzählung abgeschaltet → alle 12 Formeln
   unverändert). Ein künftiger Agent soll nicht mehr angeleitet werden,
   dort unnötig zu enumerieren.
5. Falls AP-1.3 ausgeführt wurde: entsprechende Zeile in
   `Plugins/CDB-Designer/reference_file_map.md` bei `latex-formulas.css`
   ergänzen (im aktuellen Stand: entfällt, da AP-1.3 „entfällt" war).
6. In `DOKUMENTATION.md` (Root) einen neuen Eintrag nach dem Muster der
   bestehenden Einträge ergänzen: Kurzbeschreibung, Verweis auf diesen
   Plan (`Plugins/CDB-Designer/docs/PLAN-PDF-Notizen-und-Listenformeln.md`),
   Status — inklusive der Kernaussage, dass der gemeldete Fehler ein
   bereits behobener Bug war, dessen Symptom durch einen fehlenden
   Basis-Redeploy weiterlebte.
7. „Stand"-Datum in den geänderten Dateien aktualisieren, wo ein solches
   Feld existiert.

**Akzeptanzkriterien:**
- [ ] Jede in Phase 1 geänderte Datei hat eine aktuelle Zeile in der
      jeweiligen Datei-Map.
- [ ] `Eigene WP Blocks/CLAUDE.md`, „Plugin Distribution Strategy", weist
      auf den am 2026-08-24 erfolgten Neubau des Basis-ZIPs (korrigiertes
      `blocks.css`) hin; der Dateiname `-1.0.6.zip` bleibt unverändert
      (siehe Korrektur in AP-1.doc-Vorgehen Schritt 3).
- [ ] `Eigene WP Blocks/CLAUDE.md`, Abschnitt „Farben kommen aus
      data-color-*", enthält keine Anweisung mehr, die zu unnötiger
      Enumerationserweiterung anleitet.
- [ ] `DOKUMENTATION.md` enthält einen Eintrag für dieses Vorhaben.
- [ ] Kein Verweis in der Dokumentation zeigt auf nicht mehr existierende
      Dateien/Funktionen.

**Tests:**
- Stichprobe: Die neu geschriebenen Zeilen in `reference_file_map.md`
  gegen den echten Dateiinhalt von `blocks/accordion/style.css` prüfen
  (Zeilennummern/Selektor stimmen).

**Übergabenotiz:**

---

### Phase 2: PDF-Export – Tafelbilder und eigene Notizen mit Option

#### AP-2.1: Bulk-Endpoint für serverseitige Seiten-Tafelbilder

**Status:** ☑ erledigt
**Umfang:** M
**Modell:** opus (sicherheitsrelevanter Code – Zugriffsprüfung auf fremde
Klassendaten)
**Abhängigkeiten:** keine (Vertrag bereits in Abschnitt 4 festgelegt)

**Ziel & Kontext:**
Neuer AJAX-Handler, der für eine gegebene `page_id` und `class_id` ALLE
serverseitig gespeicherten Tafelbilder (Tabelle `CBD_TABLE_DRAWINGS`) in
einem einzigen Aufruf liefert – statt eines Aufrufs pro Container. Vorbild
ist die bestehende Methode `ajax_get_page_classroom_data()`
(`Plugins/CDB-Designer/includes/class-cbd-classroom.php`, ab Zeile 1381),
die dieselbe SQL-Abfrage bereits für Schüler-Token-Sitzungen nutzt
(`SELECT container_id, drawing_data, is_behandelt FROM CBD_TABLE_DRAWINGS
WHERE class_id = %d AND page_id = %d`). Für den PDF-Export (eine
angemeldete Lehrperson mit Capability `cbd_edit_blocks`) ist das falsche
Sicherheitsmodell – der neue Handler nutzt stattdessen dasselbe Muster wie
die bestehende Methode `ajax_load_drawing()` (Zeile ~469-499 derselben
Datei): Nonce `cbd_classroom_nonce`, Capability `cbd_edit_blocks`, plus
`can_access_class($class_id)` (bestehende Methode derselben Klasse).

**Betroffene Dateien:**
- `Plugins/CDB-Designer/includes/class-cbd-classroom.php` (ändern)

**Vorgehen:**
1. In der `init()`/Hook-Registrierung derselben Klasse (nahe Zeile 74-75,
   wo bereits `wp_ajax_cbd_save_drawing`/`wp_ajax_cbd_load_drawing`
   registriert sind) ergänzen:
   ```php
   add_action('wp_ajax_cbd_get_page_drawings', array($this, 'ajax_get_page_drawings'));
   ```
2. Neue öffentliche Methode `ajax_get_page_drawings()` nach dem Muster von
   `ajax_load_drawing()` (Zeile ~469):
   ```php
   public function ajax_get_page_drawings() {
       check_ajax_referer('cbd_classroom_nonce', 'nonce');

       if (!current_user_can('cbd_edit_blocks')) {
           wp_send_json_error(array('message' => 'Keine Berechtigung.'));
       }

       global $wpdb;

       $class_id = intval($_POST['class_id'] ?? 0);
       $page_id = intval($_POST['page_id'] ?? 0);

       if ($class_id <= 0 || $page_id <= 0) {
           wp_send_json_error(array('message' => 'Fehlende Parameter.'));
       }

       if (!$this->can_access_class($class_id)) {
           wp_send_json_error(array('message' => 'Klasse nicht gefunden.'));
       }

       $drawings = $wpdb->get_results($wpdb->prepare(
           "SELECT container_id, drawing_data FROM " . CBD_TABLE_DRAWINGS . "
            WHERE class_id = %d AND page_id = %d AND drawing_data IS NOT NULL",
           $class_id, $page_id
       ));

       $result = array();
       foreach ($drawings as $d) {
           $result[] = array(
               'container_id' => $d->container_id,
               'drawing_data' => $d->drawing_data
           );
       }

       wp_send_json_success(array('drawings' => $result));
   }
   ```
   `can_access_class()` ist die bestehende Zugriffsprüfung derselben Klasse
   (bereits in `ajax_save_drawing()` verwendet, Zeile ~438) – wirft ab, wenn
   der aktuelle Nutzer weder Besitzer noch Abonnent der Klasse ist. Genau
   diese Methode verhindert, dass ein Nutzer über eine fremde `class_id`
   Zeichnungen einer nicht zugeordneten Klasse abfragen kann.
3. Antwortform bewusst `{success:true, data:{drawings:[{container_id,
   drawing_data}, ...]}}` – ein flaches Array, analog zum bereits im
   Projekt etablierten Muster bei `cbd/v1/blocks` (siehe
   `class-cbd-blocks-rest-api.php`, Kommentar „Antwortform bewusst nicht in
   ein Objekt verpackt").

**Akzeptanzkriterien:**
- [ ] `php -l includes/class-cbd-classroom.php` läuft ohne Fehler.
- [ ] Aufruf mit gültigem Nonce, `cbd_edit_blocks`-Capability und Zugriff
      auf die `class_id` liefert alle Drawings der Seite (getestet mit
      mind. 2 Containern, die je eine Zeichnung haben).
- [ ] Aufruf ohne gültigen Nonce liefert eine Fehlerantwort, KEINE Daten.
- [ ] Aufruf mit gültigem Nonce, aber einer `class_id`, auf die der
      aktuelle Nutzer laut `can_access_class()` KEINEN Zugriff hat, liefert
      eine Fehlerantwort, KEINE Daten (Negativtest, sicherheitskritisch).
- [ ] Container ohne Zeichnung (`drawing_data IS NULL`) erscheinen NICHT in
      der Antwortliste.

**Tests:**
- Smoke-Test: `php -l Plugins/CDB-Designer/includes/class-cbd-classroom.php`.
- Prüfschritt (kein `wp-admin`-Login, siehe Abschnitt 3): Temporäres
  PHP-Testskript im Webroot des Testservers anlegen, das `wp-load.php`
  einbindet, `wp_set_current_user()` auf einen Testnutzer mit
  `cbd_edit_blocks`-Capability setzt, die Test-Klasse aus Abschnitt 3
  verwendet (oder anlegt, falls noch keine existiert – dann in der
  Übergabenotiz vermerken), testweise 2 Zeilen in `CBD_TABLE_DRAWINGS`
  einfügt, dann `$_POST` befüllt und `ajax_get_page_drawings()` direkt auf
  einer Instanz der Klasse aufruft (Ausgabe abfangen statt `wp_die()`
  greifen zu lassen, z. B. über das Filter `wp_doing_ajax` oder einen
  Testmodus-Zweig – bei Bedarf die Kernlogik in eine separate, testbare
  private Methode auslagern, die `ajax_get_page_drawings()` nur noch
  aufruft). Nach dem Test: Testskript UND Test-Zeichnungszeilen wieder
  löschen (Test-Klasse selbst kann für AP-2.rev/AP-2.4 stehen bleiben,
  falls in der Übergabenotiz vermerkt).

  **Ergebnis:** 37/37 Prüfungen bestanden, darunter alle fünf
  Akzeptanzkriterien, drei Nonce-Varianten (fehlend/falsch/fremde Aktion),
  Parameter-Abwehr und der Capability-Zweig. Der Negativtest lief
  gezielt gegen eine eigens angelegte, echte Fremdklasse (id 18) mit
  echten Zeichnungen auf derselben Seite (nicht nur eine nicht-existente
  ID) — Ergebnis „Klasse nicht gefunden.", kein `drawings`-Schlüssel in
  der Antwort; Gegenprobe mit der eigenen Klasse auf derselben Seite
  lieferte weiterhin Daten. Testklasse 18 und alle Testzeilen danach
  vollständig entfernt (Tabellenstände identisch zum Ausgangszustand).

**Übergabenotiz:**

Neuer AJAX-Handler `cbd_get_page_drawings` in
`includes/class-cbd-classroom.php`: Hook-Registrierung hinter
`wp_ajax_cbd_load_drawing` (Zeile 76), Methode `ajax_get_page_drawings()`
direkt hinter `ajax_load_drawing()` (ab Zeile 502) — zeichengleich zum
Codebeispiel oben, nur Docblock + 2 Kommentare ergänzt. Keine andere Datei
berührt (64 neue Zeilen, 0 entfernt).

**Vertrag für AP-2.3 (live bestätigt):** Action `cbd_get_page_drawings`,
POST-Felder `nonce` (Aktion `cbd_classroom_nonce`), `class_id`, `page_id`.
Erfolg: `{"success":true,"data":{"drawings":[{"container_id":"…",
"drawing_data":"…"}, …]}}` — flaches Array, leer bei keinen Treffern.
Fehler: `{"success":false,"data":{"message":"…"}}`. **Wichtig für AP-2.3:**
Bei ungültigem Nonce bricht `check_ajax_referer` mit HTTP 403 und LEEREM
Rumpf ab (kein JSON) — das muss der Client separat behandeln (kann nicht
blind `response.data.message` lesen).

Container ohne Zeichnung (`drawing_data IS NULL`, entsteht beim Leeren des
Canvas — Zeile bleibt bestehen) werden per `AND drawing_data IS NOT NULL`
gefiltert.

**Für Folge-APs:** Testklasse **`class_id = 17` („Test neu", `teacher_id =
1` = Nutzer `huber`)** existiert bereits auf dem Testserver und kann direkt
verwendet werden — keine neue Klasse nötig. Achtung bei künftigen
Negativtests: Nutzer 1 ist auf die Klassen 15, 16, 12, 13, 14 abonniert,
`can_access_class()` liefert dort `true` — eine wirklich „fremde" Klasse
muss eigens angelegt und danach wieder entfernt werden. Die geänderte
Datei liegt bereits auf dem Testserver, hashgleich mit dem Commit.

Außerhalb des Scope aufgefallen, nicht umgesetzt: `ajax_get_page_
classroom_data()` (Schüler-Pfad) liefert weiterhin auch Zeilen mit
`drawing_data = NULL` und filtert erst clientseitig — bestehendes,
funktionierendes Verhalten, bewusst nicht angefasst.

Git: Branch `ap-2.1-bulk-endpoint` (Commit `eeb0112`), gemerged nach
`phase-2-pdf-tafelbilder` (Merge-Commit `10af425`) und zu `origin`
gepusht.

---

#### AP-2.2: Klassen-Zuordnung pro Container in `board-mode.js`

**Status:** ☑ erledigt
**Umfang:** S
**Modell:** opus (Architekturentscheidung aus Abschnitt 4 umsetzen, an
zwei Erfolgspfaden konsistent)
**Abhängigkeiten:** keine (Vertrag bereits in Abschnitt 4 festgelegt)

**Ziel & Kontext:**
Damit der PDF-Export später weiß, für welche Klasse ein Container ein
serverseitiges Tafelbild hat, schreibt `board-mode.js` bei jedem
erfolgreichen Server-Speichern/-Laden zusätzlich einen lokalen
Begleitschlüssel `localStorage['cbd-board-' + containerId + '-classid'] =
classId` – analog zum bereits bestehenden Begleitschlüssel
`cbd-board-{id}-bgcolor` (siehe `saveToServer()`, Zeile ~1648-1653, wo
bereits ein browserlokaler Zusatzwert neben dem eigentlichen Serverwert
abgelegt wird).

**Betroffene Dateien:**
- `Plugins/CDB-Designer/assets/js/board-mode.js` (ändern)

**Vorgehen:**
1. In `saveToServer(pageContainerId)` (Zeile ~1622-1690): nach erfolgreichem
   `data.success` (innerhalb des `.then(function(data) { ... })`-Zweigs, wo
   bereits `self._setSaveStatus('Gespeichert');` steht), ergänzen:
   ```js
   try {
       var classIdKey = 'cbd-board-' + (pageContainerId || self.stableContainerId) + '-classid';
       localStorage.setItem(classIdKey, String(self.classId));
   } catch (e) { /* Ignorieren, wie beim bestehenden bgcolor-Begleitschlüssel */ }
   ```
2. In `loadFromServer(pageContainerId)` (Zeile ~1567-1620): im Erfolgszweig
   (`if (data.success && data.data.drawing_data)`), analog ergänzen –
   dieselben zwei Zeilen, damit auch das reine Laden (ohne zwischenzeitliches
   eigenes Speichern) den Begleitschlüssel aktuell hält, z. B. wenn ein
   Schüler eine von der Lehrperson bereits gespeicherte Zeichnung nur lädt.
3. Beim Löschen einer Zeichnung im Klassen-Modus (leerer Canvas, `isBlank`
   in `saveToServer()`, Zeile ~1638-1644): den Begleitschlüssel NICHT
   entfernen – ein PDF-Export soll auch nach dem Löschen wissen, dass
   dieser Container zuletzt einer Klasse zugeordnet war (die Bulk-Abfrage
   aus AP-2.1 liefert für einen gelöschten Eintrag ohnehin keine Daten mehr,
   das ist also unschädlich).
4. `this.classId` ist zu diesem Zeitpunkt bereits gesetzt (kommt aus
   `showClassSelector()`, Zeile ~988-1036, VOR dem Aufruf von
   `saveDrawing()`/`loadDrawing()`) – keine zusätzliche Prüfung nötig, da
   `saveToServer()`/`loadFromServer()` ohnehin nur laufen, wenn
   `this.classId && this.ajaxUrl` (siehe Dispatcher `saveDrawing()`/
   `loadDrawing()`, Zeile ~1545-1561).

**Akzeptanzkriterien:**
- [ ] Nach einem erfolgreichen `saveToServer()`-Aufruf im Klassen-Modus
      steht `localStorage.getItem('cbd-board-' + containerId + '-classid')`
      auf der korrekten `class_id` (als String).
- [ ] Nach einem erfolgreichen `loadFromServer()`-Aufruf mit vorhandener
      Zeichnung ist derselbe Begleitschlüssel ebenfalls gesetzt.
- [ ] Im lokalen (nicht-Klassen-)Modus wird KEIN `-classid`-Begleitschlüssel
      geschrieben (Regressionsschutz – lokale Notizen bleiben unverändert).
- [ ] Kein Fehler in der Browser-Konsole, wenn `localStorage` nicht
      verfügbar ist (Try/Catch analog zum bestehenden `bgcolor`-Muster).

**Tests:**
- Smoke-Test: `node --check assets/js/board-mode.js` (bzw. äquivalente
  Syntaxprüfung).
- Prüfschritt: Auf der Testseite/-klasse aus AP-2.1 im Browser den
  Tafelmodus öffnen, „Klasse wählen" → Testklasse auswählen, zeichnen,
  speichern lassen (automatisches Auto-Save oder manuellen Speichern-Button
  auslösen, je nach vorhandenem UI), danach in der Browser-Konsole
  `localStorage.getItem('cbd-board-<containerId>-classid')` prüfen – muss
  die gewählte `class_id` liefern.
- Regressionstest: Denselben Container im lokalen Modus („Persönlich")
  öffnen, zeichnen, speichern – `localStorage` darf danach für diesen
  Container KEINEN `-classid`-Schlüssel enthalten.

  **Ergebnis:** Alle vier Akzeptanzkriterien live auf `fos.localhost:8080`
  bestanden (Seite 368, Klasse 15 „5BT1", zwei echte Container). AK4 wurde
  scharf geprüft (`Storage.prototype.setItem` global auf `throw
  DOMException` gepatcht, kompletter Lade- und Speicherdurchlauf im
  Klassen-Modus, `window.onerror`/`unhandledrejection`/`console.error`
  jeweils 0 Treffer).

**Übergabenotiz:**

Geändert: `assets/js/board-mode.js` (einzige Datei, +30 Zeilen). Sowohl
`saveToServer()` als auch `loadFromServer()` schreiben im Erfolgszweig
zusätzlich den Begleitschlüssel `localStorage['cbd-board-' + containerId +
'-classid']`, analog zum bestehenden `-bgcolor`-Schlüssel. Beim Löschen
einer Zeichnung (`isBlank`) bleibt der Schlüssel wie geplant stehen.

**Abweichung vom Codebeispiel des Plans — im Live-Test gefunden, wichtig
für Folge-APs:** Das Beispiel im Plan-Vorgehen liest `self.classId`
**innerhalb** des `.then()`-Zweigs. Das ist eine Race Condition:
`close()` (Zeile ~193-197) ruft `saveDrawing()` auf und setzt
`this.classId`/`this.stableContainerId` unmittelbar danach **synchron**
auf `null` (Zeile ~267-269), während die Anfrage noch läuft – die
wörtliche Fassung hätte reproduzierbar die Zeichenkette `"null"` statt der
Klassen-ID geschrieben (AK1 verletzt). Umgesetzt stattdessen: Schlüsselname
und Wert werden **synchron vor dem `fetch`** in lokale Variablen
(`classIdKey`, `classIdWert`) festgehalten, im `.then()` nur noch benutzt –
exakt das Muster, dem der bestehende `bgcolor`-Begleitschlüssel aus
demselben Grund folgt. Der Vertrag aus Abschnitt 4 (Schlüsselname, Wert,
beide Erfolgspfade) bleibt unverändert.

**Hinweis für AP-2.3, bewusst nicht behoben (Scope):** Der Schlüsselname
folgt dem `-bgcolor`-Muster und nutzt `pageContainerId` — also `<stableId>`
für Seite 0, `<stableId>:pN` für Zusatzseiten (`getPageContainerId()`,
Zeile ~1810). AP-2.3 liest laut Plan nur `'cbd-board-' + stableId +
'-classid'`, deckt also nur Seite 0 ab. Ist bei einem Container
ausschließlich eine Zusatzseite bespielt, fehlt der Begleitschlüssel. In
der Praxis unkritisch (Seite 0 wird immer zuerst bespielt), aber falls ein
künftiges AP alle Seiten abdecken soll, müsste zusätzlich nach
`:pN`-Suffix-Schlüsseln gesucht werden.

**Testklasse:** Keine neu angelegt – Klasse 15 „5BT1" (Seite 368,
`teacher_id=2`) verwendet, bereits vorhandene Zeichnung testweise
überschrieben und danach zeichengleich zurückgespielt (md5 verifiziert).
Testklasse 17 aus AP-2.1 blieb unberührt.

**Testserver-Hinweis:** Der Plugin-Handle wird mit `?ver=3.1.100`
ausgeliefert, ändert sich bei einer Dateiänderung NICHT – ein normaler
Reload zeigt die alte `board-mode.js` aus dem Browser-Cache. Beim
Nachtesten gezielt Cache umgehen (z. B. `fetch(url, {cache:'reload'})`,
dann Seite neu laden).

Git: Branch `ap-2.2-klassen-zuordnung` (Commit `b7dcbdd`), gemerged nach
`phase-2-pdf-tafelbilder` (Merge-Commit `a1ded78`) und zu `origin`
gepusht.

---

#### AP-2.3: `pdf-server-side.js` – serverseitige Tafelbilder einfügen

**Status:** ☑ erledigt
**Umfang:** M
**Modell:** sonnet (Schnittstellen aus AP-2.1/AP-2.2 sind hier bereits
vollständig als Vertrag vorgegeben, mechanische Umsetzung)
**Abhängigkeiten:** keine (Verträge bereits in Abschnitt 4 und den
AP-2.1-/AP-2.2-Beschreibungen vollständig festgelegt – dieses AP kann vor
deren Fertigstellung beginnen, solange es exakt gegen die dort
dokumentierten Schnittstellen implementiert)

**Ziel & Kontext:**
`injectDrawingsFromStorage()` in
`Plugins/CDB-Designer/assets/js/pdf-server-side.js` (Zeile ~531-635) fügt
bisher ausschließlich lokale `localStorage`-Zeichnungen
(`cbd-board-{stableId}`) unconditional ins PDF ein. Ergänzen um: (a) einen
neuen Parameter `includeDrawings` an der Export-Einstiegsfunktion, der
STEUERT, ob überhaupt Zeichnungen (lokal UND server) eingefügt werden, und
(b) eine neue Funktion, die pro Container den `-classid`-Begleitschlüssel
aus AP-2.2 ausliest und darüber serverseitige Tafelbilder über den
Bulk-Endpoint aus AP-2.1 (`cbd_get_page_drawings`) nachlädt und ebenfalls
einfügt.

**Betroffene Dateien:**
- `Plugins/CDB-Designer/assets/js/pdf-server-side.js` (ändern)

**Vorgehen:**
1. Signatur von `window.cbdPDFExportServerSide` (Zeile 49) um einen 4.
   Parameter erweitern:
   ```js
   window.cbdPDFExportServerSide = function (containerBlocks, mode, quality, includeDrawings) {
       mode = mode || 'visual';
       quality = quality || (isIOS ? 1 : 1.5);
       includeDrawings = (includeDrawings === undefined) ? true : !!includeDrawings;
       // ... Rest unverändert ...
   ```
   `includeDrawings` muss danach bis zu `processOneBlock()` durchgereicht
   werden (aktuell durchläuft es `processBlocksSequentially()` →
   `processOneBlock($block, mode, quality, callback)` – dort einen 5.
   Parameter `includeDrawings` ergänzen und in der Aufrufkette
   konsistent mitführen).
2. In `processOneBlock()` (Zeile ~217): den bestehenden Aufruf
   `injectDrawingsFromStorage($block, $clone);` (Zeile 242) in
   `if (includeDrawings) { injectDrawingsFromStorage($block, $clone); }`
   einwickeln.
3. Direkt danach eine neue Funktion `injectServerDrawings($original,
   $clone, callback)` einbauen (asynchron, da sie einen AJAX-Aufruf
   braucht), analog zu `injectDrawingsFromStorage()` aufgebaut:
   - pro Container mit `data-stable-id` (derselbe Sammel-Mechanismus wie in
     `injectDrawingsFromStorage()`, Zeile ~531-557) den Begleitschlüssel
     `localStorage.getItem('cbd-board-' + stableId + '-classid')` lesen.
   - Container ohne diesen Schlüssel überspringen (kein serverseitiges
     Tafelbild bekannt).
   - Für Container MIT Schlüssel: einen `$.ajax`-Aufruf gegen
     `cbdPDFData.ajaxurl` mit `action: 'cbd_get_page_drawings'`,
     `nonce: cbdPDFData.nonce` (dasselbe Objekt, das bereits für
     `sendPDFViaAjax` genutzt wird, Zeile ~1050-1054 – falls dort ein
     anderer Nonce-Typ verwendet wird als `cbd_classroom_nonce`, den
     korrekten `cbd_classroom_nonce`-Wert zusätzlich über
     `wp_localize_script` bereitstellen; siehe Übergabenotiz-Hinweis für
     AP-2.1, welcher Nonce-Name serverseitig tatsächlich erwartet wird –
     bei Abweichung eine zweite lokalisierte Variable
     `cbdPDFData.classroomNonce` ergänzen, siehe Schritt 4), `class_id` (aus
     dem gelesenen Begleitschlüssel), `page_id` (aktuelle Seiten-ID, z. B.
     aus einer bereits vorhandenen globalen Variable des Plugins oder über
     ein neues `wp_localize_script`-Feld `cbdPDFData.pageId` – prüfen, ob
     ein solches Feld schon existiert, sonst in Schritt 4 ergänzen).
   - **Bulk-Optimierung:** Container mit identischer `class_id` NUR EINMAL
     abfragen (ein Aufruf pro `class_id`, nicht pro Container), Ergebnis
     dann auf alle betroffenen Container verteilen (die Antwort aus AP-2.1
     enthält bereits `container_id` je Zeichnung).
   - Für jede erhaltene Zeichnung: Bild-HTML analog zum bestehenden Muster
     in `injectDrawingsFromStorage()` (Zeile ~596-619) einfügen, Label
     „Tafelbild" statt „Eigene Notiz" verwenden (zur Unterscheidung im
     PDF).
   - `callback()` erst aufrufen, wenn alle Server-Anfragen abgeschlossen
     sind (Promise.all-artiges Verhalten, ggf. mit einfachem Zähler wie an
     anderen Stellen dieser Datei üblich, z. B. `nextFormula()`-Muster in
     `captureFormulaImages()`).
4. Falls `cbdPDFData` (lokalisiert in
   `Plugins/CDB-Designer/includes/class-cbd-classroom.php`, Zeile
   ~1273-1280) `pageId` und/oder den für `cbd_classroom_nonce` gültigen
   Nonce noch nicht enthält: dort ergänzen (`'pageId' => get_the_ID(),`
   bzw. `'classroomNonce' => wp_create_nonce('cbd_classroom_nonce'),`) –
   das ist eine minimale, rückwärtskompatible Erweiterung derselben
   `wp_localize_script`-Aufrufstelle, kein neuer Aufruf.
5. In `processOneBlock()` den Aufruf von `injectServerDrawings()` NACH
   `injectDrawingsFromStorage()` einfügen (beide unter demselben
   `if (includeDrawings)`), callback-verkettet, bevor `callback(blockData)`
   am Ende der Funktion aufgerufen wird – die bestehende Formel-/Screenshot-
   Verarbeitung (Zeile ~330-349) bleibt unverändert danach.
6. Bekannte weitere Aufrufstelle von `window.cbdPDFExportServerSide`
   prüfen: `assets/js/interactivity-store.js` (Apple-PDF-Weiche, laut
   CLAUDE.md-Abschnitt „Screenshot auf Apple-Geräten") – dort wird der 4.
   Parameter nicht übergeben, das ist unschädlich, weil `includeDrawings`
   bei `undefined` auf `true` fällt (bisheriges Verhalten bleibt dort
   erhalten). Keine Änderung an `interactivity-store.js` nötig, nur als
   Prüfschritt in den Tests dieses APs verifizieren.

**Akzeptanzkriterien:**
- [ ] `window.cbdPDFExportServerSide(blocks, 'visual', 1.5, false)` fügt
      WEDER lokale noch serverseitige Zeichnungen ins PDF ein.
- [ ] `window.cbdPDFExportServerSide(blocks, 'visual', 1.5, true)` (oder
      ohne 4. Parameter) fügt weiterhin lokale Notizen ein (Regression zum
      bisherigen Verhalten ausgeschlossen) UND zusätzlich serverseitige
      Tafelbilder für Container mit gesetztem `-classid`-Begleitschlüssel.
- [ ] Container mit `class_id` A und `class_id` B (zwei verschiedene
      Klassen) auf derselben Seite lösen jeweils GENAU EINEN AJAX-Aufruf
      je `class_id` aus, nicht einen je Container.
- [ ] Der Aufruf aus `interactivity-store.js` (Apple-PDF-Weiche) ohne 4.
      Parameter funktioniert weiterhin unverändert (lokale Notizen
      enthalten, kein Fehler durch den fehlenden Parameter).

**Tests:**
- Smoke-Test: `node --check assets/js/pdf-server-side.js`.
- Prüfschritt: Auf der Testseite/-klasse aus AP-2.1/AP-2.2 (ein Container
  mit lokaler Notiz UND ein Container mit serverseitigem Tafelbild für die
  Testklasse) einen PDF-Export mit Schalter AN auslösen (vorerst direkt per
  Konsolenaufruf von `window.cbdPDFExportServerSide`, da der Dialog-Schalter
  erst in AP-2.4 entsteht) und das erzeugte PDF öffnen: beide Bilder müssen
  enthalten sein.
- Prüfschritt: Denselben Export mit `includeDrawings = false` auslösen:
  keines der beiden Bilder darf im PDF erscheinen, alle übrigen
  Blockinhalte bleiben unverändert.
- Regressionstest: Ein Export ohne 4. Parameter (wie er heute in
  `interactivity-store.js` aufgerufen wird) enthält weiterhin die lokale
  Notiz, wie vor dieser Änderung.

  **Ergebnis:** Alle vier Akzeptanzkriterien live auf `fos.localhost:8080`
  bestanden (Seite 5595, Testklasse 17). `includeDrawings === false`
  unterdrückt beide Bildquellen und löst 0 Aufrufe an
  `cbd_get_page_drawings` aus; `includeDrawings === true`/weggelassen
  liefert beide Bilder im ausgehenden PDF-Payload; zwei Container
  identischer `class_id` lösen genau 1 Aufruf aus, zwei Container
  unterschiedlicher `class_id` lösen 2 Aufrufe aus; der bestehende
  Apple-PDF-Export-Aufruf ohne 4. Parameter funktioniert unverändert.

**Übergabenotiz:**

Signatur `window.cbdPDFExportServerSide(containerBlocks, mode, quality,
includeDrawings)` (Default `true`) umgesetzt, durchgereicht bis
`processOneBlock()`. Neue Funktionen `injectServerDrawings()`/
`applyServerDrawings()`: gruppieren Container nach dem `-classid`-
Begleitschlüssel aus AP-2.2, rufen `cbd_get_page_drawings` (AP-2.1) genau
einmal je `class_id` auf (export-lauf-weiter Cache `serverDrawingsCache` —
ein reiner Cache innerhalb eines Blocks reichte nicht, da
`processOneBlock()` pro ausgewähltem Container einzeln läuft). Der
403-mit-leerem-Rumpf-Fall bei ungültigem Nonce (siehe AP-2.1-Vertrag) wird
über einen eigenen `error`-Zweig behandelt, nicht nur über
`response.success`. `class-cbd-classroom.php` ergänzt: `cbdPDFData` (Zeile
~1273) bekam die fehlenden Felder `pageId`/`classroomNonce`.

**Wichtiger Zusatzfund, im Plan nicht vorgesehen — relevant für AP-2.rev:**
`cbdPDFData` wird an ZWEI unabhängigen Stellen lokalisiert:
`class-cbd-classroom.php` (nur auf Seiten mit `[cbd_classroom]`-Shortcode)
UND `includes/class-cbd-block-registration.php` (~Zeile 646, der
NORMALFALL — jede gewöhnliche Seite mit Container-Block, ohne
Klassen-Shortcode). Die zweite Stelle liefert nur `ajaxurl`/`resturl`/
`nonce`/`restnonce`, KEIN `pageId`/`classroomNonce` — dort blieb die
Änderung an `class-cbd-classroom.php` wirkungslos (live entdeckt: erster
Testlauf auf einer gewöhnlichen Seite lieferte `page_id:0, nonce:""` und
HTTP 403). Da diese dritte Datei außerhalb des AP-Scopes lag, wurde in
`pdf-server-side.js` stattdessen ein Rückfall eingebaut:
`cbdPDFData.pageId || window.cbdClassroomData.pageId` (analog für den
Nonce) — `window.cbdClassroomData` wird bereits unverändert von
`class-cbd-block-registration.php` im `wp_footer` gesetzt, sobald
`cbd_edit_blocks` + eingeloggt. **AP-2.rev sollte diesen Fallback explizit
gegenprüfen**, insbesondere falls `class-cbd-block-registration.php` in
einem künftigen AP geändert wird.

Testklasse 17 wiederverwendet (keine neue Klasse angelegt); für den
`class_id`-Dedup-Test zwei synthetische, nie im DOM persistierte
Test-Divs verwendet (kein DB-Eintrag nötig, da die Deduplizierung rein
clientseitig anhand des localStorage-Schlüssels läuft). Alle Testzeilen,
`localStorage`-Testschlüssel und temporären PHP-Skripte entfernt;
Testklasse 17 unangetastet.

Git: Branch `ap-2.3-server-tafelbilder-v2` (Commit `d84a83c`, Basis
`main`), gemerged nach `phase-2-pdf-tafelbilder` (Merge-Commit `bb7f8ee`,
automatisches Zusammenführen von `class-cbd-classroom.php` mit AP-2.1s
Änderung ohne Konflikt) und zu `origin` gepusht.

---

#### AP-2.4: Checkbox im PDF-Export-Dialog

**Status:** ☑ erledigt
**Umfang:** S
**Modell:** sonnet (mechanische UI-Ergänzung nach bestehendem Muster)
**Abhängigkeiten:** keine (Vertrag – Parametername `includeDrawings`,
Aufrufsignatur – bereits in Abschnitt 4 und AP-2.3 vollständig festgelegt;
kann parallel zu AP-2.1/AP-2.2/AP-2.3 entstehen)

**Ziel & Kontext:**
Im bestehenden PDF-Export-Dialog
(`Plugins/CDB-Designer/assets/js/floating-pdf-button.js`) einen Schalter
„Tafelbilder/Notizen einschließen" ergänzen, Default AN (siehe Abschnitt 4).
Der Wert wird beim Klick auf „PDF erstellen" ausgelesen und bis zum Aufruf
von `window.cbdPDFExportServerSide()` durchgereicht.

**Betroffene Dateien:**
- `Plugins/CDB-Designer/assets/js/floating-pdf-button.js` (ändern)

**Vorgehen:**
1. In der Toolbar-HTML in `enterSelectionMode()` (Zeile ~262-276), direkt
   nach dem bestehenden `<select class="cbd-pdf-mode-sel">` (Zeile
   269-273), ergänzen:
   ```js
   '  <label class="cbd-pdf-drawings-toggle">' +
   '    <input type="checkbox" class="cbd-pdf-drawings-check" checked>' +
   '    Tafelbilder/Notizen einschließen' +
   '  </label>' +
   ```
2. Im Klick-Handler für `.cbd-pdf-go` (Zeile ~425-443): den Wert der neuen
   Checkbox lesen und an `startPDFExport()` übergeben:
   ```js
   var mode = $('.cbd-pdf-mode-sel').val();
   var includeDrawings = $('.cbd-pdf-drawings-check').is(':checked');
   // ...
   startPDFExport(selectedBlocks, mode, includeDrawings);
   ```
3. `startPDFExport(selectedBlocks, mode, includeDrawings)` (Zeile
   ~457-464) um den neuen Parameter erweitern und an
   `window.cbdPDFExportServerSide(selectedBlocks, mode, undefined,
   includeDrawings)` durchreichen – `quality` bleibt `undefined`
   (bestehendes Verhalten, die Funktion setzt intern ihren eigenen
   Default).
4. Minimales CSS für `.cbd-pdf-drawings-toggle` in der bestehenden
   `injectSelectionCSS()`-Funktion ergänzen, damit die Checkbox sich
   optisch in die Toolbar einfügt (Ausrichtung wie das bestehende
   `<select>`, Farbe über bereits vorhandene Variablen der Datei wie
   `colorOnAccent`/`colorTextPrimary`, keine neuen Hex-Werte).

**Akzeptanzkriterien:**
- [ ] Die Checkbox erscheint in der Export-Toolbar, ist standardmäßig
      angehakt.
- [ ] Deaktivieren der Checkbox vor „PDF erstellen" führt zu einem Export
      ohne Tafelbilder/Notizen (per AP-2.3-Verhalten).
- [ ] Aktivieren (Standard) führt zu einem Export MIT Tafelbildern/Notizen,
      wie in AP-2.3 getestet.
- [ ] Kein hartcodierter Hex-Wert im neuen CSS.

**Tests:**
- Smoke-Test: `node --check assets/js/floating-pdf-button.js` → bestanden,
  keine Syntaxfehler.
- Prüfschritt (live, `fos.localhost:8080`, Seite „Reinstoffe und Gemische",
  30 Container, Auswahlmodus über den regulären FAB, kein `wp-admin`-Login
  nötig): Checkbox erscheint korrekt zwischen `<select class="cbd-pdf-mode-
  sel">` und `.cbd-pdf-go`, standardmäßig angehakt. Da AP-2.3 zu diesem
  Zeitpunkt noch nicht existierte, wurde `window.cbdPDFExportServerSide`
  testweise durch eine Log-Funktion ersetzt: Klick auf „PDF erstellen" bei
  angehaktem Kästchen ruft mit `includeDrawings === true` auf, bei
  abgehaktem mit `includeDrawings === false`; `quality` bleibt `undefined`.
  Der vollständige End-to-End-Test mit echten Bildern im PDF steht noch aus
  und wird nach AP-2.3 nachgeholt (Teil von AP-2.rev).

**Übergabenotiz:**

Umgesetzt exakt nach dem in Abschnitt 4 festgelegten Vertrag
(`window.cbdPDFExportServerSide(containerBlocks, mode, quality,
includeDrawings)`). Änderung ausschließlich in
`assets/js/floating-pdf-button.js`: Checkbox `.cbd-pdf-drawings-check` in
einem `.cbd-pdf-drawings-toggle`-Label, Default `checked`. Klick-Handler von
`.cbd-pdf-go` liest `is(':checked')` und reicht den Wert über
`startPDFExport(selectedBlocks, mode, includeDrawings)` an
`window.cbdPDFExportServerSide(selectedBlocks, mode, undefined,
includeDrawings)` weiter. CSS ausschließlich über die vorhandene Variable
`colorOnAccent`, kein neuer Hex-Wert.

**Für AP-2.rev relevant:** Parametername (`includeDrawings`), Reihenfolge
und Checkbox-Klasse (`cbd-pdf-drawings-check`) sind exakt wie in Abschnitt 4
vorgegeben – bei der Prüfung von AP-2.3 gegen genau diese Signatur
abgleichen. Ein echter Bild-Vergleich im erzeugten PDF steht noch aus.

Git: Branch `ap-2.4-checkbox-pdf-dialog` (Commit `4d9a253`), gemerged nach
`phase-2-pdf-tafelbilder` (Merge-Commit `483ae20`) und zu `origin`
gepusht.

---

#### AP-2.rev: Unabhängiges Review Phase 2

**Status:** ☐ offen
**Umfang:** M
**Modell:** opus
**Abhängigkeiten:** AP-2.1, AP-2.2, AP-2.3, AP-2.4

**Ziel & Kontext:**
Unabhängige Qualitätsprüfung der Phase 2 durch einen Agenten, der an
keiner Implementierung beteiligt war. Nur lesend arbeiten – KEINE Datei
verändern. Besonderer Schwerpunkt: die sicherheitskritische
Zugriffsprüfung in AP-2.1 (Negativtest fremde `class_id`).

**Vorgehen:**
1. `ajax_get_page_drawings()` in `class-cbd-classroom.php` gegen die
   Akzeptanzkriterien von AP-2.1 prüfen – insbesondere: Ist
   `can_access_class()` wirklich vor der Datenabfrage aufgerufen, nicht
   nur `current_user_can()`? Gibt es einen Pfad, der die Prüfung umgeht?
2. `board-mode.js`-Änderung aus AP-2.2 gegen dessen Akzeptanzkriterien
   prüfen, insbesondere den Regressionsschutz (kein `-classid`-Schlüssel
   im lokalen Modus).
3. `pdf-server-side.js`-Änderung aus AP-2.3 prüfen: Wird `includeDrawings`
   korrekt bis `processOneBlock()` durchgereicht? Ist die
   Bulk-Optimierung (ein Aufruf je `class_id`) tatsächlich umgesetzt, nicht
   nur behauptet?
4. `floating-pdf-button.js`-Änderung aus AP-2.4 prüfen: Stimmt der
   Parametername/die Aufrufreihenfolge exakt mit AP-2.3 überein?
5. Phasen-Endzustand prüfen: Container mit lokaler Notiz UND
   serverseitigem Tafelbild erzeugt bei Schalter AN ein PDF mit beiden,
   bei Schalter AUS ein PDF mit keinem von beiden (aus den Testprotokoll-
   Einträgen der Einzel-APs nachvollziehen, bei Zweifel selbst am
   Testserver nachvollziehen – lesend, d. h. keine neuen Dateien anlegen,
   höchstens ein bereits von einem Vorgänger-AP angelegtes temporäres
   Testskript erneut aufrufen, falls es noch existiert).
6. Scope-Check gegen Nicht-Ziele aus Abschnitt 2 (keine Einzelauswahl pro
   Bild entstanden, keine Änderung an der Zeichenfunktion selbst).
7. Befunde als Review-Bericht in die Übergabenotiz: je Befund
   Schweregrad (kritisch/mittel/gering), betroffenes AP, Datei und
   Fundstelle.

**Akzeptanzkriterien:**
- [ ] Jedes Implementierungs-AP der Phase (AP-2.1–AP-2.4) wurde gegen
      seine Akzeptanzkriterien geprüft.
- [ ] Die sicherheitskritische Zugriffsprüfung aus AP-2.1 wurde explizit
      nachvollzogen (Code gelesen, nicht nur Übergabenotiz geglaubt).
- [ ] Alle Befunde mit Schweregrad, Datei und Fundstelle dokumentiert.
- [ ] Keine Datei wurde verändert.

**Tests:**
- entfällt (Review-AP; das Ergebnis ist der Bericht).

**Übergabenotiz:**

---

#### AP-2.doc: Dokumentation Phase 2 aktualisieren

**Status:** ☐ offen
**Umfang:** S
**Modell:** sonnet
**Abhängigkeiten:** AP-2.rev

**Ziel & Kontext:**
`Plugins/CDB-Designer/reference_file_map.md` und
`Plugins/CDB-Designer/CLAUDE.md` auf den Stand nach Phase 2 bringen, plus
Abschlusseintrag in `DOKUMENTATION.md` (Root).

**Betroffene Dateien:**
- `Plugins/CDB-Designer/reference_file_map.md` (ändern – Zeilen zu
  `class-cbd-classroom.php`, `board-mode.js`, `pdf-server-side.js`,
  `floating-pdf-button.js`)
- `Plugins/CDB-Designer/CLAUDE.md` (ändern – neuer Abschnitt oder Ergänzung
  zum PDF-Export, analog zum Aufbau des Abschnitts „LaTeX-Formeln:
  Renderpfad und Wiederholrendern")
- `DOKUMENTATION.md` (Root, ändern – Eintrag für dieses Vorhaben
  vervollständigen, falls AP-1.doc bereits einen Grundeintrag angelegt hat;
  sonst neuen Eintrag anlegen)

**Dateicheckliste:**
- [ ] `Plugins/CDB-Designer/reference_file_map.md`
- [ ] `Plugins/CDB-Designer/CLAUDE.md`
- [ ] `DOKUMENTATION.md`

**Vorgehen:**
1. Übergabenotizen von AP-2.1 bis AP-2.4 sowie AP-2.rev durchgehen.
2. In `reference_file_map.md` die vier betroffenen Dateien-Zeilen
   aktualisieren: neuen AJAX-Handler bei `class-cbd-classroom.php`
   erwähnen, neuen Begleitschlüssel bei `board-mode.js`, neue Funktion
   `injectServerDrawings()` + Parameter `includeDrawings` bei
   `pdf-server-side.js`, neue Checkbox bei `floating-pdf-button.js`.
3. In `CLAUDE.md` einen neuen Abschnitt „PDF-Export: Tafelbilder und eigene
   Notizen" ergänzen: Mechanismus (Begleitschlüssel, Bulk-Endpoint,
   Schalter), bewusste Einschränkung (ein Container mit Tafelbildern für
   mehrere Klassen exportiert nur die zuletzt genutzte Klassen-Zuordnung –
   siehe Risiko-Tabelle Abschnitt 5 dieses Plans).
4. In `DOKUMENTATION.md` (Root) den Eintrag aus AP-1.doc um Phase 2
   ergänzen bzw. – falls AP-1.doc noch nicht gelaufen ist – einen
   vollständigen neuen Eintrag anlegen, der beide Phasen nennt.
5. „Stand"-Datum in den geänderten Dateien aktualisieren.

**Akzeptanzkriterien:**
- [ ] Jede in Phase 2 geänderte Datei hat eine aktuelle Zeile in
      `Plugins/CDB-Designer/reference_file_map.md`.
- [ ] `CLAUDE.md` beschreibt die bekannte Einschränkung (mehrere Klassen
      pro Container) explizit, nicht nur den Erfolgsfall.
- [ ] `DOKUMENTATION.md` enthält einen vollständigen Eintrag für beide
      Phasen dieses Vorhabens.

**Tests:**
- Stichprobe: Zwei zufällige neue Zeilen der Datei-Map gegen den
  tatsächlichen Dateiinhalt prüfen.

**Übergabenotiz:**

---

## 8. Status

Legende: ☐ offen · ◐ in Arbeit · ☑ erledigt · ✗ blockiert

| AP | Titel | Modell | Status | Abhängig von | Notiz |
|---|---|---|---|---|---|
| AP-1.1 | Live-Diagnose der Farbkette | opus | ☑ | – | ÜBERRASCHENDER BEFUND: Fehler im Ist-Zustand nicht reproduzierbar, bereits am 2026-08-16 anderswo behoben (Commit b854060, blocks.css) — Ursache lag nicht in accordion/style.css |
| AP-1.2 | CSS-Fix in accordion/style.css | opus | ☑ | AP-1.1 | entfällt lt. AP-1.1-Diagnose: kein Fehler in dieser Datei, nichts zu ändern |
| AP-1.3 | Bedingter Fix in CDB-Designer | opus | ☑ | AP-1.1, AP-1.2 | entfällt lt. AP-1.1-Diagnose: kein Fehler in latex-formulas.css/class-latex-parser.php |
| AP-1.4 | Weitere Blocktypen prüfen | sonnet | ☑ | AP-1.2, AP-1.3 | inhaltlich bereits durch AP-1.1 erledigt (12 Formeln über 8 Blocktypen + Negativkontrolle gemessen, alle bestanden) — kein eigener Agentenlauf nötig |
| AP-1.rev | Review Phase 1 | opus | ☑ | AP-1.1–1.4 | Umplanung bestätigt (unabhängig nachgemessen), ABER kritischer Fund B1: einziges Basis-ZIP enthält noch den alten, kaputten CSS-Stand → AP-1.fix1 |
| AP-1.fix1 | Basis-ZIP mit korrigiertem blocks.css neu bauen | sonnet | ☑ | AP-1.rev | CSS-Inhalt verifiziert korrekt; Subagent baute im isolierten Worktree ohne vendor/-Verzeichnis (ChemViz-Libs fehlten, 49 KB statt 1,25 MB) — Orchestrator hat das ZIP danach im Hauptverzeichnis (vendor/ dort lokal vorhanden) korrekt neu gebaut, 1,25 MB, vendor/ + korrektes CSS bestätigt |
| AP-1.doc | Doku Phase 1 | sonnet | ◐ | AP-1.fix1 | gestartet als Subagent |
| AP-2.1 | Bulk-Endpoint Tafelbilder | opus | ☑ | – | 37/37 Live-Prüfungen bestanden inkl. Negativtest; Testklasse 17 („Test neu") existiert bereits für Folge-APs |
| AP-2.2 | Klassen-Zuordnung in board-mode.js | opus | ☑ | – | Race-Condition im Plan-Codebeispiel live gefunden+korrigiert (Werte synchron vor fetch erfassen, wie beim bestehenden bgcolor-Schlüssel); Hinweis für AP-2.3: Begleitschlüssel deckt nur Seite 0 ab, nicht `:pN`-Zusatzseiten |
| AP-2.3 | Server-Tafelbilder in pdf-server-side.js | sonnet | ☑ | – | Wichtiger Zusatzfund: zweite, unbehandelte cbdPDFData-Localize-Stelle in class-cbd-block-registration.php (Normalfall ohne Klassen-Shortcode) — per Client-Fallback auf window.cbdClassroomData gelöst, siehe Übergabenotiz |
| AP-2.4 | Checkbox im PDF-Dialog | sonnet | ☑ | – | Vertrag (Parametername/Reihenfolge) exakt eingehalten; E2E-Bildtest steht noch aus (braucht AP-2.3) |
| AP-2.rev | Review Phase 2 | opus | ◐ | AP-2.1–2.4 | gestartet als Subagent |
| AP-2.doc | Doku Phase 2 | sonnet | ☐ | AP-2.rev | |

## 9. Testprotokoll

Wird während der Ausführung gepflegt. Ein Eintrag pro abgeschlossenem AP
und pro Phasenabschluss.

| Datum | AP / Phase | Getestet | Ergebnis | Getestet von |
|---|---|---|---|---|
| 2026-08-24 | AP-1.fix1 | `npm run plugin-zip-empty` (zunächst im Worktree, dann vom Orchestrator im Hauptverzeichnis wiederholt); `diff` gegen Quellstand; `unzip -l` auf vendor/-Vollständigkeit; `php -l` auf Basisdateien im ZIP | Subagent: CSS-Inhalt korrekt, aber ZIP unvollständig (49 KB, vendor/ fehlte im isolierten Worktree) → vom Orchestrator im Hauptverzeichnis korrekt neu gebaut, 1,25 MB, CSS + vendor/ vollständig verifiziert; nichts deployt | Subagent (sonnet) + Orchestrator |
| 2026-08-24 | AP-2.3 | `node --check`, `php -l` + `tools/check-php74.php`; Live auf `fos.localhost:8080` (Seite 5595, Testklasse 17): includeDrawings true/false, Dedup-Test 1 vs. 2 AJAX-Aufrufe je class_id, Regressionstest Apple-PDF-Aufruf ohne 4. Parameter | bestanden – alle vier Akzeptanzkriterien live bestätigt; wichtiger Zusatzfund (zweite cbdPDFData-Localize-Stelle ohne pageId/Nonce) live entdeckt und per Client-Fallback gelöst | Subagent (sonnet) |
| 2026-08-24 | AP-1.rev | Unabhängige Nachmessung auf Testseite 5595 + Produktivseite 5422; unabhängige Verifikation der Commits `b854060`/`a2737ff`; eigene Rekonstruktion des Fehlerzustands; Prüfung der vorhandenen Plugin-ZIPs auf ihren tatsächlichen `blocks.css`-Inhalt | Umplanung bestätigt, ABER kritischer Fund: einziges Basis-ZIP (`modular-blocks-plugin-empty-1.0.6.zip`) enthält noch den alten, kaputten CSS-Stand → AP-1.fix1 nötig, bevor Phase 1 als abgeschlossen gilt | Subagent (opus) |
| 2026-08-24 | AP-1.1 | Diagnoseskript `pruefung-formelfarbe.js` auf Testseite 5595 (12 Formeln, 8 Blocktypen) und Produktivseite 5422 (22 Formeln); Fehlerzustand vor Commit `b854060` zur Laufzeit rekonstruiert und erneut gemessen | Ist-Zustand fehlerfrei in allen 12+22 Fällen; Fehler im rekonstruierten Alt-Zustand reproduziert (11/12 Formeln weiß) und eindeutig auf `assets/css/blocks.css` zurückgeführt, nicht auf `blocks/accordion/style.css` | Subagent (opus) |
| 2026-08-24 | AP-1.2/AP-1.3/AP-1.4 | Kein eigener Test nötig – Disposition „entfällt"/„durch AP-1.1 miterledigt" beruht vollständig auf den AP-1.1-Messungen | entfällt bzw. bereits erfüllt, siehe jeweilige Übergabenotiz | Orchestrator (auf Basis AP-1.1) |
| 2026-08-24 | AP-2.4 | `node --check`; Live auf `fos.localhost:8080` (Seite „Reinstoffe und Gemische"): Checkbox-Position/Default, Aufrufargumente von `window.cbdPDFExportServerSide` per Mock geprüft (an/aus) | bestanden – Vertrag exakt eingehalten; echter Bild-Vergleich im PDF steht bis AP-2.3 noch aus | Subagent (sonnet) |
| 2026-08-24 | AP-2.1 | `php -l` + `tools/check-php74.php` (569 Dateien PHP-7.4-kompatibel); Live über temporäres Webroot-Testskript: 37 Einzelprüfungen (Erfolgsfall mit 2 Containern, 3 Nonce-Varianten, Parameter-Abwehr, Capability-Zweig, NULL-Filterung, sicherheitskritischer Negativtest gegen echte Fremdklasse 18 samt Gegenprobe) | bestanden – alle 37 Prüfungen grün, Testdaten rückstandsfrei entfernt | Subagent (opus) |
| 2026-08-24 | AP-2.2 | `node --check`; Live auf `fos.localhost:8080` (Seite 368, Klasse 15 „5BT1", 2 echte Container): saveToServer/loadFromServer schreiben korrekten `-classid`-Schlüssel, lokaler Modus schreibt keinen, `localStorage.setItem` global auf Exception gepatcht → kein Konsolenfehler | bestanden NACH Korrektur einer im Plan-Codebeispiel enthaltenen Race Condition (Werte synchron vor `fetch` erfasst, wie beim bestehenden bgcolor-Muster) – vorher hätte das Beispiel `"null"` statt der Klassen-ID geschrieben | Subagent (opus) |

## 10. Dokumentation

- **Projektdokumentation:** `DOKUMENTATION.md` (Root) – erhält im
  Dokumentations-AP jeder Phase (AP-1.doc, AP-2.doc) einen Eintrag für
  dieses Vorhaben.
- **Datei-Maps:** `Plugins/CDB-Designer/reference_file_map.md`,
  `Plugins/Eigene WP Blocks/reference_file_map.md` – werden von jedem AP
  gepflegt, das Dateien anlegt oder wesentlich ändert.
- **Diagnosebericht:** `Plugins/CDB-Designer/docs/diagnose-latex-listen-2026-08-24.md`
  (entsteht in AP-1.1) – Grundlage für AP-1.2/AP-1.3, bleibt als Referenz
  im Repo erhalten.
