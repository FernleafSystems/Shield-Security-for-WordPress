<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\Adhoc;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Options\Transient;

class ShieldChangelogRetrieve {

	use PluginControllerConsumer;

	public function fromFile() :array {
		return \json_decode( Services::WpFs()->getFileContent(
			path_join( self::con()->getRootDir(), 'cl.json' )
		), true );
	}

	/**
	 * @throws \Exception
	 */
	public function fromRepo() :array {
		$cl = Transient::Get( 'shield_cl' );
		if ( empty( $cl ) ) {
			$raw = Services::HttpRequest()
						   ->getContent( 'https://raw.githubusercontent.com/FernleafSystems/Shield-Security-for-WordPress/master/cl.json' );
			if ( empty( $raw ) ) {
				throw new \Exception( "Couldn't retrieve changelog" );
			}
			$cl = \json_decode( $raw, true );
			if ( empty( $cl ) ) {
				throw new \Exception( "Couldn't build changelog" );
			}
			Transient::Set( 'shield_cl', $cl, \DAY_IN_SECONDS );
		}
		return $cl;
	}
}