<?php declare( strict_types=1 );

namespace {
	if ( !\class_exists( 'NF_Abstracts_Action', false ) ) {
		class NF_Abstracts_Action {
			/**
			 * @var string
			 */
			protected $_nicename = '';

			public function __construct( ...$args ) {
			}
		}
	}

	if ( !\class_exists( 'BuddyPress', false ) ) {
		class BuddyPress {
			/**
			 * @var bool
			 */
			public $buddyboss = true;

			/**
			 * @var object
			 */
			public $signup;

			public function __construct() {
				$this->signup = (object)[ 'errors' => [] ];
			}

			public static function instance() :self {
				static $instance;
				return $instance ??= new self();
			}
		}
	}

	if ( !\class_exists( 'Caldera_Forms', false ) ) {
		class Caldera_Forms {
			public static function form_redirect( ...$args ) :void {
			}

			public static function __callStatic( string $name, array $arguments ) {
				return null;
			}
		}
	}

	if ( !\class_exists( 'GFCommon', false ) ) {
		class GFCommon {
			public static function set_spam_filter( ...$args ) :void {
			}

			public static function __callStatic( string $name, array $arguments ) {
				return null;
			}
		}
	}

	if ( !\class_exists( 'GFForms', false ) ) {
		class GFForms {
			/**
			 * @var string
			 */
			public static $version = '0.0.0';
		}
	}

	if ( !\class_exists( 'Ninja_Forms', false ) ) {
		class Ninja_Forms {
			/**
			 * @var array
			 */
			public $actions = [];

			public static function instance() :self {
				static $instance;
				return $instance ??= new self();
			}
		}
	}

	if ( !\class_exists( 'SUPER_Common', false ) ) {
		class SUPER_Common {
			public static function output_message( ...$args ) {
				return null;
			}

			public static function __callStatic( string $name, array $arguments ) {
				return null;
			}
		}
	}

	if ( !\class_exists( 'SUPER_Forms', false ) ) {
		class SUPER_Forms {
			/**
			 * @var string
			 */
			public static $version = '0.0.0';

			public static function __callStatic( string $name, array $arguments ) {
				return null;
			}
		}
	}

	if ( !\class_exists( 'wordfence', false ) ) {
		class wordfence {
			public static function whitelistIP( ...$args ) :void {
			}

			public static function __callStatic( string $name, array $arguments ) {
				return null;
			}
		}
	}

	if ( !\function_exists( 'buddypress' ) ) {
		function buddypress() :BuddyPress {
			return BuddyPress::instance();
		}
	}

	if ( !\function_exists( 'edd_set_error' ) ) {
		function edd_set_error( ...$args ) :void {
		}
	}

	if ( !\function_exists( 'pms_errors' ) ) {
		function pms_errors() {
			return new class {
				public function add( ...$args ) :void {
				}
			};
		}
	}

	if ( !\function_exists( 'rcp_errors' ) ) {
		function rcp_errors() {
			return new class {
				public function add( ...$args ) :void {
				}
			};
		}
	}

	if ( !\function_exists( 'UM' ) ) {
		function UM() {
			return new class {
				public function __call( string $name, array $arguments ) {
					return $this;
				}
			};
		}
	}

	if ( !\function_exists( 'WPF' ) ) {
		function WPF() {
			return new class {
				public function __call( string $name, array $arguments ) {
					return $this;
				}
			};
		}
	}

	if ( !\function_exists( 'WP_Optimize' ) ) {
		function WP_Optimize() {
			return null;
		}
	}

	if ( !\function_exists( 'happyforms_get_version' ) ) {
		function happyforms_get_version() :string {
			return '0.0.0';
		}
	}

	if ( !\function_exists( 'wp_cache_clean_cache' ) ) {
		function wp_cache_clean_cache( ...$args ) :void {
		}
	}

	if ( !\function_exists( 'wp_cache_setting' ) ) {
		function wp_cache_setting( ...$args ) {
			return null;
		}
	}
}

namespace MainWP\Child {
	if ( !\class_exists( __NAMESPACE__.'\MainWP_Child', false ) ) {
		class MainWP_Child {
			/**
			 * @var string
			 */
			public static $version = '0.0.0';

			public static function __callStatic( string $name, array $arguments ) {
				return null;
			}
		}
	}

	if ( !\class_exists( __NAMESPACE__.'\MainWP_Connect', false ) ) {
		class MainWP_Connect {
			public static function instance() :self {
				static $instance;
				return $instance ??= new self();
			}

			public function auth( ...$args ) :array {
				return [];
			}

			public static function __callStatic( string $name, array $arguments ) {
				return null;
			}
		}
	}
}

namespace MainWP\Dashboard {
	if ( !\class_exists( __NAMESPACE__.'\MainWP_Connect', false ) ) {
		class MainWP_Connect {
			public static function fetch_url_authed( ...$args ) {
				return [];
			}

			public static function __callStatic( string $name, array $arguments ) {
				return null;
			}
		}
	}

	if ( !\class_exists( __NAMESPACE__.'\MainWP_DB', false ) ) {
		class MainWP_DB {
			public static function instance() :self {
				static $instance;
				return $instance ??= new self();
			}

			public function get_website_by_id( int $siteID ) :?\stdClass {
				return (object)[ 'id' => (string)$siteID ];
			}

			public function get_website_option( $website, string $optionKey ) :string {
				return '';
			}

			public function update_website_option( $website, string $optionKey, string $optionValue ) :void {
			}

			public static function __callStatic( string $name, array $arguments ) {
				return null;
			}
		}
	}

	if ( !\class_exists( __NAMESPACE__.'\MainWP_Extensions_Handler', false ) ) {
		class MainWP_Extensions_Handler {
			public static function get_extensions() :array {
				return [];
			}

			public static function __callStatic( string $name, array $arguments ) {
				return null;
			}
		}
	}

	if ( !\class_exists( __NAMESPACE__.'\MainWP_Extensions_Groups', false ) ) {
		class MainWP_Extensions_Groups {
			public static function add_extension_menu( array $args ) :void {
			}
		}
	}

	if ( !\class_exists( __NAMESPACE__.'\MainWP_Sync', false ) ) {
		class MainWP_Sync {
			public static function sync_site( ...$args ) :array {
				return [];
			}

			public static function __callStatic( string $name, array $arguments ) {
				return null;
			}
		}
	}
}

namespace FluentForm\App {
	if ( !\class_exists( __NAMESPACE__.'\App', false ) ) {
		class App {
			/**
			 * @var object
			 */
			public $formSubmissionHandler;

			public function __construct() {
				$this->formSubmissionHandler = new class {
					public function __call( string $name, array $arguments ) {
						return null;
					}
				};
			}

			public static function getInstance() :self {
				static $instance;
				return $instance ??= new self();
			}

			public function addAction( ...$args ) :void {
			}
		}
	}
}

namespace ElementorPro\Modules\Forms\Classes {
	if ( !\class_exists( __NAMESPACE__.'\Ajax_Handler', false ) ) {
		class Ajax_Handler {
			/**
			 * @var array
			 */
			public $errors = [];

			public function add_error( string $key, string $message ) :void {
			}

			public function add_error_message( string $message ) :void {
			}
		}
	}
}
