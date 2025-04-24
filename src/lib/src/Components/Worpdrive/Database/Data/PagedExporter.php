<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Database\Data;

use FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Exc\TimeLimitReachedException;
use FernleafSystems\Wordpress\Services\Services;

class PagedExporter {

	private string $dumpFileDir;

	private ExportMap $exportMap;

	private int $stopAtTS;

	public function __construct( string $dumpFileDir, ExportMap $exportMap, int $stopAtTS ) {
		$this->dumpFileDir = $dumpFileDir;
		$this->exportMap = $exportMap;
		$this->stopAtTS = $stopAtTS;
	}

	/**
	 * @throws TimeLimitReachedException
	 * @throws \Exception
	 */
	public function run() :void {
		foreach ( \array_filter( $this->exportMap->status(), fn( array $s ) => empty( $s[ 'completed_at' ] ) ) as $table => $status ) {
			do {
				$dumpFile = \fopen( $this->dumpFileFor( $table, $status[ 'page' ] ), 'w' );
				try {
					$chunkExportStatus = ( new ChunkedExporter(
						$dumpFile,
						$table,
						$status[ 'offset' ],
						$status[ 'max_page_rows' ] ?? 1000,
						$status[ 'chunk_size' ]
					) )->run();

					$status[ 'offset' ] = $chunkExportStatus[ 'current_offset' ];
					$status[ 'page' ]++;
					$status[ 'completed_at' ] = $chunkExportStatus[ 'table_export_complete' ] ? \time() : 0;
					$status[ 'exported_rows' ] += $chunkExportStatus[ 'exported_rows' ];
					$this->exportMap->updateStatus( $table, $status );

					if ( \time() >= $this->stopAtTS ) {
						throw new TimeLimitReachedException();
					}
				}
				finally {
					if ( \is_resource( $dumpFile ) ) {
						\fclose( $dumpFile );
					}
				}
			} while ( empty( $status[ 'completed_at' ] ) );
		}
	}

	private function dumpFileFor( string $table, int $page ) :string {
		$file = path_join(
			$this->dumpFileDir,
			sprintf( 'data_%s_%s.sql',
				\preg_replace(
					sprintf( "#^%s#", \preg_quote( Services::WpDb()->getPrefix(), '#' ) ),
					'',
					$table
				),
				$page
			)
		);
		if ( \is_file( $file ) ) {
			Services::WpFs()->deleteFile( $file );
		}
		return $file;
	}
}