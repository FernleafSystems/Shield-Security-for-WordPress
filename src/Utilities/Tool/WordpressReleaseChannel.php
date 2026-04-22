<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool;

use FernleafSystems\Wordpress\Services\Services;

class WordpressReleaseChannel {

	public function isDevelopmentBuild() :bool {
		return \str_contains( Services::WpGeneral()->getVersion( true ), '-' );
	}
}
