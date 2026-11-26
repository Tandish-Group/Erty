"use strict";
var KTPasswordResetGeneral = function() {
    var t, e, i;
    return {
        init: function() {
            t = document.querySelector("#kt_password_reset_form"),
            e = document.querySelector("#kt_password_reset_submit"),
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
                    }
                },
                plugins: {
                    trigger: new FormValidation.plugins.Trigger,
                    bootstrap: new FormValidation.plugins.Bootstrap5({
                        rowSelector: ".fv-row",
                        eleInvalidClass: "",
                        eleValidClass: ""
                    })
                }
            }),
            e.addEventListener("click", (function(o) {
                o.preventDefault(),
                i.validate().then((function(i) {
                    "Valid" == i ? (e.setAttribute("data-kt-indicator", "on"),
                    e.disabled = !0,
                    setTimeout((function() {
                        Swal.fire({
                            text: "Authenticating Email!",
                            icon: "info",
                            buttonsStyling: !1,
                            confirmButtonText: "Ok, got it!",
                            customClass: {
                                confirmButton: "btn btn-primary"
                            }
                        }).then((function(e) {

                            let values = $('#kt_password_reset_form').serializeArray().reduce((map, input) => {
                                let value;
                                if (map.hasOwnProperty(input.name)) {
                                    value = Array.isArray(map[input.name]) ?
                                        map[input.name] : [map[input.name]];
                                    value.push(input.value);
                                } else {
                                    value = input.value;
                                }
                                map[input.name] = value;
                                return map;
                            }, {});

                            var token = $('meta[name="csrf-token"]').attr('content');

                            var email = values.email;

                            $.ajax({
                                url: '/password/email',
                                type: 'POST',
                                data: { '_token' : token, email},
                                dataType: 'json',
                                success: function(response, status, xhr) {
                                    if (response.message) {
                                        Swal.fire({
                                            text: response.message,
                                            icon: "success",
                                            buttonsStyling: !1,
                                            confirmButtonText: "Ok, got it!",
                                            customClass: {
                                                confirmButton: "btn btn-light"
                                            }
                                        }).then((function() {
                                            var button = document.querySelector("#kt_password_reset_submit");
                                            button.removeAttribute("data-kt-indicator");
                                            button.disabled = false;
                                            t.reset();
                                            KTUtil.scrollTop();
                                        }));
                                    } 
                                },
                                error: function(response, xhr, status, error) {
                                    Swal.fire({
                                        text: "Sorry, "+response.responseJSON.message,
                                        icon: "error",
                                        buttonsStyling: !1,
                                        confirmButtonText: "Ok, got it!",
                                        customClass: {
                                            confirmButton: "btn btn-light"
                                        }
                                    }).then((function() {
                                        KTUtil.scrollTop()
                                    }));
                                }
                            });
                        }
                        ));
                        
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
            ))
        }
    }
}();
KTUtil.onDOMContentLoaded((function() {
    KTPasswordResetGeneral.init()
}
));
