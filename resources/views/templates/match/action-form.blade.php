<div class="form-group row mb-0">
    <div class="col-md-12 text-center">
        @if ($activePlayer)
            {!! Form::hidden('player_identifier', $activePlayer->unique_identifier, ['id' => 'player-identifier']) !!}
        @endif
        {{ Form::button('<i class="fa fa-bowling-ball"></i>' .' '. Lang::get('match.action'), ['type' => 'submit', 'class' => 'btn btn-primary px-5 mt-2'] )  }}
    </div>
</div>