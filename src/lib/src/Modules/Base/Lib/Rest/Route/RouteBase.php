<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Lib\Rest\Route;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Lib\Rest\Request\Process;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

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
		$can = Services::WpUsers()->isUserAdmin();
		return apply_filters( 'shield/rest_api_verify_permission', $can, $req );
	}
}