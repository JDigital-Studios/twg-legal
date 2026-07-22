<?php
/**
 * Plugin Name: TWG Legal
 * Plugin URI: https://github.com/JDigital-Studios/twg-legal
 * Description: Renders TWG legal pages via Legal SDK with admin-configurable site metadata.
 * Version: 1.0.14
 * Author: JDigital Studios
 * License: GPL-2.0-or-later
 * Text Domain: twg-legal
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Detect when a page is loaded in an iframe embed context (e.g. Fancybox lightbox).
 *
 * Themes can use this helper to suppress site chrome (header, nav, footer, age-gate
 * scripts) when the legal page is loaded inside a modal iframe.
 *
 * Example in a theme's header.php:
 *   <?php if ( ! twg_legal_is_embed() ) : ?>
 *     <header>...</header>
 *   <?php endif; ?>
 *
 * @return bool True when the current request has ?twg_legal_embed=1.
 */
function twg_legal_is_embed() {
    return isset( $_GET['twg_legal_embed'] ) && ! is_admin();
}

final class TWG_Legal_Plugin {
    const OPTION_KEY = 'twg_legal_settings';
    const TEMPLATE_META_KEY = '_wp_page_template';

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

        add_filter('theme_page_templates', array($this, 'register_page_templates'));
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

    public static function page_template_map() {
        return array(
            'twg-legal/privacy-policy.php' => 'privacy-policy',
            'twg-legal/terms-of-service.php' => 'terms-of-service',
            'twg-legal/supply-chain-transparency.php' => 'supply-chain-transparency',
        );
    }

    public function register_page_templates($templates) {
        if (!is_array($templates)) {
            $templates = array();
        }

        $legal_pages = self::legal_pages();
        foreach (self::page_template_map() as $template_key => $legal_slug) {
            if (isset($legal_pages[$legal_slug])) {
                $templates[$template_key] = $legal_pages[$legal_slug];
            }
        }

        return $templates;
    }

    public function register_settings() {
        register_setting('twg_legal_settings_group', self::OPTION_KEY, array($this, 'sanitize_settings'));

        add_settings_section('twg_legal_main', __('TWG Legal Settings', 'twg-legal'), '__return_false', 'twg-legal');

        $this->add_field('legal_name', 'Legal Name', 'Used for {SITE} token replacement and the data-legal-site attribute. Example: Meiomi Wines.');
        $this->add_field('legal_email_domain', 'Legal Email Domain', 'Used for {EMAIL} token replacement and the data-legal-emaildomain attribute. Example: meiomi.com.');
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

    /**
     * Route legal page requests to the appropriate template.
     *
     * When ?twg_legal_embed=1 is present, serves the embed template which
     * renders only legal content with no site chrome.
     */
    public function template_include($template) {
        if (!is_page()) {
            return $template;
        }

        global $post;
        if (!$post instanceof WP_Post) {
            return $template;
        }

        $slug = '';
        $selected_template = (string) get_post_meta($post->ID, self::TEMPLATE_META_KEY, true);
        $page_template_map = self::page_template_map();

        if (isset($page_template_map[$selected_template])) {
            $slug = $page_template_map[$selected_template];
        } else {
            // Backward compatibility: canonical slug-based pages still route automatically.
            $canonical_slug = $post->post_name;
            if (array_key_exists($canonical_slug, self::legal_pages())) {
                $slug = $canonical_slug;
            }
        }

        if ('' === $slug) {
            return $template;
        }

        // Embed mode: serve stripped template with no header/footer
        if (twg_legal_is_embed()) {
            $embed_template = plugin_dir_path(__FILE__) . 'templates/embed-legal.php';
            if (file_exists($embed_template)) {
                return $embed_template;
            }
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
        $this->register_assets();

        if ($this->should_load_assets()) {
            wp_enqueue_script('twg-legal-sdk');
        }
    }

    private function register_assets() {
        wp_register_script(
            'twg-legal-sdk',
            plugins_url('assets/js/wp-page-loader.js', __FILE__),
            array(),
            '1.0.14',
            true
        );
    }

    private function should_load_assets() {
        if ($this->shortcode_used) {
            return true;
        }

        // The popup opt-in path (data-twg-legal-popup) is rendered by
        // theme template parts that are not part of post_content, so we
        // cannot reliably detect it from PHP. Enqueue the SDK on every
        // front-end singular page and on the front page so theme popups
        // hydrate regardless of which page they live on.
        if (is_singular() || is_front_page() || is_home()) {
            return true;
        }

        return false;
    }

    private function is_legal_page($post) {
        if (!$post instanceof WP_Post) {
            return false;
        }

        if (array_key_exists($post->post_name, self::legal_pages())) {
            return true;
        }

        $selected_template = (string) get_post_meta($post->ID, self::TEMPLATE_META_KEY, true);
        return array_key_exists($selected_template, self::page_template_map());
    }

    public function maybe_print_custom_styles() {
        if (!$this->should_load_assets()) {
            return;
        }

        echo "\n<style id=\"twg-legal-default-mobile-label-styles\">\n@media (max-width: 767px) {\n  .legal-table td::before {\n    border: 1px solid inherit;\n    color: inherit;\n  }\n}\n</style>\n";

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
        $this->register_assets();
        wp_enqueue_script('twg-legal-sdk');

        return $this->render_legal_markup($slug);
    }

    public function render_legal_markup($slug) {
        $legal_name = (string) $this->get_setting('legal_name', '');
        $email_domain = (string) $this->get_setting('legal_email_domain', '');
        ob_start();
        ?>
<div data-legal-site="<?php echo esc_attr($legal_name); ?>" data-legal-emaildomain="<?php echo esc_attr($email_domain); ?>">
  <div
    id="legal-page"
    data-twg-legal-page
    data-slug="<?php echo esc_attr($slug); ?>"
    data-legal-site="<?php echo esc_attr($legal_name); ?>"
    data-legal-emaildomain="<?php echo esc_attr($email_domain); ?>"
  >
    <p id="legal-last-updated" data-twg-legal-last-updated></p>
    <h1 id="legal-title" data-twg-legal-title></h1>
    <div id="legal-content" data-twg-legal-content></div>
  </div>
</div>
        <?php

        return trim((string) ob_get_clean());
    }

    public function get_settings() {
        $defaults = array(
            'legal_name' => '',
            'legal_email_domain' => '',
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