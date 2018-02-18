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
         
        <title>500 | {{ Config::get('app.name') }}</title>

        <!-- Styles -->
        @include('partials.styles')
        @yield('styles')
        <!-- End of Styles -->
    </head>
    <body class="app flex-row align-items-center">
        @include('partials.header')

        <div class="mt-5 wrapper">
            <!-- Main content -->
            <main class="main py-5">
                <!-- Content -->
                <div class="content">   
                    <div class="animated fadeIn">   
                        <div class="container h-100">
                            <div class="row h-100 justify-content-center align-items-center">
                                <div class="col-lg-8 lg-offset-2">
                                    <div class="clearfix">
                                        <h1 class="float-left display-3 mr-4">{{ Lang::get('errors.500') }}</h1>
                                        <h4 class="pt-3">{{ Lang::get('errors.500_title') }}</h4>
                                        <p>{{ Lang::get('errors.500_message') }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Content -->
            </main>
        </div>
        @include('partials.footer')  

        <!-- Scripts -->
            @include('partials.scripts')
            @yield('scripts')
        <!-- End of Scripts -->
    </body>
</html>
                    
