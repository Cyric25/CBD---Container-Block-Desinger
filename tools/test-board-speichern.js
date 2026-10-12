/*
 * Prüfungen AP-4.4 (PLAN-Rueckmeldungen-Klassenmodus-und-Tafel-Lasso.md,
 * Phase 4): Die Elementliste (Modell v1) wird gespeichert und geladen -
 * Server (cbd_save_drawing/cbd_load_drawing, Feld elemente_data),
 * lokal (Begleitschlüssel <PNG-Schlüssel>-elemente, E9), beim Seitenwechsel
 * (pageCache {png, modell}) und beim Export/Import der eigenen Notizen.
 *
 * Wird von tools/test-board-zeiger.html NACH test-board-modell.js geladen
 * und hängt sich an dessen Haken window.__zusatzTests /
 * window.__zusatzTestsAsync an (die vorhandenen Haken werden zuerst
 * aufgerufen, dann diese Prüfungen). Der Server wird mit einem
 * nachgestellten window.fetch simuliert (keine echte Anfrage).
 *
 * Ohne Browser nicht lauffähig (Canvas, PointerEvents, localStorage).
 */
(function() {
    'use strict';

    var B = window.CBDBoardMode;
    var E = window.CBDBoardElemente;
    var CID = 'test-zeiger';
    var SCHLUESSEL = 'cbd-board-' + CID;
    var ctx;
    var BILD = null;

    var vorherSync = window.__zusatzTests;
    var vorherAsync = window.__zusatzTestsAsync;

    // ------------------------------------------------------------------
    // Hilfen
    // ------------------------------------------------------------------
    function el() {
        return (B.modell && B.modell.elemente) ? B.modell.elemente : [];
    }

    function typen() {
        return el().map(function(e) { return e.typ; }).join(',');
    }

    function pixel(x, y) {
        var dpr = B.dpr || 1;
        return B.drawingCtx.getImageData(Math.round(x * dpr), Math.round(y * dpr), 1, 1).data;
    }

    function istDunkel(px) {
        return px[3] > 200 && px[0] < 80 && px[1] < 80 && px[2] < 80;
    }

    function lies(k) {
        try { return localStorage.getItem(k); } catch (e) { return null; }
    }

    function setze(k, v) {
        try { localStorage.setItem(k, v); } catch (e) {}
    }

    function strich(x1, y1, x2, y2) {
        ctx.zeiger('pointerdown', 3, 'pen', x1, y1);
        ctx.zeiger('pointermove', 3, 'pen', x2, y2);
        ctx.zeiger('pointerup', 3, 'pen', x2, y2);
    }

    function tippen(x, y) {
        ctx.zeiger('pointerdown', 3, 'pen', x, y);
        ctx.zeiger('pointerup', 3, 'pen', x, y);
    }

    function text(x, y, inhalt) {
        B.setTool('text');
        ctx.zeiger('pointerdown', 1, 'mouse', x, y);
        var feld = document.querySelector('.cbd-board-text-input');
        if (!feld) throw new Error('kein Textfeld');
        feld.value = inhalt;
        feld.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true, cancelable: true }));
    }

    /** Stift schwarz + Textmarker + Text: die Testzeichnung der AP-Kriterien. */
    function testzeichnung() {
        B.setTool('pen');
        B.setColor('#000000');
        strich(40, 100, 360, 100);
        strich(40, 200, 360, 200);
        B.setTool('highlighter');
        B.setColor('#ffff00');
        strich(40, 150, 360, 150);
        B.setColor('#1565c0');
        text(60, 260, 'Na+ "Cl-"');
        B.setTool('pen');
        B.setColor('#000000');
    }

    /** Erwarteter gespeicherter Wert: Modell mit den AKTUELLEN CSS-Maßen. */
    function erwarteteSerialisierung() {
        return E.serialisiere({ v: 1, breite: B.cssWidth, hoehe: B.cssHeight, elemente: el() });
    }

    /**
     * AP-4.fix2: lokaler -elemente-Wert = Hülle {"png":"<abdruck des PNG>","modell":<Modell v1>}.
     * huelle() baut sie unabhängig vom Modul (feste Form), lokalesModell()
     * liest den Modelltext nur bei passendem Abdruck heraus.
     */
    function huelle(png, modellText) {
        return '{"png":"' + (E.abdruck ? E.abdruck(png) : '?') + '","modell":' + modellText + '}';
    }

    function lokalesModell(wert, png) {
        if (typeof wert !== 'string' || typeof png !== 'string' || !E.abdruck) return null;
        var kopf = '{"png":"' + E.abdruck(png) + '","modell":';
        if (wert.indexOf(kopf) !== 0 || wert.charAt(wert.length - 1) !== '}') return null;
        return wert.slice(kopf.length, -1);
    }

    function status() {
        var s = document.getElementById('cbd-board-save-status');
        return s ? s.textContent : null;
    }

    /** Modell in doppelter Größe der Zeichenfläche (für E13-Prüfungen). */
    function doppeltesModell() {
        return {
            v: 1, breite: B.cssWidth * 2, hoehe: B.cssHeight * 2,
            elemente: [
                { id: 'e1', typ: 'strich', werkzeug: 'pen', farbe: '#000000', breite: 6, punkte: [[200, 200], [400, 200]] },
                { id: 'e2', typ: 'text', farbe: '#000000', breite: 3, text: 'Hallo', x: 100, y: 400 }
            ]
        };
    }

    function pruefeHalbiert() {
        var f = [];
        if (typen() !== 'strich,text') f.push('Elemente ' + typen());
        else {
            var s = el()[0];
            if (JSON.stringify(s.punkte) !== '[[100,100],[200,100]]') f.push('Punkte nicht gestreckt ' + JSON.stringify(s.punkte));
            if (s.breite !== 3) f.push('Strichbreite ' + s.breite);
            if (el()[1].x !== 50 || el()[1].y !== 200) f.push('Text bei ' + el()[1].x + '/' + el()[1].y);
        }
        if (B.modell && (B.modell.breite !== B.cssWidth || B.modell.hoehe !== B.cssHeight)) f.push('Modellmaße ' + B.modell.breite + 'x' + B.modell.hoehe);
        if (B.undoStapel.length !== 0) f.push('Laden legt Undo-Schritt an');
        return f;
    }

    // Wiederöffnen OHNE Speicher zu leeren (anders als ctx.oeffnen())
    function wiederOeffnen() {
        if (B.overlay) B.destroy();
        B.open(CID, '<p>Inhalt für den Test</p>', '#ffffff');
        B.setTool('pen');
    }

    // ---- nachgestellter Server ----
    var echtesFetch = window.fetch;
    var anfragen = [];

    function formularAlsObjekt(fd) {
        var o = {};
        if (fd && typeof fd.forEach === 'function') {
            fd.forEach(function(wert, name) { o[name] = wert; });
        }
        return o;
    }

    /**
     * window.fetch ersetzen. antwort(daten) liefert das JSON-Objekt der
     * Antwort ODER ein Promise darauf (für verzögerte Antworten).
     */
    function serverStub(antwort) {
        anfragen = [];
        window.fetch = function(url, opts) {
            var daten = formularAlsObjekt(opts && opts.body);
            anfragen.push(daten);
            return Promise.resolve(antwort(daten)).then(function(json) {
                return { json: function() { return Promise.resolve(json); } };
            });
        };
    }

    function serverAus() {
        window.fetch = echtesFetch;
    }

    /** Tafel lokal öffnen und danach in den Klassenmodus (Lehrperson) schalten. */
    function serverModus(antwort) {
        ctx.oeffnen();
        serverStub(antwort);
        B.classId = '7';
        B.ajaxUrl = '/nachgestellt/admin-ajax.php';
        B.nonce = 'test-nonce';
        B.pageId = '5663';
        B.stableContainerId = CID;
    }

    function anfrage(aktion) {
        for (var i = anfragen.length - 1; i >= 0; i--) {
            if (anfragen[i].action === aktion) return anfragen[i];
        }
        return null;
    }

    function mitServer(fn) {
        return function() {
            return Promise.resolve().then(fn).then(function(r) { serverAus(); return r; },
                function(e) { serverAus(); throw e; });
        };
    }

    function bildGeladen() {
        return B.bildElementeGeladen() && el().some(function(e) { return e.typ === 'bild'; });
    }

    // ------------------------------------------------------------------
    // Synchrone Prüfungen: lokal ("Eigene Notizen") und Seitenwechsel
    // ------------------------------------------------------------------
    function lokalSpeichertElemente() {
        ctx.oeffnen();
        testzeichnung();
        B.saveDrawing();
        var f = [];
        var png = lies(SCHLUESSEL);
        var wert = lies(SCHLUESSEL + '-elemente');
        if (!png || png.indexOf('data:image/png') !== 0) f.push('PNG fehlt');
        if (!wert) f.push('Schlüssel -elemente fehlt');
        else {
            // AP-4.fix2: Liste in der Hülle {"png":"<abdruck des PNG>","modell":…}
            var kopf = '{"png":"' + (E.abdruck ? E.abdruck(png) : '?') + '","modell":';
            if (wert !== kopf + erwarteteSerialisierung() + '}') f.push('Wert weicht von Hülle(abdruck(PNG), serialisiere(modell mit aktuellen Maßen)) ab: ' + wert.slice(0, 40));
            var m = E.deserialisiere(lokalesModell(wert, png));
            if (!m || m.verworfen !== 0) f.push('gespeicherter Wert ungültig');
            else if (m.modell.elemente.map(function(e) { return e.typ; }).join(',') !== 'strich,strich,strich,text') f.push('Typen ' + m.modell.elemente.map(function(e) { return e.typ; }).join(','));
        }
        return f.length ? { ok: false, detail: f.join('; ') } : true;
    }

    function lokalBreiteAktuell() {
        ctx.oeffnen();
        B.setTool('pen');
        strich(40, 100, 200, 100);
        // Modell behält die Maße vom Öffnen - gespeichert werden die aktuellen
        B.modell.breite = B.cssWidth + 100;
        B.modell.hoehe = B.cssHeight + 50;
        B.saveDrawing();
        var m = E.deserialisiere(lokalesModell(lies(SCHLUESSEL + '-elemente'), lies(SCHLUESSEL)));
        if (!m) return { ok: false, detail: 'nichts gespeichert' };
        var soll = E.deserialisiere(E.serialisiere({ v: 1, breite: B.cssWidth, hoehe: B.cssHeight, elemente: [] })).modell;
        return (m.modell.breite === soll.breite && m.modell.hoehe === soll.hoehe) ? true :
            { ok: false, detail: 'gespeichert ' + m.modell.breite + 'x' + m.modell.hoehe + ' statt ' + soll.breite + 'x' + soll.hoehe };
    }

    function lokalLeerEntferntElemente() {
        ctx.oeffnen();
        setze(SCHLUESSEL, 'data:image/png;base64,AAAA');
        setze(SCHLUESSEL + '-elemente', '{"v":1}');
        B.saveDrawing(); // leere Tafel
        var f = [];
        if (lies(SCHLUESSEL) !== null) f.push('PNG nicht entfernt');
        if (lies(SCHLUESSEL + '-elemente') !== null) f.push('Schlüssel -elemente nicht entfernt');
        return f.length ? { ok: false, detail: f.join('; ') } : true;
    }

    function lokalQuota() {
        ctx.oeffnen();
        testzeichnung();
        setze(SCHLUESSEL + '-elemente', 'alter Stand');
        var echt = Storage.prototype.setItem;
        var f = [];
        Storage.prototype.setItem = function(k, v) {
            if (String(k).slice(-9) === '-elemente') {
                var fehler = new Error('Quota (nachgestellt)');
                fehler.name = 'QuotaExceededError';
                throw fehler;
            }
            return echt.call(this, k, v);
        };
        try {
            B.saveDrawing();
        } finally {
            Storage.prototype.setItem = echt;
        }
        var png = lies(SCHLUESSEL);
        if (!png || png.indexOf('data:image/png') !== 0) f.push('PNG nicht gespeichert');
        if (lies(SCHLUESSEL + '-elemente') !== null) f.push('Schlüssel -elemente nicht entfernt (' + lies(SCHLUESSEL + '-elemente') + ')');
        if (status() !== 'Nur als Bild gespeichert') f.push('Status "' + status() + '"');
        return f.length ? { ok: false, detail: f.join('; ') } : true;
    }

    function lokalWiederOeffnenElemente() {
        ctx.oeffnen();
        testzeichnung();
        var vorher = JSON.stringify(el());
        B.saveDrawing();
        wiederOeffnen();
        var f = [];
        if (JSON.stringify(el()) !== vorher) f.push('Elemente nach Wiederöffnen ' + typen());
        if (B.undoStapel.length !== 0) f.push('Laden legt Undo-Schritt an');
        if (!istDunkel(pixel(200, 100))) f.push('nicht gezeichnet');
        // Beweis: einzelne Striche lassen sich mit dem Strich-Radierer entfernen
        B.setTool('eraser-stroke');
        tippen(200, 100);
        if (typen() !== 'strich,strich,text') f.push('nach Strich-Radieren ' + typen());
        if (pixel(200, 100)[3] !== 0) f.push('radierter Strich noch sichtbar');
        if (!istDunkel(pixel(200, 200))) f.push('anderer Strich mitradiert');
        return f.length ? { ok: false, detail: f.join('; ') } : true;
    }

    function lokalGestreckt() {
        ctx.oeffnen();
        setze(SCHLUESSEL, BILD.src);
        setze(SCHLUESSEL + '-elemente', huelle(BILD.src, E.serialisiere(doppeltesModell())));
        wiederOeffnen();
        var f = pruefeHalbiert();
        if (!istDunkel(pixel(150, 100))) f.push('gestreckter Strich nicht gezeichnet');
        return f.length ? { ok: false, detail: f.join('; ') } : true;
    }

    function lokalVerworfenNimmtBild() {
        ctx.oeffnen();
        var m = doppeltesModell();
        m.elemente.push({ id: 'e3', typ: 'kreis', x: 1, y: 1 });
        setze(SCHLUESSEL, BILD.src);
        setze(SCHLUESSEL + '-elemente', huelle(BILD.src, JSON.stringify(m)));
        wiederOeffnen();
        return typen() === 'bild' ? true : { ok: false, detail: 'Elemente ' + typen() + ' (unvollständige Liste statt PNG)' };
    }

    function lokalUngueltigNimmtBild() {
        ctx.oeffnen();
        setze(SCHLUESSEL, BILD.src);
        setze(SCHLUESSEL + '-elemente', huelle(BILD.src, '{"v":2,"elemente":[]}'));
        wiederOeffnen();
        return typen() === 'bild' ? true : { ok: false, detail: 'Elemente ' + typen() };
    }

    // ---- AP-4.fix3 (AP-4.rev F3): Speicher voll - PNG vor Elementlisten retten ----
    var VOLL_TEXT = 'Nicht gespeichert – Speicher voll';

    function quotaFehler() {
        var fehler = new Error('Quota (nachgestellt)');
        fehler.name = 'QuotaExceededError';
        return fehler;
    }

    function elementeSchluesselAusser(eigen) {
        var liste = [];
        for (var i = 0; i < localStorage.length; i++) {
            var k = localStorage.key(i);
            if (k && k.indexOf('cbd-board-') === 0 && k.slice(-9) === '-elemente' && k !== eigen) liste.push(k);
        }
        return liste;
    }

    /** Storage.prototype.setItem ersetzen, solange fn läuft. */
    function mitSetItem(ersatz, fn) {
        var echt = Storage.prototype.setItem;
        Storage.prototype.setItem = function(k, v) { return ersatz.call(this, echt, k, v); };
        try {
            return fn();
        } finally {
            Storage.prototype.setItem = echt;
        }
    }

    /** Fremde -elemente belegen den Platz: Schreiben wirft, solange es sie gibt. */
    function lokalVollFremdeElementeWeichen() {
        ctx.oeffnen();
        setze('cbd-board-fremd-A', 'data:image/png;base64,QQ==');
        setze('cbd-board-fremd-A-elemente', '{"png":"x","modell":{}}');
        setze('cbd-board-fremd-B:p1-elemente', '{"png":"y","modell":{}}');
        setze(SCHLUESSEL, 'data:image/png;base64,QUxU');
        testzeichnung();
        var eigen = SCHLUESSEL + '-elemente';
        mitSetItem(function(echt, k, v) {
            if (elementeSchluesselAusser(eigen).length > 0) throw quotaFehler();
            return echt.call(this, k, v);
        }, function() { B.saveDrawing(); });
        var f = [];
        var png = lies(SCHLUESSEL);
        if (!png || png.indexOf('data:image/png') !== 0 || png === 'data:image/png;base64,QUxU') f.push('PNG nicht gespeichert');
        if (lies('cbd-board-fremd-A-elemente') !== null || lies('cbd-board-fremd-B:p1-elemente') !== null) f.push('fremde -elemente nicht entfernt');
        if (lies('cbd-board-fremd-A') !== 'data:image/png;base64,QQ==') f.push('fremdes PNG angetastet');
        if (png && !lokalesModell(lies(eigen), png)) f.push('eigene Elemente danach nicht gespeichert');
        if (status() === VOLL_TEXT) f.push('Status meldet "Speicher voll", obwohl gespeichert');
        return f.length ? { ok: false, detail: f.join('; ') } : true;
    }

    /** Reicht das nicht, weicht als Zweites die eigene alte Liste. */
    function lokalVollEigeneElementeWeichen() {
        ctx.oeffnen();
        var eigen = SCHLUESSEL + '-elemente';
        setze(SCHLUESSEL, 'data:image/png;base64,QUxU');
        setze(eigen, '{"png":"alt","modell":{}}');
        testzeichnung();
        mitSetItem(function(echt, k, v) {
            if (k === SCHLUESSEL && lies(eigen) !== null) throw quotaFehler();
            return echt.call(this, k, v);
        }, function() { B.saveDrawing(); });
        var f = [];
        var png = lies(SCHLUESSEL);
        if (!png || png === 'data:image/png;base64,QUxU') f.push('PNG nicht gespeichert');
        else if (!lokalesModell(lies(eigen), png)) f.push('neue Hülle fehlt (' + String(lies(eigen)).slice(0, 30) + ')');
        return f.length ? { ok: false, detail: f.join('; ') } : true;
    }

    /** Endgültig voll: sichtbare Meldung, alter Stand bleibt unverändert. */
    function lokalVollEndgueltig() {
        ctx.oeffnen();
        setze(SCHLUESSEL, 'data:image/png;base64,QUxU');
        testzeichnung();
        var meldungen = [];
        var echtWarn = console.warn;
        console.warn = function() { meldungen.push(Array.prototype.join.call(arguments, ' ')); };
        try {
            mitSetItem(function(echt, k, v) {
                if (k === SCHLUESSEL) throw quotaFehler();
                return echt.call(this, k, v);
            }, function() { B.saveDrawing(); });
        } finally {
            console.warn = echtWarn;
        }
        var f = [];
        if (status() !== VOLL_TEXT) f.push('Status "' + status() + '"');
        if (lies(SCHLUESSEL) !== 'data:image/png;base64,QUxU') f.push('alter Stand verändert');
        if (lies(SCHLUESSEL + '-elemente') !== null) f.push('-elemente ohne passendes PNG geschrieben');
        return f.length ? { ok: false, detail: f.join('; ') } : true;
    }

    // ---- AP-4.fix2 (AP-4.rev F2): veraltete Liste nach einem Rückweg ----

    /** Gültige Liste OHNE Hülle (Stand der Phase-4-Tests vor AP-4.fix2) -> PNG gilt. */
    function lokalOhneHuelleNimmtBild() {
        ctx.oeffnen();
        setze(SCHLUESSEL, BILD.src);
        setze(SCHLUESSEL + '-elemente', E.serialisiere(doppeltesModell()));
        wiederOeffnen();
        return typen() === 'bild' ? true : { ok: false, detail: 'Elemente ' + typen() + ' (Liste ohne Abdruck übernommen)' };
    }

    /** Die gespeicherte Hülle trägt den Abdruck genau des gespeicherten PNG. */
    function lokalHuelleTraegtAbdruck() {
        ctx.oeffnen();
        testzeichnung();
        B.addPage();
        B.setTool('pen');
        B.setColor('#000000');
        strich(40, 300, 360, 300);
        B.saveDrawing();
        var f = [];
        [SCHLUESSEL, SCHLUESSEL + ':p1'].forEach(function(k) {
            var png = lies(k), wert = lies(k + '-elemente');
            if (!png || !wert) { f.push(k + ': PNG oder -elemente fehlt'); return; }
            if (!E.abdruck || wert.indexOf('{"png":"' + E.abdruck(png) + '","modell":{"v":1,') !== 0) f.push(k + ': Hülle/Abdruck falsch (' + wert.slice(0, 40) + ')');
        });
        return f.length ? { ok: false, detail: f.join('; ') } : true;
    }

    function seitenwechselBehaeltElemente() {
        ctx.oeffnen();
        B.setTool('pen');
        B.setColor('#000000');
        strich(40, 100, 360, 100);
        strich(40, 200, 360, 200);
        var seite1 = JSON.stringify(el());
        B.addPage();
        strich(100, 300, 300, 300);
        var seite2 = JSON.stringify(el());
        B.goToPage(0);
        var f = [];
        if (JSON.stringify(el()) !== seite1) f.push('Seite 1 zurück als ' + typen());
        if (B.undoStapel.length !== 0) f.push('Undo-Stapel ' + B.undoStapel.length);
        if (!istDunkel(pixel(200, 100))) f.push('Seite 1 nicht gezeichnet');
        if (pixel(200, 300)[3] !== 0) f.push('Seite 2 auf Seite 1 sichtbar');
        var c = B.pageCache[1];
        if (!c || typeof c !== 'object' || !c.modell || typeof c.png !== 'string' || c.png.indexOf('data:image/png') !== 0) f.push('pageCache[1] ist nicht {png, modell}');
        if (!lies(SCHLUESSEL + ':p1-elemente')) f.push('Seite 2 ohne -elemente gespeichert');
        B.goToPage(1);
        if (JSON.stringify(el()) !== seite2) f.push('Seite 2 zurück als ' + typen());
        // einzeln radierbar
        B.goToPage(0);
        B.setTool('eraser-stroke');
        tippen(200, 100);
        if (el().length !== 1 || el()[0].punkte[0][1] !== 200) f.push('Strich-Radierer auf Seite 1: ' + typen());
        return f.length ? { ok: false, detail: f.join('; ') } : true;
    }

    function seitenwechselNeueIdsEindeutig() {
        ctx.oeffnen();
        B.setTool('pen');
        strich(40, 100, 360, 100);
        B.addPage();
        strich(40, 100, 360, 100);
        B.goToPage(0);
        strich(40, 300, 360, 300);
        var ids = el().map(function(e) { return e.id; });
        var eindeutig = ids.filter(function(id, i) { return ids.indexOf(id) === i; }).length === ids.length;
        return (ids.length === 2 && eindeutig) ? true : { ok: false, detail: 'IDs ' + ids.join(',') };
    }

    function loeschenEntferntElemente() {
        ctx.oeffnen();
        testzeichnung();
        B.saveDrawing();
        if (!lies(SCHLUESSEL + '-elemente')) return { ok: false, detail: 'Vorbedingung: nichts gespeichert' };
        B.showClearConfirm();
        var knopf = document.querySelector('.cbd-board-confirm-delete');
        if (!knopf) return { ok: false, detail: 'kein Löschen-Knopf' };
        knopf.click();
        var f = [];
        if (lies(SCHLUESSEL) !== null) f.push('PNG bleibt');
        if (lies(SCHLUESSEL + '-elemente') !== null) f.push('Schlüssel -elemente bleibt');
        return f.length ? { ok: false, detail: f.join('; ') } : true;
    }

    // ---- Export/Import der eigenen Notizen (board-mode.js) ----
    var NOTIZEN = {
        'cbd-board-X': 'data:image/png;base64,AAAA',
        'cbd-board-X-elemente': '{"v":1,"breite":10,"hoehe":10,"elemente":[]}',
        'cbd-board-X-bgcolor': '#ffffff',
        'cbd-board-X-classid': '7',
        'cbd-board-pagecount-X': '2',
        'cbd-board-X:p1': 'data:image/png;base64,BBBB',
        'cbd-board-X:p1-elemente': '{"v":1,"breite":10,"hoehe":10,"elemente":[]}',
        'cbd-board-tool-settings': '{}'
    };

    function notizenAnlegen() {
        Object.keys(NOTIZEN).forEach(function(k) { setze(k, NOTIZEN[k]); });
    }

    function mitDialogStubs(fn) {
        var echtAlert = window.alert, echtConfirm = window.confirm;
        var meldungen = [];
        window.alert = function(t) { meldungen.push(String(t)); };
        window.confirm = function(t) { meldungen.push(String(t)); return true; };
        try {
            return fn(meldungen);
        } finally {
            window.alert = echtAlert;
            window.confirm = echtConfirm;
        }
    }

    function loeschenAlleZaehlt() {
        ctx.speicherLeeren();
        notizenAnlegen();
        return mitDialogStubs(function(meldungen) {
            B.deleteAllPersonalNotes();
            var f = [];
            if (meldungen.join('|').indexOf(' 2 ') === -1) f.push('Zählung in Meldungen: ' + meldungen.join(' | ').replace(/\n/g, ' ').slice(0, 200));
            Object.keys(NOTIZEN).forEach(function(k) {
                var soll = (k === 'cbd-board-tool-settings');
                if ((lies(k) !== null) !== soll) f.push(k + (soll ? ' gelöscht' : ' nicht gelöscht'));
            });
            return f.length ? { ok: false, detail: f.join('; ') } : true;
        });
    }

    // ------------------------------------------------------------------
    // Asynchrone Prüfungen: Server (nachgestellt), echte Ladewege
    // ------------------------------------------------------------------
    function erfolg(daten) {
        return { success: true, data: daten || { message: 'ok' } };
    }

    function serverSpeichertElemente() {
        serverModus(function() { return erfolg(); });
        testzeichnung();
        var soll = erwarteteSerialisierung();
        B.saveDrawing();
        var a = anfrage('cbd_save_drawing');
        if (!a) return { ok: false, detail: 'keine Speicheranfrage' };
        var f = [];
        if (!a.drawing_data || a.drawing_data.indexOf('data:image/png') !== 0) f.push('drawing_data kein PNG');
        if (a.elemente_data !== soll) f.push('elemente_data weicht ab (' + String(a.elemente_data).slice(0, 60) + ')');
        if (a.container_id !== CID || a.class_id !== '7') f.push('Container/Klasse ' + a.container_id + '/' + a.class_id);
        return ctx.warteAuf(function() { return status() === 'Gespeichert'; }, 2000).then(function() {
            return f.length ? { ok: false, detail: f.join('; ') } : true;
        });
    }

    function serverLeerSendetLeer() {
        serverModus(function() { return erfolg(); });
        B.saveDrawing();
        var a = anfrage('cbd_save_drawing');
        if (!a) return { ok: false, detail: 'keine Speicheranfrage' };
        return (a.drawing_data === '' && a.elemente_data === '') ? true :
            { ok: false, detail: 'drawing_data "' + String(a.drawing_data).slice(0, 20) + '", elemente_data ' + JSON.stringify(a.elemente_data) };
    }

    function serverVerworfen() {
        serverModus(function(d) {
            return d.action === 'cbd_save_drawing' ? erfolg({ message: 'ok', elemente_verworfen: true }) : erfolg();
        });
        testzeichnung();
        B.saveDrawing();
        return ctx.warteAuf(function() { return status() === 'Nur als Bild gespeichert'; }, 2000).then(function() { return true; },
            function() { return { ok: false, detail: 'Status "' + status() + '"' }; });
    }

    function serverLaedtElemente() {
        var modellText = null;
        serverModus(function(d) {
            return d.action === 'cbd_load_drawing' ? erfolg({ drawing_data: BILD.src, elemente_data: modellText }) : erfolg();
        });
        modellText = E.serialisiere(doppeltesModell());
        B.loadFromServer(CID);
        return ctx.warteAuf(function() { return el().length > 0; }, 2000).then(function() {
            var f = pruefeHalbiert();
            if (el().some(function(e) { return e.typ === 'bild'; })) f.push('zusätzlich Bild-Element');
            if (!istDunkel(pixel(150, 100))) f.push('nicht gezeichnet');
            // einzeln radierbar
            B.setTool('eraser-stroke');
            tippen(150, 100);
            if (typen() !== 'text') f.push('nach Strich-Radieren ' + typen());
            return f.length ? { ok: false, detail: f.join('; ') } : true;
        });
    }

    function serverOhneElementeNimmtBild(elementeWert) {
        return function() {
            serverModus(function(d) {
                return d.action === 'cbd_load_drawing' ? erfolg({ drawing_data: BILD.src, elemente_data: elementeWert }) : erfolg();
            });
            B.loadFromServer(CID);
            return ctx.warteAuf(bildGeladen, 3000).then(function() {
                var f = [];
                if (typen() !== 'bild') f.push('Elemente ' + typen());
                if (!istDunkel(pixel(200, 150))) f.push('Bild nicht gezeichnet');
                return f.length ? { ok: false, detail: f.join('; ') } : true;
            });
        };
    }

    function serverSpaeteAntwortNachSeitenwechsel() {
        var freigeben;
        var spaet = new Promise(function(r) { freigeben = r; });
        serverModus(function(d) {
            if (d.action === 'cbd_load_drawing') {
                return spaet.then(function() { return erfolg({ drawing_data: BILD.src, elemente_data: E.serialisiere(doppeltesModell()) }); });
            }
            return erfolg();
        });
        B.loadFromServer(CID);
        B.addPage(); // Seite gewechselt, bevor die Antwort kommt
        freigeben();
        return new Promise(function(r) { setTimeout(r, 300); }).then(function() {
            return (B.currentPageIndex === 1 && el().length === 0) ? true :
                { ok: false, detail: 'Seite ' + B.currentPageIndex + ' bekam ' + typen() };
        });
    }

    function serverStrichVorLadenBleibt() {
        var freigeben;
        var spaet = new Promise(function(r) { freigeben = r; });
        serverModus(function(d) {
            if (d.action === 'cbd_load_drawing') {
                return spaet.then(function() {
                    return erfolg({ drawing_data: BILD.src, elemente_data: E.serialisiere({ v: 1, breite: B.cssWidth, hoehe: B.cssHeight, elemente: [
                        { id: 'e1', typ: 'strich', werkzeug: 'pen', farbe: '#000000', breite: 3, punkte: [[40, 100], [360, 100]] },
                        { id: 'e2', typ: 'strich', werkzeug: 'pen', farbe: '#000000', breite: 3, punkte: [[40, 200], [360, 200]] }
                    ] }) });
                });
            }
            return erfolg();
        });
        B.loadFromServer(CID);
        B.setTool('pen');
        strich(40, 300, 360, 300); // vor der Antwort gezeichnet
        freigeben();
        return ctx.warteAuf(function() { return el().length === 3; }, 2000).then(function() {
            var f = [];
            var ids = el().map(function(e) { return e.id; });
            if (ids.filter(function(id, i) { return ids.indexOf(id) === i; }).length !== 3) f.push('IDs doppelt ' + ids.join(','));
            if (el()[2].punkte[0][1] !== 300) f.push('eigener Strich nicht zuletzt');
            B.undo();
            if (el().length !== 2 || el()[0].punkte[0][1] !== 100) f.push('nach Undo ' + el().length + ' Elemente');
            return f.length ? { ok: false, detail: f.join('; ') } : true;
        }, function() {
            return { ok: false, detail: 'Elemente ' + typen() };
        });
    }

    /**
     * AP-4.fix2: Rückweg auf eine ältere Plugin-Version. Diese überschreibt
     * nur das PNG (cbd-board-<id>), der -elemente-Schlüssel bleibt stehen.
     * Nach erneutem Einspielen gilt das neuere PNG (EIN Bild-Element) statt
     * der veralteten Liste; das nächste Speichern schreibt eine frische Hülle.
     */
    function lokalRueckwegAltesPngGilt() {
        ctx.oeffnen();
        testzeichnung();
        B.saveDrawing();
        var alteListe = lies(SCHLUESSEL + '-elemente');
        // ältere Version speichert eine inzwischen geänderte Tafel nur als PNG
        setze(SCHLUESSEL, BILD.src);
        wiederOeffnen();
        var f = [];
        if (!alteListe) f.push('Vorbedingung: keine Liste gespeichert');
        if (typen() !== 'bild') f.push('nach Wiederöffnen ' + typen() + ' (veraltete Liste übernommen)');
        return ctx.warteAuf(bildGeladen, 3000).then(function() {
            if (!istDunkel(pixel(200, 150))) f.push('neueres PNG nicht gezeichnet');
            B.setTool('pen');
            B.setColor('#000000');
            strich(40, 350, 360, 350);
            B.saveDrawing();
            var png = lies(SCHLUESSEL), wert = lies(SCHLUESSEL + '-elemente');
            var m = E.deserialisiere(lokalesModell(wert, png));
            if (!m) f.push('nach erneutem Speichern keine passende Hülle');
            else if (m.modell.elemente.map(function(e) { return e.typ; }).join(',') !== 'bild,strich') f.push('neue Liste ' + m.modell.elemente.map(function(e) { return e.typ; }).join(','));
            return f.length ? { ok: false, detail: f.join('; ') } : true;
        });
    }

    function altePngSeiteImSeitenwechsel() {
        ctx.oeffnen();
        setze(SCHLUESSEL + ':p1', BILD.src);
        setze('cbd-board-pagecount-' + CID, '2');
        wiederOeffnen();
        B.goToPage(1);
        return ctx.warteAuf(bildGeladen, 3000).then(function() {
            var f = [];
            if (typen() !== 'bild') f.push('Elemente ' + typen());
            if (!istDunkel(pixel(200, 150))) f.push('nicht gezeichnet');
            // #7152 (AP-4.0): Textmarker über der alten Schrift einer per
            // Seitenwechsel geladenen PNG-Seite lässt die Schrift sichtbar
            B.setTool('highlighter');
            B.setColor('#ffff00');
            strich(100, 150, 300, 150);
            if (!istDunkel(pixel(200, 150))) f.push('Textmarker überdeckt alte Schrift');
            return f.length ? { ok: false, detail: f.join('; ') } : true;
        });
    }

    function mehrseitigSchliessenOeffnen() {
        ctx.oeffnen();
        B.setTool('pen');
        B.setColor('#000000');
        strich(40, 100, 360, 100);
        strich(40, 200, 360, 200);
        B.addPage();
        strich(40, 100, 360, 100);
        strich(40, 300, 360, 300);
        B.saveDrawing();
        wiederOeffnen();
        var f = [];
        if (typen() !== 'strich,strich') f.push('Seite 1 nach Öffnen ' + typen());
        B.goToPage(1);
        if (typen() !== 'strich,strich') f.push('Seite 2 nach Öffnen ' + typen());
        B.setTool('eraser-stroke');
        tippen(200, 300);
        if (el().length !== 1) f.push('Seite 2 nicht einzeln radierbar (' + el().length + ')');
        return f.length ? { ok: false, detail: f.join('; ') } : true;
    }

    // ---- Export (board-mode.js downloadPersonalNotes) ----
    function exportMitElementen() {
        ctx.speicherLeeren();
        notizenAnlegen();
        var echtUrl = URL.createObjectURL;
        var echtKlick = HTMLAnchorElement.prototype.click;
        var blob = null;
        URL.createObjectURL = function(b) { blob = b; return 'blob:nachgestellt'; };
        HTMLAnchorElement.prototype.click = function() {};
        var meldungen;
        try {
            meldungen = mitDialogStubs(function(m) { B.downloadPersonalNotes(); return m.slice(); });
        } finally {
            URL.createObjectURL = echtUrl;
            HTMLAnchorElement.prototype.click = echtKlick;
        }
        if (!blob) return { ok: false, detail: 'kein Export' };
        return blob.text().then(function(t) {
            var daten = JSON.parse(t);
            var f = [];
            ['cbd-board-X', 'cbd-board-X-elemente', 'cbd-board-X:p1', 'cbd-board-X:p1-elemente', 'cbd-board-X-bgcolor'].forEach(function(k) {
                if (daten[k] !== NOTIZEN[k]) f.push(k + ' fehlt im Export');
            });
            if (meldungen.join('|').indexOf('2 Notizen') === -1) f.push('Meldung: ' + meldungen.join(' | ').replace(/\n/g, ' '));
            return f.length ? { ok: false, detail: f.join('; ') } : true;
        });
    }

    // ---- Import (board-mode.js uploadPersonalNotes) ----
    function importiere(daten) {
        var echtKlick = HTMLInputElement.prototype.click;
        HTMLInputElement.prototype.click = function() {
            var dt = new DataTransfer();
            dt.items.add(new File([JSON.stringify(daten)], 'notizen.json', { type: 'application/json' }));
            this.files = dt.files;
            this.dispatchEvent(new Event('change'));
        };
        var echtAlert = window.alert, echtConfirm = window.confirm;
        var meldungen = [];
        window.alert = function(t) { meldungen.push(String(t)); };
        window.confirm = function(t) { meldungen.push(String(t)); return true; };
        try {
            B.uploadPersonalNotes();
        } finally {
            HTMLInputElement.prototype.click = echtKlick;
        }
        return ctx.warteAuf(function() {
            return meldungen.some(function(m) { return m.indexOf('importiert') !== -1 || m.indexOf('Fehler') !== -1 || m.indexOf('keine') !== -1; });
        }, 3000).then(function() {
            window.alert = echtAlert;
            window.confirm = echtConfirm;
            return meldungen;
        }, function(e) {
            window.alert = echtAlert;
            window.confirm = echtConfirm;
            throw e;
        });
    }

    function importZaehltUndRaeumtAuf() {
        ctx.speicherLeeren();
        // lokal: Notiz Y mit Elementen; Import einer ÄLTEREN Exportdatei, die
        // für Y nur das PNG hat -> der lokale -elemente-Stand ist veraltet
        setze('cbd-board-Y', 'data:image/png;base64,ALT');
        setze('cbd-board-Y-elemente', '{"v":1,"breite":10,"hoehe":10,"elemente":[]}');
        setze('cbd-board-tool-settings', '{}');
        return importiere({
            'cbd-board-Y': 'data:image/png;base64,NEU',
            'cbd-board-Z': 'data:image/png;base64,ZZZZ',
            'cbd-board-Z-elemente': '{"v":1,"breite":10,"hoehe":10,"elemente":[]}',
            'cbd-board-Z-bgcolor': '#1a472a'
        }).then(function(meldungen) {
            var f = [];
            var alles = meldungen.join(' | ').replace(/\n/g, ' ');
            if (lies('cbd-board-Y') !== 'data:image/png;base64,NEU') f.push('Y nicht importiert');
            if (lies('cbd-board-Y-elemente') !== null) f.push('veralteter Schlüssel Y-elemente bleibt (überdeckte das importierte PNG)');
            if (lies('cbd-board-Z-elemente') === null || lies('cbd-board-Z-bgcolor') !== '#1a472a') f.push('Z-Begleitschlüssel nicht importiert');
            if (alles.indexOf('2 Notizen wurden erfolgreich importiert') === -1) f.push('Zählung Import: ' + alles.slice(0, 200));
            if (alles.indexOf('bereits 1 persönliche') === -1) f.push('Zählung vorhandene: ' + alles.slice(0, 200));
            return f.length ? { ok: false, detail: f.join('; ') } : true;
        });
    }

    // ------------------------------------------------------------------
    // AP-4.fix1 (AP-4.rev F1): Obergrenze der Punkte. Gezählt wird wie auf
    // dem Server die Zahl der '[' in der serialisierten Liste (obere
    // Schranke der Punktzahl, billig). Darüber sendet der Client gar keine
    // Liste (spart Upload und Serverlast) und meldet "Nur als Bild
    // gespeichert", auch wenn der Server einfach Erfolg meldet.
    // ------------------------------------------------------------------
    var MAX_PUNKTE = 100000; // = CBD_Classroom::ELEMENTE_MAX_PUNKTE

    function klammern(text) {
        var n = 0, i = -1;
        while ((i = text.indexOf('[', i + 1)) !== -1) n++;
        return n;
    }

    function strichMit(punkte, y) {
        var p = [];
        for (var i = 0; i < punkte; i++) p.push([10 + (i % 300), y]);
        return { id: B.neueId(), typ: 'strich', werkzeug: 'pen', farbe: '#000000', breite: 3, punkte: p };
    }

    /** Elementliste auf genau `ziel` '[' in der Serialisierung auffüllen. */
    function auffuellenAuf(ziel) {
        var rest = ziel - klammern(erwarteteSerialisierung());
        var liste = B.modell.elemente.slice();
        while (rest > 0) {
            // ein Strich = 1 + n Klammern; nie einen Rest von 1 übrig lassen
            var n = rest - 1;
            if (n > 199) { n = (rest - 200 === 1) ? 198 : 199; }
            if (n < 1) { n = 1; }
            liste.push(strichMit(n, 400));
            rest -= (n + 1);
        }
        B.modell.elemente = liste;
        return klammern(erwarteteSerialisierung());
    }

    function serverZuVielePunkte() {
        serverModus(function() { return erfolg(); });
        testzeichnung();
        var zahl = auffuellenAuf(MAX_PUNKTE + 2000);
        B.saveDrawing();
        var a = anfrage('cbd_save_drawing');
        if (!a) return { ok: false, detail: 'keine Speicheranfrage' };
        var f = [];
        if (zahl <= MAX_PUNKTE) f.push('Vorbedingung: nur ' + zahl + ' Klammern');
        if (!a.drawing_data || a.drawing_data.indexOf('data:image/png') !== 0) f.push('drawing_data kein PNG');
        if (a.elemente_data !== '') f.push('elemente_data gesendet (' + String(a.elemente_data).length + ' Zeichen)');
        return ctx.warteAuf(function() { return status() === 'Nur als Bild gespeichert'; }, 2000).then(function() {
            return f.length ? { ok: false, detail: f.join('; ') } : true;
        }, function() {
            f.push('Status "' + status() + '"');
            return { ok: false, detail: f.join('; ') };
        });
    }

    function serverGenauAnDerGrenze() {
        serverModus(function() { return erfolg(); });
        testzeichnung();
        var zahl = auffuellenAuf(MAX_PUNKTE);
        var soll = erwarteteSerialisierung();
        B.saveDrawing();
        var a = anfrage('cbd_save_drawing');
        if (!a) return { ok: false, detail: 'keine Speicheranfrage' };
        var f = [];
        if (zahl !== MAX_PUNKTE) f.push('Vorbedingung: ' + zahl + ' Klammern');
        if (a.elemente_data !== soll) f.push('elemente_data weicht ab (' + String(a.elemente_data).length + ' statt ' + soll.length + ' Zeichen)');
        return ctx.warteAuf(function() { return status() === 'Gespeichert'; }, 2000).then(function() {
            return f.length ? { ok: false, detail: f.join('; ') } : true;
        }, function() {
            f.push('Status "' + status() + '"');
            return { ok: false, detail: f.join('; ') };
        });
    }

    // ------------------------------------------------------------------
    // Einhängen (nach den vorhandenen Haken)
    // ------------------------------------------------------------------
    window.__zusatzTests = function(c) {
        if (typeof vorherSync === 'function') vorherSync(c);
        ctx = c;
        BILD = c.basis.schwarz;
        var p = c.pruefe;
        p('AP-4.4: lokal - PNG und -elemente gespeichert (Stift, Textmarker, Text)', lokalSpeichertElemente);
        p('AP-4.4: lokal - gespeicherte Maße = aktuelle cssWidth/cssHeight', lokalBreiteAktuell);
        p('AP-4.4: lokal - leere Tafel entfernt PNG und -elemente', lokalLeerEntferntElemente);
        p('AP-4.4: lokal - Quota-Fehler: PNG bleibt, -elemente entfernt, Status', lokalQuota);
        p('AP-4.4: lokal - Wiederöffnen lädt Elemente, einzeln radierbar', lokalWiederOeffnenElemente);
        p('AP-4.4: lokal - Elemente in kleinere Fläche gestreckt (E13)', lokalGestreckt);
        p('AP-4.4: lokal - verworfene Elemente -> PNG als Bild-Element', lokalVerworfenNimmtBild);
        p('AP-4.4: lokal - ungültige Elemente -> PNG als Bild-Element', lokalUngueltigNimmtBild);
        p('AP-4.fix2: lokal - Liste ohne Hülle/Abdruck -> PNG als Bild-Element', lokalOhneHuelleNimmtBild);
        p('AP-4.fix2: lokal - Hülle trägt den Abdruck des gespeicherten PNG (Seite 0 und :p1)', lokalHuelleTraegtAbdruck);
        p('AP-4.fix3: lokal - Speicher voll: fremde -elemente weichen, PNG gespeichert', lokalVollFremdeElementeWeichen);
        p('AP-4.fix3: lokal - Speicher voll: danach weicht die eigene alte Liste', lokalVollEigeneElementeWeichen);
        p('AP-4.fix3: lokal - endgültig voll: "Nicht gespeichert – Speicher voll", alter Stand bleibt', lokalVollEndgueltig);
        p('AP-4.4: Seitenwechsel behält Elemente ({png, modell}), einzeln radierbar', seitenwechselBehaeltElemente);
        p('AP-4.4: Seitenwechsel - IDs nach Rückkehr eindeutig', seitenwechselNeueIdsEindeutig);
        p('AP-4.4: Zeichnung löschen entfernt auch -elemente', loeschenEntferntElemente);
        p('AP-4.4: Alle Notizen löschen - zählt ohne Begleitschlüssel, löscht -elemente', loeschenAlleZaehlt);
    };

    window.__zusatzTestsAsync = function(c) {
        var vorher = (typeof vorherAsync === 'function') ? vorherAsync(c) : Promise.resolve();
        return Promise.resolve(vorher).then(function() {
            ctx = c;
            BILD = c.basis.schwarz;
            var pa = c.pruefeAsync;
            return Promise.resolve()
                .then(function() { return pa('AP-4.4: Server - Speichern sendet elemente_data', mitServer(serverSpeichertElemente)); })
                .then(function() { return pa('AP-4.4: Server - leere Tafel sendet leeres elemente_data', mitServer(serverLeerSendetLeer)); })
                .then(function() { return pa('AP-4.4: Server - elemente_verworfen -> "Nur als Bild gespeichert"', mitServer(serverVerworfen)); })
                .then(function() { return pa('AP-4.4: Server - Laden übernimmt Elemente gestreckt, einzeln radierbar', mitServer(serverLaedtElemente)); })
                .then(function() { return pa('AP-4.4: Server - elemente_data null -> PNG als Bild-Element', mitServer(serverOhneElementeNimmtBild(null))); })
                .then(function() { return pa('AP-4.4: Server - ungültiges elemente_data -> PNG als Bild-Element', mitServer(serverOhneElementeNimmtBild('{"v":2}'))); })
                .then(function() { return pa('AP-4.4: Server - späte Antwort nach Seitenwechsel verworfen', mitServer(serverSpaeteAntwortNachSeitenwechsel)); })
                .then(function() { return pa('AP-4.4: Server - Strich vor der Antwort bleibt, IDs eindeutig', mitServer(serverStrichVorLadenBleibt)); })
                .then(function() { return pa('AP-4.4: alte PNG-Seite im Seitenwechsel -> Bild-Element', altePngSeiteImSeitenwechsel); })
                .then(function() { return pa('AP-4.fix2: lokal - Rückweg: neueres PNG gilt statt veralteter Liste, neue Hülle beim Speichern', lokalRueckwegAltesPngGilt); })
                .then(function() { return pa('AP-4.4: mehrseitig nach Schließen/Öffnen einzeln radierbar', mehrseitigSchliessenOeffnen); })
                .then(function() { return pa('AP-4.4: Export enthält -elemente, Zählung ohne Begleitschlüssel', exportMitElementen); })
                .then(function() { return pa('AP-4.4: Import zählt Notizen, entfernt veraltete -elemente', importZaehltUndRaeumtAuf); })
                .then(function() { return pa('AP-4.fix1: Server - mehr als 100 000 Punkte: keine Liste senden, "Nur als Bild gespeichert"', mitServer(serverZuVielePunkte)); })
                .then(function() { return pa('AP-4.fix1: Server - genau 100 000 Punkte: Liste wird gesendet', mitServer(serverGenauAnDerGrenze)); });
        });
    };
})();
