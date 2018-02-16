<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Match extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'winner_player_id', 'unique_identifier', 'display_score', 'start_datetime', 'end_datetime'
    ];

    /**
     * A Match may have multiple Games.
     *
     * @return \Illuminate\Database\Eloquent\Relations\hasMany
     */
    public function games()
    {
        return $this->hasMany(Game::class);
    }

    /** 
    * A Match have several Players
    *
    * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
    */
    public function players()
    {
        return $this->belongsToMany(Player::class);
    }

    /**
     * Assign the given Player to a Match.
     *
     * @param Player $player
     *
     * @return mixed
     */
    public function assignPlayer(Player $player)
    {
        return $this->players()->save($player);
    }

    /**
     * A Match have single Winner (Player).
     *
     * @return \App\Player
     */
    public function winner() {
        return $this->belongsTo(Player::class);
    }

    /**
     * Set the Winner (Player) for this Match.
     *
     * @param Player $player
     *
     * @return mixed
     */
    public function setWinner(Player $player) {
        $this->winner_player_id = $player->id;
        $this->save();
    }
}
