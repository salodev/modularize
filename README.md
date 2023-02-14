# Modularize
This pakages give modular feature to your Laravel project.

So you now can improve better code organization, growing your productivity and reducing code analysis time.

You have a set of `artisan` commands to ease most common tasks such creating modules and its components.

## File skeleton

From now you going to work with an structure as following:

```
    app\
        Modules\
            ModuleName\
                Commands\
                Mails\
                Migrations\
                Models\
                Requests\
                Resources\
                Views\
                ModuleNameController.php
                ModuleNameModule.php
            AppModule.php
```
## Installation

```bash
composer require salodev/modularize
composer require salodev/modularize-generator --dev
```

After installation, the AppModule.php file will be placed into `app\Modules` folder, and it is the main modules registration point

```
app\
    Modules\
        AppModules.php
```

Its content will see as following:

```php
<?php

namespace App\Modules;

use Salodev\Modularize\Module;

class AppModule extends Module {

}
```


## Code generator

In order to ease work, the package provides a set of commands to achieve most common tasks such as normal Laravel develompent. These commands will be under `modularize:` namespace

To see them, run the list artisan command:
```bash
php artisan list modularize
```

the output will be similar to the following:

```text
  modularize:add:route         Add a module route
  modularize:add:schedule      Add a sheduled command
  modularize:list:migrations   List all migrations
  modularize:list:modules      List all modules
  modularize:make:command      Make a module command
  modularize:make:config       Make a module config file
  modularize:make:controller   Make a module controller
  modularize:make:crud-module  Make a CRUD module
  modularize:make:mail         Make a module mail
  modularize:make:migration    Make a module migration
  modularize:make:model        Make a module model
  modularize:make:module       Make a module
  modularize:make:request      Make a module controller
  modularize:make:resource     Make a module resource
```

These commands will generate common files into specified module.

To know more about any command options, just ask for it help, as following:

```bash
php artisan modularize:make:module --help
```

## Make a module ##

Just type:
```bash
php artisan modularize:make:module
```

Command is interactive.
First, asks for parent module, from a list of previously generated modules. The first one is the `app` created by installation.
Once parent is choosen, asks for a name and creates module file with the new class definition.

All modules depends of the root module or any submodule. The composition level is infinite.

Each module can define following components:

 - Http Routes, Controllers and Requests
 - Resources
 - Models
 - Console commands
 - Scheduled tasks
 - Database Migrations and Seeders
 - Maileables and Templates
 - Configuration file
 - Its own modules

Some of these components must be registered or iniialized within module. Another will be registered automatically.

Register submodules with the **provide()** method at the **register()** method

```php
    public function register() {
        $this->provide(ChildModule::class);
    }
```

When you create submodules by the artisan command, it checks the parent module, and will generate **register()** method if not created yet, or jus add the **provide()** call

## Make module routes ##

Each module defines own routes.
You can add new routes manually or using the `artisan` command

### The artisan way ###

Make routes quickly with the following artisan command:
```
php artisan modularize:add:route
```
Program will ask for mofule, verb and name, and resource name
At its ending your code will be properly updated.

To avoid use interactive mode, checkout the parameters list:
```bash
php artisan modularize:add:route --help
```

### The manual way ###

Go to your desired module where want to add the route

add or edit the `bootApiRoutes` method for api routes, or the `bootWebRoutes` for web routes.

The following example shows how to add a new route for api:

```php
class UsersModule {
    public function bootApiRoutes() {
        $this->router()->get('', [UsersController::class, 'index']);

        // For api authorized requests
        $this->router()->middleware('auth:api')->group(function() {
            $this->router()->get('/me', [UsersController::class, 'me']);
        });
    }
}
``` 

### Change module route prefix ###
By default generated routes prepends the module name. But you can define the *routePrefix* module property for another you want:

```php
    $this->routePrefix = 'my-route-prefix'

    // another case
    $this->routePrefix = '{account}' // for GET user/{account}
```

## Migrations ##

Migrations module be stored in the Migrations folder within module folder

Following command creates module migration:

```bash
php artisan modularize:make:migration
``` 
Because no parameter provided will ask you interactively.

So you can list created migration and check status:

```bash
php artisan migrate:status
``` 

Also you can check migrations for modules, to know where was created:

```bash
php artisan modularize:list:migrations
``` 

and the output will be similar to following:

```
+------+-----------------------------------------+-----------------------------------+-------+
| Ran? | Migration                               | Directory                         | Batch |
+------+-----------------------------------------+-----------------------------------+-------+
| No   | 2023_01_21_122840_create_article_table  | /app/Modules/Articles/Migrations  |       |
| No   | 2023_01_21_165751_create_customer_table | /app/Modules/Customers/Migrations |       |
| No   | 2023_01_21_165843_create_sale_table     | /app/Modules/Sales/Migrations     |       |
+------+-----------------------------------------+-----------------------------------+-------+
```

Notice that you have the **Directory** column to see where each migration is placed

To run all migrations, just call as ever you did:

```bash
php artisan migrate
```

## Configurations ##

Modules defines own configurations, and they are stored in the `config.php` file at the module root folder:

Files:

```bash
app\
    Modules\
        Service\
            ServiceModule.php
            config.php        <-- here
        AppModule.php
```

There is an content example for the `app\Modules\Service\ServiceModule\config.php` file:

```php
return [
    'api-key' => env('APP_SERVICE_API_KEY')
];
```

To get its configurations, just call the `::config()` method of desired module, as following:

```php
$apiKey = \App\Modules\Service\ServiceModule::config('api-key');
```

Notice that also may be accessed by Laravel `config()` helper function:

```php
config('app.service.api-key');
```

Config file can be generated by the command:

```bash
php artisan modularize:make:config
``` 

All config keys will be rooted by `app` namepsace in order to avoid confilcts with package configurations.

All config keys will be nested by the parent module key.

The module key is the Module folder in kebab-case

## Usage recommendations ##

In order to keep the project modularized as well, think each module as an package or a service itself. So try to keep all features inside the module or submodules.

Imagine an scaling scenario where should split your application in microservices or dependency packages. A well modularized project should not be a mess because just will need move files, but not modify them.

For file environment variables, try to include the module chain path including the configuration name in the variable name, as following:

file: app/Articles/config.php with `expiration-time` config
variable name: APP_ARTICLES_EXPIRATION_TIME

## Production environment considerations ##

In order to avoid increase deployment package size for production environments, this library was splitted in two parts:

- The `salodev/modularize` dependency pagacke that provides modular support in Laravel
- The `salodev/modularize-generator` dependency package that provides console commands generation. Consider install it as a **dev** dependency.

So when you deploy to serverless such as AWS Lambda, make bundle with the `--no-dev` option in composer. So just `salodev/modularize` be bundled.

## Licensing

This packages was provided with the Unlicense