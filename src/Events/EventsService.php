<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Events;

use FernleafSystems\Wordpress\Plugin\Shield\Logging\NormaliseLogLevel;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class EventsService {

	use PluginControllerConsumer;

	/**
	 * @var array[]
	 */
	private array $events;

	public function eventExists( string $eventKey ) :bool {
		return !empty( $this->getEventDef( $eventKey ) );
	}

	public function fireEvent( string $event, array $meta = [] ) {
		try {
			if ( !$this->eventExists( $event ) ) {
				throw new \Exception( sprintf( __( 'Event %s does not exist.', 'wp-simple-firewall' ), $event ) );
			}
			do_action(
				'shield/event',
				$event,
				$this->verifyAuditParams( $event, $meta ),
				$this->getEventDef( $event )
			);
		}
		catch ( \Exception $e ) {
//			error_log( $e->getMessage() );
		}
	}

	/**
	 * @throws \Exception
	 */
	private function verifyAuditParams( string $event, array $meta ) :array {
		$def = $this->getEventDef( $event )[ 'audit_params' ] ?? [];
		$metaParams = \array_keys( $meta[ 'audit_params' ] ?? [] );

		if ( empty( $def ) && !empty( $metaParams ) ) {
			error_log( sprintf( __( 'WARNING: Event (%s) receives params but none are defined.', 'wp-simple-firewall' ), $event ) );
		}
		elseif ( !empty( $def ) ) {

			$missingParams = \array_diff( $def, $metaParams );
			if ( !empty( $missingParams ) ) {
				/* translators: %1$s: event, %2$s: missing parameters */
				throw new \Exception( sprintf( __( 'Event (%1$s) definition has audit parameters that are not present: %2$s', 'wp-simple-firewall' ), $event, \implode( ', ', $missingParams ) ) );
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
		$this->events ??= $this->normaliseEventLevels(
			(array)apply_filters(
				'shield/events/definitions',
				$this->buildEvents( self::con()->cfg->configuration->events )
			)
		);
		if ( empty( $this->events ) ) {
			error_log( sprintf( __( '%s event definitions are empty or not in the correct format.', 'wp-simple-firewall' ), self::con()->labels->Name ) );
		}

		try {
			// must come after $this->events is defined.
			$custom = $this->buildCustomEvents();
		}
		catch ( \Exception $e ) {
			$custom = [];
		}

		return \array_merge( $this->events, $custom );
	}

	/**
	 * @throws \Exception
	 */
	private function buildCustomEvents() :array {
		$custom = [];

		if ( self::con()->isPremiumActive() ) {

			$events = apply_filters( 'shield/events/custom_definitions', [] );
			if ( !\is_array( $events ) ) {
				throw new \Exception( __( "Custom events must be provided as an array. Please ensure the filter returns only an array.", 'wp-simple-firewall' ) );
			}

			$events = \array_filter( $events );
			foreach ( $events as $evtKey => $evtDef ) {
				if ( \is_numeric( $evtKey ) || !\is_string( $evtKey ) || !\preg_match( '#^custom_[a-z_]{1,43}$#', $evtKey ) ) {
					throw new \Exception( __( "All custom event keys must be strings, lowercase, length 10-50, prefixed with 'custom_', and contain only letters and underscores.", 'wp-simple-firewall' ) );
				}
				if ( !isset( $evtDef[ 'strings' ] ) || !\is_array( $evtDef[ 'strings' ] ) ) {
					throw new \Exception( __( "All custom events must supply an array of strings with key 'strings'.", 'wp-simple-firewall' ) );
				}
				if ( empty( $evtDef[ 'strings' ][ 'name' ] ) || !\is_string( $evtDef[ 'strings' ][ 'name' ] ) ) {
					throw new \Exception( __( "All custom events must supply a 'name' as a string within the 'strings' array.", 'wp-simple-firewall' ) );
				}
				if ( empty( $evtDef[ 'strings' ][ 'audit' ] ) || !\is_array( $evtDef[ 'strings' ][ 'audit' ] ) ) {
					throw new \Exception( __( "All custom events must supply an array of strings with key 'audit' to be displayed in the Activity Log to describe the event.", 'wp-simple-firewall' ) );
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

	public function getEventStrings( string $evt ) :array {
		return $this->eventExists( $evt ) ?
			( \str_starts_with( $evt, 'custom_' ) ? $this->getEventDef( $evt )[ 'strings' ] : ( new EventStrings() )->for( $evt ) )
			: [];
	}

	/**
	 * @return string[]
	 */
	public function getEventNames() :array {
		return \array_map( fn( $evt ) => $this->getEventName( $evt[ 'key' ] ), $this->getEvents() );
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
			'snapshot_discovery' => false, // event is captured through snapshot discovery
			'audit_params'       => [],
			'data'               => [], // a general container for general event-specific-related data
		];
		foreach ( $events as $eventKey => $evt ) {
			$events[ $eventKey ] = \array_merge( $defaults, $evt );
			$events[ $eventKey ][ 'level' ] = NormaliseLogLevel::forEvent( (string)( $events[ $eventKey ][ 'level' ] ?? '' ) );
			$events[ $eventKey ][ 'key' ] = $eventKey;
		}
		return $events;
	}

	private function normaliseEventLevels( array $events ) :array {
		foreach ( $events as $eventKey => $event ) {
			if ( \is_array( $event ) ) {
				$events[ $eventKey ][ 'level' ] = NormaliseLogLevel::forEvent( (string)( $event[ 'level' ] ?? '' ) );
			}
		}
		return $events;
	}
}
