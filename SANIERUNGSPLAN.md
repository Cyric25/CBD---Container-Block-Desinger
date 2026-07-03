# Sanierungsplan CDB-Designer

Basierend auf dem Code-Review vom 02.07.2026 (Sicherheit, Performance, Struktur).
Jede Phase ist eigenständig deploybar. Nach jeder Phase: Syntax-Check → ZIP → Test auf Staging → Commit.

Status-Legende: `[ ]` offen · `[x]` erledigt

---

## Phase 1 — Sicherheit (SOFORT, ~1–2 h) ✅ ERLEDIGT (v3.1.42)

- [x] **1.1 `ajax_edit_save` absichern** — Nonce (`cbd-admin`) + `current_user_can('cbd_admin_blocks')` ergänzt. Zusätzlich `ajax_delete_block`/`ajax_toggle_status` von `edit_posts` auf `cbd_admin_blocks` angehoben.
- [x] **1.2 Nonce-Bypass in `ajax_save_block` entfernt** — Debug-Bypass gelöscht, Nonce erzwungen, Capability auf `cbd_admin_blocks`. Doppelregistrierung von `cbd_save_block` UND `cbd_delete_block` in `CBD_Ajax_Handler` entfernt.
- [x] **1.3 Debug-Endpoint `cbd_debug_page_status` entfernt** — Registrierung (inkl. nopriv) und Handler gelöscht.
- [x] **1.4 Klassenpasswort-Bypass geschlossen** — Bypass nur noch für `current_user_can('cbd_edit_blocks')` (Lehrer), nicht mehr für beliebige eingeloggte Nutzer.
- [x] **1.5 Debug-/Altdateien gelöscht** — `debug-pdf.php`, `debug-classroom.php`, `test-listen.php`, `admin.zip` aus Git entfernt.
- [x] **1.6 Defense-in-Depth** — zentraler `cbd_admin_blocks`-Gate für mutierende Aktionen in `process_admin_actions()`; `is_rest_fallback`-Rate-Limit-Bypass im PDF-Handler entfernt.

---

## Phase 2 — Performance Quick Wins (~2–3 h) ✅ ERLEDIGT (v3.1.43)

- [x] **2.1 `time()`-Cache-Buster entfernt** — nur noch `CBD_VERSION`; Frontend-CSS wird jetzt vom Browser/CDN gecacht.
- [x] **2.2 `has_block()`-Gate** — neue Methode `frontend_has_container_block()`; `enqueue_block_assets()` bricht auf Frontend-Seiten ohne Container-Block früh ab (Editor lädt weiterhin alles). *Hinweis: Container in Reusable Blocks/FSE-Template-Parts werden von `has_block()` nicht erkannt — bei Bedarf Prüfung erweitern.*
- [x] **2.3 KaTeX konditional** — neue Methode `should_load_katex()`; Frontend nur bei `$`/`[latex]` im Inhalt, Admin nur im Block-Editor.
- [x] **2.4 Lucide gepinnt** — `@latest` → `0.454.0` in Block-Registration und Classroom (kein Redirect, cachebar, DSGVO). *Per-Feature-Ladung nur benötigter Icon-Libs bleibt als Refinement offen (siehe 3.x).*
- [x] **2.5 Debug hinter WP_DEBUG** — Features/Config-JSON pro Block, Styles-JSON-Dump im Editor und Init-`error_log()` im Hauptplugin nur noch bei aktivem WP_DEBUG.

---

## Phase 3 — Performance strukturell ✅ ERLEDIGT (v3.1.44)

- [x] **3.1 Blockliste zentral gecacht** — neue `CBD_Block_Registration::get_active_blocks()` (Per-Request-Static + Transient `cbd_active_blocks`), genutzt von `register_blocks()` und dem Render-Fallback. Invalidierung via `clear_blocks_cache()` auf `cbd_block_saved`/`cbd_block_deleted`.
- [x] **3.2 N+1 „Behandelt"-Status behoben** — neue `get_behandelt_container_ids($page_id)` lädt alle behandelten Container einer Seite in EINEM Query (statisch gecacht), O(1)-Lookup pro Block.
- [x] **3.3 Dynamisches Frontend-CSS gecacht** — `output_dynamic_styles()` cacht pro Post in Transient (`cbd_page_css_{id}`), signiert mit Style-Version + `post_modified`; bei Cache-Treffer wird `parse_blocks()` übersprungen.
- [x] **3.4 `board-mode.js` seitenbezogen** — `enqueue_feature_styles()` nutzt jetzt `get_used_blocks_on_page()` + neue `extract_active_features_from_blocks()`; board-mode.js lädt nur auf Seiten, deren Blöcke das Feature tatsächlich nutzen.
- [x] **3.5 Editor-Style-Ausgabe dedupliziert** — `output_editor_dynamic_styles` und `output_emergency_editor_styles` mit `static $done`-Guard (statt 2× bzw. 3× pro Editor-Load).
- [x] **Bonus:** Die zuvor toten Actions `cbd_block_saved`/`cbd_block_deleted` werden jetzt an ALLEN Schreibpfaden ausgelöst (CBD_Database, alle Admin-AJAX-/Formular-Handler, new-block.php) — damit funktioniert auch die bislang schlafende Style-Cache-Invalidierung des Style-Loaders.

---

## Phase 4 — Toten Code entfernen ✅ ERLEDIGT (v3.1.45)

- [x] **4.1 Tote JS-Dateien gelöscht** — `jspdf-loader.js`, `jspdf-loader-old.js`, `html2pdf-loader-v2.js`, `frontend-working.js`, `container-blocks-inline.js`, `unified-frontend.js`. Dazu die tote Methode `enqueue_frontend_scripts()` (inkl. verwaister `wp_add_inline_script('cbd-frontend-working')`) entfernt.
- [x] **4.2 Tote PHP-Schichten gelöscht** — `includes/class-consolidated-frontend.php` und der komplette Ordner `includes/API/` (zweite tote REST-API; aktiv bleibt `class-cbd-blocks-rest-api.php`).
- [x] **4.3 Geister-Referenzen entfernt (latente Fatal Errors)** — Services `frontend_renderer`, `admin_router`, `api_manager` aus dem Container gelöscht; Autoloader-Mappings auf nicht existierende Renderer-Dateien entfernt.
- [x] **4.4 Repo-/ZIP-Hygiene** — `admin/container-block-designer.php` → `admin/dashboard.php` umbenannt (Referenz aktualisiert); Status-MD-Dateien und die bereits gelöschte `debug-pdf.php` aus der ZIP-Include-Liste entfernt. *Dev-Vendor-Pakete (PHPUnit/PHPCS/nikic/…) waren im ZIP-Skript bereits ausgeschlossen.*
- [x] **4.5 `cbd_get_service()` dedupliziert** — Inline-Duplikat aus dem Hauptplugin entfernt; einzige Definition in `includes/functions.php` (mit `function_exists`-Guards), das nun explizit in `load_dependencies()` geladen wird (funktioniert auch ohne Composer).

---

## Phase 5 — Strukturelles Refactoring (teilweise erledigt, v3.1.46)

- [x] **5.1 Rollen-Definition vereinheitlicht (WICHTIGSTES Refactoring)** — kanonische `cbd_block_redakteur_capabilities()` in `functions.php` als einzige Quelle der Wahrheit; beide Erstellungspfade (Hauptplugin + `CBD_Admin`) nutzen sie. Damit erhält die Rolle unabhängig vom Codepfad identische Rechte.
- [x] **5.5 Versionen synchronisiert** — composer.json (`2.6.0` → `3.1.45`), CDB-CLAUDE.md (`2.9.0` → `3.1.45`, DB-Version → `3.1.32`). *Autoload-Strategie unverändert gelassen (die manuelle require_once-Kette funktioniert; ein Umbau auf Composer-classmap ist optional und ohne Nutzen für die Laufzeit).*
- [x] **5.6 `deprecated`-Leitlinie** — Kommentar bei `ContainerBlockSave` in `block-editor.js`: bei künftigen `save()`-Änderungen ist ein `deprecated`-Eintrag zu pflegen; block-recovery.js bleibt Sicherheitsnetz.

---

## Arbeitsanweisungen & offene Pakete

Die vollständige, schrittweise Arbeitsanleitung für alle offenen Punkte (Aufgaben
A–F: Quick-Edit, Import/Export, Formular-Entduplizierung, CBD_Admin-Aufteilung,
Service-Container, Archiv-Assets) steht in einer eigenen Datei — mit exakten
Datei-/Zeilenangaben, Zielarchitektur, Abnahmekriterien und den vorab zu
klärenden Diskussionspunkten:

➡️ **[UMSETZUNGSPLAN-OFFENE-PUNKTE.md](UMSETZUNGSPLAN-OFFENE-PUNKTE.md)**

---

## Nachreview vom 03.07.2026 (v3.1.48)

Kritische Selbstprüfung aller Sanierungs-Commits. Vier Schwachstellen in den eigenen Änderungen gefunden und behoben:

- [x] **N.1 Pluralisierungs-Bug** in block-recovery.js („Container-Blocköcke") behoben.
- [x] **N.2 Asset-Gate zu eng** — `frontend_has_container_block()` nutzt jetzt Präfix-Suche (`wp:container-block-designer/`, erkennt alle Block-Varianten) und lädt konservativ, wenn wiederverwendbare Blöcke (`wp:block`) auf der Seite sind.
- [x] **N.3 Gleiche Reusable-Block-Lücke** bei KaTeX (`should_load_katex`) und der Feature-Erkennung geschlossen — bei `wp:block` im Inhalt wird konservativ geladen bzw. auf die globale Feature-Liste zurückgefallen.
- [x] **N.4 Capability-Sync** — `ensure_roles_exist()` synchronisiert bestehende `block_redakteur`-Rollen mit der kanonischen Definition (nicht nur bei Neuerstellung) und trägt beim Admin jede der drei CBD-Caps einzeln nach (wichtig: die neuen AJAX-Gates prüfen `cbd_admin_blocks`).

Geprüft und in Ordnung: Rekursion der Block-Slug-Extraktion, Organizer-AJAX-Handler (Nonce + manage_options), Migration schreibt nur wp_posts, Transient-Signaturen/Invalidierung, keine JS-Aufrufer der gehärteten `cbd_save_block`-Action.

### Zweiter Nachreview (Plan-Prüfung, v3.1.49)

Prüfung des UMSETZUNGSPLAN-OFFENE-PUNKTE.md auf Fehler (Sicherheit + Performance) sowie erneuter Code-Scan:

- [x] **Übersehene unpinnte CDN-Abhängigkeit gefixt** — `lucide-static@latest` bestand noch in der Admin-Enqueue (`class-cbd-admin.php:339`, nur new/edit-block-Seiten); Phase 2 hatte nur die Frontend-/Classroom-Vorkommen gepinnt. Jetzt `0.454.0` (Supply-Chain-Risiko im Admin + Caching). Keine `@latest`-Referenzen mehr im Plugin.
- [x] **Plan-Fehler Aufgabe C korrigiert** — Enqueue erfolgt über `switch ($page)` (aus `$_GET['page']`), nicht über `$hook`.
- [x] **Plan-Lücke Aufgabe B geschlossen** — Upload-Feld akzeptiert `.json,.zip`; verbindliche Sicherheits-Sektion ergänzt (ZIP-Support streichen, Upload-/MIME-/Größenvalidierung, JSON-Roundtrip, kein unserialize).
- [x] **Aufgabe A Nonce verifiziert** — `cbdAdmin.nonce` = `wp_create_nonce('cbd_admin')`, gesendet als `_wpnonce`.
- [x] **Querschnitt-Sektionen ergänzt** — verbindliche Sicherheits-Checkliste (8 Punkte) und Performance-Regeln (6 Punkte) für ALLE Aufgaben; Diskussionspunkte mit Kontext/Optionen/Empfehlung/Konsequenz ausgearbeitet.

### Nebenbefunde (pre-existing, nicht durch Sanierung verursacht)

- `cbd_save_block_quick` (admin-blocks-list.js) hat keinen PHP-Handler — Quick-Edit in der Blockliste war schon immer funktionslos. Entweder Handler ergänzen oder JS-Teil entfernen.
- Import/Export-Seite: `cbd_preview_export`-Action hat keinen registrierten Handler; die Import-Formulare werden serverseitig nicht verarbeitet — die Seite ist weitgehend UI ohne Funktion.
- Auf Archiv-/Übersichtsseiten (`is_singular() === false`) werden CBD-Assets nicht geladen; Container in Beitragslisten sind dort ohne Features/Styles (Excerpts strippen Blöcke i. d. R. — geringes Risiko, aber bewusste Einschränkung).

## Test-Checkliste (nach jeder Phase)

1. `for file in *.php includes/*.php includes/Database/*.php; do php -l "$file" || exit 1; done`
2. Seite mit Container-Block: Rendering, Collapse, Copy, Screenshot, PDF, Nummerierung, LaTeX
3. Seite OHNE Container-Block: keine CBD-Assets im Network-Tab (ab Phase 2)
4. Editor: Block einfügen, Design wählen, speichern, neu laden (Recovery-Notice darf NICHT erscheinen)
5. Als Block-Redakteur einloggen: Rechte unverändert
6. Classroom: Lehrer-Login, Schüler-Passwort-Login, Zeichnungen
7. `node create-plugin-zip.js` → ZIP auf Staging installieren
