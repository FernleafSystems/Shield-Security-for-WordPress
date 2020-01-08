<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Events\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

abstract class EventsListener {

	use PluginControllerConsumer;

	/**
	 * @var bool
	 */
	private $bCommit = false;

	/**
	 * EventsListener constructor.
	 * @param Controller\Controller $oCon
	 */
	public function __construct( $oCon ) {
		$this->setCon( $oCon );

		add_action( $oCon->prefix( 'event' ),
			function ( $sEvent, $aMeta = [] ) use ( $oCon ) {
				if ( $oCon->loadEventsService()->isSupportedEvent( $sEvent ) ) {
					$this->captureEvent( $sEvent, $aMeta );
				}
			}, 10, 2 );

		add_action( $oCon->prefix( 'plugin_shutdown' ), function () {
			$this->onShutdown();
		}, 100 );
	}

	/**
	 * @param string $sEvent
	 * @param array  $aMeta
	 */
	abstract protected function captureEvent( $sEvent, $aMeta = [] );

	protected function onShutdown() {

	}

	/**
	 * @return bool
	 */
	public function isCommit() {
		return (bool)$this->bCommit;
	}

	/**
	 * @param bool $bCommit
	 * @return $this
	 */
	public function setIfCommit( $bCommit ) {
		$this->bCommit = $bCommit;
		return $this;
	}
}