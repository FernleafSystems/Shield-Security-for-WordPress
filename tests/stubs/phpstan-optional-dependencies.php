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
		function buddypress() {
			return null;
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
		}
	}
}

namespace AptowebDeps\Monolog\Processor {
	if ( !\interface_exists( __NAMESPACE__.'\ProcessorInterface', false ) ) {
		interface ProcessorInterface {
		}
	}
}

namespace AptowebDeps\Monolog\Handler {
	if ( !\class_exists( __NAMESPACE__.'\AbstractProcessingHandler', false ) ) {
		abstract class AbstractProcessingHandler {
			public function __construct( ...$args ) {
			}
		}
	}

	if ( !\class_exists( __NAMESPACE__.'\FilterHandler', false ) ) {
		class FilterHandler extends AbstractProcessingHandler {
		}
	}
}

namespace AptowebDeps\Monolog {
	if ( !\class_exists( __NAMESPACE__.'\Logger', false ) ) {
		class Logger {
			public function __construct( ...$args ) {
			}

			public function __call( string $name, array $arguments ) {
				return null;
			}
		}
	}
}

namespace AptowebDeps\Twig\Loader {
	if ( !\class_exists( __NAMESPACE__.'\FilesystemLoader', false ) ) {
		class FilesystemLoader {
			public function __construct( ...$args ) {
			}
		}
	}
}

namespace AptowebDeps\Twig {
	if ( !\class_exists( __NAMESPACE__.'\Environment', false ) ) {
		class Environment {
			public function __construct( ...$args ) {
			}
		}
	}
}

namespace AptowebDeps\CrowdSec\CapiClient {
	if ( !\class_exists( __NAMESPACE__.'\ClientException', false ) ) {
		class ClientException extends \Exception {
		}
	}

	if ( !\class_exists( __NAMESPACE__.'\Watcher', false ) ) {
		class Watcher {
			public function __construct( ...$args ) {
			}

			public function __call( string $name, array $arguments ) {
				return [];
			}
		}
	}
}

namespace AptowebDeps\CrowdSec\CapiClient\Client\CapiHandler {
	if ( !\interface_exists( __NAMESPACE__.'\CapiHandlerInterface', false ) ) {
		interface CapiHandlerInterface {
		}
	}
}

namespace AptowebDeps\CrowdSec\CapiClient\Storage {
	if ( !\interface_exists( __NAMESPACE__.'\StorageInterface', false ) ) {
		interface StorageInterface {
		}
	}
}

namespace AptowebDeps\CrowdSec\Common\Client {
	if ( !\class_exists( __NAMESPACE__.'\ClientException', false ) ) {
		class ClientException extends \Exception {
		}
	}
}

namespace AptowebDeps\CrowdSec\Common\Client\HttpMessage {
	if ( !\class_exists( __NAMESPACE__.'\Request', false ) ) {
		class Request {
			public function __construct( ...$args ) {
			}
		}
	}

	if ( !\class_exists( __NAMESPACE__.'\Response', false ) ) {
		class Response {
			public function __construct( ...$args ) {
			}
		}
	}
}
