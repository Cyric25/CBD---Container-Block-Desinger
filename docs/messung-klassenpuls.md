# Lastmessung `cbd/v1/klassenpuls` (AP-1.7)

_Datum: 2026-08-30 · Testserver `http://fos.localhost:8080`_

## 0. Umgebung

- **Server-PHP** (Apache, tatsächlich für die Website zuständig):
  `C:\allinkl-testserver\php\8.3\php.exe` → **PHP 8.3.32** (NTS).
- **Werkzeug-PHP** (führt das Messskript aus, nicht die Website):
  PHP 8.5.1 (CLI, im `PATH`) mit aktivierter `curl`-Extension.
- Beide unterscheiden sich von der PHP-Zielumgebung der Produktion
  (7.4.33) — das ist unabhängig von diesem AP so, siehe Abschnitt 3 des
  Plans; `tools/check-php74.php` prüft die Kompatibilität statisch, unabhängig
  vom tatsächlich installierten Server-PHP.
- Klassensitzung ohne interaktiven Login erzeugt (Muster aus der
  Übergabenotiz von AP-1.5/AP-1.6): temporäres WordPress-Bootstrap-Skript im
  Web-Root (`ap17-setup.php`), das `cbd_classroom_enabled` prüft/setzt und
  einen Transient `cbd_classroom_<token>` mit einer echten Klasse aus
  `wp_cbd_classes` anlegt. Nach dem Auslesen von `class_id`, `token` und
  `page_id` wieder gelöscht — kein Testartefakt im Web-Root oder Repo
  zurückgeblieben. Verwendete Sitzung: `class_id=15` („5BT1"),
  `page_id=368`, Takt zum Messzeitpunkt `10` (Vorgabewert, unverändert seit
  AP-1.6).

## 1. Messwerkzeug

**Zwei im Vorlauf zu diesem Plan dokumentierte Fallen wurden bewusst
vermieden:**

- Kein `Measure-Command`/`Invoke-WebRequest` (wirft bei HTTP-Fehlerstatus
  eine Exception, die gemessene Zeit ist dann wertlos).
- Kein `xargs -P` mit separaten `curl`-Prozessen (der Windows-Prozessstart
  kostet selbst ~280 ms und überdeckt die Servermessung; Erkennungsmerkmal
  wäre ein bei steigender Parallelität **konstant bleibender** Median).

Stattdessen: ein eigenes PHP-Skript mit `curl_multi_*` im Scratchpad-Verzeichnis
(**nicht** im Repository) —
`lasttest.php` (Einzel- und Lastmessung) und `gleichschritt.php`
(Gleichschritt-Prüfung, Abschnitt 5). Echte Nebenläufigkeit in einem
Prozess, ein Verbindungsaufbau je Anfrage, keine Prozessstartkosten. Beide
Dateien leben ausschließlich im Scratchpad der Session und sind kein Teil
dieses Commits.

**Gegenprobe, dass kein Werkzeugfehler vorliegt:** Der Median steigt in der
Sättigungskurve (Abschnitt 3) von 106 ms (1 gleichzeitig) über 150 ms
(25 gleichzeitig) auf 270 ms (100 gleichzeitig) — ein eindeutig **steigender**
Median bei steigender Parallelität, das Gegenteil des Fehlerbilds aus der
`xargs -P`-Falle.

## 2. Einzelmessung und Vergleichsmessung

Zwölf Anfragen nacheinander über **dasselbe** `curl`-Handle (Keep-Alive),
gleiches Werkzeug für Route und Vergleichsseite:

| Ziel | Min | Median | Max | P95 | Antwortgröße |
|---|---|---|---|---|---|
| `cbd/v1/klassenpuls` (mit `page_id`, gültige Sitzung) | 93,6 ms | **107,9 ms** | 371,5 ms | 371,5 ms | **109 Byte** |
| gewöhnliche Inhaltsseite (`…/chemische-gleichgewichte/`, ohne Klassenmodus) | 352,7 ms | **401,0 ms** | 861,7 ms | 861,7 ms | ~262 KB |

Weitere Antwortgrößen der Route (einzeln gemessen, `curl -w '%{size_download}'`):
- Erfolg ohne `page_id` (nur `klasse`/`fragenwand`/`takt`): **63 Byte**
- Erfolg mit `page_id` (alle vier Signaturen + `takt`): **109 Byte**
- Ablehnung (`cbd_puls_not_available`, HTTP 404): **87 Byte**

Alle drei liegen deutlich unter den geforderten 300 Byte.

**Bewertung:** Der Puls ist rund **3,7-mal schneller** als der Aufruf einer
gewöhnlichen Seite derselben Installation und liefert rund **2400-mal**
weniger Daten (109 Byte gegenüber ~262.000 Byte). Ein Achtel der
Ausführungszeit einer normalen Seite bei einem Bruchteil der Datenmenge —
der Puls ist im Vergleich zu einem echten Seitenaufruf sehr billig.

## 3. Sättigungskurve (gültige Sitzung)

Fünf Parallelitätsstufen, je mindestens das Dreifache an Gesamtanfragen
(`total = max(30, 5 × Parallelität)`):

| Parallelität | Gesamt­anfragen | Durchsatz (Anfr./s) | Median | P95 | Max | Fehlschläge |
|---:|---:|---:|---:|---:|---:|---:|
| 1 | 30 | 8,1 | 105,9 ms | 162,9 ms | 380,1 ms | 0 |
| 10 | 50 | 35,3 | 135,5 ms | 457,4 ms | 1415,2 ms | 0 |
| **25** | **125** | **50,4** | 150,2 ms | 2456,3 ms | 2480,1 ms | 0 |
| 50 | 250 | 37,8 | 252,4 ms | 5616,7 ms | 6609,1 ms | 0 |
| 100 | 500 | 31,7 | 270,5 ms | 13922,3 ms | 15777,0 ms | 0 |

**Der Knick liegt bei Parallelität 25** (Durchsatz-Spitze 50,4 Anfr./s).
Danach **fällt** der Durchsatz — auf 37,8 Anfr./s bei 50 Gleichzeitigen und
31,7 Anfr./s bei 100 Gleichzeitigen — bei gleichzeitig deutlich wachsender
Schwanzlatenz (P95 springt von 2,5 s bei 25 auf 13,9 s bei 100). Das ist der
im Plan beschriebene Staukollaps: mehr gleichzeitige Anfragen verdrängen
sich gegenseitig um dieselbe begrenzte Zahl an PHP-Arbeitsprozessen des
Testservers, statt schneller beantwortet zu werden. **Auf keiner Stufe kam
es zu einem Fehlschlag** — alle 955 Anfragen dieser Tabelle kamen mit
HTTP 200 an, auch die mit über 15 Sekunden Wartezeit bei 100 Gleichzeitigen.

## 4. Sättigungskurve (ungültige Sitzung, zeichengleiche Ablehnung)

Dieselben fünf Stufen gegen einen offensichtlich ungültigen Token:

| Parallelität | Gesamt­anfragen | Durchsatz (Anfr./s) | Median | P95 | Max | Fehlschläge |
|---:|---:|---:|---:|---:|---:|---:|
| 1 | 30 | 8,0 | 110,0 ms | 171,3 ms | 402,3 ms | 0 |
| 10 | 50 | 47,7 | 169,9 ms | 397,6 ms | 511,0 ms | 0 |
| 25 | 125 | 50,0 | 266,5 ms | 2487,9 ms | 2501,1 ms | 0 |
| 50 | 250 | 37,8 | 266,6 ms | 5611,4 ms | 6607,3 ms | 0 |
| 100 | 500 | 31,3 | 283,0 ms | 14261,0 ms | 15984,5 ms | 0 |

Alle Antworten HTTP 404, alle 955 Anfragen ohne Fehlschlag. **Der Knick
liegt auch hier bei Parallelität 25**, mit nahezu identischen Werten wie bei
gültiger Sitzung.

**Unerwarteter, aber eindeutiger Befund — im Widerspruch zur ursprünglichen
Annahme im AP-Text:** Die Ablehnung ist **nicht spürbar billiger** als die
Erfolgsantwort (z. B. Median bei Parallelität 1: 110,0 ms ungültig gegenüber
105,9 ms gültig — kein relevanter Unterschied; bei höherer Parallelität
liegen beide Kurven praktisch übereinander). Erklärung: Die Kosten stecken
überwiegend im vollständigen WordPress-Bootstrap pro Anfrage (REST-API-Init,
Plugin- und Blockregistrierung — siehe Abschnitt 6), nicht in der
Sitzungsprüfung selbst. `CBD_Classroom_Gate::sitzung()` bricht zwar früh ab,
aber der teure Teil (WordPress laden) ist zu diesem Zeitpunkt bereits
geschehen. Das ändert nichts an der Sicherheitsbewertung (`nocache_headers()`
zuerst, zeichengleiche 404-Antwort für jeden Ablehnungsgrund bleiben
korrekt), relativiert aber die Erwartung, dass ungültige Anfragen den Server
spürbar weniger belasten als gültige.

## 5. Gleichschritt-Prüfung

### 5.1 Warum nicht mit echten Browser-Tabs

Zuerst wurde versucht, die Prüfung wie im Plan beschrieben mit mehreren
gleichzeitig geöffneten Browser-Tabs auf derselben Klassenseite
durchzuführen. Dabei zeigte sich eine Werkzeug-Einschränkung der in dieser
Umgebung verfügbaren Browser-Automatisierung: **`document.hidden` ist dort
für jeden Tab dauerhaft `true`** — auch für den gerade fokussierten
(`tabs_select`) einzigen offenen Tab. Das ist keine Eigenschaft eines
normalen Browsers, sondern eine Eigenheit dieser ferngesteuerten
Automatisierung, die dem Tab nie einen echten „sichtbar"-Zustand meldet.

`klassenpuls.js` pausiert bei `document.hidden === true` bewusst jede
Abfrage (`frageAb()` und `planeNaechsteAbfrage()` prüfen das explizit, siehe
`assets/js/klassenpuls.js`) — eine im Plan (Abschnitt 4) beabsichtigte
Ressourcenschonung, kein Fehler. Empirisch bestätigt: Nach `setzeSitzung()`
und `starte()` über die Konsole meldete `window.cbdKlassenpuls.laeuft()`
zwar `true`, aber es lief **keine einzige** automatische Anfrage — weder im
Netzwerk-Tab des Browsers noch im Server-Zugriffslog. Das gilt bereits für
einen einzelnen Tab, nicht erst für mehrere gleichzeitig geöffnete.

**Ergebnis: Die Gleichschritt-Prüfung mit echten Browser-Tabs war in dieser
Umgebung nicht durchführbar.** Das wird hier ausdrücklich festgehalten,
statt es zu verschweigen oder ein erfundenes Ergebnis auszugeben.

### 5.2 Ersatzmessung: Nachbildung des echten Ablaufplans

Ersatzweise wurde der **exakte Ablaufplan von `klassenpuls.js`** nachgebaut
(Quelltext gegen `assets/js/klassenpuls.js` verifiziert, nicht angenommen):

- `starte()` löst die **erste** Abfrage sofort aus, ohne auf eine Antwort zu
  warten.
- Jede weitere Abfrage wird **erst nach Eintreffen der vorherigen Antwort**
  eingeplant, exakt `takt` Sekunden später (`verarbeiteAntwort()` bzw.
  `behandleFehler()` rufen `planeNaechsteAbfrage()` — ein rekursives
  `setTimeout`, kein festes Raster unabhängig von der Antwortzeit).
- Kein Jitter ist implementiert (bestätigt: `assets/js/klassenpuls.js`
  enthält weder `Math.random()` noch einen sonstigen Streuungsmechanismus in
  der Ablaufplanung — das ist die im AP-Text erwähnte, absichtlich noch
  fehlende Entzerrung).

Das Skript `gleichschritt.php` bildet **fünf unabhängige „virtuelle
Schüler"** nach, die diesen Ablaufplan exakt befolgen und zum selben
Zeitpunkt starten (wie beim gemeinsamen Stundenbeginn). Jede Anfrage geht an
den echten Server unter derselben Klassensitzung
(`classroom=15&token=…&page_id=368`, Takt 10 s), zehn Minuten lang
(600 Sekunden), wie im Plan gefordert. Maßgeblich für die Auswertung ist
**nicht** die eigene Mitschrift des Skripts, sondern das echte
Server-Zugriffslog `C:\allinkl-testserver\logs\fos_access.log`.

**Einordnung:** Das ist kein Ersatz für einen Test mit echten, auf fünf
Schülergeräten laufenden Browsern — es ist eine Nachbildung des
client-seitigen Ablaufplans gegen den echten Server. Es prüft genau die
eine offene Frage aus Schritt 7 des AP-Texts: Bleiben ohne Jitter gemeinsam
gestartete Abfragezyklen über zehn Minuten hinweg eng beieinander (weil die
Serverantwortzeit bei geringer Last kaum streut), oder verteilen sie sich?
Ein zusätzlicher Vorteil dieser Nachbildung gegenüber mehreren Tabs in
**einem** Browserfenster: In einer echten Klasse ist der Tab jedes Schülers
auf seinem eigenen Gerät der sichtbare, aktive Tab — die
Sichtbarkeits-Pause aus Abschnitt 5.1 träfe dort ohnehin nicht zu. Fünf
unabhängige, nie pausierte Abfrageschleifen bilden diese reale Situation
also eher nach als fünf Tabs in einem einzigen, nie sichtbaren Fenster.

### 5.3 Ergebnis

Fünf virtuelle Schüler liefen 600 Sekunden (10 Minuten) lang, gestartet zum
selben Zeitpunkt. `gleichschritt.php` protokollierte selbst 290 Anfragen;
das echte Zugriffslog zeigt im selben Zeitfenster **291** Anfragen mit dem
Testtoken (die maßgebliche Quelle) — die Abweichung um eine Anfrage ist die
zu erwartende Rundungsdifferenz an den Fensterrändern, keine verlorene oder
zusätzliche Anfrage.

**Auswertung nach der im Plan vorgesehenen Methode** (Zeitstempel aus dem
Zugriffslog, gruppiert zu „gemeinsamen Abfragerunden" — eine neue Gruppe
beginnt, sobald die Lücke zur vorherigen Anfrage mehr als 5 Sekunden
beträgt; das trennt zuverlässig zwischen der engen Häufung **innerhalb**
einer Runde und dem ca. 10-Sekunden-Abstand **zwischen** zwei Runden):

| Kennzahl | Wert |
|---|---|
| Erkannte Abfragerunden | 58 |
| Runden mit genau 5 Anfragen (alle fünf „Schüler") | 57 von 58 |
| Runden mit 6 Anfragen (einmaliges Zusammenfallen zweier Zyklen) | 1 von 58 |
| Durchschnittlicher Abstand innerhalb einer Runde (Spanne Min–Max) | **0,59 s** |
| … in den ersten 20 Runden (Minute 0–3,5) | 0,65 s |
| … in den letzten 20 Runden (Minute 6,5–10) | **0,50 s** |
| Größte beobachtete Spanne innerhalb einer Runde | 3 s (nur die eine 6er-Runde) |
| Durchschnittlicher Abstand zwischen zwei Runden | 10,42 s (Min 8 s, Max 13 s) |

**Die Anfragen aller fünf „Schüler" landen in jeder Runde innerhalb von
rund einer halben bis einer Sekunde beieinander — bei einem Rundenabstand
von rund 10,4 Sekunden.** Das ist ein schmales Zeitband (rund 5–10 % der
Taktperiode), und es bleibt über die vollen zehn Minuten schmal — die
letzten 20 Runden sind sogar geringfügig enger gebündelt als die ersten 20
(0,50 s gegenüber 0,65 s), nicht weiter auseinandergezogen. Nach der im
AP-Text festgelegten Regel („Häufen sie sich in einem schmalen Zeitband,
das über die zehn Minuten schmal bleibt oder schmaler wird, liegt
Gleichschritt vor") ist das Ergebnis eindeutig: **Gleichschritt tritt auf.**

**Warum das so ist (technische Erklärung, siehe 5.2):** Weil
`planeNaechsteAbfrage()` die nächste Abfrage exakt `takt` Sekunden **nach
Eintreffen der vorherigen Antwort** einplant und die Antwortzeit auf diesem
kaum ausgelasteten Server sehr gleichmäßig ist (Median rund 100 ms,
Abschnitt 2), bleibt die Zykluslänge aller fünf „Schüler" über die gesamte
Messung praktisch identisch. Ohne eine Streuungsquelle (Jitter) gibt es
nichts, was sie auseinanderdriften lassen könnte — ein einmal
gleichzeitiger Start bleibt ein dauerhaft gleichzeitiger Start.

**Hinweis zur Einordnung:** Eine naive Auswertung „Anfragen je feststehendem
10-Sekunden-Fenster ab Messbeginn" wurde ebenfalls berechnet, aber bewusst
**nicht** als Kriterium verwendet — sie zeigt fast überall exakt 5 Anfragen
je Fenster, weil jeder der fünf Schüler ohnehin nur einmal pro ~10 Sekunden
anfragt; das unterscheidet nicht zwischen „eng gebündelt" und „gleichmäßig
über das Fenster verteilt". Der oben verwendete Cluster-Abstand (Spanne
**innerhalb** einer erkannten Runde) misst genau die Frage, um die es geht.

## 6. Kontrolle: `debug.log` und PHP-Fehler

`wp-content/debug.log` vor Beginn der Lastmessung: **185.930** Zeilen.
Nach Einzelmessung, beiden Sättigungskurven und der Gleichschritt-Messung:
**262.551** Zeilen.

Die Differenz besteht **ausschließlich** aus der immer schon vorhandenen,
routinemäßigen Info-Protokollierung, die bei **jeder** HTTP-Anfrage entsteht
(dieser Testserver führt für jede Anfrage einen vollständigen
WordPress-Bootstrap aus, inklusive Block- und Plugin-Registrierung; siehe
bereits die Übergabenotiz von AP-1.5 zum selben Verhalten): Zeilen wie
`[CBD Block Registration] Block type registered: …`,
`[CBD Main] Plugin initialization completed` und `Modular Blocks Plugin: …`.
Gezählt über die Zeile `[CBD Main] Plugin initialization completed` (einmal
je vollständigem Bootstrap, also einmal je HTTP-Anfrage): **2.251**
Anfragen insgesamt (Einzel-, Vergleichs-, beide Sättigungskurven- und die
Gleichschritt-Messung zusammen) mit je ca. 34 solcher Info-Zeilen pro
Bootstrap — das ergibt exakt die Größenordnung der beobachteten Differenz.

**Stichprobenartig durchsucht** (`grep -icE`, getrennt über beide neuen
Bereiche — vor und nach der Gleichschritt-Messung): **0** Treffer für
`Warning`, `Notice`, `Deprecated`, `Fatal error` oder `Parse error`, in
**beiden** Teilbereichen. Es sind also **keine neuen Fehler, Warnungen oder
Notices** durch die gesamte Lastmessung entstanden — nur die für dieses
Plugin bereits dokumentierte Boot-Protokollierung.

## 7. Vorbehalt zur Übertragbarkeit auf all-inkl

Der Testserver ist ein lokaler Windows-Rechner ohne Fremdlast und mit
warmem OPcache. Auf all-inkl (Shared Hosting) teilen sich deutlich weniger
PHP-Arbeitsprozesse den Server mit anderen Kunden, und die Maschine steht
nicht exklusiv zur Verfügung. **Die Form der Kurve** — Anstieg bis zu einem
Optimum, danach Staukollaps mit wachsender Schwanzlatenz statt
Fehlschlägen — überträgt sich voraussichtlich; **die absoluten Zahlen**
(Millisekunden, der Knick bei genau 25 Gleichzeitigen, Anfragen/s) **nicht**.
Auf all-inkl dürfte der Knick bei einer niedrigeren Parallelität liegen.
Das ändert an der Bewertung in Abschnitt 8 nichts, weil der reale
Betriebsfall (Abschnitt 8, erste Frage) selbst bei einem deutlich
niedrigeren Knick noch komfortabel darunterläge.

## 8. Bewertung und Empfehlung

**Frage 1 — Trägt der Takt von 10 Sekunden für zwei Klassen (5 Anfragen/s,
der reale Betriebsfall dieser Installation)?**
Ja, deutlich. Selbst der ungünstigste in dieser Messung beobachtete
**sustained** Durchsatz (31,3–31,7 Anfr./s bei 100 Gleichzeitigen, dem
Staukollaps-Bereich) liegt noch gut sechsmal über den benötigten
5 Anfragen/s. Die Einzelmessung (Median 108 ms) zeigt zusätzlich, dass eine
einzelne Anfrage rund 100-mal schneller beantwortet wird, als es der
10-Sekunden-Takt erfordern würde. Selbst eine dritte, schulweite Auslastung
(200 Schüler, 20 Anfragen/s) bliebe innerhalb der in dieser Messung
beobachteten Kapazität.

**Frage 2 — Wo liegt der Knick der Sättigungskurve, und wie viel Luft bleibt
bis dahin?**
Bei **25 gleichzeitigen** Anfragen (Durchsatz-Spitze ~50 Anfr./s, danach
fallend). Der reale Betriebsfall (5 Anfragen/s **verteilt über** eine
10-Sekunden-Periode, nicht 5 **gleichzeitige** Anfragen) liegt weit unter
dieser Schwelle. Auch der ungünstigste denkbare Fall – alle Schüler
**einer einzigen** Klasse (bis zu 25–30) laden im selben Sekundenbruchteil
gleichzeitig, etwa weil alle Tabs zu Unterrichtsbeginn synchron öffnen –
liegt ziemlich genau **am** Knick, nicht spürbar darüber; ein solcher Burst
würde nach den Zahlen aus Abschnitt 3 noch ohne Fehlschlag, mit spürbar,
aber nicht dramatisch erhöhter Wartezeit (P95 um 2,5 s statt ~150 ms)
beantwortet. Erst ein gleichzeitiger Burst von 50 oder mehr (z. B. zwei
Klassen, die exakt im selben Moment starten) würde in den Bereich fallen,
für den die Umplanungsnotiz im AP-Text laut Betreiberangabe ohnehin keine
praktische Grundlage sieht („Zwei Lehrpersonen markieren in der Praxis
nicht zeitgleich").

**Frage 3 — Tritt Gleichschritt auf?**
**Ja.** Fünf gleichzeitig gestartete Abfrageschleifen blieben über die
gesamten zehn Minuten der Messung (58 beobachtete Runden) durchgehend in
einem Zeitband von rund einer halben bis einer Sekunde gebündelt, bei einem
Rundenabstand von rund 10,4 Sekunden — und dieses Band wurde über die Zeit
**nicht** breiter (0,65 s in den ersten 20 Runden, 0,50 s in den letzten
20 Runden), siehe Abschnitt 5.3. Ursache ist die fehlende Streuung
(Jitter) in `assets/js/klassenpuls.js`: Die response-zeit-gekoppelte
Ablaufplanung (`planeNaechsteAbfrage()` nach jeder Antwort) hat bei einer so
gleichmäßigen Antwortzeit wie auf diesem kaum ausgelasteten Server keine
Quelle, die einen einmal gemeinsamen Start auseinanderziehen könnte. **Damit
wird `AP-1.fix1` (Jitter von ±25 % auf das Intervall) empfohlen** — die
Entscheidung darüber liegt beim Orchestrator, diese Messung setzt sie nicht
selbst um.

**Einordnung der Dringlichkeit:** Gleichschritt ist an sich kein
Sicherheitsproblem und im gemessenen Lastbereich (Abschnitt 3, 5
Gleichzeitige weit unterhalb des Knicks bei 25) auch keine akute
Kapazitätsgefahr — die Empfehlung stützt sich allein auf das im AP-Text
selbst formulierte Kriterium („gemessen statt vorsorglich behandelt"), nicht
auf einen beobachteten Fehlschlag. Sollte die Installation künftig auf mehr
gleichzeitige Klassen wachsen, vergrößert ein bestehender Gleichschritt aber
genau das Bündelungsrisiko, das die Sättigungskurve in Abschnitt 3 zeigt
(Durchsatzeinbruch ab 25–50 Gleichzeitigen) — ein guter Grund, die
Entzerrung schon jetzt nachzuziehen, auch wenn sie heute noch nicht
dringend ist.

**Empfehlung:** Der Takt von 10 Sekunden (Vorgabewert) ist für den
tatsächlichen Betrieb dieser Installation (ein bis drei Klassen) unbedenklich.
Die Einstellung `cbd_klassenpuls_takt` aus AP-1.6 bleibt die richtige
Notbremse für den unwahrscheinlichen Fall einer schulweiten Ausweitung.

**Zusätzlich empfohlen: `AP-1.fix1` — Jitter von ±25 % auf das Intervall in
`assets/js/klassenpuls.js`.** Begründung: Abschnitt 5 dieser Messung weist
nach, dass ohne Jitter ein einmal gemeinsamer Start (typischerweise der
gemeinsame Stundenbeginn) über mindestens zehn Minuten hinweg im
Gleichschritt bleibt, statt sich zu entzerren. Bei der aktuellen
Betriebsgröße (ein bis drei Klassen, Abschnitt „Umplanung" oben) ist das
unkritisch, weil selbst ein vollständig synchron abfragender Klassensatz
weit unter dem gemessenen Knick von 25 Gleichzeitigen bleibt. Der Aufwand
für den Jitter ist gering (eine Zeile in `aktuellesIntervallMs()`), der
Nutzen wächst mit jeder künftig hinzukommenden gleichzeitigen Klasse —
deshalb wird die Ergänzung empfohlen, obwohl sie nach heutigem Betrieb noch
nicht akut nötig ist. Die Umsetzung selbst ist **nicht** Teil dieses APs.
