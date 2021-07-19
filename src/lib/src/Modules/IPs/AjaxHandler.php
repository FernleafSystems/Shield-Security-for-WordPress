<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Lib\Request\FormParams;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Ops;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Net\IpID;

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

			case 'not_bot':
				$response = $this->ajaxExec_CaptureNotBot();
				break;

			default:
				$response = parent::processAjaxAction( $action );
		}

		return $response;
	}

	protected function processNonAuthAjaxAction( string $action ) :array {

		switch ( $action ) {
			case 'not_bot':
				$response = $this->ajaxExec_CaptureNotBot();
				break;
			default:
				$response = parent::processNonAuthAjaxAction( $action );
		}

		return $response;
	}

	private function ajaxExec_CaptureNotBot() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		return [
			'success' => $mod->getBotSignalsController()
							 ->getHandlerNotBot()
							 ->registerAsNotBot()
		];
	}

	private function ajaxExec_AddIp() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$srvIP = Services::IP();

		$formParams = FormParams::Retrieve();

		$success = false;
		$msg = __( "IP address wasn't added to the list", 'wp-simple-firewall' );

		$ip = preg_replace( '#[^/:.a-f\d]#i', '', ( $formParams[ 'ip' ] ?? '' ) );
		$list = $formParams[ 'list' ] ?? '';

		$acceptableIP = $srvIP->isValidIp( $ip )
						 || $srvIP->isValidIp4Range( $ip )
						 || $srvIP->isValidIp6Range( $ip );

		$isBlackList = $list != $mod::LIST_MANUAL_WHITE;

		// TODO: Bring this IP verification out of here and make it more accessible
		if ( empty( $ip ) ) {
			$msg = __( "IP address not provided", 'wp-simple-firewall' );
		}
		elseif ( empty( $list ) ) {
			$msg = __( "IP list not provided", 'wp-simple-firewall' );
		}
		elseif ( !$acceptableIP ) {
			$msg = __( "IP address isn't either a valid IP or a CIDR range", 'wp-simple-firewall' );
		}
		elseif ( $isBlackList && !$mod->isPremium() ) {
			$msg = __( "Please upgrade to Pro if you'd like to add IPs to the black list manually.", 'wp-simple-firewall' );
		}
		elseif ( $isBlackList && $srvIP->checkIp( $srvIP->getRequestIp(), $ip ) ) {
			$msg = __( "Manually black listing your current IP address is not supported.", 'wp-simple-firewall' );
		}
		elseif ( $isBlackList && in_array( $ip, Services::IP()->getServerPublicIPs() ) ) {
			$msg = __( "This IP is reserved and can't be blacklisted.", 'wp-simple-firewall' );
		}
		else {
			$label = $formParams[ 'label' ] ?? '';
			$IP = null;
			switch ( $list ) {
				case $mod::LIST_MANUAL_WHITE:
					try {
						$IP = ( new Shield\Modules\IPs\Lib\Ops\AddIp() )
							->setMod( $mod )
							->setIP( $ip )
							->toManualWhitelist( (string)$label );
					}
					catch ( \Exception $e ) {
					}
					break;

				case $mod::LIST_MANUAL_BLACK:
					try {
						$IP = ( new Shield\Modules\IPs\Lib\Ops\AddIp() )
							->setMod( $mod )
							->setIP( $ip )
							->toManualBlacklist( (string)$label );
					}
					catch ( \Exception $e ) {
					}
					break;

				default:
					break;
			}

			if ( !empty( $IP ) ) {
				$msg = __( 'IP address added successfully', 'wp-simple-firewall' );
				$success = true;
			}
		}

		return [
			'success' => $success,
			'message' => $msg,
		];
	}

	private function ajaxExec_IpDelete() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$success = false;
		$ID = (int)Services::Request()->post( 'rid', -1 );

		if ( $ID < 0 ) {
			$msg = __( 'Invalid entry selected', 'wp-simple-firewall' );
		}
		else {
			/** @var Shield\Databases\IPs\EntryVO $IP */
			$IP = $mod->getDbHandler_IPs()
					  ->getQuerySelector()
					  ->byId( $ID );
			if ( $IP instanceof Shield\Databases\IPs\EntryVO ) {
				$del = ( new Ops\DeleteIp() )
					->setMod( $this->getMod() )
					->setIP( $IP->ip );
				$success = ( $IP->list == $mod::LIST_MANUAL_WHITE ) ?
					$del->fromWhiteList() : $del->fromBlacklist();
			}
			$msg = $success ? __( 'IP address deleted', 'wp-simple-firewall' )
				: __( "IP address wasn't deleted from the list", 'wp-simple-firewall' );
		}

		return [
			'success' => $success,
			'message' => $msg,
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

		try {
			list( $ipKey, $ipName ) = ( new IpID( $ip ) )->run();
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
		elseif ( !in_array( $ipKey, [ IpID::UNKNOWN, IpID::VISITOR ] ) ) {
			$msg = sprintf( __( "IP can't be processed from this page as it's a known service IP: %s" ), $ipName );
		}
		else {
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
						->setMod( $this->getMod() )
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
						->setMod( $this->getMod() )
						->setIP( $ip )
						->fromWhiteList();
					$msg = $success ? __( 'IP address removed from Bypass list.', 'wp-simple-firewall' )
						: __( "IP address couldn't be removed from Bypass list at this time.", 'wp-simple-firewall' );
					break;

				case 'delete_notbot':
					( new Ops\DeleteIp() )
						->setMod( $this->getMod() )
						->setIP( $ip )
						->fromBlacklist();
					$success = ( new Lib\Bots\BotSignalsRecord() )
						->setMod( $this->getMod() )
						->setIP( $ip )
						->delete();
					$msg = $success ? __( 'IP NotBot Score Reset.', 'wp-simple-firewall' )
						: __( "IP NotBot Score couldn't be reset at this time.", 'wp-simple-firewall' );
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