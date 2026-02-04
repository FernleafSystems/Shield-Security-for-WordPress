<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\PluginImportExport_HandshakeConfirm;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\LoadIpRules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\URL;

class Export {

	use PluginControllerConsumer;

	public function run( string $method ) {
		try {
			switch ( $method ) {
				case 'json':
					$this->toJson();
					break;
				default:
					throw new \Exception();
			}
		}
		catch ( \Exception $e ) {
		}
		die();
	}

	public function toJson() :void {
		$ieCon = self::con()->comps->import_export;
		$req = Services::Request();

		$success = false;
		$data = [];

		$url = (string)Services::Data()->validateSimpleHttpUrl( (string)$req->query( 'url', '' ) );
		if ( !$this->verifyUrl( $url, (string)$req->query( 'id', '' ), (string)$req->query( 'secret', '' ) ) ) {
			$code = 3;
			$msg = __( 'Verification of import-origin failed.', 'wp-simple-firewall' );
		}
		else {
			$code = 0;
			$success = true;
			$data = $this->getExportData();
			$msg = 'Options Exported Successfully';

			self::con()->fireEvent(
				'options_exported',
				[ 'audit_params' => [ 'site' => $url ] ]
			);

			// Only setup the network if we have a valid URL
			$networkOpt = empty( $url ) ? false : $req->query( 'network', '' );

			if ( $networkOpt === 'Y' ) {
				$ieCon->addUrlToImportExportWhitelistUrls( $url );
				self::con()->fireEvent(
					'whitelist_site_added',
					[ 'audit_params' => [ 'site' => $url ] ]
				);
			}
			elseif ( !empty( $networkOpt ) ) {
				$ieCon->removeUrlFromImportExportWhitelistUrls( $url );
				self::con()->fireEvent(
					'whitelist_site_removed',
					[ 'audit_params' => [ 'site' => $url ] ]
				);
			}
		}

		/**
		 * Send a JSON error response with 403 to also help break caches.
		 */
		wp_send_json( [
			'success' => $success,
			'code'    => $code,
			'message' => $msg,
			'data'    => $data,
		], 403 );
		/** it dies within wp_send_json_error(); but just to make sure regardless */
		die();
	}

	/**
	 * @return string[]
	 */
	public function toStandardArray() :array {
		$export = \wp_json_encode( $this->getExportData() );
		return [
			'# Site URL: '.Services::WpGeneral()->getHomeUrl(),
			'# Export Date: '.Services::WpGeneral()->getTimeStringForDisplay(),
			'# Hash: '.\hash( 'sha1', $export ),
			$export
		];
	}

	public function toFile() :array {
		return [
			'name'    => sprintf( 'shieldexport-%s-%s.json',
				Services::Data()->urlStripSchema( Services::WpGeneral()->getHomeUrl() ),
				date( 'Ymd_His' )
			),
			'content' => \implode( "\n", $this->toStandardArray() )
		];
	}

	public function getExportData() :array {
		$all = [
			'site_url'      => Services::WpGeneral()->getHomeUrl(),
			'exported_at'   => Services::Request()->ts(),
			'exported_date' => Services::Request()->carbon( true )->toIso8601String(),
			'slug'          => 'wp-simple-firewall',
			'version'       => self::con()->cfg->version(),
			'options'       => $this->getRawOptionsExport(),
		];

		if ( apply_filters( 'shield/export_include_ip_rules', true ) ) {
			$loader = new LoadIpRules();
			$loader->wheres = [
				sprintf( "`ir`.`type`='%s'", self::con()->db_con->ip_rules::T_MANUAL_BYPASS ),
				"`ir`.`can_export`='1'"
			];
			$loader->limit = 100;

			$all[ 'ip_rules' ] = \array_map(
				function ( $rule ) {
					return [
						'ip'    => $rule->ipAsSubnetRange(),
						'label' => $rule->label,
						'type'  => $rule->type,
					];
				},
				$loader->select()
			);
		}

		return $all;
	}

	public function getFullTransferableOptionsExport() :array {
		$all = [];
		foreach ( self::con()->cfg->configuration->transferableOptions() as $optKey => $optDef ) {
			$all[ $optKey ] = self::con()->opts->optGet( $optKey );
		}
		return $all;
	}

	/**
	 * Removes any options marked as to be excluded from import/export
	 */
	public function getRawOptionsExport() :array {
		return \array_diff_key( $this->getFullTransferableOptionsExport(), \array_flip( self::con()->comps->opts_lookup->getXferExcluded() ) );
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
		$urlIDs = self::con()->opts->optGet( 'import_url_ids' );

		$verified = !empty( $url ) &&
					(
						self::con()->comps->import_export->verifySecretKey( $secret )
						|| ( !empty( $id ) && ( $urlIDs[ \hash( 'md5', $url ) ] ?? '' ) === $id )
						|| ( $this->isUrlOnWhitelist( $url ) && $this->handshake( $url ) )
					);

		// Update the stored ID, so it can be used at a later date.
		if ( $verified && !empty( $id ) ) {
			$urlIDs[ \hash( 'md5', $url ) ] = $id;
			self::con()
				->opts
				->optSet( 'import_url_ids', $urlIDs )
				->store();
		}

		return $verified;
	}

	/**
	 * @return string[]
	 */
	public function getImportExportWhitelist() :array {
		return self::con()->opts->optGet( 'importexport_whitelist' );
	}

	private function isUrlOnWhitelist( string $url ) :bool {
		$isWhitelisted = false;
		$urlComponents = $this->parseURL( $url );
		if ( !empty( $urlComponents[ 'host' ] ) ) {

			$whiteURLs = \array_map(
				function ( $whitelistedURL ) {
					return $this->parseURL( $whitelistedURL );
				},
				self::con()->comps->import_export->getImportExportWhitelist()
			);

			foreach ( $whiteURLs as $whiteURL ) {
				if ( $whiteURL[ 'host' ] === $urlComponents[ 'host' ] && $whiteURL[ 'path' ] === $urlComponents[ 'path' ] ) {
					$isWhitelisted = true;
					break;
				}
			}
		}

		return $isWhitelisted;
	}

	/**
	 * @return array{host:string, path:string}
	 */
	private function parseURL( string $url ) :array {
		$components = [
			'host' => '',
			'path' => '',
		];
		$parsed = wp_parse_url( $url );
		if ( !empty( $parsed ) ) {
			$components[ 'host' ] = empty( $parsed[ 'host' ] ) ? '' : $parsed[ 'host' ];
			$components[ 'path' ] = empty( $parsed[ 'path' ] ) ? '' : \trim( $parsed[ 'path' ], '/' );
		}
		return $components;
	}

	private function handshake( string $url ) :bool {
		$raw = Services::HttpRequest()->getContent(
			URL::Build( $url, ActionData::Build( PluginImportExport_HandshakeConfirm::class, false, [], true ) )
		);
		$dec = @\json_decode( $raw, true );
		return \is_array( $dec ) && isset( $dec[ 'success' ] ) && ( $dec[ 'success' ] === true );
	}
}