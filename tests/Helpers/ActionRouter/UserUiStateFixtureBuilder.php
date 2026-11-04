<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\DashboardLiveMonitorPreference;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\RuntimeTestState;

/**
 * Every test in a browser lane signs in as the same admin, so per-user display choices one test makes
 * (such as leaving the Live Monitor collapsed) are reset to their defaults before the next test.
 */
class UserUiStateFixtureBuilder {

	/**
	 * @return array{reset:bool}
	 */
	public function reset() :array {
		RuntimeTestState::loginAsSecurityAdmin();
		$meta = RuntimeTestState::controller()->user_metas->current();
		if ( empty( $meta ) ) {
			throw new \RuntimeException( 'Shield user meta is not available for the browser admin user.' );
		}

		$flags = $meta->flags;
		if ( isset( $flags[ DashboardLiveMonitorPreference::FLAG_KEY ] ) ) {
			unset( $flags[ DashboardLiveMonitorPreference::FLAG_KEY ] );
			$meta->flags = $flags;
		}

		return [ 'reset' => true ];
	}
}
