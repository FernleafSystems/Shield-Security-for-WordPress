<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\Login\TwoFactor\Import;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\BackupCodes;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\Email;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\GoogleAuth;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Utilties\MfaRecordsHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class ImportController {

	use PluginControllerConsumer;

	/**
	 * @var SupplierBridgeInterface[]
	 */
	private array $bridges;

	/**
	 * @param SupplierBridgeInterface[]|null $bridges
	 */
	public function __construct( ?array $bridges = null ) {
		$this->bridges = \array_values( \array_filter(
			$bridges ?? [ new WordpressTwoFactorBridge(), new WordfenceLoginSecurityBridge() ],
			static fn( $bridge ) => $bridge instanceof SupplierBridgeInterface
		) );
	}

	/**
	 * @return ImportResult[]
	 */
	public function importForUser( \WP_User $user ) :array {
		return \array_map(
			fn( SupplierBridgeInterface $bridge ) => $this->importFromBridge( $bridge, $user ),
			$this->bridges
		);
	}

	private function importFromBridge( SupplierBridgeInterface $bridge, \WP_User $user ) :ImportResult {
		$result = new ImportResult( $bridge->getSupplierSlug() );

		if ( $this->hasSupplierBeenChecked( $user, $bridge->getSupplierSlug() ) || !$bridge->isApplicable() ) {
			return $result;
		}

		$data = $bridge->discoverForUser( $user );
		if ( !$data->hasSourceState ) {
			return $result;
		}

		$result->checked = true;

		if ( !empty( $data->gaSecret ) && !$this->hasMfaSecret( $user, GoogleAuth::ProviderSlug() ) ) {
			$this->importGaSecret( $user, $data->gaSecret );
			if ( $this->hasMfaSecret( $user, GoogleAuth::ProviderSlug() ) ) {
				$result->importedFactorSlugs[] = GoogleAuth::ProviderSlug();
			}
		}

		if ( $data->emailEnabled && !(bool)self::con()->user_metas->for( $user )->email_2fa_enabled ) {
			self::con()->user_metas->for( $user )->email_2fa_enabled = true;
			$result->importedFactorSlugs[] = Email::ProviderSlug();
		}

		if ( !empty( $data->backupCodeHashes ) && !$this->hasMfaSecret( $user, BackupCodes::ProviderSlug() ) ) {
			$this->importBackupCodes( $user, $data->backupCodeHashes );
			if ( $this->hasMfaSecret( $user, BackupCodes::ProviderSlug() ) ) {
				$result->importedFactorSlugs[] = BackupCodes::ProviderSlug();
			}
		}

		$result->importedFactorSlugs = \array_values( \array_unique( $result->importedFactorSlugs ) );
		$this->storeSupplierResult( $user, $result );

		return $result;
	}

	private function hasSupplierBeenChecked( \WP_User $user, string $supplierSlug ) :bool {
		$flags = self::con()->user_metas->for( $user )->flags;
		return !empty( $flags[ 'mfa_import' ][ 'suppliers' ][ $supplierSlug ][ 'checked_at' ] );
	}

	private function storeSupplierResult( \WP_User $user, ImportResult $result ) :void {
		$meta = self::con()->user_metas->for( $user );
		$flags = $meta->flags;
		$flags[ 'mfa_import' ][ 'suppliers' ][ $result->supplierSlug ] = [
			'checked_at' => Services::Request()->ts(),
			'imported'   => $result->importedFactorSlugs,
		];
		$meta->flags = $flags;
	}

	private function hasMfaSecret( \WP_User $user, string $providerSlug ) :bool {
		return \count( ( new MfaRecordsHandler() )->loadFor( $user, $providerSlug ) ) > 0;
	}

	private function importGaSecret( \WP_User $user, string $secret ) :void {
		if ( GoogleAuth::IsValidBase32Secret( $secret ) ) {
			$this->insertMfaRecord( $user, GoogleAuth::ProviderSlug(), $secret, 'Google Auth' );
		}
	}

	/**
	 * @param string[] $backupCodeHashes
	 */
	private function importBackupCodes( \WP_User $user, array $backupCodeHashes ) :void {
		foreach ( \array_values( \array_unique( $backupCodeHashes ) ) as $hash ) {
			if ( $hash !== '' ) {
				$this->insertMfaRecord( $user, BackupCodes::ProviderSlug(), $hash, 'Backup Code' );
			}
		}
	}

	private function insertMfaRecord( \WP_User $user, string $providerSlug, string $uniqueId, string $label, array $data = [] ) :void {
		$record = self::con()->db_con->mfa->getRecord();
		$record->user_id = $user->ID;
		$record->slug = $providerSlug;
		$record->unique_id = $uniqueId;
		$record->label = (string)\preg_replace( '#[^\sa-z0-9_-]#i', '', $label );
		$record->data = $data;
		$record->passwordless = false;
		$record->used_at = 0;

		( new MfaRecordsHandler() )->insert( $record );
	}
}
