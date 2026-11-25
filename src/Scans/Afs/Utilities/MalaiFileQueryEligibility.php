<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\ResultItem;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\File\Paths;

class MalaiFileQueryEligibility {

	private const SUPPORTED_EXTENSIONS = [
		'php',
		'php7',
		'phtml',
		'phtm',
		'ico',
	];

	/**
	 * @throws \Exception
	 */
	public function assertCanOfferQuery( ResultItem $item ) :string {
		$pathFull = (string)$item->path_full;

		if ( !Services::WpFs()->isAccessibleFile( $pathFull ) ) {
			throw new \Exception( __( "This file doesn't appear to be available on this site any longer.", 'wp-simple-firewall' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain-text message: ScansMalaiFileQuery returns it in an AJAX response, which the page shows as text, and Malai::getRenderData() passes it to ActionRoutingController::render(), which escapes it.
		}

		$extension = \strtolower( Paths::Ext( $pathFull ) );
		if ( !\in_array( $extension, self::SUPPORTED_EXTENSIONS, true ) ) {
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain-text message: ScansMalaiFileQuery returns it in an AJAX response, which the page shows as text, and Malai::getRenderData() passes it to ActionRoutingController::render(), which escapes it.
			throw new \Exception( sprintf(
				/* translators: %s: file extension, such as php */
				__( "The file type/extension (%s) isn't supported by the MAL{ai} engine.", 'wp-simple-firewall' ),
				Paths::Ext( $pathFull )
			) );
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		if ( $item->is_mal ) {
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain-text message: ScansMalaiFileQuery returns it in an AJAX response, which the page shows as text, and Malai::getRenderData() passes it to ActionRoutingController::render(), which escapes it.
			throw new \Exception(
				__( 'This file has already been classified as potential malware in the scan results.', 'wp-simple-firewall' )
			);
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		return $pathFull;
	}

	/**
	 * @throws \Exception
	 */
	public function assertCanSubmitQuery( ResultItem $item ) :string {
		$pathFull = $this->assertCanOfferQuery( $item );

		if ( Services::WpFs()->getFileSize( $pathFull ) === 0 ) {
			throw new \Exception( __( 'The file is empty.', 'wp-simple-firewall' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain-text message: ScansMalaiFileQuery returns it in an AJAX response, which the page shows as text.
		}

		return $pathFull;
	}
}
