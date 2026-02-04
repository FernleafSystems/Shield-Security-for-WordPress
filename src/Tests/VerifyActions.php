<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class VerifyActions {

	use PluginControllerConsumer;

	public function run() {
		$this->checkUnique();
	}

	private function checkUnique() {
		$slugs = [];
		$duplicates = [];
		foreach ( Constants::ACTIONS as $actionClass ) {
			if ( !\in_array( $actionClass::SLUG, $slugs ) ) {
				$slugs[] = $actionClass::SLUG;
			}
			else {
				$duplicates[] = $actionClass;
			}
		}

		if ( empty( $duplicates ) ) {
			echo "\nNo Duplicate action slugs";
		}
		else {
			echo "\nDuplicate action slugs for: ".var_export( $duplicates, true );
		}
	}
}