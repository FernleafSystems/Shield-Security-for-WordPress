<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Common\BaseBotDetectionController;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\WpOrg\Plugin\Data;

class IntegrationsCon {

	use ExecOnce;
	use PluginControllerConsumer;

	protected function run() {
		$this->autoIntegrations();
	}

	private function autoIntegrations() :void {
		$opts = self::con()->opts;
		if ( $opts->optIs( 'enable_auto_integrations', 'Y' ) ) {
			$trk = \array_merge( [
				'last_check_at' => 0,
				'profile_hash'  => '',
			], $opts->optGet( 'auto_integrations_track' ) );

			if ( Services::Request()->carbon()->subMinute()->timestamp > $trk[ 'last_check_at' ]
				 && !\hash_equals( $trk[ 'profile_hash' ], $this->buildCurrentProfileHash() ) ) {

				$trk[ 'last_check_at' ] = Services::Request()->carbon()->timestamp;
				$trk[ 'profile_hash' ] = $this->buildCurrentProfileHash();
				$opts->optSet( 'auto_integrations_track', $trk )->store();

				$ints = $this->buildIntegrationsStates();
				foreach ( [ self::con()->comps->forms_users, self::con()->comps->forms_spam ] as $formCon ) {
					/** @var BaseBotDetectionController $formCon */
					foreach ( \array_keys( $formCon->getInstalled() ) as $slug ) {
						if ( ( $ints[ $slug ][ 'state' ] ?? '' ) === 'available' && $ints[ $slug ][ 'has_cap' ] ) {
							$opts->optSet( $formCon->getSelectedProvidersOptKey(), \array_merge( $formCon->getSelectedProviders(), [ $slug ] ) );
						}
					}
				}
			}
		}
	}

	public function buildIntegrationsStates() :array {
		$integrations = [];
		foreach ( [ self::con()->comps->forms_users, self::con()->comps->forms_spam ] as $formCon ) {
			/** @var BaseBotDetectionController $formCon */
			foreach ( \array_keys( $formCon->getInstalled() ) as $slug ) {
				if ( $slug === 'wordpress' ) {
					$integrations[ $slug ] = [
						'slug'    => $slug,
						'state'   => 'enabled',
						'name'    => 'WordPress',
						'has_cap' => true,
					];
				}
				else {
					$integrations[ $slug ] = [
						'slug'    => $slug,
						'state'   => \in_array( $slug, $formCon->getSelectedProviders() ) ? 'enabled' : 'available',
						'name'    => Data::RetrieveFor( $slug )[ 'properties' ][ 'name' ],
						'has_cap' => $this->hasCapForIntegration( $slug ),
					];
				}
			}
		}
		return $integrations;
	}

	public function hasCapForIntegration( string $slug ) :bool {
		$con = self::con();
		return $slug === 'wordpress'
			   || ( isset( $con->comps->forms_spam->enumProviders()[ $slug ] ) && $con->caps->canThirdPartyScanSpam() )
			   || ( isset( $con->comps->forms_users->enumProviders()[ $slug ] ) && $con->caps->canThirdPartyScanUsers() );
	}

	private function buildCurrentProfileHash() :string {
		return \hash( 'md5', \serialize( [
			'plugins' => Services::WpPlugins()->getActivePlugins(),
		] ) );
	}
}