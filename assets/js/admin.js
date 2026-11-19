// admin.js - tiny helpers for the admin pages.

// Ask "Are you sure?" before any form/button that has a data-confirm message.
document.querySelectorAll("form[data-confirm]").forEach((form) => {
  form.addEventListener("submit", (event) => {
    if (!window.confirm(form.dataset.confirm)) {
      event.preventDefault();
    }
  });
});

// Stop double-clicks on action buttons (approve / reject / save).
document.querySelectorAll("form[data-once]").forEach((form) => {
  form.addEventListener("submit", (event) => {
    if (event.defaultPrevented) return;
    const button = form.querySelector("button[type=submit]");
    if (button) {
      // Disable on the next tick so the button's own value is still submitted.
      setTimeout(() => (button.disabled = true), 0);
    }
  });
});
window.addEventListener("pageshow", (event) => {
  if (event.persisted) {
    document.querySelectorAll("form[data-once] button[type=submit]").forEach((b) => (b.disabled = false));
  }
});
