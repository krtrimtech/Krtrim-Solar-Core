function handleWhatsAppRedirect(whatsapp_data) {
    if (whatsapp_data && whatsapp_data.phone && whatsapp_data.message) {
        // The message from the backend is already URL-encoded
        const url = `https://wa.me/${whatsapp_data.phone}?text=${whatsapp_data.message}`;
        window.open(url, '_blank');
    }
}

// Global dashboard settings
const REST_API_NONCE = ksc_dashboard_vars.rest_api_nonce;

// --- Generic UI Functions ---

function switchSection(event, section) {
    if (event) event.preventDefault();
    document.querySelectorAll('.section-content').forEach(el => el.style.display = 'none');
    document.getElementById(section + '-section').style.display = 'block';
    document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
    document.querySelector(`[data-section="${section}"]`).classList.add('active');

    const titles = {
        'dashboard': 'Dashboard',
        'projects': 'Projects',
        'timeline': 'Installation Timeline'
    };
    document.getElementById('section-title').textContent = titles[section] || 'Dashboard';

    if (window.innerWidth <= 768 && document.querySelector('.dashboard-sidebar.open')) {
        toggleSidebar();
    }
}

function switchVendorSection(event, section) {
    if (event) event.preventDefault();

    document.querySelectorAll('.section-content').forEach(el => el.style.display = 'none');
    document.getElementById(section + '-section').style.display = 'block';

    document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
    document.querySelector('[data-section="' + section + '"]').classList.add('active');

    // Update mobile bottom nav active state
    document.querySelectorAll('.mobile-bottom-nav .nav-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    const activeBottomBtn = document.querySelector('.mobile-bottom-nav .nav-btn[data-section="' + section + '"]');
    if (activeBottomBtn) {
        activeBottomBtn.classList.add('active');
    }

    const titles = {
        'dashboard': 'Dashboard',
        'projects': 'Projects',
        'timeline': 'Work Timeline'
    };
    document.getElementById('section-title').textContent = titles[section];

    if (window.innerWidth <= 768) {
        document.querySelector('.dashboard-sidebar').classList.remove('open');
    }
}

function toggleSidebar() {
    const sidebar = document.querySelector('.dashboard-sidebar');
    sidebar.classList.toggle('open');
    if (window.innerWidth <= 768) {
        const floatingBtn = document.querySelector('.mobile-sidebar-toggle-floating');
        floatingBtn.style.display = sidebar.classList.contains('open') ? 'none' : 'flex';
    }
}

function toggleTimelineDetail(stepId) {
    const content = document.getElementById(`content-${stepId}`);
    const arrow = document.getElementById(`arrow-${stepId}`);
    if (content.style.display === 'none') {
        content.style.display = 'block';
        arrow.style.transform = 'rotate(0deg)';
    } else {
        content.style.display = 'none';
        arrow.style.transform = 'rotate(-90deg)';
    }
}

function toggleStepDetail(stepId) {
    const content = document.getElementById('step-content-' + stepId);
    content.style.display = content.style.display === 'none' ? 'block' : 'none';
}

function toggleNotificationPanel() {
    document.querySelector('.notification-panel').classList.toggle('open');
}

function openImageModal(src) {
    document.getElementById('imageModal').style.display = 'block';
    document.getElementById('modalImage').src = src;
}

function closeImageModal() {
    document.getElementById('imageModal').style.display = 'none';
}

// --- Client-Specific Functions ---

function toggleCommentForm(stepId) {
    const form = document.getElementById(`comment-form-${stepId}`);
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
}

function submitComment(stepId, projectId) {
    const commentText = document.getElementById(`comment-text-${stepId}`).value.trim();
    if (!commentText) {
        alert('Please enter a comment.');
        return;
    }

    fetch(ksc_dashboard_vars.client_comments_url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': REST_API_NONCE
        },
        body: JSON.stringify({
            step_id: stepId,
            project_id: projectId,
            comment_text: commentText
        })
    })
        .then(response => response.json())
        .then(data => {
            if (data.message) {
                alert('✅ Comment submitted successfully!');
                document.getElementById(`comment-text-${stepId}`).value = '';
                toggleCommentForm(stepId);
            } else {
                throw new Error(data.message || 'Unknown error');
            }
        })
        .catch(error => {
            alert(`Error: ${error.message}`);
        });
}

// --- Unified Notification Loader ---

function loadNotifications(restUrl) {
    fetch(restUrl, {
        method: 'GET',
        credentials: 'same-origin',
        headers: { 'X-WP-Nonce': REST_API_NONCE }
    })
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
        })
        .then(data => {
            const notifList = document.getElementById('notif-list');
            const notifCount = document.getElementById('notif-count');
            const notifications = data.notifications || data; // Handle both possible response structures

            if (notifications && notifications.length > 0) {
                let html = '';
                notifications.forEach(n => {
                    const isVendor = restUrl.includes('vendor');
                    const borderColor = n.type === 'approved' ? '#28a745' : n.type === 'rejected' ? '#dc3545' : '#007bff';
                    const bgColor = n.type === 'approved' ? '#f8fff9' : n.type === 'rejected' ? '#fff5f5' : '#f0f7ff';

                    html += `<div class="notification-item" style="padding:12px;position:relative; border-radius:8px; border-left:4px solid ${borderColor}; background:${bgColor}; margin-bottom:10px;" data-notification-id="${n.id}">`;
                    if (isVendor) {
                        html += `<button class="btn-dismiss-notification" style="position:absolute; top:8px; right:8px; background:none; border:none; font-size:16px; cursor:pointer;">&times;</button>`;
                    }
                    html += `<div style="font-weight:600; color:#333;">${n.icon} ${n.title}</div>`;
                    html += `<div style="font-size:12px; color:#666; margin-top:4px;">${n.message}</div>`;
                    if (n.time_ago) {
                        html += `<div style="font-size:10px; color:#999; margin-top:4px;">${n.time_ago}</div>`;
                    }
                    html += `</div>`;
                });
                notifList.innerHTML = html;
                notifCount.textContent = notifications.length;
            } else {
                notifList.innerHTML = '<p style="text-align:center; color:#999; padding: 20px; margin: 0;">No new notifications</p>';
                notifCount.textContent = '0';
            }
        })
        .catch(error => {
            console.error('Notification Error:', error);
            document.getElementById('notif-list').innerHTML = '<p style="color:#dc3545; text-align:center; padding: 20px; margin:0;">Error loading notifications</p>';
        });
}

// --- Vendor-Specific Functions ---

function backToProjectsList() {
    const url = new URL(window.location);
    url.searchParams.delete('view_project');
    window.location.href = url.toString();
}

function goToProject(projectId) {
    const url = new URL(window.location);
    url.searchParams.set('view_project', projectId);
    window.location.href = url.toString();
}

// --- Document Ready ---

document.addEventListener('DOMContentLoaded', function () {
    const clientDashboard = document.getElementById('modernDashboard');
    const vendorDashboard = document.getElementById('vendorDashboard');

    if (clientDashboard) {
        const clientApiUrl = ksc_dashboard_vars.client_api_url;
        loadNotifications(clientApiUrl);
        setInterval(() => loadNotifications(clientApiUrl), 10000);

        if (typeof payment_data !== 'undefined') {
            const ctx = document.getElementById('payment-summary-chart').getContext('2d');
            new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: ['Paid', 'Balance'],
                    datasets: [{
                        label: 'Payment Summary',
                        data: [payment_data.paid, payment_data.balance],
                        backgroundColor: [
                            'rgba(40, 167, 69, 0.5)',
                            'rgba(255, 193, 7, 0.5)',
                        ],
                    }]
                },
            });
        }
    }

    if (vendorDashboard) {
        const vendorApiUrl = ksc_dashboard_vars.vendor_api_url;
        loadNotifications(vendorApiUrl);
        setInterval(() => loadNotifications(vendorApiUrl), 10000);

        function loadEarningsChart() {
            jQuery.ajax({
                url: ksc_dashboard_vars.admin_ajax_url,
                type: 'POST',
                data: {
                    action: 'get_vendor_earnings_chart_data',
                    nonce: ksc_dashboard_vars.get_earnings_chart_data_nonce,
                },
                success: function (response) {
                    if (response.success) {
                        const ctx = document.getElementById('earnings-chart').getContext('2d');
                        new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: response.data.labels,
                                datasets: [{
                                    label: 'Earnings',
                                    data: response.data.data,
                                    backgroundColor: 'rgba(40, 167, 69, 0.2)',
                                    borderColor: 'rgba(40, 167, 69, 1)',
                                    borderWidth: 1
                                }]
                            },
                            options: {
                                scales: {
                                    y: {
                                        beginAtZero: true
                                    }
                                }
                            }
                        });
                    }
                }
            });
        }
        loadEarningsChart();
    }

    // Dismiss vendor notification
    document.addEventListener('click', function (e) {
        if (e.target && e.target.classList.contains('btn-dismiss-notification')) {
            e.stopPropagation();
            const notifDiv = e.target.closest('[data-notification-id]');
            const notifId = notifDiv.dataset.notificationId;

            if (!notifId) return;

            fetch(`${ksc_dashboard_vars.vendor_notifications_url}${notifId}`, {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: { 'X-WP-Nonce': REST_API_NONCE }
            })
                .then(response => {
                    if (!response.ok) throw new Error('Failed to delete');
                    return response.json();
                })
                .then(() => {
                    notifDiv.style.transition = 'opacity 0.3s';
                    notifDiv.style.opacity = '0';
                    setTimeout(() => {
                        notifDiv.remove();
                        const notifCount = document.getElementById('notif-count');
                        const currentCount = parseInt(notifCount.textContent);
                        if (currentCount > 0) {
                            notifCount.textContent = currentCount - 1;
                        }
                    }, 300);
                })
                .catch(error => {
                    alert('Error deleting notification');
                    console.error('Delete notification error:', error);
                });
        }
    });

    // Vendor step upload form
    const uploadForm = document.querySelector('.ajax-upload-form');
    if (uploadForm) {
        uploadForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);
            const submitBtn = form.querySelector('.btn-upload');
            const statusDiv = form.querySelector('.upload-status');

            formData.append('action', 'vendor_upload_step');

            submitBtn.disabled = true;
            submitBtn.textContent = '⏳...';
            statusDiv.className = 'upload-status loading';
            statusDiv.textContent = 'Uploading...';
            statusDiv.style.display = 'block';

            fetch(ksc_dashboard_vars.admin_ajax_url, {
                method: 'POST',
                body: formData,
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        statusDiv.className = 'upload-status success';
                        statusDiv.textContent = `✅ ${data.data.message}`;
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        throw new Error(data.data.message);
                    }
                })
                .catch(error => {
                    statusDiv.className = 'upload-status error';
                    statusDiv.textContent = `❌ ${error.message || 'Upload failed'}`;
                    submitBtn.disabled = false;
                    submitBtn.textContent = '📂 Submit';
                });
        });
    }
});
// --- Vendor Profile & Coverage Logic ---

jQuery(document).ready(function ($) {

    // 1. Profile Update
    $('#vendor-profile-form').on('submit', function (e) {
        e.preventDefault();
        const btn = $(this).find('button[type="submit"]');

        $.ajax({
            url: ksc_dashboard_vars.admin_ajax_url,
            type: 'POST',
            data: {
                action: 'update_vendor_profile',
                nonce: REST_API_NONCE, // Using REST nonce as general nonce for now, or add specific one
                company_name: $('#profile-company').val(),
                phone: $('#profile-phone').val()
            },
            beforeSend: function () {
                btn.prop('disabled', true).text('Updating...');
            },
            success: function (response) {
                if (response.success) {
                    alert('Profile updated successfully!');
                } else {
                    alert('Error: ' + response.data.message);
                }
                btn.prop('disabled', false).text('Update Profile');
            },
            error: function () {
                alert('An error occurred.');
                btn.prop('disabled', false).text('Update Profile');
            }
        });
    });

    // 2. Coverage Logic
    let coverageData = null;
    const STATE_PRICE = 500;
    const CITY_PRICE = 100;

    // Load coverage data when Profile section is opened
    window.loadCoverageData = function () {
        if (coverageData) {
            console.log('Coverage data already loaded', coverageData);
            return; // Already loaded
        }

        console.log('Loading coverage data from server...');
        $.ajax({
            url: ksc_dashboard_vars.admin_ajax_url,
            type: 'GET',
            data: { action: 'get_coverage_areas' },
            success: function (response) {
                console.log('Coverage areas response:', response);
                if (response.success) {
                    coverageData = response.data;
                    console.log('Coverage data loaded:', coverageData);
                    initializeCoverageSelection();
                } else {
                    console.error('Coverage areas error:', response.data);
                }
            },
            error: function (xhr, status, error) {
                console.error('AJAX error loading coverage:', error, xhr);
            }
        });
    };

    // Hook into section switch to load data
    const originalSwitchVendorSection = window.switchVendorSection;
    window.switchVendorSection = function (event, section) {
        originalSwitchVendorSection(event, section);
        if (section === 'profile') {
            loadCoverageData();
        }
    };

    function initializeCoverageSelection() {
        console.log('initializeCoverageSelection called');
        console.log('coverageData:', coverageData);
        console.log('vendorCoverage:', typeof vendorCoverage !== 'undefined' ? vendorCoverage : 'UNDEFINED');

        if (!coverageData || typeof vendorCoverage === 'undefined') {
            console.error('Missing data - coverageData:', coverageData, 'vendorCoverage:', typeof vendorCoverage);
            return;
        }

        const stateSelect = $('#coverage-state-select');
        console.log('State select element:', stateSelect.length);
        stateSelect.empty().append(new Option('-- Choose a State --', ''));

        // Populate ALL States
        coverageData.forEach(function (stateObj) {
            stateSelect.append(new Option(stateObj.state, stateObj.state));
        });

        console.log('States populated:', coverageData.length);

        // Handle State Selection
        stateSelect.on('change', handleStateChange);

        // Handle Buy State Checkbox
        $('#buy-state-checkbox').on('change', updateCartSummary);
    }

    function handleStateChange() {
        const selectedState = $(this).val();
        const citySelectionContainer = $('#city-selection-container');
        const cityCheckboxes = $('#city-checkboxes');
        const ownedStateMsg = $('#owned-state-msg');

        // Reset UI
        cityCheckboxes.empty();

        // Hide state options container as we removed the checkbox
        $('#state-options-container').hide();

        if (!selectedState) {
            citySelectionContainer.hide();
            updateCartSummary();
            return;
        }

        citySelectionContainer.show();

        // Check if State is Owned
        const isOwned = vendorCoverage.ownedStates.includes(selectedState);

        if (isOwned) {
            ownedStateMsg.show();
            // If owned, we don't charge for state, just show message
            $('#state-options-container').show();
            $('#buy-state-option').hide();
        } else {
            ownedStateMsg.hide();
            // If not owned, we charge 500 automatically
        }

        // Populate Cities
        const stateObj = coverageData.find(s => s.state === selectedState);
        if (stateObj && stateObj.districts) {
            stateObj.districts.forEach(city => {
                // Only show cities NOT owned
                if (!vendorCoverage.ownedCities.includes(city)) {
                    const checkboxId = `city-${city.replace(/\s+/g, '-')}`;
                    const cityHtml = `
                        <label class="custom-checkbox" style="display: flex; align-items: center; cursor: pointer; font-size: 13px;">
                            <input type="checkbox" class="city-checkbox" value="${city}" style="width: 16px; height: 16px; margin-right: 8px;">
                            ${city}
                        </label>
                    `;
                    cityCheckboxes.append(cityHtml);
                }
            });
        }

        if (cityCheckboxes.children().length === 0) {
            cityCheckboxes.html('<p style="color:#999; grid-column:1/-1;">All cities in this state are already owned.</p>');
        }

        // Attach event listeners to new checkboxes
        $('.city-checkbox').on('change', updateCartSummary);

        updateCartSummary();
    }

    function updateCartSummary() {
        const selectedState = $('#coverage-state-select').val();
        const isOwned = vendorCoverage.ownedStates.includes(selectedState);

        const selectedCities = [];
        $('.city-checkbox:checked').each(function () {
            selectedCities.push($(this).val());
        });

        const cartItemsContainer = $('#cart-items');
        cartItemsContainer.empty();

        let total = 0;

        // Mandatory State Fee if not owned
        if (selectedState && !isOwned) {
            total += STATE_PRICE;
            cartItemsContainer.append(`
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px;">
                    <span>State Fee: <strong>${selectedState}</strong></span>
                    <span>₹${STATE_PRICE}</span>
                </div>
            `);
        }

        if (selectedCities.length > 0) {
            const citiesCost = selectedCities.length * CITY_PRICE;
            total += citiesCost;
            cartItemsContainer.append(`
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px;">
                    <span>Cities (${selectedCities.length})</span>
                    <span>₹${citiesCost}</span>
                </div>
                <div style="font-size: 12px; color: #666; margin-left: 10px; margin-bottom: 10px;">
                    ${selectedCities.join(', ')}
                </div>
            `);
        }

        if (total === 0) {
            cartItemsContainer.html('<p style="color: #999; text-align: center; margin-top: 30px;">No items selected</p>');
        }

        $('#cart-total').text('₹' + total);
        $('#pay-add-coverage-btn').prop('disabled', total === 0);
    }

    // 3. Payment & Add Coverage
    $('#pay-add-coverage-btn').on('click', function () {
        const selectedState = $('#coverage-state-select').val();
        const isOwned = vendorCoverage.ownedStates.includes(selectedState);
        const states = (selectedState && !isOwned) ? [selectedState] : [];
        const cities = [];
        $('.city-checkbox:checked').each(function () {
            cities.push($(this).val());
        });

        const totalAmount = (states.length * STATE_PRICE) + (cities.length * CITY_PRICE);

        if (totalAmount === 0) return;

        // Create Order
        $.ajax({
            url: ksc_dashboard_vars.admin_ajax_url,
            type: 'POST',
            data: {
                action: 'create_razorpay_order',
                nonce: ksc_dashboard_vars.vendor_coverage_nonce,  // Fixed: use correct nonce
                amount: totalAmount,
                currency: 'INR'
            },
            success: function (response) {
                if (response.success) {
                    const options = {
                        key: response.data.key,
                        amount: response.data.amount,
                        currency: response.data.currency,
                        order_id: response.data.order_id,
                        name: 'Solar Marketplace',
                        description: 'Add Coverage Area',
                        handler: function (paymentResponse) {
                            verifyAndAddCoverage(paymentResponse, states, cities, totalAmount);
                        },
                        prefill: {
                            name: vendorCoverage.userName,
                            email: vendorCoverage.userEmail,
                            contact: vendorCoverage.userPhone
                        },
                        theme: { color: "#3399cc" }
                    };
                    const rzp = new Razorpay(options);
                    rzp.open();
                } else {
                    alert('Error creating order: ' + response.data.message);
                }
            }
        });
    });

    function verifyAndAddCoverage(paymentResponse, states, cities, amount) {
        $.ajax({
            url: ksc_dashboard_vars.admin_ajax_url,
            type: 'POST',
            data: {
                action: 'add_vendor_coverage',
                nonce: ksc_dashboard_vars.vendor_coverage_nonce,  // Fixed: use correct nonce
                payment_response: JSON.stringify(paymentResponse),
                states: states,
                cities: cities,
                amount: amount
            },
            success: function (response) {
                if (response.success) {
                    alert('Coverage added successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + response.data.message);
                }
            }
        });
    }

});
