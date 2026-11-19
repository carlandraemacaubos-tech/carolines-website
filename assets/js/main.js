// main.js - scripts used on every public page.

// 1) Mobile menu: show/hide the navigation when the menu button is clicked.
const menuToggle = document.querySelector("#menuToggle");
const mainNav = document.querySelector("#mainNav");

if (menuToggle && mainNav) {
  menuToggle.addEventListener("click", () => {
    const isOpen = mainNav.classList.toggle("open");
    menuToggle.setAttribute("aria-expanded", String(isOpen));
    menuToggle.setAttribute("aria-label", isOpen ? "Close navigation" : "Open navigation");
    menuToggle.textContent = isOpen ? "×" : "☰";
  });

  // Close the menu with the Escape key.
  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && mainNav.classList.contains("open")) {
      menuToggle.click();
      menuToggle.focus();
    }
  });
}

// 2) Gallery filter buttons only show photos in the selected category.
const filterButtons = document.querySelectorAll(".filter");
const galleryItems = document.querySelectorAll(".gallery-grid figure");

filterButtons.forEach((button) => {
  button.addEventListener("click", () => {
    filterButtons.forEach((item) => {
      item.classList.remove("active");
      item.setAttribute("aria-pressed", "false");
    });
    button.classList.add("active");
    button.setAttribute("aria-pressed", "true");

    const selected = button.dataset.filter;
    galleryItems.forEach((photo) => {
      const show = selected === "all" || photo.dataset.category === selected;
      photo.classList.toggle("hide", !show);
    });
  });
});

// 3) Put the current year in the footer automatically.
const yearSpan = document.querySelector("#year");
if (yearSpan) {
  yearSpan.textContent = new Date().getFullYear();
}

// 4) Prevent double-clicking any form's submit button (e.g. contact form).
document.querySelectorAll("form[data-single-submit]").forEach((form) => {
  form.addEventListener("submit", () => {
    const button = form.querySelector("button[type=submit]");
    if (button) {
      button.disabled = true;
      button.textContent = "Sending…";
    }
  });
});
// Re-enable buttons if the visitor comes back with the browser Back button.
window.addEventListener("pageshow", (event) => {
  if (event.persisted) {
    document.querySelectorAll("form[data-single-submit] button[type=submit]").forEach((b) => (b.disabled = false));
  }
});
