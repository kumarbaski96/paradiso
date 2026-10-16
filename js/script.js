

document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('year').textContent = new Date().getFullYear();

    // Set the minimum selectable date to today
    const eventDate = document.getElementById('eventDate');
    const today = new Date();
    const localToday = new Date(today.getTime() - today.getTimezoneOffset() * 60000)
        .toISOString().slice(0, 10);
    eventDate.min = localToday;

    // Back-to-top button
    const backTop = document.getElementById('backTop');
    window.addEventListener('scroll', function () {
        backTop.classList.toggle('show', window.scrollY > 350);
    });

    // Close mobile menu after navigation
    document.querySelectorAll('#mainNav .nav-link, #mainNav .btn').forEach(function (link) {
        link.addEventListener('click', function () {
            const nav = document.getElementById('mainNav');
            if (nav.classList.contains('show')) {
                bootstrap.Collapse.getOrCreateInstance(nav).hide();
            }
        });
    });

    // Five-slide counter
    const hero = document.getElementById('weddingSlider');
    hero.addEventListener('slid.bs.carousel', function (event) {
        const current = String(event.to + 1).padStart(2, '0');
        document.getElementById('heroCount').innerHTML =
            current + ' <span class="text-warning">/</span> 05';
    });

    // Gallery popup
    const modalElement = document.getElementById('galleryModal');
    const galleryModal = new bootstrap.Modal(modalElement);
    const modalImage = document.getElementById('galleryModalImage');
    const modalTitle = document.getElementById('galleryModalTitle');

    document.querySelectorAll('.gallery-card').forEach(function (item) {
        item.addEventListener('click', function (event) {
            event.preventDefault();
            const img = item.querySelector('img');
            modalImage.src = img.src;
            modalImage.alt = img.alt;
            modalTitle.textContent = item.dataset.title || img.alt;
            galleryModal.show();
        });
    });

    modalElement.addEventListener('hidden.bs.modal', function () {
        modalImage.src = '';
    });

    // Static booking form: open WhatsApp with enquiry details
    document.getElementById('bookingForm').addEventListener('submit', function (event) {
        event.preventDefault();
        const form = event.currentTarget;

        if (!form.reportValidity()) return;

        const data = new FormData(form);
        const message = [
            'Hello Royal Marriage Hall, I would like to enquire about a booking.',
            '',
            'Name: ' + data.get('name'),
            'Phone: ' + data.get('phone'),
            'Email: ' + (data.get('email') || 'Not provided'),
            'Event: ' + data.get('event'),
            'Event Date: ' + data.get('date'),
            'Expected Guests: ' + data.get('guests'),
            'Requirements: ' + (data.get('message') || 'Not specified')
        ].join('\n');

        const whatsappNumber = '919876543210'; // Replace with your actual WhatsApp number
        const url = 'https://wa.me/' + whatsappNumber + '?text=' + encodeURIComponent(message);
        window.open(url, '_blank', 'noopener');
        document.getElementById('formNotice').textContent =
            'WhatsApp is opening with your enquiry. Send the message there to contact the hall.';
    });
});