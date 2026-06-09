<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSettingsTable extends Migration
{
	protected $tablename;
	protected $keyColumn;
	protected $valueColumn;

	public function __construct()
	{
		$this->tablename = config('settings.table', 'settings');
		$this->keyColumn = config('settings.keyColumn', 'key');
		$this->valueColumn = config('settings.valueColumn', 'value');
	}

	public function up()
	{
		Schema::create($this->tablename, function (Blueprint $table) {
			$table->increments('id');
			$table->string($this->keyColumn)->unique('settings_key_unique');
			$table->text($this->valueColumn);
		});
	}

	public function down()
	{
		Schema::drop($this->tablename);
	}
}
