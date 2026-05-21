
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


const input = document.getElementById("companySearch");
const suggestions = document.getElementById("suggest");

input.addEventListener("input", () => {
    const value = input.value.trim();
    suggestions.innerHTML = "";
    if (value === "") {
        suggestions.style.display = 'none';
        return;
    }

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
        document.getElementById('results-area').classList.add('hidden');
        document.getElementById('results-area').style.display = ''; // Clear inline style if set by JS
        const placeholder = document.getElementById('placeholder-message');
        if (placeholder) {
            placeholder.classList.remove('hidden');
            placeholder.style.display = ''; // Clear inline style
        }
    });

    function validateDates() {
        // ... (rest of validateDates remains the same)
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
        const pop_con = document.getElementById("pop_con");
        const pltcon = document.getElementById("pltcon");
        const resultsArea = document.getElementById('results-area');
        const placeholder = document.getElementById('placeholder-message');
        contentDiv.classList.add('filters-hidden');
        filterToggle.style.left = "0";

        // Validate Company (Mandatory)
        if (!input.value.trim()) {
            alert("Please enter a company name.");
            return;
        }

        if (!validateDates()) return;

        // Fetch Data
        const params = new URLSearchParams({
            company: input.value,
            date_from: dateFrom.value,
            date_to: dateTo.value
        });

        fetch(`get_company_info_details.php?${params.toString()}`)
            .then(r => r.json())
            .then(data => {
                if (data.error) {
                    alert("Error: " + data.error);
                    return;
                }

                resultsArea.classList.remove('hidden');
                resultsArea.style.display = ''; // Clear inline style
                
                if (placeholder) {
                    placeholder.classList.add('hidden');
                    placeholder.style.display = ''; // Clear inline style
                }

                // Populate Company Details
                const c = data.company;
                pop_con.innerHTML = `
                            <h2>Company Information</h2>
                            <p><strong>Company Name:</strong> ${c.CompanyName}</p>
                            <p><strong>Location:</strong> ${c.location}</p>
                            <p><strong>Company Type:</strong> ${c.company_type}</p>
                            <p><strong>Tier Level:</strong> ${c.tier_level}</p>
                            <p><strong>Depends On:</strong> ${c.depends_on}</p>
                            <p><strong>Dependents:</strong> ${c.dependents}</p>
                            <p><strong>Capacity:</strong> ${c.FactoryCapacity}</p>
                            <p><strong>Route:</strong> ${c.route}</p>
                            <p><strong>Products:</strong> ${c.products}</p>
                            <p><strong>Diversity of Products:</strong> ${c.diversity}</p>
                            <p><strong>Most Recent Financial Health Score:</strong> ${c.financial_health}</p>
                        `;
                pop_con.classList.remove('hidden');
                pop_con.style.display = ''; // Clear inline style

                // Populate Shipping Table
                const shipBody = document.querySelector('#shipping_table tbody');
                shipBody.innerHTML = '';
                if (data.shipping.length === 0) {
                    shipBody.innerHTML = '<tr><td colspan="3">No shipping data found</td></tr>';
                } else {
                    data.shipping.forEach(row => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `<td>${row.date}</td><td>${row.product}</td><td>${row.volume}</td>`;
                        shipBody.appendChild(tr);
                    });
                }

                // Populate Receiving Table
                let recTable = document.getElementById('receiving_table');
                let recBody = recTable.querySelector('tbody');
                if (!recBody) {
                    recBody = document.createElement('tbody');
                    recTable.appendChild(recBody);
                }

                recBody.innerHTML = '';
                if (data.receiving.length === 0) {
                    recBody.innerHTML = '<tr><td colspan="3">No receiving data found</td></tr>';
                } else {
                    data.receiving.forEach(row => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `<td>${row.date}</td><td>${row.product}</td><td>${row.volume}</td>`;
                        recBody.appendChild(tr);
                    });
                }

                // Populate Adjustment Table
                let adjTable = document.getElementById('adjustment_table');
                let adjBody = adjTable.querySelector('tbody');
                if (!adjBody) {
                    adjBody = document.createElement('tbody');
                    adjTable.appendChild(adjBody);
                }

                adjBody.innerHTML = '';
                if (data.adjustments.length === 0) {
                    adjBody.innerHTML = '<tr><td colspan="3">No adjustment data found</td></tr>';
                } else {
                    data.adjustments.forEach(row => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `<td>${row.date}</td><td>${row.product}</td><td>${row.volume}</td>`;
                        adjBody.appendChild(tr);
                    });
                }

                // Show results area containers
                if (resultsArea) {
                    // pop_con and pltcon are children
                    pop_con.classList.remove('hidden');
                    pltcon.classList.remove('hidden');
                    pop_con.style.display = '';
                    pltcon.style.display = '';
                }
            })
            .catch(err => {
                console.error(err);
                alert("Failed to load data.");
            });
    });