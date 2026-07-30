(function () {
  'use strict';

  var script = document.currentScript;
  var csrf = script ? String(script.getAttribute('data-csrf') || '') : '';
  var api = script ? String(script.getAttribute('data-api') || '../lib/q_func') : '../lib/q_func';
  var locale = document.documentElement.lang === 'ms' ? 'ms' : 'en';
  var state = {
    loaded: false,
    enabled: false,
    version: 0,
    authorizedMode: 'OFF',
    activationAvailable: false,
    resume: false
  };
  var text = locale === 'ms' ? {
    loading: 'Memuatkan tetapan...',
    unavailable: 'Tetapan tidak tersedia',
    loadFailed: 'Polisi User MFA tidak dapat dimuatkan.',
    review: 'Semak dan simpan',
    noChanges: 'Tiada perubahan',
    invalid: 'Isi sebab minimum 10 aksara, rujukan yang sah dan pengesahan bertulis yang tepat.',
    notSaved: 'Polisi tidak disimpan',
    saved: 'Polisi User MFA berjaya disimpan',
    authRequired: 'Admin Step-Up diperlukan untuk perubahan polisi User MFA.',
    authenticate: 'Sahkan sekarang',
    cancel: 'Batal'
  } : {
    loading: 'Loading settings...',
    unavailable: 'Settings unavailable',
    loadFailed: 'The User MFA policy could not be loaded.',
    review: 'Review and save',
    noChanges: 'No changes',
    invalid: 'Enter a reason of at least 10 characters, a valid reference and the exact typed confirmation.',
    notSaved: 'Policy not saved',
    saved: 'The User MFA policy was saved',
    authRequired: 'Admin Step-Up is required for a User MFA policy change.',
    authenticate: 'Authenticate now',
    cancel: 'Cancel'
  };

  function element(id) {
    return document.getElementById(id);
  }

  function setLoading(loading) {
    var button = element('user_mfa_global_save_button');
    var label = element('user_mfa_global_save_label');
    if (button) button.disabled = loading || !state.loaded;
    if (label) label.textContent = loading ? text.loading : (state.loaded ? text.review : text.unavailable);
  }

  function setToggle(value) {
    var toggle = element('user_mfa_global_enabled');
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

  function setStatus(value) {
    var target = element('user_mfa_global_status');
    if (target) target.textContent = value;
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

  function pendingTarget() {
    var stored = sessionStorage.getItem('oneid_user_mfa_global_enabled');
    return stored === '1' ? true : (stored === '0' ? false : null);
  }

  function clearPending() {
    ['enabled', 'reason', 'reference', 'confirmation'].forEach(function (key) {
      sessionStorage.removeItem('oneid_user_mfa_global_' + key);
    });
  }

  function load() {
    if (!element('user_mfa_global_save_button')) return;
    state.loaded = false;
    setLoading(true);
    request('admin_get_user_mfa_global_policy').then(function (response) {
      if (!response || Number(response.status) !== 1 || !response.data) {
        throw new Error(String(response && response.code || 'USER_MFA_GLOBAL_RESPONSE_INVALID'));
      }
      var data = response.data;
      state.loaded = true;
      state.enabled = Boolean(data.enabled);
      state.version = Number(data.configuration_version || 0);
      state.authorizedMode = String(data.authorized_mode || 'OFF');
      state.activationAvailable = Boolean(data.activation_available);
      setToggle(state.enabled);
      setStatus(
        'Mode: ' + String(data.effective_mode || 'OFF') +
        ' | Authorized: ' + state.authorizedMode +
        ' | Active Authenticator: ' + Number(data.active_factors || 0) +
        ' | Pending login: ' + Number(data.pending_transactions || 0) +
        ' | Pending challenge: ' + Number(data.pending_challenges || 0)
      );
      var target = pendingTarget();
      if (target !== null && target !== state.enabled) {
        setToggle(target);
        state.resume = true;
        window.setTimeout(window.saveUserMfaGlobalPolicy, 0);
      } else if (target !== null) {
        clearPending();
      }
      setLoading(false);
    }).catch(function (error) {
      state.loaded = false;
      setLoading(false);
      setStatus(text.loadFailed + ' Code: ' + String(error.message || 'UNKNOWN'));
    });
  }

  function persist(enabled, reason, reference, typed) {
    setLoading(true);
    request('admin_update_user_mfa_global_policy', {
      enabled: enabled ? '1' : '0',
      configuration_version: String(state.version),
      change_reason: reason,
      change_reference: reference,
      typed_confirmation: typed
    }).then(function (response) {
      if (!response || Number(response.status) !== 1) {
        throw new Error(String(response && response.code || 'USER_MFA_GLOBAL_RESPONSE_INVALID'));
      }
      clearPending();
      swal(text.saved, 'Mode: ' + String(response.data.effective_mode || 'OFF') +
        '\nReference: ' + String(response.correlation_id || ''), 'success');
      load();
    }).catch(function (error) {
      var payload = error.payload || {};
      var code = String(payload.code || error.message || 'UNKNOWN');
      if (error.status === 403 && ['STEP_UP_REQUIRED','STEP_UP_EXPIRED','STEP_UP_PURPOSE_MISMATCH'].indexOf(code) !== -1) {
        sessionStorage.setItem('oneid_user_mfa_global_enabled', enabled ? '1' : '0');
        sessionStorage.setItem('oneid_user_mfa_global_reason', reason);
        sessionStorage.setItem('oneid_user_mfa_global_reference', reference);
        sessionStorage.setItem('oneid_user_mfa_global_confirmation', typed);
        swal({
          title: text.authRequired,
          text: 'Security Configuration Change',
          type: 'warning',
          confirmButtonText: text.authenticate,
          closeOnConfirm: true
        }, function () {
          window.location.href = '../page/admin-step-up?purpose=SECURITY_CONFIGURATION_CHANGE&return=user_mfa_policy';
        });
        return;
      }
      swal(text.notSaved, 'Code: ' + code, 'error');
      setLoading(false);
    });
  }

  window.saveUserMfaGlobalPolicy = function () {
    if (!state.loaded) return;
    var enabled = Boolean(element('user_mfa_global_enabled').checked);
    var reason = String(element('user_mfa_global_reason').value || '').trim();
    var reference = String(element('user_mfa_global_reference').value || '').trim();
    var typed = String(element('user_mfa_global_confirmation').value || '').trim();
    if (enabled === state.enabled) {
      swal(text.noChanges, '', 'info');
      return;
    }
    if (reason.length < 10 || reference.length < 8 ||
        typed !== (enabled ? 'ENABLE USER MFA' : 'DISABLE USER MFA')) {
      swal(text.notSaved, text.invalid, 'warning');
      return;
    }
    if (enabled && !state.activationAvailable) {
      swal(text.notSaved, 'Runtime authorization does not permit activation.', 'error');
      return;
    }
    if (state.resume) {
      state.resume = false;
      persist(enabled, reason, reference, typed);
      return;
    }
    swal({
      title: enabled ? 'Enable User MFA?' : 'Disable User MFA?',
      text: enabled ? ('User MFA will be restored to ' + state.authorizedMode + '.') :
        'Password login will no longer require a second factor. Pending MFA challenges will be revoked.',
      type: 'warning',
      showCancelButton: true,
      confirmButtonColor: enabled ? '#18794e' : '#b4233b',
      confirmButtonText: enabled ? 'Enable User MFA' : 'Disable User MFA',
      cancelButtonText: text.cancel,
      closeOnConfirm: false
    }, function () { persist(enabled, reason, reference, typed); });
  };

  function restoreFields() {
    var mapping = {
      reason: 'user_mfa_global_reason',
      reference: 'user_mfa_global_reference',
      confirmation: 'user_mfa_global_confirmation'
    };
    Object.keys(mapping).forEach(function (key) {
      var value = sessionStorage.getItem('oneid_user_mfa_global_' + key);
      if (value !== null && element(mapping[key])) element(mapping[key]).value = value;
    });
  }

  restoreFields();
  load();
  document.addEventListener('shown.bs.tab', function (event) {
    if (event.target && event.target.getAttribute('href') === '#configuration_user_mfa') load();
  });
}());
