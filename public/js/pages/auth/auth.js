/**
 * Sign-in code step (auth/two-steps.blade.php).
 *
 * Six one-digit boxes that take typing, arrow keys, backspace, a pasted code or the phone's
 * one-time-code autofill. When all six are filled the code is checked (POST /validateOTP).
 * Errors appear under the boxes; the form stays usable after a wrong code. "Resend code"
 * unlocks after 60 seconds, matching the server's limit of one new code a minute.
 */
(function () {
    'use strict';

    var RESEND_AFTER = 60;

    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('kt_sing_in_two_steps_form');
        if (!form) return;

        var boxes = Array.prototype.slice.call(form.querySelectorAll('.otp-input'));
        var group = form.querySelector('[data-otp-boxes]');
        var submit = document.getElementById('kt_sing_in_two_steps_submit');
        var message = document.getElementById('otp_message');
        var resend = document.getElementById('resend_otp');
        var countdown = document.getElementById('otp_countdown');
        var token = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
        var busy = false;

        function code() { return boxes.map(function (b) { return b.value; }).join(''); }

        function say(text, kind) {
            message.textContent = text || '';
            message.className = 'auth-split__message' + (kind ? ' is-' + kind : '');
        }

        function refresh() {
            boxes.forEach(function (b) { b.classList.toggle('is-filled', b.value !== ''); });
        }

        // Put digits into the boxes from a starting box; returns how many were placed.
        function fill(digits, from) {
            digits = String(digits).replace(/\D/g, '');
            var placed = 0;
            for (var i = from; i < boxes.length && placed < digits.length; i++, placed++) {
                boxes[i].value = digits[placed];
            }
            refresh();
            return placed;
        }

        function focusNextEmpty(from) {
            for (var i = from; i < boxes.length; i++) {
                if (!boxes[i].value) { boxes[i].focus(); return; }
            }
            boxes[boxes.length - 1].focus();
        }

        function clearBoxes() {
            boxes.forEach(function (b) { b.value = ''; });
            refresh();
            boxes[0].focus();
        }

        function maybeSubmit() {
            if (code().length === boxes.length && !busy) verify();
        }

        boxes.forEach(function (box, index) {
            box.addEventListener('input', function () {
                group.classList.remove('is-error');
                var digits = box.value.replace(/\D/g, '');
                box.value = '';
                if (digits.length) {
                    // One digit typed, or several from autofill: spread them over the boxes.
                    var placed = fill(digits, digits.length > 1 ? 0 : index);
                    focusNextEmpty(digits.length > 1 ? Math.min(placed, boxes.length - 1) : index + 1);
                } else {
                    refresh();
                }
                maybeSubmit();
            });

            box.addEventListener('keydown', function (e) {
                if (e.key === 'Backspace' && !box.value && index > 0) {
                    e.preventDefault();
                    boxes[index - 1].value = '';
                    boxes[index - 1].focus();
                    refresh();
                } else if (e.key === 'ArrowLeft' && index > 0) {
                    e.preventDefault(); boxes[index - 1].focus();
                } else if (e.key === 'ArrowRight' && index < boxes.length - 1) {
                    e.preventDefault(); boxes[index + 1].focus();
                }
            });

            box.addEventListener('focus', function () { box.select(); });

            box.addEventListener('paste', function (e) {
                e.preventDefault();
                var text = (e.clipboardData || window.clipboardData).getData('text');
                var digits = text.replace(/\D/g, '');
                if (!digits) return;
                group.classList.remove('is-error');
                boxes.forEach(function (b) { b.value = ''; });
                var placed = fill(digits, 0);
                focusNextEmpty(Math.min(placed, boxes.length - 1));
                maybeSubmit();
            });
        });

        // ── Check the code ───────────────────────────────────────────────
        function setBusy(on) {
            busy = on;
            submit.disabled = on;
            if (on) submit.setAttribute('data-kt-indicator', 'on'); else submit.removeAttribute('data-kt-indicator');
        }

        function verify() {
            if (code().length !== boxes.length) {
                say('Enter all 6 digits of the code.', 'error');
                group.classList.add('is-error');
                focusNextEmpty(0);
                return;
            }
            setBusy(true);
            say('');

            var values = {};
            boxes.forEach(function (b) { values[b.name] = b.value; });

            $.ajax({ url: form.dataset.verifyUrl, type: 'POST', data: { _token: token, values: values } })
                .done(function (response) {
                    if (response && response.success === true) {
                        say('Code accepted. Signing you in...', 'success');
                        window.location.href = form.dataset.successUrl;
                        return;
                    }
                    if (response && response.status === 429) {
                        // Too many wrong codes: the account is suspended and the sign-in ended.
                        failed(response.message, true);
                        return;
                    }
                    failed((response && response.message) || "That code isn't right. Please try again.");
                })
                .fail(function (xhr) {
                    var text = xhr.responseJSON && xhr.responseJSON.message;
                    if (xhr.status === 419) {
                        failed(text || 'Your sign-in has expired. Please sign in again.', true);
                    } else {
                        failed(text || 'Something went wrong. Check your connection and try again.');
                    }
                });
        }

        function failed(text, backToLogin) {
            setBusy(false);
            say(text, 'error');
            group.classList.add('is-error');
            if (backToLogin) {
                boxes.forEach(function (b) { b.disabled = true; });
                submit.disabled = true;
                resend.disabled = true;
                countdown.innerHTML = '<a class="link-primary fw-bolder" href="' + form.dataset.loginUrl + '">Back to sign in</a>';
                return;
            }
            clearBoxes();
        }

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!busy) verify();
        });

        // ── Resend ───────────────────────────────────────────────────────
        var timer = null;

        function startCountdown(seconds) {
            clearInterval(timer);
            resend.disabled = true;
            var left = seconds;
            countdown.textContent = "Didn't get it? You can ask for a new code in " + left + 's.';
            timer = setInterval(function () {
                left--;
                if (left <= 0) {
                    clearInterval(timer);
                    countdown.textContent = "Didn't get it?";
                    resend.disabled = false;
                } else {
                    countdown.textContent = "Didn't get it? You can ask for a new code in " + left + 's.';
                }
            }, 1000);
        }

        resend.addEventListener('click', function () {
            if (resend.disabled) return;
            resend.setAttribute('data-kt-indicator', 'on');
            resend.disabled = true;

            $.ajax({ url: form.dataset.resendUrl, type: 'POST', data: { _token: token } })
                .done(function (response) {
                    say((response && response.message) || 'A new code is on its way.', 'success');
                    group.classList.remove('is-error');
                    clearBoxes();
                    startCountdown(RESEND_AFTER);
                })
                .fail(function (xhr) {
                    var text = (xhr.responseJSON && xhr.responseJSON.message) || 'We could not send a new code. Please try again.';
                    say(text, 'error');
                    var wait = /(\d+) seconds/.exec(text);
                    if (xhr.status === 419) {
                        failed(text, true);
                    } else {
                        startCountdown(wait ? parseInt(wait[1], 10) : 10);
                    }
                })
                .always(function () { resend.removeAttribute('data-kt-indicator'); });
        });

        startCountdown(RESEND_AFTER);
        refresh();
        setTimeout(function () { boxes[0].focus(); }, 50);
    });
})();
