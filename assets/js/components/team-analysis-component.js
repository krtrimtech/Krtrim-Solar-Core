/**
 * Shared Team Analysis Component
 * Handles rendering of Team Analysis tables for both Manager and Area Manager dashboards.
 */
var KSC_TeamAnalysis = (function ($) {
    'use strict';

    return {
        /**
         * Render the team analysis data into the specified table bodies.
         * 
         * @param {Object} data The data returned from the API (area_managers, sales_managers, cleaners, etc.)
         * @param {Object} tableIds Optional override for table body IDs. Defaults to manager dashboard IDs.
         */
        render: function (data, tableIds) {

            // Default IDs (Manager Dashboard)
            const ids = $.extend({
                amTable: '#team-am-tbody',
                smTable: '#team-sm-tbody',
                cleanerTable: '#team-cleaners-tbody',
                amCount: '#team-am-count',
                smCount: '#team-sm-count',
                cleanerCount: '#team-cleaner-count',
                projectCount: '#team-project-count'
            }, tableIds);

            // Update stats
            if ($(ids.amCount).length) $(ids.amCount).text(data.area_managers?.length || 0);
            if ($(ids.smCount).length) $(ids.smCount).text(data.sales_managers?.length || 0);
            if ($(ids.cleanerCount).length) $(ids.cleanerCount).text(data.cleaners?.length || 0);
            if ($(ids.projectCount).length) $(ids.projectCount).text(data.total_projects || 0);

            // Helper for badges
            const getBadge = (text, type = 'info') => `<span class="badge badge-${type}" style="padding: 5px 10px; border-radius: 12px; font-size: 0.8em;">${text}</span>`;

            // Render Area Managers table (Only if container exists)
            if ($(ids.amTable).length) {
                let amHtml = '';
                if (data.area_managers && data.area_managers.length > 0) {
                    data.area_managers.forEach(am => {
                        amHtml += `
                            <tr>
                                <td style="font-weight: 500;">
                                    ${am.display_name}
                                    <div style="font-size: 0.85em; color: #6b7280;">${am.email}</div>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center;">
                                        <span style="margin-right: 5px;">📍</span>
                                        ${am.location || (am.city ? am.city + ', ' + am.state : 'N/A')}
                                    </div>
                                </td>
                                <td class="text-center">
                                    <div style="font-weight: bold; font-size: 1.1em;">${am.project_count}</div>
                                    <div style="font-size: 0.8em; color: #10b981;">+${am.projects_this_month || 0} this month</div>
                                </td>
                                <td class="text-center">
                                    <span style="background: #eef2ff; color: #4f46e5; padding: 4px 8px; border-radius: 6px; font-weight: 500;">
                                        👥 ${am.team_size} Members
                                    </span>
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-sm view-member-detail" 
                                            data-user-id="${am.ID || am.id}" 
                                            data-role="area_manager" 
                                            data-name="${am.display_name || 'N/A'}"
                                            style="background: white; border: 1px solid #d1d5db; color: #374151; padding: 6px 12px; border-radius: 6px; hover:bg-gray-50;">
                                        👁️ View Details
                                    </button>
                                </td>
                            </tr>
                        `;
                    });
                } else {
                    amHtml = '<tr><td colspan="5" class="text-center" style="padding: 30px; color: #6b7280;">No area managers found.</td></tr>';
                }
                $(ids.amTable).html(amHtml);
            }

            // Render Sales Managers table
            if ($(ids.smTable).length) {
                let smHtml = '';
                if (data.sales_managers && data.sales_managers.length > 0) {
                    data.sales_managers.forEach(sm => {
                        smHtml += `
                            <tr>
                                <td style="font-weight: 500;">
                                    ${sm.display_name}
                                    <div style="font-size: 0.85em; color: #6b7280;">${sm.email}</div>
                                </td>
                                <td>${getBadge('Supervised by ' + (sm.supervising_am || 'N/A'), 'light')}</td>
                                <td class="text-center">
                                    <div style="font-weight: bold; font-size: 1.1em;">${sm.lead_count}</div>
                                    <div style="font-size: 0.8em; color: #10b981;">+${sm.leads_this_month || 0} this month</div>
                                </td>
                                <td class="text-center">
                                     <div style="font-weight: bold;">${sm.conversion_count}</div>
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-sm view-member-detail" 
                                            data-user-id="${sm.ID || sm.id}" 
                                            data-role="sales_manager" 
                                            data-name="${sm.display_name || 'N/A'}"
                                            style="background: white; border: 1px solid #d1d5db; color: #374151; padding: 6px 12px; border-radius: 6px;">
                                        👁️ View Details
                                    </button>
                                </td>
                            </tr>
                        `;
                    });
                } else {
                    smHtml = '<tr><td colspan="5" class="text-center" style="padding: 30px; color: #6b7280;">No sales managers found</td></tr>';
                }
                $(ids.smTable).html(smHtml);
            }

            // Render Cleaners table
            if ($(ids.cleanerTable).length) {
                let cleanerHtml = '';
                if (data.cleaners && data.cleaners.length > 0) {
                    data.cleaners.forEach(cleaner => {
                        let statusColor = 'secondary';
                        if (cleaner.status === 'on_job') statusColor = 'warning'; // Orange for busy
                        if (cleaner.status === 'active') statusColor = 'success'; // Green for active today
                        if (cleaner.status === 'offline') statusColor = 'secondary'; // Grey for offline

                        cleanerHtml += `
                            <tr>
                                <td style="font-weight: 500;">
                                    ${cleaner.name || 'N/A'}
                                    <div style="font-size: 0.85em; color: #6b7280;">${cleaner.phone || '-'}</div>
                                </td>
                                <td>${getBadge('Supervised by ' + (cleaner.supervising_am || 'N/A'), 'light')}</td>
                                <td class="text-center">
                                    <div style="font-weight: bold;">${cleaner.completed_visits}</div>
                                    <div style="font-size: 0.8em; color: #6b7280;">Visits</div>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-${statusColor}" style="padding: 5px 10px; border-radius: 12px; text-transform: capitalize;">
                                        ${cleaner.status_label || cleaner.status}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-sm view-member-detail" 
                                            data-user-id="${cleaner.id}" 
                                            data-role="cleaner" 
                                            data-name="${cleaner.name || 'N/A'}"
                                            style="background: white; border: 1px solid #d1d5db; color: #374151; padding: 6px 12px; border-radius: 6px;">
                                        👁️ View Details
                                    </button>
                                </td>
                            </tr>
                        `;
                    });
                } else {
                    cleanerHtml = '<tr><td colspan="5" class="text-center" style="padding: 30px; color: #6b7280;">No cleaners found</td></tr>';
                }
                $(ids.cleanerTable).html(cleanerHtml);
            }
        }
    };
})(jQuery);
