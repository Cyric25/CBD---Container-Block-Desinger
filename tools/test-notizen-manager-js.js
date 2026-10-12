#!/usr/bin/env node
/**
 * Prüfharnisch für assets/js/personal-notes-manager.js (globaler
 * Export/Import/Löschen der "Eigenen Notizen") - ohne Browser, ohne npm.
 *
 * Aufruf:  node tools/test-notizen-manager-js.js
 * Andere Datei (Mutationsproben):  CBD_NOTIZEN_DATEI=<pfad>
 *
 * Anlass: PLAN-Rueckmeldungen-Klassenmodus-und-Tafel-Lasso.md, AP-4.4
 * Schritt 7. Seit Phase 4 hat jede Tafel-Notiz einen weiteren
 * Begleitschlüssel `<PNG-Schlüssel>-elemente` (Elementmodell v1, E9).
 * Geprüft wird:
 *   - Export nimmt ALLE `cbd-board-`-Schlüssel mit (auch -elemente),
 *   - gezählt werden nur Notizen: ohne die Suffixe -elemente, -bgcolor,
 *     -classid, ohne `cbd-board-pagecount-` und ohne die Werkzeug-
 *     Einstellungen,
 *   - "Alle Notizen löschen" löscht auch -elemente,
 *   - Import einer älteren Exportdatei ohne -elemente entfernt einen
 *     lokal veralteten -elemente-Schlüssel derselben Notiz (sonst
 *     überdeckte er beim Öffnen das importierte PNG).
 *
 * Verfahren: Die ausgelieferte Datei läuft in einem vm-Kontext mit einem
 * kleinen nachgebauten DOM (nur, was die Datei anfasst), localStorage,
 * Blob, URL, FileReader, alert und confirm.
 */
'use strict';

var fs = require('fs');
var path = require('path');
var vm = require('vm');

var DATEI = process.env.CBD_NOTIZEN_DATEI ||
    path.join(__dirname, '..', 'assets', 'js', 'personal-notes-manager.js');

var bestanden = 0, fehlgeschlagen = 0;
function pruefe(name, bedingung, detail) {
    if (bedingung) {
        bestanden++;
        console.log('  ok   ' + name);
    } else {
        fehlgeschlagen++;
        console.log('  FEHL ' + name + (detail ? '  -> ' + detail : ''));
    }
}

// ---------------------------------------------------------------------------
// Umgebung
// ---------------------------------------------------------------------------
function umgebung() {
    var speicher = {};
    var reihenfolge = [];
    var localStorage = {
        get length() { return reihenfolge.length; },
        key: function(i) { return reihenfolge[i] === undefined ? null : reihenfolge[i]; },
        getItem: function(k) { return Object.prototype.hasOwnProperty.call(speicher, k) ? speicher[k] : null; },
        setItem: function(k, v) {
            k = String(k);
            if (!Object.prototype.hasOwnProperty.call(speicher, k)) reihenfolge.push(k);
            speicher[k] = String(v);
        },
        removeItem: function(k) {
            if (Object.prototype.hasOwnProperty.call(speicher, k)) {
                delete speicher[k];
                reihenfolge.splice(reihenfolge.indexOf(k), 1);
            }
        }
    };

    function element(tag) {
        var hoerer = {};
        return {
            tagName: tag,
            style: {},
            innerHTML: '',
            files: null,
            addEventListener: function(typ, fn) { (hoerer[typ] = hoerer[typ] || []).push(fn); },
            ausloesen: function(typ, ev) { (hoerer[typ] || []).forEach(function(fn) { fn(ev); }); },
            contains: function() { return false; },
            click: function() { this.ausloesen('click', { target: this }); },
            hoerer: hoerer
        };
    }

    var dom = {
        '.cbd-notes-toggle': element('button'),
        '.cbd-notes-export': element('button'),
        '.cbd-notes-import': element('button'),
        '.cbd-notes-delete-all': element('button'),
        '.cbd-notes-menu': element('div'),
        '.cbd-notes-info': element('div'),
        '.cbd-notes-manager-button': element('div')
    };
    dom['.cbd-notes-menu'].style.display = 'none';

    var letzteEingabe = null;
    var meldungen = [];
    var blobs = [];
    var confirmAntwort = true;

    var document = {
        readyState: 'complete',
        body: { appendChild: function() {}, removeChild: function() {} },
        createElement: function(tag) {
            var e = element(tag);
            if (tag === 'input') {
                letzteEingabe = e;
                e.click = function() {}; // Dateidialog: wird vom Test ausgelöst
            }
            return e;
        },
        querySelector: function(sel) { return dom[sel] || null; },
        addEventListener: function() {}
    };

    function FileReader() {}
    FileReader.prototype.readAsText = function(datei) {
        var self = this;
        self.onload({ target: { result: datei.inhalt } });
    };

    var kontext = {
        window: {},
        document: document,
        localStorage: localStorage,
        console: { log: function() {}, warn: function() {}, error: function() {} },
        alert: function(t) { meldungen.push(String(t)); },
        confirm: function(t) { meldungen.push(String(t)); return confirmAntwort; },
        Blob: function(teile) { this.text = teile.join(''); blobs.push(this); },
        URL: { createObjectURL: function() { return 'blob:x'; }, revokeObjectURL: function() {} },
        FileReader: FileReader,
        JSON: JSON,
        Date: Date,
        Object: Object
    };
    kontext.window = kontext;

    vm.createContext(kontext);
    vm.runInContext(fs.readFileSync(DATEI, 'utf8'), kontext, { filename: DATEI });

    return {
        ls: localStorage,
        dom: dom,
        meldungen: meldungen,
        blobs: blobs,
        importieren: function(daten) {
            dom['.cbd-notes-import'].click();
            if (!letzteEingabe) throw new Error('kein Dateifeld angelegt');
            letzteEingabe.ausloesen('change', { target: { files: [{ inhalt: JSON.stringify(daten) }] } });
        }
    };
}

var NOTIZEN = {
    'cbd-board-X': 'data:image/png;base64,AAAA',
    'cbd-board-X-elemente': '{"v":1,"breite":10,"hoehe":10,"elemente":[]}',
    'cbd-board-X-bgcolor': '#ffffff',
    'cbd-board-X-classid': '7',
    'cbd-board-pagecount-X': '2',
    'cbd-board-X:p1': 'data:image/png;base64,BBBB',
    'cbd-board-X:p1-elemente': '{"v":1,"breite":10,"hoehe":10,"elemente":[]}',
    'cbd-board-tool-settings': '{}',
    'cbd-board-font-size': '150',
    'anderer-schluessel': 'bleibt'
};

function mitNotizen() {
    var u = umgebung();
    Object.keys(NOTIZEN).forEach(function(k) { u.ls.setItem(k, NOTIZEN[k]); });
    return u;
}

// ---------------------------------------------------------------------------
// 1. Anzeige der Anzahl (updateInfo)
// ---------------------------------------------------------------------------
console.log('1. Anzeige der Anzahl');
(function() {
    var u = mitNotizen();
    u.dom['.cbd-notes-toggle'].click();
    var info = u.dom['.cbd-notes-info'].innerHTML;
    pruefe('1.1 zählt nur Notizen (2), keine Begleitschlüssel', info.indexOf('2 Notizen gespeichert') !== -1, info);

    var leer = umgebung();
    leer.ls.setItem('cbd-board-tool-settings', '{}');
    leer.ls.setItem('cbd-board-Q-elemente', '{}');
    leer.dom['.cbd-notes-toggle'].click();
    pruefe('1.2 nur Begleitschlüssel -> "Keine Notizen"', leer.dom['.cbd-notes-info'].innerHTML.indexOf('Keine Notizen') !== -1, leer.dom['.cbd-notes-info'].innerHTML);

    var eins = umgebung();
    eins.ls.setItem('cbd-board-Q', 'data:image/png;base64,QQ');
    eins.ls.setItem('cbd-board-Q-elemente', '{}');
    eins.dom['.cbd-notes-toggle'].click();
    pruefe('1.3 eine Notiz mit -elemente -> "1 Notiz"', eins.dom['.cbd-notes-info'].innerHTML.indexOf('1 Notiz gespeichert') !== -1, eins.dom['.cbd-notes-info'].innerHTML);
})();

// ---------------------------------------------------------------------------
// 2. Export
// ---------------------------------------------------------------------------
console.log('2. Export');
(function() {
    var u = mitNotizen();
    u.dom['.cbd-notes-export'].click();
    pruefe('2.1 eine Exportdatei erzeugt', u.blobs.length === 1, String(u.blobs.length));
    var daten = u.blobs.length ? JSON.parse(u.blobs[0].text) : { notes: {} };
    var mit = ['cbd-board-X', 'cbd-board-X-elemente', 'cbd-board-X:p1', 'cbd-board-X:p1-elemente',
        'cbd-board-X-bgcolor', 'cbd-board-X-classid', 'cbd-board-pagecount-X'];
    mit.forEach(function(k) {
        pruefe('2.2 Export enthält ' + k, daten.notes[k] === NOTIZEN[k]);
    });
    pruefe('2.3 Export ohne Werkzeug-Einstellungen', !('cbd-board-tool-settings' in daten.notes) && !('cbd-board-font-size' in daten.notes));
    pruefe('2.4 Export ohne fremde Schlüssel', !('anderer-schluessel' in daten.notes));
    var m = u.meldungen.join(' | ');
    pruefe('2.5 Meldung zählt 2 Notizen', m.indexOf('2 Notizen wurden exportiert') !== -1, m);

    var nurBegleit = umgebung();
    nurBegleit.ls.setItem('cbd-board-Q-elemente', '{}');
    nurBegleit.dom['.cbd-notes-export'].click();
    pruefe('2.6 nur Begleitschlüssel -> kein Export, Hinweis', nurBegleit.blobs.length === 0 &&
        nurBegleit.meldungen.join('').indexOf('Keine persönlichen Notizen') !== -1, nurBegleit.meldungen.join(' | '));
})();

// ---------------------------------------------------------------------------
// 3. Alle Notizen löschen
// ---------------------------------------------------------------------------
console.log('3. Alle Notizen löschen');
(function() {
    var u = mitNotizen();
    u.dom['.cbd-notes-delete-all'].click();
    var m = u.meldungen.join(' | ');
    pruefe('3.1 Rückfrage nennt 2 Notizen', m.indexOf('Es werden 2 persönliche Notizen') !== -1, m.slice(0, 160));
    ['cbd-board-X', 'cbd-board-X-elemente', 'cbd-board-X:p1', 'cbd-board-X:p1-elemente',
        'cbd-board-X-bgcolor', 'cbd-board-X-classid', 'cbd-board-pagecount-X'].forEach(function(k) {
        pruefe('3.2 gelöscht: ' + k, u.ls.getItem(k) === null);
    });
    pruefe('3.3 Werkzeug-Einstellungen bleiben', u.ls.getItem('cbd-board-tool-settings') === '{}' && u.ls.getItem('cbd-board-font-size') === '150');
    pruefe('3.4 fremder Schlüssel bleibt', u.ls.getItem('anderer-schluessel') === 'bleibt');
})();

// ---------------------------------------------------------------------------
// 4. Import
// ---------------------------------------------------------------------------
console.log('4. Import');
(function() {
    var u = umgebung();
    u.ls.setItem('cbd-board-Y', 'data:image/png;base64,ALT');
    u.ls.setItem('cbd-board-Y-elemente', '{"v":1,"breite":10,"hoehe":10,"elemente":[]}');
    u.ls.setItem('cbd-board-tool-settings', '{}');
    u.importieren({
        version: '1.0',
        notes: {
            'cbd-board-Y': 'data:image/png;base64,NEU',
            'cbd-board-Z': 'data:image/png;base64,ZZZZ',
            'cbd-board-Z-elemente': '{"v":1,"breite":10,"hoehe":10,"elemente":[]}',
            'cbd-board-Z-bgcolor': '#1a472a'
        }
    });
    var m = u.meldungen.join(' | ');
    pruefe('4.1 Y importiert', u.ls.getItem('cbd-board-Y') === 'data:image/png;base64,NEU');
    pruefe('4.2 veralteter Y-elemente entfernt (ältere Exportdatei ohne Elemente)', u.ls.getItem('cbd-board-Y-elemente') === null, u.ls.getItem('cbd-board-Y-elemente'));
    pruefe('4.3 Z samt Begleitschlüsseln importiert', u.ls.getItem('cbd-board-Z-elemente') !== null && u.ls.getItem('cbd-board-Z-bgcolor') === '#1a472a');
    pruefe('4.4 Rückfrage zählt vorhandene Notizen ohne Begleitschlüssel/Einstellungen (1)', m.indexOf('bereits 1 Notiz(en)') !== -1, m.slice(0, 200));
    pruefe('4.5 Rückfrage zählt importierte Notizen (2)', m.indexOf('werden 2 Notiz(en) importiert') !== -1, m.slice(0, 260));
    pruefe('4.6 Erfolgsmeldung zählt 2 Notizen', m.indexOf('2 Notizen wurden erfolgreich importiert') !== -1, m.slice(-200));

    // Import MIT -elemente lässt den importierten Wert stehen
    var v = umgebung();
    v.ls.setItem('cbd-board-Y-elemente', 'alt');
    v.importieren({ notes: { 'cbd-board-Y': 'data:image/png;base64,NEU', 'cbd-board-Y-elemente': 'neu' } });
    pruefe('4.7 Import mit -elemente übernimmt den importierten Wert', v.ls.getItem('cbd-board-Y-elemente') === 'neu', v.ls.getItem('cbd-board-Y-elemente'));

    // ältere Datei (flaches Format, ohne "notes") ohne Elemente: wie bisher
    var w = umgebung();
    w.importieren({ 'cbd-board-W': 'data:image/png;base64,WW', 'cbd-board-W-bgcolor': '#ffffff' });
    pruefe('4.8 altes flaches Format importiert wie bisher', w.ls.getItem('cbd-board-W') === 'data:image/png;base64,WW' &&
        w.ls.getItem('cbd-board-W-bgcolor') === '#ffffff' && w.ls.getItem('cbd-board-W-elemente') === null);
    pruefe('4.9 altes Format: Zählung 1 Notiz', w.meldungen.join(' | ').indexOf('1 Notiz wurde erfolgreich importiert') !== -1, w.meldungen.join(' | ').slice(-160));
})();

console.log('');
console.log(bestanden + ' bestanden, ' + fehlgeschlagen + ' fehlgeschlagen');
process.exit(fehlgeschlagen ? 1 : 0);
