// Expand/Enlarge functionality. Allows the user to expand the chart and table to the full width of the page.
window.toggleExpand = function (btn) {
    const container = btn.parentElement;
    const isExpanded = container.classList.contains('expanded-view');

    if (!isExpanded) {
        // Expanding the chart and table to the full width of the page
        container.classList.add('expanded-view');
        btn.innerHTML = '&#x2715;'; // Close/X icon
        btn.title = "Close Expanded View";
        document.body.style.overflow = 'hidden'; // Prevent background scroll
    } else {
        // Collapsing the chart and table to the original size
        container.classList.remove('expanded-view');
        btn.innerHTML = '&#x2922;'; // Enlarge icon
        btn.title = "Enlarge";
        document.body.style.overflow = ''; // Restore scroll

        // Force reset height styles
        if (container.querySelector('canvas')) {
            container.style.height = ''; // Remove any inline height
            const graphContainer = container.querySelector('.graph-container');
            if (graphContainer) graphContainer.style.height = '100%';
        }
    }

    // Resize for the charts
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
    const tableOutput = document.getElementById('table-output');
    const goButton = document.getElementById('go-button');
    const clearButton = document.getElementById('clear-filters');
    const placeholder = document.getElementById('placeholder-message');
    const resultsContainer = document.querySelector('.data-display-container');
    let myChart = null;

    // Toggle filter bar logic it allows the user to show and hide the filter bar
    const filterToggle = document.getElementById('filterToggle');
    const contentDiv = document.querySelector('.content');
    if (filterToggle && contentDiv) {
        filterToggle.addEventListener('click', () => {
            contentDiv.classList.toggle('filters-hidden');
            if (contentDiv.classList.contains('filters-hidden')) {
                filterToggle.innerHTML = '&#9654;'; // Arrow points Right
                filterToggle.title = "Show Filters";
                // filterToggle.style.left = "0"; // Handled by CSS
            } else {
                filterToggle.innerHTML = '&#9664;'; // Arrow points Left
                filterToggle.title = "Hide Filters";
                // filterToggle.style.left = "30vw"; // Handled by CSS
            }
            window.dispatchEvent(new Event('resize'));
        });
    }

    // Clear filters button logic that allows the user to clear all filters
    if (clearButton) {
        clearButton.addEventListener('click', () => {
            const fromInput = document.getElementById('dynamic-date-from');
            const toInput = document.getElementById('dynamic-date-to');
            if (fromInput) fromInput.value = '';
            if (toInput) toInput.value = '';
            // Reset filter options to default
            const granSelect = document.getElementById('granularity-select');
            if (granSelect) granSelect.value = 'month';


            // Reset results to their default
            if (placeholder) placeholder.classList.remove('hidden');
            if (resultsContainer) resultsContainer.classList.add('hidden');
            if (tableOutput) tableOutput.innerHTML = '';
            if (myChart) {
                myChart.destroy();
                myChart = null;
            }
        });
    }

    // Date range filter. Allows the user to select a date range to analyze
    const dateContainer = document.createElement('div');
    dateContainer.id = 'dynamic-date-filter-container';
    dateContainer.classList.add('filter-options');
    dateContainer.style.marginTop = '10px';

    // From label and input
    const fromLabel = document.createElement('label');
    fromLabel.textContent = 'From: ';
    const fromInput = document.createElement('input');
    fromInput.type = 'date';
    fromInput.id = 'dynamic-date-from';

    const fromDiv = document.createElement('div');
    fromDiv.className = 'filter-item';
    fromDiv.appendChild(fromLabel);
    fromDiv.appendChild(fromInput);

    // To label and input
    const toLabel = document.createElement('label');
    toLabel.textContent = ' To: ';
    const toInput = document.createElement('input');
    toInput.type = 'date';
    toInput.id = 'dynamic-date-to';

    const toDiv = document.createElement('div');
    toDiv.className = 'filter-item';
    toDiv.appendChild(toLabel);
    toDiv.appendChild(toInput);

    dateContainer.appendChild(fromDiv);
    dateContainer.appendChild(toDiv);

    dynamicFiltersContainer.appendChild(dateContainer);

    // Granularity selector that allows the user to select the how they want to group their data to be displayed
    const granContainer = document.createElement('div');
    granContainer.classList.add('filter-options');
    granContainer.classList.add('granularity-container');
    // granContainer.style.marginTop = '15px'; // Handled by CSS class if added, kept for specific spacing

    const granLabel = document.createElement('label');
    granLabel.textContent = 'Group By: ';
    granLabel.classList.add('granularity-label');
    // granLabel.style.marginRight = '5px'; // Handled by CSS class

    const granSelect = document.createElement('select');
    granSelect.id = 'granularity-select';

    ['Day', 'Month', 'Year'].forEach(opt => {
        const option = document.createElement('option');
        option.value = opt.toLowerCase();
        option.textContent = opt;
        if (opt === 'Month') {
            option.selected = true;
        }
        granSelect.appendChild(option);
    });

    granContainer.appendChild(granLabel);
    granContainer.appendChild(granSelect);

    dynamicFiltersContainer.appendChild(granContainer);

    // Go button logic it collects the filter values and fetches the data
    goButton.addEventListener('click', () => {
        contentDiv.classList.add('filters-hidden');
        // filterToggle.style.left = "0"; // Handled by CSS
        const dateFrom = document.getElementById('dynamic-date-from').value;
        const dateTo = document.getElementById('dynamic-date-to').value;
        const granularity = document.getElementById('granularity-select').value;

        // Date validation 
        if (!dateFrom || !dateTo) {
            alert("Please select both a start and end date.");
            return;
        }
        if (dateFrom > dateTo) {
            alert("Start date cannot be after the end date.");
            return;
        }

        // Build URL parameters
        const params = new URLSearchParams();
        if (dateFrom) params.append('date_from', dateFrom);
        if (dateTo) params.append('date_to', dateTo);
        if (granularity) params.append('granularity', granularity);

        // Fetch the data from the server through the get_disruption_frequency_over_time.php file
        fetch(`get_disruption_frequency_over_time.php?${params.toString()}`)
            .then(response => {
                if (!response.ok) throw new Error("Network response was not ok");
                return response.json();
            })
            .then(data => {
                if (placeholder) placeholder.classList.add('hidden');
                if (resultsContainer) resultsContainer.classList.remove('hidden');

                renderChart(data.chart_data, granularity);
                renderTable(data.table_data);
            })
            .catch(error => {
                console.error("Error:", error);
                if (placeholder) placeholder.classList.remove('hidden');
                placeholder.innerHTML = `<p class="error-msg">Error loading data: ${error.message}</p>`;
                if (resultsContainer) resultsContainer.classList.add('hidden');
                tableOutput.innerHTML = '';
            });
    });

    // Creates the table and displays the data in a table format
    function renderTable(data) {
        if (!data || data.length === 0) {
            tableOutput.innerHTML = '<p style="text-align:center;">No detailed events found for selected range.</p>';
            return;
        }

        const table = document.createElement('table');
        table.classList.add('result-table');
        // table.style.width = '100%'; // Handled by CSS
        // table.style.borderCollapse = 'collapse'; // Handled by CSS

        const thead = document.createElement('thead');
        thead.innerHTML = `
            <tr>
                <th class="result-th">Company Name</th>
                <th class="result-th">Disruption Event Type</th>
                <th class="result-th">Recovery Time</th>
                <th class="result-th">Date</th>
            </tr>
        `;
        table.appendChild(thead);

        const tbody = document.createElement('tbody');
        data.forEach(row => {
            const tr = document.createElement('tr');

            let dateDisplay = row.date;
            if (row.is_active) {
                tr.classList.add('error-msg'); // Using error-msg class for red color
                dateDisplay = `Disruption Event Currently Active (${row.date})`;
            }

            tr.innerHTML = `
                <td class="result-td">${row.CompanyName}</td>
                <td class="result-td">${row.event_type}</td>
                <td class="result-td">${row.recovery_time}</td>
                <td class="result-td">${dateDisplay}</td>
            `;
            tbody.appendChild(tr);
        });
        table.appendChild(tbody);

        tableOutput.innerHTML = '';
        tableOutput.appendChild(table);
    }

    // Creates the line chart and displays the data
    function renderChart(data, granularity) {

        const ctx = document.getElementById('freqChart').getContext('2d');

        const labels = data.map(item => item.event_date);
        const frequencies = data.map(item => item.frequency);

        if (myChart) myChart.destroy();

        // Capitalize the granularity for the title
        const granTitle = granularity.charAt(0).toUpperCase() + granularity.slice(1);
        //Creates the chart using the Chart.js library
        myChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Disruption Frequency',
                    data: frequencies,
                    borderColor: 'rgba(75, 192, 192, 1)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    fill: true,
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: 'Date (' + granTitle + ')'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Number of Disruptions'
                        },
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    }
});