<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Events;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

abstract class EventsListener {

	use PluginControllerConsumer;

	/**
	 * @var bool
	 */
	private $commit = false;

	public function __construct() {
		add_action( 'shield/event', function ( $event, $meta = [], $def = [] ) {
			$this->captureEvent( (string)$event, \is_array( $meta ) ? $meta : [], \is_array( $def ) ? $def : [] );
		}, 10, 3 );

		add_action( self::con()->prefix( 'plugin_shutdown' ), function () {
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
		return !self::con()->plugin_deleting && $this->commit;
	}

	public function setIfCommit( bool $commit ) {
		$this->commit = $commit;
	}
}