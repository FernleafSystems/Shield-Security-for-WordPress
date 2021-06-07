<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Assets\Enqueue;
use FernleafSystems\Wordpress\Services\Services;

class MfaProfilesController {

	use MfaControllerConsumer;
	use ExecOnce;

	private $rendered = false;

	private $isFrontend = false;

	protected function run() {
		$this->defineShortcodes();
		if ( Services::WpUsers()->isUserLoggedIn() ) {
			add_action( 'wp', function () {
				$this->enqueueAssets( true );
			} );
			add_action( 'admin_init', function () {
				$this->enqueueAssets( false );
			} );
		}
	}

	private function enqueueAssets( bool $isFrontend ) {
		$this->isFrontend = $isFrontend;
		add_filter( 'shield/custom_enqueues', function ( array $enqueues, $hook = '' ) {

			if ( $this->isFrontend || in_array( $hook, [ 'profile.php', 'user-edit.php' ] ) ) {
				$enqueues[ Enqueue::JS ][] = 'shield/userprofile';
				$enqueues[ Enqueue::CSS ][] = 'shield/dialog';

				if ( $this->isFrontend ) {
					add_filter( 'shield/custom_dequeues', function ( $assets ) {
						if ( !$this->rendered ) {
							$assets[ Enqueue::JS ][] = 'shield/userprofile';
							$assets[ Enqueue::CSS ][] = 'shield/dialog';
						}
						return $assets;
					} );
				}

				add_filter( 'shield/custom_localisations', function ( array $localz ) {
					$mfaCon = $this->getMfaCon();
					$user = Services::WpUsers()->getCurrentWpUser();
					$providers = $user instanceof \WP_User ? $mfaCon->getProvidersForUser( $user ) : [];
					$localz[] = [
						'shield/userprofile',
						'shield_vars_userprofile',
						[
							'ajax'    => [
								'mfa_remove_all' => $mfaCon->getMod()->getAjaxActionData( 'mfa_remove_all' )
							],
							'vars'    => [
								'providers' => array_map( function ( $provider ) {
									return $provider->getJavascriptVars();
								}, $providers )
							],
							'strings' => [
								'are_you_sure' => __( 'Are you sure?', 'wp-simple-firewall' )
							],
						]
					];
					return $localz;
				} );
			}

			return $enqueues;
		}, 10, $this->isFrontend ? 1 : 2 );
	}

	private function loadUserProfileMFA( $attributes = [] ) :string {
		$this->rendered = true;
		return ( new Profiles\RenderCustomForms() )
			->setMfaController( $this->getMfaCon() )
			->setWpUser( Services::WpUsers()->getCurrentWpUser() )
			->render( is_array( $attributes ) ? $attributes : [] );
	}

	private function defineShortcodes() {
		if ( $this->getMfaCon()->getCon()->isPremiumActive() ) {
			add_shortcode( 'SHIELD_USER_PROFILE_MFA', function ( $attributes ) {
				return $this->loadUserProfileMFA( $attributes );
			} );
		}
	}
}