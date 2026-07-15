(function (window, $) {
  'use strict';

  if (!$ || typeof $.toast !== 'function') {
    return;
  }

  var originalToast = $.toast;
  var originalSwal = window.swal;

  function normaliseType(type) {
    return ['success', 'error', 'warning', 'info'].indexOf(type) !== -1 ? type : 'info';
  }

  function oneidToast(title, message, type, options) {
    var settings = $.extend({
      heading: title || '',
      text: message || '',
      icon: normaliseType(type),
      position: 'top-right',
      loaderBg: '#fec107',
      hideAfter: type === 'error' ? 7000 : 3500,
      stack: 6,
      showHideTransition: 'slide',
      allowToastClose: true
    }, options || {});

    settings.position = 'top-right';
    return originalToast(settings);
  }

  function forcedTopRightToast(options) {
    if (arguments.length === 0) {
      return originalToast();
    }

    if (typeof options === 'object' && options !== null) {
      options = $.extend({}, options, { position: 'top-right' });
    }

    return originalToast(options);
  }

  Object.keys(originalToast).forEach(function (key) {
    forcedTopRightToast[key] = originalToast[key];
  });
  $.toast = forcedTopRightToast;

  window.oneidToast = oneidToast;
  window.oneidConfirm = function (title, message, confirmText, onConfirm) {
    if (typeof originalSwal !== 'function') {
      oneidToast('Confirmation unavailable', 'The requested action was not performed.', 'error');
      return;
    }

    originalSwal({
      title: title,
      text: message,
      type: 'warning',
      confirmButtonColor: '#DD6B55',
      confirmButtonText: confirmText || 'Yes',
      showCancelButton: true,
      closeOnConfirm: true
    }, function (confirmed) {
      if (confirmed && typeof onConfirm === 'function') {
        onConfirm();
      }
    });
  };

  if (typeof originalSwal === 'function') {
    window.swal = function () {
      var args = Array.prototype.slice.call(arguments);
      var isNotification = typeof args[0] === 'string'
        && typeof args[2] === 'string'
        && typeof args[3] !== 'function';

      if (isNotification) {
        if (typeof originalSwal.close === 'function') {
          originalSwal.close();
        }
        return oneidToast(args[0], args[1], args[2]);
      }

      return originalSwal.apply(window, args);
    };
  }
}(window, window.jQuery));
