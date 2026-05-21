// cache references to update dropdown and all dynamic form elements
// constants store references to form sections that will be dynamcally shown/hidden
const updatedd = document.getElementById('update');
const uptype = document.getElementById('uptype'); // type update form
const uptier = document.getElementById('uptier'); // tier update form
const updep = document.getElementById('updep'); // add dependency form
const updepon = document.getElementById('updepon'); // remove dependency form
const upcap = document.getElementById('upcap'); // update capacity form
const uproute = document.getElementById('uproute'); 
const addroute = document.getElementById('addroute'); // add route form
const removeroute = document.getElementById('removeroute'); // remove route form
const addproduct = document.getElementById('addproduct'); // add product form
const removeproduct = document.getElementById('removeproduct'); // remove product form
const updateprice = document.getElementById('updateprice'); // update price form
const uptrans = document.getElementById('uptrans'); // transaction update form
const info = document.getElementById('info'); // current company information display
const upprod = document.getElementById('upprod');

// fired when the user selectes an update option from the dropdown
updatedd.addEventListener('change', function () {
    const pop_con = document.getElementById("pop_con");
    const pltcon = document.getElementById("pltcon");
    const companySearchContainer = document.getElementById("company-search-container");

    // collect all dynamically dislayed forms into an array for easy hiding
    const allForms = [
        document.getElementById('upadd'), document.getElementById('uptype'),
        document.getElementById('uptier'), document.getElementById('updep'),
        document.getElementById('updepon'), document.getElementById('upcap'),
        document.getElementById('addroute'), document.getElementById('removeroute'),
        document.getElementById('addproduct'), document.getElementById('removeproduct'),
        document.getElementById('updateprice'),
        document.getElementById('uproute'), document.getElementById('upprod'),
        document.getElementById('updiv'), document.getElementById('uptrans')
    ];

    // step 1: hide all forms when dropdown changes
    allForms.forEach(form => {
        if (form) form.style.display = 'none';
    });
    
    // hide containers that should disappear on each selection
    companySearchContainer.style.display = 'none';
    if (pltcon) pltcon.style.display = 'none';
    // open the popup container box
    if (pop_con) pop_con.classList.add("show");

    const selectedValue = updatedd.value; // currently selected update type

    // Step 2: show main company search bar for selected update types
    const needsMainCompanySearch = ['Type', 'Tier', 'capacity'];
    if (needsMainCompanySearch.includes(selectedValue)) {
        companySearchContainer.style.display = 'block';
    }

    // step 3: display the correct corresponding form based on dropdown selection
    if (selectedValue === 'Type') document.getElementById('uptype').style.display = 'block';
    else if (selectedValue === 'Tier') document.getElementById('uptier').style.display = 'block';
    else if (selectedValue === 'dependants') document.getElementById('updep').style.display = 'block';
    else if (selectedValue === 'depends') document.getElementById('updepon').style.display = 'block';
    else if (selectedValue === 'capacity') document.getElementById('upcap').style.display = 'block';
    else if (selectedValue === 'addroute') document.getElementById('addroute').style.display = 'block';
    else if (selectedValue === 'removeroute') document.getElementById('removeroute').style.display = 'block';
    else if (selectedValue === 'addproduct') document.getElementById('addproduct').style.display = 'block';
    else if (selectedValue === 'removeproduct') document.getElementById('removeproduct').style.display = 'block';
    else if (selectedValue === 'updateprice') document.getElementById('updateprice').style.display = 'block';
    else if (selectedValue === 'Products') document.getElementById('upprod').style.display = 'block';
    // handle transaction update forms
    else if (selectedValue === 'existing' || selectedValue === 'new') {
        const transForm = document.getElementById('uptrans');
        // show transaction form
        transForm.style.display = 'block';
        document.getElementById('info').style.display = 'none';
        // identify the dropdown controlling transaction type
        const transTypeDropdown = document.getElementById('trans_type');
        const adjustmentOption = transTypeDropdown.querySelector('option[value="adj"]');
        // if updating an existing transaction, hide the adjustment option
        if (selectedValue === 'existing') {
            if (adjustmentOption) adjustmentOption.style.display = 'none';
            // reset value if user was previously on adjustment
            if (transTypeDropdown.value === 'adj') {
                transTypeDropdown.value = "";
                transTypeDropdown.dispatchEvent(new Event('change'));
            }
        } 
        // if creating a new transaction, show the adjusment option
        else {
            if (adjustmentOption) adjustmentOption.style.display = 'block';
        }
    }
});

/* add route form submission handler
validates input, checks if route exists, then opens confirmation window */
addroute.addEventListener('submit', function(event) {
    event.preventDefault();
    // get user-entered values
    const distributor = document.getElementById('addroute-distributor').value.trim();
    const fromCompany = document.getElementById('addroute-from').value.trim();
    const toCompany = document.getElementById('addroute-to').value.trim();

    // basic validation
    if (distributor === '' || fromCompany === '' || toCompany === '') {
        alert('Please fill out all fields: Distributor, From Company, and To Company.');
        return;
    }

    // prevent identical from and to companies
    if (fromCompany.toLowerCase() === toCompany.toLowerCase()) {
        alert('The "From" and "To" companies cannot be the same.');
        return;
    }

    // Check if the route already exists before showing confirmation
    fetch(`update_company.php?action=check_route&distributor=${encodeURIComponent(distributor)}&from_company=${encodeURIComponent(fromCompany)}&to_company=${encodeURIComponent(toCompany)}`)
        .then(response => response.json())
        .then(data => {
            if (data.exists) {
                alert('This logistics route already exists in the database.');
            } else {
                // show modal for user confirmation
                showAddRouteConfirmation(distributor, fromCompany, toCompany);
            }
        })
        .catch(error => {
            console.error('Error checking route:', error);
            alert('An error occurred while verifying the route.');
        });
});

// displays confirmation modal for adding a new route
function showAddRouteConfirmation(distributor, fromCompany, toCompany) {
    const pltcon = document.getElementById('pltcon');
    const infoDiv = document.getElementById('info');
    
    // display modal container
    pltcon.style.display = 'block';
    // populate modal details
    infoDiv.innerHTML = `
        <h2>Add Route?</h2>
        <p><strong>Distributor:</strong> ${distributor}</p>
        <p><strong>From Company:</strong> ${fromCompany}</p>
        <p><strong>To Company:</strong> ${toCompany}</p>
        <div style="margin-top: 20px;">
            <button id="add-route-confirm" class="confirm-btn">Confirm</button>
            <button id="add-route-cancel" class="cancel-btn">Cancel</button>
        </div>
    `;
    infoDiv.style.display = 'block';

    // confirm add route
    document.getElementById('add-route-confirm').addEventListener('click', function() {
        const body = `action=add_route&distributor=${encodeURIComponent(distributor)}&from_company=${encodeURIComponent(fromCompany)}&to_company=${encodeURIComponent(toCompany)}`;
        fetch('update_company.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body
        })
        .then(response => response.json())
        .then(data => {
            alert(data.message);
            if (data.status === 'success') {
                pltcon.style.display = 'none';
                document.getElementById('addroute').reset(); // Clear the form
            }
        })
        .catch(error => {
            console.error('Error adding route:', error);
            alert('A critical error occurred while adding the route.');
        });
    });

    // cancel button
    document.getElementById('add-route-cancel').addEventListener('click', function() {
        pltcon.style.display = 'none';
    });
}

// remove route logic
// form submission handler
removeroute.addEventListener('submit', function(event) {
    event.preventDefault();
    // get user entered values
    const distributor = document.getElementById('removeroute-distributor').value.trim();
    const fromCompany = document.getElementById('removeroute-from').value.trim();
    const toCompany = document.getElementById('removeroute-to').value.trim();

    // validation
    if (distributor === '' || fromCompany === '' || toCompany === '') {
        alert('Please fill out all fields: Distributor, From Company, and To Company.');
        return;
    }

    if (fromCompany.toLowerCase() === toCompany.toLowerCase()) {
        alert('The "From" and "To" companies cannot be the same.');
        return;
    }
    
    // verify route exists before deletion
    fetch(`update_company.php?action=check_route&distributor=${encodeURIComponent(distributor)}&from_company=${encodeURIComponent(fromCompany)}&to_company=${encodeURIComponent(toCompany)}`)
        .then(response => response.json())
        .then(data => {
            if (!data.exists) {
                alert('This logistics route does not exist and cannot be removed.');
            } else {
                showRemoveRouteConfirmation(distributor, fromCompany, toCompany);
            }
        })
        .catch(error => {
            console.error('Error checking route:', error);
            alert('An error occurred while verifying the route.');
        });
});

// confirmation modal for removing a route
function showRemoveRouteConfirmation(distributor, fromCompany, toCompany) {
    const pltcon = document.getElementById('pltcon');
    const infoDiv = document.getElementById('info');
    
    // display modal container
    pltcon.style.display = 'block';
    infoDiv.innerHTML = `
        <h2>Remove Route?</h2>
        <p><strong>Distributor:</strong> ${distributor}</p>
        <p><strong>From Company:</strong> ${fromCompany}</p>
        <p><strong>To Company:</strong> ${toCompany}</p>
        <div style="margin-top: 20px;">
            <button id="remove-route-confirm" class="confirm-btn">Confirm</button>
            <button id="remove-route-cancel" class="cancel-btn">Cancel</button>
        </div>
    `;
    infoDiv.style.display = 'block';

    // confirm remove route
    document.getElementById('remove-route-confirm').addEventListener('click', function() {
        const body = `action=remove_route&distributor=${encodeURIComponent(distributor)}&from_company=${encodeURIComponent(fromCompany)}&to_company=${encodeURIComponent(toCompany)}`;
        // send request to server
        fetch('update_company.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body
        })
        // handle response from server
        .then(response => response.json())
        .then(data => {
            alert(data.message);
            if (data.status === 'success') {
                pltcon.style.display = 'none';
                document.getElementById('removeroute').reset(); // Clear the form
            }
        })
        // handle error from server if necessary
        .catch(error => {
            console.error('Error removing route:', error);
            alert('A critical error occurred while removing the route.');
        });
    });

    // cancel button
    document.getElementById('remove-route-cancel').addEventListener('click', function() {
        pltcon.style.display = 'none';
    });
}

// add product supply logic
// add product form submission handler
addproduct.addEventListener('submit', function(event) {
    event.preventDefault();
    // get user entered values
    const manufacturer = document.getElementById('addproduct-manufacturer').value.trim();
    const product = document.getElementById('add-product-search').value.trim();
    const price = document.getElementById('add-product-price').value.trim();

    // validation
    if (manufacturer === '' || product === '' || price === '') {
        alert('Please fill out all fields: Manufacturer, Product, and Price.');
        return;
    }
    // check if price is a valid number
    if (isNaN(price)) {
        alert('Price must be a valid number.');
        return;
    }

    // check that manufacturer-product relationship doesn't already exist
    fetch(`update_company.php?action=check_supply&manufacturer=${encodeURIComponent(manufacturer)}&product=${encodeURIComponent(product)}`)
        .then(response => response.json())
        // handle response from server
        .then(data => {
            if (data.exists) {
                alert('This manufacturer already supplies this product.');
            } else if (data.error) {
                alert('Error: ' + data.error);
            } else {
                showAddProductConfirmation(manufacturer, product, price);
            }
        })
        // handle error from server
        .catch(error => {
            console.error('Error checking supply:', error);
            alert('An error occurred while verifying the product supply.');
        });
});

// confirmation modal for adding a product supply
function showAddProductConfirmation(manufacturer, product, price) {
    // display modal container
    const pltcon = document.getElementById('pltcon');
    const infoDiv = document.getElementById('info');
    
    // display modal content
    pltcon.style.display = 'block';
    infoDiv.innerHTML = `
        <h2>Add Product Supply?</h2>
        <p><strong>Manufacturer:</strong> ${manufacturer}</p>
        <p><strong>Product:</strong> ${product}</p>
        <p><strong>Price:</strong> ${price}</p>
        <div style="margin-top: 20px;">
            <button id="add-product-confirm" class="confirm-btn">Confirm</button>
            <button id="add-product-cancel" class="cancel-btn">Cancel</button>
        </div>
    `;
    infoDiv.style.display = 'block';

    // confirm add product supply
    document.getElementById('add-product-confirm').addEventListener('click', function() {
        // send request to server
        const body = `action=add_supply&manufacturer=${encodeURIComponent(manufacturer)}&product=${encodeURIComponent(product)}&price=${encodeURIComponent(price)}`;
        fetch('update_company.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body
        })
        // handle response from server
        .then(response => response.json())
        .then(data => {
            alert(data.message);
            if (data.status === 'success') {
                pltcon.style.display = 'none';
                document.getElementById('addproduct').reset();
            }
        })
        // handle error from server
        .catch(error => {
            console.error('Error adding supply:', error);
            alert('A critical error occurred while adding the supply.');
        });
    });

    // cancel button
    document.getElementById('add-product-cancel').addEventListener('click', function() {
        pltcon.style.display = 'none';
    });
}

// remove product supply logic
// form submission handler
removeproduct.addEventListener('submit', function(event) {
    // prevent default form submission
    event.preventDefault();
    // get user entered values
    const manufacturer = document.getElementById('removeproduct-manufacturer').value.trim();
    const product = document.getElementById('remove-product-search').value.trim();

    // validation
    if (manufacturer === '' || product === '') {
        alert('Please fill out all fields: Manufacturer and Product.');
        return;
    }

    // Check if the supply relationship exists before trying to remove it
    fetch(`update_company.php?action=check_supply&manufacturer=${encodeURIComponent(manufacturer)}&product=${encodeURIComponent(product)}`)
        .then(response => response.json())
        // handle response from server
        .then(data => {
            if (!data.exists) {
                alert('This manufacturer does not supply this product, so it cannot be removed.');
            } else if (data.error) {
                 alert('Error: ' + data.error);
            } else {
                showRemoveProductConfirmation(manufacturer, product);
            }
        })
        // handle error from server
        .catch(error => {
            console.error('Error checking supply:', error);
            alert('An error occurred while verifying the product supply.');
        });
});

// confirmation modal for removing a product supply
function showRemoveProductConfirmation(manufacturer, product) {
    // display modal container
    const pltcon = document.getElementById('pltcon');
    const infoDiv = document.getElementById('info');

    // display content
    pltcon.style.display = 'block';
    infoDiv.innerHTML = `
        <h2>Remove Product Supply?</h2>
        <p><strong>Manufacturer:</strong> ${manufacturer}</p>
        <p><strong>Product:</strong> ${product}</p>
        <div style="margin-top: 20px;">
            <button id="remove-product-confirm" class="confirm-btn">Confirm</button>
            <button id="remove-product-cancel" class="cancel-btn">Cancel</button>
        </div>
    `;
    infoDiv.style.display = 'block';

    // confirm remove product
    document.getElementById('remove-product-confirm').addEventListener('click', function() {
        // create request body
        const body = `action=remove_supply&manufacturer=${encodeURIComponent(manufacturer)}&product=${encodeURIComponent(product)}`;
        // send request to server
        fetch('update_company.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body
        })
        // handle response from server
        .then(response => response.json())
        .then(data => {
            alert(data.message);
            if (data.status === 'success') {
                pltcon.style.display = 'none';
                document.getElementById('removeproduct').reset();
            }
        })
        // handle error from server
        .catch(error => {
            console.error('Error removing supply:', error);
            alert('A critical error occurred while removing the supply.');
        });
    });

    // cancel button
    document.getElementById('remove-product-cancel').addEventListener('click', function() {
        pltcon.style.display = 'none';
    });
}

// update product price logic
// form submission handler
updateprice.addEventListener('submit', function(event) {
    // prevent default form submission
    event.preventDefault();
    // get user entered values
    const manufacturer = document.getElementById('updateprice-manufacturer').value.trim();
    const product = document.getElementById('updateprice-product').value.trim();
    const newPrice = document.getElementById('updateprice-price').value.trim();

    // validation
    if (manufacturer === '' || product === '' || newPrice === '') {
        alert('Please fill out all fields: Manufacturer, Product, and New Price.');
        return;
    }
    // check is new price is a valid number
    if (isNaN(newPrice)) {
        alert('New Price must be a valid number.');
        return;
    }

    // Check if the supply relationship exists and if the price is different
    fetch(`update_company.php?action=check_supply_price&manufacturer=${encodeURIComponent(manufacturer)}&product=${encodeURIComponent(product)}`)
        .then(response => response.json())
        // handle response from server
        .then(data => {
            if (!data.exists) {
                alert('This manufacturer does not supply this product, so the price cannot be updated.');
            } else if (data.price && parseFloat(newPrice) === parseFloat(data.price)) {
                alert('The new price is the same as the current price.');
            } else {
                showUpdatePriceConfirmation(manufacturer, product, newPrice);
            }
        })
        // handle error from server
        .catch(error => {
            console.error('Error checking supply price:', error);
            alert('An error occurred while verifying the product supply.');
        });
});

// confirmation modal for updating a product price
function showUpdatePriceConfirmation(manufacturer, product, newPrice) {
    // display container
    const pltcon = document.getElementById('pltcon');
    const infoDiv = document.getElementById('info');
    
    // display content
    pltcon.style.display = 'block';
    infoDiv.innerHTML = `
        <h2>Update Product Price?</h2>
        <p><strong>Manufacturer:</strong> ${manufacturer}</p>
        <p><strong>Product:</strong> ${product}</p>
        <p><strong>New Price:</strong> ${newPrice}</p>
        <div style="margin-top: 20px;">
            <button id="update-price-confirm" class="confirm-btn">Confirm</button>
            <button id="update-price-cancel" class="cancel-btn">Cancel</button>
        </div>
    `;
    infoDiv.style.display = 'block';

    // confirm update price
    document.getElementById('update-price-confirm').addEventListener('click', function() {
        // create request body
        const body = `action=update_price&manufacturer=${encodeURIComponent(manufacturer)}&product=${encodeURIComponent(product)}&price=${encodeURIComponent(newPrice)}`;
        // send request to server
        fetch('update_company.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body
        })
        // handle response from server
        .then(response => response.json())
        .then(data => {
            alert(data.message);
            if (data.status === 'success') {
                pltcon.style.display = 'none';
                document.getElementById('updateprice').reset();
            }
        })
        // handle error from server
        .catch(error => {
            console.error('Error updating price:', error);
            alert('A critical error occurred while updating the price.');
        });
    });

    // cancel button
    document.getElementById('update-price-cancel').addEventListener('click', function() {
        pltcon.style.display = 'none';
    });
}

// update transaction logic
// form submission handler
uptrans.addEventListener('submit', function(event) {
    // prevent default form submission
    event.preventDefault();
    // get main selection
    const mainSelection = updatedd.value;
    // handle new transaction
    if (mainSelection === 'new') {
        handleNewTransactionSubmit();
    } else if (mainSelection === 'existing') {
        handleExistingTransactionSubmit();
    }
});

// confirmation modal for updating a transaction
function showUpdateConfirmation(transactionId, updates, transType) {
    // display container
    const infoDiv = document.getElementById('info');
    const pltcon = document.getElementById('pltcon');
    // create confirmation html
    let confirmationHtml = '<h3>Confirm Your Updates</h3>';
    confirmationHtml += `<p>You are about to update Transaction ID: <strong>${transactionId}</strong> with the following changes:</p><ul>`;
    
    // add updates to confirmation html
    for (const [key, value] of Object.entries(updates)) {
        confirmationHtml += `<li><strong>${key}:</strong> ${value || 'N/A'}</li>`;
    }
    confirmationHtml += '</ul>';

    // add confirm and cancel buttons
    confirmationHtml += '<button id="confirm-update-btn" class="confirm-btn">Confirm</button>';
    confirmationHtml += '<button id="cancel-update-btn" class="cancel-btn">Cancel</button>';
    
    // set confirmation html and display container
    infoDiv.innerHTML = confirmationHtml;
    pltcon.style.display = 'block';
    infoDiv.style.display = 'block';

    // confirm update
    document.getElementById('confirm-update-btn').addEventListener('click', function() {
        // create form data
        const formData = new FormData();
        // determine action based on transaction type
        const action = transType === 'ship' ? 'update_shipping_transaction' : 'update_receiving_transaction';
        // append form data
        formData.append('action', action);
        formData.append('transaction_id', transactionId);
        formData.append('updates', JSON.stringify(updates));

        // send request to server
        fetch('update_company.php', {
            method: 'POST',
            body: formData
        })
        // handle response from server
        .then(response => response.json())
        .then(result => {
            if (result.status === 'success') {
                alert(result.message);
                resetExistingTransactionForm(transType);
            } else {
                throw new Error(result.message);
            }
        })
        // handle error from server
        .catch(error => {
            alert(`An error occurred: ${error.message}`);
        })
        // finally reset container and display
        .finally(() => {
            pltcon.style.display = 'none';
            infoDiv.innerHTML = '';
        });
    });

    // cancel button
    document.getElementById('cancel-update-btn').addEventListener('click', function() {
        pltcon.style.display = 'none';
        infoDiv.innerHTML = '';
    });
}

// get changed fields logic
function getChangedFields(selectedOptions, currentData, transType) {
    // initialize updates object
    const updates = {};
    // initialize error variable
    let error = null;

    // iterate through selected options
    selectedOptions.forEach(option => {
        if (error) return; 

        // initialize new value and current value
        let newValue = '';
        let currentValue = '';

        // switch statemetn to handle different options
        switch (option) {
            case 'Distributor':
                newValue = document.getElementById('update-distributor-search').value.trim();
                currentValue = currentData.Distributor;
                break;
            case 'Shipping Company':
                newValue = document.getElementById('update-shipping-company-search').value.trim();
                currentValue = currentData.ShippingCompany;
                break;
            case 'Receiving Company':
                newValue = document.getElementById('update-receiving-company-search').value.trim();
                currentValue = currentData.ReceivingCompany;
                break;
            case 'Product':
                newValue = document.getElementById('update-product-search').value.trim();
                currentValue = currentData.Product;
                break;
            case 'Promised Delivery Date':
                newValue = document.getElementById('update-promised-date').value.trim();
                currentValue = currentData.PromisedDate;
                break;
            case 'Actual Delivery Date':
                newValue = document.getElementById('update-actual-date').value.trim();
                currentValue = currentData.ActualDate || ''; // Handle null from DB
                break;
            case 'Date Received':
                newValue = document.getElementById('update-date-received').value.trim();
                currentValue = currentData.ReceivedDate;
                break;
            case 'Quantity':
                newValue = document.getElementById('update-quantity').value.trim();
                currentValue = String(currentData.Quantity); // Ensure comparison is string-to-string
                break;
        }

        // check if new value is empty and not actual delivery date
        if (newValue === '' && option !== 'Actual Delivery Date') { // Allow Actual Date to be cleared
            error = `The new value for "${option}" cannot be empty.`;
        } 
        // check if new value is different form current value
        else if (newValue.toLowerCase() !== (currentValue || '').toLowerCase()) {
            updates[option] = newValue;
        }
    });

    // check if error is present
    if (error) {
        return { error: error };
    }

    // return updates object
    return updates;
}

// reset existing transaction form logic
function resetExistingTransactionForm(transType) {
    // reset transaction id input
    document.getElementById('trans-id-input').value = '';
    
    // get container id based on transaction type
    const containerId = transType === 'ship' ? 'existing-shipping-update-container' : 'existing-receiving-update-container';
    const multiSelectContainer = document.getElementById(containerId);
    
    // clear selection if multi-select container is present
    if (multiSelectContainer && typeof multiSelectContainer.clearSelection === 'function') {
        multiSelectContainer.clearSelection();
    }
}

// handle new transaction submit logic
function handleNewTransactionSubmit() {
    // get transaction type dropdown element
    const transTypeDropdown = document.getElementById('trans_type');
    if (!transTypeDropdown) {
        alert('Error: Transaction type dropdown not found. Please refresh the page.');
        return;
    }
    // get transaction type
    const transType = transTypeDropdown.value;
    
    // check if transaction type is selected
    if (!transType || transType === '') {
        alert('Please select a transaction type (Shipping, Receiving, or Adjustment).');
        return;
    }

    // handle shipping transaction
    if (transType === 'ship') {
        // create details object
        const details = {
            // get values from search inputs
            distributor: document.getElementById('dist-company-search').value.trim(),
            shippingCompany: document.getElementById('ship-company-search').value.trim(),
            receivingCompany: document.getElementById('rec-company-search').value.trim(),
            product: document.getElementById('product-search').value.trim(),
            promisedDate: document.getElementById('trans-date').value.trim(),
            actualDate: document.getElementById('trans-actual-date').value.trim(),
            quantity: document.getElementById('trans-quantity').value.trim()
        };

        // check if all required fields are filled
        if (!details.distributor || !details.shippingCompany || !details.receivingCompany || !details.product || !details.promisedDate || !details.quantity) {
            alert('Please fill out all required fields for the shipping transaction.');
            return;
        }
        // check if shipping and receiving companies are the same
        if (details.shippingCompany.toLowerCase() === details.receivingCompany.toLowerCase()) {
            alert('Shipping and Receiving companies cannot be the same.');
            return;
        }
        // show confirmation modal
        showShippingConfirmation(details);

    } 
    // handles receiving transaction
    else if (transType === 'rec') {
        // create details object
        const details = {
            // get values from search inputs
            receivingCompany: document.getElementById('rec-company-search').value.trim(),
            transactionId: document.getElementById('trans-id-input').value.trim(),
            date: document.getElementById('trans-date').value.trim(),
            quantity: document.getElementById('trans-quantity').value.trim()
        };

        // check if all required fields are filled
        if (!details.receivingCompany || !details.transactionId || !details.date || !details.quantity) {
            alert('Please fill out all fields for the receiving transaction.');
            return;
        }
        // show confirmation modal
        showReceivingConfirmation(details);

    } 
    // handles adjustment transaction
    else if (transType === 'adj') {
        // create details object
        const details = {
            // get values from search inputs
            company: document.getElementById('adj-company-search').value.trim(),
            product: document.getElementById('product-search').value.trim(),
            date: document.getElementById('trans-date').value.trim(),
            quantity: document.getElementById('trans-quantity').value.trim(),
            reason: document.getElementById('trans-reason').value.trim()
        };

        // check if all required fields are filled
        if (!details.company || !details.product || !details.date || !details.quantity || !details.reason) {
            alert('Please fill out all fields for the adjustment transaction, including the reason.');
            return;
        }
        // show confirmation modal
        showAdjustmentConfirmation(details);
    }
}

// handle existing transaction submit logic
function handleExistingTransactionSubmit() {
    // get transaction type
    const transType = transTypeDropdown.value;
    const transactionId = document.getElementById('trans-id-input').value.trim();

    // check if transaction id is filled
    if (!transactionId) {
        alert('Please enter the Transaction ID of the shipment to update.');
        return;
    }

    // get multi-select container based on transaction type
    const multiSelectContainer = transType === 'ship' 
        ? document.getElementById('existing-shipping-update-container')
        : document.getElementById('existing-receiving-update-container');

    // get selected options
    const selectedOptions = multiSelectContainer.getSelectedOptions();
    if (selectedOptions.length === 0) {
        alert('Please select at least one field to update.');
        return;
    }

    // Fetch current data to compare
    fetch(`update_company.php?action=get_transaction_details&transaction_id=${transactionId}&type=${transType === 'ship' ? 'shipping' : 'receiving'}`)
        .then(response => response.json())
        // handle response from server
        .then(result => {
            // check if error is present
            if (result.status === 'error') {
                throw new Error(result.message);
            }

            // get current data and updates
            const currentData = result.data;
            const updates = getChangedFields(selectedOptions, currentData, transType);
            
            // check if error is present
            if (updates.error) {
                alert(updates.error);
                return;
            }
            
            // check if no changes are detected
            if (Object.keys(updates).length === 0) {
                alert('No changes detected. Please enter new values to update.');
                return;
            }

            // show confirmation modal
            showUpdateConfirmation(transactionId, updates, transType); 
        })
        // handle error from server
        .catch(error => {
            alert(`An error occurred: ${error.message}`);
        });
}

// confirmation modal for shipping transaction
function showShippingConfirmation(details) {
    // display container
    const pltcon = document.getElementById('pltcon');
    const infoDiv = document.getElementById('info');
    
    // Check if required elements exist
    if (!pltcon || !infoDiv) {
        alert('Error: Could not find confirmation dialog elements. Please refresh the page.');
        console.error('Missing elements: pltcon or info');
        return;
    }
    
    // Ensure popup container is visible
    const pop_con = document.getElementById('pop_con');
    if (pop_con) {
        pop_con.classList.add('show');
    }
    
    // display content
    pltcon.style.display = 'block';
    infoDiv.style.display = 'block';
    
    // Hide optional elements if they exist
    const header = document.getElementById('current-company-info-header');
    const transTbl = document.getElementById('trans_tbl');
    if (header) header.style.display = 'none';
    if (transTbl) transTbl.style.display = 'none';

    // create confirmation html
    let confirmationHtml = `
        <h2>Confirm Shipping Transaction?</h2>
        <p><strong>Distributor:</strong> ${details.distributor}</p>
        <p><strong>Shipping Company:</strong> ${details.shippingCompany}</p>
        <p><strong>Receiving Company:</strong> ${details.receivingCompany}</p>
        <p><strong>Product:</strong> ${details.product}</p>
        <p><strong>Promised Date:</strong> ${details.promisedDate}</p>
        <p><strong>Actual Date:</strong> ${details.actualDate || 'Not Provided'}</p>
        <p><strong>Quantity:</strong> ${details.quantity}</p>
        <div style="margin-top: 20px;">
            <button id="shipping-confirm" class="confirm-btn">Confirm</button>
            <button id="shipping-cancel" class="cancel-btn">Cancel</button>
        </div>
    `;
    infoDiv.innerHTML = confirmationHtml;

    // confirm button
    document.getElementById('shipping-confirm').addEventListener('click', () => {
        // create form data
        const body = new URLSearchParams(details);
        body.append('action', 'add_shipping_transaction');

        // send request to server
        fetch('update_company.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        })
        // handle response from server
        .then(response => response.json())
        .then(data => {
            alert(data.message);
            if (data.status === 'success') {
                pltcon.style.display = 'none';
                uptrans.reset();
                transTypeDropdown.value = '';
                transTypeDropdown.dispatchEvent(new Event('change'));
            }
        })
        // handle error from server
        .catch(error => {
            console.error('Error adding shipping transaction:', error);
            alert('A critical error occurred while adding the shipping transaction.');
        });
    });

    // cancel button
    document.getElementById('shipping-cancel').addEventListener('click', () => {
        pltcon.style.display = 'none';
    });
}

// confirmation modal for receiving transaction
function showReceivingConfirmation(details) {
    // display container
    const pltcon = document.getElementById('pltcon');
    const infoDiv = document.getElementById('info');
    
    // Check if required elements exist
    if (!pltcon || !infoDiv) {
        alert('Error: Could not find confirmation dialog elements. Please refresh the page.');
        console.error('Missing elements: pltcon or info');
        return;
    }
    
    // Ensure popup container is visible
    const pop_con = document.getElementById('pop_con');
    if (pop_con) {
        pop_con.classList.add('show');
    }
    
    pltcon.style.display = 'block';
    infoDiv.style.display = 'block';
    
    // Hide optional elements if they exist
    const header = document.getElementById('current-company-info-header');
    const transTbl = document.getElementById('trans_tbl');
    if (header) header.style.display = 'none';
    if (transTbl) transTbl.style.display = 'none';

    // create confirmation html
    let confirmationHtml = `
        <h2>Confirm Receiving Transaction?</h2>
        <p><strong>Receiving Company:</strong> ${details.receivingCompany}</p>
        <p><strong>Transaction ID of Shipment:</strong> ${details.transactionId}</p>
        <p><strong>Date Received:</strong> ${details.date}</p>
        <p><strong>Quantity:</strong> ${details.quantity}</p>
        <div style="margin-top: 20px;">
            <button id="receiving-confirm" class="confirm-btn">Confirm</button>
            <button id="receiving-cancel" class="cancel-btn">Cancel</button>
        </div>
    `;
    infoDiv.innerHTML = confirmationHtml;

    // confirm button
    document.getElementById('receiving-confirm').addEventListener('click', () => {
        // create form data
        const body = new URLSearchParams(details);
        body.append('action', 'add_receiving_transaction');

        // send request to server
        fetch('update_company.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        })
        // handle response from server
        .then(response => response.json())
        .then(data => {
            // display message
            alert(data.message);
            if (data.status === 'success') {
                pltcon.style.display = 'none';
                uptrans.reset();
                transTypeDropdown.value = '';
                transTypeDropdown.dispatchEvent(new Event('change'));
            }
        })
        // handle error from server
        .catch(error => {
            console.error('Error adding receiving transaction:', error);
            alert('A critical error occurred while adding the receiving transaction.');
        });
    });

    // cancel button
    document.getElementById('receiving-cancel').addEventListener('click', () => {
        pltcon.style.display = 'none';
    });
}

// confirmation modal for adjustment transaction
function showAdjustmentConfirmation(details) {
    // display container
    const pltcon = document.getElementById('pltcon');
    const infoDiv = document.getElementById('info');
    
    // Check if required elements exist
    if (!pltcon || !infoDiv) {
        alert('Error: Could not find confirmation dialog elements. Please refresh the page.');
        console.error('Missing elements: pltcon or info');
        return;
    }
    
    // Ensure popup container is visible
    const pop_con = document.getElementById('pop_con');
    if (pop_con) {
        pop_con.classList.add('show');
    }
    
    // display content
    pltcon.style.display = 'block';
    infoDiv.style.display = 'block';
    
    // Hide optional elements if they exist
    const header = document.getElementById('current-company-info-header');
    const transTbl = document.getElementById('trans_tbl');
    if (header) header.style.display = 'none';
    if (transTbl) transTbl.style.display = 'none';

    // create confirmation html
    let confirmationHtml = `
        <h2>Confirm Adjustment Transaction?</h2>
        <p><strong>Company:</strong> ${details.company}</p>
        <p><strong>Product:</strong> ${details.product}</p>
        <p><strong>Date:</strong> ${details.date}</p>
        <p><strong>Quantity Change:</strong> ${details.quantity}</p>
        <p><strong>Reason:</strong> ${details.reason}</p>
        <div style="margin-top: 20px;">
            <button id="adjustment-confirm" class="confirm-btn">Confirm</button>
            <button id="adjustment-cancel" class="cancel-btn">Cancel</button>
        </div>
    `;
    infoDiv.innerHTML = confirmationHtml;

    // confirm button
    document.getElementById('adjustment-confirm').addEventListener('click', () => {
        // create form data
        const body = new URLSearchParams(details);
        body.append('action', 'add_adjustment_transaction');

        // send request to server
        fetch('update_company.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        })
        // handle response from server
        .then(response => response.json())
        .then(data => {
            alert(data.message);
            if (data.status === 'success') {
                pltcon.style.display = 'none';
                uptrans.reset();
                transTypeDropdown.value = '';
                transTypeDropdown.dispatchEvent(new Event('change'));
            }
        })
        // handle error from server
        .catch(error => {
            console.error('Error adding adjustment transaction:', error);
            alert('A critical error occurred while adding the adjustment transaction.');
        });
    });

    // cancel button
    document.getElementById('adjustment-cancel').addEventListener('click', () => {
        pltcon.style.display = 'none';
    });
}

// setup autocomplete for company search
const input = document.getElementById("companySearch");
const suggestions = document.getElementById("suggest");
let selectedCompany = null;

// setup autocomplete for company search
function setupAutocomplete(inputElement, suggestionsElement, queryParam, companyTypeFilter = null) {
    // add event listener for input
    inputElement.addEventListener("input", () => {
        // get value from input
        const value = inputElement.value.trim();
        suggestionsElement.innerHTML = "";
        // check if value is empty
        if (value === "") {
            suggestionsElement.style.display = 'none';
            return;
        }
        
        // create url
        let url = `update_company.php?${queryParam}=${encodeURIComponent(value)}`;
        // check if company type filter is present
        if (companyTypeFilter) {
            url += `&type=${companyTypeFilter}`;
        } else if (inputElement.id === 'companySearch' && updatedd.value === 'capacity') {
            url += '&type=manufacturer';
        } else if ((inputElement.id === 'ship-company-search' || inputElement.id === 'rec-company-search') && (document.getElementById('trans_type').value === 'ship' || document.getElementById('trans_type').value === 'rec')) {
            url += '&type=manufacturer,retailer';
        }

        // fetch companies
        fetch(url)
            .then(r => r.json())
            // handle response from server
            .then(data => {
                // check if data is present
                if (data.length > 0) {
                    suggestionsElement.style.display = 'block';
                    data.forEach(companyName => {
                        const li = document.createElement("li");
                        li.textContent = companyName;
                        li.addEventListener("click", () => {
                            inputElement.value = companyName;
                            if (inputElement.id === 'companySearch') {
                                // When a company is selected, fetch its context.
                                fetchCompanyContext(companyName);
                            }
                            suggestionsElement.innerHTML = "";
                            suggestionsElement.style.display = 'none';
                        });
                        suggestionsElement.appendChild(li);
                    });
                } else {
                    suggestionsElement.style.display = 'none';
                }
            })
            // handle error from server
            .catch(e => {
                console.error("Error fetching companies:", e)
                suggestionsElement.style.display = 'none';
            });
    });

    // hide suggestions when clicking outside
    document.addEventListener("click", (e) => {
        if (!inputElement.contains(e.target) && !suggestionsElement.contains(e.target)) {
            suggestionsElement.style.display = 'none';
        }
    });
}

// Setup for main search
setupAutocomplete(input, suggestions, 'term');

// Setup for Add Dependency inputs
setupAutocomplete(document.getElementById('updep-upstream'), document.getElementById('updep-upstream-suggest'), 'term');
setupAutocomplete(document.getElementById('updep-downstream'), document.getElementById('updep-downstream-suggest'), 'term');

// Setup for Remove Dependency inputs
setupAutocomplete(document.getElementById('updepon-upstream'), document.getElementById('updepon-upstream-suggest'), 'term');
setupAutocomplete(document.getElementById('updepon-downstream'), document.getElementById('updepon-downstream-suggest'), 'term');

// Setup for Receiving Company search
setupAutocomplete(document.getElementById('rec-company-search'), document.getElementById('rec-company-suggest'), 'term');

// Setup for Shipping Company search
setupAutocomplete(document.getElementById('ship-company-search'), document.getElementById('ship-company-suggest'), 'term');

// Setup for Distributor search
setupAutocomplete(document.getElementById('dist-company-search'), document.getElementById('dist-company-suggest'), 'term', 'distributor');

// Setup for Add Route searches
setupAutocomplete(document.getElementById('addroute-distributor'), document.getElementById('addroute-distributor-suggest'), 'term', 'distributor');
setupAutocomplete(document.getElementById('addroute-from'), document.getElementById('addroute-from-suggest'), 'term', 'manufacturer,retailer');
setupAutocomplete(document.getElementById('addroute-to'), document.getElementById('addroute-to-suggest'), 'term', 'manufacturer,retailer');

// Setup for Remove Route searches
setupAutocomplete(document.getElementById('removeroute-distributor'), document.getElementById('removeroute-distributor-suggest'), 'term', 'distributor');
setupAutocomplete(document.getElementById('removeroute-from'), document.getElementById('removeroute-from-suggest'), 'term', 'manufacturer,retailer');
setupAutocomplete(document.getElementById('removeroute-to'), document.getElementById('removeroute-to-suggest'), 'term', 'manufacturer,retailer');

// Setup for Add/Remove Product manufacturer searches
setupAutocomplete(document.getElementById('addproduct-manufacturer'), document.getElementById('addproduct-manufacturer-suggest'), 'term', 'manufacturer');
setupAutocomplete(document.getElementById('removeproduct-manufacturer'), document.getElementById('removeproduct-manufacturer-suggest'), 'term', 'manufacturer');
setupAutocomplete(document.getElementById('updateprice-manufacturer'), document.getElementById('updateprice-manufacturer-suggest'), 'term', 'manufacturer');


// Setup for single-select product searches
setupAutocomplete(document.getElementById('add-product-search'), document.getElementById('add-product-suggest'), 'product_term');
setupAutocomplete(document.getElementById('remove-product-search'), document.getElementById('remove-product-suggest'), 'product_term');
setupAutocomplete(document.getElementById('updateprice-product'), document.getElementById('updateprice-product-suggest'), 'product_term');

// Setup for Adjustment Company search
setupAutocomplete(document.getElementById('adj-company-search'), document.getElementById('adj-company-suggest'), 'term');

// Setup for Product search
setupAutocomplete(document.getElementById('product-search'), document.getElementById('product-suggest'), 'product_term');


// validate company name
function validateCompany(companyName) {
    // check if company name is empty 
    if (companyName.trim() === "") {
        alert("Please type in a company name.");
        return false;
    }
    // check if selected company is present and matches the company name
    if (!selectedCompany || selectedCompany.CompanyName.toLowerCase() !== companyName.toLowerCase()) {
        alert("Invalid company name. Please select a company from the list and wait for its details to load.");
        return false;
    }
    return true;
}

// hide suggestions when clicking outside
document.addEventListener("click", (e) => {
    if (!input.contains(e.target) && !suggestions.contains(e.target)) {
        suggestions.style.display = 'none';
    }
});

// get update dropdown
const update = document.getElementById("update");

// validate update selection
function validateSelect() {
    if (update.value === "") {
        alert("Invalid update selection. Please select an option from the dropdown");
        return false;
    }
    return true;
}

// This function is no longer used for these specific updates
function fetchAndDisplayCompanyInfo(companyNameToFetch) {
    // ... Full fetch logic remains for other modules if needed
}

// fetch company context
function fetchCompanyContext(companyName) {
    // display container
    const pltcon = document.getElementById('pltcon');
    const infoDiv = document.getElementById('info');
    const header = document.getElementById('current-company-info-header');
    const trans_tbls = document.getElementById('trans_tbl');

    // send request to server
    fetch(`update_company.php?action=get_company_context&company=${encodeURIComponent(companyName)}`)
        .then(response => response.json())
        .then(data => {
            // check if data is present
            if (data.status === 'success') {
                selectedCompany = data.company; // Store the context
                
                let contextHtml = `<strong style="font-size: 14pt; color: #2d5180;">Current Information for ${selectedCompany.CompanyName}</strong>`;
                const currentUpdate = updatedd.value;
                if (currentUpdate === 'Type') {
                     contextHtml += `<p><strong>Current Type:</strong> ${selectedCompany.company_type || 'N/A'}</p>`;
                } else if (currentUpdate === 'Tier') {
                     contextHtml += `<p><strong>Current Tier Level:</strong> ${selectedCompany.tier_level || 'N/A'}</p>`;
                } else if (currentUpdate === 'capacity') {
                     contextHtml += `<p><strong>Current Capacity:</strong> ${selectedCompany.FactoryCapacity || 'N/A'}</p>`;
                }

                infoDiv.innerHTML = contextHtml;
                pltcon.style.display = 'block';
                if (header) header.style.display = 'none';
                if (trans_tbls) trans_tbls.style.display = 'none';
            } else {
                throw new Error(data.message);
            }
        })
        // handle error from server
        .catch(error => {
            console.error("Failed to fetch company context:", error);
            alert("An error occurred while fetching company details: " + error.message);
            selectedCompany = null;
            pltcon.style.display = 'none';
        });
}

// add event listener for add dependency form submission
updep.addEventListener('submit', function (event) {
    // prevent default form submission
    event.preventDefault();
    const upstream = document.getElementById('updep-upstream').value;
    const downstream = document.getElementById('updep-downstream').value;

    // check if upstream and downstream company names are present
    if (upstream.trim() === '' || downstream.trim() === '') {
        alert('Please fill in both upstream and downstream company names.');
        return;
    }

    // check if upstream and downstream companies are the same
    if (upstream.trim().toLowerCase() === downstream.trim().toLowerCase()) {
        alert('Upstream and downstream companies cannot be the same.');
        return;
    }

    // send request to server
    fetch(`update_company.php?action=check_dependency&upstream=${encodeURIComponent(upstream)}&downstream=${encodeURIComponent(downstream)}`)
        .then(response => response.json())
        .then(data => {
            if (data.exists) {
                alert('This dependency already exists.');
            } else {
    // display container
    const pltcon = document.getElementById('pltcon');
    const infoDiv = document.getElementById('info');
    const header = document.getElementById('current-company-info-header');
    const trans_tbls = document.getElementById('trans_tbl');

    // hide header and transaction table
    if (header) header.style.display = 'none';
    if (trans_tbls) trans_tbls.style.display = 'none';

    // display container
    pltcon.style.display = 'block';
    infoDiv.innerHTML = `
        <h2>Add Dependency?</h2>
        <p><strong>Upstream Company:</strong> ${upstream}</p>
        <p><strong>Downstream Company:</strong> ${downstream}</p>
        <div style="margin-top: 20px;">
            <button id="add-dependency-confirm" class="confirm-btn">Confirm</button>
            <button id="add-dependency-cancel" class="cancel-btn">Cancel</button>
        </div>
    `;

    // add event listener for the confirm button            
    document.getElementById('add-dependency-confirm').addEventListener('click', function() {
        // send request to server            
        fetch('update_company.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=add_dependency&upstream=${encodeURIComponent(upstream)}&downstream=${encodeURIComponent(downstream)}`
        })
        .then(response => response.json())
        .then(data => {
            // check if data is present
            if (data.status === 'success') {
                alert('Dependency added successfully!');
                pltcon.style.display = 'none';
            } else {
                alert('Error adding dependency: ' + data.message);
            }
        })
        // handle error from server
        .catch(error => {
            console.error('Error adding dependency:', error);
            alert('An error occurred while adding the dependency.');
        });
    });

    // add event listener for the cancel button
    document.getElementById('add-dependency-cancel').addEventListener('click', function() {
        // hide container
        pltcon.style.display = 'none';
                });
            }
        })
        // handle error from server
        .catch(error => {
            console.error('Error checking dependency:', error);
            alert('An error occurred while checking the dependency.');
    });
});

// add event listener for remove dependency form submission
updepon.addEventListener('submit', function (event) {
    // prevent default form submission
    event.preventDefault();
    // get upstream and downstream company names
    const upstream = document.getElementById('updepon-upstream').value;
    const downstream = document.getElementById('updepon-downstream').value;

    // check if upstream and downstream company names are present
    if (upstream.trim() === '' || downstream.trim() === '') {
        alert('Please fill in both upstream and downstream company names.');
        return;
    }

    // check if upstream and downstream companies are the same
    if (upstream.trim().toLowerCase() === downstream.trim().toLowerCase()) {
        alert('Upstream and downstream companies cannot be the same.');
        return;
    }

    // send request to server
    fetch(`update_company.php?action=check_dependency&upstream=${encodeURIComponent(upstream)}&downstream=${encodeURIComponent(downstream)}`)
        .then(response => response.json())
        // handle response from server
        .then(data => {
            if (!data.exists) {
                alert('This dependency does not exist and cannot be removed.');
                return; // Stop execution if dependency doesn't exist
            }
            
            // If dependency exists, show confirmation. This part is moved out of the `then` if check logic allows.
            // But based on the logic, it seems it should be inside the else. Re-evaluating.
            // The original structure was likely intended to be nested.
            // Let's look at the indentation and structure again.

            // Correcting my previous analysis. The confirmation logic *should* be in the else.
            // The issue is a missing closing brace for the `then` and the `else`.
            
            showRemoveDependencyConfirmation(upstream, downstream);
        })
        .catch(error => {
            console.error('Error checking dependency:', error);
            alert('An error occurred while checking the dependency.');
        });
});

function showRemoveDependencyConfirmation(upstream, downstream) {
    const pltcon = document.getElementById('pltcon');
    const infoDiv = document.getElementById('info');
    const header = document.getElementById('current-company-info-header');
    const trans_tbls = document.getElementById('trans_tbl');

    // display container
    pltcon.style.display = 'block';
    infoDiv.innerHTML = `
        <h2>Remove Dependency?</h2>
        <p><strong>Upstream Company:</strong> ${upstream}</p>
        <p><strong>Downstream Company:</strong> ${downstream}</p>
        <div style="margin-top: 20px;">
            <button id="remove-dependency-confirm" class="confirm-btn">Confirm</button>
            <button id="remove-dependency-cancel" class="cancel-btn">Cancel</button>
        </div>
    `;

    document.getElementById('remove-dependency-confirm').addEventListener('click', function() {
        // send request to server
        fetch('update_company.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=remove_dependency&upstream=${encodeURIComponent(upstream)}&downstream=${encodeURIComponent(downstream)}`
        })
        .then(response => response.json())
        .then(data => {
            // check if data is present
            if (data.status === 'success') {
                alert('Dependency removed successfully!');
                pltcon.style.display = 'none';
            } else {
                alert('Error removing dependency: ' + data.message);
            }
        })
        // handle error from server
        .catch(error => {
            console.error('Error removing dependency:', error);
            alert('An error occurred while removing the dependency.');
        });
    });

    document.getElementById('remove-dependency-cancel').addEventListener('click', function() {
        pltcon.style.display = 'none';
    });
}

// add event listener for update type form submission
uptype.addEventListener('submit', function (event) {
    // prevent default form submission
    event.preventDefault();
    if (!validateCompany(input.value)) return;
    
    // get company name, new type, and capacity
    const companyName = selectedCompany.CompanyName;
    const newType = uptype.querySelector('select').value;
    const capacityInput = document.getElementById('type-capacity-input');
    let capacity = capacityInput.value;

    // check if capacity is valid for manufacturer
    if (newType === 'Manufacturer' && (capacity.trim() === '' || isNaN(capacity))) {
        alert('Please enter a valid capacity for the manufacturer.');
        return;
    }

    // check if new type is the same as the current type
    if (selectedCompany.company_type && newType === selectedCompany.company_type) {
        alert('This is already the current type for this company.');
        return;
    }

    // show confirmation modal
    showTypeConfirmation(companyName, newType, newType === 'Manufacturer' ? capacity : null);
});

// show confirmation modal for update type
function showTypeConfirmation(companyName, newType, capacity = null) {
    // display container
    const pltcon = document.getElementById('pltcon');
    const infoDiv = document.getElementById('info');

    // create confirmation html
    let confirmationHtml = `
        <h2>Update Company Type?</h2>
        <p>Are you sure you want to change the type for <strong>${companyName}</strong>?</p>
        <p><strong>New Type:</strong> ${newType}</p>`;
    
    // check if capacity is present
    if (capacity !== null) {
        confirmationHtml += `<p><strong>Factory Capacity:</strong> ${capacity}</p>`;
    }

    // add confirmation html
    confirmationHtml += `
        <div style="margin-top: 20px;">
            <button id="confirm-type-btn" class="confirm-btn">Confirm</button>
            <button id="cancel-type-btn" class="cancel-btn">Cancel</button>
        </div>
    `;
    infoDiv.innerHTML = confirmationHtml;
    // display container
    pltcon.style.display = 'block';
    infoDiv.style.display = 'block';

    // add event listener for the confirm button
    document.getElementById('confirm-type-btn').addEventListener('click', function() {
        // create form data
        let body = `action=update_type&company=${encodeURIComponent(companyName)}&type=${encodeURIComponent(newType)}`;
        // check if capacity is present
        if (capacity !== null) {
            body += `&capacity=${encodeURIComponent(capacity)}`;
        }

        // send request to server
        fetch('update_company.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body
        })
        // handle response from server
        .then(response => response.json())
        .then(result => {
            alert(result.message);
            if (result.status === 'success') {
                pltcon.style.display = 'none';
                document.getElementById('main_co_search').value = '';
                resetUpdateOptions();
            }
        })
        // handle error from server
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while updating the company type.');
        })
        // hide container
        .finally(() => {
            pltcon.style.display = 'none';
        });
    });

    // add event listener for the cancel button
    document.getElementById('cancel-type-btn').addEventListener('click', function() {
        pltcon.style.display = 'none';
    });
}

// add event listener for update tier form submission
uptier.addEventListener('submit', function (event) {
    // prevent default form submission
    event.preventDefault();
    // validate company name
    if (!validateCompany(input.value)) {
        return;
    }

    // get new tier
    const newTier = uptier.querySelector('select').value;
    // check if new tier is the same as the current tier
    if (selectedCompany.tier_level && newTier === selectedCompany.tier_level) {
        alert('This is already the current tier level for this company.');
        return;
    }
    
    // show confirmation modal
    showTierConfirmation(selectedCompany.CompanyName, newTier);
});

// show confirmation modal for update tier
function showTierConfirmation(companyName, newTier) {
    // display container
    const pltcon = document.getElementById('pltcon');
    const infoDiv = document.getElementById('info');
    
    // create confirmation html
    let confirmationHtml = `
        <h2>Update Company Tier?</h2>
        <p>Are you sure you want to change the tier for <strong>${companyName}</strong>?</p>
        <p><strong>New Tier:</strong> ${newTier}</p>
        <div style="margin-top: 20px;">
            <button id="confirm-tier-btn" class="confirm-btn">Confirm</button>
            <button id="cancel-tier-btn" class="cancel-btn">Cancel</button>
        </div>
    `;
    infoDiv.innerHTML = confirmationHtml;
    pltcon.style.display = 'block';
    infoDiv.style.display = 'block';

    // add event listener for the confirm button
    document.getElementById('confirm-tier-btn').addEventListener('click', function() {
        // create form data
        let body = `action=update_tier&company=${encodeURIComponent(companyName)}&tier=${encodeURIComponent(newTier)}`;
        // send request to server
        fetch('update_company.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body
        })
        // handle response from server
        .then(response => response.json())
        .then(result => {
            alert(result.message);
            if (result.status === 'success') {
                pltcon.style.display = 'none';
                document.getElementById('main_co_search').value = '';
                resetUpdateOptions();
            }
        })
        // handle error from server
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while updating the company tier.');
        })
        // hide container
        .finally(() => {
            pltcon.style.display = 'none';
        });
    });

    // add event listener for the cancel button
    document.getElementById('cancel-tier-btn').addEventListener('click', function() {
        pltcon.style.display = 'none';
    });
}

// add event listener for the update capacity form submission
upcap.addEventListener('submit', function (event) {
    // prevent default form submission
    event.preventDefault();
    const companyName = document.getElementById('main_co_search').value.trim();
    const newCapacity = upcap.querySelector('input[type="number"]').value;

    // check if new capacity is present
    if (newCapacity.trim() === '') {
        alert('Please enter a new capacity value.');
        return;
    }

    // check if new capacity is the same as the current capacity
    if (selectedCompany.FactoryCapacity && parseFloat(newCapacity) === parseFloat(selectedCompany.FactoryCapacity)) {
        alert('This is already the current capacity for this company.');
        return;
    }

    // show confirmation modal
    showCapacityConfirmation(companyName, newCapacity);
});

// show confirmation model for update capacity
function showCapacityConfirmation(companyName, newCapacity) {
    // display container
    const infoDiv = document.getElementById('info');
    infoDiv.innerHTML = `
        <h2>Update Capacity?</h2>
        <p><strong>Company:</strong> ${companyName}</p>
        <p><strong>New Capacity:</strong> ${newCapacity}</p>
        <div style="margin-top: 20px;">
            <button id="update-capacity-confirm" class="confirm-btn">Confirm</button>
            <button id="update-capacity-cancel" class="cancel-btn">Cancel</button>
        </div>
    `;

    // add event listener for the confirm button
    document.getElementById('update-capacity-confirm').addEventListener('click', function() {
        fetch('update_company.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=update_capacity&company=${encodeURIComponent(companyName)}&capacity=${encodeURIComponent(newCapacity)}`
        })
        .then(response => response.json())
        .then(data => {
            alert(data.message);
            if (data.status === 'success') {
                fetchCompanyContext(companyName); // Refresh context on success
            }
        })
        .catch(error => {
            console.error('Error updating capacity:', error);
            alert('A critical error occurred while updating the capacity.');
        });
    });

    // add event listener for the cancel button
    document.getElementById('update-capacity-cancel').addEventListener('click', function () {
        fetchCompanyContext(companyName); // Go back to the context view
    });
}

// add event listener for the transaction type dropdown
const transTypeDropdown = document.getElementById('trans_type');
transTypeDropdown.addEventListener('change', updateTransactionFields);
updatedd.addEventListener('change', updateTransactionFields);

// Initial call to set the correct fields based on default values
updateTransactionFields();

// update transaction fields
function updateTransactionFields() {
    const mainSelection = updatedd.value;
    const transType = transTypeDropdown.value;

    // Get all container elements
    const transTypeDropdownContainer = document.getElementById('trans_type');
    const distributorCompanyContainer = document.getElementById('distributor-company-container');
    const shippingCompanyContainer = document.getElementById('shipping-company-container');
    const receivingCompanyContainer = document.getElementById('receiving-company-container');
    const adjustmentCompanyContainer = document.getElementById('adjustment-company-container');
    const productInput = document.getElementById('product-search');
    const productLabel = document.querySelector('label[for="product-search"]');
    const dateContainer = document.getElementById('trans-date-label');
    const quantityContainer = document.getElementById('trans-quantity').parentElement;
    const reasonContainer = document.getElementById('reason-container');
    const actualDateContainer = document.getElementById('actual-date-container');
    const transactionIdContainer = document.getElementById('transaction-id-container');
    const existingShippingUpdateContainer = document.getElementById('existing-shipping-update-container');
    const existingReceivingUpdateContainer = document.getElementById('existing-receiving-update-container');

    // Hide all dynamic transaction fields first
    distributorCompanyContainer.style.display = 'none';
    shippingCompanyContainer.style.display = 'none';
    receivingCompanyContainer.style.display = 'none';
    adjustmentCompanyContainer.style.display = 'none';
    productInput.style.display = 'none';
    productLabel.style.display = 'none';
    dateContainer.style.display = 'none';
    quantityContainer.style.display = 'none';
    reasonContainer.style.display = 'none';
    actualDateContainer.style.display = 'none';
    transactionIdContainer.style.display = 'none';
    existingShippingUpdateContainer.style.display = 'none';
    existingReceivingUpdateContainer.style.display = 'none';
    
    // check if main selection is new
    if (mainSelection === 'new') {
        transTypeDropdownContainer.style.display = 'block';
        // Show relevant fields for 'new'
        dateContainer.style.display = 'block';
        quantityContainer.style.display = 'block';

        // check if transaction type is shipping
        if (transType === 'ship') {
            distributorCompanyContainer.style.display = 'block';
            shippingCompanyContainer.style.display = 'block';
            receivingCompanyContainer.style.display = 'block';
            productInput.style.display = 'block';
            productLabel.style.display = 'inline-block';
            actualDateContainer.style.display = 'block';
        } else if (transType === 'rec') {
            receivingCompanyContainer.style.display = 'block';
            transactionIdContainer.style.display = 'block';
        } else if (transType === 'adj') {
            adjustmentCompanyContainer.style.display = 'block';
            productInput.style.display = 'block';
            productLabel.style.display = 'inline-block';
            reasonContainer.style.display = 'block';
        }

    } 
    // check if main selection is existing
    else if (mainSelection === 'existing') {
        // For existing transactions, always show the type dropdown
        transTypeDropdownContainer.style.display = 'block';

        // check if transaction type is shipping
        if (transType === 'ship') {
            existingShippingUpdateContainer.style.display = 'block';
            transactionIdContainer.style.display = 'block';
        } else if (transType === 'rec') {
            transactionIdContainer.style.display = 'block';
            existingReceivingUpdateContainer.style.display = 'block';
        }
        // 'adj' is hidden for existing, so no specific case needed
    } else {
        // Hide transaction type dropdown if neither 'new' nor 'existing' is selected
        transTypeDropdownContainer.style.display = 'none';
    }
}

// setup multi-select
function setupMultiSelect(containerId, searchInputId, suggestionsUlId, selectedOptionsDivId, allOptions) {
    // get multi-select container
    const multiSelectContainer = document.getElementById(containerId);
    if (!multiSelectContainer) return; // Guard against missing elements
    
    // get search input
    const searchInput = document.getElementById(searchInputId);
    const suggestionsUl = document.getElementById(suggestionsUlId);
    const selectedOptionsDiv = document.getElementById(selectedOptionsDivId);
    
    // initialize selected options
    let selectedOptions = [];

    // render suggestions
    function renderSuggestions(filteredOptions) {
        // clear suggestions
        suggestionsUl.innerHTML = '';
        // loop through filtered options
        filteredOptions.forEach(option => {
            const li = document.createElement('li');
            li.textContent = option;
            li.addEventListener('click', () => {
                if (!selectedOptions.includes(option)) {
                    selectedOptions.push(option);
                    renderSelectedOptions();
                }
                searchInput.value = '';
                suggestionsUl.innerHTML = '';
            });
            suggestionsUl.appendChild(li);
        });
    }

    // render selected options
    function renderSelectedOptions() {
        // clear selected options
        selectedOptionsDiv.innerHTML = '';
        // loop through selected options
        selectedOptions.forEach(option => {
            // create options tag
            const optionTag = document.createElement('span');
            optionTag.className = 'option-tag';
            optionTag.textContent = option;

            // create remove button
            const removeBtn = document.createElement('button');
            removeBtn.className = 'remove-btn';
            removeBtn.textContent = 'x';
            removeBtn.addEventListener('click', () => {
                selectedOptions = selectedOptions.filter(o => o !== option);
                renderSelectedOptions();
            });
            
            // append remove button to options tag
            optionTag.appendChild(removeBtn);
            selectedOptionsDiv.appendChild(optionTag);
        });
    }

    // add event listener for input
    searchInput.addEventListener('input', () => {
        // get value form input
        const value = searchInput.value.trim().toLowerCase();
        const availableOptions = allOptions.filter(o => !selectedOptions.includes(o));
        if (value === '') {
            renderSuggestions(availableOptions);
        } else {
            const filtered = availableOptions.filter(o => o.toLowerCase().includes(value));
            renderSuggestions(filtered);
        }
    });

    // add event listener for focus
    searchInput.addEventListener('focus', () => {
        const availableOptions = allOptions.filter(o => !selectedOptions.includes(o));
        renderSuggestions(availableOptions);
    });

    // add event listener for click
    document.addEventListener("click", (e) => {
        if (!multiSelectContainer.contains(e.target)) {
            suggestionsUl.innerHTML = "";
        }
    });

    // Add the logic to show/hide fields
    function updateVisibleFields() {
        // Hide all possible fields first
        Object.values(optionToFieldIdMap).forEach(id => {
            const el = document.getElementById(id);
            if (el) el.style.display = 'none';
        });

        // Show fields for selected options
        selectedOptions.forEach(option => {
            const fieldId = optionToFieldIdMap[option];
            if (fieldId) {
                const el = document.getElementById(fieldId);
                if (el) el.style.display = 'block';
            }
        });
    }

    // Tie the update logic to the rendering of selected options
    const originalRenderSelectedOptions = renderSelectedOptions;
    renderSelectedOptions = function() {
        originalRenderSelectedOptions();
        updateVisibleFields();
    };
    
    // Add a function to get the current selections
    multiSelectContainer.getSelectedOptions = () => selectedOptions;
}

// map options to field ids
const optionToFieldIdMap = {
    'Distributor': 'update-distributor-container',
    'Product': 'update-product-container',
    'Shipping Company': 'update-shipping-company-container',
    'Receiving Company': 'update-receiving-company-container',
    'Promised Delivery Date': 'update-promised-date-container',
    'Actual Delivery Date': 'update-actual-date-container',
    'Quantity': 'update-quantity-container',
    'Date Received': 'update-date-received-container'
};

// Setup for Shipping multi-select
const shippingUpdateOptions = ['Distributor', 'Product', 'Shipping Company', 'Receiving Company', 'Promised Delivery Date', 'Actual Delivery Date', 'Quantity'];
setupMultiSelect(
    'existing-shipping-update-container',
    'shipping-update-search',
    'shipping-update-suggestions',
    'shipping-update-selected',
    shippingUpdateOptions
);

// Setup for Receiving multi-select
const receivingUpdateOptions = ['Receiving Company', 'Date Received', 'Quantity'];
setupMultiSelect(
    'existing-receiving-update-container',
    'receiving-update-search',
    'receiving-update-suggestions',
    'receiving-update-selected',
    receivingUpdateOptions
);

// Setup Autocomplete for the new update fields
setupAutocomplete(document.getElementById('update-distributor-search'), document.getElementById('update-distributor-suggest'), 'term', 'distributor');
setupAutocomplete(document.getElementById('update-shipping-company-search'), document.getElementById('update-shipping-company-suggest'), 'term', 'manufacturer,retailer');
setupAutocomplete(document.getElementById('update-receiving-company-search'), document.getElementById('update-receiving-company-suggest'), 'term', 'manufacturer,retailer');
setupAutocomplete(document.getElementById('update-product-search'), document.getElementById('update-product-suggest'), 'product_term');

// Main submit handler
uptrans.addEventListener('submit', function(event) {
    event.preventDefault();
    const mainSelection = updatedd.value;
    if (mainSelection === 'new') {
        handleNewTransactionSubmit();
    } else if (mainSelection === 'existing') {
        handleExistingTransactionSubmit();
    }
});

// add event listener for DOMContentLoaded
document.addEventListener('DOMContentLoaded', function() {
    // fetch options from server
    fetch('update_company.php?fetch_options=true')
        .then(response => response.json())
        .then(data => {
            // get type and tier dropdowns
            const typeDropdown = uptype.querySelector('select');
            const tierDropdown = uptier.querySelector('select');

            // Add event listener to the company type dropdown
            const capacityContainer = document.getElementById('type-capacity-container');
            typeDropdown.addEventListener('change', function() {
                if (this.value === 'Manufacturer') {
                    capacityContainer.style.display = 'block';
                } else {
                    capacityContainer.style.display = 'none';
                }
            });

            // loop through types
            if (data.types) {
                data.types.forEach(type => {
                    const option = document.createElement('option');
                    option.value = type;
                    option.textContent = type;
                    typeDropdown.appendChild(option);
                });
            }

            // loop through tiers
            if (data.tiers) {
                data.tiers.forEach(tier => {
                    const option = document.createElement('option');
                    option.value = tier;
                    option.textContent = tier;
                    tierDropdown.appendChild(option);
                });
            }
        })
        // handle error from server
        .catch(error => console.error('Error fetching dropdown options:', error));
});