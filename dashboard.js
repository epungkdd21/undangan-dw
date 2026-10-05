const sectionMenu = document.querySelector(".section-menu");
const menuLinks = sectionMenu ? [...sectionMenu.querySelectorAll("a")] : [];
const panels = [...document.querySelectorAll(".dashboard-content > .panel-section")];
let syncSelectAll = () => {};

const showPanel = (hash) => {
  const activePanel = panels.find((panel) => `#${panel.id}` === hash) ?? panels[0];
  if (!activePanel) return;

  panels.forEach((panel) => {
    panel.hidden = panel !== activePanel;
  });

  menuLinks.forEach((link) => {
    const isActive = link.hash === `#${activePanel.id}`;
    link.classList.toggle("active", isActive);
    if (isActive) {
      link.setAttribute("aria-current", "page");
    } else {
      link.removeAttribute("aria-current");
    }
  });
};

menuLinks.forEach((link) => {
  link.addEventListener("click", (event) => {
    event.preventDefault();
    history.pushState(null, "", link.hash);
    showPanel(link.hash);
  });
});

window.addEventListener("popstate", () => showPanel(window.location.hash));
showPanel(window.location.hash);

document.querySelectorAll(".delete-guest-form").forEach((form) => {
  form.addEventListener("submit", (event) => {
    if (!window.confirm("Hapus nama tamu ini?")) {
      event.preventDefault();
    }
  });
});

const setupPagination = (list, itemSelector, label) => {
  if (!list) return null;

  const items = [...list.querySelectorAll(itemSelector)];
  const pageSize = 10;
  let currentPage = 1;
  let currentItems = items;
  const pagination = document.createElement("nav");
  pagination.className = "list-pagination";
  pagination.setAttribute("aria-label", label);

  const previousButton = document.createElement("button");
  previousButton.type = "button";
  previousButton.textContent = "Sebelumnya";

  const pageStatus = document.createElement("span");
  pageStatus.setAttribute("aria-live", "polite");

  const nextButton = document.createElement("button");
  nextButton.type = "button";
  nextButton.textContent = "Selanjutnya";

  pagination.append(previousButton, pageStatus, nextButton);
  list.after(pagination);

  const updatePage = (visibleItems = currentItems) => {
    currentItems = visibleItems;
    const pageCount = Math.max(1, Math.ceil(currentItems.length / pageSize));
    currentPage = Math.min(currentPage, pageCount);
    const firstItem = (currentPage - 1) * pageSize;
    const pageItems = new Set(currentItems.slice(firstItem, firstItem + pageSize));

    items.forEach((item) => {
      item.hidden = !pageItems.has(item);
    });
    pagination.hidden = currentItems.length <= pageSize;
    previousButton.disabled = currentPage === 1;
    nextButton.disabled = currentPage === pageCount;
    pageStatus.textContent = `Halaman ${currentPage} dari ${pageCount}`;
  };

  previousButton.addEventListener("click", () => {
    currentPage--;
    updatePage();
    syncSelectAll();
  });
  nextButton.addEventListener("click", () => {
    currentPage++;
    updatePage();
    syncSelectAll();
  });

  return { items, updatePage, resetPage: () => { currentPage = 1; } };
};

const guestList = document.querySelector(".guest-list");
const guestPagination = setupPagination(guestList, ".guest-row", "Halaman daftar kontak");
const guestSearch = document.getElementById("guest-search");
const searchStatus = document.getElementById("guest-search-status");
const noSearchResults = document.createElement("p");
noSearchResults.className = "empty-guests guest-search-empty";
noSearchResults.textContent = "Tidak ada tamu yang cocok dengan pencarian.";
noSearchResults.hidden = true;
guestList?.append(noSearchResults);

const updateGuestSearch = () => {
  if (!guestPagination) return;

  const query = guestSearch?.value.trim().toLocaleLowerCase("id") ?? "";
  const matches = guestPagination.items.filter((row) => {
    const fields = [...row.querySelectorAll(".edit-guest-form input:not([type=hidden])")];
    return fields.some((field) => field.value.toLocaleLowerCase("id").includes(query));
  });
  guestPagination.resetPage();
  guestPagination.updatePage(matches);
  syncSelectAll();
  noSearchResults.hidden = matches.length > 0;
  if (searchStatus) {
    searchStatus.textContent = `${matches.length} dari ${guestPagination.items.length} tamu`;
  }
};

guestSearch?.addEventListener("input", updateGuestSearch);
updateGuestSearch();
setupPagination(document.querySelector(".history-list"), ".history-item", "Halaman histori broadcast");

const guestCheckboxes = [...document.querySelectorAll(".guest-select input")];
const selectionCount = document.getElementById("selected-guest-count");
const selectAll = document.getElementById("select-all");

const updateSelectionCount = () => {
  const selectedCount = guestCheckboxes.filter((checkbox) => checkbox.checked).length;
  if (selectionCount) {
    selectionCount.textContent = `${selectedCount} tamu dipilih`;
  }
};

syncSelectAll = () => {
  if (!selectAll) return;
  const visibleCheckboxes = guestCheckboxes.filter((checkbox) => !checkbox.closest(".guest-row").hidden);
  const selectedVisible = visibleCheckboxes.filter((checkbox) => checkbox.checked).length;
  selectAll.checked = visibleCheckboxes.length > 0 && selectedVisible === visibleCheckboxes.length;
  selectAll.indeterminate = selectedVisible > 0 && selectedVisible < visibleCheckboxes.length;
};

guestCheckboxes.forEach((checkbox) => checkbox.addEventListener("change", () => {
  updateSelectionCount();
  syncSelectAll();
  syncSelectAll();
}));

selectAll?.addEventListener("change", () => {
  const visibleCheckboxes = guestCheckboxes.filter((checkbox) => !checkbox.closest(".guest-row").hidden);
  visibleCheckboxes.forEach((checkbox) => {
    checkbox.checked = selectAll.checked;
  });
  updateSelectionCount();
  syncSelectAll();
});
updateSelectionCount();
