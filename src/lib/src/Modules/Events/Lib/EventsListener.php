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
	 * @param Controller\Controller $con
	 */
	public function __construct( $con ) {
		$this->setCon( $con );

		add_action( $con->prefix( 'event' ),
			function ( $event, $meta = [], $def = [] ) use ( $con ) {
				// TODO @deprecated 11.1 remove ??
				$this->captureEvent( $event, $meta, $def ?? [] );
			}, 10, 2 );

		add_action( $con->prefix( 'plugin_shutdown' ), function () {
			$this->onShutdown();
		}, 100 );
	}

	/**
	 * @param string $evt
	 * @param array  $meta
	 * @param array  $def
	 */
	abstract protected function captureEvent( string $evt, $meta = [], $def = [] );

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