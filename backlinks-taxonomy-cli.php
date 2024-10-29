<?php

namespace ReneSeindal\Backlinks;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Backlinks plugin CLI commands
 *
 * ## EXAMPLES
 *
 *     wp backlinks update
 *
 * @when after_wp_load
 */

class BacklinksCLI {
    protected $plugin = NULL;

    public function __construct() {
        $this->plugin = Backlinks::instance();
    }

    private function display_posts( $header, $posts, $extra = [] ) {
        $output = [];
		foreach ( $posts as $post ) {
            $row = [
				__('ID', 'backlinks-taxonomy') => $post->ID,
				__('Type', 'backlinks-taxonomy') => $post->post_type,
				__('Status', 'backlinks-taxonomy') => $post->post_status,
				__('Title', 'backlinks-taxonomy') => $post->post_title,
				__('Date', 'backlinks-taxonomy') => $post->post_date,
			];

            if ( !empty( $extra ) ) {
                foreach ( $extra as $field => $label ) {
                    $row[$label] = $post->{$field};
                }
            }

			$output[] = $row;
		}

        if ( $output ) {
            \WP_CLI::line( $header );
            \WP_CLI\Utils\format_items( 'table', $output, join(',', array_keys($output[0])) );
        } else {
            \WP_CLI::warning( sprintf( __( '%s - NONE', 'backlinks-taxonomy' ), $header ) );
        }
    }

    /**
     * Show configuration
     *
     * ## EXAMPLES
     *
     *     wp backlink config
     *
     * @alias thumb
     */

    public function config( $args, $assoc ) {
        \WP_CLI::line( __( "Post types: ", 'backlinks-taxonomy' ) . join(',', $this->plugin->post_types()));
        \WP_CLI::line( __( "Post status: ", 'backlink-taxonomy' ) .join(',', $this->plugin->post_statuses()));
    }



    /**
     * Update links for all posts
     *
     * ## OPTIONS
     *
     * [<post_id>...]
     * : Update only the given posts
     *
     * [--format=<format>]
     * : Render output in a particular format.
     * ---
     * default: table
     * options:
     *   - table
     *   - csv
     *   - json
     *   - count
     *   - yaml
     * ---
     *
     *
     * ## EXAMPLES
     *
     *     wp backlinks rescan
     *
     */

    public function rescan( $args, $assoc ) {
        if ( empty( $args ) ) {
            $posts = get_posts( $this->plugin->build_post_query() );
        } else {
            $posts = get_posts( $this->plugin->build_post_query( [
                'post__in' => array_map( 'intval', array_filter( $args, 'intval' ) ),
                'post_status' => array_merge( [ 'draft' ], $this->plugin->post_statuses() ),
            ] ) );
        }

		$output = [];

		foreach ( $posts as $post ) {
			$links = $this->plugin->register_outlinks( $post );

			$output[] = [
				__('ID', 'backlinks-taxonomy') => $post->ID,
				__('Type', 'backlinks-taxonomy') => $post->post_type,
				__('Status', 'backlinks-taxonomy') => $post->post_status,
				__('Title', 'backlinks-taxonomy') => $post->post_title,
				__('Outgoing links', 'backlinks-taxonomy') => count( $links ),
			];
		}

		if ( $output ) {
			\WP_CLI\Utils\format_items( $assoc['format'], $output, join(',', array_keys($output[0])) );
		}
    }



    /**
     * Show backlinks for all posts
     *
     * ## OPTIONS
     *
     * [--max=<limit>]
     * : Show only posts with less than <limit> backlinks
     *
     * [--format=<format>]
     * : Render output in a particular format.
     * ---
     * default: table
     * options:
     *   - table
     *   - csv
     *   - json
     *   - count
     *   - yaml
     * ---
     *
     *
     * ## EXAMPLES
     *
     *     wp backlinks status
     *
     */

    public function status( $args, $assoc ) {
        $max = 9999;
        if ( isset( $assoc['max'] ) )
            $max = intval( $assoc['max'] );

        $posts = get_posts( $this->plugin->build_post_query() );

		$pools = [];

		foreach ( $posts as $post ) {
			$outgoing = get_the_terms( $post, $this->plugin->taxonomy() );
			if ( is_wp_error( $outgoing ) ) {
				\WP_CLI::error( $outgoing );
				$outgoing = [];
			} elseif ( $outgoing === FALSE ) {
				$outgoing = [];
			}

            $incoming = $this->plugin->get_backlinks( $post );

            $count = count( $incoming );
			$pools[ $count ][] = [
				__('ID', 'backlinks-taxonomy') => $post->ID,
				__('Type', 'backlinks-taxonomy') => $post->post_type,
				__('Status', 'backlinks-taxonomy') => $post->post_status,
				__('Title', 'backlinks-taxonomy') => $post->post_title,
				__('Incoming', 'backlinks-taxonomy') => $count,
				__('Outgoing', 'backlinks-taxonomy') => count( $outgoing ),
			];
        }

        ksort( $pools, SORT_NUMERIC );

        foreach ( $pools as $count => $output ) {
            if ( $count > $max )
                break;

            \WP_CLI::line( sprintf( __( "Found %d posts with %d backlinks", 'backlink-taxonomy' ), count( $output ), $count ) );
            \WP_CLI\Utils\format_items( $assoc['format'], $output, join(',', array_keys($output[0])) );
        }
    }


    /**
     * Show registed links for post
     *
     * ## OPTIONS
     *
     * <post_id>
     * : Show incoming and outgoing links for post_id
     *
     * [--format=<format>]
     * : Render output in a particular format.
     * ---
     * default: table
     * options:
     *   - table
     *   - csv
     *   - json
     *   - count
     *   - yaml
     * ---
     *
     * ## EXAMPLES
     *
     *     wp backlink show 42
     *
     * @alias thumb
     */

    public function show( $args, $assoc ) {
        $posts = get_posts( $this->plugin->build_post_query( [
            'post__in' => [ intval($args[0] ) ],
        ] ) );

		if ( !$posts ) {
			\WP_CLI::error( 'No such eligible post' );
			return;
		}
        $post = $posts[0];

        $this->display_posts( __( 'This post', 'backlinks-taxonomy' ) , [ $post ] );

		$outgoing = $this->plugin->get_outlinks( $post );
        $header = sprintf( __('Found %d outgoing links.', 'backlinks-taxonomy'), count( $outgoing ));
        $this->display_posts( $header , $outgoing );

		$incoming = $this->plugin->get_backlinks( $post );
        $header = sprintf( __('Found %d incoming links.', 'backlinks-taxonomy'), count( $incoming ));
        $this->display_posts( $header , $incoming );
    }



    /**
     * Suggest links for post
     *
     * ## OPTIONS
     *
     * <post-id>
     * : Suggest incoming and outgoing links for post-id
     *
     * [--verbose]
     * : Show all suggestings - incoming and outgoing -- for each taxonomy
     *
     *
     * ## EXAMPLES
     *
     *     wp backlink suggest 42
     *
     */

    public function suggest( $args, $assoc ) {
        $posts = get_posts( $this->plugin->build_post_query( [
            'post__in' => [ intval($args[0] ) ],
        ] ) );

		if ( !$posts ) {
			\WP_CLI::error( 'No such eligible post' );
			return;
		}
        $post = $posts[0];

        $suggestions = [];

        if ( $assoc['verbose'] ?? NULL ) {

            // Find taxonomies for this post
            $taxonomies = array_map(
                fn($t) => $t->name,
                array_filter(
                    get_taxonomies( [ 'public' => true, 'show_ui' => true ], 'objects' ),
                    fn($t) => in_array( $post->post_type, $t->object_type )
                )
            );


            $this->display_posts( __( 'This post', 'backlinks-taxonomy' ) , [ $post ] );

            $outlinks = $this->plugin->get_outlinks( $post );
            $this->display_posts( __( 'Outgoing links', 'backlinks-taxonomy' ), $outlinks );

            $exclude = $outlinks;
            foreach ( $taxonomies as $taxonomy ) {
                $suggestions = $this->plugin->backlink_suggestions_by_taxonomy( $post, $taxonomy, $exclude );
                $header = sprintf( __( "Outgoing link suggestions based on taxonomy '%s'", 'backlinks-taxonomy' ), $taxonomy );
                $this->display_posts( $header, $suggestions );

                $exclude = array_merge( $exclude, $suggestions );
            }




            $backlinks = $this->plugin->get_backlinks( $post );
            $this->display_posts( __( "Incoming links", 'backlinks-taxonomy' ), $backlinks );

            $exclude = $backlinks;
            $suggestions = [];

            foreach ( $taxonomies as $taxonomy ) {
                $tax_suggestions = $this->plugin->backlink_suggestions_by_taxonomy( $post, $taxonomy, $exclude );
                $header = sprintf( __( "Incoming link suggestions based on taxonomy '%s'", 'backlinks-taxonomy' ), $taxonomy );
                $this->display_posts( $header, $tax_suggestions );

                $suggestions = array_merge( $suggestions, $tax_suggestions );
                // $exclude = array_merge( $exclude, $suggestions );
            }

            $suggestions = array_unique( $suggestions, SORT_REGULAR );


            foreach ( $suggestions as $s )
                $s->menu_order = 0;

            foreach ( $taxonomies as $taxonomy ) {
                $post_terms = wp_list_pluck( get_the_terms( $post, $taxonomy ), 'term_id' );

                foreach ( $suggestions as $s ) {
                    $terms = wp_list_pluck( get_the_terms( $s, $taxonomy ), 'term_id' );
                    $s->menu_order += count( array_intersect( $post_terms, $terms ) );
                }
            }

        } else {
            $suggestions = $this->plugin->post_backlink_suggestions( $post );
        }


        usort( $suggestions, fn($a,$b) => ( $b->menu_order <=> $a->menu_order ) );
        $this->display_posts( __( 'Incoming link suggestions', 'backlinks-taxonomy' ),
                              $suggestions,
                              [ 'menu_order' => __( 'Shared terms', 'backlinks-taxonomy' ) ]
        );
    }



    /**
     * Show list of unscanned posts
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Render output in a particular format.
     * ---
     * default: table
     * options:
     *   - table
     *   - csv
     *   - json
     *   - count
     *   - yaml
     * ---
     *
     * ## EXAMPLES
     *
     *     wp backlink unscanned
     *
     */

    public function unscanned( $args, $assoc ) {
        $posts = $this->plugin->get_unregistered_posts();

        $this->display_posts( __( 'Unscanned posts', 'backlinks-taxonomy' ) , $posts );
    }
}

\WP_CLI::add_command( 'backlinks', new BacklinksCLI() );
