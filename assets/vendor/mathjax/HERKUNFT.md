# MathJax (SVG-Ausgabe) — Herkunft und Umfang

Diese Dateien werden **ausschließlich beim PDF-Export** nachgeladen, nie beim
normalen Seitenaufruf. Sie liegen lokal, weil das Projekt keine
CDN-Einbindungen erlaubt (DSGVO).

## Was hier liegt

| Datei | Größe | Herkunft | Lizenz |
|---|---|---|---|
| `tex-svg.js` | 1,85 MB | npm `mathjax@4.1.3`, Datei `tex-svg.js` | Apache-2.0 (siehe `LICENSE`) |
| `fonts/…/svg/dynamic/latin.js` | 359 KB | npm `@mathjax/mathjax-newcm-font@4.1.3` | Apache-2.0 (laut `package.json` des Pakets; es liegt dort keine eigene Lizenzdatei bei) |
| `fonts/…/svg/dynamic/latin-b.js` | 299 KB | dito | dito |
| `fonts/…/svg/dynamic/latin-i.js` | 490 KB | dito | dito |

Aus beiden Paketen ist **nur** das Genannte übernommen — keine
MathML-Eingabe, keine CommonHTML-Ausgabe, keine WOFF-Schriftdateien, keine
Sprachausgabe (`speech-rule-engine`).

## Warum das `fonts/`-Verzeichnis nötig ist — und warum sein Pfad Pflicht ist

MathJax 4 hat die Schriften aus dem Hauptbündel ausgelagert. `tex-svg.js`
enthält den Grundbestand; **Zeichen außerhalb davon werden zur Laufzeit
nachgeladen.** Gemessen an den 47 Formeln der Prüfseite:

- 46 Formeln setzen **ohne jeden Netzzugriff**.
- `ö`, `ü`, `Ö` in `\text{}` lösen das Nachladen von
  `mathjax-newcm-font/svg/dynamic/latin.js` aus. (`ß`, `µ`, `°`, `Ω` sind im
  Grundbestand enthalten.)

**Die Voreinstellung von MathJax für diesen Nachladepfad ist
`https://cdn.jsdelivr.net/npm/@mathjax`.** Ein „Lösung" in einer Formel hätte
also stillschweigend einen Drittanbieter kontaktiert — auf einer
deutschsprachigen Seite praktisch garantiert.

**Deshalb setzt `ladeMathJax()` in `assets/js/pdf-server-side.js`
`loader.paths.fonts` zwingend auf dieses lokale Verzeichnis.** Damit ist der
Weg **fail-closed**: Fehlt eine Schriftdatei, scheitert das Setzen dieser
einen Formel und sie fällt auf den Rasterweg zurück — es geht **nie** eine
Anfrage nach außen.

**Wer diese Einstellung entfernt, öffnet eine DSGVO-Lücke, die man im
Betrieb nicht sieht.**

## Weitere Schriftdateien nachrüsten

Verfügbar sind im Paket `@mathjax/mathjax-newcm-font` unter `svg/dynamic/`
unter anderem `latin-b.js`, `latin-i.js`, `latin-bi.js` (fett/kursiv),
`greek.js`, `cyrillic.js`, `arrows.js`, `math.js`.

**Welche gebraucht werden, ist gemessen, nicht geraten — `AP-1.3` ist
erledigt.** Über den gesamten Bestand (5655 Formelvorkommen, 3096
eindeutige, 246 Seiten, 16 Sonderzeichen) hat MathJax genau **drei** Dateien
angefordert: `latin`, `latin-b`, `latin-i`. Ausgelöst werden sie
ausschließlich von den deutschen Umlauten und `ß`; alles andere — `°`, `→`,
`⇌`, `≡`, `‡`, `µ`, `α`, `β`, Tiefstellungen und sämtliche geprüften
Makros einschließlich `\xrightarrow`, `\sqrt`, `\sum` — steckt im
Grundbestand.

**Der Erstkontakt-Effekt:** Beim ersten Zeichen aus einer nachgeladenen
Datei wirft MathJax einmalig `retry`, obwohl die Daten da sind. Ein zweiter
Aufruf gelingt — `setzeFormelAlsSvg()` wiederholt deshalb genau einmal.
Ohne das fiele je Seitenaufruf die erste Formel mit Umlaut ohne Not auf den
Rasterweg zurück.

Vollständiger Bericht samt Verfahren für künftige Zeichen:
**`docs/inventar-formeln.md`**.
