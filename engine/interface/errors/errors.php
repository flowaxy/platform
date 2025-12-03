<?php
/**
 * Універсальний обробник HTTP помилок
 * Підтримує всі стандартні HTTP коди помилок
 * 
 * @var int $httpCode HTTP код помилки
 * @var string|null $customTitle Користувацький заголовок
 * @var string|null $customMessage Користувацьке повідомлення
 * @var array|null $debugInfo Debug інформація
 */

// Отримуємо HTTP код помилки
$httpCode = isset($httpCode) ? (int)$httpCode : (isset($_GET['code']) ? (int)$_GET['code'] : 500);

// Мапа всіх HTTP кодів помилок
$errorCodes = [
    400 => [
        'title' => 'Некорректний запит',
        'message' => 'Сервер не зміг обробити запит через некоректний синтаксис. Перевірте правильність введених даних.',
        'uk' => ['title' => 'Некорректний запит', 'message' => 'Сервер не зміг обробити запит через некоректний синтаксис. Перевірте правильність введених даних.'],
        'ru' => ['title' => 'Некорректный запрос', 'message' => 'Сервер не смог обработать запрос из-за некорректного синтаксиса. Проверьте правильность введенных данных.'],
        'en' => ['title' => 'Bad Request', 'message' => 'The server could not process the request due to invalid syntax. Please check the data you entered.'],
    ],
    401 => [
        'title' => 'Потрібна авторизація',
        'message' => 'Для доступу до цього ресурсу необхідно авторизуватися. Будь ласка, увійдіть у систему.',
        'uk' => ['title' => 'Потрібна авторизація', 'message' => 'Для доступу до цього ресурсу необхідно авторизуватися. Будь ласка, увійдіть у систему.'],
        'ru' => ['title' => 'Требуется авторизация', 'message' => 'Для доступа к этому ресурсу необходимо авторизоваться. Пожалуйста, войдите в систему.'],
        'en' => ['title' => 'Unauthorized', 'message' => 'Authentication is required to access this resource. Please log in.'],
    ],
    403 => [
        'title' => 'Доступ заборонено',
        'message' => 'У вас немає прав доступу до цього ресурсу. Зверніться до адміністратора для отримання доступу.',
        'uk' => ['title' => 'Доступ заборонено', 'message' => 'У вас немає прав доступу до цього ресурсу. Зверніться до адміністратора для отримання доступу.'],
        'ru' => ['title' => 'Доступ запрещён', 'message' => 'У вас нет прав доступа к этому ресурсу. Обратитесь к администратору для получения доступа.'],
        'en' => ['title' => 'Forbidden', 'message' => 'You do not have permission to access this resource. Contact your administrator for access.'],
    ],
    404 => [
        'title' => 'Сторінку не знайдено',
        'message' => 'Запитана сторінка не існує або була переміщена. Перевірте правильність введеної адреси.',
        'uk' => ['title' => 'Сторінку не знайдено', 'message' => 'Запитана сторінка не існує або була переміщена. Перевірте правильність введеної адреси.'],
        'ru' => ['title' => 'Страница не найдена', 'message' => 'Запрошенная страница не существует или была перемещена. Проверьте правильность введенного адреса.'],
        'en' => ['title' => 'Page Not Found', 'message' => 'The requested page does not exist or has been moved. Please check the address you entered.'],
    ],
    405 => [
        'title' => 'Метод не підтримується',
        'message' => 'Використаний HTTP метод не підтримується для цього ресурсу. Перевірте документацію API.',
        'uk' => ['title' => 'Метод не підтримується', 'message' => 'Використаний HTTP метод не підтримується для цього ресурсу. Перевірте документацію API.'],
        'ru' => ['title' => 'Метод не поддерживается', 'message' => 'Использованный HTTP метод не поддерживается для этого ресурса. Проверьте документацию API.'],
        'en' => ['title' => 'Method Not Allowed', 'message' => 'The HTTP method used is not supported for this resource. Please check the API documentation.'],
    ],
    408 => [
        'title' => 'Час очікування вичерпано',
        'message' => 'Сервер не отримав повний запит протягом відведеного часу. Спробуйте повторити запит.',
        'uk' => ['title' => 'Час очікування вичерпано', 'message' => 'Сервер не отримав повний запит протягом відведеного часу. Спробуйте повторити запит.'],
        'ru' => ['title' => 'Время ожидания истекло', 'message' => 'Сервер не получил полный запрос в течение отведенного времени. Попробуйте повторить запрос.'],
        'en' => ['title' => 'Request Timeout', 'message' => 'The server did not receive a complete request within the allotted time. Please try again.'],
    ],
    409 => [
        'title' => 'Конфлікт запиту',
        'message' => 'Запит конфліктує з поточним станом ресурсу. Перевірте конфліктуючі дані та спробуйте ще раз.',
        'uk' => ['title' => 'Конфлікт запиту', 'message' => 'Запит конфліктує з поточним станом ресурсу. Перевірте конфліктуючі дані та спробуйте ще раз.'],
        'ru' => ['title' => 'Конфликт запроса', 'message' => 'Запрос конфликтует с текущим состоянием ресурса. Проверьте конфликтующие данные и попробуйте снова.'],
        'en' => ['title' => 'Conflict', 'message' => 'The request conflicts with the current state of the resource. Please check conflicting data and try again.'],
    ],
    410 => [
        'title' => 'Сторінка видалена',
        'message' => 'Запитаний ресурс більше не доступний і був безповоротно видалений. Перевірте актуальні посилання.',
        'uk' => ['title' => 'Сторінка видалена', 'message' => 'Запитаний ресурс більше не доступний і був безповоротно видалений. Перевірте актуальні посилання.'],
        'ru' => ['title' => 'Страница удалена', 'message' => 'Запрошенный ресурс больше не доступен и был безвозвратно удален. Проверьте актуальные ссылки.'],
        'en' => ['title' => 'Gone', 'message' => 'The requested resource is no longer available and has been permanently removed. Please check current links.'],
    ],
    413 => [
        'title' => 'Запит занадто великий',
        'message' => 'Розмір запиту перевищує максимально допустимий ліміт. Спробуйте зменшити розмір даних.',
        'uk' => ['title' => 'Запит занадто великий', 'message' => 'Розмір запиту перевищує максимально допустимий ліміт. Спробуйте зменшити розмір даних.'],
        'ru' => ['title' => 'Слишком большой запрос', 'message' => 'Размер запроса превышает максимально допустимый лимит. Попробуйте уменьшить размер данных.'],
        'en' => ['title' => 'Payload Too Large', 'message' => 'The request size exceeds the maximum allowed limit. Please reduce the data size.'],
    ],
    414 => [
        'title' => 'URL занадто довгий',
        'message' => 'Довжина URL перевищує максимально допустимий ліміт. Спробуйте використати коротший адрес.',
        'uk' => ['title' => 'URL занадто довгий', 'message' => 'Довжина URL перевищує максимально допустимий ліміт. Спробуйте використати коротший адрес.'],
        'ru' => ['title' => 'Слишком длинный URL', 'message' => 'Длина URL превышает максимально допустимый лимит. Попробуйте использовать более короткий адрес.'],
        'en' => ['title' => 'URI Too Long', 'message' => 'The URL length exceeds the maximum allowed limit. Please use a shorter address.'],
    ],
    415 => [
        'title' => 'Непідтримуваний тип даних',
        'message' => 'Формат даних у запиті не підтримується сервером. Перевірте тип контенту та спробуйте ще раз.',
        'uk' => ['title' => 'Непідтримуваний тип даних', 'message' => 'Формат даних у запиті не підтримується сервером. Перевірте тип контенту та спробуйте ще раз.'],
        'ru' => ['title' => 'Неподдерживаемый тип данных', 'message' => 'Формат данных в запросе не поддерживается сервером. Проверьте тип контента и попробуйте снова.'],
        'en' => ['title' => 'Unsupported Media Type', 'message' => 'The data format in the request is not supported by the server. Please check the content type and try again.'],
    ],
    429 => [
        'title' => 'Занадто багато запитів',
        'message' => 'Ви перевищили ліміт кількості запитів. Зачекайте деякий час перед повторною спробою.',
        'uk' => ['title' => 'Занадто багато запитів', 'message' => 'Ви перевищили ліміт кількості запитів. Зачекайте деякий час перед повторною спробою.'],
        'ru' => ['title' => 'Слишком много запросов', 'message' => 'Вы превысили лимит количества запросов. Подождите некоторое время перед повторной попыткой.'],
        'en' => ['title' => 'Too Many Requests', 'message' => 'You have exceeded the request limit. Please wait a moment before trying again.'],
    ],
    500 => [
        'title' => 'Внутрішня помилка сервера',
        'message' => 'Сталася несподівана помилка на сервері. Спробуйте пізніше або зверніться до адміністратора.',
        'uk' => ['title' => 'Внутрішня помилка сервера', 'message' => 'Сталася несподівана помилка на сервері. Спробуйте пізніше або зверніться до адміністратора.'],
        'ru' => ['title' => 'Внутренняя ошибка сервера', 'message' => 'Произошла неожиданная ошибка на сервере. Попробуйте позже или обратитесь к администратору.'],
        'en' => ['title' => 'Internal Server Error', 'message' => 'An unexpected error occurred on the server. Please try again later or contact the administrator.'],
    ],
    501 => [
        'title' => 'Метод не реалізовано',
        'message' => 'Сервер не підтримує функціональність, необхідну для обробки цього запиту.',
        'uk' => ['title' => 'Метод не реалізовано', 'message' => 'Сервер не підтримує функціональність, необхідну для обробки цього запиту.'],
        'ru' => ['title' => 'Метод не реализован', 'message' => 'Сервер не поддерживает функциональность, необходимую для обработки этого запроса.'],
        'en' => ['title' => 'Not Implemented', 'message' => 'The server does not support the functionality required to process this request.'],
    ],
    502 => [
        'title' => 'Помилка шлюзу',
        'message' => 'Сервер отримав некоректну відповідь від вищестоячого сервера. Спробуйте пізніше.',
        'uk' => ['title' => 'Помилка шлюзу', 'message' => 'Сервер отримав некоректну відповідь від вищестоячого сервера. Спробуйте пізніше.'],
        'ru' => ['title' => 'Ошибка шлюза', 'message' => 'Сервер получил некорректный ответ от вышестоящего сервера. Попробуйте позже.'],
        'en' => ['title' => 'Bad Gateway', 'message' => 'The server received an invalid response from an upstream server. Please try again later.'],
    ],
    503 => [
        'title' => 'Сервіс тимчасово недоступний',
        'message' => 'Сервер тимчасово недоступний через технічне обслуговування або перевантаження. Спробуйте пізніше.',
        'uk' => ['title' => 'Сервіс тимчасово недоступний', 'message' => 'Сервер тимчасово недоступний через технічне обслуговування або перевантаження. Спробуйте пізніше.'],
        'ru' => ['title' => 'Сервер временно недоступен', 'message' => 'Сервер временно недоступен из-за технического обслуживания или перегрузки. Попробуйте позже.'],
        'en' => ['title' => 'Service Unavailable', 'message' => 'The server is temporarily unavailable due to maintenance or overload. Please try again later.'],
    ],
    504 => [
        'title' => 'Шлюз не відповів вчасно',
        'message' => 'Сервер не отримав відповідь від вищестоячого сервера вчасно. Спробуйте пізніше.',
        'uk' => ['title' => 'Шлюз не відповів вчасно', 'message' => 'Сервер не отримав відповідь від вищестоячого сервера вчасно. Спробуйте пізніше.'],
        'ru' => ['title' => 'Шлюз не ответил вовремя', 'message' => 'Сервер не получил ответ от вышестоящего сервера вовремя. Попробуйте позже.'],
        'en' => ['title' => 'Gateway Timeout', 'message' => 'The server did not receive a response from an upstream server in time. Please try again later.'],
    ],
    505 => [
        'title' => 'Версія HTTP не підтримується',
        'message' => 'Сервер не підтримує версію HTTP протоколу, використану в запиті.',
        'uk' => ['title' => 'Версія HTTP не підтримується', 'message' => 'Сервер не підтримує версію HTTP протоколу, використану в запиті.'],
        'ru' => ['title' => 'Версия HTTP не поддерживается', 'message' => 'Сервер не поддерживает версию HTTP протокола, использованную в запросе.'],
        'en' => ['title' => 'HTTP Version Not Supported', 'message' => 'The server does not support the HTTP protocol version used in the request.'],
    ],
    507 => [
        'title' => 'Недостатньо місця',
        'message' => 'На сервері недостатньо місця для обробки запиту. Зверніться до адміністратора.',
        'uk' => ['title' => 'Недостатньо місця', 'message' => 'На сервері недостатньо місця для обробки запиту. Зверніться до адміністратора.'],
        'ru' => ['title' => 'Недостаточно места', 'message' => 'На сервере недостаточно места для обработки запроса. Обратитесь к администратору.'],
        'en' => ['title' => 'Insufficient Storage', 'message' => 'There is insufficient storage space on the server to process the request. Contact the administrator.'],
    ],
    508 => [
        'title' => 'Виявлено цикл',
        'message' => 'Сервер виявив нескінченний цикл при обробці запиту. Зверніться до адміністратора.',
        'uk' => ['title' => 'Виявлено цикл', 'message' => 'Сервер виявив нескінченний цикл при обробці запиту. Зверніться до адміністратора.'],
        'ru' => ['title' => 'Обнаружен цикл', 'message' => 'Сервер обнаружил бесконечный цикл при обработке запроса. Обратитесь к администратору.'],
        'en' => ['title' => 'Loop Detected', 'message' => 'The server detected an infinite loop while processing the request. Contact the administrator.'],
    ],
    510 => [
        'title' => 'Потрібне розширення',
        'message' => 'Для обробки запиту потрібне додаткове розширення протоколу. Зверніться до адміністратора.',
        'uk' => ['title' => 'Потрібне розширення', 'message' => 'Для обробки запиту потрібне додаткове розширення протоколу. Зверніться до адміністратора.'],
        'ru' => ['title' => 'Требуется расширение', 'message' => 'Для обработки запроса требуется дополнительное расширение протокола. Обратитесь к администратору.'],
        'en' => ['title' => 'Not Extended', 'message' => 'An extension is required to process the request. Contact the administrator.'],
    ],
    511 => [
        'title' => 'Потрібна мережева аутентифікація',
        'message' => 'Необхідна аутентифікація для доступу до мережі. Підключіться до мережі та спробуйте ще раз.',
        'uk' => ['title' => 'Потрібна мережева аутентифікація', 'message' => 'Необхідна аутентифікація для доступу до мережі. Підключіться до мережі та спробуйте ще раз.'],
        'ru' => ['title' => 'Требуется сеть/аутентификация', 'message' => 'Требуется аутентификация для доступа к сети. Подключитесь к сети и попробуйте снова.'],
        'en' => ['title' => 'Network Authentication Required', 'message' => 'Network authentication is required to gain access. Connect to the network and try again.'],
    ],
];

// Визначаємо мову
$lang = 'uk'; // За замовчуванням
if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
    $acceptLang = strtolower(substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2));
    if (in_array($acceptLang, ['uk', 'ru', 'en'])) {
        $lang = $acceptLang;
    }
}

// Отримуємо дані помилки
$errorData = $errorCodes[$httpCode] ?? $errorCodes[500];
$localized = $errorData[$lang] ?? $errorData['uk'];

// Встановлюємо змінні
$errorCode = (string)$httpCode;
$errorTitle = $customTitle ?? $localized['title'];
$errorMessage = $customMessage ?? $localized['message'];
$title = "{$httpCode} - {$errorTitle}";

// Формуємо дії залежно від коду помилки
$actions = [];

if ($httpCode === 404) {
    $actions = [
        ['text' => 'На головну', 'href' => '/', 'type' => 'primary', 'icon' => 'fa-solid fa-house'],
        ['text' => 'Назад', 'onclick' => 'history.back()', 'type' => 'secondary', 'icon' => 'fa-solid fa-arrow-left'],
    ];
} elseif ($httpCode === 401 || $httpCode === 403) {
    $actions = [
        ['text' => 'На головну', 'href' => '/', 'type' => 'secondary', 'icon' => 'fa-solid fa-house'],
        ['text' => 'Увійти', 'href' => '/admin/login', 'type' => 'primary', 'icon' => 'fa-solid fa-sign-in-alt'],
    ];
} elseif ($httpCode === 429) {
    $actions = [
        ['text' => 'На головну', 'href' => '/', 'type' => 'secondary', 'icon' => 'fa-solid fa-house'],
        ['text' => 'Оновити сторінку', 'onclick' => 'location.reload()', 'type' => 'primary', 'icon' => 'fa-solid fa-rotate-right'],
    ];
} elseif ($httpCode >= 500) {
    $actions = [
        ['text' => 'На головну', 'href' => '/', 'type' => 'secondary', 'icon' => 'fa-solid fa-house'],
        ['text' => 'Оновити сторінку', 'onclick' => 'location.reload()', 'type' => 'primary', 'icon' => 'fa-solid fa-rotate-right'],
    ];
    
    // Додаємо посилання на адмін-панель, якщо користувач адмін
    if (function_exists('sessionManager')) {
        try {
            $session = sessionManager();
            if ($session && method_exists($session, 'has') && $session->has('admin_user_id')) {
                $adminUrl = class_exists('UrlHelper') ? UrlHelper::admin('dashboard') : '/admin';
                $actions[] = ['text' => 'Адмін-панель', 'href' => $adminUrl, 'type' => 'outline', 'icon' => 'fa-solid fa-gear'];
            }
        } catch (Exception $e) {
            // Ігноруємо
        }
    }
} else {
    $actions = [
        ['text' => 'На головну', 'href' => '/', 'type' => 'primary', 'icon' => 'fa-solid fa-house'],
        ['text' => 'Назад', 'onclick' => 'history.back()', 'type' => 'secondary', 'icon' => 'fa-solid fa-arrow-left'],
    ];
}

// Для помилок 500+ готуємо debug інформацію
$debugInfo = null;
if ($httpCode >= 500 && isset($showDebug) && $showDebug) {
    $debugInfo = [];
    if (isset($type)) $debugInfo['type'] = $type;
    if (isset($message)) $debugInfo['message'] = $message;
    if (isset($file)) $debugInfo['file'] = $file;
    if (isset($line)) $debugInfo['line'] = $line;
    if (isset($code) && $code !== 0) $debugInfo['code'] = $code;
    if (isset($trace)) {
        $debugInfo['trace'] = $trace;
    }
    $debugInfo['php_version'] = PHP_VERSION;
    $debugInfo['timestamp'] = date('d.m.Y H:i:s');
    if (isset($_SERVER['REQUEST_URI'])) $debugInfo['request_uri'] = $_SERVER['REQUEST_URI'];
    if (isset($_SERVER['REQUEST_METHOD'])) $debugInfo['request_method'] = $_SERVER['REQUEST_METHOD'];
}

// Підключаємо layout
require __DIR__ . '/layout.php';

