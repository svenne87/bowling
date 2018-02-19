@extends('welcome')
@section('content')
<div class="container">
    <div class="match-info-container">
        @if ($match->players->count() > 1 && !$waitingForJoin)
            <div class="row">
                <div class="col-md-8 offset-md-2">
                    <div class="message-container" style="display:none;">
                        @if (!Session::get('success'))
                            <div class="alert alert-success alert-block">
	                            <button type="button" class="close" data-dismiss="alert">×</button>	
                                <strong><span id="message">&emsp;</span></strong>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12">
                    <h2 class="text-center">{{ $match->name }}</h2>
                </div>
            </div>
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
                        <div class="match-result-details-container">                       
                            <p class="text-muted text-center"><strong>{{ Lang::get('match.next_player') }}:</strong> <span id="next-player">{{ $currentPlayer->name }}</span></p>
                        </div>
                        <div class="action-container">
                            <input type="hidden" name="match-identifier" id="match-identifier" value="{{ $match->unique_identifier }}">
                            <input type="hidden" name="player" id="player" value="{{ ($playerIdentifier ? $playerIdentifier : '')}}">

                            @if (!$activePlayer || $activePlayer->id == $currentPlayer->id)
                                {!! Form::model($match, [
                                    'method' => 'PATCH',
                                    'url' => ['/match', $match->id],
                                    'class' => 'form-horizontal'
                                ]) !!}

                                    @include ('templates.match.action-form')
                        
                                {!! Form::close() !!}
                            @endif
                        </div>
                    @else
                        <div class="match-result-details-container">
                            <p id="match-ended" class="text-muted text-center">{{ Lang::get('match.match_ended') }}</p>
                            <h3 class="text-center">{{ Lang::get('match.winner') }}: {{ $match->winner->name }}</h3>
                        </div>
                    @endif
                    <div id="match-ended" style="display:none;" class="match-result-details-container">
                        <p class="text-muted text-center">{{ Lang::get('match.match_ended') }}</p>
                    </div>
                </div>
            </div>
        @else
            @if ($activePlayer->id != $currentPlayer->id)
                {!! Form::model($match, [
                    'method' => 'PATCH',
                    'url' => ['/match-join', $match->id],
                    'class' => 'form-horizontal'
                ]) !!}

                @include ('templates.match.join-form')
                        
            {!! Form::close() !!}
            @else
                <input type="hidden" name="match-identifier" id="match-identifier" value="{{ $match->unique_identifier }}">
                <input type="hidden" name="player" id="player" value="{{ ($playerIdentifier ? $playerIdentifier : '')}}">
                <h2 class="text-center">{{ Lang::get('match.waiting_for_player') }}</h2>
                <p class="text-center">{{ Lang::get('match.join_here') }}: <a href="{{ $match->players->where('name', '')->first()->getJoinUrl($match) }}">{{ $match->players->where('name', '')->first()->getJoinUrl($match) }}</a></p>
            @endif
        @endif
    </div>
</div>
@endsection