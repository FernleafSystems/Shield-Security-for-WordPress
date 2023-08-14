<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Options;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class BuildTransferableOptions {

	use ModConsumer;

	/**
	 * @return array[]
	 */
	public function build() :array {
		$opts = $this->getOptions();
		return \array_merge(
			\array_fill_keys( $opts->getOptionsKeys(), false ),
			\array_fill_keys( \array_keys( $opts->getTransferableOptions() ), 'Y' ),
			\array_fill_keys( $opts->getXferExcluded(), 'N' )
		);
	}
}