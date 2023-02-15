<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Rest\Route;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Rest\Request\Process;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Exceptions\NotAnIpAddressOrRangeException;
use FernleafSystems\Wordpress\Services\Services;
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
		( $this->isShieldServiceAuthorised() && $this->isRequestFromShieldService() );
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
			$data = Services::ServiceProviders()->getProviderInfo( ServiceProviders::PROVIDER_SHIELD );
			return Services::IP()->IpIn(
				$this->getCon()->this_req->ip,
				array_merge( $data[ 'ips' ][ 4 ] ?? [], $data[ 'ips' ][ 6 ] ?? [] )
			);
		}
		catch ( NotAnIpAddressOrRangeException $e ) {
			return false;
		}
	}
}