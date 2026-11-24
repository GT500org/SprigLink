(function () {
  function copyText(value) {
    if (navigator.clipboard && window.isSecureContext) {
      return navigator.clipboard.writeText(value);
    }
    return new Promise(function (resolve, reject) {
      var area = document.createElement("textarea");
      area.value = value;
      area.setAttribute("readonly", "");
      area.style.position = "fixed";
      area.style.left = "-999px";
      document.body.appendChild(area);
      area.select();
      try {
        document.execCommand("copy");
        resolve();
      } catch (err) {
        reject(err);
      } finally {
        area.remove();
      }
    });
  }

  document.querySelectorAll("[data-copy]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var value = btn.getAttribute("data-copy") || "";
      var label = btn.querySelector(".btn-label");
      var previous = label ? label.textContent : btn.textContent;
      copyText(value).then(function () {
        if (label) label.textContent = "Copied";
        else btn.textContent = "Copied";
        window.setTimeout(function () {
          if (label) label.textContent = previous;
          else btn.textContent = previous;
        }, 1400);
      });
    });
  });

  var preset = document.querySelector("[data-expiry-preset]");
  var custom = document.querySelector("[data-expiry-custom]");
  function syncExpiry() {
    if (!preset || !custom) return;
    custom.hidden = preset.value !== "custom";
  }
  if (preset) preset.addEventListener("change", syncExpiry);
  syncExpiry();

  document.querySelectorAll("[data-suggest]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var input = document.getElementById("code");
      if (!input) return;
      var alphabet = "abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789";
      var len = Number(btn.getAttribute("data-suggest") || "6");
      var bytes = new Uint32Array(len);
      crypto.getRandomValues(bytes);
      var out = "";
      for (var i = 0; i < len; i++) out += alphabet[bytes[i] % alphabet.length];
      input.value = out;
      input.focus();
    });
  });

  document.querySelectorAll("form[data-confirm]").forEach(function (form) {
    form.addEventListener("submit", function (event) {
      var message = form.getAttribute("data-confirm") || "Are you sure?";
      if (!window.confirm(message)) event.preventDefault();
    });
  });

  var qrBtn = document.querySelector("[data-qr-open]");
  var dialog = document.getElementById("qr-dialog");
  if (qrBtn && dialog) {
    qrBtn.addEventListener("click", function () {
      var box = document.getElementById("qr-box");
      var text = qrBtn.getAttribute("data-qr-open") || "";
      if (box) {
        box.innerHTML = "";
        if (window.QRCode) {
          var opts = { text: text, width: 220, height: 220, colorDark: "#14210c", colorLight: "#f3f6ee" };
          if (window.QRCode.CorrectLevel) opts.correctLevel = window.QRCode.CorrectLevel.M;
          new window.QRCode(box, opts);
        } else {
          box.textContent = "QR code could not load.";
        }
      }
      if (dialog.showModal) dialog.showModal();
    });
    var closeBtn = dialog.querySelector("[data-qr-close]");
    if (closeBtn) closeBtn.addEventListener("click", function () { dialog.close(); });
  }
})();
