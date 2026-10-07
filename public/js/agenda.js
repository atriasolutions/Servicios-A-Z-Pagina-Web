(function () {
  document.querySelectorAll("form[data-confirmar]").forEach(function (formulario) {
    formulario.addEventListener("submit", function (evento) {
      var texto = formulario.getAttribute("data-confirmar");
      if (texto && !window.confirm(texto)) {
        evento.preventDefault();
      }
    });
  });

  var reserva = document.querySelector("[data-reserva]");
  if (!reserva) {
    return;
  }

  reserva.querySelectorAll('input[name="hora"]').forEach(function (radio) {
    radio.addEventListener("change", function () {
      reserva.querySelectorAll(".bloque").forEach(function (item) {
        item.classList.remove("elegido");
      });
      if (radio.checked) {
        radio.closest(".bloque").classList.add("elegido");
      }
    });
  });

  reserva.addEventListener("submit", function (evento) {
    var elegido = reserva.querySelector('input[name="hora"]:checked');
    if (!elegido) {
      return;
    }
    var etiqueta = elegido.closest(".bloque").querySelector("span").textContent.trim();
    var dia = reserva.getAttribute("data-dia") || "día elegido";
    var tramite = reserva.getAttribute("data-servicio") || "la asesoría";
    if (!window.confirm("¿Reservar " + tramite + " para el " + dia + ", " + etiqueta + "?")) {
      evento.preventDefault();
    }
  });
})();
