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

  function ready() {
    var trigger = document.getElementById('oneid_user_health_trigger');
    if (!trigger) return;
    var responseMs = pageResponseMs();
    var updatedAt = new Date();
    var panel = document.createElement('section');
    panel.className = 'oneid-health-panel';
    panel.id = 'oneid_user_health_panel';
    panel.hidden = true;
    panel.setAttribute('role', 'dialog');
    panel.setAttribute('aria-modal', 'false');
    panel.setAttribute('aria-labelledby', 'oneid_user_health_title');
    panel.innerHTML = '<div class="oneid-health-panel__head">' +
      '<span class="oneid-health-panel__head-icon"><span class="oneid-status-lights is-panel" aria-hidden="true"><i></i><i></i><i></i></span></span>' +
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
      '<button type="button" class="oneid-health-action" data-health-copy><i class="fa fa-copy" aria-hidden="true"></i>' + escapeHtml(text.copy) + '</button>' +
      '<a class="oneid-health-action" href="mailto:' + encodeURIComponent(config.supportEmail || '') + '"><i class="fa fa-life-ring" aria-hidden="true"></i>' + escapeHtml(text.support) + '</a>' +
      '</div><p class="oneid-health-status" data-health-status aria-live="polite"></p><p class="oneid-health-note">' + escapeHtml(text.note) + '</p></div>' +
      '<div class="oneid-health-support"><a href="tel:+60390512700">' + escapeHtml(config.supportPhone) + '</a><span>' + escapeHtml(config.supportEmail) + '</span></div>';
    document.body.appendChild(panel);

    var connection = panel.querySelector('[data-health-connection]');
    var response = panel.querySelector('[data-health-response]');
    var updated = panel.querySelector('[data-health-updated]');
    var status = panel.querySelector('[data-health-status]');
    var refresh = panel.querySelector('[data-health-refresh]');
    var clear = panel.querySelector('[data-health-clear]');

    function sync() {
      var online = navigator.onLine !== false;
      connection.classList.toggle('is-offline', !online);
      connection.querySelector('b').textContent = online ? text.online : text.offline;
      response.textContent = responseLabel(responseMs) + (responseMs ? ' · ' + responseMs + ' ms' : '');
      updated.textContent = localTime(updatedAt);
    }
    function open() { panel.hidden = false; trigger.setAttribute('aria-expanded', 'true'); panel.querySelector('[data-health-close]').focus(); sync(); }
    function close(restore) { panel.hidden = true; trigger.setAttribute('aria-expanded', 'false'); if (restore) trigger.focus(); }
    function runRefresh(message) {
      status.textContent = text.refreshing;
      refresh.disabled = true; clear.disabled = true;
      var requests = [];
      try { if (typeof window.get_specific_user_app_list === 'function') requests.push(window.get_specific_user_app_list()); } catch (error) { /* handled below */ }
      try { if (typeof window.get_specific_user_activ_session === 'function') requests.push(window.get_specific_user_activ_session()); } catch (error) { /* handled below */ }
      var complete = function (resultMessage) { updatedAt = new Date(); sync(); status.textContent = resultMessage; refresh.disabled = false; clear.disabled = false; };
      if (window.jQuery && requests.length) {
        window.jQuery.when.apply(window.jQuery, requests)
          .done(function () { complete(message); })
          .fail(function () { complete(text.failed); });
      } else complete(message);
    }
    function copyDiagnostics() {
      var diagnostic = [
        'OneID UI diagnostics',
        'Version: ' + String(config.version || ''),
        'Environment: ' + String(config.environment || ''),
        'Time: ' + new Date().toISOString(),
        'Path: ' + window.location.pathname,
        'Connection: ' + (navigator.onLine === false ? 'offline' : 'online'),
        'Page response: ' + responseMs + ' ms',
        'Viewport: ' + window.innerWidth + 'x' + window.innerHeight
      ].join('\n');
      if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(diagnostic).then(function () { status.textContent = text.copied; });
      else {
        var area = document.createElement('textarea'); area.value = diagnostic; area.style.position = 'fixed'; area.style.left = '-9999px'; document.body.appendChild(area); area.select(); document.execCommand('copy'); area.remove(); status.textContent = text.copied;
      }
    }

    trigger.addEventListener('click', function () { panel.hidden ? open() : close(true); });
    panel.querySelector('[data-health-close]').addEventListener('click', function () { close(true); });
    refresh.addEventListener('click', function () { runRefresh(text.refreshed); });
    clear.addEventListener('click', function () {
      if (Array.isArray(window.userAppDirectoryGroups)) window.userAppDirectoryGroups.length = 0;
      runRefresh(text.cleared);
    });
    panel.querySelector('[data-health-copy]').addEventListener('click', copyDiagnostics);
    window.addEventListener('online', sync); window.addEventListener('offline', sync);
    document.addEventListener('keydown', function (event) { if (event.key === 'Escape' && !panel.hidden) close(true); });
    document.addEventListener('click', function (event) { if (!panel.hidden && !panel.contains(event.target) && !trigger.contains(event.target)) close(false); });
    sync();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', ready);
  else ready();
}());
