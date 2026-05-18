<?php
/**
 * Template for TWG Legal plugin pages.
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

global $post;
$slug = ($post instanceof WP_Post) ? $post->post_name : 'privacy-policy';

$plugin = TWG_Legal_Plugin::instance();
echo $plugin->render_legal_markup($slug); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

get_footer();
