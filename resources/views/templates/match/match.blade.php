@extends('welcome')
@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-12">
            <h2 class="text-center">{{ $match->name }}</h2>
        </div>
    </div>
    <div class="match-info-container">
        @if ($match->players->count() > 1)
            <div class="row">
                @foreach ($match->players as $player)
                    <div class="col-md-12 col-lg-6">
                        <div class="player-info-container">
                            <h3 class="text-center">{{ $player->name }}</h3>
                            <div class="table-responsive">
                                <table class="table table-bordered score-table">
                                    <tbody>
                                        <tr class="text-center"> 
                                            @foreach ($match->games as $game) 
                                                <td colspan="{{ $game->gameRounds()->count() / $match->players->count() }}"><strong>{{ $game->name }}</strong></td> 
                                             @endforeach
                                        </tr>
                                        <tr class="text-center"> 
                                        @foreach ($match->games as $game) 
                                            @foreach ($game->gameRounds as $gameRound)
                                                @if ($gameRound->player_id == $player->id)
                                                    @if ($gameRound->created_at == $gameRound->updated_at)
                                                        <td>&emsp;</td>
                                                    @else
                                                        @if ($gameRound->type == 0)
                                                            @if ($gameRound->score == 0)
                                                                <td>-</td>
                                                            @else
                                                            <td>{{ $gameRound->score }}</td>
                                                            @endif
                                                        @elseif ($gameRound->type == 1)
                                                            <td>/</td>
                                                        @elseif ($gameRound->type == 2)
                                                            <td>X</td>
                                                        @elseif ($gameRound->type == 3)
                                                            <td>F</td>
                                                        @elseif ($gameRound->type == 4)
                                                            <td>&emsp;</td>
                                                        @endif
                                                    @endif
                                                @endif
                                            @endforeach
                                        @endforeach
                                        </tr>
                                        <tr class="text-center"> 
                                            @foreach ($match->games as $game) 
                                                @if (isset($results[$player->id][$game->number]))
                                                    <td colspan="{{ $game->gameRounds()->count() / $match->players->count() }}"><strong>{!! $results[$player->id][$game->number] !!}</strong></td> 
                                                @endif
                                            @endforeach
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="row">
                <div class="col-md-12 col-lg-10 offset-lg-1">
                    @if (!$matchHasEnded)
                        <div class="action-container">
                            {!! Form::model($match, [
                                'method' => 'PATCH',
                                'url' => ['/match', $match->id],
                                'class' => 'form-horizontal'
                            ]) !!}

                                @include ('templates.match.action-form')
                        
                            {!! Form::close() !!}
                        </div>
                    @else
                        <div class="match-result-details-container">
                            <p class="text-muted text-center">{{ Lang::get('match.match_ended') }}</p>
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
@endsection