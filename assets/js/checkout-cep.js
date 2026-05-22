/* checkout-cep.js — autocomplete via ViaCEP no campo CEP do checkout.
 * Preenche Rua / Bairro / Cidade / UF quando CEP for valido.
 * Cobre billing_postcode e shipping_postcode (se habilitado). */
(function () {
  var VIACEP = 'https://viacep.com.br/ws/{cep}/json/';

  function digits(s) { return (s || '').replace(/\D/g, ''); }

  function setVal(selector, value) {
    var el = document.querySelector(selector);
    if (!el || !value) return;
    if (el.value && el.value.length > 1 && el.value.toLowerCase() !== value.toLowerCase()) {
      // Nao sobrescreve se ja preenchido manualmente
      return;
    }
    el.value = value;
    el.dispatchEvent(new Event('input', { bubbles: true }));
    el.dispatchEvent(new Event('change', { bubbles: true }));
  }

  function setSelectVal(selector, value) {
    var el = document.querySelector(selector);
    if (!el || !value) return;
    var hasOption = false;
    for (var i = 0; i < el.options.length; i++) {
      if (el.options[i].value === value) { hasOption = true; break; }
    }
    if (!hasOption) return;
    el.value = value;
    el.dispatchEvent(new Event('change', { bubbles: true }));
    // Select2 (jQuery)
    if (window.jQuery && window.jQuery.fn.select2) {
      try { window.jQuery(el).trigger('change.select2'); } catch (e) {}
    }
  }

  function applyAddress(prefix, data) {
    if (!data || data.erro) return;
    setVal('#' + prefix + 'address_1', data.logradouro || '');
    setVal('#' + prefix + 'neighborhood', data.bairro || '');
    setVal('#' + prefix + 'city', data.localidade || '');
    setSelectVal('#' + prefix + 'state', data.uf || '');
    // Foca no proximo campo logico (Numero)
    var next = document.querySelector('#' + prefix + 'number');
    if (next) setTimeout(function () { next.focus(); }, 50);
  }

  function showStatus(input, msg, isError) {
    var sib = input.parentNode.querySelector('.cdm-cep-status');
    if (!sib) {
      sib = document.createElement('small');
      sib.className = 'cdm-cep-status';
      sib.style.cssText = 'display:block;margin-top:0.25rem;font-size:0.78rem;';
      input.parentNode.appendChild(sib);
    }
    sib.textContent = msg;
    sib.style.color = isError ? '#ED6B5A' : '#22BB6E';
  }

  function clearStatus(input) {
    var sib = input.parentNode.querySelector('.cdm-cep-status');
    if (sib) sib.remove();
  }

  function handleCepChange(input, prefix) {
    var cep = digits(input.value);
    clearStatus(input);
    if (cep.length !== 8) return;

    showStatus(input, 'Buscando endereço...', false);
    fetch(VIACEP.replace('{cep}', cep), { mode: 'cors' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (data) {
        if (!data || data.erro) {
          showStatus(input, 'CEP não encontrado.', true);
          return;
        }
        clearStatus(input);
        applyAddress(prefix, data);
      })
      .catch(function () {
        showStatus(input, 'Falha ao buscar CEP. Preencha manualmente.', true);
      });
  }

  function bind(input, prefix) {
    if (!input || input.dataset.cdmCepBound === '1') return;
    input.dataset.cdmCepBound = '1';

    // Mascara visual leve (formato 99999-999)
    input.addEventListener('input', function () {
      var d = digits(input.value).slice(0, 8);
      input.value = d.length > 5 ? d.slice(0, 5) + '-' + d.slice(5) : d;
    });
    // Dispara busca on blur OU quando completa 8 digitos durante typing
    input.addEventListener('blur', function () { handleCepChange(input, prefix); });
    input.addEventListener('input', function () {
      if (digits(input.value).length === 8) {
        handleCepChange(input, prefix);
      }
    });
  }

  function init() {
    bind(document.getElementById('billing_postcode'),  'billing_');
    bind(document.getElementById('shipping_postcode'), 'shipping_');
  }

  // Roda no load + re-bind quando WC atualiza checkout (Ajax)
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
  } else {
    init();
  }
  if (window.jQuery) {
    window.jQuery(document.body).on('updated_checkout', init);
  }
})();
