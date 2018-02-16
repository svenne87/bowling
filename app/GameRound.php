<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class GameRound extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'number', 'game_id', 'player_id', 'score', 'type'
    ];

    /**
     * A GameRound have single Game.
     *
     * @return \App\Game
     */
    public function game() {
        return $this->belongsTo(Game::class);
    }

     /**
     * Assign the given Game to the Match.
     *
     * @param Game $game
     *
     * @return mixed
     */
    public function setGame(Game $game) {
        $this->game_id = $game->id;
        $this->save();
    }

    /**
     * A GameRound have single Player.
     *
     * @return \App\Player
     */
    public function player() {
        return $this->belongsTo(Player::class);
    }

     /**
     * Assign the given Player to the GameRound.
     *
     * @param Player $player
     *
     * @return mixed
     */
    public function setPlayer(Player $player) {
        $this->player_id = $player->id;
        $this->save();
    }
}
