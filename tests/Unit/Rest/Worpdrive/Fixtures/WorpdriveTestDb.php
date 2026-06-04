<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Rest\Worpdrive\Fixtures;

use FernleafSystems\Wordpress\Services\Core\Db;

class WorpdriveTestDb extends Db {

	public array $calls = [];

	private ?array $tableStatus = null;

	private int $optionsRowsQueryCount = 0;

	public function setTableStatus( array $tableStatus ) :self {
		$this->tableStatus = $tableStatus;
		return $this;
	}

	public function getPrefix( bool $siteBase = true ) :string {
		$this->calls[] = [ 'getPrefix', $siteBase ];
		return $siteBase ? 'wp_' : 'wp_site_';
	}

	public function loadWpdb() :\wpdb {
		$this->calls[] = [ 'loadWpdb' ];
		return new class extends \wpdb {
			public function _real_escape( string $value ) :string {
				return \addslashes( $value );
			}

			public function remove_placeholder_escape( string $value ) :string {
				return $value;
			}
		};
	}

	public function selectCustom( $query, $format = \ARRAY_A ) {
		$this->calls[] = [ 'selectCustom', $query, $format ];
		$query = \preg_replace( '#\s+#', ' ', \trim( (string)$query ) );

		if ( $query === 'SHOW FULL COLUMNS FROM `wp_options`' ) {
			return $this->wpOptionsColumns();
		}

		if ( $query === 'SHOW INDEX FROM `wp_options`' ) {
			return [
				[
					'Table'        => 'wp_options',
					'Non_unique'   => 0,
					'Key_name'     => 'PRIMARY',
					'Seq_in_index' => 1,
					'Column_name'  => 'option_id',
					'Sub_part'     => null,
				],
			];
		}

		if ( $query === 'SHOW CREATE TABLE `wp_options`' ) {
			return [
				[
					'Table'        => 'wp_options',
					'Create Table' => 'CREATE TABLE `wp_options` (`option_id` bigint unsigned NOT NULL AUTO_INCREMENT, `option_name` varchar(191) NOT NULL, `option_value` longtext NOT NULL, `autoload` varchar(20) NOT NULL, PRIMARY KEY (`option_id`)) ENGINE=InnoDB',
				],
			];
		}

		if ( \preg_match( '#^SELECT `option_id`, `option_name`, `option_value`, `autoload` FROM `wp_options`(?: WHERE `option_id` (?:>=|>) \d+)? ORDER BY `option_id` ASC LIMIT \d+ OFFSET \d+;$#', $query ) ) {
			return ++$this->optionsRowsQueryCount === 1 ? [
				[
					'option_id'    => 1,
					'option_name'  => 'siteurl',
					'option_value' => 'https://shield.test',
					'autoload'     => 'yes',
				],
			] : [];
		}

		return [ [ 'query' => $query ] ];
	}

	public function doSql( string $sql ) {
		$this->calls[] = [ 'doSql', $sql ];
		return true;
	}

	public function getVar( $sql ) {
		$this->calls[] = [ 'getVar', $sql ];
		$sql = \preg_replace( '#\s+#', ' ', \trim( (string)$sql ) );
		if ( $sql === 'SELECT @@SESSION.sql_mode' ) {
			return 'NO_ENGINE_SUBSTITUTION';
		}
		if ( $sql === 'SELECT COUNT(*) AS `total_records` FROM `wp_options`' ) {
			return 1;
		}
		return 'value';
	}

	public function showTableStatus( $format = \OBJECT ) {
		$this->calls[] = [ 'showTableStatus', $format ];
		return $this->tableStatus ?? [
			[ 'Name' => 'wp_posts' ],
		];
	}

	private function wpOptionsColumns() :array {
		return [
			[
				'Field'      => 'option_id',
				'Type'       => 'bigint unsigned',
				'Null'       => 'NO',
				'Key'        => 'PRI',
				'Default'    => null,
				'Extra'      => 'auto_increment',
				'Collation'  => null,
				'Privileges' => 'select,insert,update,references',
				'Comment'    => '',
			],
			[
				'Field'      => 'option_name',
				'Type'       => 'varchar(191)',
				'Null'       => 'NO',
				'Key'        => 'UNI',
				'Default'    => '',
				'Extra'      => '',
				'Collation'  => 'utf8mb4_unicode_ci',
				'Privileges' => 'select,insert,update,references',
				'Comment'    => '',
			],
			[
				'Field'      => 'option_value',
				'Type'       => 'longtext',
				'Null'       => 'NO',
				'Key'        => '',
				'Default'    => null,
				'Extra'      => '',
				'Collation'  => 'utf8mb4_unicode_ci',
				'Privileges' => 'select,insert,update,references',
				'Comment'    => '',
			],
			[
				'Field'      => 'autoload',
				'Type'       => 'varchar(20)',
				'Null'       => 'NO',
				'Key'        => '',
				'Default'    => 'yes',
				'Extra'      => '',
				'Collation'  => 'utf8mb4_unicode_ci',
				'Privileges' => 'select,insert,update,references',
				'Comment'    => '',
			],
		];
	}
}
