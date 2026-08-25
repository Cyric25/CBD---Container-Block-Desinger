# Projektplan: Gestaffelte Elternseiten-Auswahl im Seitenimporter

_Erstellt am: 2026-08-25 · Letzte Aktualisierung: 2026-08-25_

## 0. Anweisungen für den ausführenden Agenten

Du arbeitest nach diesem Plan. Er ist die einzige Wahrheitsquelle – du hast
keinen Zugriff auf das Gespräch, in dem er entstand. Halte dich an diese Regeln:

**Rollen und Modelle:**
A. Wird die Abarbeitung von einem Orchestrator koordiniert (Opus), gilt:
   Der Orchestrator delegiert APs an Subagenten und implementiert NIEMALS
   selbst. Er gibt jedem Subagenten nur dessen AP-Text plus die Abschnitte
   0–3 dieses Plans als Kontext, prüft jede Rückmeldung gegen die
   Akzeptanzkriterien des APs, bevor er abhängige APs freigibt, und pflegt
   die Statustabelle.
B. Jedes AP nennt sein Ausführungsmodell (**Modell:** sonnet | opus).
   Subagenten mit genau diesem Modell starten.
C. Unabhängige APs derselben Phase (keine gemeinsamen Abhängigkeiten,
   disjunkte Dateien) dürfen parallel bearbeitet werden. APs, die dieselbe
   Datei ändern, nie parallel ausführen.

**Arbeitsweise:**
1. Bearbeite genau EIN Arbeitspaket (AP) pro Auftrag, sofern nicht anders beauftragt.
2. Prüfe vor Beginn die Abhängigkeiten deines APs in der Statustabelle
   (Abschnitt 8). Sind sie nicht ☑, brich ab und melde das.
3. Setze deinen AP-Status auf ◐ (in Arbeit), bevor du beginnst.
4. Bleibe strikt im Scope des APs. Fällt dir Verbesserungspotenzial außerhalb
   auf, notiere es in der Übergabenotiz – setze es nicht um.
5. Beachte die Nicht-Ziele (Abschnitt 2) und Constraints (Abschnitt 3).

**Tests (Pflicht, ein AP ohne bestandene Tests ist nicht fertig):**
6. Nach Abschluss: alle Akzeptanzkriterien einzeln nachweisen + die im AP
   definierten Tests durchführen. **Trage niemals ein Testergebnis oder
   einen Status als erledigt ein, bevor die Arbeit tatsächlich ausgeführt
   und das Ergebnis tatsächlich beobachtet wurde.**
7. Wo dieser Plan TDD vorsieht (siehe AP-1.1): Tests zuerst schreiben,
   Fehlschlag bestätigen, rote Tests committen, dann implementieren bis
   grün. **Tests niemals abändern, damit sie bestehen.** Hältst du einen
   Test für inhaltlich falsch, dokumentiere das in der Übergabenotiz und
   stoppe – die Entscheidung liegt beim Nutzer/Orchestrator.
8. Ergebnis ins Testprotokoll (Abschnitt 9) eintragen.
9. Erst dann Status auf ☑. Bei Fehlschlag: Status ✗ (blockiert), Ursache in
   die Übergabenotiz, nicht mit abhängigen APs weitermachen.
10. Nach dem letzten Implementierungs-AP der Phase zusätzlich: Integrationstest
    + Regressionscheck (bestehende Funktionalität, insbesondere die 97
    bestehenden Prüfungen in `tools/test-seitenbaum.php`, muss weiterhin
    funktionieren). Eintrag ins Testprotokoll.
11. Danach folgt das Review-AP (`AP-1.rev`): frischer Agent, hat kein AP
    dieser Phase implementiert, arbeitet ausschließlich lesend, verändert
    keine Datei. Kritische Befunde führen zu Korrektur-APs; die Phase ist
    erst danach abgeschlossen.

**PHP-7.4-Falle (projektspezifisch, WICHTIG):**
12. Das lokal installierte `php` läuft mit einer neueren Version als die
    Zielumgebung (7.4.33) und erkennt PHP-8.0-only-Syntax NICHT über
    `php -l`. Nach JEDER PHP-Änderung zusätzlich
    `php tools/check-php74.php` ausführen (parst gezielt gegen PHP 7.4 via
    nikic/php-parser) — nicht nur `php -l`. Ein AP mit PHP-Änderungen gilt
    erst als fertig, wenn BEIDE Prüfungen fehlerfrei sind.

**Übergabe:**
13. Fülle die Übergabenotiz deines APs aus: was geändert wurde, getroffene
    Entscheidungen, was für Folge-APs relevant ist.
14. Hat dein AP Dateien angelegt, verschoben oder wesentlich geändert:
    aktualisiere deren Zeilen in `reference_file_map.md`. Die umfassende
    Dokumentation (`CLAUDE.md`) wird im Dokumentations-AP am Phasenende
    (`AP-1.doc`) nachgezogen.
15. Aktualisiere „Letzte Aktualisierung" im Dateikopf dieses Plans.
16. Git: mindestens ein Commit mit AP-ID im Text, z. B.
    `AP-1.1: cbd/v1/seitenbaum um Entwuerfe-Parameter erweitert`. Nach
    jedem abgeschlossenen AP den Branch zum Remote pushen
    (`git push -u origin <branch>` bzw. `git push` bei bestehendem
    Upstream) – das Remote
    (`https://github.com/Cyric25/CBD---Container-Block-Desinger.git`) ist
    das Backup des Fortschritts. Branch erst nach bestandenem
    Integrationstest UND Review in `main` mergen, danach ebenfalls pushen.

**Umplanung:**
17. Zeigt sich während der Ausführung, dass der Plan nicht trägt (Review-
    Befunde, blockierte APs, falsche Annahmen), werden Korrektur-APs mit
    fortlaufender Nummer ergänzt (`AP-1.fix1`, …) und in Statustabelle und
    Testprotokoll aufgenommen. Bestehende APs und Übergabenotizen werden
    nie gelöscht, nur ergänzt.

## 1. Projektziel

Der Seitenimporter-Dialog (Markdown → Seiten, CDB-Designer-Plugin,
`admin.php?page=cbd-page-import`) bietet die Elternseiten-Auswahl statt als
einzelnes, eingerücktes `wp_dropdown_pages()`-Feld als gestaffelte,
kaskadierende Dropdown-Menüs an: Eine Auswahlebene erscheint erst, nachdem
in der vorherigen eine Seite gewählt wurde, und zeigt deren Unterseiten. Die
Kaskade wächst mit der tatsächlichen Seitenhierarchie (keine feste
Ebenenzahl). Entwürfe bleiben wie bisher als Elternseite wählbar.

## 2. Nicht-Ziele

- Keine Einbindung von `window.cbdBlockAuswahl`/`HierarchieAuswahl`
  (`assets/js/block-auswahl.js`) im Seitenimporter — diese Komponente
  wählt am Ende einen Block, nicht nur eine Seite, und setzt `wp.element`
  (React) voraus, was `assets/js/page-importer.js` bewusst vermeidet
  (Datei-Docblock: „Bewusst schlichtes JavaScript ohne Build-Schritt und
  ohne React: Die Seite ist keine Editor-Oberfläche.").
- Kein Suchfeld in der neuen Kaskade.
- Keine Kennzeichnung für Lehrpersonen gesperrter Seiten in der neuen
  Kaskade.
- Keine Änderung an „Elternseite gilt für den ganzen Lauf" — weiterhin EINE
  Elternseite für alle Dateien eines Laufs, nicht je Datei (bereits
  bestätigtes Nicht-Ziel aus dem Vorgänger-Vorhaben „Importer-Elternseite").
- Keine Änderung an `CBD_Page_Importer::bereinige_elternseite()`s
  Fallback-Verhalten (jeder ungültige Wert fällt weiterhin still auf `0`
  zurück).
- Keine Änderung am bestehenden Verhalten von `GET cbd/v1/seitenbaum` OHNE
  den neuen Parameter — Rückwärtskompatibilität zu `cbdBlockAuswahl`/dem
  Block-Referenz-Feature ist zwingend, alle 97 bestehenden Prüfungen in
  `tools/test-seitenbaum.php` müssen unverändert grün bleiben.
- Kein hartes Limit auf 3 Ebenen — die Kaskade wächst natürlich mit der
  echten Seitenhierarchie (im Projekt gemessen 3–4 Ebenen).
- Keine Datenbankmigration, keine neuen Tabellen/Spalten.

## 3. Kontext & Constraints

- **Umgebung:** WordPress-Plugin „Container Block Designer"
  (`Plugins/CDB-Designer/`), Zielumgebung PHP **7.4.33** (nicht die lokal
  installierte PHP-Version — siehe Abschnitt 0, Regel 12). Kein Build-
  Schritt für JS/CSS (reines Vanilla JS, kein npm/Vite in diesem Plugin).
- **Bestehende Konventionen:** `CLAUDE.md` und `reference_file_map.md` im
  Plugin-Root sind maßgeblich. Deutsche Bezeichner im PHP- und JS-Code
  (Vorbild: `baue_seitenbaum()`, `bereinige_elternseite()`, `ebenen()`,
  `pfadVon()`). REST-Sicherheitsmodell für Redakteurs-Routen:
  `current_user_can('edit_posts')` (identisch für `/blocks` und
  `/seitenbaum`). Sicherheitskonvention in `page-importer.js`: Inhalte aus
  Dateien/Serverantworten ausschließlich über `textContent`, nie
  `innerHTML`.
- **Harte Grenzen:** Keine externen Libraries. Kein `wp.element`/React in
  `page-importer.js` (siehe Nicht-Ziele). REST-URLs werden nie im
  JavaScript hartkodiert, sondern über `wp.apiFetch` (das die REST-Wurzel
  automatisch aus den von WordPress bereitgestellten Einstellungen bezieht,
  sobald das Skript-Handle `wp-api-fetch` als Abhängigkeit eingebunden ist)
  oder alternativ `rest_url()` serverseitig ermittelt.
- **Testumgebung:** `tools/test-seitenbaum.php` läuft OHNE
  WordPress-Bootstrap (eigene Stub-Klassen `WP_REST_Request`, `Test_WPDB`
  direkt in der Datei, ab Zeile ~30). Der Stub `WP_REST_Request` unterstützt
  bereits `get_param($key)` über einen im Konstruktor übergebenen
  `$params`-Array — für neue Testfälle direkt nutzbar, ohne den Stub selbst
  zu ändern. Zusätzlich lokaler Testserver `fos.localhost:8080` /
  `C:\allinkl-testserver` (CDB-Designer dort als Dateikopie installiert,
  kein Symlink — nach Codeänderungen muss kopiert oder ein Plugin-ZIP
  gebaut/hochgeladen werden; ZIP-Bau siehe
  `create-plugin-zip.js`-Hinweise in `CLAUDE.md`, insbesondere die
  `--no-dev`-Autoloader-Falle).
- **Git-Strategie:** Ein Branch für die einzige Phase dieses Plans:
  `phase-1-seitenimporter-kaskade`. Commit pro AP mit AP-ID im Text. Push
  nach jedem AP. Merge nach `main` erst nach bestandenem
  Integrationstest/Regressionscheck und Review.
- **Remote-Repository:**
  `https://github.com/Cyric25/CBD---Container-Block-Desinger.git` (bereits
  verbunden, Branch `main`). Keine Einrichtung nötig.

## 4. Architekturentscheidungen

| Entscheidung | Begründung | Verworfene Alternative |
|---|---|---|
| `GET cbd/v1/seitenbaum` bekommt einen NEUEN, rein additiven Query-Parameter `entwuerfe` (Wert `1` schließt Entwürfe ein), Standardverhalten ohne Parameter bleibt exakt wie bisher (nur `publish`) | Wiederverwendung der bestehenden, getesteten Baumaufbau-Route statt einer zweiten Route mit dupliziertem Code; Rückwärtskompatibilität zu `cbdBlockAuswahl` bleibt durch den Opt-in-Charakter zwingend gewahrt | Zweite, separate REST-Route nur für den Importer — hätte `baue_seitenbaum()` und die Breitensuche duplizieren müssen, obwohl `baue_seitenbaum()` bereits vollständig status-agnostisch ist (sie erhält nur Zeilen, keine Statuslogik) |
| Der Cache `self::$seitenbaum_cache` wird von einem einzelnen `WP_REST_Response\|null` auf ein assoziatives Array mit zwei Schlüsseln (`ohne_entwuerfe`/`mit_entwuerfe`) umgestellt | Verhindert, dass ein zweiter Aufruf mit anderem Parameterwert innerhalb derselben Anfrage das falsch gecachte Ergebnis des ersten Aufrufs zurückbekommt | Einen einzelnen Cache-Slot behalten und bei unterschiedlichem Parameter einfach neu abfragen — würde die Memoisierungs-Garantie für den ersten Fall stillschweigend verletzen, sobald beide Varianten in einer Anfrage vorkommen |
| Die Kaskade in `page-importer.js` wird als eigener, neuer Vanilla-JS-Baustein gebaut (kein Code-Import aus `block-auswahl.js`), lädt aber über `wp.apiFetch` (Dependency `wp-api-fetch`, wie `block-auswahl.js` es vormacht) dieselbe, erweiterte REST-Route | `wp-api-fetch` ist kein React/`wp.element` — sein Einsatz verletzt die „ohne React"-Konvention der Datei nicht, spart aber manuelles Nonce-/URL-Handling gegenüber rohem `fetch()` | `cbdBlockAuswahl.HierarchieAuswahl` direkt einbinden — falscher Endpunkt (wählt einen Block, nicht nur eine Seite) und bringt `wp.element`/`wp-components` auf eine bewusst React-freie Seite |
| Das bestehende Feld `cbd-import-parent` bleibt als verstecktes `<input type="hidden">` erhalten und trägt weiterhin den finalen `parent_id`-Wert; die sichtbaren kaskadierenden `<select>`-Elemente sind reine UI, die dieses Feld synchron hält | Der gesamte nachgelagerte Code (`importStarten()`, `bereinige_elternseite()`) bleibt unverändert — er kennt nur das eine Feld und seinen Wert, unabhängig davon, wie dieser Wert zustande kommt | Den nachgelagerten Code auf ein neues Datenformat umstellen — unnötiger Eingriff in bereits getesteten, funktionierenden Code außerhalb des Scopes dieser Erweiterung |

## 5. Risiken & Rollback

| Risiko | Wahrscheinlichkeit | Auswirkung | Gegenmaßnahme / Rollback |
|---|---|---|---|
| Cache-Kollision zwischen den beiden Parameter-Varianten von `cbd/v1/seitenbaum` innerhalb derselben Anfrage | mittel | hoch (falsche Daten an einen der beiden Konsumenten) | AP-1.1 verlangt explizit einen Testfall, der beide Varianten nacheinander innerhalb eines simulierten Requests abruft und prüft, dass beide ihr jeweils korrektes Ergebnis liefern (kein Vermischen) |
| Erweiterung von `get_seitenbaum()` bricht eines der 97 bestehenden `tools/test-seitenbaum.php`-Prüfungen (z. B. weil die SQL-Zeichenkette exakt geprüft wird) | mittel | hoch (Regression im bereits produktiven Block-Referenz-Feature) | AP-1.1 läuft testgetrieben (TDD, siehe Vorgehen dort); der komplette bestehende Testlauf wird vor UND nach der Änderung ausgeführt, beide Ergebnisse werden in der Übergabenotiz dokumentiert |
| `wp.apiFetch` ist auf der Importseite nicht wie erwartet automatisch mit REST-Wurzel/Nonce vorkonfiguriert (falls WordPress das nur im Block-Editor-Kontext automatisch einrichtet, nicht auf jeder Admin-Seite) | gering | mittel (Kaskade lädt keine Daten, Fallback nötig) | AP-1.2 verlangt einen Live-Test auf dem lokalen Testserver, der genau das prüft; falls die Automatik fehlt, dokumentiert die Übergabenotiz das und schlägt `rest_url()` + manuelle `X-WP-Nonce`-Kopfzeile als Ersatz vor (Korrektur-AP) |
| PHP-8.0-only-Syntax schleicht sich ein, weil lokal eine neuere PHP-Version läuft und `php -l` das nicht meldet | gering | hoch (Fataler Fehler erst auf der Zielumgebung 7.4.33) | Abschnitt 0, Regel 12 — `tools/check-php74.php` ist für jedes PHP-AP Pflichtnachweis |

**Generelle Rollback-Strategie:** Ein Branch für die gesamte Phase, Commit
pro AP. Bei Fehlschlag: `git revert` des betreffenden Commits statt
direkter Arbeit auf `main`. Keine Datenbank-Schemaänderung, daher kein
DB-Dump nötig.

## 6. Phasenübersicht

| Phase | Ziel | Lauffähiger Endzustand | APs |
|---|---|---|---|
| 1 | Seitenimporter bietet eine gestaffelte, kaskadierende Elternseiten-Auswahl inkl. Entwürfen; bestehende Nutzung von `cbd/v1/seitenbaum` bleibt unverändert | Admin öffnet den Seitenimporter, sieht eine Kaskade statt eines Einzeldropdowns, kann eine Seite (inkl. Entwürfe) beliebig tief auswählen, der Importlauf funktioniert wie bisher; die Block-Referenz-Zielauswahl im Editor funktioniert unverändert weiter | AP-1.1, AP-1.2, AP-1.3, AP-1.rev, AP-1.doc |

## 7. Arbeitspakete

### Phase 1: Gestaffelte Elternseiten-Auswahl

### AP-1.1: REST-Route `cbd/v1/seitenbaum` um Entwürfe-Parameter erweitern

**Status:** ☐ offen
**Umfang:** M
**Modell:** opus
**Abhängigkeiten:** keine

**Ziel & Kontext:**
`Plugins/CDB-Designer/includes/class-cbd-blocks-rest-api.php` enthält die
Route `GET cbd/v1/seitenbaum` (registriert in `register_routes()`,
Zeile ~45-49; Callback `get_seitenbaum($request)`, Zeile ~275-320).
Aktuell liefert sie ausschließlich veröffentlichte Seiten
(`WHERE post_type = 'page' AND post_status = 'publish'`, Zeile ~289-294)
und ignoriert `$request` vollständig. Innerhalb einer Anfrage wird das
Ergebnis in `self::$seitenbaum_cache` (private static Eigenschaft,
aktuell `WP_REST_Response|null`) gecacht; `seitenbaum_cache_vergessen()`
(Zeile ~503-505) setzt sie auf `null` zurück.

Ziel: Ein neuer, optionaler Query-Parameter `entwuerfe` (Wert `'1'`
schließt zusätzlich Seiten mit `post_status = 'draft'` ein) erweitert die
Route additiv. OHNE den Parameter (oder mit jedem anderen Wert als
`'1'`) bleibt das Verhalten exakt wie bisher — das ist zwingend, weil
`assets/js/block-auswahl.js` (`window.cbdBlockAuswahl`) dieselbe Route
ohne diesen Parameter aufruft und weiterhin nur veröffentlichte Seiten
erwarten darf.

**Betroffene Dateien:**
- `Plugins/CDB-Designer/includes/class-cbd-blocks-rest-api.php` (ändern)
- `Plugins/CDB-Designer/tools/test-seitenbaum.php` (ändern — neue
  Testfälle)

**Vorgehen (TDD, siehe Abschnitt 0 Regel 7):**
1. **Rot:** In `tools/test-seitenbaum.php` (nach den bestehenden
   Prüfungen zu `get_seitenbaum()`, ca. ab Zeile 709, im bestehenden Stil
   mit `check()`) neue Testfälle ergänzen, die VOR der Implementierung
   fehlschlagen müssen:
   a. `CBD_Blocks_REST_API::get_seitenbaum(new WP_REST_Request(['entwuerfe' => '1']))`
      mit `$GLOBALS['test_wpdb_zeilen']` inkl. einer Zeile mit
      `post_status`-artigem Feld — WICHTIG: Die aktuelle SQL-Abfrage
      selektiert `ID, post_parent, post_title, menu_order, post_type`,
      **kein** `post_status` (die Filterung passiert serverseitig in der
      WHERE-Klausel, nicht im PHP-Code). Der Testfall prüft deshalb NICHT
      den Rückgabewert von `baue_seitenbaum()` auf Status, sondern die von
      `$GLOBALS['wpdb']->letzte_sql()` erfasste SQL-Zeichenkette: Mit
      `entwuerfe=1` muss sie `post_status IN ('publish', 'draft')` (oder
      äquivalent) enthalten, ohne den Parameter weiterhin exakt
      `post_status = 'publish'`.
   b. Cache-Isolation: `seitenbaum_cache_vergessen()` aufrufen, dann
      `get_seitenbaum(new WP_REST_Request())` (ohne Entwürfe) UND direkt
      danach `get_seitenbaum(new WP_REST_Request(['entwuerfe' => '1']))`
      (mit Entwürfen) im selben Testlauf aufrufen — beide Aufrufe müssen
      `$GLOBALS['wpdb']->abfragen` um jeweils jeweils jeweils genau 1
      erhöhen (also 2 Abfragen gesamt für die zwei unterschiedlichen
      Varianten, NICHT 1 durch fälschliche Cache-Wiederverwendung über
      Parametergrenzen hinweg). Ein dritter Aufruf mit dem bereits zuvor
      genutzten Parameterwert (z. B. erneut ohne Entwürfe) darf dagegen
      KEINE weitere Abfrage auslösen (Memoisierung bleibt je Variante
      erhalten).
   c. Rückwärtskompatibilität: Bestehendes Verhalten bei
      `new WP_REST_Request()` (kein Parameter) bleibt identisch zum
      heutigen Stand — bestehende Prüfungen (u. a. um Zeile 719-745)
      dürfen für diesen Fall nicht angepasst werden müssen.
   Test einmal ausführen (`php tools/test-seitenbaum.php`), bestätigen,
   dass GENAU die neuen Fälle als `FAIL` erscheinen, alle 97 bestehenden
   weiterhin `OK` (falls ein bestehender Fall unerwartet rot wird, ist die
   Testformulierung selbst fehlerhaft — korrigieren, bevor committet
   wird). Diesen roten Zustand committen (`AP-1.1: Rote Tests für
   Entwürfe-Parameter in cbd/v1/seitenbaum`).
2. **Grün:** In `class-cbd-blocks-rest-api.php`:
   a. `self::$seitenbaum_cache` von `private static $seitenbaum_cache = null;`
      auf `private static $seitenbaum_cache = array();` umstellen.
   b. In `get_seitenbaum($request)`: `$roh = $request->get_param('entwuerfe'); $mit_entwuerfe = ('1' === (string) $roh);`
      Cache-Schlüssel ableiten (z. B. `$cache_key = $mit_entwuerfe ? 'mit_entwuerfe' : 'ohne_entwuerfe';`).
      Cache-Lookup/-Schreiben auf `self::$seitenbaum_cache[$cache_key]`
      umstellen (statt der einzelnen Eigenschaft).
   c. SQL-Abfrage bedingt aufbauen: Bei `$mit_entwuerfe === true` den
      `post_status`-Teil der WHERE-Klausel auf
      `post_status IN ('publish', 'draft')` setzen, sonst unverändert
      `post_status = 'publish'` lassen.
   d. `seitenbaum_cache_vergessen()` auf
      `self::$seitenbaum_cache = array();` umstellen (leert beide
      Varianten).
   e. `baue_seitenbaum()` selbst NICHT ändern — sie ist bereits vollständig
      status-agnostisch (verarbeitet nur `post_type`).
3. Test erneut ausführen, bis alle Fälle (die neuen UND alle 97
   bestehenden) `OK` melden.
4. `php tools/check-php74.php` ausführen (PHP-7.4-Kompatibilität, Regel 12
   aus Abschnitt 0).

**Akzeptanzkriterien:**
- [ ] `php tools/test-seitenbaum.php` meldet 0 `FAIL` — alle 97
      ursprünglichen Prüfungen weiterhin `OK`, plus alle neuen Prüfungen
      aus diesem AP `OK`.
- [ ] `php tools/check-php74.php` meldet keine PHP-8.0-only-Syntax.
- [ ] `GET cbd/v1/seitenbaum` ohne den Parameter `entwuerfe` liefert
      byte-identisch dasselbe Ergebnis wie vor dieser Änderung (nur
      veröffentlichte Seiten).
- [ ] `GET cbd/v1/seitenbaum?entwuerfe=1` liefert zusätzlich Seiten mit
      `post_status = 'draft'`.
- [ ] Zwei aufeinanderfolgende Aufrufe mit unterschiedlichem
      `entwuerfe`-Wert innerhalb derselben Anfrage liefern beide ihr
      jeweils korrektes, nicht vermischtes Ergebnis (Cache-Isolation).

**Tests:**
- Der komplette Testlauf `php tools/test-seitenbaum.php` ist der
  Smoke-Test dieses APs (kein WordPress nötig).
- `php tools/check-php74.php` zusätzlich zwingend.
- Regressionsrelevanz: Diese Route wird von `assets/js/block-auswahl.js`
  (Block-Referenz-Feature im Editor) ohne den neuen Parameter genutzt —
  jede Abweichung vom bisherigen Standardverhalten ist eine Regression
  dieses bereits produktiven Features.

**Übergabenotiz:**
(leer – wird vom ausführenden Agenten nach Abschluss ausgefüllt)

---

### AP-1.2: Kaskadierende Auswahl in `page-importer.js` und `page-import.php`

**Status:** ☐ offen
**Umfang:** L
**Modell:** opus
**Abhängigkeiten:** AP-1.1 (liefert `GET cbd/v1/seitenbaum?entwuerfe=1`)

**Ziel & Kontext:**
`Plugins/CDB-Designer/admin/page-import.php` (Zeile ~43-58) rendert
aktuell ein einzelnes `<select id="cbd-import-parent" name="cbd-import-parent">`
über `wp_dropdown_pages()`. `assets/js/page-importer.js` liest diesen Wert
einmal vor Laufbeginn (Funktion `importStarten()`, Zeile ~526 referenziert
`elternfeld`) und schickt ihn bei jedem `cbd_import_pages`-Aufruf als
`parent_id` mit; das Feld ist während des Laufs `disabled`.

Ziel: Das sichtbare `<select>` wird durch eine Reihe kaskadierender
`<select>`-Elemente ersetzt (eines je Hierarchieebene, das nächste
erscheint erst nach einer Auswahl in der vorherigen). Das bisherige Feld
`cbd-import-parent` bleibt als verstecktes `<input type="hidden" id="cbd-import-parent" name="cbd-import-parent">`
erhalten und wird von der neuen Kaskaden-Logik bei jeder Auswahl auf die
ID der tiefsten aktuell gewählten Seite aktualisiert (oder auf `0`, wenn
nichts/„oberste Ebene" gewählt ist) — dadurch bleibt der gesamte
nachgelagerte Code in `page-importer.js` (Lesen des Werts, `disabled`
während des Laufs) UNVERÄNDERT lauffähig.

**Betroffene Dateien:**
- `Plugins/CDB-Designer/admin/page-import.php` (ändern)
- `Plugins/CDB-Designer/assets/js/page-importer.js` (ändern)
- `Plugins/CDB-Designer/includes/class-cbd-page-importer.php` (ändern —
  nur die Script-Registrierung, siehe Vorgehen Schritt 4; NICHT
  `bereinige_elternseite()`)

**Vorgehen:**
1. In `admin/page-import.php`: Den Block
   ```php
   <?php wp_dropdown_pages(array(
       'show_option_none'  => esc_html__('— oberste Ebene —', 'container-block-designer'),
       'option_none_value' => 0,
       'name'              => 'cbd-import-parent',
       'id'                => 'cbd-import-parent',
       'sort_column'       => 'menu_order,post_title',
       'post_status'       => array('publish', 'draft'),
   )); ?>
   ```
   ersetzen durch:
   ```php
   <input type="hidden" id="cbd-import-parent" name="cbd-import-parent" value="0">
   <div id="cbd-pi-kaskade" class="cbd-pi-kaskade" aria-live="polite">
       <p class="cbd-pi-kaskade-status"><?php esc_html_e('Lade Seitenbaum …', 'container-block-designer'); ?></p>
   </div>
   ```
   (Die genaue innere Struktur der Kaskade — z. B. ein `<select>` mit
   Klasse `cbd-pi-kaskade-ebene` je Ebene — baut `page-importer.js` per
   JavaScript in `#cbd-pi-kaskade` auf; das AP entscheidet die konkrete
   DOM-Struktur, solange die CSS-Klasse `cbd-pi-kaskade-ebene` je
   erzeugtem `<select>`-Element gesetzt wird, damit AP-1.3 sie gestalten
   kann.)
2. In `includes/class-cbd-page-importer.php`, bei der Registrierung von
   `cbd-page-importer` (`wp_enqueue_script('cbd-page-importer', ..., array('wp-i18n'), ...)`,
   Zeile ~119-125): `'wp-api-fetch'` zur Abhängigkeitsliste ergänzen
   (→ `array('wp-i18n', 'wp-api-fetch')`), damit `window.wp.apiFetch`
   verfügbar ist. Keine weitere Änderung an dieser Methode.
3. In `assets/js/page-importer.js`: Neue Funktion(en) ergänzen, die
   a. Beim Laden der Seite (oder beim ersten Fokus auf `#cbd-pi-kaskade`,
      Entscheidung dem AP überlassen — einfacher: beim Laden) via
      `window.wp.apiFetch({ path: '/cbd/v1/seitenbaum?entwuerfe=1' })`
      den erweiterten Seitenbaum lädt (Antwortform:
      `{knoten: {<id>: {id, parent, titel, menuOrder, tiefe, typ, gesperrt}}, kinder: {<parentId>: [<id>, ...]}, wurzeln: [<id>, ...]}`
      — `knoten` und `kinder` sind JSON-OBJEKTE, keine Arrays, siehe
      Vertrag B in `CLAUDE.md`, Abschnitt „Blockreferenz als Textformat
      und hierarchische Zielauswahl", Unterabschnitt „Vertrag B in der
      Praxis").
   b. Bei Erfolg: `#cbd-pi-kaskade`-Inhalt durch die erste Ebene ersetzt
      — ein `<select class="cbd-pi-kaskade-ebene">` mit einer Option
      „— oberste Ebene —" (Wert `0`) plus einer Option je ID in
      `baum.wurzeln` (Text = `baum.knoten[id].titel`, **ausschließlich
      über `textContent`/Options-`text`-Eigenschaft gesetzt, NIE
      `innerHTML`** — bestehende Sicherheitskonvention der Datei).
   c. Bei `change` auf einem `<select class="cbd-pi-kaskade-ebene">`:
      - Alle nachfolgenden (tieferen) Kaskaden-`<select>`-Elemente aus
        dem DOM entfernen (Nutzer hat eine höhere Ebene neu gewählt).
      - Das versteckte Feld `#cbd-import-parent` auf den neu gewählten
        Wert setzen.
      - Ist der gewählte Wert `0` („oberste Ebene" bzw. keine weitere
        Unterseite an dieser Stelle): fertig, keine weitere Ebene
        anhängen.
      - Sonst: `baum.kinder[gewählteId]` nachsehen. Gibt es Kinder, eine
        neue `<select class="cbd-pi-kaskade-ebene">`-Ebene mit „— diese
        Seite als Elternseite —" (Wert = die bereits gewählte ID, NICHT
        `0` — die Auswahl der übergeordneten Ebene bleibt gültig, falls
        der Nutzer keine tiefere Unterseite wählen will) plus einer
        Option je Kind anhängen.
   d. Bei Fehlschlag des `apiFetch`-Aufrufs (Netzwerkfehler,
      HTTP-Fehlerstatus): `#cbd-pi-kaskade` zeigt eine Fehlermeldung
      (z. B. „Seitenbaum konnte nicht geladen werden — Elternseite bleibt
      auf oberster Ebene."), das versteckte Feld bleibt auf `0` — der
      Importlauf ist dadurch NICHT blockiert (identisch zur bestehenden
      Philosophie in `bereinige_elternseite()`: ein Problem bei der
      Elternseiten-Ermittlung darf den Lauf nie verhindern, sondern fällt
      auf oberste Ebene zurück).
4. Die bestehende Logik in `importStarten()` (liest `#cbd-import-parent`,
   sperrt es während des Laufs) funktioniert unverändert weiter, weil sie
   weiterhin ein Formularfeld mit `id="cbd-import-parent"` und einem
   `.value` findet — lediglich `disabled` auf einem `<input type="hidden">`
   hat keine sichtbare Wirkung; zusätzlich auch alle
   `.cbd-pi-kaskade-ebene`-Selects während des Laufs `disabled` setzen,
   damit die Auswahl währenddessen nicht mehr geändert werden kann (Analog
   zur bisherigen UX).

**Akzeptanzkriterien:**
- [ ] `php tools/check-php74.php` meldet keine PHP-8.0-only-Syntax (für
      die Änderung in `class-cbd-page-importer.php`).
- [ ] Beim Öffnen des Seitenimporter-Dialogs erscheint zunächst eine
      Kaskaden-Ebene mit „— oberste Ebene —" plus allen Seiten auf
      oberster Ebene (inkl. Entwürfen).
- [ ] Wahl einer Seite mit Unterseiten lässt eine zweite Ebene mit deren
      Kindern erscheinen; Wahl einer Seite ohne Unterseiten (oder
      „oberste Ebene") lässt keine weitere Ebene erscheinen.
- [ ] Erneute Wahl in einer höheren Ebene entfernt alle zuvor
      angehängten tieferen Ebenen.
- [ ] Das versteckte Feld `#cbd-import-parent` trägt nach jeder Auswahl
      exakt die ID der tiefsten aktuell gewählten Seite (oder `0`).
- [ ] Ein Importlauf mit einer über die Kaskade gewählten Elternseite legt
      die neuen Seiten mit dem korrekten `post_parent` an (unverändertes
      Verhalten von `bereinige_elternseite()`/`ajax_seiten_importieren()`).
- [ ] Kein Seitentitel wird über `innerHTML` gesetzt (Stichprobe im
      Quelltext: nur `textContent` bzw. die `text`-Eigenschaft von
      `Option`-Objekten).
- [ ] Schlägt der `apiFetch`-Aufruf fehl, bleibt der Dialog benutzbar
      (Elternseite fällt auf „oberste Ebene" zurück, kein JavaScript-Fehler
      in der Konsole).

**Tests:**
- Smoke-Test (auf `fos.localhost:8080`, falls erreichbar): Seitenmanager
  → Seiten importieren öffnen, prüfen, dass die Kaskade lädt und
  mindestens zwei Ebenen tief navigierbar ist (Voraussetzung: mindestens
  eine Seite mit Unterseiten existiert). Eine Markdown-Datei mit einer
  über die Kaskade gewählten Unterseiten-Elternseite importieren, danach
  im Seitenmanager prüfen, dass die neue Seite unter der gewählten
  Elternseite liegt.
- Konsole prüfen: keine JavaScript-Fehler beim Laden und Bedienen der
  Kaskade.
- Test des Fehlerfalls: `wp.apiFetch`-Aufruf künstlich zum Scheitern
  bringen (z. B. Browser-DevTools-Netzwerkblock auf
  `*/cbd/v1/seitenbaum*`) und prüfen, dass der Dialog trotzdem benutzbar
  bleibt und ein Import auf oberster Ebene funktioniert.
- Ist der Testserver nicht erreichbar: Den JS-Code gegen alle
  Akzeptanzkriterien am Quelltext nachvollziehen und das Fehlen des
  Live-Tests im Testprotokoll vermerken.
- Regressionsrelevanz: Der bestehende Dateiauswahl-/Stil-Dialog-/
  Fortschritts-Ablauf in `page-importer.js` darf durch diese Änderung
  nicht beeinträchtigt werden — kurzer Testlauf ohne jede
  Elternseiten-Auswahl (oberste Ebene) muss weiterhin funktionieren.

**Übergabenotiz:**
(leer – wird vom ausführenden Agenten nach Abschluss ausgefüllt)

---

### AP-1.3: Gestaltung der Kaskaden-Auswahlfelder

**Status:** ☐ offen
**Umfang:** S
**Modell:** sonnet
**Abhängigkeiten:** AP-1.2 (liefert die DOM-Struktur mit Klasse
`cbd-pi-kaskade-ebene` innerhalb `#cbd-pi-kaskade`)

**Ziel & Kontext:**
`Plugins/CDB-Designer/assets/css/page-importer.css` gestaltet aktuell den
Seitenimporter-Dialog inkl. des bisherigen Einzeldropdowns (Klassen mit
Präfix `.cbd-pi-*`, u. a. `.cbd-pi-elternseite`). Nach AP-1.2 enthält
`#cbd-pi-kaskade` mehrere `<select class="cbd-pi-kaskade-ebene">`-Elemente
nebeneinander oder untereinander (abhängig von AP-1.2s konkreter
DOM-Struktur). Ziel: Diese neuen Elemente erhalten eine zum bestehenden
Dialog passende Gestaltung.

**Betroffene Dateien:**
- `Plugins/CDB-Designer/assets/css/page-importer.css` (ändern)

**Vorgehen:**
1. `admin/page-import.php` und `assets/js/page-importer.js` (nach AP-1.2)
   lesen, um die exakte erzeugte DOM-Struktur von `#cbd-pi-kaskade` und
   die Klasse(n) der einzelnen Auswahlfelder festzustellen.
2. Regeln für `.cbd-pi-kaskade` (Container, z. B. `display: flex` mit
   `flex-wrap: wrap` und Abstand zwischen den Ebenen, oder untereinander
   mit Einrückung je Ebene — Design-Entscheidung, an den bestehenden
   Abständen/Farben der übrigen `.cbd-pi-*`-Klassen orientieren, siehe
   bestehende Regeln in dieser Datei) und
   `.cbd-pi-kaskade-ebene` (Größe, Rand, Abstand zur nächsten Ebene)
   ergänzen.
3. Ladezustand (`.cbd-pi-kaskade-status`, die Meldung „Lade Seitenbaum …"
   bzw. eine Fehlermeldung aus AP-1.2) dezent, aber lesbar gestalten
   (z. B. wie bestehende `.description`-Texte im Dialog).
4. Responsives Verhalten prüfen: Bei schmalen Fenstern dürfen mehrere
   nebeneinanderstehende `<select>`-Ebenen nicht aus dem sichtbaren
   Bereich laufen (z. B. `flex-wrap: wrap` oder Umbruch auf schmalen
   Bildschirmen).

**Akzeptanzkriterien:**
- [ ] Jede Kaskaden-Ebene ist optisch klar als Teil derselben
      zusammenhängenden Auswahl erkennbar (z. B. durch gleichmäßigen
      Abstand, gemeinsame Rahmenfarbe/-stärke).
- [ ] Die Ladestatus-/Fehlermeldung aus AP-1.2 ist lesbar gestaltet und
      fügt sich optisch in den bestehenden Dialog ein.
- [ ] Bei einer Fensterbreite von 480px (mobile Ansicht) bleiben alle
      Kaskaden-Elemente innerhalb des sichtbaren Bereichs, kein
      horizontales Scrollen des gesamten Dialogs nötig.
- [ ] Keine bestehende `.cbd-pi-*`-Regel wird durch die neuen Regeln
      überschrieben oder verändert (nur additive Ergänzungen).

**Tests:**
- Smoke-Test (auf `fos.localhost:8080`, falls erreichbar): Seitenimporter
  öffnen, Browserfenster auf 480px Breite verkleinern, Kaskade bis
  mindestens zwei Ebenen aufklappen, visuell prüfen, dass nichts
  abgeschnitten wird oder überläuft.
- Ist der Testserver nicht erreichbar: CSS-Regeln gegen die
  Akzeptanzkriterien am Quelltext nachvollziehen und das Fehlen des
  visuellen Tests im Testprotokoll vermerken.
- Regressionsrelevanz: Bestehende Gestaltung von Dropzone, Dateiliste und
  Stil-Dialog in `page-importer.css` darf unverändert bleiben — nur
  additive neue Regeln, keine bestehenden Selektoren anfassen.

**Übergabenotiz:**
(leer – wird vom ausführenden Agenten nach Abschluss ausgefüllt)

---

### AP-1.rev: Unabhängiges Review Phase 1

**Status:** ☐ offen
**Umfang:** M
**Modell:** opus
**Abhängigkeiten:** AP-1.1, AP-1.2, AP-1.3 (inkl. Phasen-Integrationstest)

**Ziel & Kontext:**
Unabhängige Qualitätsprüfung der Phase durch einen Agenten, der an keiner
Implementierung beteiligt war. Nur lesend arbeiten (Read/Grep/Glob bzw.
Dateien ansehen, `php`-Kommandos zum Ausführen der Testskripte sind
erlaubt) – KEINE Datei verändern.

**Vorgehen:**
1. Für AP-1.1, AP-1.2, AP-1.3: Code gegen die jeweiligen
   Akzeptanzkriterien prüfen (Stichproben im Quelltext, nicht nur
   Übergabenotizen glauben).
2. `php tools/test-seitenbaum.php` und `php tools/check-php74.php` selbst
   ausführen und das tatsächliche Ergebnis berichten (nicht nur die
   Übergabenotiz zitieren).
3. **Besonders kritisch prüfen:** Liefert `GET cbd/v1/seitenbaum` OHNE den
   Parameter `entwuerfe` wirklich exakt dasselbe Ergebnis wie vor diesem
   Plan? Ist die Cache-Isolation zwischen den beiden Parameter-Varianten
   tatsächlich korrekt (Quelltext von `get_seitenbaum()` und
   `seitenbaum_cache_vergessen()` genau lesen)?
4. Scope-Check: Wurde `baue_seitenbaum()` unnötig verändert? Wurde
   `assets/js/block-auswahl.js` oder `blocks/block-reference/index.js`
   angefasst (sollte unverändert sein, laut Nicht-Zielen)? Wurde
   `CBD_Page_Importer::bereinige_elternseite()` verändert (sollte nicht)?
   Wurde `wp.element`/React in `page-importer.js` eingeführt (sollte
   nicht, siehe Nicht-Ziele)?
5. Sicherheitscheck: Werden Seitentitel aus der REST-Antwort in
   `page-importer.js` ausschließlich über `textContent`/`Option.text`
   gesetzt, nie über `innerHTML`?
6. Befunde als Review-Bericht in die Übergabenotiz: je Befund
   Schweregrad (kritisch / mittel / gering), betroffenes AP, Datei und
   Fundstelle (Zeilennummer).

**Akzeptanzkriterien:**
- [ ] AP-1.1, AP-1.2 und AP-1.3 wurden je gegen ihre Akzeptanzkriterien
      geprüft.
- [ ] `tools/test-seitenbaum.php` und `tools/check-php74.php` wurden vom
      Review-Agenten selbst ausgeführt, Ergebnis dokumentiert.
- [ ] Alle Befunde mit Schweregrad, Datei und Fundstelle dokumentiert.
- [ ] Keine Datei wurde verändert.

**Tests:**
- entfällt (Review-AP; das Ergebnis ist der Bericht).

**Übergabenotiz:**
(leer – wird vom ausführenden Review-Agenten nach Abschluss ausgefüllt)

---

### AP-1.doc: Dokumentation aktualisieren

**Status:** ☐ offen
**Umfang:** S
**Modell:** sonnet
**Abhängigkeiten:** AP-1.rev

**Ziel & Kontext:**
`CLAUDE.md` und `reference_file_map.md` (beide im Plugin-Root
`Plugins/CDB-Designer/`) auf den Stand nach dieser Erweiterung bringen.

**Betroffene Dateien:**
- `Plugins/CDB-Designer/CLAUDE.md` (ändern)
- `Plugins/CDB-Designer/reference_file_map.md` (ändern)

**Vorgehen:**
1. In `CLAUDE.md`, Abschnitt „Seitenimport (Markdown → Seiten, seit
   v3.1.86)", Unterabschnitt „Elternseite gilt für den ganzen Lauf": einen
   neuen Absatz ergänzen, der die kaskadierende Auswahl beschreibt (ersetzt
   `wp_dropdown_pages()`, verstecktes Feld `cbd-import-parent` trägt
   weiterhin den finalen Wert, Kaskade lädt über `wp.apiFetch` den
   erweiterten Seitenbaum via `GET cbd/v1/seitenbaum?entwuerfe=1`).
2. Im Abschnitt „Blockreferenz als Textformat und hierarchische
   Zielauswahl (seit 3.1.93)", Unterabschnitt „Vertrag B in der Praxis":
   einen Satz ergänzen, dass die Route seit diesem Plan zusätzlich den
   optionalen Parameter `entwuerfe=1` kennt (Standardverhalten ohne
   Parameter unverändert), und dass der Cache jetzt parameterabhängig ist
   (zwei Schlüssel statt einem Slot).
3. In `reference_file_map.md`: Zeilen zu `class-cbd-blocks-rest-api.php`
   (Zeile ~51), `page-import.php` (Zeile ~73), `page-importer.js`
   (Zeile ~99) und `test-seitenbaum.php` (Zeile ~152) um die jeweilige
   Änderung ergänzen (neuer Parameter bzw. neue Kaskaden-UI). „Stand"-Datum
   im Kopf der Datei (Zeile 3) aktualisieren.
4. „Letzte Aktualisierung" im Kopf dieser `PLAN.md` aktualisieren, Status
   aller APs in Abschnitt 8 auf ☑ prüfen.

**Akzeptanzkriterien:**
- [ ] `CLAUDE.md` beschreibt die kaskadierende Elternseiten-Auswahl im
      Seitenimporter-Abschnitt korrekt.
- [ ] `CLAUDE.md` beschreibt den neuen `entwuerfe`-Parameter und die
      parameterabhängige Cache-Struktur im Vertrag-B-Abschnitt korrekt.
- [ ] `reference_file_map.md` ist an allen vier genannten Zeilen aktuell,
      Stand-Datum aktualisiert.
- [ ] Kein Verweis in der aktualisierten Dokumentation zeigt auf nicht
      mehr existierende Funktionen oder falsche Parameter-/Feldnamen.

**Tests:**
- Stichprobe: Den beschriebenen Parameternamen `entwuerfe` und die
  beschriebene Cache-Struktur im tatsächlichen Quelltext von
  `class-cbd-blocks-rest-api.php` gegenprüfen.

**Übergabenotiz:**
(leer – wird vom ausführenden Agenten nach Abschluss ausgefüllt)

## 8. Status

Legende: ☐ offen · ◐ in Arbeit · ☑ erledigt · ✗ blockiert

| AP | Titel | Modell | Status | Abhängig von | Notiz |
|---|---|---|---|---|---|
| AP-1.1 | REST-Route um Entwürfe-Parameter erweitern | opus | ☐ | – | |
| AP-1.2 | Kaskadierende Auswahl in JS/PHP | opus | ☐ | AP-1.1 | |
| AP-1.3 | Gestaltung der Kaskaden-Auswahlfelder | sonnet | ☐ | AP-1.2 | |
| AP-1.rev | Review Phase 1 | opus | ☐ | AP-1.1, AP-1.2, AP-1.3 | |
| AP-1.doc | Doku Phase 1 | sonnet | ☐ | AP-1.rev | |

## 9. Testprotokoll

Wird während der Ausführung gepflegt. Ein Eintrag pro abgeschlossenem AP und pro Phasenabschluss.

| Datum | AP / Phase | Getestet | Ergebnis | Getestet von |
|---|---|---|---|---|
| | | | | |

## 10. Dokumentation

- **Projektdokumentation:** `Plugins/CDB-Designer/CLAUDE.md` — Abschnitte
  „Seitenimport" und „Blockreferenz als Textformat und hierarchische
  Zielauswahl". Wird in `AP-1.doc` aktualisiert.
- **Datei-Map:** `Plugins/CDB-Designer/reference_file_map.md` — Zeilen zu
  `class-cbd-blocks-rest-api.php`, `page-import.php`, `page-importer.js`,
  `test-seitenbaum.php`. Wird von jedem AP gepflegt, das diese Dateien
  wesentlich ändert.
