"use strict";
var KTSigninGeneral = function() {
    var t, e, i;
    return {
        init: function() {
            t = document.querySelector("#kt_sign_in_form"),
            e = document.querySelector("#kt_sign_in_submit"),
            i = FormValidation.formValidation(t, {
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
                            }
                        }
                    }
                },
                plugins: {
                    trigger: new FormValidation.plugins.Trigger,
                    bootstrap: new FormValidation.plugins.Bootstrap5({
                        rowSelector: ".fv-row"
                    })
                }
            }),
            e.addEventListener("click", (function(n) {
                n.preventDefault(),
                i.validate().then((function(i) {
                    "Valid" == i ? (e.setAttribute("data-kt-indicator", "on"),
                    e.disabled = !0,
                    setTimeout((function() {
                        $('#kt_sign_in_form').submit();
                    }
                    ), 2e3)) : Swal.fire({
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
            ))
        }
    }
}();
KTUtil.onDOMContentLoaded((function() {
    KTSigninGeneral.init()
}
));
