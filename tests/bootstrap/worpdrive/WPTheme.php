<?php declare( strict_types=1 );

if ( !\class_exists( 'WP_Theme' ) ) {
	class WP_Theme {

		private string $stylesheet;

		private string $name;

		private string $version;

		public function __construct( string $stylesheet, string $name, string $version ) {
			$this->stylesheet = $stylesheet;
			$this->name = $name;
			$this->version = $version;
		}

		public function get_stylesheet() :string {
			return $this->stylesheet;
		}

		public function get( string $key ) :string {
			return $key === 'Version' ? $this->version : ( $key === 'Name' ? $this->name : '' );
		}
	}
}
