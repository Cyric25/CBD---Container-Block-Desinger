# Inline Script Isolation für HTML-Blöcke

## Problem

Wenn du das Gutenberg "Individuelles HTML" Element mehrmals mit `<script>` Tags verwendest, funktioniert nur das letzte HTML-Element korrekt.

### Beispiel-Szenario

```
Container Block A
  └─ Individuelles HTML: <script>console.log('A');</script>

Container Block B
  └─ Individuelles HTML: <script>console.log('B');</script>
```

**Erwartung**: Beide Scripts werden ausgeführt
**Realität**: Nur Script B wird ausgeführt (oder überschreibt A)

## Ursache

WordPress und Browser behandeln mehrere `<script>` Tags mit identischem Code problematisch:
- **Variable Kollisionen**: Globale Variablen überschreiben sich gegenseitig
- **Ausführungsreihenfolge**: Nur der letzte Script wird korrekt ausgeführt
- **Scope-Probleme**: Kein isolierter Scope pro HTML-Block

## Lösung: Automatische Script-Isolation

Container Block Designer isoliert jetzt automatisch alle inline-Scripts in HTML-Blöcken.

### Vorher (Original)

```html
<script>
var myVariable = 'A';
console.log(myVariable);
</script>
```

### Nachher (Automatisch transformiert)

```html
<script data-cbd-scope="cbd-container-1_script_1">
/* CBD: Isolated script for cbd-container-1 */
(function(containerId) {
    'use strict';
    // Container ID: cbd-container-1
    // Scope ID: cbd-container-1_script_1

    // Helper: Get this container element
    const getContainer = () => document.getElementById('cbd-container-1');
    const container = getContainer();

    // Original script:
    var myVariable = 'A';
    console.log(myVariable);
})("cbd-container-1");
</script>
```

## Features

### ✅ Automatische Isolation

Jedes `<script>` Tag wird automatisch in eine IIFE (Immediately Invoked Function Expression) gewrappt:

```javascript
(function(containerId) {
    'use strict';
    // Dein Code hier - komplett isoliert
})("unique-container-id");
```

### ✅ Container-Zugriff

Jedes isolierte Script erhält automatisch Zugriff auf seinen Container:

```javascript
// In deinem HTML-Block:
<script>
// 'container' ist automatisch verfügbar
console.log('Mein Container:', container);

// Oder dynamisch abrufen:
const myContainer = getContainer();
</script>
```

### ✅ Unique Scope IDs

Jedes Script erhält eine eindeutige ID:
```html
<script data-cbd-scope="cbd-container-1_script_1">
<script data-cbd-scope="cbd-container-1_script_2">
<script data-cbd-scope="cbd-container-2_script_1">
```

### ✅ Externe Scripts nicht betroffen

Scripts mit `src` Attribut werden NICHT modifiziert:
```html
<script src="external.js"></script> <!-- Bleibt unverändert -->
```

## Verwendung

### Einfaches Beispiel

**HTML-Block 1:**
```html
<div id="countdown-1"></div>
<script>
let count = 10;
const interval = setInterval(() => {
    container.querySelector('#countdown-1').textContent = count--;
    if (count < 0) clearInterval(interval);
}, 1000);
</script>
```

**HTML-Block 2:**
```html
<div id="countdown-2"></div>
<script>
let count = 5; // Keine Kollision mit count aus Block 1!
const interval = setInterval(() => {
    container.querySelector('#countdown-2').textContent = count--;
    if (count < 0) clearInterval(interval);
}, 1000);
</script>
```

Beide Countdowns funktionieren unabhängig! ✅

### Interaktives Beispiel

```html
<button onclick="handleClick()">Click me</button>
<div id="output"></div>

<script>
// Funktion ist im isolierten Scope
function handleClick() {
    const output = container.querySelector('#output');
    output.textContent = 'Clicked at ' + new Date().toLocaleTimeString();
}

// Wichtig: onclick muss inline sein oder Event-Listener verwenden
container.querySelector('button').addEventListener('click', handleClick);
</script>
```

### Chart/Visualisierung Beispiel

```html
<canvas id="myChart"></canvas>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Chart.js im isolierten Scope
const ctx = container.querySelector('#myChart').getContext('2d');
const myChart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: ['A', 'B', 'C'],
        datasets: [{
            label: 'Data',
            data: [12, 19, 3]
        }]
    }
});
</script>
```

## Best Practices

### ✅ Verwende `container`

```javascript
// Gut: Suche nur im eigenen Container
const button = container.querySelector('.my-button');
```

```javascript
// Schlecht: Globale Suche kann falsches Element finden
const button = document.querySelector('.my-button'); // ⚠️ Findet vielleicht Button aus anderem Block!
```

### ✅ Eindeutige IDs

```html
<!-- Gut: Unique ID -->
<div id="result-cbd-123"></div>

<!-- Schlecht: Generic ID -->
<div id="result"></div> <!-- ⚠️ Könnte in mehreren Blöcken existieren -->
```

### ✅ Event Listener im Container-Scope

```javascript
// Gut: Event Listener im eigenen Container
container.querySelectorAll('.clickable').forEach(el => {
    el.addEventListener('click', () => {
        console.log('Clicked in container:', containerId);
    });
});
```

### ✅ Cleanup bei Bedarf

```javascript
// Für SPAs oder dynamische Inhalte
window.addEventListener('beforeunload', () => {
    // Cleanup Code hier
    if (typeof myInterval !== 'undefined') {
        clearInterval(myInterval);
    }
});
```

## Debugging

### Console-Ausgaben

Jedes isolierte Script gibt Debug-Informationen aus:

```javascript
/* CBD: Isolated script for cbd-container-1 */
// Container ID: cbd-container-1
// Scope ID: cbd-container-1_script_1
```

### Scope ID im DOM

```html
<script data-cbd-scope="cbd-container-1_script_1">
```

Du kannst Scripts im DevTools filtern:
```javascript
// Finde alle isolierten Scripts
document.querySelectorAll('script[data-cbd-scope]');
```

## Limitierungen

### ⚠️ Globale Variablen

Variablen sind NICHT mehr global:

```javascript
// Block 1:
<script>
var sharedData = 'test'; // Nur in diesem Block verfügbar
</script>

// Block 2:
<script>
console.log(sharedData); // ❌ Fehler: sharedData is not defined
</script>
```

**Lösung**: Verwende `window` für echte globale Variablen:
```javascript
window.sharedData = 'test'; // ✅ Global verfügbar
```

### ⚠️ Inline Event-Handler

Inline `onclick` Attribute funktionieren nur wenn die Funktion global ist:

```html
<!-- Funktioniert NICHT: -->
<script>
function handleClick() { console.log('clicked'); }
</script>
<button onclick="handleClick()">Click</button>

<!-- Lösung 1: Event Listener -->
<button id="myButton">Click</button>
<script>
container.querySelector('#myButton').addEventListener('click', function() {
    console.log('clicked');
});
</script>

<!-- Lösung 2: Globale Funktion -->
<script>
window['handleClick_' + containerId] = function() {
    console.log('clicked in', containerId);
};
</script>
<button onclick="window['handleClick_cbd-container-1']()">Click</button>
```

## Technische Details

### Transformation-Algorithmus

1. **Erkennung**: Regex findet alle `<script>` Tags ohne `src` Attribut
2. **Counter**: Statischer Counter für unique IDs
3. **IIFE Wrapping**: Code wird in `(function(containerId) { ... })()` gewrappt
4. **Helpers**: `getContainer()` und `container` werden injiziert
5. **Scope ID**: `data-cbd-scope` Attribut wird hinzugefügt

### Performance

- ✅ **Minimal Overhead**: Nur ein Regex-Pass pro Block
- ✅ **Lazy Processing**: Nur wenn `<script>` Tags vorhanden
- ✅ **Cache-Friendly**: Transformation passiert nur beim Rendering
- ✅ **No Runtime Cost**: IIFE ist JavaScript-Standard mit null Overhead

### Kompatibilität

- ✅ WordPress 5.0+ (Gutenberg)
- ✅ PHP 7.0+
- ✅ Alle modernen Browser
- ✅ Funktioniert mit jQuery, React, Vue, etc.

## Testing

### Test-Szenario 1: Zwei identische Scripts

```
Container Block A
  └─ HTML: <script>console.log('Test');</script>

Container Block B
  └─ HTML: <script>console.log('Test');</script>
```

**Erwartung**: Console zeigt "Test" zweimal
**Ergebnis**: ✅ Beide Scripts werden ausgeführt

### Test-Szenario 2: Variablen-Kollision

```
Container Block A
  └─ HTML: <script>var x = 1; console.log(x);</script>

Container Block B
  └─ HTML: <script>var x = 2; console.log(x);</script>
```

**Erwartung**: Console zeigt "1" dann "2"
**Ergebnis**: ✅ Keine Kollision, beide Werte korrekt

### Test-Szenario 3: DOM-Manipulation

```
Container Block A
  └─ HTML: <div class="target">A</div><script>container.querySelector('.target').textContent = 'Modified A';</script>

Container Block B
  └─ HTML: <div class="target">B</div><script>container.querySelector('.target').textContent = 'Modified B';</script>
```

**Erwartung**: A zeigt "Modified A", B zeigt "Modified B"
**Ergebnis**: ✅ Jeder Block manipuliert nur seinen eigenen DOM

## Changelog

### Version 2.8.1 (2025-01-XX)
- ✅ Automatische Script-Isolation für HTML-Blöcke
- ✅ IIFE Wrapping mit unique Container IDs
- ✅ Helper-Funktionen: `getContainer()` und `container`
- ✅ Scope ID Attribution für Debugging
- ✅ Externe Scripts werden nicht modifiziert

---

**Container Block Designer**
Version 2.8.1+
© 2025