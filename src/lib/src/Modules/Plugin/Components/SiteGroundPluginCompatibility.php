<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Components;

class SiteGroundPluginCompatibility {

	public function testIsIncompatible() :bool {
		$incompat = false;
		if ( $this->isSGOptimizerPluginAsExpected() ) {
			try {
				foreach ( $this->getIncompatOptions() as $sOption ) {
					if ( \SiteGround_Optimizer\Options\Options::is_enabled( $sOption ) ) {
						$incompat = true;
						break;
					}
				}
			}
			catch ( \Exception $e ) {
			}
		}
		return $incompat;
	}

	public function isSGOptimizerPluginAsExpected() :bool {
		$isExpected = false;
		try {
			$oRefl = new \ReflectionClass( '\SiteGround_Optimizer\Options\Options' );
			$isExpected = $oRefl->getMethod( 'is_enabled' )->isStatic()
						  && $oRefl->getMethod( 'disable_option' )->isStatic();
		}
		catch ( \Exception $e ) {
		}
		return $isExpected;
	}

	public function switchOffOptions() :bool {
		$success = false;
		if ( $this->isSGOptimizerPluginAsExpected() ) {
			try {
				foreach ( $this->getIncompatOptions() as $sOption ) {
					\SiteGround_Optimizer\Options\Options::disable_option( $sOption );
				}
				$success = !$this->testIsIncompatible();
			}
			catch ( \Exception $e ) {
			}
		}
		return $success;
	}

	/**
	 * @return string[]
	 */
	private function getIncompatOptions() {
		return [
			'siteground_optimizer_remove_query_strings',
			'siteground_optimizer_optimize_javascript_async',
		];
	}
}
