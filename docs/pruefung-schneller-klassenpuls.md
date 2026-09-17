# Prüfung: Verhaltensgleichheit der vier Abonnenten unter dem schnellen Takt

_Durchgeführt: 2026-09-17 · Gehört zu `PLAN-Schneller-Klassenpuls.md`, AP-2.3_

> **Kurzfassung: bestanden.** Alle 16 Einzelprüfungen der sechs Gruppen sind
> durchgeführt. Kein Abonnent verhält sich unter dem fünfmal schnelleren Takt
> anders als zuvor. **Ein Befund**, gering und nicht durch dieses Vorhaben
> verursacht; dazu drei Umgebungsnotizen. Der zweite Befund B2 — eine
> zunächst nicht durchführbare Prüfung — ist am selben Tag nachgeholt
> worden (Gruppe 4c).
>
> **Der Messwert für `AP-3.2`: eine Neuladung je Minute** bei durchgehendem
> Zeichnen der Lehrperson (20 Speicherungen in 60 Sekunden). Der
> Mindestabstand von 60 Sekunden hält unter dem schnellen Takt unverändert.

## Aufbau der Prüfung

Alles auf dem lokalen Testserver `http://fos.localhost:8080`, mit einer eigens
angelegten Klasse (Name „AP-2.1 Pruefklasse"), die nach Abschluss restlos
entfernt wurde (Nachweis unten).

**Abweichung vom Plantext, und sie hat die Prüfung besser gemacht:** Der Plan
sieht zwei Browserfenster vor — eines als Lehrperson, eines als Schüler. Statt
eines zweiten Fensters laufen **alle Lehrerhandlungen über PHP-CLI**
(`lehrer_ap23.php` im Scratchpad, ausschließlich per `php script.php`
aufgerufen, nie über den Browser). Das hat drei Vorteile: Die Handlung ist auf
die Millisekunde datiert, sie ist wiederholbar, und sie belegt nebenbei, dass
die Aktion `cbd_klassenmodus_geaendert` auch außerhalb des AJAX-Wegs trägt.
Der Preis: Die **Lehrer-Oberfläche** der Fragenwand wird so nicht bedient. Prüfung 4c
ist deshalb nachträglich mit angemeldeter Lehrersitzung nachgeholt worden (Befund B2).

**Die Schülerseite lief nicht angemeldet.** Das ist Voraussetzung für
Gruppe 2: Die serverseitige Reduktion greift ausschließlich für nicht
angemeldete Besucher.

---

## Gruppe 1 — Normale Seite (`classroom-page-filter.js`)

Seite 117 „Die wichtigsten Organischen Grundlagen", 10 Container.

| # | Prüfung | Ergebnis |
|---|---|---|
| 1a | Container freigeben | **erscheint nach 3127 ms** ✓ |
| 1b | Freigabe zurücknehmen | **verschwindet nach 2427 ms** ✓ |
| 1c | Zugeklappten Container behalten | **bleibt zugeklappt** ✓ |
| 1d | Tafelbild speichern | **erscheint nach 1991 ms**, `<img>` vorhanden ✓ |
| 1e | Nummerierung der sichtbaren Container | **1 und 5** — siehe Befund B1 |
| 1f | LaTeX-Formeln im neu freigegebenen Container | **3 von 3 mit KaTeX gerendert**, kein rohes LaTeX im Text ✓ |

**Zu 1c, der wichtigsten Prüfung dieser Gruppe.** Sie zielt auf die Falle aus
`AP-2.fix1`/`AP-2.fix2` des Vorgänger-Vorhabens: `jQuery.is(':visible')` prüft
die **Vorfahrenkette** statt des eigenen Elements, weshalb ein Container in
einem zugeklappten Elternteil fälschlich als „schon versteckt" galt. Ablauf:
Container 1 wurde per **echtem Klick** zugeklappt (`cbd-collapsed` gesetzt,
Inhalt `display: none`), danach Container 2 freigegeben. Ergebnis: Container 2
erschien, Container 1 blieb zugeklappt — über die Freigabe **und** die
anschließende Rücknahme hinweg. Die Falle bleibt entschärft.

> **Ein synthetischer Klick genügt hier nicht.** `element.click()` auf den
> Klapp-Knopf ließ den Container unverändert; erst ein echter Zeigerklick
> klappte ihn zu. Der Knopf hängt an der WordPress-Interactivity-API
> (`data-wp-on--click`). Das ist dieselbe Lehre, die das Projekt beim
> Text-Werkzeug des Tafelmodus schon einmal teuer bezahlt hat.

---

## Gruppe 2 — Gesperrte Seite (serverseitig reduziert)

Seite 5424 „Reinstoffe und Gemische — Erwartungshorizont", als „nur für
Lehrpersonen" gesperrt, 3 Container. Ausgangslage geprüft: `reduziert: "1"`,
**genau ein** Container im ausgelieferten HTML, nicht angemeldet.

| # | Prüfung | Ergebnis |
|---|---|---|
| 2a | Zweiten Container freigeben | **Neuladen nach 2325 ms**, beide Container im HTML, **Leseposition erhalten** (376 px), Hinweisleiste „Neu freigegeben" vorhanden, `sessionStorage`-Eintrag verbraucht ✓ |
| 2b | 60 Sekunden durchgehend zeichnen | **genau 1 Neuladung** bei 20 Speicherungen — siehe unten |
| 2c | Letzte Freigabe zurücknehmen | **kein HTTP 403**, Umleitung mit erhaltener Sitzung ✓ |

### 2b im Einzelnen — der Messwert für AP-3.2

Die Lehrperson speicherte das Tafelbild **zwanzigmal in 60 Sekunden** (alle
drei Sekunden, 22:32:36 bis 22:33:52). Jede Speicherung bewegt die Signatur
`tafel`, denn sie ist `MAX(updated_at)`.

Gezählt wurde **serverseitig im Apache-Zugriffsprotokoll**, nicht im Browser —
ein Neuladen vernichtet jeden Zähler in der Seite. Gesucht wurden GET-Anfragen
auf den Seitenpfad selbst:

```
17/Sep/2026:22:32:11  HTTP 200      (das Neuladen aus Prüfung 2a, vor dem Fenster)
17/Sep/2026:22:33:12  HTTP 200      (die einzige Neuladung im Messfenster)
```

> **Eine Falle beim Zählen, die fast zu einer falschen Zahl geführt hätte:**
> Ein `grep` auf den Seitenpfad trifft auch das **Referer**-Feld der
> Pulsdatei-Abrufe — und damit 57 statt 2 Zeilen. Gezählt werden darf nur der
> Anfragepfad (`"GET /pfad/`), nie das Vorkommen irgendwo in der Zeile.

**Ergebnis: eine Neuladung je Minute, nicht zwanzig.** Der Mindestabstand von
60 Sekunden (`MINDESTABSTAND_MS` in `classroom-page-filter.js`) hält unter dem
zweisekündigen Takt genauso wie unter dem zehnsekündigen. **Der schnelle Takt
verschlimmert das Neulade-Verhalten also nicht** — er macht nur die
Verzögerung bis zur **ersten** Neuladung kürzer.

### 2c im Einzelnen — wohin umgeleitet wird

Nach der Rücknahme aller Freigaben dieser Seite lud der Browser **nicht** neu
in einen HTTP 403, sondern wechselte auf Seite 117 — eine andere Seite
derselben Klasse — **mit `classroom` und `token` in der Adresse**. Dort
arbeitete der Klassenmodus normal weiter (zwei Container sichtbar).

Das ist Stufe 2 der dokumentierten Kaskade in `klassenlistenZiel()` (ein
Sitzungslink aus der Klassen-Seitenleiste), nicht Stufe 1 (die
`[cbd_classroom]`-Seite über `document.referrer`) — der Referrer war hier die
Seite selbst und fällt deshalb aus. **Der Plantext spricht von „Umleitung auf
die Klassen-Seitenliste"; erreicht wurde eine Klassenseite.** Der Zweck der
Prüfung — kein 403, Sitzung erhalten — ist erfüllt; das Ziel ist ein anderes
als der Wortlaut nahelegt, und das steht so im Code.

---

## Gruppe 3 — Klassen-Seitenliste (`classroom-frontend.js`)

| # | Prüfung | Ergebnis |
|---|---|---|
| 3a | Seite 5424 der Klasse zuordnen | **erscheint live** in der Liste, samt Elternkette „Chemie → Stoffe und Stoffeigenschaften → Reinstoffe und Gemische" ✓ |
| 3b | Klappzustand über das Neuzeichnen | **überlebt** ✓ |

**Zu 3b:** Kapitel 19 wurde per echtem Klick zugeklappt
(`localStorage['cbd_classroom_toc_collapsed'] = ["19"]`). Nach dem
Hinzufügen der neuen Seite baute die Liste sich neu auf — mit vier zusätzlichen
Knoten und einer neuen Reihenfolge. Kapitel 19 stand danach weiterhin auf
`aria-expanded="false"`, seine Kinder auf `display: none`, und der
`localStorage`-Eintrag war unverändert `["19"]`.

> **Beim Nachmessen aufgepasst:** Nach dem Neuaufbau ist der *erste* Klapp-Knopf
> der Liste ein **anderer** Knoten als vorher (die neue Seite steht oben). Wer
> per „erster Treffer" prüft, misst das falsche Kapitel und bekommt ein
> Scheinergebnis. Geprüft wurde deshalb gezielt `[data-page-id="19"]`.

---

## Gruppe 4 — Fragenwand (`fragenwand-frontend.js`)

Die kritischste Gruppe: Ihre Kernregel „im Zweifel nicht neu zeichnen" hat
unter einem fünffach höheren Takt fünfmal mehr Gelegenheiten zu versagen.

| # | Prüfung | Ergebnis |
|---|---|---|
| 4a | Notiz anlegen, Wand öffnen | Notiz sichtbar, **genau 1** Abruf beim Öffnen ✓ |
| 4b | Notizen eintreffen, **während** der Schüler tippt | **49 Sekunden gehalten, 10 Notizen, Text unverändert** ✓ |
| 4c | Offene Bearbeitung | **48 Sekunden gehalten, 8 Änderungen, Inhalt zeichengleich** ✓ |
| 4d | Erledigt setzen / löschen | beides erscheint ✓ |
| 4e | Wand geschlossen | **0 Abrufe** von `cbd/v1/fragenwand` ✓ |

### 4b im Einzelnen — und die zweite Hälfte, die der Plan nicht verlangt

Der Schüler tippte per **echter Tastatureingabe** „Warum loest sich Salz in
Wasser" (31 Zeichen) in das Fragefeld. Während der folgenden **49 Sekunden**
legte die Lehrperson **zehn** Notizen an, im Abstand von rund fünf Sekunden —
also über zwei Dutzend Takte des schnellen Zeitgebers hinweg.

Danach: Der Feldinhalt war **zeichengleich** erhalten, und das Feld hatte
**immer noch den Fokus**. Die Wand hatte in dieser Zeit **kein einziges Mal**
neu gezeichnet (weiterhin 1 Abruf, 1 Notiz sichtbar) — genau das ist die
Absicht: `darfNeuZeichnen()` verweigert, solange ein Eingabefeld Text enthält.

**Der Plan verlangt nur diese Hälfte. Die andere ist aber die wichtigere:**
Wird das Nachgeholte auch wirklich nachgeholt? Nach dem Leeren des Feldes und
dem Fokusverlust folgte **genau ein** weiterer Abruf, und **alle elf** Notizen
standen da. Der Merker `nachzeichnenAusstehend` trägt also auch unter dem
schnellen Takt — er sammelt nicht zehn ausstehende Neuzeichnungen an, sondern
holt einmal nach.

### 4c im Einzelnen — nachgeholt am selben Tag

Im ersten Durchgang nicht durchführbar (das Fenster musste für Gruppe 2
abgemeldet sein), danach mit angemeldeter Lehrersitzung nachgeholt.

Ablauf: Fragenwand über den Lehrerweg geöffnet (Klassenauswahl → Wand mit
„Bearbeiten", „Löschen", „Frage hinzufügen"), bei einer Notiz **Bearbeiten**
geklickt (`data-bearbeitung="1"` gesetzt, Eingabefeld mit dem Notiztext), und
darin per echter Tastatureingabe Text ergänzt — also ein **ungespeicherter**
Zwischenstand. Danach legte die Lehrperson **acht** weitere Notizen an,
verteilt über **48 Sekunden**.

| | |
|---|---|
| Bearbeitung noch offen | **ja** (`data-bearbeitung="1"`) |
| Eingabefeld noch da | **ja** |
| Inhalt | **zeichengleich** („Diese Notiz wird bearbeit ANGEHAENGTet") |
| Fokus | **erhalten** |
| Neuzeichnungen in dieser Zeit | **keine** — weiterhin eine Notiz sichtbar |

**Und wieder die zweite Hälfte, die zählt:** Nach dem Klick auf „Abbrechen"
folgte **genau ein** Abruf, und **alle neun** Notizen standen da. Nebenbei
belegt: „Abbrechen" verwarf den Zwischenstand korrekt — die Notiz trägt
wieder ihren Originaltext.

**Damit sind alle vier `darfNeuZeichnen()`-Bedingungen unter Last geprüft**,
nicht drei von vier.

### 4d im Einzelnen

Die älteste Notiz wurde auf erledigt gesetzt: Sie bekam die Erledigt-Kennung
**und rutschte ans Ende der Liste** — die dokumentierte Sortierung
`ORDER BY ist_erledigt ASC, created_at ASC, id ASC` wirkt also auch beim
Live-Neuzeichnen. Anschließend wurde die jüngste Notiz gelöscht: 11 → 10
Notizen, die richtige fehlte.

---

## Gruppe 5 — Abgelaufene Sitzung

Der Sitzungs-Transient wurde serverseitig gelöscht (22:41:24). Gemessen wurde
im Browser **und** im Apache-Zugriffsprotokoll:

```
22:41:26  HTTP 200  Pulsdatei       (der bereits geplante Abruf)
22:41:27  HTTP 404  cbd/v1/klassenpuls
--- danach 51 Sekunden lang nichts ---
```

- `abgelaufen` wurde **genau einmal** gemeldet.
- `window.cbdKlassenpuls.laeuft()` steht danach auf `false`.
- **Beide** Zeitgeber stehen still; kein weiterer Abruf, weder Datei noch Route.

> **Die Zählung im Browser allein hätte hier getrogen.** Der
> Resource-Timing-Puffer fasst 250 Einträge; auf einer Seite mit über hundert
> Pulsdatei-Abrufen läuft er voll und zeichnet danach nichts mehr auf. Er
> meldete „0 Routenabrufe seitdem", obwohl der 404 stattgefunden hatte. Das
> Zugriffsprotokoll ist die belastbare Quelle.

---

## Gruppe 6 — Byteidentität bei abgeschaltetem Puls

Die Regressionsgrenze des ganzen Vorhabens: Bei `cbd_klassenpuls_takt = 0`
muss eine **reduzierte** Seite byteweise dasselbe liefern wie vor Phase 2.

**Verfahren** (im Bericht benannt, wie das Akzeptanzkriterium es verlangt):

1. `cbd_klassenpuls_takt` auf `0` gesetzt.
2. **Rauschboden bestimmt:** Dieselbe Seite dreimal hintereinander anonym mit
   `curl` abgerufen, `md5sum` verglichen — **alle drei identisch**. Ohne diesen
   Schritt wäre ein Vergleich wertlos, weil Nonces oder Zeitstempel im Markup
   von sich aus streuen könnten.
3. Den Stand von `main` (`git show main:assets/js/klassenpuls.js`, 1023 Zeilen)
   auf den Testserver gespielt, Seite abgerufen.
4. Den Phase-2-Stand (1569 Zeilen) wiederhergestellt, Seite abgerufen.
5. `md5sum` und `cmp` über alle fünf Dateien.

**Ergebnis:** Alle fünf Abrufe tragen dieselbe Prüfsumme
`27fd540291402431d04ff5fdedc71650`, `cmp` meldet keinen Unterschied.
`klassenpuls.js` kommt im Quelltext **0-mal** vor.

Das ist auch strukturell zu erwarten — Phase 2 hat nur `klassenpuls.js`
angefasst, und bei Takt 0 wird die Datei gar nicht erst eingereiht. Gemessen
ist es trotzdem, weil „zu erwarten" in diesem Projekt schon mehrfach falsch
war.

---

## Fehlerprotokolle

| Quelle | Ergebnis |
|---|---|
| `wp-content/debug.log` | **+4841 Zeilen**, davon **0** mit `Warning`, `Notice`, `Deprecated`, `Fatal error` oder `Parse error` (gezählt mit `grep -icE` über die Differenz) |
| Browser-Konsole | **keine Fehler** während aller sechs Gruppen |

Die 4841 Zeilen sind ausschließlich die bekannte Boot-Protokollierung (rund 34
Zeilen je Anfrage, Registrierung der Container-Blöcke und „Eigene WP Blocks").
Sie ist der Grund, warum `WP_DEBUG_LOG` produktiv aus bleiben muss.

---

## Befunde

### B1 — Nummerierungslücken beim Schüler (gering, Bestand)

Die sichtbaren Container trugen die Nummern **1 und 5**, nicht 1 und 2.

`CBDRenumberBlocks()` nummeriert alle Container **oberster Ebene in
Dokumentreihenfolge**, unabhängig von der Sichtbarkeit. Ein Schüler sieht
deshalb Lücken. **Vorbestehend und ausdrücklich so gewollt:** Eine
sichtbarkeitsabhängige Nummerierung würde die Nummern beim Schüler *live
verschieben*, während die Lehrperson mündlich eine Nummer nennt. Beschrieben
in `CLAUDE.md`, Abschnitt „Klassenmodus: Live-Aktualisierung", Phase 2, „Die
beiden Nachrüst-Haken".

**Von diesem Vorhaben weder verursacht noch verschlimmert.** Das
Akzeptanzkriterium 1e („die fortlaufende Nummerierung der sichtbaren Container
stimmt") ist nach seinem Wortlaut **nicht** erfüllt; nach der dokumentierten
Absicht des Projekts schon.

> Ein erster Messversuch hätte das übersehen: Dort waren zufällig die
> Container 1 und 2 freigegeben, die Nummern lasen sich lückenlos. Erst die
> gezielte Freigabe eines **nicht benachbarten** Containers macht die
> Eigenschaft sichtbar. Wer 1e künftig nachmisst, wählt bewusst einen
> Container aus der Mitte.

### B2 — Prüfung 4c: nachgeholt, kein offener Punkt mehr

_Ursprünglich als Prüflücke vermerkt, am 2026-09-17 geschlossen._

Die Prüfung war im ersten Durchgang nicht durchführbar: Das Bearbeiten einer
Notiz ist eine **Lehrer**-Funktion und setzt `cbd_edit_blocks` voraus; das
Browserfenster musste für Gruppe 2 abgemeldet sein. Der Betreiber hat sich
daraufhin angemeldet, und die Prüfung ist nachgeholt worden — **bestanden**,
Einzelheiten unter „4c im Einzelnen".

**Damit ist keine der vier `darfNeuZeichnen()`-Bedingungen ungeprüft.**

> **Eine Hürde, die mit dem Prüfgegenstand nichts zu tun hatte:** Der
> Fragenwand-Knopf sitzt in der Theme-Seitenleiste, und die ist im schmalen
> Browser-Bereich dieser Umgebung ausgefahren
> (`transform: translateX(-256px)`) und für einen Klick unerreichbar — auch
> nach Vergrößern des Viewports und nach Betätigen des Navigationsknopfs.
> Statt weiter am Layout zu arbeiten wurde ein **echter**
> `<button class="cbd-fragenwand-verweis">` mitten in den Inhalt gesetzt und
> dieser geklickt. Das ist keine Umgehung des Prüfwegs: Der delegierte
> Listener in `fragenwand-frontend.js` hängt an `document` und fängt jedes
> Element mit dieser Klasse ab, unabhängig davon, wer es erzeugt hat — genau
> so machen es die beiden JS-gebauten Klassenlisten seit dem Hotfix
> „Fragenwand in Klassenlisten". Der Klick selbst war ein echter
> Zeigerklick.

## Umgebungsnotizen (keine Befunde am Code)

1. **Die WordPress-Anmeldung im Prüfbrowser ging während des Laufs verloren**
   (Zugriffsprotokoll 22:28:26, `wp-login.php?action=logout`, unmittelbar nach
   einem Aufruf von `wp-admin/profile.php`). Ausgelöst wurde das nicht durch
   die Prüfung — die letzte Browser-Aktion lag 28 Sekunden davor.
   **Folgenlos für die Ergebnisse, im Gegenteil:** Der abgemeldete Zustand ist
   Voraussetzung für Gruppe 2. Der Betreiber hat sich anschließend wieder
   angemeldet; damit konnte Prüfung 4c nachgeholt werden (Befund B2).
2. **Die Startseite lieferte einmalig HTTP 500** (22:28-Umfeld,
   `Maximum execution time of 30 seconds exceeded` in
   `themes/fos-online-schulbuch/functions.php`). Das ist die in `CLAUDE.md`
   beschriebene Zeitüberschreitung der **Glossar-Autoverlinkung** bei Seiten
   ohne `_glossar_scan_version` — ein Theme-Bestandsproblem, kein Bezug zu
   diesem Vorhaben (Phase 2 hat keine PHP-Zeile geändert). Unmittelbar danach
   lieferte dieselbe Adresse wieder 200.
3. **`document.hidden` musste im Seitenkontext steuerbar gemacht werden.** Der
   Browser-Bereich dieser Umgebung meldet dauerhaft `hidden === true`, sobald
   er nicht im Vordergrund steht — und Regel 6 hält dann beide Zeitgeber an.
   Ohne diesen Eingriff wäre **keine** der Messungen zustande gekommen. Die
   ausgelieferte Datei blieb unangetastet; überschrieben wurde nur die
   Eigenschaft im laufenden Dokument.

---

## Aufgeräumt

| | |
|---|---|
| Testklasse und Abhängige | entfernt — 0 Klassen, 0 Notizen, 0 Zeichnungen, 0 Seitenzuordnungen |
| Sitzungs-Transient | weg |
| Pulsdateien / `.tmp`-Reste | 0 / 0 |
| `cbd_klassenpuls_takt` | zurück auf `10` |
| `klassenpuls.js` auf dem Testserver | wieder der Phase-2-Stand (gegen das Repository verglichen) |
| `wp-content/mu-plugins/` | nur die reguläre `wp-migrate-db-pro-compatibility.php` |
| Webroot | kein Prüfskript |

Alle Hilfsskripte lagen im Scratchpad und liefen ausschließlich per PHP-CLI.

---

## Messwert für AP-3.2

> **Eine Neuladung je Minute** bei durchgehendem Zeichnen der Lehrperson
> (20 Speicherungen in 60 Sekunden, serverseitig im Zugriffsprotokoll gezählt).
>
> Der Mindestabstand von 60 Sekunden hält unter dem zweisekündigen Takt
> unverändert. `AP-3.2` muss also **nicht** gegen eine erhöhte Neuladefrequenz
> anarbeiten — der schnelle Takt verkürzt nur die Zeit bis zur **ersten**
> Neuladung.
