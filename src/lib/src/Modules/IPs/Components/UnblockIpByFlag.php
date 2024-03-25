<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\DeleteRule;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class UnblockIpByFlag {

	use ExecOnce;
	use PluginControllerConsumer;

	protected function canRun() :bool {
		return !empty( Services::WpFs()->findFileInDir( 'unblock', self::con()->paths->forFlag() ) );
	}

	protected function run() {
		$FS = Services::WpFs();

		$IPs = [];

		$path = $FS->findFileInDir( 'unblock', self::con()->paths->forFlag() );
		if ( !empty( $path ) && $FS->isAccessibleFile( $path ) ) {
			$content = $FS->getFileContent( $path );
			if ( !empty( $content ) ) {
				foreach ( \array_map( '\trim', \explode( "\n", $content ) ) as $ip ) {
					if ( Services::IP()->isValidIp( $ip ) ) {
						foreach ( ( new IpRuleStatus( $ip ) )->getRulesForBlock() as $record ) {
							$removed = ( new DeleteRule() )->byRecord( $record );
							if ( $removed ) {
								$IPs[] = $ip;
								self::con()->fireEvent( 'ip_unblock_flag', [ 'audit_params' => [ 'ip' => $ip ] ] );
							}
						}
					}
				}
			}
			$FS->deleteFile( $path );
		}

		try {
			$myIP = self::con()->this_req->ip;
			if ( !empty( $IPs ) && !empty( $myIP ) && Services::IP()->IpIn( $myIP, $IPs ) ) {
				Services::Response()->redirectHere();
			}
		}
		catch ( \Exception $e ) {
		}
	}
}