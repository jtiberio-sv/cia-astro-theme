/* checkout-cep.js — autocomplete via ViaCEP + mascaras + Smart Address.
 *
 * 1) Mascara CEP (99999-999) + Telefone ((11) 99999-9999)
 * 2) ViaCEP autocomplete: preenche Rua/Bairro/Cidade/UF quando CEP valido
 * 3) Smart Address: apos preencher, colapsa rua/bairro/cidade/UF em
 *    "resumo" + botao "editar"; cliente so ve Numero + Complemento.
 */
(function () {
  var VIACEP = 'https://viacep.com.br/ws/{cep}/json/';

  function digits(s) { return (s || '').replace(/\D/g, ''); }

  function setVal(selector, value) {
    var el = document.querySelector(selector);
    if (!el || !value) return;
    if (el.value && el.value.length > 1 && el.value.toLowerCase() !== value.toLowerCase()) {
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
    collapseAddress(prefix);
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

  /* ============ Smart Address: colapsa campos autopreenchidos ============
   * Pega rua + bairro + cidade + UF preenchidos via ViaCEP, esconde os
   * inputs e mostra um "resumo" amigavel + botao "Editar" pra reabrir.
   */
  function collapseAddress(prefix) {
    var rua    = document.querySelector('#' + prefix + 'address_1');
    var bairro = document.querySelector('#' + prefix + 'neighborhood');
    var cidade = document.querySelector('#' + prefix + 'city');
    var uf     = document.querySelector('#' + prefix + 'state');
    if (!rua || !rua.value) return;

    var summary = (rua.value || '') +
                  (bairro && bairro.value ? ' · ' + bairro.value : '') +
                  (cidade && cidade.value ? ' · ' + cidade.value : '') +
                  (uf && uf.value ? '/' + uf.value : '');

    var fields = [rua, bairro, cidade, uf].map(function (el) {
      if (!el) return null;
      var row = el.closest('.form-row, p.form-row, .woocommerce-form-row');
      return row;
    }).filter(Boolean);

    fields.forEach(function (row) { row.classList.add('cdm-collapsed'); });

    // Cria ou atualiza summary card
    var anchorRow = fields[0];
    if (!anchorRow) return;
    var card = anchorRow.parentNode.querySelector('.cdm-address-summary[data-prefix="' + prefix + '"]');
    if (!card) {
      card = document.createElement('div');
      card.className = 'cdm-address-summary';
      card.setAttribute('data-prefix', prefix);
      anchorRow.parentNode.insertBefore(card, anchorRow);
    }
    card.innerHTML =
      '<div class="cdm-as-icon">📍</div>' +
      '<div class="cdm-as-text">' +
        '<div class="cdm-as-label">Endereço encontrado</div>' +
        '<div class="cdm-as-value"></div>' +
      '</div>' +
      '<button type="button" class="cdm-as-edit" aria-label="Editar endereço">Editar</button>';
    card.querySelector('.cdm-as-value').textContent = summary;
    card.querySelector('.cdm-as-edit').addEventListener('click', function () {
      fields.forEach(function (row) { row.classList.remove('cdm-collapsed'); });
      card.remove();
    });
  }

  /* ============ Mascara Telefone BR ============ */
  function maskPhone(input) {
    if (!input || input.dataset.cdmPhoneBound === '1') return;
    input.dataset.cdmPhoneBound = '1';
    input.setAttribute('inputmode', 'tel');
    input.setAttribute('placeholder', '(11) 99999-9999');
    input.setAttribute('maxlength', '15');
    input.addEventListener('input', function () {
      var d = digits(input.value).slice(0, 11);
      var out = '';
      if (d.length === 0) { input.value = ''; return; }
      if (d.length <= 2) {
        out = '(' + d;
      } else if (d.length <= 6) {
        out = '(' + d.slice(0, 2) + ') ' + d.slice(2);
      } else if (d.length <= 10) {
        // Fixo: (11) 9999-9999
        out = '(' + d.slice(0, 2) + ') ' + d.slice(2, 6) + '-' + d.slice(6);
      } else {
        // Celular: (11) 99999-9999
        out = '(' + d.slice(0, 2) + ') ' + d.slice(2, 7) + '-' + d.slice(7);
      }
      input.value = out;
    });
  }

  function bind(input, prefix) {
    if (!input || input.dataset.cdmCepBound === '1') return;
    input.dataset.cdmCepBound = '1';
    input.setAttribute('inputmode', 'numeric');
    input.setAttribute('placeholder', '00000-000');
    input.setAttribute('maxlength', '9');

    input.addEventListener('input', function () {
      var d = digits(input.value).slice(0, 8);
      input.value = d.length > 5 ? d.slice(0, 5) + '-' + d.slice(5) : d;
    });
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
    maskPhone(document.getElementById('billing_phone'));
    maskPhone(document.getElementById('shipping_phone'));

    // Se rua ja vier preenchida (cliente recorrente / endereco salvo), colapsa
    ['billing_', 'shipping_'].forEach(function (p) {
      var rua = document.querySelector('#' + p + 'address_1');
      if (rua && rua.value && rua.value.length > 3) {
        collapseAddress(p);
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
  } else {
    init();
  }
  if (window.jQuery) {
    window.jQuery(document.body).on('updated_checkout', init);
  }
})();
