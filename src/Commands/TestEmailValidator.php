<?php namespace Skmetaly\EmailVerifier\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Skmetaly\EmailVerifier\SMTP\Verifier;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use EmailVerifier;

class TestEmailValidator extends Command
{

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'tem:email';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description.';

    /**
     * Create a new command instance.
     *
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function fire()
    {
        $isValid = EmailVerifier::verify($this->argument('email'));

        if ($isValid) {

            $this->info('The email address is valid');
        }else{

            $this->error('The email address is not valid or we couldn\'t contact the server');
        }
        //EmailVerifier::verify(['test@contact.seblaze.com','test@paulbele.com']);
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['email', InputArgument::REQUIRED, 'The email address that will be tested'],
        ];
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            //['example', null, InputOption::VALUE_OPTIONAL, 'An example option.', null],
        ];
    }

}
