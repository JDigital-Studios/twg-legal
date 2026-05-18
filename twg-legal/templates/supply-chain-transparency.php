<?php
/**
 * Template for the TWG Legal Supply Chain Transparency page.
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

$plugin = TWG_Legal_Plugin::instance();
echo $plugin->render_legal_markup('supply-chain-transparency'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

get_footer();
