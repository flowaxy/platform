<?php
/**
 * Компонент заголовка страницы
 */
?>
<?php if (! empty($pageHeaderTitle)): ?>
<div class="page-header">
    <div class="container-fluid">
        <div class="page-header-content d-flex justify-content-between align-items-center flex-wrap">
            <div class="page-title-section">
                <?php if (! empty($pageHeaderIcon)): ?>
                    <div class="page-icon-wrapper">
                        <div class="page-icon">
                            <i class="<?= $pageHeaderIcon ?>"></i>
                        </div>
                    </div>
                <?php endif; ?>
                <div class="page-title-content">
                    <h1><?= htmlspecialchars($pageHeaderTitle) ?></h1>
                    <?php if (! empty($pageHeaderDescription)): ?>
                        <p class="page-description"><?= htmlspecialchars($pageHeaderDescription) ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (! empty($pageHeaderButtons)): ?>
                <div class="page-actions d-flex align-items-center gap-2">
                    <?= $pageHeaderButtons ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>
