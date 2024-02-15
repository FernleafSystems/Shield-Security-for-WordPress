<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Processing;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\Malware\Ops as MalwareDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes\Malai;

class ReportToMalai {

	use ModConsumer;

	public function run( int $limit = 25 ) :array {
		$dbh = self::con()->db_con->dbhMalware();
		/** @var MalwareDB\Update $updater */
		$updater = $dbh->getQueryUpdater();
		/** @var MalwareDB\Select $select */
		$select = $dbh->getQuerySelector();
		/** @var MalwareDB\Record[] $malwares */
		$malwares = $select->filterByUnreported()
						   ->setLimit( $limit )
						   ->queryWithResult();

		$reports = [];
		foreach ( $malwares as $malware ) {
			$updateSuccess = $updater->updateRecord( $malware, [
				'reported_at' => Services::Request()->ts(),
			] );
			if ( $updateSuccess ) {
				$reports[ $malware->hash_sha256 ] = [
					'file_name'    => \basename( $malware->file_path ),
					'file_content' => \base64_encode( $malware->file_content ),
					'code_type'    => $malware->code_type,
				];
			}
		}

		if ( !empty( $reports ) ) {
			$token = self::con()
						 ->getModule_License()
						 ->getWpHashesTokenManager()
						 ->getToken();
			( new Malai\MalwareReport( $token ) )->report(
				\array_intersect_key(
					$reports,
					\array_flip( ( new Malai\ObtainAcceptableHashes( $token ) )->getAcceptableHashes( \array_keys( $reports ) ) )
				)
			);
		}
		return $reports;
	}
}