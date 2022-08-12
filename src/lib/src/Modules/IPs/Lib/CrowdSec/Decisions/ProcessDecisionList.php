<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Decisions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\CleanIpRules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\CrowdSecConstants;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Ops\AddIP;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class ProcessDecisionList {

	use ModConsumer;

	/**
	 * The stream is provided by Api\DecisionsDownload and ensures keys 'new' and 'deleted' are present.
	 */
	public function run( array $stream ) {
		$this->preRun();

		$deleted = is_array( $stream[ 'deleted' ] ) ? $this->delete( $stream[ 'deleted' ] ) : 0;
		$new = is_array( $stream[ 'new' ] ) ? $this->add( $stream[ 'new' ] ) : 0;

		if ( !empty( $new ) || !empty( $deleted ) ) {
			$this->getCon()->fireEvent( 'crowdsec_decisions_acquired', [
				'audit_params' => [
					'count_new'     => $new,
					'count_deleted' => $deleted,
				]
			] );
		}

		$this->postRun();
	}

	public function preRun() {
		( new CleanIpRules() )
			->setMod( $this->getMod() )
			->execute();
	}

	public function postRun() {
		$this->preRun();
	}

	private function add( array $decisions ) :int {
		// We only handle "ip" scope right now, but we can add more as we need to.
		return $this->addForScope_IP( $this->extractDataFromDecisionsForScope_IP( $decisions ) );
	}

	public function addForScope_IP( array $ipList ) :int {
		$count = 0;
		foreach ( $ipList as $ipToAdd ) {
			try {
				( new AddIP() )
					->setMod( $this->getMod() )
					->setIP( $ipToAdd )
					->toCrowdsecBlocklist();
				$count++;
			}
			catch ( \Exception $e ) {
			}
		}
		return $count;
	}

	private function delete( array $decisions ) :int {
		// We only handle "ip" scopes right now, but we can add more as we need to.
		return ( new CleanIpRules() )
			->setMod( $this->getMod() )
			->ipList( $this->extractDataFromDecisionsForScope_IP( $decisions ) );
	}

	private function extractDataFromDecisionsForScope_IP( array $decisions ) :array {
		return array_filter( array_map(
			function ( $decision ) {
				$ip = null;
				if ( is_array( $decision ) ) {
					try {
						$ip = $this->getValueFromDecision( $decision, CrowdSecConstants::SCOPE_IP );
					}
					catch ( \Exception $e ) {
					}
				}
				return $ip;
			},
			$decisions
		) );
	}

	/**
	 * @throws \Exception
	 */
	private function getValueFromDecision( array $decision, string $scope ) :string {
		$srvIP = Services::IP();
		if ( empty( $decision[ 'scope' ] ) ) {
			throw new \Exception( 'Empty decision scope' );
		}
		if ( $decision[ 'scope' ] !== $scope ) {
			throw new \Exception( "Unsupported decision scope (i.e. not 'ip'): ".$decision[ 'scope' ] );
		}
		if ( empty( $decision[ 'value' ] ) ) {
			throw new \Exception( 'Empty decision value' );
		}

		$value = trim( (string)$decision[ 'value' ] );

		// simple verification of data we're going to import
		if ( $scope === CrowdSecConstants::SCOPE_IP && !$srvIP->isValidIp_PublicRemote( $value ) ) {
			throw new \Exception( 'Invalid decision value for scope (IP) provided: '.$value );
		}
		// elseif $scope === another support scope

		return $value;
	}
}