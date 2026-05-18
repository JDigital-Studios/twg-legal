<?php
/**
 * Template for the TWG Legal Terms of Service page.
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

$plugin = TWG_Legal_Plugin::instance();
echo $plugin->render_legal_markup('terms-of-service'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

get_footer();
