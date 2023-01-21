<?php

namespace Salodev\Modularize;

use Illuminate\Support\ServiceProvider;

use function app_path;

class ModulesServiceProvider extends ServiceProvider
{
    private $appModuleClass = 'App\Modules\AppModule';
    private $appModuleTargetFile = 'Modules/AppModule.php';
    
    private function getAppModule()
    {
        $appModuleClass = $this->appModuleClass;
        return $appModuleClass::getInstance();
    }
    
    public function register()
    {
        
        if (!$this->installed()) {
            return;
        }
        
        $appModule = new $this->appModuleClass($this->app);
        $appModule->loadConfigs();
        $this->app->register($appModule);
    }
    
    public function boot()
    {
        if (!$this->installed()) {
            $this->installFiles();
            return;
        }
        
        $module = $this->getAppModule();
        
        $module->router()->prefix('api')->middleware('api')->group(function () use ($module) {
            $module->bootAllRoutes('api');
        });
        
        $module->router()->middleware('web')->group(function () use ($module) {
            $module->bootAllRoutes('web');
        });
    }
    
    private function installFiles()
    {
        $appModuleTargetFile = app_path($this->appModuleTargetFile);
        
        if (!file_exists($appModuleTargetFile)) {
            $this->publishes([
                dirname(__DIR__) . '/laravel-assets/AppModule.php' => $appModuleTargetFile,
            ], 'laravel-assets');
        }
    }
    
    private function installed()
    {
        return file_exists(app_path($this->appModuleTargetFile));
    }
}
