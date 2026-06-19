<?php
/**
 * Ссылка «Сравнение» для шапки. Иконка со счётчиком объектов в localStorage.
 * Счётчик обновляется на клиенте через js/compare.js::updateCompareUI().
 *
 * Подключать сразу после ссылки «Избранное» в шапках, либо в любом месте
 * внутри <nav class="nav"> или <ul class="nav__list">.
 */
?>
<a href="/compare.php" class="nav__link nav__link--icon" id="navCompareLink" aria-label="Сравнение объектов">
    <i class="fas fa-balance-scale"></i>
    <span class="badge" id="navCompareCount" style="display:none;">0</span>
</a>
