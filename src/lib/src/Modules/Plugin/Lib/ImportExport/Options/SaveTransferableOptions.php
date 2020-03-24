<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Options;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class SaveTransferableOptions {

	use ModConsumer;

	/**
	 * Takes a form submission and if the checkbox isn't checked for a given option,
	 * it means we are to exclude that option from imports/exports.
	 * @param string[] $aFormSubmission
	 */
	public function save( $aFormSubmission ) {
		$oOpts = $this->getOptions();
		$aExcluded = [];
		foreach ( array_keys( $oOpts->getTransferableOptions() ) as $sOptKey ) {
			if ( empty( $aFormSubmission[ 'optxfer-'.$sOptKey ] ) ) {
				$aExcluded[] = $sOptKey;
			}
		}
		$oOpts->setOpt( 'xfer_excluded', $aExcluded );
	}
}
