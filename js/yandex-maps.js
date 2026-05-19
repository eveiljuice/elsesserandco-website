(function () {
    'use strict';
    const el = document.getElementById('yandexMap');
    if (!el || !window.YANDEX_MAPS_KEY) return;

    const script = document.createElement('script');
    script.src = 'https://api-maps.yandex.ru/2.1/?apikey=' + encodeURIComponent(window.YANDEX_MAPS_KEY) + '&lang=ru_RU';
    script.onload = function () {
        ymaps.ready(init);
    };
    document.head.appendChild(script);

    function init() {
        const lat = parseFloat(el.dataset.lat);
        const lng = parseFloat(el.dataset.lng);
        const isList = el.dataset.mode === 'list';

        const map = new ymaps.Map(el, {
            center: isList ? [56.8389, 60.6057] : [lat, lng],
            zoom: isList ? 11 : 15,
            controls: ['zoomControl', 'fullscreenControl']
        });

        if (isList) {
            const qs = new URLSearchParams(location.search).toString();
            fetch('/php/properties/geo.php?' + qs)
                .then((r) => r.json())
                .then((geo) => {
                    const collection = new ymaps.GeoObjectCollection();
                    (geo.features || []).forEach((f) => {
                        const [lon, la] = f.geometry.coordinates;
                        const p = f.properties;
                        collection.add(new ymaps.Placemark([la, lon], {
                            balloonContent: '<strong>' + p.title + '</strong><br>' + p.price + ' ₽<br><a href="' + p.url + '">Открыть</a>'
                        }));
                    });
                    map.geoObjects.add(collection);
                    if (collection.getLength()) map.setBounds(collection.getBounds(), { checkZoomRange: true, zoomMargin: 40 });
                });
            return;
        }

        if (!isNaN(lat) && !isNaN(lng)) {
            map.geoObjects.add(new ymaps.Placemark([lat, lng], { balloonContent: el.dataset.title || '' }));
        }
    }
})();
