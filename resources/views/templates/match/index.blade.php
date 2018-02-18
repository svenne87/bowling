@extends('welcome')
@section('content')
<div class="container h-100">
    <div class="row h-100 justify-content-center align-items-center">
        <div class="col-lg-8 lg-offset-2">
            {!! Form::open(['url' => '/match', 'class' => 'form-horizontal']) !!}

                @include ('templates.match.form')

            {!! Form::close() !!}
        </div>
    </div>
</div>
@endsection