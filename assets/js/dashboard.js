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
            $.ajax({
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
