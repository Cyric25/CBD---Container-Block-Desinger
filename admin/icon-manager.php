<?php
/**
 * Admin-Seite: Icons verwalten
 *
 * Upload und Übersicht der eigenen SVG-Kacheln. Die Verarbeitung steckt in
 * CBD_Icon_Manager; hier ist ausschließlich Darstellung.
 *
 * @package ContainerBlockDesigner
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can(CBD_Icon_Manager::CAPABILITY)) {
    wp_die(esc_html__('Du hast keine Berechtigung, diese Seite zu sehen.', 'container-block-designer'));
}

$cbd_index    = CBD_Icon_Library::get_index();
$cbd_groups   = CBD_Icon_Library::GROUPS;
$cbd_override = CBD_Icon_Library::get_override_dir();

// Rückmeldungen der letzten Aktion
$cbd_report = get_transient(CBD_Icon_Manager::report_key());
if ($cbd_report) {
    delete_transient(CBD_Icon_Manager::report_key());
}

$cbd_errors = array(
    'group'    => __('Unbekannte Gruppe.', 'container-block-designer'),
    'nofile'   => __('Es wurde keine Datei ausgewählt.', 'container-block-designer'),
    'dir'      => __('Das Upload-Verzeichnis ist nicht beschreibbar. Prüfe die Rechte auf wp-content/uploads/.', 'container-block-designer'),
    'toomany'  => __('Zu viele Dateien auf einmal.', 'container-block-designer'),
    'value'    => __('Ungültiges Icon.', 'container-block-designer'),
    'notfound' => __('Das Icon wurde nicht gefunden.', 'container-block-designer'),
    'delete'   => __('Das Icon konnte nicht gelöscht werden.', 'container-block-designer'),
);
?>
<div class="wrap cbd-icon-manager">
    <h1><?php esc_html_e('Icons verwalten', 'container-block-designer'); ?></h1>

    <?php if (isset($_GET['cbd_error']) && isset($cbd_errors[$_GET['cbd_error']])) : ?>
        <div class="notice notice-error"><p><?php echo esc_html($cbd_errors[sanitize_key($_GET['cbd_error'])]); ?></p></div>
    <?php endif; ?>

    <?php if (isset($_GET['cbd_deleted'])) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('Icon gelöscht. Falls ein gleichnamiges Plugin-Icon existiert, gilt jetzt wieder dieses.', 'container-block-designer'); ?></p>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['cbd_flushed'])) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('Zwischenspeicher geleert.', 'container-block-designer'); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($cbd_report) : ?>
        <?php if ($cbd_report['saved'] > 0) : ?>
            <div class="notice notice-success is-dismissible">
                <p><?php
                    printf(
                        esc_html(_n('%d Icon gespeichert.', '%d Icons gespeichert.', $cbd_report['saved'], 'container-block-designer')),
                        (int) $cbd_report['saved']
                    );
                ?></p>
            </div>
        <?php endif; ?>

        <?php if (!empty($cbd_report['cleaned'])) : ?>
            <div class="notice notice-warning">
                <p><strong><?php esc_html_e('Aus folgenden Dateien wurden unsichere Bestandteile entfernt:', 'container-block-designer'); ?></strong></p>
                <ul style="list-style:disc;margin-left:20px;">
                    <?php foreach ($cbd_report['cleaned'] as $line) : ?>
                        <li><?php echo esc_html($line); ?></li>
                    <?php endforeach; ?>
                </ul>
                <p class="description"><?php esc_html_e('Die Icons wurden gespeichert, sehen aber eventuell anders aus als erwartet.', 'container-block-designer'); ?></p>
            </div>
        <?php endif; ?>

        <?php if (!empty($cbd_report['skipped'])) : ?>
            <div class="notice notice-error">
                <p><strong><?php esc_html_e('Nicht gespeichert:', 'container-block-designer'); ?></strong></p>
                <ul style="list-style:disc;margin-left:20px;">
                    <?php foreach ($cbd_report['skipped'] as $line) : ?>
                        <li><?php echo esc_html($line); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ('' === $cbd_override) : ?>
        <div class="notice notice-error">
            <p><?php esc_html_e('Das WordPress-Upload-Verzeichnis ist nicht verfügbar. Uploads sind derzeit nicht möglich.', 'container-block-designer'); ?></p>
        </div>
    <?php endif; ?>

    <div class="cbd-icon-manager-intro">
        <p>
            <?php esc_html_e('Hier hochgeladene Icons liegen in wp-content/uploads/cbd-icons/ und überstehen Plugin-Updates. Ein Icon mit demselben Namen wie ein mitgeliefertes ersetzt dieses; nach dem Löschen gilt wieder das Original.', 'container-block-designer'); ?>
        </p>
        <p class="description">
            <?php esc_html_e('Jede Datei wird vor dem Speichern geprüft und bereinigt: Skripte, Event-Handler und externe Verweise werden entfernt. Erlaubt sind ausschließlich SVG-Dateien.', 'container-block-designer'); ?>
        </p>
    </div>

    <h2><?php esc_html_e('Icons hochladen', 'container-block-designer'); ?></h2>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" class="cbd-icon-upload-form">
        <input type="hidden" name="action" value="cbd_icon_upload">
        <?php wp_nonce_field('cbd_icon_upload'); ?>

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="cbd_icon_group"><?php esc_html_e('Gruppe', 'container-block-designer'); ?></label></th>
                <td>
                    <select name="cbd_icon_group" id="cbd_icon_group">
                        <?php foreach ($cbd_groups as $key => $label) : ?>
                            <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">
                        <?php esc_html_e('„Zahlen" versorgt die automatische Nummerierung — Dateien müssen dort 1.svg, 2.svg usw. heißen.', 'container-block-designer'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="cbd_icon_files"><?php esc_html_e('SVG-Dateien', 'container-block-designer'); ?></label></th>
                <td>
                    <input type="file" name="cbd_icon_files[]" id="cbd_icon_files" accept=".svg,image/svg+xml" multiple required>
                    <p class="description"><?php esc_html_e('Mehrfachauswahl möglich. Der Dateiname wird zum Icon-Namen.', 'container-block-designer'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="cbd_icon_name"><?php esc_html_e('Name überschreiben', 'container-block-designer'); ?></label></th>
                <td>
                    <input type="text" name="cbd_icon_name" id="cbd_icon_name" class="regular-text" placeholder="<?php esc_attr_e('optional, nur bei einer Datei', 'container-block-designer'); ?>">
                    <p class="description">
                        <?php esc_html_e('Leer lassen, um den Dateinamen zu verwenden. Wichtig: Ein bereits in Block-Designs verwendeter Name darf sich nicht ändern, sonst zeigen diese kein Icon mehr.', 'container-block-designer'); ?>
                    </p>
                </td>
            </tr>
        </table>

        <?php submit_button(__('Hochladen', 'container-block-designer')); ?>
    </form>

    <hr>

    <h2><?php esc_html_e('Vorhandene Icons', 'container-block-designer'); ?></h2>

    <p>
        <?php
        $cbd_own = 0;
        foreach ($cbd_index as $cbd_icons) {
            foreach ($cbd_icons as $cbd_icon) {
                if (isset($cbd_icon['source']) && 'override' === $cbd_icon['source']) {
                    $cbd_own++;
                }
            }
        }
        printf(
            esc_html__('%1$d Icons insgesamt, davon %2$d selbst hochgeladen.', 'container-block-designer'),
            (int) array_sum(array_map('count', $cbd_index)),
            (int) $cbd_own
        );
        ?>
    </p>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-bottom:20px;">
        <input type="hidden" name="action" value="cbd_icon_flush">
        <?php wp_nonce_field('cbd_icon_flush'); ?>
        <button type="submit" class="button">
            <?php esc_html_e('Zwischenspeicher leeren', 'container-block-designer'); ?>
        </button>
        <span class="description" style="margin-left:8px;">
            <?php esc_html_e('Nötig, wenn Icons per FTP abgelegt wurden.', 'container-block-designer'); ?>
        </span>
    </form>

    <?php foreach ($cbd_groups as $cbd_group => $cbd_label) : ?>
        <?php $cbd_icons = isset($cbd_index[$cbd_group]) ? $cbd_index[$cbd_group] : array(); ?>

        <h3><?php echo esc_html($cbd_label); ?> <span class="count">(<?php echo (int) count($cbd_icons); ?>)</span></h3>

        <?php if (empty($cbd_icons)) : ?>
            <p class="description"><?php esc_html_e('Noch keine Icons in dieser Gruppe.', 'container-block-designer'); ?></p>
        <?php else : ?>
            <div class="cbd-icon-grid">
                <?php foreach ($cbd_icons as $cbd_name => $cbd_icon) : ?>
                    <?php
                    $cbd_is_own   = isset($cbd_icon['source']) && 'override' === $cbd_icon['source'];
                    $cbd_replaces = !empty($cbd_icon['overrides']);
                    $cbd_value    = $cbd_group . '/' . $cbd_name;
                    ?>
                    <div class="cbd-icon-card<?php echo $cbd_is_own ? ' is-own' : ''; ?>">
                        <img src="<?php echo esc_url(add_query_arg('ver', $cbd_icon['ver'], $cbd_icon['url'])); ?>"
                             alt="" width="48" height="48" loading="lazy">
                        <code><?php echo esc_html($cbd_name); ?></code>

                        <?php if ($cbd_replaces) : ?>
                            <span class="cbd-icon-badge cbd-badge-replace"><?php esc_html_e('ersetzt Original', 'container-block-designer'); ?></span>
                        <?php elseif ($cbd_is_own) : ?>
                            <span class="cbd-icon-badge cbd-badge-own"><?php esc_html_e('eigenes', 'container-block-designer'); ?></span>
                        <?php else : ?>
                            <span class="cbd-icon-badge cbd-badge-plugin"><?php esc_html_e('mitgeliefert', 'container-block-designer'); ?></span>
                        <?php endif; ?>

                        <?php if ($cbd_is_own) : ?>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                                  onsubmit="return confirm('<?php echo esc_js(__('Dieses Icon wirklich löschen?', 'container-block-designer')); ?>');">
                                <input type="hidden" name="action" value="cbd_icon_delete">
                                <input type="hidden" name="cbd_icon_value" value="<?php echo esc_attr($cbd_value); ?>">
                                <?php wp_nonce_field('cbd_icon_delete'); ?>
                                <button type="submit" class="button-link cbd-icon-delete">
                                    <?php echo $cbd_replaces
                                        ? esc_html__('Original wiederherstellen', 'container-block-designer')
                                        : esc_html__('Löschen', 'container-block-designer'); ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
</div>

<style>
.cbd-icon-manager-intro {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-left: 4px solid #e24614;
    padding: 12px 16px;
    margin: 16px 0;
    max-width: 900px;
}
.cbd-icon-manager .cbd-icon-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
    gap: 12px;
    margin-bottom: 28px;
    max-width: 1100px;
}
.cbd-icon-manager .cbd-icon-card {
    background: #fff;
    border: 1px solid #dcdcde;
    border-radius: 6px;
    padding: 12px 8px 10px;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
}
.cbd-icon-manager .cbd-icon-card.is-own {
    border-color: #e24614;
}
.cbd-icon-manager .cbd-icon-card img {
    width: 48px;
    height: 48px;
    display: block;
}
.cbd-icon-manager .cbd-icon-card code {
    font-size: 11px;
    background: none;
    padding: 0;
    word-break: break-all;
}
.cbd-icon-manager .cbd-icon-badge {
    font-size: 10px;
    line-height: 1.4;
    padding: 1px 6px;
    border-radius: 10px;
    white-space: nowrap;
}
.cbd-icon-manager .cbd-badge-plugin { background: #f0f0f1; color: #646970; }
.cbd-icon-manager .cbd-badge-own    { background: #edf7ed; color: #1e7a1e; }
.cbd-icon-manager .cbd-badge-replace{ background: #fcf0e8; color: #b53810; }
.cbd-icon-manager .cbd-icon-delete {
    color: #b32d2e;
    font-size: 11px;
    cursor: pointer;
}
.cbd-icon-manager .cbd-icon-delete:hover { color: #8a2424; }
</style>
