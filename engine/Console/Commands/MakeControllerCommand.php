<?php

/**
 * Команда генерації контролера
 *
 * @package Flowaxy\Core\System\Commands
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

namespace Flowaxy\Core\System\Commands;

class MakeControllerCommand extends MakeCommand
{
    /**
     * Генерація контролера
     *
     * @param array $args Аргументи команди
     * @return void
     */
    public function run(array $args): void
    {
        if (empty($args[0])) {
            echo "Помилка: не вказано ім'я контролера\n";
            echo "Використання: make:controller <ControllerName> [--namespace=Namespace] [--type=api|http]\n";
            exit(1);
        }

        $controllerName = $this->normalizeClassName($args[0] ?? '');
        if (empty($controllerName)) {
            echo "Помилка: не вказано ім'я контролера\n";
            echo "Використання: make:controller <ControllerName> [--namespace=Namespace] [--type=api|http]\n";
            exit(1);
        }

        if (!str_ends_with($controllerName, 'Controller')) {
            $controllerName .= 'Controller';
        }

        $namespace = $args['namespace'] ?? 'Application\\Controllers';
        $type = $args['type'] ?? 'http';

        // Визначаємо шлях до файлу
        $namespacePath = str_replace('\\', DIRECTORY_SEPARATOR, $namespace);
        $controllerDir = $this->rootDir . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'controllers';
        if ($namespace !== 'Application\\Controllers') {
            $controllerDir = $this->rootDir . DIRECTORY_SEPARATOR . strtolower($namespacePath);
        }

        $controllerFile = $controllerDir . DIRECTORY_SEPARATOR . $controllerName . '.php';

        if ($this->fileExists($controllerFile)) {
            echo "Помилка: файл {$controllerFile} вже існує\n";
            exit(1);
        }

        // Генеруємо вміст контролера
        $content = $this->generateControllerContent($controllerName, $namespace, $type);

        if ($this->writeFile($controllerFile, $content)) {
            echo "✓ Контролер {$controllerName} успішно створено: {$controllerFile}\n";
        } else {
            echo "✗ Помилка при створенні контролера {$controllerName}\n";
            exit(1);
        }
    }

    /**
     * Генерація вмісту контролера
     *
     * @param string $controllerName
     * @param string $namespace
     * @param string $type
     * @return string
     */
    private function generateControllerContent(string $controllerName, string $namespace, string $type): string
    {
        $namespaceEscaped = str_replace('\\', '\\\\', $namespace);
        $baseClass = $type === 'api' ? 'RestApiController' : 'Controller';
        $baseNamespace = $type === 'api'
            ? 'Flowaxy\\Core\\Interface\\Api\\RestApiController'
            : 'Flowaxy\\Core\\Interface\\Http\\Controllers\\Controller';

        if ($type === 'api') {
            return <<<PHP
<?php

/**
 * API Контролер {$controllerName}
 *
 * @package {$namespaceEscaped}
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

namespace {$namespace};

require_once __DIR__ . '/../../interface/api/RestApiController.php';

use {$baseNamespace};

class {$controllerName} extends {$baseClass}
{
    /**
     * Приклад GET endpoint
     *
     * @return void
     */
    public function index(): void
    {
        \$this->json([
            'message' => 'Hello from {$controllerName}',
            'data' => []
        ]);
    }

    /**
     * Приклад POST endpoint
     *
     * @return void
     */
    public function store(): void
    {
        \$data = \$this->getRequestData();

        \$this->json([
            'message' => 'Data stored successfully',
            'data' => \$data
        ], 201);
    }

    /**
     * Приклад GET endpoint з параметром
     *
     * @param int \$id
     * @return void
     */
    public function show(int \$id): void
    {
        \$this->json([
            'message' => 'Resource found',
            'data' => ['id' => \$id]
        ]);
    }

    /**
     * Приклад PUT/PATCH endpoint
     *
     * @param int \$id
     * @return void
     */
    public function update(int \$id): void
    {
        \$data = \$this->getRequestData();

        \$this->json([
            'message' => 'Resource updated successfully',
            'data' => array_merge(['id' => \$id], \$data)
        ]);
    }

    /**
     * Приклад DELETE endpoint
     *
     * @param int \$id
     * @return void
     */
    public function destroy(int \$id): void
    {
        \$this->json([
            'message' => 'Resource deleted successfully'
        ], 204);
    }
}
PHP;
        }

        return <<<PHP
<?php

/**
 * HTTP Контролер {$controllerName}
 *
 * @package {$namespaceEscaped}
 * @version 1.0.0 Alpha prerelease
 */

declare(strict_types=1);

namespace {$namespace};

require_once __DIR__ . '/../../interface/http/controllers/Request.php';
require_once __DIR__ . '/../../interface/http/controllers/Response.php';

use Flowaxy\Core\Interface\Http\Controllers\Request;
use Flowaxy\Core\Interface\Http\Controllers\Response;

class {$controllerName}
{
    /**
     * Приклад методу index
     *
     * @return void
     */
    public function index(): void
    {
        \$request = Request::getInstance();

        Response::view('path/to/view', [
            'title' => 'Page Title',
            'data' => []
        ]);
    }

    /**
     * Приклад методу show
     *
     * @param int \$id
     * @return void
     */
    public function show(int \$id): void
    {
        \$request = Request::getInstance();

        Response::json([
            'id' => \$id,
            'data' => []
        ]);
    }

    /**
     * Приклад методу store (POST)
     *
     * @return void
     */
    public function store(): void
    {
        \$request = Request::getInstance();
        \$data = \$request->all();

        // Обробка даних

        Response::redirect('/success');
    }
}
PHP;
    }
}
