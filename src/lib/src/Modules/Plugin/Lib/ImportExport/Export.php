<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;
use FernleafSystems\Wordpress\Services\Services;

class Export {

	use ModConsumer;

	/**
	 * @param string $sMethod
	 */
	public function run( $sMethod ) {
		try {
			switch ( $sMethod ) {
				case 'file':
					$this->toFile();
					break;

				case 'json':
				default:
					$this->toJson();
					break;
			}
		}
		catch ( \Exception $oE ) {
		}
		die();
	}

	public function toJson() {
		/** @var \ICWP_WPSF_FeatureHandler_Plugin $oMod */
		$oMod = $this->getMod();
		$oReq = Services::Request();

		$sSecretKey = $oReq->query( 'secret', '' );

		$sNetworkOpt = $oReq->query( 'network', '' );
		$bDoNetwork = !empty( $sNetworkOpt );
		$sUrl = Services::Data()->validateSimpleHttpUrl( $oReq->query( 'url', '' ) );

		if ( !$oMod->isImportExportSecretKey( $sSecretKey ) && !$this->isUrlOnWhitelist( $sUrl ) ) {
			return; // we show no signs of responding to invalid secret keys or unwhitelisted URLs
		}

		$bSuccess = false;
		$aData = [];

		if ( !$this->verifyUrlWithHandshake( $sUrl ) ) {
			$nCode = 3;
			$sMessage = __( 'Handshake verification failed.', 'wp-simple-firewall' );
		}
		else {
			$nCode = 0;
			$bSuccess = true;
			$aData = $this->getExportData();
			$sMessage = 'Options Exported Successfully';

			$this->getCon()->fireEvent(
				'options_exported',
				[ 'audit' => [ 'site' => $sUrl ] ]
			);

			if ( $bDoNetwork ) {
				if ( $sNetworkOpt === 'Y' ) {
					$oMod->addUrlToImportExportWhitelistUrls( $sUrl );
					$this->getCon()->fireEvent(
						'whitelist_site_added',
						[ 'audit' => [ 'site' => $sUrl ] ]
					);
				}
				else {
					$oMod->removeUrlFromImportExportWhitelistUrls( $sUrl );
					$this->getCon()->fireEvent(
						'whitelist_site_removed',
						[ 'audit' => [ 'site' => $sUrl ] ]
					);
				}
			}
		}

		$aResponse = [
			'success' => $bSuccess,
			'code'    => $nCode,
			'message' => $sMessage,
			'data'    => $aData,
		];
		echo json_encode( $aResponse );
		die();
	}

	public function toFile() {
		$sExport = json_encode( $this->getExportData() );
		$aData = [
			'# Site URL: '.Services::WpGeneral()->getHomeUrl(),
			'# Export Date: '.Services::WpGeneral()->getTimeStringForDisplay(),
			'# Hash: '.sha1( $sExport ),
			$sExport
		];
		Services::Response()->downloadStringAsFile(
			implode( "\n", $aData ),
			sprintf( 'shieldexport-%s-%s.json',
				Services::Data()->urlStripSchema( Services::WpGeneral()->getHomeUrl() ),
				$sFilename = date( 'Ymd_His' )
			)
		);
	}

	/**
	 * @return array
	 */
	private function getExportData() {
		$aD = apply_filters( $this->getCon()->prefix( 'gather_options_for_export' ), [] );
		return is_array( $aD ) ? $aD : [];
	}

	/**
	 * @param string $sUrl
	 * @return bool
	 */
	private function isUrlOnWhitelist( $sUrl ) {
		/** @var Plugin\Options $oOpts */
		$oOpts = $this->getOptions();
		return !empty( $sUrl ) && in_array( $sUrl, $oOpts->getImportExportWhitelist() );
	}

	/**
	 * @param string $sUrl
	 * @return bool
	 */
	private function verifyUrlWithHandshake( $sUrl ) {
		$bVerified = false;

		if ( !empty( $sUrl ) ) {
			$sReqUrl = add_query_arg( [ 'shield_action' => 'importexport_handshake' ], $sUrl );
			$aResp = @json_decode( Services::HttpRequest()->getContent( $sReqUrl ), true );
			$bVerified = is_array( $aResp ) && isset( $aResp[ 'success' ] ) && ( $aResp[ 'success' ] === true );
		}

		return $bVerified;
	}
}
