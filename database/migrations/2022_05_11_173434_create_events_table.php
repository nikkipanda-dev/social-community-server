<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEventsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onUpdate('cascade')->onDelete('cascade');
            $table->string('name');
            $table->dateTime('start_date');
            $table->dateTime('end_date');
            $table->dateTime('rsvp_date');
            $table->text('details');
            $table->boolean('is_hobby');
            $table->boolean('is_wellbeing');
            $table->boolean('is_career');
            $table->boolean('is_coaching');
            $table->boolean('is_science_and_tech');
            $table->boolean('is_social_cause');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('events');
    }
}
