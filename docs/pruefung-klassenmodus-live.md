# Prüfbericht: Sicherheit und Regression der serverseitigen Reduktion

_Arbeitspaket AP-3.3 aus `PLAN-Klassenmodus-Live.md` · Phase 3 „Gesperrte
Seiten live" · Durchgeführt am 2026-08-31 · Branch
`phase-3-gesperrte-seiten` (Spitze `0ca8fc0`)_

Dieses Arbeitspaket hat **keine Produktivdatei verändert.** Es führt den
Nachweis, dass Phase 3 die serverseitige Reduktion nicht bewegt hat und dass
der neue Puls-Endpunkt sie nicht umgeht.

---

## Umgebung

| | |
|---|---|
| Testserver | `http://fos.localhost:8080` (lokaler all-inkl-Testserver) |
| WordPress | `C:\allinkl-testserver\www\htdocs\w0000001\fos\` |
| Plugin auf dem Server | `…\wp-content\plugins\container-block-designer\` (Kopie, keine Verknüpfung) |
| PHP | 8.3.32 (Apache-SAPI und CLI mit `-c C:\allinkl-testserver\conf\php.ini`) |
| `CBD_VERSION` | `3.1.117`, unverändert — **nicht** zum Testen hochgesetzt |
| Fehlerlog | `…\fos\wp-content\debug.log` |
| Rückkehrpunkt vor dem Vorhaben | Commit `6b04708` auf `main` |

**Hinweis zum CLI-Bootstrap.** Die im Projekt übliche PHP-CLI (`php` im
Pfad, 8.5.1) kann WordPress **nicht** laden: Ihr fehlt `mysqli`, WordPress
bricht mit „Anforderungen sind nicht erfüllt" ab. Funktionierendes Muster
für Folge-APs:

```
"C:/allinkl-testserver/php/8.3/php.exe" -c "C:/allinkl-testserver/conf/php.ini" skript.php
```

### Testdaten (angelegt und nach der Prüfung restlos entfernt)

| | |
|---|---|
| Gesperrte Seite | 3003 „Polysaccharide", 22 Container, vorher 0 Zeichnungsdatensätze |
| Sperre | `_simple_clean_nur_lehrpersonen = '1'` (vorher nicht gesetzt) |
| Klasse A | id 29 „AP-3.3 Klasse A", **2** freigegebene Container |
| Klasse B | id 30 „AP-3.3 Klasse B", **1** freigegebener Container |
| Sitzungen | je ein Transient `cbd_classroom_<token>`, 64 Zeichen, wie `ajax_student_auth()` sie erzeugt |

Vor dem Anlegen wurden Option, Seiten-Meta, alle Zeichnungsdatensätze der
Seite und alle vorhandenen Sitzungs-Transients in eine Sicherungsdatei
geschrieben. Zum Zeitpunkt der Prüfung existierte **keine** gesperrte Seite
und **keine** aktive Klassensitzung auf dem Testserver — der Befund B8 aus
`AP-1.rev` (stehengebliebene gültige Testsitzung) ist damit erledigt.

---

## Strang 1 — Byteidentität der reduzierten Serverausgabe

**Frage:** Liefert der Server einer Schülerin auf einer gesperrten Seite
heute exakt dasselbe HTML wie vor Beginn des Vorhabens?

### 1a — Der Quelltext der Reduktion

| Prüfung | Erwartet | Tatsächlich |
|---|---|---|
| `git diff 6b04708 -- includes/class-cbd-classroom-gate.php` | leer | **leer** |
| Blob-Hash bei `6b04708` | — | `d3f5900025de69cd850f56e2837599188e867cac` |
| Blob-Hash bei `main` | gleich | `d3f5900025de69cd850f56e2837599188e867cac` |
| Blob-Hash bei `HEAD` (`0ca8fc0`) | gleich | `d3f5900025de69cd850f56e2837599188e867cac` |
| Serverkopie = Arbeitsbaum | gleich | SHA-256 beide `5df3d12c7283…` |

Die Datei ist über alle drei Commits hinweg **derselbe Blob** und liegt
unverändert auf dem Testserver.

> **Methodische Warnung für Folge-APs.** Ein roher `sha256sum` des
> Arbeitsbaums stimmt **nicht** mit dem von `git show <commit>:<datei>`
> überein: `core.autocrlf` steht auf `true`, der Arbeitsbaum trägt CRLF, der
> Objektspeicher LF. `git show … | sed 's/$/\r/' | sha256sum` reproduziert
> den Arbeitsbaum-Hash exakt. Wer das übersieht, meldet eine Abweichung, die
> keine ist. Vergleichbar werden Fassungen nur über `git rev-parse
> <commit>:<datei>` (Blob-Hash) oder `git diff`.

### 1b — Vollständige Änderungsmenge seit `6b04708`

Zwölf Dateien, geprüft auf Berührung des Reduktionspfads:

| Datei | Art der Änderung | Berührt Reduktion? |
|---|---|---|
| `includes/class-cbd-classroom-gate.php` | **keine** | — |
| `includes/class-cbd-classroom.php` | +38, rein additiv: zwei `wp_enqueue_script`-Blöcke, beide hinter `CBD_Klassenpuls::takt() > 0` | nein, `behandelte_container()` unangetastet |
| `container-block-designer.php` | +12: ein `require_once`, ein `CBD_Klassenpuls::init()` | nein |
| `includes/functions.php` | +35: `cbd_sanitize_klassenpuls_takt()` | nein |
| `admin/settings.php` | +20: Eingabefeld für den Takt | nein |
| `assets/js/classroom-page-filter.js`, `assets/css/classroom-frontend.css` | geändert | nein — Handles, URLs und `?ver=` unverändert, also identisches Markup |
| `assets/js/klassenpuls.js`, `includes/class-cbd-klassenpuls.php`, `tools/test-klassenpuls.php`, `docs/messung-klassenpuls.md`, `CLAUDE.md`, `reference_file_map.md` | neu | nein |

### 1c — Rauschpegel des Bytevergleichs

Zweimal derselbe Abruf bei unverändertem Code, innerhalb einer Sekunde:
**108 391 / 108 391 Byte, `diff` leer.** Der Bytevergleich ist damit
aussagekräftig; es gibt keine wechselnden Nonces oder Zeitstempel im
Seitenrumpf.

**Eine Ausnahme, die normalisiert werden muss** — siehe Befund F1: Ein auf
dem Testserver stehengebliebenes mu-plugin hängt an jede Plugin-Ressource
`&cbdap31=<Unixzeit>`. Innerhalb derselben Sekunde ist das unsichtbar, über
Sekundengrenzen hinweg erzeugt es Scheinunterschiede in rund 20 Zeilen. Alle
folgenden Vergleiche laufen deshalb über
`sed -E 's/(&#038;|&)cbdap31=[0-9]+//g'`.

### 1d — Der eigentliche Nachweis

Abgerufen wurde jeweils die gesperrte Seite 3003 als Schülerin der Klasse A
(gültiges Token, zwei freigegebene Container):

| Fassung | Wie hergestellt | Größe |
|---|---|---|
| `vorher.html` | Testserver auf den Stand `6b04708` zurückgesetzt: die vier geänderten PHP-Dateien per `git show 6b04708:<datei>` überschrieben, `includes/class-cbd-klassenpuls.php` beiseitegelegt | 108 018 Byte |
| `jetzt_puls_aus.html` | heutiger Code, `cbd_klassenpuls_takt = 0` | 108 018 Byte |
| `jetzt_puls_an.html` | heutiger Code, `cbd_klassenpuls_takt = 10` | 108 391 Byte |

| Vergleich | Erwartet | Tatsächlich |
|---|---|---|
| `vorher` ↔ `jetzt_puls_aus` | **byteidentisch** | **byteidentisch** — SHA-256 beider Fassungen `3bcdbaab96dab448422032c074fe69f4…`, `diff` leer |
| `vorher` ↔ `jetzt_puls_an` | Unterschied nur im eingebundenen `klassenpuls.js` | **erfüllt** — genau 5 hinzugefügte Zeilen, 0 entfernte, 0 geänderte |

Die fünf hinzugefügten Zeilen sind vollständig:

```html
<script id="cbd-klassenpuls-js-extra">
var cbdKlassenpulsDaten = {"restUrl":"http://fos.localhost:8080/wp-json/cbd/v1/klassenpuls","takt":"10"};
//# sourceURL=cbd-klassenpuls-js-extra
</script>
<script id="cbd-klassenpuls-js" src="…/assets/js/klassenpuls.js?ver=3.1.117"></script>
```

Beide Fassungen enthalten **dieselben zwei** Container
(`cbd-1771189754-iNVDBCCc`, `cbd-1771189754-R0R7es6h`) und keinen weiteren.

Der Testserver wurde unmittelbar danach aus der Sicherung wiederhergestellt;
alle fünf Dateien stimmen per SHA-256 wieder mit dem Zustand vor dem Eingriff
überein.

**Urteil Strang 1: bestanden.** Die Reduktion ist byteidentisch. Bei
abgeschaltetem Puls (`cbd_klassenpuls_takt = 0`) ist die Auslieferung
Byte für Byte die von vor dem Vorhaben — die Notbremse aus Abschnitt 5 des
Plans hält, was sie verspricht.

---

## Strang 2 — Der Endpunkt gegen fremde und fehlende Tokens

Aufgerufen wurde `GET /index.php?rest_route=/cbd/v1/klassenpuls` mit
`page_id=3003`. Klasse A = 29 (2 Freigaben), Klasse B = 30 (1 Freigabe).

| # | Aufruf | Erwartet | Tatsächlich |
|---|---|---|---|
| 1 | `?classroom=29&token=<A>` | 200 | **200** |
| 2 | `?classroom=30&token=<A>` — **Confused Deputy** | 404 | **404** |
| 3 | `?classroom=29` ohne `token` | 404 | **404** |
| 4 | `?token=<A>` ohne `classroom` | 404 | **404** |
| 5 | `?classroom=29&token=abcdefabcdef` | 404 | **404** |
| 6 | `?classroom=0&token=<A>` | 404 | **404** |

### Zeichengleichheit der fünf Ablehnungen

Alle fünf Rümpfe wurden roh gespeichert und paarweise verglichen:

```
{"code":"cbd_puls_not_available","message":"Der Klassenpuls ist nicht verf\u00fcgbar."}
```

| Prüfung | Erwartet | Tatsächlich |
|---|---|---|
| Länge je Rumpf | gleich | **87 Byte, alle fünf** |
| SHA-256 der fünf Rümpfe | ein einziger Wert | **ein einziger Wert** (`0c57196cbbcd8c74…`) |
| HTTP-Status | gleich | **404 Not Found**, alle fünf |
| `Cache-Control` | gleich, `no-store` | `no-cache, must-revalidate, max-age=0, no-store, private`, alle fünf |
| `Expires` | gleich | `Wed, 11 Jan 1984 05:00:00 GMT`, alle fünf |
| `Content-Type` | gleich | `application/json; charset=UTF-8`, alle fünf |

Ablehnung und Nichtexistenz sind damit auf **Rumpf, Status und Kopfzeilen**
nicht unterscheidbar. Lösungsseiten lassen sich durch Durchprobieren nicht
kartieren.

### Gegenprobe: Der Confused-Deputy-Fall hätte etwas zu verraten gehabt

Ein 404 beweist für sich genommen nichts, wenn beide Klassen dieselben
Signaturen trügen. Deshalb wurde Klasse B zusätzlich mit **ihrem eigenen**
Token abgerufen:

| Aufruf | Antwort |
|---|---|
| `?classroom=29&token=<A>` | `{"klasse":"75dd1d12b6ce",…,"seite":"165ed13f4e22",…}` |
| `?classroom=30&token=<B>` | `{"klasse":"c39b83c7302f",…,"seite":"5f5c7404b9df",…}` |

Die Signaturen unterscheiden sich in `klasse` **und** `seite`. Der
404 auf `?classroom=30&token=<A>` hat also tatsächlich eine Auskunft
zurückgehalten, die vorhanden und verschieden war.

`CBD_Classroom_Gate::sitzung()` fängt den Fall wie dokumentiert ab: Der
Transient ist maßgeblich, nicht der URL-Parameter; stimmt die dort
hinterlegte `class_id` nicht mit `?classroom=` überein, gilt die Sitzung als
ungültig.

**Urteil Strang 2: bestanden.** Alle sechs Aufrufe mit dem erwarteten
Status, alle fünf Ablehnungen zeichengleich bis auf das Byte.

---

## Strang 3 — Der Puls verrät keine Inhalte

Erfolgsantwort aus Strang 2, Aufruf 1, im Wortlaut:

```json
{"klasse":"75dd1d12b6ce","fragenwand":"1a406d3a0c55","seite":"165ed13f4e22","tafel":"ef36ef460785","takt":10}
```

Fünf Felder, Feld für Feld:

| Feld | Inhalt | Woraus berechnet | Verrät es etwas? |
|---|---|---|---|
| `klasse` | 12 Hexzeichen | `substr(md5(…), 0, 12)` über `COUNT(DISTINCT page_id)`, `SUM(id)`, `COUNT(*)`, `SUM(page_id * (sort_order+1))` | nein — vier Ganzzahlen |
| `fragenwand` | 12 Hexzeichen | dieselbe Kurzform über Zählwerte der Fragenwand | nein |
| `seite` | 12 Hexzeichen | Kurzform über `COUNT(*)`, `SUM(is_behandelt)`, `SUM(id * is_behandelt)` | nein — drei Ganzzahlen |
| `tafel` | 12 Hexzeichen | Kurzform über `MAX(updated_at)` | nein — ein Zeitstempel |
| `takt` | `10` | die Option `cbd_klassenpuls_takt` | nein — Betriebseinstellung |

Ausdrücklich **nicht** enthalten: keine Container-Kennung, kein Seitentitel,
kein Notiz- oder Zeichnungsinhalt, keine Klassenbezeichnung, keine Zahl, aus
der sich eine Menge ablesen ließe. Alle vier Signaturen sind auf 12 Zeichen
gekürzte MD5-Werte über reine Zahlen; die Zahlen selbst gehen nicht hinaus.

### Seite ohne Freigabe gegen Seite mit Freigaben

Für dieselbe Klasse A auf derselben Seite 3003, einmal mit zwei Freigaben,
einmal nach Rücknahme aller Freigaben:

| Zustand | Antwort |
|---|---|
| 2 Freigaben | `{"klasse":"75dd1d12b6ce","fragenwand":"1a406d3a0c55","seite":"165ed13f4e22","tafel":"ef36ef460785","takt":10}` |
| 0 Freigaben | `{"klasse":"555562867d6d","fragenwand":"1a406d3a0c55","seite":"07a07b6f0ea7","tafel":"c8867d788d8c","takt":10}` |

Erwartet und eingetreten: Die Antworten **unterscheiden sich** (sonst
bemerkte der Browser die Änderung nicht), sagen aber nichts über den Inhalt.
Aus `165ed13f4e22` lässt sich weder ablesen, dass es zwei Container waren,
noch welche.

**Nebenbefund, der eine Entscheidung aus AP-3.2 am lebenden Server
bestätigt:** Mit der Rücknahme bewegt sich **auch** `tafel`
(`ef36ef460785` → `c8867d788d8c`), weil das Zurücknehmen `updated_at`
mitschreibt. Genau darauf stützt sich Abweichung 1 der AP-3.2-Übergabenotiz
(„geprüft wird bei **beiden** Anlässen"). Die Begründung war dort aus dem
Quelltext abgeleitet; sie ist hiermit gemessen.

**Urteil Strang 3: bestanden.**

---

## Strang 4 — Das bestehende Sicherheitsnetz

### 4a — Prüfharnische

| Harnisch | Erwartet | Tatsächlich |
|---|---|---|
| `php tools/test-classroom-gate.php` | ALLE TESTS BESTANDEN | **ALLE TESTS BESTANDEN**, Exit-Code 0 |
| `php tools/test-block-content-api.php` | ALLE TESTS BESTANDEN | **ALLE TESTS BESTANDEN**, Exit-Code 0 |
| `php tools/test-klassenpuls.php` | ALLE TESTS BESTANDEN | **ALLE TESTS BESTANDEN**, Exit-Code 0 |

### 4b — Die Grenzen am lebenden Server

| # | Abruf der gesperrten Seite 3003 | Erwartet | Tatsächlich |
|---|---|---|---|
| a | ohne jeden Parameter | 403 + Hinweisseite des Themes | **403**, Titel „Nur für Lehrpersonen – Chemie Skripten FOS Meran", **0** Container im Quelltext |
| b | `?classroom=29&token=<A>` (2 Freigaben) | 200, genau 2 Container | **200**, genau die 2 freigegebenen: `iNVDBCCc`, `R0R7es6h` |
| c | `?classroom=30&token=<B>` (1 Freigabe) | 200, genau 1 Container | **200**, genau `kG5Hddk6` |
| d | `?classroom=30&token=<A>` — fremdes Token | 403 | **403**, 0 Container |
| e | `?classroom=29&token=<A>` nach Rücknahme **aller** Freigaben | 403 | **403**, Titel der Hinweisseite, 0 Container |

Zeile b ist zugleich der **Smoke-Test am Seitenquelltext einer echten
reduzierten Seite**, der aus AP-3.1 offen war: Von 22 Containern der Seite
stehen genau die 2 freigegebenen im ausgelieferten HTML. Die übrigen 20
sind nicht versteckt, sondern **nicht vorhanden** — kein
`data-stable-id`, kein Markup, kein Text.

Zeile c zeigt dieselbe Grenze aus der Gegenrichtung: Dieselbe Seite liefert
derselben Anfrage je nach Klasse einen **anderen** einzelnen Container.

Zeile e ist die **Grundannahme des gesamten AP-3.2**, die dessen
Übergabenotiz ausdrücklich als „im Quelltext lückenlos gelesen, aber nie am
lebenden Server ausgelöst" offengelassen hatte: Ein Neuladen ohne die
Umleitung aus AP-3.2 liefe tatsächlich in HTTP 403. **Hiermit am lebenden
Server bestätigt.**

### 4c — Die Kopplung, die Phase 3 neu aufgebaut hat

Abschnitt 0a des Plans verlangt ausdrücklich, die Naht zwischen
`data.treated_containers` und `CBD_Classroom::behandelte_container()`
mitzuprüfen. Gemessen für beide Klassen:

| Klasse | `behandelte_container()` | HTML der reduzierten Seite | Gate |
|---|---|---|---|
| A (2 Freigaben) | 2 Kennungen | 2 Container | offen (200) |
| A (0 Freigaben) | 0 Kennungen | — | geschlossen (403) |
| B (1 Freigabe) | 1 Kennung | 1 Container | offen (200) |

Leere Liste ⇔ geschlossenes Gate ⇔ 403, in beiden Richtungen. Die Kopplung
läuft nicht auseinander.

### 4d — Fehlerlog

| Prüfung | Erwartet | Tatsächlich |
|---|---|---|
| Neue Notices/Warnings/Fatals in `debug.log` | 0 | **0** |
| Zeilenzuwachs gesamt | — | 398 738 → 399 764, **+1 026** |

Der Zuwachs besteht ausschließlich aus den bekannten
`[CBD Block Registration] Block type registered: …`-Zeilen — Befund B9 aus
`AP-1.rev` (jeder Seitenaufbau protokolliert rund 34 Zeilen), Bestand,
unabhängig von diesem Vorhaben.

**Urteil Strang 4: bestanden.**

---

## Befunde

Kein kritischer Befund. Die Phase ist nicht blockiert.

### F1 — Stehengebliebenes Test-mu-plugin auf dem Testserver (mittel, nur Testumgebung)

`…\fos\wp-content\mu-plugins\cbd-ap31-cachebust.php`, angelegt am
2026-08-31 von der ersten `AP-3.1`-Sitzung, ist **nicht gelöscht worden** —
entgegen der eigenen Dateiüberschrift („Nach dem Test loeschen.") und
entgegen Betriebswissen-Punkt 5a in Abschnitt 0a. Es tut **drei** Dinge:

1. Hängt an jede Ressource mit dem Handle-Präfix `cbd-` oder mit
   `classroom` im Handle `&cbdap31=<Unixzeit>`. Wirkung: Jede
   Plugin-Ressource ist dauerhaft unzwischenspeicherbar. **Jede Lastmessung
   auf diesem Server ist damit verzerrt**, und jeder naive Bytevergleich
   zweier Abrufe über eine Sekundengrenze hinweg meldet rund 20
   Scheinunterschiede.
2. Entfernt auf Seite 5625 den Glossar-Filter des Themes.
3. **Lässt `determine_current_user` bei `?ap31anon=1` `false` zurückgeben** —
   ein global installierter Schalter, der jede einzelne Anfrage als
   abgemeldet gelten lässt.

Punkt 3 ist der ernste: Ein Haken in der Authentifizierungskette, der auf
Zuruf per URL-Parameter greift, gehört nicht dauerhaft auf einen Server, auf
dem Sicherheitsprüfungen stattfinden. **Auf die Produktion kann er nicht
gelangen** — er ist ein mu-plugin der Testinstallation und liegt weder im
Repository noch im Plugin-ZIP.

**Für die Prüfungen dieses Berichts ohne Wirkung**, ausdrücklich geprüft:
Keiner der Aufrufe setzte `ap31anon`, `curl` sendet keine Cookies (die
Aufrufe waren ohnehin anonym), und keine Prüfung betraf Seite 5625. Einzig
der Cache-Brecher aus Punkt 1 wirkte — er wurde vor jedem Vergleich
herausnormalisiert und ist in Strang 1c offengelegt.

**Empfehlung:** Die Datei löschen. Nicht in diesem AP getan, weil AP-3.3
laut Auftrag nichts verändert und die Datei aus einem fremden AP stammt.
Vorgemerkt für `AP-3.rev`.

### F2 — Widerspruch zur AP-3.1-Übergabenotiz (gering, Prozessbefund)

Die Übergabenotiz von `AP-3.1` hält fest, in **beiden** Sitzungen habe „kein
Browser-Werkzeug zur Verfügung" gestanden. Der Kommentar in
`cbd-ap31-cachebust.php` — von der ersten Sitzung geschrieben — begründet
den `ap31anon`-Schalter dagegen damit, dass „das Browser-Werkzeug sich das
Profil mit der angemeldeten Lehrperson teilt". Die erste Sitzung hatte
demnach sehr wohl ein Browser-Werkzeug. Da diese Sitzung nichts verbucht
hat, ist unbekannt, was sie damit geprüft hat. Für `AP-3.rev` als
Prozessbefund vorgemerkt, neben der dort bereits notierten
Scope-Überschreitung.

### F3 — Zeilenenden der Serverkopie (gering, kosmetisch)

`includes/class-cbd-klassenpuls.php` liegt auf dem Testserver mit LF, im
Repository mit CRLF (22 601 gegen 23 120 Byte). Inhaltlich identisch
(`diff` nach `tr -d '\r'` ist leer), für PHP ohne Bedeutung. Erwähnt, weil
ein roher Hash-Abgleich Server ↔ Repo sonst fälschlich Alarm schlägt; die
vier übrigen geprüften PHP-Dateien stimmen auch roh überein.

---

## Offen geblieben

Die folgenden Punkte aus den Übergabenotizen von `AP-3.1` und `AP-3.2`
konnten in diesem AP **nicht** nachgeholt werden und bleiben für `AP-3.rev`
vorgemerkt. Sie betreffen ausnahmslos die **Darstellung** im Browser, nicht
die Sicherheit der Reduktion:

- die tatsächliche Darstellung der Wiederaufnahme-Leiste
  (`.cbd-live-hinweis`) und der Abschiedsleiste
  (`.cbd-live-hinweis--abschied`): Farbe, Position, Kontrast, Dunkelmodus,
  `prefers-reduced-motion`, und ob der dreizeilige Abschiedssatz bei
  `top: 4.5rem` vollständig unter der Navigationsleiste steht;
- der Ende-zu-Ende-Weg mit zwei Fenstern (Freigabe durch die Lehrperson →
  Puls → Neuladen beim Schüler binnen ~10 s);
- welche Adresse `document.referrer` im echten Ablauf trägt (Stufe 1 der
  Kaskade in `klassenlistenZiel()`).

Zwei der ursprünglich fünf offenen Punkte sind durch diesen Bericht
**erledigt**: der Smoke-Test am Quelltext einer echten reduzierten Seite
(Strang 4b) und der Nachweis, dass ein Neuladen ohne AP-3.2 in HTTP 403
liefe (Strang 4e).

---

## Aufräumen

| Vorgang | Zustand |
|---|---|
| Testserver-PHP nach dem Vorzustands-Abruf | aus der Sicherung wiederhergestellt, alle fünf Dateien per SHA-256 gegengeprüft |
| `cbd_klassenpuls_takt` | zurück auf den Ausgangswert `10` |
| Zeichnungsdatensätze der Klassen A und B | gelöscht (Seite 3003 wieder mit 0 Datensätzen, wie vorgefunden) |
| Klassen 29 und 30 | gelöscht |
| Sitzungs-Transients | gelöscht (Server wieder ohne aktive Klassensitzung) |
| `_simple_clean_nur_lehrpersonen` auf Seite 3003 | entfernt (Meta existierte vorher nicht) |
| HTML-Vergleichsdateien | **nicht** ins Repository aufgenommen — sie enthalten Seiteninhalte — und nach dem Test gelöscht |

---

## Gesamturteil

**Bestanden. Kein kritischer Befund; die Phase ist nicht blockiert.**

Die serverseitige Reduktion ist von Phase 3 nicht bewegt worden. Der
Nachweis ist stärker als das Akzeptanzkriterium verlangt: Nicht nur ist der
Quelltext-Diff leer, die **ausgelieferte Seite** ist bei abgeschaltetem Puls
Byte für Byte dieselbe wie vor dem Vorhaben, und bei eingeschaltetem Puls
unterscheidet sie sich in genau fünf Zeilen, die ein Skript einbinden.

Der neue Endpunkt umgeht die Grenze nicht: Er antwortet auf jede
unvollständige, fremde oder erfundene Legitimation mit derselben 87 Byte
langen Ablehnung und demselben Status, und seine Erfolgsantwort besteht aus
vier gekürzten Prüfsummen über Zahlen und einer Betriebseinstellung.

Ein Befund mittleren Schweregrads (F1) betrifft ausschließlich die
**Testumgebung**: ein stehengebliebenes mu-plugin mit einem
Authentifizierungs-Schalter. Es kann die Produktion nicht erreichen, hat auf
die Ergebnisse dieses Berichts nachweislich nicht gewirkt, und sollte
gelöscht werden.
