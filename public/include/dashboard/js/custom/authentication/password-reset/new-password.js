"use strict";
var KTPasswordResetNewPassword = function() {
    var e, t, r, o, s = function() {
        return 100 === o.getScore()
    };
    return {
        init: function() {
            e = document.querySelector("#kt_new_password_form"),
            t = document.querySelector("#kt_new_password_submit"),
            o = KTPasswordMeter.getInstance(e.querySelector('[data-kt-password-meter="true"]')),
            r = FormValidation.formValidation(e, {
                fields: {
                	email: {
                        validators: {
                            notEmpty: {
                                message: "Email address is required"
                            },
                            emailAddress: {
                                message: "The value is not a valid email address"
                            },
                            regexp: {
                                regexp: '^[^@\\s]+@[^@\\s]+\\.[^@\\s]+$',
                                message: 'Email must be properly formatted (example@domain.com)'
                            },
                            stringLength: {
                                max: 254,
                                message: 'Email must not exceed 254 characters'
                            },
                            callback: {
                                message: 'Invalid email domain',
                                callback: function(value) {
                                    // Validate email domain
                                    if (value.value) {
                                        const domain = value.value.split('@')[1];
                                        // List of common disposable email domains to block
                                        const blockedDomains = [
                                            // Temporary Email Services
                                            'tempmail.com',
                                            'throwawaymail.com',
                                            '10minutemail.com',
                                            'mailinator.com',
                                            'guerrillamail.com',
                                            'sharklasers.com',
                                            'spam4.me',
                                            'yopmail.com',
                                            'tempmail.net',
                                            'maildrop.cc',
                                            
                                            // Disposable Email Domains
                                            'dispostable.com',
                                            'dropmail.me',
                                            'tempr.email',
                                            'temp-mail.org',
                                            'fakeinbox.com',
                                            'tempinbox.com',
                                            'emailondeck.com',
                                            'temp-mail.io',
                                            'tempmailaddress.com',
                                            
                                            // Common Variants
                                            'trash-mail.com',
                                            'spamgourmet.com',
                                            'jetable.org',
                                            'nospam.ze.tc',
                                            'nomail.xl.cx',
                                            'dodgit.com',
                                            'spamfree24.org',
                                            'disposableemailaddresses.com',
                                            'tempmail.ninja',
                                            'deadfake.com',
                                            
                                            // Additional Services
                                            'wegwerfemail.de',
                                            'trashmail.net',
                                            'emailtemp.org',
                                            'tempsky.com',
                                            'mohmal.com',
                                            'crazymailing.com',
                                            'eyepaste.com',
                                            'mintemail.com',
                                            'mytemp.email',
                                            'tempemail.co',
                                            
                                            // Known Variants
                                            '10minutesmail.com',
                                            'disposable-email.com',
                                            'mailnesia.com',
                                            'tempmailgen.com',
                                            'mailcatch.com',
                                            'tempmail.dev',
                                            'burnermail.io',
                                            'temporary-mail.net',
                                            'emailondeck.com',
                                            'guerrillamail.info'
                                        ];
                                        return !blockedDomains.includes(domain);
                                    }
                                    return true;
                                }
                            }
                        }
                    },
                    password: {
                        validators: {
                            notEmpty: {
                                message: "The password is required"
                            },
                            callback: {
                                message: "Please enter valid password",
                                callback: function(e) {
                                    if (e.value.length > 0) {
                                        if (r()) {
                                            return true; // If password score is 100, return true.
                                        } else {
                                            // Update the message to be more descriptive based on the password score.
                                            return {
                                                valid: false,
                                                message: "Password must have a strength score of 100. Include a mix of letters, numbers, and symbols."
                                            };
                                        }
                                    }
                                }
                            }
                        }
                    },
                    "password_confirmation": {
                        validators: {
                            notEmpty: {
                                message: "The password confirmation is required"
                            },
                            identical: {
                                compare: function() {
                                    return e.querySelector('[name="password"]').value
                                },
                                message: "The password and its confirm are not the same"
                            }
                        }
                    }
                },
                plugins: {
                    trigger: new FormValidation.plugins.Trigger({
                        event: {
                            password: !1
                        }
                    }),
                    bootstrap: new FormValidation.plugins.Bootstrap5({
                        rowSelector: ".fv-row",
                        eleInvalidClass: "",
                        eleValidClass: ""
                    })
                }
            }),
            t.addEventListener("click", (function(s) {
                s.preventDefault(),
                r.revalidateField("password"),
                r.validate().then((function(r) {
                    "Valid" == r ? (t.setAttribute("data-kt-indicator", "on"),
                    t.disabled = !0,
                    setTimeout((function() {
                        t.removeAttribute("data-kt-indicator"),
                        t.disabled = !1,
                        Swal.fire({
                            text: "You have successfully reset your password!",
                            icon: "success",
                            buttonsStyling: !1,
                            confirmButtonText: "Ok, got it!",
                            customClass: {
                                confirmButton: "btn btn-primary"
                            }
                        }).then((function(t) {
                            $('#kt_new_password_form').submit();
                        }
                        ))
                    }
                    ), 1500)) : Swal.fire({
                        text: "Sorry, looks like there are some errors detected, please try again.",
                        icon: "error",
                        buttonsStyling: !1,
                        confirmButtonText: "Ok, got it!",
                        customClass: {
                            confirmButton: "btn btn-primary"
                        }
                    })
                }
                ))
            }
            )),
            e.querySelector('input[name="password"]').addEventListener("input", (function() {
                this.value.length > 0 && r.updateFieldStatus("password", "NotValidated");
                // Update the password score display
                var scoreDisplay = document.getElementById("passwordScoreDisplay");
                var score = o.getScore(); // Assuming getScore() returns the current score
                console.log('score', score);
                scoreDisplay.textContent = "Password Strength Score: " + score;
            }
            ))
        }
    }
}();
KTUtil.onDOMContentLoaded((function() {
    KTPasswordResetNewPassword.init()
}
));
