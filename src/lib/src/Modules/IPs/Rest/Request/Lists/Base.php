<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Rest\Request\Lists;

use FernleafSystems\Wordpress\Plugin\Core\Rest\Exceptions\ApiException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Rest\Request\Process;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\IpRuleRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;

abstract class Base extends Process {

	/**
	 * @throws ApiException
	 */
	protected function getIpData( string $ip, string $list ) :array {
		$ruleStatus = new IpRuleStatus( $ip );

		if ( $list === 'block' ) {
			$IP = $ruleStatus->getRuleForAutoBlock();
			if ( empty( $IP ) ) {
				$IP = \current( $ruleStatus->getRulesForManualBlock() );
			}
		}
		elseif ( $list === 'bypass' ) {
			$IP = \current( $ruleStatus->getRulesForBypass() );
		}
		else {
			$IP = \current( $ruleStatus->getRulesForCrowdsec() );
		}

		if ( empty( $IP ) ) {
			throw new ApiException( 'IP address not found on list' );
		}

		return $this->convertIpRuleToArray( $IP );
	}

	protected function convertIpRuleToArray( IpRuleRecord $record ) :array {
		$data = \array_intersect_key(
			$record->getRawData(),
			\array_flip( [
				'label',
				'type',
				'offenses',
				'blocked_at',
				'unblocked_at',
			] )
		);
		$data[ 'ip' ] = $record->ipAsSubnetRange();
		return $data;
	}
}