<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Decisions\Scopes\V2;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Exceptions\DecisionsStreamDataIntegrityFailedException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @property int $minimum_expires_at
 * @property int $timer_start
 */
abstract class ProcessBase extends DynPropertiesClass {

	use PluginControllerConsumer;

	public const SCOPE = '';

	protected array $newDecisions = [];

	protected array $deletedDecisions = [];

	/**
	 * @return mixed
	 */
	public function __get( string $key ) {

		$value = parent::__get( $key );

		switch ( $key ) {
			case 'minimum_expires_at':
				$value = (int)\max( 0, $value );
				break;
			default:
				break;
		}

		return $value;
	}

	/**
	 * @throws DecisionsStreamDataIntegrityFailedException
	 */
	public function run( array $stream ) {
		$this->preRun();

		$this->timer_start = \microtime( true );
		if ( isset( $stream[ 'new' ] ) && !\is_array( $stream[ 'new' ] ) ) {
			throw new DecisionsStreamDataIntegrityFailedException( "Decisions Stream 'new' data wasn't of the correct format: array" );
		}
		if ( isset( $stream[ 'deleted' ] ) && !\is_array( $stream[ 'deleted' ] ) ) {
			throw new DecisionsStreamDataIntegrityFailedException( "Decisions Stream 'deleted' data wasn't of the correct format: array" );
		}

		$this->deletedDecisions = $this->extractScopeDecisionData_Deleted( $stream[ 'deleted' ] ?? [] );
		$this->newDecisions = $this->extractScopeDecisionData_New( $stream[ 'new' ] ?? [] );
		unset( $stream );

		$deletedCount = empty( $this->deletedDecisions ) ? 0 : $this->processDeleted();
		$newCount = empty( $this->newDecisions ) ? 0 : $this->processNew();

		if ( $newCount > 0 || $deletedCount > 0 ) {
			self::con()->comps->events->fireEvent( 'crowdsec_decisions_acquired', [
				'audit_params' => [
					'count_new'     => $newCount,
					'count_deleted' => $deletedCount,
					'scope'         => static::SCOPE,
					'time_taken'    => round( microtime( true ) - $this->timer_start ),
				]
			] );
		}

		$this->postRun();
	}

	protected function preRun() {
	}

	protected function postRun() {
	}

	abstract protected function processDeleted() :int;

	abstract protected function processNew() :int;

	abstract protected function extractScopeDecisionData_New( array $rawDecisionsGroups ) :array;

	protected function extractScopeDecisionData_Deleted( array $decisions ) :array {
		return $this->extractScopeDecisionData_New( $decisions );
	}

	protected function removeDuplicatesFromNewStream() {
	}

	/**
	 * @throws \Exception
	 */
	protected function getDecisionExpiresAt( array $decision ) :int {
		if ( empty( $decision[ 'duration' ] ) ) {
			throw new \Exception( "Decision doesn't contain a 'duration'" );
		}
		if ( !\is_string( $decision[ 'duration' ] ) ) {
			throw new \Exception( sprintf( "Decision duration not of the correct type (string): %s", $decision[ 'duration' ] ) );
		}
		if ( !preg_match( '#^(\d+)([a-z])$#i', $decision[ 'duration' ], $matches ) ) {
			throw new \Exception( sprintf( "Decision duration not of the correct format (123h): %s", $decision[ 'duration' ] ) );
		}

		$carbon = Services::Request()->carbon();
		switch ( $matches[ 2 ] ) {
			case 'h':
				$expiresAt = $carbon->startOfHour()->addHours( (int)$matches[ 1 ] )->timestamp;
				break;
			default:
				throw new \Exception( sprintf( "Unsupported decision format notation: '%s'", $matches[ 2 ] ) );
		}

		if ( $expiresAt < $this->minimum_expires_at ) {
			throw new \Exception( sprintf( 'We only accept the data with "expires at" greater than %s.', $this->minimum_expires_at ) );
		}

		return $expiresAt;
	}

	/**
	 * @throws \Exception
	 */
	protected function getDecisionValuesFromGroup( array $decision ) :array {
		if ( empty( $decision[ 'scope' ] ) ) {
			throw new \Exception( 'Empty decision scope' );
		}
		if ( $decision[ 'scope' ] !== static::SCOPE ) {
			throw new \Exception( "Unsupported decision scope: ".$decision[ 'scope' ] );
		}
		if ( empty( $decision[ 'decisions' ] ) || !\is_array( $decision[ 'decisions' ] ) ) {
			throw new \Exception( 'There are no decisions in group.' );
		}

		return \array_filter( \array_map(
			function ( array $d ) {
				try {
					if ( empty( $d[ 'value' ] ) ) {
						throw new \Exception( 'No value set' );
					}
					if ( empty( $d[ 'duration' ] ) ) {
						throw new \Exception( 'No duration set' );
					}
					$d[ 'value' ] = $this->normaliseDecisionValue( $d[ 'value' ] );
				}
				catch ( \Exception $e ) {
					$d = null;
				}
				return $d;
			},
			\array_filter( $decision[ 'decisions' ], fn( $d ) => !empty( $d ) && \is_array( $d ) )
		) );
	}

	/**
	 * @return mixed
	 * @throws \Exception
	 */
	protected function getDecisionValue( array $decision ) {
		if ( !isset( $decision[ 'value' ] ) ) {
			throw new \Exception( 'No decision value set.' );
		}
		$normalisedValue = $this->normaliseDecisionValue( $decision[ 'value' ] );
		// simple verification of data we're going to import
		if ( !$this->validateDecisionValue( $normalisedValue ) ) {
			throw new \Exception( sprintf( 'Invalid decision value for scope (%s) provided: %s', static::SCOPE, $decision[ 'value' ] ) );
		}

		return $normalisedValue;
	}

	/**
	 * @param mixed $value
	 * @return mixed
	 */
	abstract protected function normaliseDecisionValue( $value );

	/**
	 * @param mixed $value
	 * @return bool
	 */
	abstract protected function validateDecisionValue( $value ) :bool;
}