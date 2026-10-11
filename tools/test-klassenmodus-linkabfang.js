/* Vorbereitung der Handtestseite test-klassenmodus-linkabfang.html (laeuft VOR view.js und classroom-page-filter.js). */
window.cbdBlockReference = {
	restUrl: location.origin + '/wp-json/cbd/v1/block-html',
	nonce: '',
	texte: { nichtVerfuegbar: 'Dieser Block ist nicht verfuegbar.', seiteOeffnen: 'Seite öffnen' }
};
window.cbdClassroomPageData = { pageId: 1, ajaxUrl: '/ajax-gemockt', hatTafelbilder: '1' };
window.cbdLog = [];
document.addEventListener('DOMContentLoaded', function () {
	// AJAX des Klassenmodus mocken, damit einmaligAufbauen() laeuft.
	jQuery.post = function (url, data, cb) {
		setTimeout(function () {
			cb({ success: true, data: { class_name: 'Testklasse', pages: {}, freigegeben: [], drawings: {} } });
		}, 0);
		return { fail: function () { return this; } };
	};
});
// Protokoll NACH allen document-Handlern (Bubble-Phase am window).
window.addEventListener('click', function (e) {
	var a = e.target && e.target.closest ? e.target.closest('a') : null;
	try {
		sessionStorage.setItem('cbdLog', JSON.stringify({
			id: a ? a.id : null, defaultPrevented: e.defaultPrevented, href: location.href, zeit: Date.now()
		}));
	} catch (x) { /* ignorieren */ }
}, false);
