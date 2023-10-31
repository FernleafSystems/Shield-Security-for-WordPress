<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Utilties;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\DB\Mfa\Ops as MfaDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\Passkey;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Consumer\WpUserConsumer;
use FernleafSystems\Wordpress\Services\Services;
use Webauthn\{
	PublicKeyCredentialSource,
	PublicKeyCredentialSourceRepository,
	PublicKeyCredentialUserEntity
};

class PasskeySourcesHandler implements PublicKeyCredentialSourceRepository {

	use ModConsumer;
	use WpUserConsumer;

	public function count() :int {
		/** @var MfaDB\Select $selector */
		$selector = $this->mod()->getDbH_Mfa()->getQuerySelector();
		return $selector->filterBySlug( Passkey::ProviderSlug() )->count();
	}

	public function findOneByCredentialId( string $publicKeyCredentialId ) :?PublicKeyCredentialSource {
		$record = $this->getRecordFromSourceID( $publicKeyCredentialId );
		return empty( $record ) ? null : $this->getSourceFromRecord( $record );
	}

	/**
	 * @return PublicKeyCredentialSource[]
	 * @throws \Exception
	 */
	public function findAllForUserEntity( PublicKeyCredentialUserEntity $publicKeyCredentialUserEntity ) :array {
		$user = Services::WpUsers()->getUserByUsername( $publicKeyCredentialUserEntity->getName() );
		if ( $user->ID !== $this->getWpUser()->ID ) {
			throw new \Exception( 'Invalid user query!!' );
		}
		return \array_filter( \array_map(
			function ( MfaDB\Record $record ) {
				return $this->getSourceFromRecord( $record );
			},
			$this->getAllSourceRecords()
		) );
	}

	/**
	 * @return MfaDB\Record[]
	 */
	public function getAllSourceRecords() :array {
		/** @var MfaDB\Select $selector */
		$selector = $this->mod()->getDbH_Mfa()->getQuerySelector();
		/** @var MfaDB\Record[] $record */
		$records = $selector->filterByUserID( $this->getWpUser()->ID )
							->filterBySlug( Passkey::ProviderSlug() )
							->queryWithResult();
		return \is_array( $records ) ? $records : [];
	}

	/**
	 * @throws \Exception
	 */
	public function saveCredentialSource( PublicKeyCredentialSource $publicKeyCredentialSource ) :void {
		$preExistingSource = $this->findOneByCredentialId( $publicKeyCredentialSource->getPublicKeyCredentialId() );
		if ( empty( $preExistingSource ) ) {
			$dbh = $this->mod()->getDbH_Mfa();
			/** @var MfaDB\Record $record */
			$record = $dbh->getRecord();
			$record->user_id = $this->getWpUser()->ID;
			$record->slug = Passkey::ProviderSlug();
			$record->unique_id = $this->normalisedSourceID( $publicKeyCredentialSource->getPublicKeyCredentialId() );
			$record->label = 'No Label';
			$record->data = $publicKeyCredentialSource->jsonSerialize();

			$dbh->getQueryInserter()->insert( $record );
		}
		else {
			$this->updateSource( $publicKeyCredentialSource );
		}
	}

	/**
	 * @throws \Exception
	 */
	public function updateSource( PublicKeyCredentialSource $publicKeyCredentialSource, array $meta = [] ) :void {
		$record = $this->getRecordFromSource( $publicKeyCredentialSource );
		if ( empty( $record ) ) {
			throw new \Exception( 'Source does not exist.' );
		}

		$record->data = $publicKeyCredentialSource->jsonSerialize();

		$dbh = $this->mod()->getDbH_Mfa();
		$dbh->getQueryUpdater()->updateRecord( $record, $meta );
	}

	public function deleteSource( string $encodedID ) :bool {
		/** @var MfaDB\Delete $deleter */
		$deleter = $this->mod()->getDbH_Mfa()->getQueryDeleter();
		$deleter->filterBySlug( Passkey::ProviderSlug() )
				->filterByUniqueID( $encodedID )
				->queryWithResult();
		return true;
	}

	private function normalisedSourceID( string $publicKeyCredentialId ) :string {
		return \base64_encode( $publicKeyCredentialId );
	}

	private function getRecordFromSource( PublicKeyCredentialSource $publicKeyCredentialSource ) :?MfaDB\Record {
		return $this->getRecordFromSourceID( $publicKeyCredentialSource->getPublicKeyCredentialId() );
	}

	private function getRecordFromSourceID( string $publicKeyCredentialId ) :?MfaDB\Record {
		/** @var MfaDB\Select $selector */
		$selector = $this->mod()->getDbH_Mfa()->getQuerySelector();
		/** @var ?MfaDB\Record $record */
		return $selector->filterByUniqueID( $this->normalisedSourceID( $publicKeyCredentialId ) )
						->filterBySlug( Passkey::ProviderSlug() )
						->first();
	}

	private function getSourceFromRecord( MfaDB\Record $record ) :?PublicKeyCredentialSource {
		try {
			$source = PublicKeyCredentialSource::createFromArray( $record->data );
		}
		catch ( \InvalidArgumentException $e ) {
			$source = null;
		}
		return $source;
	}
}