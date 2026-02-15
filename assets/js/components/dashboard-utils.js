/**
 * Dashboard Utilities Component
 * Shared logic for Manager, Area Manager, and Sales Manager dashboards.
 * Handles: Tabs, Toasts, Notifications
 */

(function ($) {
    'use strict';

    // Namespace for Dashboard Utils
    window.DashboardUtils = {

        /**
         * Initialize Tab Navigation
         * @param {string} dashboardSelector - CSS selector for the dashboard container
         */
        setupTabNavigation: function (dashboardSelector) {
            // // console.log('Initializing Tab Navigation for:', dashboardSelector);

            // Handle Sidebar Navigation
            $(document).on('click', `${dashboardSelector} .nav-item`, function (e) {
                e.preventDefault();
                const section = $(this).data('section');

                // Update active state in sidebar
                $(`${dashboardSelector} .nav-item`).removeClass('active');
                $(this).addClass('active');

                // Hide all sections
                $('.section-content').hide();
                $('.section-content').removeClass('active');

                // Show target section
                const targetSection = $(`#${section}-section`);
                if (targetSection.length) {
                    targetSection.show();
                    targetSection.addClass('active');

                    // Trigger specific load functions based on section
                    DashboardUtils.triggerSectionLoad(section);
                }
            });

            // Handle URL Hash Navigation (Deep Linking)
            const hash = window.location.hash.substring(1);
            if (hash) {
                $(`${dashboardSelector} .nav-item[data-section="${hash}"]`).click();
            }
        },

        /**
         * Trigger data loading for specific sections
         * @param {string} section - Section ID
         */
        triggerSectionLoad: function (section) {
            switch (section) {
                case 'dashboard':
                    if (typeof window.loadDashboardStats === 'function') window.loadDashboardStats();
                    break;
                case 'leads':
                    if (typeof window.initLeadComponent === 'function') window.initLeadComponent();
                    if (typeof window.loadLeads === 'function') window.loadLeads();
                    break;
                case 'projects':
                    if (typeof window.loadProjects === 'function') window.loadProjects();
                    break;
                case 'my-clients':
                    if (typeof window.loadMyClients === 'function') window.loadMyClients();
                    break;
                case 'bid-management':
                    if (typeof window.loadBids === 'function') window.loadBids();
                    break;
                case 'manage-cleaners':
                    if (typeof window.initCleanerComponent === 'function') window.initCleanerComponent();
                    if (typeof window.loadCleaners === 'function') window.loadCleaners();
                    break;
                case 'cleaning-services':
                    if (typeof window.loadCleaningServices === 'function') window.loadCleaningServices();
                    if (typeof window.loadSMCleaningServices === 'function') window.loadSMCleaningServices();
                    break;
                case 'team-analysis':
                case 'my-team':
                    if (typeof window.loadTeamAnalysis === 'function') window.loadTeamAnalysis();
                    if (typeof window.loadMyTeam === 'function') window.loadMyTeam();
                    break;
                case 'project-reviews':
                    if (typeof window.loadProjectReviews === 'function') window.loadProjectReviews();
                    break;
                case 'am-assignment':
                    if (typeof window.loadAMAssignments === 'function') window.loadAMAssignments();
                    break;
                // Sales Manager / Legacy
                case 'my-leads':
                    if (typeof window.initLeadComponent === 'function') window.initLeadComponent();
                    if (typeof window.loadMyLeads === 'function') window.loadMyLeads();
                    break;
                case 'conversions':
                    if (typeof window.loadConversions === 'function') window.loadConversions();
                    break;
            }
        },

        /**
         * Show Toast Notification
         * @param {string} message - Message to display
         * @param {string} type - 'success', 'error', 'info', 'warning'
         * @param {number} duration - Duration in ms (default 3000)
         */
        showToast: function (message, type = 'info', duration = 3000) {
            const container = $('#toast-container');
            if (container.length === 0) {
                $('body').append('<div id="toast-container" class="toast-container"></div>');
            }

            // Create toast element
            const toastId = 'toast-' + Date.now();
            const icon = type === 'success' ? '✅' : type === 'error' ? '❌' : type === 'warning' ? '⚠️' : 'ℹ️';

            const toastHtml = `
                <div id="${toastId}" class="toast toast-${type}">
                    <div class="toast-content">
                        <span class="toast-icon">${icon}</span>
                        <span class="toast-message">${message}</span>
                    </div>
                    <button class="toast-close">&times;</button>
                </div>
            `;

            $('#toast-container').append(toastHtml);

            // Animate in
            setTimeout(() => {
                $(`#${toastId}`).addClass('show');
            }, 10);

            // Close button handler
            $(`#${toastId} .toast-close`).on('click', function () {
                DashboardUtils.removeToast(toastId);
            });

            // Auto dismiss
            if (duration > 0) {
                setTimeout(() => {
                    DashboardUtils.removeToast(toastId);
                }, duration);
            }
        },

        /**
         * Remove Toast Notification
         * @param {string} toastId - ID of toast to remove
         */
        removeToast: function (toastId) {
            const toast = $(`#${toastId}`);
            toast.removeClass('show');
            setTimeout(() => {
                toast.remove();
            }, 300);
        }
    };

    // Auto-initialize if configured
    $(document).ready(function () {
        // // console.log('Dashboard Utils Loaded');
    });

})(jQuery);
