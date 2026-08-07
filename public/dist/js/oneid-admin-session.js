(function (window, document) {
    'use strict';

    var config = window.OneIdAdminSessionConfig || null;
    if (!config || typeof window.swal !== 'function') {
        return;
    }

    var warningSeconds = 120;
    var deadlineMs = 0;
    var expiryTimer = null;
    var warningTimer = null;
    var countdownTimer = null;
    var warningOpen = false;
    var renewalPending = false;
    var channel = typeof window.BroadcastChannel === 'function'
        ? new window.BroadcastChannel('oneid-admin-access-session')
        : null;

    function redirectToUser() {
        window.location.replace(config.userDashboardUrl);
    }

    function remainingSeconds() {
        return Math.max(0, Math.ceil((deadlineMs - Date.now()) / 1000));
    }

    function formatDuration(seconds) {
        var minutes = Math.floor(seconds / 60);
        var remainder = seconds % 60;
        return String(minutes).padStart(2, '0') + ':' + String(remainder).padStart(2, '0');
    }

    function applyProfessionalLayout() {
        var alert = document.querySelector('.sweet-alert.showSweetAlert');
        var overlay = document.querySelector('.sweet-overlay');
        if (!alert) {
            return;
        }
        alert.classList.add('oneid-admin-session-alert');
        if (overlay) {
            overlay.classList.add('oneid-admin-session-overlay');
        }
        if (!alert.querySelector('.oneid-session-eyebrow')) {
            var eyebrow = document.createElement('div');
            eyebrow.className = 'oneid-session-eyebrow';
            eyebrow.textContent = config.text.securityEyebrow;
            var icon = alert.querySelector('.sa-icon');
            alert.insertBefore(eyebrow, icon || alert.firstChild);
        }
        var paragraph = alert.querySelector('p');
        if (paragraph && !paragraph.querySelector('.oneid-session-countdown')) {
            paragraph.textContent = '';
            var countdown = document.createElement('strong');
            countdown.className = 'oneid-session-countdown';
            var copy = document.createElement('span');
            copy.className = 'oneid-session-copy';
            copy.textContent = config.text.warningBody.replace('{time}', '').replace(/\s{2,}/g, ' ').trim();
            paragraph.appendChild(countdown);
            paragraph.appendChild(copy);
        }
    }

    function updateWarningMessage() {
        var countdown = document.querySelector('.sweet-alert.showSweetAlert .oneid-session-countdown');
        if (countdown && warningOpen) {
            countdown.textContent = formatDuration(remainingSeconds());
        }
        if (remainingSeconds() <= 0) {
            redirectToUser();
        }
    }

    function clearTimers() {
        window.clearTimeout(expiryTimer);
        window.clearTimeout(warningTimer);
        window.clearInterval(countdownTimer);
        expiryTimer = null;
        warningTimer = null;
        countdownTimer = null;
    }

    function broadcastRenewal() {
        var message = {type: 'renewed', deadlineMs: deadlineMs, sentAt: Date.now()};
        if (channel) {
            channel.postMessage(message);
        }
        try {
            window.localStorage.setItem('oneid-admin-access-session', JSON.stringify(message));
        } catch (ignored) {}
    }

    function post(action, extra) {
        var body = new URLSearchParams();
        body.append(action, '1');
        Object.keys(extra || {}).forEach(function (key) {
            body.append(key, String(extra[key]));
        });
        return window.fetch(config.apiUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-Token': config.csrfToken,
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
            },
            body: body.toString()
        }).then(function (response) {
            return response.json().catch(function () { return {}; }).then(function (payload) {
                if (!response.ok || Number(payload.status) === 0) {
                    var error = new Error(payload.error || config.text.requestFailed);
                    error.code = payload.code || '';
                    error.status = response.status;
                    throw error;
                }
                return payload;
            });
        });
    }

    function schedule(seconds, notifyOtherTabs) {
        clearTimers();
        warningOpen = false;
        deadlineMs = Date.now() + Math.max(0, Number(seconds) || 0) * 1000;
        if (remainingSeconds() <= 0) {
            redirectToUser();
            return;
        }
        expiryTimer = window.setTimeout(redirectToUser, remainingSeconds() * 1000);
        var untilWarning = Math.max(0, remainingSeconds() - warningSeconds);
        warningTimer = window.setTimeout(showWarning, untilWarning * 1000);
        if (notifyOtherTabs) {
            broadcastRenewal();
        }
    }

    function adoptDeadline(nextDeadlineMs) {
        var seconds = Math.ceil((Number(nextDeadlineMs) - Date.now()) / 1000);
        if (seconds <= 0) {
            redirectToUser();
            return;
        }
        if (warningOpen) {
            window.swal.close();
        }
        schedule(seconds, false);
    }

    function renew() {
        if (renewalPending || remainingSeconds() <= 0) {
            return;
        }
        renewalPending = true;
        window.clearInterval(countdownTimer);
        post('admin_step_up_renew').then(function (payload) {
            renewalPending = false;
            warningOpen = false;
            window.swal({
                title: config.text.renewedTitle,
                text: config.text.renewedBody.replace('{minutes}', String(payload.admin_step_up_lifetime_minutes)),
                type: 'success',
                confirmButtonText: config.text.ok,
                showConfirmButton: true,
                closeOnConfirm: true,
                allowEscapeKey: false
            });
            schedule(payload.effective_remaining_seconds !== undefined
                ? payload.effective_remaining_seconds
                : payload.grant_remaining_seconds, true);
        }).catch(function (error) {
            renewalPending = false;
            if (error.status === 401 || error.code === 'STEP_UP_EXPIRED' || remainingSeconds() <= 0) {
                redirectToUser();
                return;
            }
            window.swal({
                title: config.text.renewFailedTitle,
                text: config.text.renewFailedBody,
                type: 'error',
                confirmButtonText: config.text.tryAgain,
                closeOnConfirm: true
            }, function () {
                showWarning();
            });
        });
    }

    function showWarning() {
        if (warningOpen || renewalPending || remainingSeconds() <= 0) {
            if (remainingSeconds() <= 0) {
                redirectToUser();
            }
            return;
        }
        warningOpen = true;
        window.swal({
            title: config.text.warningTitle,
            text: config.text.warningBody,
            type: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#079bd3',
            confirmButtonText: config.text.stayConnected,
            cancelButtonText: config.text.backToUser,
            closeOnConfirm: false,
            closeOnCancel: false,
            allowEscapeKey: false
        }, function (isConfirm) {
            if (isConfirm) {
                renew();
            } else {
                redirectToUser();
            }
        });
        applyProfessionalLayout();
        updateWarningMessage();
        countdownTimer = window.setInterval(updateWarningMessage, 1000);
    }

    function synchronize() {
        post('admin_step_up_status', {purpose: 'ADMIN_ACCESS'}).then(function (payload) {
            if (!payload.feature_enabled) {
                return;
            }
            if (!payload.grant_valid || Number(payload.grant_remaining_seconds) <= 0) {
                redirectToUser();
                return;
            }
            schedule(payload.effective_remaining_seconds !== undefined
                ? payload.effective_remaining_seconds
                : payload.grant_remaining_seconds, false);
        }).catch(function () {
            redirectToUser();
        });
    }

    if (channel) {
        channel.onmessage = function (event) {
            if (event.data && event.data.type === 'renewed') {
                adoptDeadline(event.data.deadlineMs);
            }
        };
    }
    window.addEventListener('storage', function (event) {
        if (event.key !== 'oneid-admin-access-session' || !event.newValue) {
            return;
        }
        try {
            var message = JSON.parse(event.newValue);
            if (message.type === 'renewed') {
                adoptDeadline(message.deadlineMs);
            }
        } catch (ignored) {}
    });
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            synchronize();
        }
    });
    if (window.jQuery) {
        window.jQuery(document).ajaxError(function (_event, xhr) {
            var payload = xhr.responseJSON || {};
            if (xhr.status === 403 && ['STEP_UP_EXPIRED', 'STEP_UP_REQUIRED'].indexOf(payload.code) !== -1) {
                redirectToUser();
            }
        });
    }

    synchronize();
})(window, document);
