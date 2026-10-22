(function () {
  'use strict';

  var config = window.OneIdUserWeatherConfig || {};
  var text = config.text || {};

  function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>'"]/g, function (character) {
      return {'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[character];
    });
  }

  function weatherKind(code) {
    code = Number(code);
    if (code === 0) return 'clear';
    if (code === 1) return 'mainlyClear';
    if (code === 2) return 'partlyCloudy';
    if (code === 3) return 'overcast';
    if (code === 45 || code === 48) return 'fog';
    if ([51,53,55,56,57].indexOf(code) !== -1) return 'drizzle';
    if ([61,63,65,66,67].indexOf(code) !== -1) return 'rain';
    if ([71,73,75,77,85,86].indexOf(code) !== -1) return 'snow';
    if ([80,81,82].indexOf(code) !== -1) return 'showers';
    if ([95,96,99].indexOf(code) !== -1) return 'thunderstorm';
    return 'unknown';
  }

  function iconClass(kind) {
    return {clear:'fa-sun',mainlyClear:'fa-cloud-sun',partlyCloudy:'fa-cloud-sun',overcast:'fa-cloud',fog:'fa-smog',drizzle:'fa-cloud-rain',rain:'fa-cloud-showers-heavy',snow:'fa-snowflake',showers:'fa-cloud-showers-heavy',thunderstorm:'fa-bolt',unknown:'fa-cloud'}[kind] || 'fa-cloud';
  }

  function airQualityKind(aqi) {
    aqi = Number(aqi);
    if (!Number.isFinite(aqi)) return 'unknown';
    if (aqi <= 50) return 'good';
    if (aqi <= 100) return 'moderate';
    if (aqi <= 150) return 'sensitive';
    if (aqi <= 200) return 'unhealthy';
    if (aqi <= 300) return 'veryUnhealthy';
    return 'hazardous';
  }

  function number(value, digits) {
    var parsed = Number(value);
    if (!Number.isFinite(parsed)) return '--';
    return parsed.toLocaleString(document.documentElement.lang === 'en' ? 'en-MY' : 'ms-MY', {maximumFractionDigits:digits});
  }

  function localTime(value) {
    var date = new Date(value);
    if (Number.isNaN(date.getTime())) return text.notAvailable;
    try { return new Intl.DateTimeFormat(document.documentElement.lang === 'en' ? 'en-MY' : 'ms-MY', {dateStyle:'medium',timeStyle:'short'}).format(date); }
    catch (error) { return date.toLocaleString(); }
  }

  function hourLabel(value) {
    var date = new Date(value);
    if (Number.isNaN(date.getTime())) return '--';
    return new Intl.DateTimeFormat(document.documentElement.lang === 'en' ? 'en-MY' : 'ms-MY', {hour:'numeric'}).format(date);
  }

  function dayLabel(value, index) {
    if (index === 0) return text.today;
    var date = new Date(value + 'T12:00:00+08:00');
    if (Number.isNaN(date.getTime())) return value;
    return new Intl.DateTimeFormat(document.documentElement.lang === 'en' ? 'en-MY' : 'ms-MY', {weekday:'short'}).format(date);
  }

  function isCurrentHour(value) {
    var forecast = new Date(value);
    var now = new Date();
    return !Number.isNaN(forecast.getTime())
      && forecast.getFullYear() === now.getFullYear()
      && forecast.getMonth() === now.getMonth()
      && forecast.getDate() === now.getDate()
      && forecast.getHours() === now.getHours();
  }

  function ready() {
    var trigger = document.getElementById('oneid_user_weather_trigger');
    if (!trigger || !config.apiUrl) return;
    var panel = document.createElement('section');
    panel.className = 'oneid-weather-panel';
    panel.id = 'oneid_user_weather_panel';
    panel.hidden = true;
    panel.setAttribute('role', 'dialog');
    panel.setAttribute('aria-modal', 'false');
    panel.setAttribute('aria-labelledby', 'oneid_user_weather_title');
    panel.innerHTML = '<div class="oneid-weather-panel__head">' +
      '<span class="oneid-weather-panel__head-icon"><i class="fa fa-cloud-sun" data-weather-panel-icon aria-hidden="true"></i></span>' +
      '<span class="oneid-weather-panel__heading"><strong id="oneid_user_weather_title">' + escapeHtml(text.title) + '</strong><small data-weather-location>' + escapeHtml(text.location) + '</small></span>' +
      '<button type="button" class="oneid-weather-panel__refresh" data-weather-refresh aria-label="' + escapeHtml(text.refresh) + '" title="' + escapeHtml(text.refresh) + '"><i class="fa fa-refresh" aria-hidden="true"></i></button>' +
      '<button type="button" class="oneid-weather-panel__close" data-weather-close aria-label="' + escapeHtml(text.close) + '">&times;</button></div>' +
      '<div class="oneid-weather-panel__body" aria-live="polite">' +
      '<div data-weather-content hidden>' +
      '<div class="oneid-weather-tabs" role="tablist" aria-label="' + escapeHtml(text.forecast) + '"><button type="button" role="tab" aria-selected="true" data-weather-tab="now">' + escapeHtml(text.now) + '</button><button type="button" role="tab" aria-selected="false" data-weather-tab="hourly">' + escapeHtml(text.hourly) + '</button><button type="button" role="tab" aria-selected="false" data-weather-tab="daily">' + escapeHtml(text.sevenDays) + '</button></div>' +
      '<div class="oneid-weather-tab-panel" role="tabpanel" data-weather-tab-panel="now">' +
      '<section data-weather-section><div class="oneid-weather-current"><span class="oneid-weather-current__icon"><i class="fa fa-cloud-sun" data-weather-main-icon aria-hidden="true"></i></span><span class="oneid-weather-current__reading"><strong data-weather-temperature>--°C</strong><small data-weather-condition></small></span></div>' +
      '<dl class="oneid-weather-details"><div><dt><i class="fa fa-temperature-high" aria-hidden="true"></i>' + escapeHtml(text.feelsLike) + '</dt><dd data-weather-apparent></dd></div><div><dt><i class="fa fa-tint" aria-hidden="true"></i>' + escapeHtml(text.humidity) + '</dt><dd data-weather-humidity></dd></div><div><dt><i class="fa fa-cloud-rain" aria-hidden="true"></i>' + escapeHtml(text.precipitation) + '</dt><dd data-weather-precipitation></dd></div><div><dt><i class="fa fa-wind" aria-hidden="true"></i>' + escapeHtml(text.wind) + '</dt><dd data-weather-wind></dd></div></dl></section>' +
      '<p class="oneid-weather-section-error" data-weather-section-error hidden><i class="fa fa-cloud" aria-hidden="true"></i>' + escapeHtml(text.weatherUnavailable) + '</p>' +
      '<section class="oneid-weather-air" data-weather-air-card><div class="oneid-weather-air__top"><strong><i class="fa fa-smog" aria-hidden="true"></i>' + escapeHtml(text.airQuality) + '</strong><span class="oneid-weather-air__aqi-wrap"><span class="oneid-weather-air__aqi" data-weather-aqi></span><button type="button" class="oneid-weather-air__info" aria-label="' + escapeHtml(text.airQualityNote) + '" data-air-info="' + escapeHtml(text.airQualityNote) + '"><i class="fa fa-info-circle" aria-hidden="true"></i></button></span></div><small class="oneid-weather-air__status" data-weather-air-label></small><div class="oneid-weather-air__particles"><span><b>PM2.5</b><strong data-weather-pm25></strong></span><span><b>PM10</b><strong data-weather-pm10></strong></span></div></section>' +
      '<p class="oneid-weather-section-error" data-air-error hidden><i class="fa fa-smog" aria-hidden="true"></i>' + escapeHtml(text.airUnavailable) + '</p>' +
      '<p class="oneid-weather-times"><i class="fa fa-clock-o" aria-hidden="true"></i><span data-weather-observed></span><span data-weather-checked></span></p><p class="oneid-weather-stale" data-weather-stale hidden><i class="fa fa-exclamation-circle" aria-hidden="true"></i>' + escapeHtml(text.stale) + '</p></div>' +
      '<div class="oneid-weather-tab-panel" role="tabpanel" data-weather-tab-panel="hourly" hidden><div class="oneid-weather-hourly" data-weather-hourly></div><p class="oneid-weather-forecast-empty" data-weather-hourly-empty hidden>' + escapeHtml(text.forecastUnavailable) + '</p></div>' +
      '<div class="oneid-weather-tab-panel" role="tabpanel" data-weather-tab-panel="daily" hidden><div class="oneid-weather-daily" data-weather-daily></div><p class="oneid-weather-forecast-empty" data-weather-daily-empty hidden>' + escapeHtml(text.forecastUnavailable) + '</p></div></div>' +
      '<div class="oneid-weather-error" data-weather-error hidden><i class="fa fa-cloud" aria-hidden="true"></i><strong>' + escapeHtml(text.unavailable) + '</strong><button type="button" data-weather-retry>' + escapeHtml(text.retry) + '</button></div></div>' +
      '<div class="oneid-weather-panel__footer"><span>' + escapeHtml(text.source) + ' <a href="https://open-meteo.com/" target="_blank" rel="noopener noreferrer">Open-Meteo</a> · <a href="https://atmosphere.copernicus.eu/" target="_blank" rel="noopener noreferrer">CAMS ENSEMBLE</a></span><span>' + escapeHtml(text.locationNote) + '</span></div>';
    document.body.appendChild(panel);

    var triggerIcon = trigger.querySelector('[data-weather-trigger-icon]');
    var triggerTemperature = trigger.querySelector('[data-weather-trigger-temperature]');
    var content = panel.querySelector('[data-weather-content]');
    var errorBox = panel.querySelector('[data-weather-error]');
    var refresh = panel.querySelector('[data-weather-refresh]');
    var panelBody = panel.querySelector('.oneid-weather-panel__body');
    var lastLoadedAt = 0;
    var activeRequest = null;

    function setIcon(element, kind) { if (element) element.className = 'fa ' + iconClass(kind); }

    function renderForecast(weather) {
      var hourly = Array.isArray(weather && weather.hourly_forecast) ? weather.hourly_forecast : [];
      var hourlyBox = panel.querySelector('[data-weather-hourly]');
      hourlyBox.innerHTML = hourly.map(function (item) {
        var kind = weatherKind(item.weather_code);
        return '<article class="oneid-weather-hour' + (isCurrentHour(item.at) ? ' is-current' : '') + '"><time>' + escapeHtml(hourLabel(item.at)) + '</time><i class="fa ' + iconClass(kind) + '" aria-hidden="true"></i><strong>' + escapeHtml(number(item.temperature_c, 0)) + '°</strong><small><i class="fa fa-tint" aria-hidden="true"></i>' + escapeHtml(number(item.precipitation_probability_percent, 0)) + '%</small></article>';
      }).join('');
      panel.querySelector('[data-weather-hourly-empty]').hidden = hourly.length > 0;

      var daily = Array.isArray(weather && weather.daily_forecast) ? weather.daily_forecast : [];
      var dailyBox = panel.querySelector('[data-weather-daily]');
      dailyBox.innerHTML = daily.map(function (item, index) {
        var kind = weatherKind(item.weather_code);
        return '<article class="oneid-weather-day"><time>' + escapeHtml(dayLabel(item.date, index)) + '</time><i class="fa ' + iconClass(kind) + '" aria-hidden="true"></i><span class="oneid-weather-day__condition">' + escapeHtml(text.conditions[kind] || text.conditions.unknown) + '</span><strong>' + escapeHtml(number(item.temperature_min_c, 0)) + '° / ' + escapeHtml(number(item.temperature_max_c, 0)) + '°</strong><small><i class="fa fa-tint" aria-hidden="true"></i>' + escapeHtml(number(item.precipitation_probability_percent, 0)) + '%</small></article>';
      }).join('');
      panel.querySelector('[data-weather-daily-empty]').hidden = daily.length > 0;
    }

    function render(payload) {
      var weather = payload.weather;
      var air = payload.air_quality;
      var weatherSection = panel.querySelector('[data-weather-section]');
      var weatherError = panel.querySelector('[data-weather-section-error]');
      var airCard = panel.querySelector('[data-weather-air-card]');
      var airError = panel.querySelector('[data-air-error]');
      weatherSection.hidden = !payload.weather_available;
      weatherError.hidden = payload.weather_available;
      airCard.hidden = !payload.air_quality_available;
      airError.hidden = payload.air_quality_available;

      if (payload.weather_available && weather) {
        var kind = weatherKind(weather.weather_code);
        triggerTemperature.textContent = number(weather.temperature_c, 0) + '°';
        setIcon(triggerIcon, kind); setIcon(panel.querySelector('[data-weather-panel-icon]'), kind); setIcon(panel.querySelector('[data-weather-main-icon]'), kind);
        panel.querySelector('[data-weather-temperature]').textContent = number(weather.temperature_c, 1) + '°C';
        panel.querySelector('[data-weather-condition]').textContent = text.conditions[kind] || text.conditions.unknown;
        panel.querySelector('[data-weather-apparent]').textContent = number(weather.apparent_temperature_c, 1) + '°C';
        panel.querySelector('[data-weather-humidity]').textContent = number(weather.humidity_percent, 0) + '%';
        panel.querySelector('[data-weather-precipitation]').textContent = number(weather.precipitation_mm, 1) + ' mm';
        panel.querySelector('[data-weather-wind]').textContent = number(weather.wind_speed_kmh, 1) + ' km/h';
        renderForecast(weather);
      } else {
        triggerTemperature.textContent = '--°'; setIcon(triggerIcon, 'unknown'); renderForecast(null);
      }
      if (payload.air_quality_available && air) {
        var airKind = airQualityKind(air.air_quality_aqi);
        airCard.className = 'oneid-weather-air is-' + airKind;
        panel.querySelector('[data-weather-aqi]').textContent = 'AQI ' + number(air.air_quality_aqi, 0);
        panel.querySelector('[data-weather-air-label]').textContent = text.airConditions[airKind] || text.airConditions.unknown;
        panel.querySelector('[data-weather-pm25]').textContent = number(air.pm2_5, 1) + ' µg/m³';
        panel.querySelector('[data-weather-pm10]').textContent = number(air.pm10, 1) + ' µg/m³';
      }
      var observed = weather && weather.observed_at ? weather.observed_at : (air && air.observed_at ? air.observed_at : '');
      var checked = weather && weather.checked_at ? weather.checked_at : (air && air.checked_at ? air.checked_at : '');
      panel.querySelector('[data-weather-observed]').textContent = text.observed.replace('{time}', localTime(observed));
      panel.querySelector('[data-weather-checked]').textContent = text.checked.replace('{time}', localTime(checked));
      panel.querySelector('[data-weather-location]').textContent = payload.location || text.location;
      panel.querySelector('[data-weather-stale]').hidden = !((weather && weather.stale) || (air && air.stale));
      errorBox.hidden = true; content.hidden = false; lastLoadedAt = Date.now();
    }

    function setBusy(busy) { refresh.disabled = busy; refresh.querySelector('i').classList.toggle('fa-spin', busy); panelBody.setAttribute('aria-busy', busy ? 'true' : 'false'); }
    function load(force) {
      if (activeRequest || (!force && lastLoadedAt && Date.now() - lastLoadedAt < 15 * 60 * 1000)) return;
      errorBox.hidden = true; setBusy(true);
      var body = new URLSearchParams(); body.set('user_weather_status', '1'); body.set('_csrf_token', config.csrfToken || '');
      if (force) body.set('force_refresh', '1');
      activeRequest = fetch(config.apiUrl, {method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8','Accept':'application/json'},body:body.toString()})
        .then(function (response) { if (!response.ok) throw new Error('weather'); return response.json(); })
        .then(function (payload) { if (!payload || payload.status !== 1 || !payload.weather) throw new Error('weather'); render(payload.weather); })
        .catch(function () { if (!lastLoadedAt) { content.hidden = true; errorBox.hidden = false; } })
        .finally(function () { activeRequest = null; setBusy(false); });
    }

    function open() { document.dispatchEvent(new CustomEvent('oneid:context-panel-open', {detail:{id:panel.id}})); panel.hidden = false; trigger.setAttribute('aria-expanded', 'true'); panel.querySelector('[data-weather-close]').focus(); load(false); }
    function close(restore) { panel.hidden = true; trigger.setAttribute('aria-expanded', 'false'); if (restore) trigger.focus(); }
    panel.querySelectorAll('[data-weather-tab]').forEach(function (button) {
      button.addEventListener('click', function () {
        var selected = button.getAttribute('data-weather-tab');
        panel.querySelectorAll('[data-weather-tab]').forEach(function (tab) { tab.setAttribute('aria-selected', tab === button ? 'true' : 'false'); });
        panel.querySelectorAll('[data-weather-tab-panel]').forEach(function (tabPanel) { tabPanel.hidden = tabPanel.getAttribute('data-weather-tab-panel') !== selected; });
      });
    });
    trigger.addEventListener('click', function () { panel.hidden ? open() : close(true); });
    panel.querySelector('[data-weather-close]').addEventListener('click', function () { close(true); });
    refresh.addEventListener('click', function () { load(true); });
    panel.querySelector('[data-weather-retry]').addEventListener('click', function () { load(true); });
    document.addEventListener('oneid:context-panel-open', function (event) { if (event.detail && event.detail.id !== panel.id && !panel.hidden) close(false); });
    document.addEventListener('click', function (event) { if (!panel.hidden && !panel.contains(event.target) && !trigger.contains(event.target)) close(false); });
    document.addEventListener('keydown', function (event) { if (event.key === 'Escape' && !panel.hidden) close(true); });
    document.addEventListener('visibilitychange', function () { if (!document.hidden && Date.now() - lastLoadedAt >= 15 * 60 * 1000) load(true); });
    load(false);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', ready); else ready();
}());
