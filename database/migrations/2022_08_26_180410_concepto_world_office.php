<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ConceptoWorldOffice extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('conceptos_wo', function (Blueprint $table) {
            $table->id();
            $table->string("nombre");
            $table->string("unidad_medida");
        });

        Schema::table('concepto', function (Blueprint $table) {
            $table->bigInteger('fk_concepto_wo')->unsigned()->nullable();
            $table->foreign('fk_concepto_wo')->references('id')->on('conceptos_wo')->onDelete('cascade');
            $table->index('fk_concepto_wo');
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('concepto', function (Blueprint $table) {
            $table->dropForeign('concepto_fk_concepto_wo_foreign');
            $table->dropIndex('concepto_fk_concepto_wo_index');
        });
        Schema::dropIfExists('conceptos_wo');
    }
}
