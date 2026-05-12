<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\Login\TwoFactor\Import;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\BackupCodes;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\Email;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\GoogleAuth;

class WordpressTwoFactorBridge implements SupplierBridgeInterface {

	public const OPT_SITE_ENABLED_PROVIDERS = 'two_factor_enabled_providers';
	public const META_ENABLED_PROVIDERS = '_two_factor_enabled_providers';
	public const META_TOTP_SECRET = '_two_factor_totp_key';
	public const META_BACKUP_CODES = '_two_factor_backup_codes';

	private const SUPPORTED_PROVIDERS = [
		'Two_Factor_Totp',
		'Two_Factor_Email',
		'Two_Factor_Backup_Codes',
	];

	public function getSupplierSlug() :string {
		return 'wordpress_two_factor';
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
		$backupCodesRaw = get_user_meta( $user->ID, self::META_BACKUP_CODES, true );

		$totpSecret = \strtoupper( \trim( (string)get_user_meta( $user->ID, self::META_TOTP_SECRET, true ) ) );
		$backupCodes = \array_values( \array_filter(
			\array_map( 'strval', \is_array( $backupCodesRaw ) ? $backupCodesRaw : [] ),
			static fn( string $hash ) => $hash !== ''
		) );

		$data->hasSourceState = ( \is_array( $enabledProvidersRaw ) && !empty( $enabledProvidersRaw ) )
								|| $totpSecret !== ''
								|| !empty( $backupCodes );

		if ( \in_array( 'Two_Factor_Totp', $enabledProviders, true ) && $totpSecret !== '' ) {
			$data->sourceFactorSlugs[] = GoogleAuth::ProviderSlug();
			$data->gaSecret = $totpSecret;
		}

		if ( \in_array( 'Two_Factor_Email', $enabledProviders, true ) ) {
			$data->sourceFactorSlugs[] = Email::ProviderSlug();
			$data->emailEnabled = true;
		}

		if ( \in_array( 'Two_Factor_Backup_Codes', $enabledProviders, true ) ) {
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
		$enabledProviders = $this->normalizeProviderList( $enabledProvidersRaw );
		$siteEnabledProviders = $this->getSiteEnabledProviders();

		if ( $siteEnabledProviders !== null ) {
			$enabledProviders = \array_values( \array_intersect( $enabledProviders, $siteEnabledProviders ) );
		}

		return $enabledProviders;
	}

	/**
	 * @return string[]|null
	 */
	private function getSiteEnabledProviders() :?array {
		$siteEnabled = get_option( self::OPT_SITE_ENABLED_PROVIDERS, null );
		return $siteEnabled === null ? null : $this->normalizeProviderList( $siteEnabled );
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
}
