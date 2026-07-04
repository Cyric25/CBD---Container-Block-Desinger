# Verbesserungsplan 4 (vierter Review-Durchgang, 2026-07-04)

Frischer Durchgang durch die zuletzt noch ungeprüften Tiefen: drag-and-drop/view.js
(1231 Zeilen), Classroom-Schüler-Authentifizierung, mPDF-Generierungsrumpf,
Content-Importer-Parsing, iframe-whitelist (Block + Manager), Theme-style.css
sowie Querschnittsprüfungen (block.json-Konsistenz, i18n, Touch-Verhalten aller
Drag-Interaktionen). Nummerierung: AP39+.

## Rahmenbedingungen (wie Plan 1–3)

- CDB-Designer: **PHP 7.4**, vor ZIP `php tools/check-php74.php`
- Eigene WP Blocks: Block-Änderungen → `npm run build && npm run block-zips`
- Debug: PHP hinter `WP_DEBUG`, JS hinter `window.cbdDebug`

---

## ✅ Was im vierten Durchgang positiv auffiel

- **drag-and-drop/view.js:** Vollständige Interaktions-Matrix (HTML5-Drag, Touch
  mit visuellem Clone, Tap-to-Select als Alternative, Keyboard, Fullscreen-API,
  ARIA `aria-grabbed`), inklusive Init-Guard und Infinite-Draggables-Klonen.
- **statement-connector:** Touch vorbildlich — document-level `touchmove` wird
  nur während eines aktiven Drags registriert und danach wieder entfernt.
- **Classroom-Schüler-Auth:** Rate-Limiting (10 Versuche/5 min/IP), gehashte
  Klassenpasswörter, 64-Zeichen-Zufallstoken in Transients, Lehrer-Bypass korrekt
  über Capability + Nonce abgesichert.
- **iframe-whitelist:** Serverseitige Whitelist-Prüfung beim Rendern (nicht nur
  im Editor), `loading="lazy"`.
- **block.json-Konsistenz:** Alle 13 Blöcke einheitlich apiVersion 3, korrekte
  textdomain (einzige Ausnahme: svg-drawing, siehe AP41).
- **Theme-style.css:** Keine Hover-gated-Reveals; alle Einblendungen sind
  zustandsbasiert (Klassen), Lightbox/Sidebar touch-tauglich.
- **mPDF-Konfiguration:** Sinnvolle Defaults (dejavusans für UTF-8, Block-weises
  WriteHTML für saubere Seitenumbrüche, PCRE-Limits mit Restore).

---

## P1 — Funktionsfehler

### AP39: `return '<html>'` in render.php wird verschluckt — Fehlermeldungen von 10 Blöcken unsichtbar

**Dateien:** `Plugins/Eigene WP Blocks/blocks/{drag-and-drop:51, drag-the-words:40+44,
iframe-whitelist:25+38, image-comparison:48, image-overlay:63, multiple-choice:37,
point-of-interest:40, statement-connector:43, summary-block:46}/render.php`
(NICHT drag-the-words:68 — das ist ein legitimer Callback-Return)

**Problem:** Die render.php-Dateien werden von
[`render_dynamic_block()`](Plugins/Eigene WP Blocks/includes/class-block-manager.php)
per `ob_start(); include $render_file; return ob_get_clean();` eingebunden.
Ein `return '<div>…</div>';` auf Top-Level der inkludierten Datei wird zum
**Rückgabewert des include** — den der Block-Manager verwirft. In den Buffer
gelangt nichts. Folge: Sämtliche „Bitte konfigurieren Sie …"-Meldungen und
Platzhalter (fehlende Bilder, leere Wortbank, nicht-whitelisted iframe-URL, …)
rendern als **komplett leerer Block**. Besonders unglücklich beim
iframe-whitelist-Block: Die Meldung „URL nicht autorisiert" erscheint nie.

**Fix (mechanisch, pro Fundstelle):**

```php
// vorher:
return '<div class="...">' . esc_html__('...') . '</div>';
// nachher:
echo '<div class="...">' . esc_html__('...') . '</div>';
return;
```

**Verifikation:** iframe-whitelist-Block mit nicht-gelisteter URL → Fehlerbox
erscheint im Frontend; multiple-choice ohne Antworten → „Keine Antworten
konfiguriert." sichtbar.

**Deploy:** 9 Block-ZIPs neu bauen und hochladen.

---

## P2 — Touch-UX

### AP40: Scroll-Falle auf Touch-Geräten in drag-and-drop und drag-the-words

**Dateien:** [blocks/drag-and-drop/view.js:219-258](Plugins/Eigene WP Blocks/blocks/drag-and-drop/view.js),
[blocks/drag-the-words/view.js:~145-160](Plugins/Eigene WP Blocks/blocks/drag-the-words/view.js)

**Problem:** `touchstart` auf einem Draggable startet SOFORT den Drag-Modus,
und `touchmove` ruft dann immer `preventDefault()` auf. Wer auf dem Handy/Tablet
die Seite scrollen will und dabei zufällig auf einem ziehbaren Element ansetzt
(bei großen Wortbänken ein erheblicher Teil des Viewports), kann **nicht
scrollen** — die Seite „klebt". statement-connector zeigt das richtige Muster
(Listener nur während aktivem Drag).

**Fix:** Bewegungsschwelle einführen — Drag erst übernehmen, wenn sich der
Finger ≥ 10 px horizontal ODER die Geste eindeutig nicht-vertikal bewegt:

```js
handleTouchStart: Startkoordinaten nur merken, NICHTS verhindern, kein Drag-Start.
handleTouchMove:
  const dx = touch.clientX - startX, dy = touch.clientY - startY;
  if (!dragActive) {
      if (Math.abs(dy) > Math.abs(dx) && Math.abs(dy) > 10) {
          touchData = null; return;   // vertikale Geste → Browser scrollen lassen
      }
      if (Math.hypot(dx, dy) < 10) return;  // noch unklar → abwarten
      dragActive = true;  // ab hier: Clone erzeugen, Zonen highlighten
  }
  event.preventDefault(); // nur noch im aktiven Drag
```

Der bestehende Tap-to-Select-Pfad bleibt unberührt (Click-Handler).
Hinweis: `{ passive: false }` muss bleiben, sonst wirkt preventDefault nicht.

**Verifikation:** Auf Touch-Gerät (oder DevTools-Emulation) über der Wortbank
vertikal wischen → Seite scrollt; horizontal ziehen → Wort wird gezogen.

---

## P3 — Kleinigkeiten

### AP41: svg-drawing/block.json ohne textdomain

**Datei:** [blocks/svg-drawing/block.json](Plugins/Eigene WP Blocks/blocks/svg-drawing/block.json)

Als einziger der 13 Blöcke ohne `"textdomain": "modular-blocks-plugin"` —
Editor-Strings (title/description) laufen dadurch nicht durch die Übersetzung.
Fix: Feld ergänzen. (Kein viewScript ist korrekt — der Block ist editor-seitig.)

### AP42: Unübersetzte Button-Titel im CDB-Renderer

**Datei:** [class-cbd-block-registration.php:1045, 1077, 1092](Plugins/CDB-Designer/includes/class-cbd-block-registration.php)

`title="Ein-/Ausklappen"`, `title="Screenshot erstellen"`, `title="Als PDF
exportieren"` sind hartkodiert, während Board-Mode/Behandelt-Buttons korrekt
`esc_attr__()` nutzen. Fix: die drei Titel in
`esc_attr__('…', 'container-block-designer')` wrappen. Kosmetisch (Site ist
deutschsprachig), aber inkonsistent.

---

## Abarbeitungs-Checkliste

| AP | Titel | Status |
|----|-------|--------|
| 39 | echo statt return in 9 render.php | ✅ ERLEDIGT (2026-07-04) — 11 Fundstellen umgestellt; der Callback-Return in drag-the-words:68 blieb korrekt als return (ist der Ersetzungsstring von preg_replace_callback) |
| 40 | Touch-Bewegungsschwelle | ✅ ERLEDIGT (2026-07-04) — drag-and-drop + drag-the-words: Drag startet erst ab 10px nicht-vertikaler Bewegung; vertikale Geste scrollt. Zusatzeffekt: das frühere preventDefault in touchstart hatte auch das Click-Event unterdrückt — Tap-to-Place funktioniert auf Touch jetzt überhaupt erst. **Auf echtem Tablet gegentesten!** |
| 41 | svg-drawing textdomain | ✅ ERLEDIGT (2026-07-04) |
| 42 | Button-Titel übersetzen | ✅ ERLEDIGT (2026-07-04) — esc_attr__() für alle drei |

**Einschätzung nach vier Durchgängen:** Der Ertrag ist deutlich abgeflacht —
Runde 4 fand einen echten Funktionsfehler (AP39, seit jeher latent, weil
korrekt konfigurierte Blöcke nie in die Fehlerpfade laufen) und eine
Touch-UX-Schwäche (AP40). Kern-Rendering, Auth, Caching und Assets sind nach
Plan 1–3 in gutem Zustand. Weitere Reviews lohnen erst wieder nach größeren
Feature-Änderungen; sinnvoller wäre als Nächstes ein manueller Gerätetest
(Tablet) der interaktiven Blöcke.
