# Erweiterungs-Analyse: Text-Werkzeug im Tafelmodus + Wiederherstellung Notizen-Download/-Upload

Datum: 2026-09-10. Vorlauf für den anschließenden Projektplan (projektplan-skill).
Repo: `Plugins/CDB-Designer` (eigenes Git-Repository innerhalb des Website-Projekts).

## 1. Kurzbeschreibung der Erweiterung

Zwei unabhängige, aber beide im Tafelmodus (`assets/js/board-mode.js`) verankerte
Erweiterungen, in einem Gespräch beauftragt:

1. **Text-Werkzeug im Tafelmodus.** Ein neues Zeichenwerkzeug neben Stift/
   Textmarker/Radierer: Klick auf die Tafel öffnet ein kleines Texteingabefeld,
   dessen Inhalt beim Bestätigen an dieser Stelle auf die Zeichenfläche
   "gestempelt" wird. Zielgruppe: Schüler ohne Stift-/Tableteingabe, die sonst
   nur mit der Maus unpräzise freihändig schreiben könnten.
2. **Wiederherstellung Notizen-Download/-Upload.** Eine früher vorhandene,
   heute nur noch als toter Code existierende Funktion (Export/Import/Löschen
   aller lokal im Browser gespeicherten Tafel-Notizen, für einen Gerätewechsel)
   wird über einen neuen, eigenständigen Einstiegspunkt wieder zugänglich
   gemacht: einen Button, der ausschließlich auf Seiten mit einem
   Inhaltsverzeichnis-Block erscheint — nicht auf jeder Seite mit
   Tafelmodus-Block, um die Oberfläche nicht mit Buttons zu überladen.

## 2. Verständnis des Ist-Projekts

- **Projektzweck:** WordPress-Website für Chemie-/Biochemie-Unterricht (FOS
  Online Schulbuch), Theme + zwei Plugins (CDB-Designer, Eigene WP Blocks).
- **Relevante Module:** ausschließlich CDB-Designer — der Tafelmodus
  (`assets/js/board-mode.js`, `assets/css/board-mode.css`), das Feature-Flag-
  gesteuerte Enqueue-System (`includes/class-cbd-style-loader.php`), sowie
  der bereits vorhandene, aber deaktivierte globale
  `assets/js/personal-notes-manager.js` samt `admin/settings.php`-Einstellung.
  Das Theme ist nur als **Datenquelle** beteiligt (Erkennung des Blocks
  `fos/inhaltsverzeichnis` über die WordPress-Kernfunktion `has_block()`,
  keine Theme-Funktion, keine neue Naht).
- **Geltende Konventionen** (aus `CLAUDE.md` beider Ebenen), die einzuhalten
  sind:
  - PHP 7.4-Kompatibilität, geprüft über `tools/check-php74.php` bzw.
    `node create-plugin-zip.js` vor jedem ZIP-Bau.
  - Neuer CSS-Code **ausschließlich** `var(--x, #fallback)`, nie
    hartcodierte Hex-Werte; Dunkelmodus über `[data-theme="dark"] .selektor`,
    **nie** `@media (prefers-color-scheme: dark)`.
  - Kein Build-Prozess für JavaScript in diesem Plugin — reines ES5 in
    IIFEs, `var`/`function`, kein `import`/`export` (Konvention aus
    `klassenpuls.js`, `board-mode.js` selbst usw.).
  - Debugging-Konvention: `console.log` hinter `window.cbdDebug`,
    `console.error`/`warn` immer aktiv.
  - „Buttons folgen Feature-Flags" (`docs/VERBESSERUNGSPLAN.md`, AP12) —
    ein Bedienelement darf nicht angezeigt werden, wenn das zugehörige
    Feature/die zugehörige Einstellung abgeschaltet ist.
  - Keine CDN-Einbindungen (DSGVO) — beide Erweiterungen brauchen aber
    ohnehin keine externen Ressourcen.

## 3. Einordnung in die Architektur

### 3.1 Text-Werkzeug

**Andockpunkt:** Der Tafelmodus hat bereits ein generisches, datengetriebenes
Werkzeug-System: Jeder Werkzeug-Button trägt `data-tool="<name>"`
(`assets/js/board-mode.js:356-367`), ein gemeinsamer Klick-Handler
(`board-mode.js:751-760`) ruft `self.setTool(tool)` auf und setzt die
`.active`-Klasse um. Die eigentliche Zeichenlogik verzweigt anschließend über
`this.currentTool === '<name>'` (z. B. `board-mode.js:1333`, `:1345` für die
beiden Radierer-Varianten). Ein neues Werkzeug `data-tool="text"` reiht sich
in dieses bestehende Muster ein — **kein neues System**, sondern ein weiterer
Zweig im vorhandenen.

**Warum an dieser Stelle statt eines eigenen Modus:** Die Zeichenfläche wird
beim Speichern ohnehin vollständig zu einem PNG gerastert
(`this.drawingCanvas.toDataURL('image/png')`, u. a. `board-mode.js:1685`,
`:1806`, `:1897`) — sowohl für `localStorage` als auch für den Server
(`saveToServer()`, Feld `drawing_data`) und den PDF-Export. Eingegebener Text
muss deshalb nirgends als eigener Datentyp durch die Kette getragen werden:
Er wird beim Bestätigen des Textfelds per `ctx.fillText()` auf
`this.drawingCtx` gezeichnet und ist damit für Speichern, Laden, PDF-Export
und die serverseitige Klassen-Tafelbild-Anzeige **automatisch** ein normaler
Bestandteil der Zeichnung — ohne eine einzige der bestehenden Speicher-/
Export-Stellen anzufassen.

### 3.2 Notizen-Download/-Upload

**Andockpunkt:** `includes/class-cbd-style-loader.php`,
`enqueue_feature_styles()` — dort existiert bereits ein vollständiger,
funktionsfähiger, aber inaktiver Block für genau diesen Zweck (Zeile
213–244), der `assets/js/personal-notes-manager.js` und
`assets/css/personal-notes-manager.css` einreiht, gesteuert über die
Option `cbd_personal_notes_manager` (Vorgabe `disabled`).

**Warum nicht die Tafel-Werkzeugleiste selbst:** In `board-mode.js` liegen
zusätzlich drei vollständig fertige, aber seit Commit `ae681c4` (v3.0.17,
„Fix: Alle-löschen in globalen Notizen-Button, Toolbar nur Clear") **nicht
mehr verdrahtete** Methoden — `downloadPersonalNotes()`, `uploadPersonalNotes()`,
`deleteAllPersonalNotes()` (`board-mode.js:2180-2360`). Sie wurden damals
bewusst aus der Tafel-Werkzeugleiste entfernt, weil dort — bei potenziell
vielen Tafelmodus-Containern über viele Seiten verteilt — ein Menüpunkt pro
Instanz als Bedienfehler empfunden wurde. Nutzerentscheidung für diese
Erweiterung: **kein Wiedereinbau in die Werkzeugleiste**, stattdessen der
bereits vorhandene, eigenständige globale Button (unabhängig von einer
konkreten Tafel-Instanz), aber mit einer neuen, engeren Sichtbarkeitsregel.

**Warum „Seiten mit Inhaltsverzeichnis-Block" die richtige Regel ist:**
Diese Seiten sind die natürlichen Kapitel-Übersichtsseiten eines Fachs — eine
kleine, redaktionell bewusst gesetzte Teilmenge, kein `Container-Block`-
Kriterium. `has_block('fos/inhaltsverzeichnis')` ist eine **WordPress-
Kernfunktion**, die nur den gespeicherten `post_content` nach dem
Blocknamen durchsucht — sie ruft **keine** Theme-Funktion auf und braucht
deshalb **keinen** `function_exists()`-Schutz (anders als die drei
bestehenden Theme-Funktionsaufrufe des Plugins, siehe Wurzel-`CLAUDE.md`,
Abschnitt „Direkte Theme-Funktionsaufrufe des Plugins"). Ist ein anderes
Theme aktiv oder existiert der Block nicht, liefert `has_block()` schlicht
`false` — der Button bleibt aus, kein Fehler.

## 4. Betroffene Dateien

| Datei | Rolle heute | Änderung |
|-------|-------------|----------|
| `assets/js/board-mode.js` | Tafelmodus: Zeichenwerkzeuge, Speichern/Laden, Undo | ändern — neuer Werkzeug-Button `data-tool="text"`, Text-Eingabe-Overlay, `fillText()`-Rendering, Undo-Integration |
| `assets/css/board-mode.css` | Gestaltung der Tafel-Werkzeugleiste (inkl. Darkmode-Regeln seit `PLAN-PDF-Export-und-Tafelmodus-Fixes.md`) | ändern — Stil für den neuen Werkzeug-Button und das Text-Eingabe-Overlay, `[data-theme="dark"]`-Pendant |
| `includes/class-cbd-style-loader.php` | Feature-Enqueue, u. a. der inaktive Notizen-Manager-Block (Zeile 213–244) | ändern — Sichtbarkeitslogik erweitern (`has_block('fos/inhaltsverzeichnis')` statt/zusätzlich zur bisherigen Container-Feature-Erkennung); Enqueue-Aufruf aus dem frühen `return` von `enqueue_feature_styles()` herauslösen (siehe Abschnitt 6) |
| `admin/settings.php` | Einstellungsseite, u. a. Radio-Auswahl `notes_manager_mode` (disabled/all/specific) | ändern — neue Option „Nur auf Seiten mit Inhaltsverzeichnis-Block" ergänzen; ggf. Vorgabewert der Option auf diesen neuen Modus setzen, damit die Wiederherstellung ohne manuellen Admin-Schritt wirkt |
| `assets/js/personal-notes-manager.js` | fertiger, bereits vorhandener Export/Import/Löschen-Button (Floating Button) | nur lesen — Logik wird unverändert übernommen, keine Codeänderung erwartet |
| `assets/css/personal-notes-manager.css` | Gestaltung des Floating Buttons | nur lesen, ggf. prüfen (Darkmode-Tauglichkeit gegen aktuelle Variablen, siehe Regressionsfläche) |
| `reference_file_map.md` | Datei-Map des Plugins | ändern — Zeilen zu `board-mode.js`, `class-cbd-style-loader.php`, `admin/settings.php` fortschreiben |
| `CLAUDE.md` (CDB-Designer) | Architektur-/Arbeitsdoku | ändern — neuer Abschnitt „Text-Werkzeug im Tafelmodus" und Ergänzung zum bestehenden Notizen-Manager-Abschnitt |
| `../../CLAUDE.md` (Wurzel) | Projektüberblick | ggf. ändern, falls sich an der Liste der Theme-Plugin-Nähte etwas ändert (siehe Abschnitt 6 — vermutlich **keine** neue Naht nötig, da `has_block()` Kernfunktion ist) |

## 5. Wiederverwendung statt Neubau

- **Werkzeug-Infrastruktur** (`data-tool`-Attribut, Klick-Handler-Schleife,
  `setTool()`) in `board-mode.js` — das Text-Werkzeug ist ein weiterer Eintrag,
  keine Parallelstruktur.
- **Vollständige Export/Import/Löschen-Logik** in
  `assets/js/personal-notes-manager.js` — bereits produktionsreif, inklusive
  Doppelbestätigung beim Löschen, Fehlerbehandlung, Info-Anzeige der Anzahl
  gespeicherter Notizen. Keine Neuentwicklung nötig, nur Sichtbarmachung.
- **`has_block()`** — WordPress-Kernfunktion, bereits im Theme
  (`includes/kapitellink-api.php`) nach demselben Muster für denselben Block
  genutzt.
- **Rasterungs-Pipeline der Tafel** (`toDataURL()`, `saveToServer()`,
  `loadFromServer()`, PDF-Export) — das Text-Werkzeug erzeugt keinen neuen
  Datentyp, sondern nur zusätzliche Pixel auf derselben Zeichenfläche.
- **Undo-System** (`this.strokes`-Array, Basisbild-Snapshot) — ein
  Text-Eintrag wird als weiterer Stroke-artiger Eintrag geführt (analog zum
  bestehenden `highlighter`-Sonderfall in `this.strokes`), damit „Rückgängig"
  auch eine Textstempelung zurücknehmen kann, ohne eine zweite
  Undo-Mechanik einzuführen.

## 6. Integrationspunkte & Schnittstellen

### Text-Werkzeug
- Neuer Toolbar-Button in der HTML-Vorlage `board-mode.js:355-367`
  (zwischen Radierer und Farbwahl oder als weiterer Eintrag danach —
  Feinschliff dem Plan überlassen).
- Klick-/Pointer-Handler der Zeichenfläche (Bereich um `board-mode.js:1333 ff.`,
  wo `currentTool`-Verzweigungen bereits stehen) bekommt einen neuen Zweig
  `else if (this.currentTool === 'text')`, der bei Klick auf die Zeichenfläche
  ein kleines, absolut positioniertes `<input>`/`<textarea>`-Overlay an der
  Klickposition öffnet.
- Bestätigen (Enter/Blur) rendert den Text über `ctx.fillText()` (unter
  Berücksichtigung von Stiftfarbe/-größe, ggf. eigener Schriftgrad-Regler)
  auf `this.drawingCtx`, entfernt das Overlay und markiert die Tafel als
  geändert (löst denselben Auto-Save-Pfad aus wie ein fertiger Strich).
- Kein Berührungspunkt mit Server-Endpunkten, PDF-Export oder der
  Klassenmodus-Datenschicht — alles bleibt unterhalb der bestehenden
  Rasterungsgrenze.

### Notizen-Download/-Upload
- **Sichtbarkeitsentscheidung:** `includes/class-cbd-style-loader.php`,
  `enqueue_feature_styles()`. Aktuell (Zeile 165-183) steigt die Methode
  **vorzeitig aus** (`return;`), wenn auf der Seite keine aktiven
  Container-Block-Features gefunden werden — das ist für ein
  Inhaltsverzeichnis (typischerweise ohne Container-Blocks) der Regelfall
  und würde den neuen Button **nie** erreichen. **Das ist die zentrale
  technische Stellschraube dieser Erweiterung:** Die
  Notizen-Manager-Sichtbarkeitsprüfung muss aus diesem frühen `return`
  herausgelöst werden — entweder als eigener, vom Feature-Scan unabhängiger
  Codeblock direkt in `enqueue_frontend_styles()` (vor dem Aufruf von
  `enqueue_feature_styles()`), oder durch Umbau der Reihenfolge innerhalb
  `enqueue_feature_styles()` selbst. Fachlich ist das korrekt: Der
  Notizen-Export ist eine geräteweite `localStorage`-Operation, unabhängig
  davon, ob die aktuelle Seite überhaupt einen Tafelmodus-Container trägt.
- **Neue Sichtbarkeitsregel:** `has_block('fos/inhaltsverzeichnis')` auf dem
  aktuellen `$post` (analog zum bestehenden `global $post`-Zugriff in
  derselben Methode, Zeile 169). Vorschlag: neuer Options-Wert `'toc'` für
  `cbd_personal_notes_manager` (neben den bestehenden `disabled`/`all`/
  `specific`), damit die bisherige Optionsstruktur und deren Admin-UI in
  `admin/settings.php` wiederverwendet werden kann, statt eine Parallel-
  Option einzuführen.
- **Keine neue Theme-Plugin-Naht:** `has_block()` ist WordPress-Kernfunktion,
  kein Aufruf einer Theme-Funktion — anders als die drei bestehenden,
  dokumentierten Theme-Funktionsaufrufe des Plugins. Die Liste in der
  Wurzel-`CLAUDE.md` („Direkte Theme-Funktionsaufrufe des Plugins") muss
  dadurch voraussichtlich **nicht** erweitert werden; das AP-Dokumentations-
  paket sollte das aber ausdrücklich prüfen und festhalten.

## 7. Regressionsfläche (kritisch)

- **`enqueue_feature_styles()` wird an einer strukturellen Stelle geändert**
  (früher `return`-Pfad). Jedes bestehende Feature, das über diese Methode
  geladen wird (Board Mode selbst, Icons, künftige Features), muss nach der
  Änderung nachweislich **unverändert** weiterladen — insbesondere der Fall
  „Seite ohne jedes CDB-Feature" (bisher: `return` ohne jeden Enqueue; danach:
  weiterhin kein Board-Mode-/Icon-Enqueue, aber ggf. der neue
  Notizen-Manager-Enqueue, wenn `has_block()` zutrifft). Regressionstest:
  eine gewöhnliche Content-Seite ohne Container-Block und ohne
  Inhaltsverzeichnis lädt **keines** der Feature-Assets (Stand quo).
- **Darkmode-Tauglichkeit von `personal-notes-manager.css`.** Die Datei
  stammt aus v3.0.x, vor der projektweiten Darkmode-Umstellung
  (`PLAN-Darkmode-Umschaltung.md`) und der späteren, verpflichtenden
  `[data-theme="dark"]`-Konvention. Muss auf einer Inhaltsverzeichnis-Seite
  im Dunkelmodus sichtprüft werden — bekanntes Muster aus diesem Projekt:
  fehlende `color`-Angabe an `<button>`-Elementen bleibt im Darkmode
  dunkel-auf-dunkel unsichtbar (siehe `Theme/CLAUDE.md`, „Stolperstein
  `<button>`").
- **Das neue Text-Werkzeug darf die bestehenden Werkzeuge nicht stören** —
  insbesondere Undo, Seitenwechsel (`this.pageCache`) und den
  Klassenmodus-Speicherpfad (`saveToServer()`). Da die Umsetzung
  ausschließlich zusätzliche Pixel auf derselben Canvas erzeugt, ist das
  Risiko strukturell klein, aber ein Regressionstest „Zeichnen mit Stift,
  danach Text, danach wieder Stift, danach Rückgängig mehrfach" gehört ins
  Testprotokoll.
- **Bestehende, unveränderte `cbd_personal_notes_manager`-Installationen**
  (Modus `all`/`specific`, falls irgendwo bereits manuell eingeschaltet):
  Der neue Modus `toc` darf deren Verhalten nicht verändern — reine additive
  Erweiterung der Werteliste.

## 8. Konventions-Konformität

- Neuer CSS-Code ausschließlich `var(--x, #fallback)`, Darkmode über
  `[data-theme="dark"] .selektor` (siehe Abschnitt 2).
- Kein Build-Schritt, reines ES5/IIFE-JavaScript, `window.cbdDebug`-Gate für
  Diagnose-Logs.
- Vor dem nächsten Plugin-ZIP-Bau: `php tools/check-php74.php` bzw.
  `node create-plugin-zip.js` (führt die Prüfung automatisch aus) —
  Pflichtschritt laut `CLAUDE.md`.
- `CBD_VERSION`-Bump als expliziter Auslieferungsschritt am Ende (nicht
  Kosmetik, siehe wiederholt dokumentierte HTTP-Cache-Falle bei
  `?ver=CBD_VERSION`).
- „Buttons folgen Feature-Flags": Der Notizen-Button erscheint nur, wenn die
  Einstellung nicht auf `disabled` steht — dieselbe Regel wie heute, nur mit
  einem zusätzlichen Modus.

## 9. Risiken & offene Fragen

- **Risiko:** Wird `cbd_personal_notes_manager` nicht per Standardwert auf
  den neuen Modus `toc` gesetzt, bleibt die „Wiederherstellung" ohne
  manuellen Admin-Schritt wirkungslos (Option bleibt `disabled`). →
  Gegenmaßnahme: im Plan explizit festlegen, ob der neue Modus-Wert bei
  Ersteinführung als Vorgabe gesetzt wird (Migration/Upgrade-Pfad wie bei
  anderen additiven Spalten/Optionen dieses Plugins, siehe „Regel für
  künftige Spalten" in `CLAUDE.md`) oder ob der Betreiber ihn bewusst selbst
  aktiviert.
- **Risiko:** `enqueue_feature_styles()` ist eine zentrale, oft berührte
  Methode — ihre Umstrukturierung sollte ein eigenes, kleines Arbeitspaket
  mit dediziertem Regressionstest sein, nicht nebenbei im selben AP wie das
  Text-Werkzeug.
- **Entschieden (Nutzer, 2026-09-10):** Der neue Options-Wert `toc` steht als
  **zusätzliche**, dritte Wahl neben `disabled`/`all`/`specific` (kein Ersatz
  — Vorschlag dieser Analyse übernommen, risikoärmer, keine bestehende
  Konfiguration bricht) **und wird als neuer Vorgabewert der Option
  `cbd_personal_notes_manager` gesetzt** (statt weiterhin `disabled`). Die
  Wiederherstellung wirkt damit ohne manuellen Admin-Schritt — auf jeder
  Installation, auf der die Option noch nie explizit gesetzt wurde. Für
  bereits bestehende Installationen mit einem gespeicherten Wert (auch
  `disabled`) gilt die WordPress-Standardregel: `get_option()` liefert den
  gespeicherten Wert, der neue Vorgabewert wirkt nur, wo die Option in der
  Datenbank noch nicht existiert. Das betroffene AP muss diese Randbedingung
  im Testprotokoll festhalten (Neuinstallation vs. Bestandsinstallation).
- **Offene Frage:** Schriftgröße/-farbe des Text-Werkzeugs — folgt es der
  aktuellen Stiftfarbe/-dicke (Wiederverwendung bestehender Regler) oder
  bekommt es einen eigenen, kleinen Satz Einstellungen? Vorschlag: bestehende
  Stiftfarbe wiederverwenden (kein neuer Regler nötig), feste, an der
  Tafel-Textgröße (`this.fontSize`) orientierte Schriftgröße als Startpunkt.
- **Doku-Lücke:** `reference_file_map.md` beschreibt `personal-notes-manager.js`
  bislang nur mit dem Einzeiler „Persönliche Notizen" — wird im Zuge dieser
  Erweiterung auf den aktuellen Stand gebracht (Pflicht laut Skill-Vorgabe).

## 10. Grobzuschnitt für den projektplan-skill

**Mehrphasig**, da zwei fachlich unabhängige Erweiterungen mit jeweils
eigenem Regressionsrisiko (>5 APs zu erwarten, TDD-taugliche Kern-Logik in
`board-mode.js` und `class-cbd-style-loader.php`):

- **Phase 1 — Text-Werkzeug im Tafelmodus:** neues Werkzeug samt Overlay,
  `fillText()`-Rendering, Undo-Integration, CSS (inkl. Darkmode), Live-Test
  im Tafelmodus (persönlich und im Klassenmodus), Review-AP, Doku-AP.
- **Phase 2 — Notizen-Download/-Upload sichtbar machen:** Umbau
  `enqueue_feature_styles()` (eigenes AP mit Regressionstest „Seite ohne
  Feature lädt nichts"), neuer Options-Wert `toc` in `admin/settings.php`,
  Darkmode-Sichtprüfung von `personal-notes-manager.css`, Live-Test auf
  einer echten Inhaltsverzeichnis-Seite (Export, Import, Löschen, jeweils
  mit und ohne vorhandene Notizen), Review-AP, Doku-AP.

Reihenfolge zwischen den Phasen ist beliebig (keine Abhängigkeit
zueinander); getrennte Branches empfehlenswert, analog zu früheren
gebündelten, aber fachlich unabhängigen Vorhaben dieses Projekts (siehe
`PLAN-PDF-Export-und-Tafelmodus-Fixes.md`, zwei parallele Stränge).
