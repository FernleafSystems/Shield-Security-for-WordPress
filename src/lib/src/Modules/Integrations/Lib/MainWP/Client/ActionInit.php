<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Client;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class ActionInit {

	use ModConsumer;

	public function run( string $action ) :array {
		return [
			'test_response' => $action
		];
	}
}