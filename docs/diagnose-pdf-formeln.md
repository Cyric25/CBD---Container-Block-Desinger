# Diagnose: „Beim Erstellen der PDFs werden Formeln nicht angezeigt"

_Erstellt: 2026-09-04 · Branch `nachtrag-flackern-und-pdf-formeln` · Testserver
`http://fos.localhost:8080` · Plugin-Stand Repo `3.1.118`, Testserver-Kopie
`3.1.117`_

**Auftrag:** nur diagnostizieren. **Es wurde kein Produktivcode geändert**
(`git diff` gegen den Branchstand enthält ausschließlich diese Datei und die
Zeile in `reference_file_map.md`). Hilfsskripte lagen ausschließlich im
Scratchpad und sind entfernt.

---

## 0. Kurzfassung

**Die Hypothese des Orchestrators ist widerlegt.** Es fehlt nicht das
KaTeX-Stylesheet — es wird **überhaupt kein KaTeX-HTML an mPDF geschickt**. Der
Weg rastert Formeln längst im Browser zu PNG (seit v3.1.58/59), und der
Serverbaustein dafür ist vorhanden und funktioniert: Im Prüf-PDF steckten **52
Bild-XObjects**.

Es sind **zwei voneinander unabhängige Fehler**, und beide erzeugen beim
Betreiber denselben Eindruck:

| # | Ursache | Betroffen | Symptom im PDF |
|---|---|---|---|
| **U1** | `html2canvas` mit `foreignObjectRendering: true` liefert eine **vollständig transparente** Leinwand; die Annahmeprüfung testet nur `width>0 && height>0`, erkennt das Leerbild nicht und erreicht den funktionierenden Standard-Painter nie | **alle** Formeln in aufgeklappten Containern (hier 36 von 76) | Formel **völlig unsichtbar**, es bleibt eine Lücke |
| **U2** | ~~`expandAllBlocks()` sucht nur unterhalb von `[data-wp-interactive="container-block-designer"]`; die Container dieser Seite haben dieses Attribut **nicht**, bleiben also zugeklappt → Formeln haben Maß 0 → die Größenbremse überspringt sie stumm~~ **— widerlegt, siehe Richtigstellung unten** | alle Formeln in **zugeklappten** Containern (hier 40 von 76) | Formel als **verstümmelter Fließtext**, mathematisch **falsch** |

> **Richtigstellung (unabhängiges Review, `PLAN-Nachtraege-Klassenmodus.md`,
> Befund N2-1, 2026-09-04):** **U2 ist widerlegt.** Diese Zeile prüfte nur
> `$block.find('[data-wp-interactive="container-block-designer"]')` (0
> Treffer, siehe Abschnitt 5.1) — aber die zweite Zeile desselben
> Code-Ausschnitts, `$block.is('[data-wp-interactive="container-block-designer"]')`,
> ist über alle 23 geprüften Container **23 von 23 wahr**, weil
> `includes/class-cbd-block-registration.php:930` dieses Attribut
> **bedingungslos** an jeden gerenderten Container schreibt. `$allContainers`
> hat dadurch immer mindestens einen Eintrag (den Block selbst), die
> Schleife läuft, und nach der Aufklapplogik von `expandAllBlocks()` haben
> **0 von 76** Formeln ein Maß < 2 px — nicht 40. **Auch die als Ersatz
> vorgeschlagene Erklärung des N2-Fixes (abgesetzte Formeln hätten als
> Inline-Element keine eigene Breite) ist widerlegt** (Review-Befund N2-2):
> `assets/css/latex-formulas.css:31–34` setzt
> `.cbd-latex-formula.cbd-latex-display { display: block !important; }` —
> der berechnete `display`-Wert dieser Spans ist `block`, nicht `inline`,
> und keine sichtbare abgesetzte Formel hatte je Breite 0. Die 40 Formeln
> mit Maß 0 lagen ausnahmslos in **zugeklappten** Containern (Inline- wie
> abgesetzte gemischt) — die Ursache dort war das Zuklappen, nicht die
> Formelart. Die **einzige belegte** Ursache der
> `Captured 0/N`-Zeilen ist Punkt 1 aus `CLAUDE.md`, Abschnitt „PDF-Export:
> Formeln als Bild — die Blankheitsprüfung" (`canvasIstBemalt()` gegen die
> leere `foreignObjectRendering`-Leinwand); `messeFormel()` rettet auf der
> Prüfseite **0 von 76** Formeln und ist reine, ungemessene Vorsorge. Die
> Messwerte in Abschnitt 5 dieses Berichts (Selektorergebnis,
> Capture-Protokoll, Nachweis in 5.3) bleiben gültig — **nur der daraus
> gezogene Schluss „das ist die Ursache" ist falsch.** Details und
> Gegenmessung: `PLAN-Nachtraege-Klassenmodus.md`, Abschnitt „Priorität 2 —
> der U2-Streit: beide Seiten haben unrecht".

**Empfehlung: Variante A** — den bestehenden PNG-Weg reparieren (U1 zuerst),
Umfang ca. **35–40 Zeilen JavaScript in einer Datei**, Aufwand **0,5–1 Tag**
inkl. Prüfung, Risiko **gering**. Details in Abschnitt 7/8.

**Ein dritter, nachgelagerter Fund (U3):** Wer die Seite im **Dunkelmodus**
liest, bekommt selbst nach einem U1-Fix weiße Glyphen auf weißem Papier —
die Dunkelmodus-Neutralisierung greift erst **nach** allen Captures. Gemessen,
aber solange U1 besteht praktisch verdeckt. Das ist die im Auftrag
angesprochene „weiß auf weiß"-Variante: **sie existiert, ist aber nicht die
gemeldete Ursache.**

---

## 1. Vorbedingungen: läuft überhaupt der Code, den ich lese?

Diese Frage steht zuerst, weil das Projekt die Plugins **als Kopie** in den
Testserver legt (nicht als Verknüpfung) — ein Befund am Repo-Stand wäre sonst
wertlos.

| Prüfung | Ergebnis |
|---|---|
| Testserver erreichbar | `HTTP 200` auf `http://fos.localhost:8080/` |
| WordPress-Wurzel | `C:\allinkl-testserver\www\htdocs\w0000001\fos` |
| CDB-Designer aktiv | `is_plugin_active(...)` → **JA** |
| `CBD_VERSION` Repo / Testserver | `3.1.118` / `3.1.117` |
| `assets/js/pdf-server-side.js` Repo ↔ Testserver | **byteidentisch** (`diff -q`) |
| `includes/class-cbd-pdf-generator.php` | **byteidentisch** |
| `includes/class-cbd-ajax-handler.php` | **byteidentisch** |
| `assets/js/latex-renderer.js` | **byteidentisch** |
| `assets/lib/html2canvas.min.js` | vorhanden, 198 689 Byte |

Alle vier maßgeblichen Dateien sind auf dem Testserver zeichengleich mit dem
Branch-Stand; der letzte Eingriff an `pdf-server-side.js` (`546e40d`) liegt
deutlich vor dem Versionsunterschied. **Messungen am Testserver gelten damit
für den Branch.**

**Testinhalt:** echte Betreiberseiten, per SQL ermittelt. Gemessen wurde vor
allem an **Seite 1636 „Elektrochemische Grundlagen"**
(`.../elektrochemie/elektrochemische-grundlagen/`): **76 Formeln**
(`.cbd-latex-formula`), davon **25 abgesetzt** (`cbd-latex-display`) und **51
inline**, verteilt über **23 Container-Blöcke**, davon **8 zugeklappt**.
Browser: Chrome 148 (Claude-Browser-Pane).

---

## 2. Der Weg, wie er tatsächlich gebaut ist

Wichtig für die Bewertung der Hypothese: Der serverseitige PDF-Weg schickt
KaTeX-HTML **nicht** als Hauptweg an mPDF, sondern rastert im Browser zu PNG.

1. **`assets/js/pdf-server-side.js`**
   - `processOneBlock()` klont den Block (Z. 252).
   - sammelt die **Original**-Formelelemente `.cbd-latex-formula` (Z. 291 ff.).
   - ersetzt die Formeln **im Klon** durch Platzhalter
     `<span|div class="cbd-pdf-formula" data-cbd-formula-id="…">Fallbacktext</…>`
     (Z. 304–329). Der Fallbacktext ist der Textabzug aus `.katex-html`,
     ersatzweise `latexToReadable()`.
   - `captureFormulaImages()` (Z. 413 ff.) rastert **die Originale** per
     `html2canvas` (`scale: 2`); 1. Versuch `foreignObjectRendering: true`,
     bei Fehler/leer Rückfall auf den Standard-Painter.
2. **`includes/class-cbd-ajax-handler.php::generate_pdf()`** (Z. 422 ff.)
   prüft je Formel `id`, `renderedHtml`, `latex` und — falls vorhanden —
   `image`/`width`/`height`/`isDisplay`.
3. **`includes/class-cbd-pdf-generator.php::prepare_structured_block()`** (Z. 294 ff.)
   - Schritt 1.5 `insert_formula_image()` (Z. 498) ersetzt den Platzhalter
     durch `<img src="data:image/png;base64,…">` — **nur wenn `image` gesetzt ist.**
   - Schritt 4 `insert_formula()` (Z. 534) würde ersatzweise `renderedHtml`
     einsetzen, matcht dabei aber auf `id="…"` statt auf
     `data-cbd-formula-id="…"`.

**Korrektur zur Auftragsannahme:** Die Zusammenführung der Platzhalter mit den
gesammelten Formeln liegt **nicht** in `class-cbd-ajax-handler.php`, sondern in
`class-cbd-pdf-generator.php` (`insert_formula_image()` / `insert_formula()`).

---

## 3. Die Hypothese: widerlegt

> _„`collectCSSVariables()` sammelt nur Theme-Farbvariablen — kein
> KaTeX-Stylesheet. Das KaTeX-HTML erreicht mPDF ohne `katex.css` und ohne die
> KaTeX-Webfonts."_

Zutreffend ist nur der Vordersatz: `collectCSSVariables()` (Z. 1415) sammelt
tatsächlich acht Theme-Farben und kein KaTeX-CSS. **Die Folgerung trifft
nicht zu, weil nie KaTeX-HTML an den Server geht:**

1. **`extractFormulas()` ist toter Code.** Die Funktion (Z. 917) — die den
   `renderedHtml`-Zweig samt `katex.renderToString(...)` und
   `<span style="color:red;">Formula Error</span>` enthält — wird **an keiner
   Stelle aufgerufen**:
   ```
   $ grep -n "extractFormulas" assets/js/pdf-server-side.js
   917:    function extractFormulas($block) {
   ```
   Genau ein Treffer: die Definition. Kein Aufruf.
2. **Gemessen an der echten Nutzlast:** Das an den Server gehende Block-Objekt
   enthält die Schlüssel `html`, `title`, `formulas`, `screenshots`. Die
   `formulas`-Einträge stammen **ausschließlich** aus dem Rückruf von
   `captureFormulaImages()` (Z. 371–390) und tragen daher nur
   `id`/`image`/`width`/`height`/`isDisplay` — **kein `renderedHtml`, kein
   `latex`**.
3. Im gemessenen Block-HTML: `katex` **0×**, `cbd-latex-formula` **0×** — im
   Klon ist restlos jede Formel durch den Platzhalter ersetzt.

**Folge:** `insert_formula()` (Generator Z. 534) kann nie feuern; auch dieser
Serverzweig ist toter Code. Ein KaTeX-Stylesheet in mPDF zu registrieren würde
am gemeldeten Fehler **nichts** ändern.

---

## 4. Die tatsächliche Ursache U1 — die PNGs sind leer

Das ist die Hauptursache: Sie betrifft **jede** Formel in einem aufgeklappten
Container, also den Alltagsfall.

### 4.1 Die Annahmeprüfung (`pdf-server-side.js:476–492`)

```js
// 1. Versuch: foreignObjectRendering (beste KaTeX-Treue)
attemptCapture(true, function (canvas) {
    if (canvas && canvas.width > 0 && canvas.height > 0) {
        store(canvas);          // <-- ein LEERBILD besteht diese Prüfung
        return;
    }
    // 2. Versuch: Standard-Painter
    attemptCapture(false, function (canvas2) { … });
});
```

Geprüft werden nur die **Maße**, nicht der **Inhalt**. Eine korrekt
dimensionierte, aber unbemalte Leinwand wird angenommen — der zweite Versuch
ist damit unerreichbar.

### 4.2 Messung: FO liefert 0 sichtbare Pixel, der Standard-Painter malt

Drei sichtbare Formeln, je beide Verfahren, Zählung der Pixel mit Alpha > 10:

| Formel (`data-latex`) | Leinwand | `foreignObjectRendering: true` | `foreignObjectRendering: false` |
|---|---|---|---|
| `\text{Zn} + \text{Cu}^{2+} \rightarrow …` | 1426×124 | **0 Pixel (0,00 %)** | 3319 Pixel (1,88 %), alle dunkel |
| `\text{Zn} \rightarrow \text{Zn}^{2+} + 2e^-` | 276×48 | **0 Pixel (0,00 %)** | 692 Pixel (5,22 %), alle dunkel |
| `\text{Cu}^{2+} + 2e^- \rightarrow \text{Cu}` | 284×48 | **0 Pixel (0,00 %)** | 707 Pixel (5,19 %), alle dunkel |

Die FO-Leinwand ist **nicht** „tainted" (`getImageData` und `toDataURL`
funktionieren beide) — html2canvas gibt sie einfach unbemalt zurück.

### 4.3 Gegenprobe an der echten Nutzlast (Ende-zu-Ende)

Export eines Blocks mit drei sichtbaren Formeln, Nutzlast aus dem
Netzwerkaufruf abgefangen, die **übertragenen** Base64-PNGs dekodiert und
ausgezählt:

| `id` | Base64-Länge | PNG | deklariert | **opake Pixel** |
|---|---|---|---|---|
| `cbd-latex-6a9aeab137d24-1` | 5370 | 1426×124 | 713×61 | **0** |
| `cbd-latex-inline-6a9aeab137fc6-1` | **742** | 276×48 | 137×24 | **0** |
| `cbd-latex-inline-6a9aeab137fe8-2` | **738** | 284×48 | 141×24 | **0** |

742 Byte für ein 276×48-PNG ist die Größe eines vollständig transparenten
Bildes. **Der Server bekommt die Formeln, bettet sie korrekt ein — und sie
sind leer.** Genau das ist „Formeln werden nicht angezeigt".

### 4.4 Beleg im PDF selbst

Textebene des Prüf-PDFs (13 Seiten, 52 Bild-XObjects), an den Stellen der
aufgeklappten Blöcke:

```
…istdasReduktionsmittel(gibtElektronenab).…istdasOxidationsmittel(nimmtElektronenauf).
Beispiel:Zinkwirdoxidiert()undKupferwirdreduziert().
Zink-Halbzelle:ZinkblechtauchtinZinksulfatlösung()
Kupfer-Halbzelle:KupferblechtauchtinKupfersulfatlösung()
AnderZinkelektrode(Anode):        AnderKupferelektrode(Kathode):
…EineZinkhalbzelleerhältman,indemmaneineZinkelektrodeineineZinksulfatlösung(ZnSO-Lösung)taucht.
…hatperDeﬁnitioneinPotentialvon:
Redoxpaar(V)-3,04-2,92-2,38-1,66-0,76-0,44-0,130,00+0,35+0,80+1,50
```

Überall dort, wo eine Formel stehen müsste, ist **eine Lücke**: leere
Klammerpaare, ein Doppelpunkt ohne Gleichung, `ZnSO` ohne die tiefgestellte 4,
eine Tabellenspalte mit Zahlen ohne Redoxpaare. Die Bilder sind da, sie zeigen
nichts.

### 4.5 Nebenwirkung: der Export ist dadurch extrem langsam

Der vergebliche FO-Versuch läuft für **jede** Formel zuerst. Gemessen: Block 15
mit 14 Formeln brauchte **rund 100 Sekunden** (≈ 7 s je Formel); der gesamte
Export von 23 Blöcken lief **≈ 6 Minuten**. (Der Block war nicht hängend —
das habe ich geprüft, er lief weiter.) Ein U1-Fix, der FO nicht mehr blind
zuerst versucht, beschleunigt den Export also deutlich mit.

---

## 5. U2 — zugeklappte Container werden nie aufgeklappt (widerlegt, siehe Richtigstellung)

> **Diese Abschnittsüberschrift stammt aus der ursprünglichen Diagnose und
> ist widerlegt.** Das unabhängige Review (`PLAN-Nachtraege-Klassenmodus.md`,
> Befund N2-1) hat gemessen, dass `$block.is('[data-wp-interactive=
> "container-block-designer"]')` — die Zeile direkt unter der in 5.1
> zitierten `.find()`-Zeile — **23 von 23 mal wahr** ist und `$allContainers`
> deshalb nie leer bleibt. Die Messungen in 5.1–5.3 unten sind unverändert
> zutreffend (sie sind stehen gelassen, nicht gelöscht), nur der Schluss
> „das ist die Ursache der fehlenden Formeln" ist falsch: Nach der
> Aufklapplogik von `expandAllBlocks()` hat **keine** der 76 Formeln ein
> Maß < 2 px. **Die ursprünglich hier vermutete Ersatzursache — `messeFormel()`s
> Vorgänger-Zustand, eine Breite-0-Messung bei abgesetzten Formeln — ist
> ebenfalls widerlegt** (Review-Befund N2-2): `assets/css/latex-formulas.css:32`
> setzt `display: block !important` auf diese Spans, keine sichtbare
> abgesetzte Formel hatte je Breite 0, und `messeFormel()`s Rückfall rettet
> auf der Prüfseite **0 von 76** Formeln. Die belegte, alleinige Ursache der
> `Captured 0/N`-Zeilen ist stattdessen Punkt 1 aus `CLAUDE.md`, Abschnitt
> „PDF-Export: Formeln als Bild — die Blankheitsprüfung" —
> `canvasIstBemalt()` gegen die leere `foreignObjectRendering`-Leinwand.
> `messeFormel()` (Punkt 2 dort) bleibt unschädlich im Code, ist aber
> nachweislich wirkungslos, nicht die Ursache.

### 5.1 Der Selektor greift nicht (`pdf-server-side.js:128–131`)

```js
var $allContainers = $block.find('[data-wp-interactive="container-block-designer"]');
if ($block.is('[data-wp-interactive="container-block-designer"]')) {
    $allContainers = $allContainers.add($block);
}
```

Für den Block „Die Nernst-Gleichung" gemessen: **`interactiveFound: 0`** — es
gibt in diesem Block **kein einziges** Element mit
`data-wp-interactive="container-block-designer"`. `$allContainers` bleibt leer,
die Schleife läuft null Mal, **nichts wird aufgeklappt.**

Der zuklappende Knoten ist stattdessen vorhanden und heißt:

```
div.cbd-container-content.cbd-collapsed
    display: none · visibility: hidden · max-height: 0px · overflow: hidden
```

`$block.find('.cbd-container-content')` **findet** ihn (`contentFound: 1`) —
`expandAllBlocks()` kommt aber nie dorthin, weil die äußere Schleife über
`$allContainers` läuft.

### 5.2 Folge: Maß 0 → stumme Größenbremse

`captureFormulaImages()` (Z. 432–438):

```js
var rect = el.getBoundingClientRect();
// Unsichtbare/leere Formeln überspringen (Fallback-Text greift)
if (rect.width < 2 || rect.height < 2) { index++; nextFormula(); return; }
```

Gemessen im Ruhezustand der Seite: **40 von 76** Formeln haben
`width = height = 0`, und es sind **genau** die 40 in den 8 zugeklappten
Containern (`formulasInCollapsed: 40`, `zeroSize: 40`).

Die Capture-Protokollzeilen des Gesamtexports treffen das 1:1:

```
Captured 3/3 · 1/1 · 5/5 · 1/1 · 1/1 · 4/4 · 14/14 · 2/2 · 5/5      ← 36 aufgeklappt
Captured 0/13 · 0/5 · 0/12 · 0/10                                   ← 40 zugeklappt
```

13+5+12+10 = **40**. Die vier Nullblöcke sind „Die Nernst-Gleichung",
„Vereinfachte Form der Nernst-Gleichung", „Anwendung der Nernst-Gleichung",
„Konzentrationselemente". **Kein einziger `console.warn`** dabei — es ist
nicht ein fehlgeschlagener Capture, sondern der stumme Übersprung.

### 5.3 Nachweis, dass ein Aufklappen genügen würde

Ich habe genau die Stile gesetzt, die `expandAllBlocks()` setzen *würde*:
danach hatten **13 von 13** Formeln ein Maß ≥ 2 px (88 px hohe abgesetzte
Formeln, `opacity: 1`, Farbe `rgb(51,51,51)`). Die Größenbremse würde also
passieren. Zustand danach vollständig zurückgesetzt (Inline-Stil leer, Klasse
`cbd-collapsed` wieder da).

_Wichtig: Damit ist U2 gelöst, U1 aber nicht — der Capture danach lieferte
weiterhin 0 opake Pixel. **Beide Fehler müssen behoben werden.**_

### 5.4 Was der Leser stattdessen sieht: falsche Mathematik

Weil kein Bild kommt, bleibt der Platzhalter mit dem Textabzug stehen. Er
erreicht das PDF auch — aber als **flachgeklopfte, mathematisch falsche**
Zeichenkette. Aus dem PDF ausgelesen (korrekter PDF-String-Tokenizer, s.
Abschnitt 9):

| Im PDF | Richtig wäre |
|---|---|
| `E=E0+n⋅FR⋅T​⋅lnc(Red)c(Ox)​` | E = E⁰ + (R·T)/(n·F) · ln( c(Ox)/c(Red) ) |
| `E=E0+n0,059V​⋅logc(Red)c(Ox)​` | E = E⁰ + (0,059 V)/n · log( c(Ox)/c(Red) ) |
| `DerFaktorFR⋅T​wirdbei25°Czu964858,314⋅298​≈0,0257V` | (R·T)/F = (8,314·298)/96485 ≈ 0,0257 V |
| `ΔE=n0,059V​⋅logc2​c1​​` | ΔE = (0,059 V)/n · log(c₁/c₂) |

Der Bruchstrich verschwindet, **Nenner und Zähler tauschen die Reihenfolge**
(`n⋅F` vor `R⋅T`), und Argumentklammern verschwimmen. Für einen
Chemielehrer ist das schlechter als eine Lücke: Es sieht wie eine Formel aus
und ist falsch.

---

## 6. Die Nebenwege aus Punkt 2 des Auftrags — einzeln beantwortet

**a) Greift `latexToReadable()` überhaupt je?**
**Praktisch nein.** Es ist die *zweite* Fallback-Stufe und feuert nur, wenn der
Textabzug aus `.katex-html` leer bleibt (Z. 317). Gemessen über alle 76
Formeln: **76 nicht-leer, 0 leer, 0 ohne `.katex-html`.** Die Funktion ist
erreichbar, wird auf echten Seiten aber nie gebraucht, solange KaTeX rendert.
Der in Abschnitt 5.4 gezeigte falsche Text kommt **nicht** von
`latexToReadable()`, sondern vom `.katex-html`-Textabzug.

**b) Werden die Platzhalter serverseitig zusammengeführt — und wo?**
Ja, aber **nicht** im Ajax-Handler: in
`includes/class-cbd-pdf-generator.php::prepare_structured_block()`.
`insert_formula_image()` (Z. 498) matcht
`/<(?:div|span)[^>]*data-cbd-formula-id="ID"[^>]*>.*?<\/(?:div|span)>/is` und
ersetzt den Platzhalter durch das `<img>` — **nur bei gesetztem `image`**.
Geprüft und in Ordnung: Die 13 Platzhalter des Nernst-Blocks trugen korrekte
IDs (`data-cbd-formula-id="cbd-latex-6a9aeab141da1-1"` …), und für die
aufgeklappten Blöcke hat die Ersetzung nachweislich funktioniert (52
Bild-XObjects im PDF). **Der Zusammenführungscode ist nicht schuld.**
`insert_formula()` (`renderedHtml`, Z. 534) ist toter Code (Abschnitt 3).

**c) Kommt `Formula Error` im Ergebnis vor?**
**Nein — 0×.** Weder in der Nutzlast noch im PDF (beide PDFs auf
`FormulaError` durchsucht: 0 Treffer). Die Zeichenkette steht ausschließlich in
`extractFormulas()`, und die läuft nie.

**d) Beide PDF-Wege oder nur der serverseitige?**
- **Serverseitiger Weg (`pdf-server-side.js`): betroffen** — gemessen.
- **Apple-Weiche zum Einzelblock-PDF:** `interactivity-store.js:359-366` und
  `interactivity-fallback.js:381` rufen **dieselbe** Funktion
  `window.cbdPDFExportServerSide(...)` auf. Sie teilen daher U1 und U2
  vollständig. _Nicht am Gerät gemessen_ (kein Apple-Gerät verfügbar) —
  der geteilte Codepfad ist aber gelesen und eindeutig.
  _Randbeobachtung, ungeprüft:_ Dort wird `[$(mainContainer)]` übergeben, ein
  **einfaches JS-Array**; `expandAllBlocks()` ruft darauf `containerBlocks.each(...)`
  auf, was ein Array nicht kennt. Das wäre ein eigener Fehler auf der
  Apple-Weiche — bitte separat prüfen, ich konnte es nicht auslösen.
- **`assets/js/html2pdf-loader.js`: enthält keinerlei Formelbehandlung**
  (`grep -i "latex|katex|formula|formel"` → 0 Treffer); Formeln würden dort im
  Gesamt-Screenshot des Blocks mitlaufen. Eingereiht wird die Datei **nur** aus
  `includes/class-cbd-classroom.php` (Z. 2092) — **diese Datei bearbeitet
  gerade ein zweiter Agent, ich habe den Weg deshalb nicht gemessen.** Offen.

**e) Inline und abgesetzt gleich betroffen?**
**Ja, bei U1 identisch** — die Messung in 4.2/4.3 enthält beide Sorten
(`isDisplay: 1` und `isDisplay: 0`), alle mit 0 opaken Pixeln. Bei U2 ist die
Trennung nicht nach Sorte, sondern nach Klappzustand: Von den 40 übersprungenen
Formeln sind sowohl abgesetzte als auch inline betroffen.

**f) Sind Formeln in zugeklappten Containern betroffen?**
**Zum Zeitpunkt dieser Diagnose ja** (40 von 76 hatten Maß 0, siehe Abschnitt
5) — **als Ursache der PDF-Lücken aber widerlegt.** Das unabhängige Review
(`PLAN-Nachtraege-Klassenmodus.md`, N2-1) hat nachgewiesen, dass
`expandAllBlocks()` über `$block.is(...)` (23/23 wahr) tatsächlich jeden
Container aufklappt und nach dieser Aufklapplogik **0 von 76** Formeln ein
Maß < 2 px behalten — die hier vermutete Ursache U2 tritt in dieser Form
nicht auf. Anders als in der Auftragsvermutung liegt das ohnehin **nicht**
an falschem KaTeX-Rendern in `display:none`:
`cbdRenderLatex` hatte bereits alle **76 von 76** Formeln gerendert
(`data-cbd-latex-rendered="1"`, 76 `.katex`-Knoten, 0 `.cbd-latex-error`,
0 `data-cbd-latex-failed`) — auch die zugeklappten. Sie hatten zum
Capture-Zeitpunkt zwar Maß 0, aber weil sie aufgeklappt wurden, bevor der
Capture lief, wirkte sich das nicht aus.

**g) Stehen die Formeln womöglich weiß auf weiß? (U3)**
**Nicht im gemeldeten Fall, aber der Mechanismus existiert.**
Im Ruhezustand ist die Seite hell (`data-theme` = `null`, Body-Hintergrund
`rgb(255,255,255)`, Formelfarbe `rgb(51,51,51)`) — und zwar auch dann, wenn das
Betriebssystem dunkel bevorzugt (`prefers-color-scheme: dark` war im Prüfbrowser
`true`). Der Altbestandsfehler mit `prefers-color-scheme` wirkt hier also nicht;
in `assets/css/*.css` gibt es keine `prefers-color-scheme`-Regel für Formeln.
**Aber:** Mit gesetztem `data-theme="dark"` liefert der Standard-Painter
**1162 helle und 0 dunkle Pixel** (hell = weiße Glyphen), im Hellmodus
**1053 dunkle und 0 helle**. Und `collectCSSVariables()` entfernt `data-theme`
laut eigenem Kommentar (Z. 1398-1438) **nur während des synchronen Auslesens
der Farbvariablen** — das passiert in `sendPDFRequest()`, also **nach** allen
Captures in `processBlocksSequentially()`. Ein Dunkelmodus-Leser bekäme nach
einem U1-Fix also weiße Formeln auf weißem Papier. **Muss beim Fix
mitbehoben werden**, ist aber nicht die gemeldete Ursache.

---

## 7. Ein vierter, latenter Fund (kein Fehler heute)

**Reihenfolge Klon ↔ ID-Vergabe.** In `processOneBlock()`:

- Z. 252 `var $clone = $block.clone();`
- Z. 295 `el.id = 'cbd-pdf-formula-' + …` — vergibt IDs auf dem **Original**,
  also **nach** dem Klonen
- Z. 307 `var formulaId = this.id || '';` — liest die ID **aus dem Klon**

Wären Formeln ohne `id` ausgeliefert, hätte der Klon leere IDs und der Server
könnte keinen Platzhalter zuordnen. **Gemessen: tritt nicht ein** — der
LaTeX-Parser liefert bereits jede Formel mit `id` aus (**76 von 76**, z. B.
`id="cbd-latex-6a9aeab137d24-1"`), `if (!el.id)` läuft nie.

Der Fund ruht also, solange `class-latex-parser.php` IDs vergibt; er würde erst
bei clientseitig nachgerenderten Formeln ohne `id` zuschlagen. Bemerkenswert:
Für die *interaktiven* Elemente ist dieselbe Falle im Code ausdrücklich
vermieden („_must happen before cloning so the IDs are included in the clone_",
Z. 245-247) — bei den Formeln nicht. **3 Zeilen, gratis mitzunehmen.**

---

## 8. Lösungsvarianten mit Aufwand und Risiko

### Variante A — bestehenden PNG-Weg reparieren ✅ **empfohlen**

| Teil | Eingriff | Umfang |
|---|---|---|
| **A1 (U1)** | In `captureFormulaImages()` nach dem Capture prüfen, ob die Leinwand überhaupt bemalt ist (`getImageData`, irgendein Pixel mit Alpha > 10), und nur dann annehmen; sonst Standard-Painter. **Zusätzlich das Ergebnis je Sitzung merken** — ist FO beim ersten Mal leer, für die restlichen Formeln direkt den Standard-Painter nehmen (sonst zahlt jede Formel beide Verfahren und der Export wird noch langsamer). | ~20 Z. |
| **A2 (U2, widerlegt — siehe Richtigstellung Abschnitt 5)** | In `expandAllBlocks()` zusätzlich `$block.find('.cbd-container-content')` (alle, nicht `.first()`) einsammeln, unabhängig von `data-wp-interactive`. Sichern/Zurücksetzen bleibt unverändert. **Umgesetzt als `CONTAINER_SELEKTOR`-Erweiterung (Commit `08d8db6`), aber nachweislich wirkungslos:** Alle 23 Container der Prüfseite tragen `data-wp-interactive` bereits, `$block.is(...)` griff schon vorher. Bewusst nicht zurückgebaut — siehe `PLAN-Nachtraege-Klassenmodus.md`, Befund N2-4. | ~10 Z. |
| **A3 (U3)** | Formelfarbe für das Capture erzwingen — am saubersten über die `onclone`-Rückrufoption von html2canvas, die den Klon vor dem Malen anfasst (Farbe aus dem Hellmodus-Wertesatz), **nicht** durch Umschalten von `data-theme` an der echten Seite (das würde flackern — und Flackern ist auf diesem Branch bereits ein eigenes Thema). | ~8 Z. |
| **A4 (latent)** | ID-Vergabe vor `$block.clone()` ziehen (Abschnitt 7). | ~3 Z. |

**Aufwand: 0,5–1 Tag** inkl. Prüfung. **Risiko: gering** — eine Datei
(`assets/js/pdf-server-side.js`), rein clientseitig, kein PHP, keine
Datenbank, keine neue Abhängigkeit. Der Serverteil ist bereits bewiesen
funktionsfähig (52 korrekt eingebettete Bild-XObjects).
**Nebengewinn:** Der Export wird deutlich schneller (Abschnitt 4.5).
**Zu beachten:** Schwelle für „bemalt" bewusst niedrig halten (ein einziges
Pixel genügt), sonst würden zu Recht dünne Formeln (Bruchstrich, Komma,
einzelner Buchstabe) verworfen. Bei großen Leinwänden mit Schrittweite
abtasten, damit `getImageData` nicht bremst.
**Reserve, falls html2canvas auch im Standard-Painter Ärger macht:**
`assets/lib/modern-screenshot.min.js` liegt bereits im Plugin.

### Variante B — KaTeX-CSS und -Schriften in mPDF registrieren ❌

**Setzt voraus, den toten `extractFormulas()`-Zweig samt Serverzweig
`insert_formula()` erst wiederzubeleben** — die Variante behebt also nichts,
was heute kaputt ist, sondern baut einen zweiten Weg neu.
Danach müssten vier KaTeX-Schriftfamilien als TTF in mPDFs `fontdata`
registriert werden (die Dateien liegen lokal unter `assets/vendor/katex`, das
ist DSGVO-konform, aber mPDFs Registrierung ist ein eigener Mechanismus). Und
selbst dann bleibt, was die Hypothese richtig benennt: `.vlist`/`.strut`,
absolute Positionierung, negative Ränder und `em`-Ketten kann mPDFs
CSS-Maschine nicht. **Aufwand 2–4 Tage, Risiko sehr hoch**, Ergebnis mit hoher
Wahrscheinlichkeit trotzdem falsch gesetzt. **Nicht empfohlen.**

### Variante C — SVG statt PNG ❌

KaTeX kennt **keine** SVG-Ausgabe (nur HTML + MathML); es bräuchte MathJax als
neue Abhängigkeit und eine zweite Renderstrecke parallel zur bestehenden.
mPDFs SVG-Unterstützung ist zudem eingeschränkt (kein `foreignObject`,
begrenzte Textbehandlung). **Aufwand 2–3 Tage, Risiko hoch.** Nicht empfohlen.
_Der Teilgedanke „anderer Rasterer" ist dagegen brauchbar und steckt als
Reserve in A1._

### Variante D — `latexToReadable()` zum Hauptweg machen ❌

Billig (~20 Zeilen), aber inhaltlich der schlechteste Weg: Abschnitt 5.4 zeigt
**gemessen**, wie flacher Formeltext bei Brüchen aussieht — Bruchstrich weg,
Nenner und Zähler vertauscht. Für Reaktionsgleichungen, `m/z`-Verhältnisse und
die Nernst-Gleichung ist das fachlich unbrauchbar. **Als letzte Rückfallstufe
behalten** (dort steht es sinnvoll), **nicht als Hauptweg.** Wenn es dort
bleibt, wäre eine kleine Verbesserung sinnvoll: Brüche als `(a)/(b)`
ausschreiben statt zu verketten.

### Empfehlung

**Variante A, in der Reihenfolge A1 → A2 → A3 → A4.** A1 allein holt die
36 von 76 Formeln des Alltagsfalls zurück und ist der eigentliche Fix der
Meldung; A2 die restlichen 40; A3 verhindert, dass der Erfolg für
Dunkelmodus-Leser unsichtbar bleibt; A4 nimmt eine latente Falle mit.

**Prüfvorschlag für die Umsetzung** (alles am Testserver reproduzierbar):
Seite 1636 exportieren und danach messen — (1) alle Capture-Zeilen lauten
`N/N`, keine `0/N`; (2) jedes übertragene `image` hat > 0 opake Pixel; (3) in
der PDF-Textebene steht an keiner Formelstelle mehr eine leere Klammer
`()` und keine flachgeklopfte Kette wie `n⋅FR⋅T`; (4) derselbe Export mit
`data-theme="dark"` ergibt dunkle Glyphen; (5) Klappzustände und Inline-Stile
der Seite sind nach dem Export unverändert.

---

## 9. Was gemessen wurde — und was nicht

**Gemessen (am laufenden Testserver, Seite 1636, Chrome 148):**
Dateigleichheit Repo ↔ Testserver · DOM-Bestand (76 Formeln, 76 gerendert,
0 Fehler, 25 abgesetzt / 51 inline, 8 zugeklappte Container mit 40 Formeln) ·
vollständiger Export über 23 Blöcke inkl. aller Capture-Protokollzeilen ·
abgefangene Netzwerk-Nutzlast (Blockschlüssel, Platzhalter, IDs,
`formulas`-Inhalt) · Pixelauszählung der **übertragenen** PNGs · Vergleich
FO ↔ Standard-Painter an drei Formeln · Verhalten mit `data-theme="dark"` ·
Struktur und Textebene der erzeugten PDFs (13 Seiten, 52 Bild-XObjects) ·
Wirksamkeitsnachweis eines Aufklappens (13/13) · Erreichbarkeit von
`latexToReadable()` (0/76).

**Nicht gemessen — ausdrücklich offen:**
- **Der `html2pdf-loader.js`-Weg.** Eingereiht nur aus
  `includes/class-cbd-classroom.php`, die ein zweiter Agent parallel
  bearbeitet; ich habe die Datei nicht angefasst. Formelbehandlung ist dort
  nicht vorhanden — welche Folge das hat, ist unbelegt.
- **Die Apple-Weiche auf echtem Gerät.** Nur der geteilte Codepfad ist gelesen.
  Die `.each()`-auf-Array-Beobachtung (6d) ist eine Leseauffälligkeit, **keine
  Messung**.
- **Das PDF visuell.** `wp-content/uploads/cbd-temp-pdfs/` ist per `.htaccess`
  gesperrt, der Browser durfte die Datei nicht öffnen, und `pdftoppm`/poppler
  ist in dieser Umgebung nicht installiert. Statt eines Screenshots liegen die
  Pixelauszählung der übertragenen Bilder (stärker) und die extrahierte
  Textebene vor.
- **Andere Seiten.** Nur Seite 1636 wurde vollständig exportiert. Ursache U1
  ist strukturell und nicht inhaltsabhängig. **U2 ist widerlegt, und die als
  Ersatz vermutete Breite-0-Messung bei abgesetzten Formeln ebenfalls**
  (siehe Richtigstellung in Abschnitt 5 und 0, Review-Befunde N2-1/N2-2) —
  die einzige belegte Ursache der `Captured 0/N`-Zeilen ist U1
  (`canvasIstBemalt()` in `CLAUDE.md`), ebenfalls strukturell und nicht
  inhaltsabhängig.
- **Kein interaktiver WordPress-Login.** WordPress wurde per **PHP-CLI**
  (`php.exe -c C:\allinkl-testserver\conf\php.ini` + `wp-load.php`)
  gebootstrappt; **es wurde nichts in den Web-Root gelegt** und nichts in der
  Datenbank geändert.

**Eine eigene Fehlmessung, offengelegt:** Mein erster PDF-Textauszug meldete
„`Zn` 0×, `Nernst` 0×" und legte nahe, es käme gar kein Fallbacktext an. Das
war **falsch** — mein Auszug verarbeitete die UTF-16BE-Kodierung und die
maskierten Klammern von PDF-Strings nicht. Erst ein korrekter
String-Tokenizer (Klammerverschachtelung, `\(`/`\)`, Oktal-Escapes) förderte
den Text aus Abschnitt 5.4 zutage. Die Aussagen dieses Berichts beruhen auf
der korrigierten Auswertung.

**Aufräumen, nachgeprüft:** Die drei vom Prüflauf erzeugten PDFs in
`wp-content/uploads/cbd-temp-pdfs/` sind entfernt
(`find … -name "cbd-pdf-*.pdf"` → **0**); der Ordner enthält wieder nur
`.htaccess` und den vorbestehenden Schrift-Cache `mpdf/ttfontdata` (51 Dateien,
Datum März, nicht angefasst). Alle Hilfsskripte lagen im Scratchpad, nicht im
Web-Root. Die Prüfseite wurde neu geladen, ihre DOM-Änderungen aus den
Messungen sind damit weg; Klappzustände wurden nach jedem Eingriff einzeln
zurückgesetzt.
