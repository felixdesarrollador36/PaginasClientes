(() => {
  const form = document.getElementById("resetForm");
  if (!form) {
    return;
  }

  const boxes = Array.from(document.querySelectorAll(".otp-box"));
  const hidden = document.getElementById("codeHidden");
  const passwordInput = document.getElementById("newPass");
  const confirmInput = document.getElementById("newPass2");

  if (!hidden || !passwordInput || !confirmInput || boxes.length === 0) {
    return;
  }

  const syncCode = () => {
    hidden.value = boxes.map((box) => box.value).join("");
  };

  boxes.forEach((box, index) => {
    box.addEventListener("input", () => {
      if (box.value.length > 1) {
        box.value = box.value.slice(-1);
      }

      box.value = box.value.replace(/[^0-9]/g, "");
      box.classList.toggle("filled", Boolean(box.value));

      if (box.value && index < boxes.length - 1) {
        boxes[index + 1].focus();
      }

      syncCode();
    });

    box.addEventListener("keydown", (event) => {
      if (event.key === "Backspace" && !box.value && index > 0) {
        const previous = boxes[index - 1];
        previous.value = "";
        previous.classList.remove("filled");
        previous.focus();
        syncCode();
      }
    });

    box.addEventListener("paste", (event) => {
      event.preventDefault();
      const pasted = (event.clipboardData || window.clipboardData)
        .getData("text")
        .replace(/\D/g, "")
        .slice(0, boxes.length);

      pasted.split("").forEach((digit, pasteIndex) => {
        if (!boxes[pasteIndex]) {
          return;
        }
        boxes[pasteIndex].value = digit;
        boxes[pasteIndex].classList.add("filled");
      });

      if (boxes[pasted.length]) {
        boxes[pasted.length].focus();
      } else {
        boxes[boxes.length - 1].focus();
      }

      syncCode();
    });
  });

  confirmInput.addEventListener("input", () => {
    confirmInput.classList.remove("input-error");
  });

  form.addEventListener("submit", (event) => {
    syncCode();

    if (hidden.value.length < boxes.length) {
      event.preventDefault();
      boxes[0].focus();
      return;
    }

    if (passwordInput.value !== confirmInput.value) {
      event.preventDefault();
      confirmInput.classList.add("input-error");
      confirmInput.focus();
    }
  });
})();
