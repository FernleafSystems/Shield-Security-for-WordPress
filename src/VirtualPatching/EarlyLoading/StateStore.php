<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\EarlyLoading;

interface StateStore {

	public function load() :State;

	public function save( State $state ) :void;
}
