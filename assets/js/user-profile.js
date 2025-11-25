jQuery(document).ready(function ($) {
    const stateSelect = $('#sp-state-select');
    const citySelect = $('#sp-city-select');
    const statesCities = user_profile_vars.states_cities;
    const selectedCity = user_profile_vars.selected_city;

    // Populate States if empty (though PHP usually does this, JS backup is good)
    if (stateSelect.children('option').length <= 1 && statesCities) {
        statesCities.forEach(function (stateData) {
            // Handle potential key variations just in case
            const stateName = stateData.state || stateData.name;
            if (stateName) {
                stateSelect.append(new Option(stateName, stateName));
            }
        });
    }

    // Function to populate cities
    function populateCities(stateName) {
        citySelect.empty().append(new Option('Select City', ''));

        if (stateName && statesCities) {
            const stateData = statesCities.find(s => (s.state === stateName) || (s.name === stateName));
            if (stateData) {
                // Handle potential key variations for districts/cities
                const cities = stateData.districts || stateData.cities;
                if (cities) {
                    cities.forEach(function (city) {
                        const option = new Option(city, city);
                        if (city === selectedCity) {
                            option.selected = true;
                        }
                        citySelect.append(option);
                    });
                }
            }
        }
    }

    // Initial population if state is already selected
    if (stateSelect.val()) {
        populateCities(stateSelect.val());
    }

    // Handle State Change
    stateSelect.on('change', function () {
        populateCities($(this).val());
    });
});
