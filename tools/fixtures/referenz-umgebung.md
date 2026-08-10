# Referenzmarkup – Herkunft und Geltungsbereich

_Erhoben am 2026-08-10 für AP-1.2 (siehe `docs/PLAN-Seitenimport.md`)_

## Woher

Aus der **Produktivinstallation** `https://chemiefos.fos-meran.it`, Seite
„sp3-Hybridisierung" (Post-ID 4770), ausgelesen im Blockeditor über
`wp.data.select('core/editor').getEditedPostContent()` mit
`docs/pruefung-blockmarkup.js`.

Das ist bewusst **nicht** der Datenbankinhalt: `getEditedPostContent()`
serialisiert den Blockzustand mit den *heutigen* `save()`-Funktionen. In der
Datenbank könnte Markup einer älteren Plugin-Fassung stehen.

Der Editor meldete für diese Seite **keine ungültigen Blöcke** — das Markup ist
als Vorlage brauchbar.

## Umgebung

| | Produktiv | Lokale Testinstallation |
|---|---|---|
| WordPress | 7.0.3 | 7.0.3 |
| CDB-Plugin | 3.1.85 | 3.1.85 |
| Theme | – | fos-online-schulbuch 1.5.75 |
| PHP | (nicht erhoben) | 8.3.32 |
| Adresse | chemiefos.fos-meran.it | http://fos.localhost:8080 |

Beide Installationen laufen auf **derselben** WordPress- und Plugin-Version.
Damit gilt das hier erhobene Markup für beide (Risiko R1 des Plans entschärft).

## Inhalt dieser Fixture

`referenz-markup.html` enthält einen **Ausschnitt**: den ersten Container-Block
der Seite samt Beginn des ersten Absatzes. Mehr war nicht nötig — der
Container ist der einzige Block mit eigener statischer `save()`-Funktion und
damit der einzige, dessen Markup sich nicht aus dem WordPress-Quelltext
ableiten ließ.

Die Seite enthielt insgesamt: 10 × `container-block-designer/container`,
11 × `core/paragraph`, 3 × `core/list`, 8 × `core/list-item`. **Keine**
Überschriften und **keine** Tabelle — deren Markup ist deshalb abgeleitet
(siehe unten), nicht gemessen.

## Was daraus abgelesen wurde

### Container-Block

- Klassen exakt: `wp-block-container-block-designer-container cbd-container`
  — die generierte Blockklasse wird **nicht** zusätzlich angehängt, obwohl
  `useBlockProps.save()` sie normalerweise setzt. Sie steht schon im
  übergebenen `className` und erscheint nur einmal.
- `data-block` trägt den Design-Slug.
- `data-stable-id` trägt die `stableId`.
- **`data-features` fehlt**, wenn `blockFeatures` leer ist (die `save()`-Funktion
  setzt es nur bei nicht-leerem Objekt).
- Im Attribut-JSON stehen **nur die vom Standard abweichenden** Attribute, in
  dieser Reihenfolge: `selectedBlock`, `blockTitle`, `stableId`.
  `customClasses`, `blockConfig` und `blockFeatures` fehlen, weil sie ihren
  Vorgabewerten entsprechen.

### stableId

Format laut `assets/js/block-editor.js:83`:
`'cbd-' + Date.now() + '-' + Math.random().toString(36).substr(2, 8)`
→ Beispiel `cbd-1784920502523-6k9yderp`.

**Der Serializer muss sie selbst erzeugen.** Fehlt sie, ergänzt der Editor sie
beim Öffnen und markiert den Beitrag als geändert; außerdem greift bei
gleichen IDs die Duplikaterkennung (Zeile 91 ff.) und vergibt neu.

### Zeilenumbrüche — wichtiger Unterschied JS ↔ PHP

Der JavaScript-Serializer setzt nach dem öffnenden und vor dem schließenden
Kommentar je einen Zeilenumbruch:

```
<!-- wp:paragraph -->\n<p>…</p>\n<!-- /wp:paragraph -->
```

`serialize_blocks()` in PHP tut das **nicht** — es verkettet `innerContent`
unverändert. Für die Gültigkeit ist das unerheblich (Leerraum zwischen Tags
gilt als unbedeutend), für ein zeichengleiches Ergebnis nicht. Der Serializer
legt die Umbrüche deshalb selbst in `innerContent` ab.

Beim Container liegen die Innenblöcke **ohne** zusätzlichen Umbruch direkt
hinter dem `<div>`:

```
<!-- wp:container… -->\n<div …><!-- wp:paragraph -->\n<p>…</p>\n<!-- /wp:paragraph --></div>\n<!-- /wp:container… -->
```

### Attribut-Kodierung

`serialize_block_attributes()` (`wp-includes/blocks.php:1645`) nutzt
`JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE` und maskiert danach
`\`, `--`, `<`, `>`, `&` und `\"`. Deshalb steht `sp³` unverändert im JSON.

### Kernblöcke (abgeleitet, nicht gemessen)

Aus `wp-includes/blocks/<name>/block.json` und
`wp-includes/js/dist/block-library.js` der Version 7.0.3. Alle nutzen
`useBlockProps.save()` ohne eigene Klasse; die Klasse entsteht aus dem
generierten Blocknamen und entfällt, wenn `supports.className === false`:

| Block | `className` | Markup |
|---|---|---|
| `core/paragraph` | false | `<p>…</p>` |
| `core/heading` | true | `<h3 class="wp-block-heading">…</h3>` |
| `core/list` | true | `<ul class="wp-block-list">` / `<ol class="wp-block-list">` |
| `core/list-item` | false | `<li>…</li>` |
| `core/table` | true | `<figure class="wp-block-table"><table>…</table></figure>` |

Bestätigt durch die echte Seite: `core/list-item` ist vorhanden, das Format
ist also das migrierte (nicht das veraltete `values`-Attribut).

## Wenn sich etwas ändert

Steigt WordPress oder das CDB-Plugin auf eine neue Version, muss diese Fixture
neu erhoben werden — sonst erzeugt der Serializer stillschweigend ungültige
Blöcke. Vorgehen: `docs/pruefung-blockmarkup.js` erneut auf einer Seite mit
Container-Blöcken ausführen.
