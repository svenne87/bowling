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
         
        <title>{{ Config::get('app.name') }}</title>

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
                        @include('partials.flash-message')
                        @yield('content')
                    </div>
                </div>
                <!-- Content -->
            </main>
        </div>

        <!-- Footer -->
            @include('partials.footer')  
        <!-- End of Footer -->

        <!-- Scripts -->
            @include('partials.scripts')
            @yield('scripts')
        <!-- End of Scripts -->
    </body>
</html>