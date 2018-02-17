<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateGameRoundsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('game_rounds')) {
            Schema::create('game_rounds', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name');
                $table->integer('number');
                $table->integer('game_id')->unsigned();
                $table->foreign('game_id')->references('id')->on('games');
                $table->integer('player_id')->unsigned();
                $table->foreign('player_id')->references('id')->on('players');
                $table->integer('score')->default(0);
                $table->integer('type')->default(0)->comment('0 = regular, 1 = spare, 2 = strike, 3 = violation. 4 = no_action');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('game_rounds');
    }
}
