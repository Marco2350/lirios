/* =========================================================
   admin.js — interacciones compartidas del panel de administración
   (modales, confirmaciones antes de eliminar, alertas de error y
   toasts de éxito). Vanilla JS, sin librerías externas — se carga
   una sola vez desde admin_footer() en includes/layout.php, así que
   corre después de todo el HTML de la página.
   ========================================================= */

(function () {
  /* ---------- Abrir / cerrar modales genéricos (<dialog>) ---------- */

  document.querySelectorAll('[data-abrir-modal]').forEach(function (boton) {
    boton.addEventListener('click', function () {
      var dialogo = document.getElementById(boton.dataset.abrirModal);
      if (dialogo) dialogo.showModal();
    });
  });

  document.querySelectorAll('[data-cerrar-modal]').forEach(function (boton) {
    boton.addEventListener('click', function () {
      var dialogo = boton.closest('dialog');
      if (dialogo) dialogo.close();
    });
  });

  document.querySelectorAll('dialog').forEach(function (dialogo) {
    dialogo.addEventListener('click', function (e) {
      if (e.target === dialogo) dialogo.close(); // clic fuera de la tarjeta (backdrop)
    });
  });

  /* ---------- Confirmación antes de eliminar ----------
     Un solo <dialog id="modal-confirmar"> compartido por toda la página
     (impreso una vez en admin_footer()) en vez de uno por fila — evita
     duplicar decenas de dialogs en tablas largas (ej. 70+ productos).
     Cada botón "Eliminar" pasa por data-attributes el id del <form> real
     a enviar y el mensaje a mostrar; este script solo rellena el modal
     compartido y ata su botón de confirmar a ese form en concreto. */
  var modalConfirmar = document.getElementById('modal-confirmar');
  if (modalConfirmar) {
    var mensajeEl = modalConfirmar.querySelector('[data-confirmar-mensaje-texto]');
    var botonConfirmar = modalConfirmar.querySelector('[data-confirmar-boton]');

    document.querySelectorAll('[data-confirmar-eliminar]').forEach(function (boton) {
      boton.addEventListener('click', function () {
        var formId = boton.dataset.confirmarEliminar;
        mensajeEl.textContent = boton.dataset.confirmarMensaje
          || '¿Eliminar este elemento? Esta acción no se puede deshacer.';
        botonConfirmar.onclick = function () {
          var form = document.getElementById(formId);
          if (form) form.requestSubmit();
        };
        modalConfirmar.showModal();
      });
    });
  }

  /* ---------- Alerta de error (bloqueante) ----------
     mostrar_flash() en layout.php imprime <dialog id="alerta-flash"> solo
     cuando el mensaje guardado en sesión es de tipo error — se abre sola
     al cargar la página, sin esperar un clic. */
  var alertaFlash = document.getElementById('alerta-flash');
  if (alertaFlash) alertaFlash.showModal();

  /* ---------- Toast de éxito (no bloqueante) ----------
     Igual que arriba pero para mensajes tipo "ok": no interrumpe, se
     cierra solo a los ~3.2s (mismo patrón que showToast() en el sitio
     público, js/cart.js). */
  var toastFlash = document.getElementById('toast-flash');
  if (toastFlash) {
    setTimeout(function () {
      toastFlash.classList.add('oculto');
      setTimeout(function () { toastFlash.remove(); }, 300);
    }, 3200);
  }
})();

/* ---------- Enter = pasar al siguiente campo ----------
   En los formularios marcados con data-enter-siguiente (pedidos, usuarios),
   Enter ya no envía el formulario: mueve el cursor al siguiente campo, como
   pasar de celda en una hoja de cálculo. En el último campo sí envía.
   Los botones, los textarea y los campos que ya manejaron la tecla
   (preventDefault, p. ej. el editor de productos del pedido) se respetan. */
document.addEventListener('keydown', function (e) {
  if (e.key !== 'Enter' || e.defaultPrevented || e.isComposing) return;
  var campo = e.target;
  if (!campo.matches || !campo.matches('input, select')) return;
  if (campo.type === 'submit' || campo.type === 'button' || campo.type === 'checkbox') return;
  var form = campo.closest('form[data-enter-siguiente]');
  if (!form) return;

  var campos = Array.prototype.filter.call(
    form.querySelectorAll('input, select, textarea'),
    function (c) { return c.type !== 'hidden' && !c.disabled && c.offsetParent !== null; }
  );
  var i = campos.indexOf(campo);
  if (i === -1 || i === campos.length - 1) return;   // último campo: Enter envía normal
  e.preventDefault();
  campos[i + 1].focus();
  if (campos[i + 1].select && campos[i + 1].type !== 'date') campos[i + 1].select();
});
