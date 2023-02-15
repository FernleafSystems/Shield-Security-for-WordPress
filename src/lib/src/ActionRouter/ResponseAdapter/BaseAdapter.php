<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ResponseAdapter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionResponse;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class BaseAdapter {

	use PluginControllerConsumer;

	/**
	 * @throws ActionException
	 */
	public function adapt( ActionResponse $response ) {
	}
}