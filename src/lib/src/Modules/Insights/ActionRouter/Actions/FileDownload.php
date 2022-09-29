<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Utility\GetLogFileContent;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Utility\FileDownloadHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Export;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\DbTableExport;
use FernleafSystems\Wordpress\Services\Services;

class FileDownload extends BaseAction {

	const SLUG = 'file_download';

	protected function exec() {
		try {
			$id = Services::Request()->query( 'download_category' );
			if ( empty( $id ) ) {
				throw new \Exception( 'Invalid download request.' );
			}
			$contents = $this->getFileDownloadContents( $id );

			header( 'Set-Cookie: fileDownload=true; path=/' );
			Services::Response()->downloadStringAsFile(
				$contents[ 'content' ],
				$contents[ 'name' ]
			);
		}
		catch ( \Exception $e ) {
			error_log( $e->getMessage() );
			$resp = $this->response();
			$resp->success = false;
			$resp->message = $e->getMessage();
		}
	}

	/**
	 * @return array{name:string, content:string}
	 * @throws \Exception
	 */
	private function getFileDownloadContents( string $downloadID ) :array {
		$con = $this->getCon();

		switch ( $downloadID ) {

			case 'db_log':
				$fileDetails = [
					'name'    => sprintf( 'log_file-%s.json', date( 'Ymd_His' ) ),
					'content' => ( new GetLogFileContent() )
						->setMod( $con->getModule_AuditTrail() )
						->run()
				];
				break;

			case 'filelocker':
				$fileDetails = $con->getModule_HackGuard()
								   ->getFileLocker()
								   ->handleFileDownloadRequest();
				break;

			case 'scan_file':
				$fileDetails = ( new FileDownloadHandler() )
					->setMod( $con->getModule_HackGuard() )
					->downloadByItemId( (int)Services::Request()->query( 'rid', 0 ) );
				break;

			case 'db_ip':
				$fileDetails = ( new DbTableExport() )
					->setDbHandler( $con->getModule_IPs()->getDbH_IPRules() )
					->toCSV();
				break;

			case 'plugin_export':
				$fileDetails = ( new Export() )
					->setMod( $con->getModule_Plugin() )
					->toFile();
				break;

			default:
				throw new \Exception( 'Invalid download request.' );
		}

		return $fileDetails;
	}
}