

// Add Company Form Logic

document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('form');
    const plotDiv = document.querySelector('.plot');

    // Show or hide factory capacity field based on the company type
    document.getElementById('companyType').addEventListener('change', function () {
        var factoryCapacityContainer = document.getElementById('factoryCapacityContainer');
        if (this.value === 'manufacturer') {
            factoryCapacityContainer.classList.remove('hidden');
        } else {
            factoryCapacityContainer.classList.add('hidden');
        }
    });

    // Handle add new location
    document.querySelectorAll('input[name="addLocationChoice"]').forEach(elem => {
        elem.addEventListener('change', function (event) {
            var newLocationFields = document.getElementById('newLocationFields');
            var existingLocationFields = document.getElementById('existingLocationFields');
            if (event.target.value === 'yes') {
                newLocationFields.classList.remove('hidden');
                existingLocationFields.classList.add('hidden');
                if (document.getElementById('continent').options.length === 0) {
                    fetchLocationData();
                }
            } else {
                newLocationFields.classList.add('hidden');
                existingLocationFields.classList.remove('hidden');
                // Implement fetching for existing locations
                fetchExistingContinents();
            }
        });
    });

    // Main "Add Company" button
    document.getElementById('addCompanyBtn').addEventListener('click', function () {
        const formData = new FormData(form);
        const companyType = formData.get('companyType');
        const companyName = formData.get('companyName');
        const tierLevel = formData.get('tierLevel');
        const addLocationChoice = formData.get('addLocationChoice');

        // Basic required field validation
        if (!companyType || !companyName || !tierLevel) {
            alert('Company Type, Company Name, and Tier Level are required fields.');
            return;
        }

        // Manufacturer specific validation: factory capacity required
        if (companyType === 'manufacturer') {
            const capacity = formData.get('factoryCapacity');
            if (!capacity || capacity.trim() === '') {
                alert('Factory Capacity is required for Manufacturers.');
                return;
            }
        }

        // Location validation based on choice
        if (addLocationChoice === 'yes') {
            const country = formData.get('country');
            const city = formData.get('city');
            if (!country || country.trim() === '' || !city || city.trim() === '') {
                alert('Country and City are required when adding a new location.');
                return;
            }
        } else if (addLocationChoice === 'no') {
            // If existing, make sure they selected something (optional but good practice)
            const existingCountry = formData.get('existingCountry');
            const existingCity = formData.get('existingCity');
            if (!existingCountry || !existingCity) {
                alert('Please select an existing Country and City.');
                return;
            }
        } else {
            alert('Please select whether to add a new location or use an existing one.');
            return;
        }

        // Build a mini summary of what user is going to sumbit
        let confirmationHTML = '<h3>Add Company?</h3>';
        confirmationHTML += `<p><strong>Company Type:</strong> ${companyType}</p>`;
        confirmationHTML += `<p><strong>Company Name:</strong> ${companyName}</p>`;
        confirmationHTML += `<p><strong>Tier Level:</strong> ${tierLevel}</p>`;

        // Only show factory capacity summary
        if (formData.get('companyType') === 'manufacturer' && formData.get('factoryCapacity')) {
            confirmationHTML += `<p><strong>Factory Capacity:</strong> ${formData.get('factoryCapacity')}</p>`;
        }

        // Show either "New Location" or "Existing Location"
        if (formData.get('addLocationChoice') === 'yes') {
            confirmationHTML += `<h4>New Location</h4>`;
            confirmationHTML += `<p><strong>Continent:</strong> ${formData.get('continent')}</p>`;
            confirmationHTML += `<p><strong>Country:</strong> ${formData.get('country')}</p>`;
            confirmationHTML += `<p><strong>City:</strong> ${formData.get('city')}</p>`;
        } else if (formData.get('addLocationChoice') === 'no') {
            confirmationHTML += `<h4>Existing Location</h4>`;
            confirmationHTML += `<p><strong>Continent:</strong> ${formData.get('existingContinent')}</p>`;
            confirmationHTML += `<p><strong>Country:</strong> ${formData.get('existingCountry')}</p>`;
            confirmationHTML += `<p><strong>City:</strong> ${formData.get('existingCity')}</p>`;
        }

        // Add Confirm / Cancel button with styling
        // Classes used here for dynamic content
        confirmationHTML += '<div class="confirm-buttons-container">';
        confirmationHTML += '<button type="button" id="confirmBtn" class="confirm-btn">Confirm</button>';
        confirmationHTML += '<button type="button" id="cancelBtn" class="cancel-btn">Cancel</button>';
        confirmationHTML += '</div>';

        plotDiv.innerHTML = confirmationHTML;

        // If users confirm send form
        document.getElementById('confirmBtn').addEventListener('click', function () {
            fetch('add_company.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    alert(data.message);
                    if (data.status === 'success') {
                        // On success reset form and hide all conditional sections
                        form.reset();
                        plotDiv.innerHTML = '';
                        document.getElementById('factoryCapacityContainer').classList.add('hidden');
                        document.getElementById('newLocationFields').classList.add('hidden');
                        document.getElementById('existingLocationFields').classList.add('hidden');
                    }
                })
                .catch(error => {
                    console.error('Fetch Error:', error);
                    alert('An error occurred. Please check the browser console for more details.');
                });
        });
        // If the user cancels just clear out the confirmation
        document.getElementById('cancelBtn').addEventListener('click', function () {
            plotDiv.innerHTML = '';
        });
    });
});

// Location Fetch Helpers

// Fetch Locations
function fetchLocationData() {
    fetch('get_filters.php?type=continents')
        .then(response => response.json())
        .then(data => populateSelect('continent', data));
}

function fetchExistingContinents() {
    fetch('get_filters.php?type=continents')
        .then(response => response.json())
        .then(data => {
            populateSelect('existingContinent', data);
            // Trigger loading of countries for the first continent
            if (data.length > 0) {
                fetchExistingCountries(data[0]);
            }
        });
}

function fetchExistingCountries(continent) {
    fetch(`get_filters.php?type=countries&continent=${continent}`)
        .then(response => response.json())
        .then(data => {
            populateSelect('existingCountry', data);
            // Trigger loading of cities for the first country
            if (data.length > 0) {
                fetchExistingCities(data[0]);
            }
        });
}

function fetchExistingCities(country) {
    fetch(`get_filters.php?type=cities&country=${country}`)
        .then(response => response.json())
        .then(data => populateSelect('existingCity', data));
}
// When the existing continent changes
document.getElementById('existingContinent').addEventListener('change', function () {
    fetchExistingCountries(this.value);
});
// When existing countries change
document.getElementById('existingCountry').addEventListener('change', function () {
    fetchExistingCities(this.value);
});

// Helper function to populate 
function populateSelect(selectId, data) {
    var select = document.getElementById(selectId);
    select.innerHTML = '';
    // Create an <option> for each item in the data array
    data.forEach(function (item) {
        var option = document.createElement('option');
        option.value = item;
        option.text = item;
        select.add(option);
    });
}