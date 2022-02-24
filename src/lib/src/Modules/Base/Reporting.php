<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports;

abstract class Reporting {

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
	 * @param Reports\BaseReporter[] $reporters
	 * @return array
	 */
	protected function assignMod( array $reporters ) :array {
		return array_map( function ( $reporter ) {
			return $reporter->setMod( $this->getMod() );
		}, $reporters );
	}
}