<?php
/**
 * Container Block Designer - Block Organizer
 * Ermöglicht das Kopieren und Verschieben von Container-Blöcken zwischen Seiten.
 *
 * @package ContainerBlockDesigner
 * @since 3.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CBD_Block_Organizer {

    /**
     * Block-Namespace des Plugins
     */
    const BLOCK_NAMESPACE = 'container-block-designer/';

    /**
     * Gibt alle Seiten zurück, die mindestens einen Container-Block enthalten.
     *
     * @return array [ ['id' => int, 'title' => string], ... ]
     */
    public static function get_pages_with_blocks() {
        $pages = get_posts( array(
            'post_type'      => array( 'page', 'post' ),
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ) );

        $result = array();
        foreach ( $pages as $page ) {
            if ( has_blocks( $page->post_content ) ) {
                $blocks = parse_blocks( $page->post_content );
                if ( self::content_has_cbd_block( $blocks ) ) {
                    $result[] = array(
                        'id'    => $page->ID,
                        'title' => $page->post_title ?: __( '(ohne Titel)', 'container-block-designer' ),
                        'type'  => $page->post_type,
                    );
                }
            }
        }
        return $result;
    }

    /**
     * Gibt alle Top-Level-Container-Blöcke einer Seite zurück.
     *
     * @param int $post_id
     * @return array [ ['index' => int, 'label' => string], ... ]
     */
    public static function get_page_blocks( $post_id ) {
        $post = get_post( intval( $post_id ) );
        if ( ! $post ) {
            return array();
        }

        $all_blocks = parse_blocks( $post->post_content );
        $result     = array();
        $position   = 0;

        foreach ( $all_blocks as $index => $block ) {
            if ( self::is_cbd_block( $block['blockName'] ) ) {
                $label = self::get_block_label( $block, $position );
                $result[] = array(
                    'index'      => $index,
                    'position'   => $position,
                    'block_name' => $block['blockName'],
                    'label'      => $label,
                );
                $position++;
            }
        }
        return $result;
    }

    /**
     * Kopiert einen Block von einer Seite auf eine andere.
     * Weist dabei allen Blöcken im kopierten Baum neue eindeutige IDs zu.
     *
     * @param int    $source_post_id   Quell-Seite
     * @param int    $block_index      Index des Blocks in parse_blocks()-Array
     * @param int    $target_post_id   Ziel-Seite
     * @param string $position         'end' | 'start'
     * @return true|WP_Error
     */
    public static function copy_block( $source_post_id, $block_index, $target_post_id, $position = 'end' ) {
        $source_post = get_post( intval( $source_post_id ) );
        $target_post = get_post( intval( $target_post_id ) );

        if ( ! $source_post || ! $target_post ) {
            return new WP_Error( 'not_found', __( 'Seite nicht gefunden.', 'container-block-designer' ) );
        }

        $source_blocks = parse_blocks( $source_post->post_content );
        $block_index   = intval( $block_index );

        if ( ! isset( $source_blocks[ $block_index ] ) ) {
            return new WP_Error( 'invalid_index', __( 'Block nicht gefunden.', 'container-block-designer' ) );
        }

        // Tiefe Kopie + neue IDs
        $copied_block = self::regenerate_block_ids( $source_blocks[ $block_index ] );

        // In Zielseite einfügen
        $target_blocks = parse_blocks( $target_post->post_content );
        if ( $position === 'start' ) {
            array_unshift( $target_blocks, $copied_block );
        } else {
            $target_blocks[] = $copied_block;
        }

        $new_content = serialize_blocks( $target_blocks );

        $result = wp_update_post( array(
            'ID'           => $target_post->ID,
            'post_content' => $new_content,
        ), true );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return true;
    }

    /**
     * Verschiebt einen Block von einer Seite auf eine andere.
     *
     * @param int    $source_post_id
     * @param int    $block_index
     * @param int    $target_post_id
     * @param string $position  'end' | 'start'
     * @return true|WP_Error
     */
    public static function move_block( $source_post_id, $block_index, $target_post_id, $position = 'end' ) {
        $source_post = get_post( intval( $source_post_id ) );
        $target_post = get_post( intval( $target_post_id ) );

        if ( ! $source_post || ! $target_post ) {
            return new WP_Error( 'not_found', __( 'Seite nicht gefunden.', 'container-block-designer' ) );
        }

        $source_blocks = parse_blocks( $source_post->post_content );
        $block_index   = intval( $block_index );

        if ( ! isset( $source_blocks[ $block_index ] ) ) {
            return new WP_Error( 'invalid_index', __( 'Block nicht gefunden.', 'container-block-designer' ) );
        }

        $block_to_move = $source_blocks[ $block_index ];

        // Block aus Quelle entfernen
        array_splice( $source_blocks, $block_index, 1 );

        // In Ziel einfügen
        $target_blocks = parse_blocks( $target_post->post_content );
        if ( $position === 'start' ) {
            array_unshift( $target_blocks, $block_to_move );
        } else {
            $target_blocks[] = $block_to_move;
        }

        // Quelle aktualisieren
        $result_source = wp_update_post( array(
            'ID'           => $source_post->ID,
            'post_content' => serialize_blocks( $source_blocks ),
        ), true );

        if ( is_wp_error( $result_source ) ) {
            return $result_source;
        }

        // Ziel aktualisieren
        $result_target = wp_update_post( array(
            'ID'           => $target_post->ID,
            'post_content' => serialize_blocks( $target_blocks ),
        ), true );

        if ( is_wp_error( $result_target ) ) {
            return $result_target;
        }

        return true;
    }

    // ─── Private Hilfsmethoden ──────────────────────────────────────────────────

    /**
     * Prüft ob ein Block-Name zum CBD-Namespace gehört.
     */
    private static function is_cbd_block( $block_name ) {
        return $block_name && strpos( $block_name, self::BLOCK_NAMESPACE ) === 0;
    }

    /**
     * Prüft rekursiv ob ein Block-Array mindestens einen CBD-Block enthält.
     */
    private static function content_has_cbd_block( $blocks ) {
        foreach ( $blocks as $block ) {
            if ( self::is_cbd_block( $block['blockName'] ) ) {
                return true;
            }
            if ( ! empty( $block['innerBlocks'] ) && self::content_has_cbd_block( $block['innerBlocks'] ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Erstellt ein lesbares Label für einen Block.
     */
    private static function get_block_label( $block, $position ) {
        $type = str_replace( self::BLOCK_NAMESPACE, '', $block['blockName'] );

        // Titel aus Block-Attributen extrahieren, falls vorhanden
        $title = '';
        if ( ! empty( $block['attrs']['title'] ) ) {
            $title = $block['attrs']['title'];
        } elseif ( ! empty( $block['attrs']['label'] ) ) {
            $title = $block['attrs']['label'];
        } else {
            // Ersten Text-Inhalt aus innerHTML extrahieren (max. 50 Zeichen)
            $text = wp_strip_all_tags( $block['innerHTML'] ?? '' );
            $text = preg_replace( '/\s+/', ' ', trim( $text ) );
            if ( strlen( $text ) > 50 ) {
                $text = substr( $text, 0, 47 ) . '…';
            }
            $title = $text;
        }

        $label = sprintf( '#%d – %s', $position + 1, ucfirst( $type ) );
        if ( $title ) {
            $label .= ': ' . $title;
        }
        return $label;
    }

    /**
     * Weist einem Block und allen seinen InnerBlocks neue eindeutige IDs zu.
     * Betrifft Attribute wie uniqueId, blockId sowie UUID-förmige id-Werte.
     *
     * @param array $block  Parsed-Block-Array
     * @return array        Block mit neuen IDs
     */
    private static function regenerate_block_ids( $block ) {
        if ( ! empty( $block['attrs'] ) && is_array( $block['attrs'] ) ) {
            foreach ( $block['attrs'] as $key => $value ) {
                if ( is_string( $value ) && self::should_regenerate_id( $key, $value ) ) {
                    $block['attrs'][ $key ] = self::generate_new_id( $value );
                }
            }
        }

        // Rekursiv für InnerBlocks
        if ( ! empty( $block['innerBlocks'] ) ) {
            foreach ( $block['innerBlocks'] as &$inner ) {
                $inner = self::regenerate_block_ids( $inner );
            }
            unset( $inner );
        }

        // innerHTML/innerContent neu serialisieren (wird von serialize_blocks() übernommen)
        return $block;
    }

    /**
     * Entscheidet ob ein Attribut eine eindeutige ID ist, die neu generiert werden soll.
     */
    private static function should_regenerate_id( $key, $value ) {
        $id_keys = array( 'uniqueId', 'blockId', 'clientId', 'containerId' );
        if ( in_array( $key, $id_keys, true ) ) {
            return true;
        }
        // UUID-Muster erkennen
        if ( preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value ) ) {
            return true;
        }
        return false;
    }

    /**
     * Generiert eine neue ID im selben Format wie die Original-ID.
     */
    private static function generate_new_id( $original ) {
        // UUID-Format beibehalten
        if ( preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $original ) ) {
            return wp_generate_uuid4();
        }
        // Kurze alphanumerische IDs
        return substr( md5( uniqid( '', true ) ), 0, strlen( $original ) );
    }
}
