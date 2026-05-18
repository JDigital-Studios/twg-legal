<?php
/**
 * Template for the TWG Legal Privacy Policy page.
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

$plugin = TWG_Legal_Plugin::instance();
echo $plugin->render_legal_markup('privacy-policy'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

get_footer();
