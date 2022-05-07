<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCommunityDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('community_details', function (Blueprint $table) {
            $table->id();
            $table->string("name")->nullable();
            $table->string("image_path")->nullable();
            $table->text("description")->nullable();
            $table->string("facebook_account")->nullable();
            $table->string("instagram_account")->nullable();
            $table->string("twitter_account")->nullable();
            $table->string("website")->nullable();
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
        Schema::dropIfExists('community_details');
    }
}
