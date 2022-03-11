<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Rest\Route;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Rest\Request\Process;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

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
		return apply_filters( 'shield/rest_api_verify_permission', parent::verifyPermission( $req ), $req );
	}

	protected function getConfigDefaults() :array {
		$cfg = parent::getConfigDefaults();
		$cfg[ 'authorization' ][ 'user_cap' ] = 'manage_options';
		return $cfg;
	}
}