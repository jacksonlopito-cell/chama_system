/**
 * Advanced Chama Management System - Main JavaScript
 */

(function($) {
    'use strict';

    // ===== Preloader =====
    $(window).on('load', function() {
        $('.preloader').fadeOut(500);
    });

    // ===== Dark Mode =====
    function initDarkMode() {
        const saved = localStorage.getItem('darkMode');
        const toggles = $('#darkModeToggle, #darkModeToggleTop');

        if (saved === 'true') {
            $('html').attr('data-theme', 'dark');
            $('body').addClass('dark-mode');
            toggles.find('i').removeClass('fa-moon').addClass('fa-sun');
        }

        toggles.on('click', function() {
            const isDark = $('html').attr('data-theme') === 'dark';
            if (isDark) {
                $('html').removeAttr('data-theme');
                $('body').removeClass('dark-mode');
                localStorage.setItem('darkMode', 'false');
                $('#darkModeToggle i, #darkModeToggleTop i').removeClass('fa-sun').addClass('fa-moon');
            } else {
                $('html').attr('data-theme', 'dark');
                $('body').addClass('dark-mode');
                localStorage.setItem('darkMode', 'true');
                $('#darkModeToggle i, #darkModeToggleTop i').removeClass('fa-moon').addClass('fa-sun');
            }
        });
    }

    // ===== Sidebar =====
    function initSidebar() {
        // Toggle sidebar (desktop only — mobile has its own handler)
        $('.sidebar-toggle').not('#mobileSidebarToggle').on('click', function() {
            $('.sidebar').toggleClass('collapsed');
            $(window).trigger('resize');
        });

        // Mobile sidebar toggle
        $('#mobileSidebarToggle').on('click', function() {
            $('.sidebar').toggleClass('show');
            $('.sidebar-overlay').toggleClass('show');
        });

        // Close sidebar on overlay click
        $(document).on('click', '.sidebar-overlay', function() {
            $('.sidebar').removeClass('show');
            $('.sidebar-overlay').removeClass('show');
        });

        // Submenu toggle
        $('.menu-item.has-submenu').on('click', function(e) {
            e.preventDefault();
            const submenu = $(this).next('.submenu');
            const arrow = $(this).find('.menu-arrow');

            submenu.toggleClass('open');
            arrow.toggleClass('open');
        });

        // Active menu item
        const currentPath = window.location.pathname;
        $('.sidebar-menu .menu-item').each(function() {
            const link = $(this).attr('href');
            if (link && currentPath.includes(link)) {
                $(this).addClass('active');
                // Open parent submenu if any
                $(this).closest('.submenu').addClass('open');
                $(this).closest('.submenu').prev('.menu-item').find('.menu-arrow').addClass('open');
            }
        });
    }

    // ===== Fullscreen Toggle =====
    function initFullscreen() {
        $('#fullscreenToggle').on('click', function() {
            var $icon = $(this).find('i');
            if (!document.fullscreenElement && !document.webkitFullscreenElement) {
                var el = document.documentElement;
                if (el.requestFullscreen) {
                    el.requestFullscreen();
                } else if (el.webkitRequestFullscreen) {
                    el.webkitRequestFullscreen();
                }
                $icon.removeClass('fa-expand').addClass('fa-compress');
            } else {
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                } else if (document.webkitExitFullscreen) {
                    document.webkitExitFullscreen();
                }
                $icon.removeClass('fa-compress').addClass('fa-expand');
            }
        });
        // Sync icon when user exits fullscreen via Esc
        $(document).on('fullscreenchange webkitfullscreenchange', function() {
            if (!document.fullscreenElement && !document.webkitFullscreenElement) {
                $('#fullscreenToggle i').removeClass('fa-compress').addClass('fa-expand');
            }
        });
    }

    // ===== Password Visibility Toggle =====
    function initPasswordToggle() {
        $('.password-toggle').on('click', function() {
            const input = $(this).closest('.input-group').find('input');
            const icon = $(this).find('i');

            if (input.attr('type') === 'password') {
                input.attr('type', 'text');
                icon.removeClass('fa-eye').addClass('fa-eye-slash');
            } else {
                input.attr('type', 'password');
                icon.removeClass('fa-eye-slash').addClass('fa-eye');
            }
        });
    }

    // ===== Animated Counter =====
    function initCounters() {
        $('.counter-number').each(function() {
            const $this = $(this);
            const target = parseInt($this.data('target'));
            const duration = parseInt($this.data('duration')) || 2000;
            const step = target / (duration / 16);
            let current = 0;

            const updateCounter = () => {
                current += step;
                if (current < target) {
                    $this.text(Math.ceil(current).toLocaleString());
                    requestAnimationFrame(updateCounter);
                } else {
                    $this.text(target.toLocaleString());
                }
            };

            // Start when visible
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        updateCounter();
                        observer.unobserve(entry.target);
                    }
                });
            });

            observer.observe(this);
        });
    }

    // ===== Scroll Animations =====
    function initScrollAnimations() {
        const animateElements = document.querySelectorAll('.animate-on-scroll');

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('slide-up');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1 });

        animateElements.forEach(el => observer.observe(el));
    }

    // ===== Smooth Scroll =====
    function initSmoothScroll() {
        $('a[href*="#"]:not([href="#"])').on('click', function() {
            const target = $(this.hash);
            if (target.length) {
                $('html, body').animate({
                    scrollTop: target.offset().top - 80
                }, 800);
                return false;
            }
        });
    }

    // ===== Navbar Scroll Effect =====
    function initNavbarScroll() {
        $(window).on('scroll', function() {
            const scroll = $(this).scrollTop();
            if (scroll > 100) {
                $('.landing-navbar').addClass('scrolled');
                $('#scrollTop').addClass('show');
            } else {
                $('.landing-navbar').removeClass('scrolled');
                $('#scrollTop').removeClass('show');
            }
        });
    }

    // ===== Scroll to Top =====
    function initScrollTop() {
        $('#scrollTop').on('click', function() {
            $('html, body').animate({ scrollTop: 0 }, 500);
        });
    }

    // ===== Flash Messages Auto-hide =====
    function initFlashMessages() {
        $('.alert-dismissible').each(function() {
            const $alert = $(this);
            setTimeout(function() {
                $alert.fadeOut(500, function() {
                    $(this).remove();
                });
            }, 5000);
        });
    }

    // ===== Form Validation =====
    function initFormValidation() {
        $('form[data-validate]').on('submit', function(e) {
            const form = $(this);
            let valid = true;

            form.find('[required]').each(function() {
                const input = $(this);
                const value = input.val()?.trim();

                if (!value) {
                    input.addClass('is-invalid');
                    const feedback = input.next('.invalid-feedback');
                    if (feedback.length) {
                        feedback.text('This field is required');
                    }
                    valid = false;
                } else {
                    input.removeClass('is-invalid');

                    // Email validation
                    if (input.attr('type') === 'email' && !isValidEmail(value)) {
                        input.addClass('is-invalid');
                        const feedback = input.next('.invalid-feedback');
                        if (feedback.length) {
                            feedback.text('Please enter a valid email');
                        }
                        valid = false;
                    }
                }
            });

            return valid;
        });

        // Clear validation on input
        $('form[data-validate] input').on('input', function() {
            $(this).removeClass('is-invalid');
        });
    }

    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    // ===== AJAX Form Handler =====
    function initAjaxForms() {
        $('form[data-ajax]').on('submit', function(e) {
            e.preventDefault();

            const form = $(this);
            const url = form.attr('action');
            const method = form.attr('method') || 'POST';
            const submitBtn = form.find('[type="submit"]');
            const originalText = submitBtn.html();

            // Show loading
            submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Processing...');

            // Clear previous errors
            form.find('.is-invalid').removeClass('is-invalid');
            form.find('.error-message').remove();

            $.ajax({
                url: url,
                method: method,
                data: form.serialize(),
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        if (response.message) {
                            showToast('success', response.message);
                        }
                        if (response.redirect) {
                            setTimeout(function() {
                                window.location.href = response.redirect;
                            }, 1000);
                        } else if (response.reload) {
                            location.reload();
                        } else if (response.reset) {
                            form[0].reset();
                        }
                    } else {
                        if (response.errors) {
                            $.each(response.errors, function(field, message) {
                                const input = form.find('[name="' + field + '"]');
                                input.addClass('is-invalid');
                                const feedback = input.next('.invalid-feedback');
                                if (feedback.length) {
                                    feedback.text(message);
                                } else {
                                    input.after('<div class="invalid-feedback error-message">' + message + '</div>');
                                }
                            });
                        }
                        if (response.message) {
                            showToast('error', response.message);
                        }
                    }
                },
                error: function(xhr) {
                    let msg = 'An error occurred. Please try again.';
                    if (xhr.responseJSON?.message) {
                        msg = xhr.responseJSON.message;
                    }
                    showToast('error', msg);
                },
                complete: function() {
                    submitBtn.prop('disabled', false).html(originalText);
                }
            });
        });
    }

    // ===== Toast Notifications =====
    function showToast(type, message) {
        const icons = {
            success: 'fa-check-circle',
            error: 'fa-exclamation-circle',
            warning: 'fa-exclamation-triangle',
            info: 'fa-info-circle'
        };

        const colors = {
            success: '#12b76a',
            error: '#f04438',
            warning: '#f79009',
            info: '#0ba5ec'
        };

        const toast = `
            <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 999999">
                <div class="toast align-items-center border-0 show" role="alert" style="background: #fff; box-shadow: 0 8px 25px rgba(0,0,0,0.15); border-left: 4px solid ${colors[type] || colors.info};">
                    <div class="d-flex">
                        <div class="toast-body d-flex align-items-center">
                            <i class="fas ${icons[type] || icons.info}" style="color: ${colors[type] || colors.info}; font-size: 1.25rem; margin-right: 0.75rem;"></i>
                            <span style="font-size: 0.875rem;">${message}</span>
                        </div>
                        <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                </div>
            </div>
        `;

        $('body').append(toast);
        setTimeout(function() {
            $('.toast-container').fadeOut(300, function() {
                $(this).remove();
            });
        }, 4000);
    }

    // ===== DataTables Init (delegated to dtHelper) =====
    function initDataTables() {
        if (window.dtHelper) {
            dtHelper.initAll();
        }
    }

    // ===== Location Cascading Dropdowns =====
    var locationCache = { subCounties: {}, wards: {} };
    var cascadeBusy = false;

    function loadSubCounties(countyId, $sub) {
        if (locationCache.subCounties[countyId]) {
            renderSubCounties(locationCache.subCounties[countyId], $sub);
            return;
        }
        if (typeof LOCATION_DATA !== 'undefined' && LOCATION_DATA.subCounties[countyId]) {
            var data = LOCATION_DATA.subCounties[countyId];
            locationCache.subCounties[countyId] = data;
            renderSubCounties(data, $sub);
            return;
        }
        $.get(BASE_URL + 'ajax/get-sub-counties.php', { county_id: countyId })
            .done(function(data) {
                locationCache.subCounties[countyId] = data;
                renderSubCounties(data, $sub);
            })
            .fail(function() {
                $sub.empty().append('<option value="">Error loading. Retry?</option>').prop('disabled', false);
                $sub.trigger('change');
            });
    }

    function renderSubCounties(data, $sub) {
        $sub.empty().append('<option value="">Select Sub County</option>');
        $.each(data, function(i, item) {
            $sub.append($('<option>', { value: item.id, text: item.name }));
        });
        $sub.prop('disabled', false);
        $sub.trigger('change');
    }

    function loadWards(subCountyId, $ward) {
        if (locationCache.wards[subCountyId]) {
            renderWards(locationCache.wards[subCountyId], $ward);
            return;
        }
        if (typeof LOCATION_DATA !== 'undefined' && LOCATION_DATA.wards[subCountyId]) {
            var data = LOCATION_DATA.wards[subCountyId];
            locationCache.wards[subCountyId] = data;
            renderWards(data, $ward);
            return;
        }
        $.get(BASE_URL + 'ajax/get-wards.php', { sub_county_id: subCountyId })
            .done(function(data) {
                locationCache.wards[subCountyId] = data;
                renderWards(data, $ward);
            })
            .fail(function() {
                $ward.empty().append('<option value="">Error loading. Retry?</option>').prop('disabled', false);
                $ward.trigger('change');
            });
    }

    function refreshSelect2($el) {
        if ($el.data('select2')) {
            $el.select2('destroy');
        }
        var opts = {
            theme: 'bootstrap-5',
            width: '100%',
            placeholder: $el.data('placeholder') || 'Select option',
            allowClear: true,
            minimumResultsForSearch: 0
        };
        $el.select2(opts);
    }

    function renderWards(data, $ward) {
        $ward.empty();
        if (data && data.length) {
            $ward.append('<option value="">Select Ward</option>');
            $.each(data, function(i, item) {
                $ward.append($('<option>', { value: item.id, text: item.name }));
            });
            $ward.prop('disabled', false);
        } else {
            $ward.append('<option value="">No wards available for this sub-county</option>');
            $ward.prop('disabled', true);
        }
        refreshSelect2($ward);
    }

    function triggerCountyCascade($county) {
        var countyId = $county.val();
        var $form = $county.closest('form');
        if (!$form.length) return;
        var $sub = $form.find('select[name="sub_county_id"]');
        var $ward = $form.find('select[name="ward_id"]');
        $sub.empty().append('<option value="">Loading...</option>').prop('disabled', true);
        $ward.empty().append('<option value="">Select Ward</option>').prop('disabled', true);
        refreshSelect2($ward);
        if (countyId) {
            loadSubCounties(countyId, $sub);
        } else {
            $sub.empty().append('<option value="">Select Sub County</option>').prop('disabled', false);
            $sub.trigger('change');
        }
    }

    function triggerSubCountyCascade($sub) {
        if (cascadeBusy) return;
        cascadeBusy = true;
        var subCountyId = $sub.val();
        var $ward = $sub.closest('form').find('select[name="ward_id"]');
        $ward.empty().append('<option value="">Loading...</option>').prop('disabled', true);
        if (subCountyId) {
            loadWards(subCountyId, $ward);
        } else {
            $ward.empty().append('<option value="">Select Ward</option>').prop('disabled', false);
            $ward.trigger('change');
        }
        cascadeBusy = false;
    }

    function initLocationDropdowns() {
        $(document).on('change', 'select[name="county_id"]', function() {
            triggerCountyCascade($(this));
        });
        $(document).on('change', 'select[name="sub_county_id"]', function() {
            triggerSubCountyCascade($(this));
        });
    }

    // ===== Select2 Initialization =====
    function initSelect2() {
        if (typeof $.fn.select2 === 'undefined') return;
        var select2Defaults = {
            theme: 'bootstrap-5',
            width: '100%',
            placeholder: function() { return $(this).data('placeholder') || 'Select option'; },
            allowClear: true
        };
        $('select.searchable-select').each(function() {
            var $el = $(this);
            var opts = $.extend({}, select2Defaults, {
                placeholder: $el.data('placeholder') || 'Search...',
                minimumResultsForSearch: 0
            });
            if ($el.data('ajax') || $el.hasClass('ajax-select')) {
                var field = $el.data('field');
                opts.minimumInputLength = 1;
                opts.ajax = {
                    url: BASE_URL + 'ajax/suggest-field.php',
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return { field: field, q: params.term };
                    },
                    processResults: function(data) {
                        var results = $.map(data, function(item) {
                            return { id: item, text: item };
                        });
                        return { results: results };
                    }
                };
            }
            $el.select2(opts);
        });
        // Enable search on any select with select2-enable class
        $('select.select2-enable').select2($.extend({}, select2Defaults, { placeholder: 'Search...' }));
    }

    // ===== Auto-suggest for text inputs (occupation, employer, etc.) =====
    function initAutoSuggest() {
        $('input[data-suggest]').each(function() {
            var $input = $(this);
            var field = $input.data('suggest');
            var $datalist = $('<datalist>', { id: 'suggest-' + field });
            $('body').append($datalist);
            $input.attr('list', 'suggest-' + field);
            var timer;
            $input.on('input', function() {
                clearTimeout(timer);
                var q = $(this).val();
                if (q.length < 1) return;
                timer = setTimeout(function() {
                    $.get(BASE_URL + 'ajax/suggest-field.php', { field: field, q: q }, function(data) {
                        $datalist.empty();
                        $.each(data, function(i, v) {
                            $datalist.append($('<option>', { value: v }));
                        });
                    });
                }, 300);
            });
        });
    }

    // ===== Select2 re-init on modal show (for modals in DOM) =====
    $(document).on('shown.bs.modal', function() {
        $(this).find('select.searchable-select').each(function() {
            var $el = $(this);
            if (!$el.data('select2')) {
                var field = $el.data('field');
                var opts = {
                    theme: 'bootstrap-5',
                    width: '100%',
                    placeholder: $el.data('placeholder') || 'Search...',
                    allowClear: true,
                    minimumResultsForSearch: 0
                };
                if (field) {
                    opts.minimumInputLength = 1;
                    opts.ajax = {
                        url: BASE_URL + 'ajax/suggest-field.php',
                        dataType: 'json',
                        delay: 250,
                        data: function(params) {
                            return { field: field, q: params.term };
                        },
                        processResults: function(data) {
                            var results = $.map(data, function(v) { return { id: v, text: v }; });
                            return { results: results };
                        }
                    };
                }
                $el.select2(opts);
                // Fix z-index within modal
                $el.on('select2:open', function() {
                    document.querySelector('.select2-container').style.zIndex = '99999';
                });
            }
        });
    });

    // ===== Initialize All =====
    $(document).ready(function() {
        initDarkMode();
        initSidebar();
        initFullscreen();
        initPasswordToggle();
        initCounters();
        initScrollAnimations();
        initSmoothScroll();
        initNavbarScroll();
        initScrollTop();
        initFlashMessages();
        initFormValidation();
        initAjaxForms();
        initDataTables();
        initLocationDropdowns();
        initSelect2();
        initAutoSuggest();
    });

    // Fallback: hide preloader after 5s if window.load never fires
    $(function() {
        setTimeout(function() {
            $('.preloader').fadeOut(500);
        }, 5000);
    });

    // Expose showToast globally
    window.showToast = showToast;

})(jQuery);
