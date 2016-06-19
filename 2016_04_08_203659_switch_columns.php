<?php
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class SwitchColumns extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        App\Helpers\ColumnChanger::swap("users","email","password");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        App\Helpers\ColumnChanger::swap("users","password","email");
    }
}
