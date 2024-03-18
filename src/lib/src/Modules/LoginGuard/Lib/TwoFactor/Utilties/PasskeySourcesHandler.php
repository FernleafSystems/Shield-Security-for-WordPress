<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Utilties;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\Mfa\Ops as MfaDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\Passkey;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Consumer\WpUserConsumer;
use FernleafSystems\Wordpress\Services\Services;
use Webauthn\{
	PublicKeyCredentialSource,
	PublicKeyCredentialSourceRepository,
	PublicKeyCredentialUserEntity
};

class PasskeySourcesHandler implements PublicKeyCredentialSourceRepository {

	use PluginControllerConsumer;
	use WpUserConsumer;

	public function count() :int {
		return \count( $this->getUserSourceRecords() );
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
		return $this->getSourcesFromRecords( $this->getUserSourceRecords() );
	}

	/**
	 * @return MfaDB\Record[]
	 */
	public function getUserSourceRecords() :array {
		return ( new MfaRecordsHandler() )->loadFor( $this->getWpUser(), Passkey::ProviderSlug() );
	}

	/**
	 * @return PublicKeyCredentialSource[]
	 */
	public function getExcludedSourcesFromAllUsers() :array {
		/** @var MfaDB\Record[] $record */
		$records = \array_filter(
			( new MfaRecordsHandler() )->loadFor( $this->getWpUser(), Passkey::ProviderSlug() ),
			function ( MfaDB\Record $record ) {
				return $record->passwordless;
			}
		);
		return $this->getSourcesFromRecords( $records );
	}

	/**
	 * @throws \Exception
	 */
	public function saveCredentialSource( PublicKeyCredentialSource $publicKeyCredentialSource ) :void {
		$preExistingSource = $this->findOneByCredentialId( $publicKeyCredentialSource->getPublicKeyCredentialId() );
		if ( empty( $preExistingSource ) ) {
			/** @var MfaDB\Record $record */
			$record = self::con()->db_con->mfa->getRecord();
			$record->user_id = $this->getWpUser()->ID;
			$record->slug = Passkey::ProviderSlug();
			$record->unique_id = $this->normalisedSourceID( $publicKeyCredentialSource->getPublicKeyCredentialId() );
			$record->label = 'No Label';
			$record->data = $publicKeyCredentialSource->jsonSerialize();
			$record->passwordless = 1;

			( new MfaRecordsHandler() )->insert( $record );
		}
		else {
			$this->updateSource( $publicKeyCredentialSource );
		}
	}

	/**
	 * @throws \Exception
	 */
	public function updateSource( PublicKeyCredentialSource $publicKeyCredentialSource, array $data = [] ) :void {
		$record = $this->getRecordFromSource( $publicKeyCredentialSource );
		if ( empty( $record ) ) {
			throw new \Exception( 'Source does not exist.' );
		}

		$data[ 'data' ] = \base64_encode( \wp_json_encode( $publicKeyCredentialSource->jsonSerialize() ) );

		( new MfaRecordsHandler() )->update( $record, $data );
	}

	public function deleteSource( string $encodedID ) :bool {
		/** @var MfaDB\Delete $deleter */
		$deleter = self::con()->db_con->mfa->getQueryDeleter();
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
		$records = \array_filter(
			( new MfaRecordsHandler() )->loadFor( $this->getWpUser(), Passkey::ProviderSlug() ),
			function ( MfaDB\Record $record ) use ( $publicKeyCredentialId ) {
				return $record->unique_id === $this->normalisedSourceID( $publicKeyCredentialId );
			}
		);
		return empty( $records ) ? null : \reset( $records );
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

	/**
	 * @param MfaDB\Record[] $records
	 */
	private function getSourcesFromRecords( array $records ) :array {
		return \array_filter( \array_map(
			function ( MfaDB\Record $record ) {
				return $this->getSourceFromRecord( $record );
			},
			$records
		) );
	}
}