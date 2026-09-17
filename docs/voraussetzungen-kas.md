# Voraussetzungen der Produktivumgebung

_Erhoben: 2026-09-17 · Gehört zu `PLAN-Schneller-Klassenpuls.md`, AP-0.2_

Quelle, soweit nicht anders vermerkt: ein Lauf von
`docs/pruefung-voraussetzungen.js` in der Browser-Konsole des
WordPress-Adminbereichs von `https://chemiefos.fos-meran.it`, angemeldet als
Administrator. Das Skript arbeitet ausschließlich lesend.

---

## Die sieben Fragen

### Frage 1 — Produkt/Tarif

**Antwort: OFFEN.** Nur im KAS sichtbar; das Skript kann sie nicht
beantworten.

**Was das blockiert:** ausschließlich die Entscheidung über Phase 4
(gehaltene Verbindung). Die Phasen 1–3 sind davon unabhängig.

**Ein Hinweis, der die Frage NICHT ersetzt:** Der Server meldet sich als
`nginx` (siehe „Server-Software" unten). Daraus lässt sich der Tarif nicht
ableiten — er sagt nur, welche Software die Anfrage beantwortet.

### Frage 2 — Cronjobs verfügbar?

**Antwort: JA**, laut Betreiber („können eingerichtet werden", 2026-09-17).
Anzahl und kleinstes Intervall nicht erhoben — für AP-1.7 genügt die
Verfügbarkeit, dort geht es um einen **täglichen** Aufräumdurchlauf.

_Quelle: Angabe des Betreibers, nicht nachgemessen._

### Frage 3 — PHP-Version

**Antwort: `8.2.33-nmm1`.**

_Quelle: WordPress-Systembericht (Website-Zustand → Bericht), Zeile
„PHP-Version"._

**Folge für den Plan:** Das Plugin bleibt auf **PHP 7.4** ausgelegt und wird
weiter mit `tools/check-php74.php` geprüft — die Zielversion ist die
konservativere Grenze, nicht die installierte. Praktisch relevant wird die
8.2 nur für Frage 4: Ab PHP 8 wirken `php_value`-Anweisungen in einer
`.htaccess` bei all-inkl nicht mehr.

### Frage 4 — `.user.ini` wirksam?

**Antwort: OFFEN.** Braucht Dateizugriff und rund fünf Minuten Wartezeit
(`user_ini.cache_ttl`, Vorgabe 300 s).

**Nur für Phase 4 nötig.** Ergibt Frage 1 einen Webhosting-Tarif, entfällt
sie ersatzlos.

### Frage 5 — Ist `wp-content/uploads/` beschreibbar?

**Antwort: JA, belegt.**

Alle vier vom Plugin angelegten Unterverzeichnisse antworten mit **HTTP 403**:

| Verzeichnis | Status |
|---|---|
| `container-block-designer/` | 403 |
| `cbd-styles/` | 403 |
| `cbd-icons/` | 403 |
| `cbd-temp-pdfs/` | 403 |

`403` heißt „vorhanden, Auflistung aus"; ein nicht vorhandenes Verzeichnis
ergäbe `404`. Dass **alle vier** existieren, beweist: PHP durfte dort
anlegen. Damit ist die Voraussetzung der Pulsdatei erfüllt.

_Quelle: Konsolenskript, Abschnitt C._

### Frage 6 — Wird eine `.json` aus `uploads` ausgeliefert?

**Antwort: KEINE SPERRE erkennbar.**

Sonde mit Kontrollgruppe, beide Dateien existieren nicht:

| Abfrage | Status |
|---|---|
| `uploads/cbd-sonde-v5ijsd2d.json` | **404** |
| `uploads/cbd-sonde-v5ijsd2d.txt` | **404** |

Gleiches Verhalten für beide Dateitypen — es gibt **keine** Regel, die
`.json` gezielt blockiert. Wäre eine vorhanden, stünde dort `403` gegen
`404`.

**Was damit NICHT belegt ist — und warum der Handtest trotzdem lohnt:** Die
Sonde zeigt, dass kein Filter auf den **Pfad** greift. Sie kann nicht zeigen,
dass eine **tatsächlich vorhandene** `.json` mit HTTP 200 und dem richtigen
`Content-Type` ausgeliefert wird. Lokal (Apache) war das der Fall
(`application/json`), auf `nginx` ist `.json` ebenfalls Standard — das Risiko
ist gering, aber ungemessen. Der Handtest aus Frage 6 der Klickliste
(`test-puls.json` anlegen, aufrufen, löschen) bleibt die letzte Instanz.

### Frage 7 — Verzeichnisauflistung in `uploads`?

**Antwort: NEIN — HTTP 403, bereits gesperrt.**

_Quelle: Konsolenskript, Abschnitt E._

**Das widerspricht dem lokalen Testserver und korrigiert eine frühere
Aussage.** Lokal (`fos.localhost:8080`, Apache ohne `Options -Indexes`)
lieferte `wp-content/uploads/` eine echte Dateiliste („Index of
/wp-content/uploads"). Daraus war notiert worden, die `.htaccess` aus AP-1.2
sei „NÖTIG, nicht bloß vorsorglich". **Für die Produktivinstallation stimmt
das nicht** — dort ist die Auflistung ohnehin aus. Siehe „Folgen für den
Plan", Punkt 3.

---

## Weitere erhobene Umgebungsdaten

| Angabe | Wert |
|---|---|
| Adresse | `https://chemiefos.fos-meran.it` |
| Verbindung | **HTTPS** |
| Server-Software | **nginx** (Antwortkopf `Server`) |
| WordPress-Version | 7.1 |
| `WP_DEBUG_LOG` | **Deaktiviert** ✓ |
| uploads-Adresse | `https://chemiefos.fos-meran.it/wp-content/uploads` |

**`WP_DEBUG_LOG` ist aus — richtig so.** Bekannter Bestandsbefund: Jede
Anfrage erzeugt sonst rund 34 Boot-Protokollzeilen; bei 25 Schülern im
Zehn-Sekunden-Takt wären das etwa 7.600 Zeilen je Minute.

> **Der erste Lauf des Skripts meldete hier fälschlich „EINGESCHALTET".**
> Ursache war ein Fehler im Skript, nicht in der Installation: Das Muster
> `/true|wahr|aktiv/i` passte auf das Teilstück „aktiv" in
> **De-aktiv-iert**. Behoben — das Muster prüft jetzt den ganzen Wert.

---

## Messung: statischer Abruf gegen REST-Route

| Ziel | Median aus 10 |
|---|---|
| `cbd/v1/klassenpuls` (Ablehnungspfad) | **113,9 ms** |
| statische Datei, revalidiert (`/wp-content/uploads/2026/09/AOs-3D-dots.png`) | **66,6 ms** |
| **Unterschied** | **47,3 ms** |

**Der Faktor 1,7 ist hier die falsche Zahl.** Beide Messungen tragen dieselbe
Netzlaufzeit zum Server in Deutschland, und die macht bei einem entfernten
Server den größten Teil **beider** Werte aus. Sie kürzt sich in der
**Differenz** weg, nicht im Verhältnis.

**Die belastbare Aussage lautet: rund 47 ms Server-Rechenzeit je Anfrage
weniger.** Da der statische Abruf serverseitig bei etwa 1 ms liegt, ist die
PHP-Zeit einer Pulsanfrage auf dieser Maschine grob **48 ms** — deutlich
besser als die 108 ms der lokalen Messung (`docs/messung-klassenpuls.md`),
was zum schnelleren Produktivserver passt.

**Hochgerechnet auf den realen Betriebsfall (25 Schüler), als Schätzung mit
offengelegten Annahmen:**

| | heute (10-s-Takt) | nach dem Umbau (2-s-Takt + 60-s-Herzschlag) |
|---|---|---|
| REST-Anfragen | 2,5/s | 0,4/s |
| statische Abrufe | 0 | 12,5/s |
| PHP-Zeit je Sekunde | ≈ **120 ms** | ≈ **20 ms** |
| belegte PHP-Arbeitsprozesse | je Anfrage einer | **für die statischen Abrufe keiner** |

Das ist rund ein Sechstel der PHP-Last bei fünffach besserer Reaktionszeit —
die Größenordnung aus Abschnitt 4 des Plans bestätigt sich, allerdings auf
Grundlage einer **Schätzung aus einer Differenz zweier Browser-Messungen**,
nicht einer Servermessung.

**Die belastbare Messung liefert erst AP-3.3** auf der Testdomain, mit
`curl_multi_*` **auf dem Server selbst** — dort fällt die Netzlaufzeit
vollständig weg, und die Sättigungskurve wird messbar.

---

## Folgen für den Plan

### 1. Pulsdatei tragfähig: **JA**

Fragen 5 und 6 sind beide positiv beantwortet. Das Verzeichnis ist
beschreibbar, und es gibt keine Regel gegen `.json`. **Phase 1 kann
beginnen.**

Verbleibendes Restrisiko: Der Handtest aus Frage 6 (eine *vorhandene* `.json`
abrufen) steht noch aus. Er ist billig und sollte vor Phase 2 nachgeholt
werden — schlägt er fehl, betrifft das nur den Client-Teil, nicht die bereits
gebaute Serverseite.

### 2. Phase 4 findet statt: **UNENTSCHIEDEN**

Frage 1 ist offen. **Die Phasen 1–3 hängen nicht daran** und können ohne
diese Antwort vollständig abgearbeitet werden; erst am Ende von Phase 3 wird
die Antwort gebraucht.

### 3. AP-1.2 muss die `.htaccess` neu bewerten — **`nginx` liest sie nicht**

Der Server meldet sich als **nginx**. Eine `.htaccess` ist eine
Apache-Einrichtung; nginx wertet sie **nicht** aus. Die in AP-1.2 geplante
Datei mit `Options -Indexes` wäre dort also wirkungslos.

**Das ist kein Problem, sondern eine Korrektur der Begründung:**

- Die Auflistung ist auf der Produktivinstallation **ohnehin aus** (Frage 7,
  HTTP 403). Der Schutz, den die `.htaccess` liefern sollte, besteht bereits.
- Auf dem lokalen Testserver (Apache) ist die Auflistung **an** — dort wirkt
  die Datei und ist nützlich.
- Eine von nginx ignorierte Datei kostet nichts.

**Anweisung für AP-1.2:** Die `.htaccess` **bleibt** im Umfang — aber als
zweite Absicherung für Apache-Umgebungen, nicht als tragender Schutz. Die
Begründung im Quelltextkommentar und in der Dokumentation ist entsprechend zu
formulieren. **Der Schutz der Pulsdateien darf sich nicht auf sie stützen**;
er ruht auf dem unerratbaren HMAC-Dateinamen und darauf, dass der Inhalt nur
Prüfsummen sind.

**Offene Folgefrage, in AP-1.rev zu prüfen:** Liegt hinter nginx noch ein
Apache (verbreitete Aufstellung bei Shared Hosting), greift die `.htaccess`
doch. Feststellbar auf der Testdomain (AP-0.3), indem dort eine `.htaccess`
mit einer wirksamen, harmlosen Anweisung hinterlegt und deren Wirkung geprüft
wird.

### 4. `WP_DEBUG_LOG` ist aus — kein Handlungsbedarf

Die im Plan als Risiko geführte Protokollflut tritt nicht ein.
