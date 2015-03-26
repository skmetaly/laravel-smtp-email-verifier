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
        $this->publishes([
            __DIR__.'/Config/email-verifier.php' => config_path('email-verifier.php'),
        ]);
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
