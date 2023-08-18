<?php declare( strict_types=1 );

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
		try {
			if ( !$this->eventExists( $event ) ) {
				throw new \Exception( sprintf( 'Event %s does not exist.', $event ) );
			}
			do_action(
				'shield/event',
				$event,
				$this->verifyAuditParams( $event, $meta ),
				$this->getEventDef( $event )
			);
		}
		catch ( \Exception $e ) {
			/* error_log( $e->getMessage() ); */
		}
	}

	/**
	 * @throws \Exception
	 */
	private function verifyAuditParams( string $event, array $meta ) :array {
		$def = $this->getEventDef( $event )[ 'audit_params' ] ?? [];
		$metaParams = \array_keys( $meta[ 'audit_params' ] ?? [] );

		if ( empty( $def ) && !empty( $metaParams ) ) {
			error_log( sprintf( 'WARNING: Event (%s) receives params but none are defined.', $event ) );
		}
		elseif ( !empty( $def ) ) {

			$missingParams = \array_diff( $def, $metaParams );
			if ( !empty( $missingParams ) ) {
				throw new \Exception( sprintf( "Event (%s) def has audit params that aren't present: %s", $event, \implode( ', ', $missingParams ) ) );
			}

			$extraMetaParams = \array_diff(
				$metaParams,
				\array_merge( $def, [ 'snapshot_discovery' ] ) // "dynamic" allowable meta params
			);
			if ( !empty( $extraMetaParams ) ) {
				// Previously we threw an exception. Now we just clean out the unwanted params.
				$meta[ 'audit_params' ] = \array_intersect_key( $meta[ 'audit_params' ], \array_flip( $def ) );
			}
		}
		return $meta;
	}

	/**
	 * @return array[]
	 */
	public function getEvents() :array {
		if ( empty( $this->aEvents ) ) {
			$events = [];
			foreach ( $this->con()->modules as $mod ) {
				$events = \array_merge(
					$events,
					\array_map(
						function ( $evt ) use ( $mod ) {
							$evt[ 'module' ] = $mod->cfg->slug;
							/** @deprecated 12.0 */
							$evt[ 'context' ] = $mod->cfg->slug;
							return $evt;
						},
						$mod->opts()->getEvents()
					)
				);
			}
			$this->aEvents = (array)apply_filters( 'shield/events/definitions', $this->buildEvents( $events ) );
			if ( empty( $this->aEvents ) ) {
				error_log( 'Shield events definitions is empty or not the correct format' );
			}
			\ksort( $this->aEvents );
		}

		try {
			// must come after $aEvents is defined.
			$custom = $this->buildCustomEvents();
		}
		catch ( \Exception $e ) {
			$custom = [];
		}

		return \array_merge( $this->aEvents, $custom );
	}

	/**
	 * @throws \Exception
	 */
	private function buildCustomEvents() :array {
		$custom = [];

		if ( $this->con()->isPremiumActive() ) {

			$events = apply_filters( 'shield/events/custom_definitions', [] );
			if ( !\is_array( $events ) ) {
				throw new \Exception( "custom events isn't an array. Please ensure to return only an array to this filter." );
			}

			$events = \array_filter( $events );
			foreach ( $events as $evtKey => $evtDef ) {
				if ( \is_numeric( $evtKey ) || !\is_string( $evtKey ) || !\preg_match( '#^custom_[a-z_]{1,43}$#', $evtKey ) ) {
					throw new \Exception( "All Custom Event Keys must be: string; lowercase; length: 10-50; prefixed with 'custom_'; only characters: a-z and underscore (_)" );
				}
				if ( !isset( $evtDef[ 'strings' ] ) || !\is_array( $evtDef[ 'strings' ] ) ) {
					throw new \Exception( "All Custom Events must supply an array of strings with key 'strings'." );
				}
				if ( empty( $evtDef[ 'strings' ][ 'name' ] ) || !\is_string( $evtDef[ 'strings' ][ 'name' ] ) ) {
					throw new \Exception( "All Custom Events must supply a 'name' as a string within the 'strings' array." );
				}
				if ( empty( $evtDef[ 'strings' ][ 'audit' ] ) || !\is_array( $evtDef[ 'strings' ][ 'audit' ] ) ) {
					throw new \Exception( "All Custom Events must supply an array of strings with key 'audit' to be displayed in the Activity Log to describe the event." );
				}

				// Clean out the audit strings to ensure type consistency later.
				\array_filter( $evtDef[ 'strings' ][ 'audit' ], function ( $auditString ) {
					return !empty( $auditString ) && \is_string( $auditString );
				} );
			}

			$custom = $this->buildEvents( $events );
		}

		return $custom;
	}

	public function getEventDef( string $eventKey ) :?array {
		return $this->getEvents()[ $eventKey ] ?? null;
	}

	public function getEventName( string $event ) :string {
		return $this->getEventStrings( $event )[ 'name' ] ?? '';
	}

	public function getEventAuditStrings( string $event ) :array {
		return $this->getEventStrings( $event )[ 'audit' ] ?? [];
	}

	public function getEventStrings( string $eventKey ) :array {
		$eventStrings = [];

		if ( $this->eventExists( $eventKey ) ) {
			$eventStrings = ( \strpos( $eventKey, 'custom_' ) === 0 ) ?
				$this->getEventDef( $eventKey )[ 'strings' ]
				: $this->con()
					   ->getModule( $this->getEventDef( $eventKey )[ 'module' ] )
					   ->getStrings()
					   ->getEventStrings()[ $eventKey ] ?? $eventStrings;
		}
		else {
			error_log( sprintf( 'An event %s does not exist.', $eventKey ) );
		}

		return $eventStrings;
	}

	/**
	 * @return string[]
	 */
	public function getEventNames() :array {
		return \array_map(
			function ( $event ) {
				return $this->getEventName( $event[ 'key' ] );
			},
			$this->getEvents()
		);
	}

	private function buildEvents( array $events ) :array {
		$defaults = [
			'level'              => 'notice', // events default at "notice" level
			'stat'               => true,
			'audit'              => true,
			'recent'             => false, // whether to show in the recent events logs
			'offense'            => false, // whether to mark offense against IP
			'suppress_offense'   => false, // events that normally trigger offense can be forcefully suppressed
			'audit_multiple'     => false, // allow multiple audit entries in the same request
			'audit_countable'    => false, // allow shortcut to audit trail to allow events to be counted
			'snapshot_discovery' => false, // event is capture through snapshot discovery
			'audit_params'       => [],
		];
		foreach ( $events as $eventKey => $evt ) {
			$events[ $eventKey ] = \array_merge( $defaults, $evt );
			$events[ $eventKey ][ 'key' ] = $eventKey;
		}
		return $events;
	}
}