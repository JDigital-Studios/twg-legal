// Legal SDK Script
document.addEventListener("DOMContentLoaded", () => {
  const API_BASE = "https://twgwprojects.github.io/wp_legal_pages/api";

  const container = document.getElementById("legal-page");
  if (!container) return;

  const slug = container.dataset.slug;
  if (!slug) return;

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

  window.addEventListener("hashchange", () => scrollLegalPageHashWithOffset(100));

  // Read site variables from <body data-legal-site="..." data-legal-emaildomain="...">
  const site = container.parentElement.dataset.legalSite || "example";
  const email =
    container.parentElement.dataset.legalEmaildomain || "example.com";
  const customLastUpdated =
    (container.parentElement.dataset.legalLastUpdated || "").trim();

  fetch(`${API_BASE}/${slug}.json`)
    .then((res) => {
      if (!res.ok) throw new Error(`Failed to load legal JSON: ${res.status}`);
      return res.json();
    })
    .then((page) => {
      //  Title
      const titleEl = document.getElementById("legal-title");
      if (titleEl) {
        titleEl.textContent = page.title || "";
      }

      // Last Updated
      const updatedEl = document.getElementById("legal-last-updated");
      const lastUpdatedValue = customLastUpdated || page.lastUpdated || "";
      if (updatedEl && lastUpdatedValue) {
        updatedEl.textContent = `Last Updated: ${lastUpdatedValue}`;
      }

      const contentEl = document.getElementById("legal-content");
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

            if (block.id) el.id = block.id; // ✅ enables #ccpa

            // Replace placeholders
            const txt = replaceTokens(block.text || "", { site, email });

            // If your JSON includes HTML (like <a>), use innerHTML.
            // If you want plain text only, switch to: el.textContent = txt;
            el.innerHTML = txt;

            if (block.bold) el.style.fontWeight = "bold";
            break;
          }

          case "list": {
            el = document.createElement("ul");

            (block.items || []).forEach((item) => {
              const li = document.createElement("li");

              // Replace placeholders
              const txt = replaceTokens(item || "", { site, email });

              // Allow clickable links if item contains <a ...>
              li.innerHTML = txt;

              el.appendChild(li);
            });

            break;
          }

          case "table": {
            el = renderLegalTable(block, { site, email });
            break;
          }

          default:
            // Unknown block types are ignored
            return;
        }

        contentEl.appendChild(el);
      });

      // Hash targets (e.g. #ccpa) are injected above; browser scroll may run too early — re-scroll with offset.
      requestAnimationFrame(() => {
        requestAnimationFrame(() => scrollLegalPageHashWithOffset(100));
      });
    })
    .catch((err) => console.error("Legal page error:", err));

  function replaceTokens(str, { site, email }) {
    return String(str || "")
      .replaceAll("{SITE}", site)
      .replaceAll("{EMAIL}", email);
  }

  /**
   * Render legal table block
   */
  function renderLegalTable(block, { site, email }) {
    const wrapper = document.createElement("div");
    wrapper.className = "legal-table-wrapper";

    // Optional table title
    if (block.title) {
      const h = document.createElement("h3");
      h.textContent = replaceTokens(block.title, { site, email });
      wrapper.appendChild(h);
    }

    const table = document.createElement("table");
    table.className = "legal-table";

    const columns = Array.isArray(block.columns) ? block.columns : [];
    const rows = Array.isArray(block.rows) ? block.rows : [];

    // THEAD
    const thead = document.createElement("thead");
    const trHead = document.createElement("tr");

    columns.forEach((col) => {
      const th = document.createElement("th");
      th.textContent = col;
      trHead.appendChild(th);
    });

    thead.appendChild(trHead);
    table.appendChild(thead);

    // TBODY
    const tbody = document.createElement("tbody");

    rows.forEach((row) => {
      const tr = document.createElement("tr");

      columns.forEach((col) => {
        const td = document.createElement("td");
        const value = row ? row[col] : "";

        // Category can be object {text, examples[]}
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

            // Escape examples since we render it as HTML wrapper text
            ex.innerHTML = `<em>Examples:</em> ${escapeHtml(examplesText)}`;

            td.appendChild(ex);
          }
        }
        // Arrays -> bullet list within cell
        else if (Array.isArray(value)) {
          const ul = document.createElement("ul");

          value.forEach((item) => {
            const li = document.createElement("li");

            // Replace placeholders
            const txt = replaceTokens(item || "", { site, email });

            // allow clickable links if item contains <a ...>
            li.innerHTML = txt;

            ul.appendChild(li);
          });

          td.appendChild(ul);
        }
        // Simple string/object -> allow clickable links
        else {
          const txt = replaceTokens(value || "", { site, email });
          td.innerHTML = txt;
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

  function linkify(escapedText) {
    return String(escapedText).replace(
      /(https?:\/\/[^\s<]+)/g,
      '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>',
    );
  }
});