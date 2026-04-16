<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\Login\TwoFactor\Import;

use Base32\Base32;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class WordfenceLoginSecurityBridge implements SupplierBridgeInterface {

	use PluginControllerConsumer;

	public const ACTIVE_PLUGIN_FILE_CORE = 'wordfence/wordfence.php';
	public const ACTIVE_PLUGIN_FILE_STANDALONE = 'wordfence-login-security/wordfence-login-security.php';
	public const TABLE_2FA_SECRETS = 'wfls_2fa_secrets';
	public const OPT_UNAVAILABLE = 'mfa_import_wordfence_ls_unavailable';

	private const MODE_AUTHENTICATOR = 'authenticator';
	private const RECOVERY_CODE_BYTES = 8;

	public function getSupplierSlug() :string {
		return 'wordfence_login_security';
	}

	public function isApplicable() :bool {
		$isActive = $this->isSourcePluginActive();
		if ( $isActive ) {
			if ( $this->isMarkedUnavailable() ) {
				$this->markUnavailable( false );
			}
		}
		return !$isActive;
	}

	public function discoverForUser( \WP_User $user ) :SupplierFactorData {
		$data = new SupplierFactorData();
		if ( $this->isMarkedUnavailable() ) {
			return $data;
		}

		if ( !$this->doesSecretsTableExist() ) {
			$this->markUnavailable();
			return $data;
		}

		global $wpdb;
		if ( !( $wpdb instanceof \wpdb ) ) {
			return $data;
		}

		$table = $this->getSecretsTable();
		$record = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT `secret`, `recovery`, `mode` FROM `{$table}` WHERE `user_id` = %d LIMIT 1",
				$user->ID
			),
			ARRAY_A
		);
		if ( !\is_array( $record ) || ( $record[ 'mode' ] ?? '' ) !== self::MODE_AUTHENTICATOR ) {
			return $data;
		}

		$data->hasSourceState = true;

		$secret = \is_string( $record[ 'secret' ] ?? null ) ? $record[ 'secret' ] : '';
		if ( $secret !== '' ) {
			$data->gaSecret = Base32::encode( $secret );
		}

		$recovery = \is_string( $record[ 'recovery' ] ?? null ) ? $record[ 'recovery' ] : '';
		if ( $recovery !== '' ) {
			$data->backupCodeHashes = $this->hashRecoveryCodes( $recovery );
		}

		return $data;
	}

	private function getSecretsTable() :string {
		return Services::WpDb()->getPrefix().self::TABLE_2FA_SECRETS;
	}

	private function doesSecretsTableExist() :bool {
		global $wpdb;
		if ( !( $wpdb instanceof \wpdb ) ) {
			return false;
		}

		$wpDb = Services::WpDb();
		$wpDb->clearResultShowTables();
		return $wpDb->tableExists( $this->getSecretsTable() );
	}

	private function isMarkedUnavailable() :bool {
		return (bool)self::con()->opts->optGet( self::OPT_UNAVAILABLE );
	}

	private function markUnavailable( bool $unavailable = true ) :void {
		self::con()->opts->optSet( self::OPT_UNAVAILABLE, $unavailable );
	}

	private function isSourcePluginActive() :bool {
		foreach ( $this->getActivePluginFiles() as $pluginFile ) {
			if ( \preg_match( '#^wordfence[^/]*/wordfence\.php$#i', $pluginFile ) === 1
				 || \preg_match( '#^wordfence-login-security[^/]*/wordfence-login-security\.php$#i', $pluginFile ) === 1 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @return string[]
	 */
	private function getActivePluginFiles() :array {
		$activePlugins = \is_array( \get_option( 'active_plugins', [] ) ) ? \get_option( 'active_plugins', [] ) : [];

		if ( \is_multisite() ) {
			$activePlugins = \array_merge(
				$activePlugins,
				\array_keys( \is_array( \get_site_option( 'active_sitewide_plugins', [] ) ) ? \get_site_option( 'active_sitewide_plugins', [] ) : [] )
			);
		}

		return \array_values( \array_unique( \array_filter(
			\array_map( 'strval', $activePlugins ),
			static fn( string $pluginFile ) => $pluginFile !== ''
		) ) );
	}

	/**
	 * @return string[]
	 */
	private function hashRecoveryCodes( string $recovery ) :array {
		$hashes = [];

		foreach ( \str_split( $recovery, self::RECOVERY_CODE_BYTES ) as $chunk ) {
			if ( \strlen( $chunk ) === self::RECOVERY_CODE_BYTES ) {
				$hashes[] = \wp_hash_password( \bin2hex( $chunk ) );
			}
		}

		return \array_values( \array_unique( \array_filter(
			$hashes,
			static fn( string $hash ) => $hash !== ''
		) ) );
	}
}
