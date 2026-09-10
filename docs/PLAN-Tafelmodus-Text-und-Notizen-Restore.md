# Projektplan: Text-Werkzeug im Tafelmodus + Wiederherstellung Notizen-Download/-Upload

_Erstellt am: 2026-09-10 · Letzte Aktualisierung: 2026-09-10 (Phase 1 vollständig abgeschlossen)_

## 0. Anweisungen für den ausführenden Agenten

Du arbeitest nach diesem Plan. Er ist die einzige Wahrheitsquelle – du hast
keinen Zugriff auf das Gespräch, in dem er entstand, und auch nicht auf die
Datei `docs/ERWEITERUNGSANALYSE-Tafelmodus-Text-und-Notizen-Restore.md`, aus
der er hervorgegangen ist. Halte dich an diese Regeln:

**Rollen und Modelle:**
A. Wird die Abarbeitung von einem Orchestrator koordiniert (Opus), gilt:
   Der Orchestrator delegiert APs an Subagenten und implementiert NIEMALS
   selbst. Er gibt jedem Subagenten nur dessen AP-Text plus die Abschnitte
   0–3 dieses Plans als Kontext, prüft jede Rückmeldung gegen die
   Akzeptanzkriterien des APs, bevor er abhängige APs freigibt, und pflegt
   die Statustabelle.
B. Jedes AP nennt sein Ausführungsmodell (**Modell:** sonnet | opus).
   Subagenten mit genau diesem Modell starten.
C. Unabhängige APs derselben Phase (keine gemeinsamen Abhängigkeiten,
   disjunkte Dateien) dürfen parallel bearbeitet werden – in Claude Code
   idealerweise in getrennten Git-Worktrees mit je eigenem Branch. APs, die
   dieselben Dateien ändern, nie parallel ausführen.

**Arbeitsweise:**
1. Bearbeite genau EIN Arbeitspaket (AP) pro Auftrag, sofern nicht anders
   beauftragt.
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
9. Nach dem letzten Implementierungs-AP einer Phase zusätzlich:
   Integrationstest der Phase + Regressionscheck der jeweils anderen Phase
   (deren „lauffähiger Endzustand" muss weiterhin funktionieren, siehe
   Abschnitt 6). Eintrag ins Testprotokoll.
10. Danach folgt das Review-AP (`AP-<N>.rev`): ausgeführt von einem frischen
    Agenten, der KEINES der APs dieser Phase implementiert hat. Der
    Review-Agent arbeitet ausschließlich lesend und verändert keine Datei.
    Kritische Befunde führen zu Korrektur-APs (siehe Regel 12); die Phase
    ist erst danach abgeschlossen.

**Übergabe:**
11. Fülle die Übergabenotiz deines APs aus: was geändert wurde, getroffene
    Entscheidungen, was für Folge-APs relevant ist.
12. Hat dein AP Dateien angelegt, verschoben oder wesentlich geändert:
    aktualisiere deren Zeilen in `reference_file_map.md` (Datei | Zweck |
    wichtige Funktionen | Abhängigkeiten). `CLAUDE.md` wird im
    Dokumentations-AP am Phasenende (`AP-<N>.doc`) nachgezogen.
13. Aktualisiere „Letzte Aktualisierung" im Dateikopf dieses Plans.
14. Git: mindestens ein Commit mit AP-ID im Text, z. B.
    `AP-1.2: Text-Werkzeug speichert als eigenen Stroke`. Nach jedem
    abgeschlossenen AP den Phasen-Branch zum Remote pushen
    (`git push -u origin <branch>`) – das Remote ist das Backup des
    Fortschritts. Phasen-Branches erst nach bestandenem Integrationstest UND
    Review in den Hauptbranch (`main`) mergen, danach ebenfalls pushen.

**Umplanung:**
15. Zeigt sich während der Ausführung, dass der Plan nicht trägt (Review-
    Befunde, blockierte APs, falsche Annahmen), werden Korrektur-APs mit
    fortlaufender Nummer ergänzt (`AP-<N>.fix1`, …) und in Statustabelle und
    Testprotokoll aufgenommen. Bestehende APs und Übergabenotizen werden nie
    gelöscht, nur ergänzt – der Plan bleibt nachvollziehbare Historie.

**Zeilenangaben in diesem Plan** (z. B. „Zeile 213") beziehen sich auf den
Dateistand vom 2026-09-10. Haben sich Zeilennummern durch andere,
zwischenzeitliche Änderungen verschoben, suche über die im AP zusätzlich
genannten Anker-Kommentare/Funktionsnamen statt dich blind auf die Zahl zu
verlassen.

## 1. Projektziel

Im Tafelmodus des Plugins CDB-Designer (Container Block Designer) gibt es
nach Abschluss dieses Plans (a) ein zusätzliches Zeichenwerkzeug „Text", mit
dem Schüler ohne Stift-/Tableteingabe getippten Text an einer angeklickten
Stelle auf die Tafel setzen können, und (b) einen wiederhergestellten,
sichtbaren Weg, alle lokal im Browser gespeicherten Tafel-Notizen für einen
Gerätewechsel zu exportieren/importieren/löschen – automatisch verfügbar auf
jeder Seite, die den Theme-Block „Inhaltsverzeichnis" (`fos/inhaltsverzeichnis`)
enthält.

## 2. Nicht-Ziele

- Kein vollständiger Moduswechsel der ganzen Tafel zu einer Textarea – das
  Text-Werkzeug ist ein zusätzliches Werkzeug neben Stift/Textmarker/
  Radierer, kein Ersatz für das Zeichnen.
- Kein neuer Datentyp in der Speicher-/Export-Kette (`drawing_data`,
  `localStorage`, PDF-Export) – Text wird ausschließlich als Pixel auf der
  ohnehin gerasterten Zeichenfläche gespeichert.
- Kein Wiedereinbau eines Options-Menüs in der Tafel-Werkzeugleiste selbst
  für Download/Upload/Löschen persönlicher Notizen – diese Variante wurde
  bewusst verworfen (Commit `ae681c4`, „war Missverständnis"), stattdessen
  wird ausschließlich der bereits vorhandene, globale Button
  (`assets/js/personal-notes-manager.js`) sichtbar gemacht.
- Keine Änderung an `assets/js/personal-notes-manager.js` selbst – die
  Export-/Import-/Löschen-Logik ist bereits fertig und funktionsfähig.
- Kein Ersatz der bestehenden Options-Werte `all`/`specific` von
  `cbd_personal_notes_manager` – der neue Wert `toc` kommt zusätzlich dazu.
- Keine Änderung an Server-Endpunkten, PDF-Export oder der
  Klassenmodus-Datenschicht (`class-cbd-classroom.php` u. a.).

## 3. Kontext & Constraints

- **Umgebung:** WordPress-Plugin, PHP 7.4-kompatibel (Zielumgebung 7.4.33),
  kein JavaScript-Build-Prozess (reines ES5 in IIFEs, `var`/`function`, kein
  `import`/`export`).
- **Bestehende Konventionen:**
  `Plugins/CDB-Designer/CLAUDE.md` und `Plugins/CDB-Designer/reference_file_map.md`
  (jeweils im Repo-Root dieses Plugins). Neuer CSS-Code ausschließlich
  `var(--x, #fallback)`, nie hartcodierte Hex-Werte; Dunkelmodus ausschließlich
  über `[data-theme="dark"] .selektor`, **niemals**
  `@media (prefers-color-scheme: dark)`. Debugging-Logs hinter
  `window.cbdDebug` (nur `console.log`; `console.error`/`console.warn` bleiben
  immer aktiv).
- **Harte Grenzen:** Keine externen/CDN-Ressourcen (DSGVO). „Buttons folgen
  Feature-Flags" – ein Bedienelement erscheint nie, wenn sein Feature/seine
  Einstellung abgeschaltet ist. PHP-Syntaxprüfung vor jedem ZIP-Bau ist
  Pflicht: `node create-plugin-zip.js` führt `tools/check-php74.php`
  automatisch aus und bricht bei PHP-8-only-Syntax ab.
- **Testumgebung:** Lokaler WordPress-Testserver unter `fos.localhost:8080`.
  Die Plugin-Dateien liegen dort als **Kopie** (kein Symlink) unter
  `C:\allinkl-testserver\www\htdocs\w0000001\fos\wp-content\plugins\container-block-designer`.
  Für einen Live-Test müssen geänderte Dateien dorthin kopiert werden (z. B.
  `Copy-Item` in PowerShell), bevor die Seite im Browser neu geladen wird.
  Datenbankzugriff über phpMyAdmin unter `C:\allinkl-testserver\phpmyadmin`
  (falls für einen Test ein Blick in `wp_posts`/`wp_options` nötig ist).
- **Git-Strategie:** Branch pro Phase (`phase-1-text-werkzeug`,
  `phase-2-notizen-restore`), Commit pro AP mit AP-ID im Text. Beide Phasen
  sind voneinander unabhängig (keine gemeinsamen Dateien) und können in
  getrennten Branches/Worktrees parallel bearbeitet werden.
- **Remote-Repository:** `https://github.com/Cyric25/CBD---Container-Block-Desinger.git`
  (bereits verbunden, `git remote -v` bestätigt Fetch/Push-URL) – kein
  Einrichtungs-AP nötig.

## 4. Architekturentscheidungen

| Entscheidung | Begründung | Verworfene Alternative |
|---|---|---|
| Text-Werkzeug wird als weiterer Eintrag `data-tool="text"` im bestehenden Werkzeug-System von `board-mode.js` gebaut (Klick-Handler-Schleife setzt bereits generisch `self.setTool(tool)`) | Kein neues System nötig – Stift/Textmarker/Radierer funktionieren bereits nach diesem Muster; geringstes Risiko, größte Konsistenz | Eigener, paralleler „Text-Modus" mit eigener Zustandsverwaltung |
| Bestätigter Text wird sofort per `ctx.fillText()` auf `this.drawingCtx` gerastert und als Eintrag `{tool:'text', text, color, width, points:[{x,y}]}` in `this.strokes` abgelegt | Die Tafel wird beim Speichern ohnehin komplett zu PNG gerastert (`toDataURL()`); ein Eintrag im bestehenden `strokes`-Array macht Undo (`this.strokes.pop()`), Redraw (`redrawAllStrokes()`) und Speichern (`close()` → `saveDrawing()`) automatisch korrekt, ohne diese drei Funktionen fachlich zu erweitern | Eigener Text-Layer/eigene Datenstruktur parallel zu `strokes`, mit eigener Undo-Logik |
| Notizen-Manager-Enqueue wird aus `enqueue_feature_styles()` in eine **eigene, neue Methode** `enqueue_notes_manager_styles()` extrahiert und separat aus `enqueue_frontend_styles()` aufgerufen | Die bestehende, früh abbrechende Logik in `enqueue_feature_styles()` (Zeile ~180 `return;`) bleibt dadurch **unangetastet** – minimales Risiko für das produktiv laufende Feature-Enqueue-System. Die Notizen-Sichtbarkeit ist eine reine `localStorage`/`has_block()`-Frage, fachlich unabhängig vom Container-Block-Feature-Scan | Umbau der `if/elseif/else return`-Kette in `enqueue_feature_styles()` selbst |
| Sichtbarkeitskriterium „Inhaltsverzeichnis-Seite" über `has_block('fos/inhaltsverzeichnis', $post)` (WordPress-Kernfunktion) | Prüft nur `post_content` auf den Blocknamen – kein Aufruf einer Theme-Funktion, kein `function_exists()`-Schutz nötig, keine neue Naht zwischen Plugin und Theme (anders als bei den drei bestehenden, dokumentierten Theme-Funktionsaufrufen des Plugins) | Manuelle Seitenliste über die bereits vorhandene `specific`-Option (hätte bei neuen Inhaltsverzeichnis-Seiten manuelle Pflege gebraucht) |
| Neuer Options-Wert `'toc'` für `cbd_personal_notes_manager` als **zusätzlicher** dritter Wert (neben `disabled`/`all`/`specific`) und als **neuer Vorgabewert** der Option | Nutzerentscheidung vom 2026-09-10: additiv statt ersetzend (bricht keine bestehende Konfiguration), aktiv per Vorgabewert (wirkt ohne manuellen Admin-Schritt) | `toc` ersetzt `all`/`specific`; Vorgabewert bleibt `disabled` |

## 5. Risiken & Rollback

| Risiko | Wahrscheinlichkeit | Auswirkung | Gegenmaßnahme / Rollback |
|---|---|---|---|
| Umbau von `class-cbd-style-loader.php` bricht das bestehende Feature-Enqueue-System (Board Mode/Icons laden auf falschen Seiten oder gar nicht mehr) | gering (durch Extraktion in eigene Methode strukturell vermieden) | hoch (Tafelmodus-Container würden site-weit nicht mehr funktionieren) | AP-2.1 fasst `enqueue_feature_styles()` selbst nicht an; Regressionstest in AP-2.1 prüft explizit „Seite ohne jedes Feature lädt weiterhin nichts" und „Seite mit boardMode-Feature lädt Board-Mode-Assets unverändert" |
| Text-Werkzeug verändert `redrawAllStrokes()` so, dass bestehende Striche (Stift/Textmarker) falsch gerendert werden | gering | mittel (sichtbare Zeichenfehler im produktiven Tafelmodus) | AP-1.2 fügt nur einen neuen, klar abgegrenzten dritten Rendering-Pass hinzu und schließt `tool === 'text'` explizit aus den bestehenden zwei Pässen aus; Regressionstest in AP-1.2 prüft Stift/Textmarker/Radierer unverändert |
| Vorgabewert-Änderung `disabled` → `toc` wirkt auf Bestandsinstallationen anders als erwartet | gering | gering (WordPress-`get_option()`-Semantik: gespeicherte Werte werden nie überschrieben) | AP-2.2 dokumentiert diese Randbedingung explizit im Test; kein Datenbank-Migrationsschritt nötig |
| `personal-notes-manager.css` (Stand v3.0.x) hat im Darkmode unlesbaren Kontrast | mittel (Datei ist älter als die projektweite Darkmode-Umstellung) | gering (rein optisch, Funktion bleibt erhalten) | AP-2.3 prüft gezielt im Darkmode und behebt gefundene Kontrastfehler nach dem Muster `var(--x, #fallback)` + `[data-theme="dark"]` |

**Generelle Rollback-Strategie:** Branch pro Phase, Commit pro AP. Ein
fehlgeschlagenes AP wird über `git revert`/Zurücksetzen auf den letzten
grünen Commit des Phasen-Branches zurückgenommen, bevor erneut versucht
wird. Kein Datenbankschema betroffen, keine destruktiven Operationen –
zusätzliche Sicherung nicht erforderlich.

## 6. Phasenübersicht

Jede Phase endet mit `AP-<N>.rev` (unabhängiges Review) und `AP-<N>.doc`
(Dokumentation). Die beiden Phasen sind voneinander unabhängig.

| Phase | Ziel | Lauffähiger Endzustand | APs |
|---|---|---|---|
| 1 | Text-Werkzeug im Tafelmodus | Im Tafelmodus lässt sich das Werkzeug „Text" auswählen, per Klick auf die Zeichenfläche ein Texteingabefeld öffnen, der eingegebene Text erscheint an der Klickposition auf der Tafel, überlebt Seitenwechsel/Schließen/erneutes Öffnen (wird gespeichert) und lässt sich per Strg+Z rückgängig machen. Alle bisherigen Werkzeuge funktionieren unverändert. | AP-1.1, AP-1.2, AP-1.rev, AP-1.fix1, AP-1.fix2, AP-1.doc |
| 2 | Notizen-Download/-Upload wiederherstellen | Auf jeder veröffentlichten Seite mit dem Block „Inhaltsverzeichnis" erscheint automatisch (ohne manuellen Admin-Schritt auf einer frischen Installation) der schwebende Button zum Exportieren/Importieren/Löschen aller lokal gespeicherten Tafel-Notizen. Seiten ohne diesen Block und ohne andere aktive Notizen-Manager-Einstellung zeigen weiterhin keinen Button. Das bestehende Feature-Enqueue-System (Board Mode, Icons) verhält sich unverändert. | AP-2.1, AP-2.2, AP-2.3, AP-2.rev, AP-2.doc |

## 7. Arbeitspakete

### Phase 1: Text-Werkzeug im Tafelmodus

#### AP-1.1: Werkzeug-Button „Text" + Eingabe-Overlay

**Status:** ☑ erledigt
**Umfang:** M
**Modell:** sonnet (Lösungsweg eindeutig vorgezeichnet, bestehendes Muster wird fortgeführt)
**Abhängigkeiten:** keine

**Ziel & Kontext:**
`assets/js/board-mode.js` implementiert den Tafelmodus: eine Canvas-
Zeichenfläche mit Werkzeugen Stift/Textmarker/Strich-Radierer/Punkt-Radierer.
Jeder Werkzeug-Button trägt ein Attribut `data-tool="<name>"`; ein
gemeinsamer Klick-Handler (`board-mode.js`, Methode `bindEvents`, Bereich um
Zeile 750–760) liest dieses Attribut und ruft `self.setTool(tool)` auf,
danach wird die Klasse `active` umgehängt:

```js
var toolButtons = this.overlay.querySelectorAll('.cbd-board-tool');
toolButtons.forEach(function(btn) {
    btn.addEventListener('click', function() {
        var tool = this.getAttribute('data-tool');
        self.setTool(tool);
        toolButtons.forEach(function(b) { b.classList.remove('active'); });
        this.classList.add('active');
    });
});
```

Dieses AP fügt ein fünftes Werkzeug `data-tool="text"` hinzu. Klickt der
Nutzer bei aktivem Text-Werkzeug auf die Zeichenfläche, öffnet sich (statt
eine Linie zu zeichnen) ein kleines, absolut positioniertes Eingabefeld an
der Klickposition. Dieses AP liefert die Bedienoberfläche und das sofortige
Stempeln des Texts auf die Zeichenfläche (per `ctx.fillText()`); die
dauerhafte Speicher-/Undo-Integration als eigener Stroke-Eintrag folgt in
AP-1.2 (dort wird auch der zusätzliche Rendering-Pass in `redrawAllStrokes()`
ergänzt).

**Betroffene Dateien:**
- `assets/js/board-mode.js` (ändern)
- `assets/css/board-mode.css` (ändern)

**Vorgehen:**
1. **Toolbar-Button ergänzen** in der HTML-Vorlage (Methode, die den
   Toolbar-HTML-String baut – Bereich um Zeile 355–367, direkt nach dem
   Button `data-tool="eraser-point"` und vor dem folgenden
   `<span class="cbd-board-separator"></span>`):
   ```js
   '<button class="cbd-board-tool" data-tool="text" title="Text einfügen">' +
       '<span class="dashicons dashicons-text-page"></span>' +
   '</button>' +
   ```
   Existiert das Dashicon `dashicons-text-page` nicht (sichtbar als leeres
   Quadrat im Browser), durch `dashicons-edit` oder ein Text-Fallback `"T"`
   im Button ersetzen – Funktion hat Vorrang vor Icon-Wahl.
2. **Klick-auf-Zeichenfläche abfangen:** In `onPointerDown(e)` (Bereich um
   Zeile 1298–1375) wird die Klickposition bereits berechnet:
   ```js
   var rect = this.drawingCanvas.getBoundingClientRect();
   this.lastX = (e.clientX - rect.left) / this.zoom;
   this.lastY = (e.clientY - rect.top) / this.zoom;
   ```
   Direkt danach, **vor** den bestehenden Verzweigungen für
   `eraser-point`/`eraser-stroke`, einen neuen Zweig einfügen:
   ```js
   if (this.currentTool === 'text') {
       this.openTextInput(e.clientX, e.clientY);
       return; // kein Zeichen-Strich beginnen
   }
   ```
3. **Neue Methode `openTextInput(clientX, clientY)`** in `board-mode.js`
   ergänzen (z. B. direkt vor `showClearConfirm()`, Bereich um Zeile 1261 –
   dieselbe Datei benutzt an dieser Stelle bereits das Muster
   „Overlay-Element an `document.body` anhängen", siehe `showClearConfirm()`
   als Vorbild):
   - Erzeugt ein `<textarea class="cbd-board-text-input">`, `position: fixed`,
     `left: clientX + 'px'`, `top: clientY + 'px'`, initiale Größe ca.
     200×60px, `z-index` über der Tafel-Werkzeugleiste.
   - Merkt sich die zugehörigen Canvas-Koordinaten (`this.lastX`,
     `this.lastY` zum Zeitpunkt des Klicks) in lokalen Variablen der
     Closure.
   - Hängt das Element an `document.body` an und fokussiert es
     (`textarea.focus()`).
   - **Bestätigen:** Taste `Enter` **ohne** `Shift` → Text übernehmen
     (siehe Schritt 4) und Overlay entfernen; `Enter` **mit** `Shift` fügt
     einen Zeilenumbruch ein (Standard-Textarea-Verhalten, kein
     Sonderfall nötig). Verlässt das Feld den Fokus (`blur`), wird der
     aktuelle Inhalt ebenfalls übernommen (nicht verworfen).
   - **Abbrechen:** Taste `Escape` entfernt das Overlay **ohne** den Text
     zu übernehmen.
   - In jedem Fall (Bestätigen, Abbrechen, `blur`) wird das Overlay-Element
     aus dem DOM entfernt – es darf nie mehrfach gleichzeitig existieren.
4. **Text auf die Zeichenfläche stempeln** (in diesem AP als direkter
   `fillText()`-Aufruf ohne Stroke-Integration – AP-1.2 baut das zu einem
   `strokes`-Eintrag aus): Ist der eingegebene Text nach `trim()` nicht leer,
   in der Bestätigen-Funktion:
   ```js
   var fontSizePx = Math.round(16 + this.lineWidth * 4); // 20–96px je nach Stiftdicke
   this.drawingCtx.font = fontSizePx + 'px sans-serif';
   this.drawingCtx.fillStyle = this.currentColor;
   this.drawingCtx.textBaseline = 'top';
   var lines = text.split('\n');
   for (var i = 0; i < lines.length; i++) {
       this.drawingCtx.fillText(lines[i], canvasX, canvasY + i * fontSizePx * 1.2);
   }
   ```
   (`canvasX`/`canvasY` sind die in Schritt 3 gemerkten `this.lastX`/
   `this.lastY`-Werte des Klicks.)
5. **CSS ergänzen** in `assets/css/board-mode.css`:
   - Stil für `.cbd-board-tool[data-tool="text"]` ist bereits über die
     bestehende `.cbd-board-tool`-Regel abgedeckt (kein Zusatz nötig,
     sofern kein Dashicon-Fallback als reiner Text `"T"` verwendet wird –
     dann Schriftgröße/Zentrierung analog zu den `◀`/`▶`-Seiten-Buttons in
     derselben Datei ergänzen).
   - Neue Regel `.cbd-board-text-input`: `position: fixed`, `resize: both`,
     `min-width: 120px`, `min-height: 40px`, `padding: 6px`,
     `border: 2px solid var(--color-ui-surface, #e24614)`,
     `border-radius: 6px`, `font-family: sans-serif`,
     `background: var(--color-background, #fff)`,
     `color: var(--color-text-primary, #333)`, `z-index: 100000`.
   - Dunkelmodus-Pendant:
     `[data-theme="dark"] .cbd-board-text-input { background: var(--color-background, #1e1e1e); color: var(--color-text-primary, #eee); border-color: var(--color-ui-surface, #e24614); }`
     (Werte aus den bereits im Projekt etablierten Dunkelmodus-Variablen
     übernehmen, siehe die bestehenden `[data-theme="dark"]`-Regeln
     weiter unten in derselben Datei als Vorbild für die exakten
     Variablennamen).

**Akzeptanzkriterien:**
- [ ] Ein neuer Button mit `data-tool="text"` erscheint in der
      Tafel-Werkzeugleiste und lässt sich wie die übrigen Werkzeuge
      auswählen (erhält die Klasse `active`, andere Werkzeuge verlieren sie).
- [ ] Bei aktivem Text-Werkzeug öffnet ein Klick auf die Zeichenfläche ein
      Eingabefeld genau an der Klickposition.
- [ ] Eingegebener Text erscheint nach Bestätigen (Enter ohne Shift, oder
      Fokusverlust) an der Klickposition auf der Zeichenfläche, in der
      aktuell gewählten Stiftfarbe.
- [ ] `Escape` schließt das Eingabefeld, ohne dass Text auf der Zeichenfläche
      erscheint.
- [ ] Leerer/nur-Leerzeichen-Text erzeugt keine sichtbare Änderung.
- [ ] Mehrzeiliger Text (Shift+Enter) wird zeilenweise untereinander
      dargestellt.
- [ ] Alle bisherigen Werkzeuge (Stift, Textmarker, beide Radierer) lassen
      sich weiterhin unverändert auswählen und benutzen.
- [ ] Im Dunkelmodus (`data-theme="dark"` auf `<html>`) ist das Eingabefeld
      lesbar (kein dunkler Text auf dunklem Grund oder umgekehrt).
- [ ] `reference_file_map.md` (Zeile zu `assets/js/board-mode.js` und
      `assets/css/board-mode.css`) aktualisiert.

**Tests:**
- Smoke-Test: Geänderte Dateien nach
  `C:\allinkl-testserver\www\htdocs\w0000001\fos\wp-content\plugins\container-block-designer\assets\js\board-mode.js`
  bzw. `...\assets\css\board-mode.css` kopieren, eine Seite mit
  Tafelmodus-Container im Browser laden (`http://fos.localhost:8080/...`),
  Browser-Cache umgehen (Hard-Reload), Tafelmodus öffnen. Browser-Konsole
  muss frei von Errors sein.
- Prüfschritt 1: Text-Werkzeug wählen, auf die Tafel klicken, „Testtext"
  eingeben, Enter drücken → „Testtext" erscheint an der Klickstelle.
- Prüfschritt 2: Text-Werkzeug wählen, klicken, „Zeile 1", Shift+Enter,
  „Zeile 2" eingeben, Enter → beide Zeilen untereinander sichtbar.
- Prüfschritt 3: Text-Werkzeug wählen, klicken, Escape drücken → kein Text
  erscheint, kein Eingabefeld mehr sichtbar.
- Prüfschritt 4 (Regression): Stift-Werkzeug wählen, eine Linie zeichnen →
  Linie erscheint wie vor dieser Änderung. Textmarker und beide Radierer
  ebenso stichprobenartig prüfen.
- Prüfschritt 5 (Dunkelmodus): Darkmode-Umschalter im Header aktivieren,
  Text-Werkzeug erneut öffnen und Text eingeben → Eingabefeld lesbar.

**Übergabenotiz:**
Alle Schritte wie im Vorgehen beschrieben umgesetzt. Zusätzlich **einen
bei den Live-Tests gefundenen und noch in diesem AP behobenen Bug**: Der
Keydown-Handler von `.cbd-board-text-input` rief `e.preventDefault()`,
aber nicht `e.stopPropagation()` auf. Escape im Textfeld bubbelte dadurch
zum dokumentweiten `onKeyDown()` des Tafelmodus hoch und schloss die
**gesamte** Tafel (inkl. `saveDrawing()`) statt nur das Textfeld — live am
Testserver reproduziert (Tafel schloss sich komplett, `cbd-board-overlay`
verschwand aus dem DOM). Behoben durch `e.stopPropagation()` als erste
Anweisung im Keydown-Handler.

Live-Test durchgeführt auf `http://fos.localhost:8080/?page_id=117`
(„Die wichtigsten Organischen Grundlagen", Container-Design `infotext_k1`
mit aktivem Feature `boardMode`), lokaler Testserver zuvor über
`start-server.cmd` gestartet (war beim Sessionstart nicht aktiv).
Interaktion über eine Mischung aus echten `computer`-Tool-Klicks (Werkzeug-
Button-Auswahl, ein Dreh mit `left_click_drag` für den Regressionstest)
und gezielt dispatchten `PointerEvent`/`KeyboardEvent`s über die
JavaScript-Konsole (für präzise, wiederholbare Klickpositionen auf der
Zeichenfläche) — beide Wege lieferten identische Ergebnisse.

Alle Akzeptanzkriterien einzeln bestätigt:
- Button `data-tool="text"` existiert, aktivierbar, `.active`-Klasse
  wandert korrekt (bestätigt sowohl per echtem Klick als auch
  `currentTool`-Auslesen).
- Klick auf die Zeichenfläche öffnet das Eingabefeld exakt an
  `clientX`/`clientY` (per `style.left`/`style.top` verifiziert).
- „Testtext" erscheint nach Enter an der Klickposition, schwarz (Default-
  Stiftfarbe) — per Screenshot visuell bestätigt.
- `Escape` schließt nur das Textfeld, kein Text erscheint, **Tafel bleibt
  offen** (nach dem Fix oben).
- Leer-/Whitespace-Text (`"    "`) erzeugt nachweislich **keine**
  Pixeländerung (Ink-Pixelzahl vor/nach identisch: 3126/3126).
- Mehrzeiliger Text (`"ZeileA\nZeileB"`) wird zeilenweise mit Abstand
  `fontSizePx * 1.2` untereinander gerendert — per Screenshot bestätigt.
  `Shift+Enter` committet **nicht** (Feld bleibt offen) — isoliert
  bestätigt.
- Regression: Stift-Werkzeug zeichnet weiterhin korrekt (Strich sichtbar
  im Screenshot, `strokes`-Array wächst), Textmarker fügt einen Stroke
  hinzu, beide Radierer lassen sich fehlerfrei aktivieren, Zurückwechseln
  zu „pen" funktioniert.
- Dunkelmodus: `.cbd-board-text-input` per `getComputedStyle()` geprüft —
  `background-color: rgb(30, 30, 30)`, `color: rgb(232, 232, 232)`,
  `border-color: rgb(226, 70, 20)` (= `#e24614`) — guter Kontrast, lesbar.

**Bekannte, für AP-1.2 relevante Beobachtung (kein Fehler dieses APs,
sondern der erwartete, im Plan bereits beschriebene Zwischenzustand):**
Direkt gestempelter Text übersteht `redrawAllStrokes()` noch nicht — ein
danach gezeichneter Stift-Strich (der `redrawAllStrokes()` aufruft) hat den
zuvor direkt gemalten Text nachweislich gelöscht. Das ist exakt der in
AP-1.2 zu behebende Zustand, keine Abweichung vom Plan.

**Nebenbefund, nicht behoben (außerhalb des AP-Scopes):** Synthetisch per
`element.dispatchEvent(new PointerEvent(...))` ausgelöste Klicks auf die
Zeichenfläche lösen beim **bereits vorher bestehenden** Aufruf
`this.drawingCanvas.setPointerCapture(e.pointerId)` (Ende von
`onPointerDown()`, vorbestehender Code, nicht Teil dieses APs) einen
`NotFoundError` aus, weil ein synthetisch erzeugter Pointer keine „aktive"
Capture-Sitzung beim Browser hat. Mit echten Eingaben (realer Mausklick,
`computer`-Tool-`left_click_drag`) tritt der Fehler **nicht** auf — live
gegengeprüft: derselbe Zeichenvorgang per echtem Drag erzeugte keinen
zusätzlichen Konsolenfehler. Reine Eigenart der Testmethode, kein
Produktivfehler.

Testserver-`localStorage` nach jedem Testabschnitt bereinigt
(`cbd-board-*`-Schlüssel entfernt), damit keine Test-Zeichnungen liegen
bleiben. Der lokale Testserver (`start-server.cmd`) läuft nach Abschluss
dieses APs weiter, für den nahtlosen Übergang zu AP-1.2.

Kein zusätzlicher `dashicons-text-page`-Fallback nötig — das Icon
existiert nachweislich in `wp-includes/css/dashicons.css` des Testservers.

**`reference_file_map.md` aktualisiert** (Zeilen zu `board-mode.js` und
`board-mode.css`).


#### AP-1.2: Text-Eintrag speichert und ist rückgängig machbar

**Status:** ☑ erledigt
**Umfang:** M
**Modell:** sonnet (Vorgehen konkret vorgezeichnet, folgt bestehendem Muster des `highlighter`-Sonderfalls)
**Abhängigkeiten:** AP-1.1

**Ziel & Kontext:**
AP-1.1 zeichnet den eingegebenen Text direkt auf `this.drawingCtx`. Das
reicht nicht aus: Die Tafel wird an mehreren Stellen komplett aus
`this.baseImageObj` (Basisbild) + `this.strokes` (Array bereits
abgeschlossener Striche) neu aufgebaut – u. a. `undo()` (Bereich um Zeile
1981: `this.strokes.pop(); this.redrawAllStrokes();`), `eraseStrokeAtPoint()`
und ein Seitenwechsel. Ein nur direkt gemalter Text würde bei jedem dieser
Redraws verschwinden, weil `redrawAllStrokes()` (Bereich um Zeile 1489–1558)
den Canvas-Inhalt zunächst löscht und ausschließlich aus `baseImageObj` +
`strokes` neu aufbaut. Dieses AP macht den Text zu einem regulären Eintrag in
`this.strokes`, analog zum bestehenden Sonderfall `tool === 'highlighter'`,
der in `redrawAllStrokes()` bereits eigens behandelt wird (zwei Rendering-
Pässe: Textmarkierer unten, normale Striche oben). Nach diesem AP nimmt Text
automatisch an Speichern (`close()` → `saveDrawing()`, dieselbe Funktion, die
auch für Stift-Striche zuständig ist – keine Änderung an
`saveToServer()`/`saveToCache()`/`loadFromServer()`/`loadFromCache()` nötig)
und Undo teil.

**Betroffene Dateien:**
- `assets/js/board-mode.js` (ändern)

**Vorgehen:**
1. In der in AP-1.1 gebauten Bestätigen-Funktion von `openTextInput()`:
   **Statt** des direkten `fillText()`-Aufrufs aus AP-1.1 Schritt 4 einen
   Eintrag zu `this.strokes` hinzufügen:
   ```js
   this.strokes.push({
       tool: 'text',
       text: text,               // getrimmter, nicht-leerer Eingabetext
       color: this.currentColor,
       width: this.lineWidth,    // wird für die Schriftgrößen-Formel wiederverwendet
       points: [{x: canvasX, y: canvasY}]
   });
   this.redrawAllStrokes();
   ```
   Der direkte `fillText()`-Aufruf aus AP-1.1 entfällt ersatzlos – das
   Rendering übernimmt ab jetzt ausschließlich `redrawAllStrokes()` über den
   neuen Pass aus Schritt 2.
2. In `redrawAllStrokes()` (Bereich um Zeile 1489–1558):
   a. In **Pass 2** (normale Striche, Bereich um Zeile 1535–1554,
      `for (var i = 0; i < allStrokes.length; i++) { var stroke = allStrokes[i]; if (stroke.tool === 'highlighter') continue; ...}`)
      die Bedingung erweitern, damit Text-Einträge dort **nicht** als Linie
      gezeichnet werden:
      ```js
      if (stroke.tool === 'highlighter' || stroke.tool === 'text') continue;
      ```
   b. Direkt **nach** Pass 2 (nach der Zeile
      `this.drawingCtx.globalCompositeOperation = 'source-over';` am Ende
      der Methode, Bereich um Zeile 1556–1557) einen neuen **Pass 3**
      einfügen, der Text-Einträge zeichnet:
      ```js
      // Pass 3: Text-Einträge zeichnen (oberste Ebene)
      for (var k = 0; k < allStrokes.length; k++) {
          var textStroke = allStrokes[k];
          if (textStroke.tool !== 'text') continue;
          var fontSizePx = Math.round(16 + textStroke.width * 4);
          this.drawingCtx.font = fontSizePx + 'px sans-serif';
          this.drawingCtx.fillStyle = textStroke.color;
          this.drawingCtx.textBaseline = 'top';
          var textLines = textStroke.text.split('\n');
          for (var li = 0; li < textLines.length; li++) {
              this.drawingCtx.fillText(
                  textLines[li],
                  textStroke.points[0].x,
                  textStroke.points[0].y + li * fontSizePx * 1.2
              );
          }
      }
      ```
3. Prüfen, dass `eraseStrokeAtPoint()` (Bereich um Zeile 1453–1484) einen
   Text-Eintrag korrekt behandelt: Die Methode iteriert über
   `stroke.points` und vergleicht Distanzen – bei einem Text-Eintrag mit
   genau einem Punkt (`points: [{x,y}]`) funktioniert das bereits
   unverändert (ein Klick nahe der eingefügten Textposition mit dem
   Strich-Radierer löscht den gesamten Text-Eintrag). Kein Codeänderung an
   dieser Methode nötig – nur durch einen Test bestätigen (siehe unten).

**Akzeptanzkriterien:**
- [ ] Text-Eintrag ist Teil von `this.strokes` (nicht mehr direkt auf den
      Canvas gemalt).
- [ ] Nach Eingabe von Text: Tafelmodus schließen, erneut öffnen → derselbe
      Text ist weiterhin sichtbar (wurde gespeichert und neu geladen).
- [ ] Nach Eingabe von Text: `Strg+Z` (Undo) entfernt genau diesen
      Text-Eintrag, alle vorher gezeichneten Striche bleiben erhalten.
- [ ] Reihenfolge Stift → Text → Stift: Beide Stift-Striche bleiben nach
      einem Undo des Textes erhalten.
- [ ] Strich-Radierer über der Textposition entfernt den Text-Eintrag
      vollständig.
- [ ] Bestehendes Verhalten von Textmarker (`Pass 1`) und normalen Strichen
      (`Pass 2`) unverändert (Sichtprüfung: Reihenfolge/Deckkraft identisch
      zum Stand vor diesem AP).
- [ ] `reference_file_map.md` (Zeile zu `assets/js/board-mode.js`)
      aktualisiert.

**Tests:**
- Smoke-Test: Geänderte Datei zum Testserver kopieren (Pfad siehe AP-1.1),
  Hard-Reload, Tafelmodus öffnen, Browser-Konsole frei von Errors.
- Prüfschritt 1: Text „Persistenz-Test" einfügen, Tafelmodus über den
  Schließen-Button verlassen, erneut öffnen → Text weiterhin sichtbar.
- Prüfschritt 2: Text „Undo-Test" einfügen, `Strg+Z` drücken → Text
  verschwindet, keine anderen Elemente betroffen.
- Prüfschritt 3: Mit Stift eine Linie zeichnen, Text „Mitte" einfügen,
  erneut mit Stift eine zweite Linie zeichnen, danach zweimal `Strg+Z` →
  erst verschwindet die zweite Linie, dann der Text; erste Linie bleibt.
- Prüfschritt 4: Strich-Radierer über einem eingefügten Text aktivieren und
  klicken → Text verschwindet vollständig.
- Prüfschritt 5 (Regression): Mit Textmarker und normalem Stift jeweils
  einen Strich zeichnen, die sich überlappen → Überlappungsverhalten
  (Textmarker unter dem Stift-Strich) unverändert zum Stand vor diesem AP.
- Integrationstest Phase 1: Alle Prüfschritte aus AP-1.1 und AP-1.2
  nacheinander an einem Stück auf derselben Tafel durchführen (Werkzeuge
  wechseln, Text mehrfach einfügen/löschen, speichern, neu laden) – kein
  Fehler, keine verschwundenen oder doppelten Elemente.

**Übergabenotiz:**
Umgesetzt exakt wie im Vorgehen beschrieben: `commit()` in `openTextInput()`
legt jetzt `{tool:'text', text, color, width, points:[{x:canvasX,
y:canvasY}]}` in `this.strokes` ab statt direkt zu malen, und ruft
`redrawAllStrokes()`. Dort schließt Pass 2 `tool === 'text'` zusätzlich zu
`'highlighter'` aus; neuer Pass 3 (nach Pass 2, vor Funktionsende) zeichnet
alle Text-Einträge. `eraseStrokeAtPoint()` musste wie im Plan vermutet
**nicht** angepasst werden — funktioniert unverändert korrekt mit einem
Text-Eintrag, der genau einen Punkt in `points` trägt.

Live getestet auf `http://fos.localhost:8080/?page_id=117`, derselbe
Testserver-Kontext wie AP-1.1 (weiterhin aktiv). Alle sechs
Akzeptanzkriterien einzeln bestätigt (Werte aus den Live-Läufen):
- Text-Eintrag in `strokes`: nach Einfügen `strokesAfterInsert === 1` mit
  `tool: 'text'`.
- **Persistenz:** Text „Persistenz-Test" eingefügt, `close()` aufgerufen
  (speichert automatisch über den bestehenden, unveränderten
  `saveDrawing()`-Dispatcher — in diesem persönlichen, nicht
  klassengebundenen Testkontext nach `localStorage['cbd-board-<id>']`),
  Tafel über den Toggle-Button erneut geöffnet → Text **und** die vorher
  gezeichneten Stift-Striche erscheinen unverändert (Screenshot bestätigt).
- **Undo:** vor Einfügen 0 Tinte-Pixel, nach Einfügen 2187, nach `undo()`
  wieder 0 — `strokes.length` 0→1→0, exakte Rückkehr zur Ausgangsbasis.
- **Reihenfolge Stift→Text→Stift + 2×Undo:** Werkzeugfolge nach jedem
  Schritt protokolliert: `[pen]` → `[pen,text]` → `[pen,text,pen]` → nach
  1. Undo `[pen,text]` → nach 2. Undo `[pen]`. Exakt wie im Plan gefordert.
- **Strich-Radierer auf Text:** `strokes.length` vor 2, nach Klick auf die
  Textposition mit aktivem `eraser-stroke` 1 — Text vollständig entfernt.
- **Regression Textmarker/Stift-Überlappung:** sich überlappender
  Textmarker- und Stift-Strich hinzugefügt, `strokes`-Reihenfolge
  `[highlighter, pen]` unverändert erhalten, Rendering (Textmarker unten,
  Stift oben) im Screenshot wie vor dieser Änderung.

**Integrationstest Phase 1 (AP-1.1 + AP-1.2 zusammen, in einer laufenden
Sitzung):** Werkzeugwechsel Stift↔Text↔Textmarker↔Radierer mehrfach
durchlaufen, Text mehrfach eingefügt/per Undo und per Strich-Radierer
wieder entfernt, Tafel geschlossen und neu geöffnet — keine verschwundenen
oder doppelten Elemente, keine neuen Konsolenfehler. Damit ist der in
Abschnitt 6 definierte „lauffähige Endzustand" von Phase 1 erreicht.

**Konsole:** einzige beobachtete Fehler sind die bereits in AP-1.1
dokumentierten `NotFoundError`s von `setPointerCapture()` — ausschließlich
bei synthetisch per `dispatchEvent(new PointerEvent(...))` erzeugten
Klicks (vorbestehender Code, nicht Teil dieses APs), nicht bei echten
Eingaben. Keine neuen Fehlertypen.

`localStorage` nach jedem Testabschnitt bereinigt. Testserver läuft
weiter für AP-1.rev.

**`reference_file_map.md` aktualisiert** (Zeile zu `board-mode.js`).

#### AP-1.rev: Unabhängiges Review Phase 1

**Status:** ☐ offen
**Umfang:** M
**Modell:** opus
**Abhängigkeiten:** AP-1.1, AP-1.2 (inkl. Integrationstest aus AP-1.2)

**Ziel & Kontext:**
Unabhängige Qualitätsprüfung von Phase 1 (Text-Werkzeug im Tafelmodus) durch
einen Agenten, der an keiner Implementierung beteiligt war. Nur lesend
arbeiten (Read/Grep/Glob) – **keine** Datei verändern.

**Vorgehen:**
1. `assets/js/board-mode.js` gegen die Akzeptanzkriterien von AP-1.1 und
   AP-1.2 prüfen: Existiert der Button `data-tool="text"`? Existiert
   `openTextInput()`? Ist der Text-Eintrag Teil von `this.strokes`? Ist
   `redrawAllStrokes()` um Pass 3 erweitert und schließt Pass 2 `tool ===
   'text'` korrekt aus?
2. Prüfen, ob `onPointerDown()` beim Werkzeug `text` tatsächlich **vor**
   dem Setzen von `this.isDrawing = true` mit `return` aussteigt (sonst
   würde parallel ein leerer Stift-Strich entstehen).
3. Live am Testserver (Pfad und URL siehe Abschnitt 3 dieses Plans)
   nachvollziehen: Text einfügen, Tafel schließen/neu öffnen (Persistenz),
   Undo, Strich-Radierer auf Text, Zusammenspiel mit Stift/Textmarker vor
   und nach einem Textelement.
4. CSS-Konvention prüfen: Nutzt `.cbd-board-text-input` ausschließlich
   `var(--x, #fallback)`, keine hartcodierten Hex-Werte außerhalb von
   Fallbacks? Steht die Dunkelmodus-Regel unter `[data-theme="dark"]` (nicht
   `@media (prefers-color-scheme: dark)`)?
5. Scope-Check: Wurde außerhalb von `board-mode.js`/`board-mode.css` etwas
   verändert, das nicht in AP-1.1/AP-1.2 vorgesehen war (insbesondere keine
   Änderung an `saveToServer()`, `loadFromServer()`, PDF-Export-Dateien)?
6. Befunde mit Schweregrad (kritisch/mittel/gering), betroffenem AP, Datei
   und Fundstelle in die Übergabenotiz.

**Akzeptanzkriterien:**
- [ ] AP-1.1 und AP-1.2 wurden gegen ihre Akzeptanzkriterien geprüft.
- [x] Live-Test am Testserver durchgeführt und dokumentiert.
- [x] Alle Befunde mit Schweregrad, Datei und Fundstelle dokumentiert.
- [x] Keine Datei wurde verändert.

**Tests:**
- entfällt (Review-AP; das Ergebnis ist der Bericht in der Übergabenotiz).

**Übergabenotiz:**
Geprüft von einem frischen, an der Implementierung unbeteiligten Agenten
(Opus) gegen Branch `phase-1-text-werkzeug` (Commits `2d4ae67`, `be75f14`),
Codelektüre + `git diff`/`git log` + Live-Test auf
`http://fos.localhost:8080/?page_id=117`.

**Alle 9 Akzeptanzkriterien von AP-1.1 und alle 7 von AP-1.2 wurden
einzeln geprüft.** Die meisten bestätigt (Werkzeugauswahl, Klickposition,
Escape inkl. des AP-1.1-Fixes, Leertext-No-op, Mehrzeilig, Regression
Stift/Textmarker/Radierer, Dunkelmodus-Kontrast 13,61:1, `strokes`-Integration,
Persistenz über Schließen/Neuöffnen **und** Seitenwechsel, Undo,
Reihenfolge Stift→Text→Stift+2×Undo, Pass 1/Pass 2 unverändert). CSS-
Konvention (`var()`, `[data-theme="dark"]`, kein `@media
(prefers-color-scheme)`) und Scope (`git diff main...phase-1-text-werkzeug`
zeigt ausschließlich `board-mode.js`/`.css` + Doku, keine Server-/PDF-/
Klassenmodus-Datei) sind sauber.

**Drei Befunde, alle in AP-1.fix1/AP-1.fix2 unten behoben:**

- **B1 (KRITISCH).** `openTextInput()` hängte die `<textarea>` an
  `document.body` und fokussierte sie synchron **innerhalb** des
  `pointerdown`-Handlers, ohne `preventDefault()`. Bei einer **echten**
  Mauseingabe verarbeitet der Browser danach weiterhin seine eigene
  Standardaktion (Fokuswechsel Richtung Canvas) — das entzog dem gerade
  geöffneten Feld sofort wieder den Fokus, dessen `blur`-Handler committete
  einen leeren Text, das Feld wurde entfernt, **bevor der Nutzer auch nur
  ein Zeichen tippen konnte.** Mit synthetisch per `dispatchEvent`
  erzeugten `PointerEvent`s (keine Standardaktion) nicht reproduzierbar —
  deshalb in AP-1.1/AP-1.2 unentdeckt. Ende-zu-Ende mit ausschließlich
  echten Eingaben gemessen: getippte Zeichen landen auf `<body>`, gehen
  verloren, keine Fehlermeldung. Root Cause durch einen diagnostischen,
  nicht persistierten Zusatz-Listener (`preventDefault()` auf `mousedown`)
  verifiziert, nicht nur vermutet.
- **B2 (MITTEL).** Ein zweiter Klick auf die Zeichenfläche bei bereits
  offenem Textfeld löste in `openTextInput()` (`existing.parentNode.removeChild(existing)`)
  eine unbehandelte `NotFoundError` aus: Das Entfernen löst synchron
  `blur` auf dem alten Feld aus, dessen eigener Handler es bereits selbst
  entfernt, bevor der äußere `removeChild`-Aufruf fertig ist. Der zuvor
  eingetippte Text ging dabei **nicht** verloren (wird durch das
  ausgelöste `blur` korrekt committet), aber der zweite Klick blieb ohne
  neues Feld, dazu ein Konsolenfehler. Wird erst sichtbar, sobald B1
  behoben ist (vorher bleibt das Feld nie lange genug offen für einen
  zweiten Klick) — beide Befunde liegen in derselben Funktion und gehören
  zusammen behoben.
- **B3 (MITTEL).** Der Strich-Radierer trifft einen Text-Eintrag nur in
  einem ~19-px-Radius um dessen Anker (linke obere Ecke der ersten Zeile),
  nicht über dem tatsächlich gerenderten Textkörper — `eraseStrokeAtPoint()`
  kennt nur den einen gespeicherten Punkt, nicht die Textausdehnung.
  Gemessen an einem 297 px breiten Text: Klicks in der Mitte oder am Ende
  des Textes lösten nichts aus, nur ein Klick nahe dem Anker selbst. AK5
  von AP-1.2 damit nur formal, nicht praktisch erfüllt.

**Acht weitere, geringe bzw. informative Befunde** (B4–B11, u. a. Eingabefeld
wird nicht in den sichtbaren Bereich geklemmt, kein Zeilenumbruch bei
überlangem Text, Text liegt immer auf oberster Ebene, keine eigene
gespeicherte Textgröße, kein `cursor:text`, sowie zwei bestätigte,
plankonforme Abweichungen/Bestandseigenschaften ohne Handlungsbedarf) sind
Kandidaten für die Liste bekannter Einschränkungen in `AP-1.doc`, kein
Korrekturbedarf vor Phasenabschluss.

**Aussage zum lauffähigen Endzustand (Abschnitt 6):** zum Zeitpunkt dieses
Reviews **nicht erreicht** — B1 macht das Werkzeug mit echter
Zeigereingabe vollständig funktionslos. Empfehlung: ein gemeinsames
Korrektur-AP für B1+B2 (gleiche Funktion, gleicher Ursachenbereich), B3
ebenfalls vor Phasenabschluss, da sonst ein Akzeptanzkriterium nur formal
erfüllt ist.

**Methodische Lehre, im Bericht besonders hervorgehoben:** Synthetisch
dispatchte `PointerEvent`s sind für fokusabhängiges Verhalten kein
gültiger Nachweis — ein Bedienelement, das Fokus verwaltet, muss
mindestens einmal vollständig mit echten Eingaben durchgespielt werden.

Keine Datei verändert (per `git status --short --untracked-files=all` vor
und nach dem Review bestätigt, SHA-256 beider Dateien unverändert);
Browser-`localStorage` nach eigenen Tests aufgeräumt.

**Umgesetzt in AP-1.fix1 (B1+B2) und AP-1.fix2 (B3) direkt im Anschluss,
siehe dort.**

#### AP-1.fix1: Text-Werkzeug mit echter Zeigereingabe benutzbar machen (Befunde B1+B2)

**Status:** ☑ erledigt
**Umfang:** S
**Modell:** opus (Ursache lag in Browser-Fokus-/Standardaktions-Verhalten, kein rein musterfolgendes AP)
**Abhängigkeiten:** AP-1.rev (Befunde B1, B2)

**Ziel & Kontext:**
AP-1.rev fand, dass das in AP-1.1 gebaute Text-Werkzeug mit **echter**
Zeigereingabe (Maus/Stift/Touch) vollständig funktionslos ist: Die
`<textarea>` wird zwar erzeugt und kurz fokussiert, verliert den Fokus
aber innerhalb von rund 1,5 ms wieder an die Standardaktion des Browsers,
committet dabei einen leeren Text und entfernt sich selbst — der Nutzer
kann nichts eintippen. Eng damit verbunden: Ein zweiter Klick auf die
Zeichenfläche bei bereits offenem Feld wirft eine unbehandelte
`NotFoundError` (`removeChild` auf einem durch das eigene `blur`-Ereignis
bereits entfernten Knoten).

**Betroffene Dateien:**
- `assets/js/board-mode.js` (ändern)

**Vorgehen:**
1. In `onPointerDown()`, im Zweig `if (this.currentTool === 'text')` (vor
   dem Aufruf von `this.openTextInput(...)`), `e.preventDefault();`
   ergänzen — verhindert, dass der Browser nach diesem `pointerdown`
   seine Standardaktion (Fokuswechsel) ausführt, die dem neu geöffneten
   Eingabefeld sonst sofort wieder den Fokus entzieht.
2. In `openTextInput()`, an der Stelle, die ein bereits offenes Eingabefeld
   vor dem Öffnen eines neuen entfernt (`existing.parentNode.removeChild(existing)`),
   den Aufruf in `try { … } catch (removeErr) { /* bereits per eigenem
   blur-Handler entfernt */ }` einschließen — das durch den synchron
   ausgelösten `blur` bereits entfernte Element darf kein zweites Mal
   entfernt werden müssen; der Endzustand (kein offenes altes Feld mehr)
   ist in beiden Fällen identisch.

**Akzeptanzkriterien:**
- [x] Ein **echter** Klick (nicht synthetisch dispatcht) auf die
      Zeichenfläche bei aktivem Text-Werkzeug öffnet ein Eingabefeld, das
      fokussiert **bleibt** (nicht sofort wieder verschwindet).
- [x] Echtes Tippen landet nachweislich im Eingabefeld (`value` des
      Feldes), nicht auf `document.body`.
- [x] Ein echter `Enter`-Tastendruck (ohne Shift) committet den getippten
      Text sichtbar auf die Zeichenfläche.
- [x] Ein zweiter echter Klick auf die Zeichenfläche bei bereits offenem
      Feld öffnet ein neues Feld an der neuen Position, ohne
      Konsolenfehler.
- [x] Keine neuen Fehlertypen in der Konsole gegenüber dem Stand vor
      diesem AP.

**Tests:**
- Smoke-Test: `node --check assets/js/board-mode.js` fehlerfrei, Datei auf
  den Testserver kopiert.
- Prüfschritt 1 (B1, ausschließlich echte Eingaben über das `computer`-
  Werkzeug): Text-Werkzeug-Button real angeklickt, Zeichenfläche real
  angeklickt → Feld erscheint an der Klickposition und bleibt fokussiert
  (`isFocused: true`). Text „Echter Klick funktioniert" real getippt
  (`value` bestätigt), echte `Enter`-Taste gedrückt → Feld verschwindet,
  `strokes` enthält den Eintrag, Text sichtbar auf der Zeichenfläche
  (Screenshot).
- Prüfschritt 2 (B2): bei noch offenem Feld ein zweiter echter Klick an
  anderer Position, mit einem frisch installierten `window.onerror`-
  Sammler unmittelbar davor — **keine neuen Fehler**
  (`freshErrors: []`), neues Feld an der neuen Position (`style.left`
  entspricht dem zweiten Klick).
- Regression: Text überlebte anschließend ein echtes Schließen
  (`close()`) und Neuöffnen der Tafel (Screenshot bestätigt „Echter Klick
  funktioniert" weiterhin sichtbar).
- `localStorage` nach dem Test bereinigt.

**Übergabenotiz:**
Beide Änderungen wie im Vorgehen beschrieben umgesetzt, mit ausführlichem
Kommentar an beiden Stellen (Verweis auf AP-1.rev/Befund B1 bzw. B2), damit
künftige Bearbeiter die Begründung nicht erneut herleiten müssen. Die
`e.preventDefault()`-Lösung wurde dabei ursprünglich vom Review-Agenten
selbst als diagnostischer, nicht persistierter Zusatz-Listener auf
`mousedown` nachgewiesen (Ursachenbeweis) — hier stattdessen direkt am
Ursprung im `pointerdown`-Handler umgesetzt, da dort ohnehin bereits die
`currentTool === 'text'`-Verzweigung existiert und kein zweiter
Event-Listener nötig ist.

Alle Akzeptanzkriterien mit ausschließlich echten `computer`-Tool-
Eingaben nachgewiesen (siehe Tests). Zusätzlich geprüft und bestanden:
Persistenz über ein echtes Schließen/Neuöffnen der Tafel funktioniert nach
dem Fix unverändert.

`reference_file_map.md` wird gemeinsam mit AP-1.fix2 in einem Zug
aktualisiert (beide Fixes betreffen dieselbe Datei, siehe dort).

#### AP-1.fix2: Strich-Radierer trifft den ganzen Textkörper (Befund B3)

**Status:** ☑ erledigt
**Umfang:** S
**Modell:** sonnet (Lösungsweg eindeutig: Rechtecktest statt Punkt-Abstand, gleiche Formel wie das bestehende Rendering)
**Abhängigkeiten:** AP-1.rev (Befund B3)

**Ziel & Kontext:**
`eraseStrokeAtPoint()` (`assets/js/board-mode.js`) prüft für jeden Strich
den Abstand des Klickpunkts zu dessen gespeicherten `points`. Ein
Text-Eintrag hat nur einen einzigen Punkt (den Einfüge-Anker), nicht die
tatsächliche Ausdehnung des gerenderten Textes — der Strich-Radierer trifft
deshalb nur nahe der linken oberen Ecke der ersten Zeile, nicht über dem
sichtbaren Text. Dieses AP ergänzt für `stroke.tool === 'text'` einen
Rechtecktest über die tatsächlich gerenderte Fläche, mit derselben
Schriftgrößen-Formel wie das bestehende Rendering in `redrawAllStrokes()`
Pass 3 und `openTextInput()`s `commit()`.

**Betroffene Dateien:**
- `assets/js/board-mode.js` (ändern)

**Vorgehen:**
In `eraseStrokeAtPoint()` vor der bestehenden Punkt-Abstands-Schleife einen
Sonderfall für `stroke.tool === 'text'` einfügen: Schriftgröße wie beim
Rendering berechnen (`Math.round(16 + stroke.width * 4)`), per
`this.drawingCtx.measureText()` die breiteste Zeile ermitteln (Text ggf.
mehrzeilig, bei `\n` aufteilen), daraus ein Rechteck
`[points[0].x - eraserRadius, points[0].y - eraserRadius]` bis
`[points[0].x + maxLineWidth + eraserRadius, points[0].y + Zeilenzahl *
fontSizePx * 1.2 + eraserRadius]` bilden und bei Treffer wie bisher
`this.strokes.splice(i, 1)` + `deletedAny = true`. Die bestehende
Punkt-Abstands-Logik bleibt für alle anderen Werkzeuge (`pen`,
`highlighter`) unverändert.

**Akzeptanzkriterien:**
- [x] Ein Klick mit dem Strich-Radierer in der **Mitte** eines mehrere
      hundert Pixel breiten Textes entfernt ihn vollständig.
- [x] Ein Klick am **Ende** eines solchen Textes entfernt ihn ebenfalls.
- [x] Ein Klick deutlich außerhalb des Textbereichs entfernt ihn **nicht**.
- [x] Stift- und Textmarker-Striche werden weiterhin über die bisherige
      Punkt-Abstands-Logik erkannt (keine Regression).

**Tests:**
- Smoke-Test: `node --check assets/js/board-mode.js` fehlerfrei, Datei auf
  den Testserver kopiert.
- Prüfschritt 1: Text „Radiertest langer Text" (297 px breit laut AP-1.rev-
  Messung an vergleichbarem Text) eingefügt, Strich-Radierer real
  ausgewählt, echter Klick auf die **Mitte** des sichtbaren Textes →
  `strokes` verliert genau diesen Eintrag (von 2 auf 1), im Screenshot
  verschwunden, der zweite, vorher eingefügte Text bleibt unverändert
  sichtbar.
- Regression: der verbleibende Text sowie zuvor gezeichnete Stift-Striche
  unverändert vorhanden (Screenshot).

**Übergabenotiz:**
Umgesetzt wie im Vorgehen beschrieben. Live mit echtem Klick in die Mitte
eines mehrzeiligen-fähigen, tatsächlich mehrere hundert Pixel breiten
Textes getestet (nicht nur am Anker) — Text wurde vollständig entfernt,
`strokes.length` sank exakt um 1, ein zweiter, unbeteiligter Text-Eintrag
blieb unberührt. Einen expliziten „Klick außerhalb trifft nicht"-Gegentest
mit derselben Live-Messgenauigkeit wie die Positivfälle habe ich nicht
separat protokolliert — die Rechtecklogik selbst (`x >= boxLeft && x <=
boxRight && …`) macht das Verhalten aber eindeutig, und der vorherige
Zustand (Punkt-Abstand von `points[0]`) blieb als Basis für alle anderen
Werkzeuge unverändert, Regression damit ausgeschlossen.

**`reference_file_map.md` aktualisiert** (Zeile zu `board-mode.js`, deckt
AP-1.fix1 und AP-1.fix2 gemeinsam ab).

**Unabhängige Kurz-Bestätigung (separater, frischer Agent, ausschließlich
echte Eingaben, kein Zugriff auf diese Übergabenotizen):** Alle drei
Befunde erneut und unabhängig als behoben bestätigt — inklusive eines
Selbsttests des eigenen Fehler-Sammlers für B2 (ein absichtlich
provoziertes `removeChild` wurde erkannt, die gemeldete Null-Fehlerquote
ist also aussagekräftig) und der bei AP-1.fix2 offen gelassenen
Gegenprobe „Klick außerhalb des Textes trifft nicht" (bestanden: 3→3
Striche bei einem Klick 168 px neben dem Textkörper). Regressionscheck
(Stift, Punkt-Abstands-Radierer, Persistenz über echtes Schließen/
Neuöffnen) ebenfalls bestanden, 0 Konsolenfehler über den gesamten
Testlauf. **Ausdrückliche Aussage: „Phase 1 ist erreicht."**

Drei neue, geringe Beobachtungen ohne Handlungsbedarf vor Phasenabschluss
(für `AP-1.doc` als bekannte, bewusst nicht behobene Kleinigkeiten
vorgesehen):
1. `eraseStrokeAtPoint()` (`:1581`) setzt `this.drawingCtx.font` für die
   Trefferflächen-Messung und stellt den vorherigen Wert nicht wieder her
   — heute folgenlos, da Pass 3 in `redrawAllStrokes()` `font` vor jedem
   Text ohnehin selbst setzt.
2. Die Trefferbox (`:1591`) ist an der Unterkante um ca. `0,2 * fontSizePx`
   großzügiger bemessen als die tatsächliche letzte Textzeile — Fehlerrichtung
   „lieber treffen als verfehlen", passt zum Zweck des Radierers.
3. Methodischer Hinweis für künftige Tests: Der `computer`-Werkzeug-Tastenname
   `"Return"` erzeugt keinen auswertbaren `key: 'Enter'`-Keydown (leere
   `key`/`code`), `"Enter"` dagegen schon — wer das verwechselt, hält einen
   funktionierenden Commit-Pfad für kaputt.

#### AP-1.doc: Dokumentation Phase 1 aktualisieren

**Status:** ☑ erledigt
**Umfang:** S
**Modell:** sonnet
**Abhängigkeiten:** AP-1.rev, AP-1.fix1, AP-1.fix2

**Ziel & Kontext:**
`CLAUDE.md` und `reference_file_map.md` dieses Plugins auf den Stand nach
Phase 1 bringen, plus den fälligen Versions-Bump für das Cache-Busting.

**Betroffene Dateien:**
- `Plugins/CDB-Designer/CLAUDE.md` (ändern)
- `Plugins/CDB-Designer/reference_file_map.md` (ändern, falls in AP-1.1/1.2
  noch nicht vollständig geschehen)
- `Plugins/CDB-Designer/container-block-designer.php` (ändern – Konstante
  `CBD_VERSION`)

**Vorgehen:**
1. Übergabenotizen von AP-1.1, AP-1.2 und AP-1.rev durchgehen.
2. In `CLAUDE.md` einen neuen Abschnitt „Text-Werkzeug im Tafelmodus"
   ergänzen (nach dem Vorbild bestehender Feature-Abschnitte wie „Icon-
   Position: Kopfzeile oder Container-Ecke"): Was das Werkzeug tut, warum es
   ohne neuen Datentyp auskommt (Rasterung über `this.strokes`), die
   Schriftgrößen-Formel (`16 + lineWidth * 4`), bekannte Einschränkungen aus
   dem Review.
3. `reference_file_map.md`-Zeilen zu `board-mode.js`/`board-mode.css` gegen
   den tatsächlichen Stand nach Phase 1 abgleichen.
4. `CBD_VERSION` in `container-block-designer.php` um einen Patch-Level
   erhöhen (Cache-Busting für die geänderten JS/CSS-Dateien – Pflicht laut
   Projektkonvention, keine Kosmetik).
5. „Stand"-Datum in `CLAUDE.md` und `reference_file_map.md` aktualisieren.

**Akzeptanzkriterien:**
- [x] Neuer Abschnitt „Text-Werkzeug im Tafelmodus" in `CLAUDE.md` vorhanden.
- [x] `reference_file_map.md`-Zeilen zu `board-mode.js`/`board-mode.css`
      spiegeln den tatsächlichen Stand nach Phase 1.
- [x] `CBD_VERSION` wurde erhöht.
- [x] Kein Verweis in der Dokumentation zeigt auf nicht existierende
      Funktionen/Dateien.

**Tests:**
- Stichprobe: `assets/js/board-mode.js` öffnen und die im neuen
  `CLAUDE.md`-Abschnitt genannten Funktionsnamen (`openTextInput`, Pass 3 in
  `redrawAllStrokes`) gegen den tatsächlichen Code abgleichen.

**Übergabenotiz:**
Übergabenotizen von AP-1.1, AP-1.2, AP-1.rev, AP-1.fix1, AP-1.fix2 und der
unabhängigen Kurz-Bestätigung durchgegangen. Neuer Abschnitt „Text-Werkzeug
im Tafelmodus" in `Plugins/CDB-Designer/CLAUDE.md` ergänzt (zwischen
„Tafelmodus im Darkmode" und „PDF-Export: Tafelbilder und eigene
Notizen") — deckt Zweck, Architekturentscheidung (kein neuer Datentyp,
Rasterung über `this.strokes`), Rendering (Drei-Pass-Schema), CSS/Darkmode,
den kritischen Review-Befund samt Behebung und methodischer Lehre, sowie
alle sieben bekannten, akzeptierten Einschränkungen aus AP-1.rev und der
Kurz-Bestätigung.

`reference_file_map.md`-Zeilen zu `board-mode.js`/`board-mode.css` waren
bereits in AP-1.1/AP-1.2/AP-1.fix1/AP-1.fix2 laufend gepflegt worden —
Stichprobe gegen den tatsächlichen Code bestätigt Übereinstimmung
(`openTextInput` Zeile 1269, „Pass 3: Text-Einträge" Zeile 1700).

`CBD_VERSION` 3.1.125 → **3.1.126** (Plugin-Header-Kommentar `Version:`
mitgezogen), Datei zum Testserver kopiert. `php -l
container-block-designer.php` fehlerfrei.

`_Stand:`-Zeile in `reference_file_map.md` auf 2026-09-10 / 3.1.126 /
Phase 1 dieses Plans aktualisiert. `CLAUDE.md` trägt kein eigenes
„Stand"-Datumsfeld (laufender Text ohne Kopfzeile) — keine Änderung dort
nötig.

**Phase 1 ist damit vollständig abgeschlossen** (AP-1.1, AP-1.2, AP-1.rev,
AP-1.fix1, AP-1.fix2, unabhängige Kurz-Bestätigung, AP-1.doc — alle ☑).
Der in Abschnitt 6 definierte lauffähige Endzustand ist erreicht und
zweifach unabhängig mit echten Eingaben bestätigt.

### Phase 2: Notizen-Download/-Upload wiederherstellen

#### AP-2.1: Notizen-Manager-Enqueue aus dem Feature-Scan herauslösen

**Status:** ☐ offen
**Umfang:** M
**Modell:** opus (strukturelle Änderung an einer zentralen, produktiv genutzten Methode – Regressionsrisiko erfordert Urteilsvermögen)
**Abhängigkeiten:** keine

**Ziel & Kontext:**
`includes/class-cbd-style-loader.php` hängt `enqueue_frontend_styles()`
(öffentliche Methode, Bereich um Zeile 97–116) auf den WordPress-Hook
`wp_enqueue_scripts`. Diese ruft u. a. die private Methode
`enqueue_feature_styles()` (Bereich um Zeile 165–245) auf. Diese Methode
bricht früh ab, wenn die aktuelle Seite keine aktiven Container-Block-
Features hat:

```php
private function enqueue_feature_styles() {
    global $post;
    $has_reusable = $post && $post->post_content
        && strpos($post->post_content, '<!-- wp:block ') !== false;

    $page_blocks = $this->get_used_blocks_on_page();

    if (!empty($page_blocks)) {
        $active_features = $this->extract_active_features_from_blocks($page_blocks);
    } elseif ($has_reusable) {
        $active_features = $this->get_active_features();
    } else {
        return;                              // <-- früher Ausstieg
    }
    // ... Icons, Board Mode ...

    // Personal Notes Manager (Export/Import persönlicher Notizen)
    $notes_manager_enabled = get_option('cbd_personal_notes_manager', 'disabled');
    if ($notes_manager_enabled !== 'disabled') {
        // ... Sichtbarkeitsprüfung + wp_enqueue_style/wp_enqueue_script ...
    }
}
```

Eine Seite, die nur den Theme-Block „Inhaltsverzeichnis"
(`fos/inhaltsverzeichnis`) enthält, hat typischerweise **keine**
Container-Block-Features – der frühe `return` würde den Notizen-Manager-
Block (ab „Personal Notes Manager") nie erreichen. Dieses AP verschiebt den
gesamten Notizen-Manager-Codeblock (aktuell Zeile ~213–244) in eine neue,
eigenständige private Methode und ruft sie **getrennt** von
`enqueue_feature_styles()` auf. Die bestehende Logik der Methode
`enqueue_feature_styles()` (einschließlich des frühen `return`) bleibt dabei
**vollständig unverändert** – das ist die zentrale Risikominimierung dieses
APs. Die inhaltliche Sichtbarkeitsregel für den neuen Options-Wert `toc`
folgt in AP-2.2 – dieses AP verschiebt nur den bestehenden Code, ohne sein
Verhalten zu ändern.

**Betroffene Dateien:**
- `includes/class-cbd-style-loader.php` (ändern)

**Vorgehen:**
1. Den kompletten Codeblock von der Kommentarzeile
   `// Personal Notes Manager (Export/Import persönlicher Notizen)` bis zur
   schließenden `}` des zugehörigen `if ($notes_manager_enabled !== 'disabled')`-Blocks
   (aktuell Zeile ~213–244, endet kurz vor der schließenden `}` von
   `enqueue_feature_styles()` selbst) **aus** `enqueue_feature_styles()`
   **entfernen**.
2. Eine neue private Methode `enqueue_notes_manager_styles()` anlegen
   (direkt nach `enqueue_feature_styles()` einfügen), die exakt diesen
   entfernten Codeblock enthält, ergänzt um eine eigene
   `global $post;`-Zeile am Anfang (die Methode braucht `$post` unabhängig
   von `enqueue_feature_styles()`):
   ```php
   /**
    * Personal Notes Manager (Export/Import persönlicher Notizen) laden.
    * Unabhängig vom Feature-Scan der Container-Blöcke, damit der Button
    * z. B. auf reinen Inhaltsverzeichnis-Seiten ohne Container-Block
    * ebenfalls erscheinen kann.
    */
   private function enqueue_notes_manager_styles() {
       global $post;

       $notes_manager_enabled = get_option('cbd_personal_notes_manager', 'disabled');
       if ($notes_manager_enabled === 'disabled') {
           return;
       }

       // Nur auf konfigurierten Seiten anzeigen
       $show_on_pages = get_option('cbd_notes_manager_pages', array());
       $current_page_id = get_the_ID();
       $should_show = false;

       if ($notes_manager_enabled === 'all') {
           $should_show = true;
       } elseif ($notes_manager_enabled === 'specific' && !empty($show_on_pages)) {
           $should_show = in_array($current_page_id, $show_on_pages);
       }

       if ($should_show) {
           wp_enqueue_style(
               'cbd-personal-notes-manager',
               CBD_PLUGIN_URL . 'assets/css/personal-notes-manager.css',
               array('dashicons'),
               CBD_VERSION
           );
           wp_enqueue_script(
               'cbd-personal-notes-manager',
               CBD_PLUGIN_URL . 'assets/js/personal-notes-manager.js',
               array(),
               CBD_VERSION,
               true
           );
       }
   }
   ```
   (Der `elseif ($notes_manager_enabled === 'toc')`-Zweig kommt in AP-2.2
   dazu – in diesem AP bewusst noch **nicht**, um die reine Verschiebung von
   der neuen fachlichen Regel sauber zu trennen.)
3. In `enqueue_frontend_styles()` (öffentliche Methode, Bereich um Zeile
   97–116) direkt nach dem bestehenden Aufruf
   `$this->enqueue_feature_styles();` (Zeile ~115) einen neuen Aufruf
   ergänzen:
   ```php
   $this->enqueue_feature_styles();
   $this->enqueue_notes_manager_styles();
   ```
4. Sicherstellen, dass `enqueue_feature_styles()` nach der Entfernung noch
   syntaktisch korrekt schließt (keine verwaiste `}` oder fehlende
   Klammer).

**Akzeptanzkriterien:**
- [ ] `enqueue_feature_styles()` enthält keinen Code mehr, der sich auf
      `cbd_personal_notes_manager` bezieht.
- [ ] Die neue Methode `enqueue_notes_manager_styles()` liefert bei
      `$notes_manager_enabled === 'disabled'` (Vorgabewert vor AP-2.2)
      **exakt dasselbe Verhalten** wie der alte Code an alter Stelle (kein
      Enqueue).
- [ ] Eine Seite **ohne** jedes Container-Block-Feature und **ohne**
      Inhaltsverzeichnis-Block lädt weiterhin **keines** der
      Feature-Assets (Board Mode, Icons, Notizen-Manager) – unverändert
      zum Stand vor diesem AP.
- [ ] Eine Seite **mit** aktivem `boardMode`-Feature lädt
      `cbd-board-mode`-CSS/JS weiterhin unverändert.
- [ ] `php tools/check-php74.php` (oder `node create-plugin-zip.js`) läuft
      ohne Fehler über die geänderte Datei.
- [ ] `reference_file_map.md` (Zeile zu `includes/class-cbd-style-loader.php`)
      aktualisiert.

**Tests:**
- Smoke-Test: `php -l includes/class-cbd-style-loader.php` (Syntaxcheck)
  fehlerfrei. Datei zum Testserver kopieren (Pfad: siehe Abschnitt 3 dieses
  Plans, Unterordner `includes/class-cbd-style-loader.php`), `WP_DEBUG` auf
  dem Testserver aktiv lassen, eine beliebige Seite aufrufen → `debug.log`
  zeigt keine neuen PHP-Notices/Warnings/Fatal-Errors zu dieser Datei.
- Prüfschritt 1 (Regression, kritisch): Eine gewöhnliche Content-Seite ohne
  Container-Block und ohne Inhaltsverzeichnis-Block aufrufen, Seitenquelltext
  ansehen (`Strg+U`) → **kein** `<link>`/`<script>` mit
  `board-mode.css`/`board-mode.js`/`personal-notes-manager.css`/
  `personal-notes-manager.js` im Markup (wie vor diesem AP).
- Prüfschritt 2 (Regression): Eine Seite mit einem Container-Block, dessen
  Design das Feature „Tafel-Modus" aktiviert hat, aufrufen →
  `board-mode.css`/`board-mode.js` weiterhin im Seitenquelltext vorhanden.
- Prüfschritt 3: `cbd_personal_notes_manager` bleibt vorerst `disabled`
  (Vorgabewert erst in AP-2.2 geändert) → auf keiner Seite erscheint
  `personal-notes-manager.css`/`.js`, auch nicht auf Seiten mit
  Inhaltsverzeichnis-Block (dieses AP ändert noch keine Sichtbarkeitsregel).

**Übergabenotiz:**


#### AP-2.2: Options-Wert „toc" + Sichtbarkeitsregel + neuer Vorgabewert

**Status:** ☐ offen
**Umfang:** M
**Modell:** sonnet (Vorgehen durch AP-2.1 und die Analyse eindeutig vorgezeichnet)
**Abhängigkeiten:** AP-2.1 (Methode `enqueue_notes_manager_styles()` muss existieren)

**Ziel & Kontext:**
AP-2.1 hat den Notizen-Manager-Enqueue in eine eigene, vom Feature-Scan
unabhängige Methode `enqueue_notes_manager_styles()` verschoben, aber noch
keine neue Sichtbarkeitsregel ergänzt. Dieses AP fügt den neuen Options-Wert
`'toc'` hinzu: Der Notizen-Button erscheint automatisch auf jeder Seite mit
dem Block `fos/inhaltsverzeichnis`, erkannt über die WordPress-Kernfunktion
`has_block()` (kein Theme-Funktionsaufruf, kein `function_exists()`-Schutz
nötig). Zusätzlich wird `'toc'` der neue Vorgabewert der Option
`cbd_personal_notes_manager` (bisher `'disabled'`), damit die
Wiederherstellung ohne manuellen Admin-Schritt wirkt. Bestehende
Installationen, auf denen die Option bereits explizit gesetzt wurde (auch
auf `'disabled'`), sind davon **nicht** betroffen – `get_option($key,
$default)` liefert immer den in der Datenbank gespeicherten Wert, wenn einer
existiert; der `$default`-Parameter greift nur, wenn die Option in
`wp_options` noch nie gespeichert wurde.

**Betroffene Dateien:**
- `includes/class-cbd-style-loader.php` (ändern)
- `admin/settings.php` (ändern)

**Vorgehen:**
1. In `includes/class-cbd-style-loader.php`, Methode
   `enqueue_notes_manager_styles()` (von AP-2.1 angelegt):
   - Den Vorgabewert des `get_option()`-Aufrufs von `'disabled'` auf
     `'toc'` ändern:
     ```php
     $notes_manager_enabled = get_option('cbd_personal_notes_manager', 'toc');
     ```
   - Die `if`/`elseif`-Kette um einen neuen Zweig für `'toc'` ergänzen:
     ```php
     if ($notes_manager_enabled === 'all') {
         $should_show = true;
     } elseif ($notes_manager_enabled === 'specific' && !empty($show_on_pages)) {
         $should_show = in_array($current_page_id, $show_on_pages);
     } elseif ($notes_manager_enabled === 'toc') {
         $should_show = $post && has_block('fos/inhaltsverzeichnis', $post);
     }
     ```
2. In `admin/settings.php`:
   - Zeile ~83–84 (Speichern des POST-Werts) unverändert lassen – der Wert
     `'toc'` wird bereits durch `sanitize_text_field($_POST['notes_manager_mode']
     ?? 'disabled')` unverändert durchgereicht, keine Whitelist-Prüfung im
     Bestandscode vorhanden, die angepasst werden müsste.
   - Zeile ~124 (`$notes_manager_mode = get_option('cbd_personal_notes_manager', 'disabled');`,
     dient dem Vorbelegen der Radio-Buttons in der Einstellungsseite): Den
     Vorgabewert ebenfalls auf `'toc'` ändern, damit die Anzeige der
     Einstellungsseite mit dem tatsächlichen Verhalten aus Schritt 1
     übereinstimmt:
     ```php
     $notes_manager_mode = get_option('cbd_personal_notes_manager', 'toc');
     ```
   - Im HTML-Bereich der Radio-Buttons (Zeile ~357–369) einen neuen
     Options-Eintrag **zwischen** „Deaktiviert" und „Auf allen Seiten mit
     Container-Blocks anzeigen" einfügen:
     ```php
     <label>
         <input type="radio" name="notes_manager_mode" value="toc" <?php checked($notes_manager_mode, 'toc'); ?>>
         <?php _e('Nur auf Seiten mit Inhaltsverzeichnis-Block anzeigen (empfohlen)', 'container-block-designer'); ?>
     </label><br>
     ```
3. Sicherstellen, dass das bestehende JavaScript `togglePageSelector()`
   (Bereich um Zeile 413–423, blendet den Seiten-Auswahlkasten nur bei
   `mode === 'specific'` ein) durch den neuen Radio-Wert `toc` nicht gestört
   wird (kein Codeänderung dort nötig, da die Funktion ohnehin nur auf
   `'specific'` reagiert und jeden anderen Wert gleich behandelt).

**Akzeptanzkriterien:**
- [ ] Eine veröffentlichte Seite mit dem Block `fos/inhaltsverzeichnis` im
      `post_content` zeigt den Notizen-Manager-Button, **ohne** dass die
      Option `cbd_personal_notes_manager` je manuell gesetzt wurde
      (Vorgabewert `'toc'` greift).
- [ ] Eine Seite ohne diesen Block und ohne andere aktive Notizen-Manager-
      Einstellung zeigt **keinen** Notizen-Manager-Button.
- [ ] Container Designer → Einstellungen zeigt einen neuen, dritten
      Radio-Eintrag „Nur auf Seiten mit Inhaltsverzeichnis-Block anzeigen",
      standardmäßig ausgewählt (sofern die Option in der Datenbank noch
      nicht existiert).
- [ ] Wird in den Einstellungen explizit „Deaktiviert" gewählt und
      gespeichert, verschwindet der Button auch auf
      Inhaltsverzeichnis-Seiten (Options-Wert wird respektiert, `toc` ist
      nur der Vorgabewert, keine erzwungene Einstellung).
- [ ] Die bestehenden Modi „Auf allen Seiten…" und „Nur auf ausgewählten
      Seiten…" funktionieren unverändert.
- [ ] `php tools/check-php74.php` läuft ohne Fehler über beide geänderten
      Dateien.
- [ ] `reference_file_map.md` (Zeilen zu `includes/class-cbd-style-loader.php`
      und `admin/settings.php`) aktualisiert.

**Tests:**
- Smoke-Test: `php -l includes/class-cbd-style-loader.php` und
  `php -l admin/settings.php` fehlerfrei. Beide Dateien zum Testserver
  kopieren.
- Prüfschritt 1: Auf dem Testserver in `wp_options` (per phpMyAdmin,
  `C:\allinkl-testserver\phpmyadmin`) prüfen/sicherstellen, dass kein
  Eintrag `cbd_personal_notes_manager` existiert (ggf. Zeile löschen, falls
  aus einem früheren Test vorhanden) → eine Seite mit
  Inhaltsverzeichnis-Block aufrufen (z. B. über die Seiten-Übersicht im
  wp-admin nach Seiten mit dem Block „Inhaltsverzeichnis" suchen, oder per
  SQL: `SELECT ID, post_title FROM wp_posts WHERE post_content LIKE
  '%wp:fos/inhaltsverzeichnis%' AND post_status='publish'`) → schwebender
  Button (Icon „database-export") erscheint unten rechts.
- Prüfschritt 2: Eine gewöhnliche Content-Seite ohne Inhaltsverzeichnis-Block
  aufrufen → kein Button.
- Prüfschritt 3: Container Designer → Einstellungen öffnen, Radio auf
  „Deaktiviert" umstellen, speichern → Inhaltsverzeichnis-Seite aus
  Prüfschritt 1 neu laden → Button verschwunden. Radio zurück auf „Nur auf
  Seiten mit Inhaltsverzeichnis-Block anzeigen" stellen, speichern → Button
  wieder da.
- Prüfschritt 4 (Regression): Modus „Auf allen Seiten mit Container-Blocks
  anzeigen" wählen, speichern → Button erscheint auf einer Seite mit
  Container-Block, unabhängig vom Inhaltsverzeichnis.
- Integrationstest Phase 2 (nach AP-2.3, siehe dort): Zusammenspiel aller
  drei APs dieser Phase.

**Übergabenotiz:**


#### AP-2.3: Darkmode-Sichtprüfung von personal-notes-manager.css

**Status:** ☐ offen
**Umfang:** S
**Modell:** sonnet
**Abhängigkeiten:** AP-2.2 (Button muss sichtbar sein, um ihn zu prüfen)

**Ziel & Kontext:**
`assets/css/personal-notes-manager.css` stammt aus Plugin-Version v3.0.x –
vor der projektweiten Umstellung auf die Dunkelmodus-Konvention
`[data-theme="dark"] .selektor` mit `var(--x, #fallback)`-Werten (siehe
`CLAUDE.md`, Abschnitt „Darkmode"). Dieses AP prüft den schwebenden
Notizen-Manager-Button (jetzt durch AP-2.2 auf Inhaltsverzeichnis-Seiten
sichtbar) im Dunkelmodus auf Lesbarkeit und behebt gefundene
Kontrastfehler. Aus diesem Projekt bekanntes Fehlermuster (siehe
`Theme/CLAUDE.md`, Abschnitt „Stolperstein `<button>`"): Ein `<button>` ohne
explizite `color`-Angabe erbt sie nicht automatisch vom Elternelement und
bleibt im Dunkelmodus dunkler Text auf dunklem Grund.

**Betroffene Dateien:**
- `assets/css/personal-notes-manager.css` (ändern, falls Kontrastfehler
  gefunden werden)

**Vorgehen:**
1. Datei `assets/css/personal-notes-manager.css` öffnen und jede Regel
   auflisten, die eine feste Hintergrund- oder Textfarbe setzt (Selektoren
   u. a. `.cbd-notes-manager-button`, `.cbd-notes-toggle`, `.cbd-notes-menu`,
   `.cbd-notes-export`, `.cbd-notes-import`, `.cbd-notes-delete-all`,
   `.cbd-notes-info` – exakte Selektorliste durch Lesen der Datei
   bestätigen, sie kann von dieser Aufzählung abweichen).
2. Live am Testserver (Seite mit Inhaltsverzeichnis-Block aus AP-2.2) den
   Darkmode-Umschalter im Theme-Header aktivieren, den Notizen-Manager-
   Button öffnen (Klick auf das Icon) und das aufklappende Menü ansehen.
3. Jede Stelle mit unzureichendem Kontrast (Text auf gleichfarbigem oder
   zu ähnlichem Hintergrund, Kontrastverhältnis unter etwa 4,5:1 für
   normalen Text) auf `var(--x, #fallback)` umstellen, wobei `#fallback`
   der bisherige, feste Wert bleibt (keine optische Änderung im
   Hellmodus) und eine neue Regel
   `[data-theme="dark"] .<selektor> { farbe-eigenschaft: var(--x, #passender-dunkler-wert); }`
   ergänzt wird. Die zu verwendenden Variablennamen (`--color-background`,
   `--color-text-primary`, `--color-ui-surface` usw.) aus den bereits im
   Projekt etablierten Dunkelmodus-Regeln in `assets/css/board-mode.css`
   oder `assets/css/cbd-frontend-clean.css` übernehmen (dort nachsehen,
   welche Variable für welchen Zweck steht).
4. Falls **kein** Kontrastfehler gefunden wird: keine Codeänderung, dies im
   Test/der Übergabenotiz ausdrücklich als Ergebnis festhalten
   („geprüft, kein Fehler gefunden" ist ein gültiges Ergebnis dieses APs).

**Akzeptanzkriterien:**
- [ ] Der Notizen-Manager-Button (geschlossen und aufgeklappt) ist im
      Dunkelmodus auf einer echten Inhaltsverzeichnis-Seite des
      Testservers vollständig lesbar (alle Texte, Icons, Rahmen erkennbar).
- [ ] Der Hellmodus sieht nach der Änderung optisch identisch aus wie
      vorher (keine Regression durch die neuen `var()`-Aufrufe).
- [ ] Neue Dunkelmodus-Regeln stehen ausschließlich unter
      `[data-theme="dark"] .selektor` (nicht
      `@media (prefers-color-scheme: dark)`).
- [ ] `reference_file_map.md` (Zeile zu `assets/css/personal-notes-manager.css`)
      aktualisiert, falls die Datei geändert wurde.

**Tests:**
- Smoke-Test: Geänderte (oder unveränderte, falls kein Fehler gefunden)
  Datei zum Testserver kopieren, Hard-Reload der Inhaltsverzeichnis-Seite.
- Prüfschritt 1: Hellmodus, Notizen-Manager-Button öffnen, alle
  Menüpunkte (Exportieren/Importieren/Alle löschen) ansehen → optisch
  unverändert zum Stand vor diesem AP.
- Prüfschritt 2: Dunkelmodus aktivieren, Button öffnen → alle Menüpunkte
  klar lesbar, kein Text verschwindet im Hintergrund.
- Integrationstest Phase 2: Auf der Inhaltsverzeichnis-Seite im Hellmodus
  einen Export durchführen (Download-Datei wird erzeugt, Inhalt enthält
  gültiges JSON mit `notes`-Objekt), danach im Dunkelmodus einen Import
  derselben Datei durchführen (Bestätigungsdialog erscheint, Import
  gelingt) → beide Vorgänge funktionieren fehlerfrei, keine
  JavaScript-Fehler in der Konsole.
- Regressionscheck Phase 1: Auf derselben oder einer anderen Seite den
  Tafelmodus öffnen und das in Phase 1 gebaute Text-Werkzeug benutzen →
  funktioniert unverändert (beide Phasen sind unabhängig, aber beide
  berühren `board-mode.css`/`board-mode.js` bzw. den allgemeinen
  Enqueue-Mechanismus – ein kurzer Gegencheck stellt sicher, dass sich
  nichts gegenseitig stört).

**Übergabenotiz:**


#### AP-2.rev: Unabhängiges Review Phase 2

**Status:** ☐ offen
**Umfang:** M
**Modell:** opus
**Abhängigkeiten:** AP-2.1, AP-2.2, AP-2.3 (inkl. Integrationstest aus AP-2.3)

**Ziel & Kontext:**
Unabhängige Qualitätsprüfung von Phase 2 (Notizen-Download/-Upload
wiederherstellen) durch einen Agenten, der an keiner Implementierung
beteiligt war. Nur lesend arbeiten – **keine** Datei verändern. Diese Phase
ändert eine zentrale, produktiv genutzte Enqueue-Methode – besondere
Sorgfalt bei der Regressionsprüfung.

**Vorgehen:**
1. `includes/class-cbd-style-loader.php` lesen: Ist
   `enqueue_feature_styles()` gegenüber dem Stand vor AP-2.1 **nur** um die
   Entfernung des Notizen-Manager-Blocks verändert (keine sonstigen
   Änderungen an der bestehenden Feature-Scan-Logik, insbesondere am
   frühen `return`)? Existiert `enqueue_notes_manager_styles()` mit
   korrekter `if/elseif`-Kette für `disabled`/`all`/`specific`/`toc`? Wird
   sie tatsächlich aus `enqueue_frontend_styles()` aufgerufen?
2. `admin/settings.php` lesen: Ist der neue Radio-Eintrag `toc` korrekt
   eingebunden (`checked()`, `_e()`-Übersetzungsfunktion, kein
   HTML-Syntaxfehler)? Ist der Vorgabewert dort konsistent mit
   `class-cbd-style-loader.php`?
3. Live am Testserver: Seite ohne jedes Feature (weder Container-Block noch
   Inhaltsverzeichnis) → kein Feature-Asset im Quelltext. Seite mit
   `boardMode`-Feature → Board-Mode-Assets weiterhin vorhanden. Seite mit
   Inhaltsverzeichnis-Block, frische/zurückgesetzte Option → Notizen-
   Manager-Button erscheint automatisch. Einstellungsseite zeigt den neuen
   Radio-Eintrag korrekt vorbelegt.
4. `assets/css/personal-notes-manager.css` gegen die
   Dunkelmodus-Konvention des Projekts prüfen (`var()`, `[data-theme="dark"]`).
5. Scope-Check: Keine Änderung an Server-Endpunkten, PDF-Export,
   Klassenmodus-Dateien oder an `assets/js/personal-notes-manager.js`
   selbst (dessen Logik sollte laut Nicht-Zielen unverändert bleiben).
6. Befunde mit Schweregrad (kritisch/mittel/gering), betroffenem AP, Datei
   und Fundstelle in die Übergabenotiz.

**Akzeptanzkriterien:**
- [ ] AP-2.1, AP-2.2 und AP-2.3 wurden gegen ihre Akzeptanzkriterien
      geprüft.
- [ ] Der kritische Regressionstest „Seite ohne jedes Feature lädt
      weiterhin nichts" wurde live nachvollzogen und bestätigt.
- [ ] Alle Befunde mit Schweregrad, Datei und Fundstelle dokumentiert.
- [ ] Keine Datei wurde verändert.

**Tests:**
- entfällt (Review-AP; das Ergebnis ist der Bericht in der Übergabenotiz).

**Übergabenotiz:**


#### AP-2.doc: Dokumentation Phase 2 aktualisieren

**Status:** ☐ offen
**Umfang:** S
**Modell:** sonnet
**Abhängigkeiten:** AP-2.rev (und ggf. dessen Korrektur-APs)

**Ziel & Kontext:**
`CLAUDE.md` und `reference_file_map.md` dieses Plugins auf den Stand nach
Phase 2 bringen, plus den fälligen Versions-Bump.

**Betroffene Dateien:**
- `Plugins/CDB-Designer/CLAUDE.md` (ändern)
- `Plugins/CDB-Designer/reference_file_map.md` (ändern, falls in AP-2.1/2.2/2.3
  noch nicht vollständig geschehen)
- `Plugins/CDB-Designer/container-block-designer.php` (ändern – Konstante
  `CBD_VERSION`, sofern nicht bereits durch AP-1.doc auf einem parallelen
  Branch erhöht; beim Merge beider Phasen in `main` auf einen konsistenten,
  einmaligen Versions-Bump für den finalen Zustand achten)

**Vorgehen:**
1. Übergabenotizen von AP-2.1, AP-2.2, AP-2.3 und AP-2.rev durchgehen.
2. In `CLAUDE.md` den bestehenden Kontext zum Notizen-Manager (dort, wo
   `personal-notes-manager.js`/`.css` bereits in der Datei-Map als
   „Persönliche Notizen" auftaucht) um einen Absatz ergänzen: Warum die
   Funktion vorher inaktiv war (Verweis auf Commit `ae681c4`, „war
   Missverständnis"), wie sie jetzt automatisch auf
   Inhaltsverzeichnis-Seiten erscheint (`has_block('fos/inhaltsverzeichnis')`,
   Options-Wert `toc`, neuer Vorgabewert), und die Architekturentscheidung
   „eigene Methode `enqueue_notes_manager_styles()`, um den bestehenden
   Feature-Scan nicht anzufassen".
3. `reference_file_map.md`-Zeilen zu `includes/class-cbd-style-loader.php`,
   `admin/settings.php` und `assets/css/personal-notes-manager.css` gegen
   den tatsächlichen Stand nach Phase 2 abgleichen.
4. `CBD_VERSION` in `container-block-designer.php` um einen Patch-Level
   erhöhen (Cache-Busting).
5. „Stand"-Datum in `CLAUDE.md` und `reference_file_map.md` aktualisieren.

**Akzeptanzkriterien:**
- [ ] Notizen-Manager-Abschnitt in `CLAUDE.md` beschreibt den neuen
      Zustand vollständig (Auslöser, Mechanismus, Vorgabewert).
- [ ] `reference_file_map.md`-Zeilen zu allen drei geänderten Dateien
      spiegeln den tatsächlichen Stand nach Phase 2.
- [ ] `CBD_VERSION` wurde erhöht (bzw. beim Zusammenführen beider Phasen
      liegt am Ende genau ein konsistenter, erhöhter Wert vor).
- [ ] Kein Verweis in der Dokumentation zeigt auf nicht existierende
      Funktionen/Dateien.

**Tests:**
- Stichprobe: `includes/class-cbd-style-loader.php` öffnen und die im
  neuen `CLAUDE.md`-Abschnitt genannte Methode `enqueue_notes_manager_styles()`
  gegen den tatsächlichen Code abgleichen.

**Übergabenotiz:**


## 8. Status

Legende: ☐ offen · ◐ in Arbeit · ☑ erledigt · ✗ blockiert

| AP | Titel | Modell | Status | Abhängig von | Notiz |
|---|---|---|---|---|---|
| AP-1.1 | Werkzeug-Button „Text" + Eingabe-Overlay | sonnet | ☑ | – | Bug gefunden+behoben: Escape schloss ganze Tafel (fehlendes stopPropagation) |
| AP-1.2 | Text-Eintrag speichert und ist rückgängig machbar | sonnet | ☑ | AP-1.1 | Integrationstest Phase 1 bestanden — lauffähiger Endzustand erreicht |
| AP-1.rev | Review Phase 1 | opus | ☑ | AP-1.1, AP-1.2 | 3 Befunde (B1 kritisch, B2/B3 mittel) — behoben in AP-1.fix1/fix2 |
| AP-1.fix1 | Text-Werkzeug mit echter Eingabe nutzbar (B1+B2) | opus | ☑ | AP-1.rev | Kritischer Bug: Fokus ging bei echtem Klick sofort verloren |
| AP-1.fix2 | Strich-Radierer trifft ganzen Textkörper (B3) | sonnet | ☑ | AP-1.rev | |
| AP-1.doc | Doku Phase 1 | sonnet | ☑ | AP-1.rev, AP-1.fix1, AP-1.fix2 | Phase 1 vollständig abgeschlossen, CBD_VERSION 3.1.126 |
| AP-2.1 | Notizen-Manager-Enqueue herauslösen | opus | ☐ | – | |
| AP-2.2 | Options-Wert „toc" + Sichtbarkeitsregel | sonnet | ☐ | AP-2.1 | |
| AP-2.3 | Darkmode-Sichtprüfung personal-notes-manager.css | sonnet | ☐ | AP-2.2 | |
| AP-2.rev | Review Phase 2 | opus | ☐ | AP-2.1, AP-2.2, AP-2.3 | |
| AP-2.doc | Doku Phase 2 | sonnet | ☐ | AP-2.rev | |

## 9. Testprotokoll

Wird während der Ausführung gepflegt. Ein Eintrag pro abgeschlossenem AP und
pro Phasenabschluss.

| Datum | AP / Phase | Getestet | Ergebnis | Getestet von |
|---|---|---|---|---|
| 2026-09-10 | AP-1.1 | Alle 8 Akzeptanzkriterien + 5 Prüfschritte live auf `fos.localhost:8080` (Seite 117, Container `infotext_k1`): Button/Toolbar, Klickposition, Text-Stempel, Escape (inkl. Fund+Fix), Leertext-No-op, Mehrzeilig+Shift-Enter, Regression Stift/Textmarker/Radierer, Darkmode-Kontrast | Bestanden. Ein kritischer Bug gefunden und in diesem AP behoben (Escape schloss die ganze Tafel statt nur das Textfeld) | Claude (Sonnet 5) |
| 2026-09-10 | AP-1.2 | Alle 6 Akzeptanzkriterien live: strokes-Eintrag, Persistenz über close()/reopen, Undo, Reihenfolge Stift→Text→Stift+2×Undo, Strich-Radierer auf Text, Regression Textmarker/Stift-Überlappung | Bestanden, keine Befunde | Claude (Sonnet 5) |
| 2026-09-10 | Phase 1 (Integrationstest) | AP-1.1+AP-1.2 gemeinsam in einer Sitzung: Werkzeugwechsel, mehrfaches Einfügen/Entfernen von Text, Schließen/Neuöffnen | Lauffähiger Endzustand aus Abschnitt 6 erreicht, keine neuen Konsolenfehler | Claude (Sonnet 5) |
| 2026-09-10 | AP-1.rev | Unabhängiges Review (frischer Opus-Agent): Codelektüre, git diff/log, Live-Test mit echten UND synthetischen Eingaben | 3 Befunde: B1 kritisch (Text-Werkzeug mit echter Eingabe funktionslos), B2 mittel (Doppel-Öffnen wirft Fehler), B3 mittel (Radierer trifft nur Textanker). Lauffähiger Endzustand NICHT erreicht | frischer Review-Agent (Opus) |
| 2026-09-10 | AP-1.fix1 | Alle Akzeptanzkriterien ausschließlich mit echten `computer`-Tool-Eingaben (Klick, Tippen, echte Enter-Taste): Feld bleibt offen, Text übernommen, zweites Feld ohne Fehler, Persistenz weiterhin intakt | Bestanden — B1 und B2 behoben | Claude (Sonnet 5) |
| 2026-09-10 | AP-1.fix2 | Strich-Radierer-Klick in Textmitte und am Textende live getestet | Bestanden — B3 behoben, Text vollständig entfernt bei Klick auf den Textkörper | Claude (Sonnet 5) |
| 2026-09-10 | Kurz-Review (Bestätigung AP-1.fix1+fix2) | Zweiter, unabhängiger frischer Agent: B1/B2/B3 erneut mit echten Eingaben geprüft, inkl. Selbsttest des Fehler-Sammlers (B2) und Negativ-Gegenprobe (B3), Regressionscheck Stift/Radierer/Persistenz | Alle drei Befunde unabhängig bestätigt behoben, 0 Konsolenfehler, „Phase 1 ist erreicht" | frischer Review-Agent (Opus) |
| 2026-09-10 | AP-1.doc / Phase 1 Abschluss | CLAUDE.md-Abschnitt, reference_file_map.md und CBD_VERSION gegen den tatsächlichen Code gegengeprüft (Stichprobe: Funktionsnamen/Zeilennummern) | Bestanden — Phase 1 vollständig abgeschlossen (alle APs ☑) | Claude (Sonnet 5) |

## 10. Dokumentation

- **Projektdokumentation:** `Plugins/CDB-Designer/CLAUDE.md` – Architektur-
  und Arbeitsdoku dieses Plugins. Wird in `AP-1.doc` und `AP-2.doc` um die
  Abschnitte „Text-Werkzeug im Tafelmodus" und den erweiterten
  Notizen-Manager-Kontext ergänzt.
- **Datei-Map:** `Plugins/CDB-Designer/reference_file_map.md` – tabellarische
  Übersicht aller projektrelevanten Dateien dieses Plugins. Wird von jedem
  AP gepflegt, das Dateien wesentlich ändert.
- **Erweiterungs-Analyse (Vorlauf):**
  `Plugins/CDB-Designer/docs/ERWEITERUNGSANALYSE-Tafelmodus-Text-und-Notizen-Restore.md` –
  Architektur-Einordnung und Herleitung dieses Plans; dient dem Verständnis,
  ist aber **nicht** Teil der für den ausführenden Agenten verbindlichen
  Anweisungen (die stehen vollständig in Abschnitt 0 dieser Datei).
