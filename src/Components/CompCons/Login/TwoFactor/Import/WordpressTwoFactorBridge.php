<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\Login\TwoFactor\Import;

class WordpressTwoFactorBridge implements SupplierBridgeInterface {

	public const ACTIVE_PLUGIN_FILE = 'two-factor/two-factor.php';
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

	public function isApplicable() :bool {
		return !$this->isSourcePluginActive();
	}

	public function discoverForUser( \WP_User $user ) :SupplierFactorData {
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
			$data->gaSecret = $totpSecret;
		}

		if ( \in_array( 'Two_Factor_Email', $enabledProviders, true ) ) {
			$data->emailEnabled = true;
		}

		if ( \in_array( 'Two_Factor_Backup_Codes', $enabledProviders, true ) ) {
			$data->backupCodeHashes = \array_values( \array_unique( $backupCodes ) );
		}

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

	private function isSourcePluginActive() :bool {
		if ( !\function_exists( 'is_plugin_active' ) && \defined( 'ABSPATH' ) ) {
			require_once ABSPATH.'wp-admin/includes/plugin.php';
		}

		return \function_exists( 'is_plugin_active' ) && \is_plugin_active( self::ACTIVE_PLUGIN_FILE );
	}
}
