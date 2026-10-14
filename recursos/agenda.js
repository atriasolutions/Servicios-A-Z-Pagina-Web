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

(function () {
  var carrusel = document.querySelector("[data-carrusel]");
  if (!carrusel) {
    return;
  }

  var pista = carrusel.querySelector(".carrusel-pista");
  var tarjetas = Array.prototype.slice.call(carrusel.querySelectorAll(".tarjeta-noticia"));
  var anterior = carrusel.querySelector("[data-carrusel-anterior]");
  var siguiente = carrusel.querySelector("[data-carrusel-siguiente]");
  var puntos = carrusel.querySelector("[data-carrusel-puntos]");
  var estado = carrusel.querySelector("[data-carrusel-estado]");
  var pagina = 0;
  var temporizador = 0;
  var reducir = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  function porPagina() {
    return window.innerWidth <= 700 ? 1 : 3;
  }

  function totalPaginas() {
    return Math.max(1, Math.ceil(tarjetas.length / porPagina()));
  }

  function dibujarPuntos() {
    var total = totalPaginas();
    puntos.innerHTML = "";
    if (total < 2) {
      return;
    }
    for (var indice = 0; indice < total; indice += 1) {
      var boton = document.createElement("button");
      boton.type = "button";
      boton.setAttribute("aria-label", "Página " + (indice + 1));
      if (indice === pagina) {
        boton.setAttribute("aria-current", "true");
      }
      boton.addEventListener("click", function (evento) {
        var elegido = Array.prototype.indexOf.call(puntos.children, evento.currentTarget);
        if (elegido >= 0) {
          pagina = elegido;
          mostrar();
        }
      });
      puntos.appendChild(boton);
    }
  }

  function mostrar() {
    var total = totalPaginas();
    if (pagina >= total) {
      pagina = 0;
    }
    if (pagina < 0) {
      pagina = total - 1;
    }
    tarjetas.forEach(function (tarjeta) {
      tarjeta.style.flexBasis = (100 / porPagina()) + "%";
    });
    pista.style.transform = "translateX(" + (-pagina * 100) + "%)";
    dibujarPuntos();
    carrusel.querySelector(".carrusel-controles").hidden = total < 2;
    if (estado) {
      estado.textContent = "Página " + (pagina + 1) + " de " + total;
    }
  }

  function mover(delta) {
    pagina += delta;
    mostrar();
  }

  function pausar() {
    window.clearInterval(temporizador);
    temporizador = 0;
  }

  function reanudar() {
    if (reducir || totalPaginas() < 2 || temporizador) {
      return;
    }
    temporizador = window.setInterval(function () {
      mover(1);
    }, 7000);
  }

  anterior.addEventListener("click", function () {
    mover(-1);
  });
  siguiente.addEventListener("click", function () {
    mover(1);
  });
  carrusel.addEventListener("keydown", function (evento) {
    if (evento.key === "ArrowLeft") {
      evento.preventDefault();
      mover(-1);
    }
    if (evento.key === "ArrowRight") {
      evento.preventDefault();
      mover(1);
    }
  });
  carrusel.addEventListener("mouseenter", pausar);
  carrusel.addEventListener("mouseleave", reanudar);
  carrusel.addEventListener("focusin", pausar);
  carrusel.addEventListener("focusout", reanudar);
  window.addEventListener("resize", function () {
    pagina = Math.min(pagina, totalPaginas() - 1);
    mostrar();
  });

  mostrar();
  reanudar();
})();

(function () {
  var cuadro = document.querySelector('textarea[name="cuerpo"]');
  var barra = document.querySelector("[data-formato]");
  if (!cuadro || !barra) {
    return;
  }

  function reemplazar(desde, hasta, nuevo, seleccionarDesde, seleccionarHasta) {
    var texto = cuadro.value;
    cuadro.value = texto.slice(0, desde) + nuevo + texto.slice(hasta);
    cuadro.focus();
    cuadro.setSelectionRange(seleccionarDesde, seleccionarHasta);
  }

  function envolver(antes, despues) {
    var inicio = cuadro.selectionStart;
    var fin = cuadro.selectionEnd;
    var elegido = cuadro.value.slice(inicio, fin);
    reemplazar(inicio, fin, antes + elegido + despues, inicio + antes.length, inicio + antes.length + elegido.length);
  }

  function lista() {
    var inicio = cuadro.selectionStart;
    var fin = cuadro.selectionEnd;
    var texto = cuadro.value;
    var desde = texto.lastIndexOf("\n", Math.max(0, inicio - 1)) + 1;
    var hasta = texto.indexOf("\n", fin);
    if (hasta < 0) {
      hasta = texto.length;
    }
    var nuevo = texto.slice(desde, hasta).split("\n").map(function (linea) {
      return linea.indexOf("- ") === 0 ? linea : "- " + linea;
    }).join("\n");
    reemplazar(desde, hasta, nuevo, desde, desde + nuevo.length);
  }

  function enlace() {
    var inicio = cuadro.selectionStart;
    var fin = cuadro.selectionEnd;
    var elegido = cuadro.value.slice(inicio, fin);
    var direccion = window.prompt("Dirección del enlace (https://)");
    if (!direccion) {
      return;
    }
    direccion = direccion.trim();
    if (!/^https?:\/\//i.test(direccion)) {
      window.alert("La dirección tiene que empezar por http:// o https://.");
      return;
    }
    var visible = elegido || "enlace";
    var marca = "[" + visible + "](" + direccion + ")";
    reemplazar(inicio, fin, marca, inicio + 1, inicio + 1 + visible.length);
  }

  barra.addEventListener("click", function (evento) {
    var boton = evento.target.closest("button[data-marca]");
    if (!boton) {
      return;
    }
    var marca = boton.getAttribute("data-marca");
    if (marca === "negrita") {
      envolver("**", "**");
    } else if (marca === "cursiva") {
      envolver("*", "*");
    } else if (marca === "lista") {
      lista();
    } else if (marca === "enlace") {
      enlace();
    }
  });
})();
