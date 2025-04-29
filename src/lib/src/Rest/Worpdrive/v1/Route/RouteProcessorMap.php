<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\Worpdrive\v1\Route;

use FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\{
	Clean as WdClean,
	CompatibilityChecks as WdCheck,
	Database\Data\DataExportHandler,
	Database\Schema\SchemaHandler,
	Download as WdDownload,
	Filesystem\Map,
	Filesystem\Zip\ZipHandler
};
use FernleafSystems\Wordpress\Plugin\Shield\Rest\Worpdrive\Utility\BuildTimeLimit;
use WP_REST_Request as Req;

class RouteProcessorMap {

	/**
	 * @return \Closure[]
	 */
	public static function Map() :array {
		return [
			Clean::class    => fn( Req $req ) => ( new WdClean( $req->get_param( 'uuid' ), 0 ) )->run(),
			Checks::class   => fn( Req $req ) => ( new WdCheck( $req->get_param( 'uuid' ), 0 ) )->run(),
			Download::class => fn( Req $req ) => ( new WdDownload( $req->get_param( 'download_type' ), $req->get_param( 'uuid' ), 0 ) )->run(),

			FilesystemMap::class => function ( Req $req ) {
				$mapVO = new Map\MapVO();
				$mapVO->type = $req->get_param( 'type' ) === 'map' ? 'full' : $req->get_param( 'type' );
				$mapVO->dir = $req->get_param( 'dir' );
				$mapVO->exclusions = $req->get_param( 'file_exclusions' );
				$mapVO->newerThanTS = $req->get_param( 'newer_than_ts' ) ?: 0;
				if ( $mapVO->type === 'hashless' ) {
					$mapVO->hashAlgo = '';
				}
				return ( new Map\MapHandler(
					$mapVO,
					$req->get_param( 'uuid' ),
					BuildTimeLimit::Build( $req->get_param( 'time_limit' ) )
				) )->run();
			},

			FilesystemZip::class => fn( Req $req ) => ( new ZipHandler(
				\array_map( '\base64_decode', $req->get_param( 'file_paths' ) ),
				$req->get_param( 'dir' ),
				$req->get_param( 'uuid' ),
				BuildTimeLimit::Build( $req->get_param( 'time_limit' ) )
			) )->run(),

			DatabaseSchema::class => fn( Req $req ) => ( new SchemaHandler(
				$req->get_param( 'dump_method' ),
				$req->get_param( 'uuid' ),
				BuildTimeLimit::Build( $req->get_param( 'time_limit' ) )
			) )->run(),

			DatabaseData::class => fn( Req $req ) => ( new DataExportHandler(
				$req->get_param( 'table_export_map' ),
				$req->get_param( 'uuid' ),
				BuildTimeLimit::Build( $req->get_param( 'time_limit' ) )
			) )->run(),
		];
	}
}