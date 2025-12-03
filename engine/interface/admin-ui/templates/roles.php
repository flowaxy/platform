<?php
/**
 * Шаблон сторінки управління ролями
 */

$roles = $roles ?? [];
$permissions = $permissions ?? [];
?>

<?php if (!empty($message)): ?>
    <?php include __DIR__ . '/../components/alert.php'; ?>
<?php endif; ?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Список ролей</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Назва</th>
                                <th>Slug</th>
                                <th>Опис</th>
                                <th>Права</th>
                                <th>Тип</th>
                                <th>Дії</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($roles as $role): ?>
                                <tr>
                                    <td><?= htmlspecialchars($role['name']) ?></td>
                                    <td><code><?= htmlspecialchars($role['slug']) ?></code></td>
                                    <td><?= htmlspecialchars($role['description'] ?? '') ?></td>
                                    <td>
                                        <?php if (!empty($role['permissions'])): ?>
                                            <?= count($role['permissions']) ?> прав
                                        <?php else: ?>
                                            <span class="text-muted">Немає</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($role['is_system']): ?>
                                            <span class="badge bg-info">Системна</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Користувацька</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!$role['is_system']): ?>
                                            <button class="btn btn-sm btn-primary" onclick="editRole(<?= $role['id'] ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Видалити роль?');">
                                                <?= SecurityHelper::csrfField() ?>
                                                <input type="hidden" name="action" value="delete_role">
                                                <input type="hidden" name="role_id" value="<?= $role['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function editRole(roleId) {
    alert('Редагування ролі ID: ' + roleId);
}
</script>
