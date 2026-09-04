/**
 * Container Block Designer - Classroom Page Filter
 * Filters container blocks on normal WordPress pages in classroom mode
 * @package ContainerBlockDesigner
 * @since 3.0.0
 */

(function($) {
    'use strict';

    /**
     * sessionStorage-Vertrag „Wiederaufnahme" (PLAN-Klassenmodus-Live.md,
     * AP-3.1) — der Vertrag steht hier, damit er nicht stillschweigend
     * auseinanderläuft.
     *
     * Unter dem Schlüssel `cbd_klassenmodus_wiederaufnahme` liegt genau ein
     * JSON-Objekt:
     *
     *     {
     *         seite:   <pageId>,                  // Zahl, aus cbdClassroomPageData
     *         klasse:  <classroomId>,             // String, aus ?classroom=
     *         scrollY: <Zahl>,                    // window.scrollY vor dem Neuladen
     *         grund:   'freigabe' | 'tafel',      // was das Neuladen ausgelöst hat
     *         zeit:    <ms>                       // Date.now() beim Schreiben
     *     }
     *
     * Der Eintrag gilt für GENAU EINEN Ladevorgang. `stelleWiederaufnahmeHer()`
     * entfernt ihn beim Auslesen **bedingungslos** — auch wenn er nicht zur
     * Seite passt oder das Parsen wirft. Das ist die wichtigste Regel dieses
     * Vertrags: Ein liegengebliebener Eintrag darf niemals einen zweiten
     * Ladevorgang beeinflussen, sonst entstünde eine Seite, die sich immer
     * wieder selbst neu lädt.
     *
     * `sessionStorage` und nicht `localStorage`: Der Eintrag soll den Tab
     * nicht überleben. Ein zweiter Tab (zweite Sitzung) darf nichts davon
     * sehen.
     *
     * **Jeder Zugriff steht in `try/catch`** — in privaten Fenstern und bei
     * blockierten Website-Daten wirft schon der reine Zugriff auf
     * `window.sessionStorage` eine Ausnahme.
     *
     * Das Feld `zeit` hat zwei Aufgaben: Es verwirft veraltete Einträge (älter
     * als `MINDESTABSTAND_MS`) UND es trägt den Mindestabstand über den
     * Ladevorgang hinweg — eine Instanzvariable allein überlebt
     * `window.location.reload()` nicht.
     */
    var SPEICHER_SCHLUESSEL = 'cbd_klassenmodus_wiederaufnahme';

    /**
     * Frühestens 60 Sekunden zwischen zwei selbst ausgelösten Neuladungen.
     *
     * Zeichnet die Lehrperson fortlaufend an einem Tafelbild, bewegt sich die
     * Signatur 'tafel' bei jedem Speichern. Ohne Mindestabstand lüde die
     * reduzierte Seite dem Schüler im Minutentakt unter den Händen weg.
     * Derselbe Wert dient als Höchstalter eines Wiederaufnahme-Eintrags.
     */
    var MINDESTABSTAND_MS = 60000;

    /**
     * Anzeigedauer des Wiederaufnahme-Hinweises in Millisekunden — dieselben
     * 8 Sekunden wie bei `cbd-neu-freigegeben` (AP-2.1/AP-2.3).
     */
    var HINWEIS_DAUER_MS = 8000;

    /**
     * Wie lange die Abschiedsmeldung stehen bleibt, bevor der Schüler zur
     * Klassenübersicht umgeleitet wird (AP-3.2).
     *
     * Kürzer als HINWEIS_DAUER_MS, weil die Meldung nicht wieder verschwindet,
     * sondern von der Umleitung abgelöst wird — sie muss nur lang genug
     * stehen, um gelesen zu werden.
     */
    var ABSCHIED_WARTE_MS = 4000;

    /**
     * localStorage-Vertrag „Klassenmodus" (siehe PLAN-Inhaltsverzeichnisse.md,
     * Abschnitt 4): Schlüssel 'cbd_classroom_toc_collapsed', JSON-Array von
     * Seiten-IDs (als Strings) der zugeklappten Knoten. Identischer Code wie
     * in classroom-frontend.js (AP-1.3a) — beide Dateien laufen nie
     * gleichzeitig auf derselben Seite, eine gemeinsame Modul-Datei gibt es
     * in diesem Plugin nicht (kein Build-Prozess).
     */
    function cbdKlassenverzeichnisGeleseneCollapsedIds() {
        try {
            var roh = localStorage.getItem('cbd_classroom_toc_collapsed');
            var arr = roh ? JSON.parse(roh) : [];
            return Array.isArray(arr) ? arr : [];
        } catch (e) { return []; }
    }
    function cbdKlassenverzeichnisSchreibeCollapsedIds(idsArray) {
        try { localStorage.setItem('cbd_classroom_toc_collapsed', JSON.stringify(idsArray)); } catch (e) {}
    }

    var ClassroomPageFilter = {
        classroomId: null,
        token: null,
        pageId: null,
        className: null,

        /**
         * Zeitpunkt der letzten SELBST ausgelösten Neuladung (ms seit Epoche),
         * 0 wenn in dieser Sitzung noch keine stattgefunden hat (AP-3.1).
         *
         * Die Variable überlebt `window.location.reload()` NICHT — deshalb
         * füllt `stelleWiederaufnahmeHer()` sie unmittelbar nach dem Laden aus
         * dem `zeit`-Feld des gerade gelesenen Wiederaufnahme-Objekts. Erst
         * diese Übergabe lässt den Mindestabstand über den Ladevorgang hinweg
         * greifen; ein reiner Zähler im Objekt täte das nicht.
         */
        letzteNeuladung: 0,

        /**
         * Grund einer wegen des Mindestabstands zurückgestellten Neuladung
         * ('freigabe' | 'tafel' | null), plus der Zeitgeber, der sie zum
         * frühestmöglichen Zeitpunkt nachzieht (AP-3.1).
         */
        vorgemerkterGrund: null,
        abstandZeitgeber: null,

        /**
         * Ob gerade eine Freigabeprüfung unterwegs ist bzw. die Umleitung
         * bereits beschlossen wurde (AP-3.2).
         *
         * Beide sperren jeden weiteren Neuladeversuch ab. Nötig, weil das
         * Zurücknehmen der letzten Freigabe die Signaturen `'seite'` UND
         * `'tafel'` im selben Durchlauf bewegt: Ohne die Sperre liefe der
         * zweite Rückruf synchron ins Neuladen, während die Prüfung des ersten
         * noch läuft — und damit in genau den 403, den AP-3.2 verhindert.
         */
        pruefungLaeuft: false,
        abschiedLaeuft: false,

        init: function() {
            // Get URL parameters
            var urlParams = new URLSearchParams(window.location.search);
            this.classroomId = urlParams.get('classroom');
            this.token = urlParams.get('token');

            // Get page ID from localized data
            if (typeof cbdClassroomPageData !== 'undefined') {
                this.pageId = cbdClassroomPageData.pageId;
            }

            // Wiederaufnahme BEDINGUNGSLOS und VOR jedem Datenabruf abholen
            // (AP-3.1) — bewusst noch vor der Parameterprüfung darunter:
            // Der Aufruf räumt den sessionStorage-Eintrag in jedem Fall ab,
            // auch wenn er nicht zu dieser Seite gehört. Stünde er hinter
            // dem `return`, bliebe ein Eintrag auf einer Seite ohne
            // Klassenparameter liegen. Die Zuordnung prüft die Methode selbst
            // gegen `this.pageId`/`this.classroomId` (beide oben gesetzt,
            // notfalls null — dann passt der Eintrag schlicht nicht).
            this.stelleWiederaufnahmeHer();

            // Only run if we have all required parameters
            if (!this.classroomId || !this.token || !this.pageId) {
                window.cbdDebug && console.log('CBD Classroom Page Filter: Missing parameters, skipping');
                return;
            }

            window.cbdDebug && console.log('CBD Classroom Page Filter: Initializing for page', this.pageId, 'classroom', this.classroomId);
            this.loadClassroomData();
            this.verdrahteKlassenpuls();
        },

        /**
         * Den Taktgeber `window.cbdKlassenpuls` anzapfen (AP-2.1).
         *
         * Der Taktgeber ist OPTIONAL: Steht die Option `cbd_klassenpuls_takt`
         * auf 0, reiht `CBD_Classroom::enqueue_frontend_assets()` die Datei
         * `assets/js/klassenpuls.js` gar nicht erst ein — `window.cbdKlassenpuls`
         * existiert dann nicht und diese Methode steigt still aus. Der Filter
         * verhält sich in diesem Fall exakt wie vor diesem Arbeitspaket.
         *
         * Reihenfolge mit Absicht: erst `setzeSeite()`, dann `setzeSitzung()`.
         * `setzeSitzung()` startet den Taktgeber sofort (`starte()` ruft
         * synchron `frageAb()`); wäre die Seite da noch nicht gesetzt, ginge
         * die allererste Abfrage ohne `page_id` hinaus und der Server lieferte
         * die Signaturen `seite`/`tafel` erst beim zweiten Durchlauf. Der
         * Vertrag von `setzeSeite()` erlaubt den Aufruf vor `setzeSitzung()`
         * ausdrücklich (die Seitenbindung überlebt das Setzen der Sitzung).
         */
        verdrahteKlassenpuls: function() {
            if (!window.cbdKlassenpuls) {
                window.cbdDebug && console.log('CBD Classroom Page Filter: Kein Taktgeber vorhanden (cbd_klassenpuls_takt = 0?) – keine Live-Aktualisierung.');
                return;
            }

            var self = this;

            window.cbdKlassenpuls.setzeSeite(this.pageId);
            window.cbdKlassenpuls.setzeSitzung(this.classroomId, this.token);

            // Auf einer serverseitig reduzierten Seite liegt das HTML der noch
            // nicht freigegebenen Container GAR NICHT im DOM – ein Einblenden
            // wäre wirkungslos. `CBD_Classroom_Gate::inhalt_reduzieren()` hängt
            // auf `the_content` (Priorität 8, vor `do_blocks()` auf 9) und gibt
            // ausschließlich die freigegebenen Container aus; alles andere wird
            // verworfen und NIE ausgeliefert.
            //
            // Deshalb wird hier seit AP-3.1 gezielt NEU GELADEN statt
            // nachgeladen. Die Entscheidung ist in Abschnitt 4 von
            // PLAN-Klassenmodus-Live.md begründet und darf nicht umgedreht
            // werden: (1) `assets/js/interactivity-store.js` ist ein ESM-Modul
            // auf @wordpress/interactivity – WordPress bietet keinen Weg,
            // nachträglich eingefügtes DOM zu hydratisieren; Aufklappen,
            // Kopieren, Screenshot und PDF wären am eingefügten Block tot.
            // (2) Der jQuery-Rückfall hilft nicht aus: `interactivity-fallback.js`
            // steigt in `checkInteractivityAPI()` aus, sobald die Interactivity
            // API da ist, und seine Pro-Container-Initialisierung
            // `initializeContainers()` ist eine Closure in `$(document).ready()`.
            // (3) Die serverseitige Reduktion ist die kanonische Ausgabe.
            //
            // Die beiden Zweige schließen sich gegenseitig aus: Auf einer
            // reduzierten Seite gibt es KEIN `.show()`/`.hide()`, auf einer
            // normalen Seite KEIN Neuladen.
            if (this.istReduzierteSeite()) {
                window.cbdDebug && console.log('CBD Classroom Page Filter: Reduzierte Seite – Live-Aktualisierung über gezieltes Neuladen (AP-3.1).');

                window.cbdKlassenpuls.abonniere('seite', function() {
                    self.ladeNeu('freigabe');
                });

                window.cbdKlassenpuls.abonniere('tafel', function() {
                    self.ladeNeu('tafel');
                });
            } else {
                window.cbdKlassenpuls.abonniere('seite', function() {
                    self.aktualisiere();
                });

                // Tafelbilder aktualisieren sich unabhängig von Freigaben
                // (AP-2.2): Die Signatur 'tafel' bewegt sich bei JEDEM
                // Schreibvorgang an wp_cbd_drawings, nicht nur bei
                // Freigabe/Rücknahme. Auf reduzierten Seiten bewusst NICHT
                // registriert, aus demselben Grund wie beim 'seite'-Zweig
                // oben – Phase 3 deckt diese Ansicht gesondert ab.
                window.cbdKlassenpuls.abonniere('tafel', function() {
                    self.aktualisiereTafelbilder();
                });
            }

            window.cbdKlassenpuls.abonniere('abgelaufen', function() {
                self.showError('Die Klassensitzung ist abgelaufen. Bitte erneut anmelden.');
            });
        },

        /**
         * Läuft diese Seite serverseitig reduziert?
         * Der Wert kommt aus CBD_Classroom::enqueue_frontend_assets().
         */
        istReduzierteSeite: function() {
            return (typeof cbdClassroomPageData !== 'undefined')
                && !!cbdClassroomPageData.reduziert;
        },

        /**
         * Benutzbaren `window.sessionStorage` liefern oder `null` (AP-3.1).
         *
         * In privaten Fenstern und bei blockierten Website-Daten wirft
         * bereits der LESENDE ZUGRIFF AUF DIE EIGENSCHAFT selbst (nicht erst
         * `getItem()`) eine SecurityError-Ausnahme. Deshalb steht schon der
         * Eigenschaftszugriff hier in `try/catch` — jeder Aufrufer bekommt
         * entweder ein benutzbares Objekt oder `null` und muss sich um die
         * Ausnahme nicht mehr kümmern.
         */
        sitzungsSpeicher: function() {
            try {
                return window.sessionStorage || null;
            } catch (e) {
                return null;
            }
        },

        /**
         * Leseposition und Anlass für den nächsten Ladevorgang merken (AP-3.1).
         *
         * Schreibt das im Kopfkommentar dieser Datei beschriebene Objekt unter
         * SPEICHER_SCHLUESSEL. Schlägt das Schreiben fehl, wird NICHT
         * abgebrochen: Ohne Eintrag geht lediglich die Leseposition verloren,
         * das Neuladen selbst wirkt trotzdem — und der Mindestabstand greift
         * dann über den Ladevorgang hinweg nicht mehr. Genau deshalb ist der
         * Mindestabstand zusätzlich an den Zeitgeber in `ladeNeu()` gebunden.
         *
         * @param {string} grund 'freigabe' oder 'tafel'.
         */
        merkeWiederaufnahme: function(grund) {
            var speicher = this.sitzungsSpeicher();

            if (!speicher) {
                return;
            }

            try {
                speicher.setItem(SPEICHER_SCHLUESSEL, JSON.stringify({
                    seite: this.pageId,
                    klasse: this.classroomId,
                    scrollY: window.scrollY || window.pageYOffset || 0,
                    grund: grund,
                    zeit: Date.now()
                }));
            } catch (e) {
                window.cbdDebug && console.log('CBD Classroom Page Filter: Wiederaufnahme konnte nicht gespeichert werden – Neuladen trotzdem.', e);
            }
        },

        /**
         * Die reduzierte Seite gezielt neu laden (AP-3.1).
         *
         * Wird ausschließlich aus den beiden Rückrufen des Taktgebers auf
         * REDUZIERTEN Seiten gerufen. Auf normalen Seiten gibt es diesen Pfad
         * nicht — dort blendet AP-2.1/AP-2.2 ein und aus.
         *
         * **Seit AP-3.2 lädt diese Methode nicht mehr selbst neu.** Sie prüft
         * nur noch den Mindestabstand und übergibt dann an
         * `pruefeFreigabeUndLade()`, das erst feststellt, ob die Seite
         * überhaupt noch freigegeben ist. Das tatsächliche Neuladen steht in
         * `fuehreNeuladenAus()`.
         *
         * **Mindestabstand.** Zwischen zwei selbst ausgelösten Neuladungen
         * liegen mindestens MINDESTABSTAND_MS. Der Abstand greift über den
         * Ladevorgang hinweg, weil `stelleWiederaufnahmeHer()` unmittelbar nach
         * dem Laden `letzteNeuladung` aus dem `zeit`-Feld des gespeicherten
         * Objekts füllt — eine Instanzvariable allein überlebt
         * `window.location.reload()` nicht.
         *
         * Wird in der Sperrzeit eine Änderung gemeldet, geht sie NICHT
         * verloren: Der Grund wird vorgemerkt und ein einzelner Zeitgeber zieht
         * ihn zum frühestmöglichen Zeitpunkt nach. 'freigabe' verdrängt dabei
         * ein vorgemerktes 'tafel' (neue Inhalte sind wichtiger als ein
         * geändertes Tafelbild), umgekehrt nicht.
         *
         * @param {string} grund 'freigabe' oder 'tafel'.
         */
        ladeNeu: function(grund) {
            var self = this;
            var jetzt = Date.now();
            var verstrichen;
            var rest;

            if (this.letzteNeuladung) {
                verstrichen = jetzt - this.letzteNeuladung;

                // Eine zurückgestellte Uhr (verstrichen < 0) gilt bewusst als
                // „noch nicht abgelaufen" – die vorsichtige Richtung, denn ein
                // zu frühes Neuladen ist der teurere Fehler.
                if (verstrichen < MINDESTABSTAND_MS) {
                    rest = MINDESTABSTAND_MS - verstrichen;
                    if (rest < 0 || rest > MINDESTABSTAND_MS) {
                        rest = MINDESTABSTAND_MS;
                    }

                    if (grund === 'freigabe' || !this.vorgemerkterGrund) {
                        this.vorgemerkterGrund = grund;
                    }

                    // Nur EIN Zeitgeber, egal wie viele Änderungen in der
                    // Sperrzeit gemeldet werden.
                    if (!this.abstandZeitgeber) {
                        this.abstandZeitgeber = window.setTimeout(function() {
                            var nachgezogen = self.vorgemerkterGrund;

                            self.abstandZeitgeber = null;
                            self.vorgemerkterGrund = null;

                            if (nachgezogen) {
                                self.ladeNeu(nachgezogen);
                            }
                        }, rest + 250);
                    }

                    window.cbdDebug && console.log('CBD Classroom Page Filter: Neuladen (' + grund + ') zurückgestellt, Mindestabstand noch ' + rest + ' ms.');
                    return;
                }
            }

            // Seit AP-3.2 wird hier NICHT mehr direkt neu geladen: Erst muss
            // feststehen, dass die Seite für diese Klasse überhaupt noch
            // freigegeben ist. Sonst liefe das Neuladen in die 403-Seite.
            this.pruefeFreigabeUndLade(grund);
        },

        /**
         * Vor dem Neuladen prüfen, ob die Seite für diese Klasse noch
         * freigegeben ist (AP-3.2).
         *
         * **Warum das nötig ist.** Eine gesperrte Seite ist für einen Schüler
         * nur erreichbar, solange mindestens ein Container für seine Klasse
         * freigegeben ist: `CBD_Classroom_Gate::seite_freigeben()` öffnet den
         * Theme-Filter `simple_clean_lehrerseite_freigeben` genau dann, wenn
         * eine gültige Sitzung vorliegt UND
         * `CBD_Classroom::behandelte_container()` mindestens einen Treffer
         * liefert. Nimmt die Lehrperson die LETZTE Freigabe zurück, während
         * der Schüler auf der Seite steht, liefe das Neuladen aus AP-3.1 in
         * die 403-Hinweisseite des Themes — für den Schüler ein
         * Fehlerbildschirm ohne erkennbaren Grund.
         *
         * **`treated_containers` ist dieselbe Wahrheit wie das Gate.** Die
         * Liste in der Antwort von `cbd_get_page_classroom_data` entsteht aus
         * derselben Tabelle mit demselben Filter (`is_behandelt = 1`) und
         * derselben Reduktion auf Basis-Kennungen wie
         * `behandelte_container()`. Leere Liste ⇔ geschlossenes Gate.
         *
         * **Die Prüfung gilt für BEIDE Anlässe, nicht nur für 'freigabe'** —
         * hier weicht die Umsetzung bewusst vom AP-Text ab, der nur
         * `ladeNeu('freigabe')` nennt. Grund: Das Zurücknehmen einer Freigabe
         * setzt `is_behandelt = 0` und schreibt dabei `updated_at` mit, bewegt
         * also **auch** die Signatur `'tafel'`. Beide Rückrufe feuern damit im
         * selben Durchlauf. Würde nur der Freigabe-Zweig prüfen, liefe der
         * Tafel-Zweig unmittelbar danach synchron in `reload()` — genau in den
         * 403, den dieses AP verhindern soll.
         *
         * Aus demselben Grund sperrt `pruefungLaeuft` den zweiten Aufruf ab,
         * solange der erste noch unterwegs ist. Der zweite Anlass geht dabei
         * nicht verloren: Führt die laufende Prüfung zum Neuladen, bringt das
         * Neuladen ohnehin beides mit.
         *
         * **Kosten:** Diese Abfrage läuft nur, wenn sich eine Signatur
         * tatsächlich bewegt hat — also einige Male je Unterrichtsstunde, nicht
         * im Takt des Pulses.
         *
         * @param {string} grund 'freigabe' oder 'tafel'.
         */
        pruefeFreigabeUndLade: function(grund) {
            var self = this;

            if (this.pruefungLaeuft || this.abschiedLaeuft) {
                window.cbdDebug && console.log('CBD Classroom Page Filter: Freigabeprüfung läuft bereits – zweiter Anlass (' + grund + ') übersprungen.');
                return;
            }

            this.pruefungLaeuft = true;

            $.post(cbdClassroomPageData.ajaxUrl, {
                action: 'cbd_get_page_classroom_data',
                token: this.token,
                page_id: this.pageId
            }, function(response) {
                var liste;
                var anzahl = 0;
                var i;

                self.pruefungLaeuft = false;

                if (!response || !response.success) {
                    // Kein verlässlicher Befund: NICHT neu laden. Siehe die
                    // Begründung im fail()-Zweig unten.
                    window.cbdDebug && console.log('CBD Classroom Page Filter: Freigabeprüfung ohne Erfolg – Neuladen unterbleibt.');
                    return;
                }

                liste = (response.data && response.data.treated_containers) || [];

                // Leere Kennungen zählen nicht — dieselbe Regel wie in
                // `CBD_Classroom::behandelte_container()`, das '' aussortiert.
                // Ohne diesen Abgleich könnte die Liste nicht-leer wirken,
                // während das Gate sie als leer sieht: genau die Abweichung,
                // die wieder in den 403 führte.
                if (liste && typeof liste.length === 'number') {
                    for (i = 0; i < liste.length; i++) {
                        if (liste[i] !== null && typeof liste[i] !== 'undefined' && String(liste[i]) !== '') {
                            anzahl++;
                        }
                    }
                }

                if (anzahl > 0) {
                    self.fuehreNeuladenAus(grund);
                } else {
                    window.cbdDebug && console.log('CBD Classroom Page Filter: Keine Freigabe mehr auf dieser Seite – Umleitung statt Neuladen.');
                    self.verlasseGesperrteSeite();
                }
            }).fail(function(xhr, status, error) {
                self.pruefungLaeuft = false;

                // Bewusst NICHT neu laden. Ohne Befund wäre das Neuladen ein
                // Glücksspiel mit zwei ungleichen Einsätzen: Der Fehlschlag
                // „Seite lädt einmal nicht nach" ist mild und beim nächsten
                // Anlass von selbst behoben; der Fehlschlag „403-Fehlerseite"
                // ist für den Schüler ein Sackgassenbildschirm. Der
                // Mindestabstand bleibt dabei ungenutzt (`letzteNeuladung`
                // wird erst in `fuehreNeuladenAus()` gesetzt), die nächste
                // echte Änderung darf also sofort wieder prüfen.
                window.cbdDebug && console.log('CBD Classroom Page Filter: Freigabeprüfung fehlgeschlagen – Neuladen unterbleibt.', error);
            });
        },

        /**
         * Das eigentliche Neuladen ausführen (AP-3.2, herausgezogen aus
         * `ladeNeu()`).
         *
         * **`letzteNeuladung` wird hier gesetzt, nicht schon in `ladeNeu()`.**
         * Der Mindestabstand darf nur verbrauchen, wer tatsächlich neu lädt —
         * eine fehlgeschlagene Freigabeprüfung würde sonst die nächsten 60
         * Sekunden blockieren, ohne dass etwas passiert wäre.
         *
         * @param {string} grund 'freigabe' oder 'tafel'.
         */
        fuehreNeuladenAus: function(grund) {
            this.letzteNeuladung = Date.now();
            this.merkeWiederaufnahme(grund);

            window.cbdDebug && console.log('CBD Classroom Page Filter: Reduzierte Seite wird neu geladen (' + grund + ').');
            window.location.reload();
        },

        /**
         * Die nicht mehr freigegebene Seite verlassen (AP-3.2).
         *
         * Zeigt eine dauerhaft stehende Abschiedsmeldung und navigiert nach
         * ABSCHIED_WARTE_MS zur Klassenübersicht. **Ausdrücklich kein
         * `window.location.reload()`** — das führte in die 403-Seite, die
         * dieser ganze Pfad vermeiden soll.
         *
         * Getrennt vom Fall „Sitzung abgelaufen": Der `'abgelaufen'`-Rückruf
         * aus AP-2.1 zeigt weiterhin seine eigene Meldung über `showError()`
         * und leitet NICHT um. Eine abgelaufene Sitzung und eine
         * zurückgenommene Freigabe sind verschiedene Dinge und verdienen
         * verschiedene Texte.
         */
        verlasseGesperrteSeite: function() {
            var ziel;
            var $hinweis;

            if (this.abschiedLaeuft) {
                return;
            }

            this.abschiedLaeuft = true;

            // Einen offenen Wiederaufnahme-Hinweis abräumen: Zwei Leisten
            // übereinander wären nicht lesbar, und die alte Meldung („Neu
            // freigegeben") wäre jetzt falsch.
            $('.cbd-live-hinweis').remove();

            $hinweis = $('<div class="cbd-live-hinweis cbd-live-hinweis--abschied" role="status"></div>')
                .text('Diese Seite ist für deine Klasse nicht mehr freigegeben. Du wirst zur Kapitelübersicht zurückgebracht.');

            this.haengeHinweisEin($hinweis);

            ziel = this.klassenlistenZiel();

            window.cbdDebug && console.log('CBD Classroom Page Filter: Umleitung in ' + ABSCHIED_WARTE_MS + ' ms nach ' + ziel);

            window.setTimeout(function() {
                window.location.href = ziel;
            }, ABSCHIED_WARTE_MS);
        },

        /**
         * Wohin der Schüler geht, wenn die Seite nicht mehr freigegeben ist
         * (AP-3.2).
         *
         * **Der „Verlassen"-Knopf der Navigationsleiste ist hierfür NICHT
         * benutzbar — anders als der AP-Text (Schritt 3) annimmt.** Er ist ein
         * `<button>` ohne `href`; sein Klick-Handler entfernt `classroom` und
         * `token` aus der AKTUELLEN Adresse und navigiert dorthin. Auf einer
         * gesperrten Seite ist das Ergebnis genau die 403-Hinweisseite, die
         * dieser Pfad vermeiden soll. Er scheidet damit aus, und der AP-Text
         * ist an dieser Stelle sachlich falsch (in der Übergabenotiz vermerkt).
         *
         * Stattdessen eine Kaskade aus drei Quellen, keine davon eine zweite
         * Fassung der Adressbildung:
         *
         * 1. **`document.referrer`**, sofern gleiche Herkunft und nicht die
         *    eigene Seite. Das ist die einzige Quelle, die die
         *    Klassen-Seitenliste (`[cbd_classroom]`-Seite) tatsächlich
         *    erreichen kann — dort kommt der Schüler nach dem Login her. Ihre
         *    Adresse steht nirgends sonst im Browser: Der Server liefert sie
         *    weder in `cbd_student_get_data` noch in `cbdClassroomPageData`,
         *    und `localStorage` hält nur Token und Klassen-ID.
         * 2. **Der erste Sitzungslink der Klassen-Seitenleiste** (`#sidebar`),
         *    ersatzweise der Klassen-Navigationsleiste
         *    (`#cbd-classroom-nav-header`) — siehe `sitzungsLinkAus()` und
         *    den Absatz „AP-3.fix1" unten.
         * 3. **Die Startseite.** Letzter Ausweg, und der einzige Weg dieser
         *    Kaskade, der `classroom` und `token` NICHT mitnimmt: Der Schüler
         *    verliert dort still seinen Klassenmodus und muss sich über die
         *    `[cbd_classroom]`-Seite neu anmelden. Deshalb wirklich nur
         *    Notausgang — jede neue Quelle gehört VOR diese Stufe, nicht
         *    dahinter.
         *
         * **AP-3.fix1: Stufe 2 wurde ersetzt (Befund F4 aus AP-3.3).** Die
         * ursprüngliche Fassung sah ausschließlich
         * `#cbd-classroom-nav-header a[href]` an. Diese Liste füllt
         * `buildNavUl()`, und die übernimmt einen Eintrag nur bei
         * `page.url && page.level === 0`. `ajax_student_get_data()` hängt eine
         * URL aber ausschließlich an Seiten MIT Freigaben, während die
         * Level-0-Seiten dieser Website reine Kapiteleltern mit `url: null`
         * sind — **die Kopfleiste bleibt damit strukturell leer**, im DOM der
         * lebenden Seite nachgemessen (AP-3.3: 0 Links; AP-3.fix1 auf einer
         * Level-0-Testseite: 1 Link, aber der zeigt auf die eigene Seite und
         * fällt durch die Pfadprüfung). Die Kaskade landete deshalb immer auf
         * Stufe 3, also auf der Startseite **ohne `classroom` und `token`**.
         *
         * Gemessen wurde stattdessen (AP-3.fix1, echte reduzierte Seite,
         * anonyme Schülersitzung): `#sidebar a[href]` liefert die von
         * `injectClassroomSidebar()` eingesetzte **vollständige** Hierarchie —
         * 2 Links, **beide** mit `classroom` und `token`, einer davon auf eine
         * andere Seite derselben Klasse. Die übrigen 10 Links der Seite
         * (Theme-Menü, Titel, Glossar, `wp-login.php`) tragen keine
         * Sitzungsparameter und sind als Ziel damit ungeeignet.
         *
         * **Der „Verlassen"-Knopf bleibt ausdrücklich kein Kandidat** (siehe
         * oben) — wer diese Stelle künftig umbaut, prüfe zuerst im DOM, welche
         * Links wirklich `classroom` und `token` führen, statt es anzunehmen.
         * Genau diese Annahme hat F4 erzeugt.
         *
         * @returns {string} Eine navigierbare Adresse, nie leer.
         */
        klassenlistenZiel: function() {
            var referrer = '';
            var url = null;
            var treffer = '';

            try {
                referrer = document.referrer || '';
            } catch (e) {
                referrer = '';
            }

            if (referrer) {
                try {
                    url = new URL(referrer, window.location.href);
                } catch (e) {
                    url = null;
                }

                // Fremde Herkunft käme als Umleitungsziel nicht in Frage;
                // die eigene Seite wäre eine Rückkehr in den 403.
                if (url
                    && url.origin === window.location.origin
                    && url.pathname !== window.location.pathname) {
                    return url.toString();
                }
            }

            // Stufe 2 (AP-3.fix1): erst die Seitenleiste — sie trägt die
            // vollständige Hierarchie und ist die im DOM nachgemessene, hier
            // tatsächlich gefüllte Quelle. Die Kopfleiste bleibt als zweiter
            // Versuch stehen: Sie kostet nichts und griffe auf einer
            // Seitenhierarchie, deren Level-0-Seiten eigene Freigaben haben.
            treffer = this.sitzungsLinkAus('#sidebar');

            if (!treffer) {
                treffer = this.sitzungsLinkAus('#cbd-classroom-nav-header');
            }

            if (treffer) {
                return treffer;
            }

            return window.location.origin + '/';
        },

        /**
         * Ersten brauchbaren Sitzungslink unterhalb von `wurzel` finden
         * (AP-3.fix1, Stufe 2 von `klassenlistenZiel()`).
         *
         * Brauchbar heißt: alle vier Bedingungen zugleich —
         *
         * 1. **gleiche Herkunft** wie die aktuelle Seite. Eine Umleitung auf
         *    eine fremde Herkunft wäre ein Sicherheitsbefund, nicht bloß ein
         *    falsches Ziel; die Prüfung ist zeichengleich mit der von Stufe 1.
         * 2. **anderer Pfad** als die aktuelle Seite — die eigene Seite ist
         *    gerade nicht mehr freigegeben, dorthin zurück wäre der 403, den
         *    dieser ganze Pfad vermeidet.
         * 3. `classroom` **und** `token` stehen in der Abfragezeichenfolge.
         *    Diese Adressen baut der Server in `ajax_student_get_data()` per
         *    `add_query_arg()`; es entsteht also **keine zweite Fassung der
         *    Adressbildung**. Ohne diese Bedingung könnte ein Link der
         *    gewöhnlichen Theme-Seitenleiste gewinnen — den setzt
         *    `injectClassroomSidebar()` nur dann durch die Klassen-Hierarchie,
         *    wenn `cbd_student_get_data` geantwortet hat. Der Schüler landete
         *    sonst ohne Sitzung auf einer Kapitelseite, also so schlecht wie
         *    auf der Startseite.
         * 4. Die Adresse muss überhaupt parsbar sein (`new URL` wirft sonst).
         *
         * @param {string} wurzel Selektor des Behälters, z. B. '#sidebar'.
         * @returns {string} Erste passende Adresse oder '' (keine gefunden).
         */
        sitzungsLinkAus: function(wurzel) {
            var treffer = '';

            $(wurzel + ' a[href]').each(function() {
                var kandidat;

                if (treffer) {
                    return;
                }

                try {
                    kandidat = new URL($(this).attr('href'), window.location.href);
                } catch (e) {
                    return;
                }

                if (kandidat.origin !== window.location.origin) {
                    return;
                }

                if (kandidat.pathname === window.location.pathname) {
                    return;
                }

                if (!kandidat.searchParams.get('classroom')
                    || !kandidat.searchParams.get('token')) {
                    return;
                }

                treffer = kandidat.toString();
            });

            return treffer;
        },

        /**
         * Nach dem Laden: Leseposition wiederherstellen, Anlass anzeigen und
         * — am wichtigsten — den Mindestabstand über den Ladevorgang hinweg
         * verankern (AP-3.1).
         *
         * **Der Eintrag wird BEDINGUNGSLOS entfernt**, bevor irgendetwas
         * geprüft wird: auch wenn er nicht zu dieser Seite gehört, auch wenn
         * das Parsen wirft, auch wenn er veraltet ist. Ein liegengebliebener
         * Eintrag darf niemals einen zweiten Ladevorgang beeinflussen — das ist
         * die einzige wirksame Vorkehrung gegen eine Seite, die sich immer
         * wieder selbst neu lädt.
         *
         * Läuft in `init()` VOR dem ersten Datenabruf und sogar vor der
         * Parameterprüfung.
         */
        stelleWiederaufnahmeHer: function() {
            var speicher = this.sitzungsSpeicher();
            var roh = null;
            var eintrag = null;
            var alter;
            var ziel;

            if (!speicher) {
                return;
            }

            try {
                roh = speicher.getItem(SPEICHER_SCHLUESSEL);
            } catch (e) {
                roh = null;
            }

            // Entfernen in JEDEM Fall, auch wenn das Lesen schon geworfen hat.
            try {
                speicher.removeItem(SPEICHER_SCHLUESSEL);
            } catch (e) {}

            if (!roh) {
                return;
            }

            try {
                eintrag = JSON.parse(roh);
            } catch (e) {
                eintrag = null;
            }

            if (!eintrag || typeof eintrag !== 'object') {
                return;
            }

            // Gehört der Eintrag zu genau dieser Seite in genau dieser Klasse?
            // Vergleich über String(), weil `seite` als Zahl und `klasse` als
            // String aus der URL kommt (JSON behält beide Typen).
            if (String(eintrag.seite) !== String(this.pageId)
                || String(eintrag.klasse) !== String(this.classroomId)) {
                window.cbdDebug && console.log('CBD Classroom Page Filter: Wiederaufnahme passt nicht zu dieser Seite/Klasse – verworfen.');
                return;
            }

            if (typeof eintrag.zeit !== 'number') {
                return;
            }

            alter = Date.now() - eintrag.zeit;
            if (alter < 0 || alter > MINDESTABSTAND_MS) {
                window.cbdDebug && console.log('CBD Classroom Page Filter: Wiederaufnahme veraltet (' + alter + ' ms) – verworfen.');
                return;
            }

            // DER ANKER GEGEN DIE ENDLOSSCHLEIFE: Ab hier weiß diese frisch
            // geladene Seite, wann zuletzt neu geladen wurde.
            this.letzteNeuladung = eintrag.zeit;

            this.zeigeWiederaufnahmeHinweis(eintrag.grund);

            ziel = (typeof eintrag.scrollY === 'number' && isFinite(eintrag.scrollY) && eintrag.scrollY > 0)
                ? eintrag.scrollY
                : 0;

            if (ziel > 0) {
                // Die reduzierte Seite ist serverseitig gerendert; im
                // Fußbereich steht der Inhalt bereits. Der zweite Versuch nach
                // 'load' fängt Bilder ab, die die Höhe noch verschieben.
                try { window.scrollTo(0, ziel); } catch (e) {}

                window.addEventListener('load', function() {
                    try { window.scrollTo(0, ziel); } catch (e) {}
                });
            }
        },

        /**
         * Unaufdringliche Leiste einblenden, die den Anlass des Neuladens nennt
         * (AP-3.1). Verschwindet nach HINWEIS_DAUER_MS von selbst.
         *
         * `role="status"` lässt Screenreader den Hinweis ansagen, ohne den
         * Lesefluss zu unterbrechen (höflichere Stufe als `role="alert"`).
         *
         * Die Leiste wird oben in den Inhaltsbereich gehängt (dieselbe
         * Einfügekaskade wie `showWarning()`), ist per CSS aber
         * `position: fixed` — **mit Absicht**: Nach dem Neuladen steht der
         * Schüler wieder an seiner alten Leseposition, unter Umständen
         * tausende Pixel weit unten. Eine im Textfluss stehende Leiste am
         * Anfang des Inhalts hätte er nie zu Gesicht bekommen. Nebeneffekt:
         * Weil sie aus dem Fluss genommen ist, verschiebt sie den Inhalt weder
         * beim Erscheinen noch beim Verschwinden — die gerade
         * wiederhergestellte Leseposition bleibt exakt stehen.
         *
         * @param {string} grund 'freigabe' oder 'tafel'.
         */
        zeigeWiederaufnahmeHinweis: function(grund) {
            var text = (grund === 'tafel') ? 'Tafelbild aktualisiert' : 'Neu freigegeben';
            var $hinweis;

            // Nie zwei Leisten übereinander.
            $('.cbd-live-hinweis').remove();

            $hinweis = $('<div class="cbd-live-hinweis" role="status"></div>').text(text);

            this.haengeHinweisEin($hinweis);

            window.setTimeout(function() {
                $hinweis.remove();
            }, HINWEIS_DAUER_MS);
        },

        /**
         * Eine Hinweisleiste oben in den Inhaltsbereich hängen (AP-3.2,
         * herausgezogen aus `zeigeWiederaufnahmeHinweis()`).
         *
         * Die Einfügekaskade `.entry-content` → `article` → `body` ist
         * dieselbe wie in `showWarning()`/`showError()` und steht seit dem
         * Herausziehen nur noch an EINER Stelle — `verlasseGesperrteSeite()`
         * benutzt sie mit.
         *
         * @param {Object} $hinweis Das einzuhängende jQuery-Element.
         */
        haengeHinweisEin: function($hinweis) {
            if ($('.entry-content').length > 0) {
                $('.entry-content').prepend($hinweis);
            } else if ($('article').length > 0) {
                $('article').prepend($hinweis);
            } else {
                $('body').prepend($hinweis);
            }
        },

        /**
         * Load classroom data for this specific page
         */
        loadClassroomData: function() {
            var self = this;

            $.post(cbdClassroomPageData.ajaxUrl, {
                action: 'cbd_get_page_classroom_data',
                token: this.token,
                page_id: this.pageId
            }, function(response) {
                if (response.success) {
                    window.cbdDebug && console.log('CBD Classroom Page Filter: Received data', response.data);
                    self.einmaligAufbauen(response.data);
                    self.filterContainers(response.data, true);
                } else {
                    console.error('CBD Classroom Page Filter: Error loading data', response.data.message);
                    // Show error to user
                    self.showError(response.data.message || 'Fehler beim Laden der Klassendaten.');
                }
            }).fail(function(xhr, status, error) {
                console.error('CBD Classroom Page Filter: Network error', error);
                self.showError('Netzwerk-Fehler beim Laden der Klassendaten.');
            });
        },

        /**
         * Klassendaten erneut holen und den Filter wiederholen (AP-2.1).
         *
         * Wird ausschließlich vom 'seite'-Rückruf des Taktgebers gerufen, also
         * nur dann, wenn sich die Freigabe-Signatur tatsächlich geändert hat.
         * Ruft WEDER `einmaligAufbauen()` NOCH `filterContainers(data, true)` –
         * die Navigationsleiste, die Link-Umleitung und die Warnung über
         * fehlende markierte Blöcke gehören zum Erstaufbau und dürfen sich
         * nicht wiederholen.
         *
         * Ein fehlgeschlagener Nachschlag bleibt bewusst STILL: Er ist kein
         * Grund, dem lesenden Schüler eine Fehlermeldung vor die Nase zu
         * setzen. Der nächste Takt versucht es ohnehin wieder.
         */
        aktualisiere: function() {
            var self = this;

            $.post(cbdClassroomPageData.ajaxUrl, {
                action: 'cbd_get_page_classroom_data',
                token: this.token,
                page_id: this.pageId
            }, function(response) {
                if (response && response.success) {
                    window.cbdDebug && console.log('CBD Classroom Page Filter: Aktualisierung erhalten', response.data);
                    self.filterContainers(response.data);
                } else {
                    window.cbdDebug && console.log('CBD Classroom Page Filter: Aktualisierung ohne Erfolg – still ignoriert.');
                }
            }).fail(function(xhr, status, error) {
                window.cbdDebug && console.log('CBD Classroom Page Filter: Aktualisierung fehlgeschlagen – still ignoriert.', error);
            });
        },

        /**
         * Tafelbilder aktualisieren, unabhängig von Freigaben (AP-2.2).
         *
         * Wird ausschließlich vom 'tafel'-Rückruf des Taktgebers gerufen –
         * also nur, wenn sich die Tafelbild-Signatur tatsächlich geändert
         * hat (irgendein Schreibvorgang an wp_cbd_drawings, nicht
         * notwendigerweise eine Freigabe). Ruft NICHT filterContainers():
         * Sichtbarkeit der Container ändert sich hier nicht, nur ihre
         * Tafelbild-Abschnitte.
         *
         * Geht alle aktuell SICHTBAREN Container durch (nur freigegebene
         * Container zeigen je einen Tafelbild-Abschnitt) und überlässt
         * baueTafelbild() die Entscheidung, ob aufgebaut, aktualisiert,
         * unverändert gelassen oder entfernt wird – auch wenn für einen
         * Container gar keine Zeichnungsdaten (mehr) vorliegen
         * (drawings[stableId] dann undefined).
         *
         * Ein fehlgeschlagener Nachschlag bleibt bewusst STILL, aus
         * demselben Grund wie bei aktualisiere().
         */
        aktualisiereTafelbilder: function() {
            var self = this;

            $.post(cbdClassroomPageData.ajaxUrl, {
                action: 'cbd_get_page_classroom_data',
                token: this.token,
                page_id: this.pageId
            }, function(response) {
                if (!response || !response.success) {
                    window.cbdDebug && console.log('CBD Classroom Page Filter: Tafelbild-Aktualisierung ohne Erfolg – still ignoriert.');
                    return;
                }

                window.cbdDebug && console.log('CBD Classroom Page Filter: Tafelbild-Aktualisierung erhalten', response.data);

                var drawings = response.data.drawings || {};

                $('[data-wp-interactive="container-block-designer"], [data-stable-id^="cbd-"]').each(function() {
                    var $container = $(this);
                    var stableId = $container.attr('data-stable-id');

                    // WARUM NICHT `$container.is(':visible')` (dieselbe Falle
                    // wie Befund B1 aus AP-2.rev, dort in filterContainers()
                    // behoben durch AP-2.fix1 – hier dieselbe Technik an der
                    // zweiten Fundstelle, AP-2.fix2): `:visible` prueft die
                    // gesamte VORFAHRENKETTE, nicht nur diesen Container. Ein
                    // freigegebener Container in einem zugeklappten Elternteil
                    // (`.cbd-container.cbd-collapsed .cbd-container-content
                    // { display: none }`, ebenso Accordion-Panels) galt dadurch
                    // als „nicht sichtbar" und wurde bei einer Tafelbild-
                    // Aenderung UEBERSPRUNGEN – dauerhaft, denn dieser Zweig
                    // laeuft nur bei einer Signaturaenderung, die dann bereits
                    // vorbei ist. Der Schueler sah ein veraltetes Tafelbild,
                    // sobald er den Elternteil spaeter aufklappte.
                    //
                    // Gefragt wird deshalb der EIGENE Zustand des Elements:
                    // Dieser Filter versteckt ausschliesslich per
                    // `$container.hide()`, und das setzt `style="display: none"`
                    // am Element selbst. Ein Container in einem zugeklappten
                    // Elternteil ist damit wieder ein ganz normaler Fall.
                    if (!stableId || $container[0].style.display === 'none') {
                        return;
                    }

                    self.baueTafelbild($container, drawings[stableId]);
                });
            }).fail(function(xhr, status, error) {
                window.cbdDebug && console.log('CBD Classroom Page Filter: Tafelbild-Aktualisierung fehlgeschlagen – still ignoriert.', error);
            });
        },

        /**
         * Alles, was GENAU EINMAL geschehen darf (AP-2.1).
         *
         * Navigationsleiste (mit "Verlassen"-Button) IMMER einfügen –
         * unabhängig davon, ob die Seite Container-Blöcke hat. Sonst säße der
         * Schüler auf container-losen Seiten in der Klasse fest (kein Ausgang).
         *
         * Wird ausschließlich aus `loadClassroomData()` gerufen, NIE aus
         * `aktualisiere()`. Andernfalls entstünde bei jeder Live-Aktualisierung
         * eine weitere Navigationsleiste bzw. ein weiterer Klick-Abfänger.
         */
        einmaligAufbauen: function(data) {
            this.className = data.class_name;
            this.injectClassroomNavBar(data.class_name);
            this.interceptLinks();
        },

        /**
         * Filter containers based on classroom data.
         *
         * Seit AP-2.1 beliebig oft aufrufbar: Die Methode enthält nur noch den
         * wiederholbaren Teil (Schleife über die Container). Der einmalige Teil
         * steht in `einmaligAufbauen()`.
         *
         * @param {Object}  data          Antwort von cbd_get_page_classroom_data.
         * @param {boolean} istErstaufbau Nur beim ersten Durchlauf true. Steuert
         *                                die einmalige Warnung über fehlende
         *                                markierte Blöcke und verhindert, dass
         *                                der Erstaufbau als „neu freigegeben"
         *                                gilt.
         */
        filterContainers: function(data, istErstaufbau) {
            var self = this;
            var treatedContainers = data.treated_containers || [];
            var drawings = data.drawings || {};

            istErstaufbau = !!istErstaufbau;

            window.cbdDebug && console.log('CBD Classroom Page Filter: Treated containers:', treatedContainers);
            window.cbdDebug && console.log('CBD Classroom Page Filter: Drawings:', Object.keys(drawings));

            // Find all container blocks on the page
            // Try multiple selectors to catch all containers
            var $containers = $('[data-wp-interactive="container-block-designer"], [data-stable-id^="cbd-"]');
            window.cbdDebug && console.log('CBD Classroom Page Filter: Found', $containers.length, 'container blocks');

            // DEBUG: Log all found container stable IDs
            var foundStableIds = [];
            $containers.each(function() {
                var stableId = $(this).attr('data-stable-id');
                if (stableId) {
                    foundStableIds.push(stableId);
                }
            });
            window.cbdDebug && console.log('CBD Classroom Page Filter: All stable IDs found in DOM:', foundStableIds);
            window.cbdDebug && console.log('CBD Classroom Page Filter: Treated containers from server:', treatedContainers);

            // Check for inconsistencies: containers in DB but not in DOM
            var missingContainers = [];
            treatedContainers.forEach(function(containerId) {
                if (foundStableIds.indexOf(containerId) === -1) {
                    missingContainers.push(containerId);
                }
            });

            // Auf einer serverseitig reduzierten Seite ergibt diese Warnung
            // keinen Sinn: Dort steht ohnehin nur, was freigegeben ist, und
            // freigegebene Container anderer Seiten fehlen naturgemäß. Der
            // Wert kommt aus CBD_Classroom::enqueue_frontend_assets().
            var istReduziert = this.istReduzierteSeite();

            // Seit AP-2.1 NUR beim Erstaufbau: Bei jeder Live-Aktualisierung
            // würde sich dieselbe Warnung sonst endlos wiederholen. Der
            // istReduziert-Vorbehalt bleibt davon unberührt bestehen.
            if (istErstaufbau && missingContainers.length > 0 && !istReduziert) {
                console.warn('CBD Classroom Page Filter: WARNING - ' + missingContainers.length + ' treated containers from DB not found in DOM (page was likely edited):', missingContainers);

                // Show warning but DON'T auto-cleanup - teacher might want to re-mark the blocks
                this.showWarning('Hinweis: Diese Seite wurde bearbeitet. ' + missingContainers.length +
                    ' markierte(r) Block/Blöcke wurde(n) auf der Seite nicht gefunden. ' +
                    'Die Markierungen bleiben in der Datenbank gespeichert, werden aber auf dieser Seite nicht angezeigt.');

                // DON'T call cleanupInvalidContainers() - markings should persist
            } else if (istErstaufbau && missingContainers.length > 0) {
                window.cbdDebug && console.log('CBD Classroom Page Filter: ' + missingContainers.length +
                    ' markierte Container fehlen im DOM - auf einer reduzierten Seite erwartet, keine Warnung.');
            }

            // Filter to only show containers that exist in BOTH DOM and DB
            var validTreatedContainers = treatedContainers.filter(function(containerId) {
                return foundStableIds.indexOf(containerId) !== -1;
            });

            window.cbdDebug && console.log('CBD Classroom Page Filter: Valid treated containers (intersection):', validTreatedContainers);

            if ($containers.length === 0) {
                window.cbdDebug && console.log('CBD Classroom Page Filter: No containers found on page');
                return;
            }

            // Hide all containers by default, then show only treated ones that exist in DOM
            //
            // Seit AP-2.1 wird der ZUSTANDSWECHSEL erkannt, statt blind zu
            // setzen: Nur ein Container, der vorher von DIESEM Filter versteckt
            // war und jetzt sichtbar wird, gilt als „neu freigegeben".
            //
            // WARUM NICHT `$container.is(':visible')` (Befund B1 aus AP-2.rev,
            // behoben in AP-2.fix1) — bitte nicht zurückstellen:
            // `:visible` ist falsch, sobald ein VORFAHRE versteckt ist, und auf
            // dieser Website versteckt der Server ohne jedes JavaScript:
            //   • `.cbd-container.cbd-collapsed .cbd-container-content
            //      { display: none }` (cbd-frontend-clean.css) – die Klasse
            //      `cbd-collapsed` setzt bereits der PHP-Renderer, wenn das
            //      Design „standardmäßig zugeklappt" trägt. Container INNERHALB
            //      eines zugeklappten Containers sind damit von Anfang an
            //      `:visible === false`;
            //   • dasselbe gilt für Container in einem Panel des Blocks
            //      `modular-blocks/accordion`.
            // Ein nicht freigegebener Container in einem zugeklappten Elternteil
            // galt dadurch als „ohnehin unsichtbar", bekam kein `.hide()` – und
            // erschien, sobald der Schüler den Elternteil aufklappte. Gemessen
            // auf Seite 1618/Klasse 15: 6 zugeklappte Container, 5 davon ohne
            // eigenes `display:none`, dazu vier falsche „Neu freigegeben"-
            // Hinweise bei jedem Puls.
            //
            // Gefragt wird deshalb der EIGENE Zustand des Elements: Dieser
            // Filter versteckt ausschliesslich per `$container.hide()`, und das
            // setzt `style="display: none"` am Element selbst. Ein Container in
            // einem zugeklappten Elternteil ist damit wieder ein ganz normaler
            // Fall. Nebenwirkung, die erwünscht ist: kein erzwungener
            // Layout-Durchlauf mehr pro Container (`:visible` liest Geometrie).
            var neuSichtbarAnzahl = 0;

            $containers.each(function() {
                var $container = $(this);
                var stableId = $container.attr('data-stable-id');
                var sollSichtbar = !!stableId && validTreatedContainers.indexOf(stableId) !== -1;
                var istVersteckt = $container[0].style.display === 'none';

                window.cbdDebug && console.log('CBD Classroom Page Filter: Processing container', stableId);

                if (!sollSichtbar) {
                    // Container is NOT treated OR doesn't exist in DB -> hide it
                    if (!istVersteckt) {
                        $container.hide();

                        // Behandelt-Kennzeichnung mit abräumen (Befund B4 aus
                        // AP-2.rev, behoben in AP-2.fix1): Das Abzeichen wurde
                        // beim Einblenden gesetzt, aber nie wieder entfernt.
                        // Nimmt die Lehrperson eine Freigabe zurück und erteilt
                        // sie später erneut, hinge sonst ein veraltetes
                        // „✓ Behandelt" am Container. `children()` statt
                        // `find()`: das eigene Abzeichen ist ein direktes Kind –
                        // Abzeichen VERSCHACHTELTER Container gehören denen und
                        // dürfen hier nicht mit verschwinden.
                        $container.children('.cbd-behandelt-badge').remove();
                        $container.removeClass('cbd-is-behandelt');

                        window.cbdDebug && console.log('CBD Classroom Page Filter: Hiding non-treated container', stableId);
                    }
                } else {
                    // Container IS treated AND exists in DOM -> show it and add drawings/badges
                    if (istVersteckt) {
                        $container.show();
                        neuSichtbarAnzahl++;
                        window.cbdDebug && console.log('CBD Classroom Page Filter: Showing treated container', stableId);

                        // Der Hinweis „neu freigegeben" gilt nur für echte
                        // Live-Freigaben, nicht für den Erstaufbau.
                        if (!istErstaufbau) {
                            self.markiereNeuFreigegeben($container);
                        }
                    }

                    // Add drawing and badge if available
                    if (drawings[stableId]) {
                        var drawing = drawings[stableId];

                        // Add "Behandelt" badge
                        if (drawing.is_behandelt) {
                            // Only add if not already present
                            if ($container.find('.cbd-behandelt-badge').length === 0) {
                                $container.prepend('<div class="cbd-behandelt-badge">✓ Behandelt</div>');
                                $container.addClass('cbd-is-behandelt');
                            }
                        }

                        // Add collapsible drawing section with optional page navigation
                        // Seit AP-2.2 in eine eigene Methode ausgelagert, die
                        // auch ein bereits vorhandenes Tafelbild aktualisieren
                        // kann (siehe baueTafelbild() weiter unten).
                        self.baueTafelbild($container, drawing);
                    }
                }
            });

            // Nav-Leiste + Link-Interception stehen seit AP-2.1 in
            // einmaligAufbauen() – hier bewusst nicht aufrufen.

            // Nachrüst-Haken (AP-2.1), Kommentar richtiggestellt in AP-2.fix1
            // (Befund B3 aus AP-2.rev):
            //
            // `cbdRenderLatex()` ist der eigentliche Grund für diesen Block. Ein
            // Container, der versteckt im DOM lag, konnte seine Formeln nicht
            // korrekt vermessen lassen – KaTeX hätte in einem
            // `display:none`-Teilbaum die falsche Ersatzschrift erwischt.
            // `whenFontsReady()` im Renderer macht den Aufruf hier berechtigt.
            //
            // `CBDRenumberBlocks()` steht daneben aus ANDEREM Grund, als der
            // ursprüngliche Kommentar behauptete: Er sagte, die Nummerierung
            // zähle nur sichtbare Container. Das ist widerlegt —
            // `renumberBlocks()` in assets/js/block-numbering.js filtert
            // ausschliesslich auf oberste Ebene
            // (`container.parentElement.closest('.cbd-container')`),
            // Sichtbarkeit kommt dort gar nicht vor. Der Aufruf ist damit im
            // Regelfall wirkungslos, aber idempotent und schadlos; er bleibt als
            // Absicherung stehen, falls sich die Nummerierung künftig doch am
            // Anzeigezustand orientiert.
            //
            // Beide typeof-Prüfungen sind PFLICHT: block-numbering.js und
            // latex-renderer.js werden nur eingereiht, wenn die jeweilige
            // Funktion auf der Seite überhaupt gebraucht wird.
            if (!istErstaufbau && neuSichtbarAnzahl > 0) {
                if (typeof window.CBDRenumberBlocks === 'function') {
                    window.CBDRenumberBlocks();
                }
                if (typeof window.cbdRenderLatex === 'function') {
                    window.cbdRenderLatex(document);
                }
            }
        },

        /**
         * Einen gerade live freigegebenen Container für 8 Sekunden markieren.
         *
         * Die Gestaltung der Klasse `cbd-neu-freigegeben` liefert AP-2.3.
         *
         * BEWUSST OHNE `scrollIntoView()` und ohne Fokuswechsel: Der Schüler
         * liest gerade – seine Scrollposition darf sich durch eine Freigabe
         * nicht verändern.
         */
        markiereNeuFreigegeben: function($container) {
            var laufenderZeitgeber = $container.data('cbdNeuFreigegebenZeitgeber');

            // Wird derselbe Container innerhalb der acht Sekunden erneut
            // freigegeben, beginnt die Frist von vorn statt sich zu stapeln.
            if (laufenderZeitgeber) {
                window.clearTimeout(laufenderZeitgeber);
            }

            $container.addClass('cbd-neu-freigegeben');

            $container.data('cbdNeuFreigegebenZeitgeber', window.setTimeout(function() {
                $container.removeClass('cbd-neu-freigegeben');
                $container.removeData('cbdNeuFreigegebenZeitgeber');
            }, 8000));
        },

        /**
         * Tafelbild-Abschnitt eines Containers aufbauen bzw. aktualisieren
         * (AP-2.2). Aus filterContainers() herausgezogen — der Erstaufbau
         * erzeugt weiterhin BYTEIDENTISCHES Markup zum Stand vor diesem
         * Arbeitspaket, das Herausziehen selbst ändert daran nichts.
         *
         * Vor AP-2.2 wurde ein bereits vorhandener Abschnitt übersprungen
         * (`$content.find('.cbd-class-drawing-section').length === 0`) —
         * ein geändertes Tafelbild wurde dadurch nie erneuert. Seit AP-2.2
         * wird ein vorhandener Abschnitt bei geänderten Zeichnungsdaten
         * entfernt und neu aufgebaut, bzw. bei fehlenden Zeichnungsdaten
         * ganz entfernt (Aufruf aus aktualisiereTafelbilder(), wenn eine
         * Zeichnung gelöscht wurde).
         *
         * Zwei Vorkehrungen gegen ein störendes Nachladen:
         * - Kennung (Länge der drawing_data-Zeichenkette je Tafelseite, mit
         *   '|' verbunden) in $container.data('cbd-tafel-kennung').
         *   Unverändert gegenüber dem letzten Aufbau -> nichts tun, das DOM
         *   bleibt unangetastet (kein Flackern, keine neue Bildanfrage).
         * - War die Überlagerung aufgeklappt, bleibt sie es nach dem
         *   Neuaufbau (Knopftext und Klasse werden mit wiederhergestellt).
         *
         * jQuery .data() erzeugt dabei KEIN data-*-Attribut im Markup —
         * die Kennung wirkt sich also nicht auf einen outerHTML-Vergleich
         * des Erstaufbaus aus.
         *
         * @param {jQuery}      $container Der Container (trägt data-stable-id).
         * @param {Object|undefined} drawing Eintrag aus data.drawings[stableId],
         *                                   oder undefined, wenn für diesen
         *                                   Container keine Zeichnungszeile
         *                                   (mehr) existiert.
         */
        baueTafelbild: function($container, drawing) {
            var $content = $container.find('.cbd-container-content').first();
            if ($content.length === 0) {
                return;
            }

            var $bestehendeSektion = $content.find('.cbd-class-drawing-section');

            // Nur anzeigen wenn mindestens eine Seite echte Zeichnungsdaten hat
            var hasPages = !!drawing && drawing.pages && Object.keys(drawing.pages).some(function(idx) {
                return drawing.pages[idx] && drawing.pages[idx].drawing_data;
            });
            var hasLegacy = !!drawing && !hasPages && drawing.drawing_data;

            if (!hasPages && !hasLegacy) {
                // Keine (mehr vorhandenen) Zeichnungsdaten: einen
                // bestehenden Abschnitt entfernen, sonst nichts tun.
                if ($bestehendeSektion.length > 0) {
                    $bestehendeSektion.remove();
                    $container.removeData('cbd-tafel-kennung');
                }
                return;
            }

            // Kennung der Zeichnungsdaten bilden (AP-2.2): Länge je Seite,
            // mit '|' verbunden. Unverändert gegenüber dem letzten Aufbau
            // -> nichts tun, sonst würde das Bild bei jedem Puls-Durchlauf
            // neu geladen (Flackern), obwohl sich nichts geändert hat.
            var kennungTeile = [];
            if (hasPages) {
                var kennungIndices = Object.keys(drawing.pages).map(Number).sort(function(a, b) { return a - b; }).filter(function(idx) {
                    return drawing.pages[idx] && drawing.pages[idx].drawing_data;
                });
                kennungIndices.forEach(function(idx) {
                    kennungTeile.push(String(drawing.pages[idx].drawing_data.length));
                });
            } else {
                kennungTeile.push(String((drawing.drawing_data || '').length));
            }
            var neueKennung = kennungTeile.join('|');

            if ($bestehendeSektion.length > 0 && $container.data('cbd-tafel-kennung') === neueKennung) {
                return; // unverändert – Abschnitt bleibt unangetastet
            }

            // War die Überlagerung aufgeklappt, bleibt sie es nach dem
            // Neuaufbau — sonst klappte dem Schüler das gerade betrachtete
            // Tafelbild bei jeder Aktualisierung zu.
            var warAufgeklappt = false;
            if ($bestehendeSektion.length > 0) {
                warAufgeklappt = $bestehendeSektion.find('.cbd-drawing-overlay').is(':visible');
                $bestehendeSektion.remove();
            }

            var $section = $('<div class="cbd-drawing-section cbd-class-drawing-section">');
            var $toggle = $('<button class="cbd-drawing-toggle">📋 Tafelbild anzeigen</button>');
            var $drawingOverlay = $('<div class="cbd-drawing-overlay" style="display: none;">');

            if (hasPages) {
                // Multi-page: IIFE für saubere Closure-Isolation
                // Nur Seiten mit tatsächlichen Zeichnungsdaten berücksichtigen
                var pageIndices = Object.keys(drawing.pages).map(Number).sort(function(a, b) { return a - b; }).filter(function(idx) {
                    return drawing.pages[idx] && drawing.pages[idx].drawing_data;
                });
                var totalDrawingPages = pageIndices.length;

                var $img = $('<img>').attr('alt', 'Tafel-Zeichnung').css('max-width', '100%');

                if (totalDrawingPages > 1) {
                    var $pageNav = $('<div class="cbd-drawing-page-nav">');
                    var $pagePrev = $('<button class="cbd-drawing-page-prev" disabled>◀</button>');
                    var $pageIndicator = $('<span class="cbd-drawing-page-indicator">1 / ' + totalDrawingPages + '</span>');
                    var $pageNext = $('<button class="cbd-drawing-page-next">▶</button>');
                    $pageNav.append($pagePrev, $pageIndicator, $pageNext);
                    $drawingOverlay.append($pageNav);

                    // IIFE: alle Variablen als Parameter übergeben → kein var-Hoisting-Problem
                    (function($imgEl, $prev, $next, $ind, pages, indices, total) {
                        var current = 0;

                        function showPage(idx) {
                            if (idx < 0 || idx >= total) return;
                            current = idx;
                            var pd = pages[indices[idx]];
                            $imgEl.attr('src', pd && pd.drawing_data ? pd.drawing_data : '');
                            $prev.prop('disabled', idx <= 0);
                            $next.prop('disabled', idx >= total - 1);
                            $ind.text((idx + 1) + ' / ' + total);
                        }

                        $prev.on('click', function(e) { e.stopPropagation(); showPage(current - 1); });
                        $next.on('click', function(e) { e.stopPropagation(); showPage(current + 1); });

                        showPage(0);
                    })($img, $pagePrev, $pageNext, $pageIndicator, drawing.pages, pageIndices, totalDrawingPages);
                } else {
                    // Einzelne Seite: nur Bild anzeigen
                    var pd0 = drawing.pages[pageIndices[0]];
                    $img.attr('src', pd0 && pd0.drawing_data ? pd0.drawing_data : '');
                }

                $drawingOverlay.append($img);
            } else {
                // Legacy: einzelne Zeichnung
                $drawingOverlay.append(
                    $('<img>').attr({
                        'src': drawing.drawing_data || '',
                        'alt': 'Tafel-Zeichnung'
                    }).css('max-width', '100%')
                );
            }

            $section.append($toggle, $drawingOverlay);
            $content.append($section);

            $toggle.on('click', function(e) {
                e.preventDefault();
                var willBeVisible = !$drawingOverlay.is(':visible');
                $drawingOverlay.slideToggle(300);
                $toggle.text(willBeVisible ? '📋 Tafelbild verbergen' : '📋 Tafelbild anzeigen');
                $toggle.toggleClass('cbd-drawing-toggle-active', willBeVisible);
            });

            if (warAufgeklappt) {
                // Kein slideToggle() hier: Dies ist eine automatische
                // Aktualisierung, keine Nutzerhandlung — eine Animation
                // wäre eine unerwartete Bewegung mitten im Lesen.
                $drawingOverlay.show();
                $toggle.text('📋 Tafelbild verbergen');
                $toggle.addClass('cbd-drawing-toggle-active');
            }

            $container.data('cbd-tafel-kennung', neueKennung);

            window.cbdDebug && console.log('CBD Classroom Page Filter: Tafelbild-Abschnitt aufgebaut/aktualisiert für', $container.attr('data-stable-id'));
        },

        /**
         * Add visual indicator that page is in classroom mode
         */
        addClassroomIndicator: function(className) {
            // Only add if not already present
            if ($('#cbd-classroom-mode-indicator').length > 0) {
                return;
            }

            var $indicator = $('<div id="cbd-classroom-mode-indicator">')
                .addClass('cbd-classroom-indicator')
                .html('<strong>Klassen-Modus:</strong> ' + this.escapeHtml(className));

            // Insert at top of content area
            if ($('.entry-content').length > 0) {
                $('.entry-content').prepend($indicator);
            } else if ($('article').length > 0) {
                $('article').prepend($indicator);
            } else {
                $('body').prepend($indicator);
            }
        },

        /**
         * Show error message to user
         */
        showError: function(message) {
            var $error = $('<div class="cbd-classroom-error">')
                .text(message);

            if ($('.entry-content').length > 0) {
                $('.entry-content').prepend($error);
            } else if ($('article').length > 0) {
                $('article').prepend($error);
            } else {
                $('body').prepend($error);
            }
        },

        /**
         * Show warning message to user
         */
        showWarning: function(message) {
            // Only show if not already present
            if ($('#cbd-classroom-warning').length > 0) {
                return;
            }

            var $warning = $('<div id="cbd-classroom-warning">')
                .addClass('cbd-classroom-warning')
                .html('<strong>⚠️ Hinweis:</strong> ' + this.escapeHtml(message));

            if ($('.entry-content').length > 0) {
                $('.entry-content').prepend($warning);
            } else if ($('article').length > 0) {
                $('article').prepend($warning);
            } else {
                $('body').prepend($warning);
            }
        },

        /**
         * Cleanup invalid container references in database
         */
        cleanupInvalidContainers: function(invalidContainers) {
            var self = this;

            window.cbdDebug && console.log('CBD Classroom Page Filter: Cleaning up', invalidContainers.length, 'invalid containers');

            $.post(cbdClassroomPageData.ajaxUrl, {
                action: 'cbd_cleanup_invalid_containers',
                token: this.token,
                page_id: this.pageId,
                invalid_containers: invalidContainers
            }, function(response) {
                if (response.success) {
                    window.cbdDebug && console.log('CBD Classroom Page Filter: Cleanup successful -', response.data.message);
                    window.cbdDebug && console.log('CBD Classroom Page Filter: Remaining treated containers:', response.data.remaining_count);

                    if (response.data.remaining_count === 0) {
                        // No treated containers left - page should not be in TOC anymore
                        self.showError('Diese Seite wurde bearbeitet und hat keine behandelten Blöcke mehr. ' +
                            'Bitte kehren Sie zum Inhaltsverzeichnis zurück. ' +
                            '<a href="javascript:history.back();" style="color: #d32f2f; text-decoration: underline;">Zurück</a>');
                    } else {
                        // Some containers remain
                        self.showWarning('Diese Seite wurde bearbeitet. ' + response.data.deleted_count +
                            ' veraltete Container-Referenz(en) wurden automatisch entfernt. ' +
                            response.data.remaining_count + ' behandelte(r) Block/Blöcke verbleiben.');
                    }
                } else {
                    console.error('CBD Classroom Page Filter: Cleanup failed -', response.data.message);
                }
            }).fail(function(xhr, status, error) {
                console.error('CBD Classroom Page Filter: Cleanup network error', error);
            });
        },

        /**
         * Eigene Classroom-Navigationsleiste injizieren.
         * Zeigt nur Seiten mit behandelten Blöcken (Daten von cbd_student_get_data).
         * Die URLs kommen vom Server und enthalten bereits ?classroom=&token=.
         */
        injectClassroomNavBar: function(className) {
            if ($('#cbd-classroom-nav-header').length > 0) {
                return; // Bereits vorhanden
            }

            var self = this;

            // ---- Navigations-<nav> mit Ladeindikator ----
            var $nav = $('<nav class="cbd-classroom-main-nav" aria-label="Klassenmodus Navigation">');
            var $navUl = $('<ul class="cbd-classroom-nav-loading"><li>…</li></ul>');
            $nav.append($navUl);

            // ---- Verlassen-Button ----
            var $leaveBtn = $('<button class="cbd-classroom-nav-leave">✕ Verlassen</button>');
            $leaveBtn.on('click', function() {
                try {
                    localStorage.removeItem('cbd_classroom_token');
                    localStorage.removeItem('cbd_classroom_id');
                } catch (e) {}
                var url = new URL(window.location.href);
                url.searchParams.delete('classroom');
                url.searchParams.delete('token');
                window.location.href = url.toString();
            });

            // ---- Mobiler Hamburger-Button ----
            var $menuToggle = $('<button class="cbd-classroom-menu-toggle" aria-label="Menü öffnen">☰</button>');
            $menuToggle.on('click', function() {
                $nav.toggleClass('active');
                $menuToggle.attr('aria-expanded', $nav.hasClass('active'));
            });

            // ---- Aufbau ----
            var $left = $('<div class="cbd-classroom-nav-left">')
                .append('<span class="cbd-classroom-nav-badge">📚 Klassen-Modus</span>')
                .append('<span class="cbd-classroom-nav-name">' + self.escapeHtml(className) + '</span>');

            var $center = $('<div class="cbd-classroom-nav-center">').append($nav);
            var $right  = $('<div class="cbd-classroom-nav-right">').append($menuToggle).append($leaveBtn);

            var $content = $('<div class="cbd-classroom-nav-content container">')
                .append($left).append($center).append($right);

            var $header = $('<header id="cbd-classroom-nav-header" class="cbd-classroom-nav-header">')
                .append($content);

            // Klick außerhalb schließt mobiles Menü
            // (siehe interceptLinks(): eigener Namensraum, vor dem Binden
            // abgeworfen, damit kein zweiter Handler entstehen kann)
            $(document).off('click.cbdClassroomNav');
            $(document).on('click.cbdClassroomNav', function(e) {
                if (!$header.is(e.target) && $header.has(e.target).length === 0) {
                    $nav.removeClass('active');
                }
            });

            // Normale Site-Header ausblenden, Classroom-Nav einfügen
            var $siteHeader = $('.site-header').first();
            if ($siteHeader.length) {
                $siteHeader.before($header);
                $siteHeader.hide();
            } else {
                $('body').prepend($header);
            }

            // ---- Behandelte Seiten laden, Header-Nav + Sidebar befüllen ----
            $.post(cbdClassroomPageData.ajaxUrl, {
                action: 'cbd_student_get_data',
                token: this.token
            }, function(response) {
                if (response.success && response.data.pages) {
                    var pages = response.data.pages;
                    // Header: nur Level-0-Hauptseiten
                    var $builtUl = self.buildNavUl(pages);
                    $navUl.replaceWith($builtUl);
                    // Sidebar: vollständige Hierarchie
                    self.injectClassroomSidebar(pages, response.data.class_name);
                } else {
                    $navUl.empty();
                }
            }).fail(function() {
                $navUl.empty();
            });
        },

        /**
         * Baut eine flache <ul> für die Header-Navigationsleiste.
         * Zeigt nur Hauptseiten (level === 0) mit URL (behandelte Seiten).
         */
        buildNavUl: function(pages) {
            var currentPath = window.location.pathname;
            var $rootUl     = $('<ul>');

            pages.forEach(function(item) {
                if (item.type !== 'page' || !item.page) return;
                var page = item.page;

                // Nur Level-0-Seiten mit URL für den Header
                if (!page.url || (page.level || 0) !== 0) return;

                var isActive = false;
                try { isActive = new URL(page.url).pathname === currentPath; } catch (e) {}

                var $li = $('<li>');
                if (isActive) $li.addClass('current-menu-item');
                $li.append($('<a>').attr('href', page.url).text(page.title));
                $rootUl.append($li);
            });

            return $rootUl;
        },

        /**
         * Ersetzt den Inhalt der Theme-Sidebar mit der hierarchischen
         * Seitenliste des Klassenmodus. Verwendet die Theme-CSS-Klassen
         * (page-tree, page-item, page-link, etc.) für einheitliches Styling.
         * Die Öffnen/Schließen-Logik des Themes bleibt unverändert.
         */
        injectClassroomSidebar: function(pages, className) {
            var $sidebar = $('#sidebar');
            if ($sidebar.length === 0) return;

            var self        = this;
            var currentPath = window.location.pathname;

            // Sidebar-Titel aktualisieren
            $sidebar.find('.sidebar-title').text('Inhaltsverzeichnis');

            var $nav = $sidebar.find('.sidebar-navigation');
            $nav.empty();

            // Abschnitts-Überschrift mit Klassenname
            $nav.append(
                $('<div class="sidebar-section-title">').text('📚 ' + (className || 'Klassen-Modus'))
            );

            // Fragenwand-Einstieg ganz oben in der Liste (Hotfix „Fragenwand
            // in Klassenlisten"). Diese Methode ersetzt den Inhalt der
            // Theme-Seitenleiste vollständig; der PHP-Einhänger
            // CBD_Fragenwand::page_index_eintrag() (AP-4.2) bedient nur den
            // Block fos/inhaltsverzeichnis und greift hier nicht.
            //
            // Markup zeichengleich zum PHP-Vorbild: dieselbe Trigger-Klasse
            // cbd-fragenwand-verweis (der delegierte Klick-Listener in
            // assets/js/fragenwand-frontend.js hängt an document und fängt
            // jedes Element damit ab) und dieselben Gestaltungsklassen aus
            // assets/css/fragenwand.css, Abschnitt „INHALTSVERZEICHNIS-EINTRAG".
            //
            // BEWUSST OHNE data-classroom/data-token — anders als in
            // classroom-frontend.js: Diese Datei läuft ausschließlich auf
            // Seiten, deren Adresse ?classroom=&token= trägt (init() steigt
            // ohne beide Parameter aus, siehe oben). Damit greift der
            // Standardweg von fragenwand-frontend.js, das die
            // Abfragezeichenfolge der Seite unverändert weiterreicht — und
            // die Frage, welche Parameter eine Sitzung ausmachen, bleibt an
            // genau einer Stelle beantwortet (CBD_Classroom_Gate::sitzung()).
            $nav.append(
                $('<div class="cbd-classroom-fragenwand page-index__zusatz page-index__zusatz--fragenwand">').append(
                    $('<button type="button" class="cbd-fragenwand-verweis page-index__fragenwand-link">')
                        .text('Fragenwand öffnen')
                )
            );

            // Hierarchischen Baum aufbauen
            var $rootUl     = $('<ul class="page-tree">');
            var levelUls    = [$rootUl];
            var levelLastLi = [null];

            pages.forEach(function(item) {
                if (item.type !== 'page' || !item.page) return;
                var page  = item.page;
                var level = page.level || 0;

                // Stack anpassen wenn Ebene steigt oder sinkt
                if (level < levelUls.length - 1) {
                    levelUls.length    = level + 1;
                    levelLastLi.length = level + 1;
                }
                while (levelUls.length <= level) {
                    var $parentLi = levelLastLi[levelUls.length - 1];
                    if (!$parentLi) break;
                    var $sub = $('<ul class="page-tree-children">');
                    $parentLi.append($sub);
                    levelUls.push($sub);
                    levelLastLi.push(null);
                }

                var $targetUl = levelUls[Math.min(level, levelUls.length - 1)];
                var isActive  = false;
                try { isActive = page.url && new URL(page.url).pathname === currentPath; } catch (e) {}

                // Feldname ist page_id (nicht id) – siehe Antwort von
                // cbd_student_get_data, gemessen im Live-Test von AP-1.3b.
                var $li = $('<li class="page-item">').attr('data-page-id', String(page.page_id));
                if (isActive) $li.addClass('current-page expanded');

                if (page.url) {
                    $li.append(
                        $('<a class="page-link">').attr('href', page.url)
                            .append($('<span class="page-title">').text(page.title))
                    );
                } else {
                    // Elternseite ohne eigene behandelte Blöcke: nicht klickbar, gedimmt
                    $li.addClass('cbd-sidebar-parent-only')
                       .append(
                           $('<span class="page-link cbd-sidebar-no-link">')
                               .append($('<span class="page-title">').text(page.title))
                       );
                }

                $targetUl.append($li);
                levelLastLi[Math.min(level, levelLastLi.length - 1)] = $li;
            });

            // Toggle-Buttons zu Einträgen mit Kindern hinzufügen.
            // Standardzustand aufgeklappt – AUSSER die Seiten-ID steht in der
            // gespeicherten Collapsed-Liste (localStorage, siehe Hilfsfunktionen
            // oben).
            var collapsedIds = cbdKlassenverzeichnisGeleseneCollapsedIds();
            $rootUl.find('.page-item').each(function() {
                if ($(this).children('ul').length > 0) {
                    $(this).addClass('has-children');
                    var pageId = String($(this).attr('data-page-id'));
                    if (collapsedIds.indexOf(pageId) === -1) {
                        $(this).addClass('expanded');
                    }
                    $(this).prepend(
                        $('<button class="page-toggle" aria-label="Unterseiten anzeigen/verbergen">')
                            .append('<span class="toggle-icon">▸</span>')
                    );
                }
            });

            $nav.append($rootUl);

            // Event-Delegation für Toggle-Buttons (Theme-JS läuft vor dem AJAX-Ergebnis)
            $nav.on('click', '.page-toggle', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var $item = $(this).closest('.page-item');
                $item.toggleClass('expanded');
                var nunExpanded = $item.hasClass('expanded');
                $(this).attr('aria-expanded', nunExpanded);

                // Collapsed-Liste (localStorage) synchron nachziehen.
                var pageId = String($item.attr('data-page-id'));
                var ids = cbdKlassenverzeichnisGeleseneCollapsedIds();
                var pos = ids.indexOf(pageId);
                if (nunExpanded) {
                    if (pos !== -1) { ids.splice(pos, 1); }
                } else {
                    if (pos === -1) { ids.push(pageId); }
                }
                cbdKlassenverzeichnisSchreibeCollapsedIds(ids);
            });
        },

        /**
         * Alle internen Link-Klicks auf der Seite abfangen und Classroom-Parameter
         * automatisch anhängen, damit der Klassenmodus beim Navigieren erhalten bleibt.
         */
        interceptLinks: function() {
            var classroomId = this.classroomId;
            var token = this.token;
            var siteHostname = window.location.hostname;

            // Gürtel und Hosenträger (AP-2.1): Diese Methode wird seit AP-2.1
            // ausschließlich aus einmaligAufbauen() gerufen, läuft also nur
            // einmal. Der Abwurf des eigenen Namensraums stellt aber sicher,
            // dass auch ein versehentlicher zweiter Aufruf keinen zweiten
            // Klick-Abfänger hinterlässt (jeder Klick würde sonst mehrfach
            // umgeleitet).
            $(document).off('click.cbdClassroomLinks');

            $(document).on('click.cbdClassroomLinks', 'a[href]', function(e) {
                var href = $(this).attr('href');
                if (!href || href.charAt(0) === '#') return;
                try {
                    var url = new URL(href, window.location.href);
                    if (url.hostname !== siteHostname) return;        // externer Link
                    if (url.searchParams.get('classroom')) return;     // schon gesetzt
                    e.preventDefault();
                    url.searchParams.set('classroom', classroomId);
                    url.searchParams.set('token', token);
                    window.location.href = url.toString();
                } catch (e) { /* ungültige URL – ignorieren */ }
            });
        },

        /**
         * Escape HTML to prevent XSS
         */
        escapeHtml: function(text) {
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(text));
            return div.innerHTML;
        }
    };

    // Initialize on DOM ready
    $(document).ready(function() {
        ClassroomPageFilter.init();
    });

})(jQuery);
