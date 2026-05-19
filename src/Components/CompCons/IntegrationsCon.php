<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Common\BaseBotDetectionController;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class IntegrationsCon {

	use ExecOnce;
	use PluginControllerConsumer;

	protected function run() {
		if ( self::con()->this_req->wp_is_admin || self::con()->this_req->wp_is_cron ) {
			$this->autoIntegrations();
		}
	}

	private function autoIntegrations() :void {
		$opts = self::con()->opts;
		if ( $opts->optIs( 'enable_auto_integrations', 'Y' ) ) {
			$trk = \array_merge( [
				'last_check_at' => 0,
				'profile_hash'  => '',
			], $opts->optGet( 'auto_integrations_track' ) );

			$currentHash = $this->buildCurrentProfileHash();
			if ( Services::Request()->carbon()->subMinute()->timestamp > $trk[ 'last_check_at' ]
				 && !\hash_equals( $trk[ 'profile_hash' ], $currentHash ) ) {

				$trk[ 'last_check_at' ] = Services::Request()->carbon()->timestamp;
				$trk[ 'profile_hash' ] = $currentHash;
				$opts->optSet( 'auto_integrations_track', $trk );

				$ints = $this->buildIntegrationsStates();
				/** @var BaseBotDetectionController $formCon */
				foreach ( [ self::con()->comps->forms_users, self::con()->comps->forms_spam ] as $formCon ) {
					$selected = $formCon->getSelectedProviders();
					foreach ( \array_keys( $formCon->getInstalled() ) as $slug ) {
						if ( ( $ints[ $slug ][ 'state' ] ?? '' ) === 'available' && $ints[ $slug ][ 'has_cap' ] ) {
							$selected[] = $slug;
						}
					}
					$opts->optSet( $formCon->getSelectedProvidersOptKey(), \array_values( \array_unique( $selected ) ) );
				}
				if ( $opts->hasChanges() ) {
					$opts->store();
				}
			}
		}
	}

	/**
	 * @return array<string,array{slug:string,state:string,name:string,has_cap:bool}>
	 */
	public function buildIntegrationsStates() :array {
		$integrations = [];
		/** @var BaseBotDetectionController $formCon */
		foreach ( [ self::con()->comps->forms_users, self::con()->comps->forms_spam ] as $formCon ) {
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
						'state'   => \in_array( $slug, $formCon->getSelectedProviders(), true ) ? 'enabled' : 'available',
						'name'    => $this->providerNameFor( $formCon, $slug ),
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

	private function providerNameFor( BaseBotDetectionController $formCon, string $slug ) :string {
		foreach ( $formCon->providerOptions() as $option ) {
			if ( ( $option[ 'value_key' ] ?? '' ) === $slug ) {
				return (string)( $option[ 'text' ] ?? $slug );
			}
		}
		return $slug;
	}

	private function buildCurrentProfileHash() :string {
		return \hash( 'md5', \serialize( [
			'plugins' => Services::WpPlugins()->getActivePlugins(),
		] ) );
	}
}
