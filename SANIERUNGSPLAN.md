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

## Phase 3 — Performance strukturell (~1 Tag)

- [ ] **3.1 Blockliste zentral cachen** — `class-cbd-block-registration.php:64–66, 701, 1714` + Style-Loader
  `cbd_blocks` wird pro Request 3–4× per `SELECT *` abgefragt.
  Fix: Blockliste in Transient (Invalidierung bei `cbd_block_saved`/`cbd_block_deleted`), gemeinsam genutzt von Registration, Style-Loader und Renderer; nur benötigte Spalten.

- [ ] **3.2 N+1 „Behandelt"-Status beheben** — `class-cbd-block-registration.php:806–817`
  Pro Container-Block ein `SELECT COUNT(*)` (30 Blöcke = 30 Queries).
  Fix: beim ersten Render alle behandelten `container_id`s der Seite mit EINEM Query holen, statisch cachen.

- [ ] **3.3 Dynamisches Frontend-CSS cachen** — `class-cbd-style-loader.php:549–564, 1400, 2020–2039`
  `parse_blocks()` + DB-Query + Minifizierung pro Request.
  Fix: CSS pro Post in Transient (Key: Post-ID + `post_modified` + Block-Cache-Version).

- [ ] **3.4 `board-mode.js` (112 KB) nur bei Bedarf** — `class-cbd-style-loader.php:180–194`
  Feature-Erkennung ist site-weit statt seitenbezogen.
  Fix: auf `get_used_blocks_on_page()` (existiert bereits, Z. 1392) stützen.

- [ ] **3.5 Editor-Style-Ausgabe deduplizieren** — `class-cbd-style-loader.php:66–70, 569–602`
  `output_editor_dynamic_styles` läuft 2× (admin_head + admin_footer), `output_emergency_editor_styles` 3×.
  Fix: je ein Hook + `static $done`-Guard.

---

## Phase 4 — Toten Code entfernen (~0,5 Tag, ~6.000+ Zeilen)

- [ ] **4.1 Tote JS-Dateien löschen** (nirgends enqueued, per Grep verifiziert):
  `jspdf-loader.js`, `jspdf-loader-old.js`, `html2pdf-loader-v2.js`, `frontend-working.js`,
  `container-blocks-inline.js`, `unified-frontend.js`, alle `*.backup`-Dateien, vermutlich `assets/css/frontend.css`.
  Dazu verwaiste `wp_add_inline_script('cbd-frontend-working', ...)` in `class-cbd-block-registration.php:475`.

- [ ] **4.2 Tote PHP-Schichten löschen**:
  `includes/class-consolidated-frontend.php` (1.176 Z., überall auskommentiert),
  `includes/API/` komplett (794 Z., zweite tote REST-API — aktiv ist `class-cbd-blocks-rest-api.php`).

- [ ] **4.3 Geister-Referenzen entfernen (latente Fatal Errors!)**:
  Container-Services `frontend_renderer` und `admin_router` (`class-service-container.php:190–202`) verweisen auf nicht existierende Klassen → Fatal bei Abruf.
  Autoloader-Mappings auf nicht existierende Dateien (`class-autoloader.php:148–149`).

- [ ] **4.4 Repo-/ZIP-Hygiene**:
  `vendor/` mit `composer install --no-dev` neu bauen (PHPUnit/PHPCS sind aktuell im 21-MB-ZIP!), aus Git nehmen oder im ZIP-Skript ausschließen;
  10 Status-MD-Dateien (`FRONTEND_STATUS.md`, `POSITIONING_FIX_COMPLETE.md`, …) + `Ordnerstruktur.txt` archivieren/löschen;
  `admin/container-block-designer.php` → `admin/dashboard.php` umbenennen (Namenskollision mit Hauptdatei).

- [ ] **4.5 `cbd_get_service()` deduplizieren** — doppelt definiert in `container-block-designer.php:892` und `includes/functions.php:20` mit abweichendem Fehlerverhalten.

---

## Phase 5 — Strukturelles Refactoring (inkrementell, je ~0,5–1 Tag)

- [ ] **5.1 Rollen-Definition vereinheitlichen (WICHTIGSTES Refactoring)**
  `block_redakteur` wird 2× mit UNTERSCHIEDLICHEN Capability-Sets erstellt
  (`container-block-designer.php:747–808` vs. `class-cbd-admin.php:116–133`) — welche gilt, hängt vom Codepfad ab.
  Fix: eine `CBD_Roles`-Klasse als Single Source of Truth; ebenso `create_default_blocks()` deduplizieren (main:486 vs. admin:2397).

- [ ] **5.2 new-block.php / edit-block.php zusammenführen**
  ~2.500 Zeilen wortgleiches, bereits divergierendes Inline-JS/CSS (`populateIconGrid`, `updateLivePreview` …).
  Fix: gemeinsames Partial `admin/partials/block-form.php`, JS → `assets/js/admin-block-form.js`, CSS → `assets/css/admin.css`, Daten per `wp_localize_script`.

- [ ] **5.3 `CBD_Admin` (2.985 Z.) aufteilen**
  In: Admin_Menu (Routing/Enqueue), Admin_Ajax_Controller, Block_Preview_Renderer, Repair_Tools.
  CSS-Generierung ausschließlich im Style-Loader (aktuell dupliziert in `class-cbd-admin.php:1762 ff.`).

- [ ] **5.4 Service-Container-Entscheidung**
  Entweder ernsthaft nutzen (Singletons auflösen, `init_legacy_fallback()` löschen) oder ehrlich entfernen.
  Aktueller Zustand: doppelte Komplexität ohne Nutzen.

- [ ] **5.5 Versionen & Autoloading konsolidieren**
  CLAUDE.md (sagt 2.9.0), composer.json (2.6.x) mit realer Version synchronisieren;
  eine Autoload-Strategie (Composer classmap passt zu den bestehenden Dateinamen), manuelle require_once-Kette abbauen.

- [ ] **5.6 `deprecated`-Array im Block pflegen** — `assets/js/block-editor.js`
  Bei künftigen Änderungen an `save()` die alte Version als deprecated-Eintrag behalten,
  damit Blöcke gar nicht erst ungültig werden (block-recovery.js bleibt als Sicherheitsnetz).

---

## Test-Checkliste (nach jeder Phase)

1. `for file in *.php includes/*.php includes/Database/*.php; do php -l "$file" || exit 1; done`
2. Seite mit Container-Block: Rendering, Collapse, Copy, Screenshot, PDF, Nummerierung, LaTeX
3. Seite OHNE Container-Block: keine CBD-Assets im Network-Tab (ab Phase 2)
4. Editor: Block einfügen, Design wählen, speichern, neu laden (Recovery-Notice darf NICHT erscheinen)
5. Als Block-Redakteur einloggen: Rechte unverändert
6. Classroom: Lehrer-Login, Schüler-Passwort-Login, Zeichnungen
7. `node create-plugin-zip.js` → ZIP auf Staging installieren
