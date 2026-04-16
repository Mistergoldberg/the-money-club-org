(function () {
  'use strict';

  var PROTECTED_ACTIONS = {
    'apply-interest.php': true,
    'apply-contact.php': true,
    'apply-our-mission.php': true,
    'apply-instructor.php': true,
    'start-parent-approval.php': true,
    'apply-parent-approval.php': true,
    'apply-camper.php': true
  };

  function getActionName(form) {
    var action = form.getAttribute('action') || '';
    if (!action) {
      return '';
    }

    try {
      var parsed = new URL(action, window.location.href);
      return parsed.pathname.split('/').pop() || '';
    } catch (e) {
      var clean = action.split('?')[0].split('#')[0];
      return clean.split('/').pop() || '';
    }
  }

  function getCookie(name) {
    var pattern = new RegExp('(?:^|; )' + name.replace(/[.$?*|{}()\[\]\\/+^]/g, '\\$&') + '=([^;]*)');
    var match = document.cookie.match(pattern);
    return match ? decodeURIComponent(match[1]) : '';
  }

  function randomHex(bytes) {
    var arr = new Uint8Array(bytes);
    window.crypto.getRandomValues(arr);
    var out = '';
    for (var i = 0; i < arr.length; i += 1) {
      out += arr[i].toString(16).padStart(2, '0');
    }
    return out;
  }

  function ensureCsrfToken() {
    var existing = getCookie('tmc_form_csrf');
    if (/^[a-f0-9]{64}$/.test(existing)) {
      return existing;
    }

    if (!window.crypto || !window.crypto.getRandomValues) {
      return '';
    }

    var token = randomHex(32);
    var cookie = 'tmc_form_csrf=' + encodeURIComponent(token) + '; path=/; max-age=7200; SameSite=Strict';
    if (window.location.protocol === 'https:') {
      cookie += '; Secure';
    }
    document.cookie = cookie;
    return token;
  }

  function ensureHiddenInput(form, name, value) {
    var input = form.querySelector('input[name="' + name + '"]');
    if (!input) {
      input = document.createElement('input');
      input.type = 'hidden';
      input.name = name;
      form.insertBefore(input, form.firstChild);
    }
    input.value = value;
    return input;
  }

  function ensureHoneypot(form) {
    if (form.querySelector('input[name="_tmc_hp"]')) {
      return;
    }

    var wrap = document.createElement('div');
    wrap.setAttribute('aria-hidden', 'true');
    wrap.style.position = 'absolute';
    wrap.style.left = '-9999px';
    wrap.style.width = '1px';
    wrap.style.height = '1px';
    wrap.style.overflow = 'hidden';

    var label = document.createElement('label');
    label.textContent = 'Leave this field empty';

    var input = document.createElement('input');
    input.type = 'text';
    input.name = '_tmc_hp';
    input.tabIndex = -1;
    input.autocomplete = 'off';

    label.appendChild(input);
    wrap.appendChild(label);
    form.appendChild(wrap);
  }

  function defaultReturnForPage() {
    var path = (window.location.pathname || '').split('/').pop();
    return path || 'index.html';
  }

  function injectFormProtection() {
    var token = ensureCsrfToken();
    var forms = document.querySelectorAll('form[action]');

    forms.forEach(function (form) {
      var actionName = getActionName(form);
      if (!PROTECTED_ACTIONS[actionName]) {
        return;
      }

      ensureHoneypot(form);
      if (token) {
        ensureHiddenInput(form, '_csrf', token);
      }

      if ((actionName === 'apply-contact.php' || actionName === 'apply-our-mission.php' || actionName === 'apply-instructor.php' || actionName === 'start-parent-approval.php') && !form.querySelector('input[name="return-to"]')) {
        ensureHiddenInput(form, 'return-to', defaultReturnForPage());
      }

      if ((actionName === 'apply-contact.php' || actionName === 'apply-our-mission.php' || actionName === 'apply-instructor.php' || actionName === 'start-parent-approval.php') && !form.querySelector('input[name="return-error"]')) {
        ensureHiddenInput(form, 'return-error', defaultReturnForPage());
      }
    });
  }

  function applyErrorState() {
    var params = new URLSearchParams(window.location.search);
    if (params.get('status') !== 'error') {
      return;
    }

    var fieldName = params.get('field');
    var message = params.get('message') || 'Please check the highlighted field.';
    if (!fieldName) {
      return;
    }

    var fieldInput = document.querySelector('[name="' + fieldName + '"]');
    if (!fieldInput) {
      return;
    }

    var wrapper = fieldInput.closest('.field, .confirmation-card');
    if (wrapper) {
      wrapper.classList.add('is-invalid');
      var errorEl = wrapper.querySelector('.field-error');
      if (errorEl) {
        errorEl.textContent = message;
      }
    }

    try {
      fieldInput.focus();
      if (wrapper && typeof wrapper.scrollIntoView === 'function') {
        wrapper.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
    } catch (e) {}
  }

  function init() {
    injectFormProtection();
    applyErrorState();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
