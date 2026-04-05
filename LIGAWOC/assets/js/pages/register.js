(() => {
  const form = document.getElementById("regForm");
  if (!form) {
    return;
  }
  const heroItems = Array.from(document.querySelectorAll(".hero-item"));
  const rankItems = Array.from(document.querySelectorAll(".rank-item"));

  const showStep = (stepNum, direction) => {
    const current = document.querySelector(".step-container.active");
    const next = document.getElementById(`step${stepNum}`);

    if (!next) {
      return;
    }

    if (current) {
      current.classList.remove("active");
      current.classList.add(direction === "forward" ? "exiting-left" : "exiting-right");
      setTimeout(() => {
        current.classList.remove("exiting-left", "exiting-right");
      }, 450);
    }

    setTimeout(() => {
      next.classList.add("active");
    }, 60);
  };

  const nextStep = (stepNum) => {
    showStep(stepNum, "forward");
  };

  const prevStep = (stepNum) => {
    showStep(stepNum, "backward");
  };

  const buildAvailabilityUrl = (params) => {
    const formAction = form.getAttribute("action") || "";
    const fallbackBase = window.BASE_URL || "/";
    const baseUrl = /register\/?$/.test(formAction)
      ? formAction.replace(/register\/?$/, "")
      : fallbackBase;
    const query = new URLSearchParams(params).toString();
    return `${baseUrl}api/auth/register-availability?${query}`;
  };

  const checkAvailability = async (params) => {
    try {
      const response = await fetch(buildAvailabilityUrl(params), {
        headers: { "X-Requested-With": "XMLHttpRequest" },
      });
      if (!response.ok) {
        return null;
      }
      const data = await response.json();
      return data && data.success ? data.data : null;
    } catch (_error) {
      return null;
    }
  };

  const validateAndNext = async (stepFrom, stepTo) => {
    if (stepFrom === 1) {
      const email = document.getElementById("email");
      const whatsapp = document.getElementById("whatsapp");
      const discord = document.getElementById("discord");
      const phoneBrand = document.getElementById("phone_brand");

      if (!email.checkValidity()) {
        email.reportValidity();
        return;
      }
      if (!whatsapp.checkValidity()) {
        whatsapp.reportValidity();
        return;
      }
      if (!discord.checkValidity()) {
        discord.reportValidity();
        return;
      }
      if (!phoneBrand.checkValidity()) {
        phoneBrand.reportValidity();
        return;
      }

      const availability = await checkAvailability({ email: email.value.trim() });
      if (availability && availability.email && availability.email.exists) {
        email.setCustomValidity("Este correo ya esta registrado");
        email.reportValidity();
        return;
      }
    }

    if (stepFrom === 2) {
      const username = document.getElementById("username");
      const usernameValue = username.value.trim();
      if (!username.checkValidity() || usernameValue.length < 3) {
        username.reportValidity();
        return;
      }
      if (!/^[A-Za-z0-9]+$/.test(usernameValue)) {
        username.setCustomValidity("Solo letras y numeros, sin espacios ni guiones");
        username.reportValidity();
        return;
      }

      const availability = await checkAvailability({ username: usernameValue });
      if (availability && availability.username && availability.username.exists) {
        username.setCustomValidity("Este usuario ya existe");
        username.reportValidity();
        return;
      }
    }

    if (stepFrom === 3) {
      const password = document.getElementById("password");
      const passwordConfirm = document.getElementById("password_confirm");

      if (!password.checkValidity()) {
        password.reportValidity();
        return;
      }

      if (password.value.length !== 9) {
        password.setCustomValidity("La contrasena debe tener exactamente 9 caracteres");
        password.reportValidity();
        return;
      }

      if (password.value !== passwordConfirm.value) {
        passwordConfirm.setCustomValidity("Las contrasenas no coinciden");
        passwordConfirm.reportValidity();
        return;
      }
    }

    if (stepFrom === 4) {
      const mlId = document.getElementById("ml_id");
      const mlServer = document.getElementById("ml_server");
      const mlNickname = document.getElementById("ml_nickname");

      if (!mlId.checkValidity()) {
        mlId.reportValidity();
        return;
      }
      if (!mlServer.checkValidity()) {
        mlServer.reportValidity();
        return;
      }
      if (!mlNickname.checkValidity()) {
        mlNickname.reportValidity();
        return;
      }

      const availability = await checkAvailability({ ml_id: mlId.value.trim() });
      if (availability && availability.ml_id && availability.ml_id.exists) {
        mlId.setCustomValidity("Este ID de Mobile Legends ya esta registrado");
        mlId.reportValidity();
        return;
      }
    }

    nextStep(stepTo);
  };

  const passwordConfirmInput = document.getElementById("password_confirm");
  if (passwordConfirmInput) {
    passwordConfirmInput.addEventListener("input", () => {
      passwordConfirmInput.setCustomValidity("");
    });
  }

  const passwordInput = document.getElementById("password");
  if (passwordInput) {
    passwordInput.addEventListener("input", () => {
      passwordInput.setCustomValidity("");
    });
  }

  const usernameInput = document.getElementById("username");
  if (usernameInput) {
    usernameInput.addEventListener("input", () => {
      usernameInput.setCustomValidity("");
    });
  }

  const emailInput = document.getElementById("email");
  if (emailInput) {
    emailInput.addEventListener("input", () => {
      emailInput.setCustomValidity("");
    });
  }

  const numericOnlyFields = ["ml_id"];
  numericOnlyFields.forEach((fieldId) => {
    const field = document.getElementById(fieldId);
    if (!field) {
      return;
    }
    field.addEventListener("input", () => {
      field.value = field.value.replace(/\D+/g, "");
      field.setCustomValidity("");
    });
    field.addEventListener("keypress", (e) => {
      if (!/[0-9]/.test(e.key)) e.preventDefault();
    });
    field.addEventListener("paste", (e) => {
      e.preventDefault();
      const pasted = (e.clipboardData || window.clipboardData).getData("text");
      field.value = pasted.replace(/\D+/g, "");
    });
    field.addEventListener("invalid", () => {
      field.setCustomValidity("Solo se permiten numeros");
    });
  });

  const numericServerFields = ["ml_server"];
  numericServerFields.forEach((fieldId) => {
    const field = document.getElementById(fieldId);
    if (!field) {
      return;
    }
    field.addEventListener("input", () => {
      field.value = field.value.replace(/\D+/g, "");
      field.setCustomValidity("");
    });
    field.addEventListener("keypress", (e) => {
      if (!/[0-9]/.test(e.key)) e.preventDefault();
    });
    field.addEventListener("paste", (e) => {
      e.preventDefault();
      const pasted = (e.clipboardData || window.clipboardData).getData("text");
      field.value = pasted.replace(/\D+/g, "");
    });
    field.addEventListener("invalid", () => {
      field.setCustomValidity("Solo se permiten numeros");
    });
  });

  const enterMap = [
    ["email", 1, 2],
    ["username", 2, 3],
    ["password_confirm", 3, 4],
    ["ml_nickname", 4, 5],
  ];

  enterMap.forEach(([id, from, to]) => {
    const input = document.getElementById(id);
    if (!input) {
      return;
    }

    input.addEventListener("keypress", (event) => {
      if (event.key === "Enter") {
        event.preventDefault();
        void validateAndNext(from, to);
      }
    });
  });

  form.addEventListener("click", (event) => {
    const trigger = event.target.closest("[data-step-action]");
    if (!trigger) {
      return;
    }

    const action = trigger.dataset.stepAction;

    if (action === "next") {
      const target = Number(trigger.dataset.stepTarget || "0");
      nextStep(target);
      return;
    }

    if (action === "prev") {
      const target = Number(trigger.dataset.stepTarget || "0");
      prevStep(target);
      return;
    }

    if (action === "validate-next") {
      const from = Number(trigger.dataset.stepFrom || "0");
      const to = Number(trigger.dataset.stepTo || "0");
      void validateAndNext(from, to);
    }
  });

  const canvas = document.getElementById("pCanvas");
  const ctx = canvas ? canvas.getContext("2d") : null;
  let particles = [];
  let raf = null;

  const resizeCanvas = () => {
    if (!canvas || !canvas.parentElement) {
      return;
    }
    canvas.width = canvas.parentElement.offsetWidth;
    canvas.height = canvas.parentElement.offsetHeight;
  };

  const startParticlesLoop = () => {
    if (!ctx || !canvas) {
      return;
    }

    ctx.clearRect(0, 0, canvas.width, canvas.height);
    particles = particles.filter((particle) => particle.life > 0);

    particles.forEach((particle) => {
      particle.x += particle.vx;
      particle.y += particle.vy;
      particle.vy += 0.05;
      particle.life -= particle.decay;
      particle.vx *= 0.98;

      ctx.save();
      ctx.globalAlpha = Math.max(0, particle.life);
      ctx.fillStyle = `hsl(${particle.hue},80%,70%)`;
      ctx.shadowColor = `hsl(${particle.hue},80%,60%)`;
      ctx.shadowBlur = 8;
      ctx.beginPath();
      ctx.arc(particle.x, particle.y, particle.size * particle.life, 0, Math.PI * 2);
      ctx.fill();
      ctx.restore();
    });

    raf = particles.length > 0 ? requestAnimationFrame(startParticlesLoop) : null;
  };

  const burst = (cx, cy, count = 55) => {
    for (let i = 0; i < count; i += 1) {
      const angle = Math.random() * Math.PI * 2;
      const speed = 1.5 + Math.random() * 3;

      particles.push({
        x: cx,
        y: cy,
        vx: Math.cos(angle) * speed,
        vy: Math.sin(angle) * speed - 1.5,
        life: 1,
        decay: 0.018 + Math.random() * 0.025,
        size: 2 + Math.random() * 3,
        hue: 220 + Math.random() * 60,
      });
    }
  };

  const triggerBurst = () => {
    if (!canvas || !ctx) {
      return;
    }

    resizeCanvas();
    const cx = canvas.width / 2;
    const cy = canvas.height / 2;

    burst(cx, cy, 65);

    let ticks = 0;
    const drip = setInterval(() => {
      burst(cx, cy, 4);
      ticks += 1;
      if (ticks > 15) {
        clearInterval(drip);
      }
    }, 80);

    if (!raf) {
      raf = requestAnimationFrame(startParticlesLoop);
    }
  };

  window.addEventListener("resize", () => {
    if (canvas && canvas.offsetParent !== null) {
      resizeCanvas();
    }
  });

  const stageIdle = document.getElementById("stageIdle");
  const stageHero = document.getElementById("stageHero");
  const stageAvatar = document.getElementById("stageAvatar");
  const stageName = document.getElementById("stageName");
  const stageRole = document.getElementById("stageRole");
  const stageBgArt = document.getElementById("stageBgArt");
  const heroStageWrap = document.getElementById("heroStageWrap");
  const hiddenHero = document.getElementById("hiddenHero");
  const hiddenRank = document.getElementById("hiddenRank");
  const heroSearch = document.getElementById("heroSearch");

  heroItems.forEach((item) => {
    item.tabIndex = 0;

    const activateHero = () => {
      heroItems.forEach((node) => node.classList.remove("sel"));
      item.classList.add("sel");

      if (hiddenHero) {
        hiddenHero.value = item.dataset.val || "";
      }

      if (stageIdle) {
        stageIdle.classList.add("gone");
      }

      if (stageHero) {
        stageHero.classList.remove("show");
        void stageHero.offsetHeight;
        stageHero.classList.add("show");
      }

      if (stageAvatar) {
        stageAvatar.src = item.dataset.img || "";
      }

      if (stageBgArt) {
        stageBgArt.classList.remove("loaded");
        stageBgArt.style.backgroundImage = `url('${item.dataset.img || ""}')`;
        requestAnimationFrame(() => stageBgArt.classList.add("loaded"));
      }

      if (stageName) {
        stageName.textContent = (item.dataset.val || "").toUpperCase();
      }

      if (stageRole) {
        stageRole.textContent = "HEROE PRINCIPAL";
      }

      if (heroStageWrap) {
        heroStageWrap.classList.add("hero-selected");
      }

      triggerBurst();
    };

    item.addEventListener("click", activateHero);
    item.addEventListener("keydown", (event) => {
      if (event.key === "Enter" || event.key === " ") {
        event.preventDefault();
        activateHero();
      }
    });
  });

  rankItems.forEach((item) => {
    item.tabIndex = 0;

    const activateRank = () => {
      rankItems.forEach((node) => node.classList.remove("sel"));
      item.classList.add("sel");
      if (hiddenRank) {
        hiddenRank.value = item.dataset.val || "";
      }
    };

    item.addEventListener("click", activateRank);
    item.addEventListener("keydown", (event) => {
      if (event.key === "Enter" || event.key === " ") {
        event.preventDefault();
        activateRank();
      }
    });
  });

  if (heroSearch) {
    heroSearch.addEventListener("input", () => {
      const term = heroSearch.value.toLowerCase().trim();

      heroItems.forEach((item) => {
        const name = (item.dataset.val || "").toLowerCase();
        item.classList.toggle("hidden", term !== "" && !name.includes(term));
      });
    });
  }

  document.querySelectorAll(".hcard").forEach((card) => {
    card.addEventListener("mousemove", (event) => {
      if (card.classList.contains("sel")) {
        return;
      }

      const rect = card.getBoundingClientRect();
      const x = (event.clientX - rect.left) / rect.width - 0.5;
      const y = (event.clientY - rect.top) / rect.height - 0.5;
      card.style.transform = `translateY(-3px) scale(1.04) rotateX(${-y * 12}deg) rotateY(${x * 12}deg)`;
    });

    card.addEventListener("mouseleave", () => {
      card.style.transform = "";
    });
  });
})();
