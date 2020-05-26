<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\WpCli;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

abstract class BaseWpCliCmd {

	use ModConsumer;
	use \FernleafSystems\Utilities\Logic\OneTimeExecute;

	/**
	 * @throws \Exception
	 */
	protected function addCmds() {
	}

	protected function run() {
		try {
			$this->addCmds();
		}
		catch ( \Exception $oE ) {
		}
	}

	/**
	 * @param array $aParts
	 * @return string
	 */
	protected function buildCmd( array $aParts ) {
		return implode( ' ',
			array_filter( array_merge( $this->getBaseCmdParts(), $aParts ) )
		);
	}

	/**
	 * @return bool
	 */
	protected function canRun() {
		return $this->getCon()->isPremiumActive();
	}

	/**
	 * @return string[]
	 */
	protected function getBaseCmdParts() {
		return [ 'shield', $this->getBaseCmdKey() ];
	}

	/**
	 * @return string
	 */
	protected function getBaseCmdKey() {
		$sRoot = $this->getOptions()->getWpCliCfg()[ 'root' ];
		return empty( $sRoot ) ? $this->getMod()->getModSlug( false ) : $sRoot;
	}
}