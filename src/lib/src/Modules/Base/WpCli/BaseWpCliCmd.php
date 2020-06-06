<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\WpCli;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Options;

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
		/** @var Options $oOpts */
		$oOpts = $this->getCon()
					  ->getModule_Plugin()
					  ->getOptions();
		return $oOpts->isEnabledWpcli();
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

	/**
	 * @param array $aArgs
	 * @return array
	 */
	protected function mergeCommonCmdArgs( array $aArgs ) {
		return array_merge(
			$this->getCommonCmdArgs(),
			$aArgs
		);
	}

	/**
	 * @return array
	 */
	protected function getCommonCmdArgs() {
		return [
			'before_invoke' => function () {
				$this->beforeInvokeCmd();
			},
			'after_invoke' => function () {
				$this->afterInvokeCmd();
			}
		];
	}

	protected function afterInvokeCmd() {
	}

	protected function beforeInvokeCmd() {
	}
}