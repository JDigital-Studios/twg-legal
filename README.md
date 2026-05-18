# twg-legal (TWG Legal)

WordPress plugin that renders TWG legal pages from the TWG Legal JSON API using the Legal SDK loader.

Reference implementation:
https://github.com/TWGWprojects/wp_legal_pages

## Includes

- Plugin: `twg-legal`
- Admin settings page: **Settings -> TWG Legal**
  - Legal Name
  - Legal Email Domain
  - Legal Last Updated
  - Custom Styles
- Plugin template routing for legal slugs via `template_include`
- Auto page creation on activation:
  - `privacy-policy` -> TWG Legal - Privacy Policy
  - `terms-of-service` -> TWG Legal - Terms of Service
  - `supply-chain-transparency` -> TWG Legal - Supply Chain Policy
- Shortcode support:
  - `[twg-legal slug="privacy-policy"]`
  - `[twg-legal slug="terms-of-service"]`
  - `[twg-legal slug="supply-chain-transparency"]`

## Render Markup

The plugin renders the required wrapper/attributes:

```html
<div data-legal-site="LEGAL_NAME_FIELD" data-legal-emaildomain="LEGAL_EMAIL_DOMAIN_FIELD">
  <div id="legal-page" data-slug="privacy-policy">
    <p id="legal-last-updated" data-last-update="LEGAL_LAST_UPDATED"></p>
    <h1 id="legal-title"></h1>
    <div id="legal-content"></div>
  </div>
</div>
```

## Installation

1. Copy the `twg-legal` plugin folder into `wp-content/plugins/`.
2. Activate **TWG Legal** in WordPress Admin -> Plugins.
3. Go to **Settings -> TWG Legal** and configure your legal site values.

## Usage

Option A: Use one of the auto-created pages by slug:
- `/privacy-policy`
- `/terms-of-service`
- `/supply-chain-transparency`

Option B: Use shortcode in any page/post:

```text
[twg-legal slug="privacy-policy"]
```

The SDK script fetches legal JSON from:
- `https://twgwprojects.github.io/wp_legal_pages/api/privacy-policy.json`
- `https://twgwprojects.github.io/wp_legal_pages/api/terms-of-service.json`
- `https://twgwprojects.github.io/wp_legal_pages/api/supply-chain-transparency.json`

## Notes

- Output and setting values are escaped/sanitized in PHP.
- Custom Styles are injected only where legal templates/shortcode are rendered.
- SDK is included in `twg-legal/assets/js/wp-page-loader.js`.
