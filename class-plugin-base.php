<?php

namespace ReneSeindal\Backlinks;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class PluginBase {
    // These fields should be overridden in derived classes
    protected $plugin_file = NULL;

    // These are set automatically if missing
    protected $option_page = NULL;
    protected $option_base = NULL;
    protected $option_name = NULL;

    /*************************************************************
     *
     * Singleton interface
     *
     *************************************************************/

    private static $instances = [];

    public static function instance( ) {
        $class = get_called_class();
        if ( empty( self::$instances[$class] ) )
            self::$instances[$class] = new $class();
        return self::$instances[$class];
    }

    /*************************************************************
     *
     * Automatic setup of actions, filters and shortcodes based on
     * available class methods
     *
     *************************************************************/

    function __construct() {
        $methods = get_class_methods( $this );
        foreach ( $methods as $m ) {
            if ( preg_match( '/^do_(.*)_shortcode$/', $m, $match ) ) {
                $what = $match[1];

                $what = $this->callback_param( $what, 'shortcode', $what );

                add_shortcode( $what, [ $this, $m ] );
            }
            elseif ( preg_match( '/^do_(.*)_(action|filter)(_.*?)?$/', $m, $match ) ) {
                $what = $match[1];
                $type = $match[2];
                $extra = $match[3] ?? '';

                $prio = intval( $this->callback_param( "$what$extra", 'priority', 10) );
                $argc = ( new \ReflectionMethod( $this, $m ) )->getNumberOfParameters();

                $what = $this->callback_param( $what, 'hook', $what );
                $what = $this->callback_map_name( $what );

                // error_log( "$type $what / $extra -> $m $prio $argc" );

                // WP actually treats these exactly the same
                if ( 'action' === $type )
                    add_action( $what, [ $this, $m ], $prio, $argc );
                else if ( 'filter' === $type )
                    add_filter( $what, [ $this, $m ], $prio, $argc );
            }
        }

        // Set defaults for settings and options
        if ( empty( $this->option_page ) )
            $this->option_page = basename( $this->plugin_file, '.php');
        if ( empty( $this->option_base ) )
            $this->option_base = strtr( $this->option_page, '-', '_');
        if ( empty( $this->option_name ) )
            $this->option_name = $this->option_base . '_options';
    }

    protected function callback_map_name( $name ) {
        $map = [
            '__PLUGIN_BASENAME__' => plugin_basename( $this->plugin_file ),
        ];

        return str_replace( array_keys( $map ), array_values( $map ), $name );
    }

    protected function callback_param( $name, $param, $default ) {
        $property = "{$name}_callback_{$param}";
        return
            property_exists( $this, $property )
            ? $this->{$property}
            : $default;
    }

    /************************************************************************
     *
     *	Options
     *
     ************************************************************************/

    function get_options( ) {
        return get_option( $this->option_name );
    }

    function get_option( $name, $default = NULL ) {
        $options = $this->get_options();

        if ( !is_array( $options ) )
            return $default;
        if ( array_key_exists( $name, $options ) )
            return $options[$name];

        return $default;
    }

    function has_option( $name ) {
        return !empty( $this->get_option( $name ) );
    }


    /************************************************************************
     *
     *	Basic helpers
     *
     ************************************************************************/

    function build_admin_url( $page, $args = [] ) {
        return add_query_arg( $args, admin_url( $page ) );
    }


    function add_value_to_query_var( $query_var, $default, $value ) {
        if ( empty( $query_var ) ) // not set
            $query_var = [ $default, $value ];
        elseif ( is_string( $query_var ) ) // set to single value
            $query_var = [ $query_var, $value ];
        elseif ( is_array( $query_var ) ) // multiple values
            $query_var[] = $value;

        return $query_var;
    }

    function add_post_type_to_wp_query( $wp_query, $post_type ) {
        $post_types = $this->add_value_to_query_var( $wp_query->get( 'post_type' ), 'post', $post_type );
        $wp_query->set( 'post_type', $post_types );
    }

    // Fix searches on taxonomy archives to include custom post type
    // Fix author archives to include custom post-type

    function integrate_post_type_in_archives( $wp_query, $post_type, $do_term_search = true ) {
        $add_post_type = false;

        $queried_object = $wp_query->get_queried_object();
        if ( $do_term_search && is_a( $queried_object, 'WP_Term' ) ) {
            $taxonomy = get_taxonomy( $queried_object->taxonomy );
            if ( in_array( $post_type, $taxonomy->object_type ) )
                $add_post_type = true;
        }
        elseif ( $wp_query->is_author() ) {
            if ( $this->get_option( 'author_searches' ) )
                $add_post_type = true;
        }

        if ( $add_post_type ) {
            $post_types = $this->add_value_to_query_var( $wp_query->get( 'post_type' ), 'post', $post_type );
            $wp_query->set( 'post_type', $post_types );
        }
    }

    function do_current_year_shortcode() {
        return date( 'Y' );
    }
}
