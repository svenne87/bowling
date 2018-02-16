<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateGamesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('games')) {
            Schema::create('games', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name');
                $table->integer('number');
                $table->integer('match_id')->unsigned();
                $table->foreign('match_id')->references('id')->on('matches');
                $table->dateTime('start_datetime')->nullable();
                $table->dateTime('end_datetime')->nullable();
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
        Schema::dropIfExists('games');
    }
}
