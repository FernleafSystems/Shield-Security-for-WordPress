<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Lib\Request\FormParams;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpAnalyse\FindAllPluginIps;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Ops;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Net\IpID;

class AjaxHandler extends Shield\Modules\BaseShield\AjaxHandler {

	protected function getAjaxActionCallbackMap( bool $isAuth ) :array {
		$map = array_merge( parent::getAjaxActionCallbackMap( $isAuth ), [
			'not_bot' => [ $this, 'ajaxExec_CaptureNotBot' ],
		] );
		if ( $isAuth ) {
			$map = array_merge( $map, [
				'ip_insert'          => [ $this, 'ajaxExec_AddIp' ],
				'ip_delete'          => [ $this, 'ajaxExec_IpDelete' ],
				'render_table_ip'    => [ $this, 'ajaxExec_BuildTableIps' ],
				'ip_analyse_build'   => [ $this, 'ajaxExec_BuildIpAnalyse' ],
				'ip_analyse_action'  => [ $this, 'ajaxExec_IpAnalyseAction' ],
				'ip_review_select'   => [ $this, 'ajaxExec_IpReviewSelect' ],
				'render_ip_analysis' => [ $this, 'ajaxExec_RenderIpAnalysis' ],
			] );
		}
		return $map;
	}

	public function ajaxExec_CaptureNotBot() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		return [
			'success' => $mod->getBotSignalsController()
							 ->getHandlerNotBot()
							 ->registerAsNotBot()
		];
	}

	public function ajaxExec_AddIp() :array {
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
			$label = (string)$formParams[ 'label' ] ?? '';
			$IP = null;
			switch ( $list ) {
				case $mod::LIST_MANUAL_WHITE:
					try {
						$IP = ( new Shield\Modules\IPs\Lib\Ops\AddIp() )
							->setMod( $mod )
							->setIP( $ip )
							->toManualWhitelist( $label );
					}
					catch ( \Exception $e ) {
					}
					break;

				case $mod::LIST_MANUAL_BLACK:
					try {
						$IP = ( new Shield\Modules\IPs\Lib\Ops\AddIp() )
							->setMod( $mod )
							->setIP( $ip )
							->toManualBlacklist( $label );
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

	public function ajaxExec_IpDelete() :array {
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

	public function ajaxExec_BuildTableIps() :array {
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

	public function ajaxExec_IpReviewSelect() :array {
		$req = Services::Request();

		$filter = preg_replace( '#[^\da-f:.]#', '', strtolower( (string)$req->post( 'search' ) ) );
		$ips = ( new FindAllPluginIps() )
			->setCon( $this->getCon() )
			->run( $filter );

		return [
			'success'     => true,
			'ips'         => array_map( function ( $ip ) {
				return [
					'id'   => $ip,
					'text' => $ip
				];
			}, $ips ),
			'message'     => '',
			'page_reload' => false,
		];
	}

	public function ajaxExec_RenderIpAnalysis() :array {
		$data = [
			'success' => false,
			'title'   => __( "Couldn't Build IP Analysis", 'wp-simple-firewall' ),
			'body'    => __( "Couldn't Build IP Analysis", 'wp-simple-firewall' ),
		];
		try {
			$ip = Services::Request()->post( 'ip', '' );
			$data[ 'title' ] = sprintf( '%s: %s', __( 'IP Analysis', 'wp-simple-firewall' ), $ip );
			$data[ 'body' ] = ( new Shield\Modules\IPs\Lib\IpAnalyse\BuildDisplay() )
				->setMod( $this->getMod() )
				->setIP( $ip )
				->run();
		}
		catch ( \Exception $e ) {
			$data[ 'body' ] = $e->getMessage();
		}
		return $data;
	}

	public function ajaxExec_IpAnalyseAction() :array {
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
						$msg = $success ? __( 'IP address blocked.', 'wp-simple-firewall' )
							: __( "IP address couldn't be blocked at this time.", 'wp-simple-firewall' );
					}
					catch ( \Exception $e ) {
						$msg = $e->getMessage();
					}
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

	public function ajaxExec_BuildIpAnalyse() :array {
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