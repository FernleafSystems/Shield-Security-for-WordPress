<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\v1\Route;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Utilities\Net\IpID;
use FernleafSystems\Wordpress\Services\Utilities\ServiceProviders;

abstract class Base extends \FernleafSystems\Wordpress\Plugin\Core\Rest\Route\RouteBase {

	use PluginControllerConsumer;

	protected function getNamespace() :string {
		return 'shield';
	}

	protected function verifyPermission( \WP_REST_Request $req ) {
		/** @var \WP_Error|bool $verify */
		$verify = apply_filters( 'shield/rest_api_verify_permission', parent::verifyPermission( $req ), $req );
		if ( ( ( is_wp_error( $verify ) && $verify->has_errors() ) || $verify === false )
			 && ( $this->isShieldServiceAuthorised() && $this->isRequestFromShieldService() )
		) {
			$verify = true;
		}
		return $verify === true ? true : $this->normalizePermissionDeniedResult( $verify );
	}

	protected function getConfigDefaults() :array {
		$cfg = parent::getConfigDefaults();
		$cfg[ 'authorization' ][ 'user_cap' ] = 'manage_options';
		return $cfg;
	}

	protected function isShieldServiceAuthorised() :bool {
		return false;
	}

	protected function isRequestFromShieldService() :bool {
		try {
			$isShield = ( new IpID( self::con()->this_req->ip ) )->run()[ 0 ] === ServiceProviders::PROVIDER_SHIELD;
		}
		catch ( \Exception $e ) {
			$isShield = false;
		}
		return $isShield;
	}

	/**
	 * The entire REST API is available to cap:level_2 only, but the licenses endpoints are cap:level_1.
	 */
	public function isRouteAvailable() :bool {
		return self::con()->caps->canRestAPILevel2();
	}

	/**
	 * @param \WP_Error|bool $verify
	 */
	private function normalizePermissionDeniedResult( $verify ) :\WP_Error {
		$status = \function_exists( '\rest_authorization_required_code' ) ? \rest_authorization_required_code() : 403;

		if ( \is_wp_error( $verify ) ) {
			$errorCode = $verify->get_error_code();
			$errorData = $verify->get_error_data( $errorCode );
			$errorMessage = $verify->get_error_message( $errorCode );

			return new \WP_Error(
				empty( $errorCode ) ? 'shield_rest_permission_denied' : (string)$errorCode,
				empty( $errorMessage ) ? __( 'Sorry, you are not allowed to access this resource.', 'wp-simple-firewall' ) : $errorMessage,
				\array_merge(
					\is_array( $errorData ) ? $errorData : [],
					[ 'status' => (int)( \is_array( $errorData ) ? ( $errorData[ 'status' ] ?? $status ) : $status ) ]
				)
			);
		}

		return new \WP_Error(
			'shield_rest_permission_denied',
			__( 'Sorry, you are not allowed to access this resource.', 'wp-simple-firewall' ),
			[ 'status' => $status ]
		);
	}
}
