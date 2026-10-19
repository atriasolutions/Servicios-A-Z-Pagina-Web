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

  reserva.querySelectorAll('input[name="bloque"]').forEach(function (radio) {
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
    var elegido = reserva.querySelector('input[name="bloque"]:checked');
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
  var hero = document.querySelector("[data-hero-carrusel]");
  if (!hero) {
    return;
  }

  var slides = Array.prototype.slice.call(hero.querySelectorAll("[data-hero-slide]"));
  var anterior = hero.querySelector("[data-hero-anterior]");
  var siguiente = hero.querySelector("[data-hero-siguiente]");
  var puntos = hero.querySelector("[data-hero-puntos]");
  var estado = hero.querySelector("[data-hero-estado]");
  if (slides.length < 2 || !anterior || !siguiente || !puntos) {
    return;
  }

  var movimiento = window.matchMedia("(prefers-reduced-motion: reduce)");
  var intervalo = 4000;
  var actual = 0;
  var temporizador = 0;
  var sobre = false;
  var enfocado = false;

  function titulo(slide) {
    var encabezado = slide.querySelector(".hero-titulo");
    return encabezado ? encabezado.textContent.trim() : "";
  }

  slides.forEach(function (slide, indice) {
    var boton = document.createElement("button");
    boton.type = "button";
    boton.setAttribute("aria-label", "Ir a la diapositiva " + (indice + 1) + " de " + slides.length);
    boton.addEventListener("click", function () {
      mostrar(indice, true);
    });
    puntos.appendChild(boton);
  });

  function mostrar(indice, anunciar) {
    actual = (indice + slides.length) % slides.length;
    slides.forEach(function (slide, posicion) {
      var activa = posicion === actual;
      slide.classList.toggle("activa", activa);
      slide.setAttribute("aria-hidden", activa ? "false" : "true");
      if (activa) {
        slide.removeAttribute("inert");
      } else {
        slide.setAttribute("inert", "");
      }
    });
    Array.prototype.forEach.call(puntos.children, function (punto, posicion) {
      if (posicion === actual) {
        punto.setAttribute("aria-current", "true");
      } else {
        punto.removeAttribute("aria-current");
      }
    });
    if (estado && anunciar) {
      estado.textContent = "Diapositiva " + (actual + 1) + " de " + slides.length + ": " + titulo(slides[actual]);
    }
  }

  function mover(delta) {
    mostrar(actual + delta, true);
    reiniciar();
  }

  function puedeAvanzar() {
    return !movimiento.matches && !sobre && !enfocado && !document.hidden;
  }

  function detener() {
    window.clearInterval(temporizador);
    temporizador = 0;
  }

  function reanudar() {
    if (temporizador || !puedeAvanzar()) {
      return;
    }
    temporizador = window.setInterval(function () {
      mostrar(actual + 1, false);
    }, intervalo);
  }

  function reiniciar() {
    detener();
    reanudar();
  }

  anterior.addEventListener("click", function () {
    mover(-1);
  });
  siguiente.addEventListener("click", function () {
    mover(1);
  });

  hero.addEventListener("keydown", function (evento) {
    if (evento.key === "ArrowLeft") {
      evento.preventDefault();
      mover(-1);
    } else if (evento.key === "ArrowRight") {
      evento.preventDefault();
      mover(1);
    }
  });

  hero.addEventListener("mouseenter", function () {
    sobre = true;
    detener();
  });
  hero.addEventListener("mouseleave", function () {
    sobre = false;
    reanudar();
  });
  hero.addEventListener("focusin", function () {
    enfocado = true;
    detener();
  });
  hero.addEventListener("focusout", function (evento) {
    if (evento.relatedTarget && hero.contains(evento.relatedTarget)) {
      return;
    }
    enfocado = false;
    reanudar();
  });

  document.addEventListener("visibilitychange", function () {
    if (document.hidden) {
      detener();
    } else {
      reanudar();
    }
  });

  function alCambiarMovimiento() {
    if (movimiento.matches) {
      detener();
    } else {
      reanudar();
    }
  }

  if (movimiento.addEventListener) {
    movimiento.addEventListener("change", alCambiarMovimiento);
  } else if (movimiento.addListener) {
    movimiento.addListener(alCambiarMovimiento);
  }

  hero.classList.add("hero-listo");
  mostrar(0, false);
  reanudar();
})();

(function () {
  var admin = document.querySelector("[data-chat-admin]");
  if (!admin) {
    return;
  }

  function escapar(valor) {
    return String(valor).replace(/[&<>"']/g, function (caracter) {
      return {
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#39;"
      }[caracter];
    });
  }

  function pedir(datos) {
    return fetch("chat-api.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(datos)
    }).then(function (respuesta) {
      return respuesta.json().then(function (cuerpo) {
        if (!respuesta.ok || !cuerpo.ok) {
          throw new Error(cuerpo.error || "No pudimos completar la solicitud.");
        }
        return cuerpo;
      });
    });
  }

  function htmlMensajes(mensajes, visitaEsTu) {
    if (!mensajes.length) {
      return '<li class="mensaje-chat vacio">Todavía no hay mensajes.</li>';
    }
    return mensajes.map(function (mensaje) {
      var propio = visitaEsTu ? mensaje.autor === "visita" : mensaje.autor === "anfitrion";
      var autor = propio ? "Tú" : (visitaEsTu ? "A&Z" : "Visita");
      return '<li class="mensaje-chat ' + escapar(mensaje.autor) + '">'
        + '<p class="mensaje-chat-autor">' + autor + '</p>'
        + '<p class="mensaje-chat-texto">' + escapar(mensaje.texto) + '</p>'
        + '<p class="mensaje-chat-hora">' + escapar(mensaje.hora) + '</p></li>';
    }).join("");
  }

  if (admin) {
    var hilo = admin.querySelector("[data-hilo]");
    var id = admin.getAttribute("data-id");
    var csrfAdmin = admin.getAttribute("data-csrf");
    window.setInterval(function () {
      pedir({ csrf: csrfAdmin, accion: "hilo", id: Number(id) }).then(function (cuerpo) {
        var alFinal = hilo.scrollHeight - hilo.scrollTop - hilo.clientHeight < 48;
        hilo.innerHTML = htmlMensajes(cuerpo.mensajes || [], false);
        if (alFinal) {
          hilo.scrollTop = hilo.scrollHeight;
        }
      }).catch(function () {});
    }, 5000);
  }
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

(function () {
  if (!document.body.classList.contains("portada")) {
    return;
  }

  var posicionAnterior = Math.max(window.scrollY, 0);
  var actualizacionPendiente = false;
  var umbral = 4;

  function actualizarDireccion() {
    var posicionActual = Math.max(window.scrollY, 0);
    var diferencia = posicionActual - posicionAnterior;

    actualizacionPendiente = false;

    if (posicionActual <= 0) {
      document.body.classList.remove("scroll-arriba", "scroll-abajo");
      posicionAnterior = 0;
      return;
    }

    if (Math.abs(diferencia) < umbral) {
      return;
    }

    document.body.classList.toggle("scroll-abajo", diferencia > 0);
    document.body.classList.toggle("scroll-arriba", diferencia < 0);
    posicionAnterior = posicionActual;
  }

  window.addEventListener("scroll", function () {
    if (!actualizacionPendiente) {
      actualizacionPendiente = true;
      window.requestAnimationFrame(actualizarDireccion);
    }
  }, { passive: true });
})();

(function () {
  if (!document.body.classList.contains("portada") || !document.documentElement.classList.contains("con-revelado")) {
    return;
  }

  var opciones = { threshold: 0.15, rootMargin: "0px 0px -8% 0px" };
  var piezas = document.querySelectorAll(
    ".servicios h2, .contacto-presentacion, .contacto-formulario, .contacto-whatsapp, .actualidad h2, .equipo h2, .proceso h2, .beneficios h2, .acerca-contenido, .acerca-destacados, "
      + ".franja-cualidades li, .tarjeta-servicio, .tarjeta-profesional, .tarjeta-beneficio, .proceso-paso"
  );
  var observador = new IntersectionObserver(function (entradas) {
    entradas.forEach(function (entrada) {
      entrada.target.classList.toggle("visto", entrada.isIntersecting);
    });
  }, opciones);

  piezas.forEach(function (pieza) {
    observador.observe(pieza);
  });

  var actualidad = document.querySelector(".actualidad");
  if (!actualidad) {
    return;
  }

  var noticias = actualidad.querySelectorAll(".noticia-cuerpo");
  var observadorNoticias = new IntersectionObserver(function (entradas) {
    entradas.forEach(function (entrada) {
      noticias.forEach(function (noticia) {
        noticia.classList.toggle("visto", entrada.isIntersecting);
      });
    });
  }, opciones);
  observadorNoticias.observe(actualidad);
})();

(function () {
  var franja = document.querySelector(".franja-cualidades");
  if (!franja || !("IntersectionObserver" in window)) {
    return;
  }
  if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
    return;
  }

  var contador = franja.querySelector("[data-contador]");
  var meta = contador ? parseInt(contador.getAttribute("data-contador"), 10) || 0 : 0;
  var duracion = 1200;

  franja.classList.add("preparada");
  if (contador) {
    contador.textContent = "0";
  }

  function animar() {
    if (!contador) {
      franja.classList.add("animada");
      return;
    }
    var inicio = 0;
    function paso(ahora) {
      if (!inicio) {
        inicio = ahora;
      }
      var avance = Math.min((ahora - inicio) / duracion, 1);
      var suavizado = 1 - Math.pow(1 - avance, 3);
      contador.textContent = String(Math.round(meta * suavizado));
      if (avance < 1) {
        window.requestAnimationFrame(paso);
      } else {
        franja.classList.add("animada");
      }
    }
    window.requestAnimationFrame(paso);
  }

  var observador = new IntersectionObserver(function (entradas) {
    if (entradas.some(function (entrada) { return entrada.isIntersecting; })) {
      observador.disconnect();
      animar();
    }
  }, { threshold: 0.5 });
  observador.observe(franja);
})();
