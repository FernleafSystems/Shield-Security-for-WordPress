<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Lockdown;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Options extends Base\ShieldOptions {

	/**
	 * @return string[]
	 */
	public function getRestApiAnonymousExclusions() {
		$ex = $this->getOpt( 'api_namespace_exclusions' );
		return array_merge( $this->getDef( 'default_restapi_exclusions' ), is_array( $ex ) ? $ex : [] );
	}

	/**
	 * @return bool
	 */
	public function isOptFileEditingDisabled() {
		return $this->isOpt( 'disable_file_editing', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function isRestApiAnonymousAccessDisabled() {
		return $this->isOpt( 'disable_anonymous_restapi', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function isXmlrpcDisabled() {
		return $this->isOpt( 'disable_xmlrpc', 'Y' );
	}
}