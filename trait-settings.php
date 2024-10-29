<?php

namespace ReneSeindal\Backlinks;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly


/************************************************************************
 *
 *	Settings
 *
 ************************************************************************/

trait PluginBaseSettings {

    function do_plugin_action_links___PLUGIN_BASENAME___filter_settings( $links ) {
        $url = $this->build_admin_url( 'options-general.php', [ 'page' => $this->option_page ] );
        $text = __( 'Settings', 'issues-taxonomy' );
        $links[] = sprintf( '<a href="%s">%s</a>', esc_url( $url ), esc_html( $text ) );
        return $links;
    }

    // Simplified wrapper for add_options_page -- just the basics
    function settings_add_menu( $page_title, $menu_title ) {
        add_options_page(
            $page_title,
            $menu_title,
            'manage_options',
            $this->option_page,
            [ $this, 'settings_page_html' ]
        );
    }

    function do_admin_init_action_settings_define_sections_and_fields() {
        if ( method_exists( $this, 'settings_define_sections_and_fields' ) ) {
            register_setting(
                $this->option_page,
                $this->option_name,
                [
                    'sanitize_callback' => [ $this, 'settings_sanitize_option' ],
                ]
            );

            $this->settings_define_sections_and_fields();
        }
    }

    // Override in derived class for action sanitizing.
    function settings_sanitize_option( $input = NULl ) {
        return $input;
    }

    /* top level menu: callback functions */
    function settings_page_html() {
        // check user capabilities
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // check if the user have submitted the settings
        // wordpress will add the "settings-updated" $_GET parameter to the url
        if ( isset( $_GET['settings-updated'] ) ) {
            add_settings_error( $this->option_page, 'settings_saved', __( 'Settings Saved', 'issues-taxonomy' ), 'updated' );
        }

        print( '<div class="wrap">' );
        printf( '<h1>%s</h1>', esc_html( get_admin_page_title()  ) );
        print( '<form action="options.php" method="post">' );

        settings_fields( $this->option_page );
        do_settings_sections( $this->option_page );
        submit_button( __( 'Save Settings', 'issues-taxonomy') );

        print( '</form>' );
        print( '</div>' );
    }

    // Helpers to create sections and fields

    function settings_add_section( $section, $title, $description = '', $renderer = 'settings_section_print_intro_html', $args = [] ) {
        $section = $this->option_base . '_' . $section;
        $args = array_merge([ 'intro' => $description ], $args );

        add_settings_section(
            $section,
            $title,
            [ $this, $renderer ],
            $this->option_page,
            $args
        );

        return $section;
    }

    function settings_add_field( $field, $section, $title, $renderer, $args = [] ) {
        $args = array_merge( [
            'field' => $field,
            'label_for' => $this->option_base . '_' . $field,
            'settings' => $this->option_name,
            'default' => $this->get_option( $field ),
        ], $args );

        add_settings_field(
            $this->option_base . '_' . $field,
            $title,
            [ $this, $renderer ],
            $this->option_page,
            $section,
            $args
        );
    }



    /**
     * custom option and settings:
     * callback functions
     */


    function settings_section_print_intro_html( $args ) {
        if ( ! empty( $args['intro'] ) )
            printf( "<p>%s</p>", esc_html( $args['intro'] ) );
    }


    // Output a menu with or without multiple selection
    function settings_field_select_html( $args ) {
        $value = $this->get_option( $args['field'] ) ?? $args['default'];

        if ( isset( $args['multiple'] ) && $args['multiple'] ) {
            if ( isset( $args['size'] ) ) {
                $size = intval( $args['size'] );

                if ( $size <= 0 )
                    $size = count( $args['values'] );
            } else
                $size = 4;

            printf( '<select multiple size="%d" id="%s" name="%s[%s][]">' . PHP_EOL,
                    esc_attr( $size ),
                    esc_attr( $args['label_for'] ),
                    esc_attr( $args['settings'] ),
                    esc_attr( $args['field'] ),
            );

        } else {
            printf( '<select id="%s" name="%s[%s]">' . PHP_EOL,
                    esc_attr( $args['label_for'] ),
                    esc_attr( $args['settings'] ),
                    esc_attr( $args['field'] )
            );
        }

        foreach ( $args['values'] as $k => $v ) {
            printf( '<option value="%s" %s>%s</option>' . PHP_EOL,
                    esc_attr( $k ),
                    ( ( is_array( $value ) ? in_array( $k, $value ) : $k == $value ) ? 'selected' : '' ),
                    esc_html( $v )
            );
        }
        printf( "</select>" );
    }

    // Output a text input field
    function settings_field_input_html( $args ) {
        $value = $this->get_option( $args['field'] ) ?? $args['default'];

        printf( '<input type="%s" id="%s" name="%s[%s]" value="%s">',
                esc_attr( $args['type'] ?? 'text' ),
                esc_attr( $args['label_for'] ),
                esc_attr( $args['settings'] ),
                esc_attr( $args['field'] ),
                esc_attr( $value )
        );

        if ( isset( $args['validator'] ) and is_callable( $args['validator'] ) and !empty( $value ) and !empty( $args['error'] ) ) {
            $valid = call_user_func( $args['validator'], $value );
            if ( !$valid )
                printf( '<div style="color:red">%s</div>', $args['error'] );
        }
        if ( isset( $args['help'] ) ) {
            printf( '<div>%s</div>', $args['help'] );
        }
    }

    // Output a checkbox
    function settings_field_checkbox_html( $args ) {
        $checked = $this->get_option( $args['field'] ) ?? $args['default'];

        if ( $checked )
            printf( '<input type="checkbox" id="%s" name="%s[%s]" value="%s" checked>',
                    esc_attr( $args['label_for'] ),
                    esc_attr( $args['settings'] ),
                    esc_attr( $args['field'] ),
                    esc_attr( $args['value'] ?? 'on' )
            );
        else
            printf( '<input type="checkbox" id="%s" name="%s[%s]" value="%s">',
                    esc_attr( $args['label_for'] ),
                    esc_attr( $args['settings'] ),
                    esc_attr( $args['field'] ),
                    esc_attr( $args['value'] ?? 'on' )
            );

    }


    // Output a textarea
    function settings_field_textarea_html( $args ) {
        $value = $this->get_option( $args['field'] ) ?? $args['default'];

        printf( '<textarea id="%s" name="%s[%s]" cols="64" rows="12">%s</textarea>',
                esc_attr( $args['label_for'] ),
                esc_attr( $args['settings'] ),
                esc_attr( $args['field'] ),
                esc_attr( $value )
        );
    }
}
