# laravel-smtp-email-verifier
Laravel SMTP Email verifier. Simple email verifier for Laravel that tries to check with SMTP server if the given email addresses exists or not

# Installation

Require the package in composer.json : 
```bash
"skmetaly/laravel-smtp-email-verifier": "dev-master"
```
In ```config/app.php``` add ```providers```
```php
'Skmetaly\EmailVerifier\EmailVerifierServiceProvider'
```
In ```aliases```
```php
'EmailVerifier'=>'Skmetaly\EmailVerifier\Facades\EmailVerifier'
```
Publish the config
```php
php artisan vendor:publish --force
```
If you want the test command, register it in ```app/Console/Kernel```
```php
'Skmetaly\EmailVerifier\Commands\TestEmailValidator'
```

# Usage
####Test command
```bash
php artisan tem:email <email-address>
```
####Email Validator
Currently you can ue ```EmaiLValidator```  with a string as an email address or an array containing email addresses
```php
EmailValidator::verify('foo@bar');
```
Will return ```true``` if the validator was able to connect and to validate the email address 
```php
EmailValidator::verify(['foo@bar','baz@bar','baz@foo');
```
Will return an array with all the validated email addresses

# Current status
Alpha

# Licence
MIT