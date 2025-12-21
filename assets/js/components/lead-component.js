/**
 * Shared Lead Component JavaScript
 * 
 * Reusable lead management for Area Manager and Sales Manager dashboards.
 * 
 * @package Krtrim_Solar_Core
 * @since 1.0.1
 */

(function ($) {
    'use strict';

    // Activity type icons and labels
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

    const statusColors = {
        'new': { bg: '#dbeafe', color: '#1d4ed8' },
        'interested': { bg: '#ede9fe', color: '#6d28d9' },
        'in_process': { bg: '#fef3c7', color: '#b45309' },
        'converted': { bg: '#d1fae5', color: '#047857' },
        'lost': { bg: '#fee2e2', color: '#b91c1c' }
    };

    // Current state
    let currentLeadId = null;
    let canCreateClient = false;
    let canDelete = false;
    let dashboardType = 'sales_manager';

    // Initialize component
    window.initLeadComponent = function (ajaxUrl, nonce) {
        const $component = $('.lead-component');
        if (!$component.length) return;

        canCreateClient = $component.data('can-create-client') === true || $component.data('can-create-client') === 'true';
        canDelete = $component.data('can-delete') === true || $component.data('can-delete') === 'true';
        dashboardType = $component.data('dashboard') || 'sales_manager';

        // Store ajax config
        window.leadAjax = { url: ajaxUrl, nonce: nonce };

        // Bind events
        bindLeadEvents();

        // Load leads initially
        loadLeads();
    };

    // Bind all lead-related events
    function bindLeadEvents() {
        // Open Add Lead Modal
        $(document).on('click', '#btn-open-add-lead', function (e) {
            e.preventDefault();
            openModal('#add-lead-modal');
        });

        // Close modals
        $(document).on('click', '.close-lead-modal', function () {
            $(this).closest('.lead-modal').css('display', 'none');
        });

        $(document).on('click', '.lead-modal', function (e) {
            if (e.target === this) {
                $(this).css('display', 'none');
            }
        });

        // Lead row click - open detail
        $(document).on('click', '.lead-row-clickable', function () {
            const leadId = $(this).data('lead-id');
            openLeadDetail(leadId);
        });

        // Mobile card click
        $(document).on('click', '.lead-card-mobile', function () {
            const leadId = $(this).data('lead-id');
            openLeadDetail(leadId);
        });

        // Create lead form
        $(document).on('submit', '#create-lead-form', function (e) {
            e.preventDefault();
            createLead($(this));
        });

        // Add follow-up form
        $(document).on('submit', '#add-followup-form', function (e) {
            e.preventDefault();
            addFollowup($(this));
        });

        // Add followup from detail modal
        $(document).on('click', '#btn-add-followup-detail', function () {
            $('#followup_lead_id').val(currentLeadId);
            $('#followup_date').val(getCurrentDateTime());
            closeModal('#lead-detail-modal');
            openModal('#add-followup-modal');
        });

        // Update status
        $(document).on('click', '#btn-update-status', function () {
            const newStatus = $('#lead-status-select').val();
            updateLeadStatus(currentLeadId, newStatus);
        });

        // Search and filter
        $(document).on('input', '#lead-search', debounce(function () {
            loadLeads($('#filter-lead-status').val(), $(this).val());
        }, 300));

        $(document).on('change', '#filter-lead-status', function () {
            loadLeads($(this).val(), $('#lead-search').val());
        });

        // Quick actions (prevent row click)
        $(document).on('click', '.lead-quick-action', function (e) {
            e.stopPropagation();
        });

        // Create Client button (Area Manager only)
        $(document).on('click', '#btn-create-client-detail', function () {
            if (canCreateClient && currentLeadId) {
                const leadData = $(this).data('lead');
                navigateToCreateClient(leadData);
            }
        });

        // Lead Type Toggle - show/hide conditional fields
        $(document).on('change', 'input[name="lead_type"]', function () {
            const leadType = $(this).val();
            if (leadType === 'solar_project') {
                $('#project-type-group').show();
                $('#system-size-group').hide();
            } else {
                $('#project-type-group').hide();
                $('#system-size-group').show();
            }
            // Update visual selection
            $('.lead-type-option').css('border-color', '#e0e0e0');
            $(this).closest('.lead-type-option').css('border-color', '#4f46e5');
        });

        // Lead Type Filter
        $(document).on('change', '#filter-lead-type', function () {
            loadLeads($('#filter-lead-status').val(), $('#lead-search').val(), $(this).val());
        });
    }

    // Load leads from server
    function loadLeads(status = '', search = '', leadType = '') {
        $('#leads-table-body').html('<tr><td colspan="7" class="loading-cell">Loading leads...</td></tr>');
        $('#leads-cards-mobile').html('<p>Loading leads...</p>');

        const action = dashboardType === 'area_manager' ? 'get_area_manager_leads' : 'get_sales_manager_leads';

        $.ajax({
            url: window.leadAjax.url,
            type: 'POST',
            data: {
                action: action,
                status: status,
                search: search,
                lead_type: leadType
            },
            success: function (response) {
                if (response.success) {
                    renderLeads(response.data.leads || []);
                } else {
                    showError('Error loading leads');
                }
            },
            error: function () {
                showError('Network error');
            }
        });
    }
    window.loadLeads = loadLeads;

    // Render leads in table and mobile cards
    function renderLeads(leads) {
        if (!leads || leads.length === 0) {
            const emptyHtml = `
                <tr>
                    <td colspan="6" class="empty-cell">
                        <div class="empty-state">
                            <div class="icon">👥</div>
                            <h3>No leads yet</h3>
                            <p>Start adding leads to grow your pipeline</p>
                        </div>
                    </td>
                </tr>
            `;
            $('#leads-table-body').html(emptyHtml);
            $('#leads-cards-mobile').html(`
                <div class="empty-state">
                    <div class="icon">👥</div>
                    <h3>No leads yet</h3>
                    <p>Tap + to add your first lead</p>
                </div>
            `);
            return;
        }

        // Table rows
        let tableHtml = '';
        let mobileHtml = '';

        leads.forEach(lead => {
            const statusStyle = statusColors[lead.status] || statusColors['new'];
            const followupCount = lead.followups ? lead.followups.length : (lead.followup_count || 0);
            const leadTypeIcon = lead.lead_type === 'cleaning_service' ? '🧹' : '☀️';
            const leadTypeLabel = lead.lead_type === 'cleaning_service' ? 'Cleaning' : 'Solar';

            // Table row
            tableHtml += `
                <tr class="lead-row-clickable" data-lead-id="${lead.id}">
                    <td class="lead-name-cell">
                        <strong>${escapeHtml(lead.name)}</strong>
                    </td>
                    <td>
                        <span title="${leadTypeLabel}">${leadTypeIcon} ${leadTypeLabel}</span>
                    </td>
                    <td>
                        <a href="tel:${lead.phone}" class="lead-quick-action">${escapeHtml(lead.phone)}</a>
                    </td>
                    <td>
                        <span class="lead-status-badge" style="background:${statusStyle.bg}; color:${statusStyle.color};">
                            ${formatStatus(lead.status)}
                        </span>
                    </td>
                    <td>${followupCount} follow-up${followupCount !== 1 ? 's' : ''}</td>
                    <td class="lead-actions-cell">
                        <a href="https://wa.me/91${lead.phone.replace(/\D/g, '')}" target="_blank" class="lead-quick-action btn-icon" title="WhatsApp">💬</a>
                        <a href="tel:${lead.phone}" class="lead-quick-action btn-icon" title="Call">📞</a>
                    </td>
                </tr>
            `;

            // Mobile card
            mobileHtml += `
                <div class="lead-card-mobile" data-lead-id="${lead.id}">
                    <div class="lead-card-mobile-header">
                        <strong>${escapeHtml(lead.name)}</strong>
                        <span class="lead-status-badge" style="background:${statusStyle.bg}; color:${statusStyle.color};">
                            ${formatStatus(lead.status)}
                        </span>
                    </div>
                    <div class="lead-card-mobile-info">
                        <span>📞 ${escapeHtml(lead.phone)}</span>
                        <span>💬 ${followupCount} follow-ups</span>
                    </div>
                    <div class="lead-card-mobile-actions">
                        <a href="https://wa.me/91${lead.phone.replace(/\D/g, '')}" target="_blank" class="lead-quick-action">WhatsApp</a>
                        <a href="tel:${lead.phone}" class="lead-quick-action">Call</a>
                    </div>
                </div>
            `;
        });

        $('#leads-table-body').html(tableHtml);
        $('#leads-cards-mobile').html(mobileHtml);
    }

    // Open lead detail modal
    function openLeadDetail(leadId) {
        currentLeadId = leadId;
        $('#lead-detail-info').html('<p>Loading...</p>');
        $('#lead-followup-thread').html('<p>Loading follow-ups...</p>');
        openModal('#lead-detail-modal');

        const action = dashboardType === 'area_manager' ? 'get_lead_details_for_am' : 'get_lead_details_for_sm';

        $.ajax({
            url: window.leadAjax.url,
            type: 'POST',
            data: {
                action: action,
                lead_id: leadId
            },
            success: function (response) {
                if (response.success) {
                    renderLeadDetail(response.data);
                } else {
                    $('#lead-detail-info').html('<p class="error">Error loading lead details</p>');
                }
            }
        });
    }

    // Render lead detail in modal
    function renderLeadDetail(data) {
        const lead = data.lead;
        const followups = data.followups || [];
        const statusStyle = statusColors[lead.status] || statusColors['new'];

        $('#lead-detail-name').text(lead.name);
        $('#lead-detail-status')
            .text(formatStatus(lead.status))
            .css({ background: statusStyle.bg, color: statusStyle.color });

        $('#lead-status-select').val(lead.status);

        let infoHtml = `
            <div class="lead-info-grid">
                <div class="lead-info-item"><span class="label">📞 Phone</span><span class="value">${escapeHtml(lead.phone)}</span></div>
                ${lead.email ? `<div class="lead-info-item"><span class="label">📧 Email</span><span class="value">${escapeHtml(lead.email)}</span></div>` : ''}
                ${lead.address ? `<div class="lead-info-item"><span class="label">📍 Address</span><span class="value">${escapeHtml(lead.address)}</span></div>` : ''}
                ${lead.source ? `<div class="lead-info-item"><span class="label">📊 Source</span><span class="value">${formatSource(lead.source)}</span></div>` : ''}
                <div class="lead-info-item"><span class="label">📅 Created</span><span class="value">${lead.created_date}</span></div>
            </div>
            ${lead.notes ? `<div class="lead-notes"><strong>Notes:</strong> ${escapeHtml(lead.notes)}</div>` : ''}
        `;
        $('#lead-detail-info').html(infoHtml);

        // Set action links
        $('#btn-whatsapp-detail').attr('href', `https://wa.me/91${lead.phone.replace(/\D/g, '')}`);
        $('#btn-call-detail').attr('href', `tel:${lead.phone}`);

        // Store lead data for create client
        $('#btn-create-client-detail').data('lead', lead);

        // Render followups
        renderFollowupThread(followups);
    }

    // Render followup thread
    function renderFollowupThread(followups) {
        if (!followups || followups.length === 0) {
            $('#lead-followup-thread').html('<p class="no-followups">No follow-ups yet. Add one to start the conversation!</p>');
            return;
        }

        let html = '<div class="followup-thread">';
        followups.forEach(f => {
            const icon = activityIcons[f.activity_type] || '📝';
            const label = activityLabels[f.activity_type] || f.activity_type;
            html += `
                <div class="followup-thread-item">
                    <div class="followup-icon">${icon}</div>
                    <div class="followup-content">
                        <div class="followup-header">
                            <span class="followup-type">${label}</span>
                            <span class="followup-date">${formatDateTime(f.activity_date)}</span>
                        </div>
                        <div class="followup-notes">${escapeHtml(f.notes)}</div>
                    </div>
                </div>
            `;
        });
        html += '</div>';
        $('#lead-followup-thread').html(html);
    }

    // Create new lead
    function createLead($form) {
        const action = dashboardType === 'area_manager' ? 'create_solar_lead' : 'create_lead_by_sales_manager';
        const formData = $form.serialize() + '&action=' + action;

        $.ajax({
            url: window.leadAjax.url,
            type: 'POST',
            data: formData,
            beforeSend: function () {
                $form.find('button[type="submit"]').prop('disabled', true).text('Adding...');
            },
            success: function (response) {
                if (response.success) {
                    showToast('Lead created successfully! 🎉', 'success');
                    $form[0].reset();
                    closeModal('#add-lead-modal');
                    loadLeads();
                } else {
                    showToast(response.data?.message || 'Error creating lead', 'error');
                }
            },
            complete: function () {
                $form.find('button[type="submit"]').prop('disabled', false).text('➕ Add Lead');
            }
        });
    }

    // Add followup
    function addFollowup($form) {
        const formData = {
            action: 'add_lead_followup',
            lead_id: $('#followup_lead_id').val(),
            activity_type: $('#followup_type').val(),
            activity_date: $('#followup_date').val(),
            notes: $('#followup_notes').val()
        };

        $.ajax({
            url: window.leadAjax.url,
            type: 'POST',
            data: formData,
            beforeSend: function () {
                $form.find('button[type="submit"]').prop('disabled', true).text('Saving...');
            },
            success: function (response) {
                if (response.success) {
                    showToast('Follow-up added! 📞', 'success');
                    $form[0].reset();
                    closeModal('#add-followup-modal');
                    loadLeads();
                } else {
                    showToast(response.data?.message || 'Error', 'error');
                }
            },
            complete: function () {
                $form.find('button[type="submit"]').prop('disabled', false).text('Save Follow-up');
            }
        });
    }

    // Update lead status
    function updateLeadStatus(leadId, status) {
        $.ajax({
            url: window.leadAjax.url,
            type: 'POST',
            data: {
                action: 'update_lead_by_sales_manager',
                lead_id: leadId,
                lead_status: status
            },
            success: function (response) {
                if (response.success) {
                    showToast('Status updated!', 'success');
                    const statusStyle = statusColors[status] || statusColors['new'];
                    $('#lead-detail-status')
                        .text(formatStatus(status))
                        .css({ background: statusStyle.bg, color: statusStyle.color });
                    loadLeads();
                }
            }
        });
    }

    // Navigate to create client (Area Manager only)
    function navigateToCreateClient(lead) {
        closeModal('#lead-detail-modal');
        // Switch to Create Client section
        $('.nav-item[data-section="create-client"]').click();
        // Pre-fill form
        setTimeout(() => {
            $('#client_name').val(lead.name);
            $('#client_email').val(lead.email || '');
            const username = (lead.email || lead.name).split('@')[0].toLowerCase().replace(/\s/g, '_');
            $('#client_username').val(username);
        }, 100);
    }

    // Helper functions
    function openModal(selector) {
        $(selector).css('display', 'flex');
    }

    function closeModal(selector) {
        $(selector).css('display', 'none');
    }

    function formatStatus(status) {
        const labels = {
            'new': 'New',
            'interested': 'Interested',
            'in_process': 'In Process',
            'converted': 'Converted',
            'lost': 'Lost'
        };
        return labels[status] || status;
    }

    function formatSource(source) {
        const labels = {
            'door_to_door': 'Door-to-Door',
            'referral': 'Referral',
            'event': 'Event/Exhibition',
            'phone_inquiry': 'Phone Inquiry',
            'social_media': 'Social Media',
            'website': 'Website',
            'other': 'Other'
        };
        return labels[source] || source;
    }

    function formatDateTime(dateStr) {
        if (!dateStr) return '';
        const date = new Date(dateStr);
        return date.toLocaleDateString('en-IN', {
            day: 'numeric',
            month: 'short',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function getCurrentDateTime() {
        const now = new Date();
        const offset = now.getTimezoneOffset();
        const local = new Date(now.getTime() - offset * 60000);
        return local.toISOString().slice(0, 16);
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function debounce(func, wait) {
        let timeout;
        return function (...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    }

    function showToast(message, type = 'info') {
        if (typeof window.showToast === 'function') {
            window.showToast(message, type);
        } else {
            console.log(`[${type}] ${message}`);
        }
    }

    function showError(message) {
        $('#leads-table-body').html(`<tr><td colspan="6" class="error-cell">${message}</td></tr>`);
        $('#leads-cards-mobile').html(`<p class="error">${message}</p>`);
    }

})(jQuery);
