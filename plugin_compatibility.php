<?php declare( strict_types=1 );

new class() {

	private ?string $incompatible = null;

	private ?string $shield = null;

	public function __construct() {
		if ( \function_exists( 'wp_get_active_and_valid_plugins' ) ) {
			foreach ( wp_get_active_and_valid_plugins() as $file ) {
				if ( \str_ends_with( $file, '/wp-rss-aggregator.php' ) && \function_exists( 'get_plugin_data' ) ) {
					$data = get_plugin_data( $file );
					if ( !empty( $data[ 'Version' ] ) && \version_compare( (string)$data[ 'Version' ], '5.0', '<' ) ) {
						$this->incompatible = $data[ 'Name' ] ?? \basename( $file );
					}
				}
				elseif ( \str_ends_with( $file, '/icwp-wpsf.php' ) ) {
					$this->shield = $file;
				}
			}

			if ( !empty( $this->incompatible ) && !empty( $this->shield )
				 && !\file_exists( path_join( \dirname( $this->shield ), 'ignore_incompatibilities' ) ) ) {
				add_action( 'admin_notices', fn() => $this->showIncompatibleNotice() );
				add_action( 'network_admin_notices', fn() => $this->showIncompatibleNotice() );
				throw new \Exception( 'Incompatible plugin discovered.' );
			}
		}
	}

	private function showIncompatibleNotice() :void {
		echo sprintf(
			'<div class="error"><h4 style="margin-bottom: 7px;">%s</h4><p>%s</p></div>',
			sprintf( 'Shield Security - Potential Incompatible Plugin Detected: %s', esc_html( $this->incompatible ) ),
			\implode( '<br/>', [
				'Shield Security has detected that another plugin active on your site is potentially incompatible and may cause errors while running alongside Shield.',
				"To prevent crashing your site, Shield won't run and you may chose to deactivate the incompatible plugin.",
				sprintf( 'The incompatible plugin is: <strong>%s</strong>', esc_html( $this->incompatible ) )
			] ),
		);
	}
};