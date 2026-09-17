# Anfrage an die Hosting-Administration

_Angelegt: 2026-09-17 · Gehört zu `PLAN-Schneller-Klassenpuls.md`, AP-0.2_

Fertig formulierte Nachricht für die Person mit KAS-Vollzugriff. Sie
beantwortet die beiden noch offenen Fragen der Klickliste
(`docs/KLICKLISTE-Voraussetzungen-KAS.md`). Alles Übrige ist bereits geklärt
(`docs/voraussetzungen-kas.md`).

**Zeitbedarf für die angefragte Person: rund 5 Minuten** — wenn Frage 1 einen
Webhosting-Tarif ergibt, sogar nur eine Minute, weil Frage 2 dann entfällt.

---

## Zum Kopieren

**Betreff:** Zwei kurze Fragen zum all-inkl-Hosting von chemiefos.fos-meran.it

Hallo,

ich baue gerade eine Erweiterung für die Schulbuch-Website
`chemiefos.fos-meran.it` (WordPress, all-inkl). Fast alles Nötige konnte ich
selbst feststellen — zwei Dinge sehe ich aber nur im KAS, und dafür fehlt mir
der Zugriff.

**Frage 1 — Welches all-inkl-Produkt steckt hinter dem Vertrag?**

Gemeint ist der genaue Produktname, also eines von:

- ein Webhosting-Tarif (`Privat`, `PrivatPlus`, `Premium` oder `Business`)
- ein `Managed Server`
- ein `Server` (Root- bzw. VPS-Server)

Zu finden im KAS meist oben in der Kopfzeile, sonst im linken Menü unter
**Vertragsdaten** (je nach Ansicht auch „Meine Verträge" oder
„Produktübersicht").

*Warum ich das brauche:* Davon hängt ab, ob eine bestimmte, optionale
Ausbaustufe überhaupt technisch möglich ist. Bei einem Webhosting-Tarif lasse
ich sie ersatzlos weg — die Erweiterung funktioniert auch ohne sie
vollständig. Es geht also **nicht** um einen Tarifwechsel, nur um die
Einordnung.

**Frage 2 — Wird eine `.user.ini` ausgewertet?** *(nur relevant, falls
Frage 1 einen Managed Server oder Root-Server ergibt — bei einem
Webhosting-Tarif bitte einfach überspringen)*

1. Im Web-Root eine Datei `phpinfo-test.php` anlegen mit dem Inhalt
   `<?php phpinfo();`
2. Im Browser aufrufen und den Wert von **`max_execution_time`** notieren
   (Spalte „Local Value")
3. Im selben Verzeichnis eine Datei `.user.ini` anlegen mit der Zeile
   `max_execution_time = 77`
4. Etwa **fünf Minuten warten** (PHP liest diese Datei nur alle paar Minuten
   neu), dann `phpinfo-test.php` erneut aufrufen und den Wert erneut notieren
5. **Beide Dateien anschließend wieder löschen** — `phpinfo()` gibt
   Serverinterna preis und sollte nicht dauerhaft erreichbar sein

Gefragt sind nur die beiden Werte (vorher / nachher).

**Und eine freiwillige Zusatzfrage, falls es ohne Aufwand beantwortbar ist:**
Der Server meldet sich als `nginx`. Läuft dahinter noch ein Apache, oder
beantwortet nginx die Anfragen allein? Das entscheidet nur darüber, ob eine
`.htaccess` überhaupt gelesen wird — die Erweiterung funktioniert in beiden
Fällen, ich würde die Dokumentation nur gern korrekt halten.

Vielen Dank!

---

## Was mit den Antworten passiert

| Antwort | Folge im Projekt |
|---|---|
| Frage 1 = Webhosting-Tarif | Phase 4 des Plans entfällt vollständig; Frage 2 ist gegenstandslos |
| Frage 1 = Managed/Root-Server | Phase 4 wird ausgearbeitet — aber erst, wenn die Messung in AP-3.3 zeigt, dass die 2–3 Sekunden aus Phase 2 nicht genügen |
| Frage 2 (beide Werte) | fließt in die Vorbereitung von Phase 4 ein |
| Zusatzfrage (nginx/Apache) | präzisiert nur die Begründung zur `.htaccess` in `AP-1.2`; kein Codeänderungsbedarf in beiden Fällen |

**Keine der Antworten blockiert die laufende Arbeit.** Die Phasen 1 bis 3
sind davon unabhängig; gebraucht werden sie erst am Ende von Phase 3.
