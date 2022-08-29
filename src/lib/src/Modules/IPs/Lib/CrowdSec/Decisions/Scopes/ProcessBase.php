<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Decisions\Scopes;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Exceptions\DecisionsStreamDataIntegrityFailedException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

abstract class ProcessBase {

	use ModConsumer;

	const SCOPE = '';

	/**
	 * @var array
	 */
	protected $newDecisions;

	/**
	 * @var array
	 */
	protected $deletedDecisions;

	/**
	 * @throws DecisionsStreamDataIntegrityFailedException
	 */
	public function __construct( array $stream ) {
		if ( isset( $stream[ 'new' ] ) && !is_array( $stream[ 'new' ] ) ) {
			throw new DecisionsStreamDataIntegrityFailedException( "Decisions Stream 'new' data wasn't of the correct format: array" );
		}
		if ( isset( $stream[ 'deleted' ] ) && !is_array( $stream[ 'deleted' ] ) ) {
			throw new DecisionsStreamDataIntegrityFailedException( "Decisions Stream 'deleted' data wasn't of the correct format: array" );
		}

		$this->newDecisions = $this->extractScopeDecisionData( $stream[ 'new' ] ?? [] );
		$this->deletedDecisions = $this->extractScopeDecisionData( $stream [ 'deleted' ] ?? [] );
	}

	/**
	 * @return void
	 */
	public function run() {
		$this->preRun();

		$deleted = $this->runForDeleted();
		$new = $this->runForNew();

		if ( !empty( $new ) || !empty( $deleted ) ) {
			$this->getCon()->fireEvent( 'crowdsec_decisions_acquired', [
				'audit_params' => [
					'count_new'     => $new,
					'count_deleted' => $deleted,
					'scope'         => static::SCOPE,
				]
			] );
		}

		$this->postRun();
	}

	public function preRun() {
	}

	public function postRun() {
	}

	abstract protected function runForDeleted() :int;

	abstract protected function runForNew() :int;

	abstract protected function extractScopeDecisionData( array $decisions ) :array;

	/**
	 * @return mixed
	 * @throws \Exception
	 */
	protected function getValueFromDecision( array $decision ) {
		if ( empty( $decision[ 'scope' ] ) ) {
			throw new \Exception( 'Empty decision scope' );
		}
		if ( $decision[ 'scope' ] !== static::SCOPE ) {
			throw new \Exception( "Unsupported decision scope: ".$decision[ 'scope' ] );
		}
		if ( !isset( $decision[ 'value' ] ) ) {
			throw new \Exception( 'No decision value set.' );
		}

		$value = $this->normaliseDecisionValue( $decision[ 'value' ] );

		// simple verification of data we're going to import
		if ( $this->verifyDecisionValue( $value ) ) {
			throw new \Exception( sprintf( 'Invalid decision value for scope (%s) provided: %s', static::SCOPE, $value ) );
		}

		return $value;
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
	abstract protected function verifyDecisionValue( $value ) :bool;
}