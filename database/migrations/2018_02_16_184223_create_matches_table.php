<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMatchesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('matches')) {
            Schema::create('matches', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name');
                $table->string('display_score')->nullable();
                $table->integer('winner_player_id')->unsigned()->default(0);
                $table->foreign('winner_player_id')->references('id')->on('players');
                $table->dateTime('start_datetime')->nullable();
                $table->dateTime('end_datetime')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('match_player')) {
            Schema::create('match_player', function (Blueprint $table) {
                $table->integer('match_id')->unsigned();
                $table->integer('player_id')->unsigned();
            
                $table->foreign('match_id')
                    ->references('id')
                    ->on('matches')
                    ->onDelete('cascade');
            
                $table->foreign('player_id')
                    ->references('id')
                    ->on('players')
                    ->onDelete('cascade');
            
                $table->primary(['match_id', 'player_id']);
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
        Schema::dropIfExists('match_player');
        Schema::dropIfExists('matches');
        Schema::dropIfExists('players');
    }
}
