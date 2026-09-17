# Klickliste: Voraussetzungen im KAS prüfen

_Angelegt: 2026-09-17 · Gehört zu `PLAN-Schneller-Klassenpuls.md`, AP-0.2_

Sieben Fragen über die Produktivumgebung, die im Plan gebraucht werden und die
ein Agent nicht selbst beantworten kann — sie brauchen eine Anmeldung im KAS
(`https://kas.all-inkl.com`).

**So ausfüllen:** Antwort direkt unter die jeweilige Frage in die Zeile
**Antwort:** schreiben. Wenn eine Frage nicht beantwortet werden kann, dort
`nicht beantwortet` eintragen und kurz sagen, warum — **bitte nicht raten.**
Eine geratene Antwort auf Frage 5 oder 6 fiele erst in Phase 2 auf, wenn auf
der Produktivinstallation nichts funktioniert.

**Menüwege sind aus der Dokumentation abgeleitet.** Weicht das KAS ab, bitte
notieren, wo der Punkt tatsächlich lag — dann stimmt die Anleitung beim
nächsten Mal.

**Zeitbedarf:** rund 20 Minuten, davon zwei Prüfungen mit Dateiupload.

---

## Frage 1 — Welches Produkt? (die wichtigste Frage)

**Warum:** Sie entscheidet, ob Phase 4 des Plans (gehaltene Verbindung,
Latenz unter 1 Sekunde) überhaupt stattfindet. Bei einem Webhosting-Tarif
entfällt sie vollständig — laut all-inkl-Support sind selbstdefinierte
WebSockets im Shared Hosting „kaum umsetzbar und nicht empfehlenswert".

**Klickweg:** Nach der Anmeldung im KAS steht das Produkt meist oben in der
Kopfzeile. Sonst: linkes Menü → **Vertragsdaten** (oder „Meine Verträge" /
„Produktübersicht").

**Gefragt ist der genaue Produktname**, zum Beispiel:
- Webhosting-Tarif: `Privat`, `PrivatPlus`, `Premium`, `Business`
- `Managed Server`
- `Server` (Root-/VPS-Server)

**Antwort:**

---

## Frage 2 — Cronjobs verfügbar?

**Warum:** Der tägliche Aufräumdurchlauf für verwaiste Pulsdateien (AP-1.7)
hängt an WordPress' eigenem Zeitplan, und der läuft nur, wenn jemand die
Seite besucht. Ein echter Cronjob macht ihn zuverlässig.

**Klickweg:** Linkes Menü → **Tools** → **Cronjobs**.

**Gefragt:**
- Gibt es den Menüpunkt überhaupt? (Er fehlt im kleinsten Tarif.)
- Wenn ja: Wie viele Cronjobs sind laut Anzeige enthalten?
- Wenn ja: Welches **kleinste Intervall** lässt das Formular zu? (Beim
  Anlegen eines Cronjobs gibt es ein Feld für Zeitpunkt oder Intervall —
  gefragt ist die kleinste wählbare Angabe, z. B. „alle 5 Minuten" oder
  „alle 1 Minute".) **Den Cronjob dabei nicht speichern** — nur ansehen und
  abbrechen.

**Antwort:**

---

## Frage 3 — Welche PHP-Version läuft produktiv?

**Warum:** Das Plugin ist auf PHP 7.4 ausgelegt (Zielumgebung 7.4.33) und
wird weiterhin dagegen geprüft. Läuft die Installation längst auf PHP 8, ist
das kein Problem — aber es ändert, welche Einstellungsdatei greift (siehe
Frage 4).

**Klickweg:** Linkes Menü → **Domain** → bei der Hauptdomain auf das
Bearbeiten-Symbol → Abschnitt **PHP-Version** / **PHP-Einstellungen**.

**Gefragt:** die eingestellte Version, genau wie angezeigt (z. B. `7.4`,
`8.1`, `8.3`).

**Antwort:**

---

## Frage 4 — Wird eine `.user.ini` ausgewertet?

**Warum:** Nur für die optionale Phase 4 relevant (dort müsste die maximale
Laufzeit erhöht werden). Ab PHP 8 wirken `php_value`-Anweisungen in der
`.htaccess` bei all-inkl nicht mehr; stattdessen braucht es eine `.user.ini`.

**Diese Frage kann übersprungen werden, wenn Frage 1 einen Webhosting-Tarif
ergeben hat** — dann findet Phase 4 ohnehin nicht statt. In dem Fall bitte
`entfällt (Webhosting-Tarif)` eintragen.

**Klickweg:**
1. Im KAS-Dateimanager (oder per FTP) im Web-Root eine Datei
   `phpinfo-test.php` anlegen mit genau diesem Inhalt:
   ```php
   <?php phpinfo();
   ```
2. Sie im Browser aufrufen und den Wert von **`max_execution_time`** notieren
   (Spalte „Local Value").
3. Im selben Verzeichnis eine Datei `.user.ini` anlegen mit genau dieser
   Zeile:
   ```
   max_execution_time = 77
   ```
4. **Etwa fünf Minuten warten** (PHP liest `.user.ini` nur alle paar Minuten
   neu — `user_ini.cache_ttl`, Vorgabe 300 Sekunden), dann `phpinfo-test.php`
   erneut aufrufen und den Wert erneut notieren.
5. **Beide Dateien anschließend wieder löschen** — `phpinfo-test.php` **und**
   `.user.ini`. Das ist nicht optional: `phpinfo()` gibt Serverinterna preis.

**Gefragt:** der Wert vorher, der Wert nachher, und ob beide Dateien gelöscht
sind.

**Antwort:**
- `max_execution_time` vorher:
- `max_execution_time` nachher:
- beide Dateien gelöscht: ja / nein

---

## Frage 5 — Ist `wp-content/uploads/` beschreibbar? (trägt das ganze Vorhaben)

**Warum:** Die Pulsdatei wird dorthin geschrieben. Ist das Verzeichnis nicht
beschreibbar, funktioniert die Beschleunigung nicht — dann fällt alles auf
das heutige Verhalten zurück, und das Vorhaben hätte keinen Nutzen.

**Klickweg:** WordPress-Admin der Produktivinstallation → **Medien** →
**Datei hinzufügen** → eine kleine Bilddatei hochladen.

**Gefragt:** Gelingt der Upload? Erscheint das Bild in der Mediathek?
**Bild danach wieder löschen.**

**Antwort:**

---

## Frage 6 — Wird eine `.json`-Datei aus `uploads` ausgeliefert? (trägt das ganze Vorhaben)

**Warum:** Der Browser holt die Pulsdatei direkt bei Apache, ohne PHP. Sperrt
eine Server- oder Sicherheitsregel `.json` in `uploads` aus, ist der ganze
Entwurf hinfällig — dann bitte **vor** Phase 1 Bescheid sagen.

**Klickweg:**
1. Im KAS-Dateimanager (oder per FTP) eine Datei
   `wp-content/uploads/test-puls.json` anlegen mit genau diesem Inhalt:
   ```json
   {"a":1}
   ```
2. Sie im Browser direkt aufrufen:
   `https://<deine-domain>/wp-content/uploads/test-puls.json`
3. **Datei danach wieder löschen.**

**Gefragt:**
- Erscheint der Inhalt `{"a":1}`, oder kommt eine Fehlerseite (403 / 404)?
- Welchen Inhaltstyp meldet der Browser? (In den Entwicklerwerkzeugen unter
  **Netzwerk** → die Anfrage anklicken → Kopfzeile `Content-Type`. Erwartet
  wird `application/json`. Ein anderer Wert ist kein Hindernis, aber gut zu
  wissen.)
- Datei gelöscht: ja / nein

**Antwort:**

---

## Frage 7 — Zeigt `uploads` eine Verzeichnisauflistung?

**Warum:** Das Plugin legt vorsorglich eine `.htaccess` mit `Options -Indexes`
im neuen Unterordner an. Die Antwort sagt, ob das nötig oder nur eine zweite
Absicherung ist.

**Klickweg:** Im Browser aufrufen:
`https://<deine-domain>/wp-content/uploads/`

**Gefragt:** Erscheint eine Liste der Dateien, eine leere Seite, oder eine
Fehlerseite (403)?

**Antwort:**

---

## Nach dem Ausfüllen

Die ausgefüllte Liste zurückgeben. Die Antworten wandern dann in
`docs/voraussetzungen-kas.md`, zusammen mit zwei daraus abgeleiteten
Aussagen:

- **„Phase 4 findet statt: ja/nein"** — `ja` nur bei Managed Server oder
  Root-/VPS-Server (Frage 1).
- **„Pulsdatei tragfähig: ja/nein/eingeschränkt"** — abgeleitet aus den
  Fragen 5 und 6. Bei `nein` ist das Vorhaben in seiner geplanten Form
  hinfällig und muss vor Phase 1 neu entschieden werden.
