/* Listener-Zähler für tools/test-board-zeiger.html - VOR board-mode.js laden */
// Listener-Zähler (AP-2.2): addEventListener/removeEventListener an
// document/html/body/window mitzählen, BEVOR board-mode.js geladen wird.
(function() {
    var zaehler = { add: 0, remove: 0, offen: {} };
    window.__listenerZaehler = zaehler;
    var origAdd = EventTarget.prototype.addEventListener;
    var origRemove = EventTarget.prototype.removeEventListener;
    function schluessel(ziel, typ) {
        var name = ziel === window ? 'window' : (ziel === window.visualViewport ? 'visualViewport' : (ziel === document ? 'document' :
            (ziel === document.documentElement ? 'html' : (ziel === document.body ? 'body' : null))));
        return name ? name + ':' + typ : null;
    }
    EventTarget.prototype.addEventListener = function(typ, fn, opt) {
        var k = schluessel(this, typ);
        if (k && window.__zaehlerAktiv) {
            zaehler.add++;
            zaehler.offen[k] = (zaehler.offen[k] || 0) + 1;
        }
        return origAdd.call(this, typ, fn, opt);
    };
    EventTarget.prototype.removeEventListener = function(typ, fn, opt) {
        var k = schluessel(this, typ);
        if (k && window.__zaehlerAktiv) {
            zaehler.remove++;
            zaehler.offen[k] = (zaehler.offen[k] || 0) - 1;
        }
        return origRemove.call(this, typ, fn, opt);
    };
})();
