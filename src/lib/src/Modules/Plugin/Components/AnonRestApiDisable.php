<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Components;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Lockdown\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class AnonRestApiDisable {

	use ExecOnce;
	use ModConsumer;

	protected function canRun() :bool {
		return !Services::WpUsers()->isUserLoggedIn() && $this->opts()->isRestApiAnonymousAccessDisabled();
	}

	protected function run() {
		add_filter( 'rest_authentication_errors', [ $this, 'disableAnonymousRestApi' ], 99 );
	}

	/**
	 * Understand that if $mCurrentStatus is null, no check has been made. If true, something has
	 * authenticated the request, and if WP_Error, then an error is already present
	 * @param \WP_Error|true|null $mStatus
	 * @return \WP_Error
	 */
	public function disableAnonymousRestApi( $mStatus ) {

		$namespace = Services::Rest()->getNamespace();
		if ( !empty( $namespace ) && $mStatus !== true && !is_wp_error( $mStatus )
			 && !self::con()->getModule_Lockdown()->isPermittedAnonRestApiNamespace( $namespace ) ) {

			$mStatus = new \WP_Error(
				'shield_block_anon_restapi',
				sprintf( __( 'Anonymous access to the WordPress Rest API has been restricted by %s.', 'wp-simple-firewall' ),
					self::con()->getHumanName() ),
				[ 'status' => rest_authorization_required_code() ] );

			self::con()->fireEvent(
				'block_anonymous_restapi',
				[ 'audit_params' => [ 'namespace' => $namespace ] ]
			);
		}

		return $mStatus;
	}
}