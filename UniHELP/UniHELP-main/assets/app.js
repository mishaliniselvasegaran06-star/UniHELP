/* ============================================================
   UniHELP — app.js
   Vanilla JS for all interactive UI behaviour
   ============================================================ */

document.addEventListener("DOMContentLoaded", () => {
  /* ── Mobile navbar toggle ────────────────────────────── */
  const toggle = document.getElementById("navToggle");
  const links = document.getElementById("navLinks");
  if (toggle && links) {
    toggle.addEventListener("click", () => links.classList.toggle("open"));
    document.addEventListener("click", (e) => {
      if (!toggle.contains(e.target) && !links.contains(e.target)) {
        links.classList.remove("open");
      }
    });
  }

  /* ── Auto-dismiss flash messages ────────────────────── */
  const flash = document.getElementById("flashMsg");
  if (flash) setTimeout(() => (flash.style.opacity = "0"), 4000);

  /* ── OTP / 2FA input auto-advance ───────────────────── */
  const otpInputs = document.querySelectorAll(".otp-input");
  otpInputs.forEach((input, i) => {
    input.addEventListener("input", () => {
      input.value = input.value.replace(/\D/g, "").slice(0, 1);
      input.classList.toggle("filled", input.value !== "");
      if (input.value && i < otpInputs.length - 1) {
        otpInputs[i + 1].focus();
      }
    });
    input.addEventListener("keydown", (e) => {
      if (e.key === "Backspace" && !input.value && i > 0) {
        otpInputs[i - 1].focus();
      }
    });
    input.addEventListener("paste", (e) => {
      const text = (e.clipboardData || window.clipboardData)
        .getData("text")
        .replace(/\D/g, "");
      e.preventDefault();
      [...text.slice(0, 6)].forEach((ch, j) => {
        if (otpInputs[i + j]) {
          otpInputs[i + j].value = ch;
          otpInputs[i + j].classList.add("filled");
        }
      });
      const next = Math.min(i + text.length, otpInputs.length - 1);
      otpInputs[next].focus();
    });
  });

  /* ── Collect OTP value into hidden field ─────────────── */
  const otpForm = document.getElementById("otpForm");
  if (otpForm) {
    otpForm.addEventListener("submit", () => {
      const code = [...otpInputs].map((i) => i.value).join("");
      document.getElementById("otpValue").value = code;
    });
  }

  /* ── Skill checkbox styling ──────────────────────────── */
  document
    .querySelectorAll(".skill-item input[type=checkbox]")
    .forEach((cb) => {
      const item = cb.closest(".skill-item");
      const sync = () => item.classList.toggle("checked", cb.checked);
      sync();
      cb.addEventListener("change", sync);
    });

  /* ── Availability matrix ─────────────────────────────── */
  document.querySelectorAll(".avail-slot input").forEach((checkbox) => {
    const slot = checkbox.closest(".avail-slot");
    const sync = () => slot.classList.toggle("on", checkbox.checked);
    checkbox.addEventListener("change", sync);
    sync();
  });

  /* ── Request mode segmented control ──────────────────── */
  document.querySelectorAll("[data-mode-group]").forEach((group) => {
    const value = group.parentElement.querySelector("[data-mode-value]");
    group.querySelectorAll("[data-mode]").forEach((button) => {
      button.addEventListener("click", () => {
        group
          .querySelectorAll("[data-mode]")
          .forEach((item) => item.classList.remove("active"));
        button.classList.add("active");
        if (value) value.value = button.dataset.mode;
      });
    });
  });

  /* ── Generic modal helpers ───────────────────────────── */
  window.openModal = (id) => {
    const m = document.getElementById(id);
    if (m) m.style.display = "flex";
  };
  window.closeModal = (id) => {
    const m = document.getElementById(id);
    if (m) m.style.display = "none";
  };

  document.querySelectorAll(".modal-overlay").forEach((m) => {
    m.addEventListener("click", (e) => {
      if (e.target === m) m.style.display = "none";
    });
  });

  /* ── Char counter for textareas ──────────────────────── */
  document.querySelectorAll("textarea[data-maxlength]").forEach((ta) => {
    const max = parseInt(ta.dataset.maxlength, 10);
    const counter = document.getElementById(ta.dataset.counter);
    const update = () => {
      if (counter) counter.textContent = `${ta.value.length}/${max}`;
    };
    ta.addEventListener("input", update);
    update();
  });

  /* ── Password strength indicator ─────────────────────── */
  const pwInput = document.getElementById("password");
  const pwStrength = document.getElementById("pwStrength");
  const pwMeter = document.getElementById("pwMeter");
  if (pwInput && pwStrength) {
    pwInput.addEventListener("input", () => {
      const v = pwInput.value;
      let score = 0;
      if (v.length >= 8) score++;
      if (/[A-Z]/.test(v)) score++;
      if (/[0-9]/.test(v)) score++;
      if (/[^A-Za-z0-9]/.test(v)) score++;
      const labels = ["", "Weak", "Fair", "Good", "Strong"];
      const colors = ["", "#DC2626", "#D97706", "#2563EB", "#16A34A"];
      pwStrength.textContent = v.length ? labels[score] : "";
      pwStrength.style.color = colors[score];
      if (pwMeter) {
        pwMeter.style.width = v.length ? `${Math.max(score, 1) * 25}%` : "0";
        pwMeter.style.background = colors[Math.max(score, 1)];
      }
    });
  }

  /* ── Volunteer filter (client-side) ──────────────────── */
  const filterForm = document.getElementById("filterForm");
  if (filterForm) {
    const inputs = filterForm.querySelectorAll("select, input");
    const cards = document.querySelectorAll(".vol-card");
    const doFilter = () => {
      const skill =
        filterForm.querySelector("[name=skill]")?.value.toLowerCase() || "";
      const mode =
        filterForm.querySelector("[name=mode]")?.value.toLowerCase() || "";
      let shown = 0;
      cards.forEach((card) => {
        const cardSkill = (card.dataset.skill || "").toLowerCase();
        const cardMode = (card.dataset.mode || "").toLowerCase();
        const match =
          (!skill || cardSkill.includes(skill)) &&
          (!mode || cardMode.includes(mode));
        card.style.display =