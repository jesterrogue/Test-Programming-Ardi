
const menuToggle = document.getElementById('menuToggle');
const navMenu = document.getElementById('navMenu');

if (menuToggle && navMenu) {
    menuToggle.addEventListener('click', function () {
        const isOpen = navMenu.classList.toggle('show');

        menuToggle.setAttribute(
            'aria-expanded',
            String(isOpen)
        );
    });
}

const tanggalMulai = document.getElementById('tanggalMulai');
const tanggalSelesai = document.getElementById('tanggalSelesai');

if (tanggalMulai && tanggalSelesai) {
    tanggalMulai.addEventListener('change', function () {
        tanggalSelesai.min = this.value;

        if (
            tanggalSelesai.value &&
            tanggalSelesai.value < this.value
        ) {
            tanggalSelesai.value = this.value;
        }
    });
}
