<?php
/**
 * Plugin Name: TWG Legal
 * Plugin URI: https://github.com/JDigital-Studios/twg-legal
 * Description: Renders TWG legal pages via Legal SDK with admin-configurable site metadata.
 * Version: 1.0.2
 * Author: JDigital Studios
 * License: GPL-2.0-or-later
 * Text Domain: twg-legal
 */

if (!defined('ABSPATH')) {
    exit;
}

final class TWG_Legal_Plugin {
    const OPTION_KEY = 'twg_legal_settings';

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
    }

    public static function legal_pages() {
        return array(
            'privacy-policy' => 'TWG Legal - Privacy Policy',
            'terms-of-service' => 'TWG Legal - Terms of Service',
            'supply-chain-transparency' => 'TWG Legal - Supply Chain Transparency',
        );
    }

    public static function legal_templates() {
        return array(
            'privacy-policy' => 'templates/privacy-policy.php',
            'terms-of-service' => 'templates/terms-of-service.php',
            'supply-chain-transparency' => 'templates/supply-chain-transparency.php',
        );
    }

    public function register_settings() {
        register_setting('twg_legal_settings_group', self::OPTION_KEY, array($this, 'sanitize_settings'));

        add_settings_section('twg_legal_main', __('TWG Legal Settings', 'twg-legal'), '__return_false', 'twg-legal');

        $this->add_field('legal_name', 'Legal Name', 'Used for {SITE} token replacement and the data-legal-site attribute. Example: Meiomi Wines.');
        $this->add_field('legal_email_domain', 'Legal Email Domain', 'Used for {EMAIL} token replacement and the data-legal-emaildomain attribute. Example: meiomi.com.');
        $this->add_field('legal_last_updated', 'Legal Last Updated', 'Default value placed in the data-last-update attribute on legal pages. Example: May 18, 2026.');

        add_settings_field(
            'custom_styles',
            __('Custom Styles', 'twg-legal'),
            array($this, 'render_textarea_field'),
            'twg-legal',
            'twg_legal_main',
            array(
                'key' => 'custom_styles',
                'description' => 'Optional CSS injected only on TWG Legal template and shortcode renders.',
            )
        );
    }

    private function add_field($key, $label, $description = '') {
        add_settings_field(
            $key,
            esc_html__($label, 'twg-legal'),
            array($this, 'render_text_field'),
            'twg-legal',
            'twg_legal_main',
            array(
                'key' => $key,
                'description' => $description,
            )
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
        $description = isset($args['description']) ? (string) $args['description'] : '';

        printf(
            '<input type="text" class="regular-text" name="%1$s[%2$s]" value="%3$s" />',
            esc_attr(self::OPTION_KEY),
            esc_attr($key),
            esc_attr($value)
        );

        if ('' !== $description) {
            printf('<p class="description">%s</p>', esc_html($description));
        }
    }

    public function render_textarea_field($args) {
        $settings = $this->get_settings();
        $key = isset($args['key']) ? (string) $args['key'] : '';
        $value = isset($settings[$key]) ? $settings[$key] : '';
        $description = isset($args['description']) ? (string) $args['description'] : '';

        printf(
            '<textarea class="large-text code" rows="8" name="%1$s[%2$s]">%3$s</textarea>',
            esc_attr(self::OPTION_KEY),
            esc_attr($key),
            esc_textarea($value)
        );

        if ('' !== $description) {
            printf('<p class="description">%s</p>', esc_html($description));
        }
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

        $templates = self::legal_templates();
        if (!isset($templates[$slug])) {
            return $template;
        }

        $custom = plugin_dir_path(__FILE__) . $templates[$slug];
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
                '1.0.2',
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
