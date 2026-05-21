/*This is the section of page that handles the filters and the data display.*/
document.addEventListener("DOMContentLoaded", () => {
    const companyFilterCheckbox = document.getElementById('companyFilter');
    const dateFilterCheckbox = document.getElementById('dateFilter');
    const typeFilterCheckbox = document.getElementById('typeFilter');
    const dynamicFiltersContainer = document.getElementById('dynamic-filters-container');
    const goButton = document.getElementById('go-button');
    const clearButton = document.getElementById('clear-filters');
    const tableContainer = document.getElementById('table-container');
    const graphContainer = document.getElementById('graph-container');
    const placeholder = document.getElementById('placeholder-message');
    const resultsContainer = document.querySelector('.data-display-container');
    let myChart = null;
    let currentData = []; // Store fetched data for limit changing

    // This is the Limit Dropdown for the charts (5,10,20,25,50,all).
    function renderLimitDropdown() {
        if (document.getElementById('chart-limit-container')) return;

        const chartCon = document.querySelector('.chart_con');
        if (!chartCon) return;

        //Create the container for the limit dropdown
        const container = document.createElement('div');
        container.id = 'chart-limit-container';
        container.classList.add('chart-limit-container');

        //Creates the label for the limit dropdown
        const label = document.createElement('label');
        label.innerText = 'Show: ';
        label.classList.add('chart-limit-label');

        //Creates the select dropdown for the limit dropdown
        const select = document.createElement('select');
        select.id = 'chart-limit-select';
        select.classList.add('chart-limit-select');

        [5, 10, 15, 20, 25, 50, 'All'].forEach(num => {
            const opt = document.createElement('option');
            opt.value = num === 'All' ? 'all' : num;
            opt.innerText = num;
            if (num === 10) opt.selected = true; // Default
            select.appendChild(opt);
        });
        //When the user selects a new limit, the chart is updated with the new limit.
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

    // Expand/Enlarge Functionality for tables and graphs
    window.toggleExpand = function (btn) {
        const container = btn.parentElement;
        const isExpanded = container.classList.contains('expanded-view');

        if (!isExpanded) {
            // Expanding. Enter fullscreen mode
            container.classList.add('expanded-view');
            btn.innerHTML = '&#x2715;'; // Close/X icon
            btn.title = "Close Expanded View";
            document.body.classList.add('no-scroll'); // Prevent background scroll
        } else {
            // Collapsing. Exit fullscreen mode
            container.classList.remove('expanded-view');
            btn.innerHTML = '&#x2922;'; // Enlarge icon
            btn.title = "Enlarge";
            document.body.classList.remove('no-scroll'); // Restore scroll

            // Force reset of styles that might still be there from fullscreen mode
            if (container.querySelector('canvas')) {
                container.style.height = ''; // Remove any inline height
                const graphContainer = container.querySelector('#graph-container');
                if (graphContainer) graphContainer.style.height = '100%';
            }
        }

        // Resize for Charts
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

    // Show/hide filter panel
    const filterToggle = document.getElementById('filterToggle');
    const contentDiv = document.querySelector('.content');
    if (filterToggle && contentDiv) {
        filterToggle.addEventListener('click', () => {
            contentDiv.classList.toggle('filters-hidden');

            //Update the arrow direction
            if (contentDiv.classList.contains('filters-hidden')) {
                filterToggle.innerHTML = '&#9654;'; // Point Right
                filterToggle.title = "Show Filters";
                // filterToggle.style.left = "0"; // Handled by CSS

            } else {
                filterToggle.innerHTML = '&#9664;'; // Point Left
                filterToggle.title = "Hide Filters";
                // filterToggle.style.left = "30vw"; // Handled by CSS
            }
            window.dispatchEvent(new Event('resize'));
        });
    }

    // Clear Filters Logic
    if (clearButton) {
        clearButton.addEventListener('click', () => {
            //Uncheck all boxes and trigger their change
            if (companyFilterCheckbox) { companyFilterCheckbox.checked = false; companyFilterCheckbox.dispatchEvent(new Event('change')); }
            if (dateFilterCheckbox) { dateFilterCheckbox.checked = false; dateFilterCheckbox.dispatchEvent(new Event('change')); }
            if (typeFilterCheckbox) { typeFilterCheckbox.checked = false; typeFilterCheckbox.dispatchEvent(new Event('change')); }

            // Reset results and show placeholder again
            if (placeholder) placeholder.classList.remove('hidden');
            if (resultsContainer) resultsContainer.classList.add('hidden');

            // Clear stored data
            currentData = [];

            // Remove limit dropdown
            const limitContainer = document.getElementById('chart-limit-container');
            if (limitContainer) limitContainer.remove();

            if (tableContainer) tableContainer.innerHTML = '';
            if (graphContainer) graphContainer.innerHTML = '';
            if (myChart) {
                myChart.destroy();
                myChart = null;
            }
        });
    }

        //Go button logic. Collects the filter values and fetches the data.
    goButton.addEventListener('click', () => {
        //hide toggle button
        // filterToggle.style.left = "0"; // Handled by CSS
        // 1. Collect Filter Values
        //Company filter
        let companies = [];
        const selectedCompaniesDiv = document.getElementById('selected-companies');
        if (selectedCompaniesDiv) {
            Array.from(selectedCompaniesDiv.querySelectorAll('.company-tag')).forEach(tag => {
                companies.push(tag.textContent.slice(0, -1).trim());
            });
        }
        //Year filter
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
        //Company type filter
        let companyType = '';
        const typeSelect = document.getElementById('companyTypeSelect');
        if (typeSelect) {
            companyType = typeSelect.value;
        }

        // 2. Build Query String
        const params = new URLSearchParams();
        if (companies.length > 0) params.append('companies', companies.join('|'));
        if (year) params.append('year', year);
        if (quarter) params.append('quarter', quarter);
        if (companyType) params.append('company_type', companyType);

        // 3. Fetch Data
        fetch(`get_financial_health_data.php?${params.toString()}`)
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
                //Hide the placeholders and show results
                if (placeholder) placeholder.classList.add('hidden');
                if (resultsContainer) resultsContainer.classList.remove('hidden');
                
                currentData = data; // Store data
                renderTable(data);

                renderLimitDropdown(); // Ensure dropdown is present
                //Determine chart limits
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
                console.error('Error fetching data:', error);
                tableContainer.innerHTML = `<p style="color:red;">Error loading data: ${error.message}</p>`;
                graphContainer.innerHTML = '';
            });
        contentDiv.classList.add('filters-hidden');
    });

    //Renders the table with the data
    function renderTable(data) {
        //If no data is found, show a message
        if (!data || data.length === 0) {
            tableContainer.innerHTML = '<p>No data found for the selected filters.</p>';
            return;
        }

        const table = document.createElement('table');
        table.classList.add('result-table');

        const thead = document.createElement('thead');
        const headerRow = document.createElement('tr');
        // Create table headers
        const headers = ["Company Name", "Financial Health Score", "Ranking"];

        headers.forEach(headerText => {
            const th = document.createElement('th');
            th.textContent = headerText;
            th.classList.add('result-th');
            headerRow.appendChild(th);
        });
        thead.appendChild(headerRow);
        table.appendChild(thead);

        //Create the table body
        const tbody = document.createElement('tbody');
        //Loop through the data and create a row for each company
        data.forEach((rowData, index) => {
            const row = document.createElement('tr');

            // Company Name
            const tdName = document.createElement('td');
            tdName.textContent = rowData.CompanyName;
            tdName.classList.add('result-td');
            row.appendChild(tdName);

            // Financial Health Score
            const tdScore = document.createElement('td');
            tdScore.textContent = rowData.health_score;
            tdScore.classList.add('result-td');
            row.appendChild(tdScore);

            // Ranking (1-based index since data is sorted descending)
            const tdRank = document.createElement('td');
            tdRank.textContent = index + 1;
            tdRank.classList.add('result-td');
            row.appendChild(tdRank);

            tbody.appendChild(row);
        });
        table.appendChild(tbody);

        tableContainer.innerHTML = '';
        tableContainer.appendChild(table);
    }

    //Renders the chart with the data
    function renderChart(data, limit = 10) {
        //If no data is found, return
        graphContainer.innerHTML = '';
        if (!data || data.length === 0) return;

        const canvas = document.createElement('canvas');
        canvas.id = 'healthChart';
        // Ensure chart fits well
        canvas.style.width = '100%';
        canvas.style.height = '100%';
        graphContainer.appendChild(canvas);

        const ctx = canvas.getContext('2d');

        // Apply limit
        const chartData = data.slice(0, limit);

        const labels = chartData.map(d => d.CompanyName);
        const scores = chartData.map(d => d.health_score);

        if (myChart) myChart.destroy();

        myChart = new Chart(ctx, {
            type: 'bar', //Creates a bar chart
            data: {
                labels: labels,
                datasets: [{
                    label: 'Financial Health Score',
                    data: scores,
                    backgroundColor: 'rgba(54, 162, 235, 0.6)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'x', // Vertical bar chart
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Score'
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
                    }
                }
            }
        });
    }

    //Company filter checkbox logic
    companyFilterCheckbox.addEventListener('change', (event) => {
        //Check if the company filter checkbox is checked
        const existingCompanyFilter = document.getElementById('company-filter-container');

        if (event.currentTarget.checked) {
            if (!existingCompanyFilter) {
                //Create the company container
                const companyContainer = document.createElement('div');
                companyContainer.id = 'company-filter-container';
                companyContainer.classList.add('company-filter-box');

                //Create the company label
                const companyLabel = document.createElement('label');
                companyLabel.textContent = 'Companies: ';
                companyLabel.classList.add('label-margin-right');

                //Create the multi select container
                const multiSelectContainer = document.createElement('div');
                multiSelectContainer.classList.add('multi-select-container');

                //Create the selected companies container
                const selectedCompaniesDiv = document.createElement('div');
                selectedCompaniesDiv.id = 'selected-companies';
                selectedCompaniesDiv.classList.add('selected-companies-box');

                //Create the search input
                const searchInput = document.createElement('input');
                searchInput.type = 'text';
                searchInput.id = 'company-multi-search';
                searchInput.placeholder = 'Search...';
                
                //Create a suggestions list
                const suggestionsUl = document.createElement('ul');
                suggestionsUl.id = 'company-suggestions';
                suggestionsUl.classList.add('suggestions-list');

                //Add the search input to the selected companies container
                selectedCompaniesDiv.appendChild(searchInput);
                multiSelectContainer.appendChild(selectedCompaniesDiv);
                multiSelectContainer.appendChild(suggestionsUl);
                //Add the company label to the company container
                companyContainer.appendChild(companyLabel);
                companyContainer.appendChild(multiSelectContainer);
                dynamicFiltersContainer.appendChild(companyContainer);

                // Add search logic for the companies
                searchInput.addEventListener('input', () => {
                    const value = searchInput.value.trim();
                    suggestionsUl.innerHTML = '';
                    if (value === '') return;

                    // Fetch the companies from the database
                    fetch(`get_companies.php?q=${encodeURIComponent(value)}`)
                        .then(response => response.json())
                        .then(data => {
                            data.forEach(company => {
                                const li = document.createElement('li');
                                li.textContent = company.CompanyName;
                                li.classList.add('suggestion-item');
                                //When the user clicks on a company, add it to the selected companies container
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

                                        //When the user clicks the remove button, remove the company from the selected companies container
                                        removeBtn.addEventListener('click', () => {
                                            companyTag.remove();
                                        });

                                        //Add the remove button to the company tag
                                        companyTag.appendChild(removeBtn);
                                        selectedCompaniesDiv.insertBefore(companyTag, searchInput);
                                    }
                                    //Reset the search input and suggestions list
                                    searchInput.value = '';
                                    suggestionsUl.innerHTML = '';
                                });
                                suggestionsUl.appendChild(li);
                            });
                        })
                        .catch(err => console.error("Error loading companies:", err));
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

    //Date filter checkbox logic
    dateFilterCheckbox.addEventListener('change', (event) => {
        //Check if the date filter checkbox is checked
        const existingDateFilter = document.getElementById('dynamic-date-filter-container');

        if (event.currentTarget.checked) {
            if (!existingDateFilter) {
                const dateContainer = document.createElement('div');
                dateContainer.id = 'dynamic-date-filter-container';
                dateContainer.classList.add('date-filter-box');

                //Create the year selector
                const yearLabel = document.createElement('label');
                yearLabel.textContent = 'Year: ';
                yearLabel.classList.add('label-margin-right');
                const yearSelect = document.createElement('select');
                yearSelect.id = 'yearSelect';

                // Fetch available years from the database
                fetch('get_financial_years.php')
                    .then(response => response.json())
                    .then(years => {
                        if (years.error) {
                            console.error("Error fetching years:", years.error);
                            return;
                        }
                        years.forEach(year => {
                            const option = document.createElement('option');
                            option.value = year;
                            option.textContent = year;
                            yearSelect.appendChild(option);
                        });
                    })
                    .catch(error => console.error('Error fetching years:', error));


                //Create the quarter selector
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

    //Company type filter checkbox logic
    typeFilterCheckbox.addEventListener('change', (event) => {
        const existingTypeFilter = document.getElementById('type-filter-container');

        if (event.currentTarget.checked) {
            if (!existingTypeFilter) {
                const typeContainer = document.createElement('div');
                typeContainer.id = 'type-filter-container';
                typeContainer.classList.add('type-filter-box');

                const typeLabel = document.createElement('label');
                typeLabel.textContent = 'Company Type: ';
                typeLabel.classList.add('label-margin-right');

                const typeSelect = document.createElement('select');
                typeSelect.id = 'companyTypeSelect';

                const types = ["Manufacturer", "Distributor", "Retailer"];
                types.forEach(typeName => {
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
            if (existingTypeFilter) {
                existingTypeFilter.remove();
            }
        }
    });
});