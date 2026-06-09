// Legal SDK Script
(() => {
  const API_BASE = "https://twgwprojects.github.io/wp_legal_pages/api";
  const LEGAL_PAGE_SELECTOR = '[data-twg-legal-page], #legal-page';

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

  function getScopedNode(container, selector, legacyId) {
    return (
      container.querySelector(selector) ||
      Array.from(container.children).find((node) => node.id === legacyId) ||
      null
    );
  }

  function renderLegalPage(container, page, siteConfig) {
    const titleEl = getScopedNode(container, '[data-twg-legal-title]', 'legal-title');
    if (titleEl) {
      titleEl.textContent = page.title || "";
    }

    const updatedEl = getScopedNode(
      container,
      '[data-twg-legal-last-updated]',
      'legal-last-updated',
    );
    const lastUpdatedValue = page.lastUpdated || "";
    if (updatedEl) {
      updatedEl.textContent = lastUpdatedValue
        ? `Last Updated: ${lastUpdatedValue}`
        : "";
    }

    const contentEl = getScopedNode(container, '[data-twg-legal-content]', 'legal-content');
    if (!contentEl) return;

    contentEl.innerHTML = "";

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
          el.innerHTML = replaceTokens(block.text || "", siteConfig);
          if (block.bold) el.style.fontWeight = "bold";
          break;
        }

        case "list": {
          el = document.createElement("ul");
          (block.items || []).forEach((item) => {
            const li = document.createElement("li");
            li.innerHTML = replaceTokens(item || "", siteConfig);
            el.appendChild(li);
          });
          break;
        }

        case "table": {
          el = renderLegalTable(block, siteConfig);
          break;
        }

        default:
          return;
      }

      contentEl.appendChild(el);
    });

    requestAnimationFrame(() => {
      requestAnimationFrame(() => scrollLegalPageHashWithOffset(100));
    });
  }

  function initLegalPage(container) {
    if (!(container instanceof HTMLElement)) return;

    const slug = container.dataset.slug;
    if (!slug || container.dataset.twgLegalLoaded === "true") return;
    if (container.dataset.twgLegalLoading === "true") return;

    const legalRoot =
      container.closest("[data-legal-site], #twg-legal") || container.parentElement;
    const site =
      container.dataset.legalSite || legalRoot?.dataset?.legalSite || "example";
    const email =
      container.dataset.legalEmaildomain ||
      legalRoot?.dataset?.legalEmaildomain ||
      "example.com";

    container.dataset.twgLegalLoading = "true";

    fetch(`${API_BASE}/${slug}.json`)
      .then((res) => {
        if (!res.ok) throw new Error(`Failed to load legal JSON: ${res.status}`);
        return res.json();
      })
      .then((page) => {
        renderLegalPage(container, page, { site, email });
        container.dataset.twgLegalLoaded = "true";
      })
      .catch((err) => console.error("Legal page error:", err))
      .finally(() => {
        delete container.dataset.twgLegalLoading;
      });
  }

  function initAllLegalPages(root = document) {
    root.querySelectorAll(LEGAL_PAGE_SELECTOR).forEach(initLegalPage);
  }

  document.addEventListener("DOMContentLoaded", () => {
    initAllLegalPages();
    window.addEventListener("hashchange", () => scrollLegalPageHashWithOffset(100));
  });

  const observer = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
      mutation.addedNodes.forEach((node) => {
        if (!(node instanceof HTMLElement)) return;
        if (node.matches(LEGAL_PAGE_SELECTOR)) {
          initLegalPage(node);
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
