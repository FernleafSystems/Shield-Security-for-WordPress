<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class AjaxHandler extends Shield\Modules\Base\AjaxHandlerShield {

	/**
	 * @param string $sAction
	 * @return array
	 */
	protected function processAjaxAction( $sAction ) {

		switch ( $sAction ) {
			case 'ip_insert':
				$aResponse = $this->ajaxExec_AddIp();
				break;

			case 'ip_delete':
				$aResponse = $this->ajaxExec_IpDelete();
				break;

			case 'render_table_ip':
				$aResponse = $this->ajaxExec_BuildTableIps();
				break;

			default:
				$aResponse = parent::processAjaxAction( $sAction );
		}

		return $aResponse;
	}

	/**
	 * @return array
	 */
	private function ajaxExec_AddIp() {
		/** @var \ICWP_WPSF_FeatureHandler_Ips $oMod */
		$oMod = $this->getMod();
		/** @var Options $oOpts */
		$oOpts = $this->getOptions();
		/** @var \ICWP_WPSF_Processor_Ips $oProcessor */
		$oProcessor = $oMod->getProcessor();
		$oIpServ = Services::IP();

		$aFormParams = $this->getAjaxFormParams();

		$bSuccess = false;
		$sMessage = __( "IP address wasn't added to the list", 'wp-simple-firewall' );

		$sIp = preg_replace( '#[^/:.a-f\d]#i', '', ( isset( $aFormParams[ 'ip' ] ) ? $aFormParams[ 'ip' ] : '' ) );
		$sList = isset( $aFormParams[ 'list' ] ) ? $aFormParams[ 'list' ] : '';

		$bAcceptableIp = $oIpServ->isValidIp( $sIp ) || $oIpServ->isValidIp4Range( $sIp );

		$bIsBlackList = $sList != $oMod::LIST_MANUAL_WHITE;

		// TODO: Bring this IP verification out of here and make it more accessible
		if ( empty( $sIp ) ) {
			$sMessage = __( "IP address not provided", 'wp-simple-firewall' );
		}
		else if ( empty( $sList ) ) {
			$sMessage = __( "IP list not provided", 'wp-simple-firewall' );
		}
		else if ( !$bAcceptableIp ) {
			$sMessage = __( "IP address isn't either a valid IP or a CIDR range", 'wp-simple-firewall' );
		}
		else if ( $bIsBlackList && !$oMod->isPremium() ) {
			$sMessage = __( "Please upgrade to Pro if you'd like to add IPs to the black list manually.", 'wp-simple-firewall' );
		}
		else if ( $bIsBlackList && $oIpServ->isValidIp4Range( $sIp ) ) { // TODO
			$sMessage = __( "IP ranges aren't currently supported for blacklisting.", 'wp-simple-firewall' );
		}
		else if ( $bIsBlackList && $oIpServ->checkIp( $sIp, $oIpServ->getRequestIp() ) ) {
			$sMessage = __( "Manually black listing your current IP address is not supported.", 'wp-simple-firewall' );
		}
		else if ( $bIsBlackList && in_array( $sIp, $oMod->getReservedIps() ) ) {
			$sMessage = __( "This IP is reserved and can't be blacklisted.", 'wp-simple-firewall' );
		}
		else {
			$sLabel = isset( $aFormParams[ 'label' ] ) ? $aFormParams[ 'label' ] : '';
			switch ( $sList ) {

				case $oMod::LIST_MANUAL_WHITE:
					$oIp = $oProcessor->addIpToWhiteList( $sIp, $sLabel );
					break;

				case $oMod::LIST_MANUAL_BLACK:
					$oIp = $oProcessor->addIpToBlackList( $sIp, $sLabel );
					if ( !empty( $oIp ) ) {
						/** @var Shield\Databases\IPs\Update $oUpd */
						$oUpd = $oMod->getDbHandler_IPs()->getQueryUpdater();
						$oUpd->updateTransgressions( $oIp, $oOpts->getOffenseLimit() );
					}
					break;

				default:
					$oIp = null;
					break;
			}

			if ( !empty( $oIp ) ) {
				$sMessage = __( 'IP address added successfully', 'wp-simple-firewall' );
				$bSuccess = true;
			}
		}

		return [
			'success' => $bSuccess,
			'message' => $sMessage,
		];
	}

	/**
	 * @return array
	 */
	private function ajaxExec_IpDelete() {
		/** @var \ICWP_WPSF_FeatureHandler_Ips $oMod */
		$oMod = $this->getMod();
		$bSuccess = false;
		$nId = Services::Request()->post( 'rid', -1 );

		if ( !is_numeric( $nId ) || $nId < 0 ) {
			$sMessage = __( 'Invalid entry selected', 'wp-simple-firewall' );
		}
		else if ( $oMod->getDbHandler_IPs()->getQueryDeleter()->deleteById( $nId ) ) {
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

	/**
	 * @return array
	 */
	private function ajaxExec_BuildTableIps() {
		/** @var \ICWP_WPSF_FeatureHandler_Ips $oMod */
		$oMod = $this->getMod();

		$oDbH = $oMod->getDbHandler_IPs();
		$oDbH->autoCleanDb();

		$oTableBuilder = ( new Shield\Tables\Build\Ip() )
			->setMod( $oMod )
			->setDbHandler( $oDbH );

		return [
			'success' => true,
			'html'    => $oTableBuilder->buildTable()
		];
	}
}