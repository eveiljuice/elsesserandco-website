(function () {
    'use strict';
    const zone = document.getElementById('dropZone');
    const input = document.getElementById('property_images');
    const preview = document.getElementById('uploadPreview');
    const propertyId = document.querySelector('[name="property_id"]')?.value;
    if (!zone || !input) return;

    function previewFiles(files) {
        if (!preview) return;
        preview.innerHTML = '';
        [...files].forEach((f) => {
            const img = document.createElement('img');
            img.src = URL.createObjectURL(f);
            img.className = 'upload-preview__thumb';
            preview.appendChild(img);
        });
    }

    zone.addEventListener('click', () => input.click());
    zone.addEventListener('dragover', (e) => { e.preventDefault(); zone.classList.add('is-dragover'); });
    zone.addEventListener('dragleave', () => zone.classList.remove('is-dragover'));
    zone.addEventListener('drop', (e) => {
        e.preventDefault();
        zone.classList.remove('is-dragover');
        input.files = e.dataTransfer.files;
        previewFiles(input.files);
        if (propertyId && window.EcoApi) uploadAjax(input.files);
    });
    input.addEventListener('change', () => {
        previewFiles(input.files);
        if (propertyId && window.EcoApi) uploadAjax(input.files);
    });

    async function uploadAjax(fileList) {
        const fd = new FormData();
        fd.append('property_id', propertyId);
        [...fileList].forEach((f) => fd.append('images[]', f));
        const token = EcoApi.csrfToken();
        await fetch('/php/agent/upload_images.php', {
            method: 'POST',
            headers: { 'X-CSRF-Token': token },
            body: fd
        });
    }
})();
