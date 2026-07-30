(function () {
  'use strict';

  var script = document.currentScript;
  var csrf = script ? String(script.getAttribute('data-csrf') || '') : '';
  var api = script ? String(script.getAttribute('data-api') || '../lib/q_func') : '../lib/q_func';
  var stepUpUrl = script ? String(script.getAttribute('data-step-up-url') || '') : '';
  var locale = document.documentElement.lang === 'ms' ? 'ms' : 'en';
  var policies = {};
  var loaded = false;
  var resuming = false;
  var text = locale === 'ms' ? {
    loading: 'Memuatkan tetapan...',
    review: 'Semak dan simpan',
    unavailable: 'Tetapan tidak tersedia',
    loadFailed: 'Polisi kategori User 2FA tidak dapat dimuatkan.',
    invalid: 'Isi sebab minimum 10 aksara, rujukan yang sah dan pengesahan bertulis yang tepat.',
    noChanges: 'Tiada perubahan',
    notSaved: 'Polisi tidak disimpan',
    saved: 'Polisi kategori User 2FA berjaya disimpan',
    authRequired: 'Admin Step-Up diperlukan untuk perubahan polisi kategori.',
    authenticate: 'Sahkan sekarang',
    cancel: 'Batal',
    users: 'pengguna aktif',
    enabled: 'diwajibkan',
    disabled: 'tidak diwajibkan'
  } : {
    loading: 'Loading settings...',
    review: 'Review and save',
    unavailable: 'Settings unavailable',
    loadFailed: 'The User 2FA category policy could not be loaded.',
    invalid: 'Enter a reason of at least 10 characters, a valid reference and the exact typed confirmation.',
    noChanges: 'No changes',
    notSaved: 'Policy not saved',
    saved: 'The User 2FA category policy was saved',
    authRequired: 'Admin Step-Up is required for a category policy change.',
    authenticate: 'Authenticate now',
    cancel: 'Cancel',
    users: 'active users',
    enabled: 'required',
    disabled: 'not required'
  };

  function element(id) {
    return document.getElementById(id);
  }

  function setLoading(value) {
    var button = element('user_2fa_category_save_button');
    var label = element('user_2fa_category_save_label');
    if (button) button.disabled = value || !loaded;
    if (label) label.textContent = value ? text.loading : (loaded ? text.review : text.unavailable);
  }

  function setToggle(value) {
    var toggle = element('user_2fa_category_enabled');
    if (!toggle) return;
    if (toggle.getAttribute('data-switchery')) {
      if (toggle.checked !== value) toggle.click();
    } else {
      toggle.checked = value;
      if (typeof window.Switchery === 'function') {
        new window.Switchery(toggle, {color: '#11a8df', size: 'small'});
      }
    }
  }

  function request(action, data) {
    var body = new URLSearchParams(Object.assign({_csrf_token: csrf}, data || {}));
    body.set(action, '');
    var controller = new AbortController();
    var timeout = window.setTimeout(function () { controller.abort(); }, 15000);
    return fetch(api, {
      method: 'POST',
      headers: {'X-CSRF-Token': csrf, 'Accept': 'application/json'},
      body: body,
      credentials: 'same-origin',
      signal: controller.signal
    }).then(function (response) {
      return response.json().catch(function () { return {}; }).then(function (json) {
        window.clearTimeout(timeout);
        if (!response.ok) {
          var error = new Error(String(json.code || ('HTTP_' + response.status)));
          error.status = response.status;
          error.payload = json;
          throw error;
        }
        return json;
      });
    }).catch(function (error) {
      window.clearTimeout(timeout);
      throw error;
    });
  }

  function selectedCategory() {
    return String(element('user_2fa_category_code').value || 'STAFF').toUpperCase();
  }

  function actionValues() {
    var category = selectedCategory();
    var enabled = Boolean(element('user_2fa_category_enabled').checked);
    var now = new Date();
    var date = String(now.getFullYear()) +
      String(now.getMonth() + 1).padStart(2, '0') +
      String(now.getDate()).padStart(2, '0');
    return {
      reference: 'ONEID-USER-2FA-' + (enabled ? 'ENABLE-' : 'DISABLE-') + category + '-' + date,
      confirmation: (enabled ? 'ENABLE USER 2FA ' : 'DISABLE USER 2FA ') + category
    };
  }

  function updateGuides() {
    var values = actionValues();
    element('user_2fa_category_reference_suggestion').textContent = values.reference;
    element('user_2fa_category_confirmation_suggestion').textContent = values.confirmation;
  }

  window.fillUser2faCategoryReference = function () {
    element('user_2fa_category_reference').value = actionValues().reference;
  };

  window.fillUser2faCategoryConfirmation = function () {
    element('user_2fa_category_confirmation').value = actionValues().confirmation;
  };

  function renderCategory() {
    var category = selectedCategory();
    var policy = policies[category];
    if (!policy) return;
    setToggle(Boolean(policy.enabled));
    element('user_2fa_category_status').textContent =
      category + ': ' + (policy.enabled ? text.enabled : text.disabled) +
      ' | ' + Number(policy.users || 0) + ' ' + text.users +
      ' | Version: ' + Number(policy.configuration_version || 0);
    updateGuides();
  }

  function clearPending() {
    ['category', 'enabled', 'reason', 'reference', 'confirmation'].forEach(function (key) {
      sessionStorage.removeItem('oneid_user_2fa_category_' + key);
    });
  }

  function restorePending() {
    var category = sessionStorage.getItem('oneid_user_2fa_category_category');
    if (category !== 'STAFF' && category !== 'STUDENT') return false;
    element('user_2fa_category_code').value = category;
    ['reason', 'reference', 'confirmation'].forEach(function (key) {
      var value = sessionStorage.getItem('oneid_user_2fa_category_' + key);
      if (value !== null) element('user_2fa_category_' + key).value = value;
    });
    return true;
  }

  function load() {
    if (!element('user_2fa_category_save_button')) return;
    loaded = false;
    setLoading(true);
    request('admin_get_user_mfa_category_policy').then(function (response) {
      if (!response || Number(response.status) !== 1 || !response.data ||
          !response.data.STAFF || !response.data.STUDENT) {
        throw new Error(String(response && response.code || 'USER_MFA_CATEGORY_RESPONSE_INVALID'));
      }
      policies = response.data;
      loaded = true;
      var pending = restorePending();
      renderCategory();
      setLoading(false);
      if (pending) {
        var target = sessionStorage.getItem('oneid_user_2fa_category_enabled') === '1';
        if (target !== Boolean(policies[selectedCategory()].enabled)) {
          setToggle(target);
          updateGuides();
          resuming = true;
          window.setTimeout(window.saveUser2faCategoryPolicy, 0);
        } else {
          clearPending();
        }
      }
    }).catch(function (error) {
      loaded = false;
      setLoading(false);
      element('user_2fa_category_status').textContent =
        text.loadFailed + ' Code: ' + String(error.message || 'UNKNOWN');
    });
  }

  function persist(category, enabled, reason, reference, confirmation) {
    setLoading(true);
    request('admin_update_user_mfa_category_policy', {
      category: category,
      enabled: enabled ? '1' : '0',
      configuration_version: String(policies[category].configuration_version),
      change_reason: reason,
      change_reference: reference,
      typed_confirmation: confirmation
    }).then(function (response) {
      if (!response || Number(response.status) !== 1) {
        throw new Error(String(response && response.code || 'USER_MFA_CATEGORY_RESPONSE_INVALID'));
      }
      clearPending();
      swal(text.saved, category + '\nReference: ' + String(response.correlation_id || ''), 'success');
      load();
    }).catch(function (error) {
      var payload = error.payload || {};
      var code = String(payload.code || error.message || 'UNKNOWN');
      if (error.status === 403 &&
          ['STEP_UP_REQUIRED', 'STEP_UP_EXPIRED', 'STEP_UP_PURPOSE_MISMATCH'].indexOf(code) !== -1) {
        sessionStorage.setItem('oneid_user_2fa_category_category', category);
        sessionStorage.setItem('oneid_user_2fa_category_enabled', enabled ? '1' : '0');
        sessionStorage.setItem('oneid_user_2fa_category_reason', reason);
        sessionStorage.setItem('oneid_user_2fa_category_reference', reference);
        sessionStorage.setItem('oneid_user_2fa_category_confirmation', confirmation);
        swal({
          title: text.authRequired,
          text: 'Security Configuration Change',
          type: 'warning',
          confirmButtonText: text.authenticate,
          closeOnConfirm: true
        }, function () { window.location.href = stepUpUrl; });
        return;
      }
      swal(text.notSaved, 'Code: ' + code, 'error');
      setLoading(false);
    });
  }

  window.saveUser2faCategoryPolicy = function () {
    if (!loaded) return;
    var category = selectedCategory();
    var enabled = Boolean(element('user_2fa_category_enabled').checked);
    var reason = String(element('user_2fa_category_reason').value || '').trim();
    var reference = String(element('user_2fa_category_reference').value || '').trim();
    var confirmation = String(element('user_2fa_category_confirmation').value || '').trim();
    if (enabled === Boolean(policies[category].enabled)) {
      swal(text.noChanges, '', 'info');
      return;
    }
    if (reason.length < 10 || !/^[A-Za-z0-9._-]{8,100}$/.test(reference) ||
        confirmation !== actionValues().confirmation) {
      swal(text.notSaved, text.invalid, 'warning');
      return;
    }
    if (resuming) {
      resuming = false;
      persist(category, enabled, reason, reference, confirmation);
      return;
    }
    swal({
      title: (enabled ? 'Enable ' : 'Disable ') + category + ' User 2FA?',
      text: enabled ? 'Password login for this category will require User 2FA when the active mode applies.' :
        'Password login for this category will bypass User 2FA. Existing enrollments are preserved.',
      type: 'warning',
      showCancelButton: true,
      confirmButtonColor: enabled ? '#18794e' : '#b4233b',
      confirmButtonText: enabled ? 'Enable User 2FA' : 'Disable User 2FA',
      cancelButtonText: text.cancel,
      closeOnConfirm: false
    }, function () { persist(category, enabled, reason, reference, confirmation); });
  };

  if (element('user_2fa_category_code')) {
    element('user_2fa_category_code').addEventListener('change', renderCategory);
    element('user_2fa_category_enabled').addEventListener('change', updateGuides);
  }
  updateGuides();
  load();
  document.addEventListener('shown.bs.tab', function (event) {
    if (event.target && event.target.getAttribute('href') === '#configuration_user_mfa') load();
  });
}());
