<header class="app-header navbar">
    <nav class="navbar navbar-expand-md bg-secondary fixed-top navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="/" title="{{ Config::get('app.name') }}">
                <i class="fa fa-bowling-ball"></i>
                <b>{{ Config::get('app.name') }}</b>
            </a>
            <button class="navbar-toggler navbar-toggler-right" type="button" data-toggle="collapse" data-target="#navbar-collapse-content" aria-controls="navbar-collapse-content" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span> </button>
            <div class="collapse navbar-collapse text-center justify-content-end" id="navbar-collapse-content">
                <a class="btn btn-secondary mx-2 px-5" href="{{ url('/match') }}" title="{{ Lang::get('basic.play') }}">{{ Lang::get('basic.play') }}</a>
            </div>
        </div>
    </nav>
</header>