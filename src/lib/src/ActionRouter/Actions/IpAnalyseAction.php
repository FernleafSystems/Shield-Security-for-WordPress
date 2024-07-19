<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\BotSignalsRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules;
use FernleafSystems\Wordpress\Services\Utilities\Net\IpID;

class IpAnalyseAction extends BaseAction {

	public const SLUG = 'ip_analyse_action';

	protected function exec() {
		$ip = $this->action_data[ 'ip' ];

		try {
			[ $ipKey, $ipName ] = ( new IpID( $ip ) )->run();
			$validIP = true;
		}
		catch ( \Exception $e ) {
			$ipKey = IpID::UNKNOWN;
			$ipName = 'Unknown';
			$validIP = false;
		}

		$success = false;

		if ( !$validIP ) {
			$msg = __( "IP provided was invalid.", 'wp-simple-firewall' );
		}
		else {
			$ruleStatus = new IpRules\IpRuleStatus( $ip );

			switch ( $this->action_data[ 'ip_action' ] ) {

				case 'reset_offenses':
					try {
						$autoBlockIP = $ruleStatus->getRuleForAutoBlock();
						if ( empty( $autoBlockIP ) ) {
							throw new \Exception( "IP isn't on the auto block list." );
						}
						$success = ( new IpRules\DeleteRule() )->byRecord( $autoBlockIP );
						$msg = $success ? __( 'Offenses reset to zero.', 'wp-simple-firewall' )
							: __( "Offenses couldn't be reset at this time.", 'wp-simple-firewall' );
					}
					catch ( \Exception $e ) {
						$msg = $e->getMessage();
					}
					break;

				case 'block':
					try {
						if ( !\in_array( $ipKey, [ IpID::UNKNOWN, IpID::VISITOR ] ) ) {
							throw new \Exception( sprintf( __( "IP can't be blocked from this page as it's a known service IP: %s" ), $ipName ) );
						}
						( new IpRules\AddRule() )
							->setIP( $ip )
							->toManualBlacklist();
						$success = true;
						$msg = __( 'IP address blocked.', 'wp-simple-firewall' );
					}
					catch ( \Exception $e ) {
						$msg = $e->getMessage();
					}
					break;

				case 'unblock':
					foreach ( $ruleStatus->getRulesForBlock() as $record ) {
						$success = ( new IpRules\DeleteRule() )->byRecord( $record );
					}
					$msg = $success ? __( 'IP address unblocked.', 'wp-simple-firewall' )
						: __( "IP address couldn't be unblocked at this time.", 'wp-simple-firewall' );
					break;

				case 'bypass':
					try {
						( new IpRules\AddRule() )
							->setIP( $ip )
							->toManualWhitelist();
						$success = true;
						$msg = __( 'IP address added to Bypass list.', 'wp-simple-firewall' );
					}
					catch ( \Exception $e ) {
						$msg = $e->getMessage();
					}
					break;

				case 'unbypass':
					foreach ( $ruleStatus->getRulesForBypass() as $record ) {
						$success = ( new IpRules\DeleteRule() )->byRecord( $record );
					}
					$msg = $success ? __( 'IP address removed from Bypass list.', 'wp-simple-firewall' )
						: __( "IP address couldn't be removed from Bypass list at this time.", 'wp-simple-firewall' );
					break;

				case 'delete_notbot':
					$abRule = $ruleStatus->getRuleForAutoBlock();
					if ( !empty( $abRule ) ) {
						( new IpRules\DeleteRule() )->byRecord( $abRule );
					}
					$success = ( new BotSignalsRecord() )
						->setIP( $ip )
						->delete();
					$msg = $success ? __( 'IP silentCAPTCHA Score Reset.', 'wp-simple-firewall' )
						: __( "IP NotBot Score couldn't be reset at this time.", 'wp-simple-firewall' );
					break;

				default:
					$msg = __( 'Unsupported Action.', 'wp-simple-firewall' );
					break;
			}
		}

		$this->response()->action_response_data = [
			'success'     => $success,
			'message'     => $msg,
			'page_reload' => true,
		];
	}
}