<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail;

class Processor extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Processor {

	use ModConsumer;

	protected function run() {
		$this->mod()->getAuditCon()->execute();
	}
}