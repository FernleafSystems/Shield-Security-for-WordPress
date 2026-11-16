<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits;

use FernleafSystems\Wordpress\Services\Utilities\File\Download\IssueFileDownloadResponse;

trait IssuesFileDownloads {

	/**
	 * The library's own Content-Disposition is malformed (no ";" between parameters, and a "filename*" that isn't
	 * RFC 5987), so browsers save the file under a garbled name. This sends a well-formed one in its place.
	 */
	private function fileDownloadResponse( string $filename ) :IssueFileDownloadResponse {
		return new IssueFileDownloadResponse( $filename, [
			'Content-Disposition' => \sprintf(
				'attachment; filename="%s"; filename*=UTF-8\'\'%s',
				\preg_replace( '#[^\x20-\x7e]|["\\\\]#', '_', $filename ),
				\rawurlencode( $filename )
			),
		] );
	}
}
