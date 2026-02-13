/**
 * Sales Manager Dashboard JavaScript
 * 
 * Handles all frontend interactions for the Sales Manager dashboard.
 * 
 * @package Krtrim_Solar_Core
 * @since 1.0.0
 */

(function ($) {
    'use strict';

    // Activity type icons
    const activityIcons = {
        'phone_call': '📞',
        'whatsapp': '💬',
        'email': '📧',
        'meeting_offline': '🤝',
        'meeting_online': '💻',
        'site_visit': '🏠',
        'other': '📝'
    };

    const activityLabels = {
        'phone_call': 'Phone Call',
        'whatsapp': 'WhatsApp',
        'email': 'Email',
        'meeting_offline': 'Offline Meeting',
        'meeting_online': 'Online Meeting',
        'site_visit': 'Site Visit',
        'other': 'Other'
    };

    // Track if lead component is initialized
    let leadComponentInitialized = false;

    // Initialize when document is ready
    $(document).ready(function () {
        // Initialize Lead Component early to set config
        initLeadComponent();

        initNotifications();
        loadDashboardStats();
        loadTodayFollowups();
        loadSMCleaners(); // Pre-load cleaners for booking
        initModals(); // Initialize global modal handlers
    });

    // --- Tab Data Loading Triggers ---
    $(document).on('click', '.nav-item[data-section="cleaning-services"]', function () {
        loadSMCleaningServices();
    });

    $(document).on('click', '.nav-item[data-section="conversions"]', function () {
        loadConversions();
    });

    $(document).on('click', '.nav-item[data-section="my-leads"]', function () {
        initLeadComponent();
    });

    // --- Notification System ---
    function initNotifications() {
        // Load notifications on page load
        loadNotifications();
        // Refresh every 30 seconds
        setInterval(loadNotifications, 30000);

        // Toggle notification panel
        $(document).on('click', '#notification-toggle', function () {
            $('#notification-panel').toggleClass('open');
        });

        // Close notification panel
        $(document).on('click', '#close-notification-panel', function () {
            $('#notification-panel').removeClass('open');
        });
    }

    function loadNotifications() {
        $.ajax({
            url: sm_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'get_user_notifications'
            },
            success: function (response) {
                if (response.success) {
                    const notifications = response.data || [];
                    const notifList = $('#notif-list');
                    const notifCount = $('#notif-count');

                    if (notifications.length > 0) {
                        let html = '';
                        notifications.forEach(n => {
                            const borderColor = n.type === 'approved' ? '#28a745' : n.type === 'rejected' ? '#dc3545' : '#007bff';
                            const bgColor = n.type === 'approved' ? '#f8fff9' : n.type === 'rejected' ? '#fff5f5' : '#f0f7ff';

                            html += `<div class="notification-item" style="padding:12px;position:relative; border-radius:8px; border-left:4px solid ${borderColor}; background:${bgColor}; margin-bottom:10px;">`;
                            html += `<div style="font-weight:600; color:#333;">${n.icon || '🔔'} ${n.title}</div>`;
                            html += `<div style="font-size:12px; color:#666; margin-top:4px;">${n.message}</div>`;
                            if (n.time_ago) {
                                html += `<div style="font-size:10px; color:#999; margin-top:4px;">${n.time_ago}</div>`;
                            }
                            html += `</div>`;
                        });
                        notifList.html(html);
                        notifCount.text(notifications.length).show();
                    } else {
                        notifList.html('<p style="text-align:center; color:#999; padding: 20px; margin: 0;">No new notifications</p>');
                        notifCount.hide();
                    }
                } else {
                    $('#notif-list').html('<p style="color:#dc3545; text-align:center; padding: 20px; margin:0;">Error loading notifications</p>');
                }
            },
            error: function () {
                $('#notif-list').html('<p style="color:#dc3545; text-align:center; padding: 20px; margin:0;">Error loading notifications</p>');
            }
        });
    }

    // Navigation (sidebar)
    // Delegated to DashboardUtils
    $(document).ready(function () {
        if (typeof DashboardUtils !== 'undefined') {
            DashboardUtils.setupTabNavigation('.sales-manager-dashboard');

            // Also handle mobile nav if needed, or assume DashboardUtils handles .nav-item globally
            // DashboardUtils targets .nav-item which covers both sidebar and likely mobile if class matches
            // However, mobile nav uses .mobile-nav-item in this file. 
            // Let's add specific handler for mobile nav to use DashboardUtils logic manually or add to DashboardUtils?
            // For now, let's keep it simple and bind click to trigger dashboard utils logic
            $('.mobile-nav-item').on('click', function (e) {
                e.preventDefault();
                const section = $(this).data('section');
                if (section) {
                    // Trigger click on corresponding sidebar item to reuse DashboardUtils logic
                    $(`.sidebar-nav .nav-item[data-section="${section}"]`).click();
                }
            });
        }
    });

    // navigateToSection is removed as DashboardUtils handles it via triggerSectionLoad

    // Initialize shared lead component
    function initLeadComponent() {
        if (!leadComponentInitialized && typeof window.initLeadComponent === 'function') {
            window.initLeadComponent(sm_vars.ajax_url, sm_vars.nonce);
            leadComponentInitialized = true;
        } else if (typeof window.loadLeads === 'function') {
            window.loadLeads();
        }
    }

    // Load dashboard stats
    function loadDashboardStats() {
        $.ajax({
            url: sm_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'get_sales_manager_stats'
            },
            success: function (response) {
                if (response.success) {
                    const data = response.data;
                    $('#total-leads-stat').text(data.total_leads);
                    $('#pending-followups-stat').text(data.pending_followups);
                    $('#converted-leads-stat').text(data.converted_leads);
                    $('#conversion-rate-stat').text(data.conversion_rate + '%');

                    // Update charts
                    updateLeadStatusChart(data.lead_statuses);
                }
            }
        });
    }

    // Load today's follow-ups
    function loadTodayFollowups() {
        $.ajax({
            url: sm_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'get_today_followups'
            },
            success: function (response) {
                if (response.success) {
                    renderTodayFollowups(response.data.followups);
                }
            }
        });
    }

    function renderTodayFollowups(followups) {
        const container = $('#today-followups-container');

        if (!followups || followups.length === 0) {
            container.html(`
                <div class="empty-state">
                    <div class="icon">📅</div>
                    <h3>No follow-ups today</h3>
                    <p>Add follow-ups to your leads to track them here</p>
                </div>
            `);
            return;
        }

        let html = '<div class="followup-timeline">';
        followups.forEach(f => {
            html += `
                <div class="followup-item type-${f.activity_type}">
                    <div class="followup-header">
                        <span class="followup-type">${activityIcons[f.activity_type] || '📝'} ${activityLabels[f.activity_type] || f.activity_type}</span>
                        <span class="followup-date">${formatTime(f.activity_date)}</span>
                    </div>
                    <div class="followup-notes">${f.lead_name}: ${escapeHtml(f.notes)}</div>
                </div>
            `;
        });
        html += '</div>';
        container.html(html);
    }

    // Load my leads
    function loadMyLeads(status = '', search = '') {
        $('#my-leads-container').html('<p>Loading leads...</p>');

        $.ajax({
            url: sm_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'get_sales_manager_leads',
                status: status,
                search: search
            },
            success: function (response) {
                if (response.success) {
                    renderLeads(response.data.leads);
                } else {
                    $('#my-leads-container').html('<p>Error loading leads</p>');
                }
            }
        });
    }

    function renderLeads(leads) {
        const container = $('#my-leads-container');

        if (!leads || leads.length === 0) {
            container.html(`
                <div class="empty-state">
                    <div class="icon">👥</div>
                    <h3>No leads yet</h3>
                    <p>Start adding leads to grow your pipeline</p>
                    <button class="btn btn-primary" onclick="document.getElementById('btn-add-lead-open').click()">➕ Add First Lead</button>
                </div>
            `);
            return;
        }

        let html = '';
        leads.forEach(lead => {
            const followupsHtml = renderLeadFollowupThread(lead.followups || []);
            const followupCount = (lead.followups || []).length;

            html += `
                <div class="lead-card status-${lead.status}" data-lead-id="${lead.id}">
                    <div class="lead-card-header">
                        <h4>${escapeHtml(lead.name)}</h4>
                        <span class="lead-status-badge ${lead.status}">${lead.status}</span>
                    </div>
                    <div class="lead-card-info">
                        <span>📞 ${escapeHtml(lead.phone)}</span>
                        ${lead.email ? `<span>📧 ${escapeHtml(lead.email)}</span>` : ''}
                        <span>📅 ${lead.created_date}</span>
                    </div>
                    <div class="lead-card-actions">
                        <button class="btn-followup" onclick="openFollowupModal(${lead.id}, '${escapeHtml(lead.name)}')">📞 Add Follow-up</button>
                        <a href="https://wa.me/91${lead.phone.replace(/\D/g, '')}" target="_blank" class="btn-whatsapp">💬 WhatsApp</a>
                        <a href="tel:${lead.phone}" class="btn-call">📞 Call</a>
                        <button class="btn btn-secondary" onclick="viewLeadDetails(${lead.id})">👁️ Details</button>
                    </div>
                    ${followupCount > 0 ? `
                    <div class="lead-thread">
                        <button class="thread-toggle" onclick="toggleThread(this)">
                            💬 ${followupCount} Follow-up${followupCount > 1 ? 's' : ''} <span class="toggle-icon">▼</span>
                        </button>
                        <div class="thread-content" style="display: none;">
                            ${followupsHtml}
                        </div>
                    </div>
                    ` : `
                    <div class="lead-thread no-thread">
                        <span style="color: #9ca3af; font-size: 13px;">💬 No follow-ups yet - start the conversation!</span>
                    </div>
                    `}
                </div>
            `;
        });
        container.html(html);
    }

    // Render follow-up thread for a lead
    function renderLeadFollowupThread(followups) {
        if (!followups || followups.length === 0) return '';

        let html = '<div class="thread-timeline">';
        followups.forEach(f => {
            html += `
                <div class="thread-item type-${f.activity_type}">
                    <div class="thread-icon">${activityIcons[f.activity_type] || '📝'}</div>
                    <div class="thread-content-inner">
                        <div class="thread-header">
                            <span class="thread-type">${activityLabels[f.activity_type] || f.activity_type}</span>
                            <span class="thread-date">${formatDateTime(f.activity_date)}</span>
                        </div>
                        <div class="thread-notes">${escapeHtml(f.notes)}</div>
                    </div>
                </div>
            `;
        });
        html += '</div>';
        return html;
    }

    // Toggle follow-up thread visibility
    function toggleThread(btn) {
        const content = $(btn).siblings('.thread-content');
        const icon = $(btn).find('.toggle-icon');
        if (content.is(':visible')) {
            content.slideUp(200);
            icon.text('▼');
        } else {
            content.slideDown(200);
            icon.text('▲');
        }
    }
    window.toggleThread = toggleThread;

    // Lead search and filter
    $('#lead-search').on('input', debounce(function () {
        loadMyLeads($('#filter-lead-status').val(), $(this).val());
    }, 300));

    $('#filter-lead-status').on('change', function () {
        loadMyLeads($(this).val(), $('#lead-search').val());
    });

    // Load follow-ups history
    function loadFollowupsHistory(type = '') {
        $('#followups-history-container').html('<p>Loading follow-up history...</p>');

        $.ajax({
            url: sm_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'get_all_followups_history',
                type: type
            },
            success: function (response) {
                if (response.success) {
                    renderFollowupsHistory(response.data.followups);
                }
            }
        });
    }

    $('#filter-followup-type').on('change', function () {
        loadFollowupsHistory($(this).val());
    });

    function renderFollowupsHistory(followups) {
        const container = $('#followups-history-container');

        if (!followups || followups.length === 0) {
            container.html(`
                <div class="empty-state">
                    <div class="icon">📞</div>
                    <h3>No follow-ups recorded</h3>
                    <p>Start adding follow-ups to your leads</p>
                </div>
            `);
            return;
        }

        let html = '<div class="followup-timeline">';
        followups.forEach(f => {
            html += `
                <div class="followup-item type-${f.activity_type}">
                    <div class="followup-header">
                        <span class="followup-type">${activityIcons[f.activity_type] || '📝'} ${activityLabels[f.activity_type] || f.activity_type} - ${escapeHtml(f.lead_name)}</span>
                        <span class="followup-date">${formatDateTime(f.activity_date)}</span>
                    </div>
                    <div class="followup-notes">${escapeHtml(f.notes)}</div>
                </div>
            `;
        });
        html += '</div>';
        container.html(html);
    }

    // Load conversions
    function loadConversions() {
        $('#conversions-container').html('<p>Loading conversions...</p>');

        $.ajax({
            url: sm_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'get_sales_manager_conversions'
            },
            success: function (response) {
                if (response.success) {
                    renderConversions(response.data.conversions);
                }
            }
        });
    }

    function renderConversions(conversions) {
        const container = $('#conversions-container');

        if (!conversions || conversions.length === 0) {
            container.html(`
                <div class="empty-state">
                    <div class="icon">✅</div>
                    <h3>No conversions yet</h3>
                    <p>Keep following up with your leads to convert them!</p>
                </div>
            `);
            return;
        }

        let html = '';
        conversions.forEach(c => {
            html += `
                <div class="conversion-card">
                    <div>
                        <h4>✅ ${escapeHtml(c.name)}</h4>
                        <div class="lead-card-info">
                            <span>📞 ${escapeHtml(c.phone)}</span>
                            ${c.email ? `<span>📧 ${escapeHtml(c.email)}</span>` : ''}
                        </div>
                    </div>
                    <div class="conversion-date">
                        Converted: ${formatDate(c.conversion_date)}
                    </div>
                </div>
            `;
        });
        container.html(html);
    }

    // Modals
    function initModals() {
        // Close modal on X click
        $(document).on('click', '.close-modal, .close-lead-modal', function () {
            $(this).closest('.modal, .lead-modal').css('display', 'none');
        });

        // Close modal on background click
        $(document).on('click', '.modal, .lead-modal', function (e) {
            if (e.target === this) {
                $(this).css('display', 'none');
            }
        });
    }

    // Make function globally accessible
    window.openFollowupModal = function (leadId, leadName) {
        $('#followup_lead_id').val(leadId);
        $('#add-followup-modal h3').text('📞 Add Follow-up for ' + leadName);
        $('#followup_date').val(getCurrentDateTime());
        $('#add-followup-modal').css('display', 'flex');
    };

    window.viewLeadDetails = function (leadId) {
        $.ajax({
            url: sm_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'get_lead_details_for_sm',
                lead_id: leadId
            },
            success: function (response) {
                if (response.success) {
                    renderLeadDetailModal(response.data);
                }
            }
        });
    };

    function renderLeadDetailModal(data) {
        const lead = data.lead;
        const followups = data.followups;

        $('#lead-detail-name').text(lead.name);

        let html = `
            <div style="display: grid; gap: 12px; margin-bottom: 20px;">
                <p><strong>📞 Phone:</strong> ${escapeHtml(lead.phone)}</p>
                ${lead.email ? `<p><strong>📧 Email:</strong> ${escapeHtml(lead.email)}</p>` : ''}
                ${lead.address ? `<p><strong>📍 Address:</strong> ${escapeHtml(lead.address)}</p>` : ''}
                <p><strong>📊 Status:</strong> <span class="lead-status-badge ${lead.status}">${lead.status}</span></p>
                <p><strong>📅 Created:</strong> ${formatDate(lead.created_date)}</p>
            </div>
            <h4>📞 Follow-up History</h4>
        `;

        if (followups && followups.length > 0) {
            html += '<div class="followup-timeline">';
            followups.forEach(f => {
                html += `
                    <div class="followup-item type-${f.activity_type}">
                        <div class="followup-header">
                            <span class="followup-type">${activityIcons[f.activity_type] || '📝'} ${activityLabels[f.activity_type] || f.activity_type}</span>
                            <span class="followup-date">${formatDateTime(f.activity_date)}</span>
                        </div>
                        <div class="followup-notes">${escapeHtml(f.notes)}</div>
                    </div>
                `;
            });
            html += '</div>';
        } else {
            html += '<p style="color: #9ca3af;">No follow-ups yet</p>';
        }

        $('#lead-detail-content').html(html);

        // Set up action buttons
        $('#btn-add-followup').off('click').on('click', function () {
            $('#lead-detail-modal').css('display', 'none');
            openFollowupModal(lead.id, lead.name);
        });

        $('#btn-whatsapp-lead').attr('href', `https://wa.me/91${lead.phone.replace(/\D/g, '')}`);
        $('#btn-call-lead').attr('href', `tel:${lead.phone}`);

        $('#btn-mark-converted').off('click').on('click', function () {
            if (confirm('Mark this lead as converted? This will credit you for the conversion.')) {
                markLeadConverted(lead.id);
            }
        });

        $('#lead-detail-modal').css('display', 'flex');
    }

    function markLeadConverted(leadId) {
        $.ajax({
            url: sm_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'mark_lead_converted',
                lead_id: leadId
            },
            success: function (response) {
                if (response.success) {
                    showToast('Lead marked as converted! 🎉', 'success');
                    $('#lead-detail-modal').css('display', 'none');
                    loadMyLeads();
                    loadDashboardStats();
                } else {
                    showToast(response.data.message || 'Error', 'error');
                }
            }
        });
    }

    // Forms
    function initForms() {
        // Create lead form
        $('#create-lead-form').on('submit', function (e) {
            e.preventDefault();

            const formData = $(this).serialize() + '&action=create_lead_by_sales_manager';

            $.ajax({
                url: sm_vars.ajax_url,
                type: 'POST',
                data: formData,
                success: function (response) {
                    if (response.success) {
                        showToast('Lead created successfully! 🎉', 'success');
                        $('#create-lead-form')[0].reset();
                        $('#add-lead-modal').css('display', 'none');
                        loadMyLeads();
                    } else {
                        showToast(response.data.message || 'Error creating lead', 'error');
                    }
                },
                error: function () {
                    showToast('Network error. Please try again.', 'error');
                }
            });
        });

        // Add follow-up form
        $('#add-followup-form').on('submit', function (e) {
            e.preventDefault();

            const formData = {
                action: 'add_lead_followup',
                lead_id: $('#followup_lead_id').val(),
                activity_type: $('#followup_type').val(),
                activity_date: $('#followup_date').val(),
                notes: $('#followup_notes').val()
            };

            $.ajax({
                url: sm_vars.ajax_url,
                type: 'POST',
                data: formData,
                success: function (response) {
                    if (response.success) {
                        showToast('Follow-up added! 📞', 'success');
                        $('#add-followup-modal').css('display', 'none');
                        $('#add-followup-form')[0].reset();
                        loadMyLeads();
                        loadTodayFollowups();
                    } else {
                        showToast(response.data.message || 'Error', 'error');
                    }
                }
            });
        });
    }

    // Charts
    function updateLeadStatusChart(statuses) {
        const ctx = document.getElementById('lead-status-chart');
        if (!ctx) return;

        // Destroy existing chart
        if (window.leadStatusChart) {
            window.leadStatusChart.destroy();
        }

        window.leadStatusChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['New', 'Contacted', 'Interested', 'Converted', 'Lost'],
                datasets: [{
                    data: [
                        statuses.new || 0,
                        statuses.contacted || 0,
                        statuses.interested || 0,
                        statuses.converted || 0,
                        statuses.lost || 0
                    ],
                    backgroundColor: ['#3b82f6', '#f59e0b', '#8b5cf6', '#10b981', '#ef4444']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }

    // Utility functions
    // Utility functions
    // Delegated to DashboardUtils
    function showToast(message, type = 'info') {
        if (typeof DashboardUtils !== 'undefined') {
            DashboardUtils.showToast(message, type);
        } else {
            console.error('DashboardUtils not loaded');
            alert(message);
        }
    }
    // Expose globally for shared components
    window.showToast = showToast;

    function formatDate(dateStr) {
        if (!dateStr) return '-';
        const date = new Date(dateStr);
        return date.toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
    }

    function formatTime(dateStr) {
        if (!dateStr) return '-';
        const date = new Date(dateStr);
        return date.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' });
    }

    function formatDateTime(dateStr) {
        if (!dateStr) return '-';
        const date = new Date(dateStr);
        return date.toLocaleString('en-IN', {
            day: 'numeric',
            month: 'short',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function getCurrentDateTime() {
        const now = new Date();
        return now.toISOString().slice(0, 16);
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // ===============================
    // MANUAL BOOKING FUNCTIONALITY
    // ===============================

    // Open Book Cleaning Modal
    $(document).on('click', '.btn-book-cleaning', function (e) {
        e.preventDefault();
        e.stopPropagation();
        const leadId = $(this).data('lead-id');
        const leadName = $(this).data('lead-name');

        $('#book_lead_id').val(leadId);
        $('#book_customer_name').text(leadName);

        // Populate cleaners
        const cleanerSelect = $('#book_cleaner_id');
        cleanerSelect.empty().append('<option value="">Select Cleaner</option>');

        // Always try to load if empty or just to be safe
        if (smCleanersList && smCleanersList.length > 0) {
            smCleanersList.forEach(c => {
                cleanerSelect.append(`<option value="${c.id}">${escapeHtml(c.name)}</option>`);
            });
        } else {
            cleanerSelect.append('<option value="" disabled>Loading cleaners...</option>');
            // Fetch again
            $.ajax({
                url: sm_vars.ajax_url,
                type: 'POST',
                data: { action: 'get_cleaners' },
                success: function (response) {
                    cleanerSelect.empty().append('<option value="">Select Cleaner</option>');
                    if (response.success && response.data.length > 0) {
                        smCleanersList = response.data; // Update global list
                        response.data.forEach(c => {
                            cleanerSelect.append(`<option value="${c.id}">${escapeHtml(c.name)}</option>`);
                        });
                    } else {
                        cleanerSelect.append('<option value="" disabled>No cleaners found in your area</option>');
                    }
                },
                error: function () {
                    cleanerSelect.empty().append('<option value="" disabled>Error loading cleaners</option>');
                }
            });
        }

        // Set default date
        const today = new Date().toISOString().split('T')[0];
        $('#book_visit_date').val(today);

        $('#book-cleaning-modal').css('display', 'flex');
    });

    // Handle Book Cleaning Form
    initBookCleaningForm();

    function initBookCleaningForm() {
        $('#book-cleaning-form').on('submit', function (e) {
            e.preventDefault();
            const $form = $(this);
            const $btn = $form.find('button[type="submit"]');

            // Basic validation
            if (!$('#book_cleaner_id').val()) {
                showToast('Please select a cleaner', 'error');
                return;
            }

            const formData = $form.serialize() + '&action=book_cleaning_service';

            $.ajax({
                url: sm_vars.ajax_url,
                type: 'POST',
                data: formData,
                beforeSend: function () {
                    $btn.prop('disabled', true).text('Booking...');
                },
                success: function (response) {
                    if (response.success) {
                        showToast('Cleaning service booked successfully! 🎉', 'success');
                        $('#book-cleaning-modal').css('display', 'none');
                        $form[0].reset();
                        // Refresh services list
                        loadSMCleaningServices();
                    } else {
                        showToast(response.data.message || 'Error booking service', 'error');
                    }
                },
                error: function () {
                    showToast('Network error', 'error');
                },
                complete: function () {
                    $btn.prop('disabled', false).text('✅ Book Service');
                }
            });
        });
    }

    // ===============================
    // CLEANING SERVICES FUNCTIONALITY
    // ===============================

    let smCleanersList = [];

    function loadSMCleaningServices() {
        const tbody = $('#sm-cleaning-services-tbody');
        tbody.html('<tr><td colspan="6">Loading...</td></tr>');

        $.ajax({
            url: sm_vars.ajax_url,
            type: 'POST',
            data: { action: 'get_cleaning_services' },
            success: function (response) {
                if (response.success) {
                    renderSMCleaningServices(response.data);
                } else {
                    tbody.html('<tr><td colspan="6">Error loading services</td></tr>');
                }
            },
            error: function () {
                tbody.html('<tr><td colspan="6">Error loading services</td></tr>');
            }
        });

        // Load cleaners for scheduling
        loadSMCleaners();
    }

    function loadSMCleaners() {
        $.ajax({
            url: sm_vars.ajax_url,
            type: 'POST',
            data: { action: 'get_cleaners' },
            success: function (response) {
                if (response.success) {
                    smCleanersList = response.data;
                }
            },
            error: function (xhr, status, error) {
                console.error('❌ [SM Dashboard] get_cleaners AJAX Error:', error);
            }
        });
    }

    function renderSMCleaningServices(services) {
        const tbody = $('#sm-cleaning-services-tbody');

        if (!services || services.length === 0) {
            tbody.html('<tr><td colspan="6">No cleaning services from your leads yet.</td></tr>');
            return;
        }

        const planLabels = {
            'one_time': 'One-Time',
            'monthly': 'Monthly',
            '6_month': '6-Month',
            'yearly': 'Yearly'
        };

        let html = '';
        services.forEach(s => {
            const paymentBadge = s.payment_status === 'paid'
                ? '<span style="background:#d1fae5;color:#047857;padding:4px 8px;border-radius:4px;">Paid</span>'
                : '<span style="background:#fef3c7;color:#b45309;padding:4px 8px;border-radius:4px;">Pending</span>';

            html += `
                <tr class="sm-cleaning-service-row" data-id="${s.id}" style="cursor:pointer;">
                    <td><strong>${s.customer_name}</strong><br><small>${s.customer_phone}</small></td>
                    <td>${planLabels[s.plan_type] || s.plan_type}</td>
                    <td>${s.system_size_kw} kW</td>
                    <td>${s.visits_used || 0}/${s.visits_total || 1}</td>
                    <td>${paymentBadge}</td>
                    <td>
                        ${s.next_visit_date
                    ? `<span style="color:#4f46e5;">✓ ${s.next_visit_date}</span>`
                    : s.preferred_date
                        ? `<span style="color:#b45309;">⏳ ${s.preferred_date}</span><br><button class="btn btn-sm sm-schedule-visit-btn" data-id="${s.id}" data-name="${s.customer_name}">+ Schedule</button>`
                        : `<button class="btn btn-sm sm-schedule-visit-btn" data-id="${s.id}" data-name="${s.customer_name}">+ Schedule</button>`}
                    </td>
                </tr>
            `;
        });
        tbody.html(html);
    }

    // Open schedule visit modal for SM
    $(document).on('click', '.sm-schedule-visit-btn', function (e) {
        e.preventDefault();
        e.stopPropagation();

        const serviceId = $(this).data('id');
        const customerName = $(this).data('name');

        $('#schedule_service_id').val(serviceId);
        $('#schedule_customer_name').text(customerName);

        // Set minimum date to today
        const today = new Date().toISOString().split('T')[0];
        $('#schedule_date').attr('min', today).val(today);

        // Populate cleaners dropdown
        const select = $('#schedule_cleaner_id');
        select.empty().append('<option value="">Select Cleaner</option>');

        if (smCleanersList && smCleanersList.length > 0) {
            smCleanersList.forEach(cleaner => {
                select.append(`<option value="${cleaner.id}">${escapeHtml(cleaner.name)} (📞 ${cleaner.phone})</option>`);
            });
        } else {
            select.append('<option value="" disabled>Loading cleaners...</option>');
            // Fetch again
            $.ajax({
                url: sm_vars.ajax_url,
                type: 'POST',
                data: { action: 'get_cleaners' },
                success: function (response) {
                    select.empty().append('<option value="">Select Cleaner</option>');
                    if (response.success && response.data.length > 0) {
                        smCleanersList = response.data; // Update global list
                        response.data.forEach(cleaner => {
                            select.append(`<option value="${cleaner.id}">${escapeHtml(cleaner.name)} (📞 ${cleaner.phone})</option>`);
                        });
                    } else {
                        select.append('<option value="" disabled>No cleaners found in your area</option>');
                    }
                },
                error: function () {
                    select.append('<option value="" disabled>Error loading cleaners</option>');
                }
            });
        }

        $('#schedule-visit-feedback').html('');
        $('#schedule-visit-modal').css('display', 'flex');
    });

    // Close modals
    // Handled by global .close-modal click in initModals()

    // Submit schedule visit form
    $('#schedule-visit-form').on('submit', function (e) {
        e.preventDefault();

        const form = $(this);
        const feedback = $('#schedule-visit-feedback');
        const submitBtn = form.find('button[type="submit"]');

        const serviceId = $('#schedule_service_id').val();
        const cleanerId = $('#schedule_cleaner_id').val();
        const scheduledDate = $('#schedule_date').val();
        const scheduledTime = $('#schedule_time').val();

        if (!cleanerId) {
            feedback.html('<div style="background:#f8d7da;color:#721c24;padding:10px;border-radius:6px;">Please select a cleaner</div>');
            return;
        }

        submitBtn.prop('disabled', true).text('Scheduling...');
        feedback.html('');

        $.ajax({
            url: sm_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'schedule_cleaning_visit',
                service_id: serviceId,
                cleaner_id: cleanerId,
                scheduled_date: scheduledDate,
                scheduled_time: scheduledTime
            },
            success: function (response) {
                if (response.success) {
                    feedback.html('<div style="background:#d4edda;color:#155724;padding:10px;border-radius:6px;">✅ ' + response.data.message + '</div>');
                    showToast('Visit scheduled successfully!', 'success');

                    setTimeout(() => {
                        $('#schedule-visit-modal').hide();
                        form[0].reset();
                        loadSMCleaningServices();
                    }, 1500);
                } else {
                    feedback.html('<div style="background:#f8d7da;color:#721c24;padding:10px;border-radius:6px;">❌ ' + response.data.message + '</div>');
                }
            },
            error: function () {
                feedback.html('<div style="background:#f8d7da;color:#721c24;padding:10px;border-radius:6px;">❌ Error scheduling visit</div>');
            },
            complete: function () {
                submitBtn.prop('disabled', false).text('+ Schedule Visit');
            }
        });
    });

    // Click on service row to view details
    $(document).on('click', '.sm-cleaning-service-row', function (e) {
        if ($(e.target).is('button') || $(e.target).closest('button').length) {
            return;
        }

        const serviceId = $(this).data('id');
        loadSMServiceDetails(serviceId);
    });

    function loadSMServiceDetails(serviceId) {
        const content = $('#service-detail-content');
        content.html('<p>Loading...</p>');
        $('#service-detail-modal').show();

        $.ajax({
            url: sm_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'get_cleaning_service_details',
                service_id: serviceId
            },
            success: function (response) {
                if (response.success) {
                    const s = response.data.service;
                    const visits = response.data.visits || [];
                    const planLabels = {
                        'one_time': 'One-Time',
                        'monthly': 'Monthly',
                        '6_month': '6-Month',
                        'yearly': 'Yearly'
                    };

                    let html = `
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-bottom: 20px;">
                            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                                <strong>👤 Customer</strong><br>
                                ${s.customer_name}<br>
                                <small>📞 ${s.customer_phone}</small>
                            </div>
                            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                                <strong>📋 Plan</strong><br>
                                ${planLabels[s.plan_type] || s.plan_type}<br>
                                <small>${s.system_size_kw} kW System</small>
                            </div>
                            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                                <strong>🧹 Visits</strong><br>
                                ${s.visits_used || 0} / ${s.visits_total || 1} Used
                            </div>
                            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                                <strong>💰 Payment</strong><br>
                                ₹${Number(s.total_amount || 0).toLocaleString()}<br>
                                <small style="color: ${s.payment_status === 'paid' ? '#047857' : '#b45309'}">${s.payment_status === 'paid' ? '✅ Paid' : '⏳ Pending'}</small>
                            </div>
                        </div>
                        
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h4 style="margin: 0;">🗓️ Visit History</h4>
                            ${(s.visits_used < s.visits_total) ? `<button class="btn btn-primary sm-schedule-visit-btn" data-id="${serviceId}" data-name="${s.customer_name}" style="padding: 8px 15px;">+ Schedule Visit</button>` : ''}
                        </div>
                    `;

                    if (visits && visits.length > 0) {
                        html += '<table style="width: 100%; border-collapse: collapse;">';
                        html += '<thead><tr style="background: #f1f5f9;"><th style="padding: 10px; text-align: left;">Date</th><th style="padding: 10px; text-align: left;">Time</th><th style="padding: 10px; text-align: left;">Cleaner</th><th style="padding: 10px; text-align: left;">Status</th></tr></thead>';
                        html += '<tbody>';

                        visits.forEach(visit => {
                            const statusColors = {
                                'scheduled': '#fef3c7',
                                'completed': '#d1fae5',
                                'cancelled': '#fee2e2'
                            };
                            const statusIcons = {
                                'scheduled': '⏳',
                                'completed': '✅',
                                'cancelled': '❌'
                            };

                            html += `
                                <tr style="border-bottom: 1px solid #e5e7eb;">
                                    <td style="padding: 10px;">${visit.scheduled_date}</td>
                                    <td style="padding: 10px;">${visit.scheduled_time || '09:00'}</td>
                                    <td style="padding: 10px;">${visit.cleaner_name || 'Not assigned'}</td>
                                    <td style="padding: 10px;">
                                        <div style="display:flex; align-items:center; gap:10px;">
                                            <span style="background: ${statusColors[visit.status] || '#e5e7eb'}; padding: 4px 10px; border-radius: 4px;">
                                                ${statusIcons[visit.status] || '❓'} ${visit.status}
                                            </span>
                                            ${visit.status === 'scheduled' ? `
                                                <button class="btn-icon btn-edit-visit" 
                                                    data-id="${visit.id}" 
                                                    data-service-id="${s.id}" 
                                                    data-date="${visit.scheduled_date}" 
                                                    data-time="${visit.scheduled_time}" 
                                                    data-cleaner="${visit.cleaner_id}" 
                                                    data-customer="${s.customer_name}" 
                                                    title="Edit Visit"
                                                    style="background:none; border:none; cursor:pointer; font-size:16px;">
                                                    ✏️
                                                </button>
                                                <button class="btn-icon btn-cancel-visit" 
                                                    data-id="${visit.id}" 
                                                    title="Cancel Visit"
                                                    style="background:none; border:none; cursor:pointer; font-size:16px; color: #dc3545;">
                                                    ❌
                                                </button>
                                            ` : ''}
                                        </div>
                                    </td>
                                </tr>
                            `;
                        });

                        html += '</tbody></table>';
                    } else {
                        html += '<p style="color: #666; text-align: center; padding: 20px;">No visits scheduled yet.</p>';
                    }

                    content.html(html);
                } else {
                    content.html('<p style="color: red;">Error loading service details.</p>');
                }
            },
            error: function () {
                content.html('<p style="color: red;">Error loading service details.</p>');
            }
        });
    }

    // Edit Visit Handler
    $(document).on('click', '.btn-edit-visit', function (e) {
        e.preventDefault();
        const btn = $(this);

        $('#edit_visit_id').val(btn.data('id'));
        $('#edit_service_id').val(btn.data('service-id'));
        $('#edit_customer_name').text(btn.data('customer'));
        $('#edit_visit_date').val(btn.data('date'));
        $('#edit_visit_time').val(btn.data('time'));

        // Populate cleaners
        const select = $('#edit_visit_cleaner');
        select.empty().append('<option value="">Select Cleaner</option>');
        select.append('<option value="0">Unassigned</option>'); // Allow unassigning

        if (smCleanersList && smCleanersList.length > 0) {
            smCleanersList.forEach(c => {
                const selected = c.id == btn.data('cleaner') ? 'selected' : '';
                select.append(`<option value="${c.id}" ${selected}>${escapeHtml(c.name)}</option>`);
            });
        }

        $('#edit-visit-modal').show();
    });

    // Submit Edit Visit Form
    $('#edit-visit-form').on('submit', function (e) {
        e.preventDefault();
        const formData = $(this).serialize() + '&action=update_cleaning_visit';

        $.ajax({
            url: sm_vars.ajax_url,
            type: 'POST',
            data: formData,
            success: function (response) {
                if (response.success) {
                    showToast('Visit updated successfully', 'success');
                    $('#edit-visit-modal').hide();
                    // Reload details
                    loadSMServiceDetails($('#edit_service_id').val());
                } else {
                    showToast(response.data.message || 'Error updating visit', 'error');
                }
            }
        });
    });

    // Cancel Visit Handler
    $(document).on('click', '.btn-cancel-visit', function (e) {
        e.preventDefault();
        $('#cancel_visit_id').val($(this).data('id'));
        $('#cancel_reason').val('');
        $('#cancel-visit-modal').show();
    });

    // Submit Cancel Visit Form
    $('#cancel-visit-form').on('submit', function (e) {
        e.preventDefault();
        const formData = $(this).serialize() + '&action=cancel_cleaning_visit';

        $.ajax({
            url: sm_vars.ajax_url,
            type: 'POST',
            data: formData,
            success: function (response) {
                if (response.success) {
                    showToast('Visit cancelled', 'success');
                    $('#cancel-visit-modal').hide();
                    // Reload details - need service ID? 
                    // The modal doesn't have service ID, but we can refresh the details modal if it's open.
                    // Or better, we should store service ID in cancel modal too or just reload the currently open service detail modal if we can find it?
                    // Actually, loadSMServiceDetails uses `serviceId`.
                    // We can get service ID from the View Details modal if it's visible.
                    // But simpler: just reload the entire services list or just close the modal.

                    // Let's force reload of the open detail modal if possible.
                    // We can grab service ID from the open details modal context?
                    // Actually, simpler: Close detail modal and reload services list.
                    $('#service-detail-modal').hide();
                    loadSMCleaningServices();
                } else {
                    showToast(response.data.message || 'Error cancelling visit', 'error');
                }
            }
        });
    });

    // Close modals
    // Generic close modal button
    // Handled by global .close-modal click

})(jQuery);