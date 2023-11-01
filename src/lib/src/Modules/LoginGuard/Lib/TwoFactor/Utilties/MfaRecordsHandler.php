<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Utilties;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\DB\Mfa\Ops as MfaDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\ModConsumer;

class MfaRecordsHandler {

	use ModConsumer;

	/**
	 * @var MfaDB\Record[][]
	 */
	private static $records = [];

	public function insert( MfaDB\Record $record ) {
		$this->mod()
			 ->getDbH_Mfa()
			 ->getQueryInserter()
			 ->insert( $record );
		unset( self::$records[ $record->user_id ] );
	}

	public function update( MfaDB\Record $record, array $updateData ) {
		$this->mod()
			 ->getDbH_Mfa()
			 ->getQueryUpdater()
			 ->updateRecord( $record, $updateData );
		unset( self::$records[ $record->user_id ] );
	}

	public function delete( MfaDB\Record $record ) {
		$this->mod()
			 ->getDbH_Mfa()
			 ->getQueryDeleter()
			 ->deleteRecord( $record );
		unset( self::$records[ $record->user_id ] );
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
			$selector = $this->mod()->getDbH_Mfa()->getQuerySelector();
			self::$records[ $user->ID ] = \array_values( $selector->filterByUserID( $user->ID )->queryWithResult() );
		}
		return self::$records[ $user->ID ];
	}

	public function clearForUser( \WP_User $user ) {
		unset( self::$records[ $user->ID ] );
	}
}