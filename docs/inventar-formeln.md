# Formelbestand der Website — Inventar

_Erhoben am 2026-09-06 für `AP-1.3` aus `PLAN-Formeln-als-Vektor-im-PDF.md`.
Rein lesend; keine Produktivdatei durch diese Erhebung verändert._

Dieses Inventar beantwortet eine einzige Frage: **Welche Makros und Zeichen
kommen in den Formeln dieser Website wirklich vor, und was braucht MathJax,
um sie alle zu setzen?**

---

## 1. Wie erhoben wurde

Aus `{prefix}_posts` alle veröffentlichten Seiten und Beiträge mit einem
`$`-Zeichen im Inhalt gezogen (246 von 359) und mit **denselben Mustern**
zerlegt, die `CBD_LaTeX_Parser` benutzt — einschließlich der Leerzeichenregel
für `$…$` (`CLAUDE.md`, Abschnitt „Die Leerzeichenregel"), sonst zählte man
Preisangaben als Formeln.

**Eine Falle dabei, die zuerst zugeschnappt ist:** `post_content` speichert
`<` und `>` als `&lt;`/`&gt;`. Wer roh ausliest, hält 50 völlig gültige
Formeln wie `K &gt;&gt; 1` für Fehler. Der Parser löst die Entitäten in
`normalize_formula_text()` auf; die Erhebung muss das nachbilden.

Danach jede eindeutige Formel durch MathJax gesetzt und **MathJax selbst**
benennen lassen, welche Schriftdateien es dafür anfordert — statt sie aus
den vorkommenden Zeichen zu erraten. Das ist wesentlich: **Dieselbe Glyphe
braucht je nach Schriftlage eine andere Datei** (aufrecht `latin`, fett
`latin-b`, kursiv `latin-i`).

---

## 2. Der Bestand in Zahlen

| | |
|---|---|
| Seiten mit `$` im Inhalt | **246** (von 359 veröffentlichten) |
| davon Seiten mit erkannten Formeln | **234** |
| Formelvorkommen | **5655** |
| eindeutige Formeln | **3096** |
| verschiedene Makros | **84** |
| verschiedene Sonderzeichen | **16** |

### Die häufigsten Makros

> Gezählt wird hier, in **wie vielen der 3096 eindeutigen Formeln** ein
> Makro vorkommt — nicht, wie oft es im Bestand steht. Nach Häufigkeit
> gewichtet verschieben sich die Zahlen (`\mathrm` 2731, `\text` 2317,
> `\xrightarrow` 89) und die beiden häufigsten tauschen die Plätze.

| Makro | Vorkommen | | Makro | Vorkommen |
|---|---|---|---|---|
| `\text` | 2008 | | `\xrightarrow` | **86** |
| `\mathrm` | 1726 | | `\alpha` | 71 |
| `\cdot` | 513 | | `\lambda` | 61 |
| `\frac` | 396 | | `\qquad` | 61 |
| `\Delta` | 206 | | `\rightleftharpoons` | 50 |
| `\rightarrow` | 196 | | `\beta` | 34 |
| `\approx` | 128 | | `\lg` | 32 |
| `\longrightarrow` | 123 | | `\delta` | 31 |
| `\quad` | 108 | | `\sqrt` | 20 |

**`\xrightarrow` kommt in 86 verschiedenen Formeln vor (89 Vorkommen im Bestand).** Das ist genau das Konstrukt, an dem
mPDF in `AP-1.2` gescheitert ist (MathJax streckt es mit einem
verschachtelten `<svg>`) — die dort eingebaute dritte Umformung betrifft
also realen Inhalt, keine Testkonstruktion.

### Schriftlagen

| Makro | Vorkommen |
|---|---|
| `\text` | 2008 |
| `\mathrm` | 1726 |
| `\mathbf` | 4 |
| `\textbf` | 3 |

Alles Übrige steht im Mathe-Modus und wird damit **kursiv** gesetzt.

### Die 16 Sonderzeichen

| Zeichen | Codepunkt | Vorkommen | | Zeichen | Codepunkt | Vorkommen |
|---|---|---|---|---|---|---|
| `°` | U+00B0 | 102 | | `‡` | U+2021 | 7 |
| `ä` | U+00E4 | 61 | | `ü` | U+00FC | 7 |
| `–` | U+2013 | 23 | | `α` | U+03B1 | 3 |
| `ö` | U+00F6 | 17 | | `β` | U+03B2 | 3 |
| `Ä` | U+00C4 | 10 | | `₃` | U+2083 | 2 |
| `→` | U+2192 | 9 | | `≡` | U+2261 | 2 |
| `ß` | U+00DF | 7 | | `₂` `⇌` `µ` | | je 1 |

---

## 3. Kann MathJax den Bestand setzen? — ja, bis auf fünf Stellen

| Ergebnis (Lauf unter Node) | Anzahl |
|---|---|
| gesetzt | **3093** |
| Fehler (`merror`) | **3** |
| Ausnahmen | 0 |

> **Richtiggestellt am 2026-09-06 nach dem Review von Phase 1 (Befund 5).**
> Hier stand zuerst: „Die drei Fehlschläge sind alle derselbe Fall:
> `\ce{…}` aus mhchem." **Das war falsch.** Die Messdatei sagt etwas
> anderes, und ich habe alle fünf Stellen einzeln nachgeprüft — in beiden
> Maschinen.

### Was tatsächlich scheitert

**(a) Zwei Formeln mit einem echten LaTeX-Fehler**, beide auf **Seite 872**:

```
\Delta G^0' \approx -9790~\text{kJ/mol}
\Delta G^0' \approx 106 \times 30,5~\text{kJ/mol} = 3233~\text{kJ/mol}
```

| Maschine | Meldung |
|---|---|
| MathJax | `merror`: „Prime causes double exponent: use braces to clarify" |
| KaTeX (heute, am Bildschirm) | `ParseError`: „Double superscript" |

`G^0'` ist zweimal hochgestellt — einmal die `0`, einmal der Strich. **Das
ist ein Inhaltsfehler, kein Werkzeugproblem, und er zeigt sich heute schon
auf der Website.** Geschrieben als `\Delta G^{0'}` setzen **beide**
Maschinen die Formel korrekt (gegengeprüft).

> **Befund für den Betreiber:** Zwei Stellen auf Seite 872 in `G^{0'}`
> ändern — dann sind sie am Bildschirm und im PDF richtig.

**(b) Drei Formeln mit `\ce{…}` aus mhchem:**

```
\ce{α-Anomer <=>[H+][H2O] offenkettige Form <=>[H+][H2O] β-Anomer}
k_{obs} = k_0 + k_H [\ce{H+}] + k_{OH} [\ce{OH-}]
\ce{-S-S-}
```

**Diese drei erzeugen kein `merror`, sondern `retry`** — MathJax versucht,
die mhchem-Erweiterung nachzuladen, findet sie lokal nicht (404) und bricht
sauber ab. Im Exportweg heißt das: **Rückfall auf den Rasterweg**, die
Formel kommt als Bild ins PDF. Das ist die gutartige Richtung.

**Auch sie sind heute schon kaputt** — das Plugin bindet KaTeX **ohne**
mhchem ein. Gegengeprüft mit `throwOnError: true`: „Undefined control
sequence: `\ce`". Mit der üblichen Einstellung `throwOnError: false`
schluckt KaTeX den Fehler und setzt `\ce{-S-S-}` als **roten Text** —
deshalb fällt es im Alltag kaum auf. Bei drei Vorkommen ist Umschreiben in
gewöhnliches LaTeX der naheliegende Weg.

### Was die Zahl „3093 gesetzt" wirklich sagt — und was nicht

> **Ebenfalls richtiggestellt (Review-Befund 6).**

Der Volllauf zählt ausschließlich, ob `merror` im Ergebnis steht. Damit
beantwortet er die Frage, für die er gebaut wurde — **kennt MathJax die
Makros dieser Website?** — und mehr nicht. Er belegt **nicht**, dass jede
Formel im Exportweg korrekt herauskommt:

- Er lief unter **Node** mit `tex2svgPromise()`, nicht im Browser mit dem
  synchronen `tex2svg()`, das produktiv benutzt wird.
- Er lief **ohne** die drei Umformungen und ohne mPDF.
- **Eine verstümmelte Formel erzeugt kein `merror`.** Genau das war der
  schwerste Fehler dieses Vorhabens: MathJax bricht Inline-Mathematik
  standardmäßig um und liefert dann mehrere `<svg>`-Wurzeln; wer die erste
  nimmt, verliert den Rest — lautlos. Auf der Prüfseite waren **10 von 22**
  Inline-Formeln betroffen (behoben in `AP-1.fix1`).

**Die belastbare Zahl steht in Abschnitt 4 der Prüfmatrix**, und sie wird in
Phase 2 mit der Produktionsmethode neu erhoben: synchroner Aufruf, echte
`isDisplay`-Herkunft, und als Erfolgskriterium nicht „kein merror", sondern
**genau eine `<svg>`-Wurzel, Zeichenobjekte vorhanden, Breite plausibel
gegen die KaTeX-Breite**.

---

## 4. Welche Schriftdateien gebraucht werden — gemessen, nicht geraten

Prüfmatrix im Browser: **alle 16 Sonderzeichen in allen vier vorkommenden
Schriftlagen** (`\text`, `\mathrm`, `\mathbf`, freier Mathe-Modus) plus 36
Makro-Proben = **100 Prüfungen**.

| | |
|---|---|
| ohne jede Nachladung gesetzt | **85 von 100** |
| Nachladung nötig | **15** |

**Die 15 sind ausnahmslos deutsche Umlaute und `ß`** — `ä`, `ö`, `Ä`, `ü`,
`ß`. Alles andere steckt im Grundbestand von `tex-svg.js`: `°`, `–`, `→`,
`⇌`, `≡`, `‡`, `µ`, `α`, `β`, die Tiefstellungen `₂`/`₃` und **sämtliche 36
Makro-Proben**, einschließlich `\xrightarrow`, `\sqrt`, `\sum`, `\int` und
`\left…\right`.

**MathJax hat genau drei Dateien angefordert:**

| Datei | Größe | wofür |
|---|---|---|
| `svg/dynamic/latin.js` | 359 KB | aufrecht (`\text{ä}`, `\mathrm{…}`) |
| `svg/dynamic/latin-b.js` | 299 KB | fett (`\mathbf`, `\textbf`) |
| `svg/dynamic/latin-i.js` | 490 KB | kursiv (Sonderzeichen im Mathe-Modus) |

Alle drei liegen jetzt in `assets/vendor/mathjax/fonts/mathjax-newcm-font/`.
Zusammen mit `tex-svg.js` belegt MathJax damit **2,9 MB** — zum Vergleich:
KaTeX belegt heute 1,5 MB.

> `latin-b.js` wird durch **eine einzige** Fundstelle ausgelöst
> (`\textbf{…ä…}`). 299 KB für ein Vorkommen ist ein schlechtes Verhältnis;
> weggelassen fiele diese eine Formel auf den Rasterweg zurück, sonst nichts.
> Mitgeliefert wird sie trotzdem, weil `\mathbf`/`\textbf` jederzeit in
> neuen Inhalten auftauchen kann und der Rückfall dann still wäre.

### Der Erstkontakt-Effekt — wichtig für den Produktivcode

Beim **ersten** Kontakt mit einem Zeichen aus einer nachgeladenen
Schriftdatei wirft MathJax einmalig `retry — an asynchronous action is
required`, **obwohl die Daten längst geladen sind**. Ein zweiter Aufruf
gelingt. Gemessen: 15 von 100 Proben beim ersten Durchgang, **0 beim
zweiten**.

**Ohne eine Wiederholung fiele je Seitenaufruf die erste Formel mit Umlaut
ohne Not auf den Rasterweg zurück.** `setzeFormelAlsSvg()` in
`assets/js/pdf-server-side.js` wiederholt deshalb genau einmal.

---

## 5. Wenn neue Zeichen dazukommen

Führt jemand Formeln mit bisher nicht vorkommenden Zeichen ein (kyrillisch,
hebräisch, weitere Griechisch-Varianten), braucht MathJax weitere Dateien.
Verfügbar sind im Paket `@mathjax/mathjax-newcm-font` unter `svg/dynamic/`
unter anderem `greek.js`, `cyrillic.js`, `arrows.js`, `math.js`,
`monospace.js`, `calligraphic.js`, `fraktur.js`.

**Das Verfahren ist dasselbe wie hier — nicht raten, MathJax fragen:**

1. Die betroffenen Formeln in einer Seite mit geladenem MathJax synchron
   durch `MathJax.tex2svg()` schicken.
2. Wirft es `retry`, fehlt eine Schriftdatei.
3. Welche, verrät `performance.getEntriesByType('resource')` — MathJax
   fordert sie über den **lokalen** Pfad an (siehe
   `assets/vendor/mathjax/HERKUNFT.md`).
4. Die genannte Datei aus dem Paket übernehmen und in die Liste
   `erweiterungen` in `ladeMathJax()` eintragen.

**Der Weg ist fail-closed:** Fehlt eine Datei, scheitert das Setzen dieser
einen Formel und sie fällt auf den Rasterweg zurück. Es geht **nie** eine
Anfrage an ein CDN — das verhindert der lokal gesetzte `loader.paths.fonts`.
