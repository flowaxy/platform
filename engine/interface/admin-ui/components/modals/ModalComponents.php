<?php
/**
 * Менеджер модальних компонентів
 * 
 * @version 1.0.0 Alpha prerelease
 */

namespace Flowaxy\AdminUI\Components\Modals;

class ModalComponents
{
    /**
     * Типи доступних модальних компонентів
     */
    const TYPE_UPLOAD = 'upload';
    const TYPE_CONFIRM = 'confirm';
    const TYPE_FORM = 'form';
    const TYPE_ALERT = 'alert';
    
    /**
     * Шлях до компонентів
     */
    private static string $componentsPath = __DIR__;
    
    /**
     * Зареєстровані модальні вікна
     */
    private static array $registered = [];
    
    /**
     * Чи підключено стилі
     */
    private static bool $stylesLoaded = false;
    
    /**
     * Реєстрація модального вікна
     * 
     * @param string $type Тип компонента (upload, confirm, form)
     * @param array $config Конфігурація
     * @return void
     */
    public static function register(string $type, array $config): void
    {
        $id = $config['id'] ?? uniqid('modal_');
        self::$registered[$id] = [
            'type' => $type,
            'config' => $config
        ];
    }
    
    /**
     * Реєстрація модального вікна завантаження
     * 
     * @param array $config
     * @return void
     */
    public static function upload(array $config): void
    {
        self::register(self::TYPE_UPLOAD, $config);
    }
    
    /**
     * Реєстрація модального вікна підтвердження
     * 
     * @param array $config
     * @return void
     */
    public static function confirm(array $config): void
    {
        self::register(self::TYPE_CONFIRM, $config);
    }
    
    /**
     * Реєстрація модального вікна з формою
     * 
     * @param array $config
     * @return void
     */
    public static function form(array $config): void
    {
        self::register(self::TYPE_FORM, $config);
    }
    
    /**
     * Рендер модального вікна за ID
     * 
     * @param string $id
     * @return string
     */
    public static function render(string $id): string
    {
        if (!isset(self::$registered[$id])) {
            return '';
        }
        
        $modal = self::$registered[$id];
        return self::renderComponent($modal['type'], $modal['config']);
    }
    
    /**
     * Рендер всіх зареєстрованих модальних вікон
     * 
     * @return string
     */
    public static function renderAll(): string
    {
        $output = self::getStyles();
        
        foreach (self::$registered as $id => $modal) {
            $output .= self::renderComponent($modal['type'], $modal['config']);
        }
        
        return $output;
    }
    
    /**
     * Рендер компонента
     * 
     * @param string $type
     * @param array $config
     * @return string
     */
    private static function renderComponent(string $type, array $config): string
    {
        $componentFile = self::$componentsPath . '/' . $type . '.php';
        
        if (!file_exists($componentFile)) {
            return "<!-- Modal component '{$type}' not found -->";
        }
        
        ob_start();
        include $componentFile;
        return ob_get_clean();
    }
    
    /**
     * Отримати стилі модальних вікон
     * 
     * @return string
     */
    public static function getStyles(): string
    {
        if (self::$stylesLoaded) {
            return '';
        }
        
        self::$stylesLoaded = true;
        
        $cssFile = self::$componentsPath . '/modals.css';
        if (!file_exists($cssFile)) {
            return '';
        }
        
        $css = file_get_contents($cssFile);
        return "<style>\n{$css}\n</style>\n";
    }
    
    /**
     * Очистити зареєстровані модальні вікна
     * 
     * @return void
     */
    public static function clear(): void
    {
        self::$registered = [];
    }
    
    /**
     * Отримати список зареєстрованих модалів
     * 
     * @return array
     */
    public static function getRegistered(): array
    {
        return self::$registered;
    }
}


