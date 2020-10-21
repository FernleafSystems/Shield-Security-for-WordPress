<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Events\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class EventsService {

	use PluginControllerConsumer;

	/**
	 * @var array[]
	 */
	private $aEvents;

	/**
	 * @param string $sEventTag
	 * @param array  $aMetaData
	 * @return $this
	 */
	public function fireEvent( $sEventTag, $aMetaData = [] ) {
		if ( $this->isSupportedEvent( $sEventTag ) ) {
			do_action( $this->getCon()->prefix( 'event' ), $sEventTag, $aMetaData );
		}
		return $this;
	}

	/**
	 * @return array[]
	 */
	public function getEvents() :array {
		if ( empty( $this->aEvents ) ) {
			$aEvts = apply_filters( $this->getCon()->prefix( 'get_all_events' ), [] );
			$this->aEvents = is_array( $aEvts ) ? $this->buildEvents( $aEvts ) : [];
		}
		return $this->aEvents;
	}

	/**
	 * @param string $sEventKey
	 * @return array|null
	 */
	public function getEventDef( $sEventKey ) {
		return $this->isSupportedEvent( $sEventKey ) ? $this->getEvents()[ $sEventKey ] : null;
	}

	/**
	 * @return string[]
	 */
	public function getEventKeys() {
		return array_keys( $this->getEvents() );
	}

	/**
	 * @param string $sEventKey
	 * @return bool
	 */
	public function isSupportedEvent( $sEventKey ) {
		return in_array( $sEventKey, $this->getEventKeys() );
	}

	/**
	 * @param array[] $aEvents
	 * @return array[]
	 */
	protected function buildEvents( $aEvents ) {
		$aDefaults = [
			'cat'              => 1,
			'stat'             => true,
			'audit'            => true,
			'recent'           => false, // whether to show in the recent events logs
			'offense'          => false, // whether to mark offense against IP
			'audit_multiple'   => false, // allow multiple audit entries in the same request
			'suppress_offense' => false, // events that normally trigger offense can be forcefully suppressed
		];
		foreach ( $aEvents as $sEventKey => $aEvt ) {
			$aEvents[ $sEventKey ] = array_merge( $aDefaults, $aEvt );
			$aEvents[ $sEventKey ][ 'key' ] = $sEventKey;
		}
		return $aEvents;
	}
}