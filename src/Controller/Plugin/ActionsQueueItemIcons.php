<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin;

class ActionsQueueItemIcons {

	private const ICON_TOKEN_CLASSES = [
		'archive'                 => 'bi bi-archive-fill',
		'brush'                   => 'bi bi-palette-fill',
		'bug'                     => 'bi bi-bug-fill',
		'code-slash'              => 'bi bi-code-slash',
		'database-fill-lock'      => 'bi bi-database-fill-lock',
		'file-lock2'              => 'bi bi-file-lock2-fill',
		'key-fill'                => 'bi bi-key-fill',
		'person-fill-exclamation' => 'bi bi-person-fill-exclamation',
		'plug'                    => 'bi bi-plug-fill',
		'shield-exclamation'      => 'bi bi-shield-exclamation',
		'shield-lock-fill'        => 'bi bi-shield-lock-fill',
		'wordpress'               => 'bi bi-wordpress',
		'wrench'                  => 'bi bi-wrench',
	];

	private const SCAN_ICONS = [
		'wordpress'       => 'wordpress',
		'plugins'         => 'plug',
		'themes'          => 'brush',
		'vulnerabilities' => 'shield-exclamation',
		'malware'         => 'bug',
		'file_locker'     => 'file-lock2',
	];

	private const SCAN_ROW_ICON_OVERRIDES = [
		'vulnerable_assets' => 'shield-exclamation',
		'abandoned'         => 'archive',
	];

	private const MAINTENANCE_ICONS = [
		'wp_updates'             => 'wordpress',
		'default_admin_user'     => 'person-fill-exclamation',
		'wp_plugins_updates'     => 'plug',
		'wp_plugins_inactive'    => 'plug',
		'wp_themes_updates'      => 'brush',
		'wp_themes_inactive'     => 'brush',
		'system_ssl_certificate' => 'shield-lock-fill',
		'system_php_version'     => 'code-slash',
		'system_lib_openssl'     => 'key-fill',
		'wp_db_password'         => 'database-fill-lock',
	];

	public function iconClassForKey( string $key ) :string {
		return $this->iconClassForIcon( $this->iconForKey( $key ) );
	}

	public function iconClassForScanKey( string $scanKey ) :string {
		return $this->iconClassForIcon( $this->iconForScanKey( $scanKey ) );
	}

	public function iconForKey( string $key ) :string {
		if ( isset( self::SCAN_ICONS[ $key ] ) ) {
			return $this->iconForScanKey( $key );
		}

		$scanDefinition = PluginNavs::actionsLandingScanDefinitionForSummaryKey( $key );
		if ( $scanDefinition !== null ) {
			return self::SCAN_ROW_ICON_OVERRIDES[ $key ] ?? $this->iconForScanKey( $scanDefinition[ 'slug' ] );
		}

		return self::MAINTENANCE_ICONS[ $key ] ?? 'wrench';
	}

	public function iconForScanKey( string $scanKey ) :string {
		return self::SCAN_ICONS[ $scanKey ] ?? 'wrench';
	}

	private function iconClassForIcon( string $icon ) :string {
		return self::ICON_TOKEN_CLASSES[ $icon ] ?? 'bi bi-wrench';
	}
}
