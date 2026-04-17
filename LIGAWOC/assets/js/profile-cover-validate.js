// Valida que la portada subida tenga exactamente 1109x340px
// Debe enlazarse en profile.php

document.addEventListener('DOMContentLoaded', function() {
  const portadaInput = document.querySelector('input[type="file"][name="portada"]');
  if (!portadaInput) return;

  portadaInput.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;
    const img = new Image();
    img.onload = function() {
      if (img.width !== 1109 || img.height !== 340) {
        alert('La portada debe ser exactamente de 1109x340 píxeles.');
        portadaInput.value = '';
      }
    };
    img.onerror = function() {
      alert('No se pudo leer la imagen.');
      portadaInput.value = '';
    };
    img.src = URL.createObjectURL(file);
  });
});
