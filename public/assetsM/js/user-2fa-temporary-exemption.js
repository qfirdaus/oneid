(function () {
  'use strict';

  var script = document.currentScript;
  var csrf = script ? String(script.getAttribute('data-csrf') || '') : '';
  var api = script ? String(script.getAttribute('data-api') || '../lib/q_func') : '../lib/q_func';
  var stepUpUrl = script ? String(script.getAttribute('data-step-up-url') || '') : '';
  var locale = document.documentElement.lang === 'ms' ? 'ms' : 'en';
  var candidateSearchTimer = null;
  var candidateSearchSequence = 0;
  var selectedCandidateName = '';
  var text = locale === 'ms' ? {
    invalid: 'Pilih pengguna, tempoh dan sebab pengecualian.',
    otherInvalid: 'Nyatakan sebab lain sekurang-kurangnya 10 aksara.',
    created: 'Pengecualian sementara berjaya dicipta.',
    revoked: 'Pengecualian berjaya ditarik balik.',
    failed: 'Operasi pengecualian gagal.',
    revokeReason: 'Masukkan sebab revoke (minimum 10 aksara):',
    expiring: 'AKAN LUPUT',
    revoke: 'Revoke',
    empty: 'Tiada rekod ditemui.',
    auth: 'Admin Step-Up diperlukan. Klik OK untuk membuat pengesahan.',
    searchMin: 'Masukkan sekurang-kurangnya 2 aksara.',
    searchEmpty: 'Tiada pengguna ditemui.',
    selected: 'Pengguna dipilih',
    notEligible: 'Rekod ini tidak layak',
    reviewReady: 'Semak maklumat pengecualian di bawah sebelum membuat pengesahan.',
    reviewUser: 'Pengguna',
    reviewDuration: 'Tempoh',
    reviewReason: 'Sebab',
    reviewReference: 'Rujukan audit',
    reviewControl: 'Kawalan sementara',
    confirmTitle: 'Sahkan pengecualian User 2FA?',
    confirmAction: 'Cipta pengecualian',
    cancel: 'Batal',
    hours: 'jam',
    control: 'Identiti staf telah disahkan oleh Administrator; akses dipantau dan pengecualian luput secara automatik.'
  } : {
    invalid: 'Select a user, duration and exemption reason.',
    otherInvalid: 'Enter another reason of at least 10 characters.',
    created: 'The temporary exemption was created.',
    revoked: 'The exemption was revoked.',
    failed: 'The exemption operation failed.',
    revokeReason: 'Enter a revoke reason (minimum 10 characters):',
    expiring: 'EXPIRING',
    revoke: 'Revoke',
    empty: 'No records found.',
    auth: 'Admin Step-Up is required. Select OK to authenticate.',
    searchMin: 'Enter at least 2 characters.',
    searchEmpty: 'No users found.',
    selected: 'Selected user',
    notEligible: 'This record is not eligible',
    reviewReady: 'Review the exemption details below before confirming.',
    reviewUser: 'User',
    reviewDuration: 'Duration',
    reviewReason: 'Reason',
    reviewReference: 'Audit reference',
    reviewControl: 'Temporary control',
    confirmTitle: 'Confirm User 2FA exemption?',
    confirmAction: 'Create exemption',
    cancel: 'Cancel',
    hours: 'hour(s)',
    control: 'Staff identity was verified by an Administrator; access is monitored and the exemption expires automatically.'
  };

  function element(id) { return document.getElementById(id); }
  function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
      return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[character];
    });
  }

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

  function selectedReason() {
    var select = element('user_2fa_exemption_reason');
    if (!select || !select.value) return '';
    if (select.value === 'OTHER') return String(element('user_2fa_exemption_other').value || '').trim();
    return String(select.options[select.selectedIndex].text || '').trim();
  }

  function generatedReference() {
    var now = new Date();
    var date = String(now.getFullYear()) + String(now.getMonth() + 1).padStart(2, '0') + String(now.getDate()).padStart(2, '0');
    var bytes = new Uint8Array(3);
    if (window.crypto && window.crypto.getRandomValues) window.crypto.getRandomValues(bytes);
    else bytes = [Math.random() * 256, Math.random() * 256, Math.random() * 256];
    var suffix = Array.prototype.map.call(bytes, function (value) {
      return Math.floor(value).toString(16).padStart(2, '0');
    }).join('').toUpperCase();
    return 'MFA-EXM-' + date + '-' + suffix;
  }

  function updateReview() {
    var review = element('user_2fa_exemption_review');
    if (!review) return;
    var user = String(element('user_2fa_exemption_user').value || '').trim();
    var reason = selectedReason();
    var duration = String(element('user_2fa_exemption_duration').value || '');
    review.textContent = user && reason ?
      text.reviewUser + ': ' + user + ' · ' + text.reviewDuration + ': ' + duration + ' ' + text.hours + ' · ' + text.reviewReason + ': ' + reason :
      text.reviewReady;
  }

  function setConfigurationEnabled(enabled) {
    document.querySelectorAll('.user-2fa-exemption-setting').forEach(function (control) {
      control.disabled = !enabled;
    });
    if (enabled && element('user_2fa_exemption_reason').value !== 'OTHER') {
      element('user_2fa_exemption_other').disabled = true;
    }
    element('user_2fa_exemption_create_button').disabled = !enabled;
  }

  function clearSelectedUser() {
    element('user_2fa_exemption_user').value = '';
    selectedCandidateName = '';
    element('user_2fa_exemption_selected_status').textContent = '';
    setConfigurationEnabled(false);
    updateReview();
  }

  function selectCandidate(candidate) {
    if (!candidate.eligible) return;
    element('user_2fa_exemption_user').value = String(candidate.u_id);
    selectedCandidateName = String(candidate.display_name || '').trim();
    element('user_2fa_exemption_user_search').value =
      String(candidate.u_id) + (candidate.display_name ? ' — ' + String(candidate.display_name) : '');
    element('user_2fa_exemption_candidate_results').textContent = '';
    element('user_2fa_exemption_selected_status').textContent =
      text.selected + ': ' + String(candidate.u_id) +
      (candidate.display_name ? ' — ' + String(candidate.display_name) : '');
    setConfigurationEnabled(true);
    updateReview();
  }

  window.searchUser2faExemptionCandidates = function () {
    var query = String(element('user_2fa_exemption_user_search').value || '').trim();
    var results = element('user_2fa_exemption_candidate_results');
    var sequence = ++candidateSearchSequence;
    clearSelectedUser();
    results.textContent = '';
    if (query.length < 2) {
      element('user_2fa_exemption_selected_status').textContent =
        query.length === 0 ? '' : text.searchMin;
      return;
    }
    request('admin_search_user_mfa_exemption_candidates', {query: query}).then(function (response) {
      if (sequence !== candidateSearchSequence) return;
      if (!response || Number(response.status) !== 1 || !Array.isArray(response.data)) {
        throw new Error(String(response && response.code || 'INVALID'));
      }
      if (!response.data.length) {
        element('user_2fa_exemption_selected_status').textContent = text.searchEmpty;
        return;
      }
      response.data.forEach(function (candidate) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'list-group-item' + (candidate.eligible ? '' : ' disabled');
        button.disabled = !candidate.eligible;
        var label = String(candidate.u_id) +
          (candidate.display_name ? ' — ' + String(candidate.display_name) : '') +
          (candidate.identity_reference ? ' (' + String(candidate.identity_reference) + ')' : '');
        button.textContent = label + (candidate.eligible ? '' :
          ' — ' + text.notEligible + ': ' + String(candidate.eligibility));
        button.addEventListener('click', function () { selectCandidate(candidate); });
        results.appendChild(button);
      });
    }).catch(function (error) {
      if (sequence !== candidateSearchSequence) return;
      element('user_2fa_exemption_selected_status').textContent =
        text.failed + ' Code: ' + String((error.payload || {}).code || error.message);
    });
  };

  function scheduleCandidateSearch() {
    window.clearTimeout(candidateSearchTimer);
    candidateSearchSequence++;
    clearSelectedUser();
    element('user_2fa_exemption_candidate_results').textContent = '';
    var query = String(element('user_2fa_exemption_user_search').value || '').trim();
    if (query.length < 2) {
      element('user_2fa_exemption_selected_status').textContent =
        query.length === 0 ? '' : text.searchMin;
      return;
    }
    candidateSearchTimer = window.setTimeout(function () {
      window.searchUser2faExemptionCandidates();
    }, 300);
  }

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
      element('user_2fa_exemption_user_search').value = '';
      element('user_2fa_exemption_reason').value = '';
      element('user_2fa_exemption_other').value = '';
      element('user_2fa_exemption_other_wrap').classList.add('hidden');
      clearSelectedUser();
      window.loadUser2faExemptions();
    }).catch(function (error) {
      button.disabled = element('user_2fa_exemption_user').value === '';
      if (needsStepUp(error)) return redirectForStepUp({action: 'create', data: payload});
      swal(text.failed, 'Code: ' + String((error.payload || {}).code || error.message), 'error');
    });
  }

  window.createUser2faExemption = function () {
    var reasonCode = String(element('user_2fa_exemption_reason').value || '');
    var reason = selectedReason();
    var otherError = element('user_2fa_exemption_other_error');
    otherError.textContent = '';
    var payload = {
      user_id: String(element('user_2fa_exemption_user').value || '').trim(),
      duration_hours: String(element('user_2fa_exemption_duration').value || ''),
      change_reason: reason,
      change_reference: generatedReference(),
      compensating_control: text.control,
      typed_confirmation: phrase()
    };
    if (reasonCode === 'OTHER' && reason.length < 10) {
      otherError.textContent = text.otherInvalid;
      element('user_2fa_exemption_other').focus();
      return;
    }
    if (!/^[A-Za-z0-9_.@-]{1,20}$/.test(payload.user_id) ||
        [1, 4, 8, 24, 72].indexOf(Number(payload.duration_hours)) === -1 ||
        !reasonCode || payload.change_reason.length < 10) {
      swal(text.failed, text.invalid, 'warning');
      return;
    }
    var userDisplay = selectedCandidateName ? selectedCandidateName + ' (' + payload.user_id + ')' : payload.user_id;
    var reviewHtml = '<div class="rotation-reason-dialog">' +
      '<div class="site-api-code-result__feedback"><i class="fa fa-info-circle"></i> ' + escapeHtml(text.reviewReady) + '</div>' +
      '<div class="rotation-reason-dialog__choices">' +
      '<div class="rotation-reason-dialog__choice is-selected"><i class="fa fa-user"></i><span><b>' + escapeHtml(text.reviewUser) + '</b><br>' + escapeHtml(userDisplay) + '</span></div>' +
      '<div class="rotation-reason-dialog__choice is-selected"><i class="fa fa-clock-o"></i><span><b>' + escapeHtml(text.reviewDuration) + '</b><br>' + escapeHtml(payload.duration_hours + ' ' + text.hours) + '</span></div>' +
      '<div class="rotation-reason-dialog__choice rotation-reason-dialog__choice--wide is-selected"><i class="fa fa-comment-o"></i><span><b>' + escapeHtml(text.reviewReason) + '</b><br>' + escapeHtml(payload.change_reason) + '</span></div>' +
      '</div>' +
      '<div class="rotation-reason-dialog__notice"><i class="fa fa-shield"></i><span><b>' + escapeHtml(text.reviewControl) + '</b><br>' + escapeHtml(payload.compensating_control) + '<br><small>' + escapeHtml(text.reviewReference + ': ' + payload.change_reference) + '</small></span></div>' +
      '</div>';
    swal({
      title: text.confirmTitle,
      text: reviewHtml,
      html: true,
      type: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#b4233b',
      confirmButtonText: text.confirmAction,
      cancelButtonText: text.cancel,
      closeOnConfirm: false,
      allowEscapeKey: false,
      allowOutsideClick: false
    }, function () { create(payload); });
    if (typeof window.apply_site_api_alert_layout === 'function') {
      window.apply_site_api_alert_layout('rotation', locale === 'ms' ? 'Keselamatan Pengguna' : 'User Security');
    }
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
    setConfigurationEnabled(false);
    element('user_2fa_exemption_user_search').addEventListener('input', scheduleCandidateSearch);
    element('user_2fa_exemption_user_search').addEventListener('keydown', function (event) {
      if (event.key === 'Enter') {
        event.preventDefault();
        window.clearTimeout(candidateSearchTimer);
        window.searchUser2faExemptionCandidates();
      }
    });
    element('user_2fa_exemption_search').addEventListener('keydown', function (event) {
      if (event.key === 'Enter') window.loadUser2faExemptions();
    });
    element('user_2fa_exemption_duration').addEventListener('change', updateReview);
    element('user_2fa_exemption_reason').addEventListener('change', function () {
      var other = element('user_2fa_exemption_other_wrap');
      var showOther = this.value === 'OTHER';
      other.classList.toggle('hidden', !showOther);
      element('user_2fa_exemption_other').disabled = !showOther;
      element('user_2fa_exemption_other_error').textContent = '';
      updateReview();
      if (showOther) element('user_2fa_exemption_other').focus();
    });
    element('user_2fa_exemption_other').addEventListener('input', updateReview);
    updateReview();
    window.loadUser2faExemptions();
    if (window.oneidStepUpContextReady === 'configuration_user_mfa_exemption') resume();
  }
  document.addEventListener('oneid:step-up-context-ready', function (event) {
    if (event.detail && event.detail.context === 'configuration_user_mfa_exemption') resume();
  });
}());
