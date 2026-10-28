# Testmaterial „Blocktext auf die Tafel"

Erhoben am 2026-10-28 in AP-1.1 des Plans `PLAN-Blocktext-auf-Tafel.md`
(Website-Wurzel) auf der Testinstallation `http://fos.localhost:8080/`
(Plugin-Stand 3.1.145). Zweck: Der Leser des linken Tafelbereichs
(`_blocktextLesen()` in `assets/js/board-mode.js`, ab AP-2.1) und die
Formelumwandlung (`CBDBoardBlocktext.latexZuText()` in
`assets/js/board-blocktext.js`, ab AP-1.2) prüfen gegen echten Inhalt statt
gegen ausgedachte Fälle.

## Dateien

Je Container ein Paar `<name>.html` (Inhalt von `.cbd-board-content` nach dem
Öffnen der Tafel) und `<name>.erwartet.txt` (Text, den ein Schüler bekommen
soll).

| Name | Seite (ID) | Container `data-stable-id` | deckt ab |
|---|---|---|---|
| `formeln-listen-glossar` | Elektrochemische Messsysteme (1676) | `cbd-1771189754-szr8tKsO` | (a) Inline-Formeln, (b) Aufzählung, (f) Glossarbegriffe, auch im Blocktitel |
| `abgesetzte-formel-reaktionspfeil` | Die Beta-Oxidation (876) | `cbd-1771612278930-lnlprkan` | (a) abgesetzte Formel mit `\xrightarrow`, Inline-Formel nur aus `_2` mitten im Wort (`H` + Formel + `O`) |
| `tabelle` | Flammenfärbung und Ionisierung (5652) | `cbd-1.78752153215E+12-7lmfg0rl` | (c) Tabelle mit Kopfzeile, Zelle beginnt mit „1. " |
| `accordion-zugeklappt` | Flammenfärbung und Ionisierung (5652) | `cbd-1.78752153215E+12-rsexnun6` | (d) `modular-blocks/accordion`, alle Zeilen zu |
| `zusammenfassung-quiz` | Übungen zum Kapitel Gravimetrie (5304) | `cbd-1784967251013-dr28d4z4` | (e) `modular-blocks/summary-block` (Quiz mit versteckter Lösung) |
| `ueberschriften-unicode` | Versuche zum Gleichgewicht (1480) | `cbd-1771189754-1bDJyIh3` | (h) Überschriften H2/H3 im Inhalt, (b) Liste, Unicode-Chemie ohne LaTeX (CO₂, ⇌) |
| `verschachtelt-formel-nbsp` | Einfache Lipide (524) | `cbd-1771189754-4TlIXiBs` | (g) Container im Container, (a) abgesetzte Formel mit geschütztem Leerzeichen und Unicode im LaTeX, leere Überschrift |
| `bildueberlagerung` | Carotinoide (2323) | `cbd-1771189754-2lnKyeye` | (e) `modular-blocks/image-overlay` (Ebenenknöpfe), (a) Formeln mit `C_{40}`, (b), (h) |

`formeln.json` – der Formelbestand der Website (siehe unten).

## Wie die HTML-Dateien entstanden sind

1. Seite auf der Testinstallation geladen, Tafel aus der Konsole geöffnet
   (`CBDBoardMode.open(<id>, <innerHTML von .cbd-container-block>, '#ffffff', {})`
   – der Knopf „Tafel-Modus" reagiert dort wegen der CSP nicht), 3 s gewartet.
2. `.cbd-board-content` geklont; Original und Klon parallel durchlaufen. Im
   Klon entfernt: jedes Element, dessen Original berechnet `display: none`
   oder `visibility: hidden` hat, sowie alle `<script>`.
3. **Darstellungstyp konserviert (Ergänzung zum Plan):** Wo das CSS der
   Website den Darstellungstyp eines Elements gegenüber dem Browser-Standard
   umkehrt (inline ↔ block, gezählt wie im Leser: `display` beginnt mit
   `inline` oder ist `contents` = inline), trägt das Element im Klon
   `style="display: <berechneter Wert>"`. Grund: Die Prüfseite
   `tools/test-board-zeiger.html` hat das Website-CSS nicht; ohne diese
   Angabe lägen dort Absatzgrenzen anders als auf der Website (Beispiel:
   `span.group-label` im Zusammenfassungs-Block ist auf der Website ein
   Block). Ausgenommen sind Elemente in `.cbd-latex-formula`,
   `[aria-hidden="true"]`, `button` und `svg` – der Leser steigt dort nicht
   ein. Betroffen sind in allen Dateien `span.cbd-header-icon` (flex) und
   `img.cbd-custom-icon` (block), dazu einzelne Elemente der interaktiven
   Blöcke.
4. `klon.innerHTML` gesichert und anschließend Zeichen für Zeichen gegen
   einen frischen Abgriff verglichen. Abweichungen, die bewusst bleiben:
   - Formel-IDs (`cbd-latex-inline-<uniqid>-1`) wechseln bei jedem Laden.
   - `data-fos-darkmode-checked` setzt das Theme zeitabhängig.
   - Zeilen, die nur aus Leerzeichen bestanden, sind leer (das
     Schreibwerkzeug kürzt sie) – zwischen Blockelementen ohne Bedeutung.
   - `zusammenfassung-quiz`: Die Reihenfolge der Antwortknöpfe wird bei
     jedem Laden gemischt (`shuffleStatements`).
5. `bildueberlagerung.html`: Die fünf Bildquellen zeigten auf die
   Produktivseite; die Testinstallation blockiert sie per CSP
   (`img-src 'self' data:`) und meldete das als Konsolenfehler. Sie sind
   durch ein leeres 1×1-GIF als `data:`-Adresse ersetzt. Bilder sind für den
   Text ohne Belang.

## Format der Erwartung (`.erwartet.txt`)

- Ein Absatz je Blocktitel (`.cbd-block-title`), Überschrift, Absatz,
  Listenpunkt (Präfix `• ` bzw. `1. `, `2. ` …), Tabellenzeile (Zellen mit
  ` | ` verbunden) und abgesetzter Formel (`.cbd-latex-display`).
- Absätze durch genau eine Leerzeile getrennt; Leerraum im Absatz zu einem
  Leerzeichen zusammengefasst; Datei endet mit einem Zeilenwechsel (beim
  Vergleich am Ende trimmen).
- Keine Knopf-Beschriftungen, keine Bild-Alternativtexte. **Ausnahme:** Ein
  `<button>` innerhalb einer Überschrift `H1`–`H6` zählt als Text der
  Überschrift – so stehen die Zeilentitel des Accordion-Blocks
  (`h3 > button.mb-accordion-row__header`) in der Erwartung. AP-2.1 ist im
  Plan entsprechend ergänzt.
- Was nur per CSS erzeugt wird, fehlt: die Nummern „1.", „2." der
  Accordion-Zeilen (`is-numbered`, Zähler im CSS) und die Großschreibung von
  „Frage 1 von 8" (`text-transform`). Der Leser liest den Text des DOM.
- **Formeln als Platzhalter** `{{F:<data-latex>}}` – der Quelltext genau so,
  wie `getAttribute('data-latex')` ihn liefert (Entities aufgelöst; in
  `verschachtelt-formel-nbsp` stehen darin zwei geschützte Leerzeichen
  U+00A0). Die Prüfung ersetzt jeden Platzhalter durch
  `CBDBoardBlocktext.latexZuText(<data-latex>)`.
- **Ende eines Platzhalters:** das erste `}}`, an dem die geschweiften
  Klammern des Formeltexts ausgeglichen sind (`\{` und `\}` zählen nicht).
  Ein einfacher regulärer Ausdruck `\{\{F:(.*?)\}\}` reicht NICHT:
  `{{F:C_{40}}}` endet erst nach der dritten schließenden Klammer.

## `formeln.json`

Alle eindeutigen Formeln aus veröffentlichten Seiten und Beiträgen der
Testinstallation (Stand 2026-10-28), sortiert, als JSON-Array von
Zeichenketten. Erzeugt per PHP-CLI-Skript außerhalb des Projekts (danach
gelöscht): `post_content` aller Seiten/Beiträge mit `$`, `\(` oder `\[` durch
`CBD_LaTeX_Parser::get_instance()->parse_latex()`, alle `data-latex`-Werte
gelesen und mit `html_entity_decode(…, ENT_QUOTES | ENT_HTML5, 'UTF-8')`
entschlüsselt.

**Zusätzlich entschlüsselt:** Formeln in Blocktiteln stehen im Blocktrenner
als Attribut-JSON, und `serialize_block_attributes()` schreibt dort jeden
Backslash als JSON-Escape (`\` + `u005c`). Auf der Seite erscheinen sie
entschlüsselt. Das Skript wandelt deshalb jede Folge Backslash-u-vier
Hexziffern in das Zeichen zurück (265 von 5685 Vorkommen betroffen).
Kein LaTeX-Befehl beginnt mit `u` und vier Hexziffern.

Zahlen: 246 Seiten mit Formeln, 5685 Vorkommen, **2915 eindeutige Formeln**.
661 enthalten gar keinen Backslash, 208 enthalten Zeichen außerhalb von
ASCII (u. a. `°`, `ä`, `→`, `‡`, `–`, geschütztes Leerzeichen in 9 Formeln).

Neu erzeugen nur, wenn sich der Bestand deutlich geändert hat; das
Verfahren oben genügt dafür.
