<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\Mfa\Ops as MfaDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Utilties\MfaRecordsHandler;

abstract class AbstractShieldProviderMfaDB extends AbstractShieldProvider {

	public function __construct( \WP_User $user ) {
		parent::__construct( $user );
		$this->maybeMigrate();
	}

	protected function getSecret() :?MfaDB\Record {
		$rec = \current( $this->loadMfaRecords() );
		return $rec instanceof MfaDB\Record ? $rec : null;
	}

	public function hasValidatedProfile() :bool {
		return $this->hasValidSecret();
	}

	protected function hasValidSecret() :bool {
		return $this->isValidSecret( $this->getSecret() );
	}

	protected function isValidSecret( $secret ) :bool {
		return $secret instanceof MfaDB\Record;
	}

	protected function maybeMigrate() :void {
	}

	/**
	 * @return MfaDB\Record[]
	 */
	protected function loadMfaRecords() :array {
		return ( new MfaRecordsHandler() )->loadFor( $this->getUser(), $this::ProviderSlug() );
	}

	protected function createNewSecretRecord( string $secret, string $label = '', array $data = [] ) :bool {
		$dbh = self::con()->db_con->mfa;
		/** @var MfaDB\Record $record */
		$record = $dbh->getRecord();
		$record->slug = $this::ProviderSlug();
		$record->user_id = $this->getUser()->ID;
		$record->unique_id = $secret;
		$record->label = preg_replace( '#[^\sa-z0-9_-]#i', '', $label );
		$record->data = $data;
		return $dbh->getQueryInserter()->insert( $record );
	}

	public function removeFromProfile() :void {
		$this->deleteAllSecrets();
	}

	public function deleteAllSecrets() :void {
		( new MfaRecordsHandler() )->deleteFor( $this->getUser(), static::ProviderSlug() );
	}
}