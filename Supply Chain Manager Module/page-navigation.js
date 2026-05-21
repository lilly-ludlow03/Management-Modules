const searchIcon = document.getElementById("page-search");
const searchCon = document.getElementById("page-search-bar");
const searchInput = searchCon.querySelector(".search-input");
const pages = ["Company Information", "Key Performance Indicators", "Update Company Information", "Disruption Frequencies", "Average Recovery Time", "High Impact Disruption Rate", "Total Downtime", "Regional Risk Concentration", "Disruption Severity Distribution", "General Transactions", "Distributor Information", "Shipment Information", "Home"];
const pageSearchInput = document.getElementById("page-search-input");
const pageSuggestions = document.getElementById("pagesuggest");
const pageSearchGo = document.getElementById("page-search-go");
const pageLinks = {
    "Company Information": "../Company-Details/Company-Information.php",
    "Key Performance Indicators": "../Company-Details/Key-Performance-Indicators.php",
    "Update Company Information": "../Company-Details/Update-Company-Information.php",
    "Disruption Frequencies": "../Disruption-Events/Disruption-Frequencies.php",
    "Average Recovery Time": "../Disruption-Events/Average-Recovery-Time.php",
    "High Impact Disruption Rate": "../Disruption-Events/High-Impact-Disruption-Rate.php",
    "Total Downtime": "../Disruption-Events/Total-Downtime.php",
    "Regional Risk Concentration": "../Disruption-Events/Regional-Risk-Concentration.php",
    "Disruption Severity Distribution": "../Disruption-Events/Disruption-Severity-Distribution.php",
    "General Transactions": "../Transaction-Information/General-Transactions.php",
    "Distributor Information": "../Transaction-Information/Distributor-Information.php",
    "Shipment Information": "../Transaction-Information/Shipment-Information.php",
    "Home": "../SC%20Manager%20Shell.php"
};

searchIcon.addEventListener("click", (e) => {
    e.stopPropagation();
    searchCon.classList.toggle("open");
    searchInput.classList.toggle("open");
    pageSearchGo.classList.add("open");
});

const button1 = document.getElementById("btn1");
const dropdown1 = document.getElementById("ddn1");
button1.addEventListener("click", function (e) {
    e.stopPropagation();
    dropdown1.classList.toggle("show");
    dropdown.classList.remove("show");
    dropdown3.classList.remove("show");
    sbddn3.classList.remove("show");
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
    sbddn3.classList.remove("show");
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
    sbddn3.classList.remove("show");
    searchCon.classList.remove("open");
    searchInput.classList.remove("open");
})
const stb3 = document.getElementById("subtab3");
const sbddn3 = document.getElementById("subddn3");
stb3.addEventListener("click", function (e) {
    e.stopPropagation();
    sbddn3.classList.toggle("show");
    dropdown.classList.remove("show");
    dropdown1.classList.remove("show");
    searchCon.classList.remove("open");
    searchInput.classList.remove("open");
})

document.addEventListener("click", function (e) {
    dropdown1.classList.remove("show");
    dropdown.classList.remove("show");
    dropdown3.classList.remove("show");
    sbddn3.classList.remove("show");

    if (!searchCon.contains(e.target) && !searchInput.contains(e.target) && !pageSuggestions.contains(e.target)) {
        searchCon.classList.remove("open");
        searchInput.classList.remove("open");
        searchInput.value = "";
    }
})

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