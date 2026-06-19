<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Profiles;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class ProfileOptionsCatalog {

	use PluginControllerConsumer;

	private const EXCLUDED_KEYS = [
		'global_enable_plugin_features',
		'importexport_enable',
		'importexport_masterurl',
		'importexport_secretkey',
		'importexport_secretkey_expires_at',
		'importexport_handshake_expires_at',
		'import_id',
		'import_url_ids',
		'importexport_sites_migrated_at',
		'importexport_pending_network_invites',
		'importexport_network_invite_block_until',
		'xfer_excluded',
	];

	/**
	 * @return array<string,array>
	 */
	public function profileableOptions() :array {
		return \array_filter(
			self::con()->cfg->configuration->transferableOptions(),
			function ( array $option, string $key ) :bool {
				$key = (string)( $option[ 'key' ] ?? $key );
				return $key !== ''
					   && (string)( $option[ 'section' ] ?? '' ) !== 'section_hidden'
					   && !\in_array( $key, self::EXCLUDED_KEYS, true );
			},
			ARRAY_FILTER_USE_BOTH
		);
	}

	/**
	 * @return string[]
	 */
	public function profileableKeys() :array {
		return \array_keys( $this->profileableOptions() );
	}
}
