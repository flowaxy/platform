<?php
/**
 * Шаблон профиля пользователя
 */
if (! $user) {
    include __DIR__ . '/../components/alert.php';
    $message = 'Користувач не знайдено';
    $type = 'danger';
    $dismissible = false;

    return;
}
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Редагування профілю</h5>
            </div>
            <div class="card-body">
                <?php
                if (! empty($message)) {
                    include __DIR__ . '/../components/alert.php';
                    $type = $messageType ?? 'info';
                    $dismissible = true;
                }
?>
                
                <form method="POST" id="profileForm">
                    <input type="hidden" name="csrf_token" value="<?= SecurityHelper::csrfToken() ?>">
                    <input type="hidden" name="save_profile" value="1">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Логін *</label>
                                <input type="text" class="form-control" name="username" 
                                       value="<?= htmlspecialchars($user['username'] ?? '') ?>" 
                                       required 
                                       minlength="3"
                                       maxlength="50">
                                <small class="form-text text-muted">Мінімум 3 символи, максимум 50</small>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" 
                                       value="<?= htmlspecialchars($user['email'] ?? '') ?>" 
                                       placeholder="example@mail.com">
                                <small class="form-text text-muted">Email адреса для повідомлень</small>
                            </div>
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <h6 class="fw-bold mb-3">Зміна пароля</h6>
                    <p class="text-muted small mb-3">Залиште поля порожніми, якщо не хочете змінювати пароль</p>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Поточний пароль</label>
                                <input type="password" class="form-control" name="current_password" 
                                       id="current_password"
                                       autocomplete="current-password">
                                <small class="form-text text-muted">Потрібен для зміни пароля</small>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Новий пароль</label>
                                <input type="password" class="form-control" name="new_password" 
                                       id="new_password"
                                       minlength="<?= PASSWORD_MIN_LENGTH ?>"
                                       autocomplete="new-password">
                                <small class="form-text text-muted">Мінімум <?= PASSWORD_MIN_LENGTH ?> символів</small>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Підтвердження пароля</label>
                                <input type="password" class="form-control" name="confirm_password" 
                                       id="confirm_password"
                                       minlength="<?= PASSWORD_MIN_LENGTH ?>"
                                       autocomplete="new-password">
                                <small class="form-text text-muted">Повторіть новий пароль</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="showPasswords">
                            <label class="form-check-label" for="showPasswords">
                                Показати паролі
                            </label>
                        </div>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Зберегти зміни
                        </button>
                        <button type="reset" class="btn btn-secondary" onclick="resetForm()">
                            <i class="fas fa-undo me-2"></i>Скинути
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Інформація про профіль</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td class="text-muted" style="width: 150px;"><strong>ID користувача:</strong></td>
                                <td><?= htmlspecialchars($user['id'] ?? '') ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted"><strong>Логін:</strong></td>
                                <td><?= htmlspecialchars($user['username'] ?? '') ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted"><strong>Email:</strong></td>
                                <td><?= ! empty($user['email']) ? htmlspecialchars($user['email']) : '<span class="text-muted">Не вказано</span>' ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <div class="alert alert-info mb-0">
                            <h6 class="alert-heading"><i class="fas fa-info-circle me-2"></i>Поради безпеки</h6>
                            <ul class="mb-0 small">
                                <li>Використовуйте складний пароль (мінімум <?= PASSWORD_MIN_LENGTH ?> символів)</li>
                                <li>Не використовуйте один пароль для різних сервісів</li>
                                <li>Регулярно змінюйте пароль</li>
                                <li>Не передавайте свої дані доступу третім особам</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Показать/скрыть пароли
document.getElementById('showPasswords')?.addEventListener('change', function() {
    const type = this.checked ? 'text' : 'password';
    document.getElementById('current_password').type = type;
    document.getElementById('new_password').type = type;
    document.getElementById('confirm_password').type = type;
});

// Валидация формы
document.getElementById('profileForm')?.addEventListener('submit', function(e) {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    const currentPassword = document.getElementById('current_password').value;
    
    // Если введен новый пароль, проверяем остальные поля
    if (newPassword || confirmPassword || currentPassword) {
        if (!currentPassword) {
            e.preventDefault();
            alert('Для зміни пароля необхідно ввести поточний пароль');
            return false;
        }
        
        if (!newPassword) {
            e.preventDefault();
            alert('Введіть новий пароль');
            return false;
        }
        
        if (newPassword.length < <?= PASSWORD_MIN_LENGTH ?>) {
            e.preventDefault();
            alert('Пароль повинен містити мінімум <?= PASSWORD_MIN_LENGTH ?> символів');
            return false;
        }
        
        if (newPassword !== confirmPassword) {
            e.preventDefault();
            alert('Нові паролі не співпадають');
            return false;
        }
    }
});

// Сброс формы
function resetForm() {
    if (confirm('Ви впевнені, що хочете скинути всі зміни?')) {
        document.getElementById('profileForm').reset();
        document.getElementById('current_password').value = '';
        document.getElementById('new_password').value = '';
        document.getElementById('confirm_password').value = '';
        document.getElementById('showPasswords').checked = false;
    }
}
</script>

