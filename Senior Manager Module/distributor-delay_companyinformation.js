document.addEventListener("DOMContentLoaded", () => {
    const dynamicFiltersContainer = document.getElementById('dynamic-filters-container');
    const companyFilterCheckbox = document.getElementById('companyFilter');
    const dateFilterCheckbox = document.getElementById('dateFilter');
    const regionFilterCheckbox = document.getElementById('regionFilter');
    const goButton = document.getElementById('go-button');
    const clearButton = document.getElementById('clear-filters');
    const placeholder = document.getElementById('placeholder-message');
    const resultsContainer = document.querySelector('.data-display-container');
    let continents, countries, cities;
    let currentData = [];

    // Helper to create limit dropdown where the user can select how many distributors they want to display in the graphs at one time
    function renderLimitDropdown() {
        if (document.getElementById('chart-limit-container')) return;

        const chartCon = document.querySelector('.chart_con');
        if (!chartCon) return;

        const container = document.createElement('div');
        container.id = 'chart-limit-container';
        container.classList.add('chart-limit-container');

        const label = document.createElement('label');
        label.innerText = 'Show: ';
        label.classList.add('chart-limit-label');

        const select = document.createElement('select');
        select.id = 'chart-limit-select';
        select.classList.add('chart-limit-select');
        
        [5, 10, 15, 20, 25, 50, 'All'].forEach(num => {
            const opt = document.createElement('option');
            opt.value = num === 'All' ? 'all' : num;
            opt.innerText = num;
            if (num === 10) opt.selected = true;
            select.appendChild(opt);
        });

        select.addEventListener('change', () => {
            if (currentData && currentData.length > 0) {
                let limit = parseInt(select.value);
                if (select.value === 'all') {
                    limit = currentData.length;
                }
                renderChart(currentData, limit);
            }
        });

        container.appendChild(label);
        container.appendChild(select);
        chartCon.appendChild(container);
    }

    // Toggle filter button logic. Allows the user to show and hide the filter bar.
    const filterToggle = document.getElementById('filterToggle');
    const contentDiv = document.querySelector('.content');
    if(filterToggle && contentDiv) {
        filterToggle.addEventListener('click', () => {
            contentDiv.classList.toggle('filters-hidden');
            if (contentDiv.classList.contains('filters-hidden')) {
                filterToggle.innerHTML = '&#9654;'; // Arrow Points Right
                filterToggle.title = "Show Filters";
                filterToggle.style.left = "0";
            } else {
                filterToggle.innerHTML = '&#9664;'; // Arrow Points Left
                filterToggle.title = "Hide Filters";
                filterToggle.style.left = "0";
            }
            window.dispatchEvent(new Event('resize'));
        });
    }

    // Clear the filters button logic
    if(clearButton) {
        clearButton.addEventListener('click', () => {
            if(companyFilterCheckbox) { companyFilterCheckbox.checked = true; companyFilterCheckbox.dispatchEvent(new Event('change')); } // This one is checked by default
            if(dateFilterCheckbox) { dateFilterCheckbox.checked = false; dateFilterCheckbox.dispatchEvent(new Event('change')); }
            if(regionFilterCheckbox) { regionFilterCheckbox.checked = false; regionFilterCheckbox.dispatchEvent(new Event('change')); }

            // Reset results
            if (placeholder) placeholder.classList.remove('hidden');
            if (resultsContainer) resultsContainer.classList.add('hidden');
            
            // Clear the stored data
            currentData = [];
            
            // Remove the limit dropdown
            const limitContainer = document.getElementById('chart-limit-container');
            if (limitContainer) limitContainer.remove();

            const tableContainer = document.getElementById('table-container');
            if (tableContainer) tableContainer.innerHTML = '';
            
            const canvas = document.getElementById('delayChart');
            if (canvas) {
                const chartInstance = Chart.getChart(canvas);
                if (chartInstance) {
                    chartInstance.destroy();
                }
            }
            const graphContainer = document.getElementById('graph-container');
            if (graphContainer) graphContainer.innerHTML = '<canvas id="delayChart"></canvas>';
        });
    }

    // Fetch region options that allows the user to select the region they want to analyze
    Promise.all([
        fetch("get_filters.php?type=continents").then(r => r.json()).catch(e => []),
        fetch("get_filters.php?type=countries").then(r => r.json()).catch(e => []),
        fetch("get_filters.php?type=cities").then(r => r.json()).catch(e => [])
    ]).then(values => {
        [continents, countries, cities] = values;
    });

    // Distributor filter

    function renderCompanyFilter() {
        const existingCompanyFilter = document.getElementById('company-filter-container');
        if (companyFilterCheckbox.checked) {
            if (!existingCompanyFilter) {
                const companyContainer = document.createElement('div');
                companyContainer.id = 'company-filter-container';
                companyContainer.classList.add('company-filter-box');

                const companyLabel = document.createElement('label');
                companyLabel.textContent = 'Distributors: ';
                companyLabel.classList.add('label-margin-right');
                
                const multiSelectContainer = document.createElement('div');
                multiSelectContainer.classList.add('multi-select-container');

                const selectedCompaniesDiv = document.createElement('div');
                selectedCompaniesDiv.id = 'selected-companies';
                selectedCompaniesDiv.classList.add('selected-companies-box');
                
                const searchInput = document.createElement('input');
                searchInput.type = 'text';
                searchInput.id = 'company-multi-search';
                searchInput.placeholder = 'Search...';
                
                const suggestionsUl = document.createElement('ul');
                suggestionsUl.id = 'company-suggestions';
                suggestionsUl.classList.add('suggestions-list');

                selectedCompaniesDiv.appendChild(searchInput);
                multiSelectContainer.appendChild(selectedCompaniesDiv);
                multiSelectContainer.appendChild(suggestionsUl);
                
                companyContainer.appendChild(companyLabel);
                companyContainer.appendChild(multiSelectContainer);
                dynamicFiltersContainer.appendChild(companyContainer);

                // Search input logic that allows the user to search through the available distributors
                searchInput.addEventListener('input', () => {
                    const value = searchInput.value.trim();
                    suggestionsUl.innerHTML = '';
                    if (value === '') return;
                    // Fetches the list of distributors from the database via get distributors php
                    fetch(`get_distributors.php?q=${encodeURIComponent(value)}`)
                        .then(response => response.json())
                        .then(data => {
                            data.forEach(company => {
                                const li = document.createElement('li');
                                li.textContent = company.CompanyName;
                                li.classList.add('suggestion-item');
                                
                                li.addEventListener('click', () => {
                                    const existing = Array.from(selectedCompaniesDiv.querySelectorAll('.company-tag')).find(tag => tag.textContent.slice(0, -1).trim() === company.CompanyName);
                                    if (!existing) {
                                        const companyTag = document.createElement('span');
                                        companyTag.className = 'company-tag';
                                        companyTag.classList.add('company-tag-style');
                                        companyTag.textContent = company.CompanyName + ' ';

                                        const removeBtn = document.createElement('button');
                                        removeBtn.textContent = 'x';
                                        removeBtn.classList.add('remove-tag-btn');

                                        removeBtn.addEventListener('click', () => {
                                            companyTag.remove();
                                        });

                                        companyTag.appendChild(removeBtn);
                                        selectedCompaniesDiv.insertBefore(companyTag, searchInput);
                                    }
                                    searchInput.value = '';
                                    suggestionsUl.innerHTML = '';
                                });
                                suggestionsUl.appendChild(li);
                            });
                        });
                });

                // Hide suggestions if user clicks outside
                document.addEventListener("click", (e) => {
                    if (!multiSelectContainer.contains(e.target)) {
                        suggestionsUl.innerHTML = "";
                    }
                });
            }
        } else {
            if (existingCompanyFilter) existingCompanyFilter.remove();
        }
    }

    // Initialize the Company Filter
    renderCompanyFilter();
    companyFilterCheckbox.addEventListener('change', renderCompanyFilter);

    // Date filter
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
                startDateInput.id = 'startDate';
                startDateInput.classList.add('date-input-full');

                const endDateLabel = document.createElement('label');
                endDateLabel.textContent = 'End Date:';
                const endDateInput = document.createElement('input');
                endDateInput.type = 'date';
                endDateInput.id = 'endDate';
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

    // Region filter that allows the user to select the region they want to analyze
    regionFilterCheckbox.addEventListener('change', (event) => {
        let regionContainer = document.getElementById('region-filter-container');
        if (event.currentTarget.checked) {
            if (!regionContainer) {
                regionContainer = document.createElement('div');
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
            if (regionContainer) regionContainer.remove();
        }
    });

    // Go Button Logic
    goButton.addEventListener('click', () => {
        contentDiv.classList.add('filters-hidden');
        filterToggle.style.left = "0";
        // Collect filter values
        let companies = [];
        const selectedCompaniesDiv = document.getElementById('selected-companies');
        if (selectedCompaniesDiv) {
            companies = Array.from(selectedCompaniesDiv.querySelectorAll('.company-tag')).map(tag => tag.textContent.slice(0, -1).trim());
        }

        const startDateInput = document.getElementById('startDate');
        const endDateInput = document.getElementById('endDate');
        const startDate = startDateInput ? startDateInput.value : '';
        const endDate = endDateInput ? endDateInput.value : '';

        // Date validation logic that ensures the user can't enter invalid date ranges
        if (dateFilterCheckbox.checked) {
            if ((startDate && !endDate) || (!startDate && endDate)) {
                alert("Please select both a Start Date and an End Date.");
                return; // Stop execution
            }
            if (startDate && endDate && startDate > endDate) {
                alert("Start Date cannot be after End Date.");
                return; // Stop execution
            }
        }

        // Create URL parameters for the request
        const params = new URLSearchParams();
        if (companies.length > 0) params.append('companies', companies.join('|'));
        if (startDate) params.append('start_date', startDate);
        if (endDate) params.append('end_date', endDate);

        const regionTypeSelect = document.getElementById('dynamic-region-type-select');
        const regionValueSelect = document.querySelector('#region-value-container select');
        if (regionTypeSelect && regionTypeSelect.value !== 'Select...') {
             params.append('region_type', regionTypeSelect.value);
             if (regionValueSelect) params.append('region', regionValueSelect.value);
        }

        // Fetch the data from the database via get_distributor_delay.php
        fetch(`get_distributor_delay.php?${params.toString()}`)
            .then(response => {
                if (!response.ok) throw new Error("Network response was not ok");
                return response.json();
            })
            .then(data => {
                if (data.error) {
                    alert(data.error);
                    return;
                }
                if (placeholder) placeholder.classList.add('hidden');
                if (resultsContainer) resultsContainer.classList.remove('hidden');
                
                currentData = data; // Store data
                renderTable(data);
                
                renderLimitDropdown(); // Ensure dropdown is present
                const limitSelect = document.getElementById('chart-limit-select');
                let limit = 10;
                if (limitSelect) {
                    if (limitSelect.value === 'all') {
                        limit = data.length;
                    } else {
                        limit = parseInt(limitSelect.value);
                    }
                }
                
                renderChart(data, limit);
            })
            .catch(error => {
                console.error("Error fetching data:", error);
                const tableContainer = document.getElementById('table-container');
                if(tableContainer) tableContainer.innerHTML = `<p style="color:red">Error loading data: ${error.message}</p>`;
            });
    });

    // Render the table and display the data
    function renderTable(data) {
        const tableContainer = document.getElementById('table-container');
        if (!data || data.length === 0) {
            tableContainer.innerHTML = '<p>No distributors found for selected filters.</p>';
            return;
        }

        const table = document.createElement('table');
        table.classList.add('result-table');
        
        const thead = document.createElement('thead');
        thead.innerHTML = `
            <tr>
                <th class="result-th">Company Name</th>
                <th class="result-th">Average Delay (Days)</th>
                <th class="result-th">Ranking</th>
            </tr>
        `;
        table.appendChild(thead);

        const tbody = document.createElement('tbody');
        data.forEach((row, index) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="result-td">${row.CompanyName}</td>
                <td class="result-td">${row.avg_delay}</td>
                <td class="result-td">${index + 1}</td>
            `;
            tbody.appendChild(tr);
        });
        table.appendChild(tbody);
        
        tableContainer.innerHTML = '';
        tableContainer.appendChild(table);
    }

    let delayChart = null;

    // Expand/Enlarge functionality
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
            
             // Force reset height styles that might linger
            if (container.querySelector('canvas')) {
                 container.style.height = ''; // Remove any inline height
                 const graphContainer = container.querySelector('#graph-container');
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

    // Render the chart and display the data
    function renderChart(data, limit = 10) {
        const canvas = document.getElementById('delayChart');
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        
        if (delayChart) delayChart.destroy();

        // Take top 'limit' for chart clarity
        const chartData = data.slice(0, limit);

        delayChart = new Chart(ctx, {
            type: 'bar', //Bar chart
            data: {
                labels: chartData.map(d => d.CompanyName),
                datasets: [{
                    label: 'Average Delay (Days)',
                    data: chartData.map(d => d.avg_delay),
                    backgroundColor: 'rgba(255, 99, 132, 0.6)',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: { display: true, text: 'Delay (Days)' }
                    }
                },
                plugins: {
                    legend: { display: false },
                    title: { display: true, text: 'Average Distributor Delay' }
                }
            }
        });
    }
});