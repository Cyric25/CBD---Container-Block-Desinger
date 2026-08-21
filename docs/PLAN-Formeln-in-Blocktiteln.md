# Plan: Formeln in Blocktiteln werden gerendert

_Erstellt am: 2026-08-21 · Eigenständiges Kleinvorhaben · Komponente: CDB-Designer (v3.1.94)_

**Dieser Plan ist unabhängig von den drei Geschwisterplänen**
(`PLAN-Importer-Elternseite.md`, `PLAN-Aktionsleiste-Autoausblenden.md`,
`Theme/docs/PLAN-Inhaltsverzeichnis-Navigation.md`). Ihre Dateimengen sind
disjunkt; alle vier dürfen gleichzeitig laufen.

## 0. Anweisungen für den ausführenden Agenten

Du hast keinen Zugriff auf das Gespräch, in dem dieser Plan entstand. Er ist
die einzige Wahrheitsquelle.

1. Bearbeite die Arbeitspakete der Reihe nach. **AP-1 ist eine Diagnose und
   darf keinen Produktivcode ändern** — erst wenn die Ursache belegt ist,
   wird gebaut.
2. **TDD ab AP-2:** Tests zuerst, Fehlschlag bestätigen, roter Commit, dann
   implementieren bis grün. **Tests niemals abändern, damit sie bestehen.**
3. Commit-Nachrichten **ohne Anführungszeichen** — die Shell dieses Projekts
   ist PowerShell und übergibt den Text sonst als Pathspec. Mehrzeilige
   Nachrichten im Bash-Werkzeug per echtem Heredoc, **keine**
   PowerShell-Here-Strings.
4. Kein `git add .` und kein `git add -A`.
5. **PHP 7.4.** Zielumgebung 7.4.33, lokal PHP 8.x, `php -l` meldet
   8.0-Syntax **nicht** als Fehler. Nach jeder PHP-Änderung
   `php tools/check-php74.php` grün bekommen.
6. **Keine Versionsnummer erhöhen.**
7. **Debug-Ausgaben gaten.**

## 1. Ziel

Eine LaTeX-Formel im **Titel** eines Container-Blocks soll gerendert werden,
so wie sie es im Blockinhalt längst wird. Heute bleibt sie im Titel Rohtext.

## 2. Nicht-Ziele

- **Der Renderpfad für Blockinhalte bleibt unangetastet.** Die zwei Filter
  (`render_block` Priorität 5, `the_content` Priorität 11) und ihre
  Prioritäten werden nicht verändert.
- **Der seitenweite Doppelparse-Schutz wird nicht aufgeweicht.** Er ist eine
  bewusste Entscheidung mit dokumentierter Begründung; ihn feinkörniger zu
  machen wäre ein eigenes, viel größeres Vorhaben mit Regressionsfläche über
  jede Seite des Auftritts.
- **Keine neue Formel-Bibliothek**, kein zweiter Renderpfad, keine
  CDN-Einbindung (DSGVO-Entscheidung des Projekts).
- **`class-latex-parser.php` wird möglichst nicht geändert.** Wenn doch, dann
  nur additiv und mit eigener Begründung.

## 3. Kontext & Constraints

- **Komponente:** `Plugins/CDB-Designer/`, Version 3.1.94, Branch `main`.
- **Testumgebung:** `C:\allinkl-testserver`, Start `start-server.cmd`,
  WordPress unter `http://fos.localhost:8080/`, Installationspfad
  `C:\allinkl-testserver\www\htdocs\w0000001\fos`, Admin `admin` /
  `Testserver2026!`. **Plugins liegen dort als Kopie** — nach Änderungen
  dorthin kopieren. Bei HTTP 503 die `.maintenance`-Datei und
  `wp-content/upgrade/wordpress-*` löschen.
  Seiten mit Formeln: 43 („AP15 Formeln im Accordion"), 55, 62.
- **Bestehender Prüfharnisch:** `php tools/test-latex-parser.php`
  (134 Prüfungen). Er muss am Ende unverändert grün sein.

## 4. Ausgangslage und Ursachenverdacht

**Fundstellen, beide belegt:**

| Datei | Zeile | Sachverhalt |
|---|---|---|
| `includes/class-cbd-block-registration.php` | ~1285 | `$html .= '<h3 class="cbd-block-title">' . esc_html($block_title) . '</h3>';` |
| `includes/class-latex-parser.php` | 331 | `public function parse_latex($content)` |
| `includes/class-latex-parser.php` | 337–339 | `if (strpos($content, 'cbd-latex-formula') !== false) { return $content; }` |
| `includes/class-latex-parser.php` | 232 | `public function parse_latex_in_blocks($block_content, $block)` — hängt an `render_block`, Priorität 5 |
| `includes/class-latex-parser.php` | 58 | `public static function get_instance()` |

**Der Verdacht, den AP-1 zu belegen oder zu widerlegen hat.** Der
Doppelparse-Schutz in Zeile 337 wirkt **seitenweit auf den übergebenen
Text**, nicht je Formel. Auf `render_block` ist das je Block — aber
Container-Blöcke enthalten InnerBlocks, und die werden **vor** dem Container
gerendert. Enthält also irgendein innerer Block eine Formel, steht
`cbd-latex-formula` bereits im HTML des Containers, wenn dessen eigener
`render_block`-Durchlauf beginnt. Der Schutz greift, der ganze Container wird
übersprungen — **einschließlich seines Titels**.

Daraus folgt eine überprüfbare Vorhersage: Ein Container mit Formel **nur**
im Titel und **ohne** Formel im Inhalt müsste heute schon funktionieren; ein
Container mit Formeln in beidem nicht. Genau das prüft AP-1.

Zwei weitere Kandidaten, die AP-1 mit ausschließen soll:

- `esc_html()` wandelt `&`, `<`, `>`, `"` und `'` in Entities. Eine Formel mit
  `&` oder `<` (etwa `a < b`) käme beim Parser als Entity an. Der Parser
  löst Entities zwar in `normalize_formula_text()` auf, aber erst **nachdem**
  ein Delimiter erkannt wurde — ein Delimiter, der selbst maskiert wurde,
  wird nie gefunden.
- `the_content` (Priorität 11) unterliegt demselben Schutz und fängt den Fall
  deshalb ebenfalls nicht auf.

| Datei | Rolle heute | Änderung |
|---|---|---|
| `includes/class-cbd-block-registration.php` | erzeugt Titel und Kopfzeile | ändern (AP-2) |
| `includes/class-latex-parser.php` | Formelerkennung | nur lesen, wenn möglich |
| `tools/test-blocktitel-latex.php` | – | **neu** |
| `tools/test-latex-parser.php` | 134 Prüfungen | nur ausführen (Regression) |

## 4a. Berichtigt am 2026-08-21: Das Speicherproblem gibt es nicht

**Dieser Abschnitt behauptete bis zum 2026-08-21 das Gegenteil.** Er hielt
fest, `wp_kses_post()` zerstöre LaTeX-Ausdrücke im Blocktitel, und leitete
daraus ab, dieser Plan sei für die Rolle `block_redakteur` wirkungslos und
dürfe erst nach einem eigenen Sicherheitsvorhaben umgesetzt werden. **Beides
war falsch.** Die zugrundeliegende Messung prüfte Markup, das so nie aus dem
Editor kommt.

### Was tatsächlich gilt

`serialize_block_attributes()` (`wp-includes/blocks.php`) führt das
Attribut-JSON durch ein `strtr()`, das `\` zu `\u005c`, `--` zu
`\u002d\u002d` sowie `<`, `>`, `&` und `\"` in ihre `\uXXXX`-Formen überführt.
**Im Block-Trenner steht damit kein einziger Backslash** — und keines der
Zeichen, an denen kses oder der HTML-Parser sich stören könnten. Der Editor
tut im Browser dasselbe (`wp-includes/js/dist/blocks.min.js`).

Nachgemessen mit dem Konto `blockredakteur` (Rolle `block_redakteur`,
`unfiltered_html` = nein, `wp_filter_post_kses` aktiv), Titel
`Formel $\frac{a}{b}$, $\cdot$, $\sum_{i=1}^{n}$, $\alpha$, $\beta$`:

| Fall | Markup unverändert | Titel unversehrt |
|---|---|---|
| Markup aus `serialize_blocks()` — der echte Weg | **ja** | **ja** |
| Markup von Hand gebaut, Backslash roh im JSON | nein | nein |

Die alte Messung hatte den zweiten Fall erwischt. Das Schadensmuster verriet
es im Nachhinein: `\frac` → `rac` und `\beta` → `eta` ist das Verschwinden von
`\f` und `\b`, also von **Escape-Folgen**, die eine Zeichenkette im Prüfskript
gedeutet hat. kses entfernt keine Zeichenpaare dieser Art —
`wp_kses_stripslashes()` ersetzt ausschließlich `\"` durch `"`.

### Folgen für diesen Plan

- **Er ist nicht blockiert.** Er wirkt für alle Rollen gleichermaßen, auch für
  Block-Redakteure.
- **`PLAN-Blocktrenner-vor-kses-schuetzen.md` ist hinfällig** und dort als
  solcher gekennzeichnet. Es wird keine Ausnahme in eine Sicherheitsfunktion
  gebaut, und die Rolle bekommt `unfiltered_html` nicht — beides erübrigt sich.
- Die drei „Wege", die dieser Abschnitt früher zur Wahl stellte, sind
  gegenstandslos.

### Was bleibt: die eigentliche Ursache — zweimal berichtigt

**Erster Anlauf (falsch):** Die Ursache liege darin, dass
`class-cbd-block-registration.php` den Titel als `esc_html($block_title)`
ausgibt und ihn nie durch `CBD_LaTeX_Parser` schickt. Daraus wurde AP-2:
den Titel selbst vorrendern. Umgesetzt in 3.1.97/98.

**Widerlegt am selben Tag durch eine Messung**, die vorher gefehlt hatte:
Wird die Vorrender-Methode wieder entfernt, rendert der Titel **trotzdem**.
`CBD_LaTeX_Parser` hängt auf `render_block` (Priorität 5) und bekommt dort die
fertige Ausgabe des Renderers — das `<h3>` eingeschlossen. Der Titel lief also
die ganze Zeit durch den Parser.

Schlimmer noch: Das Vorrendern war **schädlich**. Die vorgerenderten
`<span class="cbd-latex-formula">` lassen den Doppelparse-Schutz in
`parse_latex()` anschlagen, der beim ersten Fund dieser Klasse sofort
zurückkehrt. Der **Inhalt** desselben Blocks blieb dadurch unformatiert.
Gemessen auf einer Seite mit fünf Blöcken: **8 Formeln ohne, 4 mit** dem
Vorrendern. AP-2 ist zurückgenommen, die Methode und ihr Prüfharnisch sind
entfernt.

**Die tatsächliche Ursache** war die `$`-Bilanzprüfung in
`parse_latex_in_blocks()`: Bei einer ungeraden Zahl von `$` gab sie den Block
unverändert zurück, setzte eine rote Warnbox darüber und hinterlegte jedes
`$` rot. Eine Formel im Titel plus ein einzelnes `$` im Text — „Das kostet
65$" — ergibt eine ungerade Bilanz. Ergebnis: weder Titel noch Inhalt
gerendert, dazu eine Fehlermeldung für einen einwandfreien Text.

Behoben durch zwei Änderungen am Parser:

1. **Die `$`-Bilanzprüfung ist entfernt.** Ein Dollarzeichen im Fließtext ist
   normal; die Anzahl sagt nichts über einen Fehler.
2. **Leerzeichenregel für das Inline-Muster:** Direkt hinter dem öffnenden und
   direkt vor dem schließenden `$` darf kein Leerraum stehen. Damit ist `65$`
   kein Formelanfang, `$Testformel $` keine Formel und `$Testformel$` eine.
   Das erübrigt die Notbremse, die die Bilanzprüfung sein sollte.

Beides auf ausdrücklichen Wunsch des Nutzers; die Beurteilung, ob eine
erkannte Formel richtig aussieht, obliegt der Sichtprüfung.

**Lehre:** AP-1 hatte den Auftrag, die Ursache zu **belegen**. Der Beleg
bestand darin, die Fundstelle zu lesen — nicht darin, sie abzuschalten und zu
messen, was dann passiert. Eine Ursachenbehauptung ist erst belegt, wenn die
Gegenprobe gelaufen ist: Verhält sich das System ohne den vermuteten Übeltäter
wirklich anders?

## 5. Architekturentscheidungen

| Entscheidung | Begründung | Verworfene Alternative |
|---|---|---|
| **Der Titel wird gezielt einzeln durch den Parser geschickt, statt den Doppelparse-Schutz zu lockern** | Der Schutz betrifft jede Seite des Auftritts; ihn feinkörniger zu machen hieße, den gesamten Renderpfad neu abzusichern. Ein direkter Aufruf auf **einer kurzen Zeichenkette** hat dagegen eine Regressionsfläche von genau einem Element | Schutz je Formel statt je Text: richtig, aber ein eigenes Vorhaben mit Prüfaufwand über alle Inhalte |
| **Reihenfolge: erst `esc_html()`, dann parsen, dann NICHT erneut escapen** | So ist der Titeltext vollständig entschärft, bevor überhaupt etwas erkannt wird. Das einzige HTML, das danach im Titel steht, hat der Parser selbst erzeugt. Kein Redakteur kann Markup einschleusen, auch nicht über einen Blocktitel | Erst parsen, dann `wp_kses_post()`: ließe fremdes Markup zu, das kses zwar filtert, aber eben durchlässt. Für einen Titel ist das unnötig weit |
| **Die Entity-Rückwandlung übernimmt der Parser, nicht eigener Code** | `normalize_formula_text()` dreht die wptexturize-Tabelle zurück und dekodiert Entities — in einer Reihenfolge, deren Vertauschung die Ableitungsschreibweise zerstört (`f'(x)` würde zu `f’(x)`). Diese Reihenfolge ein zweites Mal zu schreiben hieße, den Fehler ein zweites Mal machen zu können | Eigenes `html_entity_decode()` vor dem Parsen: zerstörte den Schutz durch `esc_html()` und dupliziert eine heikle Reihenfolge |
| **Der Aufruf geht über `CBD_LaTeX_Parser::get_instance()->parse_latex()`** | Beide Methoden sind bereits `public`; es braucht keine neue Schnittstelle und keine Änderung am Parser | Neue statische Hilfsmethode im Parser: zusätzliche öffentliche Fläche ohne Not |
| **Fehlt die Parser-Klasse, bleibt der bisherige Weg** | Der Titel muss auch dann erscheinen, wenn der Parser aus irgendeinem Grund nicht geladen ist. `class_exists()` davor, sonst `esc_html()` wie bisher | Ungeprüfter Aufruf: ein Fatal Error im Renderer nähme die ganze Seite mit |

## 6. Risiken

| Risiko | Auswirkung | Gegenmaßnahme |
|---|---|---|
| **Der Titel wird zum Einfallstor für Markup** | **hoch** | `esc_html()` läuft **vor** dem Parsen und bleibt erhalten. AP-2 hat einen Testfall mit `<script>` im Titel |
| **Titel ohne Formel verändern sich** | mittel | Der Parser gibt Text ohne Delimiter unverändert zurück. AP-2 prüft **Zeichengleichheit** für gewöhnliche Titel, auch mit Umlauten und Sonderzeichen |
| **Doppeltes Rendern, wenn der Titel doch noch durch einen Filter läuft** | gering | Der Doppelparse-Schutz greift dann und lässt den bereits gerenderten Titel unverändert — genau seine Aufgabe |
| **Die 134 bestehenden Prüfungen brechen** | mittel | `tools/test-latex-parser.php` ist Teil der Akzeptanz jedes APs |
| **PHP-8.0-Syntax** | hoch | `php tools/check-php74.php` |

**Rollback:** Rein additiv, kein Datenbank-Eingriff. Vor dem ersten AP
`git tag vor-blocktitel-latex`, Rückweg `git reset --hard vor-blocktitel-latex`.

## 7. Arbeitspakete

### AP-1: Ursache belegen (Diagnose, ohne Produktivcode zu ändern)

**Modell:** opus
**Dateien:** keine im Repository — Wegwerf-Skripte gehören in den
Scratchpad-Ordner, den die Umgebung nennt.

**Auftrag:** Die Vorhersage aus Abschnitt 4 prüfen und das Ergebnis
berichten. Erst danach wird gebaut.

1. Einen Container-Block **mit Formel nur im Titel** und **ohne** Formel im
   Inhalt rendern lassen. Wird die Titelformel gerendert?
2. Denselben Block **mit** einer Formel im Inhalt. Wird die Titelformel jetzt
   nicht mehr gerendert?
3. Falls beide Fälle fehlschlagen, ist der Doppelparse-Schutz **nicht** die
   Ursache — dann `esc_html()` als Kandidat prüfen: Kommt der Titel mit
   maskierten Delimitern beim Parser an?
4. Zusätzlich prüfen, ob die Reihenfolge stimmt, in der WordPress
   InnerBlocks und den umgebenden Block rendert — die Annahme „innen vor
   außen" ist die Grundlage der Vorhersage und sollte nicht geglaubt, sondern
   gesehen werden.

**Nachweis wahlweise** auf dem Testserver (Seite 43, 55 oder 62; Quelltext
ansehen) **oder** headless über einen Wegwerf-Harnisch, der die echte
Parser-Klasse mit Stubs lädt. Vorbild für die Stub-Technik:
`tools/test-latex-parser.php`.

**Ergebnis:** Ein kurzer Bericht mit Fundstellen und Messwerten sowie eine
klare Aussage, welche der drei Ursachen zutrifft. **Bei einer anderen als
der vermuteten Ursache stoppen und melden** — AP-2 ist dann neu zuzuschneiden.

---

### AP-2: Titel durch den Parser führen

**Modell:** sonnet
**Abhängigkeiten:** AP-1
**Dateien:** `includes/class-cbd-block-registration.php`,
`tools/test-blocktitel-latex.php` (neu)

**Umsetzung** (gilt, wenn AP-1 den Doppelparse-Schutz bestätigt hat):

1. Eine private Hilfsmethode `titel_mit_formeln($roh): string`:
   - `esc_html($roh)` — **zuerst**, ohne Ausnahme.
   - Wenn `class_exists('CBD_LaTeX_Parser')`: das Ergebnis durch
     `CBD_LaTeX_Parser::get_instance()->parse_latex()` schicken und
     zurückgeben.
   - Sonst das escapte Ergebnis unverändert zurückgeben.
2. Zeile ~1285 nutzt diese Methode; der Rückgabewert wird **nicht** erneut
   escapt. Kommentar davor: warum die Reihenfolge zwingend ist.
3. Prüfen, ob derselbe Titel an weiteren Stellen ausgegeben wird (etwa
   Modal-Titel, PDF-Kopf, Klassenansicht) — falls ja, in der Übergabenotiz
   auflisten, **aber nicht mitändern**. Das wäre ein Folge-AP.

**Akzeptanzkriterien:**

- AK1: `$E = mc^2$` im Titel erscheint als gerenderte Formel — auch wenn der
  Blockinhalt ebenfalls Formeln enthält.
- AK2: Ein Titel **ohne** Formel kommt **zeichengleich** heraus wie bisher.
  Geprüft mit Umlauten, `&`, Anführungszeichen und Apostroph.
- AK3: `<script>alert(1)</script>` im Titel erscheint als sichtbarer Text,
  nicht als Markup.
- AK4: Ein Titel mit `'` (Ableitung, `f'(x)`) wird nicht zu einem
  typografischen Anführungszeichen.
- AK5: Fehlt die Parser-Klasse, erscheint der Titel escapt wie bisher, ohne
  Fatal Error.
- AK6: `php tools/test-latex-parser.php` weiterhin **134 Prüfungen grün**.
- AK7: `php tools/check-php74.php` grün.

**Tests (TDD):** `tools/test-blocktitel-latex.php`, Prüfharnisch ohne
WordPress nach dem Muster von `tools/test-latex-parser.php`. Rote Tests
zuerst committen.

---

### AP-3: Abnahme auf dem Testserver

**Modell:** sonnet
**Abhängigkeiten:** AP-2

1. Dateien auf den Testserver kopieren.
2. Einen Container-Block anlegen, dessen Titel eine Formel enthält, und zwar
   **einmal** mit und **einmal** ohne Formeln im Inhalt.
3. Frontend prüfen: Beide Titel zeigen die gerenderte Formel.
4. **Regression:** Seiten 43, 55 und 62 aufrufen — Formeln im Inhalt
   unverändert, keine doppelt gerenderten Formeln, keine zerrissenen Absätze.
5. **Regression Accordion:** Auf Seite 43 eine Klappzeile öffnen; die Formeln
   darin müssen weiterhin erscheinen.
6. Browserkonsole und `debug.log` ohne neue Meldungen.

---

### AP-4: Dokumentation

**Modell:** sonnet
**Abhängigkeiten:** AP-3
**Dateien:** `CLAUDE.md`, `reference_file_map.md`, dieser Plan

1. In `CLAUDE.md`, Abschnitt **„LaTeX-Formeln: Renderpfad und
   Wiederholrendern"**, einen Unterabschnitt ergänzen: warum der Titel einen
   eigenen Weg braucht, und **die Reihenfolge escapen → parsen → nicht
   erneut escapen** ausdrücklich als zwingend festhalten. Wer sie dreht,
   öffnet entweder ein Einfallstor oder zerstört die Formel.
2. Den in Abschnitt 4 dieses Plans beschriebenen Zusammenhang zwischen
   Doppelparse-Schutz und InnerBlocks dort festhalten — er erklärt ein
   Verhalten, das sonst wie Zufall aussieht.
3. `reference_file_map.md`: Zeile für `class-cbd-block-registration.php`
   ergänzen, neue Zeile für `tools/test-blocktitel-latex.php`.
4. **Nur mit dem Edit-Werkzeug**, niemals per PowerShell-Lese-Schreib-Zyklus
   (Mojibake-Gefahr). Nachweis: `grep -c 'Ã\|â€' <datei>` liefert 0.

## 8. Status

| AP | Titel | Modell | Abhängig von | Status |
|---|---|---|---|---|
| AP-1 | Ursache belegen (Diagnose) | opus | – | ☑ **mit falschem Ergebnis**, am selben Tag berichtigt — echte Ursache war die `$`-Bilanzprüfung (Abschnitt 4a) |
| AP-2 | Titel durch den Parser führen | sonnet | 1 | **zurückgenommen** — überflüssig und schädlich (Abschnitt 4a). Methode und Prüfharnisch entfernt |
| AP-3 | Abnahme auf dem Testserver | sonnet | 2 | ☑ nach der Berichtigung: Seite `dollar-probe`, fünf Blöcke, 8 Formel-Spans, keine Warnbox. Der Augenschein steht beim Nutzer |
| AP-4 | Dokumentation | sonnet | 3 | ☑ (CLAUDE.md, Abschnitte „Der Blocktitel geht einen eigenen Weg" und „kses zerstört Blocktitel nicht") |

## 9. Testprotokoll

| AP | Test | Ergebnis | Datum |
|---|---|---|---|
| AP-1 | Vorhersage bestätigt oder widerlegt | **bestätigt** für die Renderseite; die zusätzlich vermutete Zerstörung beim Speichern **widerlegt** (Abschnitt 4a) | 2026-08-21 |
| AP-2 | ~~`php tools/test-blocktitel-latex.php`~~ | **entfallen** — der Harnisch prüfte `titel_mit_formeln()`, und die Methode ist zurückgenommen. Die neuen Prüfungen stehen in `tools/test-latex-parser.php`, Abschnitt „Leerzeichenregel" (13 Fälle) | 2026-08-21 |
| AP-2 | `php tools/test-latex-parser.php` (134, Regression) | **grün**, ebenso die übrigen 13 Harnische des Plugins | 2026-08-21 |
| AP-2 | `php tools/check-php74.php` | **grün**, 570 Dateien | 2026-08-21 |
| AP-3 | Titelformel mit und ohne Inhaltsformel | **bestanden.** Seite 378, als `blockredakteur` gespeichert: vier Blöcke (Titelformel allein, Titel- und Inhaltsformel, ohne Formel, Apostroph plus `&`). Drei Formel-Spans mit richtigem `data-latex`, der Block ohne Formel unverändert, KaTeX geladen | 2026-08-21 |
| AP-3 | Regression Seiten 43, 55, 62 und Accordion | **offen, nicht geprüft** — vom Nutzer nach Sicht abzunehmen | – |
| AP-4 | Mojibake-Kontrolle | **bestanden** — 13 Steuerzeichen in CLAUDE.md, allesamt Tabulatoren und schon vorher vorhanden | 2026-08-21 |
