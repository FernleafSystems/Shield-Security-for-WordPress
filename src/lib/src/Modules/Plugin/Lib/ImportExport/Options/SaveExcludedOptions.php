<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Options;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class SaveExcludedOptions {

	use ModConsumer;

	/**
	 * Takes a form submission and if the checkbox isn't checked for a given option,
	 * it means we are to exclude that option from imports/exports.
	 * @param string[] $formSubmission
	 */
	public function save( $formSubmission ) {
		$excluded = [];
		foreach ( \array_keys( $this->opts()->getTransferableOptions() ) as $optKey ) {
			if ( empty( $formSubmission[ 'optxfer-'.$optKey ] ) ) {
				$excluded[] = $optKey;
			}
		}
		$this->opts()->setOpt( 'xfer_excluded', $excluded );
	}
}
