@extends('welcome')
@section('content')
<div class="container h-100">
    <div class="row h-100 justify-content-center align-items-center">
        <div class="col-md-8 md-offset-2">
            @if($matches->count())
                <h2 class="text-center">{{ Lang::get('basic.highscore') }}</h2>   
                <div class="table-container">
                    <div class="table-responsive">
                        <table class="table">
                            <thead class="thead-dark">
                                <tr>
                                    <th scope="col">{{ Lang::get('basic.ranking') }}</th>
                                    <th scope="col">{{ Lang::get('basic.score') }}</th>
                                    <th scope="col">{{ Lang::get('basic.match') }}</th>
                                    <th scope="col">{{ Lang::get('basic.winner') }}</th>
                                    <th scope="col">{{ Lang::get('basic.started') }}</th>
                                    <th scope="col">{{ Lang::get('basic.ended') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($matches as $match)
                                    <tr>
                                        <th scope="row">{{ $match->rank }}</th>
                                        <td>{{ $match->display_score }}</td>
                                        <td><a href="{{ url('/match', $match->id) }}">@foreach ($match->players as $player) {{ $player->name }} {{ (!$loop->last ? '-' : '') }} @endforeach</a></td>
                                        <td>{{ ($match->winner ? $match->winner->name : Lang::get('basic.tied') ) }}</td>
                                        <td>{{ \Carbon\Carbon::parse($match->start_datetime)->format('Y-m-d H:i') }}</td>
                                        <td>{{ \Carbon\Carbon::parse($match->end_datetime)->format('Y-m-d H:i') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="pagination"> {!! $matches->render() !!} </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection