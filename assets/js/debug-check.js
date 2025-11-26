// CRITICAL DEBUG FILE - Check if this loads
console.log('🔴 AREA MANAGER DASHBOARD JS LOADED - VERSION 2.0');
console.log('Available vars:', typeof sp_area_dashboard_vars !== 'undefined' ? 'YES' : 'NO');
console.log('jQuery available:', typeof jQuery !== 'undefined' ? 'YES' : 'NO');

if (typeof sp_area_dashboard_vars !== 'undefined') {
    console.log('AJAX URL:', sp_area_dashboard_vars.ajax_url);
    console.log('Nonces:', Object.keys(sp_area_dashboard_vars));
}
