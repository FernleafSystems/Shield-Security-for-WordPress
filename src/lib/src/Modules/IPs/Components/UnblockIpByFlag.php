<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;
use FernleafSystems\Wordpress\Services\Services;

class UnblockIpByFlag extends Shield\Modules\Base\Common\ExecOnceModConsumer {

	use Shield\Modules\ModConsumer;

	protected function canRun() :bool {
		return !empty( Services::WpFs()->findFileInDir( 'unblock', $this->getCon()->paths->forFlag() ) );
	}

	protected function run() {
		/** @var IPs\ModCon $mod */
		$mod = $this->getMod();
		$FS = Services::WpFs();
		$srvIP = Services::IP();

		$IPs = [];

		$path = $FS->findFileInDir( 'unblock', $this->getCon()->paths->forFlag() );
		if ( !empty( $path ) && $FS->isFile( $path ) ) {
			$content = $FS->getFileContent( $path );
			if ( !empty( $content ) ) {

				foreach ( array_map( 'trim', explode( "\n", $content ) ) as $ip ) {
					$ruleStatus = ( new IpRuleStatus( $ip ) )->setMod( $this->getMod() );
					foreach ( $ruleStatus->getRulesForBlock() as $record ) {
						$removed = ( new IPs\Lib\IpRules\DeleteRule() )
							->setMod( $mod )
							->byRecord( $record );
						if ( $removed ) {
							$IPs[] = $ip;
							$this->getCon()->fireEvent( 'ip_unblock_flag', [ 'audit_params' => [ 'ip' => $ip ] ] );
						}
					}
				}
			}
			$FS->deleteFile( $path );
		}

		try {
			$myIP = $this->getCon()->this_req->ip;
			if ( !empty( $IPs ) && !empty( $myIP ) && $srvIP->checkIp( $myIP, $IPs ) ) {
				Services::Response()->redirectHere();
			}
		}
		catch ( \Exception $e ) {
		}
	}
}