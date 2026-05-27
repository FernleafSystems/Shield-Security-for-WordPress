<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Rest\Worpdrive\Fixtures;

use FernleafSystems\Wordpress\Services\Core\Request;

class WorpdriveTestRequest extends Request {

	public function server( $key, $default = null ) {
		if ( $key === 'SCRIPT_FILENAME' ) {
			return ABSPATH.'index.php';
		}
		return parent::server( $key, $default );
	}
}
