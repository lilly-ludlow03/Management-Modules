document.addEventListener("DOMContentLoaded", () => {
    const dynamicFiltersContainer = document.getElementById('dynamic-filters-container');
    const goButton = document.getElementById('go-button');
    const resultsArea = document.getElementById('results-area');
    const placeholder = document.getElementById('table-container-placeholder');
    const eventTitle = document.getElementById('event-title');
    const totalCountSpan = document.getElementById('total-count');
    const tableOutput = document.getElementById('table-output');
    const impactChartCanvas = document.getElementById('impactChart');
    const lineChartCanvas = document.getElementById('lineChart');
    let impactChart = null;
    let lineChart = null;
    let continents, countries, cities;

    // Expand/Enlarge button logic
    window.toggleExpand = function (btn) {
        const container = btn.parentElement;
        const isExpanded = container.classList.contains('expanded-view');

        if (!isExpanded) {
            // Expanding
            container.classList.add('expanded-view');
            btn.innerHTML = '&#x2715;'; // Close/X icon
            btn.title = "Close Expanded View";
            document.body.style.overflow = 'hidden'; // Prevent background scroll
        } else {
            // Collapsing
            container.classList.remove('expanded-view');
            btn.innerHTML = '&#x2922;'; // Enlarge icon
            btn.title = "Enlarge";
            document.body.style.overflow = ''; // Restore scroll

            // Force reset height styles that might linger
            if (container.querySelector('canvas')) {
                container.style.height = ''; // Remove any inline height
                const graphInner = container.querySelector('.graph-inner-container');
                if (graphInner) graphInner.style.height = '100%';
            }
        }

        // Trigger Resize for graphs
        requestAnimationFrame(() => {
            const canvas = container.querySelector('canvas');
            if (canvas) {
                const chartInstance = Chart.getChart(canvas);
                if (chartInstance) {
                    chartInstance.resize();
                } else {
                    window.dispatchEvent(new Event('resize'));
                }
            }
        });
    };

    // Toggle Filters button logic
    const filterToggle = document.getElementById('filterToggle');
    const contentDiv = document.querySelector('.content');
    if (filterToggle && contentDiv) {
        filterToggle.addEventListener('click', () => {
            contentDiv.classList.toggle('filters-hidden');
            if (contentDiv.classList.contains('filters-hidden')) {
                filterToggle.innerHTML = '&#9654;'; // Point Right
                filterToggle.title = "Show Filters";
                // filterToggle.style.left = "0"; // Handled by CSS
            } else {
                filterToggle.innerHTML = '&#9664;'; // Point Left
                filterToggle.title = "Hide Filters";
                // filterToggle.style.left = "0"; // Handled by CSS
            }
            window.dispatchEvent(new Event('resize'));
        });
    }

    // Fetch region options
    Promise.all([
        fetch("get_filters.php?type=continents").then(r => r.json()).catch(e => []),
        fetch("get_filters.php?type=countries").then(r => r.json()).catch(e => []),
        fetch("get_filters.php?type=cities").then(r => r.json()).catch(e => [])
    ]).then(values => {
        [continents, countries, cities] = values;
    });

    // Create the search input for disruption event ID
    const searchContainer = document.createElement('div');
    searchContainer.classList.add('search-container');
    // searchContainer.style.position = 'relative'; // Handled by CSS
    // searchContainer.style.width = '300px'; // Handled by CSS
    // searchContainer.style.margin = '0 auto'; // Handled by CSS

    const searchInput = document.createElement('input');
    searchInput.type = 'text';
    searchInput.id = 'event-id-search';
    searchInput.placeholder = 'Search by Disruption Type...';
    searchInput.classList.add('search-input-dynamic');
    // searchInput.style.width = '100%'; // Handled by CSS
    // searchInput.style.padding = '8px'; // Handled by CSS

    //Create the suggestions list
    const suggestionsUl = document.createElement('ul');
    suggestionsUl.id = 'event-suggestions';
    suggestionsUl.classList.add('suggestions-list');
    // suggestionsUl.style.listStyle = 'none'; // Handled by CSS
    // suggestionsUl.style.padding = '0'; // Handled by CSS
    // suggestionsUl.style.margin = '0'; // Handled by CSS
    // suggestionsUl.style.border = '1px solid #ccc'; // Handled by CSS
    // suggestionsUl.style.position = 'absolute'; // Handled by CSS
    // suggestionsUl.style.width = '100%'; // Handled by CSS
    // suggestionsUl.style.backgroundColor = 'white'; // Handled by CSS
    // suggestionsUl.style.zIndex = '1001'; // Handled by CSS
    // suggestionsUl.style.maxHeight = '150px'; // Handled by CSS
    // suggestionsUl.style.overflowY = 'auto'; // Handled by CSS

    searchContainer.appendChild(searchInput);
    searchContainer.appendChild(suggestionsUl);
    dynamicFiltersContainer.appendChild(searchContainer);

    // Add search logic for disruption event ID
    searchInput.addEventListener('input', () => {
        const value = searchInput.value.trim();
        suggestionsUl.innerHTML = '';
        if (value === '') return;

        fetch(`get_disruption_events.php?q=${encodeURIComponent(value)}`)
            .then(response => response.json())
            .then(data => {
                data.forEach(item => {
                    const li = document.createElement('li');
                    li.textContent = item.display;
                    li.dataset.value = item.value;
                    li.classList.add('suggestion-item');
                    // li.style.padding = '8px'; // Handled by CSS
                    // li.style.cursor = 'pointer'; // Handled by CSS
                    li.addEventListener('mouseover', () => li.style.backgroundColor = '#f0f0f0');
                    li.addEventListener('mouseout', () => li.style.backgroundColor = 'white');

                    li.addEventListener('click', () => {
                        // Populate input with the value (either Category Name or Event ID)
                        searchInput.value = item.value;
                        suggestionsUl.innerHTML = '';
                    });
                    suggestionsUl.appendChild(li);
                });
            })
            .catch(err => console.error("Error fetching suggestions:", err));
    });

    // Hide suggestions list if user clicks outside of the search container
    document.addEventListener("click", (e) => {
        if (!searchContainer.contains(e.target)) {
            suggestionsUl.innerHTML = "";
        }
    });

    // Optional filters
    const regionFilterCheckbox = document.getElementById('regionFilter');
    const dateFilterCheckbox = document.getElementById('dateFilter');
    const tierFilterCheckbox = document.getElementById('tierFilter');
    const optionalDynamicFiltersContainer = document.getElementById('optional-dynamic-filters-container');

    dateFilterCheckbox.addEventListener('change', (event) => {
        const existingDateFilter = document.getElementById('dynamic-date-filter-container');
        if (event.currentTarget.checked) {
            if (!existingDateFilter) {
                const dateContainer = document.createElement('div');
                dateContainer.id = 'dynamic-date-filter-container';
                dateContainer.classList.add('dynamic-filter-box');
                // dateContainer.style.marginTop = '10px'; // Handled by CSS

                const fromLabel = document.createElement('label');
                fromLabel.textContent = 'From: ';
                const fromInput = document.createElement('input');
                fromInput.type = 'date';
                fromInput.id = 'dynamic-date-from';

                const toLabel = document.createElement('label');
                toLabel.textContent = ' To: ';
                const toInput = document.createElement('input');
                toInput.type = 'date';
                toInput.id = 'dynamic-date-to';

                dateContainer.appendChild(fromLabel);
                dateContainer.appendChild(fromInput);
                dateContainer.appendChild(toLabel);
                dateContainer.appendChild(toInput);

                optionalDynamicFiltersContainer.appendChild(dateContainer);
            }
        } else {
            if (existingDateFilter) existingDateFilter.remove();
        }
    });

    //Region filter checkbox logic
    regionFilterCheckbox.addEventListener('change', (event) => {
        const existingRegionFilter = document.getElementById('region-filter-container');
        if (event.currentTarget.checked) {
            if (!existingRegionFilter) {
                const regionContainer = document.createElement('div');
                regionContainer.id = 'region-filter-container';
                regionContainer.classList.add('region-filter-box');
                // regionContainer.style.marginBottom = '15px'; // Handled by CSS

                const regionTypeLabel = document.createElement('label');
                regionTypeLabel.textContent = 'Region: ';
                const regionTypeSelect = document.createElement('select');
                regionTypeSelect.id = 'dynamic-region-type-select';

                const regionTypes = ['Select...', 'Continent', 'Country', 'City'];
                regionTypes.forEach(type => {
                    const option = document.createElement('option');
                    option.value = type;
                    option.textContent = type;
                    regionTypeSelect.appendChild(option);
                });

                regionContainer.appendChild(regionTypeLabel);
                regionContainer.appendChild(regionTypeSelect);

                const regionValueContainer = document.createElement('div');
                regionValueContainer.id = 'region-value-container';
                regionValueContainer.classList.add('dynamic-filter-box');
                // regionValueContainer.style.marginTop = '10px'; // Handled by CSS
                regionContainer.appendChild(regionValueContainer);

                optionalDynamicFiltersContainer.appendChild(regionContainer);
                //Region type select logic
                regionTypeSelect.addEventListener('change', () => {
                    regionValueContainer.innerHTML = '';
                    const selectedType = regionTypeSelect.value;
                    let options = [];

                    if (selectedType === 'Continent') options = continents;
                    if (selectedType === 'Country') options = countries;
                    if (selectedType === 'City') options = cities;

                    if (options && options.length > 0) {
                        const valueLabel = document.createElement('label');
                        valueLabel.textContent = `${selectedType}: `;
                        const valueSelect = document.createElement('select');

                        options.forEach(name => {
                            const opt = document.createElement('option');
                            opt.value = name;
                            opt.textContent = name;
                            valueSelect.appendChild(opt);
                        });

                        regionValueContainer.appendChild(valueLabel);
                        regionValueContainer.appendChild(valueSelect);
                    }
                });
            }
        } else {
            if (existingRegionFilter) existingRegionFilter.remove();
        }
    });

    //Tier filter checkbox logic
    tierFilterCheckbox.addEventListener('change', (event) => {
        const existingTierFilter = document.getElementById('dynamic-tier-filter-container');
        if (event.currentTarget.checked) {
            if (!existingTierFilter) {
                const tierContainer = document.createElement('div');
                tierContainer.id = 'dynamic-tier-filter-container';
                tierContainer.classList.add('dynamic-filter-box');
                // tierContainer.style.marginTop = '10px'; // Handled by CSS

                const tierLabel = document.createElement('label');
                tierLabel.textContent = 'Tier: ';
                const tierSelect = document.createElement('select');
                tierSelect.id = 'dynamic-tier-select';

                const tierOptions = ['Select...', '1', '2', '3'];
                tierOptions.forEach(tier => {
                    const option = document.createElement('option');
                    option.value = tier === 'Select...' ? '' : tier;
                    option.textContent = tier;
                    tierSelect.appendChild(option);
                });

                tierContainer.appendChild(tierLabel);
                tierContainer.appendChild(tierSelect);

                optionalDynamicFiltersContainer.appendChild(tierContainer);
            }
        } else {
            if (existingTierFilter) existingTierFilter.remove();
        }
    });

    // Clear all filters logic
    const clearButton = document.getElementById('clear-filters');
    if (clearButton) {
        clearButton.addEventListener('click', () => {
            // Clear main search
            const searchInput = document.getElementById('event-id-search');
            if (searchInput) searchInput.value = '';

            // Uncheck optional filters and trigger change
            if (regionFilterCheckbox) { regionFilterCheckbox.checked = false; regionFilterCheckbox.dispatchEvent(new Event('change')); }
            if (dateFilterCheckbox) { dateFilterCheckbox.checked = false; dateFilterCheckbox.dispatchEvent(new Event('change')); }
            if (tierFilterCheckbox) { tierFilterCheckbox.checked = false; tierFilterCheckbox.dispatchEvent(new Event('change')); }

            // Reset results area
            if (resultsArea) resultsArea.classList.add('hidden');
            if (placeholder) placeholder.classList.remove('hidden');
            if (eventTitle) eventTitle.textContent = '';
            if (totalCountSpan) totalCountSpan.textContent = '';
            if (tableOutput) tableOutput.innerHTML = '';
            if (impactChart) {
                impactChart.destroy();
                impactChart = null;
            }
            if (lineChart) {
                lineChart.destroy();
                lineChart = null;
            }
        });
    }


    // Go Button Logic
    goButton.addEventListener('click', () => {
        contentDiv.classList.add('filters-hidden');
        filterToggle.style.left = "0"; // Handled by CSS

        const searchTerm = searchInput.value.trim();
        if (!searchTerm) {
            alert("Please enter a Disruption Type.");
            return;
        }

        // Date validation for optional filter
        if (dateFilterCheckbox.checked) {
            const dateFrom = document.getElementById('dynamic-date-from');
            const dateTo = document.getElementById('dynamic-date-to');
            const startDate = dateFrom ? dateFrom.value : null;
            const endDate = dateTo ? dateTo.value : null;

            if (!startDate || !endDate) {
                alert("Please select both a start and end date if using the date filter.");
                return;
            }
            if (startDate > endDate) {
                alert("Start date cannot be after the end date.");
                return;
            }
        }

        const params = new URLSearchParams();
        params.append('search_term', searchTerm);

        // Collect Optional Filters
        // Region
        const regionTypeSelect = document.getElementById('dynamic-region-type-select');
        const regionValueSelect = document.querySelector('#region-value-container select');
        if (regionTypeSelect && regionTypeSelect.value !== 'Select...') {
            params.append('region_type', regionTypeSelect.value);
            if (regionValueSelect) params.append('region', regionValueSelect.value);
        }

        // Date
        const dateFrom = document.getElementById('dynamic-date-from');
        const dateTo = document.getElementById('dynamic-date-to');
        if (dateFrom && dateFrom.value) params.append('date_from', dateFrom.value);
        if (dateTo && dateTo.value) params.append('date_to', dateTo.value);

        // Tier
        const tierSelect = document.getElementById('dynamic-tier-select');
        if (tierSelect && tierSelect.value !== 'Select...') {
            params.append('tier', tierSelect.value);
        }

        //Fetch disruption event details from the database
        fetch(`get_disruption_event_details.php?${params.toString()}`)
            .then(r => r.json())
            .then(data => {
                if (data.error) {
                    alert(data.error);
                    return;
                }

                // Show Results Area
                placeholder.classList.add('hidden');
                resultsArea.classList.remove('hidden');

                // Update Header
                eventTitle.textContent = `Analysis for: "${data.search_term}"`;
                totalCountSpan.textContent = data.total_affected;

                // Render Table
                renderTable(data.companies);

                // Render Charts
                renderImpactChart(data.impact_counts);
                renderLineChart(data.timeline_data);
            })
            .catch(err => {
                console.error("Error fetching details:", err);
                alert("Failed to load event details.");
            });
    });

    //Creates the table
    function renderTable(companies) {
        if (!companies || companies.length === 0) {
            tableOutput.innerHTML = '<p>No companies affected found for this criteria.</p>';
            return;
        }

        const table = document.createElement('table');
        table.classList.add('result-table');
        // table.style.width = '100%'; // Handled by CSS
        // table.style.borderCollapse = 'collapse'; // Handled by CSS

        const thead = document.createElement('thead');
        thead.innerHTML = `
            <tr>
                <th class="result-th">Date</th>
                <th class="result-th">Event</th>
                <th class="result-th">Company Name</th>
                <th class="result-th">Tier</th>
                <th class="result-th">Region</th>
                <th class="result-th">Impact Level</th>
            </tr>
        `;
        table.appendChild(thead);

        const tbody = document.createElement('tbody');
        companies.forEach(c => {
            const tr = document.createElement('tr');
            // Use loose check for null/undefined/empty
            if (!c.EventRecoveryDate) {
                tr.classList.add('error-row');
            }
            tr.innerHTML = `
                <td class="result-td">${c.EventDate}</td>
                <td class="result-td">${c.CategoryName} (ID: ${c.EventID})</td>
                <td class="result-td">${c.CompanyName}</td>
                <td class="result-td">${c.TierLevel}</td>
                <td class="result-td">${c.CountryName}, ${c.ContinentName}</td>
                <td class="result-td">${c.ImpactLevel}</td>
            `;
            tbody.appendChild(tr);
        });
        table.appendChild(tbody);

        tableOutput.innerHTML = '';
        tableOutput.appendChild(table);
    }
    //Creates the bar graph
    function renderImpactChart(counts) {
        const ctx = impactChartCanvas.getContext('2d');
        if (impactChart) impactChart.destroy();

        impactChart = new Chart(ctx, {
            type: 'bar', //bar chart
            data: {
                labels: ['High', 'Medium', 'Low'],
                datasets: [{
                    label: 'Number of Companies Affected',
                    data: [counts.High, counts.Medium, counts.Low],
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.6)', // High - Red
                        'rgba(255, 206, 86, 0.6)', // Medium - Yellow
                        'rgba(75, 192, 192, 0.6)'  // Low - Green
                    ],
                    borderColor: [
                        'rgba(255, 99, 132, 1)',
                        'rgba(255, 206, 86, 1)',
                        'rgba(75, 192, 192, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: { display: true, text: 'Number of Companies' },
                        ticks: { stepSize: 1 }
                    }
                },
                plugins: {
                    legend: { display: false },
                    title: { display: true, text: 'Companies Affected by Impact Level' }
                }
            }
        });
    }
    //Creates the line graph
    function renderLineChart(data) {
        const ctx = lineChartCanvas.getContext('2d');
        if (lineChart) lineChart.destroy();

        // Prepare data for line chart
        // data is assumed to be array of { date: 'YYYY-MM-DD', count: N }
        const labels = data ? data.map(d => d.date) : [];
        const counts = data ? data.map(d => d.count) : [];

        lineChart = new Chart(ctx, {
            type: 'line', //line graph
            data: {
                labels: labels,
                datasets: [{
                    label: 'Impacts Over Time',
                    data: counts,
                    borderColor: 'rgba(54, 162, 235, 1)',
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    fill: true,
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: { display: true, text: 'Number of Impacts' },
                        ticks: { stepSize: 1 }
                    },
                    x: {
                        title: { display: true, text: 'Date' }
                    }
                },
                plugins: {
                    legend: { display: false },
                    title: { display: true, text: 'Timeline of Impacts' }
                }
            }
        });
    }

});
