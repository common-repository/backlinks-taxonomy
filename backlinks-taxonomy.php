<?php
/**
 * Plugin Name:         Backlinks Taxonomy
 * Description:         The purpose of this plugin is to help internal link building, to aid usability of the site and search engine optimisation. It keeps track of all internal links between posts using taxonomies.
 * Plugin URI:          https://plugins.seindal.dk/plugins/backlinks-taxonomy/
 * Author:              René Seindal
 * Author URI:          https://plugins.seindal.dk/
 * Donate link:         https://mypos.com/@historywalks
 * License:             GPL v2 or later
 * License URI:         https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:         backlinks-taxonomy
 * Domain Path:         /languages
 * Requires PHP:        7.4
 * Requires at least:   5.0
 * Version:             2.2
 **/

namespace ReneSeindal\Backlinks;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

require_once( ABSPATH . 'wp-admin/includes/taxonomy.php' );
require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );

include( __DIR__ . '/class-plugin-base.php' );
include( __DIR__ . '/trait-settings.php' );

class Backlinks extends PluginBase {
    protected $plugin_file = __FILE__;

    use PluginBaseSettings;

    protected $taxonomy_name = 'backlink';
    protected $taxonomy_count = 'backlink_count';
    protected $taxonomy_post_types = [ 'post', 'page' ];
    protected $taxonomy_post_statuses = [ 'publish', 'future' ];

    /*************************************************************
     *
     * Automatic setup of actions, filters and shortcodes based on
     * available class methods
     *
     *************************************************************/

	function __construct() {
        parent::__construct();

        $this->taxonomy_post_statuses = $this->filter_option_list( 'post_statuses',
                                                                   get_post_stati( [ 'internal' => false ], 'names' ),
                                                                   $this->taxonomy_post_statuses
        );
	}

    /************************************************************************
     *
     *	Options
     *
     ************************************************************************/

    function filter_option_list( $option, $valid_values, $default = [] ) {
        $input = $this->get_option( $option );
        $output = [];

        if ( ! empty( $input ) && is_array( $input ) ) {
            $output = array_filter( $input,  fn ( $v ) => !empty( $v ) && in_array( $v, $valid_values) );
        }

        if ( empty( $output ) )
            $output = $default;

        return array_values( $output );
    }

    /************************************************************************
     *
     *	Settings
     *
     ************************************************************************/

    function do_admin_menu_action() {
        $this->settings_add_menu(
            __( 'Backlink Taxonomy', 'backlinks-taxonomy' ),
            __( 'Backlinks', 'backlinks-taxonomy' )
        );
    }

    function settings_define_sections_and_fields() {
        // Post types

        $section = $this->settings_add_section(
            'section_post_types',
            __( 'Post-types', 'backlinks-taxonomy' ),
            __( 'Select the post-types you want to backlink taxonomy to apply to.', 'backlinks-taxonomy' )
        );

        $values = [];

        $post_types = get_post_types( [ 'public' => true ], 'objects' );
        foreach ( $post_types as $post_type ) {
            $labels = get_post_type_labels( $post_type );
            $values[ $post_type->name ] = $labels->name;
        }

        $this->settings_add_field(
            'post_types', $section,
            __( 'Post-types', 'rs-link-checker' ),
            'settings_field_select_html',
            [
                'values' => $values,
                'multiple' => true,
                'size' => -1,
            ]
        );

        // Post status

        $section = $this->settings_add_section(
            'section_post_statuses',
            __( 'Post-statuses', 'backlinks-taxonomy' ),
            __( 'Select the post statuses for which you want backlinks to be registered.', 'backlinks-taxonomy' )
        );


        $values = [];

        $post_statuses = get_post_stati( [ 'internal' => false ], 'objects' );
        foreach ( $post_statuses as $post_status ) {
            $values[ $post_status->name ] = $post_status->label;
        }

        $this->settings_add_field(
            'post_statuses', $section,
            __( 'Post statuses', 'backlinks-taxonomy' ),
            'settings_field_select_html',
            [
                'values' => $values,
                'multiple' => true,
                'size' => -1,
            ]
        );

        $section = $this->settings_add_section(
            'section_integration',
            __( 'Integrations', 'backlinks-taxonomy' ),
            __( 'Select how this plugin integrates into the WordPress admin interface', 'backlinks-taxonomy' )
        );

        $this->settings_add_field(
            'post_list_filter', $section,
            __( 'Allow post filtering', 'backlinks-taxonomy' ),
            'settings_field_checkbox_html'
        );
    }

    /************************************************************************
     *
     *	Helpers
     *
     ************************************************************************/

    public function post_type_ok( $post ) {
        $post = get_post( $post );
        return in_array( $post->post_type, $this->taxonomy_post_types);
    }

    public function term_to_post_id( $slug ) {
        return intval( substr( $slug, 1 ) );
    }

    public function post_id_to_term( $post_id ) {
        return "p$post_id";
    }

    function build_post_query( $query = [] ) {
        return array_merge(
            [
                'post_type' => $this->taxonomy_post_types,
                'post_status' => $this->taxonomy_post_statuses,
                'numberposts' => -1,
            ],
            $query
        );
    }

    function get_posts_query( $query ) {
        return get_posts( $this->build_post_query( $query ) );
    }

    function get_backlinks( $post ) {
        $post = get_post( $post );

        return $this->get_posts_query( [
            'tax_query' => [
                [
                    'taxonomy' => $this->taxonomy_name,
                    'field'    => 'slug',
                    'terms'    => $this->post_id_to_term( $post->ID ),
                ]
            ],
        ] );
    }

    function get_posts_with_no_backlinks() {
        return $this->get_posts_query( [
            'tax_query' => [
                [
                    'taxonomy' => $this->taxonomy_count,
                    'operator' => 'NOT EXISTS',
                ]
            ],
        ] );
    }

    function get_outlinks( $post ) {
        $post = get_post( $post );

        $terms = get_the_terms( $post, $this->taxonomy_name );

        if ( $terms === false || is_wp_error( $terms ) )
            return [];

        $ids = array_map( [ $this, 'term_to_post_id' ], wp_list_pluck( $terms, 'slug' ));

        return $this->get_posts_query( [ 'post__in' => $ids ] );
    }


    /************************************************************************
     *
     *	Helpers for CLI class
     *
     ************************************************************************/

    public function post_types() {
        return $this->taxonomy_post_types;
    }

    public function post_statuses() {
        return $this->taxonomy_post_statuses;
    }

    public function taxonomy() {
        return $this->taxonomy_name;
    }

    /************************************************************************
     *
     *	The backlink taxonomies
     *
     ************************************************************************/

    // Must run after prio 10 which might register other post_types
    protected $init_setup_taxonomy_callback_priority = 20;

    public function do_init_action_setup_taxonomy() {
        $this->taxonomy_post_types = $this->filter_option_list( 'post_types',
                                                                get_post_types( [ 'public' => true ], 'names' ),
                                                                $this->taxonomy_post_types
        );


        register_taxonomy( $this->taxonomy_name,
                           $this->taxonomy_post_types,
                           [
                               'label' => __( 'Backlinks', 'backlinks-taxonomy' ),
                               'public' => FALSE,
                               'hierarchical' => FALSE,
                               'show_in_rest' => TRUE,
                               'publicly_queryable' => TRUE,
                               'meta_box_cb' => FALSE,
                           ] );

        $public = false;
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            $public = true;
        }

        register_taxonomy( $this->taxonomy_count,
                           $this->taxonomy_post_types,
                           [
                               'hierarchical'          => false,
                               'public'                => $public,
                               'show_in_nav_menus'     => false,
                               'show_ui'               => false,
                               'show_admin_column'     => false,
                               'query_var'             => true,
                               'rewrite'               => true,

                               'labels'                => [
                                   'name'                       => __( 'Backlink counts', 'backlinks-taxonomy' ),
                                   'singular_name'              => _x( 'Backlink count', 'taxonomy general name', 'backlinks-taxonomy' ),
                                   'search_items'               => __( 'Search Backlink counts', 'backlinks-taxonomy' ),
                                   'popular_items'              => __( 'Popular Backlink counts', 'backlinks-taxonomy' ),
                                   'all_items'                  => __( 'All Backlink counts', 'backlinks-taxonomy' ),
                                   'parent_item'                => __( 'Parent Backlink count', 'backlinks-taxonomy' ),
                                   'parent_item_colon'          => __( 'Parent Backlink count:', 'backlinks-taxonomy' ),
                                   'edit_item'                  => __( 'Edit Backlink count', 'backlinks-taxonomy' ),
                                   'update_item'                => __( 'Update Backlink count', 'backlinks-taxonomy' ),
                                   'view_item'                  => __( 'View Backlink count', 'backlinks-taxonomy' ),
                                   'add_new_item'               => __( 'Add New Backlink count', 'backlinks-taxonomy' ),
                                   'new_item_name'              => __( 'New Backlink count', 'backlinks-taxonomy' ),
                                   'separate_items_with_commas' => __( 'Separate Backlink counts with commas', 'backlinks-taxonomy' ),
                                   'add_or_remove_items'        => __( 'Add or remove Backlink counts', 'backlinks-taxonomy' ),
                                   'choose_from_most_used'      => __( 'Choose from the most used Backlink counts', 'backlinks-taxonomy' ),
                                   'not_found'                  => __( 'No Backlink counts found.', 'backlinks-taxonomy' ),
                                   'no_terms'                   => __( 'No Backlink counts', 'backlinks-taxonomy' ),
                                   'menu_name'                  => __( 'Backlink counts', 'backlinks-taxonomy' ),
                                   'items_list_navigation'      => __( 'Backlink counts list navigation', 'backlinks-taxonomy' ),
                                   'items_list'                 => __( 'Backlink counts list', 'backlinks-taxonomy' ),
                                   'most_used'                  => _x( 'Most Used', 'backlink count', 'backlinks-taxonomy' ),
                                   'back_to_items'              => __( '&larr; Back to Backlink counts', 'backlinks-taxonomy' ),
                               ],
                               'show_in_rest'          => false,

                               'update_count_callback' => '_update_generic_term_count',
                           ] );

    }

    /************************************************************************
     *
     * Add filter on post lists on backlink_count taxonomy
     *
     ************************************************************************/

    function do_restrict_manage_posts_action_backlink_count( $post_type, $which ) {
            if ( ! $this->get_option( 'post_list_filter', false ) )
                return;

        $tax = get_taxonomy( $this->taxonomy_count );


        if ( in_array( $post_type, $tax->object_type ) ) {
            $var = $this->taxonomy_count;

            $selected = NULL;
            if ( isset( $_GET[$var] ) && is_string( $_GET[$var] ) ) {
                $selected = sanitize_title( $_GET[$var] );
            }

            wp_dropdown_categories(array(
                'show_option_all' =>  $tax->labels->all_items,
                'taxonomy'        =>  $this->taxonomy_count,
                'value_field'     => 'slug',
                'name'            =>  $this->taxonomy_count,
                'orderby'         =>  'name',
                'selected'        =>  $selected,
                'hierarchical'    =>  false,
                'show_count'      =>  true,
                'hide_empty'      =>  false,
            ) );
        }
    }

    /************************************************************************
     *
     *	Backlinks column in admin posts list
     *
     ************************************************************************/

    // Add the column

    function do_manage_posts_columns_filter( $columns, $post_type ) {
        $tax = get_taxonomy( $this->taxonomy_name );

        if ( in_array( $post_type, $tax->object_type ) ) {
            $columns[ 'backlink_count' ] = __( 'Backlinks', 'backlinks-taxonomy' );
        }
        return $columns;
    }

    function do_manage_pages_columns_filter( $columns ) {
        return $this->do_manage_posts_columns_filter( $columns, 'page' );
    }

    // Display the column content

    function do_manage_posts_custom_column_action( $column, $post_id) {
        $tax = get_taxonomy( $this->taxonomy_name );

        if ( $column == 'backlink_count' ) {
            $post = get_post( $post_id );
            if ( in_array( $post->post_status, $this->taxonomy_post_statuses ) ) {
                $backlinks = $this->get_backlinks( $post );

                $texts = [
                    __( 'Has no backlinks', 'backlinks-taxonomy' ),
                    __( 'Has %d backlink', 'backlinks-taxonomy' ),
                    __( 'Has %d backlinks', 'backlinks-taxonomy' ),

                ];
                $this->manage_posts_custom_column_formatter( 'backlinks', $backlinks, $post_id, $texts );

                echo "<br/>";

                $outlinks = $this->get_outlinks( $post );
                $texts = [
                    __( 'Has no outlinks', 'backlinks-taxonomy' ),
                    __( 'Has %d outlink', 'backlinks-taxonomy' ),
                    __( 'Has %d outlinks', 'backlinks-taxonomy' ),

                ];
                $this->manage_posts_custom_column_formatter( 'outlinks', $outlinks, $post_id, $texts );
            } else {
                esc_html_e( 'n/a', 'backlinks-taxonomy' );
            }
        }
    }

    private function manage_posts_custom_column_formatter( $mode, $links, $post_id, $text ) {
        $count = count( $links );

        if ( $count == 0 ) {
            echo esc_html( $text[0] );
        } else {
            $url = $this->management_page_link( $post_id, $mode );
            $text = sprintf( __( $count == 1 ? $text[1] : $text[2] ), $count );
            printf( '<a href="%s">%s</a>', esc_url( $url ), $text );
        }
    }

    function do_manage_pages_custom_column_action( $column, $post_id) {
        $this->do_manage_posts_custom_column_action( $column, $post_id );
    }


    // Make column sortable

    protected $init_backlink_column_sortable_callback_priority = 20;

    function do_init_action_backlink_column_sortable() {
        foreach ( $this->taxonomy_post_types as $post_type ) {
            add_filter( "manage_edit-{$post_type}_sortable_columns", function ( $columns ) {
                $columns['backlink_count'] = 'backlink_count';
                return $columns;
            } );
        }
    }

    function do_pre_get_posts_action_backlink_sorting( $query ) {
        if ( ! is_admin() ) return;

        $orderby = $query->get( 'orderby' );
        if ( $orderby == 'backlink_count' ) {
            $query->set( 'meta_key', '_backlinks_count' );
            $query->set( 'orderby', 'meta_value_num' );
        }
    }

    /************************************************************************
     *
     *	Backlink suggestions
     *
     ************************************************************************/

    public function backlink_suggestions_by_taxonomy( $post, $taxonomy, $exclude = [] ) {
        $terms = get_the_terms( $post, $taxonomy );

        if ( $terms === false || is_wp_error( $terms ) )
            return [];

        // Exclude terms with excessive posts
        $total = array_fill_keys( array_keys( get_object_vars ( wp_count_posts() ) ), 0 );
        foreach ( $this->taxonomy_post_types as $post_type ) {
            foreach ( get_object_vars( wp_count_posts( $post_type ) ) as $status => $count )
                $total[ $status ] += $count;
        }
        $limit = intval( $total['publish'] / 3 );
        $terms = array_values( array_filter( $terms, fn ( $t ) => ($t->count < $limit) ) );


        $posts = get_posts( $this->build_post_query( [
            'tax_query' => [
                [
                    'taxonomy' => $taxonomy,
                    'field'    => 'id',
                    'terms'    => wp_list_pluck( $terms, 'term_id' ),
                ]
            ],
            'post__not_in' => array_merge( [ $post->ID ], wp_list_pluck( $exclude, 'ID' ) ),
        ] ) );

        return $posts;
    }

    function post_backlink_suggestions( $post ) {
        $post = get_post( $post );
        if ( ! $post )
            return [];

        $backlinks = $this->get_backlinks( $post );

        // Find taxonomies for this post
        $taxonomies = array_filter(
            get_taxonomies( [ 'public' => true, 'show_ui' => true ], 'objects' ),
            fn($t) => in_array( $post->post_type, $t->object_type )
        );

        $suggestions = [];
        foreach ( $taxonomies as $taxonomy ) {
            $taxonomy_suggest = $this->backlink_suggestions_by_taxonomy( $post, $taxonomy->name, $backlinks );
            $suggestions = array_merge( $suggestions, $taxonomy_suggest );
        }
        $suggestions = array_unique( $suggestions, SORT_REGULAR );

        // SHOULD BE IMPROVED

        // The weight or importance of a suggestion is based on the
        // number of terms the two posts share across the taxonomies
        // they share. This is a reasonably simple way of quantifying
        // how related two posts are.

        // To get this information to the Backlinks_Link_Table we put
        // the number of shared terms in the menu_order field, so the
        // table can sort the list in a meaningful way for the user.

        // This is not exactly pretty but it's the simplest way of
        // getting the rating across.

        foreach ( $suggestions as $s )
            $s->menu_order = 0;

        foreach ( $taxonomies as $taxonomy ) {
            $post_terms = wp_list_pluck( get_the_terms( $post, $taxonomy->name ), 'term_id' );

            foreach ( $suggestions as $s ) {
                if (in_array( $s->post_type, $taxonomy->object_type ) ) {
                    $terms = wp_list_pluck( get_the_terms( $s, $taxonomy->name ), 'term_id' );
                    $s->menu_order += count( array_intersect( $post_terms, $terms ) );
                }
            }
        }

        return $suggestions;
    }

    function do_post_row_actions_filter_backlink_suggestions( $actions, $post ) {
        if ( $this->post_type_ok( $post ) ) {
            if ( in_array( $post->post_status, $this->taxonomy_post_statuses ) ) {
                $url = $this->management_page_link( $post, 'suggest' );

                $actions[ 'backlink_suggest' ] = sprintf( '<a href="%1$s">%2$s</a>',
                                                          esc_url( $url ),
                                                          esc_html( __( 'Suggest backlinks', 'backlinks-taxonomy' ) )
                );
            }
        }

        return $actions;
    }

    function do_page_row_actions_filter_backlink_suggestions( $actions, $post ) {
        return $this->do_post_row_actions_filter_backlink_suggestions( $actions, $post );
    }

    /************************************************************************
     *
     *	Lists of backlinks - using class Backlinks_Link_Table
     *
     ************************************************************************/

    public $management_page = NULL;

    function do_admin_menu_action_tools_page() {
        $this->management_page = add_management_page(
            __('Backlinks Taxonomy', 'backlinks-taxonomy' ),
            __('Backlinks', 'backlinks-taxonomy' ),
            'edit_posts',
            'backlinks_taxonomy_page',
            [ $this, 'management_page_render' ]
        );

        add_action( "load-{$this->management_page}", [ $this, 'load_managament_page_hook' ] );
    }

    function management_page_link( $post = NULL, $mode = NULL ) {
        $args = [ 'page' => 'backlinks_taxonomy_page' ];

        if ( isset( $post ) ) {
            $post = get_post( $post );
            if ( $post )
                $args['post'] = $post->ID;
        }

        if ( isset( $mode ) )
            $args['mode'] = $mode;

        return $this->build_admin_url( 'tools.php', $args );
    }

    function management_page_render() {
        printf( '<div class="wrap"><h2>%s</h2>', __( 'Backlinks Taxonomy', 'backlinks-taxonomy' ) );

        $post = NULL;
        if ( isset( $_GET['post'] ) )
            $post = get_post( intval( $_GET['post'] ) );

        $mode = 'status';
        if ( isset( $_GET['mode'] ) )
            $mode = sanitize_title( $_GET['mode'] );

        // Output header
        do_action( "backlinks_taxonomy_page_{$mode}_header", $post );

        echo '<form method="post">';

        $table = new Backlinks_Link_Table( $this, $this->management_page );

        $action = $table->current_action();
        if ( $action )
            do_action( "backlinks_taxonomy_page_bulk_{$action}" );

        $links = apply_filters( "backlinks_taxonomy_page_{$mode}_links", $post );
        $table->set_links( $links );

        $table->prepare_items();
        $table->display();

        echo '</form>';
        echo '</div>';
    }

    function do_backlinks_taxonomy_page_status_header_action( $post ) {
        printf( '<h3>%s.</h3>', __( 'List of unscanned posts', 'backlinks-taxonomy' ) );

        printf( '<p>' . __('There are <a href="%s">%d correctly scanned posts</a>.', 'backlinks-taxonomy' ) . '</p>',
                $this->management_page_link( NULL, 'all' ),
                $this->get_registered_post_count()
        );
    }

    function do_backlinks_taxonomy_page_status_links_filter( $post ) {
        return $this->get_unregistered_posts();
    }


    function do_backlinks_taxonomy_page_all_header_action( $post ) {
        printf( '<h3>%s.</h3>', __( 'List of all eligible posts', 'backlinks-taxonomy' ) );
    }

    function do_backlinks_taxonomy_page_all_links_filter( $post ) {
        return get_posts( $this->build_post_query() );
    }


    function do_backlinks_taxonomy_page_backlinks_header_action( $post ) {
        printf( '<h3>' . __( 'Backlinks for post #%d: %s', 'backlinks-taxonomy') . '</h3>',
                $post->ID, esc_html( $post->post_title ) );

        if ( !empty( $post->post_excerpt ) )
            printf( '<p>%s</p>', esc_html( $post->post_excerpt ) );
    }

    function do_backlinks_taxonomy_page_backlinks_links_filter( $post ) {
        return $this->get_backlinks( $post );
    }

    function do_backlinks_taxonomy_page_outlinks_header_action( $post ) {
        printf( '<h3>' . __( 'Outlinks for post #%d: %s', 'backlinks-taxonomy') . '</h3>',
                $post->ID, esc_html( $post->post_title ) );

        if ( !empty( $post->post_excerpt ) )
            printf( '<p>%s</p>', esc_html( $post->post_excerpt ) );
    }

    function do_backlinks_taxonomy_page_outlinks_links_filter( $post ) {
        return $this->get_outlinks( $post );
    }

    function do_backlinks_taxonomy_page_suggest_header_action( $post ) {
        printf( '<h3>' . __( 'Backlink suggestions for post #%d: %s', 'backlinks-taxonomy') . '</h3>',
                $post->ID, esc_html( $post->post_title ) );

        if ( !empty( $post->post_excerpt ) )
            printf( '<p>%s</p>', esc_html( $post->post_excerpt ) );
    }

    function do_backlinks_taxonomy_page_suggest_links_filter( $post ) {
        return $this->post_backlink_suggestions( $post );
    }


    // Single Backlinks_Link_Table actions

    function do_admin_post_scan_action() {
        if ( !( isset( $_REQUEST['_wpnonce'] ) && wp_verify_nonce( $_REQUEST['_wpnonce'], 'backlinks-row-actions-nonce' ) ) )
            die( __( 'Security check', 'backlinks-taxonomy' ) );

        if ( isset( $_GET['post'] ) ) {
            $post = get_post( intval( $_GET['post'] ) );
            if ( $post )
                $this->register_outlinks( $post );
        }

        if ( ! isset( $_GET['return'] ) )
            die( __( 'No return parameter specified', 'backlinks-taxonomy' ) );
        $return = urldecode( $_GET['return'] );
        wp_redirect( $return );
        exit;
    }

    function do_admin_post_unscan_action() {
        if ( !( isset( $_REQUEST['_wpnonce'] ) && wp_verify_nonce( $_REQUEST['_wpnonce'], 'backlinks-row-actions-nonce' ) ) )
            die( __( 'Security check', 'backlinks-taxonomy' ) );

        if ( isset( $_GET['post'] ) ) {
            $post = get_post( intval( $_GET['post'] ) );
            if ( $post )
                $this->deregister_outlinks( $post );
        }

        if ( ! isset( $_GET['return'] ) )
            die( __( 'No return parameter specified', 'backlinks-taxonomy' ) );
        $return = urldecode( $_GET['return'] );
        wp_redirect( $return );
        exit;
    }

    // Bulk Backlinks_Link_Table actions

    function do_backlinks_taxonomy_page_bulk_scan_action() {
        if ( !isset( $_POST['ids'] ) ) return;
        if ( !is_array( $_POST['ids'] ) ) return;

        $ids = array_map( fn($id) => intval( $id ), $_POST['ids'] );
        foreach ( $ids as $id ) {
            $post = get_post( $id );
            if ( $post ) {
                $outlinks = $this->register_outlinks( $post );
                printf( '<div>' .  __( 'Post %d: %s has %d outlinks', 'backlinks-taxonomy' ) . '</div>',
                        $post->ID, esc_html( $post->post_title), count( $outlinks ) );
            }
        }
    }

    function do_backlinks_taxonomy_page_bulk_unscan_action() {
        if ( !isset( $_POST['ids'] ) ) return;
        if ( !is_array( $_POST['ids'] ) ) return;

        $ids = array_map( fn($id) => intval( $id ), $_POST['ids'] );
        foreach ( $ids as $id ) {
            $post = get_post( $id );
            if ( $post ) {
                $this->deregister_outlinks( $post );
                printf( '<div>' . __( 'Post %d: %s is de-registered', 'backlinks-taxonomy' ) . '</div>',
                        $post->ID, esc_html( $post->post_title) );
            }
        }
    }

    // Set up the Screen Options

    function load_managament_page_hook() {
        $screen = get_current_screen();

        // get out of here if we are not on our settings page
        if( !is_object( $screen ) || $screen->id != $this->management_page )
            return;

        $args = array(
            'label' => __( 'Elements per page', 'backlinks-taxonomy' ),
            'default' => 20,
            'option' => 'elements_per_page'
        );

        add_screen_option( 'per_page', $args );

        // This allows column selection - I have no idea why
        new Backlinks_Link_Table( $this, $this->management_page );
    }

    protected $set_screen_option_callback_hook = 'set-screen-option';

    function do_set_screen_option_filter( $status, $option, $value ) {
        return $value;
    }

    // Make the last columns narrower
    function do_admin_head_action_link_table_css() {
        echo '<style type="text/css">';
        echo ".{this->management_page} .column-status { width: 10% !important; overflow: hidden }";
        echo ".{this->management_page} .column-type { width: 10% !important; overflow: hidden }";
        echo ".{this->management_page} .column-date { width: 14% !important; overflow: hidden; }";
        echo ".{this->management_page} .column-modified { width: 14% !important; overflow: hidden; }";
        echo ".{this->management_page} .column-backlinks { width: 8% !important; overflow: hidden; }";
        echo ".{this->management_page} .column-outlinks { width: 8% !important; overflow: hidden; }";
        echo ".{this->management_page} .widefat TD.column-backlinks { text-align: right; padding-right: 2em; }";
        echo ".{this->management_page} .widefat TD.column-outlinks { text-align: right; padding-right: 2em; }";
        echo '</style>';
    }

    /************************************************************************
     *
     *	Content parser
     *
     ************************************************************************/

    protected function content_link_parser( $post ) {
        $post = get_post( $post );

        $input = $post->post_content;
        $links = [];

        $tags = new \WP_HTML_Tag_Processor( $input );
        while ( $tags->next_tag( 'a' ) ) {
            $href = $tags->get_attribute( 'href' );
            if ( $href ) {
                $id = url_to_postid( $href );

                if ( $id ) {
                    $links[$id] = $href;
                }
            }
        }

        return array_keys( $links );
    }

    /************************************************************************
     *
     *	Register and deregister outgoing links for post/page
     *
     ************************************************************************/

    // $targets - array of post_ids
    public function update_taxonomy( $post, $targets ) {
        $post = get_post( $post );

        wp_set_post_terms( $post->ID,
                           array_map( [ $this, 'post_id_to_term' ], $targets ),
                           $this->taxonomy_name,
                           false
        );
    }

    private function backlink_count_name( $count = 0 ) {
        if ( 0 == $count )
            $slug = __( 'Has 0 backlinks', 'backlinks-taxonomy' );
        elseif ( $count < 10 )
            $slug = sprintf( _n( 'Has %d backlink', 'Has %d backlinks', $count, 'backlinks-taxonomy' ), $count );
        else
            $slug = __( 'Has many backlinks', 'backlinks-taxonomy' );

        return $slug;
    }

    public function update_taxonomy_count( $post ) {
        $post = get_post( $post );

        $inbound = $this->get_backlinks( $post );
        $count = count( $inbound );

        $slug = $this->backlink_count_name( $count );
        wp_set_post_terms( $post->ID, $slug, $this->taxonomy_count, false );

        // This is needed for sorting post lists on backlink count
        update_post_meta( $post->ID, '_backlinks_count', $count ?? 'none' );
    }

    public function register_outlinks( $post ) {
        $post = get_post( $post );
        $outlinks = $this->content_link_parser( $post );

        $this->update_taxonomy( $post, $outlinks );
        $this->update_taxonomy_count( $post );

        foreach ( $outlinks as $link )
            $this->update_taxonomy_count( $link );

        $unlinked = $this->get_posts_with_no_backlinks();
        foreach ( $unlinked as $link )
            $this->update_taxonomy_count( $link );

        return $outlinks;
    }

    public function deregister_outlinks( $post ) {
        $post = get_post( $post );
        $this->update_taxonomy( $post->ID, [] );
        delete_post_meta( $post->ID, '_backlinks_count' );
    }

    public function get_registered_post_count() {
        return count( get_posts( $this->build_post_query( [
            'meta_query' => [
                [
                    'key' => '_backlinks_count',
                    'compare' => 'EXISTS',
                ]
            ],
            'fields' => 'ids',
        ] ) ) );
    }

    public function get_unregistered_posts() {
        return get_posts( $this->build_post_query( [
            'meta_query' => [
                [
                    'key' => '_backlinks_count',
                    'compare' => 'NOT EXISTS',
                ]
            ],
        ] ) );
    }


    function do_transition_post_status_action_register_outlinks( $new, $old, $post ) {
        // Don't bother parsing unpublished posts
        if ( !in_array( $new, $this->taxonomy_post_statuses ) )
            return;

        if ( ! $this->post_type_ok( $post ) ) {
            return;
        }

        $transient = $this->taxonomy_name . '_' . $post->ID;
        $lasttime = get_transient( $transient );
        if ( $lasttime && $lasttime == $post->post_modified )
            return;
        set_transient( $transient, $post->post_modified, WEEK_IN_SECONDS );

        // Don't slow saving down unnecessarily
        add_action( 'shutdown', fn() => $this->register_outlinks( $post ) );
    }

    /************************************************************************
     *
     *	Scanner backlog
     *
     ************************************************************************/

    // Notify user if there are unscanned posts
    function do_admin_notices_action_unregistered_posts() {
        $unregistered = $this->get_unregistered_posts();
        if ( !empty( $unregistered ) ) {
            echo '<div class="notice notice-warning is-dismissible"><p>';
            printf( __( 'Backlinks Taxonomy: there are %d unregistered posts -- they\'ll be scanned shortly.', 'backlinks-taxonomy' ), count( $unregistered ) );
            echo '</p></div>';
        }
    }

    // Register a background handler to work on the backlog
    // A transient is used to avoid scheduling the event if it's still running
    function do_shutdown_action_check_backlog() {
        $lock = get_transient( 'backlinks-taxonomy-backlog-lock' );
        if ( $lock ) return;

        $unregistered = $this->get_unregistered_posts();
        if ( !empty( $unregistered ) ) {
            if ( wp_next_scheduled( 'backlinks_backlog' ) )
                return;

            wp_schedule_single_event( time() + MINUTE_IN_SECONDS, 'backlinks_backlog' );
            set_transient( 'backlinks-taxonomy-backlog-lock', time(), 30 * MINUTE_IN_SECONDS );
        }
    }

    // Work a bit on the backlog in the background
    // The shutdown action above will reschedule if needed.
    function do_backlinks_backlog_action() {
        $unregistered = $this->get_unregistered_posts();
        if (empty( $unregistered ) )
            return;

        foreach( array_slice( $unregistered, 0, 20 ) as $post )
            $this->register_outlinks( $post );

        delete_transient( 'backlinks-taxonomy-backlog-lock' );
    }

}


class Backlinks_Link_Table extends \WP_List_Table {
    protected $plugin = NULL;
    protected $page;

    protected $table_data;

    protected $statuses;
    protected $types;
    protected $date_format;

	function __construct( $plugin, $page ) {
        parent::__construct();

        $this->plugin = $plugin;
        $this->page = $page;

        $statuses = get_post_stati( [ 'internal' => false ], 'objects' );
        foreach ( $statuses as $status ) {
            $this->statuses[ $status->name ] = $status->label;
        }

        $post_types = get_post_types( [ 'public' => true ], 'objects' );
        foreach ( $post_types as $post_type ) {
            $labels = get_post_type_labels( $post_type );
            $this->types[ $post_type->name ] = $labels->singular_name;
        }

        $this->date_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

    }

    function build_admin_url( $page, $args ) {
        return add_query_arg( $args, admin_url( $page ) );
    }

    function set_links( $links ) {
        $this->table_data = [];

        foreach ( $links as $link ) {
            $this->table_data[] = [
                'id' => $link->ID,
                'title' => $link->post_title,
                'type' => $link->post_type,
                'status' => $link->post_status,
                'date' => $link->post_date,
                'modified' => $link->post_modified_date,
                'backlinks' => count( $this->plugin->get_backlinks( $link ) ),
                'outlinks' => count( $this->plugin->get_outlinks( $link ) ),
                'menu_order' => $link->menu_order,
            ];
        }
    }

    function get_columns() {
        $columns = [
            'cb' => '<input type="checkbox" />',
            'title' => __('Title', 'backlinks-taxonomy'),
            'type' => __('Type', 'backlinks-taxonomy'),
            'status' => __('Status', 'backlinks-taxonomy'),
            'modified' => __('Modified', 'backlinks-taxonomy'),
            'date' => __('Date', 'backlinks-taxonomy'),
            'backlinks' => __('Backlinks', 'backlinks-taxonomy'),
            'outlinks' => __('Outlinks', 'backlinks-taxonomy'),
        ];

        return $columns;
    }


    // Bind table with columns, data and all
    function prepare_items() {
        $columns = $this->get_columns();
        $usermeta = get_user_meta( get_current_user_id(), "manage{$this->page}columnshidden", true);
        $hidden = ( is_array( $usermeta ) ? $usermeta : [] );
        $sortable = $this->get_sortable_columns();
        $primary  = 'title';
        $this->_column_headers = [ $columns, $hidden, $sortable, $primary ];

        $this->sort_table_rows();

        /* pagination */
        $per_page = $this->get_items_per_page( 'elements_per_page', 10);
        $current_page = $this->get_pagenum();
        $total_items = count($this->table_data);

        $this->table_data = array_slice( $this->table_data, ( ($current_page - 1) * $per_page ), $per_page );

        $this->set_pagination_args( [
            'total_items' => $total_items, // total number of items
            'per_page'    => $per_page, // items to show on a page
            'total_pages' => ceil( $total_items / $per_page ) // use ceil to round up
        ] );

        $this->items = $this->table_data;
    }

    // Default set value for each column
    function column_default( $item, $column_name )  {
        return $item[$column_name];
    }

    // Add a checkbox in the first column
    function column_cb( $item )  {
        return sprintf( '<input type="checkbox" name="ids[]" value="%d" />',  esc_attr( $item['id'] ) );
    }

    // Adding action links to title column
    function column_title( $item ) {
        $edit_url = $this->build_admin_url( 'post.php', [ 'post' => $item['id'], 'action' => 'edit'] );
        $view_url = get_permalink( $item['id'] );
        $suggest_url = $this->plugin->management_page_link( $item['id'], 'suggest' );

        $actions = [
            'edit'    => sprintf( '<a href="%s">%s</a>', esc_url( $edit_url ), esc_html__( 'Edit', 'backlinks-taxonomy' ) ),
            'view'    => sprintf( '<a href="%s">%s</a>', esc_url( $view_url ), esc_html__( 'View', 'backlinks-taxonomy' ) ),
            'suggest' => sprintf( '<a href="%s">%s</a>', esc_url( $suggest_url ), esc_html( __( 'Suggest backlinks', 'backlinks-taxonomy' ) )
            ),
        ];

        $nonce = wp_create_nonce( 'backlinks-row-actions-nonce' );
        $return = urlencode( site_url( $_SERVER['REQUEST_URI'] ) );

        $post = get_post( $item['id'] );

        if ( $post ) {
            if ( isset( $post->_backlinks_count ) ) {
                $unscan_url = $this->build_admin_url( 'admin-post.php', [
                    'action' => 'unscan',
                    'post' => $item['id'],
                    '_wpnonce' => $nonce,
                    'return' => $return,
                ] );

                $actions['unscan'] = sprintf( '<a href="%s">%s</a>',
                                              esc_url( $unscan_url ),
                                              esc_html__( 'UnScan', 'backlinks-taxonomy' )
                );
            } else {
                $scan_url = $this->build_admin_url( 'admin-post.php', [
                    'action' => 'scan',
                    'post' => $item['id'],
                    '_wpnonce' => $nonce,
                    'return' => $return,
                ] );

                $actions['scan'] = sprintf( '<a href="%s">%s</a>',
                                            esc_url( $scan_url ),
                                            esc_html__( 'Scan', 'backlinks-taxonomy' )
                );
            }
        }

        return sprintf('<strong><a href="%s">%s</a></strong> %s', esc_url( $edit_url ), esc_html( $item['title'] ), $this->row_actions( $actions ) );
    }

    function column_date( $item ) {
        return get_the_date( $this->date_format, $item['id'] );
    }

    function column_modified( $item ) {
        return get_the_modified_date( $this->date_format, $item['id'] );
    }

    function column_status( $item ) {
        return $this->statuses[ $item['status'] ];
    }

    function column_type( $item ) {
        return $this->types[ $item['type'] ];
    }

    function column_backlinks( $item ) {
        return ( $item['backlinks']
                 ? sprintf( '<a href="%s">%d</a>', $this->plugin->management_page_link( $item['id'], 'backlinks'),  $item['backlinks'] )
                 : '-'
        );
    }

    function column_outlinks( $item ) {
        return ( $item['outlinks']
                 ? sprintf( '<a href="%s">%d</a>', $this->plugin->management_page_link( $item['id'], 'outlinks'),  $item['outlinks'] )
                 : '-'
        );
    }

    // Define sortable column
    protected function get_sortable_columns() {
        $sortable_columns = [
            'title'  => [ 'title', true ],
            'type'  => [ 'type', true ],
            'status'  => [ 'status', true ],
            'date'  => [ 'date', true ],
            'modified'  => [ 'modified', true ],
            'backlinks' => [ 'backlinks', false ],
            'outlinks' => [ 'outlinks', false ],
        ];
        return $sortable_columns;
    }

    protected function is_numeric_column( $column ) {
        $numeric_columns = [
            'backlinks' => true,
            'outlinks' => true,
            'menu_order' => true,
        ];
        return $numeric_columns[ $column ] ?? false;
    }

    protected function sort_table_rows() {
        // If no sort, default to weight / menu_order
        $orderby = 'menu_order';
        $order = 'desc';

        if ( !empty( $_GET['orderby'] ) ) {
            $orderby = sanitize_key( $_GET['orderby'] );
            $order = 'asc';
        }
        if ( !empty( $_GET['order'] ) )
            $order = sanitize_key( $_GET['order'] );

        if ( $this->is_numeric_column( $orderby ) )
            if ( $order == 'asc' )
                $cmp = fn($a, $b) => $a[$orderby] <=> $b[$orderby];
            else
                $cmp = fn($a, $b) => $b[$orderby] <=> $a[$orderby];
        else
            if ( $order == 'asc' )
                $cmp = fn($a, $b) => strcmp( $a[$orderby], $b[$orderby] );
            else
                $cmp = fn($a, $b) => strcmp( $b[$orderby], $a[$orderby] );

        usort( $this->table_data, $cmp );
    }


    // To show bulk action dropdown
    function get_bulk_actions() {
        $actions = [
            'scan' => __('Scan', 'backlinks-taxonomy'),
            'unscan' => __('Unscan', 'backlinks-taxonomy')
        ];
        return $actions;
    }

}

Backlinks::instance();

if ( defined( 'WP_CLI' ) && WP_CLI ) {
    require_once dirname( __FILE__ ) . '/backlinks-taxonomy-cli.php';
}
