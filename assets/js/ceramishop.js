/* =================================================================
 * CERAMISHOP – comportamiento común del sitio web
 * - Validación de formularios y prevención de doble envío
 * - Estados de carga en botones y descargas (PDF / Excel)
 * - Carrito sin recargar la página (con alternativa sin JavaScript)
 * - Aviso de cookies y analítica con consentimiento
 * ================================================================= */
(function () {
    'use strict';

    var CFG = window.CS_CONFIG || {};
    var CS = window.CS = window.CS || {};

    /* ---------- Avisos (toasts) accesibles ---------- */
    function zonaToasts() {
        var z = document.getElementById('csToasts');
        if (!z) {
            z = document.createElement('div');
            z.id = 'csToasts';
            z.className = 'toast-container position-fixed top-0 end-0 p-3';
            z.style.zIndex = 1090;
            z.setAttribute('aria-live', 'polite');
            z.setAttribute('aria-atomic', 'true');
            document.body.appendChild(z);
        }
        return z;
    }

    CS.aviso = function (mensaje, tipo) {
        tipo = tipo || 'info';
        var colores = { success: 'text-bg-success', error: 'text-bg-danger', warning: 'text-bg-warning', info: 'text-bg-dark' };
        var iconos = { success: 'check-circle-fill', error: 'exclamation-octagon-fill', warning: 'exclamation-triangle-fill', info: 'info-circle-fill' };
        var t = document.createElement('div');
        t.className = 'toast align-items-center border-0 ' + (colores[tipo] || colores.info);
        t.setAttribute('role', tipo === 'error' ? 'alert' : 'status');
        var cuerpo = document.createElement('div');
        cuerpo.className = 'd-flex';
        var texto = document.createElement('div');
        texto.className = 'toast-body d-flex gap-2 align-items-start';
        var icono = document.createElement('i');
        icono.className = 'bi bi-' + (iconos[tipo] || iconos.info);
        icono.setAttribute('aria-hidden', 'true');
        var span = document.createElement('span');
        span.textContent = mensaje;
        texto.appendChild(icono);
        texto.appendChild(span);
        var cerrar = document.createElement('button');
        cerrar.type = 'button';
        cerrar.className = 'btn-close btn-close-white me-2 m-auto';
        cerrar.setAttribute('data-bs-dismiss', 'toast');
        cerrar.setAttribute('aria-label', 'Cerrar');
        cuerpo.appendChild(texto);
        cuerpo.appendChild(cerrar);
        t.appendChild(cuerpo);
        zonaToasts().appendChild(t);
        if (window.bootstrap && window.bootstrap.Toast) {
            var toast = new window.bootstrap.Toast(t, { delay: tipo === 'error' ? 7000 : 4000 });
            t.addEventListener('hidden.bs.toast', function () { t.remove(); });
            toast.show();
        } else {
            t.classList.add('show');
            setTimeout(function () { t.remove(); }, 5000);
        }
    };

    /* ---------- Estado de carga en botones ---------- */
    CS.botonCargando = function (btn, cargando) {
        if (!btn) return;
        if (cargando) {
            if (btn.getAttribute('aria-busy') === 'true') return;
            btn.dataset.textoOriginal = btn.innerHTML;
            btn.setAttribute('aria-busy', 'true');
            btn.disabled = true;
            var texto = btn.dataset.cargando || 'Procesando…';
            btn.innerHTML = '<span class="spinner-border" aria-hidden="true"></span><span>' + escapar(texto) + '</span>';
        } else {
            btn.removeAttribute('aria-busy');
            btn.disabled = false;
            if (btn.dataset.textoOriginal) btn.innerHTML = btn.dataset.textoOriginal;
        }
    };

    function escapar(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    /* ---------- Validación de formularios ---------- */
    function mensajeCampo(campo) {
        var v = campo.validity;
        if (campo.dataset.mensaje && !v.valid) return campo.dataset.mensaje;
        if (v.valueMissing) return 'Este campo es obligatorio.';
        if (v.typeMismatch && campo.type === 'email') return 'Escribe un correo válido, por ejemplo nombre@correo.com.';
        if (v.tooShort) return 'Debe tener al menos ' + campo.minLength + ' caracteres.';
        if (v.tooLong) return 'Debe tener máximo ' + campo.maxLength + ' caracteres.';
        if (v.rangeUnderflow) return 'El valor mínimo es ' + campo.min + '.';
        if (v.rangeOverflow) return 'El valor máximo es ' + campo.max + '.';
        if (v.stepMismatch) return 'Escribe un número válido.';
        if (v.badInput) return 'Escribe un número válido.';
        if (v.patternMismatch) return campo.title || 'El formato no es válido.';
        if (v.customError) return campo.validationMessage;
        return 'Revisa este campo.';
    }

    function mostrarErrorCampo(campo) {
        var id = campo.id || (campo.name ? 'campo-' + campo.name.replace(/\W/g, '') : '');
        if (!campo.id && id) campo.id = id;
        var fb = campo.parentElement.querySelector('.invalid-feedback[data-auto]');
        if (!campo.validity.valid) {
            if (!fb) {
                fb = document.createElement('div');
                fb.className = 'invalid-feedback';
                fb.setAttribute('data-auto', '');
                fb.id = id + '-error';
                var ancla = campo.closest('.input-group, .campo-clave') || campo;
                ancla.insertAdjacentElement('afterend', fb);
                if (ancla !== campo) fb.style.display = 'block';
            }
            fb.textContent = mensajeCampo(campo);
            campo.classList.add('is-invalid');
            campo.setAttribute('aria-invalid', 'true');
            campo.setAttribute('aria-describedby', fb.id);
        } else {
            campo.classList.remove('is-invalid');
            campo.removeAttribute('aria-invalid');
            if (fb) fb.remove();
        }
    }

    function validarFormulario(form) {
        // Confirmación de contraseña
        var clave2 = form.querySelector('[data-igual-a]');
        if (clave2) {
            var original = form.querySelector(clave2.dataset.igualA);
            clave2.setCustomValidity(original && original.value !== clave2.value ? 'Las contraseñas no coinciden.' : '');
        }
        var campos = form.querySelectorAll('input, select, textarea');
        var primero = null;
        campos.forEach(function (c) {
            if (c.type === 'hidden' || c.disabled || c.closest('.trampa')) return;
            mostrarErrorCampo(c);
            if (!c.validity.valid && !primero) primero = c;
        });
        if (primero) {
            primero.focus();
            return false;
        }
        return true;
    }

    document.addEventListener('submit', function (ev) {
        var form = ev.target;
        if (!(form instanceof HTMLFormElement)) return;

        if (form.dataset.confirmar && !window.confirm(form.dataset.confirmar)) {
            ev.preventDefault();
            return;
        }
        if (form.hasAttribute('data-validar') && !validarFormulario(form)) {
            ev.preventDefault();
            CS.aviso('Revisa los campos marcados en rojo.', 'warning');
            return;
        }
        // Prevención de doble envío
        if (form.dataset.enviando === '1') {
            ev.preventDefault();
            return;
        }
        if (form.classList.contains('form-agregar') && form.hasAttribute('data-ajax') && window.fetch && window.FormData) {
            ev.preventDefault();
            agregarAlCarrito(form, ev.submitter);
            return;
        }
        if (!form.hasAttribute('data-sin-bloqueo')) {
            form.dataset.enviando = '1';
            var btn = ev.submitter || form.querySelector('[type="submit"]');
            if (btn && !form.hasAttribute('target')) {
                // Conserva el valor del botón pulsado (los botones deshabilitados no se envían)
                if (btn.name) {
                    var oculto = document.createElement('input');
                    oculto.type = 'hidden';
                    oculto.name = btn.name;
                    oculto.value = btn.value;
                    form.appendChild(oculto);
                }
                CS.botonCargando(btn, true);
            }
            if (form.hasAttribute('target')) {
                setTimeout(function () { form.dataset.enviando = ''; }, 3000);
            }
        }
    }, true);

    // Validar al salir de un campo que ya fue marcado
    document.addEventListener('input', function (ev) {
        var c = ev.target;
        if (c.classList && c.classList.contains('is-invalid')) mostrarErrorCampo(c);
    });

    // Volver de la caché del navegador (botón atrás) con botones bloqueados
    window.addEventListener('pageshow', function (ev) {
        if (ev.persisted) {
            document.querySelectorAll('form[data-enviando="1"]').forEach(function (f) { f.dataset.enviando = ''; });
            document.querySelectorAll('[aria-busy="true"]').forEach(function (b) { CS.botonCargando(b, false); });
        }
    });

    /* ---------- Confirmaciones en enlaces y botones ---------- */
    document.addEventListener('click', function (ev) {
        var el = ev.target.closest('a[data-confirmar], button[data-confirmar]:not([type="submit"])');
        if (el && !window.confirm(el.dataset.confirmar)) {
            ev.preventDefault();
            ev.stopImmediatePropagation();
        }
    });

    /* ---------- Descargas (PDF / Excel): indicador de carga ---------- */
    document.addEventListener('click', function (ev) {
        var a = ev.target.closest('[data-descarga]');
        if (!a || a.getAttribute('aria-busy') === 'true') {
            if (a) ev.preventDefault();
            return;
        }
        var texto = a.innerHTML;
        a.setAttribute('aria-busy', 'true');
        a.classList.add('disabled');
        a.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Generando…';
        CS.track('descarga_archivo', { tipo: a.dataset.descarga });
        setTimeout(function () {
            a.removeAttribute('aria-busy');
            a.classList.remove('disabled');
            a.innerHTML = texto;
        }, 3500);
    });

    /* ---------- Mostrar / ocultar contraseña ---------- */
    document.addEventListener('click', function (ev) {
        var b = ev.target.closest('.btn-ver-clave');
        if (!b) return;
        var input = b.parentElement.querySelector('input');
        var visible = input.type === 'text';
        input.type = visible ? 'password' : 'text';
        b.setAttribute('aria-pressed', String(!visible));
        b.setAttribute('aria-label', visible ? 'Mostrar contraseña' : 'Ocultar contraseña');
        b.querySelector('i').className = 'bi bi-' + (visible ? 'eye' : 'eye-slash');
    });

    /* ---------- Carrito sin recargar ---------- */
    function actualizarContador(n) {
        document.querySelectorAll('[data-carrito-contador]').forEach(function (b) {
            b.textContent = n;
            b.hidden = !n;
            var enlace = b.closest('a');
            if (enlace) enlace.setAttribute('aria-label', 'Carrito de compras, ' + n + ' productos');
        });
    }

    function agregarAlCarrito(form, boton) {
        var btn = boton || form.querySelector('[type="submit"]');
        form.dataset.enviando = '1';
        CS.botonCargando(btn, true);
        var datos = new FormData(form);
        var controlador = window.AbortController ? new AbortController() : null;
        var limite = setTimeout(function () { if (controlador) controlador.abort(); }, 15000);
        fetch(form.action, {
            method: 'POST',
            body: datos,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            signal: controlador ? controlador.signal : undefined
        }).then(function (r) {
            return r.json().catch(function () { return { success: false, message: 'Respuesta inesperada del servidor.' }; })
                .then(function (j) { j._estado = r.status; return j; });
        }).then(function (j) {
            if (j._estado === 401 && j.redirigir) {
                window.location.href = j.redirigir;
                return;
            }
            if (j.success) {
                actualizarContador(j.carrito || 0);
                CS.aviso(j.message, 'success');
                CS.track('add_to_cart', j.evento || {});
            } else {
                CS.aviso(j.message || 'No se pudo agregar el producto.', j.codigo === 'sin_stock' ? 'warning' : 'error');
            }
        }).catch(function (err) {
            CS.aviso(err && err.name === 'AbortError'
                ? 'El servidor tardó demasiado en responder. Intenta de nuevo.'
                : 'No hay conexión con el servidor. Revisa tu internet e intenta de nuevo.', 'error');
        }).then(function () {
            clearTimeout(limite);
            form.dataset.enviando = '';
            CS.botonCargando(btn, false);
        });
    }

    /* ---------- Filtro rápido de tablas (administración) ---------- */
    document.addEventListener('input', function (ev) {
        var input = ev.target.closest('[data-filtro-tabla]');
        if (!input) return;
        var tabla = document.querySelector(input.dataset.filtroTabla);
        if (!tabla) return;
        var valor = input.value.toLowerCase().trim();
        var visibles = 0;
        tabla.querySelectorAll('tbody tr').forEach(function (f) {
            var ok = f.innerText.toLowerCase().indexOf(valor) !== -1;
            f.hidden = !ok;
            if (ok) visibles++;
        });
        var contador = document.querySelector(input.dataset.contador || '#nada');
        if (contador) contador.textContent = visibles;
    });

    /* ---------- Conexión ---------- */
    window.addEventListener('offline', function () { CS.aviso('Perdiste la conexión a internet. Algunas acciones no funcionarán hasta que vuelvas a conectarte.', 'warning'); });
    window.addEventListener('online', function () { CS.aviso('Conexión restablecida.', 'success'); });

    /* =================================================================
     * COOKIES Y ANALÍTICA
     * Solo se carga Google Analytics si existe un ID configurado y el
     * visitante acepta las cookies analíticas. No se envían datos personales.
     * ================================================================= */
    var CLAVE = 'cs_consentimiento';
    function leerConsentimiento() {
        try { return window.localStorage.getItem(CLAVE); } catch (e) { return null; }
    }
    function guardarConsentimiento(v) {
        try { window.localStorage.setItem(CLAVE, v); } catch (e) { /* almacenamiento no disponible */ }
        document.cookie = CLAVE + '=' + v + ';path=/;max-age=' + (60 * 60 * 24 * 180) + ';SameSite=Lax' + (location.protocol === 'https:' ? ';Secure' : '');
    }

    var analiticaCargada = false;
    var pendientes = [];
    function cargarAnalitica() {
        if (analiticaCargada || !CFG.ga4) return;
        analiticaCargada = true;
        window.dataLayer = window.dataLayer || [];
        window.gtag = function () { window.dataLayer.push(arguments); };
        window.gtag('js', new Date());
        window.gtag('config', CFG.ga4, { anonymize_ip: true });
        var s = document.createElement('script');
        s.async = true;
        s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(CFG.ga4);
        document.head.appendChild(s);
        pendientes.forEach(function (p) { window.gtag('event', p[0], p[1]); });
        pendientes = [];
    }

    /** Registra un evento de analítica (no-op sin consentimiento o sin ID configurado). */
    CS.track = function (evento, params) {
        params = params || {};
        if (CFG.debug && window.console) console.debug('[analítica]', evento, params);
        if (leerConsentimiento() !== 'todas' || !CFG.ga4) return;
        if (window.gtag && analiticaCargada) window.gtag('event', evento, params);
        else pendientes.push([evento, params]);
    };

    function mostrarAvisoCookies(mostrar) {
        var aviso = document.getElementById('avisoCookies');
        if (!aviso) return;
        aviso.hidden = !mostrar;
        document.body.classList.toggle('cookies-visibles', mostrar);
        if (mostrar) {
            document.body.style.setProperty('--alto-cookies', (aviso.offsetHeight + 16) + 'px');
        }
    }

    document.addEventListener('click', function (ev) {
        var b = ev.target.closest('[data-cookies]');
        if (b) {
            guardarConsentimiento(b.dataset.cookies);
            mostrarAvisoCookies(false);
            if (b.dataset.cookies === 'todas') cargarAnalitica();
            CS.aviso('Preferencias de cookies guardadas.', 'success');
        }
        if (ev.target.closest('[data-cookies-configurar]')) {
            mostrarAvisoCookies(true);
            var primero = document.querySelector('#avisoCookies button');
            if (primero) primero.focus();
        }
        var enlace = ev.target.closest('[data-evento]');
        if (enlace) CS.track(enlace.dataset.evento, { ubicacion: location.pathname });
    });

    document.addEventListener('DOMContentLoaded', function () {
        var c = leerConsentimiento();
        if (!c) mostrarAvisoCookies(true);
        else if (c === 'todas') cargarAnalitica();
        // Eventos declarados por la página (ver producto, compra, búsqueda…)
        (window.CS_EVENTOS || []).forEach(function (e) { CS.track(e[0], e[1] || {}); });
    });
})();
