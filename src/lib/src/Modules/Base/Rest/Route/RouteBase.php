<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Rest\Route;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Rest\Request\Process;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Utilities\Net\IpID;
use FernleafSystems\Wordpress\Services\Utilities\ServiceProviders;

abstract class RouteBase extends \FernleafSystems\Wordpress\Plugin\Core\Rest\Route\RouteBase {

	use ModConsumer;

	protected function getNamespace() :string {
		return 'shield';
	}

	protected function getRequestProcessor() {
		/** @var Process $proc */
		$proc = parent::getRequestProcessor();
		return $proc->setMod( $this->getMod() );
	}

	protected function verifyPermission( \WP_REST_Request $req ) {
		/** @var \WP_Error|bool $verify */
		$verify = apply_filters( 'shield/rest_api_verify_permission', parent::verifyPermission( $req ), $req );
		if ( ( ( is_wp_error( $verify ) && $verify->has_errors() ) || $verify === false )
			 && ( $this->isShieldServiceAuthorised() && $this->isRequestFromShieldService() )
		) {
			$verify = true;
		}
		return $verify;
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
			$isShield = ( new IpID( $this->con()->this_req->ip ) )->run()[ 0 ] === ServiceProviders::PROVIDER_SHIELD;
		}
		catch ( \Exception $e ) {
			$isShield = false;
		}
		return $isShield;
	}
}