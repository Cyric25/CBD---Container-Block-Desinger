# Erweiterungsanalyse — Schneller Klassenpuls (Stufe 0)

Stand: 2026-09-15. Grundlage: `DOKUMENTATION.md`, Root-`CLAUDE.md`,
`Plugins/CDB-Designer/CLAUDE.md`, `Plugins/CDB-Designer/reference_file_map.md`,
`docs/ERWEITERUNGSANALYSE-Klassenmodus-Live.md`, `docs/messung-klassenpuls.md`
sowie der gelesene Klassenmodus-Code (CDB-Designer v3.1.131).

Anlass: Der Betreiber hat seit neuestem einen KAS-Zugang bei all-inkl und
möchte die Live-Aktualisierung beschleunigen, vermutet Push als Weg.

## 1. Kurzbeschreibung der Erweiterung

Die Verzögerung zwischen „Lehrperson gibt frei" und „Schüler sieht es" sinkt
von bis zu 10 Sekunden auf 2–3 Sekunden — **bei geringerer Serverlast als
heute**. Erreicht wird das nicht durch Push, sondern indem Stufe 1 der
bestehenden Abfrage den WordPress-Bootstrap verlässt: Die vier Signaturen
stehen künftig zusätzlich in einer winzigen, vom Server bei jeder Änderung neu
geschriebenen JSON-Datei, die Apache direkt ausliefert. Die bestehende
REST-Route bleibt als Sitzungsprüfung, Selbstheilung und vollständige
Rückfallebene erhalten.

## 2. Verständnis des Ist-Projekts

**Projektzweck:** WordPress-Schulbuch-Website (Theme „FOS Online Schulbuch" +
Plugins CDB-Designer und „Eigene WP Blocks"). Der Klassenmodus liegt
vollständig im CDB-Designer.

**Die Live-Aktualisierung ist bereits gebaut und abgenommen**
(`PLAN-Klassenmodus-Live.md`, vier Phasen + drei Nachträge + mehrere
`fix`-Pakete). Sie arbeitet zweistufig:

| Stufe | Was | Wo |
|---|---|---|
| 1 | `GET cbd/v1/klassenpuls` alle ~10 s, liefert **nur** vier Prüfsummen (63–109 Byte) | `includes/class-cbd-klassenpuls.php`, `assets/js/klassenpuls.js` |
| 2 | bei Signaturwechsel: die **bestehenden** Endpunkte `cbd_get_page_classroom_data`, `cbd_student_get_data`, `GET cbd/v1/fragenwand` | unverändert |

Ein Taktgeber je Browser-Tab (`window.cbdKlassenpuls`), vier Abonnenten
(`classroom-page-filter.js`, `classroom-frontend.js`, `fragenwand-frontend.js`),
±25 % Streuung auf das Intervall (`AP-1.fix1`), Notbremse
`cbd_klassenpuls_takt = 0`.

**Der Messwert, auf dem diese ganze Erweiterung steht**
(`docs/messung-klassenpuls.md`): Eine Pulsanfrage dauert im Median **108 ms**
für **109 Byte** Nutzlast. Abschnitt 4 derselben Messung zeigt, dass eine
**abgelehnte** Anfrage genauso teuer ist wie eine erfolgreiche — der Aufwand
steckt also fast vollständig im vollständigen WordPress-Bootstrap je Anfrage
(rund 34 Boot-Protokollzeilen, Registrierung aller Container-Blöcke und aller
Blöcke aus „Eigene WP Blocks"), nicht in der Sitzungsprüfung und nicht in den
SQL-Aggregaten. **Der Flaschenhals ist nicht der Takt, sondern das, was hinter
jeder einzelnen Anfrage hochgefahren wird.**

**Geltende Konventionen, die eingehalten werden müssen:**

- CDB-Designer bleibt **PHP 7.4** (Zielumgebung 7.4.33, Prüfung
  `tools/check-php74.php`).
- Kein Build-Prozess: reines PHP + Vanilla-JS (ES5, `var`/`function`, IIFE).
- **Keine CDN-Einbindungen** (DSGVO).
- **Standard ist Ablehnung**; Ablehnung und Nichtexistenz antworten
  zeichengleich; `nocache_headers()` als erste Anweisung in jedem Antwortpfad.
- **Token-Deutung gibt es genau einmal:** `CBD_Classroom_Gate::sitzung()`.
  `tools/test-klassenpuls.php` prüft ausdrücklich, dass in
  `class-cbd-klassenpuls.php` kein `get_transient('cbd_classroom_` vorkommt.
- Der Vertrag `window.cbdKlassenpuls` (sieben Namen, fünf Abonnentennamen) ist
  als solcher deklariert und wird **nicht** umbenannt.
- ZIP-Bau nur mit `--no-dev`-Autoloader, Versions-Bump je Auslieferung.

## 3. Einordnung in die Architektur

### 3.1 Vorab: Was der KAS-Zugang tatsächlich ändert — und was nicht

Das ist der Ausgangspunkt der Anfrage und muss geradegerückt werden, bevor
darauf geplant wird. **KAS ist das Kunden-Administrations-System, also ein
Verwaltungspanel — kein neues Prozessmodell.** Die vier naheliegenden
Push-Wege wurden einzeln geprüft:

| Weg | Urteil | Begründung |
|---|---|---|
| **WebSocket / eigener Node-Dienst** | scheidet aus (außer auf Root-/VPS-Server) | all-inkl-Support selbst: für sinnvolle Node-Nutzung bräuchte es selbstdefinierte WebSockets, „was im Shared Hosting kaum umsetzbar und nicht empfehlenswert ist"; Verweis auf VPS. KAS ändert daran nichts. |
| **Web Push (VAPID + Service Worker)** | technisch machbar, **für diesen Zweck untauglich** | Chrome, Firefox und Safari erzwingen `userVisibleOnly: true`. Jede Push-Nachricht **muss** eine sichtbare Systembenachrichtigung erzeugen — also ein Signal auf 25 Schülergeräten je Freigabe. Dazu Berechtigungsdialog je Gerät, iOS nur als installierte PWA, und der Transport läuft über die Push-Server von Google/Mozilla/Apple, was mit der Projektregel „keine CDN-Einbindungen (DSGVO)" kollidiert. Der Betreiber hat „alles leise" gewählt — damit ist dieser Weg aus. |
| **SSE / Long-Polling** | bleibt der Prozessblocker | Genau der Grund, aus dem `ERWEITERUNGSANALYSE-Klassenmodus-Live.md` Push verworfen hat. KAS erlaubt zwar, `max_execution_time` über `.user.ini` zu erhöhen (ab PHP 8 wirken `php_value`-Direktiven in `.htaccess` bei all-inkl nicht mehr), aber jede gehaltene Verbindung belegt weiterhin **einen PHP-Arbeitsprozess je Schüler**. Die Zahl gleichzeitiger Prozesse ist bei all-inkl nicht öffentlich dokumentiert. |
| **Cronjobs** | **echter Zugewinn — aber nicht als Transport** | Ab Tarif PrivatPlus, Verwaltung im KAS. Cron läuft Server→Server und erreicht keinen Browser. Nützlich für das Aufräumen verwaister Dateien und als zuverlässiger Auslöser für `wp-cron.php` (siehe 3.6). |

**Fazit:** Push im Sinne von „der Server stößt den Browser an" ist auf diesem
Hosting weiterhin nicht tragfähig. Der KAS-Zugang bringt Cronjobs und
`.user.ini`-Kontrolle — beides nützlich, beides kein Transport.

**Der Hebel liegt woanders und ist größer:** nicht die Zahl der Anfragen
senken, sondern die **Kosten je Anfrage**. 108 ms auf 1–3 ms.

### 3.2 Aus zwei Stufen werden drei

Die bestehende Zweistufigkeit bleibt unangetastet; **davor** kommt eine neue,
sehr billige Stufe:

| Stufe | Takt | Was | Kostet |
|---|---|---|---|
| **1 — Pulsdatei (neu)** | ~2 s | statische JSON-Datei bei Apache, `fetch(url, {cache: 'no-cache'})`, meist HTTP 304 ohne Body | **kein PHP**, ~1–3 ms |
| **0 — Herzschlag (bestehende Route, seltener)** | ~60 s | `GET cbd/v1/klassenpuls` — prüft die Sitzung, liefert Takt und Dateiadresse, **schreibt die Pulsdatei neu** | 108 ms |
| **2 — Inhalte (unverändert)** | bei Bedarf | `cbd_get_page_classroom_data`, `cbd_student_get_data`, `GET cbd/v1/fragenwand` | unverändert |

Die Nummerierung ist mit Absicht so: Die REST-Route bleibt der **Anker** des
Systems (Sitzungsprüfung, Takt, Selbstheilung), sie tickt nur seltener. Die
Pulsdatei ist die schnelle, billige Schicht davor.

**Warum 2 Sekunden billiger sind als heute 10:** 25 Schüler im 10-s-Takt
erzeugen heute 2,5 vollständige WordPress-Bootstraps pro Sekunde (≈ 270 ms
PHP-Zeit/s). Nach der Umstellung: 12,5 statische Dateiabrufe pro Sekunde
(≈ 25 ms Apache-Zeit/s, überwiegend 304 ohne Body) plus 0,4 Bootstraps pro
Sekunde für den Herzschlag (≈ 45 ms/s). **Rund ein Sechstel der heutigen
PHP-Last bei fünffach besserer Reaktionszeit.**

### 3.3 Die Pulsdatei: eine Datei je Klasse, nicht je Seite

```
wp-content/uploads/container-block-designer/klassenpuls/
    puls-<class_id>-<hmac16>.json
```

Inhalt (Beispiel, formatiert; real ohne Leerraum, ~200–800 Byte):

```json
{
  "klasse": "a1b2c3d4",
  "fragenwand": "e5f6a7b8",
  "seiten": { "368": ["c9d0e1f2", "a3b4c5d6"], "372": ["…", "…"] },
  "takt": 2,
  "stand": 1789456123
}
```

`seiten` bildet `page_id → [seite, tafel]` ab. **Eine Datei je Klasse, nicht je
Seite** — aus drei Gründen: Ein Umschalten schreibt dann genau eine Datei
statt einer je betroffener Seite; der Browser braucht nur einen einzigen
Abruf, unabhängig davon, auf welcher Seite er steht; und die Zahl der Dateien
wächst mit der Zahl der Klassen (Dutzende), nicht mit dem Produkt aus Klassen
und Seiten (Tausende).

**Die Größenfalle wird ausdrücklich behandelt** — dieselbe Klasse von Fehler,
gegen die sich das Vorhaben beim `GROUP_CONCAT` schon einmal entschieden hat
(dessen `group_concat_max_len` hätte ab ca. 44 Containern stillschweigend
abgeschnitten): Überschreitet die `seiten`-Abbildung eine feste Obergrenze
(Vorschlag: 400 Einträge), wird sie **weggelassen statt gekürzt** und ein
Feld `seiten_unvollstaendig: true` gesetzt. Der Browser holt `seite`/`tafel`
dann über die REST-Route wie heute. Lieber langsamer als still falsch.

Geschrieben wird über `tmp` + `rename()` (auf demselben Dateisystem atomar) —
ein Leser bekommt nie eine halb geschriebene Datei.

### 3.4 Autorisierung: Capability-URL, kein zweiter Auth-Pfad

Das ist der heikelste Punkt, und er berührt die schärfste Projektregel
(„Token-Deutung genau einmal").

**Die Datei prüft nichts — sie muss auch nichts prüfen.** Ihr Dateiname trägt
einen aus einem Servergeheimnis abgeleiteten Anteil:

```php
$hmac16 = substr(hash_hmac('sha256', 'klassenpuls|' . $class_id, wp_salt('auth')), 0, 16);
```

Die Adresse erfährt der Browser **ausschließlich** aus der Antwort der
bestehenden REST-Route — also erst, **nachdem** `CBD_Classroom_Gate::sitzung()`
ihn durchgelassen hat. Es entsteht damit **kein zweiter Weg zur Token-Deutung**,
sondern eine Capability-URL hinter der einen bestehenden Prüfung. Die Wache in
`tools/test-klassenpuls.php` (kein `get_transient('cbd_classroom_` in der
Pulsklasse) bleibt gültig und wird auf die neuen Dateien ausgeweitet.

**Die Schutzwürdigkeit ist gering, und das ist nachweisbar, nicht behauptet:**
Der Dateiinhalt sind ausschließlich Prüfsummen — dieselbe Feststellung, mit
der schon `ERWEITERUNGSANALYSE-Klassenmodus-Live.md` die Route begründet hat
(„Der Puls liefert nur Zahlen, nie Inhalte — selbst eine fehlerhafte Prüfung
gäbe keinen Lösungstext preis"). Alle Inhalte holen weiterhin die
Stufe-2-Endpunkte mit ihren unveränderten, geprüften Ketten.

**Die dafür einzugehende Einschränkung wird benannt, nicht versteckt:** Wer die
Adresse einmal hatte, kann sie weiter abrufen, auch nach Ablauf seiner
Klassensitzung — er sieht dann Prüfsummen, die sich ändern, aber keinen Inhalt.
Gegenmaßnahme bei Bedarf: `wp_salt('auth')` rotieren (entwertet alle Adressen
auf einen Schlag) bzw. Datei bei Klassenlöschung entfernen.

Zusätzlich in das Verzeichnis: `index.php` (leer) und eine `.htaccess` mit
`Options -Indexes` — nach dem im Projekt bereits vorhandenen Muster aus
`class-cbd-icon-manager.php:292–296` und `class-cbd-pdf-generator.php:288`.

### 3.5 Selbstheilung: warum eine veraltete Datei kein Fehler ist, sondern nur langsam

Die Pulsdatei wird an acht Schreibstellen neu erzeugt (siehe 6). Eine davon zu
übersehen — oder ein direkter SQL-Eingriff, eine Migration, ein künftiges
Arbeitspaket — ließe die Datei einfrieren und Änderungen verschlucken. **Genau
das darf nicht passieren, und deshalb ist der Herzschlag aus 3.2 nicht
optional:** Die REST-Route schreibt die Datei bei jedem Aufruf neu. Der
schlimmste Fall einer vergessenen Schreibstelle ist damit nicht „die Änderung
kommt nie an", sondern „die Änderung kommt nach bis zu 60 Sekunden an" — das
heutige Verhalten, nur etwas langsamer. Das ist die Eigenschaft, die diesen
Entwurf überhaupt vertretbar macht.

Zweite Selbstheilung: Antwortet die Datei mit 404 (nie geschrieben, aufgeräumt,
Uploads-Verzeichnis geleert) oder scheitert der Abruf dreimal, fällt der
Taktgeber auf **genau das heutige Verhalten** zurück — REST-Route im Takt
`cbd_klassenpuls_takt`. Das heutige System ist also nicht nur Vorgänger,
sondern dauerhafte Rückfallebene.

### 3.6 Wo der KAS-Zugang konkret hilft

- **Aufräumen verwaister Pulsdateien** (gelöschte Klassen): ein KAS-Cronjob,
  der `wp-cron.php` verlässlich anstößt, oder direkt ein kleines Skript. Bis
  dahin genügt das Löschen in `ajax_delete_class()` plus ein täglicher
  `wp_schedule_event`-Durchlauf.
- **`.user.ini`** statt `.htaccess`-`php_value` für PHP-Einstellungen ab PHP 8
  — relevant, falls später doch eine gehaltene Verbindung erprobt wird.
- **Testdomain** (Subdomain im KAS) für die Lastmessung auf der echten
  Maschine — der Betreiber hat diesen Weg ausdrücklich gewählt.

## 4. Betroffene Dateien

Alles im Repo **CDB-Designer**. Theme und „Eigene WP Blocks" bleiben unberührt;
keine der fünf dokumentierten Nähte wird angefasst.

| Datei | Rolle heute | Änderung |
|---|---|---|
| `includes/class-cbd-klassenpuls.php` | Route `cbd/v1/klassenpuls`, `signaturen_seite()`, `signatur_klasse()`, `signatur_fragenwand()`, `takt()`, `baue_signatur()` | **ändern** — Pulsdatei schreiben/löschen/adressieren, Antwort um `datei`, `takt_datei`, `herzschlag` erweitern, Aggregat über alle Seiten einer Klasse |
| `assets/js/klassenpuls.js` | Taktgeber, Vertrag `window.cbdKlassenpuls` | **ändern** — zweiter, schneller Zeitgeber gegen die Datei; REST wird zum Herzschlag; Rückfall auf heutiges Verhalten. **Der Vertrag nach außen bleibt Zeichen für Zeichen gleich** |
| `includes/class-cbd-classroom.php` | `ajax_toggle_behandelt()` (726), `ajax_set_behandelt()` (854), `ajax_save_drawing()` (572), `ajax_delete_class()` (430), `enqueue_frontend_assets()` (1935/2203) | **ändern** — `do_action('cbd_klassenmodus_geaendert', $class_id)` an den Schreibstellen; Pulsdatei bei Klassenlöschung entfernen |
| `includes/class-cbd-fragenwand.php` | Schreibstellen 814, 852, 892, 919, 1241 | **ändern** — dieselbe Aktion auslösen |
| `includes/functions.php` | `cbd_sanitize_klassenpuls_takt()` (107) | **ändern** — zweiter Sanitizer für den Dateitakt |
| `admin/settings.php` | Feld „Live-Aktualisierung (Sekunden)" (329–334) | **ändern** — zweites Feld „Schneller Takt (Sekunden, 0 = nur Herzschlag)" |
| `tools/test-klassenpuls.php` | 22 Prüfungen ohne WordPress | **ändern** — Dateinamensbildung, Größengrenze, Wächter gegen zweite Token-Deutung |
| `tools/test-pulsdatei.php` | – | **neu** (optional, falls `test-klassenpuls.php` zu groß wird) |
| `container-block-designer.php` | Bootstrap, `CBD_VERSION` (27) | **ändern** — nur Versions-Bump |
| `docs/messung-pulsdatei.md` | – | **neu** — Messbericht von der KAS-Testdomain |
| `includes/class-cbd-classroom-gate.php` | `sitzung()` | **nur lesen** — bleibt die einzige Token-Deutung |
| `includes/class-cbd-style-loader.php` | schreibt generiertes CSS nach uploads (2199–2213) | **nur lesen** — Vorbild für das Schreibmuster |
| `includes/class-cbd-icon-manager.php` | `.htaccess`/`index.php` im Uploads-Unterordner (292–296) | **nur lesen** — Vorbild für die Verzeichnisabsicherung |
| `includes/Database/class-schema-manager.php` | Schema | **nur lesen** — keine Migration, keine neue Spalte, keine neue Tabelle |
| `assets/js/classroom-page-filter.js`, `classroom-frontend.js`, `fragenwand-frontend.js` | die vier Abonnenten | **nur lesen** — sie merken von der Umstellung nichts |
| `reference_file_map.md`, `CLAUDE.md`, `DOKUMENTATION.md` | Doku | **ändern** |

## 5. Wiederverwendung statt Neubau

- `CBD_Klassenpuls::baue_signatur()`, `signatur_klasse()`, `signatur_fragenwand()`,
  `signaturen_seite()` → **dieselben** Signaturfunktionen füllen die Datei. Es
  entsteht keine zweite Signaturlogik; die Datei ist nur ein zweiter
  Auslieferungsweg derselben Werte.
- `CBD_Classroom_Gate::sitzung()` → einzige Token-Deutung, unverändert.
- `CBD_Klassenpuls::takt()` → Muster für den zweiten Takt (`takt_datei()`).
- `cbd_sanitize_klassenpuls_takt()` in `includes/functions.php` → Vorlage für
  den zweiten Sanitizer (Komma-Behandlung, `0 = aus`, Klemmung).
- `update_compiled_css_file()` in `class-cbd-style-loader.php:2198–2213` →
  fertiges Muster für „Verzeichnis unter uploads anlegen und Datei schreiben".
- `class-cbd-icon-manager.php:288–297` → fertiges Muster für `.htaccess` +
  `index.php` im Uploads-Unterordner.
- Der gesamte Vertrag `window.cbdKlassenpuls` mitsamt seinen vier Abonnenten →
  unverändert; die Umstellung findet vollständig **innerhalb** von
  `klassenpuls.js` statt.
- Die bestehende Regel 1 des Taktgebers („die erste Antwort löst keinen
  Rückruf aus") → gilt für die Datei genauso und verhindert das unnötige
  Neuladen direkt nach dem Seitenaufbau.
- `cbd_klassenpuls_takt = 0` → bleibt die eine Notbremse für **alles**.

## 6. Integrationspunkte & Schnittstellen

**Neue Aktion (WordPress-Hook), an acht Stellen ausgelöst:**

```php
do_action('cbd_klassenmodus_geaendert', (int) $class_id);
```

| Datei | Stelle |
|---|---|
| `class-cbd-classroom.php` | `ajax_toggle_behandelt()`, `ajax_set_behandelt()`, `ajax_save_drawing()` |
| `class-cbd-fragenwand.php` | Notiz anlegen (814), erledigt umschalten (852), Text ändern (892), löschen (919), Schülerfrage anlegen (1241) |

`CBD_Klassenpuls` hängt sich als einziger Verbraucher ein, sammelt die
`class_id`s je Anfrage und schreibt **einmal** auf `shutdown` — so verzögert
sich die Antwort auf den Klick der Lehrperson nicht. Ein Fehlschlag beim
Schreiben bleibt folgenlos und lautlos: Der Herzschlag holt es nach.

**Erweiterte Antwort der bestehenden Route** (rückwärtskompatibel, nur neue
Felder):

```
GET /wp-json/cbd/v1/klassenpuls?classroom=<id>&token=<t>[&page_id=<id>]
→ { "seite": …, "tafel": …, "klasse": …, "fragenwand": …, "takt": 10,
    "datei": "https://…/puls-15-a1b2….json", "takt_datei": 2, "herzschlag": 60 }
```

`datei: null` heißt „Datei nicht verfügbar" → der Browser verhält sich exakt
wie heute. Die Ablehnung bleibt **zeichengleich** zum heutigen Zustand
(HTTP 404, ein einziger Fehlercode) — insbesondere darf der Ablehnungspfad
**keine** Dateiadresse verraten.

**Neue statische Ressource:**
`GET wp-content/uploads/container-block-designer/klassenpuls/puls-<id>-<hmac16>.json`
→ siehe 3.3. Abgerufen mit `fetch(url, { cache: 'no-cache' })`; das erzwingt
clientseitig eine Revalidierung und liefert im Normalfall HTTP 304 ohne Body,
ganz ohne `mod_headers` auf dem Server vorauszusetzen.

**Zwei neue Optionen:** `cbd_klassenpuls_takt_datei` (Vorgabe 2,
Klemmung 1–60, `0` = nur Herzschlag) und — falls nicht fest verdrahtet —
`cbd_klassenpuls_herzschlag` (Vorgabe 60). `cbd_klassenpuls_takt` behält seine
heutige Bedeutung **unverändert**: Takt der REST-Route im Rückfallbetrieb und
Notbremse für alles.

**DB-Schema:** keine Änderung. Keine neue Tabelle, keine neue Spalte, keine
Migration — und damit auch keine Berührung der dokumentierten
`CREATE TABLE IF NOT EXISTS`-Falle, an der `dbDelta()` Spalten nicht nachzieht.

## 7. Regressionsfläche

1. **Der Vertrag `window.cbdKlassenpuls`.** Vier Abonnenten in drei Dateien
   verlassen sich Zeichen für Zeichen auf sieben Namen und fünf
   Abonnentennamen. Nachzuweisen: Alle vier Live-Funktionen (normale Seite,
   gesperrte Seite, Klassen-Seitenliste, Fragenwand) verhalten sich nach der
   Umstellung unverändert — inklusive `abgelaufen`, das **genau einmal**
   feuern muss.
2. **Regel 1 („die erste Antwort löst keinen Rückruf aus").** Mit zwei
   Datenquellen gibt es jetzt **zwei** erste Antworten. Wird das übersehen,
   lädt jede gesperrte Seite direkt nach dem Aufbau einmal unnötig neu — genau
   der Fehler, gegen den Regel 1 geschrieben wurde.
3. **Die serverseitige Reduktion (`CBD_Classroom_Gate::inhalt_reduzieren()`).**
   Die schärfste Grenze des Vorhabens, schon beim letzten Mal. Nachzuweisen wie
   in `docs/pruefung-klassenmodus-live.md`: **byteidentische** Ausgabe einer
   reduzierten Seite bei `cbd_klassenpuls_takt = 0`.
4. **`cbd_klassenpuls_takt = 0` muss weiterhin ALLES abschalten** — auch den
   schnellen Takt und das Schreiben der Dateien. Eine zweite Option, die die
   Notbremse aushebelt, wäre ein schwerer Fehler.
5. **Zeichengleiche Ablehnung.** `cbd/v1/klassenpuls` ist laut
   `Plugins/CDB-Designer/CLAUDE.md` der **erste** Endpunkt des Plugins, der
   diese Regel lückenlos einhält. Die neuen Felder dürfen das nicht brechen:
   gültiges Token einer **anderen** Klasse, fehlendes Token, abgeschaltetes
   Klassensystem → weiterhin dieselbe 404-Antwort, ohne Dateiadresse.
6. **Die Schreibstellen dürfen nicht langsamer werden.** `ajax_toggle_behandelt()`
   ist der Klick der Lehrperson vor der Klasse. Nachzuweisen: messbar kein
   Unterschied; ein fehlgeschlagener Dateischreibvorgang darf das Umschalten
   **nie** scheitern lassen.
7. **Fragenwand-Eingabe und -Bearbeitung.** Kernregel aus Phase 4: im Zweifel
   nicht neu zeichnen. Ein fünffach schnellerer Takt erhöht die Zahl der
   Gelegenheiten, diese Regel zu verletzen, um das Fünffache.
8. **Klappzustand der Inhaltsverzeichnisse** (`cbd_classroom_toc_collapsed`)
   muss das häufigere Neuzeichnen der Seitenliste überstehen.
9. **Mindestabstand zwischen zwei Neuladungen auf gesperrten Seiten.** Bei
   2-Sekunden-Takt zeichnet eine Lehrperson am Tafelbild potenziell alle zwei
   Sekunden ein Neuladen herbei. Der bestehende Mindestabstand aus Phase 3 muss
   daraufhin neu bewertet werden — das ist der wahrscheinlichste
   Regressionsfund des ganzen Vorhabens.
10. **Uploads-Verzeichnis nicht beschreibbar / Datei nicht ausgeliefert.**
    Nachzuweisen: lautloser Rückfall auf heutiges Verhalten, keine Fehlermeldung
    beim Schüler, kein Eintrag im Fehlerlog je Abruf.

## 8. Konventions-Konformität

PHP 7.4 (`tools/check-php74.php` je Phase); kein Build-Schritt; ES5 in
`klassenpuls.js` (kein `let`/`const`, keine Arrow Functions, keine
Template-Literale — der Kopfkommentar der Datei sagt das ausdrücklich); keine
CDN-Einbindung; deutschsprachige Bezeichner und Kommentare wie in den jüngeren
Klassen; Standard ist Ablehnung; `nocache_headers()` zuerst; Theme-Aufrufe
hinter `function_exists()`; eigener Prüfharnisch unter `tools/`; Uploads-Muster
aus `class-cbd-style-loader.php` und `class-cbd-icon-manager.php`; Versions-Bump
und ZIP-Bau mit `--no-dev`-Autoloader je Auslieferung.

## 9. Risiken & offene Fragen

| Risiko | Gegenmaßnahme |
|---|---|
| Eine Schreibstelle wird übersehen → Datei friert ein | Der Herzschlag (3.5) schreibt sie alle 60 s neu. Schlimmster Fall: 60 s Verzögerung statt 2 s, nie „nie" |
| Uploads nicht beschreibbar / Datei nicht erreichbar | Lautloser Rückfall auf das heutige REST-Takten; als eigenes AP geprüft, nicht angenommen |
| Capability-URL bleibt nach Sitzungsende gültig | Inhalt sind nur Prüfsummen, keine Inhalte; Datei bei Klassenlöschung entfernen; `wp_salt('auth')`-Rotation entwertet alle Adressen. **Als bewusste Einschränkung dokumentieren** |
| `seiten`-Abbildung wird zu groß | Harte Obergrenze; bei Überschreitung **weglassen statt kürzen** plus `seiten_unvollstaendig`-Flag (3.3) |
| 2-s-Takt löst auf gesperrten Seiten Neulade-Gewitter aus | Mindestabstand aus Phase 3 neu bewerten; `tafel`-Signatur bleibt von `seite` getrennt |
| Zwei Zeitgeber im selben Taktgeber → doppelte Rückrufe | Die Datei ist im Normalbetrieb die **einzige** Quelle für Rückrufe; der Herzschlag aktualisiert nur Takt, Adresse und Sitzungsgültigkeit. Eine Signaturänderung, die der Herzschlag zuerst sieht, wird verworfen, nicht doppelt gemeldet |
| Testserver ≠ all-inkl | Messung auf einer KAS-Testdomain (vom Betreiber gewählt); die letzte Messung war ausdrücklich nur lokal und hat die Nicht-Übertragbarkeit der absoluten Zahlen selbst festgehalten |
| **Offene Frage: Hosting-Paket unbekannt** | Eigenes AP „Voraussetzungen im KAS prüfen" (Tarif, Cronjob-Verfügbarkeit, PHP-Version, Schreibrechte in uploads, Auslieferung von `.json`, `.user.ini`-Wirksamkeit). Der tragende Teil dieses Vorhabens funktioniert auf **jedem** Paket; nur die optionale Ausbaustufe „gehaltene Verbindung" hinge daran |

**Doku-Lücken aus Schritt 1:**

- `DOKUMENTATION.md` beschreibt den Klassenmodus-Live-Stand bis v3.1.119; die
  Stände bis v3.1.131 stehen nur in `Plugins/CDB-Designer/CLAUDE.md`. Im
  Dokumentations-AP der letzten Phase mitschließen.
- Bekannter Bestandsbefund B10 (einseitige Klemmung der ±25-%-Streuung beim
  Mindesttakt) wird beim neuen, kleineren Dateitakt **relevanter** als heute —
  die klemmungsfreie Formel `basis * (1 + Zufall·0,5)` sollte für den
  Dateitakt von Anfang an verwendet werden.
- Bekannter Bestandsbefund B9 (`WP_DEBUG_LOG` muss produktiv aus bleiben) wird
  durch dieses Vorhaben **entschärft**, nicht verschärft: Der Herzschlag
  erzeugt sechsmal weniger Bootstraps als der heutige Puls.

## 10. Grobzuschnitt für den projektplan-skill

**Mehrphasig.** Jede Phase endet mit einem lauffähigen Zwischenergebnis; die
Rückfallebene „heutiges Verhalten" bleibt in jeder Phase erreichbar.

- **Phase 0 — Voraussetzungen und Testdomain.** KAS-Paket feststellen,
  Testdomain einrichten, Schreibrechte und `.json`-Auslieferung prüfen. Klein,
  aber Voraussetzung für alles Weitere.
- **Phase 1 — Pulsdatei serverseitig.** Schreiben, Adressieren, Aufräumen,
  Hook an den acht Schreibstellen, Prüfharnisch. Ergebnis: Die Datei entsteht
  und ist korrekt — **niemand liest sie**. Für sich allein völlig
  wirkungslos und damit risikolos.
- **Phase 2 — Taktgeber liest die Datei.** Schneller Zeitgeber, Herzschlag,
  Rückfall. Der Vertrag nach außen bleibt unverändert; die vier Abonnenten
  werden **nicht angefasst**. Hier liegt der gesamte Nutzen.
- **Phase 3 — Einstellung, Messung, Feinschliff.** Zweites Feld in
  `admin/settings.php`, Messung auf der KAS-Testdomain, Mindestabstand auf
  gesperrten Seiten neu bewerten, `docs/messung-pulsdatei.md`.
- **Optional, nur falls Phase 0 ein Managed-/Root-Paket ergibt: Phase 4 —
  gehaltene Verbindung** (`filemtime()`-Schleife ohne WP-Bootstrap gegen genau
  diese Datei, Haltezeit ~20 s, Latenz < 1 s). Ausdrücklich abschaltbar und
  erst nach Messung. Bei einem Webhosting-Tarif **entfällt diese Phase**.
- Je Phase ein Review-AP (`AP-<N>.rev`) und ein Dokumentations-AP
  (`AP-<N>.doc`); `reference_file_map.md` in jedem AP, das Dateien anlegt oder
  wesentlich ändert; ZIP-Bau samt Versions-Bump am Ende.
