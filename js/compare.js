/**
 * Property Comparison System - Elsesser & Co.
 * Система сравнения объектов недвижимости.
 *
 * UX:
 *   - Иконка-чекбокс на каждой карточке (как "избранное").
 *   - Общий счётчик выбранных объектов — бейдж на ссылке "Сравнить"
 *     в шапке (#navCompareCount).
 *   - Плавающая панель снизу-справа НЕ используется.
 */

// Maximum number of properties that can be compared
const MAX_COMPARE = 4;

/**
 * Get compare list from localStorage
 */
function getCompareList() {
    const stored = localStorage.getItem('compareList');
    return stored ? JSON.parse(stored) : [];
}

/**
 * Save compare list to localStorage
 */
function saveCompareList(list) {
    localStorage.setItem('compareList', JSON.stringify(list));
    updateCompareUI();
}

/**
 * Add property to compare list
 */
function addToCompare(propertyId) {
    const list = getCompareList();

    // Already in list — ничего не делаем
    if (list.includes(propertyId)) {
        return true;
    }

    // Лимит
    if (list.length >= MAX_COMPARE) {
        alert(`Можно сравнить максимум ${MAX_COMPARE} объекта одновременно`);
        const checkbox = document.querySelector(`input[data-property-id="${propertyId}"]`);
        if (checkbox) checkbox.checked = false;
        return false;
    }

    list.push(propertyId);
    saveCompareList(list);
    return true;
}

/**
 * Remove property from compare list
 */
function removeFromCompare(propertyId) {
    const list = getCompareList().filter(id => id !== propertyId);
    saveCompareList(list);
}

/**
 * Toggle property in compare list (вызывается из onchange на чекбоксе).
 */
function toggleCompare(propertyId) {
    const list = getCompareList();
    if (list.includes(propertyId)) {
        removeFromCompare(propertyId);
    } else {
        addToCompare(propertyId);
    }
}

/**
 * Update UI:
 *   - Бейдж navCompareCount на ссылке "Сравнить" в шапке.
 *   - Ссылка ведёт на compare.php?ids=…
 */
function updateCompareUI() {
    const list = getCompareList();
    const count = list.length;

    const navCount = document.getElementById('navCompareCount');
    if (navCount) {
        navCount.textContent = count;
        navCount.style.display = count > 0 ? '' : 'none';
    }

    const navLink = document.getElementById('navCompareLink');
    if (navLink && count > 0) {
        navLink.href = `compare.php?ids=${list.join(',')}`;
    }
}

/**
 * Initialize compare checkboxes on page load:
 * — отмечаем чекбоксы тех объектов, что уже в списке;
 * — обновляем счётчик в шапке.
 */
function initCompareCheckboxes() {
    const list = getCompareList();

    document.querySelectorAll('.compare-checkbox__input').forEach(checkbox => {
        const propertyId = parseInt(checkbox.getAttribute('data-property-id'), 10);
        if (list.includes(propertyId)) {
            checkbox.checked = true;
        }
    });

    updateCompareUI();
}

// Initialize on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCompareCheckboxes);
} else {
    initCompareCheckboxes();
}