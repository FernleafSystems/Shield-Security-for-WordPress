<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Filesystem\Map\Listing;

class FlatFileListing extends AbstractFileListing {

	/**
	 * @var resource
	 */
	private $file;

	private array $buffer = [];

	/**
	 * @throws \Exception
	 */
	public function startLargeListing() :void {
		if ( \is_resource( $this->file ) ) {
			throw new \Exception( 'File is already open for writing.' );
		}
		$this->file = \fopen( $this->listingPath, 'a' );
		if ( !\is_resource( $this->file ) ) {
			throw new \Exception( 'Resource not provided' );
		}
		$this->buffer = [];
	}

	public function finishLargeListing( bool $successfulCreation ) :void {
		$this->flush();
		\fclose( $this->file );
	}

	public function addRaw( string $path, string $hash = '', string $hashAlt = '', ?int $mtime = null, ?int $size = null ) :void {
		$this->buffer[] = [
			\base64_encode( $path ),
			$hashAlt,
			(int)$mtime,
			(int)$size,
		];

		if ( \count( $this->buffer ) >= 100 ) {
			$this->flush();
		}
	}

	private function flush() {
		if ( !empty( $this->buffer ) ) {
			\fwrite(
				$this->file,
				"\n".\implode( "\n", \array_map( fn( array $item ) => \implode( "::", $item ), $this->buffer ) )
			);
		}
		$this->buffer = [];
	}
}