<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Player extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name'
    ];

    /** 
    * A Player may participate in several Matches
    *
    * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
    */
    public function matches()
    {
        return $this->belongsToMany(Match::class);
    }

    /**
     * Assign the given Match to a Player.
     *
     * @param Match $match
     *
     * @return mixed
     */
    public function assignMatch(Match $match)
    {
        return $this->matches()->save($match);
    }


}
