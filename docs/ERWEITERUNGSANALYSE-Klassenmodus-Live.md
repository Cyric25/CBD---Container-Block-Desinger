# Erweiterungsanalyse — Live-Aktualisierung im Klassenmodus

Stand: 2026-08-30. Grundlage: `DOKUMENTATION.md`, Root-`CLAUDE.md`,
`Plugins/CDB-Designer/CLAUDE.md`, `Plugins/CDB-Designer/reference_file_map.md`
sowie der gelesene Klassenmodus-Code (CDB-Designer v3.1.117).

## 1. Kurzbeschreibung der Erweiterung

Gibt eine Lehrperson im Unterricht einen Container-Block für ihre Klasse frei,
sehen die Schüler ihn heute erst nach einem Neuladen der Seite. Die Erweiterung
lässt die Freigabe binnen rund zehn Sekunden von selbst erscheinen — auf
normalen Seiten, auf serverseitig reduzierten Lösungsseiten und in der
Klassen-Seitenliste. Rücknahmen, Tafelbilder und die Fragenwand ziehen im
selben Takt nach.

## 2. Verständnis des Ist-Projekts

**Projektzweck:** WordPress-Schulbuch-Website (Theme „FOS Online Schulbuch" +
Plugins CDB-Designer und „Eigene WP Blocks"). Der Klassenmodus liegt
vollständig im CDB-Designer.

**Wie „Freigeben" heute funktioniert:** Die Lehrperson setzt über
`cbd_toggle_behandelt` bzw. `cbd_set_behandelt` das Flag `is_behandelt` in
`wp_cbd_drawings`, eindeutig je `(class_id, page_id, container_id)`
(`UNIQUE KEY class_page_container`). Das ist die einzige Quelle der Wahrheit.

**Drei Schülerpfade, drei verschiedene Mechanismen** — das ist der entscheidende
Punkt für die Planung:

| Ansicht | Mechanismus heute | Ist das HTML da? |
|---|---|---|
| Klassen-Seitenliste `[cbd_classroom]` | `classroom-frontend.js` → `cbd_student_get_data`, einmalig | entfällt |
| Normale Seite mit `?classroom=` | `classroom-page-filter.js` → `cbd_get_page_classroom_data`, einmalig; nicht Freigegebenes wird per `$container.hide()` versteckt | **ja**, nur versteckt |
| Gesperrte Seite („nur Lehrpersonen") | `CBD_Classroom_Gate::inhalt_reduzieren()` filtert serverseitig auf `the_content` (Priorität 8, vor `do_blocks()`) | **nein**, wird nie ausgeliefert |

**Geltende Konventionen, die eingehalten werden müssen:**

- CDB-Designer bleibt **PHP 7.4** (Zielumgebung 7.4.33, Prüfung
  `tools/check-php74.php`).
- Kein Build-Prozess: reines PHP + Vanilla-JS/jQuery. Geteilter JS-Code wird im
  Plugin bewusst **dupliziert statt modularisiert** (siehe Kopfkommentar in
  `classroom-page-filter.js`).
- **Keine CDN-Einbindungen** (DSGVO).
- Sicherheitsgrundsatz aus `class-cbd-block-content-api.php` und
  `class-cbd-classroom-gate.php`: **Standard ist Ablehnung**; Ablehnung und
  Nichtexistenz antworten zeichengleich; jeder Theme-Aufruf hinter
  `function_exists()`.
- Token-Deutung gibt es **genau einmal**: `CBD_Classroom_Gate::sitzung()`. Eine
  zweite Fassung ist ausdrücklich der Fehlertyp, gegen den
  `tools/test-classroom-gate.php` wacht.
- ZIP-Bau nur mit `--no-dev`-Autoloader (sonst HTTP 500), Versions-Bump je
  Auslieferung.

## 3. Einordnung in die Architektur

### 3.1 Transport: zweistufige Abfrage, kein Push

Im gesamten Projekt gibt es **kein** `setInterval`, `EventSource` oder
WebSocket. Live-Aktualisierung ist damit neue Infrastruktur, und sie wird auf
all-inkl-Shared-Hosting betrieben.

**Server-Sent Events scheiden aus:** Jede offene SSE-Verbindung belegt dauerhaft
einen PHP-Prozess. Bei 25 gleichzeitigen Schülern wären das 25 blockierte
Worker — auf Shared Hosting nicht tragfähig.

**Gewählt: zweistufige Abfrage.**

- **Stufe 1** (alle ~10 s): neue Route `GET cbd/v1/klassenpuls` liefert nur
  Signaturen, keine Inhalte. Drei billige Aggregate über bereits vorhandene
  Indizes, kein `parse_blocks()`, kein Bildtransport. Antwort wenige Dutzend
  Byte.
- **Stufe 2** (nur wenn sich eine Signatur geändert hat): die **bestehenden**
  Endpunkte werden erneut aufgerufen — `cbd_get_page_classroom_data`,
  `cbd_student_get_data`, `GET cbd/v1/fragenwand`. Kein neuer Stufe-2-Endpunkt,
  keine zweite Fassung der Datenlogik.

Ein einziger Taktgeber je Browser-Tab bedient alle drei Teilsysteme. Die Last
bleibt damit unabhängig davon, wie viele davon gerade aktiv sind.

### 3.2 Die Signaturen

Eine Abfrage, zwei unterscheidbare Signaturen je Seite:

```sql
SELECT COUNT(*), SUM(is_behandelt), SUM(id * is_behandelt), MAX(updated_at)
FROM wp_cbd_drawings WHERE class_id = %d AND page_id = %d;
```

`SUM(id * is_behandelt)` zusammen mit `COUNT(*)` und `SUM(is_behandelt)` ist
eine Prüfsumme über die **Menge** der freigegebenen Zeilen: Jeder einzelne
Toggle verändert sie zwangsläufig. `MAX(updated_at)` bewegt sich dagegen bei
**jedem** Schreibvorgang, also auch beim Speichern eines Tafelbilds. Aus einer
Abfrage entstehen so zwei unterscheidbare Signaturen — nötig, weil Freigabe und
Tafelbild verschiedene Reaktionen auslösen und Tafelbilder als Data-URLs teuer
zu übertragen sind.

**Bewusst kein `GROUP_CONCAT`:** Dessen `group_concat_max_len` (Vorgabe 1024
Byte) würde ab etwa 44 Containern je Seite **stillschweigend** abschneiden, die
Signatur einfrieren und Aktualisierungen verschlucken.

`updated_at` trägt zwar `ON UPDATE CURRENT_TIMESTAMP`, wird in
`ajax_toggle_behandelt()` aber zusätzlich explizit über `current_time('mysql')`
gesetzt — die Signatur hängt also nicht allein an MySQLs Automatik.

### 3.3 Autorisierung: kein neuer Weg

`cbd/v1/klassenpuls` folgt zeichengleich dem Muster von `cbd/v1/block-html` und
`cbd/v1/fragenwand`: `permission_callback => '__return_true'`, die gesamte
Autorisierung im Callback, `nocache_headers()` als erste Anweisung in jedem
Antwortpfad, Klassensitzung ausschließlich über `CBD_Classroom_Gate::sitzung()`
(liest `?classroom=`/`?token=` selbst aus `$_GET`). Der Puls liefert **nur
Zahlen, nie Inhalte** — selbst eine fehlerhafte Prüfung gäbe keinen Lösungstext
preis. Die Inhalte holen die bestehenden Endpunkte mit ihren bereits geprüften
Ketten.

### 3.4 Die zentrale Architekturentscheidung: reduzierte Seiten werden neu geladen, nicht nachgerüstet

Naheliegend wäre, auf einer reduzierten Lösungsseite den neu freigegebenen
Container über `cbd/v1/block-html` nachzuladen und ins DOM zu hängen — der
Endpunkt existiert bereits und prüft genau diesen Fall. **Das trägt trotzdem
nicht,** und zwar aus drei Gründen, die alle im Code nachweisbar sind:

1. **Die Interactivity API hydratisiert nachträglich eingefügtes DOM nicht.**
   `assets/js/interactivity-store.js` ist ein ESM-Modul auf
   `@wordpress/interactivity`; WordPress bietet keinen öffentlichen Weg, einen
   nachgereichten Teilbaum zu hydratisieren. Aufklappen, Kopieren, Screenshot
   und PDF wären am eingefügten Block tot.
2. **Der jQuery-Rückfall hilft nicht aus:** `interactivity-fallback.js` steigt in
   `checkInteractivityAPI()` sofort aus, sobald die Interactivity API da ist —
   seine delegierten Handler sind dann gar nicht registriert. Und seine
   Pro-Container-Initialisierung `initializeContainers()` (Zeile 182, aufgerufen
   in Zeile 641) ist eine Closure innerhalb von `$(document).ready()`, von außen
   nicht erreichbar.
3. **Die serverseitige Reduktion ist die kanonische Ausgabe.** Sie verwirft auch
   freistehende Absätze und Überschriften. Ein nachgereichter Container in eine
   so gestutzte Seite einzusetzen erzeugt einen Zustand, den der Server nie
   ausgeliefert hätte — schwer prüfbar und schwer zu begründen.

**Deshalb:** Auf reduzierten Seiten löst eine Änderung ein gezieltes Neuladen
aus, mit erhaltener Scrollposition (`sessionStorage`) und dem Hinweis „Neu
freigegeben" nach dem Laden. Für den Schüler sieht das aus wie ein
Live-Einblenden; technisch ist es die geprüfte Serverausgabe.

**Auf normalen Seiten dagegen echtes Live-Einblenden ohne Neuladen:** Dort war
der Container beim Seitenaufbau bereits im DOM und ist längst hydratisiert — er
war nur per `$container.hide()` versteckt. `.show()` genügt, kein
Initialisierungsproblem.

Damit ist auch der Zusatzumfang sauber verteilt: Tafelbilder werden auf normalen
Seiten im DOM aktualisiert, auf reduzierten Seiten deckt sie das Neuladen ab.

### 3.5 Nummerierung und Formeln

`window.CBDRenumberBlocks` (`block-numbering.js`, Zeile 135) und
`window.cbdRenderLatex` (`latex-renderer.js`) sind bereits global und wiederholt
aufrufbar — nach jedem Ein- oder Ausblenden auf normalen Seiten werden beide
erneut aufgerufen, jeweils hinter einer `typeof`-Prüfung wie im
Accordion-Block. Ein **vierter** globaler Nachrüst-Haken wird dadurch **nicht**
nötig, weil auf normalen Seiten nichts eingefügt, sondern nur sichtbar gemacht
wird.

## 4. Betroffene Dateien

Alles im Repo **CDB-Designer**. Das Theme und „Eigene WP Blocks" bleiben
unberührt — keine der fünf dokumentierten Nähte wird angefasst.

| Datei | Rolle heute | Änderung |
|---|---|---|
| `includes/class-cbd-klassenpuls.php` | – | **neu** — Route `cbd/v1/klassenpuls`, die Signaturen |
| `assets/js/klassenpuls.js` | – | **neu** — ein Taktgeber je Tab, Page Visibility, Rückzug bei Fehlern, `window.cbdKlassenpuls.abonniere()` |
| `tools/test-klassenpuls.php` | – | **neu** — Prüfharnisch ohne WordPress, analog `test-classroom-gate.php` |
| `container-block-designer.php` | Bootstrap, `CBD_VERSION` | ändern — Klasse einbinden, Version |
| `includes/class-cbd-classroom.php` | AJAX-Handler, `enqueue_frontend_assets()` (Zeile 1633) | ändern — `klassenpuls.js` einreihen, Intervall lokalisieren |
| `assets/js/classroom-page-filter.js` | einmaliger Filter, `$container.hide()` (Zeile ~166) | ändern — an den Puls andocken; normale Seite: ein-/ausblenden, Tafelbild aktualisieren; reduzierte Seite: Neuladen mit Scroll-Erhalt |
| `assets/js/classroom-frontend.js` | Seitenliste, `renderClassroomContent()` (Zeile ~281) | ändern — Liste bei Signaturwechsel neu aufbauen |
| `assets/js/fragenwand-frontend.js` | Modal, `ladeSchuelerwand()` / `setzeListe()` | ändern — nur bei offenem Modal am Puls hängen, Bearbeitung nicht überschreiben |
| `assets/css/classroom-frontend.css` | Klassenansicht | ändern — Hinweis „Neu freigegeben", Theme-Akzentfarbe, Hell/Dunkel |
| `admin/settings.php` | Klassenmodus-Einstellungen | ändern — Regler Abfrageintervall, 0 = aus |
| `includes/class-cbd-classroom-gate.php` | `sitzung()`, `inhalt_reduzieren()` | **nur lesen** — der Puls ruft `sitzung()` unverändert auf |
| `includes/class-cbd-block-content-api.php` | `cbd/v1/block-html` | **nur lesen** — Vorbild der Autorisierungskette |
| `includes/class-cbd-fragenwand.php` | Fragenwand-Datenschicht | **nur lesen** — die Signatur liest `wp_cbd_notes` direkt |
| `includes/Database/class-schema-manager.php` | Schema | **nur lesen** — keine Migration, keine neue Spalte, keine neue Tabelle |
| `reference_file_map.md`, `CLAUDE.md` | Doku | ändern |

## 5. Wiederverwendung statt Neubau

- `CBD_Classroom_Gate::sitzung()` → einzige Token-Deutung, auch für den Puls.
- `cbd_get_page_classroom_data` (`class-cbd-classroom.php`, Zeile 1911) →
  Stufe 2 für die Seite, unverändert.
- `cbd_student_get_data` → Stufe 2 für die Klassen-Seitenliste, unverändert.
- `GET cbd/v1/fragenwand` (`rest_get_notes_for_student()`) → Stufe 2 für die
  Fragenwand, unverändert.
- `setzeListe()` in `fragenwand-frontend.js` → fertige Neuzeichnung der
  Notizliste.
- `window.CBDRenumberBlocks`, `window.cbdRenderLatex` → bestehende, global
  freigegebene Nachrüst-Haken.
- `wp_cbd_drawings.updated_at` und `UNIQUE KEY class_page_container` →
  Signaturen ohne Schemaänderung.
- Der `localStorage`-Vertrag `cbd_classroom_toc_collapsed` → der Klappzustand
  überlebt das Neuzeichnen der Seitenliste von selbst.

## 6. Integrationspunkte & Schnittstellen

**Neu:**
`GET /wp-json/cbd/v1/klassenpuls?classroom=<id>&token=<t>[&page_id=<id>]`
→ `{ "seite": "<sig>", "tafel": "<sig>", "klasse": "<sig>", "fragenwand": "<sig>", "takt": 10 }`.
Ohne `page_id` entfallen `seite` und `tafel`. Ablehnung zeichengleich zu
`cbd/v1/block-html`: HTTP 404, ein einziger Fehlercode.

**Neu (JS):** `window.cbdKlassenpuls.abonniere(name, rueckruf)` — die drei
Verbraucher hängen sich ein, ohne voneinander zu wissen.

**Datenfluss:** DB-Aggregate → Signaturstring → Vergleich im Browser → bei
Änderung Aufruf des jeweiligen Bestandsendpunkts → DOM-Aktualisierung oder
Neuladen.

**DB-Schema:** keine Änderung. Keine neue Tabelle, keine neue Spalte, keine
Migration. Das umgeht auch die dokumentierte `CREATE TABLE IF NOT EXISTS`-Falle,
an der `dbDelta()` Spalten nicht nachzieht.

## 7. Regressionsfläche

1. **Serverseitige Reduktion (`inhalt_reduzieren()`).** Die schärfste Grenze im
   ganzen Vorhaben. Der Puls darf sie nicht berühren. Nachzuweisen: mit
   abgeschaltetem Puls **byteidentische** Ausgabe einer reduzierten Seite.
2. **Sperre für nicht angemeldete Besucher ohne Klassensitzung.** Der neue
   Endpunkt ist öffentlich erreichbar. Nachzuweisen: ohne gültiges Token
   antwortet er zeichengleich ablehnend, und zwar auch bei gültigem Token einer
   **anderen** Klasse (der Confused-Deputy-Fall, den `sitzung()` abfängt).
3. **Einmaliger Erstaufbau aller drei Ansichten.** Der Puls kommt zusätzlich —
   der bestehende erste Abruf muss unverändert bleiben, damit eine abgeschaltete
   Live-Funktion exakt den heutigen Zustand ergibt.
4. **Fragenwand-Eingabe und -Bearbeitung.** Ein Neuzeichnen während des Tippens
   oder Bearbeitens darf nichts verwerfen.
5. **Klappzustand der Inhaltsverzeichnisse** (`cbd_classroom_toc_collapsed`,
   Phase 1 aus `PLAN-Inhaltsverzeichnisse.md`) muss das Neuzeichnen der
   Seitenliste überstehen — in beiden Richtungen, wie dort geprüft.
6. **Tafelbild-Abschnitt.** Der bestehende Code fügt ihn nur ein, wenn noch
   keiner da ist (`.cbd-class-drawing-section` `.length === 0`). Für die
   Aktualisierung braucht es einen Ersetzungspfad, ohne den Erstaufbau zu
   verändern.
7. **Serverlast auf Shared Hosting.** 25 Schüler × 6 Abfragen/min ≈ 2,5 req/s.
   Zu messen, nicht zu schätzen.

## 8. Konventions-Konformität

PHP 7.4 (`tools/check-php74.php` läuft je Phase); kein Build-Schritt, Vanilla-JS
+ jQuery; keine CDN-Einbindung; deutschsprachige Bezeichner und Kommentare wie
in den jüngeren Klassen (`class-cbd-classroom-gate.php`,
`class-cbd-fragenwand.php`); Standard ist Ablehnung; `nocache_headers()` zuerst;
Theme-Aufrufe hinter `function_exists()`; eigener Prüfharnisch unter `tools/`;
Versions-Bump und ZIP-Bau mit `--no-dev`-Autoloader je Auslieferung.

## 9. Risiken & offene Fragen

| Risiko | Gegenmaßnahme |
|---|---|
| Abfragelast bringt Shared Hosting an die Grenze | Regler in `admin/settings.php` inkl. **0 = aus**; Stufe 1 antwortet mit wenigen Dutzend Byte; Pause bei verstecktem Tab; Rückzug nach wiederholten Fehlern; Messung als eigenes AP |
| Prüfsummen-Kollision in `SUM(id * is_behandelt)` | Zusammen mit `COUNT(*)` und `SUM(is_behandelt)` praktisch ausgeschlossen; **Fehlerrichtung ist harmlos** — im schlimmsten Fall erscheint die Änderung erst beim nächsten Toggle, es wird nie zu viel gezeigt. Als bewusste Einschränkung dokumentieren |
| Neuladen auf reduzierter Seite verwirft Aufklapp-Zustände | Scrollposition über `sessionStorage` erhalten; Aufklapp-Zustand als bekannte Einschränkung vermerken |
| Rücknahme der **letzten** Freigabe auf einer gesperrten Seite → Neuladen ergäbe HTTP 403 | Der Client erkennt „keine Freigabe mehr" an der Signatur und leitet auf die Klassen-Seitenliste um, statt in die 403-Hinweisseite zu laufen |
| Tafelbild-Payload: die Lehrperson zeichnet, `MAX(updated_at)` springt oft | Getrennte Signatur `tafel`; Freigaben lösen nicht mit aus. Auf reduzierten Seiten könnte häufiges Zeichnen wiederholtes Neuladen auslösen → Mindestabstand zwischen zwei Neuladungen |
| Ein zweiter Weg zur Token-Deutung entstünde | `sitzung()` bleibt die einzige Stelle; `tools/test-klassenpuls.php` prüft, dass in der neuen Datei kein `get_transient('cbd_classroom_` vorkommt — dieselbe Wächter-Technik wie in `test-block-content-api.php` |
| Seiten-Cache liefert eine gecachte Puls-Antwort | `nocache_headers()` bedingungslos als erste Anweisung, wie in `block-html` und `fragenwand` |

**Doku-Lücke aus Schritt 1:** `DOKUMENTATION.md` endet beim Vorhaben
„Fragenwand" (v3.1.109); die Stände 3.1.110–3.1.117 (u. a. Schüler-Fragen,
Frontend-Klassenverwaltung, Datenbank-Reparatur-Hotfix) stehen nur in
`Plugins/CDB-Designer/CLAUDE.md`. Im Dokumentations-AP der letzten Phase
mitschließen.

## 10. Grobzuschnitt für den projektplan-skill

**Mehrphasig** — jede Phase endet mit einem lauffähigen Zwischenergebnis, und
Phase 1 ist für sich allein schon abschaltbar.

- **Phase 1 — Puls:** `cbd/v1/klassenpuls`, `klassenpuls.js`, Einstellung,
  Prüfharnisch, Lastmessung. Ergebnis: Der Takt läuft, niemand hört zu.
- **Phase 2 — Normale Seiten:** Ein-/Ausblenden live, Tafelbild-Aktualisierung,
  Hinweis „Neu freigegeben". Der wertvollste und risikoärmste Teil.
- **Phase 3 — Gesperrte Seiten:** Neuladen mit Scroll-Erhalt, Umleitung beim
  Wegfall der letzten Freigabe, Mindestabstand. Enthält den Byte-Vergleich der
  Reduktion gegen den Ausgangszustand.
- **Phase 4 — Klassen-Seitenliste und Fragenwand:** Neuzeichnen bei
  Signaturwechsel, Klappzustand und Eingaben unangetastet.
- Je Phase ein Review-AP (`AP-<N>.rev`) und ein Dokumentations-AP
  (`AP-<N>.doc`); `reference_file_map.md` in jedem AP, das Dateien anlegt oder
  wesentlich ändert; ZIP-Bau samt Versions-Bump am Ende.
