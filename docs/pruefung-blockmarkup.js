/**
 * Container Block Designer – Blockmarkup einer bestehenden Seite auslesen
 *
 * Liefert die „Grundwahrheit" für den Block-Serializer (siehe
 * docs/PLAN-Seitenimport.md, AP-1.2): das Markup, das der Gutenberg-Editor
 * beim Speichern tatsächlich schreibt.
 *
 * WARUM NICHT EINFACH IN DIE DATENBANK SCHAUEN?
 * Weil in der Datenbank stehen kann, was eine ältere Plugin-Fassung dort
 * hinterlassen hat. `getEditedPostContent()` serialisiert dagegen den
 * aktuellen Blockzustand mit den heutigen `save()`-Funktionen — das ist genau
 * das Markup, das der Serializer nachbilden muss.
 *
 * ANWENDUNG
 *   1. Eine bestehende Seite im Blockeditor öffnen, die Container-Blöcke
 *      enthält — am besten eine typische Skriptseite mit Überschriften,
 *      Absätzen, Liste und Tabelle. Nichts ändern, nichts speichern.
 *   2. Entwicklerwerkzeuge öffnen (F12) → Reiter „Konsole".
 *   3. Diese Datei vollständig hineinkopieren und Enter drücken.
 *   4. Der Inhalt landet in der Zwischenablage. Zurückschicken.
 *
 * Das Skript ist ausschließlich LESEND. Es ändert den Beitrag nicht und
 * speichert nichts — es liest nur den Zustand, den der Editor ohnehin im
 * Speicher hält.
 *
 * @package ContainerBlockDesigner
 */

(async function () {
    'use strict';

    // ----------------------------------------------------------------
    // Voraussetzungen
    // ----------------------------------------------------------------
    if (!window.wp || !wp.data || !wp.data.select('core/block-editor')) {
        console.error(
            '%cAbbruch: Das Skript muss im Blockeditor laufen.',
            'color:#b32d0f;font-weight:bold'
        );
        console.info('Bitte eine Seite zum Bearbeiten öffnen und erneut einfügen.');
        return;
    }

    const editor = wp.data.select('core/editor');
    const blockEditor = wp.data.select('core/block-editor');

    // ----------------------------------------------------------------
    // Blöcke einsammeln (rekursiv, damit auch verschachtelte gezählt werden)
    // ----------------------------------------------------------------
    const zaehler = {};
    const ungueltige = [];

    function durchlaufe(bloecke, tiefe) {
        bloecke.forEach((block) => {
            const name = block.name || '(unbenannt)';
            zaehler[name] = (zaehler[name] || 0) + 1;

            // isValid === false heißt: Der Editor hält das gespeicherte Markup
            // für unvereinbar mit der heutigen save()-Ausgabe. Solche Blöcke
            // taugen NICHT als Vorlage.
            if (block.isValid === false) {
                ungueltige.push('  ' + '  '.repeat(tiefe) + name +
                    (block.attributes && block.attributes.blockTitle
                        ? ' („' + block.attributes.blockTitle + '")'
                        : ''));
            }

            if (block.innerBlocks && block.innerBlocks.length) {
                durchlaufe(block.innerBlocks, tiefe + 1);
            }
        });
    }

    durchlaufe(blockEditor.getBlocks(), 0);

    // ----------------------------------------------------------------
    // Serialisieren – das ist die eigentliche Grundwahrheit
    // ----------------------------------------------------------------
    const inhalt = editor.getEditedPostContent();

    const titel = editor.getEditedPostAttribute('title') || '(ohne Titel)';
    const id = editor.getCurrentPostId();

    // ----------------------------------------------------------------
    // Kopfzeilen für die Fixture
    // ----------------------------------------------------------------
    let kopf = '';
    kopf += '=== Seite ===============================================\n';
    kopf += 'Titel        : ' + titel + '\n';
    kopf += 'Post-ID      : ' + id + '\n';
    kopf += 'Adresse      : ' + window.location.origin + '\n';
    kopf += 'Zeichen      : ' + inhalt.length + '\n';
    kopf += '\n=== Enthaltene Blocktypen ===============================\n';

    Object.keys(zaehler)
        .sort()
        .forEach((name) => {
            kopf += '  ' + name.padEnd(44, ' ') + zaehler[name] + '\n';
        });

    if (ungueltige.length) {
        kopf += '\n=== ACHTUNG: ungültige Blöcke ===========================\n';
        kopf += 'Diese Blöcke gelten dem Editor als ungültig. Ihr Markup ist\n';
        kopf += 'als Vorlage unbrauchbar — bitte eine andere Seite wählen\n';
        kopf += 'oder das hier melden:\n';
        kopf += ungueltige.join('\n') + '\n';
    } else {
        kopf += '\n(keine ungültigen Blöcke — das Markup ist als Vorlage brauchbar)\n';
    }

    kopf += '\n=== post_content (unverändert) ==========================\n';

    const block = kopf + inhalt + '\n=== Ende ================================================';

    // ----------------------------------------------------------------
    // Ausgabe
    // ----------------------------------------------------------------
    console.log('%c' + kopf, 'font-family:monospace;font-size:12px');
    console.log('%c(Der Inhalt selbst steht in der Zwischenablage — er ist zu lang für die Konsole.)',
        'color:#666');

    if (ungueltige.length) {
        console.warn('Es gibt ungültige Blöcke auf dieser Seite, siehe oben.');
    }

    try {
        await navigator.clipboard.writeText(block);
        console.log('%cIn die Zwischenablage kopiert – einfach einfügen.',
            'color:#2a7d2a;font-weight:bold');
    } catch (fehler) {
        console.log('%cZwischenablage nicht freigegeben (' + fehler.message +
            '). Ersatzweise steht der vollständige Text jetzt als window.cbdMarkup bereit — ' +
            'mit copy(window.cbdMarkup) kopieren.', 'color:#8a6d00');
        window.cbdMarkup = block;
    }
})();
