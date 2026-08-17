# Klickliste zur Abnahme AP-4.3 — Inline-Blockreferenz und hierarchische Zielauswahl

_Erstellt am 2026-08-17 zu `docs/PLAN-Inline-Blockreferenz.md`, AP-4.3, Schritt 8.
CDB-Designer **3.1.92**, installiert auf dem Testserver aus dem ZIP
`dist/container-block-designer-3.1.92.zip`._

**Die Nummern in dieser Liste sind die Abschnittsnummern der Prüfseiten
selbst** (`A1`…`A5`, `B1`…`B12`, `C`). Jede Überschrift, die hier genannt
wird, steht wortgleich auf der Seite — es gibt keine zweite, konkurrierende
Zählung. Wo eine Prüfung keinen Seitenabschnitt hat, steht sie unter einem
eigenen Durchgang.

---

## 0. Vorbereitung

**Server:** läuft schon. Falls nicht: `C:\allinkl-testserver\start-server.cmd`.
Bei HTTP 503 die Datei `.maintenance` im WordPress-Verzeichnis und
`wp-content/upgrade/wordpress-*` löschen.

**Zugänge**

| Rolle | Benutzer | Passwort |
|---|---|---|
| Administrator | `admin` | `Testserver2026!` |
| Block-Redakteur | `redakteur` | `Redakteur2026!` |

**Die Prüfseiten**

| Abschnitte | Seite | ID | URL |
|---|---|---|---|
| `A1`–`A5` | AP43 Prüfseite A (nur Inline) | 75 | http://fos.localhost:8080/index.php/ap43-5-klasse/ap43-biochemie/ap43-enzymkinetik/ap43-pruefseite-a-nur-inline/ |
| `B1`–`B12` | AP43 Prüfseite B (Block + Inline) | 76 | http://fos.localhost:8080/index.php/ap43-5-klasse/ap43-biochemie/ap43-enzymkinetik/ap43-pruefseite-b-block-inline/ |
| `C` | AP43 Prüfseite C (Redakteur) | 78 | http://fos.localhost:8080/index.php/ap43-5-klasse/ap43-biochemie/ap43-enzymkinetik/ap43-pruefseite-c-redakteur/ |

**Die Prüfhierarchie** (Grund: AK7 verlangt echte Hierarchie, die
Bestandsseiten 43–47 sind flach)

```
#69  AP43 5. Klasse          Ebene 0   1 Zielblock   „Überblick 5. Klasse"
 └ #70  AP43 Biochemie       Ebene 1   1 Zielblock   „Fachprofil Biochemie"
    ├ #71  AP43 Enzymkinetik Ebene 2   2 Zielblöcke  „Merksatz…" / „Aufgaben…"
    │   ├ #72  AP43 Michaelis-Menten   Ebene 3   2 Zielblöcke
    │   ├ #75  AP43 Prüfseite A        Ebene 3   (keine Zielblöcke)
    │   ├ #76  AP43 Prüfseite B        Ebene 3   1 Zielblock
    │   └ #78  AP43 Prüfseite C        Ebene 3   (keine Zielblöcke)
    └ #73  AP43 Leerer Zweig  Ebene 2  KEIN Zielblock
        └ #74  AP43 Leeres Blatt Ebene 3 KEIN Zielblock
```

**Im Browser bitte offen halten:** Entwicklerwerkzeuge, Reiter **Netzwerk**
(für `B3` gegen `B4`) und Reiter **Konsole** (dort darf während der ganzen
Liste **keine** rote Fehlermeldung erscheinen).

---

## Durchgang 1 — Frontend, angemeldet als **Administrator**

### Seite A (ID 75) — die Seite **ohne** Blockreferenz-Block

Diese Seite ist der eigentliche Grund für die Trennung: Sie enthält
ausschließlich Inline-Verweise. Wäre die Script-Einbindung aus dem
Inhaltsfilter falsch, wären hier alle Verweise stumm.

- [ ] **A1 — Verweis mitten im Fließtext (andere Seite, Nachladen)**
      Klick auf „diesem Block" mitten im Satz.
      *Erwartet:* Ein Overlay öffnet sich, Titel „Die Michaelis-Menten-Gleichung",
      darin der Zielblock. Schließen mit `Esc` **und** mit dem
      Schließen-Knopf. Der Fokus muss danach wieder im Text liegen.
      → AK3

      **Achtung, erwartete Eigenart — kein Fehler dieser Erweiterung:** Die
      Formel im nachgeladenen Block erscheint hier **unformatiert als
      Rohtext** (`v = \frac{v_{max}...}`). Grund: KaTeX wird nur geladen,
      wenn die **aufgerufene** Seite selbst eine Formel enthält, und Seite A
      enthält keine. Das gilt genauso für den Bestandsblock — siehe `B2`
      unten. Bitte nur zur Kenntnis nehmen und **nicht** als Befund melden;
      Einordnung steht in der Übergabenotiz.

- [ ] **A1 — Textfluss und die Glyphe** (bitte genau hinsehen)
      *Erwartet:* Der Verweis steht in der Zeile wie ein normaler Link,
      unterstrichen, dahinter ein kleiner Pfeil **↗** — **kein**
      Ersatzkästchen (□), **keine** vergrößerte Zeilenhöhe, **kein**
      Zeilenversatz gegenüber der Nachbarzeile. Beim Überfahren wird nur die
      Unterstreichung dicker, der Text darf **nicht** springen.
      Bitte zusätzlich auf einem echten Gerät (Handy/Tablet) ansehen.
      → AK9 von AP-4.2

- [ ] **A2 — Zweiter Verweis auf eine andere Ebene der Hierarchie**
      Beide Verweise („Merksatz Enzymkinetik", „Überblick 5. Klasse") anklicken.
      *Erwartet:* Je ein Overlay mit dem jeweiligen Block. Der zweite Verweis
      zeigt auf Ebene 0, der erste auf Ebene 2 — beide Wege müssen tragen.

- [ ] **A3 — Verweis auf die gesperrte Seite 64**
      Als **angemeldeter** Administrator anklicken.
      *Erwartet:* Der Inhalt „Geheimer Lösungsblock" **erscheint** — eine
      Lehrperson darf ihn sehen.

- [ ] **A4 — Verweis auf einen gelöschten Zielblock**
      Anklicken.
      *Erwartet:* Das Overlay öffnet und zeigt „Dieser Block ist nicht
      verfügbar." Die Seite bleibt heil, kein Absturz, keine Fehlermeldung
      in der Konsole außer der erwarteten 404-Meldung des Netzwerks.

- [ ] **A5 — Verweis in einer Liste und in einer Überschrift**
      Beide anklicken.
      *Erwartet:* Beide öffnen das Overlay. In der Überschrift darf der
      Pfeil ↗ die Überschrift nicht umbrechen.

### Seite B (ID 76) — die Seite mit **beidem**

- [ ] **B1 — Eigener Zielblock dieser Seite**
      Nur ansehen: Der Container-Block „Zielblock auf dieser Seite" muss
      normal dastehen (Icon in der Kopfzeile).

- [ ] **B2 — Blockreferenz-Block (Bestandsfunktion, Modal)**
      Auf die Karte „Blockreferenz-Block auf die Gleichung" klicken.
      *Erwartet:* Overlay wie bisher. **Das ist die Regressionsprüfung des
      Bestandsblocks** — er muss sich genau wie vor der Erweiterung verhalten.
      → AK9 von AP-4.3

      **Bitte hier den Vergleich zu `A1` ziehen:** Auch dieses Overlay zeigt
      die Formel als Rohtext, obwohl es der **alte** Block ist und der
      Inline-Verweis damit nichts zu tun hat. Das belegt, dass die Eigenart
      aus `A1` älter ist als diese Erweiterung.

- [ ] **B3 — Inline-Verweis auf einen Block DERSELBEN Seite (DOM-Klon)**
      Netzwerk-Reiter leeren, dann „diesen Verweis" anklicken.
      *Erwartet:* Overlay öffnet **ohne jeden Netzwerkaufruf** an
      `block-html`. Der geklonte Block muss vollständig sein; die
      Aktionsleiste (Klappen/Kopieren/Screenshot/PDF) darf im Overlay
      **nicht** erscheinen.
      → AK4, erste Hälfte

- [ ] **B4 — Inline-Verweis auf eine ANDERE Seite (Nachladen)**
      Netzwerk-Reiter leeren, dann „die klappbare Herleitung" anklicken.
      *Erwartet:* **Ein** Aufruf an `…/cbd/v1/block-html?post_id=72&stable_id=cbd-ap43-seite-02`
      mit HTTP 200, danach das Overlay. Der Block ist klappbar gestaltet —
      im Overlay muss er **aufgeklappt** erscheinen.
      → AK4, zweite Hälfte

- [ ] **B5 — Inline-Verweis auf die gesperrte Seite 64**
      Angemeldet anklicken. *Erwartet:* Inhalt erscheint (wie `A3`).

- [ ] **B6 — Blockreferenz-Block mit gelöschtem Ziel**
      Anklicken. *Erwartet:* „Dieser Block ist nicht verfügbar."

---

## Durchgang 2 — Frontend, **abgemeldet** (privates Fenster)

Bitte ein privates/Inkognito-Fenster nehmen, nicht abmelden — sonst sind die
Editor-Sitzungen von Durchgang 3 weg.

- [ ] **A1 (abgemeldet)** — Verweis anklicken.
      *Erwartet:* Overlay mit Inhalt. Eine nicht gesperrte Seite ist für alle
      lesbar.

- [ ] **A3 (abgemeldet) — der entscheidende Fall**
      Verweis auf den „Geheimen Lösungsblock" anklicken.
      *Erwartet:* Das Overlay zeigt **„Dieser Block ist nicht verfügbar."** —
      **niemals** den Inhalt. Wenn hier Inhalt erscheint, ist das ein
      Blocker und die Abnahme scheitert.
      → AK5

- [ ] **B5 (abgemeldet)** — dasselbe auf Seite B. Gleiche Erwartung.

- [ ] **B3 (abgemeldet)** — der DOM-Klon muss auch abgemeldet funktionieren
      (er braucht keine Autorisierung, der Block steht schon im DOM).

---

## Durchgang 3 — Editor von Seite B, **als Administrator**

Öffnen: Seiten → „AP43 Prüfseite B (Block + Inline)" → Bearbeiten.

**Zuerst, noch bevor etwas angeklickt wird:**

- [ ] **Blockgültigkeit beim Öffnen**
      *Erwartet:* **Kein** Block zeigt „Dieser Block enthält unerwarteten
      oder ungültigen Inhalt". Und: In der Kopfzeile darf **kein**
      „Speichern"-Zustand aktiv sein — das Öffnen allein darf die Seite
      nicht als geändert markieren.
      → AK6 von AP-4.1, AK7 von AP-4.2

      **Einschränkung, bitte beachten:** Die Blöcke, auf die es hier ankommt,
      sind die **Absätze mit Inline-Verweisen** (`B3`, `B4`, `B5`, `B11`) und
      die **zwei Blockreferenz-Blöcke** (`B2`, `B6`). Die Beispielblöcke in
      `B7` (Zitat, Tabelle, Knopf, Liste) habe ich als Markup von Hand
      geschrieben, damit du sie nicht selbst anlegen musst — sollte einer
      davon „unerwarteter Inhalt" zeigen, ist das **mein Gerüst**, nicht das
      Plugin. Dann bitte in `B7` den Block einmal über „Blockwiederherstellung
      versuchen" richten oder neu einfügen und **nicht** als Befund melden.

### Der Werkzeugleisten-Knopf

- [ ] **B7 — In welchen Blocktypen erscheint die Schaltfläche?**
      In jedem der sechs Beispiele Text markieren und in der schwebenden
      Werkzeugleiste nachsehen. Der Knopf trägt das Dashicon **`external`**
      (Kästchen mit Pfeil), Titel „Block-Verweis einfügen" — **nicht** das
      Ketten-Symbol des Link-Knopfs.

      | Blocktyp im Abschnitt B7 | erwartet |
      |---|---|
      | Überschrift Ebene 3 | Knopf **da** |
      | Listenelement | Knopf **da** |
      | Zitat (Absatz darin) | Knopf **da** |
      | Tabellenzelle | Knopf **da** |
      | Knopfbeschriftung (`core/button`) | Knopf **fehlt** — das ist richtig |
      | Bildunterschrift | **selbst einfügen**: irgendein Bild in die Seite ziehen, Unterschrift tippen, markieren. Knopf **da** |

      Der fehlende Knopf bei `core/button` ist **kein Fehler**: Gutenberg
      filtert dort Formate mit interaktivem `tagName` heraus
      (`withoutInteractiveFormatting`), und der Inline-Verweis ist ein `<a>`.
      → AK12 von AP-4.2 (bisher nur aus dem Gutenberg-Quelltext belegt,
      die Sichtprüfung fehlte)

- [ ] **B8 — Bestehender gewöhnlicher Link → Warnmeldung statt Dialog**
      Genau den Text „gewöhnlichen Link" markieren (der schon ein Link ist)
      und den neuen Knopf drücken.
      *Erwartet:* **Kein** Dialog. Statt dessen unten links eine Meldung:
      „Auf dem markierten Text liegt bereits ein Link. Entferne ihn zuerst —
      ein Verweis innerhalb eines Links ergäbe ungültiges HTML."
      → AK3 von AP-4.2

      Dieser Schritt prüft nur den Fall, in dem Markierung **und** Link
      deckungsgleich sind. Die zwei folgenden Schritte im selben Abschnitt
      `B8` sind die Fälle, die AP-4.rev als Blocker gefunden hat — sie
      brauchen die `format.js` **nach AP-4.fix2**.

- [ ] **B8 (Fall C) — Link _innerhalb_ der Markierung → auch hier
      Warnmeldung** *(der praxisnahe Fall)*
      Im selben Absatz den **ganzen ersten Satz** markieren, also von
      „Dieser Absatz enthaelt einen" bis einschließlich „gewoehnlichen Link."
      — die Markierung ist damit **breiter** als der Link und enthält ihn.
      Dann den neuen Knopf drücken.
      *Erwartet:* Genau wie bei `B8` — **kein** Dialog, dieselbe Warnmeldung.
      Vor AP-4.fix2 öffnete der Dialog hier, und das Übernehmen schrieb ein
      `<a>` **innerhalb** eines `<a>` in den Seiteninhalt.
      → AK1 von AP-4.fix2

      **Wenn hier ein Dialog aufgeht, bitte abbrechen und nichts
      übernehmen** — das gespeicherte Markup wäre dann ungültig und der
      Absatz beim nächsten Öffnen ein „Block enthält unerwarteten oder
      ungültigen Inhalt".

- [ ] **B8 (Fall D) — Markierung überlappt den Linkrand → auch hier
      Warnmeldung**
      Im selben Absatz eine Markierung ziehen, die **mitten im Linktext
      beginnt** und **hinter dem Link endet**: von „Link" (dem zweiten Wort
      des Linktextes) bis „Markiert man" hinein.
      *Erwartet:* **Kein** Dialog, dieselbe Warnmeldung. Auch dieser Fall
      erzeugte vor AP-4.fix2 ein verschachteltes `<a>`.
      → AK1 von AP-4.fix2

      *Gegenprobe, dass der Wächter nicht zu scharf geworden ist:* Danach
      nur „Markiert man genau diesen Linktext" markieren — also einen
      Bereich, der den Link **nicht** berührt. Dort **muss** der Dialog
      aufgehen. Mit „Abbrechen" schließen, es soll kein Verweis entstehen.

- [ ] **B9 — Knopfzustand bei leerer Markierung**
      Cursor in den Absatz setzen, **nichts** markieren.
      *Erwartet:* Der Knopf ist **deaktiviert** (ausgegraut, nicht klickbar).
      → AK2 von AP-4.2

- [ ] **B9/B4 — Knopfzustand bei Cursor IM Verweis** (das ist der
      Nachtrag aus AP-4.fix1, F3 — im Bestand nur quelltextbasiert geprüft)
      Cursor **in** den bestehenden Verweis „die klappbare Herleitung" in
      Abschnitt `B4` setzen, ohne etwas zu markieren.
      *Erwartet:* Der Knopf ist **bedienbar** (nicht ausgegraut) und sein
      Titel lautet jetzt „Block-Verweis **entfernen**".
      → AK3 von AP-4.fix1

- [ ] **B11 — Verweis entfernen**
      Cursor in „dieser Verweis" setzen (oder den Text markieren) und den
      Knopf drücken.
      *Erwartet:* Der Verweis verschwindet, **der Text bleibt stehen**.
      Danach `Strg+Z` — der Verweis kommt zurück.
      → AK6 von AP-4.2

### Der Dialog und die Kaskade — das Hauptstück

- [ ] **B10 — Neuen Verweis einfügen, Kaskade über vier Ebenen**
      Die Wörter „Sättigungskinetik der Enzyme" markieren, Knopf drücken.

      *Erwartet, Schritt für Schritt:*
      1. Ein Dialog „Verweis auf einen Container-Block" öffnet sich und zeigt
         oben den markierten Text.
      2. Zuerst ist **ein** Auswahlfeld da (oberste Ebene). „Übernehmen" ist
         **deaktiviert**.
      3. „AP43 5. Klasse" wählen → ein **zweites** Feld erscheint mit
         „AP43 Biochemie".
      4. „AP43 Biochemie" wählen → ein **drittes** Feld erscheint mit
         „AP43 Enzymkinetik". **„AP43 Leerer Zweig" darf hier NICHT
         auftauchen** — dieser Zweig hat keinen Zielblock.
      5. „AP43 Enzymkinetik" wählen → ein **viertes** Feld erscheint
         (Unterseiten: „AP43 Michaelis-Menten", „AP43 Prüfseite B") **und
         gleichzeitig** ein Feld „Ziel-Block" mit den zwei Blöcken der
         Themenseite. Beide dürfen zusammen sichtbar sein.
      6. „AP43 Michaelis-Menten" wählen → „Ziel-Block" zeigt nun deren zwei
         Blöcke.
      7. Einen Block wählen → „Übernehmen" wird **freigegeben**.
      → AK7 von AP-4.3, AK1/AK4 von AP-4.2

- [ ] **B10 — Ein Suchtreffer stellt die Kaskade auf den Trefferpfad**
      Im selben Dialog ins Suchfeld „Michaelis" eingeben und einen Treffer
      wählen.
      *Erwartet:* Die Auswahlfelder **oberhalb** stellen sich selbsttätig auf
      den Pfad des Treffers (5. Klasse → Biochemie → Enzymkinetik →
      Michaelis-Menten). Sie dürfen **nicht** auf dem alten Stand
      stehenbleiben.
      → AK13 von AP-4.2 (hängt daran, dass der Dialog `wert` zurückgibt)

- [ ] **B10 — Hinweis bei gesperrter Zielseite**
      Im Suchfeld „Geheimer" eingeben und den Block der gesperrten Seite
      wählen.
      *Erwartet:* Die Option ist mit **„(nur für Lehrpersonen)"**
      gekennzeichnet, und darunter erscheint ein gelber Hinweis: „Diese Seite
      ist für Lehrpersonen reserviert. Der Verweis öffnet für Schülerinnen
      und Schüler nur innerhalb einer Klassensitzung, in der der Block als
      behandelt markiert ist."
      → letzte Zeile von AK5

- [ ] **B10 — Abbrechen ändert nichts**
      „Abbrechen" drücken. *Erwartet:* Der markierte Text bleibt unverändert,
      kein Verweis entsteht.

- [ ] **B10 — Übernehmen**
      Erneut öffnen, „Die Michaelis-Menten-Gleichung" wählen, „Übernehmen".
      *Erwartet:* Der markierte Text ist jetzt ein Verweis mit Pfeil ↗.

- [ ] **Speichern, Seite neu laden, Editor erneut öffnen**
      *Erwartet:* **Kein** „unerwarteter oder ungültiger Inhalt", der neue
      Verweis steht noch da, und die Seite ist beim Öffnen **nicht** als
      geändert markiert.
      → AK7 von AP-4.2, AK6 von AP-4.1

### Die Seitenleiste des Blockreferenz-Blocks

- [ ] **B2 — Vorbelegung eines Bestandsblocks**
      Den Blockreferenz-Block in Abschnitt `B2` anklicken.
      *Erwartet:* Die Seitenleiste zeigt die Kaskade, und zwar **schon auf
      dem Pfad des gespeicherten Ziels** (5. Klasse → Biochemie →
      Enzymkinetik → Michaelis-Menten), Ziel-Block „Die
      Michaelis-Menten-Gleichung". Im Canvas steht die Zusammenfassung
      „Seite: … / Block: …" — **nicht** „Keine Container-Blöcke gefunden".
      → AK2/AK9 von AP-4.1

- [ ] **B6 — Gelöschtes Ziel darf nicht verloren gehen**
      Den Blockreferenz-Block in Abschnitt `B6` anklicken (sein Ziel
      `cbd-ap43-geloescht-777` existiert nicht).
      *Erwartet:* Das gespeicherte Ziel geht **nicht von selbst verloren**;
      es gibt **keine** anklickbare Option „(gespeichertes Ziel)", die es beim
      Anklicken löschen würde. Die Seite darf durch das bloße Anklicken des
      Blocks **nicht** als geändert markiert werden.
      → AP-3.fix4 (im Bestand nicht automatisiert prüfbar)

- [ ] **B2 — Die übrigen Einstellungen sind unverändert**
      „Link-Text", „Icon anzeigen" und „Verhalten beim Klick" ausprobieren.
      *Erwartet:* Alle drei wirken wie bisher.
      → AK5 von AP-4.1

- [ ] **B12 — Neuen Blockreferenz-Block einfügen**
      Unter dem Absatz einen neuen Block „Block-Referenz" einfügen.
      *Erwartet:* Im Canvas der Platzhalter „Wähle einen Container-Block in
      den Einstellungen rechts aus.", in der Seitenleiste eine **leere**
      Kaskade mit **einem** Feld. Dann die Kaskade wie bei `B10`
      durchklicken. Speichern, neu laden, Ziel muss stehen.
      → AK1 von AP-4.1

---

## Durchgang 4 — als **Block-Redakteur** (Seite C, ID 78)

Privates Fenster, Anmeldung `redakteur` / `Redakteur2026!`.

- [ ] **C — Der Knopf ist auch für den Redakteur da**
      Seite C bearbeiten. In dem Absatz Text markieren.
      *Erwartet:* Die Schaltfläche erscheint, der Dialog öffnet, die Kaskade
      funktioniert wie in `B10`.

- [ ] **C — Verweis setzen und speichern**
      Einen Verweis auf einen beliebigen Zielblock setzen und speichern.
      *Erwartet:* Speichern gelingt, keine Fehlermeldung, der Absatz bleibt
      gültig. Danach die Seite im Frontend aufrufen: Der Verweis öffnet das
      Overlay.
      → AK6 (die Datenbankseite dieser Prüfung habe ich bereits gefahren,
      siehe Abschnitt „Schon geprüft" unten)

- [ ] **C — Das Menü des Redakteurs**
      *Erwartet:* Wie bisher — kein „Beiträge"-Menü, kein Zugriff auf
      „Container Designer". Der Inline-Verweis darf daran nichts geändert
      haben.

---

## Durchgang 5 — Beurteilung (nur Urteil, kein Klick)

- [ ] **Wirkt die Kaskade mit vier Ebenen erschlagend?**
      Der Plan hat das als ausdrückliche Frage an dich vorgesehen (Risiko
      „Kaskade mit vier Ebenen wirkt erschlagend"). Vier Auswahlfelder plus
      Block-Auswahlfeld können gleichzeitig sichtbar sein. Ist das im
      Alltag angenehm, oder wäre ein Aufklapp-Baum besser? Deine Antwort
      entscheidet, ob ein `AP-4.fixN` nötig ist.
      → AK11

- [ ] **Grenze (a): Fehlschlag beim Editorstart bleibt die Sitzung über**
      Scheitert der Abruf der Zielliste beim Öffnen des Editors, bleibt die
      Auswahl bis zum Neuladen der Seite leer (mit Fehlermeldung). Störend?
      Mein Urteil unten in der Übergabe; das letzte Wort hast du.

- [ ] **Grenze (b): Ein in einem zweiten Tab angelegter Container-Block
      erscheint erst nach Neuladen** in einer schon offenen Auswahl.
      Störend? Bitte selbst ausprobieren, wenn du magst: Seite B im Editor
      offen lassen, in einem zweiten Tab auf Seite 72 einen Container-Block
      hinzufügen und speichern, dann in Tab 1 die Kaskade öffnen — der neue
      Block fehlt bis zum Neuladen.

---

## Was ich schon geprüft habe — **nicht nötig, es zu wiederholen**

Diese Punkte sind serverseitig gemessen und belegt (Zahlen in der
Übergabenotiz von AP-4.3):

- Das ZIP enthält die drei neuen Dateien; der entpackte Autoloader lädt ohne
  Fatal; der Dev-Autoloader ist lokal wiederhergestellt.
- Version **3.1.92** steht im Plugin-Menü und in `CBD_VERSION`.
- Alle 13 PHP-Prüfharnische und `tools/test-block-auswahl.js` sind grün,
  `check-php74.php` grün, `node --check` auf alle fünf geänderten JS-Dateien
  grün.
- **Vertrag D** im gespeicherten Markup: genau die fünf Attribute, **keine**
  Spur von `data-display-mode`, `data-same-page`, `aria-haspopup`.
- **Vertrag E** in der Ausgabe: `data-display-mode="modal"` und
  `aria-haspopup="dialog"` an jedem Verweis, `data-same-page="true"` **nur**
  beim Verweis auf die eigene Seite (`B3`), `href` neu berechnet.
- **`view.js` wird auf Seite A eingebunden**, obwohl dort kein
  Blockreferenz-Block steht — der Fall, für den Seite A überhaupt existiert.
- **Der Endpunkt lehnt zeichengleich ab:** gesperrte Seite 64 anonym und
  nicht existierende Kennung liefern byte-identische 404-Antworten.
- **Als Block-Redakteur über die REST-Schnittstelle gespeichert:** alle fünf
  Attribute stehen unverändert in der Datenbank, LaTeX-Backslashes heil.
- **`aria-haspopup` wird von `wp_kses_post()` tatsächlich entfernt** — die
  Begründung für Vertrag E ist damit gemessen, nicht vermutet.
- Der Filter ist im Betrieb **idempotent**; Bestandsseiten 55 und 62 kommen
  **zeichengleich** durch ihn hindurch.
- Ladezeiten: `cbd/v1/blocks` 0,28–0,38 s, `cbd/v1/seitenbaum` 0,29–0,36 s,
  Seiten mit und ohne Verweise gleich schnell.
- Bei **deaktiviertem Plugin** sind die Verweise gewöhnliche Links zur
  Zielseite, `view.js` fehlt, die serverseitigen Attribute fehlen, und der
  gespeicherte Inhalt ist unverändert (gleiche MD5-Summen).
- `debug.log`: **keine** neue Warnung, Notice, Deprecation oder Fatal.
- **Der KaTeX-Wachposten ist unberührt:** `includes/class-latex-parser.php`
  und `assets/js/latex-renderer.js` haben seit `vor-phase-3` **null**
  geänderte Zeilen. Die Rohtext-Formel aus `A1`/`B2` ist deshalb kein
  Ergebnis dieser Erweiterung.

---

## Wenn etwas nicht stimmt

Bitte notieren: **Abschnittsnummer** (`A3`, `B10`, …), was du getan hast, was
passiert ist, und was in der Browser-Konsole stand. Behoben wird es nach dem
Plan in einem `AP-4.fixN` — nicht durch eine stille Änderung.
