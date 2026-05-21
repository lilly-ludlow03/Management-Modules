/* ---------- Shared page navigation search logic ---------- */


const searchIcon = document.getElementById("page-search");
const searchCon = document.getElementById("page-search-bar");
const searchInput = searchCon.querySelector(".search-input");
const pages = ["Most Critical Companies", "Top Distributors", "Distributor Delay", "ADD COMPANY", "Average Financial Health", "Financials By Region", "Disruption Frequency", "Regional Disruption", "Disruption Effects", "Disruption By Company", "Home"];
const pageSearchInput = document.getElementById("page-search-input");
const pageSuggestions = document.getElementById("pagesuggest");
const pageSearchGo = document.getElementById("page-search-go");
const pageLinks = {
    "Most Critical Companies": "Most-Critical-Companies_CompanyInformation.php",
    "Top Distributors": "Top-Distributors_CompanyInformation.php",
    "Distributor Delay": "Distributor-Delay_CompanyInformation.php",
    "ADD COMPANY": "ADD-COMPANY_CompanyInformation.php",
    "Average Financial Health": "Average-Financial-Health_Finances.php",
    "Financials By Region": "Financials-By-Region_Finances.php",
    "Disruption Frequency": "Disruption-Frequency_DisruptionEvents.php",
    "Regional Disruption": "Regional-Disruption_DisruptionEvents.php",
    "Disruption Effects": "Disruption-Effects_DisruptionEvents.php",
    "Disruption By Company": "Disruption-By-Company_DisruptionEvents.php",
    "Home": "SM%20Shell.php"
};

// Clicking the icon toggles the search overlay open or close 
searchIcon.addEventListener("click", (e) => {
    e.stopPropagation();
    searchCon.classList.toggle("open");
    searchInput.classList.toggle("open");
    pageSearchGo.classList.add("open");
});

// Tab dropdown behavior in the sidebar
const button1 = document.getElementById("btn1");
const dropdown1 = document.getElementById("ddn1");
button1.addEventListener("click", function (e) {
    e.stopPropagation();
    dropdown1.classList.toggle("show");
    dropdown.classList.remove("show");
    dropdown3.classList.remove("show");
    searchCon.classList.remove("open");
    searchInput.classList.remove("open");
})
const button = document.getElementById("btn2");
const dropdown = document.getElementById("ddn2");
button.addEventListener("click", function (e) {
    e.stopPropagation();
    dropdown.classList.toggle("show");
    dropdown1.classList.remove("show");
    dropdown3.classList.remove("show");
    searchCon.classList.remove("open");
    searchInput.classList.remove("open");
})
const button3 = document.getElementById("btn3");
const dropdown3 = document.getElementById("ddn3");
button3.addEventListener("click", function (e) {
    e.stopPropagation();
    dropdown3.classList.toggle("show");
    dropdown.classList.remove("show");
    dropdown1.classList.remove("show");
    searchCon.classList.remove("open");
    searchInput.classList.remove("open");
})

// Clicking anywhere outside closes all dropdowns 
document.addEventListener("click", function (e) {
    dropdown1.classList.remove("show");
    dropdown.classList.remove("show");
    dropdown3.classList.remove("show");

    if (!searchCon.contains(e.target) && !searchInput.contains(e.target) && !pageSuggestions.contains(e.target)) {
        searchCon.classList.remove("open");
        searchInput.classList.remove("open");
        searchInput.value = "";
    }
})

// Page search suggestions
pageSearchInput.addEventListener("input", () => {
    pageSuggestions.style.display = "block";
    const value = pageSearchInput.value.trim();
    pageSuggestions.innerHTML = "";
    if (value === "") {
        pageSuggestions.style.display = 'none';
        return;
    }
    const pagesFilter = pages.filter(page => page.toLowerCase().includes(value));
    pagesFilter.forEach(page => {
        const li = document.createElement("li");
        li.textContent = page;
        li.addEventListener("click", (e) => {
            e.stopPropagation();
            pageSearchInput.value = page;
            pageSuggestions.innerHTML = "";
        })
        pageSuggestions.appendChild(li)
    })
})
// When user clicks the arrow navigate to the chosen page 
pageSearchGo.addEventListener("click", () => {
    const value = pageSearchInput.value.trim();
    if (pages.includes(value)) {
        const link = pageLinks[value]
        if (link) {
            window.location.href = link;
        }
    } else {
        alert("Company page not found. Select the home button to see valid pages and associated content");
    }
})