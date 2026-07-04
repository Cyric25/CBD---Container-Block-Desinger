# LaTeX Parser - Global Implementation

## Überblick

Der LaTeX-Parser wurde **von Container-Blöcken getrennt** und ist jetzt **global für alle WordPress-Blöcke** verfügbar.

## Änderungen (v2.7.7)

### 1. LaTeX-Parser ist jetzt Block-Unabhängig

**Vorher:**
- LaTeX-Formeln wurden nur innerhalb von Container-Blöcken geparst
- Parsing erfolgte manuell in `CBD_Block_Registration::render_block()`
- Funktionierte nicht in Standard-WordPress-Blöcken (Paragraph, Heading, etc.)

**Nachher:**
- LaTeX-Formeln funktionieren in **ALLEN WordPress-Blöcken**:
  - ✅ Paragraph (Absatz)
  - ✅ Heading (Überschriften)
  - ✅ Custom HTML (Individuelles HTML)
  - ✅ Container-Blöcke
  - ✅ Alle anderen Gutenberg-Blöcke

### 2. Implementierung via `render_block` Filter-Hook

**WordPress Filter:** `render_block`

Der `render_block` Filter wird von WordPress automatisch für **jeden Block** aufgerufen, bevor er gerendert wird.

**Code-Änderungen:**

#### `includes/class-latex-parser.php` (Zeile 46-56)

```php
private function __construct() {
    add_action('wp_enqueue_scripts', array($this, 'enqueue_katex'));
    add_action('admin_enqueue_scripts', array($this, 'enqueue_katex'));

    // GLOBAL BLOCK FILTER: Parse LaTeX in ALL WordPress blocks
    // This filter runs for every block before rendering (Gutenberg blocks)
    add_filter('render_block', array($this, 'parse_latex_in_blocks'), 10, 2);

    // Legacy filter for classic editor content and non-block content
    add_filter('the_content', array($this, 'parse_latex'), 999);
}
```

#### Neue Methode: `parse_latex_in_blocks()` (Zeile 117-125)

```php
public function parse_latex_in_blocks($block_content, $block) {
    // Skip empty blocks
    if (empty($block_content)) {
        return $block_content;
    }

    // Parse LaTeX in this block's content
    return $this->parse_latex($block_content);
}
```

### 3. Entfernung der Container-Block spezifischen Parsing-Logik

**Datei:** `includes/class-cbd-block-registration.php` (Zeile 846-849)

**Vorher:**
```php
// Actual content - Apply LaTeX parsing
$parsed_content = $content;

// Apply LaTeX parser if available
if (class_exists('CBD_LaTeX_Parser')) {
    $latex_parser = CBD_LaTeX_Parser::get_instance();
    $parsed_content = $latex_parser->parse_latex($content);
}
```

**Nachher:**
```php
// Actual content
// NOTE: LaTeX parsing is now handled globally via render_block filter in CBD_LaTeX_Parser
// No need to parse LaTeX here anymore - it's automatic for all blocks!
$parsed_content = $content;
```

## Unterstützte Syntax

Der Parser unterstützt folgende LaTeX-Syntaxen **in allen Blöcken**:

### 1. Display Math (Block-Level, zentriert)

```
$$E = mc^2$$
```

oder

```
[latex]E = mc^2[/latex]
```

### 2. Inline Math (Innerhalb von Text)

```
Die Formel $E = mc^2$ zeigt die Äquivalenz von Energie und Masse.
```

## Verwendung

### In Paragraph-Blöcken

```
Die Quadratische Formel ist $x = \frac{-b \pm \sqrt{b^2-4ac}}{2a}$ sehr nützlich.

Die Normalverteilung wird beschrieben durch:

$$f(x) = \frac{1}{\sigma\sqrt{2\pi}} e^{-\frac{1}{2}(\frac{x-\mu}{\sigma})^2}$$
```

### In Heading-Blöcken

```
## Das Einstein'sche $E = mc^2$
```

### In Custom HTML-Blöcken

```html
<div class="formel-container">
  <p>Die berühmte Formel $$E = mc^2$$ revolutionierte die Physik.</p>
</div>
```

### In Container-Blöcken

```
Funktioniert wie vorher - automatisch!

$$\int_0^\infty e^{-x^2} dx = \frac{\sqrt{\pi}}{2}$$
```

## Technische Details

### Filter-Reihenfolge

1. **`render_block`** (Priorität 10): WordPress ruft diesen Filter für jeden Block auf
2. **`parse_latex_in_blocks()`**: Unser LaTeX-Parser verarbeitet den Block-Content
3. **`parse_latex()`**: Eigentliche Parsing-Logik
4. Block wird mit geparsten Formeln gerendert

### Doppel-Parsing-Vermeidung

Der Parser verhindert doppeltes Parsing durch eine Prüfung:

```php
// Check if content was already parsed (prevent double parsing)
if (strpos($content, 'cbd-latex-formula') !== false) {
    return $content;
}
```

### KaTeX-Integration

**JavaScript:** `assets/js/latex-renderer.js`
**CSS:** `assets/css/latex-formulas.css`

Die Formeln werden als HTML-Struktur gerendert:

```html
<div class="cbd-latex-formula cbd-latex-display"
     id="cbd-latex-..."
     data-latex="E = mc^2"
     data-formula-id="...">
    <span class="cbd-latex-content"></span>
</div>
```

Das JavaScript rendert dann die Formel mit KaTeX in `.cbd-latex-content`.

## Vorteile

✅ **Universell:** LaTeX funktioniert in allen WordPress-Blöcken
✅ **Automatisch:** Keine manuelle Konfiguration notwendig
✅ **Performant:** Filter wird nur für Blöcke mit Formeln aktiv
✅ **Kompatibel:** Funktioniert mit allen Gutenberg-Blöcken
✅ **Wartbar:** Zentrale Parsing-Logik, keine Duplikation

## Testing

### Test-Szenarien

1. **Paragraph-Block:** LaTeX-Formel in normalem Absatz
2. **Heading-Block:** Formel in Überschrift
3. **Custom HTML:** Formel in HTML-Code
4. **Container-Block:** Formel innerhalb Container-Block
5. **Verschachtelt:** Container-Block mit Paragraph-Block mit Formel
6. **Mixed:** Inline- und Display-Math im gleichen Block

### Erwartetes Verhalten

Alle LaTeX-Formeln werden korrekt mit KaTeX gerendert, unabhängig vom Block-Typ.

## Troubleshooting

### Formeln werden nicht gerendert

1. **Browser-Konsole prüfen:** F12 → Console → KaTeX-Fehler?
2. **KaTeX geladen?** Netzwerk-Tab prüfen ob `katex.min.js` geladen wurde
3. **Syntax korrekt?** LaTeX-Syntax validieren

### Doppelte Formeln

Falls Formeln doppelt erscheinen:
- Cache leeren: `wp cache flush`
- Browser-Cache löschen
- Prüfen ob `the_content` Filter noch aktiv ist

## Migration von alter Version

Keine Migration notwendig! Bestehende Formeln in Container-Blöcken funktionieren weiterhin automatisch.

**Neu:** Jetzt können auch Formeln in Standard-Blöcken verwendet werden!

## Weitere Informationen

- **WordPress Filter API:** https://developer.wordpress.org/reference/hooks/render_block/
- **KaTeX Documentation:** https://katex.org/docs/supported.html
- **LaTeX Math Symbols:** https://katex.org/docs/support_table.html
