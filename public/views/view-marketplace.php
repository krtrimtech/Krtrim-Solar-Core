<?php
/**
 * Plugin Name: Solar Project Marketplace
 * Description: Displays a filterable list of solar projects for vendors to bid on.
 * Version: 1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function sp_project_marketplace_shortcode() {
    // Enqueue styles
    wp_enqueue_style( 'unified-dashboard-styles' );

    ob_start();
    ?>
    <div class="project-marketplace-wrapper">
        
        <!-- Filters -->
        <div class="marketplace-filters">
            <h2>Find Projects</h2>
            
            <div class="filter-group">
                <label for="state-filter">State</label>
                <select id="state-filter" name="state-filter">
                    <option value="">All States</option>
                </select>
            </div>

            <div class="filter-group">
                <label for="city-filter">City</label>
                <select id="city-filter" name="city-filter">
                    <option value="">All Cities</option>
                </select>
            </div>

            <div class="filter-group">
                <label for="budget-filter">Max Budget (₹)</label>
                <input type="number" id="budget-filter" name="budget-filter" placeholder="e.g., 500000">
            </div>

            <button id="apply-filters-btn" class="btn btn-primary">Apply Filters</button>
        </div>

        <!-- Project Listings -->
        <div id="project-listings-container" class="project-listings">
            <div class="loading-spinner" style="display: none;">Loading...</div>
            <?php // AJAX will populate this section ?>
        </div>

    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'solar_project_marketplace', 'sp_project_marketplace_shortcode' );
