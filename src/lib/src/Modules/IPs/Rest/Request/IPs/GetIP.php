<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Rest\Request\IPs;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\BotSignalsRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\Calculator\CalculateVisitorBotScores;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Strings;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Reputation\GetIPReputation;
use FernleafSystems\Wordpress\Services\Services;

class GetIP extends Base {

	protected function process() :array {
		return [
			'ip_list' => $this->getIpListInfo(),
			'not_bot' => $this->getNotBotInfo(),
		];
	}

	private function getNotBotInfo() :array {
		/** @var RequestVO $req */
		$req = $this->getRequestVO();
		/** @var Strings $strings */
		$strings = $this->mod()->getStrings();
		$names = $strings->getBotSignalNames();

		try {
			$record = ( new BotSignalsRecord() )
				->setIP( $req->ip )
				->retrieve();
		}
		catch ( \Exception $e ) {
			$record = null;
		}

		$signals = [];
		$scoreCalc = ( new CalculateVisitorBotScores() )->setIP( $req->ip );
		foreach ( $scoreCalc->scores() as $scoreKey => $scoreValue ) {
			$column = $scoreKey.'_at';
			if ( $scoreValue !== 0 ) {
				if ( empty( $record ) || empty( $record->{$column} ) ) {
					if ( \in_array( $scoreKey, [ 'known', 'created' ] ) ) {
						$signals[ $scoreKey ] = __( 'N/A', 'wp-simple-firewall' );
					}
					else {
						$signals[ $scoreKey ] = __( 'Never Recorded', 'wp-simple-firewall' );
					}
				}
				else {
					$signals[ $scoreKey ] = Services::Request()
													->carbon()
													->setTimestamp( $record->{$column} )->diffForHumans();
				}
			}
		}

		$signalsAsHuman = [];
		foreach ( $signals as $signal => $signalTime ) {
			$signalsAsHuman[ $names[ $signal ] ] = $signalTime;
		}
		\ksort( $signalsAsHuman );

		return [
			'human_probability' => $scoreCalc->probability(),
			'score_local'       => $scoreCalc->total(),
			'score_shieldnet'   => ( new GetIPReputation() )
									   ->setIP( $req->ip )
									   ->retrieve()[ 'reputation_score' ] ?? '-',
			'signals'           => $signalsAsHuman,
		];
	}

	private function getIpListInfo() :array {
		/** @var RequestVO $req */
		$req = $this->getRequestVO();

		$ruleStatus = new IpRuleStatus( $req->ip );
		if ( $ruleStatus->isBypass() ) {
			$ip = \current( $ruleStatus->getRulesForBypass() );
		}
		elseif ( $ruleStatus->isBlocked() ) {
			$ip = \current( $ruleStatus->getRulesForBlock() );
		}
		elseif ( $ruleStatus->isAutoBlacklisted() ) {
			$ip = $ruleStatus->getRuleForAutoBlock();
		}

		$info = [];
		if ( !empty( $ip ) ) {
			$dbh = self::con()->db_con->dbhIPRules();
			$info = [
				'type'           => $dbh::GetTypeName( $ip->type ),
				'offenses'       => $ruleStatus->getOffenses(),
				'label'          => $ip->label,
				'blocked_at'     => $ip->blocked_at,
				'unblocked_at'   => $ip->unblocked_at,
				'last_access_at' => $ip->last_access_at,
				'list'           => $dbh::GetTypeName( $ip->type ),
			];
		}

		return $info;
	}
}