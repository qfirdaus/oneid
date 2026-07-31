(function () {
  'use strict';

  var script = document.currentScript;
  var csrf = script ? String(script.getAttribute('data-csrf') || '') : '';
  var api = script ? String(script.getAttribute('data-api') || '../lib/q_func') : '../lib/q_func';
  var stepUpUrl = script ? String(script.getAttribute('data-step-up-url') || '') : '';
  var locale = document.documentElement.lang === 'ms' ? 'ms' : 'en';
  var text = locale === 'ms' ? {
    invalid: 'Lengkapkan ID pengguna, sebab dan kawalan minimum 10 aksara, rujukan sah serta pengesahan tepat.',
    created: 'Pengecualian sementara berjaya dicipta.',
    revoked: 'Pengecualian berjaya ditarik balik.',
    failed: 'Operasi pengecualian gagal.',
    revokeReason: 'Masukkan sebab revoke (minimum 10 aksara):',
    expiring: 'AKAN LUPUT',
    revoke: 'Revoke',
    empty: 'Tiada rekod ditemui.',
    auth: 'Admin Step-Up diperlukan. Klik OK untuk membuat pengesahan.'
  } : {
    invalid: 'Complete the user ID, reason and control (minimum 10 characters), valid reference and exact confirmation.',
    created: 'The temporary exemption was created.',
    revoked: 'The exemption was revoked.',
    failed: 'The exemption operation failed.',
    revokeReason: 'Enter a revoke reason (minimum 10 characters):',
    expiring: 'EXPIRING',
    revoke: 'Revoke',
    empty: 'No records found.',
    auth: 'Admin Step-Up is required. Select OK to authenticate.'
  };

  function element(id) { return document.getElementById(id); }

  function request(action, data) {
    var body = new URLSearchParams(Object.assign({_csrf_token: csrf}, data || {}));
    body.set(action, '');
    return fetch(api, {
      method: 'POST',
      headers: {'X-CSRF-Token': csrf, 'Accept': 'application/json'},
      credentials: 'same-origin',
      body: body
    }).then(function (response) {
      return response.json().catch(function () { return {}; }).then(function (json) {
        if (!response.ok) {
          var error = new Error(String(json.code || ('HTTP_' + response.status)));
          error.status = response.status;
          error.payload = json;
          throw error;
        }
        return json;
      });
    });
  }

  function phrase() {
    return 'ADD USER 2FA EXEMPTION ' + String(element('user_2fa_exemption_user').value || '').trim();
  }

  function updatePhrase() {
    if (element('user_2fa_exemption_confirmation_phrase')) {
      element('user_2fa_exemption_confirmation_phrase').textContent = phrase();
    }
  }

  window.fillUser2faExemptionConfirmation = function () {
    element('user_2fa_exemption_confirmation').value = phrase();
  };

  function needsStepUp(error) {
    var code = String((error.payload || {}).code || error.message || '');
    return error.status === 403 &&
      ['STEP_UP_REQUIRED', 'STEP_UP_EXPIRED', 'STEP_UP_PURPOSE_MISMATCH'].indexOf(code) !== -1;
  }

  function redirectForStepUp(payload) {
    sessionStorage.setItem('oneid_user_2fa_exemption_pending', JSON.stringify(payload));
    swal({
      title: text.auth,
      type: 'warning',
      showCancelButton: true,
      closeOnConfirm: true
    }, function () { window.location.href = stepUpUrl; });
  }

  function create(payload) {
    var button = element('user_2fa_exemption_create_button');
    button.disabled = true;
    request('admin_create_user_mfa_exemption', payload).then(function (response) {
      if (!response || Number(response.status) !== 1) throw new Error(String(response && response.code || 'INVALID'));
      sessionStorage.removeItem('oneid_user_2fa_exemption_pending');
      swal(text.created, 'Reference: ' + String(response.correlation_id || ''), 'success');
      button.disabled = false;
      window.loadUser2faExemptions();
    }).catch(function (error) {
      button.disabled = false;
      if (needsStepUp(error)) return redirectForStepUp({action: 'create', data: payload});
      swal(text.failed, 'Code: ' + String((error.payload || {}).code || error.message), 'error');
    });
  }

  window.createUser2faExemption = function () {
    var payload = {
      user_id: String(element('user_2fa_exemption_user').value || '').trim(),
      duration_hours: String(element('user_2fa_exemption_duration').value || ''),
      change_reason: String(element('user_2fa_exemption_reason').value || '').trim(),
      change_reference: String(element('user_2fa_exemption_reference').value || '').trim(),
      compensating_control: String(element('user_2fa_exemption_control').value || '').trim(),
      typed_confirmation: String(element('user_2fa_exemption_confirmation').value || '').trim()
    };
    if (!/^[A-Za-z0-9_.@-]{1,20}$/.test(payload.user_id) ||
        [1, 4, 8, 24, 72].indexOf(Number(payload.duration_hours)) === -1 ||
        payload.change_reason.length < 10 || payload.compensating_control.length < 10 ||
        !/^[A-Za-z0-9._-]{8,100}$/.test(payload.change_reference) ||
        payload.typed_confirmation !== phrase()) {
      swal(text.failed, text.invalid, 'warning');
      return;
    }
    swal({
      title: phrase() + '?',
      text: payload.duration_hours + ' hour(s). Pending MFA challenges will be revoked.',
      type: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#b4233b',
      closeOnConfirm: false
    }, function () { create(payload); });
  };

  function appendCell(row, value) {
    var cell = document.createElement('td');
    cell.textContent = value == null ? '' : String(value);
    row.appendChild(cell);
    return cell;
  }

  function render(rows) {
    var body = element('user_2fa_exemption_rows');
    body.textContent = '';
    if (!rows.length) {
      var empty = document.createElement('tr');
      var cell = appendCell(empty, text.empty);
      cell.colSpan = 7;
      body.appendChild(empty);
      return;
    }
    rows.forEach(function (item) {
      var row = document.createElement('tr');
      appendCell(row, String(item.u_id) + (item.display_name ? ' — ' + String(item.display_name) : ''));
      appendCell(row, String(item.exemption_status) + (item.expires_soon ? ' / ' + text.expiring : ''));
      appendCell(row, item.expires_at);
      appendCell(row, item.change_reference);
      appendCell(row, String(item.change_reason || '') + ' / ' + String(item.compensating_control || '') +
        (item.revoke_reason ? ' / Revoke: ' + String(item.revoke_reason) : ''));
      appendCell(row, item.approved_by);
      var action = appendCell(row, '');
      if (item.exemption_status === 'ACTIVE') {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-danger btn-xs';
        button.textContent = text.revoke;
        button.addEventListener('click', function () { revoke(item); });
        action.appendChild(button);
      }
      body.appendChild(row);
    });
  }

  window.loadUser2faExemptions = function () {
    if (!element('user_2fa_exemption_rows')) return;
    request('admin_search_user_mfa_exemptions', {
      query: String(element('user_2fa_exemption_search').value || '').trim()
    }).then(function (response) {
      if (!response || Number(response.status) !== 1 || !Array.isArray(response.data)) {
        throw new Error(String(response && response.code || 'INVALID'));
      }
      render(response.data);
    }).catch(function (error) {
      render([]);
      swal(text.failed, 'Code: ' + String((error.payload || {}).code || error.message), 'error');
    });
  };

  function revoke(item) {
    swal({
      title: text.revoke + ' #' + String(item.exemption_id),
      text: text.revokeReason,
      type: 'input',
      showCancelButton: true,
      inputPlaceholder: text.revokeReason,
      closeOnConfirm: false
    }, function (reason) {
      if (reason === false) return;
      reason = String(reason || '').trim();
      if (reason.length < 10) {
        swal.showInputError(text.revokeReason);
        return false;
      }
      var payload = {
        exemption_id: String(item.exemption_id),
        revoke_reason: reason,
        typed_confirmation: 'REVOKE USER 2FA EXEMPTION ' + String(item.exemption_id)
      };
      request('admin_revoke_user_mfa_exemption', payload).then(function (response) {
        if (!response || Number(response.status) !== 1) throw new Error(String(response && response.code || 'INVALID'));
        swal(text.revoked, 'Reference: ' + String(response.correlation_id || ''), 'success');
        window.loadUser2faExemptions();
      }).catch(function (error) {
        if (needsStepUp(error)) return redirectForStepUp({action: 'revoke', data: payload});
        swal(text.failed, 'Code: ' + String((error.payload || {}).code || error.message), 'error');
      });
    });
  }

  function resume() {
    var raw = sessionStorage.getItem('oneid_user_2fa_exemption_pending');
    if (!raw) return;
    try {
      var pending = JSON.parse(raw);
      sessionStorage.removeItem('oneid_user_2fa_exemption_pending');
      if (pending.action === 'create') create(pending.data);
      if (pending.action === 'revoke') {
        request('admin_revoke_user_mfa_exemption', pending.data).then(function (response) {
          if (!response || Number(response.status) !== 1) throw new Error(String(response && response.code || 'INVALID'));
          swal(text.revoked, 'Reference: ' + String(response.correlation_id || ''), 'success');
          window.loadUser2faExemptions();
        }).catch(function (error) {
          swal(text.failed, 'Code: ' + String((error.payload || {}).code || error.message), 'error');
        });
      }
    } catch (ignore) {
      sessionStorage.removeItem('oneid_user_2fa_exemption_pending');
    }
  }

  if (element('user_2fa_exemption_user')) {
    element('user_2fa_exemption_user').addEventListener('input', updatePhrase);
    element('user_2fa_exemption_search').addEventListener('keydown', function (event) {
      if (event.key === 'Enter') window.loadUser2faExemptions();
    });
    updatePhrase();
    window.loadUser2faExemptions();
    resume();
  }
}());
