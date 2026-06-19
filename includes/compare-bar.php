<?php
/**
 * Плавающая панель сравнения.
 * Показывается, когда в localStorage есть хотя бы один объект.
 * Появляется/исчезает через window.EcoCompare.updateCompareUI().
 *
 * @see js/compare.js
 */
?>
<div id="compareBar" class="compare-bar" aria-live="polite">
    <span class="compare-bar__count" id="compareCount">0</span>
    <span class="compare-bar__text">в сравнении</span>
    <a id="compareLink" href="/compare.php" class="compare-bar__btn">
        <i class="fas fa-balance-scale"></i>
        Сравнить
    </a>
    <button type="button" class="compare-bar__clear" id="compareClear" aria-label="Очистить список сравнения">
        <i class="fas fa-times"></i>
    </button>
</div>
<script src="js/compare.js" defer></script>
<script>
(function () {
    // Подключаем обработчик «Очистить» после готовности DOM.
    document.addEventListener('DOMContentLoaded', function () {
        var clearBtn = document.getElementById('compareClear');
        if (!clearBtn) return;
        clearBtn.addEventListener('click', function () {
            if (typeof clearCompare !== 'function') return;
            // Не подтверждаем — на панели действие очевидное.
            localStorage.removeItem('compareList');
            document.querySelectorAll('.compare-checkbox__input').forEach(function (cb) { cb.checked = false; });
            if (typeof updateCompareUI === 'function') updateCompareUI();
        });
    });
})();
</script>