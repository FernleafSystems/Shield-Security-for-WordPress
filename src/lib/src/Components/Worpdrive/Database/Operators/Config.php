<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Database\Operators;

class Config {

	private array $settings;

	public function __construct() {
		$this->settings = [];
	}

	/**
	 * @return string
	 */
	public function __toString() {
		$sQuoteChar = ( \strtoupper( \substr( PHP_OS, 0, 3 ) ) === 'WIN' ) ? '"' : "'";
		$aOptions = $this->availableOptions();
		$aParams = [];

		$sDatabases = '';
		$sTables = '';
		foreach ( $this->settings as $sKey => $mValue ) {
			if ( !isset( $aOptions[ $sKey ] ) ) {
				trigger_error( sprintf( "Invalid option '%s' specified", $sKey ) );
				continue;
			}

			if ( \in_array( $sKey, [ 'database', 'databases' ] ) ) {
				$sDatabases = $mValue;
				continue;
			}

			if ( \in_array( $sKey, [ 'tables' ] ) ) {
				$sTables = \implode( ' ', $mValue );
				continue;
			}

			$bNoQuote = ( \is_null( $mValue ) || \is_bool( $mValue ) || \is_int( $mValue ) || isset( $aOptions[ $sKey ][ 'noquote' ] ) );
			$bHasValue = !( \is_null( $mValue ) || \is_bool( $mValue ) );
			$bHasEquals = !isset( $aOptions[ $sKey ][ 'noequals' ] );
			$aParams[] = '--'.$sKey.( $bHasValue ? ( $bHasEquals ? '=' : ' ' ).( $bNoQuote ? '' : $sQuoteChar ).$mValue.( $bNoQuote ? '' : $sQuoteChar ) : '' ); // make the mValue safe by escaping '
		}
		return \implode( ' ', $aParams )
			   .( empty( $sDatabases ) ? '' : ' '.( strpos( $sDatabases, ' ' ) === false ? $sDatabases : '--databases '.$sDatabases ) )
			   .( empty( $sTables ) ? '' : ' '.$sTables );
	}

	/**
	 * @param string $sKey
	 * @param mixed  $mReturnDefault
	 * @return mixed
	 */
	public function get( $sKey, $mReturnDefault = null ) {
		if ( !$this->has( $sKey ) ) {
			return $mReturnDefault;
		}
		return $this->settings[ $sKey ];
	}

	public function has( string $key ) :bool {
		return isset( $this->settings[ $key ] );
	}

	/**
	 * @param mixed $mValue
	 */
	public function set( string $key, $mValue ) :Config {
		$this->settings[ $key ] = $mValue;
		return $this;
	}

	public function applyDumpSchemaOptions() :Config {
		$this->settings = \array_merge( $this->settings, [
			'add-drop-table' => true,
			'add-locks'      => true,
			'create-options' => true,
			'disable-keys'   => true,
			'lock-tables'    => true,
			'quick'          => true,
			'set-charset'    => true,
			'no-data'        => true,
			'no-create-db'   => true,
			'hex-blob'       => true,
			'comments'       => true
		] );
		return $this;
	}

	public function applyDumpDataOptions() :Config {
		$this->settings = array_merge( $this->settings, [
			'add-locks'            => true,
			'create-options'       => true,
			'disable-keys'         => true,
			'lock-tables'          => true,
			'quick'                => true,
			'set-charset'          => true,
			'no-create-db'         => true,
			'no-create-info'       => true,
			'hex-blob'             => true,
			'skip-extended-insert' => true,
			'comments'             => true
			//'order-by-primary' => true
		] );
		return $this;
	}

	public function availableOptions() :array {
		return [
			'add-drop-database'     => [ 'comment' => 'Add a DROP DATABASE statement before each CREATE DATABASE statement' ],
			'add-drop-table'        => [ 'comment' => 'Add a DROP TABLE statement before each CREATE TABLE statement' ],
			//'add-drop-trigger'		=> array( 'comment' => 'Add a DROP TRIGGER statement before each CREATE TRIGGER statement', 'version' => '5.1.47' ),
			'add-locks'             => [ 'comment' => 'Surround each table dump with LOCK TABLES and UNLOCK TABLES statements' ],
			//'all-databases'			=> array( 'comment' => 'Dump all tables in all databases' ),
			//'all-tablespaces'		=> array( 'comment' => 'Adds to a table dump all SQL statements needed to create any tablespaces used by an NDB Cluster table', 'version' => '5.1.6' ),
			//'allow-keywords'		=> array( 'comment' => 'Allow creation of column names that are keywords' ),
			'comments'              => [ 'comment' => 'Add comments to the dump file' ],
			'compact'               => [ 'comment' => 'Produce more compact output' ],
			'complete-insert'       => [ 'comment' => 'Use complete INSERT statements that include column names' ],
			'create-options'        => [ 'comment' => 'Include all MySQL-specific table options in CREATE TABLE statements' ],
			'default-character-set' => [
				'comment' => 'Use charset_name as the default character set',
				'value'   => 'utf8'
			],
			'delayed-insert'        => [ 'comment' => 'Write INSERT DELAYED statements rather than INSERT statements' ],
			'disable-keys'          => [ 'comment' => 'For each table, surround the INSERT statements with statements to disable and enable keys' ],
			'dump-date'             => [
				'comment' => 'Include dump date as "Dump completed on" comment if --comments is given',
				'version' => '5.1.23'
			],
			'extended-insert'       => [ 'comment' => 'Use multiple-row INSERT syntax that include several VALUES lists' ],
			'hex-blob'              => [ 'comment' => '' ],
			'insert-ignore'         => [ 'comment' => 'Write INSERT IGNORE statements rather than INSERT statements' ],
			'lock-tables'           => [ 'comment' => 'Lock all tables before dumping them' ],
			'quick'                 => [ 'comment' => 'Retrieve rows for a table from the server a row at a time' ],
			'set-charset'           => [ 'comment' => 'Add SET NAMES default_character_set to the output' ],
			'no-data'               => [ 'comment' => 'Do not dump table contents' ],
			'no-create-db'          => [ 'comment' => 'This option suppresses the CREATE DATABASE statements' ],
			'no-create-info'        => [ 'comment' => 'Do not write CREATE TABLE statements that re-create each dumped table' ],
			'single-transaction'    => [ 'comment' => 'This option issues a BEGIN SQL statement before dumping data from the server' ],
			'order-by-primary'      => [ 'comment' => 'Dump each table\'s rows sorted by its primary key, or by its first unique index' ],
			'replace'               => [
				'comment' => 'Write REPLACE statements rather than INSERT statements',
				'version' => '5.1.3'
			],
			'skip-add-drop-table'   => [ 'comment' => 'Do not add a DROP TABLE statement before each CREATE TABLE statement' ],
			'skip-comments'         => [ 'comment' => 'Do not add comments to the dump file' ],
			'skip-dump-date'        => [ 'comment' => '' ],
			'skip-extended-insert'  => [ 'comment' => 'Turn off extended-insert' ],
			'skip-set-charset'      => [ 'comment' => 'Suppress the SET NAMES statement' ],
			'skip-tz-utc'           => [ 'comment' => 'Turn off tz-utc', 'version' => '5.1.2' ],
			'tz-utc'                => [ 'comment' => '' ],
			'host'                  => [ 'comment' => '', 'value' => '' ],
			'socket'                => [ 'comment' => '', 'value' => null ],
			'port'                  => [ 'comment' => '', 'value' => null, 'noquote' => true ],
			'user'                  => [ 'comment' => '', 'value' => '' ],
			'password'              => [ 'comment' => '', 'value' => null ],
			'database'              => [ 'comment' => '', 'value' => null, 'noequals' => true, 'nokey' => true ],
			'databases'             => [ 'comment' => '', 'value' => null, 'noequals' => true ],
			'result-file'           => [ 'comment' => '', 'value' => null ],
			'tables'                => [ 'comment' => '', 'value' => null, 'noequals' => true ],
			'where'                 => [ 'comment' => 'Dump only rows selected by the given WHERE condition' ]
		];
	}
}