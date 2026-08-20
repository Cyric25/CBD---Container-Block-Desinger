# Plan: Aktionsleiste blendet sich nach einer Sekunde aus

_Erstellt am: 2026-08-21 · Eigenständiges Kleinvorhaben · Komponente: CDB-Designer (v3.1.94)_

**Dieser Plan ist unabhängig von den drei Geschwisterplänen**
(`PLAN-Importer-Elternseite.md`, `PLAN-Formeln-in-Blocktiteln.md`,
`Theme/docs/PLAN-Inhaltsverzeichnis-Navigation.md`). Ihre Dateimengen sind
disjunkt; alle vier dürfen gleichzeitig laufen.

## 0. Anweisungen für den ausführenden Agenten

Du hast keinen Zugriff auf das Gespräch, in dem dieser Plan entstand. Er ist
die einzige Wahrheitsquelle.

1. Bearbeite die Arbeitspakete der Reihe nach. Bleib strikt im Scope.
2. Commit-Nachrichten **ohne Anführungszeichen** — die Shell dieses Projekts
   ist PowerShell und übergibt den Text sonst als Pathspec. Mehrzeilige
   Nachrichten im Bash-Werkzeug per echtem Heredoc, **keine**
   PowerShell-Here-Strings.
3. Kein `git add .` und kein `git add -A` — immer nur die eigenen Pfade.
4. **Kein Build-Schritt.** Das Plugin liefert jede JS-Datei **unverändert**
   an den Browser aus. Kein JSX, kein `import`/`export`, keine Arrow
   Functions, keine Template-Literale. Hausstil in
   `assets/js/interactivity-fallback.js` ansehen und übernehmen.
5. **Debug-Ausgaben gaten:** `console.log` nur hinter `window.cbdDebug`.
   `console.warn` und `console.error` dürfen ungegatet bleiben.
6. **Tote CSS-Dateien nicht anfassen.** `assets/css/frontend.css`,
   `assets/css/frontend-positioning.css` und
   `assets/css/unified-frontend.css` sind in **keinem** `wp_enqueue_style()`
   referenziert, enthalten aber dieselben Selektoren. Wer dort ändert, sieht
   nichts passieren. Lebende Datei ist `assets/css/cbd-frontend-clean.css`.
7. **Keine Versionsnummer erhöhen.**

## 1. Ziel

Die Aktionsleiste in der oberen rechten Ecke eines Container-Blocks
(Klappen, Kopieren, Screenshot, PDF, Tafelmodus, Behandelt) soll sich
**eine Sekunde nach dem Erscheinen von selbst ausblenden**, statt zu bleiben,
solange der Zeiger über dem Block steht.

Nutzen: Auf Tablets haftet `:hover` nach dem Antippen. Die Leiste bleibt dort
über dem Inhalt stehen, bis irgendwo anders getippt wird — beim Lesen
störend, weil sie die obere rechte Ecke des Blocks verdeckt.

## 2. Nicht-Ziele

- **Die Leiste wird nicht abgeschafft und nicht verschoben.** Nur ihre
  Standzeit ändert sich.
- **Keine Änderung an den Feature-Flags.** Welche Knöpfe erscheinen,
  entscheidet weiterhin das Block-Design; die Projektentscheidung „Buttons
  folgen Feature-Flags" bleibt unberührt.
- **`class-cbd-block-registration.php` wird nicht angefasst.** Der Knopf wird
  weiterhin serverseitig erzeugt; eine gerätespezifische Ausgabe würde jeden
  Full-Page-Cache vergiften.
- **Kein Umbau der Interactivity-API-Anbindung.**

## 3. Kontext & Constraints

- **Komponente:** `Plugins/CDB-Designer/`, Version 3.1.94, Branch `main`.
- **Testumgebung:** `C:\allinkl-testserver`, Start über `start-server.cmd`,
  WordPress unter `http://fos.localhost:8080/`. Installationspfad
  `C:\allinkl-testserver\www\htdocs\w0000001\fos`. Admin `admin` /
  `Testserver2026!`. **Die Plugins liegen dort als Kopie** — nach einer
  Änderung dorthin kopieren. Bei HTTP 503 die `.maintenance`-Datei und
  `wp-content/upgrade/wordpress-*` löschen.
  Seiten mit Container-Blöcken: 43–47, 54, 55, 62, 76.
- **Zwei Frontend-Skripte, von denen genau eines lädt:** Das Plugin bindet
  `assets/js/interactivity-store.js` ein, wenn die WordPress-Interactivity-API
  verfügbar ist, sonst `assets/js/interactivity-fallback.js` (jQuery). **Die
  Logik muss deshalb in beiden stehen** — dasselbe Muster wie bei
  `istAppleGeraet()`, das dort ebenfalls doppelt gepflegt wird.

## 4. Ausgangslage

Die Sichtbarkeit der Leiste ist **rein CSS-gesteuert**; kein JavaScript fasst
sie an (geprüft über alle `assets/js/*.js` — die Treffer dort betreffen das
Entfernen der Leiste aus PDF-Klonen und das Umschalten einzelner Knöpfe,
nicht die Sichtbarkeit).

`assets/css/cbd-frontend-clean.css`:

| Zeile (etwa) | Regel |
|---|---|
| ~1063 | `.cbd-action-buttons` — Grundzustand: `opacity: 0`, `visibility: hidden`, `pointer-events: none`, alles mit `!important` |
| ~1084 | `.cbd-container:hover .cbd-action-buttons`, `…:focus-within …`, `.cbd-container.cbd-selected …` — blenden ein, ebenfalls mit `!important` |

`.cbd-selected` ist bereits ein von außen gesetzter Klassen-Haken — die
Ausgangslage kennt das Muster also schon.

| Datei | Rolle heute | Änderung |
|---|---|---|
| `assets/css/cbd-frontend-clean.css` | lebende Frontend-Stildatei | ändern |
| `assets/js/interactivity-store.js` | Frontend über die Interactivity API | ändern |
| `assets/js/interactivity-fallback.js` | Frontend über jQuery | ändern |
| `includes/class-cbd-block-registration.php` | erzeugt die Leiste serverseitig | **nur lesen** |

## 5. Architekturentscheidungen

| Entscheidung | Begründung | Verworfene Alternative |
|---|---|---|
| **JavaScript setzt eine Klasse am Container, das Ausblenden selbst macht CSS** | Der Grundzustand steht schon im CSS und arbeitet durchgehend mit `!important`. Ein Wettlauf zwischen Inline-Styles und `!important`-Regeln wäre schwer nachvollziehbar; eine Klasse mit höherer Spezifität ist die ruhigere Lösung und bleibt im Stylesheet sichtbar | Inline-Styles aus JS: kämpfen gegen `!important` und verstecken das Verhalten vor jedem, der nur das CSS liest |
| **Der Zeitgeber läuft nicht, solange der Zeiger über der Leiste selbst steht** | Sonst verschwände die Leiste, während man auf einen ihrer Knöpfe zielt — sie wäre nach einer Sekunde nicht mehr bedienbar. Das ist kein Randfall, sondern der Normalfall der Bedienung | Stur nach 1 s ausblenden: macht die Knöpfe praktisch unerreichbar |
| **Der Zeitgeber läuft nicht, solange der Container `:focus-within` ist** | Tastaturnutzer springen mit Tab in die Knöpfe. Verschwände die Leiste dabei, ginge der Fokus ins Leere und die Funktion wäre per Tastatur unerreichbar — ein Rückschritt in der Bedienbarkeit, der als „Feinschliff" durchginge | Fokus ignorieren: schließt Tastaturnutzer aus |
| **Beim Verlassen des Containers wird der Zustand zurückgesetzt** | Sonst bliebe die Leiste beim nächsten Überfahren dauerhaft unsichtbar. Der Zeitgeber muss also an das Erscheinen gekoppelt sein, nicht einmalig laufen | Einmalig ausblenden: die Leiste käme nie wieder |
| **Die Wartezeit steht als benannte Konstante in beiden Dateien** | Eine Zahl im Code, die an zwei Stellen steht, läuft auseinander. Ein Name macht sichtbar, dass es dieselbe Größe ist | Zahl direkt im Aufruf: unauffindbar beim nächsten Ändern |

## 6. Risiken

| Risiko | Auswirkung | Gegenmaßnahme |
|---|---|---|
| **Die Leiste wird unbedienbar, weil sie beim Zielen verschwindet** | **hoch** — das wäre eine Verschlechterung, keine Verbesserung | Zeitgeber pausiert über der Leiste und bei `:focus-within` (siehe Architektur). AP-3 prüft beides ausdrücklich |
| **Nur eines der beiden Frontend-Skripte bekommt die Änderung** | mittel | Auf einer Installation lädt genau eines. Fehlt die Logik im anderen, tritt der Effekt bei manchen Nutzern gar nicht auf und niemand merkt es. AP-2 hat die Gleichheit beider Fassungen als Akzeptanzkriterium |
| **Die Änderung landet in einer toten CSS-Datei** | mittel | Drei Dateien mit denselben Selektoren sind nicht eingebunden. Nur `cbd-frontend-clean.css` ist lebend |
| **Kollision mit dem Tafelmodus** | gering | `assets/css/board-mode.css` hat eine eigene Regel für `.cbd-board-content .cbd-action-buttons`. AP-3 prüft den Tafelmodus gegen Regression |

**Rollback:** Rein additiv. Vor dem ersten AP `git tag vor-aktionsleiste`,
Rückweg `git reset --hard vor-aktionsleiste`.

## 7. Arbeitspakete

### AP-1: CSS-Regel für den ausgeblendeten Zustand

**Modell:** sonnet
**Dateien:** `assets/css/cbd-frontend-clean.css`

**Umsetzung:**

1. Neue Regel unmittelbar **nach** dem bestehenden Einblend-Block (etwa
   Zeile 1084–1092), damit die Reihenfolge im Stylesheet die Absicht
   widerspiegelt:

   ```
   .cbd-container.cbd-actions-verborgen:hover .cbd-action-buttons,
   .cbd-container.cbd-actions-verborgen.cbd-selected .cbd-action-buttons {
       opacity: 0 !important;
       visibility: hidden !important;
       transform: translateY(-5px) !important;
       pointer-events: none !important;
   }
   ```

2. **`:focus-within` bewusst NICHT in diese Regel aufnehmen.** Solange
   irgendein Knopf der Leiste den Fokus hat, muss sie sichtbar bleiben — die
   bestehende `:focus-within`-Regel gewinnt dann über die Spezifität, weil
   dieser Selektor sie gar nicht erwähnt. Das ist der eigentliche Kniff
   dieses APs; als Kommentar in die Datei schreiben.
3. Einen Kommentarblock voranstellen: was die Klasse bedeutet, wer sie setzt
   (beide Frontend-Skripte), und warum `:focus-within` fehlt.

**Akzeptanzkriterien:**

- AK1: Ohne die Klasse verhält sich die Leiste unverändert.
- AK2: Mit der Klasse ist sie beim Überfahren unsichtbar und nicht klickbar.
- AK3: Mit der Klasse **und** Fokus in einem Knopf bleibt sie sichtbar und
  bedienbar.
- AK4: Die Regel steht ausschließlich in `cbd-frontend-clean.css`; die drei
  toten Dateien sind unverändert (Nachweis über `git diff`).

---

### AP-2: Zeitgeber in beiden Frontend-Skripten

**Modell:** sonnet
**Abhängigkeiten:** AP-1
**Dateien:** `assets/js/interactivity-store.js`,
`assets/js/interactivity-fallback.js`

**Umsetzung — in beiden Dateien fachlich gleich, im jeweiligen Hausstil
(Interactivity-API bzw. jQuery):**

1. Konstante `CBD_AKTIONSLEISTE_VERZOEGERUNG = 1000` mit erklärendem
   Kommentar.
2. Je Container:
   - `mouseenter` / `pointerenter` am Container: vorhandenen Zeitgeber
     abbrechen, Klasse `cbd-actions-verborgen` entfernen, neuen Zeitgeber
     starten.
   - Nach Ablauf: Klasse setzen — **außer** der Container enthält gerade den
     Fokus (`container.matches(':focus-within')`) oder der Zeiger steht über
     der Leiste.
   - `mouseenter` an `.cbd-action-buttons`: Zeitgeber abbrechen, Klasse
     entfernen.
   - `mouseleave` an `.cbd-action-buttons`: Zeitgeber neu starten.
   - `mouseleave` am Container: Zeitgeber abbrechen und Klasse entfernen
     (Zustand zurücksetzen).
   - `focusin` im Container: Zeitgeber abbrechen, Klasse entfernen.
     `focusout`: Zeitgeber neu starten.
3. **Touch:** Auf Geräten ohne Zeiger feuert `mouseenter` beim Tippen
   einmalig — das genügt und ist der eigentliche Anlass dieses Vorhabens.
   Keine eigene `touchstart`-Behandlung ergänzen; in
   `interactivity-fallback.js` gibt es bereits eine Touch-Behandlung für
   Knopfklicks (etwa Zeile 570), die davon unberührt bleibt.
4. Zeitgeber je Container halten (`WeakMap` oder Datenattribut), **nicht** in
   einer einzelnen Modulvariablen — sonst löschte ein zweiter Container den
   Zeitgeber des ersten.

**Akzeptanzkriterien:**

- AK1: Die Leiste blendet sich rund eine Sekunde nach dem Erscheinen aus.
- AK2: Sie bleibt sichtbar, solange der Zeiger über ihr steht.
- AK3: Sie bleibt sichtbar, solange ein Knopf den Fokus hat.
- AK4: Nach dem Verlassen und erneuten Überfahren erscheint sie wieder.
- AK5: Mehrere Container auf einer Seite stören sich nicht.
- AK6: Die fachliche Logik ist in **beiden** Dateien vorhanden. Nachweis:
  `grep -c cbd-actions-verborgen` in beiden liefert einen Wert größer 0.
- AK7: `node --check` auf beide Dateien grün; kein `console.log` außerhalb
  von `window.cbdDebug`; kein `let`, `const`, Arrow oder Template-Literal,
  wo der Hausstil der jeweiligen Datei das nicht ohnehin schon führt.

---

### AP-3: Abnahme auf dem Testserver

**Modell:** sonnet
**Abhängigkeiten:** AP-1, AP-2

Auf einer Seite mit mehreren Container-Blöcken (etwa 54, 62 oder 76), nach
dem Kopieren der Dateien auf den Testserver:

1. Zeiger über einen Block: Leiste erscheint, verschwindet nach etwa 1 s.
2. Zeiger über die Leiste ziehen, bevor sie verschwindet: Sie bleibt, und die
   Knöpfe lassen sich klicken.
3. Mit Tab in die Leiste springen: Sie bleibt sichtbar, bis der Fokus sie
   verlässt.
4. Block verlassen und erneut überfahren: Leiste erscheint wieder.
5. Zwei Blöcke schnell nacheinander überfahren: kein gegenseitiges Stören.
6. **Regression Tafelmodus:** Tafelmodus öffnen, Leiste dort prüfen.
7. **Regression PDF:** Einzelblock-PDF erzeugen — die Leiste darf im
   Ergebnis nicht auftauchen (sie wird aus dem Klon entfernt).
8. **Regression Klappen/Kopieren/Screenshot:** je einmal auslösen.
9. Auf einem Tablet oder in der Touch-Emulation der Entwicklerwerkzeuge:
   Antippen zeigt die Leiste, nach 1 s ist sie weg — das ist der Anlass des
   Vorhabens.
10. `debug.log` und Browserkonsole ohne neue Meldungen.

---

### AP-4: Dokumentation

**Modell:** sonnet
**Abhängigkeiten:** AP-3
**Dateien:** `CLAUDE.md`, `reference_file_map.md`, dieser Plan

1. In `CLAUDE.md` einen kurzen Abschnitt: die Klasse, wer sie setzt, warum
   `:focus-within` bewusst ausgespart ist, und dass die Logik in **beiden**
   Frontend-Skripten steht — mit dem Hinweis, dass genau eines davon lädt.
2. `reference_file_map.md`: die Zeilen der drei geänderten Dateien ergänzen.
3. **Nur mit dem Edit-Werkzeug**, niemals per PowerShell-Lese-Schreib-Zyklus
   (Mojibake-Gefahr). Nachweis: `grep -c 'Ã\|â€' <datei>` liefert 0.

## 8. Status

| AP | Titel | Modell | Abhängig von | Status |
|---|---|---|---|---|
| AP-1 | CSS-Regel für den ausgeblendeten Zustand | sonnet | – | ☐ |
| AP-2 | Zeitgeber in beiden Frontend-Skripten | sonnet | 1 | ☐ |
| AP-3 | Abnahme auf dem Testserver | sonnet | 1, 2 | ☐ |
| AP-4 | Dokumentation | sonnet | 3 | ☐ |

## 9. Testprotokoll

| AP | Test | Ergebnis | Datum |
|---|---|---|---|
| AP-1 | Tote CSS-Dateien unverändert | – | – |
| AP-2 | `node --check` auf beide Skripte | – | – |
| AP-2 | Logik in beiden Dateien vorhanden | – | – |
| AP-3 | Ausblenden, Zeiger über der Leiste, Fokus | – | – |
| AP-3 | Regression Tafelmodus, PDF, Klappen/Kopieren | – | – |
| AP-3 | Touch-Verhalten | – | – |
| AP-4 | Mojibake-Kontrolle | – | – |
