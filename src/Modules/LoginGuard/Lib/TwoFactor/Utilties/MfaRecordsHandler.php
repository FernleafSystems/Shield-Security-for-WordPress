<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Utilties;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\Mfa\Ops as MfaDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class MfaRecordsHandler {

	use PluginControllerConsumer;

	/**
	 * @var MfaDB\Record[][]
	 */
	private static $records = [];

	public function insert( MfaDB\Record $record ) {
		self::con()
			->db_con
			->mfa
			->getQueryInserter()
			->insert( $record );
		unset( self::$records[ $record->user_id ] );
	}

	public function update( MfaDB\Record $record, array $updateData ) {
		self::con()
			->db_con
			->mfa
			->getQueryUpdater()
			->updateRecord( $record, $updateData );
		unset( self::$records[ $record->user_id ] );
	}

	public function delete( MfaDB\Record $record ) {
		self::con()
			->db_con
			->mfa
			->getQueryDeleter()
			->deleteRecord( $record );
		unset( self::$records[ $record->user_id ] );
	}

	public function deleteFor( \WP_User $user, string $providerSlug ) :void {
		\array_map(
			function ( $record ) {
				$this->delete( $record );
			},
			$this->loadFor( $user, $providerSlug )
		);
	}

	public function deleteAllForUser( \WP_User $user ) :void {
		\array_map(
			function ( $record ) {
				$this->delete( $record );
			},
			$this->loadForUser( $user )
		);
	}

	/**
	 * @return MfaDB\Record[]
	 */
	public function loadFor( \WP_User $user, string $providerSlug ) :array {
		return \array_values( \array_filter(
			$this->loadForUser( $user ),
			function ( MfaDB\Record $record ) use ( $providerSlug ) {
				return $record->slug === $providerSlug;
			}
		) );
	}

	public function loadForUser( \WP_User $user ) {
		if ( !isset( self::$records[ $user->ID ] ) ) {
			/** @var MfaDB\Select $selector */
			$selector = self::con()->db_con->mfa->getQuerySelector();
			self::$records[ $user->ID ] = \array_values( $selector->filterByUserID( $user->ID )->queryWithResult() );
		}
		return self::$records[ $user->ID ];
	}

	public function clearForUser( \WP_User $user ) {
		unset( self::$records[ $user->ID ] );
	}
}