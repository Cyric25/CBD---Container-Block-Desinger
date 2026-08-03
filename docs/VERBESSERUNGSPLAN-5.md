# Verbesserungsplan 5 — Content-Importer strukturtolerant (2026-08-03)

Auftrag: Der Importer-Parser soll auch Abschnitte erkennen, denen **kein Style
zugewiesen** ist bzw. für die **kein Style existiert** — und generell mit
beliebigen Markdown-Strukturen funktionieren.

Status: ✅ ERLEDIGT (AP43–AP46), ausgeliefert in CDB v3.1.72.

---

## Ausgangsbefund (Messung, nicht Vermutung)

Analyse aller **33** Markdown-Dateien unter `Inhalte/`:

| Befund | Anzahl | Folge im alten Importer |
|---|---|---|
| Dateien ohne jede Überschrift (`*-aussagen.md`) | 5 | **100 % Inhaltsverlust** (0 Abschnitte) |
| Dateien mit Text vor der ersten H1 (Präambel) | 5 | Präambel verworfen |
| Dateien mit Inhalt direkt unter H2 | 23 | nur bei „Quellenverzeichnis" erhalten, sonst verworfen |
| H2 ohne Kompetenz-Schlüsselwort | 0 | wäre still zu K1 geworden |

Ursache: `parse_markdown_content()` speicherte einen Abschnitt nur bei
`$current_topic && $current_competence && $current_block_title`. Fehlte eine
Ebene, lief der Inhalt ins Leere. Zusätzlich verwarf `insertBlocks()` im JS
Abschnitte ohne Style-Mapping stillschweigend (`if (!selectedStyle) return;`).

---

## AP43 — Parser strukturtolerant machen

**Datei:** `includes/class-cbd-content-importer.php`

- `save_block()` → `flush_section()`: speichert jeden Abschnitt mit Inhalt,
  unabhängig von vorhandenen Überschriftenebenen.
- Titel-Fallback-Kette **H3 → H2 → H1 → „Abschnitt N"**.
- Inhalt wird ab jetzt **immer** gesammelt (auch vor der ersten Überschrift).
- `detect_competence_level()`: Rückgabe `'other'` statt stillem `'k1'`-Fallback.
- Neue Kategorie `other` in `grouped` + `stats`.
- Neue Metadaten je Abschnitt: `titleSource` (h1/h2/h3/none),
  `hasExplicitCompetence` (bool) — für die UI-Vorschau.

**Verifikation:** Testharness mit 9 Struktur-Fällen (Standard, ohne
Überschriften, H1+Inhalt, unbekannte H2, Präambel, Mischform, nur H3, leer,
YAML) + Wort-für-Wort-Vollständigkeitsvergleich über alle 33 echten Dateien:
**272 Abschnitte, kein Inhaltsverlust** (fehlen nur die H2-Kompetenz-
Überschriften selbst — die sind bewusst Kategorie, nicht Inhalt).

## AP44 — Import ohne (passendes) Block-Design

**Datei:** `assets/js/content-importer.js`

- Konstante `NO_CONTAINER = '__none__'`; jedes Style-Select enthält
  „— ohne Container (nur Inhalt) —".
- `insertBlocks()`: kein stilles `return` mehr. Ohne Zuweisung ODER bei
  unbekanntem Slug wird der Abschnitt als `core/heading` (Titel) + Inhaltsblöcke
  eingefügt. Container mit unbekanntem Slug werden bewusst nie erzeugt
  (würden im Frontend „Block nicht gefunden" rendern).
- Kategorien datengetrieben (`CATEGORIES`) statt 4× kopiertem JSX; `other`
  erscheint automatisch mit eigener Style-Wahl.
- Neu: Warnhinweis wenn keine Block-Designs existieren, Info-Hinweis bei
  `other`-Abschnitten, aufklappbare Vorschau aller erkannten Abschnitte
  („Kategorie · Titel · Ziel-Style/ohne Container"), Fehler-Notice jetzt auch
  in Schritt 2 sichtbar (war vorher nur in Schritt 1 gerendert).
- PHP-Seite: `ajax_get_style_mappings()` liefert `hasStyles` und filtert
  Vorschläge auf real existierende Slugs; `other` erbt den K1-Vorschlag.

**Verifikation:** Simulation des Einfüge-Pfads in 4 Szenarien (alle Styles da /
DB ohne Designs / Slug existiert nicht / Datei ohne Struktur + keine Designs):
jeweils **0 verlorene Abschnitte**.

## AP45 — Bonus: Inline-Formatierung zerstörte URLs (Datenverlust)

**Datei:** `includes/class-cbd-content-importer.php`, `markdown_to_html()`

Bestehender Bug, unabhängig vom Auftrag gefunden: Die Bold-Regel
`/__(.+?)__/` traf Unterstriche **in URLs**. Aus
`…/gaschromatographie__conrady__kloss___.pdf` wurde
`…/gaschromatographie<strong>conrady</strong>kloss___.pdf` — die Quellen-URL
war unbrauchbar. Gleiches Risiko für `*`/`_` in LaTeX und Inline-Code.

- Schutzphase per Platzhalter vor der Inline-Formatierung für: Inline-Code,
  `$$…$$`, `$…$`, URLs (http/https/www) — Wiederherstellung am Methodenende.
- Kursiv-Regex verschärft (keine Zeilengrenzen, keine Listen-Marker).
- Neu: Markdown-Links `[Text](URL)` → `<a>` (Schema-Whitelist).

**Verifikation:** 10 Inline-Fälle (Bold, Kursiv, Listen-Marker, LaTeX inline/
display, URL mit `__`, MD-Link, Inline-Code, chemische Formeln, Bold+URL) —
alle korrekt; URL nachweislich intakt.


## AP46 — Eigene H2-Überschriften als eigene Style-Gruppen (v3.1.71)

**Meldung:** „`## Übungen` wird nicht als Blockstil erkannt, Hinweise auch nicht."

Nach AP43/44 landeten alle H2 ohne Kompetenz-Schlüsselwort in EINER Kategorie
`other` mit EINEM Style. Fachliche Gliederungen wie „Übungen" und „Hinweise"
brauchen aber je ihr eigenes Block-Design.

**Umsetzung**
- Parser: jede eigenständige H2 bildet eine eigene Gruppe (`groupKey`
  = `h2-<normalisiert>`, `groupLabel` = Original-H2). `other` bleibt nur für
  Abschnitte ganz ohne Überschrift. `competence` bleibt für Farben/
  Rückwärtskompatibilität erhalten; maßgeblich für die Zuweisung ist `groupKey`.
- `parse_markdown_content()` liefert zusätzlich `groups`
  (`key`, `label`, `competence`, `count`, `suggestedStyle`, `matchedBy`),
  Kompetenzstufen zuerst, danach eigene H2 alphabetisch.
- Neu `normalize_key()` (Umlaute → ae/oe/ue, ß → ss, Sonderzeichen → `-`),
  `stem_key()` (Singular/Plural **inkl. Umlaut-Plural**: „Merksätze" ≈
  „Merksatz") und `match_style_for_label()` (exakt → Stammform → Teilstring).
- `attach_style_suggestions()` (im AJAX-Parse, mit DB-Zugriff) füllt
  `suggestedStyle`/`matchedBy`; Kompetenzstufen behalten ihre festen Defaults.
- **Nachschärfung (v3.1.72) auf Nutzerwunsch:** vorbelegt wird nur bei
  **exakter** Namensgleichheit. Unscharfe Treffer landen in `similarStyle`
  und erscheinen nur als Hinweis am Select („kein exakt gleichnamiges Design —
  ähnlich: „Hinweis" (bitte selbst zuweisen)"); das Select bleibt auf „ohne
  Container", damit keine falsche Automatik-Zuweisung passiert.
- UI: Zuweisungszeilen werden aus `groups` generiert (statt fixer Liste), je
  Zeile Anzahl + Hinweis „automatisch zugeordnet" / „kein gleichnamiges Design
  gefunden"; Warn-Notice nennt alle Gruppen ohne Treffer; Vorschau zeigt die
  Gruppe je Abschnitt. Neue CSS-Klassen `*-custom`.

**Verifikation:** Matching-Tabelle (11 Fälle: „Übungen", „Übung", „uebungen",
„Hinweise", „Hinweis", „Merksätze", „Beispiele", „Übungen zum Kapitel",
„Wichtige Hinweise", „Zusammenfassung", „ÜBUNGEN") + E2E-Simulation in zwei
Szenarien: mit vorhandenen Designs landen alle 6 Abschnitte im je richtigen
Container; fehlen die Designs, gehen 0 Abschnitte verloren (5× ohne Container).
Regression: 9 Strukturfälle und 272 Abschnitte über 33 echte Dateien unverändert.

---

## Testharness (Wiederverwendung bei künftigen Parser-Änderungen)

Der Parser ist headless testbar — WordPress wird nicht gebraucht, nur 4 Stubs:

```php
define('ABSPATH', __DIR__ . '/');
define('CBD_TABLE_BLOCKS', 'x');
function add_action() {}
function __($s, $d = null) { return $s; }
function esc_html($s) { return htmlspecialchars($s, ENT_QUOTES); }
function esc_url($s) { return $s; }
require '…/includes/class-cbd-content-importer.php';
$r = CBD_Content_Importer::get_instance()->parse_markdown_content($markdown);
// $r['stats'], $r['sections'][n]['competence'|'blockTitle'|'titleSource'|'content']
```

Nützlichster Test: Wortmengen-Vergleich Original ↔ Summe aller Sections
(Markdown-Marker vorher normalisieren) — deckt Inhaltsverlust sofort auf.

---

## Offen / bewusst nicht umgesetzt

- **Fußnoten, Bilder (`![]()`), Blockquotes, verschachtelte Listen** werden vom
  Mini-Parser weiterhin nicht in eigene Gutenberg-Blöcke übersetzt (landen als
  Text bzw. Rohsyntax). Bei Bedarf gezielt ergänzen.
- **`*-aussagen.md`-Dateien** sind inhaltlich für den Aussagen-/Statement-Block
  gedacht, nicht für den Container-Import. Sie werden jetzt zwar vollständig
  importiert (1 Abschnitt „Abschnitt 1"), sinnvoller ist dort aber der Block
  „statement-summary" im Modular-Plugin.
