<?php declare( strict_types=1 );

namespace {
	if ( !\class_exists( 'WP_Error' ) ) {
		class WP_Error {
			public array $errors = [];

			public array $error_data = [];

			protected array $additional_data = [];

			public function __construct( $code = '', $message = '', $data = '' ) {
				if ( !empty( $code ) ) {
					$this->add( $code, $message, $data );
				}
			}

			public function add( $code, $message = '', $data = '' ) :void {
				$this->errors[ $code ][] = $message;
				if ( !empty( $data ) ) {
					$this->add_data( $data, $code );
				}
			}

			public function has_errors() :bool {
				return !empty( $this->errors );
			}

			public function get_error_code() {
				$codes = $this->get_error_codes();
				return empty( $codes ) ? '' : $codes[ 0 ];
			}

			public function get_error_codes() :array {
				return \array_keys( $this->errors );
			}

			public function get_error_message( $code = '' ) :string {
				if ( empty( $code ) ) {
					$code = $this->get_error_code();
				}
				$messages = $this->get_error_messages( $code );
				return empty( $messages ) ? '' : (string)$messages[ 0 ];
			}

			public function get_error_messages( $code = '' ) :array {
				if ( empty( $code ) ) {
					$allMessages = [];
					foreach ( $this->errors as $messages ) {
						$allMessages = \array_merge( $allMessages, $messages );
					}
					return $allMessages;
				}

				return $this->errors[ $code ] ?? [];
			}

			public function get_error_data( $code = '' ) {
				if ( empty( $code ) ) {
					$code = $this->get_error_code();
				}
				return $this->error_data[ $code ] ?? null;
			}

			public function add_data( $data, $code = '' ) :void {
				if ( empty( $code ) ) {
					$code = $this->get_error_code();
				}
				if ( isset( $this->error_data[ $code ] ) ) {
					$this->additional_data[ $code ][] = $this->error_data[ $code ];
				}
				$this->error_data[ $code ] = $data;
			}

			public function get_all_error_data( $code = '' ) :array {
				if ( empty( $code ) ) {
					$code = $this->get_error_code();
				}
				$data = $this->additional_data[ $code ] ?? [];
				if ( isset( $this->error_data[ $code ] ) ) {
					$data[] = $this->error_data[ $code ];
				}
				return $data;
			}

			public function remove( $code ) :void {
				unset( $this->errors[ $code ], $this->error_data[ $code ], $this->additional_data[ $code ] );
			}

			public function merge_from( WP_Error $error ) :void {
				static::copy_errors( $error, $this );
			}

			public function export_to( WP_Error $error ) :void {
				static::copy_errors( $this, $error );
			}

			protected static function copy_errors( WP_Error $from, WP_Error $to ) :void {
				foreach ( $from->get_error_codes() as $code ) {
					foreach ( $from->get_error_messages( $code ) as $errorMessage ) {
						$to->add( $code, $errorMessage );
					}
					foreach ( $from->get_all_error_data( $code ) as $data ) {
						$to->add_data( $data, $code );
					}
				}
			}
		}
	}

	if ( !\class_exists( 'WP_User' ) ) {
		class WP_User {
			public int $ID = 0;
			public string $user_login = '';
			public string $user_email = '';
			public string $user_pass = '';
			public string $display_name = '';
			public array $roles = [];
		}
	}

	if ( !\class_exists( 'WP_REST_Request' ) ) {
		class WP_REST_Request {

			private array $headers = [];

			private array $params = [];

			private string $method = '';

			private string $route = '';

			/**
			 * @param array<string,mixed>|string $arg1
			 * @param array<string,mixed>|string|null $arg2
			 * @param array<string,mixed> $arg3
			 */
			public function __construct( $arg1 = [], $arg2 = null, array $arg3 = [] ) {
				if ( \is_string( $arg1 ) ) {
					$this->method = $arg1;
					$this->route = \is_string( $arg2 ) ? $arg2 : '';
					$this->params = $arg3;
					return;
				}

				if ( \is_array( $arg1 ) && \is_array( $arg2 ) ) {
					foreach ( $arg1 as $name => $value ) {
						$this->headers[ \strtolower( (string)$name ) ] = (string)$value;
					}
					$this->params = $arg2;
					return;
				}

				$this->params = \is_array( $arg1 ) ? $arg1 : [];
			}

			public function get_header( string $name ) :string {
				return $this->headers[ \strtolower( $name ) ] ?? '';
			}

			public function get_method() :string {
				return $this->method;
			}

			public function get_route() :string {
				return $this->route;
			}

			public function get_param( string $key ) {
				return $this->params[ $key ] ?? null;
			}

			public function get_json_params() :array {
				return $this->params;
			}
		}
	}

	if ( !\class_exists( 'WP_REST_Response' ) ) {
		class WP_REST_Response {

			private $data;

			private int $status;

			public function __construct( $data = null, int $status = 200 ) {
				$this->data = $data;
				$this->status = $status;
			}

			public function get_data() {
				return $this->data;
			}

			public function get_status() :int {
				return $this->status;
			}
		}
	}

	if ( !\class_exists( 'WP_REST_Controller' ) ) {
		class WP_REST_Controller {
		}
	}

	if ( !\class_exists( 'WP_REST_Server' ) ) {
		class WP_REST_Server {
			public const READABLE = 'GET';
			public const CREATABLE = 'POST';
			public const EDITABLE = 'POST, PUT, PATCH';
			public const DELETABLE = 'DELETE';
		}
	}
}
