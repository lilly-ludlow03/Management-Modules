document.addEventListener("DOMContentLoaded", () => {
    const companyFilterCheckbox = document.getElementById('companyFilter');
    const dateFilterCheckbox = document.getElementById('dateFilter');
    const regionFilterCheckbox = document.getElementById('regionFilter');
    const dynamicFiltersContainer = document.getElementById('dynamic-filters-container');
    const goButton = document.getElementById('go-button');
    const clearButton = document.getElementById('clear-filters');
    const tableContainer = document.getElementById('table-container');
    const placeholder = document.getElementById('placeholder-message');
    const resultsContainer = document.querySelector('.data-display-container');

    let continents, countries, cities;
    let currentData = []; // Store fetched data for limit changing

    // Toggle filter button logic. Allows the user to show and hide the filter bar.
    const filterToggle = document.getElementById('filterToggle');
    const contentDiv = document.querySelector('.content');
    const plotDiv = document.querySelector('.plot');
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
                // plotDiv.style.flex = "1"; // Handled by CSS
            }
            window.dispatchEvent(new Event('resize'));
        });
    }

    // Expand/Enlarge functionality. Allows the user to expand the chart and table to the full width of the page.
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
            
            // Reset styles that might have been altered
            container.style.height = ''; 
            container.style.width = '';
        }
        
        // Trigger Resize for Charts
        // Use requestAnimationFrame to wait for the layout to settle.
        requestAnimationFrame(() => {
        const canvas = container.querySelector('canvas');
        if (canvas) {
            const chartInstance = Chart.getChart(canvas);
            
            // Set height on the canvas element, not the chart instance
            if (!container.classList.contains('expanded-view')) {
                canvas.style.height = '60vh';
            }
            
            if (chartInstance) {
                chartInstance.resize();
            } else {
                window.dispatchEvent(new Event('resize'));
            }
        }
    });
    };

    // Fetch data for the region filter. Allows the user to select the region they want to analyze.
    Promise.all([
        fetch("get_filters.php?type=continents").then(r => r.json()).catch(e => []),
        fetch("get_filters.php?type=countries").then(r => r.json()).catch(e => []),
        fetch("get_filters.php?type=cities").then(r => r.json()).catch(e => [])
    ]).then(values => {
        [continents, countries, cities] = values;
    }).catch(error => console.error("Error fetching filter data:", error));

    // Helper to create Limit Dropdown. Allows the user to select the number of results to display.
    function renderLimitDropdown() {
        if (document.getElementById('chart-limit-container')) return;

        const chartCon = document.querySelector('.chart_con');
        if (!chartCon) return;

        const container = document.createElement('div');
        container.id = 'chart-limit-container';
        container.style.position = 'absolute';
        container.style.top = '5px';
        container.style.right = '5px';
        container.style.zIndex = '25';
        container.style.backgroundColor = 'rgba(255, 255, 255, 0.8)';
        container.style.padding = '2px 5px';
        container.style.borderRadius = '4px';

        const label = document.createElement('label');
        label.innerText = 'Show: ';
        label.style.fontSize = '0.8em';
        label.style.marginRight = '5px';

        const select = document.createElement('select');
        select.id = 'chart-limit-select';
        select.style.fontSize = '0.8em';
        
        [5, 10, 15, 20, 25, 50, 'All'].forEach(num => {
            const opt = document.createElement('option');
            opt.value = num === 'All' ? 'all' : num;
            opt.innerText = num;
            if (num === 10) opt.selected = true; // Default
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

    // Clear filters button logic. Allows the user to clear all filters and reset the page.
    if(clearButton) {
        clearButton.addEventListener('click', () => {
            if(companyFilterCheckbox) { companyFilterCheckbox.checked = false; companyFilterCheckbox.dispatchEvent(new Event('change')); }
            if(dateFilterCheckbox) { dateFilterCheckbox.checked = false; dateFilterCheckbox.dispatchEvent(new Event('change')); }
            if(regionFilterCheckbox) { regionFilterCheckbox.checked = false; regionFilterCheckbox.dispatchEvent(new Event('change')); }

            // Reset results
            if (placeholder) placeholder.classList.remove('hidden');
            if (resultsContainer) resultsContainer.classList.add('hidden');
            
            // Clear stored data
            currentData = [];
            
            const limitContainer = document.getElementById('chart-limit-container');
            if (limitContainer) limitContainer.remove();

            const tableContainer = document.getElementById('table-container');
            if (tableContainer) tableContainer.innerHTML = '';
            
            const canvas = document.getElementById('regionChart');
            if (canvas) {
                const chartInstance = Chart.getChart(canvas);
                if (chartInstance) {
                    chartInstance.destroy();
                }
            }
            const graphContainer = document.getElementById('graph-container');
            if (graphContainer) graphContainer.innerHTML = '<canvas id="regionChart"></canvas>';
        });
    }

    goButton.addEventListener('click', () => {
        contentDiv.classList.add('filters-hidden');
        // filterToggle.style.left = "0"; // Handled by CSS
        // Collect filter values
        let companies = [];
        const selectedCompaniesDiv = document.getElementById('selected-companies');
        if (selectedCompaniesDiv) {
            Array.from(selectedCompaniesDiv.querySelectorAll('.company-tag')).forEach(tag => {
                companies.push(tag.textContent.slice(0, -1).trim());
            });
        }

        let year = '';
        const yearSelect = document.getElementById('yearSelect');
        if (yearSelect) {
            year = yearSelect.value;
        }

        let quarter = '';
        const quarterSelect = document.getElementById('quarterSelect');
        if (quarterSelect) {
            quarter = quarterSelect.value;
        }

        let regionType = '';
        let regionValue = '';
        const regionTypeSelect = document.getElementById('dynamic-region-type-select');
        if (regionTypeSelect) {
            regionType = regionTypeSelect.value;
            const regionValueSelect = document.querySelector('#region-value-container select');
            if (regionValueSelect) {
                regionValue = regionValueSelect.value;
            }
        }

        // Build query string
        const params = new URLSearchParams();
        if (companies.length > 0) params.append('companies', companies.join('|'));
        if (year) params.append('year', year);
        if (quarter) params.append('quarter', quarter);
        if (regionType && regionValue) {
            params.append('region_type', regionType);
            params.append('region', regionValue);
        }

        // Fetch data from the database via get_financials_by_region.php
        fetch(`get_financials_by_region.php?${params.toString()}`)
            .then(response => {
                if (!response.ok) {
                    return response.text().then(text => {
                        throw new Error(text || `Server responded with status: ${response.status}`);
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.error) {
                    throw new Error(data.error);
                }
                if (placeholder) placeholder.classList.add('hidden');
                if (resultsContainer) resultsContainer.classList.remove('hidden');
                
                currentData = data.graph; // Store graph data
                
                renderTable(data.table);
                
                renderLimitDropdown(); 
                const limitSelect = document.getElementById('chart-limit-select');
                let limit = 10; // Default limit
                if (limitSelect) {
                    if (limitSelect.value === 'all') {
                        limit = currentData.length;
                    } else {
                        limit = parseInt(limitSelect.value);
                    }
                }

                renderChart(data.graph, limit);
                renderRegionTable(data.graph);
            })
            .catch(error => {
                console.error('Error fetching data:', error);
                tableContainer.innerHTML = `<p style="color:red;">Error loading data: ${error.message}</p>`;
            });
    });

    // Render the table and display the data
    function renderTable(data) {
        if (!data || data.length === 0) {
            tableContainer.innerHTML = '<p>No data found for the selected filters.</p>';
            return;
        }
        
        const table = document.createElement('table');
        table.classList.add('result-table');
        
        const thead = document.createElement('thead');
        thead.innerHTML = `
            <tr>
                <th class="result-th">Company Name</th>
                <th class="result-th">Financial Health</th>
                <th class="result-th">Ranking</th>
            </tr>
        `;
        table.appendChild(thead);

        const tbody = document.createElement('tbody');
        data.forEach((row, index) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="result-td">${row.CompanyName}</td>
                <td class="result-td">${row.health_score}</td>
                <td class="result-td">${index + 1}</td>
            `;
            tbody.appendChild(tr);
        });
        table.appendChild(tbody);
        
        tableContainer.innerHTML = '';
        tableContainer.appendChild(table);
    }

    // Render the region table and display the data
    function renderRegionTable(data) {
        const regionTableContainer = document.getElementById('region-table-container');
        if (!data || data.length === 0) {
            regionTableContainer.innerHTML = '<p>No region data found for the selected filters.</p>';
            return;
        }
        
        const table = document.createElement('table');
        table.classList.add('result-table');
        
        const thead = document.createElement('thead');
        thead.innerHTML = `
            <tr>
                <th class="result-th">Region</th>
                <th class="result-th">Avg. Financial Health</th>
                <th class="result-th">Ranking</th>
            </tr>
        `;
        table.appendChild(thead);

        const tbody = document.createElement('tbody');
        data.forEach((row, index) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="result-td">${row.location}</td>
                <td class="result-td">${row.avg_health}</td>
                <td class="result-td">${index + 1}</td>
            `;
            tbody.appendChild(tr);
        });
        table.appendChild(tbody);
        
        regionTableContainer.innerHTML = '';
        regionTableContainer.appendChild(table);
    }

    let regionChart = null;
    // Render the chart and display the data
    function renderChart(data, limit = 10) {
        const canvas = document.getElementById('regionChart');
        if (!canvas) return;
        const ctx = canvas.getContext('2d');

        if (regionChart) regionChart.destroy();

        if (!data || data.length === 0) return;
        
        // Apply limit
        const chartData = data.slice(0, limit);

        regionChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: chartData.map(d => d.location),
                datasets: [{
                    label: 'Average Financial Health',
                    data: chartData.map(d => d.avg_health),
                    backgroundColor: 'rgba(75, 192, 192, 0.6)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        title: { display: true, text: 'Health Score' }
                    },
                    x: {
                        title: { display: true, text: 'Location' }
                    }
                },
                plugins: {
                    legend: { display: false },
                    title: { display: true, text: 'Avg Financial Health by Location' }
                }
            }
        });
    }

    // Company filter checkbox logic. Allows the user to select the companies they want to analyze.
    companyFilterCheckbox.addEventListener('change', (event) => {
        const existingCompanyFilter = document.getElementById('company-filter-container');

        if (event.currentTarget.checked) {
            if (!existingCompanyFilter) {
                const companyContainer = document.createElement('div');
                companyContainer.id = 'company-filter-container';
                companyContainer.classList.add('company-filter-box');

                const companyLabel = document.createElement('label');
                companyLabel.textContent = 'Companies: ';
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

                // Add search logic. Allows the user to search through the available companies.
                searchInput.addEventListener('input', () => {
                    const value = searchInput.value.trim();
                    suggestionsUl.innerHTML = '';
                    if (value === '') return;

                    fetch(`get_companies.php?q=${encodeURIComponent(value)}`)
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
                                        companyTag.classList.add('company-tag');
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
            if (existingCompanyFilter) {
                existingCompanyFilter.remove();
            }
        }
    });

    // Date filter checkbox logic. Allows the user to select the date of financial health they want to analyze.
    dateFilterCheckbox.addEventListener('change', (event) => {
        const existingDateFilter = document.getElementById('dynamic-date-filter-container');

        if (event.currentTarget.checked) {
            if (!existingDateFilter) {
                const dateContainer = document.createElement('div');
                dateContainer.id = 'dynamic-date-filter-container';
                dateContainer.classList.add('date-filter-box');

                // Year Selector
                const yearLabel = document.createElement('label');
                yearLabel.textContent = 'Year: ';
                yearLabel.classList.add('label-margin-right');
                const yearSelect = document.createElement('select');
                yearSelect.id = 'yearSelect';
                const currentYear = new Date().getFullYear();
                for (let i = currentYear; i >= 2000; i--) {
                    const option = document.createElement('option');
                    option.value = i;
                    option.textContent = i;
                    yearSelect.appendChild(option);
                }

                // Quarter Selector
                const quarterLabel = document.createElement('label');
                quarterLabel.textContent = 'Quarter: ';
                quarterLabel.classList.add('label-margin-left', 'label-margin-right');
                const quarterSelect = document.createElement('select');
                quarterSelect.id = 'quarterSelect';
                const quarters = ['Q1', 'Q2', 'Q3', 'Q4'];
                quarters.forEach(q => {
                    const option = document.createElement('option');
                    option.value = q;
                    option.textContent = q;
                    quarterSelect.appendChild(option);
                });

                dateContainer.appendChild(yearLabel);
                dateContainer.appendChild(yearSelect);
                dateContainer.appendChild(quarterLabel);
                dateContainer.appendChild(quarterSelect);

                dynamicFiltersContainer.appendChild(dateContainer);
            }
        } else {
            if (existingDateFilter) {
                existingDateFilter.remove();
            }
        }
    });

    // Region filter checkbox logic. Allows the user to select the region they want to analyze.
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
});