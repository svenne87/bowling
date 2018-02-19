# Bowling

Bowling game in Laravel.
 
* [Details and thoughts](https://github.com/svenne87/bowling/wiki/Design-and-thoughts/)

### Prerequisites

* [Composer](https://getcomposer.org/) - PHP package manager
* [Pusher](https://pusher.com/) - Used to publish messages
* [Laravel](https://laravel.com/docs/5.6#server-requirements) - The web framework used

### Installing

* Clone project
* Create a databse
* Rename .env.example -> .env and change values to match your setup
* Make sure /storage and /bootstrap/cache are writable by your web server
* composer update
* php artisan key:generate
* php artisan migrate --seed
* All Done :)
