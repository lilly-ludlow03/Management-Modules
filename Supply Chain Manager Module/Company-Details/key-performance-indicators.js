

const input = document.getElementById("companySearch");
const suggestions = document.getElementById("suggest");

// Autocomplete using get_companies.php
input.addEventListener("input", () => {
    const value = input.value.trim();
    suggestions.innerHTML = "";
    if (value === "") {
        suggestions.style.display = 'none';
        return;
    }

    // Assume get_companies.php is in the same folder or adjusted relative path
    fetch(`get_companies.php?q=${encodeURIComponent(value)}`)
        .then(r => r.json())
        .then(data => {
            if (data.length > 0) {
                suggestions.style.display = 'block';
                data.forEach(company => {
                    const li = document.createElement("li");
                    li.textContent = company.CompanyName;
                    li.addEventListener("click", () => {
                        input.value = company.CompanyName;
                        suggestions.innerHTML = "";
                        suggestions.style.display = 'none';
                    });
                    suggestions.appendChild(li);
                });
            } else {
                suggestions.style.display = 'none';
            }
        })
        .catch(e => console.error("Error fetching companies:", e));
});

// Hide suggestions when clicking outside
document.addEventListener('click', function (e) {
    if (!input.contains(e.target) && !suggestions.contains(e.target)) {
        suggestions.style.display = 'none';
    }
});

const dateFrom = document.getElementById("dateFrom");
const dateTo = document.getElementById("dateTo");
const goButton = document.getElementById('go-button');

// Clear Filters Logic
document.getElementById('clear-filters').addEventListener('click', () => {
    document.getElementById('companySearch').value = '';
    document.getElementById('dateFrom').value = '';
    document.getElementById('dateTo').value = '';
    
    const resultsArea = document.getElementById('results-area');
    resultsArea.classList.add('hidden');
    resultsArea.style.display = ''; // Clear inline style

    const placeholder = document.getElementById('placeholder-message');
    if (placeholder) {
        placeholder.classList.remove('hidden');
        placeholder.style.display = ''; // Clear inline style
    }
});

// Toggle Filters Logic
const filterToggle = document.getElementById('filterToggle');
const contentDiv = document.querySelector('.content');

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

    // If it's the chart container, trigger resize to fill new space
    if (container.querySelector('canvas')) {
        setTimeout(() => {
            // Force chart update/resize
            window.dispatchEvent(new Event('resize'));
        }, 50);
    }
}

// Chart instance variable
let eventsLineChart = null;

function validateDates() {
    // Check if one is present but the other is missing
    if ((dateFrom.value && !dateTo.value) || (!dateFrom.value && dateTo.value)) {
        alert("Please select both a start date and an end date.");
        return false;
    }
    if (dateFrom.value && dateTo.value) {
        const from = new Date(dateFrom.value);
        const to = new Date(dateTo.value);
        if (from > to) {
            alert("Start date cannot be after end date");
            return false;
        }
    }
    return true;
}

goButton.addEventListener("click", function () {
    const resultsArea = document.getElementById("results-area");
    const placeholder = document.getElementById('placeholder-message');
    contentDiv.classList.add('filters-hidden');
    filterToggle.style.left = "0";

    if (!input.value.trim()) {
        alert("Please enter a company name.");
        return;
    }
    if (!validateDates()) return;

    // Fetch KPI Data
    const params = new URLSearchParams({
        company: input.value,
        date_from: dateFrom.value,
        date_to: dateTo.value
    });

    fetch(`get_kpi_data.php?${params.toString()}`)
        .then(r => {
            if (!r.ok) {
                throw new Error(`Server returned ${r.status}`);
            }
            return r.text().then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    throw new Error("Invalid JSON response: " + text.substring(0, 100));
                }
            });
        })
        .then(data => {
            if (data.error) {
                alert("Error: " + data.error);
                return;
            }

            resultsArea.classList.remove('hidden');
            resultsArea.style.display = ''; // Clear inline style if present
            
            if (placeholder) {
                placeholder.classList.add('hidden');
                placeholder.style.display = ''; // Clear inline style if present
            }

            // Populate List
            document.getElementById('kpi-name').textContent = data.stats.name;
            document.getElementById('kpi-rate').textContent = data.stats.on_time_rate;
            document.getElementById('kpi-avg-delay').textContent = data.stats.avg_delay;
            document.getElementById('kpi-std-dev').textContent = data.stats.std_dev_delay;

            // Display Financial Health Quarters
            const fh = data.stats.fin_health_quarters;
            if (fh) {
                document.getElementById('kpi-health').innerHTML = `
                            <div class="kpi-health-details">
                                <strong>Q1:</strong> ${fh.Q1} &nbsp; 
                                <strong>Q2:</strong> ${fh.Q2} &nbsp; 
                                <strong>Q3:</strong> ${fh.Q3} &nbsp; 
                                <strong>Q4:</strong> ${fh.Q4}
                            </div>
                        `;
            } else {
                document.getElementById('kpi-health').textContent = data.stats.fin_health;
            }

            document.getElementById('kpi-dist').textContent = data.stats.distribution;

            // Populate Table
            const tbody = document.querySelector('#events_table tbody');
            tbody.innerHTML = '';
            if (data.table.length === 0) {
                tbody.innerHTML = '<tr><td colspan="3">No data found</td></tr>';
            } else {
                data.table.forEach(row => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `<td>${row.date}</td><td>${row.event}</td><td>${row.shipment}</td>`; // Keeping .shipment key from PHP for simplicity
                    tbody.appendChild(tr);
                });
            }

            // Render Graph
            renderGraph(data.graph);
        })
        .catch(err => {
            console.error(err);
            alert("Failed to load data: " + err.message);
        });
});

function renderGraph(data) {
    if (!data || !Array.isArray(data)) {
        console.warn("Graph data is invalid/empty");
        if (eventsLineChart) eventsLineChart.destroy();
        return;
    }

    const canvas = document.getElementById('eventsLineChart');
    if (!canvas) {
        console.error("Canvas element not found");
        return;
    }
    const ctx = canvas.getContext('2d');

    if (typeof Chart === 'undefined') {
        throw new Error("Chart.js library is not loaded");
    }

    if (eventsLineChart) {
        eventsLineChart.destroy();
    }

    // Financial Health Graph Configuration
    // Data is expected to be [{quarter: 1, score: val}, ...]
    const labels = data.map(d => "Q" + d.quarter);
    const values = data.map(d => d.score);

    // Update Title
    const header = document.querySelector('.chart_con h3');
    if (header) header.textContent = "Financial Health Score (Quarters)";

    eventsLineChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels, // ["Q1", "Q2", "Q3", "Q4"]
            datasets: [{
                label: 'Financial Health Score',
                data: values,
                borderColor: '#2e7d32', // Green theme
                backgroundColor: 'rgba(46, 125, 50, 0.2)',
                borderWidth: 2,
                tension: 0.1,
                fill: true,
                spanGaps: true // Connect lines even if data is missing
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true, // Scores usually range 0-100 or similar
                    title: {
                        display: true,
                        text: 'Health Score'
                    },
                    ticks: {
                        precision: 0
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Quarter'
                    }
                }
            }
        }
    });
}