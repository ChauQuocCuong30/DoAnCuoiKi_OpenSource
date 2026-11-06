"use strict";
(() => {
  const $ = (selector) => document.querySelector(selector);
  const base = document.body.dataset.appBase;
  const posLabels = {
    noun: "Danh từ",
    verb: "Động từ",
    adjective: "Tính từ",
    adverb: "Trạng từ",
    pronoun: "Đại từ",
    preposition: "Giới từ",
    conjunction: "Liên từ",
    interjection: "Thán từ",
    determiner: "Từ hạn định",
    phrase: "Cụm từ",
  };
  const colors = [
    "#65694e",
    "#a75b3e",
    "#4d6267",
    "#8a6656",
    "#73705a",
    "#76505b",
  ];
  let csrf = "",
    books = [],
    samples = [],
    selected = null,
    page = 1,
    pages = 1;
  let listVersion = 0,
    previewVersion = 0,
    previewToken = null,
    previewTopic = null;
  let importBusy = false,
    wordBusy = false,
    ready = false;

  function node(tag, text, className) {
    const element = document.createElement(tag);
    if (text !== undefined) element.textContent = text;
    if (className) element.className = className;
    return element;
  }
  function message(text, error = false, target = "#message") {
    $(target).textContent = text;
    $(target).classList.toggle("is-error", error);
  }
  async function request(path, options = {}) {
    const headers = { Accept: "application/json", ...options.headers };
    if (options.body && !(options.body instanceof FormData)) {
      headers["Content-Type"] = "application/json";
      options.body = JSON.stringify(options.body);
    }
    if (options.method && options.method !== "GET")
      headers["X-CSRF-Token"] = csrf;
    const response = await fetch(base + "/api/v1" + path, {
      ...options,
      headers,
      credentials: "same-origin",
      cache: "no-store",
    });
    let payload;
    try {
      payload = await response.json();
    } catch {
      throw new Error(
        "Không đọc được phản hồi. Hãy thử lại hoặc tải lại trang.",
      );
    }
    if (!response.ok)
      throw new Error(payload.error?.message || "Không thể thực hiện yêu cầu.");
    return payload.data;
  }
  function guard(work, target = "#message") {
    return Promise.resolve()
      .then(work)
      .catch((error) => message(error.message, true, target));
  }
  function normalized(text) {
    return String(text)
      .toLocaleLowerCase("vi")
      .normalize("NFD")
      .replace(/\p{M}/gu, "")
      .replace(/đ/g, "d");
  }
  function renderBooks() {
    const term = normalized($("#book-search").value.trim());
    const status = $("#book-status").value;
    const filtered = books.filter(
      (book) =>
        normalized(book.name).includes(term) &&
        (status === "all" || String(book.is_active) === status),
    );
    $("#books").replaceChildren();
    $("#book-count").textContent = "(" + filtered.length + ")";
    for (const book of filtered) {
      const card = node(
        "article",
        undefined,
        "book-card" + (Number(book.is_active) ? "" : " is-hidden"),
      );
      const cover = node("button", undefined, "book-cover");
      cover.type = "button";
      cover.style.setProperty(
        "--cover",
        colors[(Number(book.position) - 1) % colors.length],
      );
      cover.setAttribute(
        "aria-label",
        "Mở sách " + book.name + ", " + book.word_count + " từ",
      );
      cover.append(
        node(
          "span",
          "DOULINGO / TẬP " + String(book.position).padStart(2, "0"),
          "book-edition",
        ),
      );
      const ornament = node("span", "✳", "book-ornament");
      ornament.setAttribute("aria-hidden", "true");
      cover.append(ornament, node("h3", book.name));
      const footer = node("span", undefined, "book-cover-footer");
      footer.append(
        node("span", book.word_count + " TỪ VỰNG"),
        node("span", "MỞ SÁCH ↗"),
      );
      cover.append(footer);
      cover.addEventListener("click", () =>
        guard(() => openBook(String(book.id))),
      );
      const meta = node("div", undefined, "book-meta");
      meta.append(
        node(
          "span",
          book.active_word_count +
            " hiện · " +
            (Number(book.word_count) - Number(book.active_word_count)) +
            " ẩn",
        ),
      );
      meta.append(
        node(
          "span",
          Number(book.is_active) ? "Đang hiện" : "Đã ẩn",
          "badge" + (Number(book.is_active) ? " active" : ""),
        ),
      );
      card.append(cover, meta);
      $("#books").append(card);
    }
    $("#books-empty").hidden = filtered.length !== 0;
    $("#books-empty").textContent = books.length
      ? "Không tìm thấy chủ đề phù hợp."
      : "Kệ sách đang trống. Tạo chủ đề đầu tiên để bắt đầu thêm từ mẫu.";
    $("#books").setAttribute("aria-busy", "false");
  }
  async function loadBooks() {
    books = (await request("/admin/content/books")).items;
    $("#stat-books").textContent = books.length;
    $("#stat-words").textContent = books.reduce(
      (n, b) => n + Number(b.word_count),
      0,
    );
    $("#stat-active").textContent = books.reduce(
      (n, b) => n + Number(b.active_word_count),
      0,
    );
    renderBooks();
    $("#open-import").disabled = !ready;
    if (selected) {
      selected =
        books.find((b) => String(b.id) === String(selected.id)) || null;
      if (selected) renderBookHeading();
    }
  }
  function renderBookHeading() {
    $("#topic-title").textContent = selected.name;
    $("#topic-number").textContent =
      " / " + String(selected.position).padStart(2, "0");
    $("#topic-description").textContent = selected.description || "";
    $("#topic-counts").textContent =
      selected.word_count +
      " từ vựng · " +
      selected.active_word_count +
      " đang hiện · " +
      (Number(selected.word_count) - Number(selected.active_word_count)) +
      " đã ẩn";
    $("#edit-topic").href =
      base + "/admin/topics?edit=" + encodeURIComponent(selected.id);
    $("#topic-hidden").hidden = Number(selected.is_active) === 1;
    $("#book-import").disabled = $("#new-word").disabled =
      Number(selected.is_active) !== 1;
  }
  async function openBook(id, push = true) {
    const book = books.find((b) => String(b.id) === id);
    if (!book)
      throw new Error("Chủ đề không tồn tại. Hãy chọn lại sách trên kệ.");
    selected = book;
    page = 1;
    $("#word-search").value = "";
    $("#library-view").hidden = true;
    $("#book-view").hidden = false;
    renderBookHeading();
    if (push) {
      const url = new URL(location.href);
      url.searchParams.set("topic_id", id);
      history.pushState({}, "", url);
    }
    await loadWords();
  }
  function showLibrary(push = true) {
    selected = null;
    listVersion++;
    $("#book-view").hidden = true;
    $("#library-view").hidden = false;
    if (push) {
      const url = new URL(location.href);
      url.searchParams.delete("topic_id");
      history.pushState({}, "", url);
    }
  }
  async function loadWords() {
    if (!selected) return;
    const version = ++listVersion;
    const query = new URLSearchParams({
      parent_id: selected.id,
      q: $("#word-search").value.trim(),
      page,
    });
    $("#words").replaceChildren();
    $("#words").setAttribute("aria-busy", "true");
    $("#prev-page").disabled = $("#next-page").disabled = true;
    const result = await request("/admin/content/words?" + query);
    if (version !== listVersion) return;
    page = result.page;
    pages = result.pages;
    $("#word-total").textContent = result.total + " từ phù hợp";
    $("#page-info").textContent = "Trang " + page + " / " + pages;
    $("#prev-page").disabled = page <= 1;
    $("#next-page").disabled = page >= pages;
    for (const word of result.items) {
      const row = node("tr");
      row.dataset.wordId = word.id;
      row.append(
        node("td", word.english),
        node("td", word.vietnamese),
        node("td", posLabels[word.part_of_speech] || word.part_of_speech),
      );
      const status = node("td");
      status.append(
        node(
          "span",
          Number(word.is_active) ? "Hiện" : "Ẩn",
          "badge" + (Number(word.is_active) ? " active" : ""),
        ),
      );
      const actions = node("td"),
        group = node("div", undefined, "row-actions");
      const edit = node("button", "Sửa", "button-secondary");
      edit.type = "button";
      edit.setAttribute("aria-label", "Sửa từ " + word.english);
      edit.addEventListener("click", () => guard(() => editWord(word.id)));
      const toggle = node(
        "button",
        Number(word.is_active) ? "Ẩn" : "Hiện",
        "button-secondary",
      );
      toggle.type = "button";
      toggle.setAttribute(
        "aria-label",
        (Number(word.is_active) ? "Ẩn từ " : "Hiện từ ") + word.english,
      );
      toggle.disabled = !Number(word.is_active) && !Number(selected.is_active);
      toggle.addEventListener("click", () =>
        guard(async () => {
          toggle.disabled = true;
          try {
            const item = await request("/admin/content/words/" + word.id);
            await request("/admin/content/words", {
              method: "POST",
              body: { ...item, is_active: Number(word.is_active) ? 0 : 1 },
            });
            message(
              (Number(word.is_active) ? "Đã ẩn từ " : "Đã hiện từ ") +
                word.english +
                ".",
            );
            await refresh();
          } finally {
            toggle.disabled = false;
          }
        }),
      );
      group.append(edit, toggle);
      actions.append(group);
      row.append(status, actions);
      $("#words").append(row);
    }
    if (!result.items.length) {
      const row = node("tr"),
        cell = node(
          "td",
          "Chưa có từ phù hợp. Nhập bộ mẫu hoặc CSV để thêm từ vào sách.",
        );
      cell.colSpan = 5;
      row.append(cell);
      $("#words").append(row);
    }
    $("#words").setAttribute("aria-busy", "false");
  }
  async function refresh() {
    await loadBooks();
    if (selected) await loadWords();
  }
  function invalidatePreview() {
    previewToken = null;
    previewTopic = null;
    previewVersion++;
    $("#commit-import").disabled = true;
    $("#preview-panel").hidden = true;
    $("#import-message").textContent = "";
  }
  function openImport() {
    invalidatePreview();
    $("#import-form").reset();
    const active = books.filter((b) => Number(b.is_active) === 1);
    $("#import-topic").replaceChildren(
      ...active.map((b) => new Option(b.name, b.id)),
    );
    if (selected && Number(selected.is_active))
      $("#import-topic").value = selected.id;
    $("#sample-key").replaceChildren(
      ...samples.map(
        (p) => new Option(p.name + " · " + p.count + " từ · " + p.level, p.key),
      ),
    );
    $("#no-import-topics").hidden = active.length !== 0;
    $("#preview-import").disabled = active.length === 0;
    updateSource();
    $("#import-dialog").showModal();
  }
  function updateSource() {
    const csv = $("#import-form").elements.namedItem("source").value === "csv";
    $("#csv-fields").hidden = !csv;
    $("#sample-fields").hidden = csv;
    $("#csv-file").required = csv;
    $("#sample-key").required = !csv;
  }
  async function importLock(work) {
    if (importBusy) return;
    importBusy = true;
    const controls = [
      ...$("#import-dialog").querySelectorAll("button,input,select"),
    ].map((el) => [el, el.disabled]);
    controls.forEach(([el]) => {
      el.disabled = true;
    });
    try {
      await work();
    } finally {
      controls.forEach(([el, disabled]) => {
        el.disabled = disabled;
      });
      importBusy = false;
      $("#commit-import").disabled = !previewToken;
    }
  }
  function renderPreview(result) {
    $("#preview-panel").hidden = false;
    $("#preview-summary").textContent =
      result.valid_count +
      " từ mới · " +
      result.skipped_count +
      " từ trùng được bỏ qua · " +
      result.errors.length +
      " lỗi";
    $("#preview-errors").replaceChildren(
      ...result.errors.map((e) =>
        node("li", "Bản ghi " + e.record + ": " + e.message),
      ),
    );
    $("#preview-rows").replaceChildren(
      ...result.sample.map((word) => {
        const row = node("tr");
        row.append(
          node("td", word.english),
          node("td", word.vietnamese),
          node("td", word.example),
        );
        return row;
      }),
    );
    message(
      result.errors.length
        ? "Có dữ liệu lỗi. Sửa file rồi xem trước lại; chưa có từ nào được nhập."
        : result.valid_count
          ? "Dữ liệu hợp lệ. Xác nhận để nhập cả đợt vào sách."
          : "Các từ này đã có trong sách. Không cần nhập lại.",
      !!result.errors.length,
      "#import-message",
    );
  }
  async function editWord(id = null) {
    const book = selected;
    if (!book) return;
    const form = $("#word-form");
    form.reset();
    form.elements.namedItem("id").value = "";
    form.elements.namedItem("topic_id").value = book.id;
    if (id !== null) {
      const item = await request("/admin/content/words/" + id);
      if (!selected || String(selected.id) !== String(book.id)) return;
      for (const [key, value] of Object.entries(item)) {
        const field = form.elements.namedItem(key);
        if (field) field.value = value ?? "";
      }
    }
    $("#word-title").textContent =
      id === null ? "Thêm từ vào " + book.name : "Sửa từ vựng";
    message("", false, "#word-message");
    $("#word-dialog").showModal();
  }
  $("#back-library").addEventListener("click", () => showLibrary());
  $("#book-search").addEventListener("input", renderBooks);
  $("#book-status").addEventListener("change", renderBooks);
  $("#word-filter").addEventListener("submit", (event) => {
    event.preventDefault();
    page = 1;
    guard(loadWords);
  });
  $("#prev-page").addEventListener("click", () => {
    if (page > 1) {
      page--;
      guard(loadWords);
    }
  });
  $("#next-page").addEventListener("click", () => {
    if (page < pages) {
      page++;
      guard(loadWords);
    }
  });
  $("#open-import").addEventListener("click", openImport);
  $("#book-import").addEventListener("click", openImport);
  $("#new-word").addEventListener("click", () => guard(() => editWord()));
  $("#import-form").addEventListener("input", invalidatePreview);
  $("#import-form").addEventListener("change", () => {
    invalidatePreview();
    updateSource();
  });
  document.querySelectorAll("[data-close]").forEach((button) =>
    button.addEventListener("click", () => {
      $("#" + button.dataset.close).close();
    }),
  );
  $("#import-dialog").addEventListener("cancel", (event) => {
    if (importBusy) event.preventDefault();
  });
  $("#word-dialog").addEventListener("cancel", (event) => {
    if (wordBusy) event.preventDefault();
  });
  $("#import-form").addEventListener("submit", (event) => {
    event.preventDefault();
    if (importBusy) return;
    invalidatePreview();
    const version = previewVersion,
      form = $("#import-form"),
      topic = $("#import-topic").value;
    const mode = $("#skip-duplicates").checked ? "skip" : "error";
    const isSample = form.elements.namedItem("source").value === "sample";
    const data = isSample
      ? {
          topic_id: topic,
          sample_key: $("#sample-key").value,
          duplicate_mode: mode,
        }
      : new FormData(form);
    if (!isSample) data.set("duplicate_mode", mode);
    guard(
      () =>
        importLock(async () => {
          message("Đang kiểm tra dữ liệu…", false, "#import-message");
          const result = await request(
            "/admin/content/" + (isSample ? "samples" : "csv") + "/preview",
            { method: "POST", body: data },
          );
          if (version !== previewVersion) return;
          previewToken = result.preview_token;
          previewTopic = topic;
          renderPreview(result);
        }),
      "#import-message",
    );
  });
  $("#commit-import").addEventListener("click", () => {
    if (!previewToken || importBusy) return;
    const token = previewToken,
      topic = previewTopic;
    guard(
      () =>
        importLock(async () => {
          message("Đang nhập từ vào sách…", false, "#import-message");
          const result = await request("/admin/content/csv/commit", {
            method: "POST",
            body: { preview_token: token },
          });
          previewToken = null;
          const name =
            books.find((b) => String(b.id) === topic)?.name || "sách";
          const text =
            "Đã nhập " +
            result.imported +
            " từ vào " +
            name +
            ". Bỏ qua " +
            result.skipped +
            " từ trùng.";
          message(text, false, "#import-message");
          message(text);
          $("#preview-panel").hidden = true;
          await refresh();
        }),
      "#import-message",
    );
  });
  $("#word-form").addEventListener("submit", (event) => {
    event.preventDefault();
    if (wordBusy) return;
    const body = Object.fromEntries(new FormData($("#word-form")));
    guard(async () => {
      wordBusy = true;
      const buttons = [...$("#word-dialog").querySelectorAll("button")];
      buttons.forEach((b) => {
        b.disabled = true;
      });
      try {
        await request("/admin/content/words", { method: "POST", body });
        $("#word-dialog").close();
        message("Đã lưu từ vựng.");
        await refresh();
      } finally {
        wordBusy = false;
        buttons.forEach((b) => {
          b.disabled = false;
        });
      }
    }, "#word-message");
  });
  window.addEventListener("popstate", () =>
    guard(async () => {
      const id = new URL(location.href).searchParams.get("topic_id");
      if (id) await openBook(id, false);
      else showLibrary(false);
    }),
  );
  guard(async () => {
    const [boot, catalog] = await Promise.all([
      request("/admin/content/bootstrap"),
      request("/admin/content/samples"),
      loadBooks(),
    ]);
    csrf = boot.csrf_token;
    samples = catalog.items;
    ready = true;
    $("#open-import").disabled = false;
    $("#word-form")
      .elements.namedItem("part_of_speech")
      .replaceChildren(
        ...boot.part_of_speech.map(
          (pos) => new Option(posLabels[pos] || pos, pos),
        ),
      );
    const id = new URL(location.href).searchParams.get("topic_id");
    if (id) await openBook(id, false);
    message("");
    if (document.body.dataset.importPage === "1") openImport();
  });
})();
