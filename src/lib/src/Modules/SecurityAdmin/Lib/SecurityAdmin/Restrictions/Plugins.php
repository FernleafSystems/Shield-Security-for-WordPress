<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\SecurityAdmin\Restrictions;

use FernleafSystems\Wordpress\Services\Services;

class Plugins extends BaseCapabilitiesRestrict {

	public const AREA_SLUG = 'plugins';

	/**
	 * @param array $allCaps
	 * @param       $cap
	 * @param array $args
	 * @return array
	 */
	public function removeCapabilities( $allCaps, $cap, $args ) {
		$req = Services::Request();

		/** @var string $requestedCap */
		$requestedCap = $args[ 0 ];

		// special case for plugin info thickbox for changelog
		$isChangelog = \defined( 'IFRAME_REQUEST' )
					   && ( $requestedCap === 'install_plugins' )
					   && ( $req->query( 'section' ) == 'changelog' )
					   && $req->query( 'plugin' );

		if ( !$isChangelog && \is_string( $requestedCap ) && $this->isCapabilityToBeRestricted( $requestedCap ) ) {
			$allCaps[ $requestedCap ] = false;
		}

		return $allCaps;
	}

	protected function getApplicableCapabilities() :array {
		return [
			'activate_plugins',
			'delete_plugins',
			'install_plugins',
			'update_plugins'
		];
	}
}