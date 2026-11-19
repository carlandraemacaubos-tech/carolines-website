// booking.js - the interactive availability calendar and instant validation for the booking form.
//
// All availability comes from the server (api/availability.php), which reads the administrator's
// settings from MySQL every time. This script only DISPLAYS it. PHP checks the schedule again when
// the form is submitted, so nothing here can be used to book an unavailable time.

(function () {
  const form = document.querySelector("#bookingForm");
  if (!form) return;

  const API = form.dataset.api;
  const MAX_DAYS = parseInt(form.dataset.maxDays, 10) || 14;

  const startDate = form.elements["start_date"];
  const endDate = form.elements["end_date"];
  const startTime = form.elements["start_time"];
  const endTime = form.elements["end_time"];

  const $ = (selector) => document.querySelector(selector);
  const startBtn = $("#startDateBtn");
  const endBtn = $("#endDateBtn");
  const calGrid = $("#calGrid");
  const calTitle = $("#calTitle");
  const calPrev = $("#calPrev");
  const calNext = $("#calNext");
  const calMode = $("#calMode");
  const message = $("#availMessage");
  const durationNote = $("#durationNote");
  const errorBox = $("#formErrors");
  const submitButton = form.querySelector(".submit-btn");

  const MONTHS = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
  const STATUS_TEXT = { past: "past date", blocked: "not available", closed: "closed", full: "fully booked" };

  const state = {
    mode: "start",      // which date the calendar is choosing: "start" or "end"
    month: "",          // "YYYY-MM" currently displayed
    days: {},           // data for the displayed month, from the API
    today: "",
    triedSubmit: false,
  };
  let monthRequest = 0, startRequest = 0, endRequest = 0;   // ignore answers that arrive late

  // ---- Helpers ------------------------------------------------------------------------
  const pad = (n) => String(n).padStart(2, "0");
  const toIso = (y, m, d) => y + "-" + pad(m) + "-" + pad(d);
  const parseIso = (iso) => { const [y, m, d] = iso.split("-").map(Number); return { y, m, d }; };
  const monthOf = (iso) => iso.slice(0, 7);

  function localToday() {
    const d = new Date();
    return toIso(d.getFullYear(), d.getMonth() + 1, d.getDate());
  }
  function daysBetween(a, b) {
    const A = parseIso(a), B = parseIso(b);
    return Math.round((Date.UTC(B.y, B.m - 1, B.d) - Date.UTC(A.y, A.m - 1, A.d)) / 86400000);
  }
  function niceDate(iso) {
    const { y, m, d } = parseIso(iso);
    return MONTHS[m - 1].slice(0, 3) + " " + d + ", " + y;
  }
  function longDate(iso) {
    const { y, m, d } = parseIso(iso);
    return MONTHS[m - 1] + " " + d + ", " + y;
  }
  function shiftMonth(month, delta) {
    const [y, m] = month.split("-").map(Number);
    const d = new Date(y, m - 1 + delta, 1);
    return d.getFullYear() + "-" + pad(d.getMonth() + 1);
  }

  async function api(params) {
    const response = await fetch(API + "?" + new URLSearchParams(params), { cache: "no-store", credentials: "same-origin" });
    if (!response.ok) throw new Error("Availability request failed");
    return response.json();
  }

  function say(text, kind) {
    message.textContent = text || "";
    message.className = "avail-message" + (kind ? " " + kind : "");
  }

  /** Show or clear the red message under a field. */
  function setError(name, text) {
    const holder = document.getElementById("err-" + name);
    if (holder) holder.textContent = text || "";
    form.querySelectorAll('[data-field="' + name + '"], [name="' + name + '"]').forEach((el) => {
      el.classList.toggle("is-invalid", Boolean(text));
      if (el.matches("input, select, textarea, button")) el.setAttribute("aria-invalid", text ? "true" : "false");
    });
  }

  // ---- Calendar ---------------------------------------------------------------------------
  async function loadMonth() {
    const ticket = ++monthRequest;
    calTitle.textContent = "Loading…";
    calGrid.setAttribute("aria-busy", "true");
    try {
      const data = await api({ action: "month", month: state.month });
      if (ticket !== monthRequest) return;
      state.days = data.days;
      state.today = data.today;
    } catch (error) {
      if (ticket !== monthRequest) return;
      state.days = {};
      say("We couldn't load the calendar. Please refresh the page or call/text Caroline at (631) 997-9504.", "bad");
    }
    calGrid.removeAttribute("aria-busy");
    renderCalendar();
  }

  /** Can the visitor click this date right now? */
  function isSelectable(date) {
    const info = state.days[date];
    if (!info || info.status !== "available") return false;
    if (state.mode === "end") {
      if (!startDate.value) return false;
      if (date < startDate.value) return false;
      if (daysBetween(startDate.value, date) + 1 > MAX_DAYS) return false;
    }
    return true;
  }

  function renderCalendar() {
    const [year, month] = state.month.split("-").map(Number);
    calTitle.textContent = MONTHS[month - 1] + " " + year;
    calMode.innerHTML = "Selecting: <strong>" + (state.mode === "start" ? "start date" : "end date") + "</strong>";

    const currentMonth = monthOf(state.today || localToday());
    calPrev.disabled = state.month <= currentMonth;

    const firstWeekday = new Date(year, month - 1, 1).getDay();
    const daysInMonth = new Date(year, month, 0).getDate();
    const frag = document.createDocumentFragment();

    for (let i = 0; i < firstWeekday; i++) {
      const blank = document.createElement("span");
      blank.className = "day blank";
      frag.appendChild(blank);
    }
    for (let day = 1; day <= daysInMonth; day++) {
      const date = toIso(year, month, day);
      const info = state.days[date];
      const selectable = isSelectable(date);

      const cell = document.createElement("button");
      cell.type = "button";
      cell.className = "day";
      cell.textContent = String(day);
      cell.dataset.date = date;
      cell.disabled = !selectable;

      if (!selectable) cell.classList.add("off");
      if (selectable) cell.classList.add("available");
      if (date === state.today) cell.classList.add("today");
      if (date === startDate.value) cell.classList.add("sel-start");
      if (date === endDate.value) cell.classList.add("sel-end");
      if (startDate.value && endDate.value && date > startDate.value && date < endDate.value) cell.classList.add("in-range");

      let label = longDate(date);
      if (selectable) label += ", available";
      else if (info && STATUS_TEXT[info.status]) label += ", " + STATUS_TEXT[info.status];
      else label += ", not available";
      cell.setAttribute("aria-label", label);
      if (info && info.status === "available" && info.windows.length) cell.title = "Open: " + info.windows.join(", ");
      frag.appendChild(cell);
    }
    calGrid.replaceChildren(frag);
  }

  function updateDateButtons() {
    startBtn.textContent = startDate.value ? niceDate(startDate.value) : "Choose a date";
    endBtn.textContent = endDate.value ? niceDate(endDate.value) : "Choose a date";
    startBtn.setAttribute("aria-pressed", String(state.mode === "start"));
    endBtn.setAttribute("aria-pressed", String(state.mode === "end"));
    startBtn.classList.toggle("active", state.mode === "start");
    endBtn.classList.toggle("active", state.mode === "end");
    startBtn.classList.toggle("filled", Boolean(startDate.value));
    endBtn.classList.toggle("filled", Boolean(endDate.value));
  }

  function setMode(mode) {
    state.mode = mode;
    updateDateButtons();
    // Show the month of the date being edited.
    const target = mode === "end" ? (endDate.value || startDate.value) : startDate.value;
    if (target && monthOf(target) !== state.month) {
      state.month = monthOf(target);
      loadMonth();
    } else {
      renderCalendar();
    }
  }

  // ---- Time lists -----------------------------------------------------------------------------
  function fillSelect(select, times, placeholder, wanted) {
    select.replaceChildren();
    const first = document.createElement("option");
    first.value = "";
    first.textContent = placeholder;
    select.appendChild(first);
    times.forEach((time) => {
      const option = document.createElement("option");
      option.value = time.value;
      option.textContent = time.label;
      select.appendChild(option);
    });
    select.disabled = times.length === 0;
    if (wanted && times.some((t) => t.value === wanted)) select.value = wanted;
  }

  async function loadStartTimes() {
    const ticket = ++startRequest;
    const wanted = startTime.value || startTime.dataset.selected || "";
    startTime.dataset.selected = "";
    fillSelect(startTime, [], "Loading…", "");
    if (!startDate.value) {
      fillSelect(startTime, [], "Choose a start date first", "");
      return loadEndTimes();
    }
    try {
      const data = await api({ action: "start_times", date: startDate.value });
      if (ticket !== startRequest) return;
      fillSelect(startTime, data.times, data.times.length ? "Select start time" : "No times available", wanted);
      if (data.times.length) {
        say("✔ This date is available! Open: " + data.windows.join(", ") + ". Choose your start and end times below.", "good");
      } else {
        say(data.message, "bad");
      }
    } catch (error) {
      if (ticket !== startRequest) return;
      fillSelect(startTime, [], "Could not load times", "");
      say("We couldn't load the available times. Please try again.", "bad");
    }
    return loadEndTimes();
  }

  async function loadEndTimes() {
    const ticket = ++endRequest;
    const wanted = endTime.value || endTime.dataset.selected || "";
    endTime.dataset.selected = "";
    if (!startDate.value || !startTime.value || !endDate.value) {
      fillSelect(endTime, [], startTime.value || !startDate.value ? "Choose your dates first" : "Choose a start time first", "");
      render();
      return;
    }
    fillSelect(endTime, [], "Loading…", "");
    try {
      const data = await api({ action: "end_times", start_date: startDate.value, start_time: startTime.value, end_date: endDate.value });
      if (ticket !== endRequest) return;
      fillSelect(endTime, data.times, data.times.length ? "Select end time" : "No times available", wanted);
      if (!data.times.length) say(data.message, "bad");
    } catch (error) {
      if (ticket !== endRequest) return;
      fillSelect(endTime, [], "Could not load times", "");
      say("We couldn't load the available times. Please try again.", "bad");
    }
    render();
  }

  // ---- Picking dates --------------------------------------------------------------------------
  calGrid.addEventListener("click", (event) => {
    const cell = event.target.closest("button.day");
    if (!cell || cell.disabled) return;
    const date = cell.dataset.date;

    if (state.mode === "start") {
      startDate.value = date;
      // Most events end the same day, so pre-fill the end date unless a valid later one is already chosen.
      if (!endDate.value || endDate.value < date || daysBetween(date, endDate.value) + 1 > MAX_DAYS) endDate.value = date;
      startTime.value = "";
      endTime.value = "";
      setError("start_date", "");
      setError("end_date", "");
      updateDateButtons();
      renderCalendar();
      loadStartTimes();
    } else {
      endDate.value = date;
      endTime.value = "";
      setError("end_date", "");
      updateDateButtons();
      renderCalendar();
      loadEndTimes();
    }
  });

  startBtn.addEventListener("click", () => setMode("start"));
  endBtn.addEventListener("click", () => {
    if (!startDate.value) {
      say("Please choose your start date first.", "bad");
      setMode("start");
      return;
    }
    setMode("end");
  });
  calPrev.addEventListener("click", () => { state.month = shiftMonth(state.month, -1); loadMonth(); });
  calNext.addEventListener("click", () => { state.month = shiftMonth(state.month, 1); loadMonth(); });

  startTime.addEventListener("change", () => { endTime.value = ""; loadEndTimes(); });
  endTime.addEventListener("change", () => render());

  // ---- Validation + summary -----------------------------------------------------------------
  const allFields = ["name", "email", "phone", "event_type", "event_location", "start_date", "end_date", "start_time", "end_time"];

  function validate() {
    const errors = {};
    if (state.triedSubmit) {
      if (form.elements["name"].value.trim().length < 2) errors.name = "Please enter your full name.";
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.elements["email"].value.trim())) errors.email = "Please enter a valid email address.";
      const digits = form.elements["phone"].value.replace(/\D/g, "");
      if (digits.length < 7 || digits.length > 15) errors.phone = "Please enter a valid contact number.";
      if (!form.elements["event_type"].value) errors.event_type = "Please choose a type of event.";
      if (form.elements["event_location"].value.trim().length < 3) errors.event_location = "Please enter where the event will be held.";
      if (!startDate.value) errors.start_date = "Please choose a start date.";
      if (!endDate.value) errors.end_date = "Please choose an end date.";
      if (!startTime.value) errors.start_time = "Please choose a start time.";
      if (!endTime.value) errors.end_time = "Please choose an end time.";
    }
    if (startDate.value && endDate.value && endDate.value < startDate.value) {
      errors.end_date = "The end date cannot be earlier than the start date.";
    }
    if (startDate.value && endDate.value && startDate.value === endDate.value && startTime.value && endTime.value && endTime.value <= startTime.value) {
      errors.end_time = "The end time must be later than the start time.";
    }
    return errors;
  }

  function render() {
    const errors = validate();
    allFields.forEach((name) => setError(name, errors[name]));
    if (errorBox) {
      const messages = Object.values(errors);
      errorBox.hidden = messages.length === 0 || !state.triedSubmit;
      errorBox.replaceChildren();
      if (!errorBox.hidden) {
        const title = document.createElement("strong");
        title.textContent = "Please fix the following:";
        const list = document.createElement("ul");
        messages.forEach((text) => {
          const li = document.createElement("li");
          li.textContent = text;
          list.appendChild(li);
        });
        errorBox.append(title, list);
      }
    }
    updateSummary();
    return Object.keys(errors).length === 0;
  }

  function updateSummary() {
    durationNote.textContent = "";
    if (!(startDate.value && endDate.value && startTime.value && endTime.value)) return;
    const [h1, m1] = startTime.value.split(":").map(Number);
    const [h2, m2] = endTime.value.split(":").map(Number);
    let minutes = daysBetween(startDate.value, endDate.value) * 1440 + (h2 * 60 + m2) - (h1 * 60 + m1);
    if (minutes <= 0) return;
    const days = Math.floor(minutes / 1440); minutes -= days * 1440;
    const hours = Math.floor(minutes / 60); minutes -= hours * 60;
    const parts = [];
    if (days) parts.push(days + (days === 1 ? " day" : " days"));
    if (hours) parts.push(hours + (hours === 1 ? " hour" : " hours"));
    if (minutes) parts.push(minutes + " min");
    const startLabel = startTime.options[startTime.selectedIndex].textContent;
    const endLabel = endTime.options[endTime.selectedIndex].textContent;
    durationNote.textContent = "✔ " + niceDate(startDate.value) + ", " + startLabel + "  →  " + niceDate(endDate.value) + ", " + endLabel + "  (" + parts.join(", ") + ")";
  }

  ["name", "email", "phone", "event_type", "event_location"].forEach((name) => {
    form.elements[name].addEventListener("input", () => { if (state.triedSubmit) render(); });
    form.elements[name].addEventListener("change", () => { if (state.triedSubmit) render(); });
  });

  form.addEventListener("submit", (event) => {
    state.triedSubmit = true;
    if (!render()) {
      event.preventDefault();
      if (errorBox && !errorBox.hidden) errorBox.scrollIntoView({ behavior: "smooth", block: "center" });
      return;
    }
    submitButton.disabled = true;
    submitButton.textContent = "Sending…";
  });

  window.addEventListener("pageshow", (event) => {
    if (event.persisted) {
      submitButton.disabled = false;
      submitButton.innerHTML = submitButton.dataset.label || "Send Booking Request";
      loadMonth();     // availability may have changed while the visitor was away
    }
  });

  // ---- Start ------------------------------------------------------------------------------------
  state.today = localToday();
  state.month = monthOf(startDate.value || state.today);
  updateDateButtons();
  loadMonth();
  if (startDate.value) {
    if (!endDate.value) endDate.value = startDate.value;
    updateDateButtons();
    loadStartTimes();        // restores the earlier choice after a server-side error
  } else {
    fillSelect(startTime, [], "Choose a start date first", "");
    fillSelect(endTime, [], "Choose a start time first", "");
  }
})();
