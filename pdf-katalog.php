<?php
/**
 * Plugin Name: PDF Blätterkatalog
 * Description: Interaktiver PDF-Katalog mit Echtzeit-Suche, Akkordion-Inhaltsverzeichnis, Doppelseiten-Ansicht und Blättereffekt. Mehrere Kataloge via Custom Post Type.
 * Version: 3.0.0
 * Author: Michael Kanda
 * Text Domain: pdf-katalog
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'PDK_VERSION', '3.0.0' );
define( 'PDK_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PDK_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/* ══════════════════════════════════════════════════
   1. Custom Post Type: pdk_catalog
   ══════════════════════════════════════════════════ */

add_action( 'init', function () {
    register_post_type( 'pdk_catalog', array(
        'labels' => array(
            'name'               => 'Kataloge',
            'singular_name'      => 'Katalog',
            'add_new'            => 'Neuer Katalog',
            'add_new_item'       => 'Neuen Katalog anlegen',
            'edit_item'          => 'Katalog bearbeiten',
            'new_item'           => 'Neuer Katalog',
            'view_item'          => 'Katalog ansehen',
            'search_items'       => 'Kataloge durchsuchen',
            'not_found'          => 'Keine Kataloge gefunden',
            'not_found_in_trash' => 'Keine Kataloge im Papierkorb',
            'all_items'          => 'Alle Kataloge',
            'menu_name'          => 'PDF Kataloge',
        ),
        'public'        => false,
        'show_ui'       => true,
        'show_in_menu'  => true,
        'menu_position' => 25,
        'menu_icon'     => 'dashicons-book-alt',
        'supports'      => array( 'title' ),
        'has_archive'   => false,
        'rewrite'       => false,
    ) );
});

/* ══════════════════════════════════════════════════
   2. Meta Boxes
   ══════════════════════════════════════════════════ */

add_action( 'add_meta_boxes', function () {
    add_meta_box( 'pdk_catalog_settings', 'Katalog-Einstellungen', 'pdk_meta_box_render', 'pdk_catalog', 'normal', 'high' );
    add_meta_box( 'pdk_catalog_shortcode', 'Shortcode', 'pdk_shortcode_box_render', 'pdk_catalog', 'side', 'high' );
});

function pdk_shortcode_box_render( $post ) {
    $slug = $post->post_name;
    $id   = $post->ID;
    if ( $post->post_status === 'auto-draft' ) {
        echo '<p class="description">Bitte zuerst speichern – Shortcode wird dann angezeigt.</p>';
        return;
    }
    echo '<p style="margin-bottom:8px;">Mit <strong>Slug</strong> (empfohlen):</p>';
    echo '<code style="display:block;padding:8px;background:#f0f0f1;font-size:13px;user-select:all;">[pdf_katalog slug="' . esc_attr( $slug ) . '"]</code>';
    echo '<p style="margin:12px 0 8px;">Mit <strong>ID</strong>:</p>';
    echo '<code style="display:block;padding:8px;background:#f0f0f1;font-size:13px;user-select:all;">[pdf_katalog id="' . esc_attr( $id ) . '"]</code>';
}

function pdk_meta_box_render( $post ) {
    wp_nonce_field( 'pdk_save_catalog', 'pdk_catalog_nonce' );

    $pdf_url = get_post_meta( $post->ID, '_pdk_pdf_url', true );
    $toc     = get_post_meta( $post->ID, '_pdk_toc_json', true );
    $accent  = get_post_meta( $post->ID, '_pdk_accent_color', true ) ?: '#e63946';
    $bg_color = get_post_meta( $post->ID, '_pdk_bg_color', true ) ?: '#0f1114';
    $text_color = get_post_meta( $post->ID, '_pdk_text_color', true ) ?: '#e4e4e7';
    $logo    = get_post_meta( $post->ID, '_pdk_logo_text', true ) ?: '';
    $subtitle = get_post_meta( $post->ID, '_pdk_subtitle', true ) ?: '';
    ?>

    <table class="form-table" style="margin-top:0;">
        <tr>
            <th><label for="pdk_pdf_url">PDF-Datei URL</label></th>
            <td>
                <input type="url" id="pdk_pdf_url" name="pdk_pdf_url"
                       value="<?php echo esc_attr( $pdf_url ); ?>"
                       class="regular-text" style="width:100%;max-width:600px;" />
                <p class="description">URL zur PDF-Datei (aus der Mediathek).</p>
                <button type="button" class="button" id="pdk-upload-btn">PDF aus Mediathek wählen</button>
            </td>
        </tr>
        <tr>
            <th><label for="pdk_logo_text">Logo-Text</label></th>
            <td>
                <input type="text" id="pdk_logo_text" name="pdk_logo_text"
                       value="<?php echo esc_attr( $logo ); ?>"
                       class="regular-text" placeholder="z.B. Katalog" />
                <p class="description">Wird oben in der Sidebar angezeigt. Leer = Titel des Katalog-Eintrags.</p>
            </td>
        </tr>
        <tr>
            <th><label for="pdk_subtitle">Untertitel</label></th>
            <td>
                <input type="text" id="pdk_subtitle" name="pdk_subtitle"
                       value="<?php echo esc_attr( $subtitle ); ?>"
                       class="regular-text" placeholder="z.B. 2026 / 2027" />
                <p class="description">Wird neben dem Logo-Text angezeigt (optional).</p>
            </td>
        </tr>
        <tr>
            <th><label for="pdk_accent_color">Akzentfarbe</label></th>
            <td>
                <input type="color" id="pdk_accent_color" name="pdk_accent_color"
                       value="<?php echo esc_attr( $accent ); ?>" />
            </td>
        </tr>
        <tr>
            <th><label for="pdk_bg_color">Hintergrundfarbe</label></th>
            <td>
                <input type="color" id="pdk_bg_color" name="pdk_bg_color"
                       value="<?php echo esc_attr( $bg_color ); ?>" />
                <p class="description">Haupthintergrund des Katalog-Viewers (Standard: #0f1114).</p>
            </td>
        </tr>
        <tr>
            <th><label for="pdk_text_color">Schriftfarbe</label></th>
            <td>
                <input type="color" id="pdk_text_color" name="pdk_text_color"
                       value="<?php echo esc_attr( $text_color ); ?>" />
                <p class="description">Textfarbe im Katalog-Viewer (Standard: #e4e4e7).</p>
            </td>
        </tr>
        <tr>
            <th><label for="pdk_toc_json">Inhaltsverzeichnis (JSON)</label></th>
            <td>
                <textarea id="pdk_toc_json" name="pdk_toc_json" rows="20"
                          style="width:100%;max-width:700px;font-family:monospace;font-size:13px;"
                ><?php echo esc_textarea( $toc ); ?></textarea>
                <p class="description">
                    Format: <code>[{"chapter":"Name","items":[{"title":"…","page":6},…]}]</code><br>
                    Leer lassen = kein Inhaltsverzeichnis (Suche im PDF-Text funktioniert trotzdem).
                </p>
            </td>
        </tr>
    </table>

    <script>
    jQuery(function($){
        $('#pdk-upload-btn').on('click', function(e){
            e.preventDefault();
            var frame = wp.media({ title:'PDF wählen', multiple:false, library:{type:'application/pdf'} });
            frame.on('select', function(){
                $('#pdk_pdf_url').val(frame.state().get('selection').first().toJSON().url);
            });
            frame.open();
        });
    });
    </script>
    <?php
}

/* ══════════════════════════════════════════════════
   3. Save Meta
   ══════════════════════════════════════════════════ */

add_action( 'save_post_pdk_catalog', function ( $post_id ) {
    if ( ! isset( $_POST['pdk_catalog_nonce'] ) || ! wp_verify_nonce( $_POST['pdk_catalog_nonce'], 'pdk_save_catalog' ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    $fields = array(
        'pdk_pdf_url'      => '_pdk_pdf_url',
        'pdk_toc_json'     => '_pdk_toc_json',
        'pdk_accent_color' => '_pdk_accent_color',
        'pdk_bg_color'     => '_pdk_bg_color',
        'pdk_text_color'   => '_pdk_text_color',
        'pdk_logo_text'    => '_pdk_logo_text',
        'pdk_subtitle'     => '_pdk_subtitle',
    );
    foreach ( $fields as $input => $meta_key ) {
        if ( isset( $_POST[ $input ] ) ) {
            $value = $meta_key === '_pdk_pdf_url'
                ? esc_url_raw( $_POST[ $input ] )
                : sanitize_text_field( wp_unslash( $_POST[ $input ] ) );

            // TOC JSON: don't sanitize_text_field (breaks JSON)
            if ( $meta_key === '_pdk_toc_json' ) {
                $value = wp_unslash( $_POST[ $input ] );
                // Validate JSON
                $decoded = json_decode( $value );
                if ( json_last_error() !== JSON_ERROR_NONE && ! empty( $value ) ) {
                    // Keep old value if invalid JSON
                    continue;
                }
            }

            update_post_meta( $post_id, $meta_key, $value );
        }
    }
});

/* Enqueue media on CPT edit screen */
add_action( 'admin_enqueue_scripts', function ( $hook ) {
    if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
        $screen = get_current_screen();
        if ( $screen && $screen->post_type === 'pdk_catalog' ) {
            wp_enqueue_media();
        }
    }
});

/* ══════════════════════════════════════════════════
   4. Admin Columns (Übersicht)
   ══════════════════════════════════════════════════ */

add_filter( 'manage_pdk_catalog_posts_columns', function ( $cols ) {
    $new = array();
    foreach ( $cols as $key => $val ) {
        $new[ $key ] = $val;
        if ( $key === 'title' ) {
            $new['pdk_shortcode'] = 'Shortcode';
            $new['pdk_accent']    = 'Farbe';
            $new['pdk_has_pdf']   = 'PDF';
        }
    }
    return $new;
});

add_action( 'manage_pdk_catalog_posts_custom_column', function ( $col, $post_id ) {
    switch ( $col ) {
        case 'pdk_shortcode':
            $slug = get_post_field( 'post_name', $post_id );
            echo '<code>[pdf_katalog slug="' . esc_attr( $slug ) . '"]</code>';
            break;
        case 'pdk_accent':
            $c = get_post_meta( $post_id, '_pdk_accent_color', true ) ?: '#e63946';
            echo '<span style="display:inline-block;width:20px;height:20px;border-radius:4px;background:' . esc_attr( $c ) . ';vertical-align:middle;border:1px solid rgba(0,0,0,.15);"></span>';
            break;
        case 'pdk_has_pdf':
            $url = get_post_meta( $post_id, '_pdk_pdf_url', true );
            echo $url ? '✅' : '❌';
            break;
    }
}, 10, 2 );

/* ══════════════════════════════════════════════════
   5. Shortcode  [pdf_katalog id="..." / slug="..."]
   ══════════════════════════════════════════════════ */

add_shortcode( 'pdf_katalog', function ( $atts ) {
    $atts = shortcode_atts( array(
        'id'   => '',
        'slug' => '',
    ), $atts, 'pdf_katalog' );

    $catalog_post = null;

    // Lookup by slug
    if ( ! empty( $atts['slug'] ) ) {
        $found = get_posts( array(
            'post_type'   => 'pdk_catalog',
            'name'        => sanitize_title( $atts['slug'] ),
            'numberposts' => 1,
            'post_status' => 'publish',
        ) );
        if ( $found ) $catalog_post = $found[0];
    }

    // Lookup by ID
    if ( ! $catalog_post && ! empty( $atts['id'] ) ) {
        $p = get_post( intval( $atts['id'] ) );
        if ( $p && $p->post_type === 'pdk_catalog' && $p->post_status === 'publish' ) {
            $catalog_post = $p;
        }
    }

    // Fallback: Legacy single-catalog mode (backward compat with v2.x)
    if ( ! $catalog_post && empty( $atts['id'] ) && empty( $atts['slug'] ) ) {
        $legacy_url = get_option( 'pdk_pdf_url', '' );
        if ( $legacy_url ) {
            return pdk_render_viewer(
                $legacy_url,
                json_decode( get_option( 'pdk_toc_json', '[]' ), true ) ?: array(),
                get_option( 'pdk_accent_color', '#e63946' ),
                'Katalog',
                '2026 / 2027',
                'legacy'
            );
        }
        return '<p style="color:red;font-weight:bold;">PDF Katalog: Kein Katalog angegeben. Bitte <code>slug</code> oder <code>id</code> im Shortcode setzen.</p>';
    }

    if ( ! $catalog_post ) {
        return '<p style="color:red;font-weight:bold;">PDF Katalog: Katalog nicht gefunden.</p>';
    }

    $post_id  = $catalog_post->ID;
    $pdf_url  = get_post_meta( $post_id, '_pdk_pdf_url', true );
    $toc_raw  = get_post_meta( $post_id, '_pdk_toc_json', true );
    $accent   = get_post_meta( $post_id, '_pdk_accent_color', true ) ?: '#e63946';
    $bg_color = get_post_meta( $post_id, '_pdk_bg_color', true ) ?: '#0f1114';
    $text_color = get_post_meta( $post_id, '_pdk_text_color', true ) ?: '#e4e4e7';
    $logo     = get_post_meta( $post_id, '_pdk_logo_text', true ) ?: $catalog_post->post_title;
    $subtitle = get_post_meta( $post_id, '_pdk_subtitle', true ) ?: '';

    if ( empty( $pdf_url ) ) {
        return '<p style="color:red;font-weight:bold;">PDF Katalog „' . esc_html( $catalog_post->post_title ) . '": Keine PDF-URL hinterlegt.</p>';
    }

    $toc = ! empty( $toc_raw ) ? ( json_decode( $toc_raw, true ) ?: array() ) : array();

    return pdk_render_viewer( $pdf_url, $toc, $accent, $logo, $subtitle, $post_id, $bg_color, $text_color );
});

/* ══════════════════════════════════════════════════
   6. Render Viewer (shared between CPT & legacy)
   ══════════════════════════════════════════════════ */

function pdk_render_viewer( $pdf_url, $toc, $accent, $logo, $subtitle, $instance_id, $bg_color = '#0f1114', $text_color = '#e4e4e7' ) {
    // Unique instance ID for multiple catalogs on one page
    $uid = 'pdk-' . sanitize_html_class( $instance_id );

    wp_enqueue_style( 'pdk-style', PDK_PLUGIN_URL . 'assets/css/katalog.css', array(), PDK_VERSION );
    wp_enqueue_script( 'pdfjs', 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js', array(), '3.11.174', true );
    wp_enqueue_script( 'pdk-app', PDK_PLUGIN_URL . 'assets/js/katalog.js', array( 'pdfjs' ), PDK_VERSION, true );

    // Pass data per instance (append to array)
    static $instance_count = 0;
    $instance_count++;

    $data_var = 'pdkData_' . $instance_count;

    wp_add_inline_script( 'pdk-app', 'window.' . $data_var . ' = ' . wp_json_encode( array(
        'instanceId'  => $uid,
        'pdfUrl'      => esc_url( $pdf_url ),
        'toc'         => $toc,
        'accentColor' => sanitize_hex_color( $accent ),
        'workerSrc'   => 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js',
    ) ) . ';window.pdkInstances = window.pdkInstances || []; window.pdkInstances.push(window.' . $data_var . ');', 'before' );

    ob_start();
    ?>
    <div id="<?php echo esc_attr( $uid ); ?>" class="pdk-katalog" style="--pdk-accent:<?php echo esc_attr( $accent ); ?>;--pdk-bg:<?php echo esc_attr( $bg_color ); ?>;--pdk-text:<?php echo esc_attr( $text_color ); ?>;">

        <!-- Sidebar -->
        <aside class="pdk-sidebar">
            <div class="pdk-sidebar-header">
                <h2 class="pdk-logo"><?php echo esc_html( $logo ); ?>
                    <?php if ( $subtitle ) : ?>
                        <span><?php echo esc_html( $subtitle ); ?></span>
                    <?php endif; ?>
                </h2>
                <div class="pdk-search-wrap">
                    <svg class="pdk-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" class="pdk-search" placeholder="Suche im Katalog…" autocomplete="off" />
                    <button class="pdk-search-clear" type="button" aria-label="Suche löschen">&times;</button>
                </div>
                <div class="pdk-search-results-info" style="display:none;"></div>
            </div>
            <nav class="pdk-toc" role="navigation" aria-label="Inhaltsverzeichnis"></nav>
        </aside>

        <!-- Main Viewer -->
        <main class="pdk-viewer">
            <div class="pdk-toolbar">
                <button class="pdk-toggle-sidebar" type="button" aria-label="Seitenleiste">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                </button>

                <button class="pdk-toggle-double" type="button" aria-label="Doppelseite" title="Doppelseiten-Ansicht">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="8" height="18" rx="1"/><rect x="14" y="3" width="8" height="18" rx="1"/></svg>
                </button>

                <span class="pdk-toolbar-sep"></span>

                <div class="pdk-page-nav">
                    <button class="pdk-prev" type="button" aria-label="Vorherige Seite">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
                    </button>
                    <span class="pdk-page-info">
                        Seite <input type="number" class="pdk-page-input" min="1" value="1" /> von <span class="pdk-page-total">–</span>
                    </span>
                    <button class="pdk-next" type="button" aria-label="Nächste Seite">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                    </button>
                </div>
                <div class="pdk-zoom-controls">
                    <button class="pdk-zoom-out" type="button" aria-label="Verkleinern">−</button>
                    <span class="pdk-zoom-level">100 %</span>
                    <button class="pdk-zoom-in" type="button" aria-label="Vergrößern">+</button>
                    <button class="pdk-zoom-fit" type="button" aria-label="Einpassen">⊡</button>
                </div>
                <button class="pdk-fullscreen" type="button" aria-label="Vollbild">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg>
                </button>
            </div>

            <div class="pdk-canvas-wrap">
                <div class="pdk-loading">
                    <div class="pdk-spinner"></div>
                    <span>Katalog wird geladen…</span>
                </div>

                <div class="pdk-book pdk-single">
                    <div class="pdk-click-prev"></div>
                    <div class="pdk-click-next"></div>
                    <div class="pdk-flip-shadow pdk-flip-shadow-l"></div>
                    <div class="pdk-flip-shadow pdk-flip-shadow-r"></div>

                    <div class="pdk-page-left">
                        <canvas class="pdk-canvas-left"></canvas>
                    </div>
                    <div class="pdk-book-spine"></div>
                    <div class="pdk-page-right">
                        <canvas class="pdk-canvas-right"></canvas>
                    </div>
                </div>

                <div class="pdk-text-layer"></div>
            </div>
        </main>

    </div>
    <?php
    return ob_get_clean();
}

/* ══════════════════════════════════════════════════
   7. Migration helper: Legacy → CPT
   ══════════════════════════════════════════════════ */

add_action( 'admin_notices', function () {
    if ( get_current_screen() && get_current_screen()->post_type === 'pdk_catalog' ) {
        $legacy_url = get_option( 'pdk_pdf_url', '' );
        if ( $legacy_url && ! get_option( 'pdk_legacy_migrated' ) ) {
            echo '<div class="notice notice-info is-dismissible">';
            echo '<p><strong>PDF Katalog:</strong> Es existiert noch ein Katalog aus der alten Plugin-Version (v2.x). ';
            echo '<a href="' . esc_url( admin_url( 'admin-post.php?action=pdk_migrate_legacy&_wpnonce=' . wp_create_nonce( 'pdk_migrate' ) ) ) . '" class="button button-small">Jetzt als Katalog-Eintrag migrieren</a></p>';
            echo '</div>';
        }
    }
});

add_action( 'admin_post_pdk_migrate_legacy', function () {
    if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'pdk_migrate' ) || ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Nicht berechtigt.' );
    }

    $pdf_url = get_option( 'pdk_pdf_url', '' );
    $toc     = get_option( 'pdk_toc_json', '' );
    $accent  = get_option( 'pdk_accent_color', '#e63946' );

    if ( $pdf_url ) {
        $post_id = wp_insert_post( array(
            'post_type'   => 'pdk_catalog',
            'post_title'  => 'Hauptkatalog (migriert)',
            'post_status' => 'publish',
            'post_name'   => 'hauptkatalog',
        ) );

        if ( $post_id && ! is_wp_error( $post_id ) ) {
            update_post_meta( $post_id, '_pdk_pdf_url', $pdf_url );
            update_post_meta( $post_id, '_pdk_toc_json', $toc );
            update_post_meta( $post_id, '_pdk_accent_color', $accent );
            update_post_meta( $post_id, '_pdk_logo_text', 'Katalog' );
            update_post_meta( $post_id, '_pdk_subtitle', '2026 / 2027' );
            update_option( 'pdk_legacy_migrated', true );
        }
    }

    wp_redirect( admin_url( 'edit.php?post_type=pdk_catalog&migrated=1' ) );
    exit;
});
