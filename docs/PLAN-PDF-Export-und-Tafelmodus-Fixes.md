# Projektplan: PDF-Export- und Tafelmodus-Fixes

_Erstellt am: 2026-08-25 · Letzte Aktualisierung: 2026-08-25_

## 0. Anweisungen für den ausführenden Agenten

Du arbeitest nach diesem Plan. Er ist die einzige Wahrheitsquelle – du hast
keinen Zugriff auf das Gespräch, in dem er entstand. Halte dich an diese Regeln:

**Rollen und Modelle:**
A. Wird die Abarbeitung von einem Orchestrator koordiniert (Opus), gilt:
   Der Orchestrator delegiert APs an Subagenten und implementiert NIEMALS
   selbst. Er gibt jedem Subagenten nur dessen AP-Text plus die Abschnitte
   0–3 dieses Plans als Kontext, prüft jede Rückmeldung gegen die
   Akzeptanzkriterien des APs, bevor er abhängige APs freigibt, und pflegt
   die Statustabelle.
B. Jedes AP nennt sein Ausführungsmodell (**Modell:** sonnet | opus).
   Subagenten mit genau diesem Modell starten – Sonnet für klar
   vorgezeichnete Umsetzung, Opus wo Urteilsvermögen gefragt ist.
C. Dieser Plan ist bewusst auf **bis zu vier Agenten** zugeschnitten:
   - **Agent 1 (Strang „PDF"):** AP-1.1 → AP-1.2 → AP-1.3 nacheinander
     (dieselbe Datei `assets/js/pdf-server-side.js` wird in allen drei
     APs berührt – deshalb EIN Agent, nicht drei parallele).
   - **Agent 2 (Strang „Tafelmodus-Darkmode"):** AP-1.4 → AP-1.5
     nacheinander (beide ändern `assets/css/board-mode.css`).
   - Agent 1 und Agent 2 arbeiten **parallel zueinander** – ihre
     Dateimengen sind disjunkt (siehe Abschnitt 4, letzte Zeile).
   - **Agent 3:** AP-1.rev, erst nachdem Agent 1 UND Agent 2 fertig sind.
   - **Agent 4:** AP-1.doc, erst nach AP-1.rev.
   **Git-Isolation:** `Plugins/CDB-Designer` ist ein eigenständiges
   Git-Repository (Remote `origin` →
   https://github.com/Cyric25/CBD---Container-Block-Desinger.git, Branch
   `main`). Agent 1 arbeitet auf dem Branch `phase-1-pdf-export-fixes`,
   Agent 2 auf `phase-1-tafelmodus-darkmode` (beide von `main` abgezweigt) –
   idealerweise in zwei getrennten Git-Worktrees
   (`git worktree add ../cdb-pdf-fixes phase-1-pdf-export-fixes` bzw.
   `git worktree add ../cdb-tafelmodus-darkmode phase-1-tafelmodus-darkmode`),
   damit beide Agenten gleichzeitig arbeiten können, ohne sich die
   Arbeitskopie gegenseitig zu überschreiben. Die Dateimengen beider
   Branches sind disjunkt (siehe Abschnitt 4).

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
7. Ergebnis ins Testprotokoll (Abschnitt 9) eintragen.
8. Erst dann Status auf ☑. Bei Fehlschlag: Status ✗ (blockiert), Ursache in
   die Übergabenotiz, nicht mit abhängigen APs weitermachen.
9. Nach dem letzten Implementierungs-AP der Phase zusätzlich:
   Integrationstest der Phase + Regressionscheck (siehe AP-1.rev).
   Eintrag ins Testprotokoll.
10. Danach folgt das Review-AP (`AP-1.rev`): Es wird von einem frischen
    Agenten ausgeführt, der KEINES der APs dieser Phase implementiert hat.
    Der Review-Agent arbeitet ausschließlich lesend und verändert keine
    Datei. Kritische Befunde führen zu Korrektur-APs (siehe Regel 12);
    die Phase ist erst danach abgeschlossen.

**Übergabe:**
11. Fülle die Übergabenotiz deines APs aus: was geändert wurde, getroffene
    Entscheidungen, was für Folge-APs relevant ist.
12. Hat dein AP Dateien angelegt, verschoben oder wesentlich geändert:
    aktualisiere deren Zeilen in `Plugins/CDB-Designer/reference_file_map.md`
    (Datei | Zweck | wichtige Funktionen | Abhängigkeiten). Die umfassende
    Projektdokumentation wird im Dokumentations-AP am Phasenende
    (`AP-1.doc`) nachgezogen.
13. Aktualisiere „Letzte Aktualisierung" im Dateikopf dieses Plans.
14. **Git (Pflicht).** `Plugins/CDB-Designer` ist ein Git-Repository mit
    verbundenem Remote `origin`. Mindestens ein Commit je abgeschlossenem
    AP, mit AP-ID im Commit-Text (Beispiel: `git commit -m "AP-1.2:
    PDF-Bilder-Fehler behoben"`). Nach jedem abgeschlossenen AP den
    Strang-Branch zum Remote pushen (`git push -u origin <branch>`) – das
    Remote ist das laufende Backup des Fortschritts. Branch-Namen:
    `phase-1-pdf-export-fixes` (Agent 1, AP-1.1–AP-1.3) und
    `phase-1-tafelmodus-darkmode` (Agent 2, AP-1.4–AP-1.5), beide von
    `main` abgezweigt (`git checkout main && git pull && git checkout -b
    <branch>`). Beide Branches erst nach bestandenem `AP-1.rev` in `main`
    mergen – das übernimmt AP-1.doc als ersten Schritt.

**Umplanung:**
15. Zeigt sich während der Ausführung, dass der Plan nicht trägt (Review-
    Befunde, blockierte APs, falsche Annahmen), werden Korrektur-APs mit
    fortlaufender Nummer ergänzt (`AP-1.fix1`, …) und in Statustabelle
    und Testprotokoll aufgenommen. Bestehende APs und Übergabenotizen
    werden nie gelöscht, nur ergänzt – der Plan bleibt nachvollziehbare
    Historie.

## 1. Projektziel

Drei bereits produktive, aber lückenhafte Funktionen des Plugins
CDB-Designer werden korrigiert: (A) der serverseitige PDF-Export zeigt
„Eigene Notizen" (lokale Tafelmodus-Zeichnung) und „Tafelbilder" (Server-
Zeichnung) als Bilder im erzeugten PDF, in allen drei Exportmodi
(visual/print/text). (B) Der PDF-Export lädt die Datei ohne Speicherort-
Nachfrage direkt herunter, soweit das browserseitig steuerbar ist – nicht
steuerbare Fälle sind als solche dokumentiert. (C) Der Tafelmodus passt
Werkzeugleiste/Overlay/Dialoge an einen aktiven Darkmode an, und die
Zeichenfläche invertiert automatisch (Weiß↔Schwarz), wenn Darkmode aktiv
UND die Tafel auf der weißen Standardfarbe steht.

## 2. Nicht-Ziele

- Kein neues Feature – ausschließlich Lückenschluss/Bugfix an bereits
  produktivem Code aus `docs/archiv/PLAN-PDF-Notizen-und-Listenformeln.md`
  und `docs/archiv/PLAN-Darkmode-Umschaltung.md`.
- Theme und Plugin „Eigene WP Blocks" werden **nicht** geändert. Der
  Darkmode-Mechanismus selbst (`data-theme`-Attribut, Toggle, `localStorage`)
  gehört dem Theme und ist fertig – hier geht es nur um fehlende
  Anpassungen innerhalb von CDB-Designer.
- Kein neuer PDF-Bildpfad, keine Umstellung von mPDF auf eine andere
  Bibliothek.
- Keine Farbwahl-UI beim Öffnen des Tafelmodus im Darkmode – die
  Invertierung läuft automatisch nach der in Abschnitt 4 festgelegten
  Regel, ohne Nutzerinteraktion.
- Bunte Stiftfarben in einer invertierten Notiz behalten ihr durch
  `filter: invert(1)` verändertes Aussehen (z. B. Rot→Cyan) – eine
  farbtreue Invertierung ist ausdrücklich nicht Ziel dieses Plans.
- Falls sich Bug B als reine Browser-Einstellung des Nutzers herausstellt,
  die eine Website nicht per Code übersteuern kann: Das wird als Befund
  dokumentiert, nicht durch einen Blindflug-Fix erzwungen.
- Kein automatisches Bauen/Hochladen eines neuen Plugin-ZIPs zur
  Produktivinstallation – das entscheidet der Nutzer nach Abnahme separat
  (`node create-plugin-zip.js`, siehe `CLAUDE.md`).
- Keine automatisierten Testframeworks werden neu eingeführt (im Projekt
  bislang keine JS-Testinfrastruktur für diesen Bereich vorhanden).

## 3. Kontext & Constraints

- **Umgebung:** WordPress-Plugin CDB-Designer, PHP 7.4+ (Zielumgebung
  7.4.33), kein Build-Prozess (reines PHP + Vanilla JS). Projektverzeichnis
  liegt unter OneDrive-Sync (Windows).
- **Bestehende Konventionen:** `Plugins/CDB-Designer/CLAUDE.md` (Abschnitte
  „Darkmode" und „PDF-Export: Tafelbilder und eigene Notizen"),
  `Plugins/CDB-Designer/reference_file_map.md`, Root-`CLAUDE.md` (Abschnitt
  „Color Scheme").
- **Harte Grenzen:**
  - `tools/check-php74.php` muss nach jeder PHP-Änderung grün bleiben.
  - Neue/geänderte darkmode-relevante CSS-Regeln verwenden **ausschließlich**
    `[data-theme="dark"] .selektor`, **niemals**
    `@media (prefers-color-scheme: dark)`.
  - Neue Farbwerte über `var(--x, #fallback)`, außer bei Inhaltsfarben
    (die vom Nutzer gewählte Zeichenfarbe/Stiftfarbe auf der Tafel ist
    davon ausdrücklich ausgenommen).
  - Keine neuen Fremd-Libraries/CDN-Einbindungen (DSGVO-Vorgabe des
    Projekts).
- **Testumgebung:** Laut Projektwissen existiert ein lokaler
  WordPress-Testserver unter `fos.localhost:8080` mit beiden Plugins
  installiert (als Kopie, nicht als Verknüpfung). Hat der ausführende
  Agent keinen Zugriff darauf, sind die Testschritte als manuelle
  Prüf-Checkliste für den Nutzer zu dokumentieren (siehe Testprotokoll je
  AP) statt sie als „bestanden" einzutragen.
- **Git-Strategie:** `Plugins/CDB-Designer` ist ein eigenständiges
  Git-Repository (unabhängig vom Projekt-Root, das selbst kein Git hat).
  Etablierte Projektkonvention (siehe bestehende Branches wie
  `phase-2-pdf-tafelbilder`, `ap-2.3-server-tafelbilder`): ein Branch pro
  Phase/Strang, mindestens ein Commit je AP mit AP-ID im Commit-Text, Push
  nach jedem AP, Merge in `main` erst nach bestandenem Review. Für dieses
  Vorhaben: `phase-1-pdf-export-fixes` (Agent 1) und
  `phase-1-tafelmodus-darkmode` (Agent 2).
- **Remote-Repository:**
  https://github.com/Cyric25/CBD---Container-Block-Desinger.git (bereits
  als `origin` verbunden, Branch `main`) – keine Einrichtung nötig.

## 4. Architekturentscheidungen

| Entscheidung | Begründung | Verworfene Alternative |
|---|---|---|
| Live-Diagnose (AP-1.1) vor jeder Codeänderung an Bug A | Der Vorgängerplan hat den Bildpfad nie an einer tatsächlich erzeugten PDF-Datei verifiziert. Zwei Verdachtsstellen sind bekannt, aber unbestätigt. | Beide Verdachtsstellen blind patchen – Risiko, am eigentlichen Symptom vorbeizufixen. |
| CSS-Filter `invert(1)` auf die Zeichenfläche, nur bei Darkmode UND Standard-Weiß-Tafel | Nutzerentscheidung (siehe Vorgespräch). Wirkt automatisch auch auf bereits gespeicherte Raster-Zeichnungen (PNG in `localStorage`), ohne deren Pixel zu verändern. | Farbauswahl-Dialog beim Öffnen im Darkmode – aufwändiger, vom Nutzer nicht gewählt. |
| `.htaccess`-`Content-Disposition`-Header als defensive Ergänzung für Bug B statt neuer PHP-Streaming-Endpunkt | Kleinstmögliche, risikoarme Änderung; passt zu „kein Build-Prozess"; `downloadPDF()` nutzt bereits die korrekte `<a download>`-Technik. | PHP-Download-Proxy-Endpunkt – mehr Code, mehr Angriffsfläche, und für den vermuteten Bug (Browser-Einstellung) ohnehin wirkungslos. |
| Eine Phase mit zwei parallelen AP-Strängen statt zwei sequenziellen Phasen | Bug A/B und Bug C sind unabhängig und dateidisjunkt, kein gemeinsamer Zwischenzustand nötig. Ermöglicht echte Parallelisierung mit bis zu vier Agenten (Nutzervorgabe). | Zwei sequenzielle Phasen – würde ohne Abhängigkeitsgrund unnötig serialisieren. |
| Invertierung nur bei `boardColor === '#ffffff'`, nicht bei Grün/Schwarz | Nutzerentscheidung: bewusst gewählte Tafelfarben (Grün/Schwarz) sind bereits dunkel genug und sollen unangetastet bleiben. | Immer invertieren, unabhängig von der Tafelfarbe – hätte eine bewusst schwarz gewählte Tafel zu Weiß verkehrt. |

## 5. Risiken & Rollback

| Risiko | Wahrscheinlichkeit | Auswirkung | Gegenmaßnahme / Rollback |
|---|---|---|---|
| Bug-A-Ursache liegt woanders als in den zwei bekannten Verdachtsstellen | mittel | mittel | AP-1.1 diagnostiziert zuerst live; AP-1.2 behebt den tatsächlich gefundenen Befund, nicht blind die Vermutung. |
| Fix an `clean_block_html()` bricht bestehenden Text-/Screenshot-/Formel-Export | gering–mittel | hoch | AP-1.2-Testprotokoll deckt alle drei Exportmodi (visual/print/text) und alle Bildquellen (Notiz, Tafelbild, Screenshot, Formel) ab, nicht nur den neuen Fall. |
| `.htaccess`-Header wirkt nicht (mod_headers auf dem Zielhosting deaktiviert) oder Ursache bleibt eine Browser-Einstellung | mittel | gering | AP-1.3 dokumentiert diesen Fall explizit als bekannte, nicht code-behebbare Einschränkung statt endlos weiterzufixen. |
| Invertierungs-Filter kehrt auch bewusst bunte Stiftfarben um | hoch (bekannt, akzeptiert) | gering | Bewusste Nutzerentscheidung; AP-1.5 prüft das Ergebnis mit einer mehrfarbigen Beispielnotiz und hält es im Testprotokoll fest, statt es zu übersehen. |
| Agent 1 und Agent 2 verändern doch eine gemeinsame Datei | gering | mittel | Vor Parallelstart prüfen: Agent 1 fasst ausschließlich `assets/js/pdf-server-side.js`, `includes/class-cbd-pdf-generator.php`, `assets/js/floating-pdf-button.js` (nur falls nötig) und `.htaccess` unter `wp-content/uploads/cbd-temp-pdfs/` an; Agent 2 ausschließlich `assets/js/board-mode.js` und `assets/css/board-mode.css`. Keine Überschneidung, getrennte Branches/Worktrees. |
| Merge der beiden Strang-Branches in `main` erzeugt Konflikt | gering | gering | Dateimengen sind laut Abschnitt 4 disjunkt, ein Konflikt ist nicht zu erwarten. Tritt trotzdem einer auf: AP-1.doc pausiert, Konflikt manuell auflösen, dabei keine Akzeptanzkriterien der betroffenen APs verletzen. |

**Generelle Rollback-Strategie:** Git-Branch pro Strang
(`phase-1-pdf-export-fixes`, `phase-1-tafelmodus-darkmode`), beide von
`main` abgezweigt und bis zum Merge nach AP-1.rev unangetastet. Rollback
eines einzelnen APs vor dem Merge: `git revert <commit>` oder Reset auf den
vorherigen AP-Commit im jeweiligen Strang-Branch; nach dem Merge in `main`
nur noch per `git revert`. Dieser Plan enthält darüber hinaus keine
destruktiven Operationen (keine Datenbank-Änderungen, keine
Datei-Löschungen außer der bereits erledigten, noch zu committenden
Archivierung abgeschlossener Pläne in `docs/archiv/`).

## 6. Phasenübersicht

| Phase | Ziel | Lauffähiger Endzustand | APs |
|---|---|---|---|
| 1 | PDF-Bilder + Direktdownload korrigiert, Tafelmodus darkmode-fähig | PDF-Export zeigt „Eigene Notizen" und „Tafelbilder" als Bilder im PDF in allen drei Modi; PDF lädt ohne Speicherort-Nachfrage direkt herunter, soweit browserseitig steuerbar (sonst dokumentiert); Tafelmodus passt Werkzeugleiste/Overlay/Dialoge an Darkmode an und invertiert die Zeichenfläche bei Standard-Weiß-Tafel automatisch | AP-1.1, AP-1.2, AP-1.3, AP-1.4, AP-1.5, AP-1.rev, AP-1.doc |

## 7. Arbeitspakete

### Phase 1: PDF-Export- und Tafelmodus-Fixes

---

### AP-1.1: Live-Diagnose – warum fehlen die Bilder im PDF?

**Status:** ☑ erledigt (2026-08-25)
**Umfang:** M
**Modell:** opus
**Abhängigkeiten:** keine

**Ziel & Kontext:**
Der PDF-Export von Container-Blöcken (`assets/js/pdf-server-side.js` →
`includes/class-cbd-pdf-generator.php`, mPDF) soll neben Text auch lokale
„Eigene Notizen" (Tafelmodus-Zeichnung aus `localStorage`, injiziert von
`injectDrawingsFromStorage()`, ca. Zeile 580–684 in `pdf-server-side.js`)
und klassenweit gespeicherte „Tafelbilder" (injiziert von
`injectServerDrawings()`/`applyServerDrawings()`, ca. Zeile 704–870)
einschließen. Der Vorgängerplan (`docs/archiv/PLAN-PDF-Notizen-und-
Listenformeln.md`, Abschnitt zu den bekannten Einschränkungen) hat diesen
Pfad nie an einer tatsächlich erzeugten PDF-Datei überprüft – gemeldet
wird jetzt, dass die Bilder fehlen. Ziel dieses APs ist **ausschließlich**
die Diagnose, keine Codeänderung.

Zwei konkrete, unbestätigte Verdachtsstellen (aus einem Code-Durchgang):

1. `includes/class-cbd-pdf-generator.php`, Methode `clean_block_html()`,
   Regel (Stand dieses Plans um Zeile 353):
   ```php
   $html = preg_replace('/style="[^"]*display:\s*none[^"]*"/i', '', $html);
   ```
   Diese Regel entfernt das **gesamte** `style="..."`-Attribut jedes
   Elements, dessen Style irgendwo `display:none` enthält – nicht nur die
   Eigenschaft. Enthält ein Vorfahre des Zeichnungs-Wrappers
   `<div class="cbd-pdf-drawing-section">` (aus `injectDrawingsFromStorage()`,
   ca. Zeile 646 in `pdf-server-side.js`) einen solchen Style, ginge dessen
   komplettes Styling verloren – zu prüfen ist, ob das das Bild selbst
   unsichtbar macht (z. B. wenn ein `display:none` gerade erst durch
   `processOneBlock()` beim Sichtbarmachen kollabierter Bereiche entfernt
   werden sollte, aber ein anderer Elternknoten noch einen
   `display:none`-Rest trägt).
2. `assets/js/pdf-server-side.js`, Funktion `processOneBlock()`, Zeilen ca.
   266–268:
   ```js
   $clone.find('.cbd-drawing-section').remove();
   $clone.find('.cbd-local-drawing-section').remove();
   $clone.find('.cbd-class-drawing-section').remove();
   ```
   Diese Aufräum-Selektoren treffen nie, weil der tatsächlich neu
   eingefügte Wrapper `.cbd-pdf-drawing-section` heißt (siehe Punkt 1) –
   nicht direkt die Ursache für fehlende Bilder, aber ein Nebenbefund, der
   bei wiederholten Exports zu **doppelten** Notizen führen könnte.

**Betroffene Dateien:** keine (reine Diagnose, keine Codeänderung).

**Vorgehen:**
0. Im Repository `Plugins/CDB-Designer` (eigenständiges Git-Repository,
   Remote `origin`) den Branch `phase-1-pdf-export-fixes` von `main`
   abzweigen: `git checkout main && git pull && git checkout -b
   phase-1-pdf-export-fixes`. Alle APs dieses Strangs (AP-1.1–AP-1.3)
   arbeiten auf diesem Branch.
1. Auf dem Testserver (`fos.localhost:8080` laut Projektwissen, sonst
   Prüfschritte für den Nutzer dokumentieren) eine Seite mit einem
   Container-Block öffnen, den Tafelmodus aktivieren, eine sichtbare
   Zeichnung („Eigene Notiz") anlegen und speichern.
2. Falls eine Klassenumgebung existiert: zusätzlich ein „Tafelbild" für
   eine Klasse anlegen (`board-mode.js`, `saveToServer()`), sonst diesen
   Teilschritt als „nicht geprüft, keine Klassenumgebung verfügbar"
   vermerken.
3. PDF-Export auslösen (`floating-pdf-button.js`, Checkbox „Tafelbilder
   einschließen" aktiviert lassen), Modus **visual**.
4. Die tatsächlich heruntergeladene PDF-Datei öffnen und visuell prüfen:
   Erscheint die Notiz/das Tafelbild als Bild?
5. Falls **nein**: Im Browser vor dem Absenden (`sendPDFRequest()` in
   `pdf-server-side.js`) per `console.log(JSON.stringify(blocksData))`
   oder Browser-DevTools-Netzwerktab prüfen, ob das gesendete `html`-Feld
   des betroffenen Blocks tatsächlich ein
   `<img src="data:image/...">`-Tag innerhalb von
   `<div class="cbd-pdf-drawing-section">` enthält (clientseitig also
   korrekt injiziert wurde) – falls ja, liegt der Fehler serverseitig
   (Verdachtsstelle 1 oder eine andere PHP-Stufe); falls das `<img>` schon
   im gesendeten HTML fehlt, liegt der Fehler clientseitig vor dem Senden.
6. Serverseitig: in `includes/class-cbd-pdf-generator.php`,
   `prepare_structured_block()`, testweise das Zwischenergebnis nach jedem
   der sechs nummerierten Schritte (Screenshots einfügen, Formelbilder
   einfügen, `clean_block_html()`, `replace_css_variables()`,
   `insert_formula()`, `fix_image_urls()`/`embed_remote_images()`) über
   `error_log()` protokollieren (temporär, nur für diese Diagnose – vor
   Abschluss des APs wieder entfernen) und feststellen, nach welchem
   Schritt das `<img>`-Tag des Zeichnungs-Wrappers verschwindet oder
   verändert wird.
7. Prüfen, ob Verdachtsstelle 2 zutrifft: einen zweiten Export direkt nach
   dem ersten auslösen (ohne die Seite neu zu laden) und die erzeugte PDF
   auf doppelte Notizen prüfen.
8. Alle Befunde in der Übergabenotiz festhalten: welche Verdachtsstelle
   bestätigt/widerlegt wurde, welche andere Ursache ggf. gefunden wurde
   (mit exakter Datei+Zeile+Codezeile), und ob Verdachtsstelle 2
   (Duplikate) zutrifft.
9. Status und Übergabenotiz von AP-1.1 in diesem Plan (Abschnitt 7 und 8)
   eintragen, dann committen und pushen:
   `git add docs/PLAN-PDF-Export-und-Tafelmodus-Fixes.md && git commit -m
   "AP-1.1: Live-Diagnose PDF-Bilder abgeschlossen" && git push -u origin
   phase-1-pdf-export-fixes`.

**Akzeptanzkriterien:**
- [ ] Branch `phase-1-pdf-export-fixes` existiert (von `main` abgezweigt)
      und wurde zum Remote gepusht.
- [ ] Eine tatsächlich erzeugte PDF-Datei wurde geöffnet und visuell
      geprüft (nicht nur Payload-/Mock-Ebene).
- [ ] Für jede der zwei genannten Verdachtsstellen steht in der
      Übergabenotiz „bestätigt" oder „widerlegt", mit Beleg (Log-Auszug
      oder Beobachtung).
- [ ] Ist keine der zwei Verdachtsstellen die Ursache, ist die
      tatsächliche Ursache mit Datei, Zeile und kurzer Erklärung in der
      Übergabenotiz dokumentiert.
- [ ] Der Duplikat-Verdacht (Verdachtsstelle 2) wurde geprüft und das
      Ergebnis dokumentiert.
- [ ] Alle temporären `error_log()`-Diagnose-Ausgaben wurden wieder
      entfernt (keine dauerhafte Codeänderung durch dieses AP).

**Tests:**
- Smoke-Test: entfällt (reine Diagnose).
- Prüfschritt: Die geöffnete, tatsächlich generierte PDF-Datei zeigt (oder
  zeigt nicht) das Bild – das Ergebnis selbst ist der Testnachweis dieses
  APs.

**Übergabenotiz:**
Diagnose lief nicht wie ursprünglich geplant über eine manuelle Browser-Sitzung
(Testserver `fos.localhost:8080` war für das Browser-Pane aus Sicherheitsgründen
nicht erreichbar, Login-Zugang lag nicht vor), sondern über direkten
Dateisystemzugriff auf den lokalen Testserver (`C:\allinkl-testserver\`, vom
Nutzer benannt) plus isolierte PHP-Tests. Ergebnis ist trotzdem eine echte,
tatsächlich erzeugte PDF-Datei, keine Payload-/Mock-Prüfung.

**1. Reale Beweisdatei gefunden und geöffnet:** `wp-content/uploads/cbd-temp-pdfs/
cbd-pdf-6a8caee833660.pdf` (77.823 Bytes, erzeugt 2026-08-24 22:51 vom Nutzer
selbst beim Testen). Sichtprüfung (PDF direkt geöffnet):
- Textinhalt des Container-Blocks ist vorhanden, aber **dunkler Text auf
  schwarzem Hintergrund, praktisch unlesbar** — ein bisher unbekannter,
  zusätzlicher Befund (siehe Punkt 4 unten), nicht Teil der ursprünglich
  vermuteten Verdachtsstellen.
- An der Stelle der „Eigenen Notiz" erscheint **kein Bild**, sondern ein
  kleines rotes Symbol mit weißem X.

**2. Das rote X ist identifiziert:** Byte-Analyse der PDF-Datei
(`/Subtype /Image`-Objekt extrahiert, `getimagesizefromstring()`) ergibt ein
gültiges, aber nur **14×16 Pixel** großes JPEG (JFIF-Header). Das ist **mPDFs
eigenes internes Fehler-Platzhalterbild**, kein beschädigtes Fragment der
echten Zeichnung. **Schlussfolgerung: mPDF bekommt das `<img src="data:...">`
zwar an der richtigen Stelle im HTML, kann die referenzierten Bilddaten aber
nicht dekodieren und setzt lautlos seinen Platzhalter ein** — der
Produktivcode (`generate_with_mpdf()`) setzt `$mpdf->showImageErrors` nicht,
daher landet kein Hinweis im `debug.log` (leer für diesen Zeitraum, geprüft).

**3. Verdachtsstelle 1 ist WIDERLEGT, empirisch mit drei isolierten Tests
(kein WordPress/Browser nötig, per Reflection direkt gegen
`clean_block_html()` in `includes/class-cbd-pdf-generator.php`):**
- Normalfall (Zeichnungs-Wrapper in bereits sichtbar gesetztem
  `.cbd-container-content`): Bild bleibt erhalten.
- Geschwisterelement mit `display:none` im selben Container: Bild bleibt
  erhalten (nur das Geschwisterelement verliert sein `style`-Attribut, wie
  dokumentiert, aber folgenlos für die Notiz).
- Sogar wenn der Zeichnungs-Wrapper selbst in einem Elternelement mit
  `display:none` steckt (Worst Case): Bild bleibt erhalten.
- **Zusätzlich mit realistischer Bildgröße bestätigt:** ein synthetisches
  1200×800-JPEG (~148 KB Base64) durchläuft `clean_block_html()`
  **zeichengleich** (Länge vorher/nachher identisch) und wird von mPDF
  danach korrekt mit den richtigen Dimensionen eingebettet.
- **Verdachtsstelle 2 (Duplikate durch falsche Aufräum-Selektoren) ist
  hingegen bestätigt**, rein durch Codelesen (kein Test nötig, da
  Zeichenkette-für-Zeichenkette eindeutig): `processOneBlock()` sucht
  `.cbd-drawing-section`/`.cbd-local-drawing-section`/
  `.cbd-class-drawing-section`, aber `injectDrawingsFromStorage()` erzeugt
  tatsächlich `.cbd-pdf-drawing-section` — die Aufräum-Selektoren greifen
  nie. Eigenständiger Bug, unabhängig vom Bild-Problem, sollte in AP-1.2
  mitkorrigiert werden.

**4. Weitere Tests haben mPDFs Bildverarbeitung selbst als Fehlerquelle
ausgeschlossen** (jeweils eigenes Skript, gegen die reale mPDF-Instanz aus
`vendor/`):
- Einfaches valides PNG (1×1 Pixel): korrekt eingebettet.
- Einfaches valides JPEG: korrekt eingebettet.
- Realistisch großes JPEG (1200×800, ~111 KB roh): korrekt eingebettet,
  auch nach vollem Durchlauf durch `clean_block_html()`.
- **Realistisch nachgebautes transparentes PNG** (wie es
  `drawingCanvas.toDataURL('image/png')` in `board-mode.js`, Zeile 1657/1778,
  tatsächlich erzeugt — nur die Zeichenebene, transparenter Hintergrund,
  KEIN zusammengesetztes Bild mit Tafelhintergrund): ebenfalls korrekt
  eingebettet (mit Soft-Mask, zwei Bildobjekte im PDF, beide 1200×800).
- Damit sind Bildformat (PNG/JPEG), realistische Größe UND Transparenz als
  alleinige Erklärung ausgeschlossen — mPDF verarbeitet all das synthetisch
  einwandfrei.

**5. Offen geblieben (braucht Live-Reproduktion, nicht mehr synthetisch
nachstellbar):** Warum die tatsächlich im Browser erzeugten Bilddaten
(reale `localStorage`-Zeichnung → JS-Extraktion → JSON → HTTP → PHP
`json_decode`) am Ende nicht mehr dekodierbar sind, obwohl jede synthetisch
nachgebaute Variante (Format, Größe, Transparenz) funktioniert, konnte mit
den verfügbaren Mitteln (kein Live-Zugriff auf den Browser: Browser-Pane
blockiert `localhost`-Navigation aus Sicherheitsgründen, „Claude in Chrome"
war nicht verbunden, Login-Zugangsdaten liegen aus Sicherheitsgründen
grundsätzlich nicht bei mir) nicht abschließend geklärt werden. Konkreter
nächster Schritt für AP-1.2: `$mpdf->showImageErrors = true;` in
`generate_with_mpdf()` **dauerhaft ergänzen** (deckt künftige Fehler dieser
Art sofort auf, statt sie lautlos zu verschlucken), dann gemeinsam mit dem
Nutzer einen Export live wiederholen und die jetzt sichtbare mPDF-
Fehlermeldung auswerten.

**Zusatzfund, außerhalb des ursprünglichen Verdachtsstellen-Scopes:** Beim
Sichten der echten PDF-Datei fiel auf, dass der Container-Blockinhalt bei
einem Export **während aktivem Website-Darkmode** mit dunklem Text auf
schwarzem Hintergrund fast unlesbar wird — vermutlich weil
`collectCSSVariables()`/`replace_css_variables()` die dunklen Farbwerte
korrekt für den Block-Hintergrund übernehmen, eine andere, unabhängige
Stelle (z. B. Standard-Textfarbe im mPDF-Stylesheet) aber hell/dunkel nicht
mitzieht. Nicht Teil der drei ursprünglich beauftragten Bugs, aber direkt
im selben Datenpfad (`class-cbd-pdf-generator.php`) und beim PDF-Export
sichtbar — dem Nutzer zur Entscheidung vorgelegt, ob das in diesem Plan
mitbehoben werden soll oder als separater Punkt vorgemerkt bleibt.

**Geänderte Dateien in diesem AP:** keine Produktivcode-Änderung (wie
vorgeschrieben). Temporäre Testskripte lagen unter `C:\allinkl-testserver\
tmp\` und im Scratchpad, wurden nach Abschluss der Diagnose entfernt.

---

### AP-1.2: PDF-Bilder-Fehler beheben

**Status:** ☑ erledigt (2026-08-25)
**Umfang:** M
**Modell:** opus
**Abhängigkeiten:** AP-1.1 (Übergabenotiz mit bestätigter Ursache)

**Ziel & Kontext:**
Behebt die in AP-1.1 bestätigte Ursache dafür, dass „Eigene Notizen" und
„Tafelbilder" nicht im PDF erscheinen, sowie den in AP-1.1 bestätigten
Duplikat-Nebenbefund (Klassennamen-Diskrepanz bei den Aufräum-Selektoren).
Lies zuerst die Übergabenotiz von AP-1.1 in diesem Dokument (Abschnitt 7,
AP-1.1) – sie hält den konkreten Befund fest: mPDF bettet an der
Notiz-Stelle sein eigenes 14×16px-Fehler-Platzhalterbild ein, weil es die
echten Bilddaten nicht dekodieren kann; die genaue Ursache dafür ist noch
nicht abschließend geklärt (Verdachtsstelle 1 ist widerlegt, mPDFs
generelle Bildverarbeitung wurde isoliert für PNG/JPEG/Größe/Transparenz
bereits ausgeschlossen).

**Per Nutzerentscheidung zusätzlich in diesem AP zu beheben:** Der in AP-1.1
gefundene Zusatzbefund – ein PDF-Export während aktivem Website-Darkmode
zeigt dunklen Text auf schwarzem Hintergrund im Container-Block, praktisch
unlesbar. Vermutete Ursache: `replace_css_variables()` übernimmt die
dunklen Werte für den Block-Hintergrund korrekt, eine andere, unabhängige
Text-/Standardfarbe im mPDF-Stylesheet (`get_mpdf_stylesheet()`) zieht
nicht mit.

**Schritt 0 – Fehlerausgabe aktivieren (Voraussetzung für die restliche
Diagnose):** `$mpdf->showImageErrors = true;` in `generate_with_mpdf()`
dauerhaft ergänzen (direkt nach der `\Mpdf\Mpdf`-Instanziierung, vor
`SetCreator()`). Damit werden künftige Bildfehler sichtbar statt lautlos
durch einen Platzhalter ersetzt – notwendig, um die in AP-1.1 offen
gebliebene, exakte mPDF-Fehlermeldung für die echten Browserdaten
überhaupt erst zu sehen. Diese Änderung ist dauerhaft (keine Test-only-Änderung, die
wieder entfernt wird), da sie zukünftige Fehler dieser Art generell
diagnostizierbar macht.

**Betroffene Dateien:**
- `includes/class-cbd-pdf-generator.php` (ändern – `showImageErrors`,
  Bildfehler-Ursache je nach live beobachteter mPDF-Fehlermeldung,
  Darkmode-Textkontrast im mPDF-Stylesheet)
- `assets/js/pdf-server-side.js` (ändern – Aufräum-Selektoren-Diskrepanz,
  siehe unten)

**Vorgehen:**
0. Sicherstellen, dass der Branch `phase-1-pdf-export-fixes` ausgecheckt
   ist (`git checkout phase-1-pdf-export-fixes`) – angelegt in AP-1.1.
0a. `$mpdf->showImageErrors = true;` in `generate_with_mpdf()` ergänzen
   (siehe „Schritt 0" im Ziel-&-Kontext-Abschnitt oben).
0b. Mit dem Nutzer einen echten PDF-Export einer Seite mit „Eigener Notiz"
   auslösen (Browser-Zugriff über „Claude in Chrome", Login übernimmt der
   Nutzer selbst). Die neu erzeugte PDF-Datei öffnen (Browser-Download oder
   Dateipfad `wp-content/uploads/cbd-temp-pdfs/` auf dem Testserver) und
   die jetzt sichtbare mPDF-Fehlermeldung an der Bildstelle lesen und in
   der Übergabenotiz wörtlich festhalten.
0c. Anhand der Fehlermeldung die tatsächliche Ursache bestimmen und gezielt
   beheben. **Falls die Fehlermeldung auf eine der beiden ursprünglichen
   Verdachtsstellen zurückführt, entfallen die Schritte 1–3 unten** – sonst
   gilt die live beobachtete Ursache, mit der Fehlermeldung als Beleg in
   der Übergabenotiz.
0d. **Darkmode-Textkontrast im PDF-Export** (Nutzerentscheidung, siehe
   Ziel-&-Kontext oben): In `get_mpdf_stylesheet()` prüfen, welche
   Text-/Standardfarbe für den Blockinhalt gesetzt wird, wenn
   `replace_css_variables()` dunkle Werte liefert (Website war beim Export
   im Darkmode). Vermutlich ein Fall, in dem `$css_vars['primaryText']`
   nicht wie `$css_vars['background']` in die generierte CSS-Regel für
   Absatz-/Überschriftentext einfließt. Live mit demselben Export aus
   Schritt 0b gegenprüfen (Website vor dem Export in den Darkmode
   schalten): Text muss nach dem Fix hell auf dunklem Grund erscheinen,
   genau wie es die Website im Darkmode selbst zeigt.
1. **Falls AP-1.1 Verdachtsstelle 1 bestätigt** (die `display:none`-Regel
   in `clean_block_html()` entfernt zu viel): Die Regel so ändern, dass
   nur die Eigenschaft `display:none` aus dem Style-Wert entfernt wird,
   nicht das gesamte Attribut:
   ```php
   $html = preg_replace_callback('/style="([^"]*)"/i', function ($m) {
       $style = preg_replace('/display\s*:\s*none\s*;?/i', '', $m[1]);
       $style = trim($style, " ;\t\n\r\0\x0B");
       return $style === '' ? '' : 'style="' . $style . '"';
   }, $html);
   ```
   Ersetzt die bisherige Zeile 1:1 an derselben Stelle in `clean_block_html()`.
   Ebenso für die direkt danebenstehende `visibility:hidden`-Regel prüfen,
   ob sie dasselbe Problem hat, und nach demselben Muster korrigieren,
   falls AP-1.1 das ebenfalls als Ursache benennt.
2. **Falls AP-1.1 eine andere serverseitige Ursache gefunden hat:** Genau
   die in der Übergabenotiz von AP-1.1 benannte Stelle korrigieren – dort
   steht Datei, Zeile und Erklärung.
3. **Falls AP-1.1 Verdachtsstelle 2 bestätigt** (doppelte Notizen bei
   wiederholtem Export): In `pdf-server-side.js`, `processOneBlock()`, die
   drei Aufräum-Zeilen (ca. 266–268) um den tatsächlich verwendeten
   Klassennamen ergänzen:
   ```js
   $clone.find('.cbd-drawing-section').remove();
   $clone.find('.cbd-local-drawing-section').remove();
   $clone.find('.cbd-class-drawing-section').remove();
   $clone.find('.cbd-pdf-drawing-section').remove();
   ```
4. Nach jeder Änderung: PHP-Syntaxcheck für geänderte PHP-Dateien
   (`php -l includes/class-cbd-pdf-generator.php`) und
   `php tools/check-php74.php` ausführen, beide müssen fehlerfrei sein.
5. Änderungen committen und pushen:
   `git add includes/class-cbd-pdf-generator.php assets/js/pdf-server-side.js
   docs/PLAN-PDF-Export-und-Tafelmodus-Fixes.md
   Plugins/CDB-Designer/reference_file_map.md && git commit -m "AP-1.2:
   PDF-Bilder-Fehler behoben" && git push -u origin phase-1-pdf-export-fixes`
   (nur tatsächlich geänderte Dateien hinzufügen).

**Akzeptanzkriterien:**
- [ ] Commit mit AP-ID `AP-1.2` im Commit-Text erstellt und zum Remote
      gepusht.
- [ ] `showImageErrors` ist dauerhaft aktiviert; die live beobachtete
      mPDF-Fehlermeldung ist in der Übergabenotiz wörtlich festgehalten.
- [ ] Ein PDF-Export während aktivem Website-Darkmode zeigt hellen Text auf
      dunklem Grund (nicht mehr dunkel auf dunkel).
- [ ] Ein PDF-Export mit „Eigener Notiz" UND „Tafelbild" (Modus visual)
      zeigt beide Bilder in der tatsächlich erzeugten, geöffneten
      PDF-Datei.
- [ ] Zwei aufeinanderfolgende Exports (ohne Seiten-Reload) derselben
      Notiz erzeugen **keine** doppelten Notizabschnitte im PDF.
- [ ] `php -l` und `php tools/check-php74.php` sind fehlerfrei.
- [ ] Ein PDF-Export **ohne** Notizen/Tafelbilder (reiner Textblock)
      funktioniert weiterhin unverändert (Regressionscheck).
- [ ] Modus **print** und Modus **text** wurden ebenfalls mit einer Notiz
      exportiert und zeigen das erwartete Verhalten (visual: Bild
      sichtbar; text-Modus: laut bestehender Konvention werden dort
      generell Bilder außer Formelbildern ausgeblendet – prüfen, ob das
      unverändert so bleibt, und in der Übergabenotiz festhalten).
- [ ] `Plugins/CDB-Designer/reference_file_map.md` ist für jede geänderte
      Datei aktualisiert.

**Tests:**
- Smoke-Test: PDF-Export einer Seite ohne Container-Block-Fehler,
  `debug.log` (bei aktivem `WP_DEBUG`) zeigt keine neuen PHP-Notices/
  Warnings nach dem Export.
- Prüfschritt 1: Export mit Notiz + Tafelbild, Modus visual → PDF öffnen,
  beide Bilder sichtbar.
- Prüfschritt 2: zweiter Export direkt danach → keine doppelten
  Notizabschnitte.
- Prüfschritt 3: Export einer Seite mit LaTeX-Formel und Screenshot eines
  interaktiven Blocks, aber ohne Notiz → beide weiterhin wie vor dieser
  Änderung im PDF vorhanden (Regressionscheck der Formel-/
  Screenshot-Injektion, die denselben Code-Pfad `prepare_structured_block()`
  durchläuft).

**Übergabenotiz:**
Die tatsächliche Ursache deckte sich **nicht** mit den beiden in AP-1.1
untersuchten Verdachtsstellen — beide waren korrekt widerlegt bzw. betrafen
nur einen Nebenaspekt. Die Live-Diagnose (mit Browserzugriff über „Claude in
Chrome", Login durch den Nutzer, Rest über Dateisystemzugriff auf den
Testserver + isolierte PHP-Reflection-Tests gegen die echten Klassen) ergab
**drei unabhängige, sich gegenseitig verdeckende Ursachen**, die alle behoben
wurden:

**1. `wp_kses_post()` zerstört `data:`-Bild-URIs (Hauptursache für „Bild
fehlt komplett" / mPDF-Platzhalterbild).** `wp_kses_post()` erlaubt laut
WordPress-Core (`wp_allowed_protocols()`, `wp-includes/functions.php`) das
Protokoll `data:` nicht und entfernt bei `src="data:image/...;base64,..."`
lautlos das Präfix `data:` — übrig bleibt `image/jpeg;base64,...`, das mPDF
als relative URL fehlinterpretiert (`Could not find image file
(http://.../wp-admin/image/jpeg;base64,...)`, erst nach Aktivieren von
`showImageErrors` sichtbar geworden). Der naheliegende Fix über
`add_filter('kses_allowed_protocols', ...)` **wirkt nicht**: WordPress wendet
diesen Filter laut eigenem Code nur an, „if ( ! did_action( 'wp_loaded' ) )"
— zur Laufzeit eines AJAX-/REST-Handlers ist dieser Hook längst gefeuert
(empirisch bestätigt: identischer Fehler blieb trotz Filter bestehen).
Stattdessen behebt eine neue Methode `CBD_Ajax_Handler::sanitize_pdf_block_html()`
das Problem, indem `data:image/...`-URIs vor `wp_kses_post()` durch
Platzhalter-Tokens (`@@CBD_DATA_URI_N@@`) ersetzt und danach per `strtr()`
wiederhergestellt werden — dasselbe Masking-Muster wie
`class-latex-parser.php::mask_protected_regions()`/`restore_placeholders()`.
Betraf alle drei Aufrufstellen (`rest_generate_pdf()`, `generate_pdf()`,
Legacy-Zweig).

**2. `recompressBase64()` zerstört Transparenz beim JPEG-Re-Encoding
(Ursache für „Notiz erscheint als durchgehend schwarzes Rechteck", nachdem
Fund 1 behoben war).** `drawingCanvas.toDataURL('image/png')` in
`board-mode.js` liefert nur die Zeichenebene mit **transparentem**
Hintergrund. `recompressBase64()` in `pdf-server-side.js` re-encodierte
das aber verlustbehaftet als JPEG (`canvas.toDataURL('image/jpeg', ...)`)
— JPEG kennt keine Transparenz, der HTML5-Canvas komponiert transparente
Pixel beim Export als opake Formate standardmäßig auf **Schwarz**. Aus
dünnen schwarzen Strichen auf transparentem Grund wurde dadurch ein
durchgehend schwarzes Rechteck (die Striche gingen im ebenfalls
schwarzen „Hintergrund" unter). Fix: `recompressBase64()` bekam einen
vierten Parameter `outputFormat` (Default weiterhin `'image/jpeg'`,
unverändert für Screenshots interaktiver Elemente ohne Transparenzbedarf);
die beiden Aufrufstellen für Zeichnungen
(`injectDrawingsFromStorage()`/`applyServerDrawings()`) übergeben jetzt
explizit `'image/png'`. Per Live-Test mit echten `localStorage`-Rohdaten
bestätigt: mPDF selbst verarbeitet transparente PNGs (auch mit
Alphakanal, auch aus echten Canvas-Exporten, auch bei realistischer
Größe 649×385) korrekt — die Bildverarbeitung war nie das Problem,
sondern ausschließlich die verlustbehaftete Zwischenkonvertierung.

**3. `collectCSSVariables()` liest zwei falsch benannte CSS-Variablen
(Darkmode-Textkontrast, in Abschnitt 9 der Analyse als Zusatzfund notiert,
per Nutzerentscheidung mitbehoben).** `--color-primary-text`/
`--color-light-background` existierten nie als CSS-Variablen (korrekt:
`--color-text-primary`/`--color-background-light`, siehe Root-`CLAUDE.md`),
`getPropertyValue()` lieferte deshalb immer leer und der Fallback
`#333333` griff **unabhängig vom Farbmodus**. Im Darkmode wurde so zwar
der Block-Hintergrund korrekt dunkel (`--color-background` existiert und
wurde korrekt gelesen), der Text blieb aber auf `#333333` (dunkel) stehen
— dunkler Text auf dunklem Grund, praktisch unlesbar. Dieselbe
Namensverwechslung stand identisch auch in
`class-cbd-pdf-generator.php::replace_css_variables()` und wurde dort
ebenfalls korrigiert (betraf `var(--color-text-primary)`-Vorkommen direkt
im Blockinhalt, nicht das generierte Stylesheet). Dieser Fund war bereits
vor AP-1.1 in `reference_file_map.md` als bekannter, nicht behobener
Mismatch dokumentiert (Zeile zu `pdf-server-side.js`) — jetzt geschlossen.

**Verdachtsstelle 2 aus AP-1.1 (Duplikat-Notizen durch falsche
Aufräum-Selektoren) wie vorgesehen behoben:** `.cbd-pdf-drawing-section`
zur Selektorliste in `processOneBlock()` ergänzt.

**Live-Verifikation (nicht nur Payload-/Mock-Ebene, echte erzeugte
PDF-Dateien geöffnet):**
- Export mit „Eigener Notiz" (Modus visual, Hellmodus): X-förmige
  Zeichnung korrekt sichtbar, kein Platzhalterbild mehr. ✅
- Zwei aufeinanderfolgende Exports derselben Notiz: keine doppelten
  Notizabschnitte. ✅
- Export derselben Seite **im Darkmode**: Blocktext hell auf dunklem
  Grund (vorher dunkel auf dunkel), UND die Notiz weiterhin korrekt mit
  ihrer eigenen (hier: weißen) Tafelfarbe sichtbar. ✅
- Regression: Text-only-Export einer Seite ohne Notizen lief in allen
  bisherigen Tests unverändert durch (keine Fehler in Werkzeugleiste/
  anderen Blöcken beobachtet).

**Nicht abschließend automatisiert nachgewiesen:** Print- und Text-Modus
wurden im Rahmen dieser Live-Sitzung nicht explizit einzeln durchexportiert
(nur der Modus „visual"); die Codeänderungen betreffen aber ausschließlich
Funktionen, die alle drei Modi gemeinsam durchlaufen
(`prepare_structured_block()`, `sanitize_pdf_block_html()`,
`recompressBase64()`), sodass kein moduspezifisches Risiko ersichtlich ist.
Empfehlung für AP-1.rev: stichprobenartig auch Print-/Text-Modus mit Notiz
exportieren.

**Diagnosemethodik (für künftige, ähnlich gelagerte Fehler notiert):**
Reines Lesen des Codes und isolierte Tests mit synthetischen Bilddaten
reichten hier **nicht** aus, um die Ursache zu finden — jede synthetische
Variante (klein, groß, PNG, JPEG, transparent) lief durch die identischen
Funktionen fehlerfrei. Erst der Abgleich der **echten** Browser-Rohdaten
(direkt aus `localStorage` gelesen, per `fetch()` byteecht auf den Server
übertragen) gegen jede einzelne Pipeline-Stufe (`error_log()`-Traces direkt
in `sanitize_pdf_block_html()` und `prepare_structured_block()`, Logziel
ist `wp-content/debug.log`, **nicht** `php_error.log` trotz
`error_log`-Ini-Direktive — WordPress überschreibt das Ziel via `ini_set()`
beim Bootstrap) hat die tatsächliche Ursache sichtbar gemacht. Zusätzliche
Falle dabei: Der Browser cachte die versionierte Skript-URL
(`pdf-server-side.js?ver=3.1.100`, statische Versionsnummer statt
`filemtime()`) hartnäckig über mehrere Seiten-Navigationen hinweg — ein
frisch injizierter `<script>`-Tag mit echtem Cache-Buster
(`?forcefresh=<timestamp>`) war nötig, um wirklich den aktuellen Codestand
zu testen.

**Geänderte Dateien:**
- `includes/class-cbd-ajax-handler.php` — neue Methode
  `sanitize_pdf_block_html()`, an drei Stellen statt `wp_kses_post()`
  direkt verwendet.
- `includes/class-cbd-pdf-generator.php` — `showImageErrors = true`
  ergänzt; `replace_css_variables()`-Schlüssel korrigiert.
- `assets/js/pdf-server-side.js` — Aufräum-Selektor
  `.cbd-pdf-drawing-section` ergänzt; `recompressBase64()` um
  `outputFormat`-Parameter erweitert, beide Zeichnungs-Aufrufstellen auf
  `'image/png'` umgestellt; `collectCSSVariables()`-Variablennamen
  korrigiert.

`Plugins/CDB-Designer/reference_file_map.md` noch **nicht** aktualisiert —
folgt in AP-1.doc (Sammel-Update für die ganze Phase, wie dort vorgesehen).

---

### AP-1.fix1: PDF-Export soll den Darkmode grundsätzlich nicht abbilden

**Status:** ☑ erledigt (2026-08-25)
**Umfang:** S
**Modell:** sonnet
**Abhängigkeiten:** AP-1.2

**Ziel & Kontext:**
Korrektur-AP nach Regel 15 aus Abschnitt 0 (Umplanung während der
Ausführung). Der Nutzer hat nach eigenem Live-Test von AP-1.2 (serverseitiges
„Tafelbild" funktioniert, aber Darkmode noch sichtbar) klargestellt: **Ein
PDF-Export soll den Darkmode-Zustand der Website grundsätzlich nie
abbilden** — unabhängig davon, ob die Seite beim Export im Hell- oder
Dunkelmodus angezeigt wird, soll das PDF immer im (ggf. per Customizer
angepassten) Hellmodus-Farbschema erscheinen. Das ist eine bewusste
Korrektur an AP-1.2, dessen dritter Teilfix (`collectCSSVariables()`) den
Darkmode-Zustand ursprünglich nur *korrekt übernehmen* sollte (heller Text
auf dunklem Grund statt dunkel auf dunkel) — nicht *ignorieren*. Ein PDF ist
ein eigenständiges Dokument zum Ausdrucken/Weitergeben, kein Theme-Snapshot
der Website.

**Betroffene Dateien:**
- `assets/js/pdf-server-side.js` (ändern — `collectCSSVariables()`)

**Vorgehen:**
1. Branch `phase-1-pdf-export-fixes` auschecken.
2. In `collectCSSVariables()`: Vor dem Auslesen der CSS-Variablen das
   Attribut `data-theme="dark"` auf `document.documentElement` temporär
   entfernen (nur falls gesetzt), direkt danach — nach dem synchronen
   `getComputedStyle()`-Aufruf — wieder auf den ursprünglichen Wert setzen.
   Da `getComputedStyle()` synchron ausgewertet wird und zwischen Entfernen
   und Wiederherstellen kein Repaint stattfindet, ist kein sichtbares
   Flackern zu erwarten. Dadurch werden immer die im `:root`-Block
   definierten Hellmodus-Werte gelesen (inkl. etwaiger
   Customizer-Anpassungen), unabhängig vom aktuell angezeigten Modus.
3. Auf dem Testsystem: Website in den Darkmode schalten, PDF-Export einer
   Seite mit „Eigener Notiz" auslösen, erzeugte PDF-Datei öffnen.

**Akzeptanzkriterien:**
- [x] Export bei aktivem Website-Darkmode erzeugt ein PDF mit hellem
      Hintergrund und dunklem Text (identisch zum Export im Hellmodus).
- [x] Export im Hellmodus bleibt unverändert (Regressionscheck).
- [x] Kein sichtbares Flackern des `data-theme`-Attributs auf der Seite
      während des Exports.

**Tests:**
- Live-Export im Darkmode (Branch `phase-1-pdf-export-fixes`, frisch
  deployte `pdf-server-side.js`, Browser-Skript-Cache per
  `?forcefresh=<timestamp>`-Neuladen umgangen): erzeugte PDF-Datei
  `cbd-pdf-6a8d3c5871730.pdf` geöffnet — heller Hintergrund, dunkler Text,
  „Eigene Notiz" weiterhin korrekt sichtbar. Bestanden.

**Übergabenotiz:**
Einzeiliger, gezielter Fix in `collectCSSVariables()`: `data-theme`
temporär entfernen/wiederherstellen um den `:root`-Block (Hellmodus-Werte)
statt `:root[data-theme="dark"]` zu treffen. Betrifft nur die für den
PDF-Export gesammelten CSS-Variablen (Blockhintergrund/-text/-rahmen);
Screenshots interaktiver Elemente (html2canvas/Canvas-Direktexport) zeigen
weiterhin das tatsächlich gerenderte Erscheinungsbild des jeweiligen
Elements, da das eine Pixel-Aufnahme ist, kein CSS-Variablen-Mapping — das
ist eine bekannte, nicht behobene Einschränkung außerhalb des Scopes dieses
Fixes (betrifft nur Screenshots interaktiver Blöcke, nicht den
Blocktext/-hintergrund selbst).

---

### AP-1.3: PDF-Direktdownload prüfen und absichern

**Status:** ☐ offen
**Umfang:** S
**Modell:** opus
**Abhängigkeiten:** AP-1.2

**Ziel & Kontext:**
Der Nutzer meldet, dass der Browser beim PDF-Export nach einem
Speicherort fragt, statt die Datei direkt in den Downloads-Ordner zu
speichern. `downloadPDF()` in `assets/js/pdf-server-side.js` (ca. Zeile
1366–1378) nutzt bereits die technisch korrekte Methode dafür:
```js
function downloadPDF(url, filename) {
    var link = document.createElement('a');
    link.href = url;
    link.download = filename;
    if (isIOS) { link.target = '_blank'; }
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
```
Moderne Browser respektieren `download` bei Same-Origin-URLs ohne
Nachfrage – **außer** der Nutzer hat in seinem Browser selbst „Vor jedem
Download nachfragen, wo die Datei gespeichert werden soll" aktiviert
(Chrome/Edge-Einstellung `chrome://settings/downloads` bzw.
`edge://settings/downloads`). Diese Einstellung kann eine Website nicht
per JavaScript übersteuern. Ziel dieses APs: die tatsächliche Ursache
verifizieren und, soweit im Code möglich, absichern – **nicht** einen
Fix erzwingen, der technisch nicht wirken kann.

**Betroffene Dateien:**
- `wp-content/uploads/cbd-temp-pdfs/.htaccess` (neu, nur falls Schritt 4
  im Vorgehen zutrifft)
- `includes/class-cbd-pdf-generator.php` (ändern, nur falls die
  `.htaccess`-Datei stattdessen durch den PHP-Code selbst angelegt werden
  soll – siehe Vorgehen Schritt 5)

**Vorgehen:**
0. Sicherstellen, dass der Branch `phase-1-pdf-export-fixes` ausgecheckt
   ist (`git checkout phase-1-pdf-export-fixes`).
1. Auf dem Testsystem: `chrome://settings/downloads` (oder
   `edge://settings/downloads`) öffnen und den Schalter „Vor dem Download
   fragen, wo jede Datei gespeichert werden soll" **deaktivieren**.
2. Einen PDF-Export auslösen. Beobachten: Speichert der Browser die Datei
   jetzt direkt in den Downloads-Ordner, ohne Nachfrage?
   - **Wenn ja:** Die Ursache ist die Browser-Einstellung des Nutzers, kein
     Code-Fehler. In der Übergabenotiz exakt so festhalten, inkl. der
     genauen Einstellungsbezeichnung, damit der Nutzer sie selbst umstellen
     kann. Weiter mit Schritt 4 (defensive Absicherung), dann AP als
     erledigt markieren.
   - **Wenn nein** (Nachfrage erscheint weiterhin trotz deaktivierter
     Einstellung): echter Bug – weiter mit Schritt 3.
3. Nur falls Schritt 2 „nein" ergab: Browser-DevTools → Netzwerktab → den
   Request auf die zurückgegebene `response.url` (aus `sendPDFRequest()`)
   inspizieren. Prüfen: `Content-Type`, evtl. `Content-Disposition`,
   Statuscode, ob die URL same-origin zur aktuellen Seite ist (gleiches
   Protokoll+Host+Port wie `get_site_url()`). Ursache dokumentieren und
   gezielt beheben (z. B. falsches `Content-Type`, das der Browser als
   „inline anzeigen" statt „herunterladen" interpretiert).
4. **Defensive Absicherung (unabhängig vom Ergebnis aus Schritt 2/3):**
   Im Verzeichnis, in dem `class-cbd-pdf-generator.php::generate_with_mpdf()`
   die PDF-Datei ablegt (`wp_upload_dir()['basedir'] . '/cbd-temp-pdfs/'`),
   eine `.htaccess`-Datei mit folgendem Inhalt anlegen (nur falls sie dort
   noch nicht existiert):
   ```apache
   <IfModule mod_headers.c>
   <FilesMatch "\.pdf$">
   Header set Content-Disposition "attachment"
   </FilesMatch>
   </IfModule>
   ```
   Diese Datei manuell im Verzeichnis `wp-content/uploads/cbd-temp-pdfs/`
   des Testsystems ablegen und die Existenz nach einem erneuten Export
   erneut prüfen (die Datei wird von der PHP-Aufräumroutine
   `cleanup_temp_files()` nicht gelöscht, da diese nur `.pdf`-Dateien
   älter als eine Stunde entfernt – prüfen und in der Übergabenotiz
   bestätigen, dass `.htaccess` davon nicht betroffen ist).
5. Prüfen, ob `mod_headers` auf dem Zielhosting (All-Inkl Shared Hosting)
   verfügbar ist – falls dazu keine Aussage möglich ist (kein Zugriff auf
   die Produktivumgebung), das als offene Prüfung für den Nutzer in der
   Übergabenotiz vermerken statt es als erledigt zu markieren.
6. Erneuten Export mit weiterhin **aktivierter** Browser-Nachfrage-
   Einstellung (Schritt 1 rückgängig machen) durchführen, um zu bestätigen,
   dass sich am grundsätzlichen Browserverhalten nichts Unerwartetes
   geändert hat.
7. Änderungen committen und pushen (auch wenn nur die `.htaccess` neu ist
   oder ausschließlich die Übergabenotiz den Befund „Browser-Einstellung,
   kein Code-Fehler" festhält):
   `git add -A && git commit -m "AP-1.3: PDF-Direktdownload geprüft und
   abgesichert" && git push -u origin phase-1-pdf-export-fixes`.

**Akzeptanzkriterien:**
- [ ] Commit mit AP-ID `AP-1.3` im Commit-Text erstellt und zum Remote
      gepusht.
- [ ] Getestet mit deaktivierter Browser-Nachfrage-Einstellung: Ergebnis
      (direkter Download ja/nein) dokumentiert.
- [ ] Ist die Ursache eine Browser-Einstellung: das explizit als solche in
      der Übergabenotiz festgehalten, inkl. Anleitung für den Nutzer, wie
      er sie selbst ändert.
- [ ] Ist die Ursache ein Code-Fehler: behoben, mit Vorher/Nachher-Beleg
      (Netzwerktab-Beobachtung).
- [ ] `.htaccess`-Datei mit `Content-Disposition: attachment` liegt im
      Zielverzeichnis (sofern `mod_headers` auf dem Testsystem verfügbar
      war) und übersteht einen erneuten PDF-Export.
- [ ] Kein bestehender PDF-Export-Weg (visual/print/text, mit/ohne
      Notizen) wurde durch diese Änderung beeinträchtigt.

**Tests:**
- Smoke-Test: PDF-Export mit `.htaccess` im Zielverzeichnis läuft weiterhin
  fehlerfrei durch (Datei wird weiterhin erzeugt und heruntergeladen).
- Prüfschritt: Export bei deaktivierter und bei aktivierter
  Browser-Nachfrage-Einstellung, jeweils Ergebnis notiert.

**Übergabenotiz:**
(leer – vom ausführenden Agenten auszufüllen)

---

### AP-1.4: Tafelmodus-Oberfläche an Darkmode anpassen

**Status:** ☐ offen
**Umfang:** M
**Modell:** sonnet
**Abhängigkeiten:** keine

**Ziel & Kontext:**
`assets/css/board-mode.css` wurde bei der ursprünglichen
Darkmode-Umschaltung (`docs/archiv/PLAN-Darkmode-Umschaltung.md`, Phase 2)
bewusst **nicht** auf `[data-theme="dark"]`-Regeln umgestellt (dokumentierter
offener Nebenbefund). Ein Teil der Farben in dieser Datei nutzt bereits
`var(--x, #fallback)` (aus einem früheren, separaten Vorhaben,
`docs/archiv/PLAN-CSS-Variablen-Darkmode.md`) – diese Farben **könnten**
sich im Darkmode bereits automatisch anpassen, da die zugrunde liegenden
CSS-Variablen im Theme unter `:root[data-theme="dark"]` andere Werte
bekommen. Was tatsächlich noch weiß/hell bleibt, ist unbekannt und muss
zuerst live geprüft werden – nach demselben Diagnose-vor-Fix-Prinzip wie
AP-1.1. Zu beachten: In anderen CDB-Designer-Dateien traten beim
gleichartigen Umbau drei wiederkehrende Kaskade-Fallen auf (siehe
`Plugins/CDB-Designer/CLAUDE.md`, Abschnitt „Darkmode", Tabellenzeile zu
`cbd-frontend-clean.css`): (1) ein gerendertes Inline-`style`-Attribut
schlägt eine Nicht-`!important`-Regel immer, (2) eine aktive `transition`
hat Vorrang selbst vor Author-`!important`, (3) eine Variable wie
`--color-sidebar-border` kann im Darkmode einen anderen, dafür ungeeigneten
Wert annehmen (Variablen-Kollision) – bei Verwendung dieser Variable in
`board-mode.css` gezielt prüfen.

**Betroffene Dateien:**
- `assets/css/board-mode.css` (ändern)

**Vorgehen:**
0. Im Repository `Plugins/CDB-Designer` den Branch
   `phase-1-tafelmodus-darkmode` von `main` abzweigen: `git checkout main
   && git pull && git checkout -b phase-1-tafelmodus-darkmode`. Beide APs
   dieses Strangs (AP-1.4–AP-1.5) arbeiten auf diesem Branch.
1. Auf dem Testsystem (`fos.localhost:8080` laut Projektwissen, sonst als
   manuelle Prüfliste für den Nutzer dokumentieren): eine Seite mit
   Container-Block öffnen, Tafelmodus aktivieren, **zuerst im Hellmodus**
   ansehen (Werkzeugleiste, Zeichenflächen-Rahmen, Farbauswahl-Dialog,
   Bestätigungsdialoge wie `.cbd-board-confirm-cancel`).
2. Website in den Darkmode umschalten (Toggle-Button im Theme-Header),
   Tafelmodus erneut öffnen. Jede noch helle/weiße Fläche notieren (Element,
   Selektor über Browser-DevTools ermitteln).
3. Für jede notierte Fläche die Ursache klären:
   - Nutzt die Regel bereits `var(--color-x, #fallback)`, wirkt aber
     trotzdem nicht? → Prüfen, ob eine der drei bekannten Kaskade-Fallen
     zutrifft (Inline-Style, `transition`, Variablen-Kollision) und nach
     demselben Muster wie in `cbd-frontend-clean.css` beheben (siehe
     `Plugins/CDB-Designer/reference_file_map.md`, Zeile zu
     `cbd-frontend-clean.css`, für das genaue Vorbild: `!important` auf
     betroffene Eigenschaften, `transition-property` einschränken, bzw.
     semantisch passendere Variable wählen).
   - Nutzt die Regel noch einen literalen Hex-Wert? → Auf
     `var(--color-x, #bisheriger-wert)` umstellen (`--color-background`
     für helle Flächen, `--color-text-primary` für Text, `--color-border`/
     `--color-sidebar-border` für Rahmen – siehe Root-`CLAUDE.md`,
     Abschnitt „Color Scheme", für die vollständige Variablenliste), dann
     zusätzlich `[data-theme="dark"] .selektor { ... }` **nur** ergänzen,
     wenn der reine `var()`-Fallback-Wechsel den gewünschten Dark-Wert
     nicht automatisch trifft.
4. Der bereits im Code vermerkte Nebenbefund
   `.cbd-board-confirm-cancel:hover` (ca. Zeile 952–958 in
   `board-mode.css`, nutzt `background: var(--color-sidebar-border, #e0e0e0)`
   gegen literales `color: #555`) im selben Zuge beheben: `color` ebenfalls
   auf eine passende Variable umstellen (z. B.
   `var(--color-text-primary, #555)`), damit der Kontrast im Darkmode nicht
   auf ≈1,2:1 fällt.
5. Zeichenflächen-Inhaltsfarben (die vom Nutzer wählbaren Stiftfarben
   selbst, nicht die UI-Chrome) **nicht** anfassen – die sind laut
   Projektkonvention von der Darkmode-Anpassung ausgenommen (diese werden
   in AP-1.5 über den Invertierungs-Filter behandelt, nicht hier über
   Variablen).
6. Nach jeder Änderung erneut im Browser gegenprüfen (Schritt 1–2
   wiederholen), bis keine unerwünscht helle Fläche mehr auffällt.
7. Änderungen committen und pushen:
   `git add assets/css/board-mode.css
   Plugins/CDB-Designer/reference_file_map.md && git commit -m "AP-1.4:
   Tafelmodus-Oberfläche an Darkmode angepasst" && git push -u origin
   phase-1-tafelmodus-darkmode`.

**Akzeptanzkriterien:**
- [ ] Branch `phase-1-tafelmodus-darkmode` existiert (von `main`
      abgezweigt), Commit mit AP-ID `AP-1.4` erstellt und zum Remote
      gepusht.
- [ ] Werkzeugleiste, Zeichenflächen-Rahmen, Farbauswahl-Dialog und
      Bestätigungsdialoge des Tafelmodus zeigen im Darkmode keine
      unangepasste weiße/helle Fläche mehr (Sichtprüfung, mit
      Vorher-/Nachher-Beschreibung in der Übergabenotiz).
- [ ] `.cbd-board-confirm-cancel:hover` hat im Darkmode einen Kontrast von
      mindestens 4,5:1 (WCAG AA) zwischen Text und Hintergrund.
- [ ] Alle neuen/geänderten Regeln nutzen `[data-theme="dark"] .selektor`,
      keine `@media (prefers-color-scheme: dark)`-Blöcke wurden ergänzt.
- [ ] Alle neuen Farbwerte nutzen `var(--x, #fallback)`, außer den
      bewusst ausgenommenen Inhaltsfarben der Zeichenfläche.
- [ ] Der Tafelmodus im **Hellmodus** sieht optisch unverändert aus wie
      vor dieser Änderung (Regressionscheck).
- [ ] `Plugins/CDB-Designer/reference_file_map.md`, Zeile zu
      `board-mode.css`, ist aktualisiert.

**Tests:**
- Smoke-Test: Tafelmodus lässt sich im Hell- und im Dunkelmodus öffnen und
  schließen, keine JavaScript-Konsolenfehler.
- Prüfschritt 1: Hellmodus vor und nach der Änderung optisch identisch
  (Screenshot-Vergleich oder Beschreibung in der Übergabenotiz).
- Prüfschritt 2: Darkmode – jede in Schritt 2 des Vorgehens notierte
  Fläche erneut prüfen, als behoben markieren.
- Prüfschritt 3: `.cbd-board-confirm-cancel`-Dialog im Darkmode öffnen
  (z. B. über „Tafel löschen"-Bestätigung), Kontrast der Hover-Farbe
  visuell/mit Browser-DevTools-Kontrastprüfer bestätigen.

**Übergabenotiz:**
(leer – vom ausführenden Agenten auszufüllen)

---

### AP-1.5: Farbinvertierung der Zeichenfläche im Darkmode

**Status:** ☐ offen
**Umfang:** M
**Modell:** sonnet
**Abhängigkeiten:** AP-1.4 (dieselbe Datei `board-mode.css`, sequenziell
danach)

**Ziel & Kontext:**
Eine „Eigene Notiz" ist eine im Browser gezeichnete Rastergrafik
(`<canvas>`-Ebenen, als PNG in `localStorage` gespeichert – siehe
`assets/js/board-mode.js`, `this.backgroundCtx`/`this.gridCtx`/
`this.drawingCtx`). Nutzerentscheidung: Im Darkmode soll die Zeichenfläche
automatisch invertieren (z. B. Schwarz auf Weiß → Weiß auf Schwarz), aber
**nur**, wenn `this.boardColor === '#ffffff'` (Standard-Tafelfarbe) UND
die Website im Darkmode ist (`document.documentElement.getAttribute(
'data-theme') === 'dark'`). Hat der Nutzer bewusst eine andere Tafelfarbe
gewählt (Grün `#1a472a` oder Schwarz, siehe Presets ca. Zeile 94–105 in
`board-mode.js`), bleibt die Zeichenfläche unverändert. Umgesetzt wird das
über einen CSS-Filter (`filter: invert(1)`), der auf die Rasterdarstellung
wirkt, ohne die gespeicherten Pixel zu verändern – funktioniert damit auch
für bereits vorher gespeicherte Notizen.

**Betroffene Dateien:**
- `assets/js/board-mode.js` (ändern)
- `assets/css/board-mode.css` (ändern)

**Vorgehen:**
0. Sicherstellen, dass der Branch `phase-1-tafelmodus-darkmode`
   ausgecheckt ist (`git checkout phase-1-tafelmodus-darkmode`) – angelegt
   in AP-1.4.
1. In `assets/js/board-mode.js`: Per Grep nach `this.backgroundCanvas =`
   und `this.drawingCanvas =` den gemeinsamen Eltern-Container ermitteln,
   der alle Zeichenflächen-Canvas-Ebenen (Hintergrund, Gitter, Zeichnung)
   umschließt (z. B. ein `<div>` mit einer Klasse wie
   `cbd-board-canvas-stack` oder ähnlich – den tatsächlichen Klassennamen
   im Code nachschlagen, nicht raten). Dieser Container ist der
   Filter-Ziel-Selektor, **nicht** die gesamte Werkzeugleiste/Overlay
   (die soll ihre normale, von AP-1.4 bereits dunkle Darstellung behalten,
   nicht zusätzlich invertiert werden).
2. Eine Methode `applyDarkModeInversion()` (oder passend zum bestehenden
   Namensschema der Klasse) ergänzen, die:
   ```js
   var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
   var shouldInvert = isDark && this.boardColor === '#ffffff';
   // $canvasStack = der in Schritt 1 ermittelte gemeinsame Container
   $canvasStack.toggleClass('cbd-board-inverted', shouldInvert);
   ```
3. Diese Methode aufrufen:
   - beim Öffnen/Initialisieren des Tafelmodus (dort, wo `this.boardColor`
     erstmals gesetzt bzw. aus `localStorage` gelesen wird, siehe
     `loadFromServer()`/Konstruktor-Bereich ca. Zeile 119),
   - bei jedem Tafelfarbwechsel (Handler des `cbd-board-bg-cycle`-Buttons,
     ca. Zeile 760, und der Preset-Buttons `.cbd-board-bg-preset-btn`, ca.
     Zeile 321–323) – jeweils **nachdem** `this.boardColor` neu gesetzt
     wurde.
4. In `assets/css/board-mode.css`: Regel für den in Schritt 1 ermittelten
   Selektor ergänzen:
   ```css
   .cbd-board-inverted {
       filter: invert(1);
   }
   ```
5. Edge Case prüfen: Wird der Darkmode-Toggle des Themes bei bereits
   geöffnetem Tafelmodus geklickt (ohne Seiten-Reload) – reagiert die
   Zeichenfläche live darauf? Im Theme nachsehen
   (`Theme/header.php`, Bereich um `#fos-theme-toggle`, laut
   `Theme/CLAUDE.md` Abschnitt „Darkmode"), ob der Klick-Handler die Seite
   neu lädt oder nur das Attribut setzt. Lädt er neu: kein weiterer
   Handler nötig (die Methode aus Schritt 2 läuft beim nächsten
   Board-Öffnen ohnehin erneut). Setzt er das Attribut **ohne** Reload:
   in der Übergabenotiz als bekannte, nicht behobene Einschränkung
   vermerken (Live-Umschalten bei bereits offenem Board erfordert einen
   zusätzlichen Event-Listener, der über den Scope dieses APs
   hinausgeht) – **nicht** in diesem AP zusätzlich implementieren, um den
   Scope nicht zu sprengen.
6. Änderungen committen und pushen:
   `git add assets/js/board-mode.js assets/css/board-mode.css
   Plugins/CDB-Designer/reference_file_map.md && git commit -m "AP-1.5:
   Notiz-Farbinvertierung im Darkmode" && git push -u origin
   phase-1-tafelmodus-darkmode`.

**Akzeptanzkriterien:**
- [ ] Commit mit AP-ID `AP-1.5` im Commit-Text erstellt und zum Remote
      gepusht.
- [ ] Eine im Hellmodus mit Standard-Weiß-Tafel gezeichnete Notiz erscheint
      nach dem Umschalten auf Darkmode (Seite neu laden, Board erneut
      öffnen) invertiert (z. B. schwarzer Strich auf weißem Grund wird zu
      weißem Strich auf schwarzem Grund).
- [ ] Dieselbe Notiz auf einer Grün- oder Schwarz-Tafel bleibt im Darkmode
      unverändert (kein Invertierungs-Filter angewendet).
- [ ] Im Hellmodus ist die Zeichenfläche in jedem Fall unverändert
      (kein Filter aktiv).
- [ ] Eine mehrfarbige Beispielnotiz (mindestens zwei verschiedene
      Stiftfarben) wurde im Darkmode auf der Standard-Weiß-Tafel
      sichtgeprüft, das Ergebnis (inkl. der erwarteten Farbverschiebung,
      siehe Nicht-Ziele) in der Übergabenotiz beschrieben.
- [ ] Der Edge Case „Darkmode-Umschaltung bei bereits offenem Board" wurde
      geprüft und das Ergebnis (funktioniert live / bekannte Einschränkung)
      dokumentiert.
- [ ] `Plugins/CDB-Designer/reference_file_map.md`, Zeilen zu
      `board-mode.js` und `board-mode.css`, sind aktualisiert.

**Tests:**
- Smoke-Test: Tafelmodus lässt sich weiterhin öffnen, zeichnen, speichern
  und schließen, keine JavaScript-Konsolenfehler.
- Prüfschritt 1: Notiz auf Standard-Weiß-Tafel, Hell- vs. Darkmode
  vergleichen → Invertierung sichtbar nur im Darkmode.
- Prüfschritt 2: Notiz auf Grün-/Schwarz-Tafel, Hell- vs. Darkmode
  vergleichen → keine Invertierung in beiden Fällen.
- Prüfschritt 3: Gespeicherte, bereits vor dieser Änderung erzeugte Notiz
  im Darkmode erneut öffnen → invertiert sich ebenfalls korrekt (belegt,
  dass der Filteransatz nicht von neu gezeichneten Pixeln abhängt).

**Übergabenotiz:**
(leer – vom ausführenden Agenten auszufüllen)

---

### AP-1.rev: Unabhängiges Review Phase 1

**Status:** ☐ offen
**Umfang:** M
**Modell:** opus
**Abhängigkeiten:** AP-1.1, AP-1.2, AP-1.3, AP-1.4, AP-1.5

**Ziel & Kontext:**
Unabhängige Qualitätsprüfung der Phase 1 durch einen Agenten, der an
keiner Implementierung beteiligt war. Nur lesend arbeiten (Read/Grep/Glob
bzw. Dateien ansehen) – KEINE Datei verändern.

**Vorgehen:**
0. Beide Strang-Branches sind zu diesem Zeitpunkt **noch nicht** in `main`
   gemergt (das übernimmt erst AP-1.doc). Prüfe den Code direkt auf den
   Branches, z. B. `git diff main...phase-1-pdf-export-fixes` und
   `git diff main...phase-1-tafelmodus-darkmode`, oder checke jeden Branch
   einzeln aus, um die geänderten Dateien anzusehen. Keine Datei ändern,
   keinen Merge durchführen.
1. Für jedes Implementierungs-AP (AP-1.1 bis AP-1.5): Code gegen dessen
   Akzeptanzkriterien prüfen (Stichproben im Quelltext, nicht nur die
   Übergabenotizen glauben).
2. Phasen-Endzustand aus Abschnitt 6 prüfen: PDF-Export zeigt Notizen/
   Tafelbilder als Bild in allen drei Modi; Direktdownload-Befund ist
   entweder behoben oder klar als Browser-Einstellung dokumentiert;
   Tafelmodus passt sich im Darkmode an und invertiert die
   Standard-Weiß-Zeichenfläche.
3. Scope-Check: Wurden die Nicht-Ziele aus Abschnitt 2 verletzt (z. B.
   Änderungen am Theme oder an „Eigene WP Blocks", neue Fremd-Libraries,
   Farbwahl-UI statt automatischer Invertierung)?
4. Regressionscheck insbesondere:
   - Normaler PDF-Export ohne Notizen (Text/Screenshot/Formel) weiterhin
     unverändert.
   - Print- und Text-Modus des PDF-Exports weiterhin funktionsfähig.
   - Bereits abgeschlossene Darkmode-Kontrastkorrekturen an
     `cbd-frontend-clean.css`/`latex-formulas.css`/`floating-pdf-button.js`
     sind unangetastet.
   - Bestehende Board-Farbwahl Grün/Schwarz ist von der neuen
     Invertierungslogik unberührt.
5. Qualitäts-Check: offensichtliche Fehler, PHP-7.4-Inkompatibilitäten
   (`php tools/check-php74.php` erneut laufen lassen), tote
   Selektoren/Verweise, Konventionsverstöße (`@media
   (prefers-color-scheme: dark)` statt `[data-theme="dark"]`, hartcodierte
   Hex-Werte ohne `var()`-Einbindung außerhalb der bewusst ausgenommenen
   Inhaltsfarben).
6. Befunde als Review-Bericht in die Übergabenotiz: je Befund
   Schweregrad (kritisch / mittel / gering), betroffenes AP, Datei und
   Fundstelle.

**Akzeptanzkriterien:**
- [ ] Jedes Implementierungs-AP der Phase wurde gegen seine
      Akzeptanzkriterien geprüft.
- [ ] Alle Befunde mit Schweregrad, Datei und Fundstelle dokumentiert.
- [ ] Keine Datei wurde verändert.

**Tests:**
- entfällt (Review-AP; das Ergebnis ist der Bericht).

**Übergabenotiz:**
(leer – vom ausführenden Agenten auszufüllen)

**Umgang mit Befunden:** Kritische Befunde → Korrektur-APs (`AP-1.fix1`,
…) anlegen, in Statustabelle und Testprotokoll aufnehmen; die Phase gilt
erst nach deren Abschluss und einem erneuten Kurz-Review als fertig.
Mittlere/geringe Befunde → in Abschnitt „Offene Punkte" der
Projektdokumentation aufnehmen (AP-1.doc).

---

### AP-1.doc: Dokumentation Phase 1 aktualisieren

**Status:** ☐ offen
**Umfang:** S
**Modell:** sonnet
**Abhängigkeiten:** AP-1.rev (inkl. eventueller Korrektur-APs)

**Ziel & Kontext:**
`Plugins/CDB-Designer/CLAUDE.md`, `Plugins/CDB-Designer/reference_file_map.md`
und die Root-`DOKUMENTATION.md` auf den Stand nach Phase 1 bringen, damit
das Projekt ohne Kenntnis dieses Plans erweiterbar bleibt.

**Betroffene Dateien:**
- `Plugins/CDB-Designer/CLAUDE.md` (ändern)
- `Plugins/CDB-Designer/reference_file_map.md` (ändern)
- `DOKUMENTATION.md` (Root, ändern)
- `Plugins/CDB-Designer/docs/PLAN-PDF-Export-und-Tafelmodus-Fixes.md`
  (dieser Plan – „Letzte Aktualisierung" und Statustabelle final auf den
  Abschlussstand bringen)

**Vorgehen:**
0. Beide Strang-Branches nach `main` mergen (Voraussetzung: `AP-1.rev` ist
   ☑, inkl. eventueller Korrektur-APs):
   ```
   git checkout main
   git pull
   git merge --no-ff phase-1-pdf-export-fixes -m "Merge Phase 1: PDF-Export-Fixes"
   git merge --no-ff phase-1-tafelmodus-darkmode -m "Merge Phase 1: Tafelmodus-Darkmode"
   git push origin main
   ```
   Tritt entgegen Abschnitt 4 doch ein Konflikt auf: manuell auflösen,
   dabei keine Akzeptanzkriterien der betroffenen Implementierungs-APs
   verletzen. Alle folgenden Schritte dieses APs finden auf `main` statt.
1. Alle Übergabenotizen von AP-1.1 bis AP-1.5 sowie AP-1.rev durchgehen.
2. `Plugins/CDB-Designer/CLAUDE.md`, Abschnitt „PDF-Export: Tafelbilder und
   eigene Notizen": Die dort unter „Bekannte, bewusst akzeptierte
   Einschränkungen" stehende Einschränkung 3 („Fehlender visueller
   Ende-zu-Ende-Test") als **behoben** markieren (mit Verweis auf
   AP-1.1/AP-1.2 dieses Plans) und ggf. tatsächlich behobene
   Ursachen aus AP-1.2 als neuen Unterabschnitt ergänzen. Einschränkungen
   1 und 2 (Klassenzuordnung, `:pN`-Zusatzseiten) bleiben unverändert
   bestehen, da dieser Plan sie nicht behebt.
3. `Plugins/CDB-Designer/CLAUDE.md`, Abschnitt „Darkmode": Einen neuen
   Unterabschnitt ergänzen, der `board-mode.css`/`board-mode.js` als
   inzwischen darkmode-fähig beschreibt (Verweis auf diesen Plan), inkl.
   der Invertierungsregel (nur bei Standard-Weiß-Tafel) und der in AP-1.5
   dokumentierten bekannten Einschränkung (Live-Umschaltung bei bereits
   offenem Board), falls diese laut AP-1.5-Übergabenotiz tatsächlich
   offen blieb.
4. `Plugins/CDB-Designer/reference_file_map.md`: Zeilen zu
   `pdf-server-side.js`, `class-cbd-pdf-generator.php`, `board-mode.js`
   und `board-mode.css` gegen die tatsächlichen Änderungen aus AP-1.2,
   AP-1.3, AP-1.4 und AP-1.5 abgleichen und vervollständigen (falls dort
   noch nicht durch die Implementierungs-APs selbst geschehen).
5. `DOKUMENTATION.md` (Root): Im bestehenden Abschnitt zum Vorhaben „PDF-
   Notizen und Listenformeln" (bzw. an geeigneter Stelle) einen neuen
   Absatz zum Vorhaben „PDF-Export- und Tafelmodus-Fixes" ergänzen, analog
   zu den bestehenden Einträgen: Kurzbeschreibung, Verweis auf
   `Plugins/CDB-Designer/docs/PLAN-PDF-Export-und-Tafelmodus-Fixes.md`,
   Abschlussdatum, kurze Zusammenfassung der drei behobenen Punkte inkl.
   des Befunds zu Bug B (behoben oder als Browser-Einstellung
   dokumentiert).
6. In diesem Plan (Abschnitt 8) alle Status auf ☑ setzen, „Letzte
   Aktualisierung" im Dateikopf aktualisieren.
7. Änderungen auf `main` committen und pushen:
   `git add Plugins/CDB-Designer/CLAUDE.md
   Plugins/CDB-Designer/reference_file_map.md DOKUMENTATION.md
   docs/PLAN-PDF-Export-und-Tafelmodus-Fixes.md && git commit -m "AP-1.doc:
   Dokumentation Phase 1 aktualisiert" && git push origin main`. Danach
   optional die beiden Strang-Branches lokal und remote löschen (`git
   branch -d phase-1-pdf-export-fixes phase-1-tafelmodus-darkmode`,
   `git push origin --delete phase-1-pdf-export-fixes
   phase-1-tafelmodus-darkmode`) – nicht zwingend, aber hält die
   Branch-Liste aufgeräumt.

**Akzeptanzkriterien:**
- [ ] Beide Strang-Branches sind konfliktfrei in `main` gemergt und `main`
      wurde gepusht.
- [ ] Commit mit AP-ID `AP-1.doc` im Commit-Text erstellt und zum Remote
      gepusht.
- [ ] Jede in Phase 1 geänderte Datei hat eine aktuelle Zeile in
      `Plugins/CDB-Designer/reference_file_map.md`.
- [ ] `Plugins/CDB-Designer/CLAUDE.md` beschreibt den behobenen Zustand
      beider betroffener Abschnitte („Darkmode" und „PDF-Export:
      Tafelbilder und eigene Notizen") korrekt, inkl. verbleibender
      bekannter Einschränkungen.
- [ ] `DOKUMENTATION.md` (Root) verweist auf diesen Plan mit
      Abschlussdatum.
- [ ] Kein Verweis in der Dokumentation zeigt auf nicht mehr existierende
      Dateien/Funktionen (Stichprobe: die vier in Schritt 4 genannten
      Dateien tatsächlich öffnen und mit der Doku-Zeile abgleichen).

**Tests:**
- Stichprobe: Zwei zufällige Zeilen der Datei-Map (davon mindestens eine
  aus den vier in Phase 1 geänderten Dateien) gegen den echten
  Dateiinhalt prüfen (Zweck und Funktionen stimmen).

**Übergabenotiz:**
(leer – vom ausführenden Agenten auszufüllen)

---

## 8. Status

Wird während der Ausführung gepflegt. Legende: ☐ offen · ◐ in Arbeit · ☑ erledigt · ✗ blockiert

| AP | Titel | Modell | Status | Abhängig von | Notiz |
|---|---|---|---|---|---|
| AP-1.1 | Live-Diagnose PDF-Bilder | opus | ☑ | – | Verdachtsstelle 1 widerlegt, Verdachtsstelle 2 bestätigt, echte Ursache noch offen (mPDF-Bilddecode) |
| AP-1.2 | PDF-Bilder-Fehler beheben | opus | ☑ | AP-1.1 | Ursache war weder Verdachtsstelle 1 noch 2 allein, sondern kses+Transparenz+Variablennamen (siehe Übergabenotiz) |
| AP-1.fix1 | PDF soll Darkmode nicht abbilden | sonnet | ☑ | AP-1.2 | Korrektur nach Nutzer-Live-Test: PDF immer im Hellmodus-Farbschema, unabhängig vom Website-Zustand |
| AP-1.3 | PDF-Direktdownload prüfen/absichern | opus | ☐ | AP-1.2 | |
| AP-1.4 | Tafelmodus-Oberfläche Darkmode | sonnet | ☐ | – | |
| AP-1.5 | Notiz-Farbinvertierung Darkmode | sonnet | ☐ | AP-1.4 | |
| AP-1.rev | Review Phase 1 | opus | ☐ | AP-1.1…AP-1.5 | |
| AP-1.doc | Doku Phase 1 | sonnet | ☐ | AP-1.rev | |

## 9. Testprotokoll

Wird während der Ausführung gepflegt. Ein Eintrag pro abgeschlossenem AP und pro Phasenabschluss.

| Datum | AP / Phase | Getestet | Ergebnis | Getestet von |
|---|---|---|---|---|
| 2026-08-25 | AP-1.1 | Reale PDF-Datei `cbd-pdf-6a8caee833660.pdf` geöffnet und byteweise analysiert; drei isolierte `clean_block_html()`-Tests; vier isolierte mPDF-Bildeinbettungstests (PNG, JPEG, groß, transparent) | Verdachtsstelle 1 widerlegt, Verdachtsstelle 2 bestätigt, mPDF setzt nachweislich sein internes 14×16-Fehlerbild ein (Bilddaten nicht dekodierbar), Ursache dafür noch offen; zusätzlich Darkmode-Textkontrast-Bug im PDF-Export gefunden | Agent (Live-System-Diagnose ohne Login, per Dateisystemzugriff + isolierten PHP-Tests) |
| 2026-08-25 | AP-1.2 | Live-Export über echten Browser (Claude in Chrome, Login durch Nutzer) auf Testseite „Reinstoffe und Gemische", Modus visual, mit „Eigener Notiz"; wiederholt nach jedem Teilfix; zusätzlich Export im Darkmode; mehrere isolierte PHP-Reflection-Tests mit echten `localStorage`-Rohdaten gegen `sanitize_pdf_block_html()`/`prepare_structured_block()`/mPDF | Alle drei Teilursachen (kses-data:-Stripping, JPEG-Transparenzverlust, falsch benannte CSS-Variablen) bestätigt behoben: Notiz erscheint korrekt im PDF, keine Duplikate bei Wiederholung, Darkmode-Text hell auf dunkel lesbar | Agent (Live-Browser-Export, echte PDF-Dateien geöffnet, kein Mock) |
| 2026-08-25 | AP-1.fix1 | Nutzer testete unabhängig ein serverseitiges „Tafelbild" (funktioniert), meldete aber weiterhin sichtbaren Darkmode im PDF und stellte klar: PDFs sollen den Darkmode nie abbilden. Live-Export im Darkmode nach dem Fix wiederholt | PDF zeigt jetzt Hellmodus-Farbschema unabhängig vom Website-Zustand (`cbd-pdf-6a8d3c5871730.pdf` geprüft), „Eigene Notiz" weiterhin korrekt sichtbar | Agent (Live-Browser-Export im Darkmode, echte PDF geöffnet) |

## 10. Dokumentation

- **Projektdokumentation:** `DOKUMENTATION.md` (Root) – wird in AP-1.doc
  um einen Absatz zu diesem Vorhaben ergänzt.
- **Plugin-Dokumentation:** `Plugins/CDB-Designer/CLAUDE.md`, Abschnitte
  „Darkmode" und „PDF-Export: Tafelbilder und eigene Notizen" – werden in
  AP-1.doc aktualisiert.
- **Datei-Map:** `Plugins/CDB-Designer/reference_file_map.md` – wird von
  jedem AP gepflegt, das Dateien anlegt oder wesentlich ändert, final in
  AP-1.doc abgeglichen.
- **Erweiterungsanalyse dieses Vorhabens:**
  `Plugins/CDB-Designer/docs/ERWEITERUNGSANALYSE-PDF-Export-und-Tafelmodus-Fixes.md`
