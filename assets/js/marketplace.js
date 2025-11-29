console.log('🔥🔥🔥 MARKETPLACE.JS FILE LOADED - TOP OF FILE 🔥🔥🔥');

jQuery(document).ready(function ($) {
    console.log('🚀 MARKETPLACE JS LOADED');
    console.log('marketplace_vars:', typeof marketplace_vars !== 'undefined' ? marketplace_vars : 'UNDEFINED');

    if (typeof marketplace_vars === 'undefined') {
        console.error('❌ marketplace_vars is NOT defined!');
        alert('Marketplace error: Configuration not loaded. The page may not have the correct slug or shortcode.');
        return;
    }

    console.log('✅ marketplace_vars.ajax_url:', marketplace_vars.ajax_url);
    console.log('✅ marketplace_vars.nonce:', marketplace_vars.nonce);

    const stateSelect = $('#state-filter');
    const citySelect = $('#city-filter');
    const statesCities = marketplace_vars.states_cities;

    // Populate States
    if (statesCities) {
        statesCities.forEach(function (stateData) {
            const stateName = stateData.state || stateData.name;
            if (stateName) {
                stateSelect.append(new Option(stateName, stateName));
            }
        });
    }

    // Handle State Change
    stateSelect.on('change', function () {
        const selectedState = $(this).val();
        citySelect.empty().append(new Option('All Cities', ''));

        if (selectedState) {
            const stateData = statesCities.find(s => (s.state === selectedState) || (s.name === selectedState));
            const cities = stateData ? (stateData.districts || stateData.cities) : [];
            if (cities) {
                cities.forEach(function (city) {
                    citySelect.append(new Option(city, city));
                });
            }
        }
    });

    // Check for URL parameters and auto-select filters
    const urlParams = new URLSearchParams(window.location.search);
    const filterState = urlParams.get('filter_state');
    const filterCity = urlParams.get('filter_city');

    if (filterState) {
        stateSelect.val(filterState).trigger('change');
    }

    if (filterCity && filterState) {
        // Wait a bit for cities to load, then select the city
        setTimeout(function () {
            citySelect.val(filterCity);
        }, 100);
    }

    // Handle Filter Button Click
    $('#apply-filters-btn').on('click', function (e) {
        e.preventDefault();
        fetchProjects();
    });

    // Initial Fetch
    fetchProjects();

    function fetchProjects() {
        console.log('📡 fetchProjects() called');

        const container = $('#project-listings-container');
        const spinner = container.find('.loading-spinner');

        spinner.show();

        const data = {
            action: 'filter_marketplace_projects',
            nonce: marketplace_vars.nonce,
            state: stateSelect.val(),
            city: citySelect.val()
        };

        console.log('📤 Sending AJAX request with data:', data);

        $.ajax({
            url: marketplace_vars.ajax_url,
            type: 'POST',
            data: data,
            success: function (response) {
                console.log('=== MARKETPLACE RESPONSE ===');
                console.log('Full Response:', response);
                console.log('Success:', response.success);
                console.log('Data:', response.data);
                if (response.data) {
                    console.log('HTML exists:', !!response.data.html);
                    console.log('HTML length:', response.data.html ? response.data.html.length : 0);
                    console.log('Count:', response.data.count);
                }
                console.log('===========================');

                spinner.hide();
                if (response.success) {
                    // Remove existing project items and messages
                    container.find('.project-card').remove();
                    container.find('.no-projects').remove();

                    if (response.data.html) {
                        container.append(response.data.html);
                    } else {
                        container.append('<div class="no-projects"><p>No projects found matching your criteria.</p></div>');
                    }
                } else {
                    console.error('Error response:', response.data);
                    alert('Error loading projects: ' + (response.data ? response.data.message : 'Unknown error'));
                }
            },
            error: function (xhr, status, error) {
                console.error('=== AJAX ERROR ===');
                console.error('Status:', status);
                console.error('Error:', error);
                console.error('Response Text:', xhr.responseText);
                console.error('==================');
                spinner.hide();
                alert('Network error while loading projects.');
            }
        });
    }
});

console.log('🔥🔥🔥 MARKETPLACE.JS FILE END 🔥🔥🔥');
