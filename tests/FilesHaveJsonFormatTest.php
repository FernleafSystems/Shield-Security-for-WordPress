<?php

class FilesHaveJsonFormatTest extends \PHPUnit_Framework_TestCase {

    public function testAllConfigFilesHaveValidJSONContent() {
        $allItems = scandir($this->getConfigFilesPath());
        $files = $this->filterFiles($allItems);

        foreach( $files as $file ) {
            $jsonContent = file_get_contents($this->getConfigFilesPath($file));
            $jsonParsed = \json_decode($jsonContent);

            $this->assertNotNull($jsonParsed);
        }
    }

    private function getConfigFilesPath( $path = '')
    {
        return __DIR__ . '/../src/config/' . $path;
    }

    private function filterFiles( $dirs )
    {
        return array_filter( $dirs, function( $dir ) {
            return !is_dir($this->getConfigFilesPath($dir));
        });
    }
}
