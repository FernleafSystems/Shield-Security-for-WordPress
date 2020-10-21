<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports;

abstract class BaseReporting {

	use ModConsumer;

	/**
	 * @return Reports\BaseReporter[]
	 */
	public function getAlertReporters() :array {
		return $this->assignMod( $this->enumAlertReporters() );
	}

	/**
	 * @return Reports\BaseReporter[]
	 */
	public function getInfoReporters() :array {
		return $this->assignMod( $this->enumInfoReporters() );
	}

	/**
	 * @return Reports\BaseReporter[]
	 */
	protected function enumAlertReporters() :array {
		return [];
	}

	/**
	 * @return Reports\BaseReporter[]
	 */
	protected function enumInfoReporters() :array {
		return [];
	}

	/**
	 * @param Reports\BaseReporter[] $aReporters
	 * @return array
	 */
	protected function assignMod( array $aReporters ) :array {
		return array_map( function ( $oReporter ) {
			return $oReporter->setMod( $this->getMod() );
		}, $aReporters );
	}
}