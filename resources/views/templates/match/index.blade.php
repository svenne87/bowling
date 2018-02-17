@extends('welcome')
@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-12">
            {!! Form::open(['url' => '/match', 'class' => 'form-horizontal']) !!}

                @include ('templates.match.form')

            {!! Form::close() !!}
        </div>
    </div>
</div>
@endsection