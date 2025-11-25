jQuery(document).ready(function ($) {
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

    // Handle Filter Button Click
    $('#apply-filters-btn').on('click', function (e) {
        e.preventDefault();
        fetchProjects();
    });

    // Initial Fetch
    fetchProjects();

    function fetchProjects() {
        const container = $('#project-listings-container');
        const spinner = container.find('.loading-spinner');

        spinner.show();

        const data = {
            action: 'filter_projects',
            nonce: marketplace_vars.nonce,
            state: stateSelect.val(),
            city: citySelect.val(),
            budget: $('#budget-filter').val()
        };

        $.ajax({
            url: marketplace_vars.ajax_url,
            type: 'POST',
            data: data,
            success: function (response) {
                spinner.hide();
                if (response.success) {
                    // Remove existing project items but keep spinner
                    container.find('.project-item').remove();
                    container.find('.no-projects').remove();

                    if (response.data.html) {
                        container.append(response.data.html);
                    } else {
                        container.append('<div class="no-projects"><p>No projects found matching your criteria.</p></div>');
                    }
                } else {
                    alert('Error loading projects: ' + (response.data ? response.data.message : 'Unknown error'));
                }
            },
            error: function () {
                spinner.hide();
                alert('Network error while loading projects.');
            }
        });
    }
});
