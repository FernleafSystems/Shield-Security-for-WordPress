<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive;

use FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Utility\LocateFilesForType;

class Download extends BaseHandler {

	private string $downloadType;

	/**
	 * @throws \Exception
	 */
	public function __construct( string $downloadType, string $uuid, int $stopAtTS ) {
		parent::__construct( $uuid, $stopAtTS );
		$this->downloadType = $downloadType;
	}

	/**
	 * @throws \Exception
	 */
	public function run() :array {
		$files = ( new LocateFilesForType() )->find( $this->workingDir(), $this->downloadType );
		if ( \count( $files ) === 0 ) {
			throw new \Exception( sprintf( 'Files of type "%s" does not exist.', $this->downloadType ) );
		}
		if ( \count( $files ) > 1 ) {
			throw new \Exception( sprintf( 'There is more than 1 file of type "%s".', $this->downloadType ) );
		}
		$file = \current( $files );
		\header( "Pragma: public" );
		\header( "Expires: 0" );
		\header( "Cache-Control: must-revalidate, post-check=0, pre-check=0" );
		\header( "Cache-Control: public" );
		\header( "Content-Description: File Transfer" );
		\header( "Content-type: application/octet-stream" );
		\header( 'Content-Disposition: attachment; filename="'.\basename( $file ).'"' );
		\header( "Content-Transfer-Encoding: binary" );
		\header( "Content-Length: ".\filesize( $file ) );
		@\readfile( $file );
		die();
	}
}