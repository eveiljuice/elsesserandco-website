<?php
/**
 * Compare UI stub.
 *
 * Раньше здесь рендерилась плавающая панель "compare-bar" снизу-справа.
 * Сейчас вся логика сравнения делается через иконку-чекбокс на каждой
 * карточке (как кнопка "избранное"), а общий счётчик выбранных объектов
 * — бейдж #navCompareCount на ссылке "Сравнить" в шапке.
 *
 * Файл сохранён как include, чтобы не править все точки подключения
 * (index.php, properties.php, property.php, favorites.php).
 * @see js/compare.js
 */
?>
<script src="js/compare.js" defer></script>