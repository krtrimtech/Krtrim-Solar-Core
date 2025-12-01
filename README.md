# Krtrim Solar Core

This plugin provides a comprehensive project management and bidding platform for solar companies. It creates a unified dashboard for "Solar Clients", "Solar Vendors", and "Area Managers".

## Features

*   **Role-Based Dashboards:** Separate dashboard views for clients, vendors, and area managers.
*   **Project Management:** Create and manage solar projects, including details like system size, cost, and status.
*   **Bidding System:** Vendors can bid on projects in a marketplace.
*   **Project Timeline:** Track the progress of projects through a series of steps.
*   **Custom Metaboxes:** All project data is handled with native WordPress metaboxes, with no external dependencies.

## File Structure

*   `unified-solar-dashboard.php`: The main plugin file.
*   `includes/`: Contains the core plugin classes.
    *   `class-post-types-taxonomies.php`: Defines the `solar_project` post type and related taxonomies.
    *   `class-admin-menus.php`: Creates the admin menus.
    *   `class-api-handlers.php`: Handles all AJAX and REST API endpoints.
    *   `class-custom-metaboxes.php`: Creates the custom metaboxes for the `solar_project` post type.
    *   `class-razorpay-light-client.php`: A lightweight client for interacting with the Razorpay API.
*   `public/`: Contains the public-facing views and assets.
    *   `views/`: Contains the templates for the dashboards and single project pages.
*   `admin/`: Contains the admin-facing views and assets.
    *   `views/`: Contains the templates for the admin pages.
*   `assets/`: Contains the CSS and JavaScript files.

## How to Use

1.  **Install the plugin:** Upload the plugin files to your `/wp-content/plugins/` directory, or install as a zip file through the WordPress admin panel.
2.  **Activate the plugin:** Activate the "Krtrim Solar Core" plugin through the 'Plugins' menu in WordPress.
3.  **Shortcodes:** The plugin automatically creates the necessary pages with the following shortcodes:
    *   `[unified_solar_dashboard]` on the "Dashboard" page.
    *   `[area_manager_dashboard]` on the "Area Manager Dashboard" page.
    *   `[vendor_registration_form]` on the "Vendor Registration" page.
    *   `[solar_project_marketplace]` on the "Project Marketplace" page.

## How it Works

The plugin creates a `solar_project` custom post type to store all project-related data. It uses custom roles (`solar_client`, `solar_vendor`, `area_manager`) to determine what content to display to the user. All custom fields are handled by native WordPress metaboxes.
