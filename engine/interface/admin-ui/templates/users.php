<?php
/**
 * Шаблон сторінки управління користувачами
 */

$users = $users ?? [];
$roles = $roles ?? [];
?>

<?php if (!empty($message)): ?>
    <?php include __DIR__ . '/../components/alert.php'; ?>
<?php endif; ?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Список користувачів</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Логін</th>
                                <th>Email</th>
                                <th>Ролі</th>
                                <th>Статус</th>
                                <th>Остання активність</th>
                                <th>Дії</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?= $user['id'] ?></td>
                                    <td><?= htmlspecialchars($user['username']) ?></td>
                                    <td><?= htmlspecialchars($user['email'] ?? '') ?></td>
                                    <td>
                                        <?php if (!empty($user['roles'])): ?>
                                            <?php foreach ($user['roles'] as $role): ?>
                                                <span class="badge bg-primary"><?= htmlspecialchars($role['name']) ?></span>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span class="text-muted">Немає ролей</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($user['is_active']): ?>
                                            <span class="badge bg-success">Активний</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Неактивний</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($user['last_activity'] ?? 'Ніколи') ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-primary" onclick="editUser(<?= $user['id'] ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if ($user['id'] !== 1): ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Видалити користувача?');">
                                                <?= SecurityHelper::csrfField() ?>
                                                <input type="hidden" name="action" value="delete_user">
                                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
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
function editUser(userId) {
    alert('Редагування користувача ID: ' + userId);
}
</script>
