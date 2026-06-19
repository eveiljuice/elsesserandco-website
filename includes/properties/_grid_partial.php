<?php
/** @var array $result @var array $filters */
$properties = $result['items'];
$category = $filters['category'];
$page = $result['page'];
$totalPages = $result['totalPages'];
$queryBase = $_GET;
unset($queryBase['page']);

if (empty($properties)): ?>
<div class="empty-state">
    <i class="fas fa-home"></i>
    <h3>Объекты не найдены</h3>
    <p>Попробуйте изменить параметры поиска</p>
    <a href="?category=<?= escape($category) ?>" class="btn btn--secondary">Сбросить фильтры</a>
</div>
<?php else: ?>
<div class="properties-grid" id="propertiesGrid">
<?php foreach ($properties as $property):
    $isFavorite = !empty($property['is_favorite']);
    $roomsText = match((int)$property['bedrooms']) {
        0 => 'Студия', 1 => '1-комн.', 2 => '2-комн.', 3 => '3-комн.',
        default => $property['bedrooms'] . '-комн.'
    };
    $propUrl = !empty($property['slug'])
        ? '/property/' . (int)$property['id'] . '-' . rawurlencode((string)$property['slug'])
        : 'property.php?id=' . (int)$property['id'];
?>
<article class="property-card">
    <div class="property-card__image">
        <a href="<?= escape($propUrl) ?>">
            <img src="<?= escape($property['primary_image'] ?? 'https://via.placeholder.com/600x400') ?>"
                 alt="<?= escape($roomsText) ?>" class="property-card__img" loading="lazy">
        </a>
        <?php if ($property['featured']): ?><span class="property-card__badge">Рекомендуем</span><?php endif; ?>
        <button type="button" class="property-card__favorite favorite-btn <?= $isFavorite ? 'favorite-btn--active' : '' ?>"
                data-property-id="<?= (int)$property['id'] ?>">
            <i class="<?= $isFavorite ? 'fas' : 'far' ?> fa-heart"></i>
        </button>
        <label class="compare-checkbox" aria-label="Добавить в сравнение">
            <input type="checkbox" class="compare-checkbox__input" data-property-id="<?= (int)$property['id'] ?>" onchange="toggleCompare(<?= (int)$property['id'] ?>)">
            <i class="fas fa-balance-scale compare-checkbox__icon"></i>
        </label>
    </div>
    <div class="property-card__body">
        <div class="property-card__price"><?= formatPrice($property['price']) ?>
            <?php if ($category === 'rent'): ?><span class="property-card__period">/мес</span><?php endif; ?>
        </div>
        <h3 class="property-card__title">
            <a href="<?= escape($propUrl) ?>"><?= $roomsText ?>, <?= number_format((float)($property['area_total'] ?? $property['area_sqft']), 1) ?> м²</a>
        </h3>
        <div class="property-card__address"><i class="fas fa-map-marker-alt"></i> <?= escape($property['location'] ?? $property['street'] ?? '') ?></div>
        <?php if (!empty($property['district_name'])): ?>
        <div class="property-card__district"><?= escape($property['district_name']) ?></div>
        <?php endif; ?>
    </div>
</article>
<?php endforeach; ?>
</div>
<?php if ($totalPages > 1): ?>
<nav class="pagination" id="propertiesPagination">
    <?php if ($page > 1): ?>
    <a href="?<?= http_build_query(array_merge($queryBase, ['page' => $page - 1])) ?>" class="pagination__btn" data-page="<?= $page - 1 ?>">Назад</a>
    <?php endif; ?>
    <span class="pagination__info">Стр. <?= $page ?> / <?= $totalPages ?></span>
    <?php if ($page < $totalPages): ?>
    <a href="?<?= http_build_query(array_merge($queryBase, ['page' => $page + 1])) ?>" class="pagination__btn" data-page="<?= $page + 1 ?>">Вперёд</a>
    <?php endif; ?>
</nav>
<?php endif; ?>
<?php endif; ?>
