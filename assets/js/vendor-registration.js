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
        // Step 1: Basic Info → Coverage
        $('#vreg-step1-next').on('click', async function () {
            if (await validateStep1()) {
                saveStep1Data();
                showStep(2);
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
            console.error('Email check error:', error);
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
     * Initiate Razorpay Payment
     */
    function initiatePayment() {
        // First create Razorpay order
        $.ajax({
            url: config.ajax_url,
            type: 'POST',
            data: {
                action: 'create_razorpay_order',
                amount: registrationData.total_amount,
                nonce: config.nonce
            },
            beforeSend: function () {
                $('#vreg-pay-btn').prop('disabled', true).text('Creating order...');
            },
            success: function (response) {
                if (response.success && response.data.order_id) {
                    openRazorpayCheckout(response.data.order_id, response.data.amount);
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
                // Payment successful
                completeRegistration(response);
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
                    $('#vreg-pay-btn').prop('disabled', false).text('Proceed to Payment');
                }
            }
        };

        const rzp = new Razorpay(options);
        rzp.open();
    }

    /**
     * Complete Registration after payment
     */
    function completeRegistration(paymentResponse) {
        $.ajax({
            url: config.ajax_url,
            type: 'POST',
            data: {
                action: 'complete_vendor_registration',
                registration_data: JSON.stringify(registrationData),
                payment_response: JSON.stringify(paymentResponse),
                nonce: config.nonce
            },
            beforeSend: function () {
                showFeedback('Processing registration...', 'info');
            },
            success: function (response) {
                if (response.success) {
                    showFeedback('Registration successful! Redirecting...', 'success');
                    setTimeout(function () {
                        window.location.href = response.data.redirect_url || '/vendor-status/';
                    }, 2000);
                } else {
                    showFeedback(response.data.message || 'Registration failed', 'error');
                }
            },
            error: function () {
                showFeedback('Error completing registration', 'error');
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

})(jQuery);
