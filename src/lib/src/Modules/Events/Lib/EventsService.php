<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Events\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class EventsService {

	use PluginControllerConsumer;

	/**
	 * @var array[]
	 */
	private $aEvents;

	public function eventExists( string $eventKey ) :bool {
		return !empty( $this->getEventDef( $eventKey ) );
	}

	public function fireEvent( string $event, array $meta = [] ) {
		if ( $this->isSupportedEvent( $event ) ) {
			try {
				$this->verifyAuditParams( $event, $meta );
				do_action(
					$this->getCon()->prefix( 'event' ),
					$event,
					$meta,
					$this->getEventDef( $event )
				);
			}
			catch ( \Exception $e ) {
				error_log( $e->getMessage() );
			}
		}
	}

	/**
	 * @throws \Exception
	 */
	private function verifyAuditParams( string $event, array $meta ) {
		$def = $this->getEventDef( $event )[ 'audit_params' ] ?? [];
		$metaParams = array_keys( $meta[ 'audit_params' ] ?? [] );

		if ( empty( $def ) && !empty( $metaParams ) ) {
			error_log( sprintf( 'WARNING: Event (%s) receives params but none are defined.', $event ) );
		}
		elseif ( !empty( $def ) ) {
			if ( array_diff( $def, $metaParams ) ) {
				throw new \Exception( sprintf( "Event (%s) def has audit params that aren't present: %s", $event, implode( ', ', $def ) ) );
			}
			if ( array_diff( $metaParams, $def ) ) {
				throw new \Exception( sprintf( "Event (%s) has audit params that aren't present in def: %s", $event, implode( ', ', $metaParams ) ) );
			}
		}
	}

	/**
	 * @return array[]
	 */
	public function getEvents() :array {
		if ( empty( $this->aEvents ) ) {
			$events = [];
			foreach ( $this->getCon()->modules as $mod ) {
				$opts = $mod->getOptions();
				$events = array_merge(
					$events,
					array_map(
						function ( $evt ) use ( $mod ) {
							$evt[ 'context' ] = $mod->getSlug();
							return $evt;
						},
						/** @deprecated 12.0 - replace with $opts->getEvents() */
						is_array( $opts->getDef( 'events' ) ) ? $opts->getDef( 'events' ) : []
					)
				);
			}
			$this->aEvents = $this->buildEvents( $events );
		}
		return $this->aEvents;
	}

	/**
	 * @param string $eventKey
	 * @return array|null
	 */
	public function getEventDef( string $eventKey ) {
		return $this->getEvents()[ $eventKey ] ?? null;
	}

	public function getEventName( string $event ) :string {
		return $this->getEventStrings( $event )[ 'name' ] ?? '';
	}

	public function getEventAuditStrings( string $event ) :array {
		return $this->getEventStrings( $event )[ 'audit' ] ?? [];
	}

	public function getEventStrings( string $eventKey ) :array {
		return $this->getCon()
					->getModule( $this->getEventDef( $eventKey )[ 'context' ] )
					->getStrings()
					->getEventStrings()[ $eventKey ] ?? [];
	}

	/**
	 * @return string[]
	 */
	public function getEventNames() :array {
		return array_map(
			function ( $event ) {
				return $this->getEventName( $event[ 'key' ] );
			},
			$this->getEvents()
		);
	}

	private function buildEvents( array $events ) :array {
		$defaults = [
			'cat'              => 1,
			'stat'             => true,
			'audit'            => true,
			'recent'           => false, // whether to show in the recent events logs
			'offense'          => false, // whether to mark offense against IP
			'audit_multiple'   => false, // allow multiple audit entries in the same request
			'suppress_offense' => false, // events that normally trigger offense can be forcefully suppressed
			'level'            => 'warning', // events default at "warning" level
			'audit_params'     => [],
		];
		foreach ( $events as $eventKey => $evt ) {
			$events[ $eventKey ] = array_merge( $defaults, $evt );
			$events[ $eventKey ][ 'key' ] = $eventKey;
		}
		return $events;
	}

	/**
	 * @param string $eventKey
	 * @return bool
	 * @deprecated 12.0
	 */
	public function isSupportedEvent( string $eventKey ) :bool {
		return array_key_exists( $eventKey, $this->getEvents() );
	}
}