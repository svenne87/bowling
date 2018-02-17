@extends('welcome')
@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-12">
            <h1 class="text-center">{{ $match->name }}<h1>
        </div>
    </div>
    <div class="match-info-container">
        @if ($match->players->count() > 1)
            <div class="row">
                @foreach ($match->players as $player)
                    <div class="col-md-12 col-lg-10 offset-lg-1">
                        <div class="player-info-container">
                            <h3 class="text-center">{{ $player->name }}</h3>
                            <div class="table-responsive">
                                <table class="table table-bordered">
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
                                                        <td>{{ $gameRound->score }}</td>
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
        @endif
    </div>
</div>
@endsection