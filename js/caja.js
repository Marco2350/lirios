/* =========================================================
   caja.js — conteo de billetes y monedas del módulo Control de caja
   (admin/caja.php y admin/caja-detalle.php). Solo calcula en vivo lo que
   se muestra; el servidor vuelve a calcular todo al guardar.
   ========================================================= */

(function () {
  function lempiras(n) {
    return 'L. ' + n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  document.querySelectorAll('[data-desglose]').forEach(function (bloque) {
    var dialogo = bloque.closest('dialog');
    var esperado = dialogo && dialogo.dataset.esperado !== undefined ? parseFloat(dialogo.dataset.esperado) : null;
    var totalEl = bloque.querySelector('[data-desglose-total]');

    function recalcular() {
      var total = 0;
      bloque.querySelectorAll('.caja-cant').forEach(function (input) {
        var cantidad = Math.max(0, parseInt(input.value, 10) || 0);
        var sub = cantidad * parseFloat(input.dataset.valor);
        total += sub;
        input.closest('.caja-fila').querySelector('[data-subtotal]').textContent = lempiras(sub);
      });
      total = Math.round(total * 100) / 100;
      totalEl.textContent = lempiras(total);

      // Cierre: copia del total, diferencia contra lo esperado y observación obligatoria si no cuadra
      if (esperado !== null && dialogo) {
        var copia = dialogo.querySelector('[data-desglose-total-copia]');
        var caja = dialogo.querySelector('[data-diferencia-caja]');
        var texto = dialogo.querySelector('[data-diferencia-texto]');
        var obs = dialogo.querySelector('textarea[name="observacion"]');
        var dif = Math.round((total - esperado) * 100) / 100;
        if (copia) copia.textContent = lempiras(total);
        if (caja && texto) {
          caja.className = dif === 0 ? 'cuadra' : (dif > 0 ? 'sobra' : 'falta');
          texto.textContent = dif === 0 ? 'Cuadra' : (dif > 0 ? 'Sobra ' : 'Falta ') + lempiras(Math.abs(dif));
        }
        if (obs) obs.required = dif !== 0;
      }
    }

    bloque.addEventListener('input', recalcular);
    recalcular();
  });
})();
