/**
 * Pruefharnisch fuer die SVG-Aufbereitung des PDF-Exports (AP-2.1 aus
 * PLAN-Formeln-als-Vektor-im-PDF.md).
 *
 * Geprueft werden `bereiteSvgFuerMpdfAuf()` und `loeseVerschachtelteSvgAuf()`
 * aus `assets/js/pdf-server-side.js`. Beide fuehren Umformungen aus, die in
 * mPDF **still** scheitern: eine nicht aufgeloeste Farbe malt gar nichts,
 * ein `ex`-Mass sprengt die Seite, eine fehlende Grundlinien-Korrektur laesst
 * die Formel ueber der Zeile schweben, und ein verschachteltes `<svg>` faellt
 * ersatzlos weg. Kein einziger dieser Faelle meldet sich von selbst -
 * deshalb dieser Harnisch.
 *
 * ZWEI ENTSCHEIDUNGEN, DIE DIE AUSSAGEKRAFT TRAGEN:
 *
 * 1. Der Harnisch liest die AUSGELIEFERTE Datei und fuehrt die dort
 *    stehenden Funktionen aus - er prueft keine Kopie. Weicht der
 *    Produktivcode ab, faellt der Test.
 * 2. Die Eingaben sind ECHTE MathJax-Ausgaben aus `tools/fixtures/mathjax/`,
 *    nicht von Hand gebautes Markup. Das Projekt hat schon einmal Lehrgeld
 *    dafuer bezahlt, eine Eingabeform selbst zu erfinden statt sie zu
 *    erheben (siehe CLAUDE.md, "Wer Blockmarkup misst, erzeugt es ueber
 *    serialize_blocks()").
 *
 * Der DOM ist ein Stub - Node bringt keinen mit, und das Plugin hat keine
 * Abhaengigkeit dafuer. Er ist bewusst klein und deckt genau die Aufrufe ab,
 * die die beiden Funktionen benutzen. Weil die EINGABE echt ist, prueft der
 * Stub die Logik und nicht die eigene Vorstellung von ihr.
 *
 * Aufruf:  node tools/test-svg-aufbereitung.js
 *
 * @package ContainerBlockDesigner
 */

'use strict';

const fs = require('fs');
const path = require('path');

const QUELLE = path.join(__dirname, '..', 'assets', 'js', 'pdf-server-side.js');
const FIXTURES = path.join(__dirname, 'fixtures', 'mathjax');

let bestanden = 0;
let fehlgeschlagen = 0;

function pruefe(name, bedingung, zusatz) {
    if (bedingung) {
        bestanden++;
    } else {
        fehlgeschlagen++;
        console.log('  FEHL  ' + name + (zusatz ? '\n        ' + zusatz : ''));
        return;
    }
    console.log('  OK    ' + name);
}

// =========================================================================
// Minimaler SVG-DOM
// =========================================================================

class Attr {
    constructor(name, value) { this.name = name; this.value = value; }
}

class Element {
    constructor(tag) {
        this.tagName = tag;
        this.nodeName = tag;
        this._attr = new Map();
        this.childNodes = [];
        this.parentNode = null;
    }
    get attributes() {
        const liste = [];
        for (const [k, v] of this._attr) { liste.push(new Attr(k, v)); }
        liste.length = this._attr.size;
        return liste;
    }
    get firstChild() { return this.childNodes.length ? this.childNodes[0] : null; }
    getAttribute(n) { return this._attr.has(n) ? this._attr.get(n) : null; }
    setAttribute(n, v) { this._attr.set(n, String(v)); }
    removeAttribute(n) { this._attr.delete(n); }
    hasAttribute(n) { return this._attr.has(n); }
    appendChild(k) {
        if (k.parentNode) { k.parentNode.removeChild(k); }
        k.parentNode = this; this.childNodes.push(k); return k;
    }
    removeChild(k) {
        const i = this.childNodes.indexOf(k);
        if (i >= 0) { this.childNodes.splice(i, 1); k.parentNode = null; }
        return k;
    }
    replaceChild(neu, alt) {
        const i = this.childNodes.indexOf(alt);
        if (i >= 0) { this.childNodes[i] = neu; neu.parentNode = this; alt.parentNode = null; }
        return alt;
    }
    /** Unterstuetzt genau die Selektoren, die der Produktivcode benutzt. */
    querySelectorAll(sel) {
        const treffer = [];
        const attrSel = /^\[([\w-]+)="([^"]*)"\]$/.exec(sel.trim());
        // Auch die Form ohne Wert - sonst faellt [style] auf den
        // Tagnamen-Zweig zurueck und trifft STILL nichts (Falle beim
        // Bauen von AP-2.fix1 aufgetreten).
        const daSel = /^\[([\w-]+)\]$/.exec(sel.trim());
        const passt = (n) => {
            if (sel === '*') { return true; }
            if (attrSel) { return n.getAttribute(attrSel[1]) === attrSel[2]; }
            if (daSel) { return n.getAttribute(daSel[1]) !== null; }
            return sel.split(',').map(s => s.trim()).indexOf(n.tagName) !== -1;
        };
        const geh = (n) => {
            for (const k of n.childNodes) {
                if (k instanceof Element) { if (passt(k)) { treffer.push(k); } geh(k); }
            }
        };
        geh(this);
        return treffer;
    }
    querySelector(sel) { const t = this.querySelectorAll(sel); return t.length ? t[0] : null; }
    get outerHTML() {
        const attrs = [];
        for (const [k, v] of this._attr) { attrs.push(' ' + k + '="' + v + '"'); }
        const innen = this.childNodes.map(k =>
            k instanceof Element ? k.outerHTML : String(k.text || '')).join('');
        return '<' + this.tagName + attrs.join('') + '>' + innen + '</' + this.tagName + '>';
    }
}

class TextNode {
    constructor(text) { this.text = text; this.parentNode = null; }
}

/** Sehr einfacher Parser fuer wohlgeformtes SVG-Markup (Fixtures). */
function parse(markup) {
    const wurzelHalter = new Element('#root');
    let aktuell = wurzelHalter;
    const muster = /<\/?([a-zA-Z][\w:-]*)((?:\s+[\w:-]+="[^"]*")*)\s*(\/?)>|([^<]+)/g;
    let m;
    while ((m = muster.exec(markup)) !== null) {
        if (m[4] !== undefined) {
            const t = m[4];
            if (t.trim()) { const tn = new TextNode(t); tn.parentNode = aktuell; aktuell.childNodes.push(tn); }
            continue;
        }
        const schliessend = m[0][1] === '/';
        const tag = m[1];
        if (schliessend) {
            if (aktuell.parentNode) { aktuell = aktuell.parentNode; }
            continue;
        }
        const el = new Element(tag);
        const aMuster = /([\w:-]+)="([^"]*)"/g;
        let a;
        while ((a = aMuster.exec(m[2] || '')) !== null) { el.setAttribute(a[1], a[2]); }
        aktuell.appendChild(el);
        if (!m[3]) { aktuell = el; }
    }
    return wurzelHalter.querySelector('svg');
}

const dokument = {
    createElementNS(_ns, tag) { return new Element(tag); }
};
// Jedes Element braucht ownerDocument fuer createElementNS im Produktivcode
Object.defineProperty(Element.prototype, 'ownerDocument', {
    get() { return dokument; }
});

// =========================================================================
// Die beiden Funktionen aus der ausgelieferten Datei holen
// =========================================================================

function holeFunktion(quelltext, name) {
    const start = quelltext.indexOf('function ' + name + '(');
    if (start === -1) { throw new Error('Funktion nicht gefunden: ' + name); }
    let i = quelltext.indexOf('{', start);
    let tiefe = 0;
    for (; i < quelltext.length; i++) {
        if (quelltext[i] === '{') { tiefe++; }
        else if (quelltext[i] === '}') { tiefe--; if (tiefe === 0) { break; } }
    }
    return quelltext.slice(start, i + 1);
}

const quelltext = fs.readFileSync(QUELLE, 'utf8');
const sandkasten = { console: { warn() {}, log() {} }, Math };
const bauen = new Function('console', 'Math',
    holeFunktion(quelltext, 'loeseVerschachtelteSvgAuf') + '\n' +
    holeFunktion(quelltext, 'bereiteSvgFuerMpdfAuf') + '\n' +
    'return { bereiteSvgFuerMpdfAuf: bereiteSvgFuerMpdfAuf,' +
    ' loeseVerschachtelteSvgAuf: loeseVerschachtelteSvgAuf };');
const F = bauen(sandkasten.console, Math);

// =========================================================================
// Pruefungen
// =========================================================================

const PRO_EX = 7.3;          // px je ex, im Browser gemessen
const FARBE = '#71230a';     // bewusst NICHT die Vorgabefarbe

function fixture(name) {
    return parse(fs.readFileSync(path.join(FIXTURES, name + '.svg'), 'utf8'));
}

console.log('\n--- Gruppe 1: Fixtures sind echt und vollstaendig ---');
const NAMEN = ['inline-bruch', 'inline-text', 'display-nernst', 'pfeil', 'wurzel', 'unterlaenge'];
NAMEN.forEach(n => {
    const roh = fs.readFileSync(path.join(FIXTURES, n + '.svg'), 'utf8');
    pruefe('Fixture ' + n + ' traegt currentColor und ex-Masse',
        /currentColor/.test(roh) && /(width|height)="[\d.]+ex"/.test(roh),
        'Fixture sieht nicht nach MathJax-Rohausgabe aus');
});

console.log('\n--- Gruppe 2: (1) currentColor wird je Formel aufgeloest ---');
{
    const svg = fixture('inline-bruch');
    F.bereiteSvgFuerMpdfAuf(svg, PRO_EX, FARBE);
    const html = svg.outerHTML;
    pruefe('kein currentColor mehr uebrig', html.indexOf('currentColor') === -1);
    pruefe('die uebergebene Farbe steht drin', html.indexOf(FARBE) !== -1);
    pruefe('KEINE feste Vorgabefarbe eingeschmuggelt (N4b)',
        html.indexOf('#333333') === -1,
        'Eine pauschale Farbe war in diesem Projekt schon zweimal ein Fehler.');
}
{
    // AP-2.fix1 (AP-2.rev, Befund B4): currentColor kommt auch in
    // style-Attributen vor - AP-2.1 verlangt das ausdruecklich, der Code
    // konnte es bis dahin aber nicht.
    const svg = fixture('inline-bruch');
    svg.setAttribute('style', 'color: currentColor; vertical-align: -0.8ex;');
    const erstesKind = svg.querySelector('g');
    erstesKind.setAttribute('style', 'fill: currentColor');
    F.bereiteSvgFuerMpdfAuf(svg, PRO_EX, FARBE);
    pruefe('currentColor auch in style-Attributen aufgeloest',
        svg.outerHTML.indexOf('currentColor') === -1,
        svg.outerHTML.slice(0, 160));
    pruefe('und dort steht die uebergebene Farbe',
        (erstesKind.getAttribute('style') || '').indexOf(FARBE) !== -1);
}
{
    // dieselbe Formel, andere Farbe -> muss sich unterscheiden
    const a = fixture('inline-bruch'); F.bereiteSvgFuerMpdfAuf(a, PRO_EX, '#111111');
    const b = fixture('inline-bruch'); F.bereiteSvgFuerMpdfAuf(b, PRO_EX, '#eeeeee');
    pruefe('zwei Formeln mit verschiedener Blockfarbe ergeben verschiedenes SVG',
        a.outerHTML !== b.outerHTML);
}

console.log('\n--- Gruppe 3: (2) ex -> px ---');
{
    const svg = fixture('display-nernst');
    F.bereiteSvgFuerMpdfAuf(svg, PRO_EX, FARBE);
    pruefe('width in px', /^[\d.]+px$/.test(svg.getAttribute('width')));
    pruefe('height in px', /^[\d.]+px$/.test(svg.getAttribute('height')));
    pruefe('kein ex-Mass mehr im ganzen Markup',
        !/(width|height)="[\d.]+ex"/.test(svg.outerHTML));
    // 25.52ex * 7.3 = 186.3
    pruefe('Breite korrekt umgerechnet (25,52ex x 7,3 = 186,30px)',
        Math.abs(parseFloat(svg.getAttribute('width')) - 186.30) < 0.05,
        'ist: ' + svg.getAttribute('width'));
}
{
    const svg = fixture('inline-bruch');
    F.bereiteSvgFuerMpdfAuf(svg, 12, FARBE);
    const b = parseFloat(svg.getAttribute('width'));
    const svg2 = fixture('inline-bruch');
    F.bereiteSvgFuerMpdfAuf(svg2, 6, FARBE);
    pruefe('anderer ex-Faktor ergibt proportional andere Breite',
        Math.abs(b / parseFloat(svg2.getAttribute('width')) - 2) < 0.01);
}

console.log('\n--- Gruppe 4: (3) vertical-align wird nur ENTFERNT ---');
{
    // Zwei Fehlversuche stecken in dieser Gruppe, beide am PDF nachgewiesen:
    //
    //  - AP-2.1 hat den Kasten unten um die Grundlinientiefe gekuerzt, weil
    //    eine Messung sagte, mPDF beschneide nicht. Die Messung zaehlte
    //    Zeichenobjekte im Inhaltsstrom - die stehen auch dann darin, wenn
    //    sie beschnitten sind. mPDF beschneidet sehr wohl an der viewBox;
    //    im Vollexport fiel der Nenner des Bruchs `L = 1/R` weg.
    //  - Ein Laengenwert in `vertical-align` half auch nicht: mPDF wertet an
    //    einem <img> nur die Schluesselwoerter aus (gemessen ueber vier
    //    Zeilenhoehen und vier Werte).
    //
    // Ergebnis: Am SVG wird nichts mehr veraendert ausser dem Entfernen der
    // wirkungslosen Angabe. Die Ausrichtung macht die Serverseite mit
    // `vertical-align: middle` am <img>.
    const roh = fs.readFileSync(path.join(FIXTURES, 'unterlaenge.svg'), 'utf8');
    const hoeheRoh = parseFloat(/height="([\d.]+)ex"/.exec(roh)[1]) * PRO_EX;
    const breiteRoh = parseFloat(/width="([\d.]+)ex"/.exec(roh)[1]) * PRO_EX;
    const vbRoh = /viewBox="([^"]+)"/.exec(roh)[1].replace(/\s+/g, ' ').trim();
    pruefe('Fixture traegt ueberhaupt ein vertical-align',
        /vertical-align/.test(roh));

    const svg = fixture('unterlaenge');
    F.bereiteSvgFuerMpdfAuf(svg, PRO_EX, FARBE);

    pruefe('vertical-align ist aus dem style entfernt',
        (svg.getAttribute('style') || '').indexOf('vertical-align') === -1);
    pruefe('und steht auch sonst nirgends mehr im Markup',
        svg.outerHTML.indexOf('vertical-align') === -1);
    pruefe('die Hoehe bleibt UNVERAENDERT (kein Kuerzen)',
        Math.abs(parseFloat(svg.getAttribute('height')) - hoeheRoh) < 0.05,
        'ist ' + svg.getAttribute('height') + ', soll ' + hoeheRoh.toFixed(2) + 'px');
    pruefe('die Breite bleibt unveraendert',
        Math.abs(parseFloat(svg.getAttribute('width')) - breiteRoh) < 0.05);
    pruefe('die viewBox bleibt UNVERAENDERT',
        svg.getAttribute('viewBox').replace(/\s+/g, ' ').trim() === vbRoh,
        'ist ' + svg.getAttribute('viewBox') + ', soll ' + vbRoh);
}

console.log('\n--- Gruppe 4b: kein Unterschied zwischen inline und abgesetzt ---');
{
    // Frueher entschied hier ein Parameter istBlock ueber eine
    // Grundlinien-Korrektur. Den gibt es nicht mehr: Ob eine Formel inline
    // oder abgesetzt gesetzt wird, entscheidet allein die Serverseite
    // anhand von `isDisplay` in der Nutzlast. Diese Pruefung haelt fest,
    // dass die Aufbereitung davon nichts wissen muss.
    pruefe('bereiteSvgFuerMpdfAuf nimmt genau drei Parameter',
        F.bereiteSvgFuerMpdfAuf.length === 3,
        'ist ' + F.bereiteSvgFuerMpdfAuf.length);

    const a = fixture('display-nernst');
    const b = fixture('display-nernst');
    F.bereiteSvgFuerMpdfAuf(a, PRO_EX, FARBE);
    F.bereiteSvgFuerMpdfAuf(b, PRO_EX, FARBE, true);   // vierter Wert ignoriert
    pruefe('ein zusaetzlicher Parameter aendert nichts',
        a.outerHTML === b.outerHTML);

    const roh = fs.readFileSync(path.join(FIXTURES, 'display-nernst.svg'), 'utf8');
    pruefe('auch die abgesetzte Formel behaelt ihre volle Hoehe',
        Math.abs(parseFloat(a.getAttribute('height'))
            - parseFloat(/height="([\d.]+)ex"/.exec(roh)[1]) * PRO_EX) < 0.05);
    pruefe('die uebrigen Umformungen greifen trotzdem',
        a.outerHTML.indexOf('currentColor') === -1
        && !/(width|height)="[\d.]+ex"/.test(a.outerHTML)
        && a.outerHTML.indexOf('vertical-align') === -1);
}

console.log('\n--- Gruppe 5: (4) verschachtelte <svg> aufloesen ---');
{
    const roh = fs.readFileSync(path.join(FIXTURES, 'pfeil.svg'), 'utf8');
    pruefe('Fixture "pfeil" enthaelt wirklich ein inneres <svg>',
        (roh.match(/<svg/g) || []).length === 2,
        'Ohne inneres <svg> pruefte diese Gruppe nichts.');
    const svg = fixture('pfeil');
    const pfadeVorher = svg.querySelectorAll('path').length;
    F.bereiteSvgFuerMpdfAuf(svg, PRO_EX, FARBE);
    pruefe('kein inneres <svg> mehr', svg.querySelectorAll('svg').length === 0);
    pruefe('kein Pfad verloren gegangen',
        svg.querySelectorAll('path').length === pfadeVorher,
        'vorher ' + pfadeVorher + ', nachher ' + svg.querySelectorAll('path').length);
    const gruppen = svg.querySelectorAll('g').filter(
        g => /translate\([^)]*\)\s*scale\(/.test(g.getAttribute('transform') || ''));
    pruefe('die Ersatzgruppe traegt translate+scale', gruppen.length >= 1);
}
{
    // Ohne viewBox: nur verschieben, nicht skalieren
    const svg = parse('<svg width="10px" height="10px" viewBox="0 0 10 10">' +
        '<svg x="3" y="4" width="5" height="5"><path d="M0 0"/></svg></svg>');
    F.loeseVerschachtelteSvgAuf(svg);
    const g = svg.querySelectorAll('g')[0];
    pruefe('inneres <svg> ohne viewBox wird zu reinem translate',
        g && g.getAttribute('transform') === 'translate(3,4)',
        'ist: ' + (g && g.getAttribute('transform')));
}
{
    // Mehrfache Verschachtelung
    const svg = parse('<svg viewBox="0 0 10 10" width="10px" height="10px">' +
        '<svg x="1" y="1" width="8" height="8" viewBox="0 0 8 8">' +
        '<svg x="2" y="2" width="4" height="4" viewBox="0 0 4 4">' +
        '<rect x="0" y="0" width="4" height="4"/></svg></svg></svg>');
    F.loeseVerschachtelteSvgAuf(svg);
    pruefe('auch mehrfach verschachtelte <svg> werden alle aufgeloest',
        svg.querySelectorAll('svg').length === 0);
    pruefe('der innerste Inhalt bleibt erhalten',
        svg.querySelectorAll('rect').length === 1);
}
{
    // preserveAspectRatio: ungleiche Skalierung ohne "none" -> gleichmaessig
    const svg = parse('<svg viewBox="0 0 100 100" width="100px" height="100px">' +
        '<svg x="0" y="0" width="40" height="10" viewBox="0 0 20 10">' +
        '<rect x="0" y="0" width="20" height="10"/></svg></svg>');
    F.loeseVerschachtelteSvgAuf(svg);
    const t = svg.querySelectorAll('g')[0].getAttribute('transform');
    const sk = /scale\(([-\d.]+),([-\d.]+)\)/.exec(t);
    pruefe('ohne preserveAspectRatio wird gleichmaessig skaliert (Vorgabe meet)',
        sk && Math.abs(parseFloat(sk[1]) - parseFloat(sk[2])) < 1e-9,
        'ist: ' + t);
}
{
    // preserveAspectRatio="none" -> darf ungleich skalieren
    const svg = parse('<svg viewBox="0 0 100 100" width="100px" height="100px">' +
        '<svg x="0" y="0" width="40" height="10" viewBox="0 0 20 10" ' +
        'preserveAspectRatio="none"><rect x="0" y="0" width="20" height="10"/></svg></svg>');
    F.loeseVerschachtelteSvgAuf(svg);
    const t = svg.querySelectorAll('g')[0].getAttribute('transform');
    const sk = /scale\(([-\d.]+),([-\d.]+)\)/.exec(t);
    pruefe('mit preserveAspectRatio="none" bleibt die Skalierung ungleich',
        sk && Math.abs(parseFloat(sk[1]) - 2) < 1e-9 && Math.abs(parseFloat(sk[2]) - 1) < 1e-9,
        'ist: ' + t);
}

console.log('\n--- Gruppe 6: Ballast abwerfen ---');
{
    const svg = parse('<svg viewBox="0 0 10 10" width="10px" height="10px">' +
        '<g data-mml-node="math" data-semantic-type="relseq" data-semantic-children="1,2" ' +
        'data-latex="x" data-c="58" data-mjx-texclass="ORD" fill="currentColor">' +
        '<path d="M0 0" data-c="41"/></g></svg>');
    F.bereiteSvgFuerMpdfAuf(svg, PRO_EX, FARBE);
    const html = svg.outerHTML;
    ['data-semantic', 'data-mml-node', 'data-latex', 'data-c', 'data-mjx-texclass']
        .forEach(a => pruefe('Attribut ' + a + ' entfernt', html.indexOf(a) === -1));
    pruefe('die Zeichnung selbst bleibt', html.indexOf('<path') !== -1);
    pruefe('das d-Attribut bleibt', html.indexOf('d="M0 0"') !== -1);
}

console.log('\n--- Gruppe 7: Wirkung auf allen echten Fixtures ---');
NAMEN.forEach(n => {
    const svg = fixture(n);
    F.bereiteSvgFuerMpdfAuf(svg, PRO_EX, FARBE);
    const html = svg.outerHTML;
    const sauber = html.indexOf('currentColor') === -1
        && !/(width|height)="[\d.]+ex"/.test(html)
        && html.indexOf('vertical-align') === -1
        && svg.querySelectorAll('svg').length === 0
        && svg.querySelectorAll('path, rect').length > 0;
    pruefe(n + ': alle vier Umformungen greifen, Zeichnung erhalten', sauber);
});

console.log('\n--- Gruppe 8: Waechter gegen Wiederholung ---');
{
    // Zweimal aufbereiten muss dieselben Masse liefern - und beim zweiten
    // Mal KEINE Tiefe mehr melden: vertical-align ist beim ersten Mal
    // entfernt worden. Wer die Tiefe braucht, merkt sie sich beim ersten
    // Aufruf (so macht es setzeFormelAlsSvg()).
    const svg = fixture('unterlaenge');
    F.bereiteSvgFuerMpdfAuf(svg, PRO_EX, FARBE);
    const html1 = svg.outerHTML;
    F.bereiteSvgFuerMpdfAuf(svg, PRO_EX, FARBE);
    pruefe('zweite Aufbereitung aendert nichts mehr',
        svg.outerHTML === html1,
        'Die Aufbereitung muss idempotent sein - sie laeuft je Formel nur'
        + ' einmal, aber ein Harnisch darf sich darauf nicht verlassen.');
}

console.log('\n=========================================================');
console.log('  ' + bestanden + ' bestanden, ' + fehlgeschlagen + ' fehlgeschlagen');
console.log('=========================================================\n');
process.exit(fehlgeschlagen > 0 ? 1 : 0);
