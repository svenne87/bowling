<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Game extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'number', 'match_id', 'start_datetime', 'end_datetime'
    ];

    /**
     * A Game have single Match.
     *
     * @return \App\Match
     */
    public function match() {
        return $this->belongsTo(Match::class);
    }

     /**
     * Assign the given Match to the Game.
     *
     * @param Match $match
     *
     * @return mixed
     */
    public function setMatch(Match $match) {
        $this->match_id = $match->id;
        $this->save();
    }

    /**
     * A Game may have several GameRounds.
     *
     * @return \Illuminate\Database\Eloquent\Relations\hasMany
     */
    public function gameRounds()
    {
        return $this->hasMany(GameRound::class);
    }
}
