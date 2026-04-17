<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Import;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\BackupCodes;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\Email;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\GoogleAuth;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Utilties\MfaRecordsHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class ImportUserProcessor {

	use PluginControllerConsumer;

	public const SKIP_EXISTING_SHIELD_PROFILE = 'existing_shield_profile';
	public const SKIP_SHIELD_PROVIDER_UNAVAILABLE = 'shield_provider_unavailable';
	public const SKIP_INVALID_SECRET = 'invalid_secret';

	public function process( \WP_User $user, SupplierBridgeInterface $bridge ) :UserImportResult {
		$result = new UserImportResult();

		$factorStates = $this->getShieldFactorStatesForUser( $user );
		$importableFactorSlugs = \array_values( \array_filter(
			$bridge->getSupportedFactorSlugs(),
			fn( string $factorSlug ) => ( $factorStates[ $factorSlug ][ 'available' ] ?? false )
									   && ( $factorStates[ $factorSlug ][ 'vacant' ] ?? false )
		) );

		if ( empty( $importableFactorSlugs ) ) {
			return $result;
		}

		$data = $bridge->discoverForUser( $user, $importableFactorSlugs );
		$result->hasSourceState = $data->hasSourceState;

		if ( !$data->hasSourceState ) {
			return $result;
		}

		$this->importGaForUser( $user, $data, $result, $factorStates[ GoogleAuth::ProviderSlug() ] ?? [] );
		$this->importEmailForUser( $user, $data, $result, $factorStates[ Email::ProviderSlug() ] ?? [] );
		$this->importBackupCodesForUser( $user, $data, $result, $factorStates[ BackupCodes::ProviderSlug() ] ?? [] );

		$result->importedFactorSlugs = \array_values( \array_unique( $result->importedFactorSlugs ) );

		return $result;
	}

	/**
	 * @return array<string, array{available: bool, vacant: bool}>
	 */
	private function getShieldFactorStatesForUser( \WP_User $user ) :array {
		$availableProviders = self::con()->comps->mfa->getProvidersAvailableToUser( $user );

		return [
			GoogleAuth::ProviderSlug()  => [
				'available' => isset( $availableProviders[ GoogleAuth::ProviderSlug() ] ),
				'vacant'    => !$this->hasMfaSecret( $user, GoogleAuth::ProviderSlug() ),
			],
			Email::ProviderSlug()       => [
				'available' => isset( $availableProviders[ Email::ProviderSlug() ] ),
				'vacant'    => !(bool)self::con()->user_metas->for( $user )->email_2fa_enabled,
			],
			BackupCodes::ProviderSlug() => [
				'available' => isset( $availableProviders[ BackupCodes::ProviderSlug() ] ),
				'vacant'    => !$this->hasMfaSecret( $user, BackupCodes::ProviderSlug() ),
			],
		];
	}

	/**
	 * @param array{available?: bool, vacant?: bool} $factorState
	 */
	private function importGaForUser( \WP_User $user, SupplierFactorData $data, UserImportResult $result, array $factorState ) :void {
		$providerSlug = GoogleAuth::ProviderSlug();
		if ( !$this->supplierHasFactorState( $data, $providerSlug ) ) {
			return;
		}

		if ( !( $factorState[ 'available' ] ?? false ) ) {
			$result->skippedFactorReasons[ $providerSlug ] = self::SKIP_SHIELD_PROVIDER_UNAVAILABLE;
		}
		elseif ( !( $factorState[ 'vacant' ] ?? false ) ) {
			$result->skippedFactorReasons[ $providerSlug ] = self::SKIP_EXISTING_SHIELD_PROFILE;
		}
		elseif ( isset( $data->skippedFactorReasons[ $providerSlug ] ) ) {
			$result->skippedFactorReasons[ $providerSlug ] = $data->skippedFactorReasons[ $providerSlug ];
		}
		elseif ( !empty( $data->gaSecret ) ) {
			$this->importGaSecret( $user, $data->gaSecret );
			if ( $this->hasMfaSecret( $user, $providerSlug ) ) {
				$result->importedFactorSlugs[] = $providerSlug;
			}
			else {
				$result->skippedFactorReasons[ $providerSlug ] = self::SKIP_INVALID_SECRET;
			}
		}
		else {
			$result->skippedFactorReasons[ $providerSlug ] = self::SKIP_INVALID_SECRET;
		}
	}

	/**
	 * @param array{available?: bool, vacant?: bool} $factorState
	 */
	private function importEmailForUser( \WP_User $user, SupplierFactorData $data, UserImportResult $result, array $factorState ) :void {
		$providerSlug = Email::ProviderSlug();
		if ( !$this->supplierHasFactorState( $data, $providerSlug ) ) {
			return;
		}

		if ( !( $factorState[ 'available' ] ?? false ) ) {
			$result->skippedFactorReasons[ $providerSlug ] = self::SKIP_SHIELD_PROVIDER_UNAVAILABLE;
		}
		elseif ( !( $factorState[ 'vacant' ] ?? false ) ) {
			$result->skippedFactorReasons[ $providerSlug ] = self::SKIP_EXISTING_SHIELD_PROFILE;
		}
		elseif ( $data->emailEnabled ) {
			self::con()->user_metas->for( $user )->email_2fa_enabled = true;
			$result->importedFactorSlugs[] = $providerSlug;
		}
	}

	/**
	 * @param array{available?: bool, vacant?: bool} $factorState
	 */
	private function importBackupCodesForUser( \WP_User $user, SupplierFactorData $data, UserImportResult $result, array $factorState ) :void {
		$providerSlug = BackupCodes::ProviderSlug();
		if ( !$this->supplierHasFactorState( $data, $providerSlug ) ) {
			return;
		}

		if ( !( $factorState[ 'available' ] ?? false ) ) {
			$result->skippedFactorReasons[ $providerSlug ] = self::SKIP_SHIELD_PROVIDER_UNAVAILABLE;
		}
		elseif ( !( $factorState[ 'vacant' ] ?? false ) ) {
			$result->skippedFactorReasons[ $providerSlug ] = self::SKIP_EXISTING_SHIELD_PROFILE;
		}
		elseif ( !empty( $data->backupCodeHashes ) ) {
			$this->importBackupCodes( $user, $data->backupCodeHashes );
			if ( $this->hasMfaSecret( $user, $providerSlug ) ) {
				$result->importedFactorSlugs[] = $providerSlug;
			}
		}
	}

	private function supplierHasFactorState( SupplierFactorData $data, string $providerSlug ) :bool {
		return \in_array( $providerSlug, $data->sourceFactorSlugs, true )
			   || isset( $data->skippedFactorReasons[ $providerSlug ] );
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
