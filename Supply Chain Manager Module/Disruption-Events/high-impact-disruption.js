
// Toggle Filters Logic
const filterToggle = document.getElementById('filterToggle');
const contentDiv = document.querySelector('.content');

if (filterToggle) {
    filterToggle.addEventListener('click', (e) => {
        e.stopPropagation(); // Prevents the events from bubbling up
        contentDiv.classList.toggle('filters-hidden');
        // Update the Icons
        if (contentDiv.classList.contains('filters-hidden')) {
            filterToggle.innerHTML = '&#9654;';
            filterToggle.title = "Show Filters";
        } else {
            filterToggle.innerHTML = '&#9664;';
            filterToggle.title = "Hide Filters";
        }
        // This Triggers chart the resize when needed
        window.dispatchEvent(new Event('resize'));
    });
}

// Expand and Enlarge 
function toggleExpand(btn) {
    const container = btn.parentElement;
    container.classList.toggle('expanded-view');

    if (container.classList.contains('expanded-view')) {
        btn.innerHTML = '&#x2715;'; // Close/X icon
        btn.title = "Close Expanded View";
    } else {
        btn.innerHTML = '&#x2922;'; // Enlarge icon
        btn.title = "Enlarge";
    }

    // If it's the chart container it triggers resize 
    if (container.querySelector('canvas')) {
        setTimeout(() => {
            window.dispatchEvent(new Event('resize'));
        }, 50);
    }
}
document.addEventListener("DOMContentLoaded", () => {
    const tierFilterCheckbox = document.getElementById('tierFilter');
    const companyFilterCheckbox = document.getElementById('companyFilter');
    const regionFilterCheckbox = document.getElementById('regionFilter');
    const dateFilterCheckbox = document.getElementById('dateFilter');
    const dynamicFiltersContainer = document.getElementById('dynamic-filters-container');
    const dynamicDividers = document.getElementById('dynamic-dividers');
    const goButton = document.getElementById('go-button-filters');
    const tableContainer = document.querySelector('.table-container');
    const clearFiltersBtn = document.getElementById('clear-filters');

    // Clear Filters Logic
    if (clearFiltersBtn) {
        clearFiltersBtn.addEventListener('click', () => {
            // Uncheck main toggles
            tierFilterCheckbox.checked = false;
            companyFilterCheckbox.checked = false;
            regionFilterCheckbox.checked = false;
            dateFilterCheckbox.checked = false;

            // Trigger change events to remove dynamic containers
            tierFilterCheckbox.dispatchEvent(new Event('change'));
            companyFilterCheckbox.dispatchEvent(new Event('change'));
            regionFilterCheckbox.dispatchEvent(new Event('change'));
            dateFilterCheckbox.dispatchEvent(new Event('change'));

            // Clear the results
            tableContainer.innerHTML = '';
            const graphContainer = document.querySelector('.graph-container');
            if (graphContainer) graphContainer.innerHTML = '';
            if (myChart) {
                myChart.destroy();
                myChart = null;
            }

            // Remove dropdown if they exists
            const limitDropdown = document.getElementById('chartLimitSelect');
            if (limitDropdown && limitDropdown.parentElement) {
                limitDropdown.parentElement.remove();
            }
            currentData = null;


            const resultsArea = document.getElementById('results-area');
            resultsArea.classList.add('hidden');
            resultsArea.style.display = '';

            const placeholder = document.getElementById('placeholder-message');
            if (placeholder) {
                placeholder.classList.remove('hidden');
                placeholder.style.display = '';
            }
        });
    }

    // Function to update the dividers
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

    // Attach observer to watch for changes
    const observer = new MutationObserver(updateDividers);
    observer.observe(dynamicFiltersContainer, { childList: true });

    let myChart = null;
    let currentData = null;
    // Main Entry
    goButton.addEventListener('click', () => {
        generateTable(tableContainer);
        contentDiv.classList.add('filters-hidden');
        filterToggle.style.left = "0";
    });

    function generateTable(container) {
        // 1) Collect Filter Values
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

        let tier = 'All';
        const tierSelect = document.getElementById('tierSelect');
        if (tierSelect) {
            tier = tierSelect.value;
        }

        let companies = [];
        const selectedCompaniesDiv = document.getElementById('selected-companies');
        if (selectedCompaniesDiv) {
            Array.from(selectedCompaniesDiv.children).forEach(span => {
                if (span.firstChild && span.firstChild.nodeType === Node.TEXT_NODE) {
                    companies.push(span.firstChild.textContent.trim());
                }
            });
        }

        let dateFrom = '';
        let dateTo = '';
        const dateFromInput = document.getElementById('dynamic-date-from');
        const dateToInput = document.getElementById('dynamic-date-to');
        if (dateFromInput) dateFrom = dateFromInput.value;
        if (dateToInput) dateTo = dateToInput.value;

        // Validate all the Dates
        if ((dateFrom && !dateTo) || (!dateFrom && dateTo)) {
            alert("Please select both a start date and an end date.");
            return;
        }
        if (dateFrom && dateTo) {
            const from = new Date(dateFrom);
            const to = new Date(dateTo);
            if (from > to) {
                alert("Start date cannot be after end date");
                return;
            }
        }

        // 2) Build Query String
        const params = new URLSearchParams();
        if (regionType && regionType !== 'Select...') params.append('region_type', regionType);
        if (regionValue) params.append('region', regionValue);
        if (tier !== 'All') params.append('tier', tier);

        // Companies are passed as pipe-delimited list
        if (companies.length > 0) {
            params.append('companies', companies.join('|'));
        }

        if (dateFrom) params.append('date_from', dateFrom);
        if (dateTo) params.append('date_to', dateTo);

        // 3) Fetch Data
        fetch(`get_high_impact_rate.php?${params.toString()}`)
            .then(r => {
                if (!r.ok) {
                    return r.text().then(text => { throw new Error(text || r.statusText) });
                }
                return r.json();
            })
            .then(data => {
                if (data.error) {
                    throw new Error(data.error);
                }
                if (!data || data.length === 0) {
                    container.innerHTML = '<p>No data found for the selected filters.</p>';
                    const graphContainer = document.querySelector('.graph-container');
                    graphContainer.innerHTML = '';

                    // Show results area even if empty to show message/error
                    const resultsArea = document.getElementById('results-area');
                    resultsArea.classList.remove('hidden');
                    resultsArea.style.display = '';

                    const placeholder = document.getElementById('placeholder-message');
                    if (placeholder) {
                        placeholder.classList.add('hidden');
                        placeholder.style.display = '';
                    }
                    return;
                }

                // Show the container whe the data is loaded
                const resultsArea = document.getElementById('results-area');
                resultsArea.classList.remove('hidden');
                resultsArea.style.display = '';

                const placeholder = document.getElementById('placeholder-message');
                if (placeholder) {
                    placeholder.classList.add('hidden');
                    placeholder.style.display = '';
                }

                // 4) Render Table
                container.innerHTML = '<button class="expand-btn" title="Enlarge Table" onclick="toggleExpand(this)">&#x2922;</button>';
                const table = document.createElement('table');
                table.style.width = '100%';
                table.style.borderCollapse = 'collapse';
                table.className = 'table';

                const thead = document.createElement('thead');
                const headerRow = document.createElement("tr");
                // Updated the Header
                const headers = ["Company", "High Impact Rate (%)", "Ranking"];

                headers.forEach(headerText => {
                    const th = document.createElement('th');
                    th.textContent = headerText;
                    th.style.border = '1px solid #ddd';
                    th.style.padding = '8px';
                    th.style.textAlign = 'center';
                    th.style.backgroundColor = '#f2f2f2';
                    headerRow.appendChild(th);
                });
                thead.appendChild(headerRow);
                table.appendChild(thead);

                const tbody = document.createElement('tbody');

                data.forEach((row, index) => {
                    const tr = document.createElement('tr');

                    const tdName = document.createElement('td');
                    tdName.textContent = row.company_name;
                    tdName.style.border = '1px solid #ddd';
                    tdName.style.padding = '8px';
                    tdName.style.textAlign = 'center';

                    const tdVal = document.createElement('td');
                    // Use new field highimpactrate
                    tdVal.textContent = parseFloat(row.highImpactRate).toFixed(2) + '%';
                    tdVal.style.border = '1px solid #ddd';
                    tdVal.style.padding = '8px';
                    tdVal.style.textAlign = 'center';

                    const tdRank = document.createElement('td');
                    tdRank.textContent = index + 1;
                    tdRank.style.border = '1px solid #ddd';
                    tdRank.style.padding = '8px';
                    tdRank.style.textAlign = 'center';

                    tr.appendChild(tdName);
                    tr.appendChild(tdVal);
                    tr.appendChild(tdRank);
                    tbody.appendChild(tr);
                });

                table.appendChild(tbody);
                container.appendChild(table);

                // 5) Render Chart
                currentData = data;
                renderLimitDropdown();
                renderChart(data, '10');
            })
            .catch(err => {
                console.error("Error fetching data:", err);
                container.innerHTML = `<p style="color:red;">Error loading data: ${err.message}</p>`;
            });
    }
    // Dropdown in the chart area to limit number of companies
    function renderLimitDropdown() {
        const graphContainer = document.querySelector('.graph-container');
        if (document.getElementById('chartLimitSelect')) return;

        const limitContainer = document.createElement('div');
        limitContainer.style.position = 'absolute';
        limitContainer.style.top = '5px';
        limitContainer.style.right = '50px';
        limitContainer.style.zIndex = '20';
        limitContainer.style.backgroundColor = 'rgba(255, 255, 255, 0.8)';
        limitContainer.style.padding = '2px';
        limitContainer.style.borderRadius = '4px';

        const label = document.createElement('label');
        label.textContent = 'Show: ';
        label.style.fontSize = '0.9em';
        label.style.marginRight = '5px';

        const select = document.createElement('select');
        select.id = 'chartLimitSelect';
        select.style.fontSize = '0.9em';

        const options = ['All', '5', '10', '15', '20', '25', '50'];
        options.forEach(opt => {
            const option = document.createElement('option');
            option.value = opt;
            option.textContent = opt;
            select.appendChild(option);
        });

        select.value = '10';

        select.addEventListener('change', (e) => {
            renderChart(currentData, e.target.value);
        });

        limitContainer.appendChild(label);
        limitContainer.appendChild(select);
        graphContainer.appendChild(limitContainer);
    }

    function renderChart(data, limit = '10') {
        const graphContainer = document.querySelector('.graph-container');

        // Ensure the Expand Button Exists
        if (!graphContainer.querySelector('.expand-btn')) {
            const expandBtn = document.createElement('button');
            expandBtn.className = 'expand-btn';
            expandBtn.title = 'Enlarge Graph';
            expandBtn.onclick = function () { toggleExpand(this); };
            expandBtn.innerHTML = '&#x2922;';
            graphContainer.appendChild(expandBtn);
        }

        // Ensure the Dropdown Exists
        renderLimitDropdown();


        const oldCanvas = graphContainer.querySelector('canvas');
        if (oldCanvas) oldCanvas.remove();

        const canvas = document.createElement('canvas');
        canvas.id = 'recoveryChart';

        graphContainer.appendChild(canvas);

        const ctx = canvas.getContext('2d');

        // Sort data by the High Impact Rate 
        let chartData = [...data].sort((a, b) => parseFloat(b.highImpactRate) - parseFloat(a.highImpactRate));

        if (limit !== 'All') {
            chartData = chartData.slice(0, parseInt(limit));
        }

        // Extract Company names and High Impact Rate
        const labels = chartData.map(d => d.company_name);
        const values = chartData.map(d => parseFloat(d.highImpactRate));

        if (values.length === 0) return;


        const backgroundColors = 'rgba(46, 125, 50, 0.6)';
        const borderColors = '#2e7d32';

        if (myChart) {
            myChart.destroy();
        }

        myChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'High Impact Rate (%)',
                    data: values,
                    backgroundColor: backgroundColors,
                    borderColor: borderColors,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'High Impact Rate (%)'
                        },
                        ticks: {
                            callback: function (value) {
                                return value + "%"
                            }
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Company'
                        },
                        ticks: {
                            autoSkip: false,
                            maxRotation: 45,
                            minRotation: 45
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: (item) => `Rate: ${item.raw}%`
                        }
                    }
                }
            }
        });
    }

    // Initialize all the Filters
    let continents, countries, cities;

    // Ongoing Disruption Alert 

    // Fetch initially without to check for the ungoing events
    fetch('get_company_data.php')
        .then(r => r.json())
        .then(data => {
            if (data.ongoing && data.ongoing.count > 0) {
                let msg = `There are currently ${data.ongoing.count} ongoing disruption event(s).`;
                if (data.ongoing.types) {
                    msg += `<br><br>Types: ${data.ongoing.types}`;
                }
                // Show Custom Alert
                const overlay = document.getElementById('custom-alert-overlay');
                const messageEl = document.getElementById('custom-alert-message');
                const closeBtn = document.getElementById('custom-alert-close');

                messageEl.innerHTML = msg;
                overlay.style.display = 'flex';

                closeBtn.onclick = function () {
                    overlay.style.display = 'none';
                };
            }
        })
        .catch(err => console.error("Error checking ongoing events:", err));

    Promise.all([
        fetch("get_filters.php?type=continents").then(r => r.json()),
        fetch("get_filters.php?type=countries").then(r => r.json()),
        fetch("get_filters.php?type=cities").then(r => r.json())
    ]).then(values => {
        [continents, countries, cities] = values;
        initializeRegionFilter();
    }).catch(error => console.error("Error fetching filter data:", error));


    function initializeRegionFilter() {
        regionFilterCheckbox.addEventListener('change', (event) => {
            const existingRegionFilter = document.getElementById('region-filter-container');

            if (event.currentTarget.checked) {
                if (!existingRegionFilter) {
                    const regionContainer = document.createElement('div');
                    regionContainer.id = 'region-filter-container';
                    regionContainer.style.marginBottom = '15px';

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
                    regionValueContainer.style.marginTop = '10px';
                    regionContainer.appendChild(regionValueContainer);

                    dynamicFiltersContainer.appendChild(regionContainer);
                    // Populate dropdown
                    regionTypeSelect.addEventListener('change', () => {
                        regionValueContainer.innerHTML = '';
                        const selectedType = regionTypeSelect.value;
                        let options = [];

                        if (selectedType === 'Continent') options = continents;
                        if (selectedType === 'Country') options = countries;
                        if (selectedType === 'City') options = cities;

                        if (options.length > 0) {
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
                if (existingRegionFilter) {
                    existingRegionFilter.remove();
                }
            }
        });
    }
    // Tier Dinamic Dropdown
    tierFilterCheckbox.addEventListener('change', (event) => {
        const existingTierFilter = document.getElementById('tier-filter-container');

        if (event.currentTarget.checked) {
            if (!existingTierFilter) {
                const tierContainer = document.createElement('div');
                tierContainer.id = 'tier-filter-container';
                tierContainer.style.marginBottom = '15px';

                const tierLabel = document.createElement('label');
                tierLabel.setAttribute('for', 'tierSelect');
                tierLabel.textContent = 'Tier: ';
                tierLabel.style.marginRight = '5px';

                const tierSelect = document.createElement('select');
                tierSelect.id = 'tierSelect';

                const tiers = ["Tier 1", "Tier 2", "Tier 3"];
                tiers.forEach(tierName => {
                    const option = document.createElement('option');
                    option.value = tierName.replace('Tier ', '');
                    option.textContent = tierName;
                    tierSelect.appendChild(option);
                });

                tierContainer.appendChild(tierLabel);
                tierContainer.appendChild(tierSelect);
                dynamicFiltersContainer.appendChild(tierContainer);
            }
        } else {
            if (existingTierFilter) {
                existingTierFilter.remove();
            }
        }
    });
    // Company Multi-Select
    companyFilterCheckbox.addEventListener('change', (event) => {
        const existingCompanyFilter = document.getElementById('company-filter-container');

        if (event.currentTarget.checked) {
            if (!existingCompanyFilter) {
                const companyContainer = document.createElement('div');
                companyContainer.id = 'company-filter-container';
                companyContainer.style.marginBottom = '15px';

                const companyLabel = document.createElement('label');
                companyLabel.textContent = 'Companies: ';
                companyLabel.style.marginRight = '5px';

                const multiSelectContainer = document.createElement('div');
                multiSelectContainer.style.display = 'block'; // block for sidebar
                multiSelectContainer.style.position = 'relative';
                multiSelectContainer.style.width = '100%';

                const selectedCompaniesDiv = document.createElement('div');
                selectedCompaniesDiv.id = 'selected-companies';
                selectedCompaniesDiv.style.display = 'flex';
                selectedCompaniesDiv.style.flexWrap = 'wrap';
                selectedCompaniesDiv.style.gap = '5px';
                selectedCompaniesDiv.style.marginBottom = '5px';

                const searchInput = document.createElement('input');
                searchInput.type = 'text';
                searchInput.id = 'company-multi-search';
                searchInput.placeholder = 'Search...';

                const suggestionsUl = document.createElement('ul');
                suggestionsUl.id = 'company-suggestions';
                suggestionsUl.style.listStyle = 'none';
                suggestionsUl.style.padding = '0';
                suggestionsUl.style.margin = '0';
                suggestionsUl.style.border = '1px solid #ccc';
                suggestionsUl.style.position = 'absolute';
                suggestionsUl.style.width = '100%';
                suggestionsUl.style.backgroundColor = 'white';
                suggestionsUl.style.zIndex = '1001';
                suggestionsUl.style.maxHeight = '150px';
                suggestionsUl.style.overflowY = 'auto';


                multiSelectContainer.appendChild(selectedCompaniesDiv);
                multiSelectContainer.appendChild(searchInput);
                multiSelectContainer.appendChild(suggestionsUl);

                companyContainer.appendChild(companyLabel);
                companyContainer.appendChild(multiSelectContainer);
                dynamicFiltersContainer.appendChild(companyContainer);

                // Add search logic
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
                                li.style.padding = '8px';
                                li.style.cursor = 'pointer';
                                li.addEventListener('mouseover', () => li.style.backgroundColor = '#f0f0f0');
                                li.addEventListener('mouseout', () => li.style.backgroundColor = 'white');

                                li.addEventListener('click', () => {
                                    const existing = Array.from(selectedCompaniesDiv.children).find(tag => tag.textContent.slice(0, -1).trim() === company.CompanyName);
                                    if (!existing) {
                                        const companyTag = document.createElement('span');
                                        companyTag.style.backgroundColor = '#e0e0e0';
                                        companyTag.style.padding = '2px 8px';
                                        companyTag.style.borderRadius = '12px';
                                        companyTag.style.marginRight = '5px';
                                        companyTag.style.fontSize = '0.8em';
                                        companyTag.textContent = company.CompanyName + ' ';

                                        const removeBtn = document.createElement('button');
                                        removeBtn.textContent = 'x';
                                        removeBtn.style.border = 'none';
                                        removeBtn.style.backgroundColor = 'transparent';
                                        removeBtn.style.color = '#888';
                                        removeBtn.style.cursor = 'pointer';
                                        removeBtn.style.marginLeft = '4px';

                                        removeBtn.addEventListener('click', () => {
                                            companyTag.remove();
                                        });

                                        companyTag.appendChild(removeBtn);
                                        selectedCompaniesDiv.appendChild(companyTag);
                                    }
                                    searchInput.value = '';
                                    suggestionsUl.innerHTML = '';
                                });
                                suggestionsUl.appendChild(li);
                            });
                        });
                });
                // Hide suggestions if user clicks anywhere else
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
    // Date range filters

    dateFilterCheckbox.addEventListener('change', (event) => {
        const existingDateFilter = document.getElementById('date-filter-container');

        if (event.currentTarget.checked) {
            if (!existingDateFilter) {
                const dateContainer = document.createElement('div');
                dateContainer.id = 'date-filter-container';
                dateContainer.style.cssText = 'margin-top:10px; display:grid; gap:8px; grid-template-columns:auto 1fr; align-items:center;';

                const fromLabel = document.createElement('label');
                fromLabel.textContent = 'From: ';
                const fromInput = document.createElement('input');
                fromInput.type = 'date';
                fromInput.id = 'dynamic-date-from';
                fromInput.style.width = '100%';

                const toLabel = document.createElement('label');
                toLabel.textContent = ' To: ';
                const toInput = document.createElement('input');
                toInput.type = 'date';
                toInput.id = 'dynamic-date-to';
                toInput.style.width = '100%';

                dateContainer.appendChild(fromLabel);
                dateContainer.appendChild(fromInput);
                dateContainer.appendChild(toLabel);
                dateContainer.appendChild(toInput);

                dynamicFiltersContainer.appendChild(dateContainer);
            }
        } else {
            if (existingDateFilter) {
                existingDateFilter.remove();
            }
        }
    });
});

// Ongoing Distruption Alert 
fetch('get_company_data.php')
    .then(r => r.json())
    .then(data => {
        if (data.ongoing && data.ongoing.count > 0) {
            let msg = `There are currently ${data.ongoing.count} ongoing disruption event(s).`;
            if (data.ongoing.types) {
                msg += `<br><br>Types: ${data.ongoing.types}`;
            }
            // Show the Custom Alert
            const overlay = document.getElementById('custom-alert-overlay');
            const messageEl = document.getElementById('custom-alert-message');
            const closeBtn = document.getElementById('custom-alert-close');

            messageEl.innerHTML = msg;
            overlay.style.display = 'flex';

            closeBtn.onclick = function () {
                overlay.style.display = 'none';
            };
        }
    })
    .catch(err => console.error("Error checking ongoing events:", err));
