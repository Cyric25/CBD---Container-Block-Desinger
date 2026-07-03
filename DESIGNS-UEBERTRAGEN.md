# Container-Designs zwischen Installationen übertragen

Die Import/Export-Funktion im Admin wurde ab v3.1.50 ausgeblendet (sie verarbeitete
Uploads serverseitig nie und war eine unnötige Angriffsfläche: Datei-Upload inkl.
ZIP, eingefügtes JSON und URL-Fetch/SSRF). Der Austausch von Container-Designs
erfolgt stattdessen sicher und verlustfrei über die **Datenbank**.

## Was übertragen wird (und was nicht)

- **Übertragen:** die Container-**Designs** (Vorlagen wie „Info-Box", „Merksatz",
  „Aufgabe") — sie liegen in der DB-Tabelle **`{prefix}cbd_blocks`** (Standard:
  `wp_cbd_blocks`) mit den Spalten
  `id, name, title, description, config, styles, features, status, created_at, updated_at`.
- **NICHT übertragen:** Seiteninhalte, platzierte Blöcke, Zeichnungen, Klassen.
  Seiten überträgst du separat über den normalen WordPress-Export oder eine
  komplette DB-Migration.

## Wichtig: der Slug entscheidet

Jeder Container-Block auf einer Seite referenziert sein Design über den **Slug**
(im gespeicherten Markup als `data-block="mein-slug"`). Damit bestehende Seiten
auf der Zielseite korrekt gestylt rendern, müssen die übertragenen Designs
**denselben `slug`** haben wie beim Anlegen der Seiten. Bei abweichenden Slugs
bleiben die Container ohne Style.

---

## Weg A — WP-CLI (empfohlen, wenn Kommandozeilenzugriff besteht)

Auf der **Quell-Seite** nur die Design-Tabelle exportieren:

```bash
wp db export cbd-designs.sql --tables=wp_cbd_blocks
```

Datei auf die **Ziel-Seite** kopieren und dort importieren:

```bash
wp db import cbd-designs.sql
```

Danach den Block-Cache leeren (siehe unten).

> Ersetze `wp_` durch das tatsächliche Tabellen-Präfix beider Seiten.

---

## Weg B — phpMyAdmin (ohne Kommandozeile)

1. **Quelle:** phpMyAdmin → Datenbank wählen → Tabelle `wp_cbd_blocks` →
   Reiter **Exportieren** → Format **SQL** → OK. Es entsteht eine `.sql`-Datei.
2. **Ziel:** phpMyAdmin → Ziel-Datenbank wählen → Reiter **Importieren** →
   die `.sql`-Datei hochladen → OK.
3. Block-Cache leeren (siehe unten).

**Wenn die Zielseite schon Designs hat:** Ein SQL-Export enthält
`INSERT`-Anweisungen mit festen `id`-Werten und ggf. `DROP TABLE`/`CREATE TABLE`.
Das kann bestehende Designs überschreiben. Sicherer ist dann Weg C.

---

## Weg C — Nur ausgewählte Designs, ohne ID-Kollision (selektiv)

Wenn die Zielseite bereits eigene Designs hat und du nur einzelne hinzufügen
willst, überträgst du die Zeilen **ohne die `id`-Spalte** — MySQL vergibt dann
neue IDs, bestehende Designs bleiben unangetastet.

1. Auf der **Quelle** die gewünschten Designs als `INSERT` ohne `id` erzeugen,
   z. B. per SQL (phpMyAdmin → Reiter **SQL**):
   ```sql
   SELECT name, title, description, config, styles, features, status, created_at, updated_at
   FROM wp_cbd_blocks
   WHERE status = 'active';
   ```
   Die Ergebnisse als CSV/SQL exportieren, oder die Werte direkt in ein
   `INSERT` übernehmen.
2. Auf der **Ziel-Seite** einfügen (Spaltenliste OHNE `id`):
   ```sql
   INSERT INTO wp_cbd_blocks
     (name, title, description, config, styles, features, status, created_at, updated_at)
   VALUES
     ('info-box', 'Info-Box', '...', '{...}', '{...}', '{...}', 'active', NOW(), NOW());
   ```
3. **Slug-Kollision prüfen:** Existiert auf der Zielseite schon ein Design mit
   demselben `name`/`slug`, vorher entscheiden: überschreiben (`UPDATE`) oder
   unter neuem Slug anlegen. Doppelte Slugs vermeiden.
4. Block-Cache leeren (siehe unten).

---

## Nach dem Import: Block-Cache leeren

Seit der Sanierung wird die Liste der aktiven Designs in einem Transient gecacht
(`cbd_active_blocks`, bis zu 24 h). Nach einem DB-Import einmal invalidieren —
eine der folgenden Aktionen genügt:

- Im Admin **Container Blocks → Blöcke** öffnen und bei einem Block eine
  Kleinigkeit speichern (löst die Cache-Invalidierung aus), **oder**
- Plugin einmal deaktivieren und wieder aktivieren, **oder**
- per WP-CLI: `wp transient delete cbd_active_blocks` (und zur Sicherheit
  `wp transient delete --all`).

Danach das neue Design im Editor im Dropdown **Design-Style** prüfen und eine
Testseite mit dem entsprechenden Container aufrufen.

---

## Checkliste

- [ ] Tabellen-Präfix beider Seiten geprüft (Standard `wp_`)
- [ ] Übertragungsweg gewählt (A/B/C je nach Zugriff und Kollisionslage)
- [ ] Slugs stimmen mit den auf Seiten referenzierten Designs überein
- [ ] Keine doppelten Slugs auf der Zielseite erzeugt
- [ ] Block-Cache nach dem Import geleert
- [ ] Design im Editor-Dropdown sichtbar und auf einer Testseite korrekt gestylt

---

## Import/Export doch wieder aktivieren?

Falls der eingebaute Import/Export später doch gebraucht wird, ist er **nicht
gelöscht**, nur ausgeblendet: In `includes/class-cbd-admin.php` den
auskommentierten `add_submenu_page`-Block für `cbd-import-export` wieder
einkommentieren. **Vorher** aber die Handler sicher implementieren — die
verbindlichen Sicherheitsanforderungen (ZIP entfernen, Upload-/MIME-/Größen-
validierung, JSON-Roundtrip, kein `unserialize`, SSRF beim URL-Import) stehen in
[UMSETZUNGSPLAN-OFFENE-PUNKTE.md](UMSETZUNGSPLAN-OFFENE-PUNKTE.md), Aufgabe B.
