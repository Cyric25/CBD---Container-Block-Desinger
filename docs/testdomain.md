# Entfernte Testdomain

_Angelegt: 2026-09-17 · Gehört zu `PLAN-Schneller-Klassenpuls.md`, AP-0.3_

> # ⊘ DIESE EINRICHTUNG ENTFÄLLT
>
> **Entscheidung des Betreibers vom 2026-09-17:** Es wird keine eigene
> Testdomain eingerichtet. Die Messung aus `AP-3.3` findet stattdessen auf
> der **Produktivseite** statt, außerhalb der Unterrichtszeit und mit einer
> Parallelität von höchstens 25.
>
> **Dieses Dokument bleibt als Anleitung stehen**, falls später doch eine
> getrennte Testumgebung gebraucht wird — etwa für eine schulweite
> Ausweitung, bei der man den Knick der Sättigungskurve wirklich suchen
> müsste. Genau das bleibt bei der Messung auf der Produktivseite
> ausdrücklich unbelegt: Sie zeigt, dass der Betriebsfall weit unterhalb
> jeder Grenze liegt, nicht wo die Grenze ist.
>
> Begründung und Auflagen: `PLAN-Schneller-Klassenpuls.md`, `AP-3.3`.

Eine Subdomain auf der echten all-inkl-Maschine mit eigener
WordPress-Installation. Sie dient der Lastmessung in AP-3.3.

**Warum es sie braucht:** Die bisherige Lastmessung
(`docs/messung-klassenpuls.md`) lief auf einem lokalen Windows-Rechner ohne
Fremdlast und mit warmem OPcache. Ihr eigener Abschnitt 7 hält fest: Die
**Form** der Sättigungskurve überträgt sich auf all-inkl voraussichtlich, die
**absoluten Zahlen nicht** — dort dürfte der Knick bei niedrigerer
Parallelität liegen. Eine Messung auf der echten Maschine ist deshalb die
Voraussetzung dafür, überhaupt beurteilen zu können, ob der Entwurf trägt.

**Warum nicht auf der Produktivinstallation:** Eine Sättigungsmessung treibt
den Server absichtlich in den Stau. Das gehört nicht auf eine Installation,
auf der Unterricht stattfindet.

> **⚠️ Diese Datei liegt in einem öffentlichen Repository.**
> Hier stehen **keine** Passwörter, **keine** Datenbank-Zugangsdaten und
> **keine** Klassentokens. Nur Adressen, Pfade und Beobachtungen.

---

## 1. Einrichtung (führt der Betreiber im KAS aus)

**Zeitbedarf:** rund 45 Minuten.

### 1.1 Subdomain und Datenbank

1. KAS → **Domain** → **Subdomain anlegen**. Vorschlag: `test.<hauptdomain>`.
2. Für die Subdomain **SSL aktivieren** (Let's Encrypt). Ohne HTTPS verhält
   sich der Browser bei manchen Prüfungen anders als produktiv.
3. KAS → **Datenbank** → **neue Datenbank anlegen**. **Nicht** die Datenbank
   der Produktivinstallation mitbenutzen — eine Fehlbedienung bei der Messung
   darf den Unterrichtsbetrieb nicht berühren.

### 1.2 WordPress und die drei Komponenten

4. WordPress in das Verzeichnis der Subdomain installieren.
5. Theme **„FOS Online Schulbuch"** installieren und aktivieren.
6. Plugin **Container Block Designer** im aktuellen Stand installieren und
   aktivieren. Maßgeblich ist der Stand, der auch produktiv läuft — sonst
   misst man etwas anderes, als man ausliefert.
7. Plugin **„Eigene WP Blocks"** installieren und aktivieren.

### 1.3 Testdaten

8. Klassenmodus aktivieren (Container Designer → Einstellungen).
9. **Eine** Testklasse anlegen.
10. **Eine** Testseite mit mindestens **drei** Container-Blöcken anlegen und
    der Klasse zuordnen.
11. Genau **einen** Container freigeben — damit in `wp_cbd_drawings` ein
    Datensatz existiert und die Signaturen etwas zu rechnen haben.

### 1.4 Fehlerlog einschalten

12. In der `wp-config.php` **der Testdomain**:
    ```php
    define('WP_DEBUG', true);
    define('WP_DEBUG_LOG', true);
    define('WP_DEBUG_DISPLAY', false);
    ```
    AP-3.3 wertet `wp-content/debug.log` aus, um nachzuweisen, dass die
    Lastmessung keine Warnungen erzeugt hat.

> **Auf der Produktivinstallation muss `WP_DEBUG_LOG` ausdrücklich AUS
> bleiben.** Bekannter Bestandsbefund: Jede Anfrage erzeugt rund 34
> Boot-Protokollzeilen (Registrierung der Container-Blöcke, „Eigene WP
> Blocks"). Bei 25 Schülern im Zehn-Sekunden-Takt wären das etwa 7.600
> Logzeilen je Minute.

### 1.5 Fertigmeldung

13. Bescheid geben. Dann laufen die Prüfungen aus Abschnitt 2.

---

## 2. Prüfungen nach der Einrichtung (führt der Agent aus)

_Ergebnisse werden nach der Fertigmeldung hier eingetragen._

| # | Prüfung | Erwartung | Ergebnis |
|---|---|---|---|
| 1 | Startseite der Testdomain aufrufen | HTTP 200, WordPress rendert, Browser-Konsole ohne Error | _offen_ |
| 2 | `/wp-json/cbd/v1/klassenpuls` ohne Parameter | HTTP 404, Code `cbd_puls_not_available` — Nachweis, dass das Plugin dort in derselben Fassung läuft wie lokal | _offen_ |
| 3 | Testseite im Klassenmodus (`?classroom=<id>&token=<t>`) | der freigegebene Container ist sichtbar, die übrigen nicht | _offen_ |
| 4 | Abweichungen zur lokalen Umgebung | dokumentiert | _offen_ |

---

## 3. Eckdaten

_Nach der Einrichtung auszufüllen._

| Angabe | Wert |
|---|---|
| URL der Testdomain | _offen_ |
| WordPress-Verzeichnis (Serverpfad) | _offen_ |
| CDB-Designer-Version dort | _offen_ |
| Version „Eigene WP Blocks" dort | _offen_ |
| Theme-Version dort | _offen_ |
| Name der Testklasse | _offen_ |
| `page_id` der Testseite | _offen_ |
| PHP-Version der Subdomain | _offen_ |

---

## 4. Beobachtete Abweichungen zur lokalen Umgebung

_Nach der Einrichtung auszufüllen. Alles, was sich anders verhält als auf
`http://fos.localhost:8080`, gehört hierher — es ist genau das, was die
lokale Messung nicht zeigen konnte._

---

## 5. Aufräumen nach Abschluss des Vorhabens

Die Testdomain kann nach AP-3.doc bestehen bleiben (sie ist für künftige
Vorhaben nützlich) oder abgebaut werden. Beim Abbau: Subdomain, Datenbank
und Verzeichnis im KAS entfernen. Die Entscheidung trifft der Betreiber.
