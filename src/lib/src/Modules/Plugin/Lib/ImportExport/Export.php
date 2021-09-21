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
		$url = Services::Data()->validateSimpleHttpUrl( $req->query( 'url', '' ) );

		if ( !$mod->isImportExportSecretKey( $sSecretKey ) && !$this->isUrlOnWhitelist( $url ) ) {
			return; // we show no signs of responding to invalid secret keys or unwhitelisted URLs
		}

		$bSuccess = false;
		$aData = [];

		if ( !$this->verifyUrlWithHandshake( $url ) ) {
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
				[ 'audit_params' => [ 'site' => $url ] ]
			);

			if ( $bDoNetwork ) {
				if ( $sNetworkOpt === 'Y' ) {
					$mod->addUrlToImportExportWhitelistUrls( $url );
					$this->getCon()->fireEvent(
						'whitelist_site_added',
						[ 'audit_params' => [ 'site' => $url ] ]
					);
				}
				else {
					$mod->removeUrlFromImportExportWhitelistUrls( $url );
					$this->getCon()->fireEvent(
						'whitelist_site_removed',
						[ 'audit_params' => [ 'site' => $url ] ]
					);
				}
			}
		}

		echo json_encode( [
			'success' => $bSuccess,
			'code'    => $nCode,
			'message' => $sMessage,
			'data'    => $aData,
		] );
		die();
	}

	/**
	 * @return string[]
	 */
	public function toStandardArray() :array{
		$sExport = json_encode( $this->getExportData() );
		return [
			'# Site URL: '.Services::WpGeneral()->getHomeUrl(),
			'# Export Date: '.Services::WpGeneral()->getTimeStringForDisplay(),
			'# Hash: '.sha1( $sExport ),
			$sExport
		];
	}

	public function toFile() {
		Services::Response()->downloadStringAsFile(
			implode( "\n", $this->toStandardArray() ),
			sprintf( 'shieldexport-%s-%s.json',
				Services::Data()->urlStripSchema( Services::WpGeneral()->getHomeUrl() ),
				date( 'Ymd_His' )
			)
		);
	}

	private function getExportData() :array{
		$all = [];
		foreach ( $this->getCon()->modules as $mod ) {
			$oOpts = $mod->getOptions();
			$all[ $mod->getOptionsStorageKey() ] = array_diff_key(
				$oOpts->getTransferableOptions(),
				array_flip( $oOpts->getXferExcluded() )
			);
		}
		return $all;
	}

	/**
	 * @param string $url
	 * @return bool
	 */
	private function isUrlOnWhitelist( $url ) :bool{
		/** @var Plugin\Options $opts */
		$opts = $this->getOptions();
		return !empty( $url ) && in_array( $url, $opts->getImportExportWhitelist() );
	}

	/**
	 * @param string $url
	 * @return bool
	 */
	private function verifyUrlWithHandshake( $url ):bool {
		$bVerified = false;

		if ( !empty( $url ) ) {
			$sReqUrl = add_query_arg( [ 'shield_action' => 'importexport_handshake' ], $url );
			$aResp = @json_decode( Services::HttpRequest()->getContent( $sReqUrl ), true );
			$bVerified = is_array( $aResp ) && isset( $aResp[ 'success' ] ) && ( $aResp[ 'success' ] === true );
		}

		return $bVerified;
	}
}
