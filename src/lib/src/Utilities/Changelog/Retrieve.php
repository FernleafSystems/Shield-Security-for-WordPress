<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\Changelog;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class Retrieve {

	use PluginControllerConsumer;

	public function fromFile() :array {
		return json_decode( Services::WpFs()->getFileContent(
			path_join( $this->getCon()->getRootDir(), 'cl.json' )
		), true );
	}

	/**
	 * @return array
	 * @throws \Exception
	 */
	public function fromRepo() :array {
		$raw = Services::HttpRequest()
					   ->get( 'https://raw.githubusercontent.com/FernleafSystems/Shield-Security-for-WordPress/develop/cl.json' );
		if ( empty( $raw ) ) {
			throw new \Exception( "Couldn't retrieve changelog" );
		}
		return json_decode( $raw, true );
	}
}