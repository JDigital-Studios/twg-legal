<?php
/**
 * Plugin Name: TWG Legal
 * Plugin URI: https://github.com/JDigital-Studios/twg-legal
 * Description: Renders TWG legal pages via Legal SDK with admin-configurable site metadata.
 * Version: 1.0.0
 * Author: JDigital Studios
 * License: GPL-2.0-or-later
 * Text Domain: twg-legal
 */

if (!defined('ABSPATH')) {
    exit;
}

final class TWG_Legal_Plugin {
    const OPTION_KEY = 'twg_legal_settings';
    const TEMPLATE_FILE = 'twg-legal/templates/legal-template.php';

    private static $instance = null;
    private $shortcode_used = false;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_menu', array($this, 'add_settings_page'));

        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_head', array($this, 'maybe_print_custom_styles'));

        add_filter('template_include', array($this, 'template_include'));

        add_shortcode('twg-legal', array($this, 'render_shortcode'));

        register_activation_hook(__FILE__, array(__CLASS__, 'activate'));
    }

    public static function activate() {
        $pages = self::legal_pages();

        foreach ($pages as $slug => $title) {
            $existing = get_page_by_path($slug, OBJECT, 'page');

            if ($existing instanceof WP_Post) {
                wp_update_post(array(
                    'ID' => (int) $existing->ID,
                    'post_title' => $title,
                    'post_status' => 'publish',
                ));
                update_post_meta((int) $existing->ID, '_wp_page_template', self::TEMPLATE_FILE);
                update_post_meta((int) $existing->ID, '_twg_legal_slug', $slug);
                continue;
            }

            $page_id = wp_insert_post(array(
                'post_title' => $title,
                'post_name' => $slug,
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_content' => '',
            ));

            if (!is_wp_error($page_id) && $page_id > 0) {
                update_post_meta((int) $page_id, '_wp_page_template', self::TEMPLATE_FILE);
                update_post_meta((int) $page_id, '_twg_legal_slug', $slug);
            }
        }
    }

    public static function legal_pages() {
        return array(
            'privacy-policy' => 'TWG Legal - Privacy Policy',
            'terms-of-service' => 'TWG Legal - Terms of Service',
            'supply-chain-transparency' => 'TWG Legal - Supply Chain Policy',
        );
    }

    public function register_settings() {
        register_setting('twg_legal_settings_group', self::OPTION_KEY, array($this, 'sanitize_settings'));

        add_settings_section('twg_legal_main', __('TWG Legal Settings', 'twg-legal'), '__return_false', 'twg-legal');

        $this->add_field('legal_name', 'Legal Name');
        $this->add_field('legal_email_domain', 'Legal Email Domain');
        $this->add_field('legal_last_updated', 'Legal Last Updated');

        add_settings_field(
            'custom_styles',
            __('Custom Styles', 'twg-legal'),
            array($this, 'render_textarea_field'),
            'twg-legal',
            'twg_legal_main',
            array('key' => 'custom_styles')
        );
    }

    private function add_field($key, $label) {
        add_settings_field(
            $key,
            esc_html__($label, 'twg-legal'),
            array($this, 'render_text_field'),
            'twg-legal',
            'twg_legal_main',
            array('key' => $key)
        );
    }

    public function sanitize_settings($input) {
        $input = is_array($input) ? $input : array();

        $sanitized = array();
        $sanitized['legal_name'] = isset($input['legal_name']) ? sanitize_text_field($input['legal_name']) : '';
        $sanitized['legal_email_domain'] = isset($input['legal_email_domain']) ? sanitize_text_field($input['legal_email_domain']) : '';
        $sanitized['legal_last_updated'] = isset($input['legal_last_updated']) ? sanitize_text_field($input['legal_last_updated']) : '';
        $sanitized['custom_styles'] = isset($input['custom_styles']) ? sanitize_textarea_field($input['custom_styles']) : '';

        return $sanitized;
    }

    public function add_settings_page() {
        add_options_page(
            __('TWG Legal', 'twg-legal'),
            __('TWG Legal', 'twg-legal'),
            'manage_options',
            'twg-legal',
            array($this, 'render_settings_page')
        );
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('TWG Legal Settings', 'twg-legal'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('twg_legal_settings_group');
                do_settings_sections('twg-legal');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function render_text_field($args) {
        $settings = $this->get_settings();
        $key = isset($args['key']) ? (string) $args['key'] : '';
        $value = isset($settings[$key]) ? $settings[$key] : '';

        printf(
            '<input type="text" class="regular-text" name="%1$s[%2$s]" value="%3$s" />',
            esc_attr(self::OPTION_KEY),
            esc_attr($key),
            esc_attr($value)
        );
    }

    public function render_textarea_field($args) {
        $settings = $this->get_settings();
        $key = isset($args['key']) ? (string) $args['key'] : '';
        $value = isset($settings[$key]) ? $settings[$key] : '';

        printf(
            '<textarea class="large-text code" rows="8" name="%1$s[%2$s]">%3$s</textarea>',
            esc_attr(self::OPTION_KEY),
            esc_attr($key),
            esc_textarea($value)
        );
    }

    public function template_include($template) {
        if (!is_page()) {
            return $template;
        }

        global $post;
        if (!$post instanceof WP_Post) {
            return $template;
        }

        $slug = $post->post_name;
        if (!array_key_exists($slug, self::legal_pages())) {
            return $template;
        }

        $custom = plugin_dir_path(__FILE__) . 'templates/legal-template.php';
        if (file_exists($custom)) {
            return $custom;
        }

        return $template;
    }

    public function enqueue_assets() {
        if ($this->should_load_assets()) {
            wp_enqueue_script(
                'twg-legal-sdk',
                plugins_url('assets/js/wp-page-loader.js', __FILE__),
                array(),
                '1.0.0',
                true
            );
        }
    }

    private function should_load_assets() {
        if ($this->shortcode_used) {
            return true;
        }

        if (is_page()) {
            global $post;
            if ($post instanceof WP_Post && array_key_exists($post->post_name, self::legal_pages())) {
                return true;
            }
        }

        return false;
    }

    public function maybe_print_custom_styles() {
        if (!$this->should_load_assets()) {
            return;
        }

        $styles = trim((string) $this->get_setting('custom_styles', ''));
        if ('' === $styles) {
            return;
        }

        echo "\n<style id=\"twg-legal-custom-styles\">\n" . wp_strip_all_tags($styles) . "\n</style>\n";
    }

    public function render_shortcode($atts) {
        $atts = shortcode_atts(array(
            'slug' => 'privacy-policy',
        ), $atts, 'twg-legal');

        $slug = sanitize_title($atts['slug']);
        if (!array_key_exists($slug, self::legal_pages())) {
            return '';
        }

        $this->shortcode_used = true;
        wp_enqueue_script('twg-legal-sdk');

        return $this->render_legal_markup($slug);
    }

    public function render_legal_markup($slug) {
        $legal_name = (string) $this->get_setting('legal_name', '');
        $email_domain = (string) $this->get_setting('legal_email_domain', '');
        $last_updated = (string) $this->get_setting('legal_last_updated', '');

        ob_start();
        ?>
<div data-legal-site="<?php echo esc_attr($legal_name); ?>" data-legal-emaildomain="<?php echo esc_attr($email_domain); ?>">
  <div id="legal-page" data-slug="<?php echo esc_attr($slug); ?>">
    <p id="legal-last-updated" data-last-update="<?php echo esc_attr($last_updated); ?>"></p>
    <h1 id="legal-title"></h1>
    <div id="legal-content"></div>
  </div>
</div>
        <?php

        return trim((string) ob_get_clean());
    }

    public function get_settings() {
        $defaults = array(
            'legal_name' => '',
            'legal_email_domain' => '',
            'legal_last_updated' => '',
            'custom_styles' => '',
        );

        $saved = get_option(self::OPTION_KEY, array());
        if (!is_array($saved)) {
            $saved = array();
        }

        return wp_parse_args($saved, $defaults);
    }

    public function get_setting($key, $default = '') {
        $settings = $this->get_settings();
        return isset($settings[$key]) ? $settings[$key] : $default;
    }
}

TWG_Legal_Plugin::instance();
