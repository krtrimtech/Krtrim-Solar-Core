<?php
/**
 * Solar Cleaning Booking Form
 * 
 * Provides a frontend form for users to book solar panel cleaning services.
 * Includes plan selection, price calculation, and Razorpay integration.
 * 
 * @package Krtrim_Solar_Core
 */

if (!defined('ABSPATH')) {
    exit;
}

function ksc_render_cleaning_booking_form() {
    // Enqueue Razorpay
    wp_enqueue_script('razorpay', 'https://checkout.razorpay.com/v1/checkout.js', [], null, true);
    
    ob_start();
    ?>
    <div class="ksc-booking-container">
        <div class="ksc-booking-header">
            <h2>☀️ Book Solar Cleaning</h2>
            <p>Professional solar panel cleaning services to maximize your energy production.</p>
        </div>

        <div class="ksc-steps-indicator">
            <div class="step active" data-step="1">1. Contact</div>
            <div class="step" data-step="2">2. Plan & System</div>
            <div class="step" data-step="3">3. Payment</div>
        </div>

        <form id="ksc-cleaning-booking-form">
            <?php wp_nonce_field('ksc_booking_nonce', 'booking_nonce'); ?>
            
            <!-- Step 1: Contact Details -->
            <div class="form-step active" id="step-1">
                <h3>Contact Details</h3>
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="customer_name" required placeholder="Enter your name">
                </div>
                <div class="form-group">
                    <label>Phone Number *</label>
                    <input type="tel" name="customer_phone" required placeholder="10-digit mobile number">
                </div>
                <div class="form-group">
                    <label>Address *</label>
                    <textarea name="customer_address" required rows="3" placeholder="Full address for service"></textarea>
                </div>
                <button type="button" class="btn-next" onclick="nextStep(2)">Next: Plan Selection →</button>
            </div>

            <!-- Step 2: System & Plan -->
            <div class="form-step" id="step-2">
                <h3>System & Plan</h3>
                
                <div class="form-group">
                    <label>System Size (kW) *</label>
                    <input type="number" name="system_size_kw" id="system_size_kw" required min="1" step="0.1" value="3">
                    <small>Enter the capacity of your solar plant in kW</small>
                </div>

                <div class="plan-selection">
                    <label>Select Plan *</label>
                    <div class="plans-grid">
                        <label class="plan-card">
                            <input type="radio" name="plan_type" value="one_time" checked onchange="calculatePrice()">
                            <div class="plan-content">
                                <span class="plan-title">One Time</span>
                                <span class="plan-desc">Single deep cleaning visit</span>
                            </div>
                        </label>
                        <label class="plan-card">
                            <input type="radio" name="plan_type" value="monthly" onchange="calculatePrice()">
                            <div class="plan-content">
                                <span class="plan-title">Monthly</span>
                                <span class="plan-desc">12 visits/year (Best Value)</span>
                            </div>
                        </label>
                        <label class="plan-card">
                            <input type="radio" name="plan_type" value="6_month" onchange="calculatePrice()">
                            <div class="plan-content">
                                <span class="plan-title">6 Months</span>
                                <span class="plan-desc">6 visits (10% Off)</span>
                            </div>
                        </label>
                        <label class="plan-card">
                            <input type="radio" name="plan_type" value="yearly" onchange="calculatePrice()">
                            <div class="plan-content">
                                <span class="plan-title">Yearly</span>
                                <span class="plan-desc">12 visits (Pay yearly, 15% Off)</span>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label>Preferred Date *</label>
                    <input type="date" name="preferred_date" required min="<?php echo date('Y-m-d'); ?>">
                    <small>We will confirm availability for this date.</small>
                </div>

                <div class="form-group">
                    <label>Preferred Date *</label>
                    <input type="date" name="preferred_date" required min="<?php echo date('Y-m-d'); ?>">
                    <small>We will confirm availability for this date.</small>
                </div>

                <!-- Coupon Section -->
                <div class="coupon-section" style="margin-bottom: 20px; border-top: 1px dashed #ddd; padding-top: 15px;">
                    <label>Have a Coupon?</label>
                    <div style="display: flex; gap: 10px;">
                        <input type="text" id="coupon_code" placeholder="Enter code" style="text-transform: uppercase;">
                        <button type="button" id="apply_coupon_btn" onclick="applyCoupon()" style="background: #333; color: #fff; border: none; padding: 0 15px; border-radius: 4px; cursor: pointer;">Apply</button>
                    </div>
                    <small id="coupon_msg" style="display: block; margin-top: 5px;"></small>
                </div>

                <!-- Price Calculator Result -->
                <div class="price-summary" id="price-summary">
                    <div class="price-row"><span>Base Price:</span> <span id="summ-base">₹0</span></div>
                    <div class="price-row"><span>Visits:</span> <span id="summ-visits">0</span></div>
                    <div class="price-row" id="row-discount" style="display: none; color: #27ae60;"><span>Discount:</span> <span id="summ-discount">-₹0</span></div>
                    <div class="price-row total"><span>Total to Pay:</span> <span id="summ-total">₹0</span></div>
                </div>
                
                <div class="button-group">
                    <button type="button" class="btn-back" onclick="prevStep(1)">← Back</button>
                    <button type="button" class="btn-next" onclick="nextStep(3)">Next: Payment →</button>
                </div>
            </div>

            <!-- Step 3: Payment Method -->
            <div class="form-step" id="step-3">
                <h3>Select Payment Method</h3>
                
                <div class="payment-options">
                    <label class="payment-card">
                        <input type="radio" name="payment_option" value="online" checked>
                        <div class="payment-content">
                            <span class="payment-icon">💳</span>
                            <span class="title">Pay Online</span>
                            <span class="desc">Secure payment via UPI/Card</span>
                        </div>
                    </label>
                    <label class="payment-card">
                        <input type="radio" name="payment_option" value="pay_after">
                        <div class="payment-content">
                            <span class="payment-icon">💵</span>
                            <span class="title">Pay After Service</span>
                            <span class="desc">Cash or UPI to Cleaner</span>
                        </div>
                    </label>
                </div>

                <div class="button-group">
                    <button type="button" class="btn-back" onclick="prevStep(2)">← Back</button>
                    <button type="button" class="btn-next" onclick="processBooking()">Confirm Booking →</button>
                </div>
            </div>
        </form>
    </div>

    <style>
        .ksc-booking-container {
            max-width: 600px;
            margin: 0 auto;
            background: #fff;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            font-family: 'Segoe UI', sans-serif;
        }
        .ksc-booking-header { text-align: center; margin-bottom: 30px; }
        .ksc-booking-header h2 { color: #f39c12; margin-bottom: 5px; }
        
        .ksc-steps-indicator { display: flex; justify-content: space-between; margin-bottom: 30px; position: relative; }
        .ksc-steps-indicator::after {
            content: ''; position: absolute; top: 15px; left: 0; right: 0; height: 2px; background: #eee; z-index: 0;
        }
        .step {
            position: relative; z-index: 1; background: #fff; padding: 5px 15px; border-radius: 20px;
            font-size: 14px; font-weight: 600; color: #999; border: 2px solid #eee; width: 30%; text-align: center;
        }
        .step.active { border-color: #f39c12; color: #f39c12; background: #fffbe6; }
        
        .form-step { display: none; animation: fadeIn 0.3s ease; }
        .form-step.active { display: block; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; color: #333; }
        .form-group input, .form-group textarea {
            width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 16px;
            box-sizing: border-box;
        }
        
        .plans-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .plan-card {
            border: 2px solid #eee; border-radius: 8px; padding: 15px; cursor: pointer; transition: all 0.2s;
            position: relative; overflow: hidden;
        }
        .plan-card:hover { border-color: #f39c12; }
        .plan-card input { position: absolute; opacity: 0; }
        .plan-card input:checked + .plan-content { color: #f39c12; }
        .plan-card input:checked ~ .plan-content .plan-title { font-weight: bold; }
        .plan-card:has(input:checked) { border-color: #f39c12; background: #fffaf0; }
        
        .plan-title { display: block; font-size: 16px; margin-bottom: 5px; }
        .plan-desc { display: block; font-size: 12px; color: #666; }
        
        .price-summary {
            background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 25px 0; border: 1px solid #e9ecef;
        }
        .price-row { display: flex; justify-content: space-between; margin-bottom: 10px; color: #555; }
        .price-row.total { font-weight: bold; font-size: 18px; color: #2c3e50; border-top: 1px solid #ddd; padding-top: 10px; margin-bottom: 0; }
        
        .button-group { display: flex; gap: 10px; }
        .btn-next, .btn-back {
            flex: 1; padding: 14px; border: none; border-radius: 8px; font-size: 16px; cursor: pointer; font-weight: 600;
        }
        .btn-next { background: #f39c12; color: white; }
        .btn-next:hover { background: #e67e22; }
        .btn-back { background: #f1f1f1; color: #333; }
        
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
        
        /* Razorpay iframe fix */
        .razorpay-checkout-frame { z-index: 99999 !important; }

        .payment-options { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 25px; }
        .payment-card {
            border: 2px solid #eee; border-radius: 8px; padding: 20px; cursor: pointer; transition: all 0.2s; text-align: center;
        }
        .payment-card:hover { border-color: #f39c12; }
        .payment-card input { display: none; }
        .payment-card:has(input:checked) { border-color: #f39c12; background: #fffaf0; }
        .payment-content { display: flex; flex-direction: column; align-items: center; }
        .payment-icon { font-size: 32px; margin-bottom: 10px; }
        .payment-content .title { font-weight: bold; font-size: 16px; margin-bottom: 5px; display: block; }
        .payment-content .desc { font-size: 12px; color: #666; display: block; }
    </style>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initial Calculation
        calculatePrice();
        
        // Input Listener
        document.getElementById('system_size_kw').addEventListener('input', calculatePrice);
    });

    function nextStep(step) {
        // Validation
        if (step === 2) {
            const name = document.querySelector('input[name="customer_name"]').value;
            const phone = document.querySelector('input[name="customer_phone"]').value;
            const address = document.querySelector('textarea[name="customer_address"]').value;
            
            if (!name || !phone || !address) {
                alert('Please fill in all contact details.');
                return;
            }
        }
        
        if (step === 3) {
            const date = document.querySelector('input[name="preferred_date"]').value;
            if (!date) {
                alert('Please select a preferred date.');
                return;
            }
        }

        document.querySelectorAll('.form-step').forEach(el => el.classList.remove('active'));
        document.getElementById('step-' + step).classList.add('active');
        
        document.querySelectorAll('.step').forEach(el => {
            if (parseInt(el.dataset.step) <= step) el.classList.add('active');
            else el.classList.remove('active');
        });
    }

    function processBooking() {
        const paymentMethod = document.querySelector('input[name="payment_option"]:checked').value;
        if (paymentMethod === 'online') {
            initiatePayment();
        } else {
            createPayAfterBooking();
        }
    }

    function createPayAfterBooking() {
        const form = document.getElementById('ksc-cleaning-booking-form');
        const formData = new FormData(form);
        formData.append('action', 'create_pay_after_booking');

        const btn = document.querySelectorAll('.btn-next')[2]; // The confirm button
        const originalText = btn.innerText;
        btn.innerText = 'Processing...';
        btn.disabled = true;

        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showSuccessMessage(data.data.booking_id);
            } else {
                alert('Error: ' + data.data.message);
                btn.innerText = originalText;
                btn.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
            btn.innerText = originalText;
            btn.disabled = false;
        });
    }

    function initiatePayment() {
        const form = document.getElementById('ksc-cleaning-booking-form');
        const formData = new FormData(form);
        formData.append('action', 'create_cleaning_razorpay_order');

        const btn = document.querySelector('.btn-next');
        const originalText = btn.innerText;
        btn.innerText = 'Processing...';
        btn.disabled = true;

        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const options = {
                    "key": data.data.key_id,
                    "amount": data.data.amount,
                    "currency": data.data.currency,
                    "name": data.data.name,
                    "description": data.data.description,
                    "order_id": data.data.order_id,
                    "prefill": data.data.prefill,
                    "theme": { "color": "#f39c12" },
                    "handler": function (response) {
                        verifyPayment(response);
                    },
                    "modal": {
                        "ondismiss": function() {
                            btn.innerText = originalText;
                            btn.disabled = false;
                        }
                    }
                };
                const rzp1 = new Razorpay(options);
                rzp1.open();
            } else {
                alert('Error: ' + data.data.message);
                btn.innerText = originalText;
                btn.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
            btn.innerText = originalText;
            btn.disabled = false;
        });
    }

    function verifyPayment(response) {
        const formData = new FormData();
        formData.append('action', 'verify_cleaning_payment');
        formData.append('razorpay_order_id', response.razorpay_order_id);
        formData.append('razorpay_payment_id', response.razorpay_payment_id);
        formData.append('razorpay_signature', response.razorpay_signature);

        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showSuccessMessage(data.data.booking_id);
            } else {
                alert('Payment verification failed: ' + data.data.message);
            }
        });
    }

    function showSuccessMessage(bookingId) {
        document.querySelector('.ksc-booking-container').innerHTML = `
            <div style="text-align: center; padding: 40px 20px;">
                <div style="font-size: 50px; margin-bottom: 20px;">✅</div>
                <h2 style="color: #27ae60;">Booking Confirmed!</h2>
                <p>Thank you for booking. Your Booking ID is <strong>#${bookingId}</strong>.</p>
                <p>We will contact you shortly to schedule the visit.</p>
                <a href="<?php echo home_url('/solar-dashboard'); ?>" class="btn-next" style="display:inline-block; text-decoration:none; margin-top:20px; padding: 10px 20px;">Go to Dashboard</a>
            </div>
        `;
    }

    let currentTotal = 0;
    let appliedCoupon = null;

    function applyCoupon() {
        const code = document.getElementById('coupon_code').value;
        const btn = document.getElementById('apply_coupon_btn');
        const msg = document.getElementById('coupon_msg');

        if (!code) return;

        btn.innerText = '...';
        btn.disabled = true;
        msg.innerText = '';
        msg.style.color = '#666';

        const formData = new FormData();
        formData.append('action', 'validate_cleaning_coupon');
        formData.append('coupon_code', code);

        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            btn.innerText = 'Apply';
            btn.disabled = false;

            if (data.success) {
                appliedCoupon = data.data;
                msg.innerText = data.data.message;
                msg.style.color = 'green';
                updateTotalWithCoupon();
            } else {
                appliedCoupon = null;
                msg.innerText = data.data.message;
                msg.style.color = 'red';
                updateTotalWithCoupon(); // Reset if invalid
            }
        })
        .catch(err => {
            console.error(err);
            btn.innerText = 'Apply';
            btn.disabled = false;
        });
    }

    function updateTotalWithCoupon() {
        // Recalculate based on currentTotal
        if (!currentTotal) return;

        let finalTotal = currentTotal;
        let discount = 0;

        if (appliedCoupon) {
            if (appliedCoupon.type === 'percent') {
                discount = currentTotal * (appliedCoupon.amount / 100);
            } else {
                discount = appliedCoupon.amount;
            }
            if (discount > currentTotal) discount = currentTotal;
            finalTotal = currentTotal - discount;

            document.getElementById('row-discount').style.display = 'flex';
            document.getElementById('summ-discount').innerText = '-₹' + Math.round(discount);
        } else {
            document.getElementById('row-discount').style.display = 'none';
        }

        document.getElementById('summ-total').innerText = '₹' + Math.round(finalTotal);
        
        // Add coupon to inputs for submission
        let hidden = document.getElementById('hidden_coupon');
        if (!hidden) {
            hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.id = 'hidden_coupon';
            hidden.name = 'coupon_code';
            document.getElementById('ksc-cleaning-booking-form').appendChild(hidden);
        }
        hidden.value = appliedCoupon ? appliedCoupon.code : '';
    }

    function calculatePrice() {
        const kw = document.getElementById('system_size_kw').value;
        const plan = document.querySelector('input[name="plan_type"]:checked').value;
        
        if (!kw || kw <= 0) return;

        // Update Price Summary UI to loading state?
        document.getElementById('summ-total').innerText = '...';

        const formData = new FormData();
        formData.append('action', 'calculate_cleaning_price');
        formData.append('kw', kw);
        formData.append('plan', plan);

        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const res = data.data;
                document.getElementById('summ-base').innerText = '₹' + Math.round(res.subtotal);
                document.getElementById('summ-visits').innerText = res.visits;
                
                currentTotal = res.total; // Store global base total
                updateTotalWithCoupon();  // Apply coupon if exists
            }
        })
        .catch(err => console.error(err));
    }
    </script>
    <?php
    return ob_get_clean();
}
