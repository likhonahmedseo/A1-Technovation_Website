document.addEventListener("DOMContentLoaded", () => {
  const editorRegistry = new Map();
  const codeMirrorRegistry = new Map();
  const editorSourceRegistry = new Map();

  const sidebarToggle = document.querySelector("[data-sidebar-toggle]");
  if (sidebarToggle && sidebarToggle.getAttribute("data-sidebar-toggle-bound") !== "1") {
    sidebarToggle.setAttribute("data-sidebar-toggle-bound", "1");
    const storageKey = "a1-cms-sidebar-collapsed";
    const setSidebarState = (collapsed) => {
      document.body.classList.toggle("admin-sidebar-collapsed", collapsed);
      sidebarToggle.setAttribute("aria-expanded", collapsed ? "false" : "true");
      sidebarToggle.setAttribute("aria-label", collapsed ? "Expand sidebar" : "Minimize sidebar");
      sidebarToggle.setAttribute("title", collapsed ? "Expand sidebar" : "Minimize sidebar");
      const label = sidebarToggle.querySelector("span");
      const icon = sidebarToggle.querySelector("i");
      if (label) {
        label.textContent = collapsed ? "Expand sidebar" : "Minimize sidebar";
      }
      if (icon) {
        icon.className = collapsed ? "fas fa-angles-right" : "fas fa-angles-left";
      }
    };

    try {
      setSidebarState(localStorage.getItem(storageKey) === "1");
    } catch (error) {
      setSidebarState(false);
    }
    sidebarToggle.addEventListener("click", () => {
      const collapsed = !document.body.classList.contains("admin-sidebar-collapsed");
      try {
        localStorage.setItem(storageKey, collapsed ? "1" : "0");
      } catch (error) {}
      setSidebarState(collapsed);
    });
  }

  const flash = document.querySelector("[data-autohide-flash]");
  if (flash) {
    window.setTimeout(() => {
      flash.style.opacity = "0";
      flash.style.transform = "translateY(-6px)";
    }, 4500);
  }

  const titleField = document.querySelector("[data-slug-source]");
  const slugField = document.querySelector("[data-slug-target]");
  if (titleField && slugField) {
    let slugTouched = slugField.value.trim() !== "";
    slugField.addEventListener("input", () => {
      slugTouched = slugField.value.trim() !== "";
    });
    titleField.addEventListener("input", () => {
      if (slugTouched) {
        return;
      }

      slugField.value = titleField.value
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, "-")
        .replace(/^-+|-+$/g, "");
    });
  }

  const copyText = async (button, text, fallbackLabel = "Copy this content") => {
    if (!text) {
      return;
    }

    try {
      await navigator.clipboard.writeText(text);
      const original = button.textContent;
      button.textContent = "Copied";
      window.setTimeout(() => {
        button.textContent = original;
      }, 1800);
    } catch (error) {
      window.prompt(fallbackLabel, text);
    }
  };

  document.querySelectorAll("[data-copy-text]").forEach((button) => {
    button.addEventListener("click", () => copyText(button, button.getAttribute("data-copy-text") || "", "Copy this URL"));
  });

  document.querySelectorAll("[data-copy-snippet]").forEach((button) => {
    button.addEventListener("click", () => copyText(button, button.getAttribute("data-copy-snippet") || "", "Copy this HTML"));
  });

  document.querySelectorAll("[data-fill-target]").forEach((button) => {
    button.addEventListener("click", () => {
      const selector = button.getAttribute("data-fill-target");
      const value = button.getAttribute("data-fill-value") || "";
      if (!selector) {
        return;
      }

      const target = document.querySelector(selector);
      if (!target) {
        return;
      }

      target.value = value;
      target.dispatchEvent(new Event("input", { bubbles: true }));
      target.scrollIntoView({ behavior: "smooth", block: "center" });
      target.focus();
    });
  });

  const insertIntoField = (selector, snippet) => {
    if (!selector || !snippet) {
      return;
    }

    const target = document.querySelector(selector);
    if (!target) {
      return;
    }

    const editor = editorRegistry.get(target);
    if (editor) {
      const source = editorSourceRegistry.get(target);
      if (source?.wrap && source.wrap.hidden === false) {
        if (source.codeMirror) {
          const doc = source.codeMirror.getDoc();
          doc.replaceRange(snippet, doc.getCursor());
          source.codeMirror.save();
          target.dispatchEvent(new Event("input", { bubbles: true }));
          source.codeMirror.focus();
        } else if (source.textarea) {
          const start = typeof source.textarea.selectionStart === "number" ? source.textarea.selectionStart : source.textarea.value.length;
          const end = typeof source.textarea.selectionEnd === "number" ? source.textarea.selectionEnd : source.textarea.value.length;
          source.textarea.value = `${source.textarea.value.slice(0, start)}${snippet}${source.textarea.value.slice(end)}`;
          source.textarea.setSelectionRange(start + snippet.length, start + snippet.length);
          source.textarea.focus();
        }
        return;
      }

      const viewFragment = editor.data.processor.toView(snippet);
      const modelFragment = editor.data.toModel(viewFragment);
      editor.model.change(() => {
        editor.model.insertContent(modelFragment, editor.model.document.selection);
      });
      if (typeof editor.updateSourceElement === "function") {
        editor.updateSourceElement();
      }
      target.dispatchEvent(new Event("input", { bubbles: true }));
      editor.editing.view.focus();
      return;
    }

    const codeMirror = codeMirrorRegistry.get(target);
    if (codeMirror) {
      const doc = codeMirror.getDoc();
      doc.replaceRange(snippet, doc.getCursor());
      codeMirror.save();
      target.dispatchEvent(new Event("input", { bubbles: true }));
      codeMirror.focus();
      return;
    }

    const start = typeof target.selectionStart === "number" ? target.selectionStart : target.value.length;
    const end = typeof target.selectionEnd === "number" ? target.selectionEnd : target.value.length;
    const prefix = start > 0 && !/\s$/.test(target.value.slice(0, start)) ? "\n" : "";
    const suffix = end < target.value.length && !/^\s/.test(target.value.slice(end)) ? "\n" : "";
    target.value = `${target.value.slice(0, start)}${prefix}${snippet}${suffix}${target.value.slice(end)}`;
    const cursor = start + prefix.length + snippet.length;
    if (typeof target.setSelectionRange === "function") {
      target.setSelectionRange(cursor, cursor);
    }
    target.dispatchEvent(new Event("input", { bubbles: true }));
    target.scrollIntoView({ behavior: "smooth", block: "center" });
    target.focus();
  };

  document.querySelectorAll("[data-insert-snippet]").forEach((button) => {
    button.addEventListener("click", () => {
      insertIntoField(button.getAttribute("data-insert-target") || "", button.getAttribute("data-insert-snippet") || "");
    });
  });

  document.querySelectorAll("[data-table-search]").forEach((input) => {
    const table = document.querySelector(input.getAttribute("data-table-search") || "");
    if (!table) return;

    input.addEventListener("input", () => {
      const query = input.value.trim().toLowerCase();
      table.querySelectorAll("tbody tr").forEach((row) => {
        row.style.display = row.textContent.toLowerCase().includes(query) ? "" : "none";
      });
    });
  });

  document.querySelectorAll("[data-dashboard-command-wrap]").forEach((wrap) => {
    const input = wrap.querySelector("[data-dashboard-command]");
    const results = wrap.querySelector("[data-dashboard-command-results]");
    const items = Array.from(wrap.querySelectorAll("[data-dashboard-command-item]"));
    if (!input || !results || items.length === 0) return;

    const syncResults = () => {
      const query = input.value.trim().toLowerCase();
      let visibleCount = 0;

      items.forEach((item) => {
        const isMatch = query === "" || item.textContent.toLowerCase().includes(query);
        item.hidden = !isMatch;
        if (isMatch) visibleCount += 1;
      });

      results.hidden = document.activeElement !== input || visibleCount === 0;
    };

    input.addEventListener("focus", syncResults);
    input.addEventListener("input", syncResults);
    input.addEventListener("keydown", (event) => {
      if (event.key === "Escape") {
        results.hidden = true;
        input.blur();
      }
    });

    document.addEventListener("click", (event) => {
      if (!wrap.contains(event.target)) {
        results.hidden = true;
      }
    });
  });

  document.querySelectorAll("[data-check-all]").forEach((checkbox) => {
    checkbox.addEventListener("change", () => {
      document.querySelectorAll(checkbox.getAttribute("data-check-all") || "").forEach((target) => {
        if (target instanceof HTMLInputElement && target.type === "checkbox") {
          target.checked = checkbox.checked;
        }
      });
    });
  });

  document.addEventListener("keydown", (event) => {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === "s") {
      const form = document.querySelector("form[data-autosave-form], .editor-card form");
      if (form) {
        event.preventDefault();
        form.requestSubmit();
      }
    }
  });

  const codeMirrors = [];
  const cmOptions = (mode) => ({
    mode,
    theme: "dracula",
    lineNumbers: true,
    lineWrapping: true,
    tabSize: 2,
    indentWithTabs: false,
    autofocus: false,
    extraKeys: { Tab: (cm) => cm.replaceSelection("  ") },
  });
  const createCodeMirror = (el, options) => {
    if (!window.CodeMirror) {
      return null;
    }
    const cm = CodeMirror.fromTextArea(el, options);
      cm.on("change", () => {
        cm.save();
        el.dispatchEvent(new Event("input", { bubbles: true }));
      });
      codeMirrorRegistry.set(el, cm);
      codeMirrors.push(cm);
      return cm;
  };

  if (window.CodeMirror) {
    document.querySelectorAll("[data-codemirror='html']").forEach((el) => {
      createCodeMirror(el, cmOptions("htmlmixed"));
    });
    document.querySelectorAll("[data-codemirror='css']").forEach((el) => {
      createCodeMirror(el, cmOptions("css"));
    });
    document.querySelectorAll("[data-codemirror='js']").forEach((el) => {
      createCodeMirror(el, cmOptions("javascript"));
    });
    document.querySelectorAll("[data-codemirror='json']").forEach((el) => {
      createCodeMirror(el, { ...cmOptions("application/json"), mode: "application/json" });
    });
    document.querySelectorAll("form").forEach((form) => {
      form.addEventListener("submit", () => {
        codeMirrors.forEach((cm) => cm.save());
      });
    });
  }

  if (window.ClassicEditor) {
    document.querySelectorAll("[data-ckeditor]").forEach((element) => {
      window.ClassicEditor.create(element, {
        toolbar: [
          "heading",
          "|",
          "bold",
          "italic",
          "link",
          "bulletedList",
          "numberedList",
          "blockQuote",
          "|",
          "insertTable",
          "undo",
          "redo"
        ]
      }).then((editor) => {
        editorRegistry.set(element, editor);
        let sourceWrap = null;
        let sourceEditor = null;
        let sourceTextarea = null;
        const editorElement = editor.ui.view.element;
        const sourceId = `${element.id || element.name || "ckeditor"}_html_source`;
        const sourceToolbar = document.createElement("div");
        sourceToolbar.className = "editor-source-actions";
        sourceToolbar.innerHTML = `
          <button type="button" class="btn btn-secondary btn-sm" data-editor-source-toggle>
            <i class="fas fa-code"></i> Edit HTML
          </button>
        `;
        editorElement.insertAdjacentElement("afterend", sourceToolbar);

        const showEditor = () => {
          if (sourceEditor) {
            editor.setData(sourceEditor.getValue());
          } else if (sourceWrap) {
            editor.setData(sourceWrap.querySelector("textarea").value);
          }
          if (sourceEditor || sourceWrap) {
            if (typeof editor.updateSourceElement === "function") {
              editor.updateSourceElement();
            }
            element.dispatchEvent(new Event("input", { bubbles: true }));
          }
          editorElement.hidden = false;
          if (sourceWrap) {
            sourceWrap.hidden = true;
          }
          sourceToolbar.querySelector("[data-editor-source-toggle]").innerHTML = '<i class="fas fa-code"></i> Edit HTML';
          editor.editing.view.focus();
        };

        const showSource = () => {
          if (typeof editor.updateSourceElement === "function") {
            editor.updateSourceElement();
          }
          if (!sourceWrap) {
            sourceWrap = document.createElement("div");
            sourceWrap.className = "code-editor-wrap editor-source-wrap";
            sourceWrap.hidden = true;
            sourceWrap.innerHTML = `
              <div class="code-editor-bar"><i class="fas fa-code"></i> HTML source</div>
              <textarea id="${sourceId}" aria-label="HTML source"></textarea>
            `;
            sourceToolbar.insertAdjacentElement("afterend", sourceWrap);
            sourceTextarea = sourceWrap.querySelector("textarea");
            sourceTextarea.value = element.value;
            if (window.CodeMirror) {
              sourceEditor = createCodeMirror(sourceTextarea, cmOptions("htmlmixed"));
            }
            editorSourceRegistry.set(element, { wrap: sourceWrap, textarea: sourceTextarea, codeMirror: sourceEditor });
          }
          if (sourceEditor) {
            sourceEditor.setValue(editor.getData());
            sourceEditor.refresh();
          } else {
            sourceWrap.querySelector("textarea").value = editor.getData();
          }
          editorElement.hidden = true;
          sourceWrap.hidden = false;
          sourceToolbar.querySelector("[data-editor-source-toggle]").innerHTML = '<i class="fas fa-pen"></i> Visual editor';
          window.setTimeout(() => {
            if (sourceEditor) {
              sourceEditor.refresh();
              sourceEditor.focus();
            } else {
              sourceWrap.querySelector("textarea").focus();
            }
          }, 0);
        };

        sourceToolbar.querySelector("[data-editor-source-toggle]").addEventListener("click", () => {
          if (editorElement.hidden) {
            showEditor();
          } else {
            showSource();
          }
        });
        const form = element.closest("form");
        if (form) {
          form.addEventListener("submit", () => {
            if (editorElement.hidden) {
              showEditor();
            }
          });
        }

        editor.model.document.on("change:data", () => {
          if (typeof editor.updateSourceElement === "function") {
            editor.updateSourceElement();
          }
          element.dispatchEvent(new Event("input", { bubbles: true }));
        });
      }).catch((error) => {
        console.error("CKEditor failed to load", error);
      });
    });
  }

  document.querySelectorAll("[data-seo-checklist]").forEach((box) => {
    const form = box.closest("form");
    const itemsNode = box.querySelector(".seo-checklist-items");
    if (!form || !itemsNode) return;

    const value = (selector) => (form.querySelector(selector)?.value || "").trim();
    const hasContent = () => {
      const html = value("[name='content_html']") || value("[name='custom_html']") || value("#builder_json");
      return html.replace(/<[^>]+>/g, "").trim().length > 80;
    };
    const validJson = () => {
      const json = value("[name='schema_json']");
      if (!json) return true;
      try {
        JSON.parse(json);
        return true;
      } catch (error) {
        return false;
      }
    };
    const checks = () => [
      ["Title added", value("[name='title']").length > 0],
      ["Slug added", value("[name='slug']").length > 0],
      ["Meta title is 30-65 chars", value("[name='meta_title']").length >= 30 && value("[name='meta_title']").length <= 65],
      ["Meta description is 90-160 chars", value("[name='meta_description']").length >= 90 && value("[name='meta_description']").length <= 160],
      ["Featured image added", value("[name='featured_image']").length > 0],
      ["Content has useful length", hasContent()],
      ["Schema JSON is valid", validJson()],
    ];
    const render = () => {
      itemsNode.innerHTML = checks().map(([label, ok]) => `
        <span class="seo-check ${ok ? "is-ok" : "is-missing"}">
          <i class="fas ${ok ? "fa-check-circle" : "fa-circle-exclamation"}"></i>${label}
        </span>
      `).join("");
    };

    form.addEventListener("input", render);
    render();
  });

  document.querySelectorAll("form[data-autosave-form]").forEach((form) => {
    const key = `a1cms-autosave:${form.getAttribute("data-autosave-key") || location.pathname}`;
    let isDirty = false;

    const serialize = () => {
      const payload = {};
      new FormData(form).forEach((value, name) => {
        if (name !== "_token" && name !== "action") {
          payload[name] = value;
        }
      });
      return payload;
    };

    const restore = (payload) => {
      Object.entries(payload).forEach(([name, value]) => {
        const safeName = window.CSS?.escape ? CSS.escape(name) : name.replace(/"/g, '\\"');
        const field = form.querySelector(`[name="${safeName}"]`);
        if (field && field.type !== "hidden") {
          field.value = value;
          field.dispatchEvent(new Event("input", { bubbles: true }));
        }
      });
    };

    try {
      const saved = JSON.parse(localStorage.getItem(key) || "null");
      if (saved?.payload) {
        const notice = document.createElement("div");
        notice.className = "autosave-notice";
        notice.innerHTML = `<strong>Autosaved draft found.</strong><button type="button" class="btn btn-secondary btn-sm">Restore</button><button type="button" class="btn btn-ghost btn-sm">Dismiss</button>`;
        form.prepend(notice);
        const [restoreButton, dismissButton] = notice.querySelectorAll("button");
        restoreButton.addEventListener("click", () => {
          restore(saved.payload);
          notice.remove();
          isDirty = true;
        });
        dismissButton.addEventListener("click", () => {
          localStorage.removeItem(key);
          notice.remove();
        });
      }
    } catch (error) {}

    form.addEventListener("input", () => {
      isDirty = true;
      window.clearTimeout(form.autosaveTimer);
      form.autosaveTimer = window.setTimeout(() => {
        localStorage.setItem(key, JSON.stringify({ savedAt: new Date().toISOString(), payload: serialize() }));
      }, 900);
    });
    form.addEventListener("submit", () => {
      isDirty = false;
      localStorage.removeItem(key);
    });
    window.addEventListener("beforeunload", (event) => {
      if (isDirty) {
        event.preventDefault();
        event.returnValue = "";
      }
    });
  });

  const builderShell = document.querySelector("[data-builder-shell]");
  if (builderShell) {
    const list = builderShell.querySelector("[data-builder-list]");
    const picker = document.querySelector("[data-widget-picker]");
    const addButton = document.querySelector("[data-add-widget]");
    const pickerHelp = document.querySelector("[data-widget-picker-help]");
    const hiddenField = document.querySelector("#builder_json");
    const seedNode = document.querySelector("#cms-builder-seed");
    const catalogNode = document.querySelector("#cms-builder-catalog");

    const parseJson = (node, fallback) => {
      if (!node) return fallback;
      try {
        return JSON.parse(node.textContent || node.value || "");
      } catch (error) {
        const decoder = document.createElement("textarea");
        decoder.innerHTML = node.textContent || node.value || "";
        try {
          return JSON.parse(decoder.value);
        } catch (innerError) {
          return fallback;
        }
      }
    };

    const state = Array.isArray(parseJson(seedNode, [])) ? parseJson(seedNode, []) : [];
    const defaultsRaw = parseJson(catalogNode, {});
    const widgetLabels = {
      hero: "Hero banner",
      text: "Text section",
      image: "Single image",
      video: "Video block",
      gallery: "Image gallery",
      logo_grid: "Logo grid",
      downloads: "Download links",
      split_content: "Text with image",
      embed: "Embed code",
      cta: "Call to action",
      faq: "FAQ accordion",
      pricing: "Pricing packages",
      testimonials: "Client testimonials",
      stats: "Number highlights",
      service_cards: "Service cards",
      buttons: "Button group",
      custom_html: "Custom HTML"
    };
    const widgetDescriptions = {
      hero: "Use for the top banner: headline, short copy, background image, and main buttons.",
      text: "Use for standard page copy with a heading and formatted body text.",
      image: "Use for one image with alt text and an optional caption.",
      video: "Use for one video with an optional poster image and caption.",
      gallery: "Use for multiple images shown together.",
      logo_grid: "Use for client, partner, certification, or tool logos.",
      downloads: "Use for PDFs, resources, files, or other downloadable assets.",
      split_content: "Use for a two-column layout with text beside an image.",
      embed: "Use for trusted embed code such as YouTube, maps, or third-party tools.",
      cta: "Use when you want visitors to take one clear action.",
      faq: "Use for common questions and short answers.",
      pricing: "Use for packages with price, summary, and features.",
      testimonials: "Use for customer quotes and social proof.",
      stats: "Use for numbers like growth, project counts, or turnaround time.",
      service_cards: "Use for included services, features, or process steps.",
      buttons: "Use for a small group of helpful links.",
      custom_html: "Use for trusted custom markup when no ready-made section fits."
    };

    const escapeHtml = (value) => String(value ?? "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");

    const field = (label, input) => `
      <label class="builder-field">
        <span>${escapeHtml(label)}</span>
        ${input}
      </label>
    `;

    const textArea = (value, rows = 4) =>
      `<textarea rows="${rows}" class="builder-input">${escapeHtml(value)}</textarea>`;

    const textInput = (value) =>
      `<input type="text" class="builder-input" value="${escapeHtml(value)}" />`;

    const selectInput = (value, options) =>
      `<select class="builder-input">${options.map((option) => `<option value="${escapeHtml(option.value)}" ${String(value ?? "") === option.value ? "selected" : ""}>${escapeHtml(option.label)}</option>`).join("")}</select>`;

    const renderItems = (items, columns, mapFn) => {
      const rows = Array.isArray(items) && items.length > 0 ? items : [null];
      return `
        <div class="builder-sublist" data-array-items>
          ${rows.map((item, index) => `
            <div class="builder-subitem" data-subitem>
              <div class="builder-subitem-grid cols-${columns}">
                ${mapFn(item, index, item === null)}
              </div>
              <button type="button" class="btn btn-danger btn-sm" data-remove-subitem>Remove</button>
            </div>
          `).join("")}
        </div>
      `;
    };

    const itemTemplate = {
      gallery: () => ({ image_url: "/assets/images/seo-service-new.webp", alt: "SEO strategy workspace", caption: "Replace this caption with a short note about the image." }),
      logo_grid: () => ({ image_url: "/assets/images/A1Technovation-Dark-Background.png", alt: "Partner logo", url: "#" }),
      downloads: () => ({ title: "Free SEO checklist", description: "Replace this with a short description of the file visitors can download.", file_url: "/assets/files/sample.pdf", button_label: "Download checklist" }),
      faq: () => ({ question: "How quickly can we launch this page?", answer: "Replace this answer with the response your customers need before they contact you." }),
      pricing: () => ({ name: "Growth Plan", price: "$499", details: "Replace this with a short summary of who this package is for.", features: ["Keyword research", "On-page optimization", "Monthly performance report"] }),
      testimonials: () => ({ quote: "Replace this with a real client quote that proves the result.", name: "Client Name", role: "Business Owner" }),
      stats: () => ({ number: "250%", label: "Organic traffic growth" }),
      service_cards: () => ({ title: "Technical SEO Audit", text: "Replace this with one included service, feature, or process step." }),
      buttons: () => ({ label: "Book a Call", url: "/pages/contact" })
    };

    const defaultPayload = (type) => JSON.parse(JSON.stringify(defaultsRaw[type] || { heading: "New section" }));

    const renderPayload = (type, payload) => {
      switch (type) {
        case "hero":
          return `
            <div class="builder-grid cols-2">
              ${field("Eyebrow", textInput(payload.eyebrow))}
              ${field("Heading", textInput(payload.heading))}
              ${field("Highlight", textInput(payload.highlight))}
              ${field("Background image URL", textInput(payload.background_image))}
              ${field("Primary button label", textInput(payload.primary_label))}
              ${field("Primary button URL", textInput(payload.primary_url))}
              ${field("Secondary button label", textInput(payload.secondary_label))}
              ${field("Secondary button URL", textInput(payload.secondary_url))}
            </div>
            ${field("Hero text", textArea(payload.text, 5))}
          `;
        case "text":
          return `${field("Heading", textInput(payload.heading))}${field("Body HTML", textArea(payload.body, 10))}`;
        case "image":
          return `<div class="builder-grid cols-2">${field("Heading", textInput(payload.heading))}${field("Image URL", textInput(payload.image_url))}${field("Alt text", textInput(payload.alt))}${field("Caption", textInput(payload.caption))}</div>`;
        case "video":
          return `<div class="builder-grid cols-2">${field("Heading", textInput(payload.heading))}${field("Video URL", textInput(payload.video_url))}${field("Poster URL", textInput(payload.poster_url))}${field("Caption", textInput(payload.caption))}</div>`;
        case "gallery":
          return `${field("Heading", textInput(payload.heading))}${renderItems(payload.items, 3, (item, index, bare) => {
            const seed = bare ? itemTemplate.gallery() : item;
            return `${field("Image URL", textInput(seed.image_url))}${field("Alt text", textInput(seed.alt))}${field("Caption", textArea(seed.caption, 3))}`;
          })}<button type="button" class="btn btn-secondary btn-sm" data-add-subitem data-subtype="gallery">Add Gallery Image</button>`;
        case "logo_grid":
          return `${field("Heading", textInput(payload.heading))}${renderItems(payload.items, 3, (item, index, bare) => {
            const seed = bare ? itemTemplate.logo_grid() : item;
            return `${field("Logo URL", textInput(seed.image_url))}${field("Alt text", textInput(seed.alt))}${field("Link URL", textInput(seed.url))}`;
          })}<button type="button" class="btn btn-secondary btn-sm" data-add-subitem data-subtype="logo_grid">Add Logo</button>`;
        case "downloads":
          return `${field("Heading", textInput(payload.heading))}${field("Intro", textArea(payload.intro, 4))}${renderItems(payload.items, 4, (item, index, bare) => {
            const seed = bare ? itemTemplate.downloads() : item;
            return `${field("Title", textInput(seed.title))}${field("Description", textArea(seed.description, 3))}${field("File URL", textInput(seed.file_url))}${field("Button label", textInput(seed.button_label))}`;
          })}<button type="button" class="btn btn-secondary btn-sm" data-add-subitem data-subtype="downloads">Add Download</button>`;
        case "split_content":
          return `
            <div class="builder-grid cols-2">
              ${field("Eyebrow", textInput(payload.eyebrow))}
              ${field("Heading", textInput(payload.heading))}
              ${field("Image URL", textInput(payload.image_url))}
              ${field("Image alt", textInput(payload.image_alt))}
              ${field("Image position", selectInput(payload.image_position, [{ value: "right", label: "Right" }, { value: "left", label: "Left" }]))}
              ${field("Primary label", textInput(payload.primary_label))}
              ${field("Primary URL", textInput(payload.primary_url))}
              ${field("Secondary label", textInput(payload.secondary_label))}
              ${field("Secondary URL", textInput(payload.secondary_url))}
            </div>
            ${field("Body HTML", textArea(payload.body, 8))}
          `;
        case "embed":
          return `${field("Heading", textInput(payload.heading))}${field("Embed HTML", textArea(payload.embed_html, 10))}${field("Caption", textInput(payload.caption))}`;
        case "cta":
          return `${field("Heading", textInput(payload.heading))}${field("Text", textArea(payload.text, 5))}<div class="builder-grid cols-2">${field("Button label", textInput(payload.button_label))}${field("Button URL", textInput(payload.button_url))}</div>`;
        case "faq":
          return `${field("Heading", textInput(payload.heading))}${renderItems(payload.items, 2, (item, index, bare) => {
            const seed = bare ? itemTemplate.faq() : item;
            return `${field("Question", textInput(seed.question))}${field("Answer", textArea(seed.answer, 4))}`;
          })}<button type="button" class="btn btn-secondary btn-sm" data-add-subitem data-subtype="faq">Add FAQ</button>`;
        case "pricing":
          return `${field("Heading", textInput(payload.heading))}${renderItems(payload.items, 4, (item, index, bare) => {
            const seed = bare ? itemTemplate.pricing() : item;
            return `${field("Name", textInput(seed.name))}${field("Price", textInput(seed.price))}${field("Details", textArea(seed.details, 3))}${field("Features (one per line)", textArea(Array.isArray(seed.features) ? seed.features.join("\n") : "", 4))}`;
          })}<button type="button" class="btn btn-secondary btn-sm" data-add-subitem data-subtype="pricing">Add Package</button>`;
        case "testimonials":
          return `${field("Heading", textInput(payload.heading))}${renderItems(payload.items, 3, (item, index, bare) => {
            const seed = bare ? itemTemplate.testimonials() : item;
            return `${field("Quote", textArea(seed.quote, 4))}${field("Name", textInput(seed.name))}${field("Role", textInput(seed.role))}`;
          })}<button type="button" class="btn btn-secondary btn-sm" data-add-subitem data-subtype="testimonials">Add Testimonial</button>`;
        case "stats":
          return `${field("Heading", textInput(payload.heading))}${renderItems(payload.items, 2, (item, index, bare) => {
            const seed = bare ? itemTemplate.stats() : item;
            return `${field("Number", textInput(seed.number))}${field("Label", textInput(seed.label))}`;
          })}<button type="button" class="btn btn-secondary btn-sm" data-add-subitem data-subtype="stats">Add Stat</button>`;
        case "service_cards":
          return `${field("Heading", textInput(payload.heading))}${renderItems(payload.items, 2, (item, index, bare) => {
            const seed = bare ? itemTemplate.service_cards() : item;
            return `${field("Title", textInput(seed.title))}${field("Text", textArea(seed.text, 4))}`;
          })}<button type="button" class="btn btn-secondary btn-sm" data-add-subitem data-subtype="service_cards">Add Card</button>`;
        case "buttons":
          return `${field("Heading", textInput(payload.heading))}${renderItems(payload.items, 2, (item, index, bare) => {
            const seed = bare ? itemTemplate.buttons() : item;
            return `${field("Label", textInput(seed.label))}${field("URL", textInput(seed.url))}`;
          })}<button type="button" class="btn btn-secondary btn-sm" data-add-subitem data-subtype="buttons">Add Button</button>`;
        case "custom_html":
          return `${field("Heading", textInput(payload.heading))}${field("Trusted HTML", textArea(payload.html, 10))}`;
        default:
          return `${field("Heading", textInput(payload.heading))}`;
      }
    };

    const extractPayload = (card, type) => {
      const inputs = card.querySelectorAll(".builder-input");
      const get = (index) => inputs[index] ? inputs[index].value : "";

      switch (type) {
        case "hero":
          return { eyebrow: get(0), heading: get(1), highlight: get(2), background_image: get(3), primary_label: get(4), primary_url: get(5), secondary_label: get(6), secondary_url: get(7), text: get(8) };
        case "text":
          return { heading: get(0), body: get(1) };
        case "image":
          return { heading: get(0), image_url: get(1), alt: get(2), caption: get(3) };
        case "video":
          return { heading: get(0), video_url: get(1), poster_url: get(2), caption: get(3) };
        case "gallery": {
          const heading = get(0);
          const items = [];
          card.querySelectorAll("[data-subitem]").forEach((subitem) => {
            const vals = Array.from(subitem.querySelectorAll(".builder-input")).map((node) => node.value);
            items.push({ image_url: vals[0] || "", alt: vals[1] || "", caption: vals[2] || "" });
          });
          return { heading, items };
        }
        case "logo_grid": {
          const heading = get(0);
          const items = [];
          card.querySelectorAll("[data-subitem]").forEach((subitem) => {
            const vals = Array.from(subitem.querySelectorAll(".builder-input")).map((node) => node.value);
            items.push({ image_url: vals[0] || "", alt: vals[1] || "", url: vals[2] || "" });
          });
          return { heading, items };
        }
        case "downloads": {
          const heading = get(0);
          const intro = get(1);
          const items = [];
          card.querySelectorAll("[data-subitem]").forEach((subitem) => {
            const vals = Array.from(subitem.querySelectorAll(".builder-input")).map((node) => node.value);
            items.push({ title: vals[0] || "", description: vals[1] || "", file_url: vals[2] || "", button_label: vals[3] || "" });
          });
          return { heading, intro, items };
        }
        case "split_content":
          return {
            eyebrow: get(0),
            heading: get(1),
            image_url: get(2),
            image_alt: get(3),
            image_position: get(4),
            primary_label: get(5),
            primary_url: get(6),
            secondary_label: get(7),
            secondary_url: get(8),
            body: get(9)
          };
        case "embed":
          return { heading: get(0), embed_html: get(1), caption: get(2) };
        case "cta":
          return { heading: get(0), text: get(1), button_label: get(2), button_url: get(3) };
        case "faq":
        case "pricing":
        case "testimonials":
        case "stats":
        case "service_cards":
        case "buttons": {
          const heading = get(0);
          const items = [];
          card.querySelectorAll("[data-subitem]").forEach((subitem) => {
            const vals = Array.from(subitem.querySelectorAll(".builder-input")).map((node) => node.value);
            if (type === "faq") items.push({ question: vals[0] || "", answer: vals[1] || "" });
            if (type === "pricing") items.push({ name: vals[0] || "", price: vals[1] || "", details: vals[2] || "", features: (vals[3] || "").split("\n").map((v) => v.trim()).filter(Boolean) });
            if (type === "testimonials") items.push({ quote: vals[0] || "", name: vals[1] || "", role: vals[2] || "" });
            if (type === "stats") items.push({ number: vals[0] || "", label: vals[1] || "" });
            if (type === "service_cards") items.push({ title: vals[0] || "", text: vals[1] || "" });
            if (type === "buttons") items.push({ label: vals[0] || "", url: vals[1] || "" });
          });
          return { heading, items };
        }
        case "custom_html":
          return { heading: get(0), html: get(1) };
        default:
          return { heading: get(0) };
      }
    };

    const sync = () => {
      const rows = [];
      list.querySelectorAll("[data-widget-card]").forEach((card) => {
        const type = card.getAttribute("data-widget-type") || "text";
        rows.push({ widget_type: type, payload: extractPayload(card, type) });
      });
      hiddenField.value = JSON.stringify(rows);
      hiddenField.dispatchEvent(new Event("input", { bubbles: true }));
    };

    const refreshStateFromDom = () => {
      const rows = [];
      list.querySelectorAll("[data-widget-card]").forEach((card) => {
        const type = card.getAttribute("data-widget-type") || "text";
        rows.push({ widget_type: type, payload: extractPayload(card, type) });
      });
      state.splice(0, state.length, ...rows);
      sync();
    };

    const cardTemplate = (type, payload) => {
      const label = widgetLabels[type] || type;
      const description = widgetDescriptions[type] || "Fill in the fields for this page section.";
      return `
        <article class="builder-card" data-widget-card data-widget-type="${type}">
          <div class="builder-card-head">
            <button type="button" class="builder-card-title" data-toggle-widget><i class="fas fa-chevron-down"></i><span><strong>${label}</strong><small>${description}</small></span></button>
            <div class="inline-actions">
              <button type="button" class="btn btn-ghost btn-sm" data-duplicate-widget title="Duplicate section" aria-label="Duplicate section"><i class="fas fa-copy"></i></button>
              <button type="button" class="btn btn-ghost btn-sm" data-move-up title="Move section up" aria-label="Move section up"><i class="fas fa-arrow-up"></i></button>
              <button type="button" class="btn btn-ghost btn-sm" data-move-down title="Move section down" aria-label="Move section down"><i class="fas fa-arrow-down"></i></button>
              <button type="button" class="btn btn-danger btn-sm" data-remove-widget title="Remove section" aria-label="Remove section"><i class="fas fa-trash"></i></button>
            </div>
          </div>
          <div class="builder-card-body">${renderPayload(type, payload)}</div>
        </article>
      `;
    };

    const mount = () => {
      list.innerHTML = state.map((row) => cardTemplate(row.widget_type, row.payload || defaultPayload(row.widget_type))).join("");
      sync();
    };

    const addWidget = (type, payload = null) => {
      refreshStateFromDom();
      state.push({ widget_type: type, payload: payload || defaultPayload(type) });
      mount();
    };

    addButton?.addEventListener("click", () => {
      addWidget(picker?.value || "text");
    });

    document.querySelector("[data-add-html-widget]")?.addEventListener("click", () => {
      addWidget("custom_html");
    });

    const updatePickerHelp = () => {
      if (!picker || !pickerHelp) return;
      pickerHelp.textContent = widgetDescriptions[picker.value] || "";
    };

    picker?.addEventListener("change", updatePickerHelp);
    updatePickerHelp();

    document.querySelectorAll("[data-add-template-widget]").forEach((button) => {
      button.addEventListener("click", () => {
        const type = button.getAttribute("data-template-type") || "text";
        let payload = defaultPayload(type);
        try {
          payload = JSON.parse(button.getAttribute("data-template-payload") || "{}");
        } catch (error) {}
        addWidget(type, payload);
      });
    });

    list.addEventListener("click", (event) => {
      const target = event.target.closest("button");
      if (!target) return;
      const card = target.closest("[data-widget-card]");
      if (!card) return;
      const cards = Array.from(list.querySelectorAll("[data-widget-card]"));
      const index = cards.indexOf(card);
      if (index < 0) return;

      if (target.hasAttribute("data-toggle-widget")) {
        card.classList.toggle("is-collapsed");
        return;
      }

      refreshStateFromDom();

      if (target.hasAttribute("data-remove-widget")) {
        state.splice(index, 1);
        mount();
      }
      if (target.hasAttribute("data-duplicate-widget")) {
        state.splice(index + 1, 0, JSON.parse(JSON.stringify(state[index])));
        mount();
      }
      if (target.hasAttribute("data-move-up") && index > 0) {
        [state[index - 1], state[index]] = [state[index], state[index - 1]];
        mount();
      }
      if (target.hasAttribute("data-move-down") && index < state.length - 1) {
        [state[index + 1], state[index]] = [state[index], state[index + 1]];
        mount();
      }
      if (target.hasAttribute("data-add-subitem")) {
        const subtype = target.getAttribute("data-subtype");
        const row = state[index];
        row.payload.items = Array.isArray(row.payload.items) ? row.payload.items : [];
        row.payload.items.push(itemTemplate[subtype] ? itemTemplate[subtype]() : {});
        mount();
      }
      if (target.hasAttribute("data-remove-subitem")) {
        const subitem = target.closest("[data-subitem]");
        const row = state[index];
        const subitems = Array.from(card.querySelectorAll("[data-subitem]"));
        const subIndex = subitems.indexOf(subitem);
        if (subIndex >= 0 && Array.isArray(row.payload.items)) {
          row.payload.items.splice(subIndex, 1);
          mount();
        }
      }
    });

    list.addEventListener("input", () => {
      sync();
    });

    mount();
  }

  // Fade-in lazy-loaded media images
  document.querySelectorAll(".media-preview img").forEach((img) => {
    if (img.complete) {
      img.classList.add("loaded");
    } else {
      img.addEventListener("load", () => img.classList.add("loaded"));
    }
  });

});
