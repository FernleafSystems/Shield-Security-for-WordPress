<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Components;

class SiteGroundPluginCompatibility {

	/**
	 * @return bool
	 */
	public function testIsIncompatible() {
		$bIncompatExist = false;
		if ( $this->isSGOptimizerPluginAsExpected() ) {
			try {
				foreach ( $this->getIncompatOptions() as $sOption ) {
					if ( \SiteGround_Optimizer\Options\Options::is_enabled( $sOption ) ) {
						$bIncompatExist = true;
						break;
					}
				}
			}
			catch ( \Exception $oE ) {
			}
		}
		return $bIncompatExist;
	}

	/**
	 * @return bool
	 */
	public function isSGOptimizerPluginAsExpected() {
		$bExpected = false;
		try {
			$oRefl = new \ReflectionClass( '\SiteGround_Optimizer\Options\Options' );
			$bExpected = $oRefl->getMethod( 'is_enabled' )->isStatic()
						 && $oRefl->getMethod( 'disable_option' )->isStatic();
		}
		catch ( \Exception $oE ) {
		}
		return $bExpected;
	}

	/**
	 * @return bool
	 */
	public function switchOffOptions() {
		$bSuccess = false;
		if ( $this->isSGOptimizerPluginAsExpected() ) {
			try {
				foreach ( $this->getIncompatOptions() as $sOption ) {
					\SiteGround_Optimizer\Options\Options::disable_option( $sOption );
				}
				$bSuccess = !$this->testIsIncompatible();
			}
			catch ( \Exception $oE ) {
			}
		}
		return $bSuccess;
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
