<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Utilties;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\Mfa\Ops as MfaDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\Passkey;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Consumer\WpUserConsumer;
use FernleafSystems\Wordpress\Services\Services;
use Symfony\Component\Serializer\Normalizer\{
	DenormalizerInterface,
	NormalizerInterface
};
use Webauthn\{
	AttestationStatement\AttestationStatementSupportManager,
	CredentialRecord,
	Denormalizer\WebauthnSerializerFactory,
	PublicKeyCredentialUserEntity
};

class PasskeySourcesHandler {

	use PluginControllerConsumer;
	use WpUserConsumer;

	public function count() :int {
		return \count( $this->getUserSourceRecords() );
	}

	public function findOneByCredentialId( string $publicKeyCredentialId ) :?CredentialRecord {
		$record = $this->getRecordFromSourceID( $publicKeyCredentialId );
		return $record === null ? null : $this->getCredentialRecordFromRecord( $record );
	}

	/**
	 * @return list<CredentialRecord>
	 * @throws \Exception
	 */
	public function findAllForUserEntity( PublicKeyCredentialUserEntity $publicKeyCredentialUserEntity ) :array {
		$user = Services::WpUsers()->getUserByUsername( $publicKeyCredentialUserEntity->name );
		if ( !$user instanceof \WP_User || $user->ID !== $this->getWpUser()->ID ) {
			throw new \Exception( 'Invalid user query.' );
		}
		return $this->getCredentialRecordsFromMfaRecords( $this->getUserSourceRecords() );
	}

	/**
	 * @return list<MfaDB\Record>
	 */
	public function getUserSourceRecords() :array {
		return \array_values( ( new MfaRecordsHandler() )->loadFor( $this->getWpUser(), Passkey::ProviderSlug() ) );
	}

	/**
	 * @return list<CredentialRecord>
	 */
	public function getExcludedCredentialRecordsForCurrentUser() :array {
		return $this->getCredentialRecordsFromMfaRecords(
			\array_filter(
				$this->getUserSourceRecords(),
				static fn( MfaDB\Record $record ) => $record->passwordless
			)
		);
	}

	/**
	 * @throws \Exception
	 */
	public function saveCredentialRecord( CredentialRecord $credentialRecord ) :void {
		$this->saveCredentialData( $this->credentialRecordToArray( $credentialRecord ) );
	}

	/**
	 * @throws \Exception
	 */
	public function saveCredentialData( array $credentialData ) :void {
		$credentialData = $this->normalizeCredentialDataForStorage( $credentialData );
		$preExistingSource = $this->getRecordFromCredentialData( $credentialData );
		if ( $preExistingSource === null ) {
			/** @var MfaDB\Record $record */
			$record = self::con()->db_con->mfa->getRecord();
			$record->user_id = $this->getWpUser()->ID;
			$record->slug = Passkey::ProviderSlug();
			$record->unique_id = $this->normalisedSourceIDFromCredentialData( $credentialData );
			$record->label = 'No Label';
			$record->data = $credentialData;
			$record->passwordless = true;

			( new MfaRecordsHandler() )->insert( $record );
		}
		else {
			$this->updateCredentialData( $credentialData );
		}
	}

	/**
	 * @throws \Exception
	 */
	public function updateCredentialRecord( CredentialRecord $credentialRecord, array $data = [] ) :void {
		$this->updateCredentialData( $this->credentialRecordToArray( $credentialRecord ), $data );
	}

	/**
	 * @throws \Exception
	 */
	public function updateCredentialData( array $credentialData, array $data = [] ) :void {
		$credentialData = $this->normalizeCredentialDataForStorage( $credentialData );
		$record = $this->getRecordFromCredentialData( $credentialData );
		if ( $record === null ) {
			throw new \Exception( 'Source does not exist.' );
		}

		$data[ 'data' ] = \base64_encode( $this->encodeCredentialData( $credentialData ) );

		( new MfaRecordsHandler() )->update( $record, $data );
	}

	public function deleteSource( string $uniqueID ) :bool {
		$record = $this->getRecordFromUniqueID( $uniqueID );
		if ( $record === null ) {
			return false;
		}

		( new MfaRecordsHandler() )->delete( $record );
		return true;
	}

	private function normalisedSourceID( string $publicKeyCredentialId ) :string {
		return \base64_encode( $publicKeyCredentialId );
	}

	private function getRecordFromCredentialData( array $credentialData ) :?MfaDB\Record {
		$sourceId = $this->sourceIdFromCredentialData( $credentialData );
		return $sourceId === null ? null : $this->getRecordFromSourceID( $sourceId );
	}

	private function getRecordFromSourceID( string $publicKeyCredentialId ) :?MfaDB\Record {
		return $this->getRecordFromUniqueID( $this->normalisedSourceID( $publicKeyCredentialId ) );
	}

	private function getRecordFromUniqueID( string $uniqueID ) :?MfaDB\Record {
		foreach ( $this->getUserSourceRecords() as $record ) {
			if ( $record->unique_id === $uniqueID ) {
				return $record;
			}
		}
		return null;
	}

	private function getCredentialRecordFromRecord( MfaDB\Record $record ) :?CredentialRecord {
		try {
			$credentialRecord = $this->credentialRecordFromArray( $record->data );
		}
		catch ( \Throwable $e ) {
			// Stored MFA rows are a DB boundary; ignore corrupt passkey records without blocking login.
			$credentialRecord = null;
		}
		return $credentialRecord;
	}

	/**
	 * @param MfaDB\Record[] $records
	 * @return list<CredentialRecord>
	 */
	private function getCredentialRecordsFromMfaRecords( array $records ) :array {
		return \array_values( \array_filter( \array_map(
			function ( MfaDB\Record $record ) {
				return $this->getCredentialRecordFromRecord( $record );
			},
			$records
		) ) );
	}

	private function normalisedSourceIDFromCredentialData( array $credentialData ) :string {
		$sourceId = $this->sourceIdFromCredentialData( $credentialData );
		if ( $sourceId === null ) {
			throw new \InvalidArgumentException( 'Invalid passkey credential ID.' );
		}

		return $this->normalisedSourceID( $sourceId );
	}

	private function sourceIdFromCredentialData( array $credentialData ) :?string {
		if ( !\array_key_exists( 'publicKeyCredentialId', $credentialData )
			 || !\is_string( $credentialData[ 'publicKeyCredentialId' ] )
			 || $credentialData[ 'publicKeyCredentialId' ] === '' ) {
			return null;
		}

		try {
			$sourceId = PasskeyBase64Url::decode( $credentialData[ 'publicKeyCredentialId' ] );
		}
		catch ( \Throwable $e ) {
			return null;
		}

		return $sourceId === '' ? null : $sourceId;
	}

	private function credentialRecordFromArray( array $credentialData ) :CredentialRecord {
		$credentialRecord = $this->serializer()->denormalize(
			$this->normalizeCredentialDataForWebauthn( $credentialData ),
			CredentialRecord::class
		);
		if ( !$credentialRecord instanceof CredentialRecord ) {
			throw new \InvalidArgumentException( 'Invalid CredentialRecord payload.' );
		}
		return $credentialRecord;
	}

	private function credentialRecordToArray( CredentialRecord $credentialRecord ) :array {
		$normalized = $this->serializer()->normalize( $credentialRecord );
		if ( !\is_array( $normalized ) ) {
			throw new \UnexpectedValueException( sprintf(
				'Expected WebAuthn normalizer to return an array, got %s.',
				\get_debug_type( $normalized )
			) );
		}
		return $this->normalizeCredentialDataForStorage( $normalized );
	}

	private function encodeCredentialData( array $credentialData ) :string {
		$encoded = \wp_json_encode( $credentialData );
		if ( !\is_string( $encoded ) ) {
			throw new \UnexpectedValueException( 'Passkey credential data could not be encoded.' );
		}

		return $encoded;
	}

	private function normalizeCredentialDataForStorage( array $credentialData ) :array {
		return ( new PasskeyCredentialDataNormalizer() )->normalizeForStorage( $credentialData );
	}

	private function normalizeCredentialDataForWebauthn( array $credentialData ) :array {
		return ( new PasskeyCredentialDataNormalizer() )->normalizeForWebauthn( $credentialData );
	}

	private function serializer() :NormalizerInterface&DenormalizerInterface {
		$serializer = ( new WebauthnSerializerFactory( new AttestationStatementSupportManager() ) )->create();
		if ( !$serializer instanceof NormalizerInterface || !$serializer instanceof DenormalizerInterface ) {
			throw new \UnexpectedValueException( 'Expected WebAuthn serializer to support normalization and denormalization.' );
		}

		return $serializer;
	}
}
