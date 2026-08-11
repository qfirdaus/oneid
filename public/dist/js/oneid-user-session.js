(function (window, document) {
    'use strict';

    var config = window.OneIdUserSessionConfig || null;
    if (!config || !config.enabled || typeof window.swal !== 'function' || typeof window.fetch !== 'function') {
        return;
    }
    var sessionSwal = window.swal;

    var deadlineMs = 0;
    var warningTimer = null;
    var expiryTimer = null;
    var countdownTimer = null;
    var warningOpen = false;
    var requestPending = false;
    var resyncRequested = false;
    var ending = false;
    var terminalDialogOpen = false;
    var channel = typeof window.BroadcastChannel === 'function'
        ? new window.BroadcastChannel('oneid-user-portal-session')
        : null;

    function clearTimers() {
        window.clearTimeout(warningTimer);
        window.clearTimeout(expiryTimer);
        window.clearInterval(countdownTimer);
        warningTimer = null;
        expiryTimer = null;
        countdownTimer = null;
    }

    function remainingSeconds() {
        return Math.max(0, Math.ceil((deadlineMs - Date.now()) / 1000));
    }

    function formatDuration(seconds) {
        var minutes = Math.floor(seconds / 60);
        var remainder = seconds % 60;
        return String(minutes).padStart(2, '0') + ':' + String(remainder).padStart(2, '0');
    }

    function clearSensitiveInputs() {
        Array.prototype.forEach.call(document.querySelectorAll(
            'input[type="password"], input[autocomplete="one-time-code"]'
        ), function (input) { input.value = ''; });
    }

    function broadcast(type) {
        var message = {type: type, sentAt: Date.now()};
        if (channel) {
            channel.postMessage(message);
        }
        try {
            window.localStorage.setItem('oneid-user-portal-session', JSON.stringify(message));
        } catch (ignored) {}
    }

    function post(action) {
        var body = new URLSearchParams();
        body.append(action, '1');
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
                    error.code = payload.code || 'SESSION_STATUS_UNAVAILABLE';
                    error.status = response.status;
                    throw error;
                }
                return payload;
            });
        });
    }

    function redirectToLanding() {
        clearSensitiveInputs();
        window.location.replace(config.landingUrl);
    }

    function terminalMessage(code) {
        if (terminalDialogOpen) {
            return;
        }
        terminalDialogOpen = true;
        ending = true;
        clearTimers();
        clearSensitiveInputs();
        var body = code === 'SSO_TOKEN_REVOKED'
            ? config.text.revokedBody
            : (code === 'ACCOUNT_INACTIVE' ? config.text.inactiveBody : config.text.expiredBody);
        sessionSwal({
            title: config.text.expiredTitle,
            text: body,
            type: 'warning',
            confirmButtonText: config.text.ok,
            showCancelButton: false,
            closeOnConfirm: true,
            allowEscapeKey: false
        }, redirectToLanding);
        window.setTimeout(markTerminalDialog, 0);
    }

    function handleError(error) {
        if (error.code === 'CSRF_INVALID') {
            try {
                if (window.sessionStorage.getItem('oneid-user-session-csrf-retried') !== '1') {
                    window.sessionStorage.setItem('oneid-user-session-csrf-retried', '1');
                    window.location.reload();
                    return;
                }
            } catch (ignored) {}
            terminalMessage('CSRF_INVALID');
            return;
        }
        if (error.code === 'USER_SESSION_EXPIRED'
            || error.code === 'SSO_TOKEN_REVOKED'
            || error.code === 'ACCOUNT_INACTIVE'
        ) {
            broadcast('ended');
            terminalMessage(error.code);
            return;
        }
        window.setTimeout(synchronize, 15000);
    }

    function updateCountdown() {
        var countdown = document.querySelector('.sweet-alert.showSweetAlert .oneid-user-session-countdown');
        if (countdown && warningOpen) {
            countdown.textContent = formatDuration(remainingSeconds());
        }
        if (remainingSeconds() <= 0) {
            expirePortal();
        }
    }

    function decorateWarning() {
        var alert = document.querySelector('.sweet-alert.showSweetAlert');
        if (!alert) {
            return;
        }
        alert.classList.add('oneid-user-session-alert');
        markSessionOverlay();
        var paragraph = alert.querySelector('p');
        if (paragraph) {
            paragraph.textContent = '';
            var countdown = document.createElement('strong');
            countdown.className = 'oneid-user-session-countdown';
            countdown.textContent = formatDuration(remainingSeconds());
            var body = document.createElement('span');
            body.className = 'oneid-user-session-copy';
            body.textContent = config.text.warningBody;
            var note = document.createElement('small');
            note.className = 'oneid-user-session-note';
            note.textContent = config.text.otherAppsNote;
            paragraph.appendChild(countdown);
            paragraph.appendChild(body);
            paragraph.appendChild(note);
        }
        if (!alert.querySelector('.oneid-user-session-eyebrow')) {
            var eyebrow = document.createElement('span');
            eyebrow.className = 'oneid-user-session-eyebrow';
            eyebrow.textContent = config.text.eyebrow;
            alert.insertBefore(eyebrow, alert.firstChild);
        }
    }

    function markSessionOverlay() {
        var overlay = document.querySelector('.sweet-overlay');
        if (overlay) {
            overlay.classList.add('oneid-user-session-overlay');
        }
    }

    function markTerminalDialog() {
        var alert = document.querySelector('.sweet-alert.showSweetAlert');
        if (alert) {
            alert.classList.add('oneid-user-session-alert');
            if (!alert.querySelector('.oneid-user-session-eyebrow')) {
                var eyebrow = document.createElement('div');
                eyebrow.className = 'oneid-user-session-eyebrow';
                eyebrow.textContent = config.text.eyebrow;
                var icon = alert.querySelector('.sa-icon');
                alert.insertBefore(eyebrow, icon || alert.firstChild);
            }
        }
        markSessionOverlay();
    }

    function anotherDialogIsOpen() {
        if (warningOpen) {
            return false;
        }
        return Array.prototype.some.call(document.querySelectorAll(
            '.sweet-alert.showSweetAlert, .modal.in, .modal.show'
        ), function (dialog) {
            var style = window.getComputedStyle(dialog);
            return dialog.getAttribute('aria-hidden') !== 'true'
                && style.display !== 'none'
                && style.visibility !== 'hidden'
                && Number(style.opacity) !== 0
                && (dialog.offsetWidth > 0 || dialog.offsetHeight > 0);
        });
    }

    function showWarning() {
        if (ending || warningOpen) {
            return;
        }
        if (remainingSeconds() <= 0) {
            expirePortal();
            return;
        }
        if (anotherDialogIsOpen()) {
            warningTimer = window.setTimeout(showWarning, 1000);
            return;
        }
        warningOpen = true;
        sessionSwal({
            title: config.text.warningTitle,
            text: config.text.warningBody,
            type: 'warning',
            showCancelButton: true,
            confirmButtonText: config.text.stayConnected,
            cancelButtonText: config.text.endSession,
            confirmButtonColor: '#1769aa',
            closeOnConfirm: false,
            closeOnCancel: false,
            allowEscapeKey: false
        }, function (confirmed) {
            if (confirmed) {
                renew();
            } else {
                expirePortal();
            }
        });
        window.setTimeout(decorateWarning, 0);
        countdownTimer = window.setInterval(updateCountdown, 1000);
    }

    function schedule(payload) {
        clearTimers();
        var remaining = Math.max(0, Number(payload.effective_remaining_seconds) || 0);
        if (warningOpen
            && remaining > Number(config.warningSeconds || 120)
            && typeof sessionSwal.close === 'function'
        ) {
            sessionSwal.close();
            warningOpen = false;
        }
        deadlineMs = Date.now() + (remaining * 1000);
        if (remaining <= 0) {
            expirePortal();
            return;
        }
        if (remaining > Number(config.warningSeconds || 120)) {
            warningTimer = window.setTimeout(
                synchronizeForWarning,
                (remaining - Number(config.warningSeconds || 120)) * 1000
            );
        }
        expiryTimer = window.setTimeout(expirePortal, remaining * 1000 + 250);
        if (warningOpen) {
            countdownTimer = window.setInterval(updateCountdown, 1000);
            updateCountdown();
        }
    }

    function synchronizeForWarning() {
        synchronize(true);
    }

    function synchronize(showWhenDue) {
        if (requestPending || ending) {
            return;
        }
        requestPending = true;
        post('user_session_status').then(function (payload) {
            requestPending = false;
            try { window.sessionStorage.removeItem('oneid-user-session-csrf-retried'); } catch (ignored) {}
            schedule(payload);
            if (showWhenDue && Number(payload.effective_remaining_seconds) <= Number(config.warningSeconds || 120)) {
                showWarning();
            }
            if (resyncRequested) {
                resyncRequested = false;
                window.setTimeout(function () { synchronize(false); }, 0);
            }
        }).catch(function (error) {
            requestPending = false;
            resyncRequested = false;
            handleError(error);
        });
    }

    function renew() {
        if (requestPending || ending) {
            return;
        }
        requestPending = true;
        post('user_session_renew').then(function (payload) {
            requestPending = false;
            try { window.sessionStorage.removeItem('oneid-user-session-csrf-retried'); } catch (ignored) {}
            if (typeof sessionSwal.close === 'function') {
                sessionSwal.close();
            }
            warningOpen = false;
            schedule(payload);
            broadcast('renewed');
            var absoluteLimited = Number(payload.absolute_remaining_seconds || 0)
                <= Number(payload.idle_remaining_seconds || 0)
                && Number(payload.effective_remaining_seconds || 0) <= Number(config.warningSeconds || 120);
            sessionSwal({
                title: absoluteLimited ? config.text.absoluteLimitTitle : config.text.renewedTitle,
                text: absoluteLimited ? config.text.absoluteLimitBody : config.text.renewedBody,
                type: absoluteLimited ? 'warning' : 'success',
                confirmButtonText: config.text.ok,
                showConfirmButton: true,
                closeOnConfirm: true
            });
            window.setTimeout(markTerminalDialog, 0);
        }).catch(function (error) {
            requestPending = false;
            handleError(error);
        });
    }

    function expirePortal() {
        if (ending) {
            return;
        }
        ending = true;
        clearTimers();
        clearSensitiveInputs();
        post('user_session_expire').then(function () {
            broadcast('ended');
            terminalMessage('USER_SESSION_EXPIRED');
        }).catch(function (error) {
            broadcast('ended');
            if (error.code === 'SESSION_STATUS_UNAVAILABLE' && error.status !== 401) {
                ending = false;
                window.setTimeout(synchronize, 15000);
                return;
            }
            ending = false;
            terminalMessage(error.code || 'USER_SESSION_EXPIRED');
        });
    }

    function receive(message) {
        if (!message || !message.type) {
            return;
        }
        if (message.type === 'ended') {
            redirectToLanding();
        } else if (message.type === 'renewed') {
            if (warningOpen && typeof sessionSwal.close === 'function') {
                sessionSwal.close();
                warningOpen = false;
            }
            synchronize(true);
        }
    }

    function handleExternalError(status, code) {
        var normalizedStatus = Number(status) || 0;
        var normalizedCode = String(code || 'SESSION_STATUS_UNAVAILABLE');
        if (normalizedStatus === 401
            && normalizedCode !== 'USER_SESSION_EXPIRED'
            && normalizedCode !== 'SSO_TOKEN_REVOKED'
            && normalizedCode !== 'ACCOUNT_INACTIVE'
        ) {
            synchronize(false);
            return;
        }
        var error = new Error(config.text.requestFailed);
        error.status = normalizedStatus;
        error.code = normalizedCode;
        handleError(error);
    }

    function activityCommitted() {
        if (requestPending) {
            resyncRequested = true;
            return;
        }
        synchronize(false);
    }

    if (channel) {
        channel.onmessage = function (event) { receive(event.data); };
    }
    window.addEventListener('storage', function (event) {
        if (event.key !== 'oneid-user-portal-session' || !event.newValue) {
            return;
        }
        try { receive(JSON.parse(event.newValue)); } catch (ignored) {}
    });
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            synchronize(true);
        }
    });
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            synchronize(true);
        }
    });
    document.addEventListener('oneid:user-activity-committed', activityCommitted);

    window.OneIdUserSession = {
        revalidate: function () { synchronize(false); },
        handleExternalError: handleExternalError,
        activityCommitted: activityCommitted
    };

    synchronize(true);
})(window, document);
