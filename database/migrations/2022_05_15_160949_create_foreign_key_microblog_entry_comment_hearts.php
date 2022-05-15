<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateForeignKeyMicroblogEntryCommentHearts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('microblog_entry_comment_hearts', function (Blueprint $table) {
            $table->foreign('comment_id')->references('id')->on('microblog_entry_comments');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('microblog_entry_comment_hearts', function (Blueprint $table) {
            $table->dropForeign('comment_id');
        });
    }
}
