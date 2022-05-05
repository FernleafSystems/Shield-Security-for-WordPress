<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;
use FernleafSystems\Wordpress\Services\Services;

class Export {

	use ModConsumer;

	public function run( string $method ) {
		try {
			switch ( $method ) {
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
		$ieCon = $mod->getImpExpController();
		$req = Services::Request();

		$url = Services::Data()->validateSimpleHttpUrl( (string)$req->query( 'url', '' ) );
		if ( !$ieCon->verifySecretKey( (string)$req->query( 'secret', '' ) ) && !$this->isUrlOnWhitelist( $url ) ) {
			return; // we show no signs of responding to invalid secret keys or unwhitelisted URLs
		}

		$success = false;
		$data = [];

		if ( !$this->verifyUrlWithHandshake( $url ) ) {
			$code = 3;
			$msg = __( 'Handshake verification failed.', 'wp-simple-firewall' );
		}
		else {
			$code = 0;
			$success = true;
			$data = $this->getExportData();
			$msg = 'Options Exported Successfully';

			$this->getCon()->fireEvent(
				'options_exported',
				[ 'audit_params' => [ 'site' => $url ] ]
			);

			// Only setup the network if we have a valid URL
			$networkOpt = empty( $url ) ? false : $req->query( 'network', '' );

			if ( $networkOpt === 'Y' ) {
				$ieCon->addUrlToImportExportWhitelistUrls( $url );
				$this->getCon()->fireEvent(
					'whitelist_site_added',
					[ 'audit_params' => [ 'site' => $url ] ]
				);
			}
			elseif ( !empty( $networkOpt ) ) {
				$ieCon->removeUrlFromImportExportWhitelistUrls( $url );
				$this->getCon()->fireEvent(
					'whitelist_site_removed',
					[ 'audit_params' => [ 'site' => $url ] ]
				);
			}
		}

		echo json_encode( [
			'success' => $success,
			'code'    => $code,
			'message' => $msg,
			'data'    => $data,
		] );
		die();
	}

	/**
	 * @return string[]
	 */
	public function toStandardArray() :array {
		$export = json_encode( $this->getExportData() );
		return [
			'# Site URL: '.Services::WpGeneral()->getHomeUrl(),
			'# Export Date: '.Services::WpGeneral()->getTimeStringForDisplay(),
			'# Hash: '.sha1( $export ),
			$export
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

	public function getExportData() :array {
		$all = [];
		foreach ( $this->getRawOptionsExport() as $modSlug => $modOptions ) {
			$mod = $this->getCon()->modules[ $modSlug ];
			$all[ $mod->getOptionsStorageKey() ] = $modOptions;
		}
		return $all;
	}

	public function getRawOptionsExport( bool $filterExcluded = true ) :array {
		$all = [];
		foreach ( $this->getCon()->modules as $mod ) {
			$opts = $mod->getOptions();
			$xfr = $opts->getTransferableOptions();
			if ( $filterExcluded ) {
				$xfr = array_diff_key(
					$xfr,
					array_flip( $opts->getXferExcluded() )
				);
			}
			$all[ $mod->getSlug() ] = $xfr;
		}
		return $all;
	}

	/**
	 * @param string $url
	 */
	private function isUrlOnWhitelist( $url ) :bool {
		/** @var Plugin\Options $opts */
		$opts = $this->getOptions();
		return !empty( $url ) && in_array( $url, $opts->getImportExportWhitelist() );
	}

	/**
	 * @param string $url
	 * @return bool
	 */
	private function verifyUrlWithHandshake( $url ) :bool {
		$bVerified = false;

		if ( !empty( $url ) ) {
			$sReqUrl = add_query_arg( [ 'shield_action' => 'importexport_handshake' ], $url );
			$aResp = @json_decode( Services::HttpRequest()->getContent( $sReqUrl ), true );
			$bVerified = is_array( $aResp ) && isset( $aResp[ 'success' ] ) && ( $aResp[ 'success' ] === true );
		}

		return $bVerified;
	}
}
