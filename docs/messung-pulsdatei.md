# Messung: Pulsdatei gegen REST-Route auf der Produktivseite

**Vorhaben:** „Schneller Klassenpuls", `AP-3.3`
**Gemessen:** 2026-09-18, 15:17 und 16:48 Uhr
**Ziel:** `https://chemiefos.fos-meran.it` — die **Produktivinstallation**,
nicht der lokale Testserver
**Freigabe des Betreibers für dieses Zeitfenster:** liegt vor

> **Die eine Frage, die dieser Bericht beantwortet:** Ist ein statischer
> Dateiabruf auf **dieser** Maschine messbar billiger als ein Aufruf der
> REST-Route? Der ganze Entwurf des Vorhabens steht auf dieser Annahme. Der
> Vorgängerbericht `messung-klassenpuls.md` hält in seinem Abschnitt 7 selbst
> fest, dass er lokal lief und seine **absoluten Zahlen** sich nicht
> übertragen — nur die Kurvenform.

---

## 1. Umgebung

| | |
|---|---|
| Ziel | `chemiefos.fos-meran.it`, HTTPS, all-inkl „Privat Premium" (Webhosting) |
| Auslieferung | nginx vor Apache (ETag-Format und die 403-Seite verraten den Apache) |
| Messrechner | derselbe Windows-Arbeitsplatz wie beim Vorgängerbericht |
| Netzweg | über das Internet, **nicht** localhost — das ist der wichtigste Unterschied zum Vorgängerbericht |
| Plugin-Stand am Ziel | **ohne** den Pulsdatei-Teil dieses Vorhabens (siehe Abschnitt 6) |

---

## 2. Messwerkzeug

Ein eigenes PHP-Skript je Messung, beide im Scratchpad der Sitzung,
**nicht im Repository** (`mess_ap33_einzel.php`, `mess_ap33_kurve.php`).

**Zwei Messfallen des Vorgänger-Vorhabens sind bewusst vermieden:**

- **Kein `Measure-Command`/`Invoke-WebRequest`.** Die werfen bei einem
  HTTP-Fehlerstatus eine Ausnahme; die gemessene Zeit ist dann wertlos. Das
  ist hier besonders heikel, weil das erste Messziel planmäßig mit **404**
  antwortet.
- **Kein `xargs -P` mit getrennten `curl`-Prozessen.** Der Prozessstart
  kostet unter Windows rund 280 ms und überdeckt die Servermessung.
  Stattdessen `curl_multi_*`: echte Nebenläufigkeit in **einem** Prozess.

**Gemessen wurden je Anfrage zwei Werte:** die Gesamtzeit und die Zeit bis
zum ersten Byte. Bei 73-Byte-Antworten ist die Differenz Leitung, nicht
Maschine — und für die Frage nach der Serverlast zählt der zweite Wert.

### Eine Falle, die erst der zweite Lauf sichtbar gemacht hat

Der **erste** Kurvenlauf lieferte bei Parallelität 1 ein Maximum von
**571 ms** neben einem Median von 23,8 ms, und bei Stufe 10 sprang P95 auf
**542 ms**. Das sah nach einsetzender Sättigung aus. **Es war der
TLS-Verbindungsaufbau.** `curl_multi` hält einen gemeinsamen
Verbindungsvorrat; die ersten `parallel` Anfragen jeder Stufe bauen ihre
Verbindung erst auf — bei Stufe 10 also 10 von 50 Anfragen, was P95 genau
trifft.

Der zweite Lauf verwirft deshalb die ersten `parallel` Ergebnisse jeder
Stufe und startet auch die Durchsatzuhr erst danach. **Alle Zahlen in
diesem Bericht stammen aus dem zweiten Lauf.** Die Einzelmessung hatte
Aufwärmrunden von Anfang an; der Kurve fehlten sie.

> **Für künftige Messungen:** Ein Maximum, das ein Vielfaches des Medians
> beträgt und schon bei Parallelität 1 auftritt, ist fast nie Sättigung.
> Sättigung trifft den Median.

---

## 3. Einzelmessung

Drei Ziele, gleiches Werkzeug, je **12 Anfragen über dasselbe
`curl`-Handle** (Keep-Alive), 3 Aufwärmrunden verworfen. Alle Zeiten in
Millisekunden.

| Ziel | Status | Bytes | min | **Median** | P95 | max |
|---|---|---|---|---|---|---|
| REST-Route `cbd/v1/klassenpuls` | 404 | 87 | 58,2 | **64,3** | 76,1 | 76,5 |
| Pulsdatei, Erstabruf | 200 | 73 | 20,6 | **26,7** | 41,8 | 47,8 |
| Pulsdatei mit `If-Modified-Since` | 304 | 0 | 19,7 | **24,2** | 30,5 | 31,1 |

Bis zum ersten Byte:

| Ziel | min | **Median** | P95 | max |
|---|---|---|---|---|
| REST-Route | 58,0 | **63,9** | 75,8 | 76,2 |
| Pulsdatei (200) | 20,3 | **26,0** | 41,3 | 47,1 |
| Pulsdatei (304) | 19,6 | **24,0** | 30,3 | 30,9 |

**Der rohe Faktor beträgt 2,4 — und er untertreibt.** Die dritte Zeile ist
der Beleg: Ein 304-Abruf kostet den Server praktisch nichts und braucht
trotzdem 24 ms. Das ist der Netzweg. Zieht man ihn ab, bleibt als
Serveranteil:

| | Rechnung | Serveranteil (Median) |
|---|---|---|
| REST-Route | 64,3 − 24,2 | **≈ 40,1 ms** |
| Pulsdatei | 26,7 − 24,2 | **≈ 2,5 ms** |

**Jeder vermiedene Routenaufruf spart also rund 40 ms PHP-Zeit** auf einer
Maschine, die mit anderen Kunden geteilt wird. Das deckt sich mit der
unabhängigen Schätzung aus `AP-0.2` (`voraussetzungen-kas.md`: rund 47 ms),
die mit anderem Werkzeug entstand.

> **Korrigiert am 2026-09-20 (`AP-3.fix1`, Befund `B5` aus `AP-3.rev`).**
> Hier standen zuerst **40,8** und **4,6 ms** — Zahlen, die sich aus den
> Tabellen dieses Abschnitts nicht nachrechnen lassen. Der Routenwert lag im
> Rundungsbereich, der Dateiwert war um rund 84 % zu hoch. Jetzt steht die
> Rechnung in der Tabelle selbst, und zwar nach der Regel, die der Absatz
> darüber nennt: Median minus Netzweg aus der 304-Zeile.
>
> **Die Richtung des alten Fehlers war günstig** — ein zu hoch angesetzter
> statischer Serveranteil macht den Vergleich konservativer. Das Review hat
> mit eigenem Werkzeug (nacktes `curl`, eine Verbindung, 2026-09-19)
> **19,1 ms** als schnellsten statischen Abruf und daraus rund **2 ms**
> Serveranteil gemessen — dieselbe Größenordnung wie die 2,5 ms hier, nicht
> wie die alten 4,6 ms. **Der Faktor steigt dadurch von ≈ 9 auf ≈ 16**; die
> betrieblich tragende Größe, die Differenz von rund 40 ms, ändert sich nicht.

---

## 4. Sättigungskurve

Nur gegen die **Pulsdatei** in voller Breite. Die Route bekommt bewusst nur
einen zweiten, niedrigen Ankerpunkt — Begründung in Abschnitt 6.

### Pulsdatei (statisch, kein PHP)

| parallel | Anfragen | ok | Fehler | Dauer s | Anfr./s | min | **Median** | P95 | max |
|---|---|---|---|---|---|---|---|---|---|
| 1 | 30 | 30 | **0** | 0,67 | 44,9 | 17,4 | **22,2** | 26,8 | 27,8 |
| 10 | 50 | 50 | **0** | 0,14 | 364,0 | 17,0 | **21,8** | 26,3 | 27,2 |
| 25 | 125 | 125 | **0** | 0,16 | 766,9 | 16,5 | **22,1** | 42,3 | 56,0 |

### REST-Route, Ablehnungspfad

| parallel | Anfragen | ok | Fehler | Dauer s | Anfr./s | min | **Median** | P95 | max |
|---|---|---|---|---|---|---|---|---|---|
| 1 | 30 | 30 | **0** | 1,93 | 15,6 | 57,2 | **62,9** | 74,7 | 82,2 |
| 5 | 30 | 30 | **0** | 0,64 | 46,8 | 58,3 | **65,3** | 84,5 | 89,1 |

**Der Median der Pulsdatei bewegt sich über die drei Stufen nicht** — 22,2 /
21,8 / 22,1 ms. Der Durchsatz steigt dabei praktisch linear auf **767
Anfragen je Sekunde**. **0 Fehlschläge auf 265 Anfragen insgesamt.**

> **Wie die Spalte „Anfr./s" zu lesen ist** (nachgetragen am 2026-09-20,
> `AP-3.fix1`, Befund `B6` aus `AP-3.rev`): Sie rechnet mit der **vollen**
> Anfragezahl der Stufe, nicht mit der um die Aufwärmrunden verringerten
> (30 ÷ 0,67 = 44,8 · 50 ÷ 0,137 = 365 · 125 ÷ 0,163 = 767). Abschnitt 2
> sagt dagegen, die Durchsatzuhr starte erst nach den verworfenen ersten
> `parallel` Ergebnissen. **Beides zusammen geht nur auf eine von zwei
> Weisen, und welche es war, lässt sich nicht mehr entscheiden** — das
> Messskript liegt planmäßig nicht im Repository:
>
> - Entweder enthält der Zähler die Aufwärmrunden, die Uhr aber nicht —
>   dann ist der ausgewiesene Durchsatz **zu hoch**, bei Stufe 10 um bis zu
>   ein Fünftel.
> - Oder die Aufwärmrunden waren zusätzliche Anfragen — dann stimmt der
>   Durchsatz, und die Statuscode-Bilanz „200 × 205, 404 × 60" nennt nur
>   die **gewerteten** Anfragen; an die Maschine gingen entsprechend mehr.
>
> Auf die Bewertung hat es in keiner der beiden Lesarten Einfluss: Selbst
> die ungünstige („zu hoch") lässt den Drei-Klassen-Fall bei rund 6 % statt
> 5 % der gemessenen Rate liegen.

**Wo der Knick liegt, sagt diese Messung nicht — und das ist kein Versehen.**
Bei Parallelität 25 ist der Server erkennbar noch nicht die Grenze: 25
gleichzeitige Anfragen bei 22 ms Antwortzeit ergeben rechnerisch rund 1100
Anfragen/s, gemessen sind 767 — die Messung ist **latenzgebunden, nicht
servergebunden**. Die Obergrenze 25 ist eine Auflage des Betreibers (siehe
Abschnitt 6), keine gemessene Grenze.

Die Kurve der REST-Route aus `messung-klassenpuls.md` (Knick bei 25
gleichzeitigen Anfragen, Spitze rund 50 Anfr./s) steht **ausdrücklich nicht
daneben als Vergleich**: Sie wurde **lokal** gemessen, auf einem Rechner
ohne Fremdlast und mit warmem OPcache. Vergleichbar ist allenfalls die
Form, nicht der Wert.

---

## 5. Betriebsfallrechnung

Serveranteil je Anfrage aus Abschnitt 3: Route ≈ 40,1 ms, Pulsdatei ≈ 2,5 ms
(davon 0 ms PHP). **Die Zeile „PHP-Zeit/s" hängt allein am Routenwert** —
die statischen Abrufe kosten kein PHP, ihr Serveranteil geht in diese
Rechnung gar nicht ein.

### Eine Klasse, 25 Schüler

| | vorher (nur Route, Takt 10 s) | nachher (Datei 2 s + Herzschlag 60 s) |
|---|---|---|
| statische Abrufe/s | – | 12,5 |
| REST-Anfragen/s | **2,5** | **0,42** |
| PHP-Zeit/s | ≈ **100 ms** | ≈ **17 ms** |
| Reaktionszeit auf eine Freigabe | 5–8 s | **1,2–3,6 s** |

### Drei Klassen, 75 Schüler

| | vorher | nachher |
|---|---|---|
| statische Abrufe/s | – | 37,5 |
| REST-Anfragen/s | **7,5** | **1,25** |
| PHP-Zeit/s | ≈ **301 ms** | ≈ **50 ms** |

**Die PHP-Last sinkt auf rund ein Sechstel, während die Reaktionszeit sich
verfünffacht.** Die 37,5 statischen Abrufe/s im Drei-Klassen-Fall sind
**5 %** dessen, was die Messung ohne erkennbare Sättigung geliefert hat.

---

## 6. Vorbehalte

Sie stehen hier vollständig, weil ein Messbericht ohne sie mehr behauptet,
als er zeigt.

1. **Gemessen wurde der Ablehnungspfad der Route, nicht der Erfolgspfad.**
   Der Pulsdatei-Teil dieses Vorhabens läuft auf der Produktivseite noch
   nicht — nachgewiesen: `uploads/container-block-designer/klassenpuls/`
   antwortet mit 404, während `uploads/container-block-designer/` und
   `uploads/cbd-icons/` mit 403 antworten (Gegenprobe mit einem erfundenen
   Verzeichnisnamen: ebenfalls 404). Es gibt dort also keine echte
   Pulsdatei und keine gültige Sitzung, ohne eine Testklasse anzulegen.
   **Die Richtung des Fehlers ist günstig:** Der Erfolgspfad macht
   zusätzlich die Transient-Prüfung und drei bis vier SQL-Aggregate, ist
   also **teurer**. Die gemessene Ersparnis von 40 ms ist damit eine
   **Untergrenze**. Dass Ablehnung und Erfolg annähernd gleich teuer sind,
   hat `messung-klassenpuls.md`, Abschnitt 4, gezeigt — **lokal**, nicht
   hier.
2. **Statt der Pulsdatei diente eine von Hand angelegte Probedatei**
   (`uploads/nw1-probe.json`, 73 Byte, aus `NW-1`). Sie ist in Größe und
   Ablageort das, was das Plugin später selbst schreibt. **Entfernt am
   2026-09-20 durch den Betreiber, und diesmal nachgewiesen statt
   behauptet:** Der Abruf antwortet mit **HTTP 404**, während
   `wp-includes/js/jquery/jquery.min.js` im selben Lauf mit **200** und
   `application/javascript` antwortet — die 404 ist also die Abwesenheit
   der Datei, keine allgemeine Sperre. Gegenprobe mit einem erfundenen
   Namen: ebenfalls 404.

   > **Hier stand bis zum 2026-09-20 „Nach der Messung entfernt."** — und
   > das war zu diesem Zeitpunkt falsch: Das Review `AP-3.rev` hat die
   > Datei am 2026-09-19 um 08:47 Uhr noch mit HTTP 200 und demselben
   > `Last-Modified` auf die Sekunde abgerufen (Befund `B1`, mittel). Drei
   > Dokumente behaupteten die Entfernung, keines hatte sie geprüft.
   > Korrigiert in `AP-3.fix1`.
3. **Die Maschine wird mit anderen Kunden geteilt.** Jede Zahl ist eine
   Momentaufnahme eines Freitagnachmittags. Fremdlast ist weder bekannt
   noch kontrollierbar.
4. **Die Obergrenze 25 ist gesetzt, nicht gemessen.** Die ursprünglich
   geplanten Stufen 50 und 100 entfallen auf Entscheidung des Betreibers:
   Sie waren dazu da, den Staukollaps zu zeigen — also den Server
   absichtlich in die Überlast zu treiben, was auf Shared Hosting auch die
   anderen Kunden träfe. **Dadurch bleibt unbelegt, wo der Knick liegt.**
   Für die Frage „trägt der Entwurf?" genügt das; für eine spätere
   schulweite Ausweitung wäre es zu wenig. Das Akzeptanzkriterium des
   Arbeitspakets nennt noch „alle fünf Parallelitätsstufen" — es steht im
   Widerspruch zum Kasten desselben Arbeitspakets, und der Kasten ist die
   jüngere Entscheidung.
5. **Die schwere Parallelität lief nur gegen die statische Datei.** Die
   Route bekam Stufe 1 und 5, mehr nicht: Im Betrieb sieht sie 0,42
   Anfragen/s, und jeder Aufruf bindet einen PHP-Arbeitsprozess. Eine Kurve
   bis 25 hätte eine Belastung erzeugt, die im Betrieb nie auftritt.
6. **Der Fehlerlog-Vergleich aus Schritt 5 des Arbeitspakets ist auf
   diesem Ziel nicht durchführbar** — und das ist kein Versäumnis, sondern
   eine Eigenschaft der Umgebung: Es gibt keinen Dateizugriff auf die
   Produktivinstallation, und `WP_DEBUG_LOG` ist dort **aus** und muss es
   bleiben (rund 34 Boot-Protokollzeilen je Anfrage, siehe
   `voraussetzungen-kas.md`). Ein `debug.log`, das man vergleichen könnte,
   existiert also gar nicht. **Ersatzweise belegt: 0 Fehlschläge und
   ausschließlich erwartete Statuscodes** (200 × 205, 404 × 60) über alle
   Stufen.
7. **Ein Ergebnis, das der Erwartung widerspricht, wäre hier berichtet
   worden.** Der Entwurf stünde in Frage, wenn der statische Abruf nicht
   spürbar billiger wäre. Er ist es.

---

## 7. Bewertung

### a) Ist der statische Abruf messbar billiger als die REST-Route? Um welchen Faktor?

**Ja.** Roh über die Gesamtzeit: **Faktor 2,4** (64,3 gegen 26,7 ms Median).
Das ist die vorsichtige Lesart, und sie untertreibt, weil bei 73 Byte rund
22 ms reiner Netzweg sind. Nach Abzug dieses Bodens: **rund 40,1 gegen
2,5 ms Serveranteil, Faktor ≈ 16** (korrigiert, siehe den Kasten in
Abschnitt 3). Die betrieblich entscheidende Größe ist die Differenz:
**jeder vermiedene Routenaufruf spart rund 40 ms PHP-Zeit.**

### b) Trägt der Vorgabewert von 2 Sekunden den realen Betriebsfall auf dieser Maschine?

**Ja, mit großem Abstand.** Eine Klasse erzeugt 12,5 statische Abrufe/s,
drei Klassen 37,5. Gemessen wurden **767 Anfragen/s ohne erkennbare
Sättigung und ohne einen einzigen Fehlschlag**; der Median blieb dabei von
Parallelität 1 bis 25 unverändert. Der Drei-Klassen-Fall liegt bei **5 %**
der gemessenen Rate. Gleichzeitig sinkt die PHP-Last gegenüber dem Zustand
vor dem Vorhaben auf rund ein Sechstel.

### c) Wo liegt der Knick der Sättigungskurve, und wie weit liegt der reale Betriebsfall darunter?

**Der Knick wurde nicht erreicht und ist damit unbekannt** — siehe Vorbehalt
4. Belegt ist die andere Hälfte der Frage: Bei 25 gleichzeitigen Anfragen
ist der Server noch nicht der Engpass (die Messung ist latenzgebunden), der
Median steht unverändert bei 22 ms, und der reale Betriebsfall liegt
mindestens **eine Größenordnung** darunter. Für die Entscheidung „trägt der
Entwurf?" genügt das; wer die Live-Aktualisierung später schulweit
ausrollen will, braucht eine eigene Messung.

---

## 8. Was aus dieser Messung noch offen ist

- **Der Rauchtest nach der Messung** (Klassenseite aufrufen,
  Live-Aktualisierung prüft sich selbst, Konsole ohne Fehler) konnte auf der
  Produktivseite nicht laufen: Sie ist passwortgeschützt, und eine
  Klassensitzung hätte eine Testklasse erfordert — ausdrücklich unerwünscht.
  Auf dem lokalen Testserver ist derselbe Nachweis in `AP-3.2` erbracht.
- **Der Erfolgspfad der Route** bleibt auf dieser Maschine ungemessen. Er
  lässt sich nach dem Ausrollen ohne Zusatzaufwand nachholen — dann
  existiert eine echte Pulsdatei, und die Messung braucht keine Testklasse
  mehr, nur eine laufende Stunde.
