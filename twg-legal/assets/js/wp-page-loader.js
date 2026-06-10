// Legal SDK Script
(() => {
  const API_BASE = "https://twgwprojects.github.io/wp_legal_pages/api";
  const LEGAL_PAGE_SELECTOR = '[data-twg-legal-page], #legal-page';
  const LEGAL_POPUP_SELECTOR = '[data-twg-legal-popup]';

  /** Scroll to in-page hash target (e.g. /privacy-policy/#ccpa) with fixed header offset. */
  function scrollLegalPageHashWithOffset(offsetPx = 100) {
    const raw = window.location.hash;
    if (!raw || raw.length < 2) return;
    const id = decodeURIComponent(raw.slice(1)).replace(/^#/, "");
    if (!id) return;
    const el = document.getElementById(id);
    if (!el) return;
    const top = el.getBoundingClientRect().top + window.scrollY - offsetPx;
    window.scrollTo({ top: Math.max(0, top), behavior: "auto" });
  }

  function replaceTokens(str, { site, email }) {
    return String(str || "")
      .replaceAll("{SITE}", site)
      .replaceAll("{EMAIL}", email);
  }

  /**
   * Escape HTML helper
   */
  function escapeHtml(str) {
    return String(str)
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");
  }

  /**
   * Render legal table block
   */
  function renderLegalTable(block, { site, email }) {
    const wrapper = document.createElement("div");
    wrapper.className = "legal-table-wrapper";

    if (block.title) {
      const h = document.createElement("h3");
      h.textContent = replaceTokens(block.title, { site, email });
      wrapper.appendChild(h);
    }

    const table = document.createElement("table");
    table.className = "legal-table";

    const columns = Array.isArray(block.columns) ? block.columns : [];
    const rows = Array.isArray(block.rows) ? block.rows : [];

    const thead = document.createElement("thead");
    const trHead = document.createElement("tr");

    columns.forEach((col) => {
      const th = document.createElement("th");
      th.textContent = col;
      trHead.appendChild(th);
    });

    thead.appendChild(trHead);
    table.appendChild(thead);

    const tbody = document.createElement("tbody");

    rows.forEach((row) => {
      const tr = document.createElement("tr");

      columns.forEach((col) => {
        const td = document.createElement("td");
        const columnLabel = String(col || "");
        td.setAttribute("data-label", columnLabel);

        const labelSpan = document.createElement("span");
        labelSpan.className = "legal-table-cell-label";
        labelSpan.textContent = columnLabel;
        td.appendChild(labelSpan);

        const value = row ? row[col] : "";

        if (
          col === "Category" &&
          value &&
          typeof value === "object" &&
          !Array.isArray(value)
        ) {
          const strong = document.createElement("strong");
          strong.textContent = replaceTokens(value.text || "", { site, email });
          td.appendChild(strong);

          if (Array.isArray(value.examples) && value.examples.length) {
            const ex = document.createElement("div");
            ex.className = "legal-table-examples";

            const examplesText = replaceTokens(value.examples.join(", "), {
              site,
              email,
            });

            ex.innerHTML = `<em>Examples:</em> ${escapeHtml(examplesText)}`;
            td.appendChild(ex);
          }
        } else if (Array.isArray(value)) {
          const ul = document.createElement("ul");

          value.forEach((item) => {
            const li = document.createElement("li");
            li.innerHTML = replaceTokens(item || "", { site, email });
            ul.appendChild(li);
          });

          td.appendChild(ul);
        } else {
          td.innerHTML = replaceTokens(value || "", { site, email });
        }

        tr.appendChild(td);
      });

      tbody.appendChild(tr);
    });

    table.appendChild(tbody);
    wrapper.appendChild(table);

    return wrapper;
  }

  /**
   * Render legal blocks into a target container.
   * Used by both the full legal page and the popup variant.
   *
   * For popups (`data-twg-legal-popup` containers), the plugin guarantees
   * two child slots inside the container:
   *   - a title element (auto-created h1 if not present)
   *   - a body element (auto-created div if not present)
   * Body content is rendered inside the body slot. Title and body slots
   * are deterministic and re-render-safe, so the popup can be opened
   * multiple times without losing the title or accumulating duplicates.
   */
  function renderLegalBlocks(container, page, { site, email }) {
    const isPopup = container.matches(LEGAL_POPUP_SELECTOR);

    // For full pages, content lives in a child #legal-content / [data-twg-legal-content].
    // For popups, the plugin manages an auto-created body slot.
    let contentEl;
    if (isPopup) {
      contentEl = ensurePopupBody(container);
    } else {
      contentEl =
        container.querySelector('[data-twg-legal-content], #legal-content') ||
        container;
    }

    // Title: resolve (or auto-create for popups).
    const titleEl = ensurePopupTitle(container, isPopup);
    if (page.title && titleEl) {
      titleEl.textContent = page.title;
    }

    // Last Updated: full pages use the explicit slot in markup; popups
    // get an auto-created slot so the API date always shows in the modal.
    const updatedEl = ensureLastUpdatedSlot(container, isPopup);
    if (page.lastUpdated && updatedEl) {
      updatedEl.textContent = `Last Updated: ${page.lastUpdated}`;
    }

    // Clear the body slot. This is safe across re-renders and is bounded
    // to the body element, never the whole container.
    contentEl.replaceChildren();

    (page.content || []).forEach((block) => {
      let el;

      switch (block.type) {
        case "heading": {
          el = document.createElement(`h${block.level || 2}`);
          el.textContent = block.text || "";
          break;
        }

        case "paragraph": {
          el = document.createElement("p");
          if (block.id) el.id = block.id;
          el.innerHTML = replaceTokens(block.text || "", { site, email });
          if (block.bold) el.style.fontWeight = "bold";
          break;
        }

        case "list": {
          el = document.createElement("ul");
          (block.items || []).forEach((item) => {
            const li = document.createElement("li");
            li.innerHTML = replaceTokens(item || "", { site, email });
            el.appendChild(li);
          });
          break;
        }

        case "table": {
          el = renderLegalTable(block, { site, email });
          break;
        }

        default:
          return;
      }

      contentEl.appendChild(el);
    });

    // Hash scroll only makes sense for full legal pages.
    if (container.matches(LEGAL_PAGE_SELECTOR)) {
      requestAnimationFrame(() => {
        requestAnimationFrame(() => scrollLegalPageHashWithOffset(100));
      });
    }
  }

  /**
   * Ensure the popup container has a body slot.
   * Returns the body element. Re-entrant safe: clears any pre-existing
   * "Loading…" or stale content the theme may have set.
   */
  function ensurePopupBody(container) {
    let body = container.querySelector("[data-twg-legal-popup-body]");
    if (!body) {
      body = document.createElement("div");
      body.setAttribute("data-twg-legal-popup-body", "");
      container.appendChild(body);
    }
    return body;
  }

  /**
   * Ensure the popup container has a title slot.
   * Returns the title element, or null if not in popup mode.
   */
  function ensurePopupTitle(container, isPopup) {
    const existing = container.querySelector(
      '[data-twg-legal-title], #legal-title',
    );
    if (existing) return existing;
    if (!isPopup) return null;

    const title = document.createElement("h1");
    title.className = "twg-legal-popup-title";
    title.setAttribute("data-twg-legal-title", "");
    container.insertBefore(title, container.firstChild);
    return title;
  }

  /**
   * Ensure the popup container has a Last Updated slot.
   * - Full pages: use the existing `[data-twg-legal-last-updated]` /
   *   `#legal-last-updated` element from the plugin's render_legal_markup().
   * - Popups: auto-create a `<p data-twg-legal-last-updated>` between the
   *   title and body slots so the API's `lastUpdated` date always shows.
   */
  function ensureLastUpdatedSlot(container, isPopup) {
    const existing = container.querySelector(
      '[data-twg-legal-last-updated], #legal-last-updated',
    );
    if (existing) return existing;
    if (!isPopup) return null;

    const p = document.createElement("p");
    p.className = "twg-legal-popup-last-updated";
    p.setAttribute("data-twg-legal-last-updated", "");

    // Place after the title slot (if any) and before the body slot (if any).
    const body = container.querySelector("[data-twg-legal-popup-body]");
    if (body) {
      container.insertBefore(p, body);
    } else {
      container.appendChild(p);
    }
    return p;
  }

  /**
   * Resolve site / email tokens for any legal container
   * (full page, shortcode, or popup).
   */
  function resolveSiteConfig(container) {
    const legalRoot =
      container.closest("[data-legal-site], #twg-legal") || container.parentElement;
    const site =
      container.dataset.legalSite || legalRoot?.dataset?.legalSite || "example";
    const email =
      container.dataset.legalEmaildomain ||
      legalRoot?.dataset?.legalEmaildomain ||
      "example.com";
    return { site, email };
  }

  /**
   * Fetch a legal page JSON and render it into the given container.
   * Shared by the full-page and popup init flows.
   */
  function fetchAndRenderLegal(container, slug, siteConfig) {
    container.dataset.twgLegalLoading = "true";
    // Phase flag prevents the MutationObserver from re-entering
    // init*() during the in-progress mutation sequence.
    container.dataset.twgLegalPhase = "loading";

    fetch(`${API_BASE}/${slug}.json`)
      .then((res) => {
        if (!res.ok) throw new Error(`Failed to load legal JSON: ${res.status}`);
        return res.json();
      })
      .then((page) => {
        container.dataset.twgLegalPhase = "rendering";
        try {
          renderLegalBlocks(container, page, siteConfig);
          container.dataset.twgLegalLoaded = "true";
        } finally {
          delete container.dataset.twgLegalPhase;
        }
      })
      .catch((err) => {
        console.error("Legal page error:", err);
        // If the container is a popup, surface the error inline via the
        // dedicated body slot so we don't clobber the title.
        if (container.matches(LEGAL_POPUP_SELECTOR)) {
          const body = ensurePopupBody(container);
          body.replaceChildren();
          const p = document.createElement("p");
          p.textContent = "Unable to load content. Please try again.";
          body.appendChild(p);
        }
      })
      .finally(() => {
        delete container.dataset.twgLegalLoading;
      });
  }

  /**
   * Initialize a full legal-page container
   * (data-twg-legal-page or #legal-page, with data-slug).
   */
  function initLegalPage(container) {
    if (!(container instanceof HTMLElement)) return;
    if (container.dataset.twgLegalPhase) return;

    const slug = container.dataset.slug;
    if (!slug || container.dataset.twgLegalLoaded === "true") return;
    if (container.dataset.twgLegalLoading === "true") return;

    fetchAndRenderLegal(container, slug, resolveSiteConfig(container));
  }

  /**
   * Initialize a popup-style legal container
   * (data-twg-legal-popup with data-legal-slug).
   *
   * This is a strict opt-in path; popup markup must declare
   * data-twg-legal-popup to use it.
   */
  function initLegalPopup(container) {
    if (!(container instanceof HTMLElement)) return;
    if (container.dataset.twgLegalPhase) return;

    // Popup slugs may live on data-legal-slug (matches the existing
    // Woodbridge / cupcake conventions) or on data-slug as a fallback.
    const slug = container.dataset.legalSlug || container.dataset.slug;
    if (!slug || container.dataset.twgLegalLoaded === "true") return;
    if (container.dataset.twgLegalLoading === "true") return;

    // Render a loading state into the body slot (reuses it if it exists
    // from a prior open). We do NOT wipe the container — the title slot
    // belongs to the plugin and stays put across re-opens.
    const body = ensurePopupBody(container);
    body.replaceChildren();
    const p = document.createElement("p");
    p.textContent = "Loading…";
    body.appendChild(p);

    fetchAndRenderLegal(container, slug, resolveSiteConfig(container));
  }

  function initAllLegalPages(root = document) {
    root.querySelectorAll(LEGAL_PAGE_SELECTOR).forEach(initLegalPage);
    root.querySelectorAll(LEGAL_POPUP_SELECTOR).forEach(initLegalPopup);
  }

  document.addEventListener("DOMContentLoaded", () => {
    initAllLegalPages();
    window.addEventListener("hashchange", () => scrollLegalPageHashWithOffset(100));
  });

  const observer = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
      mutation.addedNodes.forEach((node) => {
        if (!(node instanceof HTMLElement)) return;
        if (node.dataset && node.dataset.twgLegalPhase) return;
        if (node.matches(LEGAL_PAGE_SELECTOR)) {
          initLegalPage(node);
        }
        if (node.matches(LEGAL_POPUP_SELECTOR)) {
          initLegalPopup(node);
        }
        initAllLegalPages(node);
      });
    });
  });

  if (document.body) {
    observer.observe(document.body, { childList: true, subtree: true });
  } else {
    document.addEventListener("DOMContentLoaded", () => {
      observer.observe(document.body, { childList: true, subtree: true });
    });
  }
})();
