/**
 * Container Block Designer - Klassenpuls: der Taktgeber des Klassenmodus
 *
 * KEIN BUILD-SCHRITT: Diese Datei wird unveraendert an den Browser
 * ausgeliefert. Hausstil ist ES5 mit var/function und IIFE - kein JSX, keine
 * Arrow Functions, keine Template-Literale, kein let/const. Keine externe
 * Bibliothek, keine CDN-Einbindung, kein jQuery (fetch() genuegt), kein
 * import/export.
 *
 * ---------------------------------------------------------------------------
 * WAS DIESE DATEI TUT (AP-1.4 aus PLAN-Klassenmodus-Live.md)
 * ---------------------------------------------------------------------------
 *
 * Sie fragt die Route `cbd/v1/klassenpuls` in regelmaessigem Takt ab und
 * benachrichtigt Abonnenten, wenn sich eine Signatur geaendert hat. Die Route
 * liefert ausschliesslich Zahlen - vier kurze Pruefsummen und den Takt -,
 * niemals Inhalte. Wer auf eine Aenderung reagieren will, holt die Inhalte
 * danach ueber die bestehenden Endpunkte mit ihren geprueften
 * Autorisierungsketten.
 *
 * SEIT AP-1.fix1 ist das tatsaechliche Abfrageintervall NICHT mehr exakt der
 * Takt: Im Normalbetrieb streut es bei jeder Planung neu um +-25 % um den
 * Takt (Regel 9 unten), damit gemeinsam gestartete Tabs einer ganzen Klasse
 * nicht dauerhaft im Sekundenbruchteil-Gleichschritt abfragen.
 *
 * ES GIBT GENAU EINEN TAKTGEBER JE BROWSER-TAB. Das ist der ganze Zweck der
 * Datei: Ab Phase 2 haengen sich `classroom-page-filter.js`,
 * `classroom-frontend.js` und `fragenwand-frontend.js` daran - jeweils ohne
 * voneinander zu wissen. Wuerde jedes Teilsystem einen eigenen `setInterval`
 * unterhalten, haenge die Serverlast an der Zahl der Live-Funktionen statt an
 * der Zahl der Schueler. So haengt sie nur an der Zahl der Schueler.
 *
 * ---------------------------------------------------------------------------
 * OEFFENTLICHE SCHNITTSTELLE `window.cbdKlassenpuls` (VERTRAG)
 * ---------------------------------------------------------------------------
 *
 * Vier spaetere Arbeitspakete (AP-2.1, AP-3.1, AP-4.1, AP-4.2) verlassen sich
 * Zeichen fuer Zeichen auf diese sieben Namen. Sie sind ein Vertrag und
 * werden nicht umbenannt:
 *
 *   window.cbdKlassenpuls = {
 *       setzeSitzung: function (classroomId, token) {},
 *       setzeSeite:   function (pageId) {},
 *       abonniere:    function (name, rueckruf) {},   // gibt Abmeldefunktion zurueck
 *       starte:       function () {},
 *       halte:        function () {},
 *       sofort:       function () {},                 // eine Abfrage jetzt
 *       laeuft:       function () {}                  // bool
 *   };
 *
 * `name` in `abonniere()` ist einer von genau fuenf Werten:
 *
 *   | Name           | Bewegt sich, wenn ...                                  |
 *   |----------------|--------------------------------------------------------|
 *   | `seite`        | ein Container dieser Seite freigegeben oder
 *   |                | zurueckgenommen wird                                    |
 *   | `tafel`        | ein Tafelbild dieser Seite geschrieben wird             |
 *   | `klasse`       | sich die Seitenliste der Klasse aendert                 |
 *   | `fragenwand`   | sich die Fragenwand der Klasse aendert                  |
 *   | `abgelaufen`   | Sonderfall: die Sitzung ist ungueltig (siehe unten)      |
 *
 * Die ersten vier sind die Feldnamen der Serverantwort. `seite` und `tafel`
 * gibt es nur, wenn `setzeSeite()` eine Seite gesetzt hat. Der Rueckruf wird
 * mit `(neueSignatur, alteSignatur)` aufgerufen; bei `abgelaufen` mit
 * `(null, null)`.
 *
 * `abonniere()` gibt eine Funktion zurueck, die den Rueckruf wieder
 * abmeldet. Ein unbekannter Name oder ein Nicht-Funktions-Rueckruf ergibt
 * eine wirkungslose Abmeldefunktion, keinen Fehler.
 *
 * ---------------------------------------------------------------------------
 * DIE REGELN, DIE MAN BEIM LESEN DES CODES LEICHT UEBERSIEHT
 * ---------------------------------------------------------------------------
 *
 * 1. DIE ERSTE SERVERANTWORT LOEST KEINEN RUECKRUF AUS. Sie legt nur die
 *    Ausgangssignaturen fest. Ohne diese Regel wuerde direkt nach dem
 *    Seitenaufbau alles unnoetig neu geladen - der Verbraucher hat seinen
 *    Erstzustand ja gerade selbst aufgebaut. Umgesetzt ueber
 *    `erstanfrageErledigt`; zusaetzlich feuert ein Rueckruf nie fuer eine
 *    Signatur, deren vorheriger Wert `undefined` war (also erstmals
 *    auftaucht).
 *
 * 2. `setzeSitzung()` SETZT EINE BEREITS GESETZTE `page_id` NICHT ZURUECK.
 *    Auf einer Klassenseite hat `classroom-page-filter.js` dem Taktgeber
 *    bereits Sitzung UND Seite mitgeteilt; ruft danach die Fragenwand
 *    (AP-4.2) `setzeSitzung()` mit denselben Werten erneut auf, muss die
 *    Seitenbindung erhalten bleiben - sonst verlaeren die Abonnenten von
 *    `seite` und `tafel` still ihre Datenquelle. Die Seite aendert nur
 *    `setzeSeite()`.
 *
 * 3. RUECKZUG BEI NETZFEHLERN. Jeder Fehlschlag erhoeht `fehlerZaehler`. Ab
 *    dem dritten Fehlschlag in Folge wird das Intervall verdoppelt, mit
 *    jedem weiteren Fehlschlag erneut - gedeckelt bei 120 s. Die erste
 *    erfolgreiche Antwort setzt den Zaehler auf 0 und damit den Takt zurueck
 *    auf den Servertakt. Ein Server, der gerade nicht kann, wird so nicht
 *    von 25 Schuelern im Sekundentakt weiter angeklopft.
 *
 * 4. ABGELAUFENE SITZUNG IST ENDGUELTIG. Antwortet der Server mit HTTP 404,
 *    gilt die Sitzung als ungueltig: Der Taktgeber stellt sich fuer die
 *    Lebensdauer dieses Seitenaufrufs ein und ruft die Abonnenten von
 *    `abgelaufen` genau einmal auf. Es gibt bewusst keinen Weg zurueck -
 *    auch `setzeSitzung()` mit einem anderen Token startet ihn nicht neu.
 *    Ein Schueler mit abgelaufenem Token darf nicht im Sekundentakt
 *    anklopfen. Wer eine neue Sitzung braucht, laedt die Seite neu.
 *
 * 5. DER TAKT KOMMT VOM SERVER, nicht aus einer Konstante hier. Das Feld
 *    `takt` jeder Antwort gilt ab dem naechsten Durchlauf; `takt: 0` haelt
 *    den Taktgeber an (die Notbremse des Website-Betriebs, Option
 *    `cbd_klassenpuls_takt`). Nur solange noch keine Antwort vorliegt, gilt
 *    ersatzweise `cbdKlassenpulsDaten.takt` aus `wp_localize_script()` und
 *    zuletzt `TAKT_RUECKFALL`.
 *
 * 6. BEI VERSTECKTEM TAB WIRD NICHT ABGEFRAGT. `document.hidden` haelt den
 *    Zeitgeber an, ohne den Taktgeber zu stoppen (`laeuft()` bleibt `true`).
 *    Wechselt der Tab auf sichtbar, wird sofort einmal abgefragt und danach
 *    normal weitergetaktet.
 *
 * 7. ES LAEUFT NIE MEHR ALS EINE ABFRAGE. `laeuftGerade` verhindert, dass
 *    ein langsamer Server sich Anfragen aufstauen laesst.
 *
 * 8. BEIM LADEN PASSIERT NICHTS. Ohne `cbdKlassenpulsDaten` und ohne
 *    `setzeSitzung()` gibt es keine einzige Netzwerkanfrage. Die Datei darf
 *    auf einer Seite ohne Klassenmodus keinen Fehler und keine Last
 *    erzeugen. Der Start erfolgt erst, wenn ein Verbraucher
 *    `setzeSitzung()` ruft. In Phase 1 ruft niemand auf - das ist gewollt.
 *
 * 9. DAS INTERVALL IST NICHT EXAKT DER TAKT (seit AP-1.fix1). Im
 *    Normalbetrieb streut `aktuellesIntervallMs()` bei JEDER Planung neu um
 *    +-25 % (gleichverteilt, `basis * (0.75 + Math.random() * 0.5)`),
 *    danach auf mindestens 5000 ms geklemmt. Grund: Ohne Streuung blieben
 *    gemeinsam gestartete Abfrageschleifen (gemeinsamer Stundenbeginn einer
 *    Klasse) dauerhaft im selben Sekundenbruchteil-Gleichschritt - gemessen
 *    in AP-1.7, `docs/messung-klassenpuls.md` Abschnitt 5: 58 Runden, zehn
 *    Minuten, Zeitband blieb bei 0,5-0,65 s statt sich aufzuloesen. Die
 *    Streuung wirkt NUR im Normalbetrieb, NICHT zusaetzlich auf die bereits
 *    verdoppelnden Rueckzugsstufen aus Regel 3 - dort wuerde sie nur addiert,
 *    ohne den Gleichschritt-Fall zu betreffen.
 *
 * 10. ES GIBT ZWEI ZEITGEBER, NICHT EINEN (seit AP-2.2). Der schnelle
 *    fragt die Pulsdatei (Vorgabe 2 s), der bestehende wird zum
 *    HERZSCHLAG und tickt seltener (Vorgabe 60 s). Beide gehorchen
 *    denselben Regeln 4, 6 und 7: Nach einer abgelaufenen Sitzung
 *    stehen beide, bei verstecktem Tab pausieren beide, und je
 *    Zeitgeber laeuft nie mehr als eine Abfrage (`laeuftGerade` fuer
 *    die Route, `dateiLaeuftGerade` fuer die Datei).
 *
 * 11. DER DATEITAKT STREUT NUR NACH OBEN, der REST-Takt um den Wert
 *    herum. Das ist kein Versehen: Die Formel des REST-Takts
 *    (`0,75 + Zufall * 0,5` plus Klemmung) laesst beim kleinen
 *    Dateitakt rund die Haelfte aller Planungen exakt auf der
 *    Untergrenze landen - genau die Entzerrung faellt damit weg, die
 *    die Streuung bewirken soll (Bestandsbefund B10 aus AP-1.rev des
 *    Vorgaengervorhabens). `dateiIntervallMs()` benutzt deshalb
 *    `1 + Zufall * 0,5`, und die Klemmung bleibt reine Absicherung.
 *
 * 12. DREI FEHLSCHLAEGE SCHALTEN DEN DATEIMODUS AB, DAUERHAFT FUER
 *    DIESEN SEITENAUFRUF. Danach traegt der Herzschlag den Betrieb
 *    allein und kehrt auf `takt` zurueck - der Klassenmodus verhaelt
 *    sich dann wie vor diesem Vorhaben, nur wieder langsamer. **Das
 *    ist der Grund, warum dieses Vorhaben den Unterrichtsbetrieb
 *    nicht gefaehrden kann.** Anders als beim Rueckzug aus Regel 3
 *    wird hier NICHT verlangsamt, sondern aufgegeben: Eine Datei ist
 *    entweder da oder nicht. Der Rueckfall meldet sich einmal mit
 *    `console.warn` - bewusst nicht hinter `window.cbdDebug`, sonst
 *    faellt der Ausfall des schnellen Wegs niemandem auf.
 *
 * 13. DIE DATEIADRESSE KOMMT AUSSCHLIESSLICH AUS DER ROUTE. Sie wird
 *    hier nie zusammengesetzt - der Server kennt Uploadverzeichnis
 *    und HMAC-Anteil, der Browser nicht. Sie gehoert zur Sitzung: Ein
 *    Sitzungswechsel verwirft sie samt Fehlerzaehler und Sperre, und
 *    erst die naechste Antwort der Route nennt die neue. Derselbe
 *    Gedanke wie bei der REST-Basis (siehe ADRESSBILDUNG unten).
 *
 * ---------------------------------------------------------------------------
 * DIE ZWEITE DATENQUELLE: DIE PULSDATEI (AP-2.1)
 * ---------------------------------------------------------------------------
 *
 * Seit dem Vorhaben „Schneller Klassenpuls" gibt es dieselben Signaturen an
 * zwei Stellen. Der Grund ist Kosten, nicht Bequemlichkeit: Eine Anfrage an
 * die Route kostet einen vollstaendigen WordPress-Bootstrap - auf der
 * Produktivmaschine rund 47 ms Rechenzeit gemessen, und zwar AUCH dann, wenn
 * sie abgelehnt wird. Eine statische Datei kostet den Webserver fast nichts
 * und belegt keinen PHP-Arbeitsprozess.
 *
 *   Stufe 1  Pulsdatei     schnell (~2 s)    kein PHP
 *   Stufe 0  Route         selten (~60 s)    prueft die Sitzung, nennt Takt
 *                                            und Dateiadresse, repariert
 *   Stufe 2  Inhalte       bei Bedarf        unveraendert
 *
 * FORM DER DATEI (nur Zahlen und Pruefsummen, niemals Inhalte):
 *
 *   {
 *       "klasse":     "a1b2c3d4e5f6",
 *       "fragenwand": "0f1e2d3c4b5a",
 *       "seiten":     { "1618": ["9988776655ff", "1122334455aa"] },
 *       "takt":       10,
 *       "stand":      1789456123
 *   }
 *
 * Sie gilt fuer eine ganze KLASSE, nicht fuer eine Seite - deshalb die
 * Abbildung `seiten`. `verarbeiteDatei()` uebersetzt sie in die flache Form,
 * die `uebernehmeSignaturen()` erwartet.
 *
 * DER TAKT KOMMT WEITERHIN NUR AUS DER ROUTE. Regel 5 unten gilt unveraendert;
 * das Feld `takt` der Datei wird gelesen, aber nicht verwendet. Eine
 * Autoritaet, nicht zwei.
 *
 * ZWEI FAELLE, IN DENEN `seite`/`tafel` NICHT AUS DER DATEI KOMMEN:
 * `seiten_unvollstaendig === true` (die Klasse ueberschreitet die
 * serverseitige Obergrenze; dann fehlt `seiten` ganz - weglassen statt
 * kuerzen) und eine `seiteId` ohne Eintrag in `seiten`. In beiden Faellen
 * zieht der naechste Herzschlag ueber die Route sie nach. Ein Ersatzwert
 * waere falsch - die Begruendung steht ausfuehrlich am Docblock von
 * `verarbeiteDatei()`.
 *
 * REGEL 1 GILT UNVERAENDERT UND DECKT ZWEI QUELLEN BEREITS AB. Sie lautet:
 * „Ein Rueckruf feuert nie fuer eine Signatur, deren vorheriger Wert
 * `undefined` war." Welche der beiden Quellen einen Namen zuerst sieht, ist
 * damit gleichgueltig - sie legt den Ausgangswert fest und meldet nichts.
 * Es braucht KEINE zweite Erstanfrage-Markierung je Quelle; `uebernehmeSignaturen()`
 * setzt die eine vorhandene. Eine zweite waere eine zweite Auslegung
 * derselben Regel.
 *
 * DER VERTRAG NACH AUSSEN AENDERT SICH NICHT. Dieselben sieben Namen,
 * dieselben fuenf Abonnentennamen, dieselbe Rueckrufsignatur. Die vier
 * Abonnenten wissen nicht, aus welcher Quelle eine Signatur stammt - und
 * sollen es nicht wissen.
 *
 * ---------------------------------------------------------------------------
 * ADRESSBILDUNG
 * ---------------------------------------------------------------------------
 *
 * Die Basis kommt AUSSCHLIESSLICH aus `cbdKlassenpulsDaten.restUrl`
 * (serverseitig ueber `rest_url()` gebildet, AP-1.5) und wird hier nie selbst
 * zusammengesetzt: Auf Installationen ohne huebsche Permalinks lautet die
 * Adresse `?rest_route=/cbd/v1/klassenpuls`, ein hier gebautes `/wp-json/...`
 * liefe dort in einen Apache-404. Aus demselben Grund wird der Trenner
 * geprueft (`?` oder `&`) statt blind `?` anzuhaengen - dieselbe Regel wie in
 * `fragenwand-frontend.js` und `blocks/block-reference/view.js`.
 *
 * Angehaengt werden `classroom`, `token`, bei gesetzter Seite `page_id` und
 * immer ein Cache-Brecher `_`, damit kein Zwischenspeicher eine alte Antwort
 * ausliefert.
 *
 * @package ContainerBlockDesigner
 * @since Vorhaben „Klassenmodus-Live", Phase 1 (AP-1.4)
 */
(function (window, document) {
	'use strict';

	// =====================================================================
	// KONSTANTEN
	// =====================================================================

	/**
	 * Die vier Signaturnamen der Serverantwort, in der Reihenfolge, in der
	 * die Rueckrufe bei einem Durchlauf ausgeloest werden.
	 *
	 * @type {string[]}
	 */
	var SIGNATURNAMEN = ['seite', 'tafel', 'klasse', 'fragenwand'];

	/**
	 * Der fuenfte, kuenstliche Name: kein Feld der Antwort, sondern der
	 * Sonderfall „Sitzung ungueltig" (HTTP 404).
	 *
	 * @type {string}
	 */
	var NAME_ABGELAUFEN = 'abgelaufen';

	/**
	 * HTTP-Status, mit dem der Server jede Ablehnung beantwortet. Er ist
	 * zeichengleich fuer jeden Fehlschlag (Klasse existiert nicht, Sitzung
	 * abgelaufen, Token gefaelscht) - genau deshalb gilt er hier pauschal
	 * als „Sitzung ungueltig".
	 *
	 * @type {number}
	 */
	var STATUS_ABGELAUFEN = 404;

	/**
	 * Ab dem wievielten Fehlschlag in Folge das Intervall verdoppelt wird.
	 *
	 * @type {number}
	 */
	var RUECKZUG_AB_FEHLER = 3;

	/**
	 * Obergrenze des Rueckzugs in Millisekunden.
	 *
	 * @type {number}
	 */
	var RUECKZUG_MAX_MS = 120000;

	/**
	 * Groesster erlaubter Verdopplungs-Exponent. Rein rechnerischer Schutz:
	 * Ohne ihn liefe `Math.pow(2, fehlerZaehler)` bei einem tagelang
	 * offenen Tab ins Unendliche, bevor die Deckelung greift.
	 *
	 * @type {number}
	 */
	var RUECKZUG_MAX_EXPONENT = 16;

	/**
	 * Ersatztakt in Sekunden, falls weder eine Serverantwort noch
	 * `cbdKlassenpulsDaten.takt` einen brauchbaren Wert liefert.
	 *
	 * @type {number}
	 */
	var TAKT_RUECKFALL = 10;

	/**
	 * Untergrenze der Streuung auf das Normalbetrieb-Intervall: 75 % des
	 * Takts (AP-1.fix1, siehe Regel 9 im Kopfkommentar).
	 *
	 * @type {number}
	 */
	var STREUUNG_MIN_FAKTOR = 0.75;

	/**
	 * Breite des Streuungsbereichs oberhalb der Untergrenze. Zusammen mit
	 * `STREUUNG_MIN_FAKTOR` ergibt sich ein Bereich von 75 % bis 125 % des
	 * Takts.
	 *
	 * @type {number}
	 */
	var STREUUNG_SPANNE_FAKTOR = 0.5;

	/**
	 * Absolute Untergrenze des gestreuten Intervalls in Millisekunden.
	 * Ohne sie fiele der Mindesttakt von 5 s (5000 ms) nach der Streuung
	 * auf bis zu 3750 ms - also unter die eigentlich vorgesehene Untergrenze.
	 *
	 * @type {number}
	 */
	var INTERVALL_MIN_MS = 5000;

	/**
	 * Ab dem wievielten Fehlschlag in Folge der DATEIMODUS endgueltig
	 * abgeschaltet wird. Anders als beim Rueckzug des Herzschlags wird hier
	 * nicht verlangsamt, sondern aufgegeben: Die Datei ist entweder da oder
	 * nicht, und der Herzschlag traegt den Betrieb allein weiter.
	 *
	 * @type {number}
	 */
	var DATEI_RUECKZUG_AB_FEHLER = 3;

	/**
	 * Absolute Untergrenze des Dateiintervalls in Millisekunden.
	 *
	 * @type {number}
	 */
	var DATEI_INTERVALL_MIN_MS = 1000;

	/**
	 * Spanne der Streuung des Dateitakts. Zusammen mit dem festen Summanden
	 * 1 ergibt das den Faktor 1,0 bis 1,5 - siehe `dateiIntervallMs()`.
	 *
	 * @type {number}
	 */
	var DATEI_STREUUNG_SPANNE_FAKTOR = 0.5;

	/**
	 * Rueckfallwert fuer den Herzschlag in Sekunden, falls die Antwort das
	 * Feld `herzschlag` nicht oder unbrauchbar liefert.
	 *
	 * @type {number}
	 */
	var HERZSCHLAG_RUECKFALL = 60;

	// =====================================================================
	// ZUSTAND
	// =====================================================================

	/**
	 * Die Klassensitzung: `{classroom: int, token: string}` oder `null`.
	 * Ohne sie wird nie abgefragt.
	 *
	 * @type {?Object}
	 */
	var sitzung = null;

	/**
	 * Die Seiten-ID, auf die sich `seite` und `tafel` beziehen. `0` bedeutet
	 * „kein Seitenbezug" - dann liefert der Server diese beiden Felder gar
	 * nicht (Klassen-Seitenliste, Fragenwand).
	 *
	 * @type {number}
	 */
	var seiteId = 0;

	/**
	 * Zuletzt gesehene Signatur je Name. Ein fehlender Schluessel bedeutet
	 * „noch nie gesehen" und loest deshalb nie einen Rueckruf aus.
	 *
	 * @type {Object}
	 */
	var signaturen = {};

	/**
	 * Name -> Array von Rueckrufen.
	 *
	 * @type {Object}
	 */
	var abonnenten = {};

	/**
	 * Handle des laufenden `setTimeout`, sonst `null`.
	 *
	 * @type {?number}
	 */
	var zeitgeber = null;

	/**
	 * Aktueller Takt in Sekunden. `0` heisst abgeschaltet bzw. noch
	 * unbekannt.
	 *
	 * @type {number}
	 */
	var takt = 0;

	/**
	 * Fehlschlaege in Folge. Jede erfolgreiche Antwort setzt ihn auf 0.
	 *
	 * @type {number}
	 */
	var fehlerZaehler = 0;

	/**
	 * Ob bereits eine Serverantwort verarbeitet wurde. Solange `false`,
	 * werden Signaturen nur aufgenommen, nie gemeldet (Regel 1 oben).
	 *
	 * @type {boolean}
	 */
	var erstanfrageErledigt = false;

	/**
	 * Ob gerade eine Abfrage unterwegs ist.
	 *
	 * @type {boolean}
	 */
	var laeuftGerade = false;

	/**
	 * Ob der Taktgeber sich wegen einer ungueltigen Sitzung endgueltig
	 * eingestellt hat. Wird nie wieder `false`.
	 *
	 * @type {boolean}
	 */
	var endgueltigGestoppt = false;

	/**
	 * Ob der Taktgeber laufen SOLL. Bleibt bei verstecktem Tab `true`,
	 * obwohl dann kein Zeitgeber laeuft - der Taktgeber ist dort pausiert,
	 * nicht gestoppt. Das ist der Rueckgabewert von `laeuft()`.
	 *
	 * @type {boolean}
	 */
	var aktiv = false;

	/**
	 * Fortlaufender Cache-Brecher.
	 *
	 * @type {number}
	 */
	var cacheZaehler = 0;

	/**
	 * Adresse der Pulsdatei, wie sie die Route im Feld `datei` nennt.
	 * `null` heisst: kein Dateimodus. Sie wird NIE hier zusammengesetzt -
	 * der Server kennt Uploadverzeichnis und HMAC-Anteil, der Browser nicht.
	 *
	 * @type {?string}
	 */
	var dateiUrl = null;

	/**
	 * Takt der Dateiabfrage in Sekunden. `0` heisst abgeschaltet.
	 *
	 * @type {number}
	 */
	var taktDatei = 0;

	/**
	 * Takt des Herzschlags in Sekunden - also des REST-Zeitgebers, solange
	 * der Dateimodus laeuft. Ohne Dateimodus ist er ohne Bedeutung.
	 *
	 * @type {number}
	 */
	var herzschlag = 0;

	/**
	 * Fehlschlaege der Dateiabfrage in Folge.
	 *
	 * @type {number}
	 */
	var dateiFehler = 0;

	/**
	 * Ob der Dateimodus fuer diesen Seitenaufruf aufgegeben wurde. Wird
	 * nur durch einen Sitzungswechsel wieder `false`.
	 *
	 * @type {boolean}
	 */
	var dateiModusAus = false;

	/**
	 * Handle des schnellen Zeitgebers, sonst `null`.
	 *
	 * @type {?number}
	 */
	var zeitgeberDatei = null;

	/**
	 * Ob gerade eine Dateiabfrage unterwegs ist. Eigenes Flag neben
	 * `laeuftGerade`: Die beiden Zeitgeber laufen unabhaengig, und Regel 7
	 * („es laeuft nie mehr als eine Abfrage") soll fuer beide gelten.
	 *
	 * @type {boolean}
	 */
	var dateiLaeuftGerade = false;

	// =====================================================================
	// HILFEN
	// =====================================================================

	/**
	 * Eine Meldung ausgeben, aber nur bei eingeschaltetem Debug-Modus.
	 *
	 * Hausregel des Plugins: `console.log` haengt hinter `window.cbdDebug`,
	 * `console.error` bleibt immer aktiv.
	 *
	 * @param {string} text Was passiert ist.
	 * @param {*}      [zusatz] Beliebiger Zusatzwert.
	 * @returns {void}
	 */
	function melde(text, zusatz) {
		if (!window.cbdDebug || !window.console || !window.console.log) {
			return;
		}

		if ('undefined' === typeof zusatz) {
			window.console.log('[CBD Klassenpuls] ' + text);
		} else {
			window.console.log('[CBD Klassenpuls] ' + text, zusatz);
		}
	}

	/**
	 * Die Basisadresse der Route aus dem lokalisierten Objekt lesen.
	 *
	 * Bewusst bei JEDEM Aufruf neu gelesen statt einmal beim Laden: So
	 * funktioniert die Datei auch, wenn `cbdKlassenpulsDaten` erst nach dem
	 * Skript gesetzt wird (Pruefschritt 2 aus AP-1.4 tut genau das).
	 *
	 * @returns {string} Die Adresse, oder `''` wenn es keine gibt.
	 */
	function basisUrl() {
		var daten = window.cbdKlassenpulsDaten;

		if (!daten || 'string' !== typeof daten.restUrl || '' === daten.restUrl) {
			return '';
		}

		return daten.restUrl;
	}

	/**
	 * Der Takt, der gilt, solange noch keine Serverantwort vorliegt.
	 *
	 * AP-1.5 legt `cbdKlassenpulsDaten.takt` mit `CBD_Klassenpuls::takt()`
	 * ab - das ist ebenfalls ein Serverwert, keine Konstante im Browser.
	 * `TAKT_RUECKFALL` greift nur, wenn das Feld fehlt oder Unsinn enthaelt.
	 *
	 * @returns {number} Takt in Sekunden, nie negativ.
	 */
	function anfangsTakt() {
		var daten = window.cbdKlassenpulsDaten;
		var wert;

		if (daten && 'undefined' !== typeof daten.takt && null !== daten.takt) {
			wert = parseInt(daten.takt, 10);

			if (!isNaN(wert)) {
				return wert < 0 ? 0 : wert;
			}
		}

		return TAKT_RUECKFALL;
	}

	/**
	 * Ist der Name ein gueltiger Abonnement-Name?
	 *
	 * @param {*} name Der zu pruefende Wert.
	 * @returns {boolean} True bei einem der fuenf erlaubten Namen.
	 */
	function istGueltigerName(name) {
		var i;

		if (NAME_ABGELAUFEN === name) {
			return true;
		}

		for (i = 0; i < SIGNATURNAMEN.length; i++) {
			if (SIGNATURNAMEN[i] === name) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Das Intervall bis zur naechsten Abfrage in Millisekunden.
	 *
	 * Grundlage ist der Servertakt. Im NORMALBETRIEB (kein laufender
	 * Rueckzug) wird das Intervall bei JEDER Planung neu um +-25 % gestreut
	 * (AP-1.fix1, Befund in `docs/messung-klassenpuls.md`, Abschnitt 5: Ohne
	 * Streuung blieben fuenf gleichzeitig gestartete Abfrageschleifen ueber
	 * zehn Minuten in einem 0,5-0,65 s breiten Zeitband - der beim
	 * gemeinsamen Stundenbeginn entstehende Gleichschritt einer Klasse loest
	 * sich von selbst nie wieder auf, weil die Serverantwortzeit auf diesem
	 * Server kaum streut). Eine einmalige, je Tab fest gezogene Streuung
	 * wuerde die Schueler nur dauerhaft konstant gegeneinander verschieben
	 * und den Abstand zwischen ihnen starr lassen - deshalb wird bei jedem
	 * Aufruf neu gewuerfelt.
	 *
	 * Ab dem dritten Fehlschlag in Folge greift STATTDESSEN die
	 * Rueckzugsverdopplung, mit jedem weiteren Fehlschlag erneut, gedeckelt
	 * bei `RUECKZUG_MAX_MS`. Die Streuung wird dort BEWUSST NICHT zusaetzlich
	 * angewendet: Die Verdopplung wirkt bereits genau wie eine Streuung nach
	 * oben, und der Gleichschritt-Fall aus AP-1.7/AP-1.fix1 betrifft ohnehin
	 * nur den Normalbetrieb - ein Server, der bereits Fehler wirft, hat kein
	 * Gleichschritt-Problem mehr, sondern ein Verfuegbarkeitsproblem.
	 *
	 * @returns {number} Millisekunden.
	 */
	/**
	 * Laeuft der Dateimodus gerade?
	 *
	 * Alle vier Bedingungen muessen erfuellt sein. Die Funktion ist die
	 * EINZIGE Stelle, die diese Frage beantwortet - sie entscheidet ueber den
	 * schnellen Zeitgeber UND ueber die Basis des Herzschlags.
	 *
	 * @returns {boolean}
	 */
	function dateiModusAktiv() {
		return !dateiModusAus
			&& 'string' === typeof dateiUrl
			&& '' !== dateiUrl
			&& taktDatei > 0
			&& !endgueltigGestoppt;
	}

	/**
	 * Das naechste Dateiintervall in Millisekunden, gestreut.
	 *
	 * BEWUSST NICHT DIE FORMEL DES REST-TAKTS. Dort wird mit
	 * `0,75 + Zufall * 0,5` um den Takt gestreut und danach auf eine
	 * Untergrenze geklemmt. Beim kleinen Dateitakt (Vorgabe 2 s) wird diese
	 * Untergrenze staendig erreicht, und dann landet rund die Haelfte aller
	 * Planungen exakt auf dem Minimum - genau die Entzerrung faellt damit
	 * weg, die die Streuung bewirken soll. Das ist der bekannte
	 * Bestandsbefund B10 aus `AP-1.rev` des Vorgaengervorhabens.
	 *
	 * Hier wird deshalb NUR NACH OBEN gestreut: `1 + Zufall * 0,5`. Bei
	 * `taktDatei = 2` ergibt das gleichverteilt 2000-3000 ms, ohne dass die
	 * Klemmung je greift - sie bleibt reine Absicherung.
	 *
	 * @returns {number} Millisekunden.
	 */
	function dateiIntervallMs() {
		var wert = taktDatei * 1000
			* (1 + Math.random() * DATEI_STREUUNG_SPANNE_FAKTOR);

		return wert < DATEI_INTERVALL_MIN_MS ? DATEI_INTERVALL_MIN_MS : wert;
	}

	function aktuellesIntervallMs() {
		// Im Dateimodus wird der REST-Zeitgeber zum Herzschlag und tickt
		// seltener. `Math.max()` sorgt dafuer, dass ein Betrieb mit einem
		// GROESSEREN Takt als dem Herzschlag (z. B. 300 s) dadurch nicht
		// ploetzlich haeufiger abfragt als eingestellt.
		var basis = (dateiModusAktiv() ? Math.max(herzschlag, takt) : takt) * 1000;
		var exponent;
		var wert;

		if (fehlerZaehler < RUECKZUG_AB_FEHLER) {
			wert = basis * (STREUUNG_MIN_FAKTOR + Math.random() * STREUUNG_SPANNE_FAKTOR);

			return wert < INTERVALL_MIN_MS ? INTERVALL_MIN_MS : wert;
		}

		exponent = fehlerZaehler - RUECKZUG_AB_FEHLER + 1;

		if (exponent > RUECKZUG_MAX_EXPONENT) {
			exponent = RUECKZUG_MAX_EXPONENT;
		}

		wert = basis * Math.pow(2, exponent);

		return wert > RUECKZUG_MAX_MS ? RUECKZUG_MAX_MS : wert;
	}

	/**
	 * Die Abfrageadresse zusammensetzen.
	 *
	 * @returns {string} Vollstaendige Adresse, oder `''` ohne Basis/Sitzung.
	 */
	function baueUrl() {
		var basis = basisUrl();
		var trenner;
		var url;

		if ('' === basis || !sitzung) {
			return '';
		}

		// `?rest_route=`-Installationen tragen bereits ein `?` in der Basis.
		trenner = basis.indexOf('?') > -1 ? '&' : '?';

		url = basis + trenner
			+ 'classroom=' + encodeURIComponent(sitzung.classroom)
			+ '&token=' + encodeURIComponent(sitzung.token);

		if (seiteId > 0) {
			url += '&page_id=' + encodeURIComponent(seiteId);
		}

		// Cache-Brecher: kein Zwischenspeicher darf eine alte Antwort
		// ausliefern. Der Server setzt zusaetzlich `nocache_headers()`.
		url += '&_=' + (cacheZaehler++);

		return url;
	}

	/**
	 * Alle Abonnenten eines Namens aufrufen.
	 *
	 * Jeder Rueckruf steckt in einem eigenen `try/catch`: Ein Fehler in
	 * einem Abonnenten darf weder den Taktgeber noch die uebrigen
	 * Abonnenten mitreissen. Die Liste wird vorher kopiert, damit ein
	 * Rueckruf sich waehrend des Durchlaufs abmelden darf.
	 *
	 * @param {string} name Signaturname oder `abgelaufen`.
	 * @param {*}      neu  Neue Signatur (bei `abgelaufen`: `null`).
	 * @param {*}      alt  Vorherige Signatur (bei `abgelaufen`: `null`).
	 * @returns {void}
	 */
	function rufeAbonnenten(name, neu, alt) {
		var liste = abonnenten[name];
		var kopie;
		var i;

		if (!liste || !liste.length) {
			return;
		}

		kopie = liste.slice();

		for (i = 0; i < kopie.length; i++) {
			try {
				kopie[i](neu, alt);
			} catch (fehler) {
				if (window.console && window.console.error) {
					window.console.error(
						'[CBD Klassenpuls] Fehler in einem Rueckruf fuer "' + name + '":',
						fehler
					);
				}
			}
		}
	}

	// =====================================================================
	// TAKT
	// =====================================================================

	/**
	 * Den laufenden Zeitgeber loeschen, ohne den Taktgeber zu stoppen.
	 *
	 * @returns {void}
	 */
	function loescheZeitgeber() {
		if (null !== zeitgeber) {
			window.clearTimeout(zeitgeber);
			zeitgeber = null;
		}
	}

	/**
	 * Die naechste Abfrage einplanen.
	 *
	 * Ein bereits eingeplanter Zeitgeber wird dabei ersetzt - die Funktion
	 * darf also gefahrlos mehrfach gerufen werden.
	 *
	 * @returns {void}
	 */
	/**
	 * Den laufenden Datei-Zeitgeber loeschen.
	 *
	 * @returns {void}
	 */
	function loescheDateiZeitgeber() {
		if (null !== zeitgeberDatei) {
			window.clearTimeout(zeitgeberDatei);
			zeitgeberDatei = null;
		}
	}

	/**
	 * Die naechste Dateiabfrage einplanen.
	 *
	 * Zwilling von `planeNaechsteAbfrage()`, mit denselben Ausstiegen -
	 * insbesondere demselben Verhalten bei verstecktem Tab (Regel 6). Der
	 * Weckruf kommt in beiden Faellen aus `beiSichtbarkeitswechsel()`.
	 *
	 * @returns {void}
	 */
	function planeNaechsteDateiAbfrage() {
		loescheDateiZeitgeber();

		if (!aktiv || endgueltigGestoppt || !sitzung || !dateiModusAktiv()) {
			return;
		}

		if (document.hidden) {
			return;
		}

		zeitgeberDatei = window.setTimeout(function () {
			zeitgeberDatei = null;
			frageDateiAb();
		}, dateiIntervallMs());
	}

	/**
	 * Den schnellen Zeitgeber an den aktuellen Zustand angleichen.
	 *
	 * Ein bereits laufender Zeitgeber wird BEWUSST NICHT neu geplant - sonst
	 * schoebe ihn jeder Herzschlag um ein volles Dateiintervall nach hinten.
	 *
	 * @returns {void}
	 */
	function dateiZeitgeberAbgleichen() {
		if (!dateiModusAktiv()) {
			loescheDateiZeitgeber();
			return;
		}

		if (null === zeitgeberDatei) {
			planeNaechsteDateiAbfrage();
		}
	}

	function planeNaechsteAbfrage() {
		loescheZeitgeber();

		if (!aktiv || endgueltigGestoppt || !sitzung || takt <= 0) {
			return;
		}

		// Bei verstecktem Tab wird nicht getaktet. Der Weckruf kommt aus
		// `beiSichtbarkeitswechsel()`.
		if (document.hidden) {
			return;
		}

		zeitgeber = window.setTimeout(function () {
			zeitgeber = null;
			frageAb();
		}, aktuellesIntervallMs());
	}

	/**
	 * Den Endzustand einnehmen: Sitzung ungueltig.
	 *
	 * @returns {void}
	 */
	function sitzungAbgelaufen() {
		if (endgueltigGestoppt) {
			return;
		}

		endgueltigGestoppt = true;
		halte();

		melde('Sitzung abgelaufen (HTTP ' + STATUS_ABGELAUFEN + '), Taktgeber endgueltig gestoppt.');

		rufeAbonnenten(NAME_ABGELAUFEN, null, null);
	}

	/**
	 * Eine Menge frischer Signaturen uebernehmen und die Rueckrufe ausloesen.
	 *
	 * HERAUSGELOEST IN AP-2.1, damit beide Datenquellen - die Route und die
	 * Pulsdatei - durch DIESELBE Vergleichslogik laufen. Zwei Kopien waeren
	 * zwei Gelegenheiten, Regel 1 unterschiedlich auszulegen.
	 *
	 * Der Rumpf ist zeichengleich aus `verarbeiteAntwort()` uebernommen,
	 * einschliesslich der Feuerbedingung. DREI Eigenschaften duerfen dabei
	 * nicht verloren gehen:
	 *
	 * 1. EIN FEHLENDER NAME WIRD UEBERSPRUNGEN, nicht auf `undefined`
	 *    zurueckgesetzt. `signaturen[name]` behaelt seinen Wert. Sonst
	 *    verlaere eine Quelle, die einen Namen gar nicht kennt (die
	 *    Pulsdatei ohne Seitenbezug kennt `seite`/`tafel` nicht), die von
	 *    der anderen Quelle bereits gesetzte Signatur - und die naechste
	 *    echte Aenderung fiele unter Regel 1 und wuerde verschluckt.
	 * 2. ERST ALLE VERGLEICHEN, DANN ALLE MELDEN. Ein Rueckruf darf nicht
	 *    mitten in der Vergleichsschleife laufen; er koennte `halte()`
	 *    rufen oder selbst Signaturen beruehren.
	 * 3. `erstanfrageErledigt` WIRD HIER GESETZT, nicht beim Aufrufer - und
	 *    es ist EINE Markierung fuer BEIDE Quellen, keine je Quelle. Regel 1
	 *    („ein Rueckruf feuert nie fuer eine Signatur, deren vorheriger Wert
	 *    `undefined` war") deckt zwei Quellen damit bereits ab: Welche
	 *    Quelle einen Namen zuerst sieht, ist gleichgueltig - sie legt den
	 *    Ausgangswert fest und meldet nichts.
	 *
	 *    EHRLICH DAZU (Mutationsprobe zu AP-2.1): Der Term
	 *    `erstanfrageErledigt &&` in der Feuerbedingung ist HEUTE redundant.
	 *    Der zweite Term (`alt` ist `undefined`) blockt jede Erstsichtung
	 *    bereits allein, weil `signaturen` und `erstanfrageErledigt` nur
	 *    GEMEINSAM zurueckgesetzt werden - beim Sitzungswechsel in
	 *    `setzeSitzung()` und sonst nirgends. Der Term bleibt trotzdem
	 *    stehen: Er kostet nichts und wird wieder tragend, sobald jemand
	 *    eine der beiden Marken einzeln zuruecksetzt. Wer das tut, sollte
	 *    wissen, dass er es tut.
	 *
	 * @param {Object} werte Name -> Signatur. Nur eigene Schluessel zaehlen.
	 * @returns {void}
	 */
	function uebernehmeSignaturen(werte) {
		var aenderungen = [];
		var i;
		var name;
		var neu;
		var alt;

		if (!werte || 'object' !== typeof werte) {
			return;
		}

		for (i = 0; i < SIGNATURNAMEN.length; i++) {
			name = SIGNATURNAMEN[i];

			if (!Object.prototype.hasOwnProperty.call(werte, name)) {
				continue;
			}

			neu = werte[name];
			alt = Object.prototype.hasOwnProperty.call(signaturen, name)
				? signaturen[name]
				: undefined;

			signaturen[name] = neu;

			// Gemeldet wird nur, was sich gegenueber einem BEKANNTEN Wert
			// unterscheidet - und erst ab der zweiten Antwort.
			if (erstanfrageErledigt && 'undefined' !== typeof alt && alt !== neu) {
				aenderungen.push({ name: name, neu: neu, alt: alt });
			}
		}

		erstanfrageErledigt = true;

		for (i = 0; i < aenderungen.length; i++) {
			melde('Signatur "' + aenderungen[i].name + '" geaendert.', aenderungen[i]);
			rufeAbonnenten(aenderungen[i].name, aenderungen[i].neu, aenderungen[i].alt);
		}
	}

	/**
	 * Eine erfolgreiche Antwort der ROUTE auswerten.
	 *
	 * Reihenfolge mit Absicht: erst Takt uebernehmen und den naechsten
	 * Durchlauf einplanen, DANN die Rueckrufe aufrufen. So gewinnt ein
	 * Abonnent, der in seinem Rueckruf `halte()` ruft.
	 *
	 * DESHALB steht der Aufruf von `uebernehmeSignaturen()` hier NACH der
	 * Planung, obwohl die Vergleichsschleife frueher davor stand: Die
	 * Funktion vergleicht UND meldet. Zwischen Vergleich und Planung liegt
	 * nichts, was `signaturen` oder `erstanfrageErledigt` liest - das
	 * Verhalten bleibt dasselbe, die Regel „planen vor melden" auch.
	 *
	 * @param {Object} daten Die geparste Antwort.
	 * @returns {void}
	 */
	function verarbeiteAntwort(daten) {
		var taktWert;

		if (!daten || 'object' !== typeof daten) {
			behandleFehler(new Error('Unbrauchbare Antwort'));
			return;
		}

		fehlerZaehler = 0;

		// --- Takt ---------------------------------------------------------
		if ('undefined' !== typeof daten.takt && null !== daten.takt) {
			taktWert = parseInt(daten.takt, 10);

			if (!isNaN(taktWert)) {
				takt = taktWert < 0 ? 0 : taktWert;
			}
		}

		// --- Dateimodus ---------------------------------------------------
		// MUSS vor der Planung stehen: `aktuellesIntervallMs()` liest
		// `dateiModusAktiv()` und damit alle drei Felder.
		dateiUrl = ('string' === typeof daten.datei && '' !== daten.datei)
			? daten.datei
			: null;

		taktWert = parseInt(daten.takt_datei, 10);
		taktDatei = (isNaN(taktWert) || taktWert < 0) ? 0 : taktWert;

		taktWert = parseInt(daten.herzschlag, 10);
		herzschlag = (isNaN(taktWert) || taktWert <= 0)
			? HERZSCHLAG_RUECKFALL
			: taktWert;

		// --- Naechster Durchlauf ------------------------------------------
		if (takt <= 0) {
			melde('Server meldet Takt 0 - Taktgeber angehalten.');
			halte();
		} else {
			planeNaechsteAbfrage();
			dateiZeitgeberAbgleichen();
		}

		// --- Signaturen vergleichen und melden -----------------------------
		uebernehmeSignaturen(daten);
	}

	/**
	 * Eine erfolgreiche Antwort der PULSDATEI auswerten.
	 *
	 * Die Datei hat eine andere Form als die Route: Sie gilt fuer die ganze
	 * Klasse und traegt die Seitensignaturen als Abbildung
	 * `seiten: { "<page_id>": ["<seite>", "<tafel>"] }`. Diese Funktion
	 * uebersetzt sie in die flache Form, die `uebernehmeSignaturen()`
	 * erwartet.
	 *
	 * DER TAKT WIRD AUS DER DATEI NICHT UEBERNOMMEN. `takt`, `takt_datei`
	 * und `herzschlag` kommen ausschliesslich aus der Antwort der Route -
	 * eine Autoritaet, nicht zwei. Das Feld `takt` in der Datei wird hier
	 * bewusst ignoriert; es steht dort nur, damit die Datei fuer sich
	 * gelesen verstaendlich bleibt. Zwei Quellen fuer denselben Wert waeren
	 * zwei Gelegenheiten, unterschiedlich schnell zu takten.
	 *
	 * ZWEI FAELLE, IN DENEN `seite`/`tafel` BEWUSST NICHT AUS DER DATEI
	 * KOMMEN - in beiden zieht erst der naechste Herzschlag ueber die Route
	 * sie nach:
	 *
	 * 1. `seiten_unvollstaendig === true`. Die Klasse hat mehr Seiten mit
	 *    Zeichnungsdatensaetzen als die serverseitige Obergrenze zulaesst;
	 *    der Schluessel `seiten` fehlt dann GANZ (weglassen statt kuerzen).
	 * 2. Die aktuelle `seiteId` hat keinen Eintrag in `seiten`. Fuer diese
	 *    Seite existiert noch kein Datensatz.
	 *
	 * EIN ERSATZWERT WAERE HIER FALSCH. Er muesste zeichengleich zu dem
	 * sein, was die Route fuer eine leere Menge berechnet; jede Abweichung
	 * erzeugte einen Rueckruf, sobald die Route das naechste Mal antwortet -
	 * und danach gleich wieder einen, sobald die Datei antwortet. Die
	 * Signatur spraenge dauerhaft zwischen beiden Quellen hin und her, und
	 * jeder Sprung kostet einen Stufe-2-Abruf.
	 *
	 * Ein fehlendes Feld wird gar nicht erst in `werte` aufgenommen - so
	 * bleibt die `hasOwnProperty`-Pruefung in `uebernehmeSignaturen()`
	 * zeichengleich zum Bestand, und eine beschaedigte Datei kann eine
	 * bereits bekannte Signatur nicht mit `undefined` ueberschreiben.
	 *
	 * @param {Object} daten Der geparste Inhalt der Pulsdatei.
	 * @returns {void}
	 */
	function verarbeiteDatei(daten) {
		var werte = {};
		var schluessel;
		var paar;

		if (!daten || 'object' !== typeof daten) {
			return;
		}

		if ('undefined' !== typeof daten.klasse && null !== daten.klasse) {
			werte.klasse = daten.klasse;
		}

		if ('undefined' !== typeof daten.fragenwand && null !== daten.fragenwand) {
			werte.fragenwand = daten.fragenwand;
		}

		if (seiteId > 0 && true !== daten.seiten_unvollstaendig
				&& daten.seiten && 'object' === typeof daten.seiten) {
			schluessel = String(seiteId);
			paar = Object.prototype.hasOwnProperty.call(daten.seiten, schluessel)
				? daten.seiten[schluessel]
				: null;

			if (paar && paar.length >= 2) {
				werte.seite = paar[0];
				werte.tafel = paar[1];
			}
		}

		uebernehmeSignaturen(werte);
	}

	/**
	 * Einen Fehlschlag verbuchen und den Rueckzug anwenden.
	 *
	 * @param {*} fehler Was schiefging.
	 * @returns {void}
	 */
	function behandleFehler(fehler) {
		fehlerZaehler++;

		melde('Abfrage fehlgeschlagen (' + fehlerZaehler + ' in Folge).', fehler);

		planeNaechsteAbfrage();
	}

	/**
	 * Eine Abfrage ausfuehren - sofern gerade eine ausgefuehrt werden darf.
	 *
	 * @returns {void}
	 */
	/**
	 * Einen Fehlschlag der Dateiabfrage verbuchen.
	 *
	 * DIE RUECKFALLKETTE IST DER GRUND, WARUM DIESES VORHABEN OHNE RISIKO
	 * FUER DEN UNTERRICHTSBETRIEB IST. Drei Fehlschlaege in Folge schalten
	 * den Dateimodus fuer diesen Seitenaufruf ab; der Herzschlag kehrt auf
	 * `takt` zurueck, und der Klassenmodus verhaelt sich danach wie vor
	 * diesem Vorhaben - nur wieder langsamer, nie gar nicht.
	 *
	 * @param {*} fehler Was schiefging.
	 * @returns {void}
	 */
	function behandleDateiFehler(fehler) {
		dateiFehler++;

		melde('Dateiabfrage fehlgeschlagen (' + dateiFehler + ' in Folge).', fehler);

		if (dateiFehler < DATEI_RUECKZUG_AB_FEHLER) {
			planeNaechsteDateiAbfrage();
			return;
		}

		dateiModusAus = true;
		loescheDateiZeitgeber();

		// Nicht hinter `window.cbdDebug`: Diese Zeile ist der einzige
		// Hinweis darauf, dass der schnelle Weg ausgefallen ist.
		if (window.console && window.console.warn) {
			window.console.warn('[CBD Klassenpuls] Pulsdatei dreimal nicht '
				+ 'erreichbar - Rueckfall auf den Servertakt.');
		}

		// Sofort neu planen, damit der Herzschlag ohne Umweg wieder auf
		// `takt` statt auf `herzschlag` laeuft.
		planeNaechsteAbfrage();
	}

	/**
	 * Die Pulsdatei abfragen.
	 *
	 * `cache: 'no-cache'` erzwingt clientseitig eine Revalidierung: Der
	 * Browser schickt `If-None-Match`/`If-Modified-Since` und bekommt im
	 * Normalfall HTTP 304 ohne Koerper zurueck - und das OHNE dass der
	 * Server dafuer eigene Kopfzeilen setzen muesste. `fetch()` loest dabei
	 * mit Status 200 und dem zwischengespeicherten Koerper auf; die 304
	 * sieht man im Netzwerkmitschnitt, nicht am Rueckgabewert.
	 *
	 * `credentials: 'omit'`, weil die Datei keine Anmeldung braucht - so
	 * reisen bei jedem Abruf keine Cookies mit.
	 *
	 * @returns {void}
	 */
	function frageDateiAb() {
		if (dateiLaeuftGerade || !dateiModusAktiv()) {
			return;
		}

		if (document.hidden) {
			return;
		}

		if ('function' !== typeof window.fetch) {
			return;
		}

		dateiLaeuftGerade = true;

		window.fetch(dateiUrl, { cache: 'no-cache', credentials: 'omit' })
			.then(function (antwort) {
				if (!antwort.ok) {
					return Promise.reject(new Error('HTTP ' + antwort.status));
				}

				return antwort.json();
			}).then(function (daten) {
				dateiLaeuftGerade = false;
				dateiFehler = 0;

				verarbeiteDatei(daten);
				planeNaechsteDateiAbfrage();
			})['catch'](function (fehler) {
				dateiLaeuftGerade = false;
				behandleDateiFehler(fehler);
			});
	}

	function frageAb() {
		var url;

		if (endgueltigGestoppt || laeuftGerade || !sitzung) {
			return;
		}

		// Bei verstecktem Tab wird nicht abgefragt.
		if (document.hidden) {
			return;
		}

		if ('function' !== typeof window.fetch) {
			return;
		}

		url = baueUrl();

		if ('' === url) {
			return;
		}

		laeuftGerade = true;

		window.fetch(url, { credentials: 'same-origin' }).then(function (antwort) {
			if (STATUS_ABGELAUFEN === antwort.status) {
				return { abgelaufen: true, daten: null };
			}

			if (!antwort.ok) {
				return Promise.reject(new Error('HTTP ' + antwort.status));
			}

			return antwort.json().then(function (daten) {
				return { abgelaufen: false, daten: daten };
			});
		}).then(function (ergebnis) {
			laeuftGerade = false;

			if (ergebnis.abgelaufen) {
				sitzungAbgelaufen();
				return;
			}

			verarbeiteAntwort(ergebnis.daten);
		})['catch'](function (fehler) {
			laeuftGerade = false;
			behandleFehler(fehler);
		});
	}

	// =====================================================================
	// OEFFENTLICHE SCHNITTSTELLE
	// =====================================================================

	/**
	 * Die Klassensitzung mitteilen und den Taktgeber starten.
	 *
	 * Eine bereits ueber `setzeSeite()` gesetzte `page_id` bleibt dabei
	 * UNANGETASTET (Regel 2 im Kopfkommentar). Derselbe Aufruf mit
	 * denselben Werten ist unschaedlich: Er startet den Taktgeber
	 * hoechstens, falls er noch nicht laeuft, und wirft nichts weg.
	 *
	 * @param {number|string} classroomId Klassen-ID.
	 * @param {string}        token       Sitzungs-Token.
	 * @returns {void}
	 */
	function setzeSitzung(classroomId, token) {
		var id = parseInt(classroomId, 10);
		var wert;

		if (isNaN(id) || id <= 0) {
			return;
		}

		if ('undefined' === typeof token || null === token) {
			return;
		}

		wert = '' + token;

		if ('' === wert) {
			return;
		}

		// Nur ein echter Wechsel verwirft die bisherigen Signaturen; sonst
		// wuerde ein zweiter Aufruf mit denselben Werten (AP-4.2 auf einer
		// Klassenseite) die Ausgangslage unnoetig neu bestimmen.
		if (!sitzung || sitzung.classroom !== id || sitzung.token !== wert) {
			sitzung = { classroom: id, token: wert };
			signaturen = {};
			erstanfrageErledigt = false;
			fehlerZaehler = 0;

			// Der Dateimodus gehoert zur Sitzung: andere Klasse, andere
			// Adresse. Auch ein frueher aufgegebener Dateimodus bekommt
			// damit eine neue Gelegenheit.
			dateiUrl = null;
			taktDatei = 0;
			dateiFehler = 0;
			dateiModusAus = false;
			loescheDateiZeitgeber();

			// Den Herzschlag neu planen. Noetig, weil `starte()` weiter unten
			// bei bereits laufendem Taktgeber sofort zurueckkehrt: Der schon
			// geplante Zeitgeber traegt dann noch die HERZSCHLAG-Basis (bis zu
			// 60 s), waehrend der Dateimodus soeben verworfen wurde - die neue
			// Sitzung wuerde also bis zu eine Minute lang gar nicht abgefragt.
			// Ohne den Dateimodus rechnet `aktuellesIntervallMs()` wieder mit
			// `takt`, der Zeitgeber steht damit sofort wieder auf dem
			// gewohnten Abstand.
			planeNaechsteAbfrage();

			melde('Sitzung gesetzt: Klasse ' + id + '.');
		}

		starte();
	}

	/**
	 * Die Seite mitteilen, auf die sich `seite` und `tafel` beziehen.
	 *
	 * Ein Wert <= 0 (oder Unsinn) hebt die Seitenbindung auf - der Server
	 * liefert dann keine seitenbezogenen Signaturen mehr.
	 *
	 * @param {number|string} pageId Seiten-ID.
	 * @returns {void}
	 */
	function setzeSeite(pageId) {
		var id = parseInt(pageId, 10);

		if (isNaN(id) || id < 0) {
			id = 0;
		}

		if (id === seiteId) {
			return;
		}

		seiteId = id;

		// Die seitenbezogenen Signaturen gehoerten zur alten Seite. Sie
		// werden vergessen, nicht ueberschrieben: Der naechste Durchlauf
		// nimmt sie als „noch nie gesehen" auf und meldet deshalb nichts.
		if (Object.prototype.hasOwnProperty.call(signaturen, 'seite')) {
			delete signaturen.seite;
		}

		if (Object.prototype.hasOwnProperty.call(signaturen, 'tafel')) {
			delete signaturen.tafel;
		}

		melde('Seite gesetzt: ' + id + '.');
	}

	/**
	 * Einen Rueckruf anmelden.
	 *
	 * @param {string}   name     `seite`, `tafel`, `klasse`, `fragenwand` oder `abgelaufen`.
	 * @param {Function} rueckruf Wird mit `(neueSignatur, alteSignatur)` gerufen.
	 * @returns {Function} Abmeldefunktion; mehrfaches Aufrufen ist harmlos.
	 */
	function abonniere(name, rueckruf) {
		var abgemeldet = false;

		function abmelden() {
			var liste;
			var stelle;

			if (abgemeldet) {
				return;
			}

			abgemeldet = true;
			liste = abonnenten[name];

			if (!liste) {
				return;
			}

			stelle = liste.indexOf(rueckruf);

			if (stelle > -1) {
				liste.splice(stelle, 1);
			}
		}

		if ('function' !== typeof rueckruf || !istGueltigerName(name)) {
			if (window.console && window.console.warn) {
				window.console.warn(
					'[CBD Klassenpuls] Abonnement abgelehnt - unbekannter Name oder kein Rueckruf:',
					name
				);
			}

			return function () {};
		}

		if (!abonnenten[name]) {
			abonnenten[name] = [];
		}

		abonnenten[name].push(rueckruf);

		return abmelden;
	}

	/**
	 * Den Taktgeber starten.
	 *
	 * Tut nichts ohne Sitzung, ohne Adresse, bei Takt 0 und nach einer
	 * abgelaufenen Sitzung. Laeuft er bereits, ist der Aufruf wirkungslos -
	 * es entsteht kein zweiter Zeitgeber und keine zweite Abfrage.
	 *
	 * @returns {void}
	 */
	function starte() {
		if (endgueltigGestoppt || aktiv || !sitzung) {
			return;
		}

		if ('' === basisUrl()) {
			return;
		}

		// Solange keine Serverantwort vorliegt, gilt der lokalisierte Takt.
		// Danach gilt ausschliesslich der Wert aus der Antwort - ein vom
		// Server auf 0 gesetzter Takt bleibt also 0.
		if (takt <= 0 && !erstanfrageErledigt) {
			takt = anfangsTakt();
		}

		if (takt <= 0) {
			return;
		}

		aktiv = true;

		melde('Taktgeber gestartet, Takt ' + takt + ' s.');

		frageAb();
		planeNaechsteAbfrage();

		// Im Regelfall wirkungslos: Beim ersten Start ist noch keine
		// Dateiadresse bekannt. Noetig fuer die Folge `halte()` ->
		// `setzeSitzung()` mit UNVERAENDERTEN Werten - dort bliebe der
		// schnelle Zeitgeber sonst bis zum naechsten Herzschlag aus.
		dateiZeitgeberAbgleichen();
	}

	/**
	 * Den Taktgeber anhalten.
	 *
	 * @returns {void}
	 */
	function halte() {
		aktiv = false;
		loescheZeitgeber();
		loescheDateiZeitgeber();
	}

	/**
	 * Eine Abfrage sofort ausfuehren, ohne den Takt zu veraendern.
	 *
	 * Bewusst NICHT an `takt > 0` gebunden: Der Aufruf ist die ausdrueckliche
	 * Bitte eines Verbrauchers um genau eine Abfrage jetzt. An einer
	 * abgelaufenen Sitzung, einer laufenden Abfrage, einer fehlenden Sitzung
	 * und einem versteckten Tab scheitert er trotzdem.
	 *
	 * @returns {void}
	 */
	function sofort() {
		frageAb();

		if (dateiModusAktiv()) {
			frageDateiAb();
		}
	}

	/**
	 * Laeuft der Taktgeber?
	 *
	 * Bei verstecktem Tab weiterhin `true` - dort ist er pausiert, nicht
	 * gestoppt.
	 *
	 * @returns {boolean} True, solange er laufen soll.
	 */
	function laeuft() {
		return aktiv;
	}

	// =====================================================================
	// SICHTBARKEIT DES TABS
	// =====================================================================

	/**
	 * Auf einen Wechsel der Tab-Sichtbarkeit reagieren.
	 *
	 * Versteckt: Zeitgeber anhalten, `aktiv` unangetastet lassen.
	 * Sichtbar: sofort einmal abfragen, danach normal weiter.
	 *
	 * @returns {void}
	 */
	function beiSichtbarkeitswechsel() {
		if (document.hidden) {
			loescheZeitgeber();
			loescheDateiZeitgeber();
			melde('Tab versteckt - Abfragen pausiert.');
			return;
		}

		if (!aktiv || endgueltigGestoppt) {
			return;
		}

		melde('Tab wieder sichtbar - sofortige Abfrage.');

		frageAb();
		planeNaechsteAbfrage();

		if (dateiModusAktiv()) {
			frageDateiAb();
			planeNaechsteDateiAbfrage();
		}
	}

	if (document.addEventListener) {
		document.addEventListener('visibilitychange', beiSichtbarkeitswechsel);
	}

	// =====================================================================
	// VEROEFFENTLICHEN
	// =====================================================================

	/**
	 * Ein bereits vorhandenes Objekt wird ergaenzt statt ersetzt - die
	 * Reihenfolge, in der Skripte laufen, soll keine Rolle spielen (Muster
	 * aus `fragenwand-frontend.js`).
	 */
	var api = window.cbdKlassenpuls || {};

	api.setzeSitzung = setzeSitzung;
	api.setzeSeite = setzeSeite;
	api.abonniere = abonniere;
	api.starte = starte;
	api.halte = halte;
	api.sofort = sofort;
	api.laeuft = laeuft;

	window.cbdKlassenpuls = api;

	// Beim Laden wird BEWUSST nicht gestartet. Der Start erfolgt, sobald ein
	// Verbraucher `setzeSitzung()` ruft.
})(window, document);
