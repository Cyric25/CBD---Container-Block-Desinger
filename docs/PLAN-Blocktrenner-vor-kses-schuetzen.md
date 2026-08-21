# Plan: Block-Trenner vor der kses-Filterung schützen

> # ⛔ HINFÄLLIG — nicht umsetzen
>
> **AP-1 hat die Voraussetzung dieses Plans widerlegt (2026-08-21).** kses
> zerstört Blocktitel **nicht**. Die Messung, auf der dieser Plan beruht, war
> fehlerhaft — sie prüfte Markup, das so nie aus dem Editor kommt. Einzelheiten
> im neuen Abschnitt 10 am Ende.
>
> Der Plan bleibt als Beleg stehen: Er dokumentiert eine Sicherheitsausnahme,
> die **nicht** gebaut wurde, und warum. Wer erwägt, sie doch zu bauen, findet
> hier die Messung, die das erübrigt.

_Erstellt am: 2026-08-21 · Eigenständiges Kleinvorhaben · Komponente: CDB-Designer (v3.1.94)_

**Vorbedingung für `PLAN-Formeln-in-Blocktiteln.md`.** Jener Plan will
Formeln in Blocktiteln **rendern**; solange der Titel beim **Speichern**
zerstört wird, liefe er für die Rolle Block-Redakteur ins Leere. Erst dieses
Vorhaben, dann jenes.

**Sicherheitsrelevant.** Hier wird eine Ausnahme in eine Sicherheitsfunktion
gebaut. Das ist zulässig und vom Nutzer bewusst gewählt — der Preis dafür ist,
dass die Ausnahme selbst wasserdicht sein muss. Jedes Akzeptanzkriterium in
Abschnitt 7 ist deshalb ernst gemeint, auch die, die nach Formalie klingen.

## 0. Anweisungen für den ausführenden Agenten

Du hast keinen Zugriff auf das Gespräch, in dem dieser Plan entstand. Er ist
die einzige Wahrheitsquelle.

1. **AP-1 ist eine Untersuchung und darf keinen Produktivcode ändern.** Erst
   wenn geklärt ist, ob WordPress selbst einen Weg anbietet, wird gebaut.
2. **TDD ab AP-2:** Tests zuerst, Fehlschlag bestätigen, roter Commit, dann
   implementieren bis grün. **Tests niemals abändern, damit sie bestehen.**
   Hältst du einen Test für inhaltlich falsch, stoppe und melde es.
3. Commit-Nachrichten **ohne Anführungszeichen** — die Shell dieses Projekts
   ist PowerShell und übergibt den Text sonst als Pathspec. Mehrzeilige
   Nachrichten im Bash-Werkzeug per echtem Heredoc, **keine**
   PowerShell-Here-Strings.
4. Kein `git add .` und kein `git add -A` — immer nur die eigenen Pfade.
5. **PHP 7.4.** Zielumgebung ist 7.4.33, lokal läuft PHP 8.x, und `php -l`
   meldet 8.0-Syntax **nicht** als Fehler. Nach jeder PHP-Änderung
   `php tools/check-php74.php` grün bekommen. Verboten: `match`, Nullsafe,
   Constructor Promotion, benannte Argumente, `str_contains`,
   `str_starts_with`, `str_ends_with`, `mixed`, Union Types.
6. **Keine Versionsnummer erhöhen.**
7. **Debug-Ausgaben gaten.**
8. **Wenn du in einem Skript Markdown oder Code mit Backslashes schreibst,
   nimm rohe Zeichenketten** (`r"..."` in Python). Beim Anlegen dieses
   Vorhabens ist genau daran ein Text zerstört worden: `\f`, `\b`, `\a`,
   `\n`, `\t` und `\r` wurden als Escapes gedeutet, und aus `\frac` wurde
   `rac` — dieselbe Fehlerfamilie, die dieses Vorhaben behebt.

## 1. Ziel

Der **Blocktitel** eines Container-Blocks soll ein Speichern durch eine Rolle
**ohne** `unfiltered_html` unbeschadet überstehen.

**Gemessen am 2026-08-21** auf dem Testserver, als echter Nutzer mit der Rolle
`block_redakteur` gespeichert:

| Prüfung | Ergebnis |
|---|---|
| Rolle hat `unfiltered_html` | nein |
| kses-Filter beim Speichern aktiv (`content_save_pre`) | ja |
| Blocktitel `Formel $\frac{a}{b}$ und $\cdot$` nach dem Speichern | **im Markup nicht mehr auffindbar — Attribut zerstört** |
| Dieselben Formeln im Block-**Inhalt** | **unverändert erhalten** |
| Block-Trenner selbst | erhalten |

Betroffen ist also nur, was **im HTML-Kommentar des Block-Trenners** steht:
die Blockattribute, darunter `blockTitle`. Einzelmessungen von
`wp_kses_post()`:

| Eingabe im Blocktitel | Nach kses |
|---|---|
| `\frac{a}{b}` | `rac{a}{b}` |
| `\beta` | `eta` |
| `\cdot`, `\sum_{i=1}^{n}`, `\alpha` | Titel unlesbar zerstört |
| `\nabla`, `\tau`, `\rho` | unverändert |

## 2. Nicht-Ziele

- **Der Rolle wird `unfiltered_html` NICHT gegeben.** Das war der
  ausdrücklich verworfene Weg. Wer diesen Plan umsetzt und dabei auf die Idee
  kommt, es wäre einfacher — es wäre einfacher, und es ist nicht gewollt.
- **Die Filterung wird für nichts anderes ausgesetzt.** Alles außerhalb der
  Block-Trenner läuft weiter durch kses, unverändert.
- **Keine Datenmigration.** Bereits zerstörte Titel werden **nicht**
  repariert — sie sind unwiederbringlich, die Ursprungsformel steht nirgends
  mehr. Betroffene Blöcke müssen von Hand nachgetragen werden; das gehört in
  die Dokumentation, nicht in den Code.
- **Das Rendern der Formeln ist nicht Teil dieses Plans** — dafür gibt es
  `PLAN-Formeln-in-Blocktiteln.md`.
- **Das Theme wird nicht geändert.**

## 3. Kontext & Constraints

- **Komponente:** `Plugins/CDB-Designer/`, Version 3.1.94, Branch `main`.
- **Umgebung produktiv:** All-inkl Shared Hosting, PHP 7.4.33.
- **Testumgebung:** `C:\allinkl-testserver`, Start über `start-server.cmd`
  (mit dem PowerShell-Werkzeug und vorherigem `Set-Location`; ein direkter
  Aufruf aus Git Bash findet die Datei nicht). WordPress unter
  `http://fos.localhost:8080/`, Installationspfad
  `C:\allinkl-testserver\www\htdocs\w0000001\fos`.
  Admin `admin` / `Testserver2026!`.
  **Konto mit der Zielrolle ist vorhanden:** `blockredakteur` /
  `BlockRed2026!`, Rolle `block_redakteur`, angelegt am 2026-08-21 für genau
  diese Messung.
  Datenbank `d0000001` / `d0000001` / `EBZvYRyrEM34gtfmv3Z8`, Client
  `C:\allinkl-testserver\mariadb\bin\mysql.exe`.
  **Die Plugins liegen dort als Kopie, nicht als Verknüpfung.**
  Bei HTTP 503 die `.maintenance`-Datei und `wp-content/upgrade/wordpress-*`
  löschen.
- **PHP von der Kommandozeile mit WordPress-Bootstrap** braucht zwei
  Erweiterungen, sonst scheitert es irreführend:
  `-d extension_dir="C:/allinkl-testserver/php/8.3/ext" -d extension=mysqli -d extension=mbstring`.
  Ohne `mbstring` endet **jedes** Speichern in einem Fatal Error, weil der
  Glossar-Scanner des Themes `mb_strtolower()` auf `save_post` ruft.

## 4. Ausgangslage

| Datei | Rolle heute | Änderung |
|---|---|---|
| `includes/class-cbd-block-markup-guard.php` | – | **neu** |
| `container-block-designer.php` | Bootstrap | ändern (zwei Zeilen) |
| `tools/test-block-markup-guard.php` | – | **neu** |
| `includes/functions.php` | `cbd_block_redakteur_capabilities()` — **`unfiltered_html` kommt dort nicht vor**, weder erlaubt noch ausdrücklich verweigert | **nur lesen** |
| `includes/class-latex-parser.php` | enthält mit `mask_protected_regions()` / `restore_placeholders()` das Maskier-Muster des Projekts | **nur lesen — als Vorbild** |

**Der Mechanismus, den es zu umgehen gilt:** WordPress hängt für Nutzer ohne
`unfiltered_html` die Funktion `wp_filter_post_kses` an `content_save_pre`
(Priorität 10). Dort läuft der gesamte `post_content` durch `wp_kses`, und
dessen Behandlung von HTML-Kommentaren zerstört die Backslash-Folgen.

**Das Projekt kennt das Maskier-Muster bereits.** `CBD_LaTeX_Parser` nimmt
`<script>`, `<pre>` und `<code>` per Platzhalter aus dem Text, bevor die
Delimiter-Muster laufen, und tauscht sie danach zurück. Dieses Vorhaben ist
dasselbe Verfahren an anderer Stelle — **nicht** ein neu erfundenes.

## 5. Architekturentscheidungen

| Entscheidung | Begründung | Verworfene Alternative |
|---|---|---|
| **Maskieren vor kses, zurücktauschen danach — statt kses abzuschalten** | Alles außer den Trennern bleibt gefiltert. Die Ausnahme ist damit so klein wie möglich und im Code sichtbar begrenzt | kses ganz aushängen: hebt den Schutz für den gesamten Inhalt auf |
| **Der Platzhalter trägt ein pro Aufruf neu erzeugtes Zufallsmerkmal** | **Das ist die Sicherheitsnaht dieses Vorhabens.** Wäre der Platzhalter vorhersagbar, könnte ein Redakteur ihn selbst in seinen Text schreiben und damit beliebiges Blockmarkup an der Filterung vorbeischmuggeln. Mit `uniqid()` je Aufruf ist er nicht erratbar. `CBD_LaTeX_Parser` macht es genauso | Fester Platzhalter: öffnet genau das Loch, das der Plan schließen soll |
| **Maskiert werden ausschließlich Block-Trenner**, erkannt als `<!-- wp:… -->` und `<!-- /wp:… -->` | Alle **anderen** HTML-Kommentare laufen weiter durch kses. Würde man jeden Kommentar maskieren, ließe sich beliebiger Inhalt in einem Kommentar verstecken | Alle Kommentare schützen: unnötig weit, und der Schutz ginge verloren |
| **Der Ausdruck endet am ersten `-->`** | Dadurch kann der maskierte Text **von Bauart her** kein `-->` enthalten. Ein Ausbruch aus dem Kommentar ist damit unmöglich, ohne dass man eigens dagegen prüfen müsste — genau die Prüfung, die kses mit dem Zusammenziehen von `--` leistet und die hier entfällt | Gierige Suche bis zum letzten `-->`: erlaubte einem Angreifer, den Kommentar früh zu schließen und den Rest in das Dokument zu entlassen |
| **Zurückgetauscht wird wortgleich, ohne erneute Prüfung** | Der maskierte Text ist derselbe, der vorher dastand — er stammt aus `serialize_blocks()` des Editors, nicht aus freier Eingabe. Eine zweite Prüfung müsste JSON verstehen und wäre eine neue Fehlerquelle | Attribute nach dem Zurücktauschen validieren: mehr Code, mehr Angriffsfläche, kein Gewinn |
| **Bleibt beim Zurücktauschen ein Platzhalter übrig, wird der Speichervorgang abgebrochen** | Ein Inhalt mit sichtbaren Platzhaltern in der Datenbank wäre schlimmer als ein zerstörter Titel: Er sieht im Editor wie Datenmüll aus und ist nicht mehr zuzuordnen. Lieber ein erkennbarer Fehlschlag als eine stille Beschädigung | Platzhalter stehen lassen: verwandelt einen Anzeigefehler in einen Datenschaden |
| **Der Filter läuft für alle Rollen, nicht nur für die ohne `unfiltered_html`** | Für Administratoren ist er wirkungslos — maskieren und sofort zurücktauschen ergibt denselben Text. Eine Fallunterscheidung nach Rolle wäre eine zweite Stelle, an der etwas falsch sein kann | Nur bei fehlendem `unfiltered_html` einhängen: spart nichts und verdoppelt die Bedingungen |

## 6. Risiken

| Risiko | Auswirkung | Gegenmaßnahme |
|---|---|---|
| **Der Platzhalter ist erratbar** | **sehr hoch** — beliebiges Blockmarkup ließe sich an kses vorbeischleusen | Zufallsmerkmal je Aufruf. AP-2 hat einen Testfall, der genau diesen Angriff versucht |
| **Ein Platzhalter überlebt in der Datenbank** | hoch — stiller Datenschaden | Abbruch statt Speichern (siehe Architektur). AP-2 prüft es mit einem absichtlich gestörten Rücktausch |
| **Ausbruch aus dem Kommentar über ein eingeschmuggeltes `-->`** | **sehr hoch** | Der Ausdruck endet am ersten `-->`; der maskierte Text kann keines enthalten. Eigener Testfall |
| **Ein fremder Filter auf `content_save_pre` sieht die Platzhalter** | mittel | Das Fenster zwischen Maskieren und Zurücktauschen ist so eng wie möglich zu wählen (unmittelbar vor und nach Priorität 10). In der Dokumentation festhalten |
| **Inhalte ohne Block-Trenner ändern sich** | mittel | Der Filter zieht sich sofort zurück, wenn `<!-- wp:` nicht vorkommt. AP-2 prüft **Zeichengleichheit** für solche Inhalte |
| **Administratoren bekommen anderen Inhalt als bisher** | mittel | Für sie ist der Filter wirkungslos. AP-3 prüft ein Speichern als Administrator auf Zeichengleichheit |
| **PHP-8.0-Syntax** | hoch | `php tools/check-php74.php` |

**Rollback:** Rein additiv, kein Datenbank-Eingriff, kein Schema. Vor dem
ersten AP `git tag vor-blocktrenner-schutz`, Rückweg
`git reset --hard vor-blocktrenner-schutz`. Bereits gespeicherte Inhalte
bleiben gültig; ohne den Filter gilt wieder das heutige Verhalten.

## 7. Arbeitspakete

### AP-1: Bietet WordPress selbst einen Weg? (Untersuchung, ohne Codeänderung)

**Modell:** opus
**Dateien:** keine im Repository — Wegwerf-Skripte in den Scratchpad-Ordner,
den die Umgebung nennt.

**Bevor eine eigene Ausnahme gebaut wird, ist zu klären, ob WordPress das
Problem bereits gelöst hat.** Eine Kernfunktion oder ein vorgesehener Filter
wäre jedem Eigenbau vorzuziehen — sie wird gepflegt, dieser Code nicht.

Zu beantworten, jeweils mit Fundstelle in der WordPress-Quelle des
Testservers (Version 7.0.4):

1. **Wo genau zerstört kses den Kommentarinhalt?** `wp_kses_split2()` in
   `wp-includes/kses.php` behandelt Kommentare eigens. Welche Zeile frisst
   die Backslash-Folge? Die Antwort entscheidet, ob ein schmalerer Eingriff
   möglich ist als vollständiges Maskieren.
2. **Gibt es einen vorgesehenen Filter?** `pre_kses`, `wp_kses_allowed_html`,
   `kses_allowed_protocols` — greift einer davon für Kommentare?
3. **Behandelt WordPress Block-Trenner bereits gesondert?** Suche in
   `wp-includes/kses.php` und `wp-includes/blocks.php` nach einer solchen
   Ausnahme. Die Frage ist naheliegend genug, dass der Kern sie gelöst haben
   könnte.
4. **Wie verhält sich der Speicherweg des Blockeditors (REST) im Vergleich zu
   `wp_insert_post()`?** Läuft dieselbe Filterkette? Die Messung vom
   2026-08-21 lief über `wp_insert_post()`; der Editor speichert über die
   REST-Schnittstelle.

**Ergebnis:** Ein kurzer Bericht. **Findet sich ein vom Kern vorgesehener
Weg, stoppe und melde das** — der Plan wird dann neu zugeschnitten, statt eine
eigene Ausnahme zu bauen.

---

### AP-2: Maskieren und Zurücktauschen

**Modell:** opus
**Abhängigkeiten:** AP-1
**Dateien:** `includes/class-cbd-block-markup-guard.php` (neu),
`container-block-designer.php` (zwei Zeilen),
`tools/test-block-markup-guard.php` (neu)

**Umsetzung:**

1. Neue Klasse `CBD_Block_Markup_Guard`, Muster wie die Nachbarklassen
   (statische `init()`, Registrierung in `container-block-designer.php` hinter
   `class_exists()`).
2. Zwei Filter auf `content_save_pre`: maskieren **unmittelbar vor**
   Priorität 10, zurücktauschen **unmittelbar danach**. Die genauen
   Prioritäten begründen — das Fenster soll so eng wie möglich sein.
3. **Maskieren:** Kommt `<!-- wp:` im Inhalt nicht vor, sofort unverändert
   zurückgeben. Sonst jeden Block-Trenner durch einen Platzhalter ersetzen,
   der ein **pro Aufruf** erzeugtes Zufallsmerkmal trägt, und die
   Originaltexte in einer Eigenschaft der Klasse ablegen.
4. **Zurücktauschen:** Alle Platzhalter durch ihre Originale ersetzen.
   Bleibt danach ein Platzhalter übrig oder ist einer verschwunden, gilt der
   Vorgang als gescheitert — **abbrechen statt speichern** (siehe
   Architektur). Der Speicher wird in jedem Fall geleert, auch im
   Fehlerfall, damit ein nächster Aufruf nicht auf Resten aufsetzt.
5. Der Ausdruck für die Trenner endet am **ersten** `-->`.

**Akzeptanzkriterien:**

- AK1: Ein Titel mit `\frac{a}{b}`, `\cdot`, `\sum_{i=1}^{n}`, `\alpha` und
  `\beta` überlebt `wp_kses_post()` **zeichengleich**, wenn der Schutz aktiv
  ist.
- AK2: Inhalt **ohne** `<!-- wp:` kommt **zeichengleich** heraus.
- AK3: Ein gewöhnlicher HTML-Kommentar (kein Block-Trenner) wird **weiterhin
  von kses behandelt** wie bisher — der Schutz gilt nur den Trennern.
- AK4: `<script>` im Blockinhalt wird **weiterhin entfernt**. Der Schutz darf
  ausschließlich die Trenner betreffen, nicht den Inhalt dazwischen.
- AK5: **Angriffsfall Platzhalter.** Schreibt der Nutzer selbst einen Text in
  seinen Inhalt, der wie ein Platzhalter aussieht, darf daraus **kein**
  Blockmarkup entstehen. Der Testfall muss den Platzhalter-Aufbau kennen und
  ihn nachbauen.
- AK6: **Angriffsfall Kommentarausbruch.** Ein Trenner, dessen JSON ein
  `-->` enthält, darf nach dem Rücktausch **kein** Markup außerhalb des
  Kommentars erzeugen.
- AK7: **Angriffsfall verlorener Platzhalter.** Wird der Rücktausch
  künstlich gestört, endet der Vorgang in einem erkennbaren Fehlschlag —
  **niemals** in einem gespeicherten Inhalt mit sichtbarem Platzhalter.
- AK8: Zwei Speichervorgänge nacheinander stören sich nicht (der Speicher
  wird zwischen den Aufrufen geleert).
- AK9: Der Filter ist für einen Nutzer **mit** `unfiltered_html` wirkungslos:
  Ein- und Ausgabe sind zeichengleich.
- AK10: `php tools/check-php74.php` grün.

**Tests (TDD):** `tools/test-block-markup-guard.php`, Prüfharnisch ohne
WordPress nach dem Muster von `tools/test-block-content-api.php`
(CLI-Wächter plus Stubs). **Die vier Angriffsfälle AK5 bis AK7 sind die
eigentliche Arbeit dieses Harnischs** — die übrigen Kriterien sind
Regressionsschutz. Rote Tests zuerst committen.

---

### AP-3: Abnahme auf dem Testserver, mit echtem Konto

**Modell:** opus
**Abhängigkeiten:** AP-2

1. Dateien auf den Testserver kopieren (die Plugins liegen dort als Kopie).
2. **Als `blockredakteur` anmelden** (Konto vorhanden, siehe Abschnitt 3) und
   im Blockeditor einen Container-Block mit dem Titel
   `Formel $\frac{a}{b}$ und $\cdot$` anlegen, speichern, Seite neu laden.
   **Über den Editor speichern, nicht per Skript** — der REST-Weg ist der,
   den der Nutzer wirklich benutzt.
3. Den `post_content` **aus der Datenbank** lesen und den Titel wörtlich
   vergleichen.
4. Denselben Ablauf **als Administrator** — der Inhalt muss zeichengleich zu
   dem sein, was ohne diesen Filter entstünde.
5. **Regression:** Eine Bestandsseite mit Container-Blöcken (55, 62, 76)
   öffnen, ohne Änderung speichern, und den `post_content` vorher/nachher
   per MD5 vergleichen. Er muss identisch sein.
6. **Regression Seitenimport:** Eine Markdown-Datei importieren und prüfen,
   dass die LaTeX-Backslashes im Inhalt weiterhin einfach sind.
7. `debug.log` und Browserkonsole ohne neue Meldungen.

**Akzeptanzkriterien:** AK1 bis AK7 entsprechend den Schritten. Schritt 2 ist
der Kern — er ist der Nachweis, dass das Vorhaben sein Ziel erreicht.

---

### AP-4: Unabhängiges Review

**Modell:** opus
**Abhängigkeiten:** AP-3
**Dateien:** keine — **ausschließlich lesend**

Ausgeführt von einem Agenten, der AP-2 nicht gebaut hat. **Bei einem
sicherheitsrelevanten Eingriff ist ein zweiter Blick keine Förmlichkeit.**

Prüfschwerpunkte:

1. **Kann ein Redakteur den Platzhalter erraten oder erzwingen?** Reicht das
   Zufallsmerkmal? Ist es je Aufruf wirklich neu? Lässt es sich aus der
   Ausgabe ableiten?
2. **Gibt es einen Weg, Inhalt in einen maskierten Bereich zu schmuggeln?**
   Denk dir Eingaben aus, die AP-2 nicht getestet hat.
3. **Ist das Fenster zwischen Maskieren und Zurücktauschen wirklich eng?**
   Welche anderen Filter hängen dazwischen?
4. **Was passiert bei einer Ausnahme mitten im Vorgang?** Bleibt der Speicher
   gefüllt? Kann ein zweiter Aufruf darauf aufsetzen?
5. **Bleibt kses für alles andere in Kraft?** Prüfe es mit eigenen Fällen,
   nicht anhand der Tests von AP-2.
6. Die zehn Akzeptanzkriterien von AP-2 gegen den Code halten.

Befunde nach Schwere sortiert, je Befund Fundstelle mit Zeilennummer,
Auswirkung, Vorschlag. **Eine ausdrückliche Aussage: Ist der Eingriff sicher,
ja oder nein?**

---

### AP-5: Dokumentation

**Modell:** sonnet
**Abhängigkeiten:** AP-4
**Dateien:** `CLAUDE.md`, `reference_file_map.md`, dieser Plan,
`docs/PLAN-Formeln-in-Blocktiteln.md`

1. `CLAUDE.md`: ein eigener Abschnitt. Was der Schutz tut, **warum das
   Zufallsmerkmal die Sicherheitsnaht ist**, warum der Ausdruck am ersten
   `-->` endet, und warum bei einem übrig gebliebenen Platzhalter abgebrochen
   statt gespeichert wird. Dazu die Messwerte aus Abschnitt 1 — sie erklären,
   wozu der Code überhaupt da ist.
2. **Ausdrücklich festhalten, was NICHT repariert wird:** Bereits zerstörte
   Titel sind unwiederbringlich. Wer betroffene Blöcke hat, muss die Titel
   von Hand nachtragen. Ohne diesen Satz sucht später jemand nach einer
   Migration, die es nicht gibt.
3. Den Abschnitt bei „Custom User Roles" aktualisieren — dort steht seit
   AP-4 des Importer-Vorhabens der kses-Befund. Er bleibt richtig, bekommt
   aber einen Verweis auf die Behebung.
4. `reference_file_map.md`: Zeilen für die neue Klasse und den neuen
   Harnisch.
5. **In `docs/PLAN-Formeln-in-Blocktiteln.md`, Abschnitt 4a, vermerken, dass
   die Vorbedingung erfüllt ist** — jener Plan kann danach laufen.
6. **Nur mit dem Edit-Werkzeug**, niemals per PowerShell-Lese-Schreib-Zyklus
   und nicht per Python-Skript mit nicht-rohen Zeichenketten. Nachweis:
   `grep -c 'Ã\|â€' <datei>` liefert 0, und die Dateien enthalten keine
   Steuerzeichen.

## 8. Status

| AP | Titel | Modell | Abhängig von | Status |
|---|---|---|---|---|
| AP-1 | Bietet WordPress selbst einen Weg? | opus | – | ☐ |
| AP-2 | Maskieren und Zurücktauschen | opus | 1 | ☐ |
| AP-3 | Abnahme mit echtem Block-Redakteur-Konto | opus | 2 | ☐ |
| AP-4 | Unabhängiges Review | opus | 3 | ☐ |
| AP-5 | Dokumentation | sonnet | 4 | ☐ |

## 9. Testprotokoll

| AP | Test | Ergebnis | Datum |
|---|---|---|---|
| AP-1 | Kernweg vorhanden, ja oder nein | – | – |
| AP-2 | `php tools/test-block-markup-guard.php` | – | – |
| AP-2 | Die vier Angriffsfälle (AK5–AK7) | – | – |
| AP-2 | `php tools/check-php74.php` | – | – |
| AP-3 | Titel überlebt Speichern als Block-Redakteur (über den Editor) | – | – |
| AP-3 | Als Administrator zeichengleich | – | – |
| AP-3 | Bestandsseiten per MD5 unverändert | – | – |
| AP-3 | Regression Seitenimport | – | – |
| AP-4 | Sicherheitsurteil | – | – |
| AP-5 | Mojibake- und Steuerzeichenkontrolle | – | – |

---

## 10. AP-1: Ergebnis der Untersuchung (2026-08-21)

**AP-1 lautete: „Bietet WordPress selbst einen Weg?" Die Antwort ist ja — und
zwar so vollständig, dass es nichts zu bauen gibt.**

### 10.1 Der Kern maskiert Blockattribute bereits selbst

`serialize_block_attributes()` in `wp-includes/blocks.php` schickt das
JSON durch ein `strtr()` mit genau diesen Ersetzungen:

| Zeichen | wird zu |
|---|---|
| `\` (Backslash) | `\u005c` |
| `--` | `\u002d\u002d` |
| `<` | `\u003c` |
| `>` | `\u003e` |
| `&` | `\u0026` |
| `\"` | `\u0022` |

**Im Block-Trenner steht deshalb überhaupt kein Backslash** — und kein `--`,
kein `<`, kein `>`, kein `&`. Genau die Zeichen, an denen sich kses und der
HTML-Parser stören könnten, sind vorher weg. Der Editor tut im Browser
dasselbe: `wp-includes/js/dist/blocks.min.js` enthält
`replaceAll("\\\\","\u005c").replaceAll("--","\u002d\u002d")…`. Beide
Speicherwege sind abgedeckt.

Das ist keine glückliche Nebenwirkung, sondern der Zweck der Funktion.

### 10.2 Gemessen, mit dem echten Konto und dem echten Speicherweg

Titel: `Formel $\frac{a}{b}$, $\cdot$, $\sum_{i=1}^{n}$, $\alpha$, $\beta$`
Konto: `blockredakteur`, Rolle `block_redakteur`, `unfiltered_html` = **nein**,
`wp_filter_post_kses` auf `content_save_pre` = **aktiv**.

| Fall | Markup unverändert | Titel unversehrt |
|---|---|---|
| **A** — Markup aus `serialize_blocks()`, also so wie der Editor es liefert | **ja** | **ja** |
| **B** — Markup von Hand gebaut, Backslash roh im JSON | nein | nein, Attribut zerstört |

`wp_kses_post()` allein auf Fall A: **zeichengleich**.

### 10.3 Warum die ursprüngliche Messung das Gegenteil zeigte

Sie hat Fall **B** gemessen. Solches Markup entsteht nie im Editor, weder
serverseitig noch im Browser — es entsteht nur, wenn ein Prüfskript den
Block-Trenner selbst zusammensetzt und dabei den Backslash roh ins JSON
schreibt.

Das Muster der gemeldeten Schäden verrät zusätzlich, dass schon vor kses etwas
schiefging: `\frac` → `rac` und `\beta` → `eta` sind das Verschwinden von
`\f` und `\b`, also **Escape-Folgen**, die eine Zeichenkette im Prüfskript
gedeutet hat — `\f` ist Seitenvorschub, `\b` Rückschritt. kses entfernt keine
Zeichenpaare dieser Art; `wp_kses_stripslashes()` ersetzt ausschließlich `\"`
durch `"`. Es ist dieselbe Fehlerfamilie, vor der Abschnitt 0 Punkt 8 dieses
Plans warnt.

**Lehre:** Eine Messung, die eine Sicherheitsausnahme begründen soll, muss den
Eingabewert auf demselben Weg erzeugen wie das Produktivsystem — hier also
über `serialize_blocks()` statt über eine selbst gebaute Zeichenkette.

### 10.4 Antworten auf die vier Fragen aus AP-1

1. **Wo zerstört kses den Kommentarinhalt?** Bei Markup aus dem Editor:
   nirgends. Die Kommentarbehandlung in `wp_kses_split2()`
   (`wp-includes/kses.php`, ab „Normative HTML comments should be handled
   separately") entfernt `<!--`/`-->`, schickt den Rest durch `wp_kses()` bis
   zur Stabilität und zieht danach `--+` auf einen Bindestrich zusammen. Weil
   `serialize_block_attributes()` `--`, `<`, `>`, `&` und `\` vorher in
   `\uXXXX`-Folgen überführt, findet dieser Code nichts vor, woran er sich
   stören könnte.
2. **Gibt es einen vorgesehenen Filter?** `pre_kses` (kses.php, Zeile 1245)
   gäbe es, wird aber nicht gebraucht.
3. **Behandelt WordPress Block-Trenner in kses gesondert?** Nein — in
   `kses.php` kommt weder `wp:` noch ein Blockbegriff vor. Der Schutz sitzt
   eine Ebene früher, beim Serialisieren.
4. **REST gegenüber `wp_insert_post()`?** Beide laufen über
   `content_save_pre`; der Unterschied ist ohne Belang, weil beide Wege
   dasselbe bereits maskierte Markup erhalten.

### 10.5 Folge

**Dieser Plan wird nicht umgesetzt.** Die Rolle `block_redakteur` braucht
`unfiltered_html` weiterhin nicht — nicht als Zugeständnis, sondern weil es
kein Problem gibt, das sie lösen müsste. `PLAN-Formeln-in-Blocktiteln.md` ist
damit **nicht blockiert**; dessen Abschnitt 4a ist entsprechend berichtigt.
