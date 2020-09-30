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
}