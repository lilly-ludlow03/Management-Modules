const button1 = document.getElementById("btn1");
const dropdown1 = document.getElementById("ddn1");
button1.addEventListener("click", function () {
    dropdown1.classList.toggle("show");
});
const button = document.getElementById("btn2");
const dropdown = document.getElementById("ddn2");
button.addEventListener("click", function () {
    dropdown.classList.toggle("show");
});
const button3 = document.getElementById("btn3");
const dropdown3 = document.getElementById("ddn3");
button3.addEventListener("click", function () {
    dropdown3.classList.toggle("show");
});
const stb3 = document.getElementById("subtab3");
const sbddn3 = document.getElementById("dubddn3");
stb3.addEventListener("click", function () {
    sbddn3.classList.toggle("show");
});
const subtab = document.querySelector('#ddn3 .subtab');
const subddn = document.querySelector('#ddn3 .subtabdropdown');
subtab.addEventListener('click', e => { e.stopPropagation(); subddn.classList.toggle('show'); });