# Diagnose: LaTeX-Formeln in Listen im Accordion-Panel

_Erstellt am: 2026-08-24 · AP-1.1 aus `docs/PLAN-PDF-Notizen-und-Listenformeln.md`_

Ergebnisbericht der Live-Diagnose. Grundlage für AP-1.2 und AP-1.3.
Alles hier ist **gemessen**, nicht abgeleitet — Messwerkzeug war das
vorhandene Skript `docs/pruefung-formelfarbe.js`, ausgeführt in der
Browser-Konsole auf dem lokalen Testserver (`fos.localhost:8080`,
WordPress 7.1, Theme „FOS Online Schulbuch", CDB-Designer 3.1.100,
„Eigene WP Blocks" 1.1.8).

## 0. Das wichtigste Ergebnis in drei Sätzen

1. **Der gemeldete Fehler ist auf dem aktuellen Stand der beiden Repositories
   nicht mehr reproduzierbar** — weder in Listen noch in Absätzen, weder im
   Hell- noch im Dunkelmodus, in keinem von zwölf gemessenen Formelvorkommen
   über acht Blocktypen.
2. Der Fehler ist identisch mit einem **bereits behobenen** Befund:
   `AP-1.fix1` aus `docs/PLAN-Vier-Erweiterungen.md`, behoben am 2026-08-16
   mit Commit `b854060` im Repo „Eigene WP Blocks". Die Ursache lag **nicht**
   in `blocks/accordion/style.css`, sondern in `assets/css/blocks.css`.
3. Er ist auf der Produktivseite mit hoher Wahrscheinlichkeit deshalb noch
   sichtbar, weil `assets/css/blocks.css` **nicht** Teil eines Block-ZIPs ist,
   sondern der Plugin-Basis — und die Projektkonvention sieht vor, nur
   einzelne Block-ZIPs auszurollen („Empty Plugin Base … Never needs to be
   updated unless core functionality changes").

**Folge für AP-1.2: Es gibt in `blocks/accordion/style.css` nichts zu
reparieren.** Ausführliche Empfehlung in Abschnitt 6.

## 1. Testinhalt

Angelegt über ein temporäres PHP-Skript im Webroot (kein `wp-admin`-Login,
Nicht-Ziel Abschnitt 2 des Plans); das Skript wurde nach dem Test gelöscht.

- **Seite:** „AP-1.1 Formeldiagnose Liste vs Absatz", ID **5595**, Status
  `publish`
- **URL:** `http://fos.localhost:8080/ap-1-1-formeldiagnose-liste-vs-absatz/`
- **Aufbau:** `container-block-designer/container` (Design `infotext_k1`) →
  `modular-blocks/accordion` (`headingLevel: 3`) mit zwei Klappzeilen

Die Seite bleibt bewusst stehen — AP-1.2 und AP-1.4 bauen laut Plan darauf auf.

Enthaltene Fälle (Formel jeweils `$V = 20{,}5\,\text{mL}$`):

| # | Fall | Blocktyp |
|---|---|---|
| 0 | Absatzfall | `core/paragraph` |
| 1 | **Listenfall** | `core/list` → `core/list-item` |
| 2/3 | verschachtelte Liste (außen/innen) | `core/list` in `core/list` |
| 3 | nummerierte Liste | `core/list {"ordered":true}` |
| 4 | Display-Formel im Absatz | `$$…$$` in `core/paragraph` |
| 5 | Display-Formel im Listenpunkt | `$$…$$` in `core/list-item` |
| 6 | Zitat | `core/quote` |
| 7 | Tabellenzelle | `core/table` → `td` |
| 8 | Bild-/Tabellenunterschrift | `figcaption.wp-element-caption` |
| 9 | Überschrift im Panel | `core/heading` (H4) |
| 10 | Spalte | `core/columns` → `core/column` |
| 11 | Kontrolle **außerhalb** des Accordions | `core/paragraph` |
| — | Negativkontrolle | `core/code` und `core/preformatted` mit `\(x\)` |

Zusätzlich geprüft wurde **echter Produktivinhalt** auf demselben Server:
Seite 5422 „Stoffeigenschaften messen — Erwartungshorizont" (Accordion mit
Listen voller Formeln), 22 gemessene Formeln.

## 2. Messung im Ist-Zustand: kein Fehler

Diagnoseskript, Testseite 5595, Hellmodus (`<html>` ohne `data-theme`):

```
=== Formelfarbe: Diagnose ===
Aufgeklappt: 1 Bereiche
Gefundene Formeln: 3
… alle: "opacity":"1", "color":"rgb(51, 51, 51)"
--- Urteil ---
ohne Klasse cbd-latex-rendered : 0 von 3
opacity kleiner 0.9            : 0
>> Ursache (a) ausgeschlossen — opacity ist überall 1.
Keine blasse Formel gefunden — alle sehen normal aus.
=== Ende ===
```

Auf der ausgebauten Testseite (12 Formeln) und auf der Produktivseite 5422
(22 Formeln) dasselbe Bild:

| Fall | Eltern-Element | gemessene Farbe (hell) | gemessene Farbe (dunkel) |
|---|---|---|---|
| **Absatz im Panel** | `p.wp-block-paragraph` | `rgb(51, 51, 51)` | `rgb(232, 232, 232)` |
| **Listenpunkt im Panel** | `li` | `rgb(51, 51, 51)` | `rgb(232, 232, 232)` |
| verschachtelter Listenpunkt | `li` | `rgb(51, 51, 51)` | `rgb(232, 232, 232)` |
| Tabellenzelle | `td` | `rgb(51, 51, 51)` | `rgb(232, 232, 232)` |
| Unterschrift | `figcaption` | `rgb(102, 102, 102)` | `rgb(160, 160, 160)` |
| Überschrift H4 | `h4` | `rgb(51, 51, 51)` | `rgb(232, 232, 232)` |
| außerhalb des Accordions | `p` | `rgb(51, 51, 51)` | `rgb(51, 51, 51)` |

Negativkontrolle bestanden: In `core/code` und `core/preformatted` existiert
**kein** `.cbd-latex-formula`-Element, `\(x\)` steht unverändert als Text
(`KEIN_LATEX_BLOCK` in `class-latex-parser.php` greift).

Der Dunkelmodus-Wert `rgb(232, 232, 232)` ist **korrekt**, nicht fehlerhaft:
Das Theme schaltet dort Fläche und Schrift gemeinsam um.

## 3. Die gewinnende CSS-Regel — Ist-Zustand, beide Fälle

Gemessen über `element.matches(selectorText)` gegen alle lesbaren
Stylesheets, Kette von innen nach außen. **Listenfall und Absatzfall sind
strukturell identisch** — es gibt keine DOM-Diskrepanz zwischen `<p>` und
`<li>`:

### Absatzfall (Formel Nr. 0)

| Ebene | Element | Farbe | gewinnende Regel |
|---|---|---|---|
| 1 | `span.katex` | `rgb(51,51,51)` | `latex-formulas.css:89-90` · `.cbd-latex-formula .katex, .cbd-latex-formula .katex *` → `color: inherit !important` |
| 2 | `span.cbd-latex-content` | `rgb(51,51,51)` | `latex-formulas.css:56,61` · `.cbd-latex-content` → `color: inherit` |
| 3 | `span.cbd-latex-formula` | `rgb(51,51,51)` | `latex-formulas.css:24` · `.cbd-latex-formula` → `color: inherit` |
| 4 | **`p.wp-block-paragraph`** | **`rgb(51,51,51)`** | **`blocks/accordion/style.css:235-245`** · `.mb-accordion .mb-accordion__content p` (u. a.) → `color: var(--color-text-primary, #333333)` — Spezifität 0-2-1 |
| 5 | `div.mb-accordion__content` | `rgb(51,51,51)` | `assets/css/blocks.css:106-110` · `[class*="wp-block-modular-blocks"] [class*="content"]:not([class*="cbd-"])` → `color: var(--modular-blocks-text)` — Spezifität 0-3-0 |

### Listenfall (Formel Nr. 1)

| Ebene | Element | Farbe | gewinnende Regel |
|---|---|---|---|
| 1 | `span.katex` | `rgb(51,51,51)` | `latex-formulas.css:89-90` · `.cbd-latex-formula .katex *` → `color: inherit !important` |
| 2 | `span.cbd-latex-content` | `rgb(51,51,51)` | `latex-formulas.css:56,61` · `.cbd-latex-content` → `color: inherit` |
| 3 | `span.cbd-latex-formula` | `rgb(51,51,51)` | `latex-formulas.css:24` · `.cbd-latex-formula` → `color: inherit` |
| 4 | **`li`** | **`rgb(51,51,51)`** | **`blocks/accordion/style.css:235-245`** · `.mb-accordion .mb-accordion__content li` → `color: var(--color-text-primary, #333333)` — Spezifität 0-2-1 |
| 5 | `ul.wp-block-list` | `rgb(51,51,51)` | keine eigene Regel — geerbt |
| 6 | `div.mb-accordion__content` | `rgb(51,51,51)` | `assets/css/blocks.css:106-110` (wie oben) |

**Es gibt in der Ausgabe des Diagnoseskripts kein
`>>> HIER SPRINGT DIE FARBE UM`** — die Farbe bleibt über die gesamte Kette
`rgb(51, 51, 51)`.

Die Regel wird nicht als externe Datei geladen, sondern von WordPress als
Inline-`<style>` ausgeliefert (`wp_maybe_inline_styles`); Quelle ist der
Build-Artefakt `blocks/accordion/style-index.css`, den `block.json` im Feld
`"style"` referenziert. Er wurde gegen die Quelle geprüft und ist aktuell
(siehe Abschnitt 5).

### Zusatzmessung: Die Aufzählung trägt derzeit gar nichts

Wird die Regel `style.css:235-245` zur Laufzeit vollständig abgeschaltet
(`rule.style.removeProperty('color')`), bleiben **alle zwölf Formeln
unverändert lesbar** — hell `rgb(51,51,51)`, dunkel `rgb(232,232,232)`.
Grund: `blocks.css:106-110` färbt `.mb-accordion__content` bereits auf
`var(--modular-blocks-text)`, und diese Variable folgt seit
`blocks.css:9` dem Theme (`var(--color-text-primary, #1e1e1e)`).

Die Tag-Aufzählung ist auf dem heutigen Stand also **redundant**. Sie schadet
nicht, sie rettet aber auch nichts.

## 4. Rekonstruktion des gemeldeten Fehlers

Der Fehler ließ sich **exakt reproduzieren**, indem der Stand von
`assets/css/blocks.css` **vor** Commit `b854060` (2026-08-16) zur Laufzeit
nachgestellt wurde. Dieser Stand hatte zwei Eigenschaften, die beide mit
diesem Commit entfielen:

1. einen `@media (prefers-color-scheme: dark)`-Block, der
   `--modular-blocks-text` auf `#ffffff` setzte
2. die `[class*="content"]`-Regeln **ohne** die Ausnahme
   `:not([class*="cbd-"])`

Nachgestellt als zusätzliches Stylesheet (die Aufzählung in
`accordion/style.css` blieb dabei nachweislich unangetastet):

```css
:root { --modular-blocks-text: #ffffff; }
.modular-block-content,
[class*="wp-block-modular-blocks"] [class*="content"] {
    line-height: 1.6;
    color: var(--modular-blocks-text);
}
```

Messergebnis — **11 von 12 Formeln weiß auf weißem Grund**, die zwölfte
(außerhalb des Accordions) unberührt:

| Fall | Eltern-Element | Farbe des Elternelements | Farbe der **Formel** |
|---|---|---|---|
| Absatz im Panel | `p` | `rgb(51, 51, 51)` | **`rgb(255, 255, 255)`** |
| Listenpunkt im Panel | `li` | `rgb(51, 51, 51)` | **`rgb(255, 255, 255)`** |
| verschachtelter Listenpunkt | `li` | `rgb(51, 51, 51)` | **`rgb(255, 255, 255)`** |
| Tabellenzelle | `td` | `rgb(255, 255, 255)` | **`rgb(255, 255, 255)`** |
| Überschrift H4 | `h4` | `rgb(51, 51, 51)` | **`rgb(255, 255, 255)`** |
| außerhalb des Accordions | `p` | `rgb(51, 51, 51)` | `rgb(51, 51, 51)` |

Das Diagnoseskript benennt in diesem Zustand die Ursache selbst:

```
   span.cbd-latex-content  = rgb(255, 255, 255)
        [latex-formulas.css]  .cbd-latex-content  ->  inherit
        [blocks.css-Vorfix]   .modular-block-content,
                              [class*="wp-block-modular-blocks"] [class*="content"]
                              ->  var(--modular-blocks-text)
```

### Die Ursache in einem Satz

Der weit gefasste Selektor `[class*="content"]` in `assets/css/blocks.css`
trifft auch den inneren Formel-Span **`.cbd-latex-content`** des
CDB-Designers und überschreibt dessen `color: inherit` (Spezifität 0-2-0
gegen 0-1-0) mit `--modular-blocks-text`, das der frühere
`prefers-color-scheme`-Block auf Weiß stellte — während das Theme seinen
Hintergrund weiß ließ.

### Der gemeldete Unterschied „Liste kaputt, Absatz in Ordnung" besteht nicht

Gemessen sind Absatz und Listenpunkt **gleichermaßen** betroffen: In beiden
Fällen bleibt der umgebende Text dunkel (die Aufzählung `style.css:235-245`
rettet `p` **und** `li` gleichermaßen), und in beiden Fällen wird allein die
Formel weiß. Genau so steht es auch im Kommentar, der den damaligen Fix
begleitet (`assets/css/blocks.css:196-199`): der Absatz werde
zurückgeholt, „eine Formel ist keines davon und blieb weiß".

Die Wahrnehmung „nur in Listen" erklärt sich vermutlich daraus, dass eine
fehlende Inline-Formel in einem Fließtext-Absatz als Lücke leicht übersehen
wird, während sie in einem kurzen Listenpunkt den halben Punkt leer
erscheinen lässt. **Eine strukturelle Diskrepanz zwischen `<p>` und `<li>`
gibt es nicht** — die im AP-1.1-Text vermutete Spezifitäts-/Kaskadenfrage
zwischen diesen beiden Fällen ist damit ausgeschlossen.

### Warum es auf der Produktivseite trotzdem noch auftreten kann

`assets/css/blocks.css` liegt **nicht** in einem Block-ZIP, sondern in der
Plugin-Basis. Die Ausrollkonvention des Projekts sieht vor, im Regelbetrieb
nur einzelne Block-ZIPs hochzuladen und die Basis unangetastet zu lassen
(`CLAUDE.md`, „Plugin Distribution Strategy": die Basis „never needs to be
updated unless core functionality changes"). Eine Installation, die seit dem
2026-08-16 zwar `accordion.zip`, aber keine neue Basis erhalten hat, führt
den alten `blocks.css`-Stand weiter — und zeigt den Fehler dann bei **jedem
Besucher mit dunklem Systemdesign**, unabhängig vom Theme-Umschalter.

Das erklärt zwanglos, warum der Fehler gemeldet wird, obwohl er im
Quellstand seit acht Tagen behoben ist.

## 5. Nebenbefund: Ausgelieferte Artefakte sind aktuell

Vor jeder Messung gemäß Plan-Abschnitt 3 geprüft:

- `blocks/accordion/block.json` referenziert im Feld `"style"` tatsächlich
  `style-index.css` (Build-Artefakt), nicht `style.css`.
- Das auf dem Testserver liegende `style-index.css` enthält die vollständige
  Aufzählung inklusive `…__content li` und die Änderungen aus `6fdd1ea`
  (AP-3.0, Custom-Property-Buttons) — es ist gegenüber der Quelle **nicht
  veraltet**. Die Messungen zeigen daher den echten Quellstand, kein
  „fälschlich bestanden".
- Dasselbe gilt für `plugin-zips/accordion.zip`: enthält die Aufzählung mit
  `li`.
- `assets/css/blocks.css` auf dem Testserver ist gegenüber dem Repo nur um
  den `[data-theme="dark"]`-Schattenblock vom 2026-08-24 älter; die hier
  entscheidende Ausnahme `:not([class*="cbd-"])` **ist** vorhanden.

## 6. Empfehlung für AP-1.2

### 6.1 In `blocks/accordion/style.css` ist nichts zu ändern

Die Datei enthält keinen Fehler. Die Regel `style.css:235-245` deckt `li`
bereits ab, sie gewinnt in beiden Fällen, und ihr gemessener Wert ist
korrekt. Ein Fix dort würde ein Symptom behandeln, das an dieser Stelle
nicht entsteht.

**Empfohlenes Vorgehen für AP-1.2:** Status auf ☑ mit der Begründung
„entfällt — die Ursache lag in `assets/css/blocks.css` und ist seit Commit
`b854060` (2026-08-16, AP-1.fix1 aus `PLAN-Vier-Erweiterungen.md`) behoben;
gemessen in AP-1.1, kein Fehler im Ist-Zustand". Das entspricht dem
Risiko-Eintrag „AP-1.1 findet eine Ursache, die NICHT durch eine
CSS-Änderung an `blocks/accordion/style.css` lösbar ist" in Abschnitt 5 des
Plans; der Orchestrator legt dafür nach Regel 20 ein `AP-1.fix1` an.

### 6.2 Was stattdessen zu tun ist: ausrollen, nicht umbauen

Die eigentliche offene Arbeit ist eine **Ausroll-, keine Codeaufgabe**:
Die Plugin-Basis von „Eigene WP Blocks" muss auf der Produktivinstallation
mit dem aktuellen `assets/css/blocks.css` versorgt werden. Ohne das bleibt
der Fehler dort bestehen, egal was in `blocks/accordion/style.css` steht.

Bevor dafür Aufwand entsteht, sollte **am Produktivsystem gemessen werden**,
ob die Vermutung aus Abschnitt 4 zutrifft. Zwei Zeilen in der
Browser-Konsole auf einer betroffenen Seite genügen:

```js
// Erwartung bei aktuellem blocks.css: leeres Ergebnis.
// Trifft die Vermutung zu, erscheint die Regel OHNE :not([class*="cbd-"]).
Array.from(document.styleSheets).flatMap(s => { try { return Array.from(s.cssRules); } catch (e) { return []; } })
  .filter(r => r.selectorText && r.selectorText.includes('[class*="content"]'))
  .map(r => r.selectorText);
```

Ergänzend `getComputedStyle(document.querySelector('.cbd-latex-content')).color`
auf einer Seite mit Accordion-Formeln, einmal mit dunklem Systemdesign.

### 6.3 Bewertung des Wrapper-Grundfarbe-Ansatzes aus Abschnitt 4

**Der Ansatz passt nicht zur gemessenen Ursache und würde sie nicht
beheben.** Begründung:

- Der Wrapper-Ansatz setzt die Grundfarbe **einmal** auf
  `.mb-accordion-row__panel-inner` und lässt alle Kinder erben. Genau diese
  Vererbung ist im Fehlerfall aber **unterbrochen**: `.cbd-latex-content`
  bekommt eine **eigene**, direkt zugewiesene Farbe aus
  `blocks.css` (Spezifität 0-2-0). Eine geerbte Farbe von einem Vorfahren
  verliert gegen jede direkte Zuweisung am Element selbst, unabhängig von
  der Spezifität des Vorfahren-Selektors.
- Ein Wrapper auf `.mb-accordion-row__panel-inner` läge zudem **innerhalb**
  von `.mb-accordion__content` und damit hinter der Stelle, an der die Farbe
  im Fehlerfall bereits umgeschlagen ist.
- Die Prämisse des Ansatzes — „strukturell immun gegen jeden zukünftigen,
  noch nicht enumerierten Blocktyp" — ist unabhängig davon durch die
  Zusatzmessung in Abschnitt 3 relativiert: `blocks.css:106-110` leistet
  diese generische Grundfarbe **bereits heute** für den gesamten
  Panel-Inhalt, und zwar eine Ebene weiter außen.

Der Ansatz ist damit nicht falsch gedacht, aber gegenstandslos: Er löst ein
Enumerationsproblem, das im gemessenen Fehlerbild keine Rolle spielt.

**Wenn** die Aufzählung dennoch angefasst werden soll, dann nicht als Fix,
sondern als Aufräumarbeit — und dann mit dem Wissen aus Abschnitt 3, dass
ihr Entfallen heute messbar folgenlos wäre. Das ist eine eigene
Entscheidung außerhalb dieses Fehlerbilds und gehört in ein eigenes AP.

### 6.4 Folge für AP-1.3

AP-1.3 („Bedingter Fix in CDB-Designer") **entfällt ebenfalls**: In
`assets/css/latex-formulas.css` und `includes/class-latex-parser.php` wurde
kein Fehler gemessen. `.cbd-latex-content`s `color: inherit`
(`latex-formulas.css:56,61`) ist genau richtig — es war das Opfer der
fremden Regel, nicht deren Ursache.

Eine **Härtung** wäre denkbar, ist aber ausdrücklich **nicht empfohlen**:
`.cbd-latex-content { color: inherit !important; }` würde das Plugin gegen
fremde, zu weit gefasste Selektoren immunisieren, verstößt aber gegen das
Prinzip, kein Wettrüsten mit `!important` zu beginnen (dieselbe Begründung
steht bereits in `blocks/accordion/style.css:211-220`). Falls das erwogen
wird, gehört es in ein eigenes AP mit eigener Abwägung.

## 7. Nachweise zu den Akzeptanzkriterien

| Kriterium | Nachweis |
|---|---|
| Bericht liegt unter `docs/diagnose-latex-listen-2026-08-24.md` und enthält für Listen- **und** Absatzformel die vollständige Farbkette mit gewinnender Regel (Datei + Selektor + Wert) | Abschnitt 3 (Ist-Zustand, beide Fälle einzeln) und Abschnitt 4 (Fehlerzustand, beide Fälle einzeln) |
| Bericht benennt konkret, welche Datei und welcher Selektor in AP-1.2 geändert werden muss | Abschnitt 6.1 — **keine**, mit Begründung; die Ursache liegt in `assets/css/blocks.css:106-110`, dort bereits behoben |
| Bericht bewertet explizit, ob der Wrapper-Grundfarbe-Ansatz zur Ursache passt | Abschnitt 6.3 — passt **nicht**, mit drei einzelnen Gründen |
| Smoke-Test: Skript läuft ohne JS-Fehler durch und gibt „=== Ende ===" aus | Abschnitt 2, vollständige Ausgabe; drei Durchläufe (Testseite vorher/nachher, Produktivseite 5422), jeder mit „=== Ende ===" |
| Prüfschritt: genannte Zeilen/Selektoren in den CSS-Dateien manuell verifiziert | `accordion/style.css:235-245`, `blocks.css:9`, `:106-110`, `:184-207`, `latex-formulas.css:24`, `:56,61`, `:89-90` — alle geöffnet und gegen die Konsolenausgabe abgeglichen |

## 8. Verwendete Fundstellen

| Datei | Zeilen | Inhalt |
|---|---|---|
| `Plugins/Eigene WP Blocks/blocks/accordion/style.css` | 235-245 | Tag-Aufzählung, setzt `color: var(--color-text-primary, #333333)` |
| `Plugins/Eigene WP Blocks/blocks/accordion/style.css` | 211-220 | Kommentar „Kontrast-Falle", erklärt die Spezifitätswahl ohne `!important` |
| `Plugins/Eigene WP Blocks/assets/css/blocks.css` | 9 | `--modular-blocks-text: var(--color-text-primary, #1e1e1e)` |
| `Plugins/Eigene WP Blocks/assets/css/blocks.css` | 88-110 | weit gefasster `[class*="content"]`-Selektor **mit** Ausnahme `:not([class*="cbd-"])` |
| `Plugins/Eigene WP Blocks/assets/css/blocks.css` | 184-207 | „KEIN Dunkelmodus — bitte nicht wieder einbauen", dokumentiert genau diesen Fehler und seinen Fix |
| `Plugins/CDB-Designer/assets/css/latex-formulas.css` | 24 | `.cbd-latex-formula { color: inherit }` |
| `Plugins/CDB-Designer/assets/css/latex-formulas.css` | 56, 61 | `.cbd-latex-content { color: inherit }` — das überschriebene Opfer |
| `Plugins/CDB-Designer/assets/css/latex-formulas.css` | 89-90 | `.cbd-latex-formula .katex *  { color: inherit !important }` |
| Commit `b854060` (Eigene WP Blocks) | 2026-08-16 | „AP-1.fix1: Weisse Schrift im Dunkelmodus abgestellt" |
| Commit `a2737ff` (Eigene WP Blocks) | 2026-08-16 | „AP-1.fix4: Ausnahme auf die Nachbarregeln gezogen (Befund G5)" |
