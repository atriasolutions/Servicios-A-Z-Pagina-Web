(function () {
  var correo = /^[^\s@]+@[^\s@]+\.[a-z]{2,}$/i;

  function esCelularChileno(valor) {
    var digitos = valor.replace(/\D/g, "");
    if (digitos.length === 11 && digitos.indexOf("569") === 0) {
      digitos = digitos.slice(2);
    } else if (digitos.length === 10 && digitos.indexOf("09") === 0) {
      digitos = digitos.slice(1);
    }
    return /^9\d{8}$/.test(digitos);
  }

  document.querySelectorAll(".contacto-form, .reserva-publica").forEach(function (formulario) {
    var email = formulario.querySelector('input[name="email"]');
    var telefono = formulario.querySelector('input[name="telefono"]');

    function revisar(entrada) {
      if (!entrada || entrada.disabled) {
        return;
      }
      entrada.setCustomValidity("");
      if (entrada.value.trim() === "") {
        return;
      }
      if (entrada === email && !correo.test(entrada.value.trim())) {
        entrada.setCustomValidity("Escribe un correo válido, por ejemplo nombre@correo.cl.");
      }
      if (entrada === telefono && !esCelularChileno(entrada.value)) {
        entrada.setCustomValidity("Escribe un celular chileno, por ejemplo +56 9 1234 5678.");
      }
    }

    [email, telefono].forEach(function (entrada) {
      if (!entrada) {
        return;
      }
      entrada.addEventListener("input", function () {
        revisar(entrada);
      });
    });
    formulario.addEventListener("submit", function () {
      revisar(email);
      revisar(telefono);
    }, true);
  });
})();

(function () {
  document.querySelectorAll(".contacto-form, .reserva-publica").forEach(function (formulario) {
    var campos = formulario.querySelectorAll("[data-campo-empresa]");
    var etiquetaNombre = formulario.querySelector("[data-etiqueta-nombre]");
    var etiquetaContacto = formulario.querySelector("[data-etiqueta-contacto]");

    function aplicarTipoContacto() {
      var elegido = formulario.querySelector('input[name="tipo_persona"]:checked');
      var empresa = elegido !== null && elegido.value === "empresa";
      campos.forEach(function (campo) {
        campo.hidden = !empresa;
        campo.querySelectorAll("input").forEach(function (entrada) {
          entrada.disabled = !empresa;
          entrada.required = empresa;
        });
      });
      if (etiquetaNombre) {
        etiquetaNombre.hidden = empresa;
      }
      if (etiquetaContacto) {
        etiquetaContacto.hidden = !empresa;
      }
    }

    formulario.addEventListener("change", function (evento) {
      if (evento.target && evento.target.name === "tipo_persona") {
        aplicarTipoContacto();
      }
    });
    aplicarTipoContacto();
  });
})();

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
  var contador = carrusel.querySelector("[data-carrusel-contador]");
  var pagina = 0;
  var temporizador = 0;
  var reducir = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  function porPagina() {
    if (window.innerWidth <= 700) {
      return 1;
    }
    return window.innerWidth <= 1100 ? 2 : 4;
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
    if (contador) {
      contador.textContent = (pagina + 1) + " / " + total;
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
      mostrarToast("La dirección tiene que empezar por http:// o https://.");
      return;
    }
    var visible = elegido || "enlace";
    var marca = "[" + visible + "](" + direccion + ")";
    reemplazar(inicio, fin, marca, inicio + 1, inicio + 1 + visible.length);
  }

  var entradaImagen = barra.querySelector("[data-imagen-articulo]");
  var botonImagen = barra.querySelector('[data-marca="imagen"]');
  var formulario = cuadro.closest("form");
  var campoCsrf = formulario ? formulario.querySelector('input[name="csrf"]') : null;
  var subiendoImagen = false;

  function textoAlternativo(valor) {
    var limpio = String(valor || "").replace(/[\[\]\r\n]/g, " ").replace(/\s+/g, " ").trim();
    if (!limpio) {
      return "Imagen";
    }
    if (limpio.length > 200) {
      return limpio.slice(0, 200).trim() || "Imagen";
    }
    return limpio;
  }

  function insertarLinea(marca) {
    var inicio = cuadro.selectionStart;
    var fin = cuadro.selectionEnd;
    var texto = cuadro.value;
    var antes = texto.slice(0, inicio).replace(/\n+$/, "");
    var despues = texto.slice(fin).replace(/^\n+/, "");
    var prefijo = antes === "" ? "" : "\n\n";
    var sufijo = "\n\n";
    cuadro.value = antes + prefijo + marca + sufijo + despues;
    var cursor = antes.length + prefijo.length + marca.length + sufijo.length;
    cuadro.focus();
    cuadro.setSelectionRange(cursor, cursor);
  }

  function restaurarBotonImagen() {
    subiendoImagen = false;
    if (!botonImagen) {
      return;
    }
    botonImagen.disabled = false;
    var etiqueta = botonImagen.querySelector("span");
    if (etiqueta) {
      etiqueta.textContent = "Imagen";
    }
  }

  function subirImagen(archivo) {
    if (!campoCsrf || !campoCsrf.value) {
      mostrarToast("La solicitud no es válida. Vuelve a cargar la página.");
      return;
    }
    if (archivo.size > 5 * 1024 * 1024) {
      mostrarToast("La imagen puede pesar hasta 5 MB.");
      return;
    }
    if (archivo.type === "image/svg+xml") {
      mostrarToast("No se aceptan archivos SVG.");
      return;
    }

    subiendoImagen = true;
    if (botonImagen) {
      botonImagen.disabled = true;
      var etiqueta = botonImagen.querySelector("span");
      if (etiqueta) {
        etiqueta.textContent = "Subiendo…";
      }
    }

    var datos = new FormData();
    datos.append("csrf", campoCsrf.value);
    datos.append("imagen", archivo);

    fetch("articulo-imagen.php", {
      method: "POST",
      body: datos,
      credentials: "same-origin"
    }).then(function (respuesta) {
      return respuesta.text().then(function (texto) {
        var cuerpo;
        try {
          cuerpo = JSON.parse(texto);
        } catch (error) {
          throw new Error("No se pudo subir la imagen.");
        }
        if (!cuerpo || typeof cuerpo !== "object" || !respuesta.ok || !cuerpo.ok || typeof cuerpo.url !== "string") {
          throw new Error((cuerpo && cuerpo.error) || "No se pudo subir la imagen.");
        }
        if (!/^\/img\/articulos\/[a-f0-9]{32}\.(jpg|png|webp)$/.test(cuerpo.url)) {
          throw new Error("No se pudo subir la imagen.");
        }
        return cuerpo.url;
      });
    }).then(function (url) {
      restaurarBotonImagen();
      var descripcion = window.prompt("Texto alternativo de la imagen");
      insertarLinea("![" + textoAlternativo(descripcion) + "](" + url + ")");
    }).catch(function (error) {
      restaurarBotonImagen();
      mostrarToast(error.message || "No se pudo subir la imagen.");
    });
  }

  if (entradaImagen) {
    entradaImagen.addEventListener("change", function () {
      var archivo = entradaImagen.files && entradaImagen.files[0];
      entradaImagen.value = "";
      if (!archivo || subiendoImagen) {
        return;
      }
      subirImagen(archivo);
    });
  }

  barra.addEventListener("click", function (evento) {
    var boton = evento.target.closest("button[data-marca]");
    if (!boton || boton.disabled) {
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
    } else if (marca === "imagen") {
      if (entradaImagen && !subiendoImagen) {
        entradaImagen.click();
      }
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

(function () {
  var seccion = document.querySelector(".por-que-elegirnos");
  if (!seccion || !("IntersectionObserver" in window)) {
    return;
  }
  if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
    return;
  }

  seccion.classList.add("preparada");
  var observador = new IntersectionObserver(function (entradas) {
    if (entradas.some(function (entrada) { return entrada.isIntersecting; })) {
      observador.disconnect();
      seccion.classList.add("animada");
    }
  }, { threshold: 0.35 });
  observador.observe(seccion);
})();

function raizToasts() {
  return document.querySelector("main.lienzo, main.contenido");
}

function listaToasts(raiz) {
  if (!raiz) {
    return [];
  }
  return Array.prototype.filter.call(raiz.children, function (hijo) {
    return hijo.classList && hijo.classList.contains("aviso") && hijo.classList.contains("toast");
  });
}

function mostrarToast(mensaje, tipo) {
  var texto = String(mensaje || "").trim();
  var raiz = raizToasts();
  if (!texto || !raiz) {
    return;
  }
  var toast = document.createElement("p");
  toast.className = "aviso " + (tipo === "ok" ? "ok" : "error") + " toast";
  toast.setAttribute("role", "status");
  toast.textContent = texto;
  var existentes = listaToasts(raiz);
  var ancla = existentes.length ? existentes[existentes.length - 1].nextSibling : raiz.firstChild;
  raiz.insertBefore(toast, ancla);
  if (existentes.length) {
    var movil = window.matchMedia("(max-width: 700px)").matches;
    var base = movil ? 1.25 : 5.25;
    var separacion = 0;
    existentes.forEach(function (existente) {
      separacion += existente.offsetHeight + 12;
    });
    toast.style.bottom = "calc(" + base + "rem + " + separacion + "px)";
  }
  window.setTimeout(function () {
    toast.remove();
  }, 6000);
}

(function () {
  var raiz = raizToasts();
  if (!raiz) {
    return;
  }
  Array.prototype.forEach.call(document.querySelectorAll(".aviso.toast"), function (toast) {
    if (toast.parentElement !== raiz) {
      raiz.insertBefore(toast, raiz.firstChild);
    }
  });
  var toasts = listaToasts(raiz);
  if (!toasts.length) {
    return;
  }
  if (toasts.length > 1) {
    var movil = window.matchMedia("(max-width: 700px)").matches;
    var base = movil ? 1.25 : 5.25;
    var separacion = 0;
    toasts.forEach(function (toast) {
      if (separacion > 0) {
        toast.style.bottom = "calc(" + base + "rem + " + separacion + "px)";
      }
      separacion += toast.offsetHeight + 12;
    });
  }
  window.setTimeout(function () {
    toasts.forEach(function (toast) {
      toast.remove();
    });
  }, 6000);
})();

(function () {
  var tira = document.querySelector(".calendarios");
  if (!tira) {
    return;
  }

  function meses() {
    return tira.querySelectorAll(".mes-calendario");
  }

  function indiceVisible() {
    var ancho = tira.clientWidth || 1;
    var indice = Math.round(tira.scrollLeft / ancho);
    var ultimo = meses().length - 1;
    return Math.max(0, Math.min(indice, ultimo));
  }

  var anterior = document.querySelector("[data-mes-anterior]");
  var siguiente = document.querySelector("[data-mes-siguiente]");

  function mostrar(indice) {
    if (indice < 0 || indice >= meses().length) {
      return;
    }
    tira.scrollLeft = indice * tira.clientWidth;
    actualizarFlechas();
  }

  function actualizarFlechas() {
    var indice = indiceVisible();
    var ultimo = meses().length - 1;
    if (anterior) {
      anterior.disabled = indice <= 0;
    }
    if (siguiente) {
      siguiente.disabled = indice >= ultimo;
    }
  }

  var activo = tira.querySelector(".dia-link.activo");
  if (activo) {
    var mesActivo = activo.closest(".mes-calendario");
    mostrar(Array.prototype.indexOf.call(meses(), mesActivo));
  } else {
    actualizarFlechas();
  }

  if (anterior) {
    anterior.addEventListener("click", function () {
      mostrar(indiceVisible() - 1);
    });
  }
  if (siguiente) {
    siguiente.addEventListener("click", function () {
      mostrar(indiceVisible() + 1);
    });
  }

  var ocupado = false;
  tira.addEventListener("wheel", function (evento) {
    var horizontal = Math.abs(evento.deltaX) > Math.abs(evento.deltaY);
    var delta = horizontal ? evento.deltaX : evento.deltaY;
    if (Math.abs(delta) < 4) {
      return;
    }
    var destino = indiceVisible() + (delta > 0 ? 1 : -1);
    if (destino < 0 || destino >= meses().length) {
      return;
    }
    evento.preventDefault();
    if (ocupado) {
      return;
    }
    ocupado = true;
    mostrar(destino);
    window.setTimeout(function () {
      ocupado = false;
    }, 280);
  }, { passive: false });
  tira.addEventListener("scroll", actualizarFlechas);
})();
