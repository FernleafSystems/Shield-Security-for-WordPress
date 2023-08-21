<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Data;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\ModConsumer;

class BuildBase {

	use ModConsumer;

	protected $start;

	protected $end;

	public function __construct( int $start, int $end ) {
		$this->start = $start;
		$this->end = $end;
	}
}