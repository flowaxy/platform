<?php
/**
 * Компонент хлібних крихт
 */
?>
<?php if (! empty($pageBreadcrumbs) && is_array($pageBreadcrumbs)): ?>
<div class="page-breadcrumbs-wrapper">
    <div class="container-fluid">
        <nav class="page-breadcrumbs" aria-label="breadcrumb">
            <ol class="breadcrumb">
                <?php foreach ($pageBreadcrumbs as $index => $crumb): ?>
                    <?php if (isset($crumb['title'])): ?>
                        <?php if ($index === count($pageBreadcrumbs) - 1): ?>
                            <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($crumb['title']) ?></li>
                        <?php else: ?>
                            <li class="breadcrumb-item">
                                <a href="<?= isset($crumb['url']) ? htmlspecialchars($crumb['url']) : '#' ?>"><?= htmlspecialchars($crumb['title']) ?></a>
                            </li>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ol>
        </nav>
    </div>
</div>
<?php endif; ?>

