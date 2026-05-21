// Toggle filter button logic. Allows the user to show and hide the filter bar.
const filterToggle = document.getElementById('filterToggle');
const contentDiv = document.querySelector('.content');
const plotDiv = document.querySelector('.plot');
if (filterToggle && contentDiv) {
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

// Expand/Enlarge functionality. Allows the user to expand and contract the table and graph.
window.toggleExpand = function (btn) {
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
    // Use requestAnimationFrame to wait for layout to settle
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

document.addEventListener("DOMContentLoaded", () => {
    const dynamicFiltersContainer = document.getElementById('dynamic-filters-container');
    const companyFilterCheckbox = document.getElementById('companyFilter');
    const dateFilterCheckbox = document.getElementById('dateFilter');
    const typeFilterCheckbox = document.getElementById('typeFilter');
    const goButton = document.getElementById('go-button');
    const clearButton = document.getElementById('clear-filters');
    let criticalityChart = null;
    let currentData = [];

    // Helper to create Limit Dropdown. Allows the user to select the number of results to display.
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

    // Clear filters button logic. Allows the user to clear all filters and reset the page.
    if (clearButton) {
        clearButton.addEventListener('click', () => {
            if (companyFilterCheckbox) { companyFilterCheckbox.checked = false; companyFilterCheckbox.dispatchEvent(new Event('change')); }
            if (dateFilterCheckbox) { dateFilterCheckbox.checked = false; dateFilterCheckbox.dispatchEvent(new Event('change')); }
            if (typeFilterCheckbox) { typeFilterCheckbox.checked = false; typeFilterCheckbox.dispatchEvent(new Event('change')); }

            if (dynamicFiltersContainer) dynamicFiltersContainer.innerHTML = '';

            const resultsArea = document.getElementById('results-area');
            const placeholder = document.getElementById('placeholder-message');

            if (resultsArea) resultsArea.classList.add('hidden');
            if (placeholder) placeholder.classList.remove('hidden');

            // Clear stored data
            currentData = [];

            // Remove limit dropdown
            const limitContainer = document.getElementById('chart-limit-container');
            if (limitContainer) limitContainer.remove();

            const tableContainer = document.getElementById('table-container');
            if (tableContainer) tableContainer.innerHTML = '';

            if (criticalityChart) {
                criticalityChart.destroy();
                criticalityChart = null;
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

                    // Fetch companies from the database via get_companies.php
                    fetch(`get_companies.php?q=${encodeURIComponent(value)}`)
                        .then(response => response.json())
                        .then(data => {
                            // Render the companies in the dropdown.
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
                                        removeBtn.addEventListener('click', () => companyTag.remove());

                                        companyTag.appendChild(removeBtn);
                                        selectedCompaniesDiv.insertBefore(companyTag, searchInput);
                                    }
                                    searchInput.value = '';
                                    suggestionsUl.innerHTML = '';
                                });
                                suggestionsUl.appendChild(li);
                            });
                        })
                        .catch(err => console.error("Error loading companies:", err));
                });
                document.addEventListener("click", (e) => {
                    if (!multiSelectContainer.contains(e.target)) {
                        suggestionsUl.innerHTML = "";
                    }
                });
            }
        } else {
            const existingCompanyFilter = document.getElementById('company-filter-container');
            if (existingCompanyFilter) existingCompanyFilter.remove();
        }
    });

    // Date filter checkbox logic. Allows the user to select the date of financial health they want to analyze.
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

    // Company type filter checkbox logic. Allows the user to select the type of company they want to analyze.
    typeFilterCheckbox.addEventListener('change', (event) => {
        const existingTypeFilter = document.getElementById('type-filter-container');
        if (event.currentTarget.checked) {
            if (!existingTypeFilter) {
                const typeContainer = document.createElement('div');
                typeContainer.id = 'type-filter-container';
                typeContainer.classList.add('type-filter-box');

                const typeLabel = document.createElement('label');
                typeLabel.textContent = 'Company Type: ';
                typeLabel.classList.add('type-label-style');

                const typeSelect = document.createElement('select');
                typeSelect.id = 'companyTypeSelect';

                ["Manufacturer", "Distributor", "Retailer"].forEach(typeName => {
                    const option = document.createElement('option');
                    option.value = typeName;
                    option.textContent = typeName;
                    typeSelect.appendChild(option);
                });

                typeContainer.appendChild(typeLabel);
                typeContainer.appendChild(typeSelect);
                dynamicFiltersContainer.appendChild(typeContainer);
            }
        } else {
            if (existingTypeFilter) existingTypeFilter.remove();
        }
    });

    // Go button logic. Allows the user to display the results of the selected filters.
    goButton.addEventListener('click', () => {
        contentDiv.classList.add('filters-hidden');
        filterToggle.style.left = "0";
        const resultsArea = document.getElementById('results-area');
        const placeholder = document.getElementById('placeholder-message');

        let companies = [];
        const selectedCompaniesDiv = document.getElementById('selected-companies');
        if (selectedCompaniesDiv) {
            companies = Array.from(selectedCompaniesDiv.querySelectorAll('.company-tag')).map(tag => tag.textContent.slice(0, -1).trim());
        }

        const startDateInput = document.getElementById('startDate');
        const endDateInput = document.getElementById('endDate');
        const startDate = startDateInput ? startDateInput.value : '';
        const endDate = endDateInput ? endDateInput.value : '';

        // Date error messaging. Ensure the user inputs a valid date range
        if (dateFilterCheckbox.checked) {
            if ((startDate && !endDate) || (!startDate && endDate)) {
                alert("Please select both a Start Date and an End Date.");
                return;
            }
            if (startDate && endDate && startDate > endDate) {
                alert("Start Date cannot be after End Date.");
                return;
            }
        }

        let companyType = '';
        const typeSelect = document.getElementById('companyTypeSelect');
        if (typeSelect) companyType = typeSelect.value;

        const params = new URLSearchParams();
        if (companies.length > 0) params.append('companies', companies.join('|'));
        if (startDate) params.append('start_date', startDate);
        if (endDate) params.append('end_date', endDate);
        if (companyType) params.append('company_type', companyType);

        // Fetch criticality data from the database via get_criticality_data.php
        fetch(`get_criticality_data.php?${params.toString()}`)
            .then(response => response.ok ? response.json() : Promise.reject(`Network error: ${response.statusText}`))
            .then(data => {
                // Error messaging. If there is an error, display the error message.
                if (data.error) {
                    alert(data.error);
                    return;
                }
                if (resultsArea) resultsArea.classList.remove('hidden');
                if (placeholder) placeholder.classList.add('hidden');

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
                if (tableContainer) tableContainer.innerHTML = `<p class="error-text">Error loading data: ${error.message}</p>`;
            });
    });

    // Render the table and display the data.
    function renderTable(data) {
        const tableContainer = document.getElementById('table-container');
        if (!data || data.length === 0) {
            tableContainer.innerHTML = '<p>No data found for selected filters.</p>';
            return;
        }
        let tableHTML = '<table class="result-table"><thead><tr>' +
            '<th class="result-th">Company Name</th>' +
            '<th class="result-th">Criticality</th>' +
            '<th class="result-th">Ranking</th>' +
            '</tr></thead><tbody>';
        data.forEach((row, index) => {
            tableHTML += `<tr>
                                <td class="result-td">${row.CompanyName}</td>
                                <td class="result-td">${row.Criticality}</td>
                                <td class="result-td">${index + 1}</td>
                              </tr>`;
        });
        tableHTML += '</tbody></table>';
        tableContainer.innerHTML = tableHTML;
    }

    // Render the chart and display the data.
    function renderChart(data, limit = 10) {
        const canvas = document.getElementById('criticalityChart');
        if (!canvas) return;
        const ctx = canvas.getContext('2d');

        if (criticalityChart) criticalityChart.destroy();

        const chartData = data.slice(0, limit);

        criticalityChart = new Chart(ctx, {
            type: 'bar', //Bar chart
            data: {
                labels: chartData.map(d => d.CompanyName),
                datasets: [{
                    label: 'Criticality Score',
                    data: chartData.map(d => d.Criticality),
                    backgroundColor: 'rgba(40, 87, 51, 0.6)', // Green theme
                    borderColor: 'rgba(40, 87, 51, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                scales: {
                    x: { beginAtZero: true, title: { display: true, text: 'Criticality Score' } }
                },
                plugins: {
                    legend: { display: false },
                    title: { display: true, text: 'Top Critical Companies' }
                }
            }
        });
    }
});