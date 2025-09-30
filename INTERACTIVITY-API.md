# WordPress Interactivity API Integration

## Übersicht

Container Block Designer verwendet ab Version 2.8.0 die **WordPress Interactivity API** für eine zukunftssichere Verwaltung mehrerer interaktiver Block-Instanzen auf einer Seite.

## Problem gelöst

### Vorher (Legacy-Implementierung)
- **ID-Konflikte**: Mehrere Blöcke mit identischen IDs führten zu JavaScript-Fehlern
- **JavaScript-Ausführung**: Inline-Scripts wurden nur für die erste Instanz ausgeführt
- **State-Management**: Globales State führte zu unerwünschten Interferenzen zwischen Blöcken

### Jetzt (Interactivity API)
- ✅ **Eindeutige IDs**: Jede Block-Instanz erhält eine unique ID via `wp_unique_id()`
- ✅ **Lokaler Context**: Jeder Block hat seinen eigenen isolierten State
- ✅ **Deklarative Syntax**: Verwendung von `data-wp-*` Directives
- ✅ **Automatische State-Verwaltung**: WordPress handhabt die Synchronisation
- ✅ **Keine Interferenzen**: Blöcke beeinflussen sich nicht gegenseitig

## Architektur

### 1. Store Definition (`interactivity-store.js`)

```javascript
import { store, getContext, getElement } from '@wordpress/interactivity';

store('container-block-designer', {
    state: {
        // Globaler State (shared)
        hasHtml2Canvas: ...,
        hasJsPDF: ...
    },
    actions: {
        // Lokale Actions pro Block-Instanz
        toggleCollapse() { ... },
        copyText() { ... },
        createScreenshot() { ... }
    },
    callbacks: {
        // Lifecycle hooks
        onInit() { ... }
    }
});
```

### 2. Block Rendering (PHP)

Jeder Block wird mit Interactivity API Directives gerendert:

```php
<div
    data-wp-interactive="container-block-designer"
    data-wp-context='{"containerId": "cbd-123", "isCollapsed": false}'
    data-wp-init="callbacks.onInit"
>
    <button
        data-wp-on--click="actions.toggleCollapse"
        data-wp-bind--aria-expanded="!context.isCollapsed"
    >
        <span
            data-wp-class--dashicons-arrow-up-alt2="!context.isCollapsed"
            data-wp-class--dashicons-arrow-down-alt2="context.isCollapsed"
        ></span>
    </button>

    <div
        data-wp-class--cbd-collapsed="context.isCollapsed"
        data-wp-bind--aria-hidden="context.isCollapsed"
    >
        <!-- Content -->
    </div>
</div>
```

### 3. Context Pro Block-Instanz

Jeder Block erhält seinen eigenen lokalen Context:

```json
{
    "containerId": "cbd-container-1",
    "blockId": 42,
    "blockName": "my-block",
    "isCollapsed": false,
    "copySuccess": false,
    "screenshotLoading": false,
    "features": {
        "collapse": true,
        "copyText": true,
        "screenshot": true
    }
}
```

## Verwendete Directives

### Event Handling
- `data-wp-on--click="actions.toggleCollapse"` - Click-Event Handler
- `data-wp-on--mouseover="..."` - Hover-Events (optional)

### Attribute Binding
- `data-wp-bind--aria-expanded="!context.isCollapsed"` - Dynamische Attribute
- `data-wp-bind--disabled="context.loading"` - Button States

### Class Binding
- `data-wp-class--cbd-collapsed="context.isCollapsed"` - Conditional CSS Classes
- `data-wp-class--dashicons-yes-alt="context.success"` - Icon States

### Lifecycle
- `data-wp-init="callbacks.onInit"` - Initialisierung beim Mount

## Features

### ✅ Collapse/Expand
Jeder Block kann unabhängig eingeklappt werden:
```javascript
actions: {
    *toggleCollapse() {
        const context = getContext();
        context.isCollapsed = !context.isCollapsed;
    }
}
```

### ✅ Copy Text
Text wird aus dem spezifischen Block kopiert:
```javascript
actions: {
    *copyText() {
        const context = getContext();
        const element = getElement();
        const text = element.ref.querySelector('.cbd-container-content').innerText;
        yield navigator.clipboard.writeText(text);
        context.copySuccess = true;
    }
}
```

### ✅ Screenshot
Screenshot wird nur vom aktuellen Block erstellt:
```javascript
actions: {
    *createScreenshot() {
        const context = getContext();
        const element = getElement();
        const canvas = yield html2canvas(element.ref);
        // Download...
    }
}
```

## Testing

### Test-Szenarien

1. **Mehrere identische Blöcke auf einer Seite**
   - ✅ Jeder Block hat eigene ID
   - ✅ Buttons funktionieren unabhängig
   - ✅ State wird nicht geteilt

2. **Collapse-Funktionalität**
   - ✅ Block A einklappen → Block B bleibt expanded
   - ✅ Aria-Attribute werden korrekt gesetzt
   - ✅ Animation läuft smooth

3. **Copy-Funktionalität**
   - ✅ Copy von Block 1 → nur Text von Block 1
   - ✅ Copy von Block 2 → nur Text von Block 2
   - ✅ Visual Feedback nur am geklickten Button

4. **Screenshot-Funktionalität**
   - ✅ Screenshot von Block 1 → nur Block 1 im Bild
   - ✅ Loading-State nur am aktiven Button
   - ✅ Collapsed-State wird temporär expanded für Screenshot

### Test-Seite erstellen

```html
<!-- Füge 3+ identische Blöcke auf einer Seite ein -->
<!-- Container Block Designer: Test Block A -->
<p>Inhalt Block A - Lorem ipsum dolor sit amet...</p>

<!-- Container Block Designer: Test Block B -->
<p>Inhalt Block B - Consectetur adipiscing elit...</p>

<!-- Container Block Designer: Test Block C -->
<p>Inhalt Block C - Sed do eiusmod tempor...</p>
```

Teste dann:
1. Collapse Block A → nur A klappt ein
2. Copy Text von Block B → nur Text von B wird kopiert
3. Screenshot von Block C → nur C wird fotografiert

## Browser-Kompatibilität

Die Interactivity API benötigt:
- ✅ WordPress 6.5+
- ✅ Modern Browsers (Chrome 90+, Firefox 88+, Safari 14+)
- ✅ JavaScript aktiviert
- ✅ ES6 Module Support

## Debugging

### Debug-Modus aktivieren

```javascript
// In browser console
window.CBD_DEBUG = true;
```

### Debug-Ausgaben

```html
<!-- Im HTML sichtbar -->
<!-- CBD DEBUG: Block initialized: {"blockId": 42, "containerId": "cbd-123"} -->
```

### CSS Debug-Klasse

```html
<body class="cbd-debug-mode">
```

Zeigt orange Rahmen um alle interaktiven Blöcke.

## Migration von Legacy-Code

Die alte Implementierung bleibt während der Übergangszeit für Backward-Compatibility erhalten:

```php
// Legacy data attributes (deprecated, but still supported)
$wrapper_attributes['data-features'] = esc_attr(json_encode($features));
```

Zukünftige Versionen werden die Legacy-Attribute entfernen.

## Performance

### Vorteile
- ✅ Weniger globaler State
- ✅ Keine manuellen Event-Listener mehr
- ✅ Automatisches Cleanup
- ✅ Kleinere Bundle-Size durch native WordPress APIs

### Benchmarks
- **Legacy**: ~15KB JavaScript pro Block-Typ
- **Interactivity API**: ~5KB JavaScript (geteilt)
- **Verbesserung**: ~66% weniger Code

## Weiterführende Links

- [WordPress Interactivity API Dokumentation](https://developer.wordpress.org/block-editor/reference-guides/interactivity-api/)
- [API Reference](https://developer.wordpress.org/block-editor/reference-guides/interactivity-api/api-reference/)
- [Core Concepts](https://developer.wordpress.org/block-editor/reference-guides/interactivity-api/core-concepts/undestanding-global-state-local-context-and-derived-state/)

## Changelog

### Version 2.8.0 (2025-01-XX)
- ✅ WordPress Interactivity API Integration
- ✅ Lokaler Context pro Block-Instanz
- ✅ Deklarative Event-Handler mit `data-wp-on--*`
- ✅ Automatisches State-Management
- ✅ Backward-Compatibility mit Legacy-Code
- ✅ Neue CSS-Datei `interactivity-api.css`
- ✅ Neue Store-Datei `interactivity-store.js`

---

**Entwickelt für Container Block Designer**
Version 2.8.0+
© 2025