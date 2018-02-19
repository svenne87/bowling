<div class="form-group row mb-0">
    <div class="form-group col-md-8 offset-md-2{{ $errors->has('player') ? ' has-error' : ''}}">
        <div class="input-group mb-0">
            <span class="input-group-addon"><i class="fa fa-user"></i></span>
            {!! Form::text('player', null, ['class' => 'form-control', 'placeholder' => Lang::get('match.player'), 'required' => 'required']) !!}
        </div>
        {!! $errors->first('player', '<p class="help-block text-danger">:message</p>') !!}
    </div>
</div>
<div class="form-group row mb-0">
    <div class="col-md-12 text-center">
        {!! Form::hidden('player_identifier', $activePlayer->unique_identifier, ['id' => 'player-identifier']) !!}
        {{ Form::button('<i class="fa fa-bowling-ball"></i>' .' '. Lang::get('match.start'), ['type' => 'submit', 'class' => 'btn btn-primary px-5 mt-4'] )  }}
    </div>
</div>

