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
				$this->captureEvent(
					(string)$event,
					is_array( $meta ) ? $meta : [],
					is_array( $def ) ? $def : []
				);
			}, 10, 3 );

		add_action( $con->prefix( 'plugin_shutdown' ), function () {
			$this->onShutdown();
		}, 100 );

		$this->init();
	}

	protected function init() {
	}

	abstract protected function captureEvent( string $evt, array $meta = [], array $def = [] );

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