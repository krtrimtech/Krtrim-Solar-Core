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
        initNavigation();
        initMobileNav();
        initNotifications();
        loadDashboardStats();
        loadTodayFollowups();
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
    function initNavigation() {
        $('.sidebar-nav .nav-item').on('click', function () {
            navigateToSection($(this).data('section'), $(this));
        });
    }

    // Mobile Bottom Navigation
    function initMobileNav() {
        $('.mobile-nav-item').on('click', function (e) {
            const section = $(this).data('section');
            if (!section) return; // Logout link doesn't have section

            e.preventDefault();
            navigateToSection(section, $(this));
        });
    }

    // Navigate to section (shared between sidebar and mobile nav)
    function navigateToSection(section, $clickedNav) {
        // Update active nav for both sidebar and mobile
        $('.sidebar-nav .nav-item').removeClass('active');
        $('.mobile-nav-item').removeClass('active');

        // Set active on clicked and corresponding nav
        $(`.sidebar-nav .nav-item[data-section="${section}"]`).addClass('active');
        $(`.mobile-nav-item[data-section="${section}"]`).addClass('active');

        // Update title
        const titles = {
            'dashboard': 'Dashboard',
            'my-leads': 'Leads',
            'conversions': 'My Conversions'
        };
        $('#section-title').text(titles[section] || 'Dashboard');

        // Show/hide sections
        $('.section-content').hide();
        $(`#${section}-section`).show();

        // Load data for section
        switch (section) {
            case 'dashboard':
                loadDashboardStats();
                loadTodayFollowups();
                break;
            case 'my-leads':
                initLeadComponent();
                break;
            case 'conversions':
                loadConversions();
                break;
        }
    }

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
        $(document).on('click', '.close-modal', function () {
            $(this).closest('.modal').css('display', 'none');
        });

        // Close modal on background click
        $(document).on('click', '.modal', function (e) {
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
    function showToast(message, type = 'info') {
        const toast = $(`<div class="toast ${type}">${message}</div>`);
        $('#toast-container').append(toast);
        setTimeout(() => toast.remove(), 4000);
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

})(jQuery);
