<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\PluginNotices;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Dependencies\Monolog;

class ConflictMonolog extends Base {

	public function check() :?array {
		try {
			( new Monolog() )->assess();
			$issue = null;
		}
		catch ( \Exception $e ) {
			$issue = [
				'id'        => 'conflict_monolog',
				'type'      => 'warning',
				'text'      => [
					__( 'You have a PHP library conflict with the Monolog library. Likely another plugin is using an incompatible version of the library.', 'wp-simple-firewall' ),
					$e->getMessage(),
				],
				'locations' => [
					'shield_admin_top_page',
				],
				'flags'     => [
					'conflict',
				]
			];
		}
		return $issue;
	}
}