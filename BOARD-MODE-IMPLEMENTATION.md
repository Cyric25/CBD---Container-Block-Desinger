# Tafel-Modus (Board Mode) - Implementierungsanweisungen

## Feature-Beschreibung

Der **Tafel-Modus** ist ein Fullscreen-Overlay-Anzeigemodus fuer Container-Bloecke. Er teilt den Bildschirm in zwei Haelften:

- **Linke Seite:** Block-Inhalt (read-only, scrollbar)
- **Rechte Seite:** Zeichenflaeche (HTML5 Canvas) mit Werkzeugen

### Werkzeuge
- Freihand-Stift (waehlbare Farbe + Dicke)
- Radierer
- Alles-loeschen Button

### Persistenz
Zeichnungen werden im **localStorage** des Browsers gespeichert (`cbd-board-{containerId}`) und beim naechsten Oeffnen wiederhergestellt.

### UI-Elemente
- **Aktivierung:** Button mit `dashicons-welcome-write-blog` Icon in der Action-Buttons-Leiste des Blocks
- **Beenden:** X-Button oben rechts im Overlay-Header

---

## Betroffene Dateien

### Neue Dateien

| Datei | Zweck |
|-------|-------|
| `assets/css/board-mode.css` | Alle Tafel-Modus CSS-Styles (Overlay, Split-Layout, Toolbar, Canvas) |
| `assets/js/board-mode.js` | Canvas-Zeichenlogik, Overlay-DOM-Erstellung, Toolbar-Steuerung, localStorage |

### Geaenderte Dateien

| Datei | Aenderung | Stelle |
|-------|----------|--------|
| `includes/class-cbd-block-registration.php` | Board-Mode Button in Action-Buttons rendern | nach Zeile ~882 (nach PDF-Button) |
| `includes/class-cbd-block-registration.php` | Context um boardMode-State erweitern | Zeile ~726 (local_context) |
| `includes/class-cbd-block-registration.php` | CSS-Klasse `cbd-has-board-mode` hinzufuegen | Zeile ~756 (wrapper_classes) |
| `admin/new-block.php` | Feature-Daten sammeln | Zeile ~133 (nach screenshot) |
| `admin/new-block.php` | Feature-Toggle HTML im Formular | Zeile ~952 (nach Screenshot-Feature) |
| `admin/edit-block.php` | Feature-Daten sammeln (identisch zu new-block) | gleiche Stellen |
| `admin/edit-block.php` | Feature-Toggle HTML (identisch zu new-block) | gleiche Stellen |
| `assets/js/interactivity-store.js` | Action `toggleBoardMode` hinzufuegen | nach Zeile ~399 (nach createPDF) |
| `assets/js/interactivity-fallback.js` | jQuery-Fallback Handler hinzufuegen | nach letztem Action-Handler |
| `container-block-designer.php` | Board-Mode CSS/JS enqueuen | Frontend-Enqueue-Methode |

---

## Detaillierte Implementierung

### 1. Feature-Daten im Admin (new-block.php + edit-block.php)

#### 1a) Datensammlung (PHP, nach Screenshot-Feature ~Zeile 133)

```php
'boardMode' => array(
    'enabled' => isset($_POST['features']['boardMode']['enabled']) ? true : false,
    'boardColor' => sanitize_hex_color($_POST['features']['boardMode']['boardColor'] ?? '#1a472a'),
)
```

#### 1b) HTML Feature-Toggle (nach Screenshot-Feature ~Zeile 952)

```php
<div class="cbd-feature-item">
    <label>
        <input type="checkbox" name="features[boardMode][enabled]" value="1"
               <?php checked($block['features']['boardMode']['enabled'] ?? false); ?>
               class="cbd-feature-toggle">
        <strong><?php _e('Tafel-Modus', 'container-block-designer'); ?></strong>
    </label>
    <div class="cbd-feature-options"
         <?php echo !($block['features']['boardMode']['enabled'] ?? false) ? 'style="display:none;"' : ''; ?>>
        <label for="board_color"><?php _e('Tafel-Hintergrundfarbe:', 'container-block-designer'); ?></label>
        <input type="color" id="board_color" name="features[boardMode][boardColor]"
               value="<?php echo esc_attr($block['features']['boardMode']['boardColor'] ?? '#1a472a'); ?>">
        <p class="description"><?php _e('Hintergrundfarbe der Zeichenflaeche (Standard: Dunkelgruen)', 'container-block-designer'); ?></p>
    </div>
</div>
```

---

### 2. Block-Rendering (class-cbd-block-registration.php)

#### 2a) Context erweitern (~Zeile 726)

```php
// Nach den bestehenden features im local_context:
$local_context['boardModeActive'] = false;
$local_context['features']['boardMode'] = !empty($features['boardMode']['enabled']);
```

#### 2b) CSS-Klasse (~Zeile 756)

```php
if (!empty($features['boardMode']['enabled'])) {
    $wrapper_classes[] = 'cbd-has-board-mode';
}
```

#### 2c) Button rendern (~Zeile 882, nach PDF-Button, vor </div> der action-buttons)

```php
// Button 5: Board Mode (Tafel-Modus) - NUR wenn Feature aktiviert
if (!empty($features['boardMode']['enabled'])) {
    $board_color = esc_attr($features['boardMode']['boardColor'] ?? '#1a472a');
    $html .= '<button type="button" ';
    $html .= 'class="cbd-board-mode-toggle" ';
    $html .= 'data-wp-on--click="actions.toggleBoardMode" ';
    $html .= 'data-board-color="' . $board_color . '" ';
    $html .= 'style="display: flex !important; visibility: visible !important; opacity: 1 !important;" ';
    $html .= 'title="' . esc_attr__('Tafel-Modus', 'container-block-designer') . '">';
    $html .= '<span class="dashicons dashicons-welcome-write-blog"></span>';
    $html .= '</button>';
}
```

**Wichtig:** Dieser Button wird NUR gerendert wenn `boardMode.enabled` aktiv ist (anders als die anderen 4 Buttons die immer sichtbar sind).

---

### 3. CSS (assets/css/board-mode.css)

#### Layout-Struktur

```
+--------------------------------------------------+
| [Icon] Tafel-Modus                          [X]  |  <- Header (50px)
+------------------------+-------------------------+
|                        |  [Stift][Radierer]       |  <- Toolbar
|   Block-Inhalt         |  [Farbe][Dicke][Loeschen]|
|   (scrollbar)          +-------------------------+
|                        |                         |
|                        |   Canvas                |
|                        |   (Zeichenflaeche)      |
|                        |                         |
+------------------------+-------------------------+
```

#### Wichtige CSS-Klassen

| Klasse | Zweck | Wichtige Properties |
|--------|-------|---------------------|
| `.cbd-board-overlay` | Fullscreen-Container | `position: fixed; inset: 0; z-index: 999999;` |
| `.cbd-board-header` | Titelleiste oben | `height: 50px; background: #333; color: #fff;` |
| `.cbd-board-close` | X-Button oben rechts | Absolut positioniert, gross klickbar |
| `.cbd-board-split` | Grid-Container | `grid-template-columns: 1fr 1fr;` |
| `.cbd-board-content` | Linke Haelfte (Inhalt) | `overflow-y: auto; padding: 20px;` |
| `.cbd-board-canvas-area` | Rechte Haelfte | `display: flex; flex-direction: column;` |
| `.cbd-board-toolbar` | Werkzeugleiste | `display: flex; gap: 8px; padding: 10px;` |
| `.cbd-board-tool` | Einzelner Werkzeug-Button | Toggle-Styling mit `.active` Klasse |
| `.cbd-board-tool.active` | Aktives Werkzeug | Hervorgehobener Hintergrund |
| `.cbd-board-canvas` | Das Canvas-Element | `flex: 1; cursor: crosshair;` |
| `.cbd-board-separator` | Trennlinie in Toolbar | `width: 1px; background: #ccc;` |

#### Responsive (max-width: 768px)

- Split aendert zu vertikal: `grid-template-columns: 1fr; grid-template-rows: 40% 60%;`
- Content oben, Canvas unten

---

### 4. JavaScript Canvas-Logik (assets/js/board-mode.js)

#### Globales Objekt: `window.CBDBoardMode`

```javascript
window.CBDBoardMode = {
    // Zustand
    overlay: null,          // DOM-Referenz zum Overlay
    canvas: null,           // Canvas-Element
    ctx: null,              // 2D Context
    isDrawing: false,       // Zeichnet gerade?
    currentTool: 'pen',     // 'pen' | 'eraser'
    currentColor: '#ffffff', // Stiftfarbe
    lineWidth: 3,           // Stiftdicke
    containerId: null,      // ID des aktuellen Blocks

    // Methoden
    open(containerId, contentHtml, boardColor),
    close(),
    initCanvas(canvas, boardColor),
    setTool(tool),
    setColor(color),
    setLineWidth(width),
    clearCanvas(),
    saveToCache(containerId),
    loadFromCache(containerId),
    onPointerDown(e),
    onPointerMove(e),
    onPointerUp(e),
    createOverlayDOM(contentHtml, boardColor),
    bindEvents(),
    destroy()
};
```

#### open() Ablauf:

1. Overlay-DOM erstellen (`createOverlayDOM()`)
2. An `document.body` anhaengen
3. Canvas initialisieren (Groesse, Context)
4. Gespeicherte Zeichnung laden (`loadFromCache()`)
5. Events binden (`bindEvents()`)
6. `document.body.style.overflow = 'hidden'` (Scroll sperren)

#### close() Ablauf:

1. Zeichnung speichern (`saveToCache()`)
2. Events entfernen
3. Overlay-DOM entfernen
4. `document.body.style.overflow = ''` (Scroll freigeben)

#### Canvas-Zeichentechnik:

- **Pointer Events** (`pointerdown`, `pointermove`, `pointerup`) fuer Maus + Touch + Stift
- `ctx.lineCap = 'round'` und `ctx.lineJoin = 'round'` fuer glatte Striche
- **Stift:** `ctx.globalCompositeOperation = 'source-over'`, Farbe aus Farbwaehler
- **Radierer:** `ctx.globalCompositeOperation = 'destination-out'`
- Canvas-Groesse = `canvas.parentElement` Groesse (responsive via `ResizeObserver`)

#### localStorage-Schema:

```
Key:   "cbd-board-{containerId}"
Value: canvas.toDataURL('image/png')
```

- **Speichern:** Automatisch beim `close()`
- **Laden:** Automatisch beim `open()` via `drawImage()`
- **Loeschen:** "Alles loeschen" Button entfernt auch den localStorage-Eintrag

---

### 5. Interactivity Store (interactivity-store.js)

Neue Action nach `*createPDF()` (~Zeile 399):

```javascript
*toggleBoardMode() {
    const context = getContext();
    const element = getElement();

    const mainContainer = element.ref.closest('[data-wp-interactive="container-block-designer"]');
    if (!mainContainer) return;

    const containerBlock = mainContainer.querySelector('.cbd-container-block');
    if (!containerBlock) return;

    // Board-Farbe aus Button-Attribut lesen
    const boardButton = mainContainer.querySelector('.cbd-board-mode-toggle');
    const boardColor = boardButton?.getAttribute('data-board-color') || '#1a472a';

    // Board Mode oeffnen
    if (window.CBDBoardMode) {
        window.CBDBoardMode.open(
            context.containerId,
            containerBlock.innerHTML,
            boardColor
        );
    }
}
```

---

### 6. jQuery Fallback (interactivity-fallback.js)

Neuer Handler nach dem letzten bestehenden Action-Handler:

```javascript
/**
 * Board Mode (Tafel-Modus) Action
 */
$(document).on('click', '[data-wp-on--click="actions.toggleBoardMode"]', function(e) {
    e.preventDefault();
    e.stopPropagation();

    // Skip wenn Interactivity API aktiv
    if (interactivityAPIActive || checkInteractivityAPI()) return;

    const $button = $(this);
    const $container = $button.closest('[data-wp-interactive="container-block-designer"]');
    const context = $container.data('cbd-context') || {};
    const $containerBlock = $container.children('.cbd-container-block');

    const boardColor = $button.attr('data-board-color') || '#1a472a';

    if (window.CBDBoardMode) {
        window.CBDBoardMode.open(
            context.containerId,
            $containerBlock.html(),
            boardColor
        );
    }
});
```

---

### 7. Asset-Loading (container-block-designer.php)

Board-Mode Assets nur laden wenn mindestens ein Block das Feature hat:

```php
// In der Frontend-Enqueue Methode pruefen:
private function has_board_mode_blocks() {
    global $wpdb;
    $table = $wpdb->prefix . 'cbd_blocks';
    $result = $wpdb->get_var(
        "SELECT COUNT(*) FROM $table WHERE status = 'active' AND features LIKE '%boardMode%'"
    );
    return $result > 0;
}

// Enqueue:
if ($this->has_board_mode_blocks()) {
    wp_enqueue_style('cbd-board-mode', CBD_PLUGIN_URL . 'assets/css/board-mode.css', array('dashicons'), CBD_VERSION);
    wp_enqueue_script('cbd-board-mode', CBD_PLUGIN_URL . 'assets/js/board-mode.js', array(), CBD_VERSION, true);
}
```

---

## Overlay HTML-Struktur (von board-mode.js dynamisch erstellt)

```html
<div class="cbd-board-overlay" id="cbd-board-overlay">
    <div class="cbd-board-header">
        <span class="cbd-board-title">
            <span class="dashicons dashicons-welcome-write-blog"></span>
            Tafel-Modus
        </span>
        <button class="cbd-board-close" title="Tafel-Modus beenden">&times;</button>
    </div>

    <div class="cbd-board-split">
        <div class="cbd-board-content">
            <!-- Block-Inhalt (innerHTML kopiert) -->
        </div>

        <div class="cbd-board-canvas-area">
            <div class="cbd-board-toolbar">
                <button class="cbd-board-tool active" data-tool="pen" title="Stift">
                    <span class="dashicons dashicons-edit"></span>
                </button>
                <button class="cbd-board-tool" data-tool="eraser" title="Radierer">
                    <span class="dashicons dashicons-editor-removeformatting"></span>
                </button>
                <span class="cbd-board-separator"></span>
                <input type="color" class="cbd-board-color" value="#ffffff" title="Stiftfarbe">
                <input type="range" class="cbd-board-width" min="1" max="20" value="3" title="Stiftdicke">
                <span class="cbd-board-width-display">3px</span>
                <span class="cbd-board-separator"></span>
                <button class="cbd-board-clear" title="Alles loeschen">
                    <span class="dashicons dashicons-trash"></span>
                </button>
            </div>
            <canvas class="cbd-board-canvas"></canvas>
        </div>
    </div>
</div>
```

---

## Implementierungsreihenfolge

1. `assets/css/board-mode.css` erstellen
2. `assets/js/board-mode.js` erstellen
3. `includes/class-cbd-block-registration.php` aendern (Button + Context)
4. `assets/js/interactivity-store.js` erweitern
5. `assets/js/interactivity-fallback.js` erweitern
6. `admin/new-block.php` aendern (Feature-Toggle + Datensammlung)
7. `admin/edit-block.php` aendern (gleiche Aenderungen)
8. `container-block-designer.php` aendern (Asset-Loading)

---

## Testen

1. **Admin:** Block bearbeiten -> "Tafel-Modus" aktivieren -> Speichern
2. **Frontend:** Seite oeffnen -> Tafel-Button klicken -> Overlay pruefen
3. **Zeichnen:** Stift, Farbe, Dicke, Radierer testen
4. **Persistenz:** Overlay schliessen und wieder oeffnen -> Zeichnung da?
5. **Responsive:** Auf mobilem Viewport testen
6. **Nesting:** Verschachtelter Container -> nur dieser Block im Overlay
7. **ESC-Taste:** Overlay per Escape-Taste schliessen
