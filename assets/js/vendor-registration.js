/**
 * Vendor Registration Form - Complete Implementation
 * Handles 3-step registration: Basic Info → Coverage Selection → Payment
 */

(function ($) {
    'use strict';

    // Registration data object
    let registrationData = {
        basic_info: {},
        coverage: {
            states: [],
            cities: []
        },
        total_amount: 0
    };

    // Configuration from WordPress
    const config = window.vendor_reg_vars || {};

    /**
     * Initialize on DOM ready
     */
    $(document).ready(function () {
        initializeStepNavigation();
        loadCoverageAreas();
        initializePasswordToggle();
    });

    /**
     * Initialize password visibility toggle
     */
    function initializePasswordToggle() {
        $('#toggle-vreg-password').on('click', function () {
            const passwordInput = $('#vreg-password');
            const currentType = passwordInput.attr('type');
            const $button = $(this);

            if (currentType === 'password') {
                // Show password - use eye-off icon
                passwordInput.attr('type', 'text');
                $button.html(`
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="eye-icon">
                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                        <line x1="1" y1="1" x2="23" y2="23"></line>
                    </svg>
                `);
                $button.attr('aria-label', 'Hide password');
            } else {
                // Hide password - use eye icon
                passwordInput.attr('type', 'password');
                $button.html(`
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="eye-icon">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                `);
                $button.attr('aria-label', 'Show password');
            }
        });
    }

    /**
     * Step Navigation Initialization
     */
    function initializeStepNavigation() {
        // Step 1: Basic Info → Registration & Halt
        $('#vreg-step1-next').on('click', async function () {
            if (await validateStep1()) {
                saveStep1Data();
                await submitInitialRegistration();
            }
        });

        // Step 2: Previous
        $('#vreg-step2-prev').on('click', function () {
            showStep(1);
        });

        // Step 2: Coverage → Summary
        $('#vreg-step2-next').on('click', function () {
            if (validateStep2()) {
                saveStep2Data();
                showStep(3);
                renderSummary();
            }
        });

        // Step 3: Previous
        $('#vreg-step3-prev').on('click', function () {
            showStep(2);
        });

        // Step 3: Payment
        $('#vreg-pay-btn').on('click', function () {
            initiatePayment();
        });
    }

    /**
     * Show specific step
     */
    function showStep(stepNumber) {
        $('.vreg-step').hide();
        $('#vreg-step-' + stepNumber).show();
    }

    /**
     * Validate Step 1 (Basic Info) - Async to check email
     */
    async function validateStep1() {
        const name = $('#vreg-name').val().trim();
        const company = $('#vreg-company').val().trim();
        const email = $('#vreg-email').val().trim();
        const phone = $('#vreg-phone').val().trim();
        const password = $('#vreg-password').val().trim();

        if (!name || !company || !email || !phone || !password) {
            showFeedback('Please fill all required fields', 'error');
            return false;
        }

        // Basic email validation
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            showFeedback('Please enter a valid email address', 'error');
            return false;
        }

        // Check if email already exists
        try {
            showFeedback('Checking email availability...', 'info');

            const emailCheck = await $.ajax({
                url: config.ajax_url,
                type: 'POST',
                data: {
                    action: 'check_email_exists',
                    email: email
                }
            });

            if (emailCheck.success && emailCheck.data.exists) {
                showFeedback('This email is already registered. Please use a different email or login.', 'error');
                return false;
            } else if (emailCheck.success && !emailCheck.data.exists) {
                showFeedback('Email is available!', 'success');
            }
        } catch (error) {
            // console.error('Email check error:', error);
            showFeedback('Unable to verify email. Please check your connection and try again.', 'error');
            return false; // Block progression on network failure
        }

        // Basic phone validation (10 digits)
        const phoneRegex = /^\d{10}$/;
        if (!phoneRegex.test(phone.replace(/\D/g, ''))) {
            showFeedback('Please enter a valid 10-digit phone number', 'error');
            return false;
        }

        // Password length
        if (password.length < 6) {
            showFeedback('Password must be at least 6 characters', 'error');
            return false;
        }

        return true;
    }

    /**
     * Save Step 1 Data
     */
    function saveStep1Data() {
        registrationData.basic_info = {
            full_name: $('#vreg-name').val().trim(),
            company_name: $('#vreg-company').val().trim(),
            email: $('#vreg-email').val().trim(),
            phone: $('#vreg-phone').val().trim(),
            password: $('#vreg-password').val().trim()
        };
    }

    /**
     * Load Coverage Areas (States/Cities)
     */
    function loadCoverageAreas() {
        $.ajax({
            url: config.ajax_url,
            type: 'POST',
            data: {
                action: 'get_coverage_areas'
            },
            beforeSend: function () {
                $('#coverage-selection-loader').show();
                $('#coverage-selection-ui').hide();
            },
            success: function (response) {
                if (response.success && response.data) {
                    renderCoverageUI(response.data);
                } else {
                    showFeedback('Failed to load coverage areas', 'error');
                }
            },
            error: function () {
                showFeedback('Error loading coverage areas', 'error');
            },
            complete: function () {
                $('#coverage-selection-loader').hide();
            }
        });
    }

    /**
     * Render Coverage Area UI
     */
    function renderCoverageUI(data) {
        // Data is array of {state: "...", districts: [...]}
        let html = '<div class="coverage-container">';
        html += '<div class="coverage-section">';
        html += '<h3>Select States (₹' + config.per_state_fee + ' per state)</h3>';

        // Multi-select dropdown for states
        html += '<select id="state-select" multiple size="10" class="state-multiselect">';
        data.forEach(function (stateObj) {
            html += '<option value="' + stateObj.state + '">' + stateObj.state + '</option>';
        });
        html += '</select>';
        html += '<p class="help-text">Hold Ctrl (Windows) or Cmd (Mac) to select multiple states</p>';
        html += '</div></div>';

        html += '<div class="coverage-section" id="cities-container" style="display:none;">';
        html += '<h3>Select Cities (₹' + config.per_city_fee + ' per city)</h3>';
        html += '<div id="cities-list" class="coverage-grid"></div>';
        html += '</div></div>';

        $('#coverage-selection-ui').html(html).show();

        // Store data for city rendering (convert array to object for easy lookup)
        window.coverageData = {};
        data.forEach(function (stateObj) {
            window.coverageData[stateObj.state] = stateObj.districts;
        });

        // Listen for state selection
        $('#state-select').on('change', function () {
            updateCityOptions();
            calculateTotalFee();
        });
    }

    /**
     * Update City Options based on selected states
     */
    function updateCityOptions() {
        const selectedStates = $('#state-select').val() || [];

        if (selectedStates.length === 0) {
            $('#cities-container').hide();
            return;
        }

        $('#cities-container').show();
        let citiesHtml = '';
        const allCities = [];

        selectedStates.forEach(function (state) {
            if (window.coverageData[state] && Array.isArray(window.coverageData[state])) {
                window.coverageData[state].forEach(function (city) {
                    allCities.push({ state: state, city: city });
                });
            }
        });

        // Sort cities alphabetically
        allCities.sort((a, b) => a.city.localeCompare(b.city));

        allCities.forEach(function (item) {
            citiesHtml += '<label class="coverage-checkbox">';
            citiesHtml += '<input type="checkbox" class="city-checkbox" value="' + item.city + '" data-state="' + item.state + '">';
            citiesHtml += '<span>' + item.city + ' (' + item.state + ')</span>';
            citiesHtml += '</label>';
        });

        $('#cities-list').html(citiesHtml);

        // Listen for city selection
        $('.city-checkbox').on('change', function () {
            calculateTotalFee();
        });
    }

    /**
     * Calculate Total Fee
     */
    function calculateTotalFee() {
        const statesCount = $('#state-select').val() ? $('#state-select').val().length : 0;
        const citiesCount = $('.city-checkbox:checked').length;

        const stateFee = statesCount * parseFloat(config.per_state_fee || 500);
        const cityFee = citiesCount * parseFloat(config.per_city_fee || 100);

        const total = stateFee + cityFee;
        registrationData.total_amount = total;

        $('#vreg-total-amount').text(total.toFixed(0));
    }

    /**
     * Validate Step 2 (Coverage Selection)
     */
    function validateStep2() {
        const selectedStates = $('#state-select').val() ? $('#state-select').val().length : 0;
        const selectedCities = $('.city-checkbox:checked').length;

        if (selectedStates === 0 && selectedCities === 0) {
            showFeedback('Please select at least one state or city', 'error');
            return false;
        }

        if (registrationData.total_amount === 0) {
            showFeedback('Total amount cannot be zero', 'error');
            return false;
        }

        return true;
    }

    /**
     * Save Step 2 Data
     */
    function saveStep2Data() {
        const selectedStates = $('#state-select').val() || [];

        const selectedCities = $('.city-checkbox:checked').map(function () {
            return {
                city: $(this).val(),
                state: $(this).data('state')
            };
        }).get();

        registrationData.coverage = {
            states: selectedStates,
            cities: selectedCities
        };
    }

    /**
     * Render Summary (Step 3)
     */
    function renderSummary() {
        let html = '<div class="summary-container">';

        // Basic Info Summary
        html += '<div class="summary-section">';
        html += '<h3>Basic Information</h3>';
        html += '<p><strong>Name:</strong> ' + registrationData.basic_info.full_name + '</p>';
        html += '<p><strong>Company:</strong> ' + registrationData.basic_info.company_name + '</p>';
        html += '<p><strong>Email:</strong> ' + registrationData.basic_info.email + '</p>';
        html += '<p><strong>Phone:</strong> ' + registrationData.basic_info.phone + '</p>';
        html += '</div>';

        // Coverage Summary
        html += '<div class="summary-section">';
        html += '<h3>Coverage Selection</h3>';

        if (registrationData.coverage.states.length > 0) {
            html += '<p><strong>States (' + registrationData.coverage.states.length + '):</strong> ';
            html += registrationData.coverage.states.join(', ') + '</p>';
        }

        if (registrationData.coverage.cities.length > 0) {
            html += '<p><strong>Cities (' + registrationData.coverage.cities.length + '):</strong> ';
            const cityNames = registrationData.coverage.cities.map(c => c.city);
            html += cityNames.join(', ') + '</p>';
        }

        html += '</div>';

        // Fee Breakdown
        html += '<div class="summary-section">';
        html += '<h3>Fee Breakdown</h3>';
        const statesFee = registrationData.coverage.states.length * parseFloat(config.per_state_fee || 500);
        const citiesFee = registrationData.coverage.cities.length * parseFloat(config.per_city_fee || 100);

        html += '<p>States: ' + registrationData.coverage.states.length + ' × ₹' + config.per_state_fee + ' = ₹' + statesFee + '</p>';
        html += '<p>Cities: ' + registrationData.coverage.cities.length + ' × ₹' + config.per_city_fee + ' = ₹' + citiesFee + '</p>';
        html += '<p class="total-fee"><strong>Total: ₹' + registrationData.total_amount + '</strong></p>';
        html += '</div>';

        html += '</div>';

        $('#vreg-summary').html(html);
    }

    /**
     * Submit Initial Registration (Step 1)
     */
    function submitInitialRegistration() {
        return new Promise((resolve, reject) => {
            $.ajax({
                url: config.ajax_url,
                type: 'POST',
                data: {
                    action: 'complete_vendor_registration',
                    registration_data: JSON.stringify(registrationData),
                    nonce: config.nonce
                },
                beforeSend: function () {
                    $('#vreg-step1-next').prop('disabled', true).text('Registering...');
                },
                success: function (response) {
                    if (response.success) {
                        showFeedback('Registration successful! Please check your email and click the verification link to continue.', 'success');
                        
                        // Hide Step 1 UI and show a success message block
                        $('#vreg-step-1').html(`
                            <div class="registration-success-message" style="text-align: center; padding: 40px 20px;">
                                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom: 20px;">
                                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                </svg>
                                <h2>Account Created!</h2>
                                <p style="font-size: 16px; color: #4b5563; margin-top: 10px;">We've sent a verification link to <strong>${registrationData.basic_info.email}</strong>.</p>
                                <p style="font-size: 16px; color: #4b5563;">Please click the link in that email to proceed to coverage selection and payment.</p>
                            </div>
                        `);
                        resolve(response.data.user_id);
                    } else {
                        showFeedback(response.data.message || 'Registration failed', 'error');
                        $('#vreg-step1-next').prop('disabled', false).text('Next');
                        reject(response.data.message);
                    }
                },
                error: function () {
                    showFeedback('Error submitting registration.', 'error');
                    $('#vreg-step1-next').prop('disabled', false).text('Next');
                    reject('Error submitting registration.');
                }
            });
        });
    }

    /**
     * Initiate Razorpay Payment (Now runs AFTER user is fully verified and logged in)
     */
    async function initiatePayment() {
        try {
            $('#vreg-pay-btn').prop('disabled', true).text('Initializing Gateway...');
            
            // Note: The user account is now completely created and verified before this point.
            // All we need to do is generate the order.

            // 2. Create Razorpay order
            $.ajax({
                url: config.ajax_url,
                type: 'POST',
                data: {
                    action: 'create_razorpay_order',
                    amount: registrationData.total_amount,
                    nonce: config.nonce
                },
                beforeSend: function () {
                    $('#vreg-pay-btn').text('Creating order...');
                },
                success: function (response) {
                    if (response.success && response.data.order_id) {
                        openRazorpayCheckout(response.data.order_id, response.data.amount);
                        $('#vreg-pay-btn').prop('disabled', false).text('Proceed to Payment');
                    } else {
                        showFeedback(response.data.message || 'Failed to create payment order', 'error');
                        $('#vreg-pay-btn').prop('disabled', false).text('Proceed to Payment');
                    }
                },
                error: function () {
                    showFeedback('Error creating payment order', 'error');
                    $('#vreg-pay-btn').prop('disabled', false).text('Proceed to Payment');
                }
            });
            
        } catch (error) {
            showFeedback(error, 'error');
            $('#vreg-pay-btn').prop('disabled', false).text('Proceed to Payment');
        }
    }

    /**
     * Open Razorpay Checkout
     */
    function openRazorpayCheckout(orderId, amount) {
        const options = {
            key: config.razorpay_key_id,
            amount: amount,
            currency: 'INR',
            name: 'Vendor Registration',
            description: 'Coverage Area Purchase',
            order_id: orderId,
            handler: function (response) {
                // Payment successful - verify it with the central gateway
                verifyCentralPayment(response, orderId, amount);
            },
            prefill: {
                name: registrationData.basic_info.full_name,
                email: registrationData.basic_info.email,
                contact: registrationData.basic_info.phone
            },
            theme: {
                color: '#667eea'
            },
            modal: {
                ondismiss: function () {
                    // Nothing to revert
                }
            }
        };

        const rzp = new Razorpay(options);
        rzp.open();
    }

    /**
     * Complete Registration after payment
     */
    function verifyCentralPayment(paymentResponse, orderId, amount) {
        $.ajax({
            url: config.ajax_url,
            type: 'POST',
            data: {
                action: 'verify_ksc_payment',
                context: 'vendor_registration',
                razorpay_payment_id: paymentResponse.razorpay_payment_id,
                razorpay_order_id: paymentResponse.razorpay_order_id,
                razorpay_signature: paymentResponse.razorpay_signature,
                extra_data: JSON.stringify({
                    states: registrationData.coverage.states,
                    cities: registrationData.coverage.cities.map(c => c.city),
                    amount: registrationData.total_amount
                    // vendor_id is NOT needed here anymore since the user is fully logged in now.
                }),
                nonce: config.nonce
            },
            beforeSend: function () {
                showFeedback('Processing payment...', 'info');
            },
            success: function (response) {
                if (response.success) {
                    $('#vreg-step-3').html(`
                        <div class="registration-success-message" style="text-align: center; padding: 40px 20px;">
                            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom: 20px;">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                <polyline points="22 4 12 14.01 9 11.01"></polyline>
                            </svg>
                            <h2>Payment Successful!</h2>
                            <p style="font-size: 16px; color: #4b5563; margin-top: 10px;">Your coverage areas have been locked in and your account is now Fully Approved.</p>
                            <p style="font-size: 16px; color: #4b5563;">Redirecting you to your dashboard...</p>
                        </div>
                    `);
                    setTimeout(function () {
                        window.location.href = response.data.redirect_url || '/vendor-status/';
                    }, 3000);
                } else {
                    showFeedback(response.data.message || 'Payment processing failed', 'error');
                }
            },
            error: function () {
                showFeedback('Error processing payment confirmation on the server.', 'error');
            }
        });
    }

    /**
     * Show Feedback Message
     */
    function showFeedback(message, type) {
        const $feedback = $('#vreg-feedback');
        $feedback.removeClass('error success info warning')
            .addClass(type)
            .text(message)
            .fadeIn();

        if (type === 'success' || type === 'info') {
            setTimeout(function () {
                $feedback.fadeOut();
            }, 5000);
        }
    }

    // Check if the page loaded with a pending-payment state (verified but unpaid vendor)
    // NOTE: wp_localize_script converts integers to strings, so we use parseInt()
    // to avoid "2" === 2 being false (strict type mismatch).
    if (parseInt(config.resume_step) === 2) {
        $(document).ready(function() {
            // Pre-fill the basic info so the summary doesn't look empty when they reach Step 3
            if (config.user_data) {
                registrationData.basic_info = {
                    full_name: config.user_data.name || 'Vendor',
                    company_name: config.user_data.company || '',
                    email: config.user_data.email || '',
                    phone: config.user_data.phone || ''
                };
            }

            // Hide "Previous" buttons — Step 1 is done (account created + email verified).
            // Letting the user go back would show a registration form they can't re-submit.
            $('#vreg-step2-prev').hide();
            $('#vreg-step3-prev').hide();

            showStep(2);
            showFeedback('Email verified successfully! Please select your coverage areas.', 'success');
        });
    }

})(jQuery);
