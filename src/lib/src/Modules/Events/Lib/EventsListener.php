<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Events\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

abstract class EventsListener {

	use PluginControllerConsumer;

	/**
	 * @var bool
	 */
	private $commit;

	/**
	 * @param Controller\Controller $con
	 * @throws \Exception
	 */
	public function __construct( $con = null, bool $commit = false ) {
		if ( !$con instanceof Controller\Controller ) {
			$con = shield_security_get_plugin()->getController();
		}
		$this->setCon( $con );
		$this->commit = $commit;

		add_action( 'shield/event',
			function ( $event, $meta = [], $def = [] ) {
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

	public function isCommit() :bool {
		return $this->commit;
	}

	/**
	 * @return $this
	 */
	public function setIfCommit( bool $commit ) {
		$this->commit = $commit;
		return $this;
	}
}