(function () {
  'use strict';

  var storageKey = 'oneid.display-settings.v3';
  var legacyStorageKeys = ['oneid.display-settings.v1', 'oneid.display-settings.v2'];
  var allowedScales = ['100', '108', '115', '123', '130'];
  var defaults = { version: 1, fontScale: '100', highContrast: false, reduceMotion: false, underlineLinks: false };

  function normalize(value) {
    var input = value && typeof value === 'object' ? value : {};
    return {
      version: 1,
      fontScale: allowedScales.indexOf(String(input.fontScale)) >= 0 ? String(input.fontScale) : defaults.fontScale,
      highContrast: input.highContrast === true,
      reduceMotion: input.reduceMotion === true,
      underlineLinks: input.underlineLinks === true
    };
  }

  function read() {
    try { return normalize(JSON.parse(window.localStorage.getItem(storageKey) || '{}')); }
    catch (error) { return normalize(defaults); }
  }

  function write(state) {
    try { window.localStorage.setItem(storageKey, JSON.stringify(normalize(state))); legacyStorageKeys.forEach(function (key) { window.localStorage.removeItem(key); }); }
    catch (error) { /* Storage is optional; the active page still updates. */ }
  }

  function apply(state) {
    var root = document.documentElement;
    state = normalize(state);
    root.setAttribute('data-oneid-font-scale', state.fontScale);
    if (state.highContrast) root.setAttribute('data-oneid-contrast', 'high');
    else root.removeAttribute('data-oneid-contrast');
    if (state.reduceMotion) root.setAttribute('data-oneid-motion', 'reduce');
    else root.removeAttribute('data-oneid-motion');
    if (state.underlineLinks) root.setAttribute('data-oneid-links', 'underline');
    else root.removeAttribute('data-oneid-links');
    return state;
  }

  var state = apply(read());

  function ready() {
    var widget = document.querySelector('[data-oneid-display-settings]');
    if (!widget) return;
    var trigger = widget.querySelector('.oneid-display-settings__trigger');
    var panel = widget.querySelector('.oneid-display-settings__panel');
    var close = widget.querySelector('.oneid-display-settings__close');
    var reset = widget.querySelector('[data-oneid-display-reset]');
    var status = widget.querySelector('.oneid-display-settings__status');
    var scaleButtons = widget.querySelectorAll('[data-oneid-font-scale]');
    var toggles = widget.querySelectorAll('[data-oneid-setting]');
    var localeSwitcher = document.querySelector('.login-locale-switcher, .profile-locale-switcher');
    var savedText = document.documentElement.lang === 'en' ? 'Saved' : 'Disimpan';
    var resetText = document.documentElement.lang === 'en' ? 'Defaults restored' : 'Tetapan asal dipulihkan';

    function sync(message) {
      Array.prototype.forEach.call(scaleButtons, function (button) {
        button.setAttribute('aria-pressed', String(button.getAttribute('data-oneid-font-scale') === state.fontScale));
      });
      Array.prototype.forEach.call(toggles, function (toggle) { toggle.checked = state[toggle.getAttribute('data-oneid-setting')] === true; });
      status.textContent = message || '';
    }

    function positionPanel() {
      if (!localeSwitcher || panel.hidden) return;
      var triggerRect = trigger.getBoundingClientRect();
      var panelRect = panel.getBoundingClientRect();
      var gap = 8;
      var viewportRight = window.innerWidth - panelRect.width - gap;
      var opensBesideProfile = localeSwitcher.classList.contains('profile-locale-switcher');
      var hasRoomOnRight = triggerRect.right + gap + panelRect.width <= window.innerWidth - gap;
      var left = opensBesideProfile && hasRoomOnRight
        ? triggerRect.right + gap
        : Math.max(gap, Math.min(viewportRight, triggerRect.right - panelRect.width));
      var top = opensBesideProfile && hasRoomOnRight
        ? Math.max(gap, Math.min(window.innerHeight - panelRect.height - gap, triggerRect.top))
        : triggerRect.bottom + gap;
      if (!opensBesideProfile && top + panelRect.height > window.innerHeight - gap) {
        top = Math.max(gap, triggerRect.top - panelRect.height - gap);
      }
      panel.style.bottom = 'auto'; panel.style.left = left + 'px'; panel.style.top = top + 'px';
    }
    function persist(message) { state = apply(state); write(state); sync(message); positionPanel(); }
    function open() { panel.hidden = false; trigger.setAttribute('aria-expanded', 'true'); positionPanel(); close.focus(); }
    function shut(restoreFocus) { panel.hidden = true; trigger.setAttribute('aria-expanded', 'false'); if (restoreFocus) trigger.focus(); }

    trigger.addEventListener('click', function () { if (panel.hidden) open(); else shut(); });
    close.addEventListener('click', function () { shut(true); });
    Array.prototype.forEach.call(scaleButtons, function (button) {
      button.addEventListener('click', function () { state.fontScale = button.getAttribute('data-oneid-font-scale'); persist(savedText); });
    });
    Array.prototype.forEach.call(toggles, function (toggle) {
      toggle.addEventListener('change', function () { state[toggle.getAttribute('data-oneid-setting')] = toggle.checked; persist(savedText); });
    });
    reset.addEventListener('click', function () {
      state = normalize(defaults);
      try { window.localStorage.removeItem(storageKey); legacyStorageKeys.forEach(function (key) { window.localStorage.removeItem(key); }); } catch (error) { /* Optional storage. */ }
      state = apply(state); sync(resetText);
    });
    panel.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') { event.preventDefault(); shut(true); }
    });
    document.addEventListener('click', function (event) {
      if (!panel.hidden && !widget.contains(event.target)) shut(false);
    });
    if (localeSwitcher) { widget.classList.add('is-locale-mounted'); localeSwitcher.appendChild(widget); }
    window.addEventListener('resize', positionPanel);
    sync('');
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', ready);
  else ready();
}());
