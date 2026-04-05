(() => {
  const form = document.getElementById("verifyForm");
  if (!form) {
    return;
  }

  const boxes = Array.from(document.querySelectorAll(".otp-box"));
  const hidden = document.getElementById("otpHidden");
  const resendEmail = document.getElementById("resendEmail");
  const emailInput = document.getElementById("emailInput");
  const row = document.querySelector(".otp-row");

  if (!hidden || !resendEmail || !emailInput || !row || boxes.length === 0) {
    return;
  }

  const syncHidden = () => {
    hidden.value = boxes.map((box) => box.value).join("");
  };

  const triggerShake = () => {
    row.classList.remove("shake");
    void row.offsetWidth;
    row.classList.add("shake");
  };

  emailInput.addEventListener("input", () => {
    resendEmail.value = emailInput.value;
  });

  boxes.forEach((box, index) => {
    box.addEventListener("input", () => {
      if (box.value.length > 1) {
        box.value = box.value.slice(-1);
      }

      box.value = box.value.replace(/[^0-9]/g, "");

      if (box.value) {
        box.classList.add("filled");
        if (index < boxes.length - 1) {
          boxes[index + 1].focus();
        }
      } else {
        box.classList.remove("filled");
      }

      syncHidden();
    });

    box.addEventListener("keydown", (event) => {
      if (event.key === "Backspace" && !box.value && index > 0) {
        const previous = boxes[index - 1];
        previous.value = "";
        previous.classList.remove("filled");
        previous.focus();
        syncHidden();
      }

      if (event.key === "ArrowLeft" && index > 0) {
        boxes[index - 1].focus();
      }

      if (event.key === "ArrowRight" && index < boxes.length - 1) {
        boxes[index + 1].focus();
      }
    });

    box.addEventListener("paste", (event) => {
      event.preventDefault();

      const pasted = (event.clipboardData || window.clipboardData)
        .getData("text")
        .replace(/\D/g, "")
        .slice(0, boxes.length);

      pasted.split("").forEach((digit, pastedIndex) => {
        if (!boxes[pastedIndex]) {
          return;
        }
        boxes[pastedIndex].value = digit;
        boxes[pastedIndex].classList.add("filled");
      });

      if (pasted.length < boxes.length && boxes[pasted.length]) {
        boxes[pasted.length].focus();
      } else {
        boxes[boxes.length - 1].focus();
      }

      syncHidden();
    });
  });

  form.addEventListener("submit", (event) => {
    syncHidden();

    if (hidden.value.length < boxes.length || /[^0-9]/.test(hidden.value)) {
      event.preventDefault();
      boxes[0].focus();
      triggerShake();
    }
  });

  boxes[0].focus();
})();
