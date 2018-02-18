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
        'name', 'winner_player_id', 'starting_player_id', 'unique_identifier', 'display_score', 'winner_score', 'start_datetime', 'end_datetime'
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
        return $this->belongsTo(Player::class, 'winner_player_id');
    }

    /**
     * Set the Winner (Player) for this Match.
     *
     * @param Player $player
     * @param mixed $score
     *
     * @return mixed
     */
    public function setWinner(Player $player, $score) {
        $this->winner_player_id = $player->id;
        $this->winner_score = $score;
        $this->save();
    }

    /**
     * A Match have single Starting Player.
     *
     * @return \App\Player
     */
    public function startingPlayer() {
        return $this->belongsTo(Player::class, 'starting_player_id');
    }

    /**
     * Set the starting Player for this Match.
     *
     * @param Player $player
     *
     * @return mixed
     */
    public function setStartingPlayer(Player $player) {
        $this->starting_player_id = $player->id;
        $this->save();
    }

    /**
     * Used to display Ranking
     */
    public function getRankAttribute()
    {
        return $this->newQuery()->where('winner_score', '>=', $this->winner_score)->count();
    }
}
