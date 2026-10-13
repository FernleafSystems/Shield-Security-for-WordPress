<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\EarlyLoading;

use FernleafSystems\Wordpress\Services\Services;

class WpStateStore implements StateStore {

	private string $optionKey;

	public function __construct( string $optionKey ) {
		$this->optionKey = $optionKey;
	}

	public function load() :State {
		$data = Services::WpGeneral()->getOption( $this->optionKey );
		return State::fromArray( \is_array( $data ) ? $data : [] );
	}

	public function save( State $state ) :void {
		Services::WpGeneral()->updateOption( $this->optionKey, $state->toArray() );
	}
}
