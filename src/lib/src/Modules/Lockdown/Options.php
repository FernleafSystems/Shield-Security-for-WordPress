<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Lockdown;

use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\ArrayOps;

class Options extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Options {

	/**
	 * @return string[]
	 * @deprecated 19.1
	 */
	public function getRestApiAnonymousExclusions() :array {
		return \array_unique( \array_merge(
			ArrayOps::CleanStrings(
				apply_filters( 'shield/anonymous_rest_api_exclusions', $this->getOpt( 'api_namespace_exclusions' ) ),
				'#[^\da-z_-]#i'
			)
		) );
	}

	/**
	 * @deprecated 19.1
	 */
	public function isBlockAuthorDiscovery() :bool {
		return $this->isOpt( 'block_author_discovery', 'Y' );
	}

	/**
	 * @deprecated 19.1
	 */
	public function isXmlrpcDisabled() :bool {
		return $this->isOpt( 'disable_xmlrpc', 'Y' );
	}
}