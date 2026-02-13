/**
 * Manager Dashboard JavaScript
 * Extends Area Manager functionality with Team Analysis and AM Assignment
 */
(function ($) {
    'use strict';

    // console.log('Manager Dashboard Script Initialized');

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
    // Delegated to DashboardUtils
    $(document).ready(function () {
        // Initialize Lead Component early to set config
        if (typeof initLeadComponent === 'function') {
            initLeadComponent();
        }

        if (typeof DashboardUtils !== 'undefined') {
            DashboardUtils.setupTabNavigation('.manager-dashboard');
        }
    });

    // Handle deep linking for tabs - handled by DashboardUtils


    // --- Toast Notification Helper ---
    // --- Toast Notification Helper ---
    // Delegated to DashboardUtils
    function showToast(message, type = 'info') {
        if (typeof DashboardUtils !== 'undefined') {
            DashboardUtils.showToast(message, type);
        } else {
            console.error('DashboardUtils not loaded');
            alert(message);
        }
    }
    // Expose showToast globally for shared components
    window.showToast = showToast;

    // removeToast is handled internally by DashboardUtils


    // --- Initialize AJAX URL and nonces FIRST (before any functions use them) ---
    const ajaxUrl = (typeof sp_area_dashboard_vars !== 'undefined') ? sp_area_dashboard_vars.ajax_url : '';

    if (!ajaxUrl) {
        // console.error('Manager Dashboard: sp_area_dashboard_vars is undefined or missing ajax_url.');
        return;
    }

    const createProjectNonce = sp_area_dashboard_vars.create_project_nonce;
    const projectDetailsNonce = sp_area_dashboard_vars.project_details_nonce;

    // Track if shared lead component is initialized
    let leadComponentInitialized = false;

    // Forward declaration for loadBids (defined later, used by window.loadSectionData)
    var loadBids;

    // Initialize shared components
    function initComponents() {
        // Initialize Lead Component
        if (!leadComponentInitialized && typeof window.initLeadComponent === 'function') {
            window.initLeadComponent(ajaxUrl, sp_area_dashboard_vars.get_leads_nonce);
            leadComponentInitialized = true;
        } else if (typeof window.loadLeads === 'function') {
            window.loadLeads();
        }

        // Initialize Project Modal Component
        if (typeof ProjectModalComponent !== 'undefined') {
            ProjectModalComponent.init({
                ajaxUrl: ajaxUrl,
                nonces: {
                    project_details_nonce: sp_area_dashboard_vars.project_details_nonce
                }
            });
        }
    }

    // Call init
    initComponents();

    // Alias for backward compatibility (if needed)
    function initLeadComponent() {
        if (!leadComponentInitialized && typeof window.initLeadComponent === 'function') {
            window.initLeadComponent(ajaxUrl, sp_area_dashboard_vars.get_leads_nonce);
            leadComponentInitialized = true;
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
                            html += `<div style="font-weight:600; color:#333;">🔔 ${n.type.replace('_', ' ').toUpperCase()}</div>`;
                            html += `<div style="font-size:13px; color:#333; margin-top:4px;">${n.message}</div>`;
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
        // console.log('Loading dashboard stats...');
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_area_manager_dashboard_stats',
                nonce: sp_area_dashboard_vars.get_dashboard_stats_nonce,
            },
            success: function (response) {
                // console.log('Stats response:', response);
                if (response.success) {
                    updateDashboardStats(response.data);
                    initializeCharts(response.data);
                } else {
                    // console.error('Stats error:', response);
                }
            },
            error: function (xhr, status, error) {
                // console.error('AJAX error loading stats:', error);
            }
        });
    }

    function updateDashboardStats(stats) {
        // console.log('=== UPDATING DASHBOARD STATS ===');
        // console.log('Stats object received:', stats);
        // console.log('Total Projects:', stats.total_projects);
        // console.log('Total Revenue:', stats.total_revenue);
        // console.log('DOM element #total-projects-stat exists:', $('#total-projects-stat').length > 0);

        $('#total-projects-stat').text(stats.total_projects || 0);
        $('#total-revenue-stat').text('₹' + (stats.total_revenue || 0).toLocaleString('en-IN'));
        $('#client-payments-stat').text('₹' + (stats.total_client_payments || 0).toLocaleString('en-IN'));
        $('#outstanding-balance-stat').text('₹' + (stats.total_outstanding || 0).toLocaleString('en-IN'));
        $('#total-costs-stat').text('₹' + (stats.total_costs || 0).toLocaleString('en-IN'));
        $('#total-profit-stat').text('₹' + (stats.total_profit || 0).toLocaleString('en-IN'));
        $('#profit-margin-stat').text((stats.profit_margin || 0).toFixed(1) + '%');
        $('#collection-rate-stat').text((stats.collection_rate || 0).toFixed(1) + '%');
        $('#total-leads-stat').text(stats.total_leads || 0);

        // console.log('Stats updated. Checking DOM values:');
        // console.log('  Projects stat now shows:', $('#total-projects-stat').text());
        // console.log('  Revenue stat now shows:', $('#total-revenue-stat').text());
        // console.log('=== END STATS UPDATE ===');
    }

    function initializeCharts(stats) {
        if (typeof Chart === 'undefined') {
            // console.log('Chart.js not loaded');
            return;
        }
        // console.log('Charts would be initialized here with:', stats);
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
        // console.log('Loading section data for:', section);
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
            // console.log('Triggering shared lead component...');
            initLeadComponent();
        } else if (section === 'my-clients') {
            loadMyClients();
        } else if (section === 'manage-cleaners') {
            if (window.loadCleaners) window.loadCleaners();
        } else if (section === 'cleaning-services') {
            loadCleaningServices();
        } else if (section === 'team-analysis') {
            loadTeamAnalysis();
        } else if (section === 'am-assignment') {
            loadAMAssignments();
        }
    };

    // Load data when section becomes visible
    $(document).on('click', '.nav-item', function () {
        const section = $(this).data('section');
        // console.log('Nav clicked, section:', section);

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
        // console.log('Document ready, checking initial section');
        setTimeout(function () {
            if ($('#dashboard-section').is(':visible')) {
                // console.log('Dashboard visible on load, loading stats');
                loadDashboardStats();
            }
            if ($('#leads-section').is(':visible')) {
                // console.log('Leads visible on load, initializing lead component');
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
            // console.log('Initial dashboard stats load');
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
            // console.log('Custom date selected:', $(this).val());
            filterProjects();
        }
    });

    // Attach filter listeners for status
    $(document).on('change', '#filter-status', filterProjects);

    // View project details button handler
    // Handled by ProjectModalComponent
    /*
    $(document).on('click', '.view-project-details', function () {
        const projectId = $(this).data('id');
        // console.log('View project details:', projectId);
        if (typeof openProjectModal === 'function') {
            openProjectModal(projectId);
        } else {
            // console.error('openProjectModal function not found');
        }
    });
    */

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
        // console.log('Loading project for edit:', projectId);

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
                // console.error('Error fetching project:', error);
                showToast('Failed to load project data', 'error');
                submitBtn.prop('disabled', false).text(originalText);
            }
        });
    }

    // Populate Create Form with Project Data for Editing
    function populateCreateFormForEdit(data, projectId) {
        const project = data.project;
        const meta = data.meta;

        // console.log('Populating create form for edit:', data);

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
    function loadReviews(filter = 'pending') {
        const container = $('#project-reviews-container');

        // Add filter buttons if not already added
        if (!$('#review-filter-buttons').length) {
            container.before(`
                <div id="review-filter-buttons" style="margin-bottom: 20px; display: flex; gap: 10px;">
                    <button class="review-filter-btn" data-filter="pending" style="padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 14px; transition: all 0.2s; background: #3b82f6; color: white; border: none;">
                        📝 Pending Reviews
                    </button>
                    <button class="review-filter-btn" data-filter="all" style="padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 14px; transition: all 0.2s; background: white; color: #6b7280; border: 2px solid #e5e7eb;">
                        📋 All Reviews
                    </button>
                </div>
            `);
        }

        // Update active button styling
        $('.review-filter-btn').css({ background: 'white', color: '#6b7280', border: '2px solid #e5e7eb' });
        $('.review-filter-btn[data-filter="' + filter + '"]').css({ background: '#3b82f6', color: 'white', border: 'none' });

        container.html('<p>Loading reviews...</p>');
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_area_manager_reviews',
                nonce: sp_area_dashboard_vars.get_reviews_nonce,
                filter: filter
            },
            success: function (response) {
                if (response.success) {
                    let html = '';
                    if (response.data.reviews.length > 0) {
                        // console.log('Reviews data:', response.data.reviews);
                        // Group reviews by project
                        const projectGroups = {};
                        response.data.reviews.forEach(review => {
                            if (!projectGroups[review.project_id]) {
                                projectGroups[review.project_id] = {
                                    project_title: review.project_title || `Project #${review.project_id}`,
                                    project_city: review.project_city,
                                    project_state: review.project_state,
                                    system_size: review.system_size,
                                    total_cost: review.total_cost,
                                    project_status: review.project_status,
                                    client_name: review.client_name,
                                    vendor_name: review.vendor_name,
                                    progress: review.progress,
                                    am_id: review.am_id,
                                    reviews: []
                                };
                            }
                            projectGroups[review.project_id].reviews.push(review);
                        });

                        // Render each collapsible project card
                        Object.keys(projectGroups).forEach(projectId => {
                            const group = projectGroups[projectId];
                            const reviewCount = group.reviews.length;

                            // Status badge styling
                            const statusColors = {
                                'pending': { bg: '#fef3c7', text: '#b45309' },
                                'in_progress': { bg: '#dbeafe', text: '#1e40af' },
                                'completed': { bg: '#d1fae5', text: '#065f46' }
                            };
                            const status = group.project_status || 'pending';
                            const statusColor = statusColors[status] || statusColors['pending'];
                            const statusLabel = status.replace('_', ' ').toUpperCase();

                            html += `
                                <div class="project-review-card" data-project-id="${projectId}" style="background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 20px; overflow: hidden;">
                                    <!-- Project Summary (Always Visible) -->
                                    <div class="project-summary" style="padding: 20px; border-bottom: 2px solid #f0f0f0;">
                                        <!-- Header with Title and Status -->
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                            <h3 style="margin: 0; font-size: 18px; color: #1f2937;">${group.project_title}</h3>
                                            <span style="background: ${statusColor.bg}; color: ${statusColor.text}; padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 700;">
                                                ${statusLabel}
                                            </span>
                                        </div>
                                        
                                        <!-- Info Grid (Like Admin) -->
                                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-bottom: 15px;">
                                            ${group.client_name ? `
                                                <div style="display: flex; align-items: center; gap: 8px;">
                                                    <span style="font-size: 20px;">👤</span>
                                                    <div>
                                                        <div style="font-size: 11px; color: #9ca3af; font-weight: 600; text-transform: uppercase;">Client</div>
                                                        <div style="font-size: 14px; color: #1f2937; font-weight: 600;">${group.client_name}</div>
                                                    </div>
                                                </div>
                                            ` : ''}
                                            
                                            ${group.vendor_name ? `
                                                <div style="display: flex; align-items: center; gap: 8px;">
                                                    <span style="font-size: 20px;">🏢</span>
                                                    <div>
                                                        <div style="font-size: 11px; color: #9ca3af; font-weight: 600; text-transform: uppercase;">Vendor</div>
                                                        <div style="font-size: 14px; color: #1f2937; font-weight: 600;">${group.vendor_name}</div>
                                                    </div>
                                                </div>
                                            ` : ''}
                                            
                                            ${group.project_city || group.project_state ? `
                                                <div style="display: flex; align-items: center; gap: 8px;">
                                                    <span style="font-size: 20px;">📍</span>
                                                    <div>
                                                        <div style="font-size: 11px; color: #9ca3af; font-weight: 600; text-transform: uppercase;">Location</div>
                                                        <div style="font-size: 14px; color: #1f2937; font-weight: 600;">${group.project_city || ''}${group.project_city && group.project_state ? ', ' : ''}${group.project_state || ''}</div>
                                                    </div>
                                                </div>
                                            ` : ''}
                                            
                                            ${group.system_size ? `
                                                <div style="display: flex; align-items: center; gap: 8px;">
                                                    <span style="font-size: 20px;">⚡</span>
                                                    <div>
                                                        <div style="font-size: 11px; color: #9ca3af; font-weight: 600; text-transform: uppercase;">System Size</div>
                                                        <div style="font-size: 14px; color: #1f2937; font-weight: 600;">${group.system_size} kW</div>
                                                    </div>
                                                </div>
                                            ` : ''}
                                            
                                            ${group.total_cost ? `
                                                <div style="display: flex; align-items: center; gap: 8px;">
                                                    <span style="font-size: 20px;">💰</span>
                                                    <div>
                                                        <div style="font-size: 11px; color: #9ca3af; font-weight: 600; text-transform: uppercase;">Total Cost</div>
                                                        <div style="font-size: 14px; color: #1f2937; font-weight: 600;">₹${parseFloat(group.total_cost).toLocaleString('en-IN')}</div>
                                                    </div>
                                                </div>
                                            ` : ''}
                                            
                                            ${group.progress !== undefined ? `
                                                <div style="display: flex; align-items: center; gap: 8px;">
                                                    <span style="font-size: 20px;">📊</span>
                                                    <div>
                                                        <div style="font-size: 11px; color: #9ca3af; font-weight: 600; text-transform: uppercase;">Progress</div>
                                                        <div style="font-size: 14px; color: #1f2937; font-weight: 600;">${group.progress}%</div>
                                                    </div>
                                                </div>
                                            ` : ''}
                                        </div>
                                        
                                        <!-- Pending Reviews Badge and Button -->
                                        <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 12px; border-top: 1px solid #f0f0f0;">
                                            <span style="background: #fef3c7; color: #b45309; padding: 6px 14px; border-radius: 20px; font-size: 13px; font-weight: 600;">
                                                🟡 ${reviewCount} pending review${reviewCount !== 1 ? 's' : ''}
                                            </span>
                                            <button class="btn-review-toggle" onclick="toggleProjectReviews(${projectId})" style="background: #3b82f6; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 14px; transition: background 0.2s;">
                                                Review Project →
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Review Details (Initially Hidden) -->
                                    <div class="reviews-details" id="reviews-${projectId}" style="display: none; padding: 20px; background: #fafafa;">
                                        <div style="margin-bottom: 15px; text-align: right;">
                                            <button onclick="toggleProjectReviews(${projectId})" style="background: #6b7280; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-size: 13px;">
                                                ▲ Hide Reviews
                                            </button>
                                        </div>
                            `;

                            // Render each step as a card
                            group.reviews.forEach((review, index) => {
                                html += `
                                    <div class="step-review-card" style="background: white; padding: 20px; border-radius: 10px; margin-bottom: 15px; border-left: 4px solid #3b82f6; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <h4 style="margin: 0 0 15px 0; color: #1f2937; font-size: 16px; display: flex; align-items: center; gap: 10px;">
                                            <span style="background: #3b82f6; color: white; width: 28px; height: 28px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 14px; font-weight: bold;">
                                                ${review.step_number}
                                            </span>
                                            ${review.step_name}
                                        </h4>
                                        
                                        ${review.image_url ? `
                                            <div style="margin: 15px 0;">
                                                <label style="display: block; font-weight: 600; color: #4b5563; margin-bottom: 8px; font-size: 13px;">📷 Vendor Submission:</label>
                                                <a href="${review.image_url}" target="_blank">
                                                    <img src="${review.image_url}" alt="Step ${review.step_number}" style="max-width: 350px; width: 100%; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); cursor: pointer; transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.02)'" onmouseout="this.style.transform='scale(1)'">
                                                </a>
                                            </div>
                                        ` : `
                                            <div style="margin: 15px 0; padding: 12px; background: #fef2f2; border-left: 3px solid #ef4444; border-radius: 6px; color: #991b1b; font-size: 13px;">
                                                ⚠️ No image submitted
                                            </div>
                                        `}
                                        
                                        ${review.vendor_comment ? `
                                            <div style="margin: 15px 0;">
                                                <label style="display: block; font-weight: 600; color: #4b5563; margin-bottom: 6px; font-size: 13px;">💬 Vendor Comment:</label>
                                                <p style="background: #f0f9ff; padding: 12px; border-radius: 6px; margin: 0; color: #0c4a6e; font-size: 14px; line-height: 1.5;">
                                                    "${review.vendor_comment}"
                                                </p>
                                            </div>
                                        ` : ''}
                                        
                                        <div style="margin: 15px 0;">
                                            <label style="display: block; font-weight: 600; color: #4b5563; margin-bottom: 6px; font-size: 13px;">✍️ Your Review Comment:</label>
                                            <textarea class="review-comment" data-step-id="${review.id}" 
                                                      placeholder="Add your review comment here..."
                                                      style="width: 100%; min-height: 80px; padding: 12px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px; font-family: inherit; resize: vertical; transition: border-color 0.2s;" 
                                                      onfocus="this.style.borderColor='#3b82f6'" 
                                                      onblur="this.style.borderColor='#e5e7eb'"></textarea>
                                        </div>
                                        
                                        <div style="display: flex; gap: 10px; margin-top: 20px;">
                                            ${review.admin_status === 'under_review' ? `
                                                <button class="btn btn-success review-btn" 
                                                        data-decision="approved" 
                                                        data-step-id="${review.id}"
                                                        style="flex: 1; background: #10b981; color: white; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 14px; transition: background 0.2s;"
                                                        onmouseover="this.style.background='#059669'"
                                                        onmouseout="this.style.background='#10b981'">
                                                    ✓ Approve Step
                                                </button>
                                                <button class="btn btn-danger review-btn" 
                                                        data-decision="rejected" 
                                                        data-step-id="${review.id}"
                                                        style="flex: 1; background: #ef4444; color: white; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 14px; transition: background 0.2s;"
                                                        onmouseover="this.style.background='#dc2626'"
                                                        onmouseout="this.style.background='#ef4444'">
                                                    ✗ Reject Step
                                                </button>
                                            ` : `
                                                <div style="padding: 12px; background: ${review.admin_status === 'approved' ? '#d1fae5' : '#fef2f2'}; border-radius: 8px; text-align: center;">
                                                    <div style="font-weight: 700; font-size: 16px; color: ${review.admin_status === 'approved' ? '#065f46' : '#991b1b'}; margin-bottom: 5px;">
                                                        ${review.admin_status === 'approved' ? '✅ APPROVED' : '❌ REJECTED'}
                                                    </div>
                                                    ${review.admin_comment ? `
                                                        <div style="font-size: 13px; color: #4b5563; margin-top: 8px;">
                                                            <strong>Manager Comment:</strong> "${review.admin_comment}"
                                                        </div>
                                                    ` : ''}
                                                    ${review.reviewed_at ? `
                                                        <div style="font-size: 12px; color: #9ca3af; margin-top: 4px;">
                                                            Reviewed: ${new Date(review.reviewed_at).toLocaleDateString()}
                                                        </div>
                                                    ` : ''}
                                                </div>
                                            `}
                                        </div>
                                    </div>
                                `;
                            });

                            html += `
                                    </div>
                                </div>
                            `;
                        });
                    } else {
                        html = `
                            <div class="empty-state" style="text-align: center; padding: 60px 20px; background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                                <div class="icon" style="font-size: 64px; margin-bottom: 20px;">✅</div>
                                <h3 style="margin: 0 0 10px 0; color: #1f2937; font-size: 20px;">No Pending Reviews</h3>
                                <p style="margin: 0; color: #6b7280; font-size: 14px;">All vendor submissions have been reviewed. New submissions will appear here.</p>
                            </div>
                        `;
                    }
                    $('#project-reviews-container').html(html);
                } else {
                    $('#project-reviews-container').html('<p class="text-danger">Error loading reviews.</p>');
                }
            }
        });
    }

    // Toggle function for expanding/collapsing project reviews (Global scope)
    window.toggleProjectReviews = function (projectId) {
        const detailsDiv = $('#reviews-' + projectId);
        detailsDiv.slideToggle(300);
    };

    // Review Button Handler for Project Reviews Tab
    $(document).on('click', '#project-reviews-container .review-btn', function () {
        const button = $(this);
        const stepId = button.data('step-id');
        const decision = button.data('decision');
        const comment = button.closest('.step-review-card').find('.review-comment[data-step-id="' + stepId + '"]').val();

        // console.log('Review clicked:', { stepId, decision, comment });

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'review_vendor_submission',
                nonce: sp_area_dashboard_vars.review_submission_nonce,
                step_id: stepId,
                decision: decision,
                comment: comment,
            },
            beforeSend: function () {
                button.prop('disabled', true);
                const originalText = button.text();
                button.data('original-text', originalText);
                button.text('Processing...');
            },
            success: function (response) {
                // console.log('Review response:', response);
                if (response.success) {
                    showToast(response.data.message || 'Review submitted successfully!', 'success');
                    // Reload reviews to refresh the list
                    loadReviews();
                } else {
                    showToast(response.data.message || 'Error submitting review', 'error');
                    button.prop('disabled', false);
                    button.text(button.data('original-text'));
                }
            },
            error: function (xhr, status, error) {
                // console.error('AJAX error:', error);
                showToast('Network error. Please try again.', 'error');
                button.prop('disabled', false);
                button.text(button.data('original-text'));
            }
        });
    });

    // Filter Button Handler
    $(document).on('click', '.review-filter-btn', function () {
        const filter = $(this).data('filter');
        loadReviews(filter);
    });

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
                // console.error('Project save error:', error, xhr.responseText);
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
        // console.log('🔄 Loading leads...');
        $('#area-leads-container').html('<p>Loading leads...</p>');
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_area_manager_leads',
                nonce: sp_area_dashboard_vars.get_leads_nonce,
            },
            success: function (response) {
                // console.log('✅ Leads loaded:', response);
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
                    // console.error('❌ Lead loading failed:', response);
                    $('#area-leads-container').html('<p class="text-danger">Error loading leads.</p>');
                }
            },
            error: function (xhr, status, error) {
                // console.error('❌ AJAX error:', error);
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
                // console.log('Clients loaded:', response);
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
                // console.error('Client load AJAX error:', error);
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
    // Cleaner management is now handled by CleanerComponent

    // Edit Cleaner handled by CleanerComponent

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
                        const planLabels = {
                            'one_time': 'One-Time',
                            'monthly': 'Monthly',
                            '6_month': '6-Month',
                            'yearly': 'Yearly'
                        };


                        // Payment option display (from booking form)
                        const paymentOptionLabels = {
                            'online': '💳 Pay Online',
                            'pay_after': '💵 Pay After Service'
                        };
                        const paymentOptionDisplay = s.payment_option
                            ? paymentOptionLabels[s.payment_option] || s.payment_option
                            : '<span style="color: #9ca3af;">—</span>';

                        // Payment status badge
                        const paymentStatusBadge = s.payment_status === 'paid'
                            ? '<span style="background:#d1fae5;color:#047857;padding:4px 8px;border-radius:4px;font-weight:500;">✓ Paid</span>'
                            : '<span style="background:#fef3c7;color:#b45309;padding:4px 8px;border-radius:4px;font-weight:500;">⏳ Pending</span>';

                        // Assigned cleaner display
                        const assignedCleanerDisplay = s.next_visit_cleaner
                            ? `<span style="color: #059669;">👤 ${s.next_visit_cleaner} <br><small style="color: #666; font-size: 0.85em;">📞 ${s.next_visit_cleaner_phone || ''}</small></span>`
                            : '<span style="color: #9ca3af;">—</span>';

                        // Next visit display
                        const nextVisitDisplay = s.next_visit_date
                            ? `<span style="color:#4f46e5;">✓ ${s.next_visit_date}</span>`
                            : (s.visits_used < s.visits_total)
                                ? `<span style="color: #dc2626;">Not Scheduled</span>`
                                : '<span style="color: #9ca3af;">Complete</span>';

                        html += `
                            <tr class="cleaning-service-row" data-id="${s.id}">
                                <td><strong>${s.customer_name}</strong><br><small>${s.customer_phone}</small></td>
                                <td>${planLabels[s.plan_type] || s.plan_type}</td>
                                <td>${s.visits_used || 0}/${s.visits_total || 1}</td>
                                <td>${nextVisitDisplay}</td>
                                <td>${assignedCleanerDisplay}</td>
                                <td>${paymentOptionDisplay}</td>
                                <td>${paymentStatusBadge}</td>
                                <td>
                                    ${s.visits_used < s.visits_total
                                ? `<button class="btn btn-sm schedule-visit-btn" 
                                                data-id="${s.id}" 
                                                data-name="${s.customer_name}"
                                                data-preferred-date="${s.preferred_date || ''}">+ Schedule</button>`
                                : ''}
                                    <button class="btn btn-sm view-service-details-btn" 
                                            data-id="${s.id}" 
                                            style="background: #6366f1; color: white; margin-left: 5px;">📋 Details</button>
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

    // Create/Delete Cleaner handled by CleanerComponent

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
        const customerName = $(this).data('name');
        const preferredDate = $(this).data('preferred-date');

        $('#schedule_service_id').val(serviceId);
        $('#schedule_customer_name').text(customerName);

        // Set minimum date to today and auto-fill preferred date
        const today = new Date().toISOString().split('T')[0];
        $('#schedule_date').attr('min', today);

        if (preferredDate && preferredDate !== '') {
            $('#schedule_date').val(preferredDate);
        } else {
            $('#schedule_date').val(today);
        }

        // Load cleaners if not loaded
        if (cleanersList.length === 0) {
            loadCleanersForSchedule();
        } else {
            updateCleanerDropdown();
        }

        $('#schedule-visit-feedback').html('');
        $('#schedule-visit-modal').show();
    });

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

    // View Service Details Button Handler
    $(document).on('click', '.view-service-details-btn', function (e) {
        e.preventDefault();
        e.stopPropagation();

        const serviceId = $(this).data('id');
        displayServiceDetails(serviceId);
    });

    // Function to display service details
    function displayServiceDetails(serviceId) {
        const modal = $('#service-details-modal');
        const content = $('#service-details-content');

        modal.show();
        content.html('<p style="text-align: center; color: #666;">Loading service details...</p>');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_cleaning_service_details',
                service_id: serviceId
            },
            success: function (response) {
                console.log('Manager Service Details Response:', response);
                if (response.success) {
                    const service = response.data.service;
                    const visits = response.data.visits;

                    const planLabels = {
                        'one_time': 'One-Time',
                        'monthly': 'Monthly',
                        '6_month': '6-Month',
                        'yearly': 'Yearly'
                    };

                    const paymentModeLabels = {
                        'online': '💳 Online',
                        'cash': '💵 Cash',
                        'upi': '📱 UPI',
                        'card': '💳 Card'
                    };

                    let html = `
                        <div style="background: #f9fafb; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                            <h4 style="margin: 0 0 15px 0; color: #1f2937;">👤 Customer Information</h4>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                <div><strong>Name:</strong> ${service.customer_name}</div>
                                <div><strong>Phone:</strong> ${service.customer_phone}</div>
                                <div style="grid-column: 1 / -1;"><strong>Address:</strong> ${service.customer_address || 'N/A'}</div>
                            </div>
                        </div>
                        
                        <div style="background: #eff6ff; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                            <h4 style="margin: 0 0 15px 0; color: #1e40af;">📋 Service Plan</h4>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                <div><strong>Plan Type:</strong> ${planLabels[service.plan_type] || service.plan_type}</div>
                                <div><strong>System Size:</strong> ${service.system_size_kw} kW</div>
                                <div><strong>Total Visits:</strong> ${service.visits_total}</div>
                                <div><strong>Visits Used:</strong> ${service.visits_used}</div>
                                <div><strong>Remaining:</strong> ${service.visits_total - service.visits_used}</div>
                                ${service.preferred_date ? `<div><strong>Preferred Date:</strong> ${service.preferred_date}</div>` : ''}
                            </div>
                        </div>
                        
                        <div style="background: #f0fdf4; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                            <h4 style="margin: 0 0 15px 0; color: #166534;">💰 Payment Information</h4>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                <div><strong>Total Amount:</strong> ₹${Number(service.total_amount || 0).toLocaleString()}</div>
                                <div><strong>Payment Status:</strong> <span style="background: ${service.payment_status === 'paid' ? '#d1fae5' : '#fef3c7'}; color: ${service.payment_status === 'paid' ? '#047857' : '#b45309'}; padding: 3px 8px; border-radius: 4px;">${service.payment_status === 'paid' ? 'Paid' : 'Pending'}</span></div>
                                <div><strong>Payment Mode:</strong> ${service.payment_mode ? paymentModeLabels[service.payment_mode] || service.payment_mode : 'N/A'}</div>
                                ${service.transaction_id ? `<div><strong>Transaction ID:</strong> ${service.transaction_id}</div>` : ''}
                            </div>
                        </div>
                        
                        <div style="background: #fef3c7; padding: 20px; border-radius: 8px;">
                            <h4 style="margin: 0 0 15px 0; color: #92400e;">📅 Visit History</h4>
                    `;

                    if (visits.length === 0) {
                        html += '<p style="color: #666; text-align: center;">No visits scheduled yet.</p>';
                    } else {
                        html += '<div style="max-height: 300px; overflow-y: auto;">';
                        visits.forEach((visit, index) => {
                            const statusColors = {
                                'scheduled': '#3b82f6',
                                'completed': '#10b981',
                                'cancelled': '#ef4444'
                            };
                            const statusIcons = {
                                'scheduled': '📅',
                                'completed': '✅',
                                'cancelled': '❌'
                            };

                            // Timeline Logic
                            const isCompleted = visit.status === 'completed';
                            const isStarted = visit.status === 'in_progress' || isCompleted;
                            const timelineId = `visit-timeline-${visit.id}`;
                            const cleanerPill = visit.cleaner_name ? `<span style="font-size:11px; background:#f3f4f6; padding:2px 6px; border-radius:4px; margin-left:5px;">👤 ${visit.cleaner_name}</span>` : '';

                            html += `
                            <div style="background: white; border-radius: 6px; margin-bottom: 10px; border-left: 4px solid ${statusColors[visit.status] || '#6b7280'}; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                                <div style="padding: 15px; display: flex; justify-content: space-between; align-items: center; cursor: pointer;" onclick="jQuery('#${timelineId}').slideToggle()">
                                    <div>
                                        <div style="font-weight: 600; color: #374151; display:flex; align-items:center;">
                                            ${statusIcons[visit.status] || '📍'} Visit ${index + 1}
                                            ${cleanerPill}
                                        </div>
                                        <div style="font-size: 12px; color: #6b7280; margin-top: 2px;">${visit.scheduled_date} • ${visit.scheduled_time || 'N/A'}</div>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <span style="background: ${statusColors[visit.status] || '#6b7280'}; color: white; padding: 2px 8px; border-radius: 4px; font-size: 11px; text-transform: uppercase;">${visit.status}</span>
                                        <span style="color: #9ca3af; font-size: 12px;">▼</span>
                                    </div>
                                </div>
                                
                                <div id="${timelineId}" style="display: none; border-top: 1px solid #f3f4f6; padding: 15px; background: #f9fafb;">
                                    <div style="position: relative; padding-left: 20px; border-left: 2px solid #e5e7eb; margin-left: 5px;">
                                        <!-- Scheduled / Start Node -->
                                        <div style="margin-bottom: 20px; position: relative;">
                                            <div style="position: absolute; left: -26px; top: 0; background: ${isStarted ? '#10b981' : '#e5e7eb'}; width: 10px; height: 10px; border-radius: 50%; border: 2px solid white; box-shadow: 0 0 0 1px #d1d5db;"></div>
                                            <div style="font-size: 13px; font-weight: 600; color: #374151;">Started / Check-in</div>
                                            <div style="font-size: 12px; color: #6b7280;">
                                                 ${visit.start_time ? `Time: ${visit.start_time}` : (isStarted ? 'Started (Time N/A)' : 'Not started yet')}
                                            </div>
                                            
                                            ${visit.before_photo ? `
                                            <div style="margin-top: 8px;">
                                                <a href="${visit.before_photo}" target="_blank" style="display: inline-block;">
                                                    <img src="${visit.before_photo}" style="width: 80px; height: 80px; object-fit: cover; border-radius: 4px; border: 1px solid #d1d5db;">
                                                </a>
                                                <div style="font-size: 10px; color: #6b7280; margin-top:2px;">Before Cleaning</div>
                                            </div>` : ''}
                                        </div>

                                        <!-- Completion Node -->
                                        <div style="position: relative;">
                                            <div style="position: absolute; left: -26px; top: 0; background: ${isCompleted ? '#10b981' : '#e5e7eb'}; width: 10px; height: 10px; border-radius: 50%; border: 2px solid white; box-shadow: 0 0 0 1px #d1d5db;"></div>
                                            <div style="font-size: 13px; font-weight: 600; color: #374151;">Completed</div>
                                            ${visit.completed_at ? `<div style="font-size: 12px; color: #6b7280;">Time: ${visit.completed_at}</div>` : '<div style="font-size: 12px; color: #9ca3af;">Pending completion</div>'}
                                            
                                            ${visit.completion_notes ? `<div style="font-size: 12px; font-style: italic; color: #4b5563; margin-top:5px; background: white; padding: 8px; border-radius: 4px; border: 1px solid #e5e7eb;">"${visit.completion_notes}"</div>` : ''}

                                            ${visit.completion_photo ? `
                                            <div style="margin-top: 8px;">
                                                <a href="${visit.completion_photo}" target="_blank" style="display: inline-block;">
                                                    <img src="${visit.completion_photo}" style="width: 80px; height: 80px; object-fit: cover; border-radius: 4px; border: 1px solid #d1d5db;">
                                                </a>
                                                <div style="font-size: 10px; color: #6b7280; margin-top:2px;">After Cleaning</div>
                                            </div>` : ''}
                                        </div>
                                    </div>

                                    ${visit.status === 'scheduled' ? `
                                    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px dashed #e5e7eb; display: flex; gap: 10px; justify-content: flex-end;">
                                         <button class="btn btn-sm edit-visit-btn" 
                                                data-visit-id="${visit.id}" 
                                                data-service-id="${serviceId}"
                                                data-date="${visit.scheduled_date}"
                                                data-time="${visit.scheduled_time || ''}"
                                                data-cleaner-id="${visit.cleaner_id || ''}"
                                                style="background: #eab308; color: white; border:none; padding: 5px 12px; border-radius:4px; cursor:pointer;">✏️ Edit</button>
                                        <button class="btn btn-sm cancel-visit-btn" 
                                                data-visit-id="${visit.id}" 
                                                style="background: #ef4444; color: white; border:none; padding: 5px 12px; border-radius:4px; cursor:pointer;">✕ Cancel</button>
                                    </div>` : ''}
                                </div>
                            </div>
                            `;
                        });
                        html += '</div>';
                    }

                    html += '</div>';

                    content.html(html);
                    $('#service-details-title').text(`Service Details - ${service.customer_name} `);
                } else {
                    content.html('<p style="color: red; text-align: center;">Error loading service details.</p>');
                }
            },
            error: function () {
                content.html('<p style="color: red; text-align: center;">Error loading service details. Please try again.</p>');
            }
        });
    }

    // Cancel Visit Handler
    $(document).on('click', '.cancel-visit-btn', function (e) {
        e.preventDefault();
        const visitId = $(this).data('visit-id');

        if (!confirm('Are you sure you want to cancel this visit? This cannot be undone.')) {
            return;
        }

        const reason = prompt('Please enter a cancellation reason (optional):');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'cancel_cleaning_visit',
                visit_id: visitId,
                reason: reason
            },
            success: function (response) {
                if (response.success) {
                    showToast('Visit cancelled successfully', 'success');
                    $('#service-details-modal').hide();
                    if ($('#service-detail-modal').length) {
                        $('#service-detail-modal').hide();
                    }
                    loadCleaningServices();
                } else {
                    showToast(response.data.message || 'Error cancelling visit', 'error');
                }
            },
            error: function () {
                showToast('Error cancelling visit', 'error');
            }
        });
    });


    // Load cleaners when cleaning services section is activated
    $(document).on('click', '[data-section="cleaning-services"]', function () {
        loadCleanersForSchedule();
    });

    // ========================================
    // MANAGER-SPECIFIC FUNCTIONS
    // ========================================

    // --- Team Analysis Functions ---
    function loadTeamAnalysis() {
        // console.log('Loading team analysis data...');

        // Load Area Managers
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_team_analysis_data',
                nonce: sp_area_dashboard_vars.get_projects_nonce
            },
            success: function (response) {
                if (response.success) {
                    renderTeamAnalysis(response.data);
                } else {
                    // console.error('Failed to load team data:', response);
                    $('#team-am-tbody').html('<tr><td colspan="6">Error loading data. ' + (response.data?.message || '') + '</td></tr>');
                }
            },
            error: function (xhr, status, error) {
                // console.error('AJAX error loading team data:', error);
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
                                < tr >
                        <td>${am.display_name || 'N/A'}</td>
                        <td>${am.email || 'N/A'}</td>
                        <td>${am.city || '-'}</td>
                        <td>${am.state || '-'}</td>
                        <td>${am.project_count || 0}</td>
                        <td>${am.team_size || 0}</td>
                        <td>
                            <button class="btn btn-sm view-member-detail" 
                                    data-user-id="${am.ID || am.id}" 
                                    data-role="area_manager" 
                                    data-name="${am.display_name || 'N/A'}"
                                    title="View Details">
                                👁️ View
                            </button>
                        </td>
                    </tr >
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
                                < tr >
                        <td>${sm.display_name || 'N/A'}</td>
                        <td>${sm.email || 'N/A'}</td>
                        <td>${sm.supervising_am || '-'}</td>
                        <td>${sm.lead_count || 0}</td>
                        <td>${sm.conversion_count || 0}</td>
                        <td>
                            <button class="btn btn-sm view-member-detail" 
                                    data-user-id="${sm.ID || sm.id}" 
                                    data-role="sales_manager" 
                                    data-name="${sm.display_name || 'N/A'}"
                                    title="View Details">
                                👁️ View
                            </button>
                        </td>
                    </tr >
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
                                < tr >
                        <td>${cleaner.name || 'N/A'}</td>
                        <td>${cleaner.phone || '-'}</td>
                        <td>${cleaner.supervising_am || '-'}</td>
                        <td>${cleaner.completed_visits || 0}</td>
                        <td><span class="badge ${statusClass}">${cleaner.status || 'active'}</span></td>
                        <td>
                            <button class="btn btn-sm view-member-detail" 
                                    data-user-id="${cleaner.id}" 
                                    data-role="cleaner" 
                                    data-name="${cleaner.name || 'N/A'}"
                                    title="View Details">
                                👁️ View
                            </button>
                        </td>
                    </tr >
                                `;
            });
        } else {
            cleanerHtml = '<tr><td colspan="5">No cleaners found</td></tr>';
        }
        $('#team-cleaners-tbody').html(cleanerHtml);
    }

    // --- AM Assignment Functions ---
    function loadAMAssignments() {
        // console.log('Loading AM assignments...');

        // Load Area Managers for dropdown
        loadAMDropdown();

        // Load current assignments table
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
                        html += `< option value = "${am.ID}" > ${am.display_name} (${am.email})</option > `;
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
                            < tr >
                                <td>${assignment.am_name}</td>
                                <td>${assignment.state}</td>
                                <td>${assignment.city}</td>
                                <td>
                                    <button class="action-btn action-btn-danger remove-am-assignment" 
                                            data-am-id="${assignment.am_id}">
                                        🗑️ Remove
                                    </button>
                                </td>
                            </tr >
                            `;
                    });
                } else {
                    html = '<tr><td colspan="4">No area assignments found</td></tr>';
                }
                $('#am-assignments-tbody').html(html);
            }
        });
    }

    // State change handler for city dropdown in AM assignment
    $(document).on('change', '#assign_state', function () {
        const state = $(this).val();
        if (!state) {
            $('#assign_city').html('<option value="">Select state first</option>');
            return;
        }

        // Load cities for this state
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
                        html += `< option value = "${city}" > ${city}</option > `;
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
                nonce: sp_area_dashboard_vars.get_projects_nonce,
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

    // ========================================
    // Team Member Detail Modal
    // ========================================

    // Open team member detail modal
    $(document).on('click', '.view-member-detail', function () {
        const userId = $(this).data('user-id');
        const role = $(this).data('role');
        const memberName = $(this).data('name');

        $('#member-modal-name').text(memberName + ' - Details');
        $('#team-member-modal').fadeIn(200);
        loadMemberDetails(userId, role);
    });

    // Close modal
    $(document).on('click', '#close-member-modal', function () {
        $('#team-member-modal').fadeOut(200);
    });

    // Close modal when clicking outside
    $(document).on('click', '#team-member-modal', function (e) {
        if ($(e.target).is('#team-member-modal')) {
            $('#team-member-modal').fadeOut(200);
        }
    });

    /**
     * Load team member details via AJAX
     */
    function loadMemberDetails(userId, role) {
        $('#member-detail-content').html('<p style="text-align: center; padding: 40px; color: #666;">Loading member details...</p>');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_team_member_details',
                nonce: sp_area_dashboard_vars.get_projects_nonce,
                user_id: userId,
                role: role
            },
            success: function (response) {
                if (response.success) {
                    renderMemberDetails(response.data, role);
                } else {
                    $('#member-detail-content').html('<p class="error" style="color: #dc2626; text-align: center; padding: 40px;">' + (response.data.message || 'Error loading details') + '</p>');
                }
            },
            error: function () {
                $('#member-detail-content').html('<p class="error" style="color: #dc2626; text-align: center; padding: 40px;">Network error. Please try again.</p>');
            }
        });
    }

    /**
     * Render member details based on role
     */
    function renderMemberDetails(data, role) {
        let html = '';

        // User Info Section
        html += '<div class="detail-section">';
        html += '<h3>📋 Basic Information</h3>';
        html += '<div class="member-stats-grid">';
        html += `< div class="member-stat-card" ><h4>${data.user_info.name}</h4><span>Name</span></div > `;
        html += `< div class="member-stat-card" ><h4>${data.user_info.email}</h4><span>Email</span></div > `;
        if (data.user_info.phone) {
            html += `< div class="member-stat-card" ><h4>${data.user_info.phone}</h4><span>Phone</span></div > `;
        }
        // Show Photo if available
        if (data.user_info.photo_url) {
            html += `< div class="member-stat-card" >
                            <img src="${data.user_info.photo_url}" style="width:50px; height:50px; border-radius:50%; object-fit:cover; margin-bottom:5px;">
                                <span>Photo</span>
                            </div>`;
        }

        if (data.user_info.state) {
            html += `< div class="member-stat-card" ><h4>${data.user_info.state}</h4><span>State</span></div > `;
        }
        if (data.user_info.city) {
            html += `< div class="member-stat-card" ><h4>${data.user_info.city}</h4><span>City</span></div > `;
        }
        html += '</div></div>';

        // Documents Section (Mainly for Cleaners)
        if (data.user_info.aadhaar_number || data.user_info.aadhaar_image_url) {
            html += '<div class="detail-section">';
            html += '<h3>📑 Documents</h3>';
            html += '<div class="member-stats-grid">';
            if (data.user_info.aadhaar_number) {
                html += `< div class="member-stat-card" ><h4>${data.user_info.aadhaar_number}</h4><span>Aadhaar Number</span></div > `;
            }
            if (data.user_info.aadhaar_image_url) {
                html += `< div class="member-stat-card" >
                    <a href="${data.user_info.aadhaar_image_url}" target="_blank" class="btn btn-sm btn-info">👁️ View Aadhaar Card</a>
                    <span style="margin-top:5px;">Document</span>
                 </div > `;
            }
            html += '</div></div>';
        }

        // Stats Section
        html += '<div class="detail-section">';
        html += '<h3>📊 Performance Stats</h3>';
        html += '<div class="member-stats-grid">';

        if (role === 'area_manager') {
            html += `< div class="member-stat-card" ><h4>${data.stats.total_projects || 0}</h4><span>Total Projects</span></div > `;
            html += `< div class="member-stat-card" ><h4>${data.stats.total_leads || 0}</h4><span>Total Leads</span></div > `;
            html += `< div class="member-stat-card" ><h4>${data.stats.team_size || 0}</h4><span>Team Size</span></div > `;
        } else if (role === 'sales_manager') {
            html += `< div class="member-stat-card" ><h4>${data.stats.total_leads || 0}</h4><span>Total Leads</span></div > `;
            html += `< div class="member-stat-card" ><h4>${data.stats.conversions || 0}</h4><span>Conversions</span></div > `;
            html += `< div class="member-stat-card" ><h4>${data.stats.conversion_rate || 0}%</h4><span>Conversion Rate</span></div > `;
        } else if (role === 'cleaner') {
            html += `< div class="member-stat-card" ><h4>${data.stats.completed_visits || 0}</h4><span>Completed Visits</span></div > `;
            html += `< div class="member-stat-card" ><h4>${data.stats.pending_visits || 0}</h4><span>Pending Visits</span></div > `;
            html += `< div class="member-stat-card" ><h4>${data.stats.completion_rate || 0}%</h4><span>Completion Rate</span></div > `;
        }
        html += '</div></div>';

        // Projects Section (for Area Managers)
        if (role === 'area_manager' && data.projects && data.projects.length > 0) {
            html += '<div class="detail-section">';
            html += '<h3>🏗️ Recent Projects</h3>';
            html += '<div class="table-responsive">';
            html += '<table class="data-table"><thead><tr><th>Project</th><th>Status</th><th>Location</th><th>Cost</th></tr></thead><tbody>';
            data.projects.slice(0, 10).forEach(project => {
                html += `< tr >
                    <td>${project.title}</td>
                    <td><span class="badge badge-${project.status}">${project.status}</span></td>
                    <td>${project.city}, ${project.state}</td>
                    <td>₹${parseFloat(project.cost || 0).toLocaleString()}</td>
                </tr > `;
            });
            html += '</tbody></table></div></div>';
        }

        // Leads Section (for AMs and SMs)
        if ((role === 'area_manager' || role === 'sales_manager') && data.leads && data.leads.length > 0) {
            html += '<div class="detail-section">';
            html += '<h3>🎯 Recent Leads</h3>';
            html += '<div class="table-responsive">';
            html += '<table class="data-table"><thead><tr><th>Name</th><th>Phone</th><th>Status</th><th>Location</th><th>Created</th></tr></thead><tbody>';
            data.leads.slice(0, 10).forEach(lead => {
                html += `< tr >
                    <td>${lead.name}</td>
                    <td>${lead.phone}</td>
                    <td><span class="badge badge-${lead.status}">${lead.status}</span></td>
                    <td>${lead.city}</td>
                    <td>${lead.created_date}</td>
                </tr > `;
            });
            html += '</tbody></table></div></div>';
        }

        // Team Members Section (for Area Managers)
        if (role === 'area_manager' && data.team_members && data.team_members.length > 0) {
            html += '<div class="detail-section">';
            html += '<h3>👥 Team Members</h3>';
            html += '<div class="table-responsive">';
            html += '<table class="data-table"><thead><tr><th>Name</th><th>Role</th><th>Email</th><th>Phone</th><th>Leads</th><th>Joined</th></tr></thead><tbody>';
            data.team_members.forEach(member => {
                html += `< tr >
                    <td>${member.name}</td>
                    <td><span class="badge badge-info">${member.role}</span></td>
                    <td>${member.email}</td>
                    <td>${member.phone || '-'}</td>
                    <td>${member.lead_count || 0}</td>
                    <td>${member.joined_date}</td>
                </tr > `;
            });
            html += '</tbody></table></div></div>';
        }

        // Cleaning Visits (for Cleaners)
        if (role === 'cleaner' && data.visits && data.visits.length > 0) {
            html += '<div class="detail-section">';
            html += '<h3>🧹 Cleaning Visits</h3>';
            html += '<div class="table-responsive">';
            html += '<table class="data-table"><thead><tr><th>Date</th><th>Client</th><th>Location</th><th>Status</th></tr></thead><tbody>';
            data.visits.slice(0, 10).forEach(visit => {
                html += `< tr >
                    <td>${visit.visit_date}</td>
                    <td>${visit.client_name}</td>
                    <td>${visit.location}</td>
                    <td><span class="badge badge-${visit.status}">${visit.status}</span></td>
                </tr > `;
            });
            html += '</tbody></table></div></div>';
        }

        // Activity Timeline
        if (data.recent_activity && data.recent_activity.length > 0) {
            html += '<div class="detail-section">';
            html += '<h3>⏱️ Recent Activity</h3>';
            html += '<div class="activity-timeline">';
            data.recent_activity.forEach(activity => {
                html += `< div class="activity-item" >
                    <div class="activity-time">${activity.time}</div>
                    <p class="activity-desc">${activity.description}</p>
                </div > `;
            });
            html += '</div></div>';
        }

        // If no data
        if (!data.projects?.length && !data.leads?.length && !data.visits?.length && !data.recent_activity?.length) {
            html += '<div class="detail-section"><p style="text-align: center; color: #999; padding: 40px;">No activity data available.</p></div>';
        }

        $('#member-detail-content').html(html);
    }

    // Initial Load
    loadDashboardStats();

    // Init Cleaner Component
    if (window.initCleanerComponent && typeof sp_area_dashboard_vars !== 'undefined') {
        initCleanerComponent(sp_area_dashboard_vars.ajax_url, sp_area_dashboard_vars.cleaner_nonce);
    }


    // Cleaner loading helper
    function loadCleanersForSelect(selectId, selectedId = null) {
        const select = $(selectId);
        select.html('<option value="">Loading...</option>');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: { action: 'get_all_cleaners' },
            success: function (response) {
                if (response.success) {
                    let options = '<option value="">-- Select Cleaner --</option>';
                    options += '<option value="0">Not Assigned</option>'; // Allow unassigning
                    response.data.forEach(cleaner => {
                        const isSelected = selectedId && String(cleaner.id) === String(selectedId) ? 'selected' : '';
                        options += `< option value = "${cleaner.id}" ${isSelected}> ${cleaner.name} (${cleaner.phone})</option > `;
                    });
                    select.html(options);
                } else {
                    select.html('<option value="">No cleaners found</option>');
                }
            },
            error: function () {
                select.html('<option value="">Error loading cleaners</option>');
            }
        });
    }

    // Append Edit Modal to Body
    $('body').append(`
            <div id="edit-visit-modal" class="modal" style="display: none; position: fixed; z-index: 1050; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4);">
                <div class="modal-content" style="background-color: #fefefe; margin: 10% auto; padding: 20px; border: 1px solid #888; width: 90%; max-width: 500px; border-radius: 8px;">
                    <span class="close-modal" data-modal="edit-visit-modal" style="color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>
                    <h3 style="margin-top: 0; color: #1f2937;">✏️ Edit Visit</h3>
                    <form id="edit-visit-form">
                        <input type="hidden" id="edit_visit_id" name="visit_id">
                            <input type="hidden" id="edit_service_id" name="service_id">

                                <div class="form-group" style="margin-bottom: 15px;">
                                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">📅 Date</label>
                                    <input type="date" id="edit_scheduled_date" name="scheduled_date" required style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;">
                                </div>

                                <div class="form-group" style="margin-bottom: 15px;">
                                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">⏰ Time</label>
                                    <input type="time" id="edit_scheduled_time" name="scheduled_time" style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;">
                                </div>

                                <div class="form-group" style="margin-bottom: 15px;">
                                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">👤 Cleaner</label>
                                    <select id="edit_cleaner_id" name="cleaner_id" style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;">
                                        <option value="">Loading...</option>
                                    </select>
                                </div>

                                <div style="text-align: right; margin-top: 20px;">
                                    <button type="button" class="btn close-modal-btn" data-modal="edit-visit-modal" style="background: #9ca3af; color: white; padding: 8px 16px; border: none; border-radius: 4px; margin-right: 10px;">Cancel</button>
                                    <button type="submit" class="btn" style="background: #3b82f6; color: white; padding: 8px 16px; border: none; border-radius: 4px;">Update Visit</button>
                                </div>
                            </form>
                        </div>
                </div>
        `);

    // Edit Visit Button Click
    $(document).on('click', '.edit-visit-btn', function (e) {
        e.preventDefault();
        const visitId = $(this).data('visit-id');
        const serviceId = $(this).data('service-id');
        const date = $(this).data('date');
        const time = $(this).data('time');
        const cleanerId = $(this).data('cleaner-id');

        $('#edit_visit_id').val(visitId);
        $('#edit_service_id').val(serviceId);
        $('#edit_scheduled_date').val(date);
        $('#edit_scheduled_time').val(time);

        loadCleanersForSelect('#edit_cleaner_id', cleanerId);

        $('#edit-visit-modal').show();
    });

    // Edit Visit Form Submit
    $('#edit-visit-form').on('submit', function (e) {
        e.preventDefault();

        const $btn = $(this).find('button[type="submit"]');
        const originalText = $btn.text();
        $btn.prop('disabled', true).text('Updating...');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'update_cleaning_visit',
                visit_id: $('#edit_visit_id').val(),
                service_id: $('#edit_service_id').val(),
                scheduled_date: $('#edit_scheduled_date').val(),
                scheduled_time: $('#edit_scheduled_time').val(),
                cleaner_id: $('#edit_cleaner_id').val()
            },
            success: function (response) {
                if (response.success) {
                    showToast('Visit updated successfully', 'success');
                    $('#edit-visit-modal').hide();
                    if ($('#service-detail-modal').is(':visible')) {
                        // Ideally we refresh the details view too, but reloading main table is priority
                        // For now, let's trigger a click on details button if we can find it, or just close details
                        $('#service-detail-modal').hide();
                    }
                    loadCleaningServices();
                } else {
                    showToast(response.data.message || 'Error updating visit', 'error');
                }
            },
            error: function () {
                showToast('Error updating visit', 'error');
            },
            complete: function () {
                $btn.prop('disabled', false).text(originalText);
            }
        });
    });

    // Close Modal Handlers (Generic)
    $(document).on('click', '.close-modal, .close-modal-btn', function () {
        const modalId = $(this).data('modal');
        $(`#${modalId} `).hide();
    });

    $(window).on('click', function (e) {
        if ($(e.target).hasClass('modal')) {
            $(e.target).hide();
        }
    });

})(jQuery);