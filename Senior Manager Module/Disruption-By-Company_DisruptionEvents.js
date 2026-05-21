/* Page-specific logic for Disruptions by Company  */
document.addEventListener("DOMContentLoaded", () => {
            const dynamicFiltersContainer = document.getElementById('dynamic-filters-container');

            /*  Multi-Select Company Filter */

            // Create the company search filter directly
            const companyContainer = document.createElement('div');
            companyContainer.id = 'company-filter-container';
            companyContainer.classList.add('filter-options');
            companyContainer.style.marginBottom = '15px';

            const companyLabel = document.createElement('label');
            companyLabel.textContent = 'Companies: ';
            companyLabel.style.marginRight = '5px';

            const multiSelectContainer = document.createElement('div');
            multiSelectContainer.style.display = 'inline-block';
            multiSelectContainer.style.position = 'relative';
            multiSelectContainer.style.width = 'calc(100% - 90px)';

            // This div holds the "selected company" chips 
            const selectedCompaniesDiv = document.createElement('div');
            selectedCompaniesDiv.id = 'selected-companies';
            selectedCompaniesDiv.style.display = 'flex';
            selectedCompaniesDiv.style.flexWrap = 'wrap';
            selectedCompaniesDiv.style.gap = '5px';
            selectedCompaniesDiv.style.marginBottom = '5px';
            selectedCompaniesDiv.style.padding = '5px';
            selectedCompaniesDiv.style.border = '1px solid #ccc';
            selectedCompaniesDiv.style.minHeight = '30px';

            // Inline search to find companies and also add them as tags
            const searchInput = document.createElement('input');
            searchInput.type = 'text';
            searchInput.id = 'company-multi-search';
            searchInput.placeholder = 'Search...';
            searchInput.style.width = '100%';
            /*searchInput.style.border = 'none';
            searchInput.style.outline = 'none';*/

            //Drop down with company suggestions
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

            selectedCompaniesDiv.appendChild(searchInput);
            multiSelectContainer.appendChild(selectedCompaniesDiv);
            multiSelectContainer.appendChild(suggestionsUl);

            companyContainer.appendChild(companyLabel);
            companyContainer.appendChild(multiSelectContainer);
            dynamicFiltersContainer.appendChild(companyContainer);

            /* Optional Filter: Disruption Type  */

            // Optional text-based filter for disruption category this is not required
            const optionalFilterContainer = document.createElement('div');
            optionalFilterContainer.classList.add('filter-options');
            optionalFilterContainer.style.marginTop = '15px';

            const typeLabel = document.createElement('label');
            typeLabel.textContent = 'Disruption Type (Optional): ';
            typeLabel.style.display = 'block';
            typeLabel.style.textAlign = 'left';
            typeLabel.style.marginBottom = '5px';

            const typeSearchContainer = document.createElement('div');
            typeSearchContainer.style.position = 'relative';

            const typeSearchInput = document.createElement('input');
            typeSearchInput.type = 'text';
            typeSearchInput.id = 'disruption-type-search';
            typeSearchInput.placeholder = 'Search disruption type...';
            typeSearchInput.style.width = '100%';
            typeSearchInput.style.padding = '8px';
            typeSearchInput.style.boxSizing = 'border-box'; // Ensure padding doesn't overflow width

            const typeSuggestionsUl = document.createElement('ul');
            typeSuggestionsUl.id = 'disruption-type-suggestions';
            typeSuggestionsUl.style.listStyle = 'none';
            typeSuggestionsUl.style.padding = '0';
            typeSuggestionsUl.style.margin = '0';
            typeSuggestionsUl.style.border = '1px solid #ccc';
            typeSuggestionsUl.style.position = 'absolute';
            typeSuggestionsUl.style.width = '100%';
            typeSuggestionsUl.style.backgroundColor = 'white';
            typeSuggestionsUl.style.zIndex = '1001';
            typeSuggestionsUl.style.maxHeight = '150px';
            typeSuggestionsUl.style.overflowY = 'auto';

            typeSearchContainer.appendChild(typeSearchInput);
            typeSearchContainer.appendChild(typeSuggestionsUl);

            optionalFilterContainer.appendChild(typeLabel);
            optionalFilterContainer.appendChild(typeSearchContainer);
            dynamicFiltersContainer.appendChild(optionalFilterContainer);

            // Disruption Type Search Logic
            typeSearchInput.addEventListener('input', () => {
                const value = typeSearchInput.value.trim();
                typeSuggestionsUl.innerHTML = '';
                if (value === '') return;

                // Use get_disruption_events.php which implements category search
                fetch(`get_disruption_events.php?q=${encodeURIComponent(value)}`)
                    .then(response => response.json())
                    .then(data => {
                        // Filter for categories only or just use what's returned
                        // get_disruption_events returns both categories and events
                        // We prefer categories for this filter
                        const categories = data.filter(item => item.type === 'category');

                        // If no categories found, maybe show events or just nothing?
                        // Let's show categories first
                        const itemsToShow = categories.length > 0 ? categories : [];

                        itemsToShow.forEach(item => {
                            const li = document.createElement('li');
                            // Display text: "Cyber Attack (All Events)" -> "Cyber Attack"
                            // item.display has "(All Events)", item.value has "Cyber Attack"
                            li.textContent = item.value;
                            li.dataset.value = item.value;
                            li.style.padding = '8px';
                            li.style.cursor = 'pointer';
                            li.addEventListener('mouseover', () => li.style.backgroundColor = '#f0f0f0');
                            li.addEventListener('mouseout', () => li.style.backgroundColor = 'white');

                            li.addEventListener('click', () => {
                                typeSearchInput.value = item.value;
                                typeSuggestionsUl.innerHTML = '';
                            });
                            typeSuggestionsUl.appendChild(li);
                        });
                    })
                    .catch(e => console.error("Error fetching disruption types:", e));
            });

            // Hide suggestions if user clicks outside
            document.addEventListener("click", (e) => {
                if (!typeSearchContainer.contains(e.target)) {
                    typeSuggestionsUl.innerHTML = "";
                }
            });

            // Add search logic
            searchInput.addEventListener('input', () => {
                const value = searchInput.value.trim();
                suggestionsUl.innerHTML = '';
                if (value === '') return;

                // Assumes get_companies.php is in the same directory
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
                                // Prevent duplicate tags for the same company
                                const existing = Array.from(selectedCompaniesDiv.querySelectorAll('.company-tag')).find(tag => tag.textContent.slice(0, -1).trim() === company.CompanyName);
                                if (!existing) {
                                    const companyTag = document.createElement('span');
                                    companyTag.className = 'company-tag';
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
                                    selectedCompaniesDiv.insertBefore(companyTag, searchInput);
                                }
                                // Reset search field and also suggestions when selected
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

            /* Global references for results, charts, etc */

            const goButton = document.getElementById('go-button');
            const clearButton = document.getElementById('clear-filters');
            const resultsArea = document.getElementById('results-area');
            const placeholder = document.getElementById('table-container-placeholder');
            const totalHeader = document.getElementById('total-disruptions-header');
            const totalCountSpan = document.getElementById('total-count');
            const tableOutput = document.getElementById('table-output');
            const lineChartCanvas = document.getElementById('lineChart');
            const barChartCanvas = document.getElementById('barChart');
            let lineChart = null;
            let barChart = null;

            // Expand/Enlarge Functionality
            window.toggleExpand = function (btn) {
                const container = btn.parentElement;
                const isExpanded = container.classList.contains('expanded-view');

                if (!isExpanded) {
                    // Expanding
                    container.classList.add('expanded-view');
                    btn.innerHTML = '&#x2715;'; // Close/X icon
                    btn.title = "Close Expanded View";
                    document.body.style.overflow = 'hidden'; // Prevent background scroll
                } else {
                    // Collapsing
                    container.classList.remove('expanded-view');
                    btn.innerHTML = '&#x2922;'; // Enlarge icon
                    btn.title = "Enlarge";
                    document.body.style.overflow = ''; // Restore scroll

                    // Force reset height styles that might linger
                    if (container.querySelector('canvas')) {
                        container.style.height = ''; // Remove any inline height
                        /*container.style.width = '20vw';*/
                        const graphInner = container.querySelector('.graph-inner-container');
                        if (graphInner){
                            graphInner.style.height = '100%';
                            /*graphInner.style.width = '45vw';*/
                        }
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

            // Toggle Filters Logic
            const filterToggle = document.getElementById('filterToggle');
            const contentDiv = document.querySelector('.content');
            if (filterToggle && contentDiv) {
                filterToggle.addEventListener('click', () => {
                    contentDiv.classList.toggle('filters-hidden');
                    if (contentDiv.classList.contains('filters-hidden')) {
                        filterToggle.innerHTML = '&#9654;'; // Point Right
                        filterToggle.title = "Show Filters";
                        filterToggle.style.left = "0";
                    } else {
                        filterToggle.innerHTML = '&#9664;'; // Point Left
                        filterToggle.title = "Hide Filters";
                        filterToggle.style.left = "30vw";
                    }
                    window.dispatchEvent(new Event('resize'));
                });
            }

            // Clear Filters Logic
            if (clearButton) {
                clearButton.addEventListener('click', () => {
                    // Clear selected companies
                    const selectedCompaniesDiv = document.getElementById('selected-companies');
                    if (selectedCompaniesDiv) {
                        const tags = selectedCompaniesDiv.querySelectorAll('.company-tag');
                        tags.forEach(tag => tag.remove());
                    }
                    // Clear disruption type search
                    const typeSearchInput = document.getElementById('disruption-type-search');
                    if (typeSearchInput) typeSearchInput.value = '';

                    // Reset results area
                    if (resultsArea) resultsArea.style.display = 'none';
                    if (placeholder) placeholder.style.display = 'block';
                    if (totalHeader) totalHeader.style.display = 'none';
                    if (totalCountSpan) totalCountSpan.textContent = '0';
                    if (tableOutput) tableOutput.innerHTML = '';
                    if (lineChart) {
                        lineChart.destroy();
                        lineChart = null;
                    }
                    if (barChart) {
                        barChart.destroy();
                        barChart = null;
                    }
                });
            }

            goButton.addEventListener('click', () => {
                contentDiv.classList.add('filters-hidden');
                filterToggle.style.left = "0";
                resultsArea.style.display = "block";
                // Collect selected companies
                let companies = [];
                Array.from(selectedCompaniesDiv.querySelectorAll('.company-tag')).forEach(tag => {
                    companies.push(tag.textContent.slice(0, -1).trim());
                });

                if (companies.length === 0) {
                    alert("Please select at least one company.");
                    return;
                }

                const params = new URLSearchParams();
                params.append('companies', companies.join('|'));

                // Add optional disruption type filter
                const typeSearchInput = document.getElementById('disruption-type-search');
                if (typeSearchInput && typeSearchInput.value) {
                    params.append('disruption_type', typeSearchInput.value);
                }

                fetch(`get_company_disruptions.php?${params.toString()}`)
                    .then(r => r.json())
                    .then(data => {
                        if (data.error) {
                            alert(data.error);
                            return;
                        }

                        // Show Results Area
                        placeholder.style.display = 'none';
                        resultsArea.style.display = 'block';

                        // Update Total Count
                        totalCountSpan.textContent = data.total_disruptions;
                        totalHeader.style.display = 'block';

                        renderTable(data.table_data);
                        renderLineChart(data.line_chart_data);
                        renderBarChart(data.bar_chart_data);
                    })
                    .catch(err => {
                        console.error("Error fetching data:", err);
                        alert("Failed to load analysis.");
                    });
            });

            // Create a simple HTML table from the rows provided by the backend
            function renderTable(data) {
                if (!data || data.length === 0) {
                    tableOutput.innerHTML = '<p>No disruption events found.</p>';
                    return;
                }

                const table = document.createElement('table');
                table.style.width = '100%';
                table.style.borderCollapse = 'collapse';

                const thead = document.createElement('thead');
                thead.innerHTML = `
            <tr>
                <th style="border: 1px solid #ddd; padding: 8px; background-color: #f2f2f2;">Company</th>
                <th style="border: 1px solid #ddd; padding: 8px; background-color: #f2f2f2;">Event</th>
                <th style="border: 1px solid #ddd; padding: 8px; background-color: #f2f2f2;">Impact</th>
                <th style="border: 1px solid #ddd; padding: 8px; background-color: #f2f2f2;">Date</th>
            </tr>
        `;
                table.appendChild(thead);

                const tbody = document.createElement('tbody');
                data.forEach(row => {
                    const tr = document.createElement('tr');
                    // Use loose check for null/undefined/empty
                    if (!row.EventRecoveryDate) {
                        tr.style.color = 'red';
                    }
                    tr.innerHTML = `
                <td style="border: 1px solid #ddd; padding: 8px; text-align: center;">${row.CompanyName}</td>
                <td style="border: 1px solid #ddd; padding: 8px; text-align: center;">${row.event}</td>
                <td style="border: 1px solid #ddd; padding: 8px; text-align: center;">${row.impact}</td>
                <td style="border: 1px solid #ddd; padding: 8px; text-align: center;">${row.date}</td>
            `;
                    tbody.appendChild(tr);
                });
                table.appendChild(tbody);

                tableOutput.innerHTML = '';
                tableOutput.appendChild(table);
            }

            /* Line Chart of Distrubtions Over Time  */
            function renderLineChart(data) {
                const ctx = lineChartCanvas.getContext('2d');
                if (lineChart) lineChart.destroy();

                // Each point corresponds to a month or period
                lineChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: data.map(d => d.period),
                        datasets: [{
                            label: 'Disruption Events',
                            data: data.map(d => d.count),
                            borderColor: 'rgba(75, 192, 192, 1)',
                            backgroundColor: 'rgba(75, 192, 192, 0.2)',
                            fill: true,
                            tension: 0.1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false, // Let the container control the height
                        plugins: {
                            title: { display: true, text: 'Disruptions Over Time (Monthly)' },
                            legend: { display: false }
                        },
                        scales: {
                            y: { beginAtZero: true, ticks: { stepSize: 1 } },
                            x: { title: { display: true, text: 'Date (YYYY-MM)' } }
                        }
                    }
                });
            }

            function renderBarChart(data) {
                const ctx = barChartCanvas.getContext('2d');
                if (barChart) barChart.destroy();

                // Generate distinct colors
                const colors = [
                    '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40'
                ];

                barChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: data.map(d => d.CategoryName),
                        datasets: [{
                            label: 'Occurrences',
                            data: data.map(d => d.count),
                            backgroundColor: data.map((_, i) => colors[i % colors.length]),
                            borderColor: data.map((_, i) => colors[i % colors.length]),
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            title: { display: true, text: 'Disruption Types' },
                            legend: { display: false }
                        },
                        scales: {
                            y: { beginAtZero: true, ticks: { stepSize: 1 } }
                        }
                    }
                });
            }
        });