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
- **Popup / lightbox / age-gate opt-in support** (v1.0.8+)
  - Plugin can hydrate a modal container directly so themes do not need custom JSON loaders

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

### Option A: Use page template dropdown on any page

1. Edit or create a WordPress page.
2. In the Page Attributes / Template selector, choose one of:
   - `TWG Legal - Privacy Policy`
   - `TWG Legal - Terms of Service`
   - `TWG Legal - Supply Chain Transparency`
3. Publish/update the page.

This works on arbitrary page slugs (for example `/legal/privacy`), not only canonical slugs.

### Option B: Backward-compatible slug routing (legacy behavior)

If a page slug is one of the canonical legal slugs below, the plugin still auto-routes to the corresponding legal template even if no template is selected:
- `/privacy-policy`
- `/terms-of-service`
- `/supply-chain-transparency`

### Option C: Use shortcode in any page/post

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

## Age Gate / Fancybox / Popup Integration

If your theme opens legal content from an age gate or a Fancybox lightbox, use one of the two approaches below.

### Requirements

- **Plugin v1.0.11+** is required for popup containers rendered from theme template parts (e.g. `age-gate.php`).
- **Plugin v1.0.12+** is required if you want the "Last Updated" date to appear inside the popup.

### Approach 1: Iframe the real legal page (recommended)

Point Fancybox to the legal page permalink with a custom embed query flag, and strip site chrome for embed requests.

1. **Update age-gate links** to real permalinks with a custom query flag:
   ```html
   <a href="/terms-of-service/?age_gate_embed=1" data-fancybox data-type="iframe">Terms of Service</a>
   <a href="/privacy-policy/?age_gate_embed=1" data-fancybox data-type="iframe">Privacy Policy</a>
   ```
   > Do **not** use `?embed=1` — that triggers WordPress core oEmbed behavior and will render the wrong template.

2. **Add embed detection in `functions.php`**:
   ```php
   function theme_is_age_gate_legal_embed() {
       return is_page() && isset($_GET['age_gate_embed']);
   }
   ```

3. **Suppress site chrome** for embed requests:
   - `header.php` — skip nav/header wrappers when `theme_is_age_gate_legal_embed()` is true.
   - `footer.php` — skip footer/newsletter blocks.
   - `page.php` or the legal page template — use a stripped layout.
   - Hide any off-canvas overlays or global wrappers.

### Approach 2: Plugin popup opt-in (inline / cloned DOM)

Use this when Fancybox must use cloned/local markup rather than an iframe, or when you want the plugin JS to drive the popup content directly.

1. **Add the opt-in attribute** to your popup container:
   ```html
   <div
     class="legal-popup-content"
     data-twg-legal-popup
     data-legal-slug="terms-of-service"
     data-legal-site="Your Site Name"
     data-legal-emaildomain="yoursite"
   >
   </div>
   ```

   | Attribute | Required | Purpose |
   | --- | --- | --- |
   | `data-twg-legal-popup` | **Yes** | Tells the plugin to hydrate this container. |
   | `data-legal-slug` | **Yes** (or `data-slug`) | Which legal page to load. |
   | `data-legal-site` | Recommended | Resolves `{SITE}` tokens. |
   | `data-legal-emaildomain` | Recommended | Resolves `{EMAIL}` tokens. |

2. **Source site/email from plugin settings** (recommended in PHP):
   ```php
   <?php $twg = get_option('twg_legal_settings'); ?>
   <div
     class="legal-popup-content"
     data-twg-legal-popup
     data-legal-slug="terms-of-service"
     data-legal-site="<?php echo esc_attr($twg['legal_name'] ?? 'Your Site'); ?>"
     data-legal-emaildomain="<?php echo esc_attr($twg['legal_email_domain'] ?? 'example.com'); ?>"
   >
   </div>
   ```

3. **Remove the theme's custom JSON loader.** If your theme has a `loadLegalPopupContent()` or similar function that fetches the TWG API directly, delete it — the plugin renderer now handles fetching, token replacement, and table rendering.

4. **What the plugin creates inside the container** (v1.0.12+):
   ```
   .legal-popup-content
   ├── <h1 data-twg-legal-popup-title>       (auto-created)
   ├── <p data-twg-legal-popup-last-updated> (auto-created)
   └── <div data-twg-legal-popup-body>       (auto-created, re-populated each open)
   ```

### Popup diagnostic checklist

| Check | How |
|---|---|
| SDK is on the page | Network tab → look for `wp-page-loader.js` |
| Popup container has children after open | Inspect → should see title + last-updated + body slots |
| No raw `{SITE}` / `{EMAIL}` | Read popup text |
| Title appears | "Privacy Policy" / "Terms of Service" |
| Last Updated appears (v1.0.12+) | Between title and body content |
| Re-open doesn't duplicate | Close and reopen → same child count, no double content |
| Standalone legal page still works | Visit `/terms-of-service/` directly |

## Practical Fancybox / Iframe Implementation Guide

This section documents a real-world age-gate implementation (Franzia Wines) using Fancybox iframes and TWG Legal. Use it as a reference for future sites.

### The problem

Opening a TWG Legal page inside a Fancybox iframe loads the **entire WordPress site** — header, navigation, off-canvas menu, footer, newsletter signup, and the age gate itself (since `header.php` runs inside the iframe and has no cookie context).

Result: a cluttered popup with duplicate age gates, site chrome, and broken UX.

### The solution

Use a **query-flag + chrome stripping** pattern. The iframe URL carries `?age_gate_embed=1` and the theme suppresses site chrome for those requests.

---

#### Step 1: Update age-gate links

In your age gate template (or `header.php` / `footer.php`), change links from static `.html` paths or `data-src` attributes to **real WordPress permalinks** with the embed flag:

```html
<!-- BEFORE (broken - 404s or wrong content) -->
<a data-fancybox data-src="/terms-of-service.html">Terms of Service</a>

<!-- AFTER (correct) -->
<a data-fancybox data-type="iframe"
   href="<?php echo get_site_url(); ?>/terms-of-service/?age_gate_embed=1">
   Terms of Service
</a>
```

Use **standard Fancybox attributes** (`data-fancybox` + `data-type="iframe"` + `href`). Custom opt-in attributes are not supported by the Fancybox handler.

> ⚠️ Do **not** use `?embed=1` — that triggers WordPress core oEmbed behavior.

---

#### Step 2: Add embed detection in `functions.php`

```php
/**
 * Detect when a page is loaded inside the age-gate Fancybox iframe.
 */
function my_theme_is_age_gate_embed() {
    return isset( $_GET['age_gate_embed'] ) && ! is_admin();
}
```

---

#### Step 3: Strip site chrome in `header.php`

The off-canvas wrapper, navigation, and site header should be suppressed for embed requests. Wrap them in the conditional:

```php
<?php if ( ! function_exists( 'my_theme_is_age_gate_embed' ) || ! my_theme_is_age_gate_embed() ) : ?>
<div class="off-canvas-wrapper">
  <?php get_template_part( 'parts/content', 'offcanvas' ); ?>
  <header class="header" role="banner">
    <?php get_template_part( 'parts/nav', 'title-bar' ); ?>
  </header>
<?php endif; ?>
```

---

#### Step 4: Strip site chrome in `footer.php`

The footer, newsletter signup, and off-canvas closing divs should be suppressed. Also suppress any age-gate script that lives in `footer.php`:

```php
<?php if ( ! function_exists( 'my_theme_is_age_gate_embed' ) || ! my_theme_is_age_gate_embed() ) : ?>
  <footer class="footer" role="contentinfo">
    <!-- footer content -->
  </footer>
  </div> <!-- end .off-canvas-content -->
  </div> <!-- end .off-canvas-wrapper -->
<?php endif; ?>
```

If your footer contains age-gate JavaScript (cookie plugin, `av_legality_check`, `av_showmodal`, etc.), wrap the entire script block:

```php
<?php if ( ! function_exists( 'my_theme_is_age_gate_embed' ) || ! my_theme_is_age_gate_embed() ) : ?>
  <script>
    // jQuery Cookie Plugin + av_legality_check + av_showmodal + ...
  </script>
<?php endif; ?>
```

---

#### Step 5: Prevent duplicate age gates (critical)

If your theme has age-gate code in **both** `header.php` and `footer.php`, the iframe will show a nested gate because the iframe has no access to the parent page's cookie.

**Best practice: put the age gate in only ONE file.**

Recommended: keep the complete age gate in `footer.php` only, and remove it from `header.php`. `footer.php` typically has the richer version (logo, heading, explanatory text, cookie consent).

If you cannot consolidate, add iframe detection inside the age gate script:

```javascript
av_legality_check = function() {
  // Inside iframe: auto-pass to prevent nested gate
  if (window.parent !== window) {
    $.cookie('is_legal', 'yes', { expires: 30, path: '/' });
    return;
  }
  // Normal behavior on parent page
  if ($.cookie('is_legal') == "yes") { return; }
  av_showmodal();
};
```

---

### Troubleshooting checklist

| Symptom | Cause | Fix |
|---|---|---|
| **Iframe loads 404** | Links point to `.html` files or wrong paths | Use `get_site_url() . '/terms-of-service/?age_gate_embed=1'` |
| **Iframe shows full site** | No chrome stripping | Add `my_theme_is_age_gate_embed()` conditional to header/footer |
| **Duplicate age gates** | Both header.php and footer.php create modals | Consolidate to one file, or add `window.parent !== window` check |
| **Missing logo/text in gate** | Minimal gate in header.php, full gate blocked | Remove gate from header.php, keep only in footer.php |
| **Last Updated date missing** | Plugin version < 1.0.12 | Upgrade to twg-legal v1.0.12+ |
| **Raw `{SITE}` tokens** | Missing `data-legal-site` attribute | Ensure the legal page template renders the wrapper with site name |

---

### Complete minimal example

A theme implementing the iframe approach needs only these three changes:

**`functions.php`**
```php
function my_theme_is_age_gate_embed() {
    return isset( $_GET['age_gate_embed'] ) && ! is_admin();
}
```

**Age-gate link**
```html
<a data-fancybox data-type="iframe"
   href="<?php echo get_site_url(); ?>/terms-of-service/?age_gate_embed=1">
   Terms of Service
</a>
```

**`header.php`** (chrome suppression)
```php
<?php if ( ! my_theme_is_age_gate_embed() ) : ?>
  <div class="off-canvas-wrapper">
    <header>...</header>
  </div>
<?php endif; ?>
```

**`footer.php`** (chrome + script suppression)
```php
<?php if ( ! my_theme_is_age_gate_embed() ) : ?>
  <footer>...</footer>
  <!-- age gate script only here, not in header.php -->
<?php endif; ?>
```

## Notes


- Output and setting values are escaped/sanitized in PHP.
- Custom Styles are injected only where legal templates/shortcode are rendered.
- Page template dropdown and slug-based routing can be used together; slug routing is kept for backward compatibility.
- SDK is included in `twg-legal/assets/js/wp-page-loader.js`.
- The SDK no-ops when no legal containers exist, so enqueueing it on all front-end pages (v1.0.11+) is safe and low-cost.
