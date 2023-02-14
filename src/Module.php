<?php

namespace Salodev\Modularize;

use Exception;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Salodev\Modularize\Console\Kernel;
use ReflectionClass;

use function app;
use function app_path;
use function config;

class Module extends ServiceProvider
{
    private static array $instances = [];
    
    private string $configKey = '';
    
    protected array $children = [];
    
    public string $apiRoutesPrefix = '';
    public string $webRoutesPrefix = '';
    public string $routePrefix     = '';
    
    public function __construct($app)
    {
        if (static::getInstance() === null) {
            static::setInstance($this);
            
            // throw new \Exception('Module does not support multiple instances');
        }
        parent::__construct($app);
    }
    
    public function boot()
    {
        $this->bootCommands();

        $this->bootViews();

        if (app()->runningInConsole()) {
            $migrationsPath = $this->getMigrationsPath();

            if (is_dir($migrationsPath)) {
                $this->loadMigrationsFrom([$this->getMigrationsPath()]);
            }
        }
    }
    
    public function loadConfigs(): void
    {
        $configFile = $this->getRootPath() . '/config.php';
        
        $this->configKey = $this->getKey();
        
        if (is_file($configFile) && file_exists($configFile)) {
            $this->mergeConfigFrom($configFile, $this->configKey);
        }
    }
    
    public function renderList(): array
    {
        $list = [];
        $key = $this->getKey();
        $list[] = [
            'key'      => $key,
            'path'     => $this->getRootPath(),
            'class'    => get_class($this),
            'instance' => $this,
        ];
        foreach ($this->children as $child) {
            $childrenList = $child->renderList();
            $list = array_merge($list, $childrenList);
        }
        
        return $list;
    }
    
    public static function getInstance()
    {
        if (!array_key_exists(static::class, static::$instances)) {
            return null;
        }
        
        return static::$instances[static::class];
    }
    
    public static function setInstance(Module $module)
    {
        static::$instances[get_class($module)] = $module;
    }
    
    
    public static function config($name = null, $default = null)
    {
        $module = static::getInstance();
        if ($module === null) {
            throw new Exception('Module not initialized');
        }
        
        if (is_null($module->configKey)) {
            return null;
        }
        
        if (is_null($name)) {
            return config($module->configKey, $default);
        }
        
        return config("{$module->configKey}.{$name}", $default);
    }
    
    /**
     * Register a submodule and appentds to children list.
     */
    public function provide(string $moduleClass): self
    {
        $module = new $moduleClass($this->app);
        $module->loadConfigs();
        $this->children[] = $this->app->register($moduleClass);
        return $module;
    }
    
    public static function getRootPath(): string
    {
        $ruta = str_replace('App\\', '', static::class);
        $ruta = str_replace('\\', DIRECTORY_SEPARATOR, $ruta);
        $ruta = app_path($ruta);
        $ruta = dirname($ruta);
        return $ruta;
    }
    
    public static function getRootNamespace(): string
    {
        $className = static::class;
        $step1     = str_replace('\\', DIRECTORY_SEPARATOR, $className);
        $step2     = dirname($step1);
        $step3     = str_replace(DIRECTORY_SEPARATOR, '\\', $step2);
        
        return $step3;
    }
    
    protected function getMigrationsPath()
    {
        return $this->getRootPath() . DIRECTORY_SEPARATOR . 'Migrations';
    }
    
    /**
     * Module api routes
     */
    public function bootApiRoutes()
    {
        //
    }
    
    /**
     * Module web routes
     */
    public function bootWebRoutes()
    {
        //
    }
    
    /**
     * Register module routes recursively
     */
    final public function bootAllRoutes($type = 'api')
    {
        foreach ($this->children as $module) {
            $customPrefix = $module->getRoutePrefix($type);
            $modulePrefix = $module->getRoutePrefixForCurrent();
            $prefix       = $customPrefix . $modulePrefix;
                    
            $this->router()
                ->prefix($prefix)
                ->group(function () use ($module, $type) {
                    if ($type === 'api') {
                        $module->bootApiRoutes();
                    }
                    if ($type === 'web') {
                        $module->bootWebRoutes();
                    }
                    $module->bootAllRoutes($type);
                });
        }
    }
    
    public function getRoutePrefix(string $type): string
    {
        $prefix = '';
        if ($type === 'api') {
            $prefix = $this->apiRoutesPrefix;
        }
        if ($type === 'web') {
            $prefix = $this->webRoutesPrefix;
        }
        $result = $prefix ? $prefix . '/' : '';
        
        return $result;
    }

    public function getRoutePrefixForCurrent(): string
    {
        if ($this->routePrefix) {
            return $this->routePrefix;
        }

        $name = $this->getName();
        return CaseHelper::toKebab($name);
    }
    
    public function getName(): string
    {
        $name = (new \ReflectionClass($this))->getShortName();
        if (substr($name, -6) === 'Module') {
            return substr($name, 0, -6);
        }
        
        return $name;
    }
    
    public static function getKey(): string
    {
        $parts = explode('\\', (new \ReflectionClass(static::class))->getNamespaceName());
        $parts = array_map(function (string $part) {
            return CaseHelper::toKebab($part);
        }, $parts);
        
        $parts = array_slice($parts, 2);
        array_unshift($parts, 'app');
        
        $key = implode('.', $parts);
        return $key;
    }
    
    protected function bootCommands()
    {
        $reflectedClass = new ReflectionClass($this);

        $commandsPath = dirname($reflectedClass->getFileName()) . '/Commands';

        if (is_dir($commandsPath)) {
            app()->make(Kernel::class)->loadCommands($commandsPath);
        }
        
        $this->onSchedule(function () {
            $this->bootSchedule();
        });
    }
    
    public function bootSchedule()
    {
        //
    }

    public function bootViews()
    {
        $viewsPath = __DIR__ . '/views';
        if (is_dir($viewsPath)) {
            $this->loadViewsFrom($viewsPath, $this->getKey());
        }
    }
    
    public static function view(string $name)
    {
        $key = static::getKey();
        return view("{$key}::{$name}");
    }

    public function router(): Router
    {
        return $this->app->make(Router::class);
    }
    
    public function console(): Kernel
    {
        return $this->app->make(Kernel::class);
    }
    
    public function scheduler(): Schedule
    {
        return $this->app->make(Schedule::class);
    }
    
    public function onRouter(callable $function)
    {
        $this->callAfterResolving(Router::class, $function);
    }
    
    public function onSchedule(callable $function)
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) use ($function) {
            $function($schedule);
        });
    }
}
