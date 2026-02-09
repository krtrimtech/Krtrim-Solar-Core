/**
 * Area Manager Dashboard JavaScript  
 */
(function ($) {
    'use strict';

    console.log('Area Manager Dashboard Script Initialized');

    // Password Toggle
    $(document).on('click', '.toggle-password', function () {
        const targetId = $(this).data('target');
        const field = $('#' + targetId);
        if (field.attr('type') === 'password') {
            field.attr('type', 'text');
        } else {
            field.attr('type', 'password');
        }
    });

    // Generate Password
    $(document).on('click', '.generate-password-btn', function () {
        const targetId = $(this).data('target');
        const chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()";
        let password = "";
        for (let i = 0; i < 12; i++) {
            password += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        $('#' + targetId).val(password).trigger('input');
    });

    // Password Strength
    $('#client_password').on('input', function () {
        const password = $(this).val();
        const meter = $('#password-strength-bar');
        const text = $('#password-strength-text');
        let strength = 0;

        if (password.length >= 8) strength++;
        if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
        if (password.match(/\d/)) strength++;
        if (password.match(/[^a-zA-Z\d]/)) strength++;

        let color = 'red';
        let width = '0%';
        let label = '';

        switch (strength) {
            case 0:
            case 1:
                width = '25%';
                color = 'red';
                label = 'Weak';
                break;
            case 2:
                width = '50%';
                color = 'orange';
                label = 'Medium';
                break;
            case 3:
                width = '75%';
                color = 'blue';
                label = 'Strong';
                break;
            case 4:
                width = '100%';
                color = 'green';
                label = 'Very Strong';
                break;
        }

        if (password.length === 0) {
            width = '0%';
            label = '';
        }

        meter.css({ 'width': width, 'background-color': color });
        text.text(label).css('color', color);
    });

    //Navigation (Global) ---
    console.log('Attaching click handler to .area-manager-dashboard .nav-item');
    $(document).on('click', '.area-manager-dashboard .nav-item', function (e) {
        console.log('=== NAV CLICK DETECTED ===');
        e.preventDefault();
        e.stopPropagation();

        const section = $(this).data('section');
        const sectionId = '#' + section + '-section';

        console.log('Clicked section:', section);
        console.log('Target selector:', sectionId);
        console.log('Target element exists:', $(sectionId).length > 0);
        console.log('All .section-content elements:', $('.section-content').length);

        // Remove active class
        $('.area-manager-dashboard .nav-item').removeClass('active');
        $(this).addClass('active');
        console.log('Active class updated');

        // Hide all sections
        $('.section-content').each(function () {
            console.log('Hiding:', this.id, 'Current display:', $(this).css('display'));
            $(this).hide();
        });

        // Show target section
        console.log('Showing section:', sectionId);
        $(sectionId).show();
        console.log('Target section display after show():', $(sectionId).css('display'));

        $('#section-title').text($(this).text());

        // Trigger data load if needed
        if (typeof loadSectionData === 'function') {
            loadSectionData(section);
        } else {
            $(document).trigger('area-manager-nav-click', [section]);
        }

        console.log('=== NAV CLICK COMPLETE ===');
    });

    // --- Toast Notification Helper ---
    function showToast(message, type = 'info') {
        const toastContainer = $('#toast-container');
        const toastId = 'toast-' + Date.now();

        const toast = $(`
                <div class="toast ${type}" id="${toastId}">
                    <div class="toast-icon"></div>
                    <div class="toast-message">${message}</div>
                    <button class="toast-close">×</button>
                </div>
            `);

        toastContainer.append(toast);

        // Close button click
        toast.find('.toast-close').on('click', function () {
            removeToast(toastId);
        });

        // Auto remove after 3 seconds
        setTimeout(() => {
            removeToast(toastId);
        }, 3000);
    }
    // Expose showToast globally for shared components
    window.showToast = showToast;

    function removeToast(toastId) {
        const toast = $('#' + toastId);
        if (toast.length) {
            toast.addClass('removing');
            setTimeout(() => {
                toast.remove();
            }, 300);
        }
    }

    // --- Initialize AJAX URL and nonces FIRST (before any functions use them) ---
    const ajaxUrl = (typeof sp_area_dashboard_vars !== 'undefined') ? sp_area_dashboard_vars.ajax_url : '';

    if (!ajaxUrl) {
        console.error('Area Manager Dashboard: sp_area_dashboard_vars is undefined or missing ajax_url.');
        return;
    }

    const createProjectNonce = sp_area_dashboard_vars.create_project_nonce;
    const projectDetailsNonce = sp_area_dashboard_vars.project_details_nonce;

    // Track if shared lead component is initialized
    let leadComponentInitialized = false;

    // Forward declaration for loadBids (defined later, used by window.loadSectionData)
    var loadBids;

    // Initialize shared lead component
    function initLeadComponent() {
        if (!leadComponentInitialized && typeof window.initLeadComponent === 'function') {
            window.initLeadComponent(ajaxUrl, sp_area_dashboard_vars.get_leads_nonce);
            leadComponentInitialized = true;
        } else if (typeof window.loadLeads === 'function') {
            window.loadLeads();
        }
    }

    // --- Notification System ---
    function loadNotifications() {
        $.ajax({
            url: ajaxUrl,
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

    // Toggle notification panel
    $(document).on('click', '#notification-toggle', function () {
        $('#notification-panel').toggleClass('open');
    });

    // Close notification panel
    $(document).on('click', '#close-notification-panel', function () {
        $('#notification-panel').removeClass('open');
    });

    // Load notifications on page load
    loadNotifications();
    // Refresh every 30 seconds
    setInterval(loadNotifications, 30000);

    // --- End Notification System ---

    const reviewSubmissionNonce = sp_area_dashboard_vars.review_submission_nonce;
    const awardBidNonce = sp_area_dashboard_vars.award_bid_nonce;

    // --- Dashboard Charts & Stats ---
    let projectStatusChart, monthlyTrendChart, financialChart, leadChart;

    function loadDashboardStats() {
        console.log('Loading dashboard stats...');
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_area_manager_dashboard_stats',
                nonce: sp_area_dashboard_vars.get_dashboard_stats_nonce,
            },
            success: function (response) {
                console.log('Stats response:', response);
                if (response.success) {
                    updateDashboardStats(response.data);
                    initializeCharts(response.data);
                } else {
                    console.error('Stats error:', response);
                }
            },
            error: function (xhr, status, error) {
                console.error('AJAX error loading stats:', error);
            }
        });
    }

    function updateDashboardStats(stats) {
        console.log('Updating stats:', stats);
        $('#total-projects-stat').text(stats.total_projects || 0);
        $('#total-revenue-stat').text('₹' + (stats.total_revenue || 0).toLocaleString('en-IN'));
        $('#client-payments-stat').text('₹' + (stats.total_client_payments || 0).toLocaleString('en-IN'));
        $('#outstanding-balance-stat').text('₹' + (stats.total_outstanding || 0).toLocaleString('en-IN'));
        $('#total-costs-stat').text('₹' + (stats.total_costs || 0).toLocaleString('en-IN'));
        $('#total-profit-stat').text('₹' + (stats.total_profit || 0).toLocaleString('en-IN'));
        $('#profit-margin-stat').text((stats.profit_margin || 0).toFixed(1) + '%');
        $('#collection-rate-stat').text((stats.collection_rate || 0).toFixed(1) + '%');
        $('#total-leads-stat').text(stats.total_leads || 0);
    }

    function initializeCharts(stats) {
        if (typeof Chart === 'undefined') {
            console.log('Chart.js not loaded');
            return;
        }
        console.log('Charts would be initialized here with:', stats);
        // Destroy existing charts if they exist
        if (projectStatusChart) projectStatusChart.destroy();
        if (monthlyTrendChart) monthlyTrendChart.destroy();
        if (financialChart) financialChart.destroy();
        if (leadChart) leadChart.destroy();

        // 1. Project Status Pie Chart
        const statusCtx = document.getElementById('project-status-chart');
        if (statusCtx) {
            projectStatusChart = new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Pending', 'In Progress', 'Completed'],
                    datasets: [{
                        data: [
                            stats.project_status?.pending || 0,
                            stats.project_status?.in_progress || 0,
                            stats.project_status?.completed || 0
                        ],
                        backgroundColor: ['#f59e0b', '#3b82f6', '#10b981'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' }
                    }
                }
            });
        }

        // 2. Monthly Trend Bar Chart
        const trendCtx = document.getElementById('monthly-trend-chart');
        if (trendCtx) {
            monthlyTrendChart = new Chart(trendCtx, {
                type: 'bar',
                data: {
                    labels: stats.monthly_data?.labels || ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                    datasets: [{
                        label: 'Projects',
                        data: stats.monthly_data?.values || [0, 0, 0, 0, 0, 0],
                        backgroundColor: '#4f46e5',
                        borderRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });
        }

        // 3. Financial Overview Chart
        const finCtx = document.getElementById('financial-chart');
        if (finCtx) {
            financialChart = new Chart(finCtx, {
                type: 'line',
                data: {
                    labels: stats.monthly_data?.labels || ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                    datasets: [
                        {
                            label: 'Revenue',
                            data: stats.financial_data?.revenue || [0, 0, 0, 0, 0, 0],
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            tension: 0.4,
                            fill: true
                        },
                        {
                            label: 'Client Payments',
                            data: stats.financial_data?.payments || [0, 0, 0, 0, 0, 0],
                            borderColor: '#3b82f6',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            tension: 0.4,
                            fill: true
                        },
                        {
                            label: 'Vendor Costs',
                            data: stats.financial_data?.costs || [0, 0, 0, 0, 0, 0],
                            borderColor: '#ef4444',
                            backgroundColor: 'rgba(239, 68, 68, 0.1)',
                            tension: 0.4,
                            fill: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' }
                    },
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });
        }

        // 4. Lead Conversion Chart
        const leadCtx = document.getElementById('lead-chart');
        if (leadCtx) {
            leadChart = new Chart(leadCtx, {
                type: 'pie',
                data: {
                    labels: ['Converted', 'Pending', 'Lost'],
                    datasets: [{
                        data: [
                            stats.lead_data?.converted || 0,
                            stats.lead_data?.pending || 0,
                            stats.lead_data?.lost || 0
                        ],
                        backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' }
                    }
                }
            });
        }
    }

    // Load stats when dashboard section is shown
    $(document).on('area-manager-nav-click', function (e, section) {
        if (section === 'dashboard') {
            loadDashboardStats();
        }
    });

    // Initial load
    if ($('#dashboard-section').is(':visible')) {
        loadDashboardStats();
    }

    // Global section data loader
    window.loadSectionData = function (section) {
        console.log('Loading section data for:', section);
        if (section === 'dashboard') {
            loadDashboardStats();
        } else if (section === 'projects') {
            loadProjects();
        } else if (section === 'project-reviews') {
            loadReviews();
        } else if (section === 'bid-management') {
            loadBids();
        } else if (section === 'vendor-approvals') {
            loadVendorApprovals();
        } else if (section === 'leads') {
            console.log('Triggering shared lead component...');
            initLeadComponent();
        } else if (section === 'my-clients') {
            loadMyClients();
        } else if (section === 'manage-cleaners') {
            loadCleanersList();
        } else if (section === 'cleaning-services') {
            loadCleaningServices();
        } else if (section === 'my-team') {
            loadMyTeam();
        } else if (section === 'team-analysis') {
            loadTeamAnalysis();
        } else if (section === 'am-assignment') {
            loadAMAssignments();
        }
    };

    // Load data when section becomes visible
    $(document).on('click', '.nav-item', function () {
        const section = $(this).data('section');
        console.log('Nav clicked, section:', section);

        // Reset create form when navigating to it (unless we're in edit mode)
        if (section === 'create-project' && !$('#edit_project_id').val()) {
            resetCreateForm();
        }

        setTimeout(function () {
            loadSectionData(section);
        }, 100);
    });

    // Initial load - check if dashboard is visible and load it
    $(document).ready(function () {
        console.log('Document ready, checking initial section');
        setTimeout(function () {
            if ($('#dashboard-section').is(':visible')) {
                console.log('Dashboard visible on load, loading stats');
                loadDashboardStats();
            }
            if ($('#leads-section').is(':visible')) {
                console.log('Leads visible on load, initializing lead component');
                initLeadComponent();
            }
        }, 500);
    });

    // Handle event from global handler
    $(document).on('area-manager-nav-click', function (e, section) {
        window.loadSectionData(section);
    });

    // Initial load if dashboard is visible
    setTimeout(function () {
        if ($('#dashboard-section').is(':visible')) {
            console.log('Initial dashboard stats load');
            loadDashboardStats();
        }
    }, 500);


    // Navigation handler moved to global scope for reliability

    // --- Load Projects with Filters ---
    let allProjects = [];

    function loadProjects() {
        $('#area-project-list-container').html('<div class="loading-spinner"><div class="spinner"></div><p>Loading projects...</p></div>');
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_area_manager_projects',
                nonce: sp_area_dashboard_vars.get_projects_nonce,
            },
            success: function (response) {
                if (response.success) {
                    allProjects = response.data.projects;
                    renderProjects(allProjects);
                }
            }
        });
    }

    function renderProjects(projects) {
        if (!projects || projects.length === 0) {
            $('#area-project-list-container').html('<div class="empty-state"><div class="empty-state-icon">📋</div><h3>No Projects</h3></div>');
            return;
        }
        let html = '<div class="leads-grid">';
        projects.forEach(project => {
            const statusClass = 'status-' + (project.status || 'pending');
            const statusText = (project.status || 'pending').replace(/_/g, ' ').toUpperCase();
            const totalCost = Number(project.total_cost || 0);
            const paidAmount = Number(project.paid_amount || 0);
            const balance = totalCost - paidAmount;

            html += `
                    <div class="lead-card">
                        <div class="lead-card-header">
                            <h3 class="lead-card-title">${project.title}</h3>
                            <span class="lead-card-status ${statusClass}">${statusText}</span>
                        </div>
                        <div class="lead-card-body">
                            <div class="lead-info">📍 ${project.project_city || ''}, ${project.project_state || ''}</div>
                            <div class="lead-info">⚡ ${project.solar_system_size_kw || 0} kW</div>
                            <div class="lead-info">💰 Total: ₹${totalCost.toLocaleString()}</div>
                            <div class="lead-info">💵 Paid: ₹${paidAmount.toLocaleString()}</div>
                            <div class="lead-info" style="font-weight: 600; color: ${balance > 0 ? '#ff9800' : '#4CAF50'}">
                                💳 Balance: ₹${balance.toLocaleString()}
                            </div>
                            ${project.vendor_name ? `<div class="lead-info">👤 Vendor: ${project.vendor_name}</div>` : ''}
                            ${project.pending_submissions > 0 ? `<div class="lead-info" style="color: #ff9800;">🟡 ${project.pending_submissions} pending review(s)</div>` : ''}
                        </div>
                        <div class="lead-card-actions">
                            <button class="action-btn action-btn-primary view-project-details" data-id="${project.id}">👁️ View</button>
                            <button class="action-btn action-btn-secondary edit-project" data-id="${project.id}">✏️ Edit</button>
                        </div>
                    </div>
                `;
        });
        html += '</div>';
        $('#area-project-list-container').html(html);
    }

    function filterProjects() {
        const status = $('#filter-status').val();
        const datePreset = $('#filter-date-preset').val();
        const customDate = $('#filter-custom-date').val();

        let filtered = allProjects.filter(p => {
            // Status filter
            if (status && p.status !== status) return false;

            // Date filter
            if (datePreset) {
                const projectDate = new Date(p.start_date || p.created_at);
                const today = new Date();
                today.setHours(0, 0, 0, 0);

                if (datePreset === 'today') {
                    const pDate = new Date(projectDate);
                    pDate.setHours(0, 0, 0, 0);
                    if (pDate.getTime() !== today.getTime()) return false;
                } else if (datePreset === 'yesterday') {
                    const yesterday = new Date(today);
                    yesterday.setDate(yesterday.getDate() - 1);
                    const pDate = new Date(projectDate);
                    pDate.setHours(0, 0, 0, 0);
                    if (pDate.getTime() !== yesterday.getTime()) return false;
                } else if (datePreset === 'week') {
                    const weekAgo = new Date(today);
                    weekAgo.setDate(weekAgo.getDate() - 7);
                    if (projectDate < weekAgo) return false;
                } else if (datePreset === 'custom' && customDate) {
                    const selectedDate = new Date(customDate);
                    const pDate = new Date(projectDate);
                    pDate.setHours(0, 0, 0, 0);
                    selectedDate.setHours(0, 0, 0, 0);
                    if (pDate.getTime() !== selectedDate.getTime()) return false;
                }
            }

            return true;
        });

        renderProjects(filtered);
    }

    // Clear filters button
    $(document).on('click', '.clear-project-filters-btn', function () {
        $('#filter-status, #filter-date-preset, #filter-custom-date').val('');
        $('#custom-date-wrapper').hide();
        renderProjects(allProjects);
    });

    window.clearProjectFilters = function () {
        $('#filter-status, #filter-date-preset, #filter-custom-date').val('');
        $('#custom-date-wrapper').hide();
        renderProjects(allProjects);
    };

    // Show/hide custom date input and trigger filter
    $(document).on('change', '#filter-date-preset', function () {
        const preset = $(this).val();
        if (preset === 'custom') {
            $('#custom-date-wrapper').show();
            // Set default to today if no date selected
            if (!$('#filter-custom-date').val()) {
                const today = new Date().toISOString().split('T')[0];
                $('#filter-custom-date').val(today);
            }
            // Trigger filter with the date
            filterProjects();
        } else {
            $('#custom-date-wrapper').hide();
            $('#filter-custom-date').val(''); // Clear custom date when switching away
            filterProjects();
        }
    });

    // Trigger filter when custom date is changed via calendar
    $(document).on('change', '#filter-custom-date', function () {
        if ($('#filter-date-preset').val() === 'custom') {
            console.log('Custom date selected:', $(this).val());
            filterProjects();
        }
    });

    // Attach filter listeners for status
    $(document).on('change', '#filter-status', filterProjects);

    // View project details button handler
    $(document).on('click', '.view-project-details', function () {
        const projectId = $(this).data('id');
        console.log('View project details:', projectId);
        if (typeof openProjectModal === 'function') {
            openProjectModal(projectId);
        } else {
            console.error('openProjectModal function not found');
        }
    });

    // Edit Project - Navigate to create form and pre-fill
    $(document).on('click', '.edit-project', function () {
        const projectId = $(this).data('id');
        // Navigate to create project section
        $('.nav-item[data-section="create-project"]').click();
        // Wait for section to be visible, then load project data
        setTimeout(() => {
            loadProjectForEdit(projectId);
        }, 100);
    });

    // Load Project Data for Editing (reuses create form)
    function loadProjectForEdit(projectId) {
        console.log('Loading project for edit:', projectId);

        // Show loading state
        const submitBtn = $('#create-project-form button[type="submit"]');
        const originalText = submitBtn.text();
        submitBtn.prop('disabled', true).text('Loading...');

        // Fetch project details
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_area_manager_project_details',
                nonce: projectDetailsNonce,
                project_id: projectId,
            },
            success: function (response) {
                if (response.success) {
                    populateCreateFormForEdit(response.data, projectId);
                } else {
                    showToast('Error loading project: ' + (response.data.message || 'Unknown error'), 'error');
                    submitBtn.prop('disabled', false).text(originalText);
                }
            },
            error: function (xhr, status, error) {
                console.error('Error fetching project:', error);
                showToast('Failed to load project data', 'error');
                submitBtn.prop('disabled', false).text(originalText);
            }
        });
    }

    // Populate Create Form with Project Data for Editing
    function populateCreateFormForEdit(data, projectId) {
        const project = data.project;
        const meta = data.meta;

        console.log('Populating create form for edit:', data);

        // Add hidden field to track edit mode
        if ($('#edit_project_id').length === 0) {
            $('#create-project-form').prepend('<input type="hidden" id="edit_project_id" name="edit_project_id">');
        }
        $('#edit_project_id').val(projectId);

        // Update form title and button
        $('#create-project-section h3').text('✏️ Edit Project: ' + project.title);
        $('#create-project-form button[type="submit"]').text('Update Project').prop('disabled', false);

        // Set basic fields
        $('#project_title').val(project.title || '');
        $('#project_description').val(project.description || '');

        // Set meta fields
        $('#solar_system_size_kw').val(meta.solar_system_size_kw || '');
        $('#client_address').val(meta.client_address || '');
        $('#client_phone_number').val(meta.client_phone_number || '');
        $('#project_start_date').val(meta.project_start_date || '');
        $('#total_project_cost').val(meta.total_project_cost || '');
        $('#paid_amount').val(meta.paid_amount || '');
        $('#project_status').val(meta.project_status || 'pending');
        $('#client_user_id').val(meta.client_user_id || '');

        // Handle state/city - only if dropdowns exist (admin/manager without location)
        if ($('#project_state').is('select')) {
            $('#project_state').val(meta.project_state || '');
            $('#project_state').trigger('change');
            // Set city after cities are loaded
            setTimeout(() => {
                $('#project_city').val(meta.project_city || '');
            }, 150);
        }

        // Set vendor assignment method
        const vendorMethod = meta.vendor_assignment_method || 'bidding';
        if (vendorMethod === 'manual') {
            $('input[name="vendor_assignment_method"][value="manual"]').prop('checked', true).trigger('change');
            $('#assigned_vendor_id').val(meta.assigned_vendor_id || '');
            $('#paid_to_vendor').val(meta.vendor_paid_amount || '');
        } else {
            $('input[name="vendor_assignment_method"][value="bidding"]').prop('checked', true).trigger('change');
        }
    }

    // Reset Create Form to Create Mode
    function resetCreateForm() {
        // Remove edit project ID
        $('#edit_project_id').remove();

        // Reset form
        $('#create-project-form')[0].reset();

        // Reset title and button
        $('#create-project-section h3').text('Create New Solar Project');
        $('#create-project-form button[type="submit"]').text('Create Project');

        // Reset feedback
        $('#create-project-feedback').text('').removeClass('text-success text-danger');
    }

    // --- Load Reviews ---
    function loadReviews() {
        $('#project-reviews-container').html('<p>Loading reviews...</p>');
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_area_manager_reviews',
                nonce: sp_area_dashboard_vars.get_reviews_nonce,
            },
            success: function (response) {
                if (response.success) {
                    let html = '';
                    if (response.data.reviews.length > 0) {
                        response.data.reviews.forEach(review => {
                            html += `
                                <div class="review-item">
                                    <h4>Project: ${review.project_id}</h4>
                                    <p><strong>Step ${review.step_number}:</strong> ${review.step_name}</p>
                                    ${review.image_url ? `<a href="${review.image_url}" target="_blank">View Submission</a>` : ''}
                                    <p><em>${review.vendor_comment || ''}</em></p>
                                    <div class="review-form">
                                        <textarea class="review-comment" placeholder="Add a comment..."></textarea>
                                        <button class="btn btn-success review-btn" data-decision="approved" data-step-id="${review.id}">Approve</button>
                                        <button class="btn btn-danger review-btn" data-decision="rejected" data-step-id="${review.id}">Reject</button>
                                    </div>
                                </div>
                            `;
                        });
                    } else {
                        html = '<p>No pending reviews.</p>';
                    }
                    $('#project-reviews-container').html(html);
                } else {
                    $('#project-reviews-container').html('<p class="text-danger">Error loading reviews.</p>');
                }
            }
        });
    }

    // --- Load Bids for Area Manager Projects ---
    loadBids = function () {
        const container = $('#bid-management-container');
        container.html('<div class="loading-spinner"><div class="spinner"></div><p>Loading bids...</p></div>');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_area_manager_bids',
                nonce: sp_area_dashboard_vars.get_projects_nonce
            },
            success: function (response) {
                if (response.success) {
                    renderBids(response.data.projects);
                } else {
                    container.html('<p class="text-danger">Error loading bids: ' + (response.data.message || 'Unknown error') + '</p>');
                }
            },
            error: function () {
                container.html('<p class="text-danger">Error loading bids. Please try again.</p>');
            }
        });
    };

    function renderBids(projects) {
        const container = $('#bid-management-container');

        if (!projects || projects.length === 0) {
            container.html(`
                <div class="empty-state">
                    <div class="icon">📋</div>
                    <h3>No Bids Yet</h3>
                    <p>When vendors submit bids on your projects, they will appear here.</p>
                </div>
            `);
            return;
        }

        let html = '<div class="bid-projects-list">';

        projects.forEach(project => {
            const isAwarded = project.assigned_vendor_id && project.assigned_vendor_id > 0;

            html += `
                <div class="card bid-project-card" style="margin-bottom: 20px;">
                    <div class="bid-project-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <div>
                            <h3 style="margin: 0;">${project.title}</h3>
                            <small style="color: #666;">${project.project_city || ''}, ${project.project_state || ''}</small>
                        </div>
                        ${isAwarded ? `
                            <span class="status-badge status-accepted" style="background: #d1fae5; color: #047857; padding: 6px 12px; border-radius: 6px;">
                                ✓ Awarded to ${project.assigned_vendor_name} (₹${Number(project.winning_bid_amount || 0).toLocaleString()})
                            </span>
                        ` : `
                            <span class="status-badge" style="background: #fef3c7; color: #b45309; padding: 6px 12px; border-radius: 6px;">
                                ${project.bids.length} Bid${project.bids.length !== 1 ? 's' : ''} Received
                            </span>
                        `}
                    </div>
                    
                    <div class="bids-list">
                        <table class="data-table" style="width: 100%;">
                            <thead>
                                <tr>
                                    <th>Vendor</th>
                                    <th>Bid Amount</th>
                                    <th>Contact</th>
                                    <th>Submitted</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${project.bids.map(bid => `
                                    <tr class="${isAwarded && bid.vendor_id == project.assigned_vendor_id ? 'awarded-bid' : ''}" 
                                        style="${isAwarded && bid.vendor_id == project.assigned_vendor_id ? 'background: #f0fdf4;' : ''}">
                                        <td>
                                            <strong>${bid.vendor_name}</strong>
                                        </td>
                                        <td>
                                            <span style="font-size: 16px; font-weight: 600; color: #4f46e5;">₹${Number(bid.bid_amount).toLocaleString()}</span>
                                        </td>
                                        <td>
                                            <a href="mailto:${bid.vendor_email}" style="color: #3b82f6;">${bid.vendor_email}</a>
                                        </td>
                                        <td>
                                            ${bid.created_at ? new Date(bid.created_at).toLocaleDateString('en-IN') : '-'}
                                        </td>
                                        <td>
                                            ${isAwarded ?
                    (bid.vendor_id == project.assigned_vendor_id ?
                        '<span style="color: #047857;">✓ Awarded</span>' :
                        '<span style="color: #9ca3af;">—</span>'
                    ) :
                    `<button class="btn btn-primary btn-sm award-bid-btn" 
                                                         data-project-id="${project.id}" 
                                                         data-bid-id="${bid.id}" 
                                                         data-vendor-id="${bid.vendor_id}"
                                                         data-vendor-name="${bid.vendor_name}"
                                                         data-amount="${bid.bid_amount}">
                                                    🏆 Award
                                                </button>`
                }
                                        </td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
        });

        html += '</div>';
        container.html(html);
    }

    // Handle Award Bid click
    $(document).on('click', '.award-bid-btn', function (e) {
        e.preventDefault();
        const btn = $(this);
        const projectId = btn.data('project-id');
        const bidId = btn.data('bid-id');
        const vendorId = btn.data('vendor-id');
        const vendorName = btn.data('vendor-name');
        const amount = btn.data('amount');

        if (!confirm(`Award this project to ${vendorName} for ₹${Number(amount).toLocaleString()}?`)) {
            return;
        }

        const originalText = btn.html();
        btn.prop('disabled', true).html('Processing...');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'award_project_to_vendor',
                nonce: $('#award_bid_nonce_field').val() || sp_area_dashboard_vars.get_projects_nonce,
                project_id: projectId,
                bid_id: bidId,
                vendor_id: vendorId
            },
            success: function (response) {
                if (response.success) {
                    showToast('🏆 Project awarded successfully!', 'success');
                    loadBids(); // Refresh the bids list
                } else {
                    showToast('Error: ' + (response.data.message || 'Could not award project'), 'error');
                    btn.prop('disabled', false).html(originalText);
                }
            },
            error: function () {
                showToast('Network error. Please try again.', 'error');
                btn.prop('disabled', false).html(originalText);
            }
        });
    });

    // --- Load Vendor Approvals ---
    function loadVendorApprovals() {
        $('#vendor-approvals-container').html('<p>Loading vendor approvals...</p>');
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_area_manager_vendor_approvals',
                nonce: sp_area_dashboard_vars.get_vendor_approvals_nonce,
            },
            success: function (response) {
                if (response.success) {
                    let html = '';
                    if (response.data.vendors.length > 0) {
                        html += '<table class="wp-list-table widefat fixed striped users"><thead><tr><th>Name</th><th>Email</th><th>Action</th></tr></thead><tbody>';
                        response.data.vendors.forEach(vendor => {
                            html += `
                                <tr>
                                    <td>${vendor.display_name}</td>
                                    <td>${vendor.user_email}</td>
                                    <td>
                                        <button class="button button-primary approve-vendor-btn" data-vendor-id="${vendor.ID}">Approve</button>
                                        <button class="button button-secondary deny-vendor-btn" data-vendor-id="${vendor.ID}">Deny</button>
                                    </td>
                                </tr>
                            `;
                        });
                        html += '</tbody></table>';
                    } else {
                        html = '<p>No vendors awaiting approval.</p>';
                    }
                    $('#vendor-approvals-container').html(html);
                } else {
                    $('#vendor-approvals-container').html('<p class="text-danger">Error loading vendor approvals.</p>');
                }
            }
        });
    }

    // --- Create Project ---
    let statesAndCities = [];
    if ($('#project_state').is('select')) {
        $.getJSON(sp_area_dashboard_vars.states_cities_json_url, function (data) {
            statesAndCities = data.states;
            const stateSelect = $('#project_state');
            statesAndCities.forEach(state => {
                stateSelect.append(`<option value="${state.state}">${state.state}</option>`);
            });
        });

        $('#project_state').on('change', function () {
            const selectedState = $(this).val();
            const citySelect = $('#project_city');
            citySelect.empty().append('<option value="">Select City</option>');

            if (selectedState) {
                const stateData = statesAndCities.find(state => state.state === selectedState);
                if (stateData) {
                    stateData.districts.forEach(city => {
                        citySelect.append(`<option value="${city}">${city}</option>`);
                    });
                }
            }
        });
    }

    $('input[name="vendor_assignment_method"]').on('change', function () {
        if ($(this).val() === 'bidding') {
            // Bidding
            $('.vendor-manual-fields').hide();
            $('#assigned_vendor_id, #paid_to_vendor').prop('disabled', true);
        } else {
            // Manual
            $('.vendor-manual-fields').show();
            $('#assigned_vendor_id, #paid_to_vendor').prop('disabled', false);
        }
    });

    // --- Create/Edit Project Form Submission ---
    $('#create-project-form').on('submit', function (e) {
        e.preventDefault();
        const form = $(this);
        const feedback = $('#create-project-feedback');

        // Check if we're in edit mode
        const projectId = $('#edit_project_id').val();
        const isEdit = !!projectId;
        const action = isEdit ? 'update_solar_project' : 'create_solar_project';
        const nonce = isEdit ? sp_area_dashboard_vars.update_project_nonce : createProjectNonce;
        const nonceField = isEdit ? 'sp_update_project_nonce' : 'sp_create_project_nonce';

        const formData = {
            action: action,
            [nonceField]: nonce,
            project_title: $('#project_title').val(),
            project_description: $('#project_description').val(),
            project_state: $('#project_state').val(),
            project_city: $('#project_city').val(),
            project_status: $('#project_status').val(),
            client_user_id: $('#client_user_id').val(),
            solar_system_size_kw: $('#solar_system_size_kw').val(),
            client_address: $('#client_address').val(),
            client_phone_number: $('#client_phone_number').val(),
            project_start_date: $('#project_start_date').val(),
            total_project_cost: $('#total_project_cost').val(),
            paid_amount: $('#paid_amount').val(),
            vendor_assignment_method: $('input[name="vendor_assignment_method"]:checked').val(),
            assigned_vendor_id: $('#assigned_vendor_id').val(),
            paid_to_vendor: $('#paid_to_vendor').val(),
        };

        // Add project_id if editing
        if (isEdit) {
            formData.project_id = projectId;
        }

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: formData,
            beforeSend: function () {
                const btnText = isEdit ? 'Updating...' : 'Creating...';
                form.find('button[type="submit"]').prop('disabled', true).text(btnText);
                feedback.text('').removeClass('text-success text-danger');
            },
            success: function (response) {
                if (response.success) {
                    showToast(response.data.message, 'success');
                    feedback.text(response.data.message).addClass('text-success');

                    // Reset form and navigate
                    setTimeout(() => {
                        resetCreateForm();
                        // Navigate to projects section
                        $('.nav-item[data-section="projects"]').click();
                    }, 1500);
                } else {
                    feedback.text(response.data.message || 'Error saving project').addClass('text-danger');
                    showToast(response.data.message || 'Error saving project', 'error');
                }
            },
            error: function (xhr, status, error) {
                console.error('Project save error:', error, xhr.responseText);
                feedback.text('AJAX error: ' + error).addClass('text-danger');
                showToast('Failed to save project. Check console.', 'error');
            },
            complete: function () {
                const btnText = isEdit ? 'Update Project' : 'Create Project';
                form.find('button[type="submit"]').prop('disabled', false).text(btnText);
            }
        });
    });


    // --- Lead Management ---
    function loadLeads() {
        console.log('🔄 Loading leads...');
        $('#area-leads-container').html('<p>Loading leads...</p>');
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_area_manager_leads',
                nonce: sp_area_dashboard_vars.get_leads_nonce,
            },
            success: function (response) {
                console.log('✅ Leads loaded:', response);
                if (response.success) {
                    let html = '';
                    if (response.data.leads.length > 0) {
                        html += '<table class="wp-list-table widefat fixed striped"><thead><tr><th>Name</th><th>Phone</th><th>Email</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
                        response.data.leads.forEach(lead => {
                            html += `
                                <tr>
                                    <td>${lead.name}</td>
                                    <td>${lead.phone}</td>
                                    <td>${lead.email}</td>
                                    <td><span class="badge status-${lead.status}">${lead.status}</span></td>
                                    <td>
                                        <button class="button button-small open-msg-modal" data-type="email" data-lead-id="${lead.id}" data-recipient="${lead.email}" ${!lead.email ? 'disabled' : ''}>Email</button>
                                        <button class="button button-small open-msg-modal" data-type="whatsapp" data-lead-id="${lead.id}" data-recipient="${lead.phone}" ${!lead.phone ? 'disabled' : ''}>WhatsApp</button>
                                        <button class="button button-small convert-lead-btn" data-lead-name="${lead.name}" data-lead-email="${lead.email}" data-lead-phone="${lead.phone}">Create Client</button>
                                        <button class="button button-small button-link-delete delete-lead-btn" data-lead-id="${lead.id}" style="color:red;">Delete</button>
                                    </td>
                                </tr>
                                <tr><td colspan="5"><small><em>${lead.notes}</em></small></td></tr>
                            `;
                        });
                        html += '</tbody></table>';
                    } else {
                        html = '<p>No leads found.</p>';
                    }
                    $('#area-leads-container').html(html);
                } else {
                    console.error('❌ Lead loading failed:', response);
                    $('#area-leads-container').html('<p class="text-danger">Error loading leads.</p>');
                }
            },
            error: function (xhr, status, error) {
                console.error('❌ AJAX error:', error);
                $('#area-leads-container').html('<p class="text-danger">AJAX error. Check console.</p>');
            }
        });
    }

    // NOTE: Lead form submission is handled by lead-component.js via form serialization
    // which correctly includes the nonce from the form's hidden field


    $(document).on('click', '.delete-lead-btn', function () {
        if (!confirm('Are you sure you want to delete this lead?')) return;
        const leadId = $(this).data('lead-id');
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'delete_solar_lead',
                nonce: sp_area_dashboard_vars.delete_lead_nonce,
                lead_id: leadId,
            },
            success: function (response) {
                if (response.success) {
                    loadLeads();
                } else {
                    showToast(response.data.message, 'error');
                }
            }
        });
    });

    // Message Modal
    $(document).on('click', '.open-msg-modal', function (e) {
        e.preventDefault();
        const type = $(this).data('type');
        const leadId = $(this).data('lead-id');
        const recipient = $(this).data('recipient');

        $('#msg_type').val(type);
        $('#msg_lead_id').val(leadId);
        $('#msg_recipient').text(recipient + ' (' + type + ')');
        $('#message-modal').show();
    });

    $('.close-modal').on('click', function () {
        $('#message-modal').hide();
    });

    $('#send-message-form').on('submit', function (e) {
        e.preventDefault();
        const form = $(this);
        const feedback = $('#send-message-feedback');
        const type = $('#msg_type').val();

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'send_lead_message',
                nonce: sp_area_dashboard_vars.send_message_nonce,
                lead_id: $('#msg_lead_id').val(),
                type: type,
                message: $('#msg_content').val(),
            },
            beforeSend: function () {
                form.find('button').prop('disabled', true).text('Sending...');
                feedback.text('');
            },
            success: function (response) {
                if (response.success) {
                    if (type === 'whatsapp' && response.data.whatsapp_url) {
                        window.open(response.data.whatsapp_url, '_blank');
                        feedback.text('WhatsApp opened.').addClass('text-success');
                    } else {
                        showToast(response.data.message, 'success');
                    }
                    setTimeout(() => { $('#message-modal').hide(); form[0].reset(); feedback.text(''); }, 2000);
                } else {
                    feedback.text(response.data.message).addClass('text-danger');
                }
            },
            complete: function () {
                form.find('button').prop('disabled', false).text('Send');
            }
        });
    });

    // --- Create Client ---
    // --- Create Client ---
    // Convert Lead to Client
    $(document).on('click', '.convert-lead-btn', function () {
        const name = $(this).data('lead-name');
        const email = $(this).data('lead-email');

        // Switch to Create Client section
        $('.nav-item[data-section="create-client"]').click();

        // Pre-fill form
        $('#client_name').val(name);
        $('#client_email').val(email);

        // Generate a username suggestion
        const username = email.split('@')[0];
        $('#client_username').val(username);

        $('#create-client-feedback').text('Pre-filled from lead data. Please set a password.').addClass('text-info');
    });

    $('#create-client-form').on('submit', function (e) {
        e.preventDefault();
        const form = $(this);
        const feedback = $('#create-client-feedback');

        const password = $('#client_password').val();

        // No confirm check needed anymore

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'create_client_from_dashboard',
                name: $('#client_name').val(),
                username: $('#client_username').val(),
                email: $('#client_email').val(),
                password: password,
                nonce: sp_area_dashboard_vars.create_client_nonce,
            },
            beforeSend: function () {
                form.find('button[type="submit"]').prop('disabled', true).text('Creating...');
                feedback.text('').removeClass('text-success text-danger text-info');
            },
            success: function (response) {
                if (response.success) {
                    showToast(response.data.message, 'success');
                    form[0].reset();
                    // Close modal and refresh clients list
                    $('#create-client-modal').css('display', 'none');
                    loadMyClients();

                    // Auto-convert lead to 'converted' status if created from lead
                    if (typeof window.autoConvertLeadToClient === 'function') {
                        window.autoConvertLeadToClient();
                    }
                } else {
                    feedback.html('<div style="background:#f8d7da;color:#721c24;padding:10px;border-radius:6px;">❌ ' + response.data.message + '</div>');
                }
            },
            complete: function () {
                form.find('button[type="submit"]').prop('disabled', false).text('Create Client');
            }
        });
    });

    // --- Create Client Modal Handlers ---
    $('#open-create-client-modal').on('click', function () {
        $('#create-client-form')[0].reset();
        $('#create-client-feedback').html('');
        $('#create-client-modal').css('display', 'flex');
    });

    $('#close-create-client-modal').on('click', function () {
        $('#create-client-modal').css('display', 'none');
    });

    // Close modal on outside click
    $('#create-client-modal').on('click', function (e) {
        if (e.target === this) {
            $(this).css('display', 'none');
        }
    });

    // --- Project Details ---
    $('#area-project-list-container').on('click', '.project-card', function () {
        const projectId = $(this).data('project-id');
        loadProjectDetails(projectId);
    });

    $('#back-to-projects-list').on('click', function () {
        $('#project-detail-section').hide();
        $('#projects-section').show();
    });

    function loadProjectDetails(projectId) {
        $('#projects-section').hide();
        $('#project-detail-section').show();

        // Clear previous details
        $('#project-detail-title').text('Loading...');
        $('#project-detail-meta').html('');
        $('#vendor-submissions-list').html('');
        $('#project-bids-list').html('');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_area_manager_project_details',
                nonce: projectDetailsNonce,
                project_id: projectId,
            },
            success: function (response) {
                if (response.success) {
                    const project = response.data;
                    $('#project-detail-title').text(project.title);

                    let metaHtml = '';
                    for (const key in project.meta) {
                        metaHtml += `<div><strong>${key}:</strong> ${project.meta[key]}</div>`;
                    }
                    $('#project-detail-meta').html(metaHtml);

                    let submissionsHtml = '';
                    if (project.submissions.length > 0) {
                        project.submissions.forEach(sub => {
                            submissionsHtml += `
                                <div class="submission-item">
                                    <p><strong>Step ${sub.step_number}:</strong> ${sub.step_name} - <span class="badge status-${sub.admin_status}">${sub.admin_status}</span></p>
                                    ${sub.image_url ? `<a href="${sub.image_url}" target="_blank">View Submission</a>` : ''}
                                    <p><em>${sub.vendor_comment || ''}</em></p>
                                    ${sub.admin_status === 'pending' ? `
                                        <div class="review-form">
                                            <textarea class="review-comment" placeholder="Add a comment..."></textarea>
                                            <button class="btn btn-success review-btn" data-decision="approved" data-step-id="${sub.id}">Approve</button>
                                            <button class="btn btn-danger review-btn" data-decision="rejected" data-step-id="${sub.id}">Reject</button>
                                        </div>
                                    ` : `<p><strong>Admin Comment:</strong> ${sub.admin_comment}</p>`}
                                </div>
                            `;
                        });
                    } else {
                        submissionsHtml = '<p>No submissions yet.</p>';
                    }
                    $('#vendor-submissions-list').html(submissionsHtml);

                    let bidsHtml = '';
                    if (project.bids.length > 0) {
                        project.bids.forEach(bid => {
                            bidsHtml += `
                                <div class="bid-item">
                                    <p><strong>${bid.vendor_name}</strong> - ₹${bid.bid_amount}</p>
                                    <p>${bid.bid_details}</p>
                                    <button class="btn btn-primary award-bid-btn" data-project-id="${projectId}" data-vendor-id="${bid.vendor_id}" data-bid-amount="${bid.bid_amount}">Award Project</button>
                                </div>
                            `;
                        });
                    } else {
                        bidsHtml = '<p>No bids yet.</p>';
                    }
                    $('#project-bids-list').html(bidsHtml);
                } else {
                    $('#project-detail-title').text('Error');
                    $('#project-detail-meta').html(`<p class="text-danger">${response.data.message}</p>`);
                }
            }
        });
    }

    // --- Review Submission ---
    $('#vendor-submissions-list').on('click', '.review-btn', function () {
        const button = $(this);
        const stepId = button.data('step-id');
        const decision = button.data('decision');
        const comment = button.siblings('.review-comment').val();

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'review_vendor_submission',
                nonce: reviewSubmissionNonce,
                step_id: stepId,
                decision: decision,
                comment: comment,
            },
            beforeSend: function () {
                button.prop('disabled', true).text('Processing...');
            },
            success: function (response) {
                if (response.success) {
                    showToast(response.data.message, 'error');
                    loadProjectDetails(button.closest('.project-detail-card').find('.award-bid-btn').data('project-id'));
                } else {
                    showToast('Error: ' + response.data.message, 'error');
                }
            },
            complete: function () {
                button.prop('disabled', false).text(decision.charAt(0).toUpperCase() + decision.slice(1));
            }
        });
    });

    // --- Award Bid ---
    $('#project-bids-list').on('click', '.award-bid-btn', function () {
        const button = $(this);
        const projectId = button.data('project-id');
        const vendorId = button.data('vendor-id');
        const bidAmount = button.data('bid-amount');

        if (!confirm('Are you sure you want to award this project to this vendor?')) {
            return;
        }

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'award_project_to_vendor',
                nonce: awardBidNonce,
                project_id: projectId,
                vendor_id: vendorId,
                bid_amount: bidAmount,
            },
            beforeSend: function () {
                button.prop('disabled', true).text('Awarding...');
            },
            success: function (response) {
                if (response.success) {
                    showToast(response.data.message, 'error');
                    loadProjectDetails(projectId);
                } else {
                    showToast('Error: ' + response.data.message, 'error');
                }
            },
            complete: function () {
                button.prop('disabled', false).text('Award Project');
            }
        });
    });
    // --- My Clients ---
    function loadMyClients() {
        $('#my-clients-container').html('<p>Loading clients...</p>');
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_area_manager_clients',
                nonce: sp_area_dashboard_vars.get_clients_nonce,
            },
            success: function (response) {
                console.log('Clients loaded:', response);
                if (response.success) {
                    let html = '';
                    if (response.data.clients && response.data.clients.length > 0) {
                        html += '<table class="wp-list-table widefat fixed striped"><thead><tr><th>Name</th><th>Username</th><th>Email</th><th>Actions</th></tr></thead><tbody>';
                        response.data.clients.forEach(client => {
                            html += `
                                <tr>
                                    <td>${client.name}</td>
                                    <td>${client.username}</td>
                                    <td>${client.email}</td>
                                    <td>
                                        <button class="button button-small open-reset-password-modal" data-client-id="${client.id}" data-client-name="${client.name}">Reset Password</button>
                                    </td>
                                </tr>
                            `;
                        });
                        html += '</tbody></table>';
                    } else {
                        html = '<p>No clients found.</p>';
                    }
                    $('#my-clients-container').html(html);
                } else {
                    $('#my-clients-container').html('<p class="text-danger">Error: ' + (response.data?.message || 'Failed to load clients') + '</p>');
                }
            },
            error: function (xhr, status, error) {
                console.error('Client load AJAX error:', error);
                $('#my-clients-container').html('<p class="text-danger">Error loading clients. Please try again.</p>');
            }
        });
    }

    $(document).on('click', '.open-reset-password-modal', function (e) {
        e.preventDefault();
        const clientId = $(this).data('client-id');
        const clientName = $(this).data('client-name');

        $('#reset_password_client_id').val(clientId);
        $('#reset-password-client-name').text(clientName);
        $('#reset-password-modal').show();
    });

    $('#reset-password-form').on('submit', function (e) {
        e.preventDefault();
        const form = $(this);
        const feedback = $('#reset-password-feedback');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'reset_client_password',
                nonce: sp_area_dashboard_vars.reset_password_nonce,
                client_id: $('#reset_password_client_id').val(),
                new_password: $('#new_password').val(),
            },
            beforeSend: function () {
                form.find('button').prop('disabled', true).text('Resetting...');
                feedback.text('');
            },
            success: function (response) {
                if (response.success) {
                    showToast(response.data.message, 'success');
                    setTimeout(() => { $('#reset-password-modal').hide(); form[0].reset(); feedback.text(''); }, 2000);
                } else {
                    feedback.text(response.data.message).addClass('text-danger');
                }
            },
            complete: function () {
                form.find('button').prop('disabled', false).text('Reset Password');
            }
        });
    });

    // ===============================
    // CLEANER MANAGEMENT FUNCTIONS
    // ===============================

    // Load cleaners list
    function loadCleanersList() {
        const container = $('#cleaners-list-container');
        container.html('<p>Loading cleaners...</p>');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: { action: 'get_cleaners' },
            success: function (response) {
                if (response.success) {
                    const cleaners = response.data;
                    if (cleaners.length === 0) {
                        container.html('<p>No cleaners found. Use the form above to add your first cleaner.</p>');
                        return;
                    }

                    let html = '<div class="cleaners-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px;">';
                    cleaners.forEach(cleaner => {
                        const photoUrl = cleaner.photo_url || 'https://via.placeholder.com/80x80?text=No+Photo';
                        const aadhaarMasked = cleaner.aadhaar ? 'XXXX-XXXX-' + cleaner.aadhaar.slice(-4) : 'N/A';

                        html += `
                            <div class="cleaner-card" style="background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                                <div style="display: flex; gap: 15px; align-items: center; margin-bottom: 15px;">
                                    <img src="${photoUrl}" alt="${cleaner.name}" style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover;">
                                    <div>
                                        <h4 style="margin: 0; font-size: 16px;">${cleaner.name}</h4>
                                        <p style="margin: 5px 0 0; color: #666; font-size: 13px;">📞 ${cleaner.phone}</p>
                                    </div>
                                </div>
                                <div style="font-size: 13px; color: #555;">
                                    <p style="margin: 5px 0;"><strong>Aadhaar:</strong> ${aadhaarMasked}</p>
                                    <p style="margin: 5px 0;"><strong>Location:</strong> ${cleaner.city || 'N/A'}, ${cleaner.state || 'N/A'}</p>
                                </div>
                                <div style="margin-top: 15px; display: flex; gap: 10px; flex-wrap: wrap;">
                                    <button class="btn btn-primary btn-sm view-profile-btn" data-cleaner='${JSON.stringify(cleaner)}' style="padding: 6px 12px; font-size: 12px; flex: 1;">👤 Profile</button>
                                    <a href="tel:${cleaner.phone}" class="btn btn-sm" style="padding: 6px 12px; font-size: 12px; border: 1px solid #ddd;">📞 Call</a>
                                    <button class="btn btn-danger btn-sm delete-cleaner" data-id="${cleaner.id}" style="padding: 6px 12px; font-size: 12px;">🗑️</button>
                                </div>
                            </div>
                        `;
                    });
                    html += '</div>';
                    container.html(html);
                } else {
                    container.html('<p style="color: red;">Error loading cleaners: ' + response.data.message + '</p>');
                }
            },
            error: function () {
                container.html('<p style="color: red;">Error loading cleaners. Please try again.</p>');
            }
        });
    }

    // View Cleaner Profile Handler
    $(document).on('click', '.view-profile-btn', function () {
        const cleaner = JSON.parse($(this).attr('data-cleaner'));

        // Populate Modal
        $('#modal-cleaner-name').text(cleaner.name);
        $('#modal-cleaner-phone').text(cleaner.phone);
        $('#modal-cleaner-email').text(cleaner.email || 'N/A');
        $('#modal-cleaner-address').text(cleaner.address || 'N/A');
        $('#modal-cleaner-aadhaar').text(cleaner.aadhaar || 'N/A');

        // Images
        $('#modal-cleaner-photo').attr('src', cleaner.photo_url || 'https://via.placeholder.com/150?text=No+Photo');
        if (cleaner.aadhaar_image_url) {
            $('#modal-cleaner-aadhaar-img').attr('src', cleaner.aadhaar_image_url).show();
            $('#modal-cleaner-aadhaar-img').parent().find('p').show();
        } else {
            $('#modal-cleaner-aadhaar-img').hide();
            $('#modal-cleaner-aadhaar-img').parent().append('<p>No document uploaded</p>');
        }

        // Show Modal
        $('#cleaner-detail-modal').show();
    });

    // Close Modal Handler
    $('#close-cleaner-modal').on('click', function () {
        $('#cleaner-detail-modal').hide();
    });

    // Load cleaning services
    function loadCleaningServices() {
        const tbody = $('#cleaning-services-tbody');
        tbody.html('<tr><td colspan="7">Loading cleaning services...</td></tr>');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: { action: 'get_cleaning_services' },
            success: function (response) {
                if (response.success) {
                    const services = response.data;
                    if (services.length === 0) {
                        tbody.html('<tr><td colspan="7">No cleaning services yet. Bookings will appear here once customers book cleanings.</td></tr>');
                        return;
                    }

                    let html = '';
                    services.forEach(s => {
                        const paymentBadge = s.payment_status === 'paid'
                            ? '<span style="background:#d1fae5;color:#047857;padding:4px 8px;border-radius:4px;">Paid</span>'
                            : '<span style="background:#fef3c7;color:#b45309;padding:4px 8px;border-radius:4px;">Pending</span>';
                        const planLabels = {
                            'one_time': 'One-Time',
                            'monthly': 'Monthly',
                            '6_month': '6-Month',
                            'yearly': 'Yearly'
                        };

                        html += `
                            <tr class="cleaning-service-row" data-id="${s.id}" style="cursor:pointer;">
                                <td><strong>${s.customer_name}</strong><br><small>${s.customer_phone}</small></td>
                                <td>${planLabels[s.plan_type] || s.plan_type}</td>
                                <td>${s.system_size_kw} kW</td>
                                <td>${s.visits_used || 0}/${s.visits_total || 1}</td>
                                <td>${paymentBadge}</td>
                                <td>₹${Number(s.total_amount || 0).toLocaleString()}</td>
                                <td>
                                    ${s.next_visit_date
                                ? `<span style="color:#4f46e5;">✓ ${s.next_visit_date}</span>`
                                : s.preferred_date
                                    ? `<span style="color:#b45309;">⏳ Requested: ${s.preferred_date}</span><br><button class="btn btn-sm schedule-visit-btn" data-id="${s.id}">+ Schedule</button>`
                                    : '<button class="btn btn-sm schedule-visit-btn" data-id="' + s.id + '">+ Schedule</button>'}
                                </td>
                            </tr>
                        `;
                    });
                    tbody.html(html);
                } else {
                    tbody.html('<tr><td colspan="7" style="color:red;">Error loading services</td></tr>');
                }
            },
            error: function () {
                tbody.html('<tr><td colspan="7" style="color:red;">Error loading cleaning services</td></tr>');
            }
        });
    }

    // Create cleaner form submission
    $(document).on('submit', '#create-cleaner-form', function (e) {
        e.preventDefault();

        const form = $(this);
        const formData = new FormData(this);
        formData.append('action', 'create_cleaner');

        const feedback = $('#create-cleaner-feedback');
        const submitBtn = form.find('button[type="submit"]');

        submitBtn.prop('disabled', true).text('Creating...');
        feedback.html('');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                if (response.success) {
                    feedback.html(`
                        <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 8px;">
                            <strong>✅ Cleaner created successfully!</strong><br>
                            <strong>Username:</strong> ${response.data.username}<br>
                            <strong>Password:</strong> ${response.data.password}<br>
                            <em style="font-size: 12px;">Please save these credentials and share with the cleaner.</em>
                        </div>
                    `);
                    form[0].reset();
                    loadCleanersList();
                } else {
                    feedback.html(`<div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px;">❌ ${response.data.message}</div>`);
                }
            },
            error: function () {
                feedback.html('<div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px;">❌ Error creating cleaner. Please try again.</div>');
            },
            complete: function () {
                submitBtn.prop('disabled', false).text('➕ Create Cleaner Account');
            }
        });
    });

    // Delete cleaner
    $(document).on('click', '.delete-cleaner', function () {
        const cleanerId = $(this).data('id');
        if (!confirm('Are you sure you want to delete this cleaner?')) return;

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: { action: 'delete_cleaner', cleaner_id: cleanerId },
            success: function (response) {
                if (response.success) {
                    showToast('Cleaner deleted successfully', 'success');
                    loadCleanersList();
                } else {
                    showToast(response.data.message, 'error');
                }
            },
            error: function () {
                showToast('Error deleting cleaner', 'error');
            }
        });
    });

    // ===============================
    // SCHEDULE VISIT FUNCTIONALITY
    // ===============================

    // Store cleaners list for reuse
    let cleanersList = [];

    // Load cleaners for dropdown
    function loadCleanersForSchedule() {
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: { action: 'get_cleaners' },
            success: function (response) {
                if (response.success) {
                    cleanersList = response.data;
                    updateCleanerDropdown();
                }
            }
        });
    }

    function updateCleanerDropdown() {
        const select = $('#schedule_cleaner_id');
        select.empty().append('<option value="">Select Cleaner</option>');
        cleanersList.forEach(cleaner => {
            select.append(`<option value="${cleaner.id}">${cleaner.name} (📞 ${cleaner.phone})</option>`);
        });
    }

    // Open schedule visit modal
    $(document).on('click', '.schedule-visit-btn', function (e) {
        e.preventDefault();
        e.stopPropagation();

        const serviceId = $(this).data('id');
        const row = $(this).closest('tr');
        const customerName = row.find('td:first strong').text();

        $('#schedule_service_id').val(serviceId);
        $('#schedule_customer_name').text(customerName);

        // Set minimum date to today
        const today = new Date().toISOString().split('T')[0];
        $('#schedule_date').attr('min', today).val(today);

        // Load cleaners if not loaded
        if (cleanersList.length === 0) {
            loadCleanersForSchedule();
        } else {
            updateCleanerDropdown();
        }

        $('#schedule-visit-feedback').html('');
        $('#cleaner-schedule-preview').hide();
        $('#cleaner-schedule-content').html('<p style="color: #666; font-size: 14px;">Select a cleaner to view their schedule</p>');
        $('#schedule-visit-modal').show();
    });

    // Load cleaner schedule when cleaner or date changes
    $(document).on('change', '#schedule_cleaner_id, #schedule_date', function () {
        loadCleanerSchedulePreview();
    });

    // Function to load and show cleaner's schedule
    function loadCleanerSchedulePreview() {
        const cleanerId = $('#schedule_cleaner_id').val();
        const selectedDate = $('#schedule_date').val();

        if (!cleanerId) {
            $('#cleaner-schedule-preview').hide();
            return;
        }

        // Get cleaner name from dropdown
        const cleanerName = $('#schedule_cleaner_id option:selected').text();
        $('#preview-cleaner-name').text(cleanerName.split(' (')[0] || 'Cleaner\'s');
        $('#cleaner-schedule-preview').show();
        $('#cleaner-schedule-content').html('<p style="color: #666; font-size: 14px;">Loading schedule...</p>');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_cleaner_schedule',
                cleaner_id: cleanerId,
                date: selectedDate || new Date().toISOString().split('T')[0]
            },
            success: function (response) {
                if (response.success) {
                    renderCleanerSchedule(response.data, selectedDate);
                } else {
                    $('#cleaner-schedule-content').html('<p style="color: red; font-size: 14px;">Error loading schedule</p>');
                }
            },
            error: function () {
                $('#cleaner-schedule-content').html('<p style="color: red; font-size: 14px;">Error loading schedule</p>');
            }
        });
    }

    // Render cleaner's schedule
    function renderCleanerSchedule(data, selectedDate) {
        let html = '';

        // Summary stats
        html += `<div style="display: flex; gap: 10px; margin-bottom: 15px;">
            <div style="background: #e8f4fd; padding: 10px; border-radius: 6px; text-align: center; flex: 1;">
                <div style="font-size: 18px; font-weight: bold; color: #007bff;">${data.today_count || 0}</div>
                <div style="font-size: 11px; color: #666;">Today</div>
            </div>
            <div style="background: #fff3cd; padding: 10px; border-radius: 6px; text-align: center; flex: 1;">
                <div style="font-size: 18px; font-weight: bold; color: #856404;">${data.week_count || 0}</div>
                <div style="font-size: 11px; color: #666;">This Week</div>
            </div>
            <div style="background: #d4edda; padding: 10px; border-radius: 6px; text-align: center; flex: 1;">
                <div style="font-size: 18px; font-weight: bold; color: #155724;">${data.total_completed || 0}</div>
                <div style="font-size: 11px; color: #666;">Completed</div>
            </div>
        </div>`;

        // Selected date visits
        if (selectedDate) {
            const dateFormatted = new Date(selectedDate).toLocaleDateString('en-IN', { weekday: 'short', day: '2-digit', month: 'short' });
            html += `<h5 style="margin-bottom: 10px; color: #333;">📅 ${dateFormatted}</h5>`;

            if (data.date_visits && data.date_visits.length > 0) {
                html += `<div style="background: #fff3cd; padding: 10px; border-radius: 6px; margin-bottom: 10px;">
                    <strong style="color: #856404;">⚠️ ${data.date_visits.length} visit(s) already scheduled</strong>
                </div>`;

                data.date_visits.forEach(visit => {
                    html += `<div style="background: white; padding: 10px; border-radius: 6px; margin-bottom: 8px; border-left: 3px solid #007bff;">
                        <div style="font-weight: 500;">${visit.customer_name || 'Customer'}</div>
                        <div style="font-size: 12px; color: #666;">🕐 ${visit.scheduled_time || 'TBD'} | ${visit.status || 'scheduled'}</div>
                    </div>`;
                });
            } else {
                html += `<div style="background: #d4edda; padding: 10px; border-radius: 6px;">
                    <strong style="color: #155724;">✅ No visits scheduled on this date</strong>
                </div>`;
            }
        }

        // Upcoming visits (next 5)
        if (data.upcoming_visits && data.upcoming_visits.length > 0) {
            html += `<h5 style="margin: 15px 0 10px; color: #333;">📋 Upcoming Visits</h5>`;
            data.upcoming_visits.slice(0, 5).forEach(visit => {
                const visitDate = new Date(visit.scheduled_date).toLocaleDateString('en-IN', { day: '2-digit', month: 'short' });
                html += `<div style="background: white; padding: 8px; border-radius: 4px; margin-bottom: 5px; font-size: 13px; display: flex; justify-content: space-between;">
                    <span>${visitDate} - ${visit.customer_name || 'Customer'}</span>
                    <span style="color: #666;">${visit.scheduled_time || ''}</span>
                </div>`;
            });
        }

        $('#cleaner-schedule-content').html(html);
    }

    // Close modals
    $(document).on('click', '#schedule-visit-modal .close-modal', function () {
        $('#schedule-visit-modal').hide();
    });

    $(document).on('click', '#service-detail-modal .close-modal', function () {
        $('#service-detail-modal').hide();
    });

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
            feedback.html('<div class="alert alert-danger">Please select a cleaner</div>');
            return;
        }

        submitBtn.prop('disabled', true).text('Scheduling...');
        feedback.html('');

        $.ajax({
            url: ajaxUrl,
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
                        loadCleaningServices(); // Refresh the table
                    }, 1500);
                } else {
                    feedback.html('<div style="background:#f8d7da;color:#721c24;padding:10px;border-radius:6px;">❌ ' + response.data.message + '</div>');
                }
            },
            error: function () {
                feedback.html('<div style="background:#f8d7da;color:#721c24;padding:10px;border-radius:6px;">❌ Error scheduling visit. Please try again.</div>');
            },
            complete: function () {
                submitBtn.prop('disabled', false).text('+ Schedule Visit');
            }
        });
    });

    // Click on service row to view details
    $(document).on('click', '.cleaning-service-row', function (e) {
        // Don't trigger if clicking on a button
        if ($(e.target).is('button') || $(e.target).closest('button').length) {
            return;
        }

        const serviceId = $(this).data('id');
        loadServiceDetails(serviceId);
    });

    // Load service details with visit history
    function loadServiceDetails(serviceId) {
        const content = $('#service-detail-content');
        content.html('<p>Loading service details...</p>');
        $('#service-detail-modal').show();

        $.ajax({
            url: ajaxUrl,
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
                                ${s.visits_used || 0} / ${s.visits_total || 1} Used<br>
                                <small>${(s.visits_total - s.visits_used) || 1} Remaining</small>
                            </div>
                            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                                <strong>💰 Payment</strong><br>
                                ₹${Number(s.total_amount || 0).toLocaleString()}<br>
                                <small style="color: ${s.payment_status === 'paid' ? '#047857' : '#b45309'}">${s.payment_status === 'paid' ? '✅ Paid' : '⏳ Pending'}</small>
                            </div>
                        </div>
                        
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h4 style="margin: 0;">🗓️ Visit History</h4>
                            ${(s.visits_used < s.visits_total) ? `<button class="btn btn-primary schedule-visit-btn" data-id="${serviceId}" style="padding: 8px 15px;">+ Schedule Visit</button>` : ''}
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
                                        <span style="background: ${statusColors[visit.status] || '#e5e7eb'}; padding: 4px 10px; border-radius: 4px;">
                                            ${statusIcons[visit.status] || '❓'} ${visit.status}
                                        </span>
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
                content.html('<p style="color: red;">Error loading service details. Please try again.</p>');
            }
        });
    }

    // Load cleaners when cleaning services section is activated
    $(document).on('click', '[data-section="cleaning-services"]', function () {
        loadCleanersForSchedule();
    });

    // ========================================
    // MY TEAM FUNCTIONS (for Area Managers to view their Sales Managers)
    // ========================================

    function loadMyTeam() {
        console.log('Loading my team data...');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_am_team_data',
                nonce: sp_area_dashboard_vars.get_projects_nonce
            },
            success: function (response) {
                if (response.success) {
                    renderMyTeam(response.data);
                } else {
                    console.error('Failed to load team data:', response);
                    $('#my-team-sm-tbody').html('<tr><td colspan="7">Error loading team. ' + (response.data?.message || '') + '</td></tr>');
                }
            },
            error: function (xhr, status, error) {
                console.error('AJAX error loading team data:', error);
                $('#my-team-sm-tbody').html('<tr><td colspan="7">Error loading team. Please try again.</td></tr>');
            }
        });
    }

    function renderMyTeam(data) {
        // Update stats
        const smCount = data.sales_managers?.length || 0;
        let totalLeads = 0;
        let totalConversions = 0;

        if (data.sales_managers) {
            data.sales_managers.forEach(sm => {
                totalLeads += parseInt(sm.lead_count) || 0;
                totalConversions += parseInt(sm.conversion_count) || 0;
            });
        }

        $('#my-team-sm-count').text(smCount);
        $('#my-team-leads-count').text(totalLeads);
        $('#my-team-conversions-count').text(totalConversions);

        // Render Sales Managers table
        let smHtml = '';
        if (data.sales_managers && data.sales_managers.length > 0) {
            data.sales_managers.forEach(sm => {
                const leads = parseInt(sm.lead_count) || 0;
                const conversions = parseInt(sm.conversion_count) || 0;
                const rate = leads > 0 ? ((conversions / leads) * 100).toFixed(1) : '0.0';
                const phone = sm.phone || '-';
                const whatsappLink = phone !== '-' ? `https://wa.me/${phone.replace(/[^0-9]/g, '')}` : '#';

                smHtml += `
                    <tr>
                        <td><strong>${sm.display_name || 'N/A'}</strong></td>
                        <td>${sm.email || 'N/A'}</td>
                        <td>${phone}</td>
                        <td>${leads}</td>
                        <td>${conversions}</td>
                        <td><span class="badge ${rate >= 20 ? 'badge-success' : rate >= 10 ? 'badge-warning' : 'badge-info'}">${rate}%</span></td>
                        <td>
                            <button class="action-btn action-btn-primary view-sm-leads" 
                                    data-sm-id="${sm.ID}" 
                                    data-sm-name="${sm.display_name}" 
                                    title="View Leads">📋 View Leads</button>
                            ${phone !== '-' ? `<a href="${whatsappLink}" target="_blank" class="action-btn action-btn-success" title="WhatsApp">📱</a>` : ''}
                            <a href="mailto:${sm.email}" class="action-btn action-btn-info" title="Email">✉️</a>
                        </td>
                    </tr>
                `;
            });
        } else {
            smHtml = '<tr><td colspan="7">No Sales Managers assigned to you yet</td></tr>';
        }
        $('#my-team-sm-tbody').html(smHtml);
    }

    // --- SM Leads Detail Functions ---
    let currentSmId = null;
    let currentSmLeads = [];

    // View SM Leads button click handler
    $(document).on('click', '.view-sm-leads', function () {
        const smId = $(this).data('sm-id');
        const smName = $(this).data('sm-name');

        currentSmId = smId;
        $('#selected-sm-name').text(smName);
        $('#sm-leads-detail-panel').slideDown();
        $('#sm-leads-status-filter').val('');
        $('#sm-leads-search').val('');

        loadSmLeads(smId);
    });

    // Close SM leads panel
    $(document).on('click', '#close-sm-leads-panel', function () {
        $('#sm-leads-detail-panel').slideUp();
        currentSmId = null;
        currentSmLeads = [];
    });

    // Load SM leads
    function loadSmLeads(smId) {
        $('#sm-leads-tbody').html('<tr><td colspan="7">Loading leads...</td></tr>');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_sm_leads_for_am',
                sm_id: smId,
                nonce: sp_area_dashboard_vars.get_leads_nonce
            },
            success: function (response) {
                if (response.success) {
                    currentSmLeads = response.data.leads || [];
                    renderSmLeads(currentSmLeads);
                } else {
                    $('#sm-leads-tbody').html('<tr><td colspan="7">Error: ' + (response.data?.message || 'Failed to load leads') + '</td></tr>');
                }
            },
            error: function () {
                $('#sm-leads-tbody').html('<tr><td colspan="7">Error loading leads. Please try again.</td></tr>');
            }
        });
    }

    // Render SM leads table
    function renderSmLeads(leads) {
        const statusFilter = $('#sm-leads-status-filter').val();
        const searchTerm = $('#sm-leads-search').val().toLowerCase();

        let filteredLeads = leads.filter(lead => {
            if (statusFilter && lead.status !== statusFilter) return false;
            if (searchTerm) {
                const searchableText = `${lead.name} ${lead.phone} ${lead.email} ${lead.city} ${lead.notes}`.toLowerCase();
                if (!searchableText.includes(searchTerm)) return false;
            }
            return true;
        });

        let html = '';
        if (filteredLeads.length > 0) {
            filteredLeads.forEach(lead => {
                const statusBadge = getStatusBadge(lead.status);
                const location = [lead.city, lead.state].filter(Boolean).join(', ') || '-';
                const createdDate = lead.created_date ? formatDate(lead.created_date) : '-';
                const lastFollowup = lead.last_followup ? formatDate(lead.last_followup) : 'No followups';

                html += `
                    <tr>
                        <td><strong>${lead.name || 'N/A'}</strong></td>
                        <td>${lead.phone || '-'}</td>
                        <td>${statusBadge}</td>
                        <td>${location}</td>
                        <td>${createdDate}</td>
                        <td>${lastFollowup}</td>
                        <td>
                            <button class="action-btn action-btn-info view-lead-followups" 
                                    data-lead-id="${lead.id}" 
                                    data-lead-name="${lead.name}"
                                    data-lead-phone="${lead.phone || ''}"
                                    data-lead-email="${lead.email || ''}"
                                    title="View Followups">📝 History</button>
                            ${lead.phone ? `<a href="https://wa.me/${lead.phone.replace(/[^0-9]/g, '')}" target="_blank" class="action-btn action-btn-success" title="WhatsApp">📱</a>` : ''}
                        </td>
                    </tr>
                `;
            });
        } else {
            html = '<tr><td colspan="7">No leads found' + (statusFilter || searchTerm ? ' matching your filters' : '') + '</td></tr>';
        }
        $('#sm-leads-tbody').html(html);
    }

    // Status filter and search handlers
    $(document).on('change', '#sm-leads-status-filter', function () {
        renderSmLeads(currentSmLeads);
    });

    $(document).on('input', '#sm-leads-search', function () {
        renderSmLeads(currentSmLeads);
    });

    // Helper function for status badge
    function getStatusBadge(status) {
        const badges = {
            'new': '<span class="badge badge-info">New</span>',
            'contacted': '<span class="badge badge-primary">Contacted</span>',
            'qualified': '<span class="badge badge-warning">Qualified</span>',
            'proposal': '<span class="badge badge-secondary">Proposal</span>',
            'negotiation': '<span class="badge badge-warning">Negotiation</span>',
            'converted': '<span class="badge badge-success">Converted</span>',
            'lost': '<span class="badge badge-danger">Lost</span>'
        };
        return badges[status] || `<span class="badge">${status || 'Unknown'}</span>`;
    }

    // Helper function for date formatting
    function formatDate(dateStr) {
        if (!dateStr) return '-';
        const date = new Date(dateStr);
        return date.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
    }

    // View Lead Followup History
    $(document).on('click', '.view-lead-followups', function () {
        const leadId = $(this).data('lead-id');
        const leadName = $(this).data('lead-name');
        const leadPhone = $(this).data('lead-phone');
        const leadEmail = $(this).data('lead-email');

        $('#followup-lead-name').text(leadName);
        $('#followup-lead-contact').html(`📞 ${leadPhone || 'N/A'} | ✉️ ${leadEmail || 'N/A'}`);
        $('#followup-timeline').html('<p>Loading followup history...</p>');
        $('#lead-followup-modal').show();

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_lead_followup_history',
                lead_id: leadId,
                nonce: sp_area_dashboard_vars.get_leads_nonce
            },
            success: function (response) {
                if (response.success && response.data.followups) {
                    renderFollowupTimeline(response.data.followups);
                } else {
                    $('#followup-timeline').html('<p style="color: #666;">No followup records found for this lead.</p>');
                }
            },
            error: function () {
                $('#followup-timeline').html('<p style="color: red;">Error loading followup history.</p>');
            }
        });
    });

    // Render followup timeline
    function renderFollowupTimeline(followups) {
        if (!followups || followups.length === 0) {
            $('#followup-timeline').html('<p style="color: #666;">No followup records found for this lead.</p>');
            return;
        }

        let html = '<div class="timeline">';
        followups.forEach((f, index) => {
            const date = f.created_at ? formatDate(f.created_at) : 'Unknown date';
            const icon = f.type === 'call' ? '📞' : f.type === 'email' ? '✉️' : f.type === 'whatsapp' ? '💬' : f.type === 'meeting' ? '🤝' : '📝';
            const typeLabel = f.type ? f.type.charAt(0).toUpperCase() + f.type.slice(1) : 'Note';

            html += `
                <div class="timeline-item" style="border-left: 2px solid #007bff; padding-left: 20px; margin-bottom: 20px; position: relative;">
                    <div style="position: absolute; left: -9px; top: 0; width: 16px; height: 16px; background: #007bff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 10px;">${icon}</div>
                    <div style="background: ${index === 0 ? '#e8f4fd' : '#f8f9fa'}; padding: 15px; border-radius: 8px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <strong style="color: #007bff;">${typeLabel}</strong>
                            <span style="color: #666; font-size: 12px;">${date}</span>
                        </div>
                        <p style="margin: 0; color: #333;">${f.notes || 'No notes'}</p>
                        ${f.outcome ? `<p style="margin-top: 8px; font-size: 13px; color: #666;"><strong>Outcome:</strong> ${f.outcome}</p>` : ''}
                        ${f.next_action ? `<p style="margin-top: 4px; font-size: 13px; color: #28a745;"><strong>Next Action:</strong> ${f.next_action} (${f.next_action_date ? formatDate(f.next_action_date) : 'TBD'})</p>` : ''}
                    </div>
                </div>
            `;
        });
        html += '</div>';
        $('#followup-timeline').html(html);
    }

    // Close followup modal
    $(document).on('click', '[data-modal="lead-followup-modal"]', function () {
        $('#lead-followup-modal').hide();
    });
    $(document).on('click', '#lead-followup-modal', function (e) {
        if (e.target === this) $(this).hide();
    });


    // ========================================
    // MANAGER-SPECIFIC FUNCTIONS (shown for manager role only)
    // ========================================

    // --- Team Analysis Functions ---
    function loadTeamAnalysis() {
        console.log('Loading team analysis data...');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_manager_team_data',
                nonce: sp_area_dashboard_vars.get_projects_nonce
            },
            success: function (response) {
                if (response.success) {
                    renderTeamAnalysis(response.data);
                } else {
                    console.error('Failed to load team data:', response);
                    $('#team-am-tbody').html('<tr><td colspan="6">Error loading data. ' + (response.data?.message || '') + '</td></tr>');
                }
            },
            error: function (xhr, status, error) {
                console.error('AJAX error loading team data:', error);
                $('#team-am-tbody').html('<tr><td colspan="6">Error loading data. Please try again.</td></tr>');
            }
        });
    }

    function renderTeamAnalysis(data) {
        // Update stats
        $('#team-am-count').text(data.area_managers?.length || 0);
        $('#team-sm-count').text(data.sales_managers?.length || 0);
        $('#team-cleaner-count').text(data.cleaners?.length || 0);
        $('#team-project-count').text(data.total_projects || 0);

        // Render Area Managers table
        let amHtml = '';
        if (data.area_managers && data.area_managers.length > 0) {
            data.area_managers.forEach(am => {
                amHtml += `
                    <tr>
                        <td>${am.display_name || 'N/A'}</td>
                        <td>${am.email || 'N/A'}</td>
                        <td>${am.city || '-'}</td>
                        <td>${am.state || '-'}</td>
                        <td>${am.project_count || 0}</td>
                        <td>${am.team_size || 0}</td>
                    </tr>
                `;
            });
        } else {
            amHtml = '<tr><td colspan="6">No area managers in your assigned states</td></tr>';
        }
        $('#team-am-tbody').html(amHtml);

        // Render Sales Managers table
        let smHtml = '';
        if (data.sales_managers && data.sales_managers.length > 0) {
            data.sales_managers.forEach(sm => {
                smHtml += `
                    <tr>
                        <td>${sm.display_name || 'N/A'}</td>
                        <td>${sm.email || 'N/A'}</td>
                        <td>${sm.supervising_am || '-'}</td>
                        <td>${sm.lead_count || 0}</td>
                        <td>${sm.conversion_count || 0}</td>
                    </tr>
                `;
            });
        } else {
            smHtml = '<tr><td colspan="5">No sales managers found</td></tr>';
        }
        $('#team-sm-tbody').html(smHtml);

        // Render Cleaners table
        let cleanerHtml = '';
        if (data.cleaners && data.cleaners.length > 0) {
            data.cleaners.forEach(cleaner => {
                const statusClass = cleaner.status === 'active' ? 'status-success' : 'status-warning';
                cleanerHtml += `
                    <tr>
                        <td>${cleaner.name || 'N/A'}</td>
                        <td>${cleaner.phone || '-'}</td>
                        <td>${cleaner.supervising_am || '-'}</td>
                        <td>${cleaner.completed_visits || 0}</td>
                        <td><span class="badge ${statusClass}">${cleaner.status || 'active'}</span></td>
                    </tr>
                `;
            });
        } else {
            cleanerHtml = '<tr><td colspan="5">No cleaners found</td></tr>';
        }
        $('#team-cleaners-tbody').html(cleanerHtml);
    }

    // --- AM Assignment Functions ---
    function loadAMAssignments() {
        console.log('Loading AM assignments...');
        loadAMDropdown();
        loadAssignmentsTable();
    }

    function loadAMDropdown() {
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_unassigned_area_managers',
                nonce: sp_area_dashboard_vars.get_projects_nonce
            },
            success: function (response) {
                let html = '<option value="">Select Area Manager</option>';
                if (response.success && response.data.managers) {
                    response.data.managers.forEach(am => {
                        html += `<option value="${am.ID}">${am.display_name} (${am.email})</option>`;
                    });
                }
                $('#assign_am_id').html(html);
            }
        });
    }

    function loadAssignmentsTable() {
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_am_location_assignments',
                nonce: sp_area_dashboard_vars.get_projects_nonce
            },
            success: function (response) {
                let html = '';
                if (response.success && response.data.assignments && response.data.assignments.length > 0) {
                    response.data.assignments.forEach(assignment => {
                        html += `
                            <tr>
                                <td>${assignment.am_name}</td>
                                <td>${assignment.state}</td>
                                <td>${assignment.city}</td>
                                <td>
                                    <button class="action-btn action-btn-danger remove-am-assignment" 
                                            data-am-id="${assignment.am_id}">
                                        🗑️ Remove
                                    </button>
                                </td>
                            </tr>
                        `;
                    });
                } else {
                    html = '<tr><td colspan="4">No area assignments found</td></tr>';
                }
                $('#am-assignments-tbody').html(html);
            }
        });
    }

    // State change handler for city dropdown
    $(document).on('change', '#assign_state', function () {
        const state = $(this).val();
        if (!state) {
            $('#assign_city').html('<option value="">Select state first</option>');
            return;
        }

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_cities_for_state',
                state: state
            },
            success: function (response) {
                let html = '<option value="">Select City</option>';
                if (response.success && response.data.cities) {
                    response.data.cities.forEach(city => {
                        html += `<option value="${city}">${city}</option>`;
                    });
                }
                $('#assign_city').html(html);
            }
        });
    });

    // AM Assignment form submission
    $(document).on('submit', '#assign-am-location-form', function (e) {
        e.preventDefault();

        const $form = $(this);
        const $btn = $form.find('button[type="submit"]');
        const $feedback = $('#assign-am-feedback');

        $btn.prop('disabled', true).text('Assigning...');
        $feedback.html('');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'assign_area_manager_location',
                manager_id: $('#assign_am_id').val(),
                state: $('#assign_state').val(),
                city: $('#assign_city').val()
            },
            success: function (response) {
                if (response.success) {
                    $feedback.html('<p class="text-success">✅ ' + response.data.message + '</p>');
                    $form[0].reset();
                    loadAssignmentsTable();
                    loadAMDropdown();
                } else {
                    $feedback.html('<p class="text-danger">❌ ' + (response.data?.message || 'Error assigning area') + '</p>');
                }
            },
            error: function () {
                $feedback.html('<p class="text-danger">❌ Error assigning area. Please try again.</p>');
            },
            complete: function () {
                $btn.prop('disabled', false).text('Assign Area');
            }
        });
    });

    // Remove AM assignment
    $(document).on('click', '.remove-am-assignment', function () {
        if (!confirm('Are you sure you want to remove this area assignment?')) return;

        const amId = $(this).data('am-id');
        const $btn = $(this);

        $btn.prop('disabled', true).text('Removing...');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'remove_area_manager_location',
                manager_id: amId
            },
            success: function (response) {
                if (response.success) {
                    showToast('Area assignment removed successfully', 'success');
                    loadAssignmentsTable();
                    loadAMDropdown();
                } else {
                    showToast(response.data?.message || 'Error removing assignment', 'error');
                    $btn.prop('disabled', false).text('🗑️ Remove');
                }
            },
            error: function () {
                showToast('Error removing assignment. Please try again.', 'error');
                $btn.prop('disabled', false).text('🗑️ Remove');
            }
        });
    });

    // Initial Load
    loadDashboardStats();


})(jQuery);