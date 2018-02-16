<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <meta name="description" content="Bowling game in Laravel">
        <meta name="author" content="Emil Svensson">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta data-timezone="{{ Config::get('app.timezone') }}">
        <meta data-environment="{{ App::environment() }}">
         
        <title>404 | {{ Config::get('app.name') }}</title>

        <!-- Styles -->
        @include('partials.styles')
        @yield('styles')
        <!-- End of Styles -->
    </head>
    <body class="app flex-row align-items-center">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="clearfix">
                        <h1 class="float-left display-3 mr-4">{{ Lang::get('error.404') }}</h1>
                        <h4 class="pt-3">{{ Lang::get('error.404_title') }}</h4>
                        <p>{{ Lang::get('error.404_message') }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Scripts -->
            @include('partials.scripts')
            @yield('scripts')
        <!-- End of Scripts -->
    </body>
</html>