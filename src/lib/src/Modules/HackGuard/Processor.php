<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

/**
 * @deprecated 19.1
 */
class Processor extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Processor {

	use ModConsumer;

	public function addAdminBarMenuGroup( array $groups ) :array {
		return $groups;
	}
}