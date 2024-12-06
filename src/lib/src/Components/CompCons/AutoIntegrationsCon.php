<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class AutoIntegrationsCon {

	use ExecOnce;
	use PluginControllerConsumer;

	protected function canRun() :bool {
		return self::con()->opts->optIs( 'enable_auto_integrations', 'Y' );
	}

	protected function run() {
		$trk = \array_merge( [
			'last_check_at' => 0,
			'profile_hash'  => '',
		], self::con()->opts->optGet( 'auto_integrations_track' ) );

		if ( Services::Request()->carbon()->subMinute()->timestamp > $trk[ 'last_check_at' ]
			 && !\hash_equals( $trk[ 'profile_hash' ], $this->buildCurrentProfileHash() ) ) {

			$trk[ 'last_check_at' ] = Services::Request()->carbon()->timestamp;
			$trk[ 'profile_hash' ] = $this->buildCurrentProfileHash();
			self::con()->opts->optSet( 'auto_integrations_track', $trk )->store();

			$this->runCheck();
		}
	}

	private function runCheck() :void {
		$con = self::con();
		foreach ( [ $con->comps->forms_users, $con->comps->forms_spam ] as $formCon ) {
			foreach ( $formCon->getInstalled() as $slug => $class ) {
				if ( !\in_array( $slug, $formCon->getSelectedProviders() ) ) {
					$con->opts->optSet(
						$formCon->getSelectedProvidersOptKey(),
						\array_merge( $formCon->getSelectedProviders(), [ $slug ] )
					);
				}
			}
		}
	}

	private function buildCurrentProfileHash() :string {
		return \hash( 'md5', \serialize( [
			'plugins' => Services::WpPlugins()->getActivePlugins(),
		] ) );
	}
}