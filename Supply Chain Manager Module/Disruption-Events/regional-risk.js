
// Toggle Filters Logic
const filterToggle = document.getElementById('filterToggle');
const contentDiv = document.querySelector('.content');

if (filterToggle) {
    filterToggle.addEventListener('click', (e) => {
        e.stopPropagation(); // Prevent event from bubbling up if necessary
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

// Expand/Enlarge Functionality
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

    // If it's the chart container, trigger resize
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

            // Clear results
            tableContainer.innerHTML = '';
            const graphContainer = document.querySelector('.graph-container');
            if (graphContainer) graphContainer.innerHTML = '';
            if (myChart) {
                myChart.destroy();
                myChart = null;
            }

            // Hide container again and show placeholder
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

    // Function to update dividers
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

    goButton.addEventListener('click', () => {
        generateTable(tableContainer);
        contentDiv.classList.add('filters-hidden');
        filterToggle.style.left = "0";
    });

    function generateTable(container) {
        // 1. Collect Filter Values
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

        // Validate Dates
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

        // 2. Build Query String
        const params = new URLSearchParams();
        if (regionType && regionType !== 'Select...') params.append('region_type', regionType);
        if (regionValue) params.append('region', regionValue);
        if (tier !== 'All') params.append('tier', tier);

        // Use pipe delimiter as per recent fix
        if (companies.length > 0) {
            params.append('companies', companies.join('|'));
        }

        if (dateFrom) params.append('date_from', dateFrom);
        if (dateTo) params.append('date_to', dateTo);

        // 3. Fetch Data
        // Use the NEW PHP file for Regional Risk Concentration
        fetch(`get_regional_risk.php?${params.toString()}`)
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

                    // Show results area even if empty to show message
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

                // Show the container when data is loaded
                const resultsArea = document.getElementById('results-area');
                resultsArea.classList.remove('hidden');
                resultsArea.style.display = '';

                const placeholder = document.getElementById('placeholder-message');
                if (placeholder) {
                    placeholder.classList.add('hidden');
                    placeholder.style.display = '';
                }

                // 4. Render Table
                container.innerHTML = '<button class="expand-btn" title="Enlarge Table" onclick="toggleExpand(this)">&#x2922;</button>';
                const table = document.createElement('table');
                table.style.width = '100%';
                table.style.borderCollapse = 'collapse';
                table.className = 'table';

                const thead = document.createElement('thead');
                const headerRow = document.createElement("tr");
                // Updated Header
                const headers = ["Company", "Regional Risk Concentration", "Ranking"];

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
                    // Use new field rrc (formatted as decimal fraction)
                    tdVal.textContent = parseFloat(row.rrc).toFixed(4);
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

                // 5. Render Chart
                renderChart(data, regionType);
            })
            .catch(err => {
                console.error("Error fetching data:", err);
                container.innerHTML = `<p style="color:red;">Error loading data: ${err.message}</p>`;
            });
    }

    function renderChart(data, regionType) {
        const graphContainer = document.querySelector('.graph-container');
        graphContainer.innerHTML = '<button class="expand-btn" title="Enlarge Graph" onclick="toggleExpand(this)">&#x2922;</button>'; // Clear previous but keep button
        const canvas = document.createElement('canvas');
        canvas.id = 'recoveryChart';
        // Removed inline style.maxHeight = '400px'; to respect CSS
        graphContainer.appendChild(canvas);

        const ctx = canvas.getContext('2d');

        if (data.length === 0) return;

        // Determine X-Axis Scope
        let xField = 'continent';
        let xLabel = 'Region (Continent)';

        // If specific region selected, drill down
        if (regionType === 'Continent') {
            xField = 'country';
            xLabel = 'Region (Country)';
        } else if (regionType === 'Country') {
            xField = 'city';
            xLabel = 'Region (City)';
        }

        // Define Risk Ranges (Y-Axis)
        // 0-0.1, 0.1-0.2, ..., 0.9-1.0
        const bins = [];
        for (let i = 0; i < 10; i++) {
            const min = i / 10;
            const max = (i + 1) / 10;
            bins.push({
                id: i,
                min: min,
                max: max,
                label: `${min.toFixed(1)} - ${max.toFixed(1)}`
            });
        }

        // Prepare Data for Matrix
        // Map: "RegionName|BinIndex" -> Count
        const matrixMap = new Map();
        const regionsSet = new Set();

        data.forEach(d => {
            const regionName = d[xField];
            if (!regionName) return; // Skip if null
            regionsSet.add(regionName);

            const val = parseFloat(d.rrc);
            // Determine Bin
            let binIndex = Math.floor(val * 10);
            if (binIndex >= 10) binIndex = 9; // Handle 1.0 inclusive or >1

            const key = `${regionName}|${binIndex}`;
            matrixMap.set(key, (matrixMap.get(key) || 0) + 1);
        });

        const regions = Array.from(regionsSet).sort();

        const matrixData = [];
        regions.forEach(r => {
            bins.forEach(bin => {
                const count = matrixMap.get(`${r}|${bin.id}`) || 0;
                // Add data point (even if 0 count, for complete grid, or skip 0)
                // Matrix chart usually looks better if we only plot non-zero or use different color for zero
                if (count > 0) {
                    matrixData.push({
                        x: r,
                        y: bin.label,
                        v: count,
                        binId: bin.id
                    });
                }
            });
        });

        // Find max count for color scaling
        const maxCount = Math.max(...matrixData.map(d => d.v));

        if (myChart) {
            myChart.destroy();
        }

        myChart = new Chart(ctx, {
            type: 'matrix',
            data: {
                datasets: [{
                    label: 'Company Count',
                    data: matrixData,
                    backgroundColor(c) {
                        const value = c.dataset.data[c.dataIndex].v;
                        const binId = c.dataset.data[c.dataIndex].binId;
                        // Calculate alpha based on count intensity
                        const alpha = (value / maxCount) * 0.8 + 0.2; // Min 0.2 opacity
                        // Map Risk Level (binId) to Hue (Green 120 -> Red 0)
                        const hue = 120 - (binId * (120 / 9));
                        return `hsla(${hue}, 70%, 50%, ${alpha})`;
                    },
                    borderColor(c) {
                        return 'rgba(0,0,0,0.1)';
                    },
                    borderWidth: 1,
                    width: ({ chart }) => (chart.chartArea || {}).width / regions.length - 2,
                    height: ({ chart }) => (chart.chartArea || {}).height / bins.length - 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        type: 'category',
                        labels: regions,
                        title: {
                            display: true,
                            text: xLabel
                        },
                        grid: {
                            display: false
                        },
                        ticks: {
                            autoSkip: true,
                            maxRotation: 45,
                            minRotation: 45
                        }
                    },
                    y: {
                        type: 'category',
                        labels: bins.map(b => b.label).reverse(), // Show High risk (0.9-1.0) at top? Or numeric asc?
                        // Usually charts have 0 at bottom. 'reverse()' would put 0.9 at top if labels are 0.0..0.9
                        // Let's check array order. bins is 0.0 to 0.9.
                        // Category scale renders first label at Top/Left usually? No, usually bottom-up for cartesian? 
                        // 'category' scale in Chart.js usually renders index 0 at Left/Top.
                        // So if we want 0.0 at bottom, we need to reverse labels array passed to scale?
                        // Or just let it be. Standard matrix usually X (Top/Bottom), Y (Left/Right).
                        // Let's assume we want ascending risk 0->1 from bottom to top.
                        // If labels are [0..1], and we want 0 at bottom, we might need to reverse?
                        // Actually, let's list them naturally.
                        offset: true,
                        title: {
                            display: true,
                            text: 'Risk Concentration Range'
                        },
                        grid: {
                            display: true,
                            color: '#f0f0f0'
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            title() { return ''; },
                            label(context) {
                                const d = context.raw;
                                return `${d.x} (${d.y}): ${d.v} Companies`;
                            }
                        }
                    }
                }
            }
        });
    }

    // Initialize Filters
    let continents, countries, cities;

    // --- ONGOING DISRUPTION ALERT ---
    // Fetch initially without filters to check for ongoing events
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

    dateFilterCheckbox.addEventListener('change', (event) => {
        const existingDateFilter = document.getElementById('date-filter-container');

        if (event.currentTarget.checked) {
            if (!existingDateFilter) {
                const dateContainer = document.createElement('div');
                dateContainer.id = 'date-filter-container';
                dateContainer.style.marginBottom = '15px';
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