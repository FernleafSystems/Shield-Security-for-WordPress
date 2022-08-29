<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Decisions\Scopes;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\CleanIpRules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\LoadIpRules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\Ops\Handler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\CrowdSecConstants;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\AddRule;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\DeleteRule;
use FernleafSystems\Wordpress\Services\Services;
use IPLib\Factory;
use IPLib\Range\RangeInterface;

class ProcessIPs extends ProcessBase {

	const SCOPE = CrowdSecConstants::SCOPE_IP;

	public function preRun() {
		( new CleanIpRules() )
			->setMod( $this->getMod() )
			->execute();
	}

	public function postRun() {
		$this->preRun();
	}

	protected function runForNew() :int {
		$count = 0;
		foreach ( $this->newDecisions as $ipToAdd ) {
			try {
				( new AddRule() )
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

	protected function runForDeleted() :int {
		$count = 0;

		$ipsToDelete = $this->deletedDecisions;

		/** @var RangeInterface[] $ipsToDelete */
		$ipsToDelete = array_filter( array_map( 'trim', array_filter( $ipsToDelete ) ), function ( $ip ) {
			return Factory::parseRangeString( $ip );
		} );

		$loader = ( new LoadIpRules() )->setMod( $this->getMod() );
		$loader->wheres = [
			sprintf( "`ir`.`type`='%s'", Handler::T_CROWDSEC )
		];

		foreach ( $loader->select() as $record ) {
			$recordAsRange = Factory::parseRangeString( $record->ipAsSubnetRange() );
			foreach ( $ipsToDelete as $ipRange ) {
				if ( $ipRange->containsRange( $recordAsRange ) ) {
					( new DeleteRule() )
						->setMod( $this->getMod() )
						->byRecord( $record );
					$count++;
				}
			}
		}
		return $count;
	}

	protected function extractScopeDecisionData( array $decisions ) :array {
		return array_filter( array_map(
			function ( $decision ) {
				$ip = null;
				if ( is_array( $decision ) ) {
					try {
						$ip = $this->getValueFromDecision( $decision );
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
	 * @param mixed $value
	 * @return mixed
	 */
	protected function normaliseDecisionValue( $value ) {
		return trim( (string)$value );
	}

	/**
	 * @inheritDoc
	 */
	protected function verifyDecisionValue( $value ) :bool {
		return Services::IP()->isValidIp_PublicRemote( $value );
	}
}