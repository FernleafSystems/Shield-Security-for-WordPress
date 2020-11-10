<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Ops;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Net\IpIdentify;

class AjaxHandler extends Shield\Modules\BaseShield\AjaxHandler {

	protected function processAjaxAction( string $action ) :array {

		switch ( $action ) {
			case 'ip_insert':
				$response = $this->ajaxExec_AddIp();
				break;

			case 'ip_delete':
				$response = $this->ajaxExec_IpDelete();
				break;

			case 'render_table_ip':
				$response = $this->ajaxExec_BuildTableIps();
				break;

			case 'build_ip_analyse':
				$response = $this->ajaxExec_BuildIpAnalyse();
				break;

			case 'ip_analyse_action':
				$response = $this->ajaxExec_IpAnalyseAction();
				break;

			default:
				$response = parent::processAjaxAction( $action );
		}

		return $response;
	}

	private function ajaxExec_AddIp() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$oIpServ = Services::IP();

		$aFormParams = $this->getAjaxFormParams();

		$bSuccess = false;
		$sMessage = __( "IP address wasn't added to the list", 'wp-simple-firewall' );

		$ip = preg_replace( '#[^/:.a-f\d]#i', '', ( isset( $aFormParams[ 'ip' ] ) ? $aFormParams[ 'ip' ] : '' ) );
		$sList = isset( $aFormParams[ 'list' ] ) ? $aFormParams[ 'list' ] : '';

		$bAcceptableIp = $oIpServ->isValidIp( $ip )
						 || $oIpServ->isValidIp4Range( $ip )
						 || $oIpServ->isValidIp6Range( $ip );

		$bIsBlackList = $sList != $mod::LIST_MANUAL_WHITE;

		// TODO: Bring this IP verification out of here and make it more accessible
		if ( empty( $ip ) ) {
			$sMessage = __( "IP address not provided", 'wp-simple-firewall' );
		}
		elseif ( empty( $sList ) ) {
			$sMessage = __( "IP list not provided", 'wp-simple-firewall' );
		}
		elseif ( !$bAcceptableIp ) {
			$sMessage = __( "IP address isn't either a valid IP or a CIDR range", 'wp-simple-firewall' );
		}
		elseif ( $bIsBlackList && !$mod->isPremium() ) {
			$sMessage = __( "Please upgrade to Pro if you'd like to add IPs to the black list manually.", 'wp-simple-firewall' );
		}
		elseif ( $bIsBlackList && $oIpServ->checkIp( $oIpServ->getRequestIp(), $ip ) ) {
			$sMessage = __( "Manually black listing your current IP address is not supported.", 'wp-simple-firewall' );
		}
		elseif ( $bIsBlackList && in_array( $ip, Services::IP()->getServerPublicIPs() ) ) {
			$sMessage = __( "This IP is reserved and can't be blacklisted.", 'wp-simple-firewall' );
		}
		else {
			$label = $aFormParams[ 'label' ] ?? '';
			$oIP = null;
			switch ( $sList ) {
				case $mod::LIST_MANUAL_WHITE:
					try {
						$oIP = ( new Shield\Modules\IPs\Lib\Ops\AddIp() )
							->setMod( $mod )
							->setIP( $ip )
							->toManualWhitelist( (string)$label );
					}
					catch ( \Exception $oE ) {
					}
					break;

				case $mod::LIST_MANUAL_BLACK:
					try {
						$oIP = ( new Shield\Modules\IPs\Lib\Ops\AddIp() )
							->setMod( $mod )
							->setIP( $ip )
							->toManualBlacklist( (string)$label );
					}
					catch ( \Exception $oE ) {
					}
					break;

				default:
					break;
			}

			if ( !empty( $oIP ) ) {
				$sMessage = __( 'IP address added successfully', 'wp-simple-firewall' );
				$bSuccess = true;
			}
		}

		return [
			'success' => $bSuccess,
			'message' => $sMessage,
		];
	}

	private function ajaxExec_IpDelete() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$bSuccess = false;
		$nId = Services::Request()->post( 'rid', -1 );

		if ( !is_numeric( $nId ) || $nId < 0 ) {
			$sMessage = __( 'Invalid entry selected', 'wp-simple-firewall' );
		}
		elseif ( $mod->getDbHandler_IPs()->getQueryDeleter()->deleteById( $nId ) ) {
			$sMessage = __( 'IP address deleted', 'wp-simple-firewall' );
			$bSuccess = true;
		}
		else {
			$sMessage = __( "IP address wasn't deleted from the list", 'wp-simple-firewall' );
		}

		return [
			'success' => $bSuccess,
			'message' => $sMessage,
		];
	}

	private function ajaxExec_BuildTableIps() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$dbh = $mod->getDbHandler_IPs();
		$dbh->autoCleanDb();

		return [
			'success' => true,
			'html'    => ( new Shield\Tables\Build\Ip() )
				->setMod( $mod )
				->setDbHandler( $dbh )
				->render()
		];
	}

	private function ajaxExec_IpAnalyseAction() :array {
		$req = Services::Request();

		$ip = $req->post( 'ip' );

		$ipIdentifier = new IpIdentify( $ip );
		try {
			$ipID = $ipIdentifier->run();
			$ipKey = key( $ipID );
			$validIP = true;
		}
		catch ( \Exception $e ) {
			$ipKey = IpIdentify::UNKNOWN;
			$validIP = false;
		}

		$success = false;

		if ( $ipKey !== IpIdentify::UNKNOWN ) {
			$msg = sprintf( __( "IP can't be processed from this page as it's a known service IP: %s" ), $ipIdentifier->getName( $ipKey ) );
		}
		elseif ( !$validIP ) {
			$msg = __( "IP provided was invalid.", 'wp-simple-firewall' );
		}
		else {
			$dbh = $this->getCon()->getModule_IPs()->getDbHandler_IPs();
			switch ( $req->post( 'ip_action' ) ) {

				case 'block':
					try {
						$success = ( new Ops\AddIp() )
									   ->setMod( $this->getMod() )
									   ->setIP( $ip )
									   ->toManualBlacklist() instanceof Shield\Databases\IPs\EntryVO;
					}
					catch ( \Exception $e ) {
					}
					$msg = $success ? __( 'IP address blocked.', 'wp-simple-firewall' )
						: __( "IP address couldn't be blocked at this time.", 'wp-simple-firewall' );
					break;

				case 'unblock':
					$success = ( new Ops\DeleteIp() )
						->setDbHandler( $dbh )
						->setIP( $ip )
						->fromBlacklist();
					$msg = $success ? __( 'IP address unblocked.', 'wp-simple-firewall' )
						: __( "IP address couldn't be unblocked at this time.", 'wp-simple-firewall' );
					break;

				case 'bypass':
					try {
						$success = ( new Ops\AddIp() )
									   ->setMod( $this->getMod() )
									   ->setIP( $ip )
									   ->toManualWhitelist() instanceof Shield\Databases\IPs\EntryVO;
					}
					catch ( \Exception $e ) {
					}
					$msg = $success ? __( 'IP address added to Bypass list.', 'wp-simple-firewall' )
						: __( "IP address couldn't be added to Bypass list at this time.", 'wp-simple-firewall' );
					break;

				case 'unbypass':
					$success = ( new Ops\DeleteIp() )
						->setDbHandler( $dbh )
						->setIP( $ip )
						->fromWhiteList();
					$msg = $success ? __( 'IP address removed from Bypass list.', 'wp-simple-firewall' )
						: __( "IP address couldn't be removed from Bypass list at this time.", 'wp-simple-firewall' );
					break;

				default:
					$msg = __( 'Unsupported Action.', 'wp-simple-firewall' );
					break;
			}
		}

		return [
			'success'     => $success,
			'message'     => $msg,
			'page_reload' => true,
		];
	}

	private function ajaxExec_BuildIpAnalyse() :array {
		try {
			$ip = Services::Request()->post( 'fIp', '' );
			$response = ( new Shield\Modules\IPs\Lib\IpAnalyse\BuildDisplay() )
				->setMod( $this->getMod() )
				->setIP( $ip )
				->run();

			$msg = '';
			$success = true;
		}
		catch ( \Exception $e ) {
			$msg = $e->getMessage();
			$success = false;
			$response = $msg;
		}

		return [
			'success' => $success,
			'message' => $msg,
			'html'    => $response,
		];
	}
}