<?php
/**
 * Шаблон страницы настроек (карточки в сетке по категориям)
 *
 * @var array $settingsCategories Категории настроек
 */
?>

<?php foreach ($settingsCategories as $categoryKey => $category): ?>
    <?php if (! empty($category['items'])): ?>
        <div class="settings-category mb-5">
            <h4 class="settings-category-title mb-3">
                <i class="<?= htmlspecialchars($category['icon']) ?> me-2"></i>
                <?= htmlspecialchars($category['title']) ?>
            </h4>
            <div class="row g-3">
                <?php foreach ($category['items'] as $item): ?>
                    <div class="col-xl-4 col-lg-4 col-md-6 col-sm-12">
                        <a href="<?= htmlspecialchars($item['url']) ?>" class="settings-card-link">
                            <div class="settings-card">
                                <div class="settings-card-icon">
                                    <i class="<?= htmlspecialchars($item['icon']) ?>"></i>
                                </div>
                                <div class="settings-card-content">
                                    <h5 class="settings-card-title"><?= htmlspecialchars($item['title']) ?></h5>
                                    <p class="settings-card-description"><?= htmlspecialchars($item['description'] ?? '') ?></p>
                                </div>
                                <div class="settings-card-arrow">
                                    <i class="fas fa-arrow-right"></i>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
<?php endforeach; ?>

<style>
.settings-category {
    margin-bottom: 2.5rem;
}

.settings-category:last-child {
    margin-bottom: 0;
}

.settings-category-title {
    font-size: 1.125rem;
    font-weight: 600;
    color: #23282d;
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid #e1e5e9;
}

.settings-category-title i {
    color: #0073aa;
    font-size: 1rem;
}

.settings-card-link {
    text-decoration: none;
    color: inherit;
    display: block;
    height: 100%;
}

.settings-card {
    background: #ffffff;
    border: 1px solid #e1e5e9;
    border-radius: 6px;
    padding: 1rem 1.25rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: all 0.2s ease;
    height: 100%;
    position: relative;
    min-height: 80px;
}

.settings-card:hover {
    border-color: #0073aa;
    box-shadow: 0 2px 8px rgba(0, 115, 170, 0.15);
    text-decoration: none;
}

.settings-card-icon {
    width: 48px;
    height: 48px;
    min-width: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f0f6fc;
    border-radius: 6px;
    flex-shrink: 0;
}

.settings-card-icon i {
    font-size: 1.25rem;
    color: #0073aa;
}

.settings-card-content {
    flex: 1;
    min-width: 0;
}

.settings-card-title {
    font-size: 0.9375rem;
    font-weight: 600;
    color: #0073aa;
    margin: 0 0 0.25rem 0;
    line-height: 1.3;
}

.settings-card-description {
    font-size: 0.8125rem;
    color: #666;
    margin: 0;
    line-height: 1.4;
}

.settings-card-arrow {
    color: #999;
    transition: all 0.2s ease;
    opacity: 0.6;
    flex-shrink: 0;
}

.settings-card:hover .settings-card-arrow {
    transform: translateX(3px);
    opacity: 1;
    color: #0073aa;
}

.settings-card-arrow i {
    font-size: 0.75rem;
}

@media (max-width: 1200px) {
    .settings-card {
        padding: 1rem;
        min-height: 75px;
    }
    
    .settings-card-icon {
        width: 44px;
        height: 44px;
        min-width: 44px;
    }
    
    .settings-card-icon i {
        font-size: 1.125rem;
    }
}

@media (max-width: 768px) {
    .settings-card {
        padding: 0.875rem 1rem;
        min-height: 70px;
        gap: 0.75rem;
    }
    
    .settings-card-icon {
        width: 40px;
        height: 40px;
        min-width: 40px;
    }
    
    .settings-card-icon i {
        font-size: 1rem;
    }
    
    .settings-card-title {
        font-size: 0.875rem;
    }
    
    .settings-card-description {
        font-size: 0.75rem;
    }
    
    .settings-category-title {
        font-size: 1rem;
    }
}
</style>
