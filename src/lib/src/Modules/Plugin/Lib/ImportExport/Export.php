<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\PluginImportExport_HandshakeConfirm;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\URL;

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

		$success = false;
		$data = [];

		$url = (string)Services::Data()->validateSimpleHttpUrl( (string)$req->query( 'url', '' ) );
		if ( !$this->verifyUrl( $url, (string)$req->query( 'id', '' ), (string)$req->query( 'secret', '' ) ) ) {
			$code = 3;
			$msg = __( 'Verification failed.', 'wp-simple-firewall' );
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

	public function toFile() :array {
		return [
			'name'    => sprintf( 'shieldexport-%s-%s.json',
				Services::Data()->urlStripSchema( Services::WpGeneral()->getHomeUrl() ),
				date( 'Ymd_His' )
			),
			'content' => implode( "\n", $this->toStandardArray() )
		];
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
	 * 2022-10-27:
	 * There is real issue with some sites being able to perform automated import and export. So we want to simplify
	 * this so that if the URL handshake doesn't work, we can fallback to an ID lookup. The one issue here is that we
	 * accept the ID if it's the first time see this URL. However, at this stage, the requesting URL has either already
	 * been added to the "whitelist" or they're sending the correct secret key.
	 *
	 * So you're verified if:
	 * - You're on the whitelist and your ID is valid, OR you can handshake
	 * - You're not on the whitelist AND your secret is valid AND ( ID is valid OR you can handshake ).
	 */
	private function verifyUrl( string $url, string $id, string $secret ) :bool {
		/** @var Plugin\ModCon $mod */
		$mod = $this->getMod();

		$urlIDs = $this->getOptions()->getOpt( 'import_url_ids' );
		if ( !is_array( $urlIDs ) ) {
			$urlIDs = [];
		}

		$verified = !empty( $url ) &&
					(
						$mod->getImpExpController()->verifySecretKey( $secret )
						|| ( !empty( $id ) && ( $urlIDs[ md5( $url ) ] ?? '' ) === $id )
						|| ( $this->isUrlOnWhitelist( $url ) && $this->handshake( $url ) )
					);

		// Update the stored ID, so it can be used at a later date.
		if ( $verified && !empty( $id ) ) {
			$urlIDs[ md5( $url ) ] = $id;
			$this->getOptions()->setOpt( 'import_url_ids', $urlIDs );
			$this->getMod()->saveModOptions();
		}

		return $verified;
	}

	private function isUrlOnWhitelist( string $url ) :bool {
		/** @var Plugin\Options $opts */
		$opts = $this->getOptions();
		return !empty( $url ) && in_array( $url, $opts->getImportExportWhitelist() );
	}

	private function handshake( string $url ) :bool {
		$reqURL = URL::Build( $url, [ 'shield_action' => 'importexport_handshake' ] );
		$dec = @json_decode( Services::HttpRequest()->getContent( $reqURL ), true );
		return is_array( $dec ) && isset( $dec[ 'success' ] ) && ( $dec[ 'success' ] === true );
	}
}
