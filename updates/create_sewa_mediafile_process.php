<?php namespace Sewa\Mediafile\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class CreateSewaMediafileProcess extends Migration
{
    public function up()
    {
        Schema::create('sewa_mediafile_process', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('description')->nullable();
            $table->string('command')->nullable();
            
            $table->boolean('queue')->default(0);
            $table->string('input_file')->nullable();
            $table->string('output_file')->nullable();
            
            $table->dateTime('created_at')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('finished_at')->nullable();
            
            $table->text('stdout')->nullable();
            $table->text('stderr')->nullable();
            $table->string('stdout_file')->nullable();
            $table->string('stderr_file')->nullable();
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('sewa_mediafile_process');
    }
}
