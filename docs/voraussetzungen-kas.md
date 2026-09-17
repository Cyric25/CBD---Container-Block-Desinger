# Voraussetzungen der Produktivumgebung

_Erhoben: 2026-09-17 · Vervollständigt: 2026-09-17 (Frage 1 beantwortet) ·
Nachgezogen: 2026-09-17 nach `AP-0.rev` (Befunde M1, G7–G9) ·
Gehört zu `PLAN-Schneller-Klassenpuls.md`, AP-0.2_

> **Alle sieben Fragen sind beantwortet oder gegenstandslos.** Kurzfassung:
> Die Pulsdatei trägt, die Phasen 1–3 laufen — und **Phase 4 entfällt**, weil
> der Tarif Webhosting ist und keinen eigenen Server umfasst.

Quelle, soweit nicht anders vermerkt: ein Lauf von
`docs/pruefung-voraussetzungen.js` in der Browser-Konsole des
WordPress-Adminbereichs von `https://chemiefos.fos-meran.it`, angemeldet als
Administrator. Das Skript arbeitet ausschließlich lesend.

---

## Die sieben Fragen

### Frage 1 — Produkt/Tarif

**Antwort: „Privat Premium“ — ein WEBHOSTING-TARIF, kein eigener Server.**

_Quelle: Angabe der Hosting-Administration über den Betreiber, 2026-09-17._

**Damit ist die wichtigste Weiche gestellt: PHASE 4 ENTFÄLLT VOLLSTÄNDIG.**
Ein Webhosting-Tarif ist Shared Hosting — eine gehaltene Verbindung belegt
dort je Schüler einen PHP-Arbeitsprozess, und selbstdefinierte WebSockets
sind laut all-inkl-Support im Shared Hosting „kaum umsetzbar und nicht
empfehlenswert“. Die Entscheidung aus Abschnitt 3.1 der Erweiterungsanalyse
bleibt damit ohne Einschränkung gültig.

**Zur Namensform:** Die öffentliche Tarifliste von all-inkl führt `Privat`,
`PrivatPlus`, `Premium` und `Business`. Die gemeldete Bezeichnung wurde hier
**wörtlich** übernommen, statt sie auf einen der vier Namen zurechtzubiegen.
Für den Plan ist ohnehin nur die Einordnung entscheidend, und die ist
eindeutig: Webhosting, nicht Managed und nicht Root. Sollte je eine
Unterscheidung zwischen den Webhosting-Stufen nötig werden, ist die Angabe im
KAS nachzuschlagen.

**Konsistent mit Frage 2:** Cronjobs sind laut Betreiber verfügbar; enthalten
sind sie ab der Stufe `PrivatPlus`. Das passt zu einem Tarif oberhalb von
`Privat`.

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

### Frage 4 — Wird eine `.user.ini` ausgewertet?

**Antwort: ENTFÄLLT.** Frage 1 hat einen Webhosting-Tarif ergeben, damit
findet Phase 4 nicht statt und diese Frage ist gegenstandslos.

Sie wäre ausschließlich für die gehaltene Verbindung nötig gewesen, wo die
maximale Laufzeit hätte erhöht werden müssen. **Die Prüfung ist nicht mehr
durchzuführen** — sie kostet fünf Minuten Wartezeit und legt vorübergehend
eine `phpinfo()`-Datei offen, beides ohne jeden Gegenwert.

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

_Quelle: Konsolenskript, Abschnitt D._

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

**Das weicht vom lokalen Testserver ab.** Dort (`fos.localhost:8080`, Apache
ohne `Options -Indexes`) liefert `wp-content/uploads/` eine echte Dateiliste
(„Index of /wp-content/uploads“). Produktiv ist die Auflistung also schon
heute aus — die `.htaccess` aus AP-1.2 ist dort eine **zweite** Absicherung,
auf dem Testserver dagegen die einzige. Wirksam ist sie in beiden Umgebungen
(siehe „Wird die `.htaccess` gelesen?“ unten).

### Zusatzfrage — Wird die `.htaccess` gelesen?

**Antwort: JA.** Auf der Produktivinstallation sind unter anderem
IP-Beschränkungen über `.htaccess` umgesetzt; ohne deren Auswertung wären
sie kaum realisierbar.

_Quelle: Auskunft der Hosting-Administration über den Betreiber, 2026-09-17._

**Damit ist eine frühere Schlussfolgerung von mir widerlegt, und das ist
wichtig genug, es hier festzuhalten:** Aus dem Antwortkopf `Server: nginx`
hatte ich gefolgert, die `.htaccess` sei auf der Produktivinstallation
wirkungslos — nginx wertet sie nämlich nicht aus. Diese Aussage stand
bereits im Docblock von `verzeichnis_sicherstellen()`, in dieser Datei und
im Plan. **Sie war falsch.** Hinter nginx arbeitet ein Apache, und der liest
die Datei; der Antwortkopf nennt nur die äußerste Schicht.

**Lehre für künftige Befunde:** Ein `Server:`-Kopf beschreibt, wer die
Anfrage **entgegennimmt**, nicht, wer sie **verarbeitet**. Bei Shared
Hosting ist nginx vor Apache die verbreitete Aufstellung. Wer daraus auf die
Verarbeitungskette schließt, rät — geprüft hätte das nur ein echter Test
mit einer wirksamen `.htaccess`-Anweisung.

**Folge für AP-1.2:** Die `Options -Indexes`-Datei ist wirksam, nicht inert.
Sie bleibt trotzdem **nicht** der tragende Schutz der Pulsdateien — der ruht
unverändert auf dem unerratbaren HMAC-Anteil im Dateinamen und darauf, dass
der Inhalt ausschließlich Prüfsummen sind. Geändert hat sich die
Begründung, nicht der Code.

---

## Weitere erhobene Umgebungsdaten

| Angabe | Wert |
|---|---|
| Adresse | `https://chemiefos.fos-meran.it` |
| Verbindung | **HTTPS** |
| Server-Software | **nginx** (Antwortkopf `Server`) — dahinter jedoch ein Apache, siehe Zusatzfrage |
| `.htaccess` wird gelesen | **ja** (Auskunft der Hosting-Administration; dort sind IP-Beschränkungen umgesetzt) |
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

**Die belastbare Messung liefert erst AP-3.3**, mit `curl_multi_*` **auf
dem Server selbst** — dort fällt die Netzlaufzeit vollständig weg.
**Nachtrag 2026-09-17:** Die ursprünglich dafür vorgesehene Testdomain
(`AP-0.3`) ist auf Entscheidung des Betreibers entfallen; gemessen wird
auf der Produktivseite, außerhalb der Unterrichtszeit und mit einer
Parallelität von höchstens 25. **Der Knick der Sättigungskurve bleibt
damit unbelegt** — die Messung zeigt, dass der Betriebsfall weit
unterhalb jeder Grenze liegt, nicht wo die Grenze ist.

---

## Anmerkung zu den Handtests

**Es wurde nie eine Testdatei angelegt** — weder die `phpinfo-test.php`
aus Frage 4 (die Frage ist entfallen) noch die `test-puls.json` aus
Frage 6 (der Handtest steht noch aus, siehe `NW-1` im Plan). Das ist die
bessere Lage als geplant: Insbesondere war die `phpinfo()`-Datei, die
Serverinterna preisgibt, zu keinem Zeitpunkt erreichbar. Das
Akzeptanzkriterium „beide Testdateien sind nachweislich wieder
gelöscht“ ist damit leer erfüllt — hier festgehalten, damit ein
späterer Leser kein abgehaktes Kriterium ohne Beleg vorfindet.

## Folgen für den Plan

### 1. Pulsdatei tragfähig: **JA**

Fragen 5 und 6 sind beide positiv beantwortet. Das Verzeichnis ist
beschreibbar, und es gibt keine Regel gegen `.json`. **Phase 1 kann
beginnen.**

Verbleibendes Restrisiko: Der Handtest aus Frage 6 (eine *vorhandene* `.json`
abrufen) steht noch aus. Er ist billig und sollte vor Phase 2 nachgeholt
werden — schlägt er fehl, betrifft das nur den Client-Teil, nicht die bereits
gebaute Serverseite.

### 2. Phase 4 findet statt: **NEIN** — entschieden am 2026-09-17

Der Tarif ist Webhosting, also Shared Hosting. Eine gehaltene Verbindung
belegt dort je Schüler einen PHP-Arbeitsprozess; die Zahl gleichzeitiger
Prozesse ist begrenzt und nicht öffentlich dokumentiert. **Phase 4 entfällt
ersatzlos**, und mit ihr Frage 4 dieser Liste.

Das Vorhaben endet damit planmäßig mit `AP-3.doc`. Die erreichte Latenz von
2–3 Sekunden ist das Ergebnis — ausdrücklich kein Kompromiss, sondern der
Wert, den der Betreiber in der Planungsphase als Ziel gewählt hat.

**Diese Entscheidung gilt, solange der Tarif derselbe bleibt.** Wechselte die
Installation je auf einen Managed- oder Root-Server, wäre Phase 4 erneut zu
erwägen — aber auch dann erst, wenn eine Messung zeigt, dass 2–3 Sekunden im
Unterricht nicht genügen.

### 3. AP-1.2: Die `.htaccess` ist wirksam — Korrektur einer Korrektur

**Stand 2026-09-17, nach Rückmeldung der Hosting-Administration.** Hier stand
zuvor, nginx lese keine `.htaccess` und die Datei aus AP-1.2 sei auf der
Produktivinstallation wirkungslos. **Das war falsch** (siehe Zusatzfrage
oben): Sie wird gelesen, hinter nginx arbeitet ein Apache.

Was daraus folgt — und was ausdrücklich **nicht**:

- Die in AP-1.2 geschriebene `.htaccess` mit `Options -Indexes` **wirkt**.
- Sie ist produktiv trotzdem eine **zweite** Absicherung, weil die
  Verzeichnisauflistung dort ohnehin aus ist (Frage 7). Auf dem lokalen
  Testserver ist sie die einzige.
- **Der Schutz der Pulsdateien ruht weiterhin NICHT auf ihr**, sondern auf
  dem unerratbaren HMAC-Anteil im Dateinamen und darauf, dass der Inhalt
  ausschließlich Prüfsummen sind. Dieser Satz galt vorher und gilt
  unverändert — er hängt nicht an der Frage, ob die Datei gelesen wird.
- **Am Code ändert sich nichts.** Korrigiert wurde ausschließlich die
  Begründung — im Docblock von `verzeichnis_sicherstellen()`, in dieser
  Datei und in `reference_file_map.md` am 2026-09-17, **im Plan erst am
  selben Tag nach Befund M1 aus `AP-0.rev`**: Dort stand die widerlegte
  Aussage noch, während diese Zeile bereits behauptete, sie sei korrigiert.
  Eine Behauptung über eine erfolgte Korrektur, die nicht erfolgt ist, ist
  schlimmer als die stehen gebliebene Stelle — sie verhindert das
  Nachsehen.

### 4. `WP_DEBUG_LOG` ist aus — kein Handlungsbedarf

Die im Plan als Risiko geführte Protokollflut tritt nicht ein.
