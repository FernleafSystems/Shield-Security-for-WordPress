<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

abstract class BaseReporting {

	use ModConsumer;

	/**
	 * @param int|null $nFromTs
	 * @param int|null $nUntilTs
	 * @return array
	 */
	abstract public function buildAlerts( $nFromTs = null, $nUntilTs = null );
}