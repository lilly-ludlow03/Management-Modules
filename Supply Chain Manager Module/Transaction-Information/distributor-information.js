/* Expand/ or Enlarge Functionality */
    function toggleExpand(btn) {
        // Handle both possible structures
        const container = btn.parentElement;
        container.classList.toggle('expanded-view');
        
        if (container.classList.contains('expanded-view')) {
            btn.innerHTML = '&#x2715;'; // Close/X icon
            btn.title = "Close Expanded View";
            document.body.style.overflow = 'hidden'; // Prevent background scroll
        } else {
            btn.innerHTML = '&#x2922;'; // Enlarge the icon
            btn.title = "Enlarge";
            document.body.style.overflow = ''; // Restores the scroll
        }
        
        // If it's the chart container trigger resize
        const canvas = container.querySelector('canvas');
        if (canvas) {
             
             setTimeout(() => {
                 const chartInstance = Chart.getChart(canvas);
                 if (chartInstance) {
                     chartInstance.resize();
                     chartInstance.update();
                 } else {
                     window.dispatchEvent(new Event('resize'));
                 }
             }, 300); // Wait for the transition
        }
    }

    document.addEventListener("DOMContentLoaded", () => {
        const companyFilterCheckbox = document.getElementById('companyFilter');
        const dateFilterCheckbox = document.getElementById('dateFilter');
        const regionFilterCheckbox = document.getElementById('regionFilter');
        
        const dynamicFiltersContainer = document.getElementById('dynamic-filters-container');
        const dynamicDividers = document.getElementById('dynamic-dividers');
        const goButton = document.getElementById('go-button-filters');
        const resultsContainer = document.getElementById('results-container');
        const clearFiltersBtn = document.getElementById('clear-filters');

        // Clears the Filters Logic
        if (clearFiltersBtn) {
            clearFiltersBtn.addEventListener('click', () => {
                // Uncheck the main toggles
                companyFilterCheckbox.checked = false;
                dateFilterCheckbox.checked = false;
                regionFilterCheckbox.checked = false;

                
                companyFilterCheckbox.dispatchEvent(new Event('change'));
                dateFilterCheckbox.dispatchEvent(new Event('change'));
                regionFilterCheckbox.dispatchEvent(new Event('change'));

                // Clear the results
                resultsContainer.classList.add('hidden');
                resultsContainer.style.display = ''; // Clear inline style if any

                const placeholder = document.getElementById('placeholder-message');
                placeholder.classList.remove('hidden');
                placeholder.style.display = ''; // Clear inline style if any
            });
        }

        // Toggle Filters Logic
        const filterToggle = document.getElementById('filterToggle');
        const contentDiv = document.querySelector('.content');
        
        if(filterToggle) {
            filterToggle.addEventListener('click', (e) => {
                e.stopPropagation(); 
                contentDiv.classList.toggle('filters-hidden');
                // Update the icon
                if (contentDiv.classList.contains('filters-hidden')) {
                    filterToggle.innerHTML = '&#9654;'; 
                    filterToggle.title = "Show Filters";
                } else {
                    filterToggle.innerHTML = '&#9664;'; 
                    filterToggle.title = "Hide Filters";
                }
                // Trigger chart resize
                window.dispatchEvent(new Event('resize'));
            });
        }
        // Region Options
        let continents, countries, cities;

        // Fetch Region Data
        Promise.all([
            fetch("../Disruption-Events/get_filters.php?type=continents").then(r => r.json()).catch(e => []),
            fetch("../Disruption-Events/get_filters.php?type=countries").then(r => r.json()).catch(e => []),
            fetch("../Disruption-Events/get_filters.php?type=cities").then(r => r.json()).catch(e => [])
        ]).then(values => {
            [continents, countries, cities] = values;
        });

        // Update the Dividers
        function updateDividers() {
            if (dynamicFiltersContainer.hasChildNodes()) {
                dynamicDividers.innerHTML = `
                    <hr class="divider-major">
                    <hr class="divider-minor">
                `;
            } else {
                dynamicDividers.innerHTML = '';
            }
        }
        const observer = new MutationObserver(updateDividers);
        observer.observe(dynamicFiltersContainer, { childList: true });

        // Filter Event Listeners 

        companyFilterCheckbox.addEventListener('change', (event) => {
            const existingFilter = document.getElementById('company-filter-container');
            if (event.currentTarget.checked) {
                if (!existingFilter) {
                    const container = document.createElement('div');
                    container.id = 'company-filter-container';
                    container.className = 'filter-container-flex';
                    
                    const label = document.createElement('label');
                    label.textContent = 'Company: ';
                    
                    // Role Select 
                    const roleSelect = document.createElement('select');
                    roleSelect.id = 'companyRoleSelect';
                    roleSelect.innerHTML = `
                        <option value="Both">Both</option>
                        <option value="Sending">Sending Company</option>
                        <option value="Receiving">Receiving Company</option>
                    `;

                    // Autocomplete Wrapper
                    const wrapper = document.createElement('div');
                    wrapper.className = 'autocomplete-wrapper';

                    const searchInput = document.createElement('input');
                    searchInput.type = 'text';
                    searchInput.id = 'company-autocomplete-input';
                    searchInput.placeholder = 'Search Company...';
                    searchInput.className = 'full-width';
                    searchInput.autocomplete = 'off';

                    const suggestionsUl = document.createElement('ul');
                    suggestionsUl.id = 'company-suggestions';
                    suggestionsUl.className = 'suggestions-list';

                    wrapper.appendChild(searchInput);
                    wrapper.appendChild(suggestionsUl);
                    
                    container.appendChild(label);
                    container.appendChild(roleSelect);
                    container.appendChild(wrapper);
                    dynamicFiltersContainer.appendChild(container);

                    searchInput.addEventListener('input', () => {
                        const value = searchInput.value.trim();
                        suggestionsUl.innerHTML = '';
                        if (value === '') {
                            suggestionsUl.style.display = 'none';
                            return;
                        }
                        
                        // Use existing backend or new ending point
                        fetch(`../Disruption-Events/get_companies.php?q=${encodeURIComponent(value)}`)
                            .then(r => r.json())
                            .then(data => {
                                if (data.length > 0) {
                                    suggestionsUl.style.display = 'block';
                                    data.forEach(company => {
                                        const li = document.createElement('li');
                                        li.textContent = company.CompanyName;
                                        li.className = 'suggestion-item';
                                        
                                        li.onclick = () => {
                                            searchInput.value = company.CompanyName;
                                            suggestionsUl.innerHTML = '';
                                            suggestionsUl.style.display = 'none';
                                        };
                                        // Hover handled by CSS
                                        suggestionsUl.appendChild(li);
                                    });
                                } else {
                                    suggestionsUl.style.display = 'none';
                                }
                            })
                            .catch(e => console.error(e));
                    });

                    document.addEventListener("click", (e) => {
                        if (!wrapper.contains(e.target)) {
                            suggestionsUl.style.display = 'none';
                        }
                    });
                }
            } else if (existingFilter) existingFilter.remove();
        });

        // Date Filter
        dateFilterCheckbox.addEventListener('change', (event) => {
            const existingFilter = document.getElementById('date-filter-container');
            if (event.currentTarget.checked) {
                if (!existingFilter) {
                    const container = document.createElement('div');
                    container.id = 'date-filter-container';
                    container.className = 'date-filter-grid';
                    
                    const fromLabel = document.createElement('label');
                    fromLabel.textContent = 'From: ';
                    const fromInput = document.createElement('input');
                    fromInput.type = 'date';
                    fromInput.id = 'dateFrom';
                    fromInput.className = 'margin-right-10';

                    const toLabel = document.createElement('label');
                    toLabel.textContent = 'To: ';
                    const toInput = document.createElement('input');
                    toInput.type = 'date';
                    toInput.id = 'dateTo';
                    
                    container.appendChild(fromLabel);
                    container.appendChild(fromInput);
                    container.appendChild(toLabel);
                    container.appendChild(toInput);
                    dynamicFiltersContainer.appendChild(container);
                }
            } else if (existingFilter) existingFilter.remove();
        });

        // Region filter
        regionFilterCheckbox.addEventListener('change', (event) => {
            const existingFilter = document.getElementById('region-filter-container');
            if (event.currentTarget.checked) {
                if (!existingFilter) {
                    const container = document.createElement('div');
                    container.id = 'region-filter-container';
                    container.className = 'region-filter-container';
                    
                    const label = document.createElement('label');
                    label.textContent = 'Region:';
                    
                    const typeSelect = document.createElement('select');
                    typeSelect.id = 'regionTypeSelect';
                    typeSelect.innerHTML = '<option value="">Select Region Type...</option><option value="Continent">Continent</option><option value="Country">Country</option><option value="City">City</option>';
                    
                    const valueContainer = document.createElement('div');
                    valueContainer.id = 'regionValueContainer';
                    valueContainer.className = 'inline-block';

                    container.appendChild(label);
                    container.appendChild(typeSelect);
                    container.appendChild(valueContainer);
                    dynamicFiltersContainer.appendChild(container);

                    typeSelect.addEventListener('change', () => {
                        valueContainer.innerHTML = '';
                        let options = [];
                        if (typeSelect.value === 'Continent') options = continents;
                        else if (typeSelect.value === 'Country') options = countries;
                        else if (typeSelect.value === 'City') options = cities;

                        if (options && options.length > 0) {
                            
                            const valSelect = document.createElement('select');
                            valSelect.id = 'regionValueSelect';
                            
                            const defaultOpt = document.createElement('option');
                            defaultOpt.textContent = `Select ${typeSelect.value}...`;
                            defaultOpt.value = '';
                            valSelect.appendChild(defaultOpt);

                            options.forEach(opt => {
                                const o = document.createElement('option');
                                o.value = opt;
                                o.textContent = opt;
                                valSelect.appendChild(o);
                            });
                            
                            valueContainer.appendChild(valSelect);
                        }
                    });
                }
            } else if (existingFilter) existingFilter.remove();
        });

        let fetchedData = null; // Store fetched data globally

        // Go Button Logic
        goButton.addEventListener('click', () => {
            contentDiv.classList.add('filters-hidden');
            filterToggle.style.left = "0";

            const resultsContainer = document.getElementById('results-container');
            const placeholder = document.getElementById('placeholder-message');
            
            // Gather Params
            const params = new URLSearchParams();
            
            // Company Filter
            const compRole = document.getElementById('companyRoleSelect');
            const compName = document.getElementById('company-autocomplete-input');
            if (compRole && compName && compName.value.trim()) {
                params.append('company_role', compRole.value);
                params.append('company_name', compName.value.trim());
            }

            // Date Filter
            const dFrom = document.getElementById('dateFrom');
            const dTo = document.getElementById('dateTo');
            if (dFrom && dFrom.value) params.append('date_from', dFrom.value);
            if (dTo && dTo.value) params.append('date_to', dTo.value);

            // Validate the Dates
            if ((dFrom && dFrom.value && (!dTo || !dTo.value)) || ((!dFrom || !dFrom.value) && dTo && dTo.value)) {
                 alert("Please select both a start date and an end date.");
                 return;
            }
            if (dFrom && dFrom.value && dTo && dTo.value) {
                const from = new Date(dFrom.value);
                const to = new Date(dTo.value);
                if (from > to) {
                    alert("Start date cannot be after end date");
                    return;
                }
            }

            // Region Filter
            const rType = document.getElementById('regionTypeSelect');
            const rVal = document.getElementById('regionValueSelect');
            if (rType && rType.value && rVal && rVal.value) {
                params.append('region_type', rType.value);
                params.append('region', rVal.value);
            }

            // Fetch
            fetch(`get_distributor_info.php?${params.toString()}`)
                .then(r => r.json())
                .then(data => {
                    if (data.error) throw new Error(data.error);
                    
                    resultsContainer.classList.remove('hidden');
                    resultsContainer.style.display = '';

                    placeholder.classList.add('hidden');
                    placeholder.style.display = '';

                    fetchedData = data; // Store data
                    renderTransactions(data.table);
                    updateGraphs(); // Call update logic
                })
                .catch(e => {
                    console.error(e);
                    alert("Error loading data: " + e.message);
                });
        });

        // Dropdown Event Listener
        document.getElementById('subsetSelect').addEventListener('change', updateGraphs);

        function updateGraphs() {
            if (!fetchedData) return;
            
            const subset = document.getElementById('subsetSelect').value;
            const filteredData = filterDataBySubset(fetchedData, subset);
            renderGraphs(filteredData);
        }

        function filterDataBySubset(data, subset) {
            if (subset === 'all') return data;

            // Sort Companies by Delivery Rate by default
            const sorted = [...data.table].sort((a, b) => b.RawDeliveryRate - a.RawDeliveryRate);
            const total = sorted.length;

            let selectedCompanies = [];
            if (subset === 'top_5') {
                selectedCompanies = sorted.slice(0, 5).map(c => c.CompanyName);
            } else if (subset === 'top_10') {
                selectedCompanies = sorted.slice(0, 10).map(c => c.CompanyName);
            } else if (subset === 'middle_10') {
                const start = Math.max(0, Math.floor(total / 2) - 5);
                selectedCompanies = sorted.slice(start, start + 10).map(c => c.CompanyName);
            } else if (subset === 'bottom_10') {
                selectedCompanies = sorted.slice(-10).map(c => c.CompanyName);
            }

            // Filter all datasets
            
            return {
                table: data.table.filter(d => selectedCompanies.includes(d.CompanyName)),
                graph_rate_exposure: data.graph_rate_exposure.filter(d => selectedCompanies.includes(d.CompanyName)),
                graph_avg_rate: data.graph_avg_rate.filter(d => selectedCompanies.includes(d.CompanyName)),
                graph_rate_time: data.graph_rate_time.filter(d => selectedCompanies.includes(d.CompanyName)),
                graph_exposure_time: data.graph_exposure_time.filter(d => selectedCompanies.includes(d.CompanyName))
            };
        }
        // Render the sumarry before the charts
        function renderTransactions(data) {
            const tbody = document.querySelector('#transactions-table tbody');
            tbody.innerHTML = '';
            if (!data || data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="3" class="text-center" style="padding:10px;">No distributors found</td></tr>';
                return;
            }
            data.forEach(row => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="table-td">${row.CompanyName}</td>
                    <td class="table-td">${row.DeliveryRate}%</td>
                    <td class="table-td">${row.DisruptionExposure}</td>
                `;
                tbody.appendChild(tr);
            });
        }

        // Keep Track of Chart
        let charts = {}; 

        // Main chart- rendering
        function renderGraphs(data) {
            const ctx1 = document.getElementById('rateVsExposureChart').getContext('2d');
            const ctx2 = document.getElementById('rateTimeChart').getContext('2d');
            const ctx3 = document.getElementById('exposureTimeChart').getContext('2d');
            const ctx4 = document.getElementById('avgRateChart').getContext('2d');

            // Destroy old charts to do new
            ['c1', 'c2', 'c3', 'c4'].forEach(k => {
                if (charts[k]) charts[k].destroy();
            });

            // Helper for color cycling
            const colors = ['#36a2eb', '#ff6384', '#4bc0c0', '#ffcd56', '#9966ff', '#ff9f40', '#c9cbcf'];
            const getColor = (i) => colors[i % colors.length];

            // 1) Delivery Rate vs Disruption Exposure 
            const companies1 = data.graph_rate_exposure.map(d => d.CompanyName);
            
            charts['c1'] = new Chart(ctx1, {
                type: 'line',
                data: {
                    labels: companies1,
                    datasets: [
                        {
                            label: 'Delivery Rate (%)',
                            data: data.graph_rate_exposure.map(d => d.RawDeliveryRate),
                            borderColor: '#36a2eb',
                            yAxisID: 'y',
                        },
                        {
                            label: 'Disruption Exposure',
                            data: data.graph_rate_exposure.map(d => d.DisruptionExposure),
                            borderColor: '#ff6384',
                            yAxisID: 'y1',
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: { title: { display: true, text: 'Delivery Rate vs Disruption Exposure' } },
                    scales: {
                        y: { type: 'linear', display: true, position: 'left', title: {display: true, text: 'Rate (%)'} },
                        y1: { type: 'linear', display: true, position: 'right', grid: { drawOnChartArea: false }, title: {display: true, text: 'Exposure Score'} },
                    }
                }
            });

            // 2) Company Delivery Rate Over Time 
            const dates2 = [...new Set(data.graph_rate_time.map(d => d.date))];
            const companies2 = [...new Set(data.graph_rate_time.map(d => d.CompanyName))];
            
            const datasets2 = companies2.map((comp, i) => ({
                label: comp,
                data: dates2.map(date => {
                    const entry = data.graph_rate_time.find(d => d.date === date && d.CompanyName === comp);
                    return entry ? entry.rate : null; 
                }),
                borderColor: getColor(i),
                fill: false,
                tension: 0.1
            }));

            charts['c2'] = new Chart(ctx2, {
                type: 'line',
                data: { labels: dates2, datasets: datasets2 },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { title: { display: true, text: 'Delivery Rate Over Time' }, legend: { display: false } }
                }
            });

            // 3) Disruption Exposure Over Time 
            const dates3 = [...new Set(data.graph_exposure_time.map(d => d.date))];
            const companies3 = [...new Set(data.graph_exposure_time.map(d => d.CompanyName))];
            
            const datasets3 = companies3.map((comp, i) => ({
                label: comp,
                data: dates3.map(date => {
                    const entry = data.graph_exposure_time.find(d => d.date === date && d.CompanyName === comp);
                    return entry ? Number(entry.exposure) : 0;
                }),
                borderColor: getColor(i),
                fill: false,
                tension: 0.1
            }));

            charts['c3'] = new Chart(ctx3, {
                type: 'line',
                data: { labels: dates3, datasets: datasets3 },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { title: { display: true, text: 'Disruption Exposure Over Time' }, legend: { display: false } }
                }
            });

            // 4) Average Rate Bar Graph
            // Simple Bar Chart
            charts['c4'] = new Chart(ctx4, {
                type: 'bar',
                data: {
                    labels: data.graph_avg_rate.map(d => d.CompanyName),
                    datasets: [{
                        label: 'Average Delivery Rate (%)',
                        data: data.graph_avg_rate.map(d => d.RawDeliveryRate),
                        backgroundColor: '#4bc0c0'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { title: { display: true, text: 'Average Delivery Rate' }, legend: { display: false } }
                }
            });
        }
    });