<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\BotSignals;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

class Select extends Base\Select {

	use Common;
	use Base\Traits\Select_IPTable;
}