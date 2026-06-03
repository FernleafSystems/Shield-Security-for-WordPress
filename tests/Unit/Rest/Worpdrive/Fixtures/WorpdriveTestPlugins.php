<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Rest\Worpdrive\Fixtures;

use FernleafSystems\Wordpress\Services\Core\Plugins;

class WorpdriveTestPlugins extends Plugins {

	public function getPlugins() :array {
		return [
			'zeta/zeta.php'   => [
				'Name'    => 'Zeta',
				'Version' => '2.0.0',
			],
			'alpha/main.php'  => [
				'Name'    => 'Alpha',
				'Version' => '1.0.0',
			],
			'single-file.php' => [
				'Name' => 'Single File',
			],
		];
	}
}
