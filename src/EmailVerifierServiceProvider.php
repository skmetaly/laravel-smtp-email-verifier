<?php namespace Skmetaly\EmailVerifier;

use Illuminate\Support\ServiceProvider;
use Skmetaly\EmailVerifier\SMTP\Verifier;

/**
 * Class EmailVerifierServiceProvider
 * @package Skmetaly\EmailVerifier
 */
class EmailVerifierServiceProvider extends ServiceProvider {

	/**
	 * Bootstrap the application services.
	 *
	 * @return void
	 */
	public function boot()
	{
        $configPath =  __DIR__.'/../config/email-verifier.php';

        $this->publishes([
            $configPath => config_path('email-verifier.php'),
        ]);

        $this->mergeConfigFrom($configPath, 'email-verifier');
    }

	/**
	 * Register the application services.
	 *
	 * @return void
	 */
	public function register()
	{
		$this->registerVerifiers();

        $this->publishConfig();
	}

    /**
     *
     */
    private function registerVerifiers()
    {
        $this->app->bind('Skmetaly\EmailVerifier\Verifier','Skmetaly\EmailVerifier\SMTP\Verifier');
    }

    /**
     *
     */
    private function publishConfig()
    {

    }


}
