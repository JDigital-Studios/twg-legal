# twg-legal (TWG Legal)

WordPress plugin that renders TWG legal pages from the TWG Legal JSON API using the Legal SDK loader.

Reference implementation:
https://github.com/TWGWprojects/wp_legal_pages

## Includes

- Plugin: `twg-legal`
- Admin settings page: **Settings -> TWG Legal**
  - Legal Name
  - Legal Email Domain
  - Custom Styles
- Plugin template routing via:
  - page template dropdown selection on any WordPress page
  - canonical legal slug fallback via `template_include` (backward compatible)
- Page template dropdown options:
  - `TWG Legal - Privacy Policy`
  - `TWG Legal - Terms of Service`
  - `TWG Legal - Supply Chain Transparency`
- Slug-based legal templates:
  - `privacy-policy` -> `templates/privacy-policy.php`
  - `terms-of-service` -> `templates/terms-of-service.php`
  - `supply-chain-transparency` -> `templates/supply-chain-transparency.php`
- Shortcode support:
  - `[twg-legal slug="privacy-policy"]`
  - `[twg-legal slug="terms-of-service"]`
  - `[twg-legal slug="supply-chain-transparency"]`

## Render Markup

The plugin renders the required wrapper/attributes:

```html
<div data-legal-site="LEGAL_NAME_FIELD" data-legal-emaildomain="LEGAL_EMAIL_DOMAIN_FIELD">
  <div id="legal-page" data-slug="privacy-policy">
    <p id="legal-last-updated"></p>
    <h1 id="legal-title"></h1>
    <div id="legal-content"></div>
  </div>
</div>
```

Note: the plugin no longer includes a "Legal Last Updated" admin setting and no longer renders a `data-last-update` attribute on `#legal-last-updated`.

## Installation

1. Copy the `twg-legal` plugin folder into `wp-content/plugins/`.
2. Activate **TWG Legal** in WordPress Admin -> Plugins.
3. Go to **Settings -> TWG Legal** and configure your legal site values.
4. (Recommended for backward compatibility) Manually create/publish these WordPress pages (exact slugs):
   - `privacy-policy`
   - `terms-of-service`
   - `supply-chain-transparency`

## Usage

Option A: Use page template dropdown on any page

1. Edit or create a WordPress page.
2. In the Page Attributes / Template selector, choose one of:
   - `TWG Legal - Privacy Policy`
   - `TWG Legal - Terms of Service`
   - `TWG Legal - Supply Chain Transparency`
3. Publish/update the page.

This works on arbitrary page slugs (for example `/legal/privacy`), not only canonical slugs.

Option B: Backward-compatible slug routing (legacy behavior)

If a page slug is one of the canonical legal slugs below, the plugin still auto-routes to the corresponding legal template even if no template is selected:
- `/privacy-policy`
- `/terms-of-service`
- `/supply-chain-transparency`

Option C: Use shortcode in any page/post:

```text
[twg-legal slug="privacy-policy"]
```

Additional shortcode examples:

```text
[twg-legal slug="terms-of-service"]
[twg-legal slug="supply-chain-transparency"]
```

Shortcode caveats:
- `slug` must be one of: `privacy-policy`, `terms-of-service`, `supply-chain-transparency`.
- The plugin now pre-detects `[twg-legal]` in singular post content and enqueues the SDK automatically.
- The shortcode callback also force-registers/enqueues the SDK as a fallback, so shortcode rendering remains reliable on arbitrary pages.

The SDK script fetches legal JSON from:
- `https://twgwprojects.github.io/wp_legal_pages/api/privacy-policy.json`
- `https://twgwprojects.github.io/wp_legal_pages/api/terms-of-service.json`
- `https://twgwprojects.github.io/wp_legal_pages/api/supply-chain-transparency.json`

## Notes

- Output and setting values are escaped/sanitized in PHP.
- Custom Styles are injected only where legal templates/shortcode are rendered.
- Page template dropdown and slug-based routing can be used together; slug routing is kept for backward compatibility.
- SDK is included in `twg-legal/assets/js/wp-page-loader.js`.
