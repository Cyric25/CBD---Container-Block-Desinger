# Erweiterungsanalyse: PDF-Export- und Tafelmodus-Fixes

_Erstellt am: 2026-08-25 · Komponente: CDB-Designer_

## 1. Kurzbeschreibung der Erweiterung

Drei zusammenhängende Korrekturen am bereits produktiven PDF-Export und
Tafelmodus des CDB-Designer-Plugins:

- **(A) PDF-Bilder fehlen:** Beim Export eines Container-Blocks mit „Eigenen
  Notizen" (lokale Tafelmodus-Zeichnung) und/oder „Tafelbildern"
  (klassenweit serverseitig gespeicherte Zeichnung) erscheinen die Bilder
  nicht im erzeugten PDF.
- **(B) PDF wird nicht direkt heruntergeladen:** Der Browser fragt beim
  PDF-Export nach einem Speicherort, statt die Datei direkt in den
  Downloads-Ordner zu speichern.
- **(C) Tafelmodus im Darkmode:** Wenn die Website im Dunkelmodus (Toggle,
  `data-theme="dark"`) läuft und der Tafelmodus geöffnet wird, bleibt dessen
  Oberfläche (Werkzeugleiste/Overlay/Dialoge) weiß statt sich anzupassen.
  Zusätzlich soll die Farbe einer bereits gezeichneten „Eigenen Notiz" im
  Darkmode invertieren (z. B. Schwarz auf Weiß → Weiß auf Schwarz).

## 2. Verständnis des Ist-Projekts

- **Projektzweck:** WordPress-Website mit den Plugins CDB-Designer
  (Container-Blöcke inkl. Tafelmodus und PDF-Export) und „Eigene WP Blocks"
  sowie einem eigenen Theme mit manuellem Darkmode-Toggle.
- **Relevante Module:** Ausschließlich **CDB-Designer** — weder Theme noch
  „Eigene WP Blocks" sind an einer der drei Baustellen beteiligt. Der
  Darkmode-*Mechanismus* selbst (`data-theme="dark"` auf `<html>`,
  `localStorage`, Toggle-Button) gehört dem Theme und ist fertig; hier geht
  es nur um fehlende CSS-Anpassungen **innerhalb** des Plugins.
- **Geltende Konventionen (CLAUDE.md/DOKUMENTATION.md):**
  - Neue/geänderte darkmode-relevante CSS-Regeln verwenden
    `[data-theme="dark"] .selektor`, **nicht**
    `@media (prefers-color-scheme: dark)` (projektweite Pflichtkonvention
    seit `PLAN-Darkmode-Umschaltung.md`).
  - Neuer CSS-Code nutzt ausschließlich `var(--x, #fallback)`, keine
    hartcodierten Hex-Werte — mit der dokumentierten Ausnahme von
    **Inhaltsfarben** (vom Nutzer gewählte Zeichenfarben zählen dazu und
    bleiben bewusst unangetastet).
  - PHP 7.4-Kompatibilität ist Pflicht (`tools/check-php74.php` vor jedem
    ZIP-Bau).
  - Vor jedem AP-Abschluss: Live-Diagnose bevorzugen, wenn eine Vermutung
    noch nicht am echten System bestätigt ist (etabliertes Muster aus
    AP-1.1 des Vorgängerplans, siehe Abschnitt 9).

## 3. Einordnung in die Architektur

- **Andockpunkt (A, B):** Der bestehende serverseitige PDF-Export-Datenweg
  — Client (`assets/js/pdf-server-side.js`, `floating-pdf-button.js`) baut
  HTML inkl. eingebetteter Bilder und schickt es strukturiert an
  `includes/class-cbd-pdf-generator.php` (mPDF). Es wird **kein neuer**
  Datenweg gebraucht, sondern der bestehende (aus
  `docs/archiv/PLAN-PDF-Notizen-und-Listenformeln.md`, Phase 2) korrigiert
  bzw. ergänzt.
- **Andockpunkt (C):** Die bestehende Darkmode-Umstellung auf
  `[data-theme="dark"] .selektor` wird auf die bisher **bewusst
  ausgenommene** Datei `assets/css/board-mode.css` erweitert — exakt nach
  dem Muster, das `cbd-frontend-clean.css` und `latex-formulas.css` in
  Phase 2 des Darkmode-Plans bereits durchlaufen haben (inkl. der dort drei
  gefundenen Kaskade-Fallen: fehlendes `!important` gegen Inline-Styles,
  aktive `transition` mit Vorrang, Variablen-Kollisionen).
- **Begründung:** Alle drei Punkte sind **Lücken in bereits abgeschlossenen
  Vorhaben**, keine neuen Features. Der naheliegende und einzig konsistente
  Andockpunkt ist deshalb, exakt dort weiterzumachen, wo die jeweiligen
  Vorgängerpläne den Scope bewusst abgeschnitten oder ungeprüft gelassen
  haben (siehe Abschnitt 9 für die Fundstellen).

## 4. Betroffene Dateien

| Datei | Rolle heute | Änderung |
|---|---|---|
| `assets/js/pdf-server-side.js` | Baut Export-HTML, injiziert lokale/Server-Zeichnungen, sendet an Server, löst Download aus (`downloadPDF()`) | ändern (A, B) |
| `includes/class-cbd-pdf-generator.php` | mPDF-Rendering, `clean_block_html()`, `prepare_structured_block()` | ändern (A) — Verdachtsstelle: `clean_block_html()` entfernt jedes `style="..."`, das `display:none` **irgendwo** enthält, komplett statt nur die Eigenschaft |
| `assets/js/floating-pdf-button.js` | Export-Dialog, Checkbox „Tafelbilder einschließen" | evtl. ändern (A/B, falls Diagnose dort Ursache findet) |
| `includes/class-cbd-classroom.php` | Bulk-Endpoint `cbd_get_page_drawings` für Server-Tafelbilder | nur lesen, außer Diagnose findet Ursache hier |
| `assets/js/board-mode.js` | Tafelmodus-Logik, Zeichenfläche, Tafelfarbe (Weiß/Grün/Schwarz-Presets), `localStorage`-Persistenz | ändern (C) — Filter-Zuschaltung abhängig von `data-theme` und aktueller Tafelfarbe |
| `assets/css/board-mode.css` | Tafelmodus-Gestaltung (Werkzeugleiste, Zeichenfläche, Dialoge) — bisher **ohne** `[data-theme="dark"]`-Regeln | ändern (C) |
| `CLAUDE.md` (CDB-Designer) | Abschnitte „Darkmode" und „PDF-Export: Tafelbilder und eigene Notizen" | ändern (Doku-AP) |
| `reference_file_map.md` (CDB-Designer) | Einträge zu `board-mode.css`, `pdf-server-side.js`, `class-cbd-pdf-generator.php` | ändern (Doku-AP) |
| `DOKUMENTATION.md` (Root) | Verweis auf diesen Plan ergänzen | ändern (Doku-AP) |

## 5. Wiederverwendung statt Neubau

- **Darkmode-Mechanismus** (`data-theme="dark"`, `localStorage
  fos-color-scheme`) — Theme, unverändert nutzen, nicht neu bauen.
- **`[data-theme="dark"] .selektor`-Muster** aus `cbd-frontend-clean.css`
  und `latex-formulas.css` — als Vorlage für `board-mode.css` übernehmen,
  inkl. der dort bereits bekannten Kaskade-Fallen (Inline-Style-Vorrang,
  `transition`, Variablen-Kollision bei `--color-sidebar-border`).
- **Bestehendes Tafelfarben-System** (`boardColor`, Presets Weiß/Grün/
  Schwarz, `cbd-board-{id}-bgcolor`-Begleitschlüssel) — die Invertierung
  für „Eigene Notiz" baut darauf auf (nur bei `boardColor === '#ffffff'`
  automatisch invertieren; bewusste Grün/Schwarz-Wahl bleibt unangetastet),
  kein neues Farbsystem nötig.
- **`getComputedStyle`-Muster** aus `floating-pdf-button.js` (AP-2.9 des
  Darkmode-Plans) für evtl. nötige CSS-Variablen-Abfragen im PDF-Kontext.

## 6. Integrationspunkte & Schnittstellen

- **(A/B) Kein neuer Endpunkt.** `sendPDFRequest()`/`sendPDFViaAjax()` in
  `pdf-server-side.js` und die REST-Route `generate-pdf` bzw.
  `cbd_generate_pdf` bleiben unverändert in ihrer Signatur; es wird nur
  geprüft/korrigiert, **warum** eingebettete `data:image/...`-URIs auf dem
  Weg dorthin oder im mPDF-Rendering verloren gehen bzw. warum
  `downloadPDF()` (Standard-`<a download>`-Technik, bereits korrekt
  implementiert) den Browser dennoch nach einem Speicherort fragen lässt.
- **(C)** Kein neuer Hook nötig — reine CSS-Ergänzung plus eine kleine
  JS-Ergänzung in `board-mode.js`, die bei Board-Öffnen/Board-Farbwechsel
  `document.documentElement.getAttribute('data-theme')` liest und je nach
  Kombination `data-theme=dark` + `boardColor=#ffffff` eine CSS-Klasse
  (z. B. `cbd-board-inverted`) auf die Zeichenflächen-Elemente setzt, die
  in `board-mode.css` einen `filter: invert(1)` bekommt.

## 7. Regressionsfläche (kritisch)

- **`pdf-server-side.js` wird von A und B gemeinsam berührt** — beide
  Korrekturen sollten **nicht** von zwei Agenten parallel an derselben
  Datei bearbeitet werden (Merge-Konflikt-Risiko). Empfehlung: ein
  gemeinsamer AP-Strang für A+B.
- **Bereits produktiver PDF-Export ohne Zeichnungen** (reiner Textinhalt,
  Screenshots interaktiver Blöcke, LaTeX-Formeln) darf durch Änderungen an
  `clean_block_html()`/`prepare_structured_block()` **nicht** brechen —
  das ist der am längsten laufende, meistgenutzte Teil des Exports.
- **Print- und Text-Modus des PDF-Exports** (`mode=print`/`text`) teilen
  sich `get_mpdf_stylesheet()`/`clean_block_html()` mit dem Standardmodus
  — nach jeder Änderung alle drei Modi gegenprüfen.
- **Bereits bestehende Board-Farbwahl Grün/Schwarz** darf durch die neue
  Invertierungslogik nicht überschrieben werden — nur der
  Weiß-Standardfall bekommt die automatische Umkehr (siehe Nutzer-
  Entscheidung in Abschnitt 9).
- **PDF-Export auf Seiten ohne Tafelmodus/Notizen** (der Normalfall auf den
  meisten Seiten) darf durch die A/B-Korrektur nicht verlangsamt oder
  beeinträchtigt werden.
- **Bereits abgeschlossene Darkmode-Kontrastkorrekturen** an
  `cbd-frontend-clean.css`/`latex-formulas.css`/`floating-pdf-button.js`
  dürfen durch die `board-mode.css`-Ergänzung nicht angetastet werden.

## 8. Konventions-Konformität

- Neue/geänderte Regeln in `board-mode.css` ausschließlich als
  `[data-theme="dark"] .selektor`, nie `@media (prefers-color-scheme:
  dark)`.
- Neue Farbwerte über `var(--x, #fallback)`, außer bei der bewusst
  ausgenommenen Inhaltsfarbe (Zeichenfläche/Stiftfarben).
- PHP-Änderungen in `class-cbd-pdf-generator.php` müssen
  `tools/check-php74.php` weiterhin grün lassen.
- Jede Änderung an `board-mode.css`/`pdf-server-side.js` etc. zieht die
  entsprechende Zeile in `reference_file_map.md` nach (Projektpflicht).
- Debug-Ausgaben weiterhin hinter `window.cbdDebug` gaten (JS) bzw.
  `WP_DEBUG`-Gates (PHP), wie im gesamten Plugin üblich.

## 9. Risiken & offene Fragen

- **Bug A ist bislang unbestätigt in der Ursache.** Der Vorgängerplan
  (`docs/archiv/PLAN-PDF-Notizen-und-Listenformeln.md`) hat den
  Injektionsmechanismus nie an einer tatsächlich erzeugten PDF-Datei
  überprüft — das war dort als „niedrig-riskant" akzeptierte Lücke
  vermerkt, manifestiert sich jetzt aber als realer Nutzerfehler. Beim
  Code-Durchgang für diese Analyse fielen zwei konkrete Verdachtsstellen
  auf (unbestätigt, brauchen Live-Diagnose):
  1. `class-cbd-pdf-generator.php::clean_block_html()` entfernt via Regex
     **das gesamte** `style="..."`-Attribut jedes Elements, dessen Style
     irgendwo `display:none` enthält — nicht nur die Eigenschaft. Trifft
     das eine der Zeichnungs-Wrapper (direkt oder ein Vorfahre im HTML),
     verschwindet auch das `<img>` darin ersatzlos.
  2. `pdf-server-side.js::processOneBlock()` entfernt zu Beginn
     vorhandene Elemente mit den Klassen `.cbd-drawing-section`,
     `.cbd-local-drawing-section`, `.cbd-class-drawing-section` — die
     tatsächlich neu eingefügten Wrapper heißen aber
     `.cbd-pdf-drawing-section`. Die Aufräum-Selektoren greifen also nie;
     bei wiederholten Exports könnten sich Notizen dadurch **duplizieren**
     statt zu fehlen — ein Nebenbefund, kein Erklärungsversuch für das
     gemeldete Fehlen, aber im selben Codepfad und sollte mitkorrigiert
     werden.
  → **Gegenmaßnahme:** Erste AP dieses Strangs ist eine Live-Diagnose
    (echten Export mit „Eigener Notiz" UND Server-Tafelbild auslösen, die
    erzeugte PDF-Datei tatsächlich öffnen), nach demselben, im Projekt
    etablierten Muster wie AP-1.1 des Vorgängerplans — **bevor** Code
    geändert wird.
- **Bug B ist wahrscheinlich (teilweise) außerhalb der Kontrolle des
  Plugins.** `downloadPDF()` verwendet bereits die technisch korrekte
  `<a download>`-Methode; moderne Browser respektieren das für
  Same-Origin-URLs ohne Nachfrage — **außer** der Nutzer hat in seinem
  Browser selbst „Vor jedem Download nachfragen, wo die Datei gespeichert
  werden soll" aktiviert (Chrome/Edge-Einstellung). Diese Einstellung kann
  keine Website per JavaScript übersteuern. → **Gegenmaßnahme:** Ein AP
  verifiziert die aktuelle Implementierung, prüft testweise mit
  deaktivierter Browser-Nachfrage-Einstellung, ob dann tatsächlich direkt
  gespeichert wird, und ergänzt defensiv einen `Content-Disposition:
  attachment`-Header (aktuell liefert der Webserver die erzeugte
  PDF-Datei als gewöhnliche statische Datei ohne diesen Header aus). Kann
  die Nachfrage danach immer noch nicht verschwinden, ist das eine
  Browser-Einstellung des Nutzers und **kein** behebbarer Code-Fehler —
  das AP muss diesen Befund dann explizit so festhalten, statt einen
  Blindflug-Fix zu erzwingen.
- **Offene Frage an den Nutzer (bereits beantwortet):** Invertierungsart
  für „Eigene Notiz" im Darkmode → **CSS-Filter auf die Zeichenfläche**,
  nur wenn die Tafel auf der weißen Standardfarbe steht (siehe Abschnitt
  6). Damit bewusst gewählte Grün-/Schwarz-Tafeln unangetastet bleiben.
- **Risiko bei der Filter-Lösung:** `filter: invert(1)` kehrt **alle**
  Farben um, nicht nur Schwarz/Weiß — enthält eine „Eigene Notiz" bunte
  Stiftfarben, wirken diese nach der Umkehr ggf. ungewohnt (z. B. Rot wird
  Cyan). Das ist mit der getroffenen Nutzerentscheidung bewusst in Kauf
  genommen und sollte im Test-AP mit einer mehrfarbigen Beispielnotiz
  sichtgeprüft werden, damit das Ergebnis nicht überrascht.
- **Doku-Lücke:** Es existiert kein automatischer Test/Harnisch für den
  PDF-Bildpfad (weder für lokale Notizen noch Server-Tafelbilder) — die
  Prüfung bleibt manuell (Live-Diagnose + Sichtprüfung). Das ist keine
  Lücke, die dieser Plan zwingend schließen muss, aber im Plan als
  bekannte Einschränkung zu vermerken.

## 10. Grobzuschnitt für den projektplan-skill

Vorschlag für bis zu vier weitgehend unabhängige Arbeitspaket-Stränge
(finale Aufteilung entscheidet der projektplan-skill):

1. **Strang PDF-Bilder + Direktdownload (A+B, ein Agent/eine Reihenfolge,
   da beide dieselbe Datei `pdf-server-side.js` anfassen):**
   Live-Diagnose (echten Export mit Notiz+Tafelbild auslösen, PDF öffnen)
   → Ursache(n) beheben (Verdachtsstellen aus Abschnitt 9 zuerst prüfen)
   → Direktdownload verifizieren/absichern → Testprotokoll mit
   tatsächlich geöffneter PDF-Datei.
2. **Strang Tafelmodus-Darkmode (C, unabhängiger Agent):**
   `[data-theme="dark"]`-Regeln für `board-mode.css` (Werkzeugleiste,
   Overlay, Dialoge) nach dem etablierten Muster → Invertierungslogik in
   `board-mode.js`/`board-mode.css` für die Zeichenfläche (nur bei
   `boardColor === '#ffffff'`) → Sichtprüfung mit ein-/mehrfarbiger Notiz
   im Hell- und Dunkelmodus.
3. **Review-AP (verpflichtend, nach projektplan-skill-Konvention):**
   unabhängige Prüfung beider Stränge, insbesondere der
   Regressionsfläche aus Abschnitt 7.
4. **Dokumentations-AP:** `CLAUDE.md` (Abschnitte „Darkmode" und
   „PDF-Export: Tafelbilder und eigene Notizen"), `reference_file_map.md`,
   `DOKUMENTATION.md` auf den neuen Stand bringen; diesen Plan im
   Abschnitt „Status"/„Rückblick" abschließen.

Stränge 1 und 2 sind dateidisjunkt und können parallel laufen; Strang 3
und 4 sind von deren Abschluss abhängig.
