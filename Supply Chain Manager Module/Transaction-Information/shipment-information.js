/* Expand/Enlarge Functionality (Global Scope) */
function toggleExpand(btn) {
    const container = btn.parentElement;
    container.classList.toggle('expanded-view');

    if (container.classList.contains('expanded-view')) {
        btn.innerHTML = '&#x2715;'; // Close/X icon
        btn.title = "Close Expanded View";
        document.body.style.overflow = 'hidden'; // Prevent background scroll
    } else {
        btn.innerHTML = '&#x2922;'; // Enlarge icon
        btn.title = "Enlarge";
        document.body.style.overflow = ''; // Restore scroll
    }

    // If it's the chart container, trigger resize
    const canvas = container.querySelector('canvas');
    if (canvas) {
        // Use Chart.js instance method if available for better resizing
        setTimeout(() => {
            const chartInstance = Chart.getChart(canvas);
            if (chartInstance) {
                chartInstance.resize();
                chartInstance.update();
            } else {
                window.dispatchEvent(new Event('resize'));
            }
        }, 300); // Wait for transition
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

    // Clear Filters Logic
    if (clearFiltersBtn) {
        clearFiltersBtn.addEventListener('click', () => {
            // Uncheck main toggles
            companyFilterCheckbox.checked = false;
            dateFilterCheckbox.checked = false;
            regionFilterCheckbox.checked = false;

            // Trigger change events to remove dynamic containers
            companyFilterCheckbox.dispatchEvent(new Event('change'));
            dateFilterCheckbox.dispatchEvent(new Event('change'));
            regionFilterCheckbox.dispatchEvent(new Event('change'));

            // Clear results
            resultsContainer.classList.add('hidden');
            resultsContainer.style.display = ''; 
            
            const placeholder = document.getElementById('placeholder-message');
            placeholder.classList.remove('hidden');
            placeholder.style.display = '';
        });
    }

    // Toggle Filters Logic
    const filterToggle = document.getElementById('filterToggle');
    const contentDiv = document.querySelector('.content');

    if (filterToggle) {
        filterToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            contentDiv.classList.toggle('filters-hidden');
            // Update Icon
            if (contentDiv.classList.contains('filters-hidden')) {
                filterToggle.innerHTML = '&#9654;'; // Point Right
                filterToggle.title = "Show Filters";
            } else {
                filterToggle.innerHTML = '&#9664;'; // Point Left
                filterToggle.title = "Hide Filters";
            }
            // Trigger chart resize if needed
            window.dispatchEvent(new Event('resize'));
        });
    }

    let continents, countries, cities;

    // Fetch Region Data
    Promise.all([
        fetch("../Disruption-Events/get_filters.php?type=continents").then(r => r.json()).catch(e => []),
        fetch("../Disruption-Events/get_filters.php?type=countries").then(r => r.json()).catch(e => []),
        fetch("../Disruption-Events/get_filters.php?type=cities").then(r => r.json()).catch(e => [])
    ]).then(values => {
        [continents, countries, cities] = values;
    });

    // Update Dividers
    function updateDividers() {
        if (dynamicFiltersContainer.hasChildNodes()) {
            dynamicDividers.innerHTML = `
                    <hr style="border: 1px solid #ccc; margin-top: 20px;">
                    <hr style="border: 1px solid #ccc; margin-top: 5px; margin-bottom: 20px;">
                `;
        } else {
            dynamicDividers.innerHTML = '';
        }
    }
    const observer = new MutationObserver(updateDividers);
    observer.observe(dynamicFiltersContainer, { childList: true });

    // --- Filter Event Listeners ---

    companyFilterCheckbox.addEventListener('change', (event) => {
        const existingFilter = document.getElementById('company-filter-container');
        if (event.currentTarget.checked) {
            if (!existingFilter) {
                const container = document.createElement('div');
                container.id = 'company-filter-container';
                container.className = 'filter-container-flex';

                const label = document.createElement('label');
                label.textContent = 'Company: ';

                // Role Select (Sending, Receiving, Both)
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

                    // Use existing backend or new endpoint if needed. get_companies.php works.
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
                // valueContainer.style.display = 'inline-block'; // Ensure it stays inline

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
                        // Label not needed if we want compact line, or can add small one
                        // Let's just add the select to keep it on one line

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
        // Validation (Optional: allow empty search for all data)
        // if (!dynamicFiltersContainer.hasChildNodes()) { ... }

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

        // Validate Dates
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
        fetch(`get_shipment_info.php?${params.toString()}`)
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

        // Calculate Total Volume per Company
        // We can use graph_vol_product as it groups by company and product volume
        const companyVolumes = {};
        data.graph_vol_product.forEach(d => {
            const vol = Number(d.total_volume) || 0;
            companyVolumes[d.CompanyName] = (companyVolumes[d.CompanyName] || 0) + vol;
        });

        // Sort Companies by Volume Descending
        const sortedCompanies = Object.keys(companyVolumes).sort((a, b) => companyVolumes[b] - companyVolumes[a]);
        const total = sortedCompanies.length;

        let selectedCompanies = [];
        if (subset === 'top_5') {
            selectedCompanies = sortedCompanies.slice(0, 5);
        } else if (subset === 'top_10') {
            selectedCompanies = sortedCompanies.slice(0, 10);
        } else if (subset === 'middle_10') {
            const start = Math.max(0, Math.floor(total / 2) - 5);
            selectedCompanies = sortedCompanies.slice(start, start + 10);
        } else if (subset === 'bottom_10') {
            selectedCompanies = sortedCompanies.slice(-10);
        }

        // Filter Datasets based on selected companies
        return {
            ...data,
            graph_vol_product: data.graph_vol_product.filter(d => selectedCompanies.includes(d.CompanyName)),
            graph_status_company: data.graph_status_company.filter(d => selectedCompanies.includes(d.CompanyName)),
            graph_company_trend: data.graph_company_trend.filter(d => selectedCompanies.includes(d.CompanyName)),
            // graph_status_trend: Not filtering this as it doesn't have company data, acts as global context
        };
    }

    function renderTransactions(data) {
        const tbody = document.querySelector('#transactions-table tbody');
        tbody.innerHTML = '';
        if (!data || data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:10px;">No transactions found</td></tr>';
            return;
        }
        data.forEach(row => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                    <td class="table-td">${row.leaving_company}</td>
                    <td class="table-td">${row.going_to_company}</td>
                    <td class="table-td text-right">${row.volume}</td>
                    <td class="table-td text-center">${row.status}</td>
                    <td class="table-td">${row.product}</td>
                `;
            tbody.appendChild(tr);
        });
    }

    let charts = {}; // Store chart instances

    function renderGraphs(data) {
        const ctxVolProd = document.getElementById('volProductChart').getContext('2d');
        const ctxStatComp = document.getElementById('statusCompanyChart').getContext('2d');
        const ctxStatTrend = document.getElementById('statusTrendChart').getContext('2d');
        const ctxCompTrend = document.getElementById('companyTrendChart').getContext('2d');

        // Destroy old charts
        ['volProd', 'statComp', 'statTrend', 'compTrend'].forEach(k => {
            if (charts[k]) charts[k].destroy();
        });

        // Helper for dynamic colors
        const colors = ['#36a2eb', '#ff6384', '#4bc0c0', '#ffcd56', '#9966ff', '#ff9f40', '#c9cbcf'];
        const getColor = (i) => colors[i % colors.length];

        // 1. Stacked Bar: Volume by Product for each Company
        // Logic: Show all products, no filtering
        const companies1 = [...new Set(data.graph_vol_product.map(d => d.CompanyName))];
        const allProducts = [...new Set(data.graph_vol_product.map(d => d.ProductName))];

        const datasets1 = allProducts.map((prod, i) => ({
            label: prod,
            data: companies1.map(comp => {
                const entry = data.graph_vol_product.find(d => d.CompanyName === comp && d.ProductName === prod);
                return entry ? Number(entry.total_volume) : 0;
            }),
            backgroundColor: getColor(i)
        }));

        charts['volProd'] = new Chart(ctxVolProd, {
            type: 'bar',
            data: {
                labels: companies1,
                datasets: datasets1
            },
            options: {
                plugins: {
                    title: { display: true, text: 'Volume by Product per Company' },
                    legend: { display: false }, // Hide legend
                    tooltip: {
                        callbacks: {
                            footer: (items) => {
                                const total = items.reduce((a, b) => a + b.parsed.y, 0);
                                return 'Total Volume: ' + total;
                            }
                        }
                    }
                },
                scales: {
                    x: { stacked: true },
                    y: { stacked: true, beginAtZero: true }
                },
                responsive: true,
                maintainAspectRatio: false
            }
        });

        // 2. Stacked Bar: Shipment Status by Company
        // Labels = Companies, Datasets = Statuses
        const companies2 = [...new Set(data.graph_status_company.map(d => d.CompanyName))];
        const statuses = [...new Set(data.graph_status_company.map(d => d.status))];

        const datasets2 = statuses.map((stat, i) => ({
            label: stat,
            data: companies2.map(comp => {
                const entry = data.graph_status_company.find(d => d.CompanyName === comp && d.status === stat);
                return entry ? entry.count : 0;
            }),
            backgroundColor: getColor(i)
        }));

        charts['statComp'] = new Chart(ctxStatComp, {
            type: 'bar',
            data: {
                labels: companies2,
                datasets: datasets2
            },
            options: {
                plugins: { title: { display: true, text: 'Shipment Status per Company' } },
                scales: { x: { stacked: true }, y: { stacked: true } },
                responsive: true,
                maintainAspectRatio: false
            }
        });

        // 3. Line Graph: Volume vs Time (Trends in shipment status)
        // X-Axis = Dates, Datasets = Statuses (Volume)
        const dates3 = [...new Set(data.graph_status_trend.map(d => d.date))];
        const statuses3 = [...new Set(data.graph_status_trend.map(d => d.status))];

        const datasets3 = statuses3.map((stat, i) => ({
            label: stat,
            data: dates3.map(date => {
                const entry = data.graph_status_trend.find(d => d.date === date && d.status === stat);
                return entry ? entry.volume : 0;
            }),
            borderColor: getColor(i),
            fill: false
        }));

        charts['statTrend'] = new Chart(ctxStatTrend, {
            type: 'line',
            data: {
                labels: dates3,
                datasets: datasets3
            },
            options: {
                plugins: { title: { display: true, text: 'Shipment Status Trends (Volume)' } },
                responsive: true,
                maintainAspectRatio: false
            }
        });

        // 4. Line Graph: Volume by Differing Companies over Time
        // X-Axis = Dates, Datasets = Companies
        const dates4 = [...new Set(data.graph_company_trend.map(d => d.date))];
        const companies4 = [...new Set(data.graph_company_trend.map(d => d.CompanyName))];

        const datasets4 = companies4.map((comp, i) => ({
            label: comp,
            data: dates4.map(date => {
                const entry = data.graph_company_trend.find(d => d.date === date && d.CompanyName === comp);
                return entry ? entry.volume : 0;
            }),
            borderColor: getColor(i),
            fill: false
        }));

        charts['compTrend'] = new Chart(ctxCompTrend, {
            type: 'line',
            data: {
                labels: dates4,
                datasets: datasets4
            },
            options: {
                plugins: {
                    title: { display: true, text: 'Company Volume Trends' },
                    legend: { display: false } // Hide legend
                },
                responsive: true,
                maintainAspectRatio: false
            }
        });
    }
});