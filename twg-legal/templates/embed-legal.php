<?php
/**
 * Template for embedded legal pages (iframe / Fancybox lightbox).
 *
 * This template renders ONLY the legal content with no site chrome:
 * no header, no footer, no navigation, no off-canvas menus, no sidebars.
 *
 * It is served automatically when a legal page is requested with
 * ?twg_legal_embed=1. Themes do NOT need to modify header.php/footer.php
 * for embed support when using this plugin template.
 *
 * Example usage in a Fancybox link:
 *   <a data-fancybox data-type="iframe"
 *      href="/terms-of-service/?twg_legal_embed=1">
 *      Terms of Service
 *   </a>
 */

if (!defined('ABSPATH')) {
    exit;
}

$plugin = TWG_Legal_Plugin::instance();
$slug   = '';

// Resolve slug the same way template_include() does.
$selected_template = (string) get_post_meta(get_the_ID(), TWG_Legal_Plugin::TEMPLATE_META_KEY, true);
$page_template_map = TWG_Legal_Plugin::page_template_map();

if (isset($page_template_map[$selected_template])) {
    $slug = $page_template_map[$selected_template];
} else {
    $canonical_slug = get_post_field('post_name', get_the_ID());
    if (array_key_exists($canonical_slug, TWG_Legal_Plugin::legal_pages())) {
        $slug = $canonical_slug;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php wp_head(); ?>
<style>
  /* Minimal embed reset: hide anything the theme injects into wp_head/wp_footer */
  html, body { margin: 0; padding: 0; background: #fff; }
  body > *:not([data-legal-site]):not(script):not(style) { display: none !important; }
</style>
</head>
<body <?php body_class('twg-legal-embed'); ?>>
  <?php
  if ('' !== $slug) {
      echo $plugin->render_legal_markup($slug); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
  }
  ?>
<?php wp_footer(); ?>
</body>
</html>