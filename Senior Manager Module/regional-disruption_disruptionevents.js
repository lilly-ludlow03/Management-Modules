document.addEventListener('DOMContentLoaded', () => {
    // --- Get Elements ---
    const dateFilterCheckbox = document.getElementById('dateFilter');
    const regionFilterCheckbox = document.getElementById('regionFilter');
    const impactFilterCheckbox = document.getElementById('impactFilter');
    const dynamicFiltersContainer = document.getElementById('dynamic-filters-container');

    const goButton = document.getElementById('go-button');
    const clearButton = document.getElementById('clear-filters');
    const placeholderMessage = document.getElementById('placeholder-message');
    const resultsContainer = document.querySelector('.data-display-container');

    let chart1 = null;

    // Expand/Enlarge Functionality
    window.toggleExpand = function(btn) {
        const container = btn.parentElement;
        const isExpanded = container.classList.contains('expanded-view');
        
        if (!isExpanded) {
            // Expanding
            container.classList.add('expanded-view');
            btn.innerHTML = '&#x2715;'; // Close/X icon
            btn.title = "Close Expanded View";
            document.body.classList.add('no-scroll'); // Prevent background scroll
        } else {
            // Collapsing
            container.classList.remove('expanded-view');
            btn.innerHTML = '&#x2922;'; // Enlarge icon
            btn.title = "Enlarge";
            document.body.classList.remove('no-scroll'); // Restore scroll
            
             // Force reset height styles
            if (container.querySelector('canvas')) {
                 container.style.height = ''; // Remove any inline height
                 const graphContainer = container.querySelector('.graph-container');
                 if (graphContainer) graphContainer.style.height = '100%';
            }
        }
        
        // Trigger Resize for Charts
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

    let continents, countries, cities;

    // Toggle Filters Logic
    const filterToggle = document.getElementById('filterToggle');
    const contentDiv = document.querySelector('.content');
    if(filterToggle && contentDiv) {
        filterToggle.addEventListener('click', () => {
            contentDiv.classList.toggle('filters-hidden');
            if (contentDiv.classList.contains('filters-hidden')) {
                filterToggle.innerHTML = '&#9654;'; // Arrow Points Right
                filterToggle.title = "Show Filters";
                // filterToggle.style.left = "0"; // Handled by CSS
            } else {
                filterToggle.innerHTML = '&#9664;'; // Arrow Points Left
                filterToggle.title = "Hide Filters";
                // filterToggle.style.left = "30vw"; // Handled by CSS
            }
            window.dispatchEvent(new Event('resize'));
        });
    }

    // Fetch data for filters
    Promise.all([
        fetch("get_filters.php?type=continents").then(r => r.json()).catch(e => []),
        fetch("get_filters.php?type=countries").then(r => r.json()).catch(e => []),
        fetch("get_filters.php?type=cities").then(r => r.json()).catch(e => [])
    ]).then(values => {
        [continents, countries, cities] = values;
    }).catch(error => {
        console.error("Error fetching initial filter data:", error);
    });


    // Chart Rendering Function (Stacked Bar Chart)
    function renderStackedBarChart(canvasId, data, chartInstanceRef) {
        const ctx = document.getElementById(canvasId).getContext('2d');
        if (chartInstanceRef) {
            chartInstanceRef.destroy();
        }

        if (!data || data.length === 0) {
            return null;
        }

        // Process data for stacking
        const continents = {};
        const countries = new Set();
        const impacts = ['High', 'Medium', 'Low']; // Define order
        const impactColors = {
            'High': 'rgba(255, 99, 132, 0.7)',
            'Medium': 'rgba(255, 206, 86, 0.7)',
            'Low': 'rgba(75, 192, 192, 0.7)'
        };

        data.forEach(item => {
            if (!continents[item.ContinentName]) {
                continents[item.ContinentName] = {};
            }
            if (!continents[item.ContinentName][item.CountryName]) {
                continents[item.ContinentName][item.CountryName] = {};
            }
            continents[item.ContinentName][item.CountryName][item.ImpactLevel] = item.disruption_count;
            countries.add(item.CountryName);
        });
        
        const sortedCountries = Array.from(countries).sort();
        //Create the datasets for the chart.
        const datasets = impacts.map(impact => {
            return {
                label: impact,
                data: sortedCountries.map(country => {
                    let total = 0;
                    for (const continent in continents) {
                        if (continents[continent][country] && continents[continent][country][impact]) {
                            total += continents[continent][country][impact];
                        }
                    }
                    return total;
                }),
                backgroundColor: impactColors[impact],
            };
        });
        //Create the chart.
        const newChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: sortedCountries,
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Disruptions by Country and Impact Level'
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                },
                scales: {
                    x: {
                        stacked: true,
                        ticks: {
                            autoSkip: false,
                            maxRotation: 90,
                            minRotation: 45
                        }
                    },
                    y: {
                        stacked: true,
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Number of Disruptions'
                        }
                    }
                }
            }
        });

        return newChart;
    }


    // Event Handlers for Go Buttons & Clear Buttons 
    //Go button logic. Allows the user to display the results of the selected filters.
    goButton.addEventListener('click', () => {
        const dateFromInput = document.getElementById('dynamic-date-from');
        const dateToInput = document.getElementById('dynamic-date-to');
        const startDate = dateFromInput ? dateFromInput.value : null;
        const endDate = dateToInput ? dateToInput.value : null;
        contentDiv.classList.add('filters-hidden');
        // filterToggle.style.left = "0"; // Handled by CSS

        // Date validation. Ensures the user inputs a proper date range
        if (dateFilterCheckbox.checked) {
            if (!startDate || !endDate) {
                alert("Please select both a start and end date.");
                return;
            }
            if (startDate > endDate) {
                alert("Start date cannot be after the end date.");
                return;
            }
        }

        // Hide placeholder and show results
        if(placeholderMessage) placeholderMessage.classList.add('hidden');
        if(resultsContainer) resultsContainer.classList.remove('hidden');

        const params = new URLSearchParams();
        //Get filters
        // Date
        const dateFrom = document.getElementById('dynamic-date-from');
        const dateTo = document.getElementById('dynamic-date-to');
        if (dateFrom) params.append('date_from', dateFrom.value);
        if (dateTo) params.append('date_to', dateTo.value);

        // Region
        const regionTypeSelect = document.getElementById('dynamic-region-type-select');
        const regionValueSelect = document.querySelector('#region-value-container select');
        if (regionTypeSelect) params.append('region_type', regionTypeSelect.value);
        if (regionValueSelect) params.append('region', regionValueSelect.value);

        // Impact
        const impactSelect = document.getElementById('impactSelect');
        if (impactSelect) params.append('impact', impactSelect.value);

        //Fetch the data from the database using get disruption heatmap data php
        fetch(`get_disruption_heatmap_data.php?${params.toString()}`)
            .then(r => r.json())
            .then(data => {
                // Map data is in data.map_data
                chart1 = renderStackedBarChart('map1', data.map_data, chart1);
                // Table data is in data.table_data
                renderTable(data.table_data);
            })
            .catch(err => console.error("Error loading map 1:", err));
    });

    //Clear button logic. Allows the user to clear the filters and reset the chart and table.
    clearButton.addEventListener('click', () => {
        // Uncheck all checkboxes
        document.querySelectorAll('input[name="filterOptions"]').forEach(checkbox => {
            checkbox.checked = false;
            // Manually trigger change event to remove dynamic filters
            checkbox.dispatchEvent(new Event('change'));
        });
        
        // Show placeholder and hide results
        if(placeholderMessage) placeholderMessage.classList.remove('hidden');
        if(resultsContainer) resultsContainer.classList.add('hidden');

        // Clear chart and table
        if (chart1) {
            chart1.destroy();
            chart1 = null;
        }
        document.getElementById('table-output').innerHTML = ''; // Keep it simple, placeholder is separate
    });

    //Creates the table
    function renderTable(data) {
        const tableOutput = document.getElementById('table-output');
        if (!data || data.length === 0) {
            tableOutput.innerHTML = '<p class="result-msg">No detailed events found for selected filters.</p>';
            return;
        }

        const table = document.createElement('table');
        table.classList.add('result-table');
        
        const thead = document.createElement('thead');
        thead.innerHTML = `
            <tr>
                <th class="result-th">Company Name</th>
                <th class="result-th">Disruption Event Type</th>
                <th class="result-th">Impact Level</th>
                <th class="result-th">Location</th>
                <th class="result-th">Date</th>
            </tr>
        `;
        table.appendChild(thead);

        const tbody = document.createElement('tbody');
        data.forEach(row => {
            const tr = document.createElement('tr');
            // Use loose check for null/undefined/empty
            if (!row.EventRecoveryDate) {
                tr.classList.add('error-row');
            }
            tr.innerHTML = `
                <td class="result-td">${row.CompanyName}</td>
                <td class="result-td">${row.event_type}</td>
                <td class="result-td">${row.impact}</td>
                <td class="result-td">${row.location}</td>
                <td class="result-td">${row.date}</td>
            `;
            tbody.appendChild(tr);
        });
        table.appendChild(tbody);
        
        tableOutput.innerHTML = '';
        tableOutput.appendChild(table);
    }


    //Date filter logic. Allows the user to filter the data by date.
    dateFilterCheckbox.addEventListener('change', (event) => {
            const existingDateFilter = document.getElementById('dynamic-date-filter-container');
            if (event.currentTarget.checked) {
                if (!existingDateFilter) {
                    const dateContainer = document.createElement('div');
                    dateContainer.id = 'dynamic-date-filter-container';
                    dateContainer.classList.add('date-filter-grid');

                    const startDateLabel = document.createElement('label');
                    startDateLabel.textContent = 'Start Date:';
                    const startDateInput = document.createElement('input');
                    startDateInput.type = 'date';
                    startDateInput.id = 'dynamic-date-from'; // Corrected ID to match Go button logic
                    startDateInput.classList.add('date-input-full');

                    const endDateLabel = document.createElement('label');
                    endDateLabel.textContent = 'End Date:';
                    const endDateInput = document.createElement('input');
                    endDateInput.type = 'date';
                    endDateInput.id = 'dynamic-date-to'; // Corrected ID to match Go button logic
                    endDateInput.classList.add('date-input-full');

                    dateContainer.appendChild(startDateLabel);
                    dateContainer.appendChild(startDateInput);
                    dateContainer.appendChild(endDateLabel);
                    dateContainer.appendChild(endDateInput);

                    dynamicFiltersContainer.appendChild(dateContainer);
                }
            } else {
                if (existingDateFilter) existingDateFilter.remove();
            }
        });

    //Region filter logic. Allows the user to filter the data by region.
    regionFilterCheckbox.addEventListener('change', (event) => {
        const existingRegionFilter = document.getElementById('region-filter-container');
        if (event.currentTarget.checked) {
            if (!existingRegionFilter) {
                const regionContainer = document.createElement('div');
                regionContainer.id = 'region-filter-container';
                regionContainer.classList.add('region-filter-box');

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
                regionValueContainer.classList.add('region-value-box');
                regionContainer.appendChild(regionValueContainer);

                dynamicFiltersContainer.appendChild(regionContainer);

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

    //Impact filter logic. Allows the user to filter the data by impact level.
    impactFilterCheckbox.addEventListener('change', (event) => {
        const existingImpactFilter = document.getElementById('impact-filter-container');
        if (event.currentTarget.checked) {
            if (!existingImpactFilter) {
                const impactContainer = document.createElement('div');
                impactContainer.id = 'impact-filter-container';
                impactContainer.classList.add('impact-filter-box');

                const impactLabel = document.createElement('label');
                impactLabel.textContent = 'Impact Level: ';
                const impactSelect = document.createElement('select');
                impactSelect.id = 'impactSelect';
                const impacts = ['Any', 'High', 'Medium', 'Low'];
                impacts.forEach(level => {
                    const option = document.createElement('option');
                    option.value = (level === 'Any') ? '' : level;
                    option.textContent = level;
                    impactSelect.appendChild(option);
                });
                
                impactContainer.appendChild(impactLabel);
                impactContainer.appendChild(impactSelect);
                dynamicFiltersContainer.appendChild(impactContainer);
            }
        } else {
            if (existingImpactFilter) existingImpactFilter.remove();
        }
    });
    
});