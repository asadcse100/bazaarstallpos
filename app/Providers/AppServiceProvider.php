<?php

namespace App\Providers;

use App\System;
use App\Utils\ModuleUtil;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Filesystem;
use Spatie\Dropbox\Client as DropboxClient;
use Spatie\FlysystemDropbox\DropboxAdapter;

use Laravel\Passport\Console\ClientCommand;
use Laravel\Passport\Console\InstallCommand;
use Laravel\Passport\Console\KeysCommand;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        ini_set('memory_limit', '-1');
        set_time_limit(0);

        if (config('app.debug')) {
            error_reporting(E_ALL & ~E_USER_DEPRECATED);
        } else {
            error_reporting(0);
        }

        //force https
        $url = parse_url(config('app.url'));

        if ($url['scheme'] == 'https') {
            \URL::forceScheme('https');
        }

        if (request()->has('lang')) {
            \App::setLocale(request()->get('lang'));
        }

        //In Laravel 5.6, Blade will double encode special characters by default. If you would like to maintain the previous behavior of preventing double encoding, you may add Blade::withoutDoubleEncoding() to your AppServiceProvider boot method.
        // Blade::withoutDoubleEncoding();

        //Laravel 5.6 uses Bootstrap 4 by default. Shift did not update your front-end resources or dependencies as this could impact your UI. If you are using Bootstrap and wish to continue using Bootstrap 3, you should add Paginator::useBootstrapThree() to your AppServiceProvider boot method.
        Paginator::useBootstrapThree();

        \Illuminate\Pagination\Paginator::useBootstrap();

        // Dropbox service provider
        Storage::extend('dropbox', function ($app, $config) {
            $adapter = new DropboxAdapter(new DropboxClient(
                $config['authorization_token']
            ));
 
            return new FilesystemAdapter(
                new Filesystem($adapter, $config),
                $adapter,
                $config
            );
        });


        $asset_v = config('constants.asset_version', 1);
        View::share('asset_v', $asset_v);

        // Share the list of modules enabled in sidebar
        View::composer(
            ['*'],
            function ($view) {
                $enabled_modules = ! empty(session('business.enabled_modules')) ? session('business.enabled_modules') : [];

                $__is_pusher_enabled = isPusherEnabled();

                if (! Auth::check()) {
                    $__is_pusher_enabled = false;
                }

                $view->with('enabled_modules', $enabled_modules);
                $view->with('__is_pusher_enabled', $__is_pusher_enabled);
            }
        );

        View::composer(
            ['layouts.*'],
            function ($view) {
                if (isAppInstalled()) {
                    $keys = ['additional_js', 'additional_css'];
                    $__system_settings = System::getProperties($keys, true);

                    //Get js,css from modules
                    $moduleUtil = new ModuleUtil;
                    $module_additional_script = $moduleUtil->getModuleData('get_additional_script');
                    $additional_views = [];
                    $additional_html = '';
                    foreach ($module_additional_script as $key => $value) {
                        if (! empty($value['additional_js'])) {
                            if (isset($__system_settings['additional_js'])) {
                                $__system_settings['additional_js'] .= $value['additional_js'];
                            } else {
                                $__system_settings['additional_js'] = $value['additional_js'];
                            }
                        }
                        if (! empty($value['additional_css'])) {
                            if (isset($__system_settings['additional_css'])) {
                                $__system_settings['additional_css'] .= $value['additional_css'];
                            } else {
                                $__system_settings['additional_css'] = $value['additional_css'];
                            }
                        }
                        if (! empty($value['additional_html'])) {
                            $additional_html .= $value['additional_html'];
                        }
                        if (! empty($value['additional_views'])) {
                            $additional_views = array_merge($additional_views, $value['additional_views']);
                        }
                    }

                    $view->with('__additional_views', $additional_views);
                    $view->with('__additional_html', $additional_html);
                    $view->with('__system_settings', $__system_settings);
                }
            }
        );

        //This will fix "Specified key was too long; max key length is 767 bytes issue during migration"
        Schema::defaultStringLength(191);

        //Blade directive to format number into required format.
        // Blade::directive('num_format', function ($expression) {
        //     return "number_format($expression, session('business.currency_precision', 2), session('currency')['decimal_separator'], session('currency')['thousand_separator'])";
        // });

        //Blade directive to format quantity values into required format.
        // Blade::directive('format_quantity', function ($expression) {
        //     return "number_format($expression, session('business.quantity_precision', 2), session('currency')['decimal_separator'], session('currency')['thousand_separator'])";
        // });

        //Blade directive to return appropiate class according to transaction status
       
        $this->registerCommands();
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Register commands.
     *
     * @return void
     */
    protected function registerCommands()
    {
        $this->commands([
            // InstallCommand::class,
            ClientCommand::class,
            KeysCommand::class,
        ]);
    }
}
