<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Database\Operators;

class SqlDumpIdentifierEscaper {

	public function escape( string $identifier ) :string {
		return '`'.\str_replace( '`', '``', $identifier ).'`';
	}

	public function escapeAll( array $identifiers ) :array {
		return \array_map(
			fn( string $identifier ) => $this->escape( $identifier ),
			$identifiers
		);
	}
}
