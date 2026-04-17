<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Import;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\BackupCodes;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\Email;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\GoogleAuth;

class SolidSecurityBridge implements SupplierBridgeInterface {

	public const META_ENABLED_PROVIDERS = '_two_factor_enabled_providers';
	public const META_TOTP_SECRET = '_two_factor_totp_key';
	public const META_BACKUP_CODES = '_two_factor_backup_codes';

	private const SITE_STORAGE_OPTION = 'itsec-storage';
	private const SITE_STORAGE_KEY = 'two-factor';
	private const ENCRYPTED_TOTP_PREFIX = '$t1$';
	private const SUPPORTED_PROVIDERS = [
		'Two_Factor_Totp',
		'Two_Factor_Email',
		'Two_Factor_Backup_Codes',
	];

	public function getSupplierSlug() :string {
		return 'solid_security';
	}

	public function getSupportedFactorSlugs() :array {
		return [
			GoogleAuth::ProviderSlug(),
			Email::ProviderSlug(),
			BackupCodes::ProviderSlug(),
		];
	}

	public function discoverForUser( \WP_User $user, array $importableFactorSlugs = [] ) :SupplierFactorData {
		$data = new SupplierFactorData();

		$enabledProvidersRaw = get_user_meta( $user->ID, self::META_ENABLED_PROVIDERS, true );
		$enabledProviders = $this->getEnabledProvidersForUser( $enabledProvidersRaw );
		$totpSecret = \trim( (string)get_user_meta( $user->ID, self::META_TOTP_SECRET, true ) );
		$backupCodesRaw = get_user_meta( $user->ID, self::META_BACKUP_CODES, true );
		$backupCodes = \array_values( \array_filter(
			\array_map( 'strval', \is_array( $backupCodesRaw ) ? $backupCodesRaw : [] ),
			static fn( string $hash ) => $hash !== ''
		) );

		$data->hasSourceState = ( \is_array( $enabledProvidersRaw ) && !empty( $enabledProvidersRaw ) )
								|| $totpSecret !== ''
								|| !empty( $backupCodes );

		if ( \in_array( 'Two_Factor_Totp', $enabledProviders, true ) && $totpSecret !== '' ) {
			$data->sourceFactorSlugs[] = GoogleAuth::ProviderSlug();

			if ( \in_array( GoogleAuth::ProviderSlug(), $importableFactorSlugs, true ) ) {
				if ( $this->isEncryptedSecret( $totpSecret ) ) {
					$decrypted = $this->decryptTotpSecretForUser( $user, $totpSecret, $data );
					if ( $decrypted !== null ) {
						$data->gaSecret = $decrypted;
					}
				}
				else {
					$data->gaSecret = \strtoupper( $totpSecret );
				}
			}
		}

		if ( \in_array( 'Two_Factor_Email', $enabledProviders, true ) ) {
			$data->sourceFactorSlugs[] = Email::ProviderSlug();
			$data->emailEnabled = true;
		}

		if ( \in_array( 'Two_Factor_Backup_Codes', $enabledProviders, true ) && !empty( $backupCodes ) ) {
			$data->sourceFactorSlugs[] = BackupCodes::ProviderSlug();
			$data->backupCodeHashes = \array_values( \array_unique( $backupCodes ) );
		}

		$data->sourceFactorSlugs = \array_values( \array_unique( $data->sourceFactorSlugs ) );

		return $data;
	}

	/**
	 * @param mixed $enabledProvidersRaw
	 * @return string[]
	 */
	private function getEnabledProvidersForUser( $enabledProvidersRaw ) :array {
		return \array_values( \array_intersect(
			$this->normalizeProviderList( $enabledProvidersRaw ),
			$this->getSiteEnabledProviders()
		) );
	}

	/**
	 * @return string[]
	 */
	private function getSiteEnabledProviders() :array {
		$settings = $this->getTwoFactorSiteSettings();
		$availableMethods = (string)( $settings[ 'available_methods' ] ?? 'all' );

		switch ( $availableMethods ) {
			case 'not_email':
				$enabledProviders = self::SUPPORTED_PROVIDERS;
				unset( $enabledProviders[ 1 ] );
				break;
			case 'custom':
				$enabledProviders = $this->normalizeProviderList( $settings[ 'custom_available_methods' ] ?? [] );
				break;
			case 'all':
			default:
				$enabledProviders = self::SUPPORTED_PROVIDERS;
				break;
		}

		return \array_values( \array_unique( $enabledProviders ) );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function getTwoFactorSiteSettings() :array {
		$storage = get_site_option( self::SITE_STORAGE_OPTION, [] );
		return \is_array( $storage[ self::SITE_STORAGE_KEY ] ?? null ) ? $storage[ self::SITE_STORAGE_KEY ] : [];
	}

	/**
	 * @param mixed $providers
	 * @return string[]
	 */
	private function normalizeProviderList( $providers ) :array {
		return \array_values( \array_intersect(
			\array_map( 'strval', \is_array( $providers ) ? $providers : [] ),
			self::SUPPORTED_PROVIDERS
		) );
	}

	private function isEncryptedSecret( string $secret ) :bool {
		return \strlen( $secret ) >= 40 && \strpos( $secret, self::ENCRYPTED_TOTP_PREFIX ) === 0;
	}

	private function decryptTotpSecretForUser( \WP_User $user, string $storedSecret, SupplierFactorData $data ) :?string {
		$secret = $this->getItsecEncryptionSecret();
		if ( $secret === null ) {
			$data->skippedFactorReasons[ GoogleAuth::ProviderSlug() ] = 'encrypted_missing_key';
			return null;
		}

		if ( !\function_exists( 'sodium_crypto_aead_xchacha20poly1305_ietf_decrypt' ) && \defined( 'ABSPATH' ) ) {
			$sodiumCompat = ABSPATH.WPINC.'/sodium_compat/autoload.php';
			if ( \is_file( $sodiumCompat ) ) {
				require_once $sodiumCompat;
			}
		}

		if ( !\function_exists( 'sodium_crypto_aead_xchacha20poly1305_ietf_decrypt' ) ) {
			$data->skippedFactorReasons[ GoogleAuth::ProviderSlug() ] = 'decrypt_failed';
			return null;
		}

		$decoded = \base64_decode( \substr( $storedSecret, 4 ), true );
		if ( !\is_string( $decoded ) || \strlen( $decoded ) <= 24 ) {
			$data->skippedFactorReasons[ GoogleAuth::ProviderSlug() ] = 'decrypt_failed';
			return null;
		}

		$nonce = \substr( $decoded, 0, 24 );
		$ciphertext = \substr( $decoded, 24 );
		$decrypted = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
			$ciphertext,
			self::ENCRYPTED_TOTP_PREFIX.$nonce.\pack( 'N', $user->ID ),
			$nonce,
			\hash_hmac( 'sha256', $secret, 'itsec-user-encryption', true )
		);

		if ( !\is_string( $decrypted ) ) {
			$data->skippedFactorReasons[ GoogleAuth::ProviderSlug() ] = 'decrypt_failed';
			return null;
		}

		return \strtoupper( \trim( $decrypted ) );
	}

	protected function getItsecEncryptionSecret() :?string {
		return \defined( 'ITSEC_ENCRYPTION_KEY' ) && \strlen( (string)ITSEC_ENCRYPTION_KEY ) > 16
			? (string)ITSEC_ENCRYPTION_KEY
			: null;
	}
}
