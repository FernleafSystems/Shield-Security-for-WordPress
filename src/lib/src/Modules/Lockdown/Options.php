<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Lockdown;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class Options extends BaseShield\Options {

	/**
	 * @return string[]
	 */
	public function getRestApiAnonymousExclusions() :array {
		$exc = apply_filters( 'shield/anonymous_rest_api_exclusions', $this->getOpt( 'api_namespace_exclusions' ) );
		return $this->getMod()->cleanStringArray( $exc, '#[^\da-z_-]#i' );
	}

	public function isOptFileEditingDisabled() :bool {
		return $this->isOpt( 'disable_file_editing', 'Y' );
	}

	public function isBlockAuthorDiscovery() :bool {
		return $this->isOpt( 'block_author_discovery', 'Y' );
	}

	public function isRestApiAnonymousAccessDisabled() :bool {
		return $this->isOpt( 'disable_anonymous_restapi', 'Y' );
	}

	public function isXmlrpcDisabled() :bool {
		return $this->isOpt( 'disable_xmlrpc', 'Y' );
	}
}