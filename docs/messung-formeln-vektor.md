# Formeln im PDF: Vektor gegen Raster — die Messungen

Erhoben in Phase 3 von `PLAN-Formeln-als-Vektor-im-PDF.md` (`AP-3.1`,
`AP-3.2`), 2026-09-07, auf dem Testserver.

## Was verglichen wurde

Fünf echte Inhaltsseiten mit unterschiedlichem Formelcharakter, je einmal
mit dem **Vektorweg** (MathJax setzt die Formel, sie geht als SVG ins PDF)
und einmal mit dem **Rasterweg** (html2canvas fotografiert die
KaTeX-Darstellung).

| Seite | Titel | Charakter |
|---|---|---|
| 3539 | Michaelis-Menten-Kinetik | viele Brüche (33), Indizes (46), Formeln in Blocktiteln (7) |
| 860 | Die Oxidative Decarboxylierung | lange Reaktionsgleichungen, 20 gestreckte Pfeile |
| 5824 | Oxidationszahlen des Kohlenstoffs | Formeln in Tabellen (8 Tabellen) |
| 5681 | Löslichkeit und Löslichkeitsprodukt | Wurzel, Tabellen, Formel im Blocktitel |
| 1636 | Elektrochemische Grundlagen | gemischt: 17 Brüche, 18 Pfeile, Tabelle mit Formeln je Zelle |

**Ausgewählt wurden je Seite die Container mit Formeln und ohne
interaktives Element.** Der Bildschirmfoto-Weg für interaktive Elemente ist
in beiden Wegen derselbe Code, hat mit Formeln nichts zu tun und ist im
Rasterlauf mehrfach minutenlang hängen geblieben. Über die fünf Seiten
bleiben dadurch **432 von 465** Formeln im Vergleich (93 %).

## Ergebnis je Seite

| Seite | Formeln | Vektor | Raster | Größe |
|---|---|---|---|---|
| 3539 | 89 / 89 | 8 S · **0 Bilder** · 772 Zeichenobjekte · 190 888 B | 9 S · 106 Bilder · 140 Obj · 333 671 B | **−43 %** |
| 860 | 130 / 130 | 19 S · 2 Bilder¹ · 1639 Obj · 657 532 B | 19 S · 138 Bilder · 308 Obj · 942 040 B | **−30 %** |
| 5824 | 70 / 70 | 9 S · **0 Bilder** · 886 Obj · 170 439 B | 9 S · 74 Bilder · 408 Obj · 278 522 B | **−39 %** |
| 5681 | 67 / 67 | 8 S · **0 Bilder** · 838 Obj · 210 649 B | 9 S · 96 Bilder · 261 Obj · 327 720 B | **−36 %** |
| 1636 | 76 / 76 | 9 S · **0 Bilder** · 1153 Obj · 253 649 B | 10 S · 138 Bilder · 237 Obj · 496 946 B | **−49 %** |

¹ die zwei Bilder auf Seite 860 sind ein echtes Inhaltsbild (Farbbild +
Alphamaske), keine Formel.

**Kein Rasterbild für eine Formel, auf keiner der fünf Seiten.** Alle
432 Formeln wurden gesetzt, keine fiel zurück, keine fehlt.

**Die Textebene ist auf allen fünf Seiten wortgleich** — nach
Normalisierung des Leerraums null Wortunterschiede gegenüber dem
Rasterlauf. Der Zeilenumbruch verschiebt sich stellenweise, weil die
Vektorformeln minimal andere Maße haben als die Rasterbilder.

## Dauer

**Ohne die Messbedingungen ist eine Dauer wertlos** — deshalb zuerst die
Bedingungen:

| | |
|---|---|
| Rechner | Windows-Entwicklungsrechner, lokaler Apache/PHP-Testserver, keine Fremdlast |
| Browser | Chromium, Prüf-Browser der Entwicklungsumgebung |
| Drosselung | keine künstliche; **aber**: Chrome drosselt Zeitgeber in einem Tab im **Hintergrund** bis auf einen Aufruf je Minute, und `html2canvas` liefert dort für einen Teil der Formeln leere Leinwände. **Alle Dauern unten stammen aus einem Tab im Vordergrund.** |
| Auswahl | dieselbe wie oben (Container mit Formeln, ohne interaktives Element) |
| Gemessen wird | vom Klick bis zum Erscheinen der Schaltfläche „PDF speichern", also einschließlich Serverlauf |

| Seite | Vektor | Raster | Unterschied |
|---|---|---|---|
| 5824 (70 Formeln) | 16,49 / 15,98 / 15,00 s → **Median 15,98 s** | 25,41 / 23,81 / 24,41 s → **Median 24,41 s** | Vektor **35 % schneller** |
| 860 (130 Formeln) | **29,7 s** (ein Lauf) | **80,8 / 91,4 s** | Vektor rund **3× schneller** |

**Abweichung von der Vorgabe „je drei Läufe für fünf Seiten", offen
gesagt:** Drei saubere Läufe je Weg gibt es nur für Seite 5824, für Seite
860 drei Läufe insgesamt. Grund ist die Umgebung: Jeder Rasterlauf braucht
einen Tab im Vordergrund, und die Sichtbarkeit des Prüf-Browsers lässt sich
hier nicht festhalten — sie kippt zwischen den Aufrufen. Die Streuung der
sauberen Läufe liegt bei ±5 %, der gemessene Unterschied zwischen den
Wegen bei 35 % bis 200 %; die Aussage hängt also nicht an der Zahl der
Wiederholungen.

**Die Ersparnis wächst mit der Formelzahl** — erwartbar, denn der
Rasterweg zahlt je Formel einen html2canvas-Lauf, der Vektorweg setzt sie
in rund 10 ms.

## Dateigröße des Plugin-Pakets

| | |
|---|---|
| Neue Dateien | `assets/vendor/mathjax/` (11 Dateien), `tools/test-svg-aufbereitung.js`, `tools/fixtures/mathjax/` (6 Dateien) |
| Roh | 2,93 MB |
| **Im ZIP (deflate)** | **+0,90 MB** |

Davon ist `tex-svg.js` mit 1,76 MB roh der größte Anteil. Zum Vergleich:
`assets/lib/` (html2canvas-pro, jsPDF, modern-screenshot) belegt 0,61 MB,
KaTeX rund 1,5 MB.

**Die Reißleine aus A2 (`fontCache: 'local'`) war nicht nötig** — sie käme
erst bei einer Verschlechterung von mehr als 50 % in Betracht, und
gemessen ist das Gegenteil: kleinere PDFs, kürzere Läufe. (Sie scheidet
ohnehin aus, weil `CBD_SVG_Sanitizer` `<use>` nicht durchlässt.)

## Was beim Ansehen aufgefallen ist

Die Gegenüberstellung liegt als Bild vor:
`docs/bilder/formeln-raster-gegen-vektor.png` — fünf charakteristische
Stellen, links der alte Rasterweg, rechts der neue Vektorweg.

1. **Abgesetzte Formeln waren im Rasterweg deutlich zu klein.** Das ist der
   auffälligste Unterschied und ein Gewinn, den niemand gesucht hat:
   `E⁰(H⁺/H₂) = 0,00 V` steht im neuen PDF im Schriftgrad des Fließtextes,
   im alten merklich kleiner.
2. **Brüche, Pfeile, Indizes und Hochstellungen sitzen in beiden Wegen
   richtig** — nach dem Bibliothekstausch auf `html2canvas-pro` hat auch
   der Rasterweg keinen falschen Bruchstrich mehr.
3. **Formeln in Tabellenzellen** sind in beiden Wegen gleich groß und
   sauber ausgerichtet.
4. Der Vektorsatz ist in jeder Vergrößerung scharf; das Rasterbild wird
   beim Hineinzoomen unscharf.

## Abweichungen zwischen KaTeX- und MathJax-Satz

**Die Rasterspalte der Gegenüberstellung IST der KaTeX-Satz** — der
Rasterweg fotografiert genau das, was am Bildschirm steht. Ein Vergleich
Bildschirm gegen altes PDF zeigt also dieselben Glyphen.

Über die fünf Seiten hinweg: **keine strukturelle Abweichung gefunden.**
Beide Setzer verwenden Computer-Modern-Schriften (KaTeX: `KaTeX_*`,
MathJax: `mathjax-newcm`); Zeichenformen, Bruchstrichlagen, Klammergrößen
und Operatorabstände sind praktisch deckungsgleich. Die sichtbaren
Unterschiede sind **Größe und Schärfe**, beide zugunsten des Vektorwegs.

## Dunkelmodus

Seite 5824 im Dunkelmodus exportiert: Das PDF ist mit dem Hellmodus-Export
**deckungsgleich** — gleiche Größe (170 439 B), gleiche Füllfarben
(477 × `#333333`), identische Textebene. Keine weißen Glyphen.
