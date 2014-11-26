<?php namespace rexlu\Laravelodbc;

use Illuminate\Support\ServiceProvider;


class ODBCServiceProvider extends ServiceProvider {

	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = false;

	/**
	 * Bootstrap the application events.
	 *
	 * @return void
	 */
	public function boot()
	{
        $factory = $this->app['db'];
        $factory->extend('odbc',function($config) {
			if ( ! isset($config['prefix']))
			{
				$config['prefix'] = '';
			}

            $connector =  new ODBCConnector();
			$pdo = $connector->connect($config);
            return new ODBCConnection($pdo, $config['database'], $config['prefix']);

		});
	}

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		//
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return array();
	}

}