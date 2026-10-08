(function () {
  'use strict';

  var config = window.OneIdUserHealthConfig || {};
  var text = config.text || {};

  function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>'"]/g, function (character) {
      return {'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[character];
    });
  }

  function pageResponseMs() {
    var entries = window.performance && performance.getEntriesByType ? performance.getEntriesByType('navigation') : [];
    var duration = entries.length ? entries[0].duration : 0;
    if (!duration && performance.timing) duration = performance.timing.loadEventEnd - performance.timing.navigationStart;
    return Math.max(0, Math.round(duration || 0));
  }

  function responseLabel(ms) {
    if (!ms || ms <= 1000) return text.fast;
    if (ms <= 2500) return text.moderate;
    return text.slow;
  }

  function localTime(date) {
    try { return new Intl.DateTimeFormat(document.documentElement.lang === 'en' ? 'en-MY' : 'ms-MY', {dateStyle:'short',timeStyle:'medium'}).format(date); }
    catch (error) { return date.toLocaleString(); }
  }

  function browserLabel() {
    var agent = String(navigator.userAgent || '');
    var match = agent.match(/Edg\/([\d.]+)/) || agent.match(/Firefox\/([\d.]+)/) || agent.match(/(?:Chrome|CriOS)\/([\d.]+)/) || agent.match(/Version\/([\d.]+).*Safari/);
    var name = /Edg\//.test(agent) ? 'Microsoft Edge' : (/Firefox\//.test(agent) ? 'Firefox' : (/(Chrome|CriOS)\//.test(agent) ? 'Chrome' : (/Safari\//.test(agent) ? 'Safari' : 'Browser')));
    return name + (match && match[1] ? ' ' + match[1].split('.')[0] : '');
  }

  function safeReferenceId() {
    var bytes = new Uint8Array(6);
    if (window.crypto && window.crypto.getRandomValues) window.crypto.getRandomValues(bytes);
    else for (var index = 0; index < bytes.length; index++) bytes[index] = Math.floor(Math.random() * 256);
    return 'ONEID-UI-' + Array.prototype.map.call(bytes, function (value) { return value.toString(16).padStart(2, '0'); }).join('').toUpperCase();
  }

  function ready() {
    var trigger = document.getElementById('oneid_user_health_trigger');
    if (!trigger) return;
    var responseMs = pageResponseMs();
    var updatedAt = new Date();
    var referenceId = safeReferenceId();
    var panel = document.createElement('section');
    panel.className = 'oneid-health-panel';
    panel.id = 'oneid_user_health_panel';
    panel.hidden = true;
    panel.setAttribute('role', 'dialog');
    panel.setAttribute('aria-modal', 'false');
    panel.setAttribute('aria-labelledby', 'oneid_user_health_title');
    panel.innerHTML = '<div class="oneid-health-panel__head">' +
      '<span class="oneid-health-panel__head-icon"><svg class=\"oneid-gauge-icon fa-gauge-high\" viewBox=\"0 0 32 24\" aria-hidden=\"true\" focusable=\"false\"><path class=\"oneid-gauge-icon__track\" d=\"M4 19a12 12 0 0 1 24 0\"/><path class=\"oneid-gauge-icon__green\" d=\"M4 19a12 12 0 0 1 3.5-8.5\"/><path class=\"oneid-gauge-icon__amber\" d=\"M7.5 10.5A12 12 0 0 1 18 7.2\"/><path class=\"oneid-gauge-icon__red\" d=\"M18 7.2A12 12 0 0 1 28 19\"/><path class=\"oneid-gauge-icon__needle\" d=\"M16 19l7-7\"/><circle class=\"oneid-gauge-icon__hub\" cx=\"16\" cy=\"19\" r=\"2.2\"/></svg></span>' +
      '<span class="oneid-health-panel__heading"><strong id="oneid_user_health_title">' + escapeHtml(text.title) + '</strong><small>' + escapeHtml(text.subtitle) + '</small></span>' +
      '<button type="button" class="oneid-health-panel__close" data-health-close aria-label="' + escapeHtml(text.close) + '">&times;</button></div>' +
      '<div class="oneid-health-panel__body"><div class="oneid-health-grid">' +
      '<div class="oneid-health-metric"><span>' + escapeHtml(text.connection) + '</span><strong class="oneid-health-state" data-health-connection><i class="oneid-health-dot"></i><b></b></strong></div>' +
      '<div class="oneid-health-metric"><span>' + escapeHtml(text.response) + '</span><strong data-health-response></strong></div>' +
      '<div class="oneid-health-metric"><span>' + escapeHtml(text.updated) + '</span><strong data-health-updated></strong></div>' +
      '<div class="oneid-health-metric"><span>' + escapeHtml(text.version) + '</span><strong>' + escapeHtml(config.version) + '</strong></div>' +
      '</div><div class="oneid-health-actions">' +
      '<button type="button" class="oneid-health-action is-primary" data-health-refresh><i class="fa fa-refresh" aria-hidden="true"></i>' + escapeHtml(text.refresh) + '</button>' +
      '<button type="button" class="oneid-health-action" data-health-clear><i class="fa fa-eraser" aria-hidden="true"></i>' + escapeHtml(text.clear) + '</button>' +
      '<button type="button" class="oneid-health-action" data-health-view aria-expanded="false"><i class="fa fa-eye" aria-hidden="true"></i>' + escapeHtml(text.view) + '</button>' +
      '<button type="button" class="oneid-health-action" data-health-copy><i class="fa fa-copy" aria-hidden="true"></i>' + escapeHtml(text.copy) + '</button>' +
      '</div><section class="oneid-health-diagnostics" data-health-diagnostics hidden aria-labelledby="oneid_health_diagnostics_title">' +
      '<div class="oneid-health-diagnostics__head"><strong id="oneid_health_diagnostics_title"><i class="fa fa-shield" aria-hidden="true"></i>' + escapeHtml(text.diagnosticsTitle) + '</strong><button type="button" data-health-diagnostics-close aria-label="' + escapeHtml(text.closeDiagnostics) + '">&times;</button></div>' +
      '<dl><div><dt>' + escapeHtml(text.environment) + '</dt><dd data-diagnostic-environment></dd></div><div><dt>' + escapeHtml(text.browser) + '</dt><dd data-diagnostic-browser></dd></div>' +
      '<div><dt>' + escapeHtml(text.viewport) + '</dt><dd data-diagnostic-viewport></dd></div><div><dt>' + escapeHtml(text.response) + '</dt><dd data-diagnostic-response></dd></div>' +
      '<div><dt>' + escapeHtml(text.connection) + '</dt><dd data-diagnostic-connection></dd></div><div><dt>' + escapeHtml(text.checkedAt) + '</dt><dd data-diagnostic-time></dd></div>' +
      '<div class="is-reference"><dt>' + escapeHtml(text.reference) + '</dt><dd data-diagnostic-reference></dd></div></dl><p>' + escapeHtml(text.diagnosticsNote) + '</p></section>' +
      '<p class="oneid-health-status" data-health-status aria-live="polite"><span data-health-status-message></span><button type="button" data-health-retry hidden><i class="fa fa-refresh" aria-hidden="true"></i>' + escapeHtml(text.retry) + '</button></p><p class="oneid-health-note">' + escapeHtml(text.note) + '</p></div>' +
      '<div class="oneid-health-support"><a href="tel:+60390512700">' + escapeHtml(config.supportPhone) + '</a><span class="oneid-health-support__right"><span>' + escapeHtml(config.supportEmail) + '</span><a class="oneid-health-support__link" href="mailto:' + encodeURIComponent(config.supportEmail || '') + '"><i class="fa fa-life-ring" aria-hidden="true"></i>' + escapeHtml(text.support) + '</a></span></div>';
    document.body.appendChild(panel);

    var connection = panel.querySelector('[data-health-connection]');
    var response = panel.querySelector('[data-health-response]');
    var updated = panel.querySelector('[data-health-updated]');
    var status = panel.querySelector('[data-health-status]');
    var statusMessage = panel.querySelector('[data-health-status-message]');
    var retry = panel.querySelector('[data-health-retry]');
    var refresh = panel.querySelector('[data-health-refresh]');
    var clear = panel.querySelector('[data-health-clear]');
    var view = panel.querySelector('[data-health-view]');
    var diagnostics = panel.querySelector('[data-health-diagnostics]');

    function diagnosticValues() {
      return {
        environment:String(config.environment || text.notAvailable || ''),
        browser:browserLabel(),
        viewport:window.innerWidth + ' × ' + window.innerHeight + ' px',
        response:(responseMs ? responseMs + ' ms' : String(text.notAvailable || '')),
        connection:(navigator.onLine === false ? text.offline : text.online),
        time:localTime(new Date()),
        reference:referenceId
      };
    }

    function updateDiagnostics() {
      var values = diagnosticValues();
      Object.keys(values).forEach(function (key) {
        var field = diagnostics.querySelector('[data-diagnostic-' + key + ']');
        if (field) field.textContent = values[key];
      });
      return values;
    }

    function toggleDiagnostics(forceOpen) {
      var open = typeof forceOpen === 'boolean' ? forceOpen : diagnostics.hidden;
      diagnostics.hidden = !open;
      view.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (open) { updateDiagnostics(); diagnostics.querySelector('[data-health-diagnostics-close]').focus(); }
      else view.focus();
    }

    function sync() {
      var online = navigator.onLine !== false;
      var gaugeState = !online ? 'offline' : (responseMs > 2500 ? 'slow' : (responseMs > 1000 ? 'moderate' : 'fast'));
      connection.classList.toggle('is-offline', !online);
      connection.querySelector('b').textContent = online ? text.online : text.offline;
      response.textContent = responseLabel(responseMs) + (responseMs ? ' · ' + responseMs + ' ms' : '');
      updated.textContent = localTime(updatedAt);
      Array.prototype.forEach.call(document.querySelectorAll('.oneid-gauge-icon'), function (gauge) {
        gauge.classList.remove('is-fast', 'is-moderate', 'is-slow', 'is-offline');
        gauge.classList.add('is-' + gaugeState);
      });
    }
    function open() { panel.hidden = false; trigger.setAttribute('aria-expanded', 'true'); panel.querySelector('[data-health-close]').focus(); sync(); }
    function close(restore) { panel.hidden = true; trigger.setAttribute('aria-expanded', 'false'); if (restore) trigger.focus(); }
    function formatMessage(template, date) { return String(template || '').replace('{time}', localTime(date)); }
    function setStatus(message, state, canRetry) {
      status.classList.remove('is-working', 'is-success', 'is-error');
      if (state) status.classList.add('is-' + state);
      statusMessage.textContent = message;
      retry.hidden = !canRetry;
    }
    function setActionsBusy(busy) {
      refresh.disabled = busy; clear.disabled = busy;
      [refresh, clear].forEach(function (button) {
        var icon = button.querySelector('i');
        if (!icon) return;
        if (busy) { icon.setAttribute('data-previous-class', icon.className); icon.className = 'fa fa-refresh fa-spin'; }
        else if (icon.hasAttribute('data-previous-class')) { icon.className = icon.getAttribute('data-previous-class'); icon.removeAttribute('data-previous-class'); }
      });
    }
    function runRefresh(message) {
      var refreshStartedAt = window.performance && performance.now ? performance.now() : Date.now();
      setStatus(text.refreshing, 'working', false);
      setActionsBusy(true);
      document.dispatchEvent(new CustomEvent('oneid:dashboard-health-refresh'));
      var requests = [];
      try { if (typeof window.get_specific_user_app_list === 'function') requests.push(window.get_specific_user_app_list()); } catch (error) { /* handled below */ }
      try { if (typeof window.get_specific_user_activ_session === 'function') requests.push(window.get_specific_user_activ_session()); } catch (error) { /* handled below */ }
      var complete = function (successful) {
        responseMs = Math.max(0, Math.round((window.performance && performance.now ? performance.now() : Date.now()) - refreshStartedAt));
        updatedAt = new Date(); sync(); setActionsBusy(false);
        setStatus(successful ? formatMessage(message, updatedAt) : text.failed, successful ? 'success' : 'error', !successful);
      };
      if (window.jQuery && requests.length) {
        window.jQuery.when.apply(window.jQuery, requests)
          .done(function () { complete(true); })
          .fail(function () { complete(false); });
      } else complete(true);
    }
    function copyDiagnostics() {
      var values = updateDiagnostics();
      var diagnostic = [
        'OneID UI diagnostics',
        'Version: ' + String(config.version || ''),
        'Environment: ' + values.environment,
        'Browser: ' + values.browser,
        'Screen size: ' + values.viewport,
        'Page response: ' + values.response,
        'Connection: ' + values.connection,
        'Checked at: ' + new Date().toISOString(),
        'Reference ID: ' + values.reference
      ].join('\n');
      if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(diagnostic).then(function () { setStatus(text.copied, 'success', false); });
      else {
        var area = document.createElement('textarea'); area.value = diagnostic; area.style.position = 'fixed'; area.style.left = '-9999px'; document.body.appendChild(area); area.select(); document.execCommand('copy'); area.remove(); setStatus(text.copied, 'success', false);
      }
    }

    trigger.addEventListener('click', function () { panel.hidden ? open() : close(true); });
    panel.querySelector('[data-health-close]').addEventListener('click', function () { close(true); });
    refresh.addEventListener('click', function () { runRefresh(text.refreshed); });
    clear.addEventListener('click', function () {
      if (Array.isArray(window.userAppDirectoryGroups)) window.userAppDirectoryGroups.length = 0;
      runRefresh(text.cleared);
    });
    retry.addEventListener('click', function () { runRefresh(text.refreshed); });
    view.addEventListener('click', function () { toggleDiagnostics(); });
    panel.querySelector('[data-health-diagnostics-close]').addEventListener('click', function () { toggleDiagnostics(false); });
    panel.querySelector('[data-health-copy]').addEventListener('click', copyDiagnostics);
    window.addEventListener('online', sync); window.addEventListener('offline', sync);
    window.addEventListener('resize', function () { if (!diagnostics.hidden) updateDiagnostics(); });
    document.addEventListener('keydown', function (event) {
      if (event.key !== 'Escape' || panel.hidden) return;
      if (!diagnostics.hidden) toggleDiagnostics(false); else close(true);
    });
    document.addEventListener('click', function (event) { if (!panel.hidden && !panel.contains(event.target) && !trigger.contains(event.target)) close(false); });
    sync();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', ready);
  else ready();
}());
