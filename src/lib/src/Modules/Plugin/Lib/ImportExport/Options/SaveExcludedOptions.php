<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Options;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

/**
 * @deprecated 19.1 - better to replace all of this with an AJAX request per exclusion clicked.
 */
class SaveExcludedOptions {

	use ModConsumer;

	/**
	 * Takes a form submission and if the checkbox isn't checked for a given option,
	 * it means we are to exclude that option from imports/exports.
	 * @param string[] $formSubmission
	 */
	public function save( $formSubmission ) {
		$ex = [];
		$notEx = [];
		foreach ( \array_keys( self::con()->cfg->configuration->optsForModule( $this->mod()->cfg->slug ) ) as $key ) {
			empty( $formSubmission[ 'optxfer-'.$key ] ) ? $ex[] = $key : $notEx[] = $key;
		}

		self::con()->opts->setXferExcluded(
			\array_diff( \array_merge( self::con()->opts->getXferExcluded(), $ex ), $notEx )
		);
	}
}
