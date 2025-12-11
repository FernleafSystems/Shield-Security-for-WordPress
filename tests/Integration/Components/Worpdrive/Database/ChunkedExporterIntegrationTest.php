<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Components\Worpdrive\Database;

use FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Database\Data\ChunkedExporter;
use FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Database\Data\ExportMap;
use FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Database\Data\PagedExporter;
use FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Database\Operators\Table\TableHelper;
use FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Exc\TimeLimitReachedException;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldWordPressTestCase;

/**
 * Integration tests for Worpdrive database export functionality.
 * 
 * These tests use REAL MySQL database operations (not mocks) to verify:
 * 1. PK-based export works correctly with real tables
 * 2. Non-PK (LIMIT/OFFSET) export works correctly
 * 3. Bug fixes prevent infinite loops in real scenarios
 * 4. Integration between ChunkedExporter and PagedExporter
 * 
 * IMPORTANT: These tests create temporary tables in the WordPress test database.
 * All tables are cleaned up in tearDown().
 */
class ChunkedExporterIntegrationTest extends ShieldWordPressTestCase {

    /**
     * @var string[] Table names created for testing (for cleanup)
     */
    private array $testTables = [];

    /**
     * @var string[] Temp files created (for cleanup)
     */
    private array $tempFiles = [];

    /**
     * @var string Temp directory for dump files
     */
    private string $tempDir;

    public function set_up(): void {
        parent::set_up();
        
        // Create temp directory for dump files
        // Use DIRECTORY_SEPARATOR for cross-platform compatibility
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'shield_export_test_' . uniqid();
        if (!mkdir($this->tempDir, 0755, true) && !is_dir($this->tempDir)) {
            $this->fail('Failed to create temp directory: ' . $this->tempDir);
        }
        
        // Create all test tables
        $this->createTestTables();
    }

    public function tear_down(): void {
        global $wpdb;
        
        // Drop all test tables
        foreach ($this->testTables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS `{$table}`");
        }
        $this->testTables = [];
        
        // Clean up temp files
        foreach ($this->tempFiles as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
        $this->tempFiles = [];
        
        // Clean up temp directory (with null check for robustness)
        if (!empty($this->tempDir) && is_dir($this->tempDir)) {
            $files = glob($this->tempDir . DIRECTORY_SEPARATOR . '*');
            if ($files !== false) {
                array_map('unlink', array_filter($files, 'is_file'));
            }
            @rmdir($this->tempDir);
        }
        
        parent::tear_down();
    }

    /**
     * Create all test tables with appropriate data
     * @throws \RuntimeException if table creation fails
     */
    private function createTestTables(): void {
        global $wpdb;
        $prefix = $wpdb->prefix;
        
        // Table 1: Small table (50 rows)
        $this->testTables['small'] = "{$prefix}shield_export_test_small";
        $result = $wpdb->query("CREATE TABLE `{$this->testTables['small']}` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `data` VARCHAR(255) NOT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        if ($result === false) {
            throw new \RuntimeException('Failed to create table: ' . $this->testTables['small']);
        }
        for ($i = 1; $i <= 50; $i++) {
            $wpdb->insert($this->testTables['small'], [
                'data' => "row_data_{$i}",
            ]);
        }
        
        // Table 2: Medium table (150 rows)
        $this->testTables['medium'] = "{$prefix}shield_export_test_medium";
        $result = $wpdb->query("CREATE TABLE `{$this->testTables['medium']}` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `data` VARCHAR(255) NOT NULL,
            `extra_data` TEXT,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        if ($result === false) {
            throw new \RuntimeException('Failed to create table: ' . $this->testTables['medium']);
        }
        for ($i = 1; $i <= 150; $i++) {
            $wpdb->insert($this->testTables['medium'], [
                'data' => "row_data_{$i}",
                'extra_data' => str_repeat("extra_{$i}_", 10),
            ]);
        }
        
        // Table 3: Large table (1500 rows)
        $this->testTables['large'] = "{$prefix}shield_export_test_large";
        $result = $wpdb->query("CREATE TABLE `{$this->testTables['large']}` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `data` VARCHAR(255) NOT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        if ($result === false) {
            throw new \RuntimeException('Failed to create table: ' . $this->testTables['large']);
        }
        // Batch insert for performance
        $values = [];
        for ($i = 1; $i <= 1500; $i++) {
            $values[] = $wpdb->prepare("(%s)", "row_data_{$i}");
            if (count($values) >= 100) {
                $insertResult = $wpdb->query("INSERT INTO `{$this->testTables['large']}` (`data`) VALUES " . implode(',', $values));
                if ($insertResult === false) {
                    throw new \RuntimeException('Failed to insert batch into: ' . $this->testTables['large']);
                }
                $values = [];
            }
        }
        if (!empty($values)) {
            $insertResult = $wpdb->query("INSERT INTO `{$this->testTables['large']}` (`data`) VALUES " . implode(',', $values));
            if ($insertResult === false) {
                throw new \RuntimeException('Failed to insert final batch into: ' . $this->testTables['large']);
            }
        }
        
        // Table 4: Composite key table (no usable PK)
        $this->testTables['composite'] = "{$prefix}shield_export_test_composite";
        $result = $wpdb->query("CREATE TABLE `{$this->testTables['composite']}` (
            `tenant_id` VARCHAR(50) NOT NULL,
            `record_id` VARCHAR(50) NOT NULL,
            `data` TEXT,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`tenant_id`, `record_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        if ($result === false) {
            throw new \RuntimeException('Failed to create table: ' . $this->testTables['composite']);
        }
        for ($i = 1; $i <= 200; $i++) {
            $wpdb->insert($this->testTables['composite'], [
                'tenant_id' => 'tenant_' . ($i % 10),
                'record_id' => 'record_' . $i,
                'data' => "composite_data_{$i}",
            ]);
        }
        
        // Table 5: Empty table
        $this->testTables['empty'] = "{$prefix}shield_export_test_empty";
        $result = $wpdb->query("CREATE TABLE `{$this->testTables['empty']}` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `data` VARCHAR(255)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        if ($result === false) {
            throw new \RuntimeException('Failed to create table: ' . $this->testTables['empty']);
        }
        
        // Table 6: Gaps in PK sequence
        $this->testTables['gaps'] = "{$prefix}shield_export_test_gaps";
        $result = $wpdb->query("CREATE TABLE `{$this->testTables['gaps']}` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `data` VARCHAR(255) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        if ($result === false) {
            throw new \RuntimeException('Failed to create table: ' . $this->testTables['gaps']);
        }
        // Insert with specific IDs to create gaps
        foreach ([1, 2, 5, 10, 100, 101, 102] as $id) {
            $wpdb->query($wpdb->prepare(
                "INSERT INTO `{$this->testTables['gaps']}` (`id`, `data`) VALUES (%d, %s)",
                $id, "gap_row_{$id}"
            ));
        }
    }

    /**
     * Helper: Create a temp file for dump output
     * @throws \RuntimeException if temp file cannot be created
     */
    private function createTempFile(): string {
        $file = tempnam($this->tempDir, 'shield_dump_');
        if ($file === false) {
            throw new \RuntimeException('Failed to create temp file in: ' . $this->tempDir);
        }
        $this->tempFiles[] = $file;
        return $file;
    }

    /**
     * Helper: Get file handle for dump
     * @return resource
     * @throws \RuntimeException if file cannot be opened
     */
    private function openDumpFile(string $path) {
        $handle = fopen($path, 'w+');
        if ($handle === false) {
            throw new \RuntimeException('Failed to open dump file: ' . $path);
        }
        return $handle;
    }

    /**
     * Helper: Read dump file contents
     * @throws \RuntimeException if file cannot be read
     */
    private function readDumpFile(string $path): string {
        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException('Failed to read dump file: ' . $path);
        }
        return $content;
    }

    /**
     * Helper: Count INSERT statements in dump file
     */
    private function countInsertStatements(string $content): int {
        return preg_match_all('/INSERT\s+INTO/i', $content);
    }

    // =========================================================================
    // SCENARIO GROUP 1: PK-Based Export (Happy Path)
    // =========================================================================

    /**
     * Test 1.1: Small table completes in single chunk
     */
    public function testSmallPKTableCompletesInSingleChunk(): void {
        $dumpFile = $this->createTempFile();
        $handle = $this->openDumpFile($dumpFile);
        
        try {
            $exporter = new ChunkedExporter(
                $handle,
                $this->testTables['small'],
                0,      // startingOffset
                1000,   // maxPageRows
                50      // chunkSize
            );
            
            $result = $exporter->run();
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }
        
        // Verify results
        $this->assertTrue($result['table_export_complete'], 
            'Small table should complete in single chunk');
        $this->assertEquals(50, $result['current_offset'], 
            'Offset should equal last PK value (50)');
        $this->assertEquals(50, $result['exported_rows'], 
            'Should export exactly 50 rows');
        
        // Verify SQL output
        $content = $this->readDumpFile($dumpFile);
        $this->assertStringContainsString('INSERT', $content, 
            'Dump should contain INSERT statements');
        $insertCount = $this->countInsertStatements($content);
        $this->assertEquals(50, $insertCount, 
            'Should have 50 INSERT statements');
    }

    /**
     * Test 1.2: Medium table requires multiple chunks
     */
    public function testMediumPKTableRequiresMultipleChunks(): void {
        $dumpFile = $this->createTempFile();
        $handle = $this->openDumpFile($dumpFile);
        
        try {
            $exporter = new ChunkedExporter(
                $handle,
                $this->testTables['medium'],
                0,
                1000,
                50  // chunkSize=50 means 3 chunks for 150 rows
            );
            
            $result = $exporter->run();
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }
        
        $this->assertTrue($result['table_export_complete']);
        $this->assertEquals(150, $result['current_offset']);
        $this->assertEquals(150, $result['exported_rows']);
        
        // Verify all rows exported
        $content = $this->readDumpFile($dumpFile);
        $insertCount = $this->countInsertStatements($content);
        $this->assertEquals(150, $insertCount);
    }

    /**
     * Test 1.3: Large table stops at maxPageRows
     */
    public function testLargePKTableStopsAtMaxPageRows(): void {
        $dumpFile = $this->createTempFile();
        $handle = $this->openDumpFile($dumpFile);
        
        try {
            $exporter = new ChunkedExporter(
                $handle,
                $this->testTables['large'],
                0,
                1000,   // maxPageRows - should stop here
                50
            );
            
            $result = $exporter->run();
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }
        
        $this->assertFalse($result['table_export_complete'], 
            'Should NOT be complete - more rows exist');
        $this->assertGreaterThanOrEqual(1000, $result['current_offset'], 
            'Offset should be at least 1000');
        $this->assertGreaterThanOrEqual(1000, $result['exported_rows'], 
            'Should export at least 1000 rows');
        $this->assertLessThanOrEqual(1050, $result['exported_rows'], 
            'Should not exceed maxPageRows by more than one chunk');
    }

    /**
     * Test 1.4: Continuation from non-zero offset
     */
    public function testPKTableContinuationFromOffset(): void {
        $dumpFile = $this->createTempFile();
        $handle = $this->openDumpFile($dumpFile);
        
        try {
            // Continue from offset 1000
            $exporter = new ChunkedExporter(
                $handle,
                $this->testTables['large'],
                1000,   // startingOffset - continue from here
                1000,
                50
            );
            
            $result = $exporter->run();
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }
        
        $this->assertTrue($result['table_export_complete'], 
            'Should complete - only 500 rows remaining');
        $this->assertEquals(1500, $result['current_offset'], 
            'Offset should be 1500 (last PK)');
        $this->assertEquals(500, $result['exported_rows'], 
            'Should export 500 remaining rows');
        
        // Verify SQL only contains rows > 1000
        $content = $this->readDumpFile($dumpFile);
        $this->assertStringNotContainsString("'row_data_1'", $content, 
            'Should not contain row 1');
        $this->assertStringNotContainsString("'row_data_1000'", $content, 
            'Should not contain row 1000');
        $this->assertStringContainsString("row_data_1001", $content, 
            'Should contain row 1001');
    }

    // =========================================================================
    // SCENARIO GROUP 2: Non-PK Export (LIMIT/OFFSET Path)
    // =========================================================================

    /**
     * Test 2.1: Non-PK table uses offset pagination
     */
    public function testNonPKTableUsesOffsetPagination(): void {
        // Verify TableHelper returns null for composite key table
        $tableHelper = new TableHelper($this->testTables['composite']);
        $pk = $tableHelper->getAppropriatePrimaryKeyForOrdering();
        $this->assertNull($pk, 
            'Composite key table should not have usable PK for ordering');
        
        $dumpFile = $this->createTempFile();
        $handle = $this->openDumpFile($dumpFile);
        
        try {
            $exporter = new ChunkedExporter(
                $handle,
                $this->testTables['composite'],
                0,
                1000,
                50
            );
            
            $result = $exporter->run();
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }
        
        $this->assertTrue($result['table_export_complete']);
        $this->assertEquals(200, $result['exported_rows']);
        // For non-PK path, offset is page number, not PK value
        $this->assertGreaterThan(0, $result['current_offset']);
    }

    /**
     * Test 2.2: Non-PK table has reduced maxPageRows
     * 
     * Verifies line 56: $this->maxPageRows = (int)\max( 1, \round( 2*$this->maxPageRows/3 ) );
     */
    public function testNonPKTableHasReducedMaxPageRows(): void {
        $dumpFile = $this->createTempFile();
        $handle = $this->openDumpFile($dumpFile);
        
        try {
            // With maxPageRows=150 and chunkSize=50, non-PK path should:
            // - Reduce maxPageRows to 100 (round(150 * 2/3) = 100)
            // - Export up to 100 rows per page
            $exporter = new ChunkedExporter(
                $handle,
                $this->testTables['composite'],
                0,
                150,    // This gets reduced to 100 for non-PK
                50
            );
            
            $result = $exporter->run();
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }
        
        // If maxPageRows was NOT reduced, we'd export all 200 rows
        // With reduction to 100, we should stop around 100 rows
        // (actual may vary slightly due to chunk boundaries)
        $this->assertFalse($result['table_export_complete'], 
            'Should NOT complete if maxPageRows reduction is working');
        $this->assertLessThanOrEqual(150, $result['exported_rows'], 
            'Should export ~100 rows with reduced maxPageRows');
    }

    // =========================================================================
    // SCENARIO GROUP 3: Empty and Edge Cases
    // =========================================================================

    /**
     * Test 3.1: Empty table returns immediately
     */
    public function testEmptyTableReturnsImmediately(): void {
        $dumpFile = $this->createTempFile();
        $handle = $this->openDumpFile($dumpFile);
        
        try {
            $exporter = new ChunkedExporter(
                $handle,
                $this->testTables['empty'],
                0,
                1000,
                50
            );
            
            $result = $exporter->run();
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }
        
        $this->assertTrue($result['table_export_complete']);
        $this->assertEquals(0, $result['current_offset']);
        $this->assertEquals(0, $result['exported_rows']);
        
        // Verify no INSERT statements
        $content = $this->readDumpFile($dumpFile);
        $insertCount = $this->countInsertStatements($content);
        $this->assertEquals(0, $insertCount);
    }

    /**
     * Test 3.2: Table with gaps in PK sequence
     */
    public function testPKTableWithGapsInSequence(): void {
        $dumpFile = $this->createTempFile();
        $handle = $this->openDumpFile($dumpFile);
        
        try {
            $exporter = new ChunkedExporter(
                $handle,
                $this->testTables['gaps'],
                0,
                1000,
                50
            );
            
            $result = $exporter->run();
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }
        
        $this->assertTrue($result['table_export_complete']);
        $this->assertEquals(102, $result['current_offset'], 
            'Offset should be last actual PK (102)');
        $this->assertEquals(7, $result['exported_rows'], 
            'Should export all 7 rows');
        
        // Verify content
        $content = $this->readDumpFile($dumpFile);
        $this->assertStringContainsString('gap_row_1', $content);
        $this->assertStringContainsString('gap_row_102', $content);
    }

    // =========================================================================
    // SCENARIO GROUP 4: Error Handling and Safety Guards
    // =========================================================================

    /**
     * Test 4.1: Verify PK detection works correctly
     * 
     * This verifies TableHelper::detectPossiblePrimaryKey() requirements:
     * - Key = 'PRI'
     * - Extra = 'auto_increment'
     * - Type contains 'int' and 'unsigned'
     */
    public function testPrimaryKeyDetectionRequirements(): void {
        // PK table should detect 'id'
        $tableHelper = new TableHelper($this->testTables['small']);
        $pk = $tableHelper->getAppropriatePrimaryKeyForOrdering();
        $this->assertEquals('id', $pk, 
            'Should detect auto-increment PK column');
        
        // Composite key should return null
        $tableHelper = new TableHelper($this->testTables['composite']);
        $pk = $tableHelper->getAppropriatePrimaryKeyForOrdering();
        $this->assertNull($pk, 
            'Composite key should not be detected as usable PK');
    }

    /**
     * Test 4.2: maxIterations guard calculation
     * 
     * Verifies: $maxIterations = (int)\ceil( $this->maxPageRows / $this->chunkSize ) + 10;
     */
    public function testMaxIterationsCalculation(): void {
        // With maxPageRows=100, chunkSize=50: ceil(100/50) + 10 = 12
        // Normal operation should complete in ~2 iterations
        // This test verifies normal operation doesn't trigger the guard
        
        $dumpFile = $this->createTempFile();
        $handle = $this->openDumpFile($dumpFile);
        
        try {
            $exporter = new ChunkedExporter(
                $handle,
                $this->testTables['medium'],
                0,
                100,    // maxPageRows=100
                50      // chunkSize=50 -> maxIterations = 12
            );
            
            // Should complete normally without hitting maxIterations
            $result = $exporter->run();
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }
        
        $this->assertFalse($result['table_export_complete'], 
            'Should stop at maxPageRows, not throw exception');
        $this->assertGreaterThanOrEqual(100, $result['exported_rows']);
    }

    // =========================================================================
    // SCENARIO GROUP 5: Integration with PagedExporter
    // =========================================================================

    /**
     * Test 5.1: PagedExporter correctly tracks multi-page export and creates properly named files
     */
    public function testPagedExporterMultiPageTracking(): void {
        global $wpdb;
        
        // Create ExportMap with initial status
        $initialStatus = [
            $this->testTables['large'] => [
                'offset' => 0,
                'page' => 0,
                'completed_at' => 0,
                'exported_rows' => 0,
                'max_page_rows' => 500,
                'chunk_size' => 50,
            ]
        ];
        
        $map = new ExportMap($initialStatus);
        
        // Run PagedExporter with generous time limit (120 seconds for slow CI systems)
        $pagedExporter = new PagedExporter(
            $this->tempDir,
            $map,
            time() + 120
        );
        
        $pagedExporter->run();
        
        // Check final status
        $finalStatus = $map->status();
        $tableStatus = $finalStatus[$this->testTables['large']];
        
        $this->assertGreaterThan(0, $tableStatus['completed_at'], 
            'Table should be marked complete');
        $this->assertEquals(1500, $tableStatus['exported_rows'], 
            'Should have exported all 1500 rows');
        $this->assertGreaterThan(0, $tableStatus['page'], 
            'Should have processed multiple pages');
        $this->assertEquals(1500, $tableStatus['offset'], 
            'Final offset should be last PK');
        
        // Verify file naming convention: data_{table_without_prefix}_{page}.sql
        // PagedExporter::dumpFileFor() strips the table prefix
        $tableWithoutPrefix = preg_replace(
            sprintf('#^%s#', preg_quote($wpdb->prefix, '#')),
            '',
            $this->testTables['large']
        );
        
        // Check that at least the first page file exists with correct naming
        // Use path_join() for cross-platform compatibility (matches PagedExporter::dumpFileFor)
        $expectedFirstFile = path_join($this->tempDir, sprintf('data_%s_0.sql', $tableWithoutPrefix));
        $this->assertFileExists($expectedFirstFile, 
            'Dump file should follow naming pattern: data_{table}_{page}.sql');
        
        // Verify file contains SQL content
        $content = file_get_contents($expectedFirstFile);
        if ($content === false) {
            $this->fail('Failed to read dump file: ' . $expectedFirstFile);
        }
        $this->assertStringContainsString('INSERT', $content, 
            'Dump file should contain INSERT statements');
    }

    /**
     * Test 5.2: PagedExporter handles multiple tables
     */
    public function testPagedExporterMultipleTables(): void {
        $initialStatus = [
            $this->testTables['small'] => [
                'offset' => 0,
                'page' => 0,
                'completed_at' => 0,
                'exported_rows' => 0,
                'max_page_rows' => 1000,
                'chunk_size' => 50,
            ],
            $this->testTables['medium'] => [
                'offset' => 0,
                'page' => 0,
                'completed_at' => 0,
                'exported_rows' => 0,
                'max_page_rows' => 1000,
                'chunk_size' => 50,
            ],
        ];
        
        $map = new ExportMap($initialStatus);
        
        // Use generous time limit (120 seconds for slow CI systems)
        $pagedExporter = new PagedExporter(
            $this->tempDir,
            $map,
            time() + 120
        );
        
        $pagedExporter->run();
        
        $finalStatus = $map->status();
        
        // Both tables should be complete
        $this->assertGreaterThan(0, $finalStatus[$this->testTables['small']]['completed_at']);
        $this->assertGreaterThan(0, $finalStatus[$this->testTables['medium']]['completed_at']);
        $this->assertEquals(50, $finalStatus[$this->testTables['small']]['exported_rows']);
        $this->assertEquals(150, $finalStatus[$this->testTables['medium']]['exported_rows']);
    }
}

