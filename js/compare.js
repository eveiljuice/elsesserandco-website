/**
 * Property Comparison System - Elsesser & Co.
 * Система сравнения объектов недвижимости
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
    let list = getCompareList();
    
    // Check if already in list
    if (list.includes(propertyId)) {
        return true;
    }
    
    // Check max limit
    if (list.length >= MAX_COMPARE) {
        alert(`Можно сравнить максимум ${MAX_COMPARE} объекта одновременно`);
        // Uncheck the checkbox
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
    let list = getCompareList();
    list = list.filter(id => id !== propertyId);
    saveCompareList(list);
}

/**
 * Toggle property in compare list
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
 * Clear all compare list
 */
function clearCompare() {
    if (confirm('Очистить весь список сравнения?')) {
        localStorage.removeItem('compareList');
        updateCompareUI();
        
        // Uncheck all checkboxes
        document.querySelectorAll('.compare-checkbox__input').forEach(cb => {
            cb.checked = false;
        });
    }
}

/**
 * Update compare UI elements
 */
function updateCompareUI() {
    const list = getCompareList();
    const count = list.length;
    
    // Update counter
    const counterElem = document.getElementById('compareCount');
    if (counterElem) {
        counterElem.textContent = count;
    }
    
    // Update compare bar visibility
    const compareBar = document.getElementById('compareBar');
    if (compareBar) {
        if (count > 0) {
            compareBar.classList.add('active');
        } else {
            compareBar.classList.remove('active');
        }
    }
    
    // Update compare link
    const compareLink = document.getElementById('compareLink');
    if (compareLink && count > 0) {
        compareLink.href = `compare.php?ids=${list.join(',')}`;
    }
}

/**
 * Initialize compare checkboxes on page load
 */
function initCompareCheckboxes() {
    const list = getCompareList();
    
    // Check checkboxes for properties in compare list
    document.querySelectorAll('.compare-checkbox__input').forEach(checkbox => {
        const propertyId = parseInt(checkbox.getAttribute('data-property-id'));
        if (list.includes(propertyId)) {
            checkbox.checked = true;
        }
    });
    
    // Update UI
    updateCompareUI();
}

// Initialize on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', updateCompareUI);
} else {
    updateCompareUI();
}
