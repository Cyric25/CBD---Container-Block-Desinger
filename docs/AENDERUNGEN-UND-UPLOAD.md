# Änderungs-Zusammenfassung & Upload-Anleitung (Stand 2026-07-04)

Alle Änderungen aus den vier Review-Runden (VERBESSERUNGSPLAN.md bis -4.md,
42 Arbeitspakete) plus Hotfix. Alles ist committet und auf GitHub gepusht.

---

## ⚠️ ZUERST: Kritischen 500-Fehler beheben (Hotfix)

**Was passiert ist:** Die Plugin-ZIPs v3.1.63–v3.1.65 enthielten einen
Composer-Autoloader, der Dateien aus den (im ZIP nicht enthaltenen)
Entwickler-Paketen fest einband → Fatal Error bei jedem Seitenaufruf → HTTP 500
auf der ganzen Website inkl. wp-admin.

**Behoben in:** `container-block-designer-3.1.69.zip` (verifiziert: entpacktes
ZIP lädt fehlerfrei; das Build-Skript ist dauerhaft korrigiert).

**So bekommst du die Seite wieder hoch:**

1. **Per FTP oder Hosting-Dateimanager** ins Verzeichnis
   `wp-content/plugins/` gehen und den Ordner
   `container-block-designer` **umbenennen** (z. B. in
   `container-block-designer-OFF`).
   → Die Website läuft sofort wieder (WordPress deaktiviert das Plugin).
   Es gehen KEINE Daten verloren — alle Block-Designs liegen in der Datenbank.
2. Im WordPress-Admin anmelden → Plugins → der Eintrag ist als deaktiviert/
   fehlend markiert. Den umbenannten Ordner per FTP löschen.
3. **`container-block-designer-3.1.69.zip`** hochladen
   (Plugins → Installieren → Plugin hochladen) und aktivieren.
4. Beim Aktivieren läuft automatisch die Feature-Key-Migration (collapse/
   copyText) — einmalig, dauert Sekunden.

Alternative, falls du eine „Technisches Problem"-E-Mail von WordPress bekommen
hast: der Recovery-Link darin führt in den Wiederherstellungsmodus, dort das
Plugin deaktivieren, dann Schritt 2–4.

---

## 📤 Upload-Liste (was wohin muss)

| # | Datei | Wohin | Warum |
|---|---|---|---|
| 1 | `Plugins/CDB-Designer/dist/container-block-designer-3.1.69.zip` (47 MB) | Plugins → Plugin hochladen (nach Hotfix-Schritten oben) | Alle CDB-Fixes + lokale Fonts/KaTeX. **NICHT 3.1.63–3.1.68 verwenden (3.1.63-65: Autoloader-Fatal; 3.1.66-68: 404-CSS bzw. Debug-Zeilen)!** |
| 2 | `Plugins/Eigene WP Blocks/plugin-zips/modular-blocks-plugin-empty-1.0.6.zip` (1,2 MB) | Plugins → vorhandenes Basis-Plugin ERSETZEN (deaktivieren, löschen, neu hochladen — Blöcke in `blocks/` bleiben erhalten, liegen aber im Plugin-Ordner: vorher Block-ZIP-Uploads bereithalten!) | Core-Fixes: Discovery-Cache, ChemViz, Throwable, Logging, Shortcodes |
| 3 | Block-ZIPs aus `Plugins/Eigene WP Blocks/block-zips/`: **alle 13** (sicherste Variante) oder mindestens: drag-and-drop, drag-the-words, iframe-whitelist, image-comparison, image-overlay, molecule-viewer, multiple-choice, point-of-interest, statement-connector, summary-block, interactive-data-chart, svg-drawing | Einstellungen → Modulare Blöcke → Block hochladen (je ZIP) | Doppel-Init-Fixes, sichtbare Fehlermeldungen, Touch-Scroll-Fix |
| 4 | `Theme/dist/fos-online-schulbuch.zip` (v1.5.58, 75 KB) | Design → Themes → Theme hochladen (ersetzen) | Log-Gate + HOTFIX sidebar.php (v1.5.57 war fehlerhaft: Fatal auf Seiten mit Sidebar) |

**Achtung bei Schritt 2:** Beim Löschen des Basis-Plugins über den WordPress-
Plugin-Bildschirm wird der GESAMTE Plugin-Ordner inkl. hochgeladener Blöcke
entfernt. Deshalb: erst Basis neu installieren, dann sofort die Block-ZIPs
(Schritt 3) wieder hochladen. Einstellungen (aktivierte Blöcke, Whitelist)
bleiben in der Datenbank erhalten.

**Nach allen Uploads einmal testen:**
- Seite mit Container-Block auf einem Tablet: Buttons sichtbar & tippbar
- Multiple-Choice durchklicken (kein doppeltes Umschalten)
- drag-the-words auf Touch: vertikal wischen = scrollen, horizontal = ziehen, Tap-to-Place
- Seite mit Formel (`$$...$$`): rendert, Network-Tab ohne externe CDN-Requests
- Block-Design mit „Klappbar": Einklappen funktioniert

---

## 📋 Zusammenfassung aller Änderungen (4 Review-Runden)

### Behobene Funktionsfehler

1. **Feature-Key-Chaos `collapse`/`collapsible`** — Style-Loader las andere
   Keys als der Admin schrieb; Klapp-/Copy-CSS wurde nie generiert. Vereinheitlicht
   inkl. automatischer DB-Migration beim Update.
2. **Erzwungenes Icon** — Debug-Rest `$has_icon = true` zeigte für jeden Block
   einen Icon-Header, egal was eingestellt war.
3. **„Als Standard setzen" defekt** — das Zurücksetzen der anderen Blöcke
   schlug wegen leerem WHERE still fehl → mehrere Standard-Blöcke möglich.
   Zweiter Eintrittspunkt (toter AJAX-Duplizierer, kopierte `is_default` mit) entfernt.
4. **Doppelte Initialisierung in 7 Lernblöcken** — Event-Handler wurden zweimal
   gebunden (Inline-Bootstrap + Auto-Init). Guards eingebaut, Bootstraps entfernt.
5. **Unsichtbare Fehlermeldungen in 9 Blöcken** — `return '<html>'` in render.php
   wurde vom Output-Buffering verschluckt; „Bitte konfigurieren…"-Meldungen und
   die iframe-Whitelist-Fehlbox erscheinen jetzt.
6. **`[chemviz_chart]`-Shortcode war tot** (lud Dateien eines gelöschten Blocks,
   ewiger Spinner) → degradiert sauber mit Redakteurs-Hinweis.
7. **Anchor zerstörte Container-ID**, **gefährliche `DELETE LIKE '%Container%'`-Query
   entschärft**, **Nummerierung ignorierte ihr Feature**, **PHP-Errors in render.php
   erzeugten White-Screens** (jetzt Throwable-Catch), **Alphabetisch/Römisch-Nummerierung
   brach nach Z/X ab**.

### Touch-Optimierung

- Container-Action-Buttons auf Touch-Geräten dauerhaft sichtbar, 44-px-Ziele
  (waren vorher hover-gebunden = unerreichbar).
- **Scroll-Falle behoben:** drag-and-drop/drag-the-words starten den Drag erst
  ab 10 px nicht-vertikaler Bewegung — Seite scrollt wieder. Nebeneffekt:
  Tap-to-Place funktioniert auf Touch jetzt erstmals (Click-Event war unterdrückt).

### DSGVO

- **Keine externen CDN-Requests mehr im Frontend:** Font Awesome, Material Icons
  (Google Fonts!), Lucide, KaTeX (inkl. 60 Font-Dateien) und jsPDF sind lokal
  gebündelt. Lucide-Icons funktionieren dadurch erstmals (CSS-Klassen-Mismatch behoben).
- Einzige Ausnahme (dokumentiert): emoji-picker-element im Admin-Icon-Picker.

### Performance

- ~90 `error_log()`-Aufrufe pro Request entfernt/gegated; ~140 `console.log`
  hinter `window.cbdDebug` (zum Debuggen in der Browser-Konsole aktivierbar).
- Icon-Bibliotheken & Nummerierungs-Script laden nur noch, wenn ein Block-Design
  sie nutzt; ChemViz (3,5 MB) im Editor nur bei vorhandenen Blöcken;
  Block-Discovery gecacht; Webapp-Größenberechnung aus dem Editor-Pfad entfernt;
  Seiten-CSS-Cache invalidiert jetzt auch bei Änderungen wiederverwendbarer Blöcke.

### Architektur/Wartbarkeit

- Feature-Parsing zentralisiert (war 4× dupliziert — Ursache von Fehler 1),
  Duplizier-Logik konsolidiert, 3Dmol/Plotly-Enqueue dedupliziert (war 3×),
  toter Code entfernt, TCPDF aus Composer (mpdf reicht; Lock jetzt PHP-7.4-konsistent —
  vorher hätte psr/log 3.x den PDF-Export auf dem Server gebrochen),
  Doku/Versionen überall angeglichen.

### Getroffene Entscheidungen (von Martin)

- Action-Buttons respektieren die Feature-Flags (PDF-Button immer, hat kein Flag)
- CDB-Designer bleibt auf PHP 7.4 (Prüftool bleibt Pflicht vor jedem ZIP)

Details pro Arbeitspaket: VERBESSERUNGSPLAN.md, -2.md, -3.md, -4.md (je mit Status).
