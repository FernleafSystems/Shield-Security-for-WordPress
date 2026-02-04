<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Utility;

class FileNameFor {

	public static function For( string $type ) :string {
		switch ( $type ) {
			case 'hashless_map_progress':
			case 'recent_map_progress':
			case 'full_map_progress':
				$name = \sprintf( '%s.json', $type );
				break;
			case 'hashless_map_db':
			case 'recent_map_db':
			case 'full_map_db':
				$name = \sprintf( '%s.%s', $type, \in_array( 'sqlite3', \get_loaded_extensions() ) ? 'sqlite3' : 'flat' );
				break;
			case 'files_zip':
				$name = 'zipped_files.archive';
				break;
			case 'db_exports_zip':
				$name = 'zipped_db_exp.archive';
				break;
			case 'db_schema_zip':
				$name = 'zipped_db_schema.archive';
				break;
			default:
				$name = $type;
				break;
		}
		return $name;
	}
}