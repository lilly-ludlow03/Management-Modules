// Expand/Enlarge Functionality (Global Scope)
function toggleExpand(btn) {
    // Handle both possible structures: inside .result-section or .table_con equivalent
    const container = btn.parentElement;
    container.classList.toggle('expanded-view');

    if (container.classList.contains('expanded-view')) {
        btn.innerHTML = '&#x2715;'; // Close/X icon
        btn.title = "Close Expanded View";
    } else {
        btn.innerHTML = '&#x2922;'; // Enlarge icon
        btn.title = "Enlarge";
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
            resultsContainer.style.display = 'none';
            document.getElementById('placeholder-message').style.display = 'block';
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
                container.style.marginBottom = '15px';
                container.style.display = 'flex';
                container.style.flexDirection = 'column';
                container.style.gap = '10px';

                // Top row for Label and Select
                const topRow = document.createElement('div');
                topRow.style.display = 'flex';
                topRow.style.alignItems = 'center';
                topRow.style.gap = '10px';

                const label = document.createElement('label');
                label.textContent = 'Company Role: ';

                const roleSelect = document.createElement('select');
                roleSelect.id = 'companyRoleSelect';
                roleSelect.innerHTML = `
                        <option value="Both">Both</option>
                        <option value="Sending Company">Sending Company</option>
                        <option value="Receiving Company">Receiving Company</option>
                    `;

                topRow.appendChild(label);
                topRow.appendChild(roleSelect);

                // Input Wrapper on next line
                const nameInputWrapper = document.createElement('div');
                nameInputWrapper.style.position = 'relative';
                nameInputWrapper.style.display = 'block'; // Block to take full width if needed or just sit below
                nameInputWrapper.style.width = '100%';
                nameInputWrapper.style.maxWidth = '300px'; // Limit width slightly for aesthetics

                const nameInput = document.createElement('input');
                nameInput.type = 'text';
                nameInput.id = 'companySearchInput';
                nameInput.placeholder = 'Search Company...';
                nameInput.style.width = '100%';

                const suggestionsUl = document.createElement('ul');
                suggestionsUl.id = 'company-suggestions';
                suggestionsUl.style.listStyle = 'none';
                suggestionsUl.style.padding = '0';
                suggestionsUl.style.margin = '0';
                suggestionsUl.style.border = '1px solid #ccc';
                suggestionsUl.style.position = 'absolute';
                suggestionsUl.style.width = '100%';
                suggestionsUl.style.background = 'white';
                suggestionsUl.style.zIndex = '1001';
                suggestionsUl.style.maxHeight = '150px';
                suggestionsUl.style.overflowY = 'auto';
                suggestionsUl.style.display = 'none';

                nameInputWrapper.appendChild(nameInput);
                nameInputWrapper.appendChild(suggestionsUl);

                container.appendChild(topRow);
                container.appendChild(nameInputWrapper);
                dynamicFiltersContainer.appendChild(container);

                // Autocomplete Logic
                nameInput.addEventListener('input', () => {
                    const value = nameInput.value.trim();
                    suggestionsUl.innerHTML = '';
                    if (value === '') {
                        suggestionsUl.style.display = 'none';
                        return;
                    }

                    fetch(`../Company-Details/get_companies.php?q=${encodeURIComponent(value)}`)
                        .then(r => r.json())
                        .then(data => {
                            if (data.length > 0) {
                                suggestionsUl.style.display = 'block';
                                data.forEach(company => {
                                    const li = document.createElement('li');
                                    li.textContent = company.CompanyName;
                                    li.style.padding = '8px';
                                    li.style.cursor = 'pointer';

                                    li.onclick = () => {
                                        nameInput.value = company.CompanyName;
                                        suggestionsUl.style.display = 'none';
                                    };
                                    li.onmouseover = () => li.style.backgroundColor = '#f0f0f0';
                                    li.onmouseout = () => li.style.backgroundColor = 'white';
                                    suggestionsUl.appendChild(li);
                                });
                            } else {
                                suggestionsUl.style.display = 'none';
                            }
                        })
                        .catch(e => console.error(e));
                });

                document.addEventListener("click", (e) => {
                    if (!nameInputWrapper.contains(e.target)) {
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
                container.style.marginBottom = '15px';
                container.style.cssText = 'margin-top:10px; display:grid; gap:8px; grid-template-columns:auto 1fr; align-items:center;';

                const fromLabel = document.createElement('label');
                fromLabel.textContent = 'From: ';
                const fromInput = document.createElement('input');
                fromInput.type = 'date';
                fromInput.id = 'dateFrom';
                fromInput.style.marginRight = '10px';

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
                container.style.marginBottom = '15px';
                container.style.display = 'flex';
                container.style.alignItems = 'center';
                container.style.gap = '10px';
                container.style.whiteSpace = 'nowrap';

                const label = document.createElement('label');
                label.textContent = 'Region:';

                const typeSelect = document.createElement('select');
                typeSelect.id = 'regionTypeSelect';
                typeSelect.innerHTML = '<option value="">Select Region Type...</option><option value="Continent">Continent</option><option value="Country">Country</option><option value="City">City</option>';

                const valueContainer = document.createElement('div');
                valueContainer.id = 'regionValueContainer';
                valueContainer.style.display = 'inline-block';
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
        const roleSelect = document.getElementById('companyRoleSelect');
        const companyInput = document.getElementById('companySearchInput');

        if (roleSelect && companyInput && companyInput.value.trim() !== '') {
            params.append('company_role', roleSelect.value);
            params.append('company_name', companyInput.value.trim());
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
        fetch(`get_general_transactions.php?${params.toString()}`)
            .then(r => r.json())
            .then(data => {
                if (data.error) throw new Error(data.error);

                resultsContainer.style.display = 'block';
                placeholder.style.display = 'none';

                renderCompanyStats(data.company_stats);
                renderTransactions(data.transactions);
                renderGraphs(data);
            })
            .catch(e => {
                console.error(e);
                alert("Error loading data: " + e.message);
            });
    });

    function renderCompanyStats(data) {
        const tbody = document.querySelector('#company-stats-table tbody');
        tbody.innerHTML = '';
        if (!data || data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:10px;">No data available</td></tr>';
            return;
        }
        data.forEach(row => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                    <td style="border: 1px solid #ddd; padding: 8px;">${row.company_name}</td>
                    <td style="border: 1px solid #ddd; padding: 8px; text-align: center;">${row.on_time_rate}</td>
                    <td style="border: 1px solid #ddd; padding: 8px;">${row.products}</td>
                    <td style="border: 1px solid #ddd; padding: 8px;">${row.disruption_type}</td>
                `;
            tbody.appendChild(tr);
        });
    }

    function renderTransactions(data) {
        const tbody = document.querySelector('#transactions-table tbody');
        tbody.innerHTML = '';
        if (!data || data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:10px;">No transactions found</td></tr>';
            return;
        }
        data.forEach(row => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                    <td style="border: 1px solid #ddd; padding: 8px;">${row.date}</td>
                    <td style="border: 1px solid #ddd; padding: 8px;">${row.leaving_company}</td>
                    <td style="border: 1px solid #ddd; padding: 8px;">${row.going_to_company}</td>
                    <td style="border: 1px solid #ddd; padding: 8px; text-align: right;">${row.volume}</td>
                    <td style="border: 1px solid #ddd; padding: 8px; text-align: center;">${row.status}</td>
                    <td style="border: 1px solid #ddd; padding: 8px; text-align: center;">${row.id}</td>
                `;
            tbody.appendChild(tr);
        });
    }

    let charts = {}; // Store chart instances

    function renderGraphs(data) {
        const ctxDis = document.getElementById('disruptionLineChart').getContext('2d');
        const ctxStat = document.getElementById('statusBarChart').getContext('2d');
        const ctxPerf = document.getElementById('deliveryPieChart').getContext('2d');
        const ctxVol = document.getElementById('volumeLineChart').getContext('2d');

        // Destroy old charts
        ['disruption', 'status', 'perf', 'vol'].forEach(k => {
            if (charts[k]) charts[k].destroy();
        });

        // 1. Disruption Over Time (Line)
        charts['disruption'] = new Chart(ctxDis, {
            type: 'line',
            data: {
                labels: data.graph_disruption.map(d => d.date),
                datasets: [{
                    label: 'Disruptions',
                    data: data.graph_disruption.map(d => d.count),
                    borderColor: 'red',
                    fill: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { title: { display: true, text: 'Disruption Over Time' } }
            }
        });

        // 2. Shipment Status (Bar)
        charts['status'] = new Chart(ctxStat, {
            type: 'bar',
            data: {
                labels: data.graph_status.map(d => d.calculated_status),
                datasets: [{
                    label: 'Shipments',
                    data: data.graph_status.map(d => d.count),
                    backgroundColor: ['#ffcd56', '#36a2eb', '#ff6384', '#4bc0c0']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { title: { display: true, text: 'Shipment Status' }, legend: { display: false } }
            }
        });

        // 3. Delivery Performance (Pie)
        charts['perf'] = new Chart(ctxPerf, {
            type: 'pie',
            data: {
                labels: data.graph_performance.map(d => d.performance),
                datasets: [{
                    data: data.graph_performance.map(d => d.count),
                    backgroundColor: ['#36a2eb', '#ff6384', '#ffcd56', '#cc65fe']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { title: { display: true, text: 'Delivery Performance' } }
            }
        });

        // 4. Volume Over Time (Line)
        // Check if canvas exists before creating chart (it was removed from dedicated section)
        if (ctxVol) {
            charts['vol'] = new Chart(ctxVol, {
                type: 'line',
                data: {
                    labels: data.graph_volume.map(d => d.date),
                    datasets: [{
                        label: 'Volume',
                        data: data.graph_volume.map(d => d.volume),
                        borderColor: 'blue',
                        fill: false
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { title: { display: true, text: 'Volume Over Time' } }
                }
            });
        }
    }
});