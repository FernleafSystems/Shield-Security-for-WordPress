<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive;

use FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Utility\FileNameFor;

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
		$file = path_join( $this->workingDir(), FileNameFor::For( $this->downloadType ) );
		if ( !\is_file( $file ) ) {
			throw new \Exception( sprintf( 'File type "%s" file path "%s" does not exist.', $this->downloadType, $file ) );
		}
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