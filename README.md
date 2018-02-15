# Bowling

Bowling game in Laravel

## Getting Started

These instructions will get you a copy of the project up and running on your local machine for development and testing purposes. See deployment for notes on how to deploy the project on a live system.

### Prerequisites

[Composer](https://getcomposer.org/)
[Laravel](https://laravel.com/docs/5.6#server-requirements)

### Installing

Create database
Set environment values in .env file
Make sure /storage and /bootstrap/cache are writable by your web server
composer update
rename .env.example -> .env (And set values) 
php artisan key:generate
php artisan migrate --seed
php artisan storage:link


## Running the tests

Explain how to run the automated tests for this system


## Built With

* [Laravel](https://laravel.com/) - The web framework used
