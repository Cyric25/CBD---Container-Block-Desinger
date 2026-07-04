/**
 * Container Block Designer - Automatische Block-Wiederherstellung
 *
 * Nach Plugin-Updates kann sich das gespeicherte Markup der Container-Blöcke
 * ändern. Gutenberg markiert diese Blöcke dann als ungültig ("Dieser Block
 * enthält unerwartete oder ungültige Inhalte") und der Benutzer muss jeden
 * Block manuell über "Blockwiederherstellung versuchen" reparieren.
 *
 * Dieses Skript führt diese Wiederherstellung automatisch beim Öffnen des
 * Editors aus - für alle ungültigen container-block-designer-Blöcke,
 * inklusive verschachtelter Blöcke.
 *
 * Datei: assets/js/block-recovery.js
 */

(function(wp) {
    'use strict';

    if (!wp || !wp.data || !wp.blocks || !wp.domReady) {
        return;
    }

    var NAMESPACE = 'container-block-designer/';
    var hasRun = false;

    /**
     * Prüft, ob ein Block (oder einer seiner Kind-Blöcke) ein ungültiger
     * Container-Block ist.
     */
    function subtreeNeedsRecovery(block) {
        if (block.isValid === false && block.name && block.name.indexOf(NAMESPACE) === 0) {
            return true;
        }
        return (block.innerBlocks || []).some(subtreeNeedsRecovery);
    }

    /**
     * Baut einen Block-Teilbaum frisch aus den Attributen neu auf.
     * Entspricht dem, was Gutenbergs "Blockwiederherstellung versuchen" tut:
     * createBlock(name, attributes, innerBlocks) erzeugt einen garantiert
     * gültigen Block, dessen Markup beim Speichern neu generiert wird.
     */
    function recreateTree(block, counter) {
        var inner = (block.innerBlocks || []).map(function(child) {
            return recreateTree(child, counter);
        });
        if (block.isValid === false && block.name.indexOf(NAMESPACE) === 0) {
            counter.count++;
        }
        return wp.blocks.createBlock(block.name, block.attributes, inner);
    }

    function runRecovery() {
        if (hasRun) {
            return;
        }
        hasRun = true;

        var blockEditor = wp.data.select('core/block-editor');
        if (!blockEditor) {
            return;
        }

        var counter = { count: 0 };
        var topLevelBlocks = blockEditor.getBlocks();

        topLevelBlocks.forEach(function(block) {
            if (!subtreeNeedsRecovery(block)) {
                return;
            }
            var recovered = recreateTree(block, counter);
            wp.data.dispatch('core/block-editor').replaceBlock(block.clientId, recovered);
        });

        if (counter.count > 0) {
            window.cbdDebug && console.log('CBD Block-Recovery: ' + counter.count + ' Container-Block/Blöcke automatisch repariert');

            if (wp.data.dispatch('core/notices')) {
                var message = (counter.count > 1)
                    ? counter.count + ' Container-Blöcke wurden nach dem Plugin-Update automatisch repariert.'
                    : 'Ein Container-Block wurde nach dem Plugin-Update automatisch repariert.';
                wp.data.dispatch('core/notices').createNotice(
                    'info',
                    message + ' Bitte die Seite aktualisieren/speichern, um die Reparatur dauerhaft zu übernehmen.',
                    { isDismissible: true, id: 'cbd-block-recovery-notice' }
                );
            }
        }
    }

    wp.domReady(function() {
        // Nur im Post-/Seiten-Editor ausführen
        var coreEditor = wp.data.select('core/editor');
        if (!coreEditor) {
            return;
        }

        // Warten bis der Editor den Inhalt fertig geparst hat
        var unsubscribe = wp.data.subscribe(function() {
            var isReady = true;
            if (typeof coreEditor.__unstableIsEditorReady === 'function') {
                isReady = coreEditor.__unstableIsEditorReady();
            }
            if (!isReady || hasRun) {
                return;
            }
            unsubscribe();
            // Kleiner Puffer, damit Meta-Boxen/Reusable Blocks fertig laden
            setTimeout(runRecovery, 300);
        });

        // Fallback: Falls __unstableIsEditorReady nie true meldet (ältere
        // WP-Versionen), nach 3 Sekunden trotzdem einmalig ausführen
        setTimeout(function() {
            if (!hasRun) {
                try { unsubscribe(); } catch (e) {}
                runRecovery();
            }
        }, 3000);
    });

})(window.wp);
