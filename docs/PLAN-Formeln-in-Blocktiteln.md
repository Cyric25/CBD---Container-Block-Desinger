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
| AP-1 | Ursache belegen (Diagnose) | opus | – | ☐ |
| AP-2 | Titel durch den Parser führen | sonnet | 1 | ☐ |
| AP-3 | Abnahme auf dem Testserver | sonnet | 2 | ☐ |
| AP-4 | Dokumentation | sonnet | 3 | ☐ |

## 9. Testprotokoll

| AP | Test | Ergebnis | Datum |
|---|---|---|---|
| AP-1 | Vorhersage bestätigt oder widerlegt | – | – |
| AP-2 | `php tools/test-blocktitel-latex.php` | – | – |
| AP-2 | `php tools/test-latex-parser.php` (134, Regression) | – | – |
| AP-2 | `php tools/check-php74.php` | – | – |
| AP-3 | Titelformel mit und ohne Inhaltsformel | – | – |
| AP-3 | Regression Seiten 43, 55, 62 und Accordion | – | – |
| AP-4 | Mojibake-Kontrolle | – | – |
