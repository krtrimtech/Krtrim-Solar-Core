/**
 * Shared Notification Component JavaScript
 * 
 * Reusable notification system for all dashboards.
 * 
 * @package Krtrim_Solar_Core
 */

(function ($) {
    'use strict';

    window.KSC_NotificationComponent = {
        apiUrl: null,
        nonce: null,
        refreshInterval: null,
        isInitialized: false,

        init: function (apiUrl, nonce) {
            if (this.isInitialized) return;
            
            this.apiUrl = apiUrl;
            this.nonce = nonce;

            if (!this.apiUrl || !this.nonce) {
                console.error('[KSC Notifications] Missing API URL or Nonce');
                return;
            }

            // Bind global events only once
            this.bindEvents();

            // Load immediately
            this.loadNotifications();

            // Setup polling (every 30 seconds)
            this.refreshInterval = setInterval(() => {
                this.loadNotifications();
            }, 30000);

            this.isInitialized = true;
            console.log('[KSC Notifications] Initialized 🔔');
        },

        bindEvents: function () {
            const self = this;

            // Global delegation for dismiss buttons
            $(document).on('click', '.btn-dismiss-notification', function () {
                const $item = $(this).closest('.notification-item');
                const id = $item.data('notification-id');
                if (!id) return;

                // Visual immediate feedback
                $item.css({ opacity: 0.5, pointerEvents: 'none' });

                fetch(`${self.apiUrl}/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-WP-Nonce': self.nonce
                    }
                }).then(response => {
                    if (response.ok) {
                        self.loadNotifications();
                    } else {
                        $item.css({ opacity: 1, pointerEvents: 'auto' });
                    }
                }).catch(err => {
                    $item.css({ opacity: 1, pointerEvents: 'auto' });
                    console.error('Failed to dismiss notification:', err);
                });
            });
        },

        loadNotifications: function () {
            const notifList = document.getElementById('notif-list');
            const notifCount = document.getElementById('notif-count');
            
            if (!notifList) return; // Silent return if elements aren't securely in the DOM

            fetch(this.apiUrl, {
                method: 'GET',
                credentials: 'same-origin',
                headers: { 'X-WP-Nonce': this.nonce }
            })
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            })
            .then(data => {
                // Handle `{notifications: [...]}` struct OR `[...]` fallback gracefully
                const notifications = data.notifications || data; 

                if (notifications && notifications.length > 0) {
                    let html = '';
                    notifications.forEach(n => {
                        const borderColor = n.type === 'approved' ? '#28a745' : n.type === 'rejected' ? '#dc3545' : '#007bff';
                        const bgColor = n.type === 'approved' ? '#f8fff9' : n.type === 'rejected' ? '#fff5f5' : '#f0f7ff';

                        html += `<div class="notification-item" style="padding:12px;position:relative; border-radius:8px; border-left:4px solid ${borderColor}; background:${bgColor}; margin-bottom:10px;" data-notification-id="${n.id}">`;
                        html += `<button class="btn-dismiss-notification" style="position:absolute; top:8px; right:8px; background:none; border:none; font-size:16px; cursor:pointer;" title="Dismiss">&times;</button>`;
                        html += `<div style="font-weight:600; color:#333;">${n.icon || '🔔'} ${n.title}</div>`;
                        html += `<div style="font-size:12px; color:#666; margin-top:4px;">${n.message}</div>`;
                        if (n.time_ago) {
                            html += `<div style="font-size:10px; color:#999; margin-top:4px;">${n.time_ago}</div>`;
                        }
                        html += `</div>`;
                    });
                    notifList.innerHTML = html;
                    
                    if (notifCount) {
                        notifCount.textContent = notifications.length;
                        notifCount.style.display = 'inline-block';
                    }
                } else {
                    notifList.innerHTML = '<p style="text-align:center; color:#999; padding: 20px; margin: 0;">No new notifications</p>';
                    if (notifCount) notifCount.style.display = 'none';
                }
            })
            .catch(error => {
                notifList.innerHTML = '<p style="color:#dc3545; text-align:center; padding: 20px; margin:0;">Error loading notifications</p>';
            });
        }
    };

})(jQuery);
