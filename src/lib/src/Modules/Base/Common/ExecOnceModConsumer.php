<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

/**
 * @deprecated 19.2
 */
class ExecOnceModConsumer {

	use ModConsumer;
	use ExecOnce;
}