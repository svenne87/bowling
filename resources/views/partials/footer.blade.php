<footer class="app-footer navbar-fixed-bottom text-center text-lg-left pt-4 bg-secondary">
    <div class="container">
        <div class="row">
            <div class="offset-md-4 col-md-4">
                <a href="/" class="text-center" title="{{ Config::get('app.name') }}">
                    <span class="logo-icon"><i class="fa fa-bowling-ball"></i></span>
                    <p class="text-white text-lg-center"><b>{{ Config::get('app.name') }}</b></p>
                </a>
            </div>
       </div>
      <div class="row">
            <div class="col-md-12">
                <p class="text-center text-white mt-2">© {{ Date('Y') }} {{ Config::get('app.name') }}</p>
            </div>
        </div>
    </div>
</footer>