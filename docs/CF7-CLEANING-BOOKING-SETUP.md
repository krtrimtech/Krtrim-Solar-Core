# Solar Cleaning Booking - Contact Form 7 Setup

## Step 1: Create the CF7 Form

Go to **wp-admin → Contact → Add New** and paste this form template:

```
<div class="cleaning-booking-form">

<h3>📋 Your Details</h3>

<div class="form-row">
[text* customer_name placeholder "Full Name *"]
[tel* customer_phone placeholder "Phone Number *"]
</div>

<div class="form-row">
[email customer_email placeholder "Email (optional)"]
[textarea customer_address placeholder "Service Address *" 40x3]
</div>

<h3>☀️ Solar System Details</h3>

<div class="form-row">
[number* system_size_kw min:1 max:100 placeholder "System Size in kW *" class:kw-input]
</div>

<h3>📅 Choose Your Plan</h3>

[radio plan_type default:1 "one_time|One-Time Cleaning" "monthly|Monthly (12 cleanings/year)" "6_month|6-Month Plan (6 cleanings) - 10% OFF" "yearly|Yearly Plan (12 cleanings) - 10% OFF"]

<h3>💳 Payment Option</h3>

[radio payment_option default:1 "pay_before|Pay Now & Select Exact Date" "pay_after|Pay After Service (We'll schedule within your preferred week)"]

<div id="date-selector-before" style="display:none;">
<h4>📅 Select Your Preferred Date</h4>
[date preferred_date min:today+1]
</div>

<div id="date-selector-after" style="display:none;">
<h4>📅 When would you like the cleaning?</h4>
[select preferred_week "this_week|This Week" "next_week|Next Week" "week_after|Week After Next"]
</div>

<div id="price-summary" style="background: #f0f9ff; padding: 20px; border-radius: 12px; margin: 20px 0;">
<h4 style="margin-top:0;">💰 Price Summary</h4>
<div id="price-details">Enter system size to see pricing</div>
</div>

<div id="pay-now-section" style="display:none;">
[submit "💳 Pay Now & Book"]
</div>

<div id="pay-later-section" style="display:none;">
[submit "📅 Book Now, Pay Later"]
</div>

</div>
```

---

## Step 2: Add Form Styling

Add this CSS to your theme or via **Appearance → Customize → Additional CSS**:

```css
.cleaning-booking-form .form-row {
    display: flex;
    gap: 15px;
    margin-bottom: 15px;
}

.cleaning-booking-form .form-row > span {
    flex: 1;
}

.cleaning-booking-form input,
.cleaning-booking-form textarea,
.cleaning-booking-form select {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 14px;
}

.cleaning-booking-form input:focus,
.cleaning-booking-form textarea:focus {
    border-color: #4f46e5;
    outline: none;
}

.cleaning-booking-form h3 {
    margin: 25px 0 15px;
    color: #1f2937;
}

.cleaning-booking-form .wpcf7-list-item {
    display: block;
    padding: 12px 15px;
    margin: 5px 0;
    background: #f8f9fa;
    border-radius: 8px;
    border: 2px solid transparent;
    cursor: pointer;
}

.cleaning-booking-form .wpcf7-list-item:hover {
    border-color: #4f46e5;
}

.cleaning-booking-form input[type="submit"] {
    background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
    color: white;
    padding: 15px 30px;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    width: 100%;
}

.cleaning-booking-form input[type="submit"]:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.4);
}

#price-summary {
    background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
    border: 1px solid #bae6fd;
}

#price-details .price-row {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #e0e0e0;
}

#price-details .total-row {
    font-weight: bold;
    font-size: 18px;
    color: #4f46e5;
    border-bottom: none;
    padding-top: 15px;
}
```

---

## Step 3: Add JavaScript for Dynamic Behavior

Add this script to your page or theme:

```html
<script>
jQuery(document).ready(function($) {
    // Toggle date selectors based on payment option
    $('input[name="payment_option"]').on('change', function() {
        var val = $(this).val();
        if (val === 'pay_before') {
            $('#date-selector-before').show();
            $('#date-selector-after').hide();
            $('#pay-now-section').show();
            $('#pay-later-section').hide();
        } else {
            $('#date-selector-before').hide();
            $('#date-selector-after').show();
            $('#pay-now-section').hide();
            $('#pay-later-section').show();
        }
    });

    // Initial trigger
    $('input[name="payment_option"]:checked').trigger('change');

    // Calculate price on kW or plan change
    function calculatePrice() {
        var kw = parseFloat($('.kw-input input').val()) || 0;
        var plan = $('input[name="plan_type"]:checked').val() || 'one_time';

        if (kw <= 0) {
            $('#price-details').html('<p>Enter system size to see pricing</p>');
            return;
        }

        $.ajax({
            url: ksc_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'calculate_cleaning_price',
                kw: kw,
                plan: plan
            },
            success: function(response) {
                if (response.success) {
                    var d = response.data;
                    var html = '<div class="price-row"><span>Base Price (₹' + d.base_price + '/kW)</span><span>₹' + d.base_price + '</span></div>';
                    html += '<div class="price-row"><span>Visits</span><span>' + d.visits + ' cleaning(s)</span></div>';
                    html += '<div class="price-row"><span>Subtotal</span><span>₹' + d.subtotal + '</span></div>';
                    if (d.discount > 0) {
                        html += '<div class="price-row" style="color: green;"><span>Discount (' + d.discount_percent + '%)</span><span>-₹' + d.discount + '</span></div>';
                    }
                    html += '<div class="price-row total-row"><span>Total</span><span>₹' + d.total + '</span></div>';
                    $('#price-details').html(html);
                }
            }
        });
    }

    $('.kw-input input').on('input', calculatePrice);
    $('input[name="plan_type"]').on('change', calculatePrice);
});
</script>
```

---

## Step 4: Embed on Page

1. Go to **Pages → Book Solar Cleaning**
2. Add the CF7 shortcode: `[contact-form-7 id="YOUR_FORM_ID" title="Solar Cleaning Booking"]`
3. Publish!

---

## How It Works

| Payment Option | Date Selection | Flow |
|---------------|----------------|------|
| **Pay Before** | Exact date picker | Form → Razorpay → Booking created with date |
| **Pay After** | This/Next/After week | Form → Booking created (pending) → AM assigns cleaner & date → Pay on arrival |
