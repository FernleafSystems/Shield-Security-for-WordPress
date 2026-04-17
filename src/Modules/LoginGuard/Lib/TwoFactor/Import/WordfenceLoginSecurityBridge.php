<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Import;

use Base32\Base32;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\BackupCodes;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\GoogleAuth;
use FernleafSystems\Wordpress\Services\Services;

class WordfenceLoginSecurityBridge implements SupplierBridgeInterface {

	public const TABLE_2FA_SECRETS = 'wfls_2fa_secrets';

	private const MODE_AUTHENTICATOR = 'authenticator';
	private const RECOVERY_CODE_BYTES = 8;

	public function getSupplierSlug() :string {
		return 'wordfence_login_security';
	}

	public function getSupportedFactorSlugs() :array {
		return [
			GoogleAuth::ProviderSlug(),
			BackupCodes::ProviderSlug(),
		];
	}

	public function discoverForUser( \WP_User $user, array $importableFactorSlugs = [] ) :SupplierFactorData {
		$data = new SupplierFactorData();

		if ( !$this->hasSecretsTable() ) {
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
			$data->sourceFactorSlugs[] = GoogleAuth::ProviderSlug();
			$data->gaSecret = Base32::encode( $secret );
		}

		$recovery = \is_string( $record[ 'recovery' ] ?? null ) ? $record[ 'recovery' ] : '';
		if ( $recovery !== '' ) {
			$data->sourceFactorSlugs[] = BackupCodes::ProviderSlug();
			$data->backupCodeHashes = $this->hashRecoveryCodes( $recovery );
		}

		$data->sourceFactorSlugs = \array_values( \array_unique( $data->sourceFactorSlugs ) );

		return $data;
	}

	public function hasSecretsTable() :bool {
		global $wpdb;
		if ( !( $wpdb instanceof \wpdb ) ) {
			return false;
		}

		$wpDb = Services::WpDb();
		$wpDb->clearResultShowTables();
		return $wpDb->tableExists( $this->getSecretsTable() );
	}

	private function getSecretsTable() :string {
		return Services::WpDb()->getPrefix().self::TABLE_2FA_SECRETS;
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
