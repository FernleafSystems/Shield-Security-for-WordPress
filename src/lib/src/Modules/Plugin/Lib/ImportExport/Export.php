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
		catch ( \Exception $e ) {
		}
		die();
	}

	public function toJson() {
		/** @var Plugin\ModCon $mod */
		$mod = $this->getMod();
		$req = Services::Request();

		$sSecretKey = $req->query( 'secret', '' );

		$sNetworkOpt = $req->query( 'network', '' );
		$bDoNetwork = !empty( $sNetworkOpt );
		$sUrl = Services::Data()->validateSimpleHttpUrl( $req->query( 'url', '' ) );

		if ( !$mod->isImportExportSecretKey( $sSecretKey ) && !$this->isUrlOnWhitelist( $sUrl ) ) {
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
					$mod->addUrlToImportExportWhitelistUrls( $sUrl );
					$this->getCon()->fireEvent(
						'whitelist_site_added',
						[ 'audit' => [ 'site' => $sUrl ] ]
					);
				}
				else {
					$mod->removeUrlFromImportExportWhitelistUrls( $sUrl );
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

	/**
	 * @return string[]
	 */
	public function toStandardArray() {
		$sExport = json_encode( $this->getExportData() );
		return [
			'# Site URL: '.Services::WpGeneral()->getHomeUrl(),
			'# Export Date: '.Services::WpGeneral()->getTimeStringForDisplay(),
			'# Hash: '.sha1( $sExport ),
			$sExport
		];
	}

	public function toFile() {
		$aData = $this->toStandardArray();
		Services::Response()->downloadStringAsFile(
			implode( "\n", $aData ),
			sprintf( 'shieldexport-%s-%s.json',
				Services::Data()->urlStripSchema( Services::WpGeneral()->getHomeUrl() ),
				date( 'Ymd_His' )
			)
		);
	}

	/**
	 * @return array
	 */
	private function getExportData() {
		$aAll = [];
		foreach ( $this->getCon()->modules as $mod ) {
			$oOpts = $mod->getOptions();
			$aAll[ $mod->getOptionsStorageKey() ] = array_diff_key(
				$oOpts->getTransferableOptions(),
				array_flip( $oOpts->getXferExcluded() )
			);
		}
		return $aAll;
	}

	/**
	 * @param string $url
	 * @return bool
	 */
	private function isUrlOnWhitelist( $url ) {
		/** @var Plugin\Options $opts */
		$opts = $this->getOptions();
		return !empty( $url ) && in_array( $url, $opts->getImportExportWhitelist() );
	}

	/**
	 * @param string $url
	 * @return bool
	 */
	private function verifyUrlWithHandshake( $url ) {
		$bVerified = false;

		if ( !empty( $url ) ) {
			$sReqUrl = add_query_arg( [ 'shield_action' => 'importexport_handshake' ], $url );
			$aResp = @json_decode( Services::HttpRequest()->getContent( $sReqUrl ), true );
			$bVerified = is_array( $aResp ) && isset( $aResp[ 'success' ] ) && ( $aResp[ 'success' ] === true );
		}

		return $bVerified;
	}
}
