<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\ResponseAdapter;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\ActionResponse;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class BaseAdapter {

	use ModConsumer;

	/**
	 * @throws ActionException
	 */
	public function adapt( ActionResponse $response ) {
	}
}