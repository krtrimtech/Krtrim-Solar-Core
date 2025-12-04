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
            
            <?php
            // Show coverage filter only for vendors
            $current_user = wp_get_current_user();
            $is_vendor = in_array('solar_vendor', (array) $current_user->roles);
            
            if ($is_vendor):
            ?>
            <div class="filter-group coverage-filter-group">
                <label class="coverage-filter-label">
                    <input type="checkbox" id="coverage-only-filter" name="coverage-only-filter">
                    <span>Show Only My Coverage Area</span>
                </label>
                <p class="filter-description">Filter projects to show only those in your purchased states/cities</p>
            </div>
            <?php endif; ?>

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

// Shortcode registration moved to unified-solar-dashboard.php to avoid duplicate registration
