<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ScanQueue;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

/**
 * Class Controller
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ScanQueue
 */
class Controller {

	use ModConsumer;

	/**
	 * @var QueueProcessor
	 */
	private $oQueue;

	public function __construct() {
		add_action( 'init', [ $this, 'onWpInit' ] );
	}

	public function onWpInit() {
		$this->getQueueProcessor();
	}

	/**
	 * @param string $sScanSlug
	 * @throws \Exception
	 */
	public function startScan( $sScanSlug ) {
		( new ScanLaunch() )
			->setMod( $this->getMod() )
			->setQueueProcessor( $this->getQueueProcessor() )
			->launch( $sScanSlug );
	}

	/**
	 * @return QueueProcessor
	 */
	public function getQueueProcessor() {
		if ( empty( $this->oQueue ) ) {
			$this->oQueue = ( new QueueProcessor() )->setMod( $this->getMod() );
		}
		return $this->oQueue;
	}
}
