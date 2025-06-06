<?php
/**
 * Copyright 2016 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Google\Cloud\Samples\Spanner;

use Google\Cloud\Spanner\InstanceConfiguration;
use Google\Cloud\Spanner\SpannerClient;
use Google\Cloud\Spanner\Instance;
use Google\Cloud\Spanner\Transaction;
use Google\Cloud\TestUtils\EventuallyConsistentTestTrait;
use Google\Cloud\TestUtils\TestTrait;
use PHPUnitRetry\RetryTrait;
use PHPUnit\Framework\TestCase;
use Google\Auth\ApplicationDefaultCredentials;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;

/**
 * @retryAttempts 3
 * @retryDelayMethod exponentialBackoff
 */
class spannerTest extends TestCase
{
    use TestTrait {
        TestTrait::runFunctionSnippet as traitRunFunctionSnippet;
    }

    use RetryTrait, EventuallyConsistentTestTrait;

    /** @var string autoscalingInstanceId */
    protected static $autoscalingInstanceId;

    /** @var string instanceId */
    protected static $instanceId;

    /** @var string lowCostInstanceId */
    protected static $lowCostInstanceId;

    /** @var string instancePartitionInstanceId */
    protected static $instancePartitionInstanceId;

    /** @var Instance instancePartitionInstance */
    protected static $instancePartitionInstance;

    /** @var string databaseId */
    protected static $databaseId;

    /** @var string encryptedDatabaseId */
    protected static $encryptedDatabaseId;

    /** @var string $encryptedMrCmekDatabaseId */
    protected static $encryptedMrCmekDatabaseId;

    /** @var string backupId */
    protected static $backupId;

    /** @var Instance $instance */
    protected static $instance;

    /** @var string multiInstanceId */
    protected static $multiInstanceId;

    /** @var Instance $multiInstance */
    protected static $multiInstance;

    /** @var string multiDatabaseId */
    protected static $multiDatabaseId;

    /** @var string instanceConfig */
    protected static $instanceConfig;

    /** @var string defaultLeader */
    protected static $defaultLeader;

    /** @var string defaultLeader */
    protected static $updatedDefaultLeader;

    /** @var string kmsKeyName */
    protected static $kmsKeyName;

    /** @var string kmsKeyName2 */
    protected static $kmsKeyName2;

    /** @var string kmsKeyName3 */
    protected static $kmsKeyName3;

    /**
     * Low cost instance with less than 1000 processing units.
     *
     * @var $instance lowCostInstance
     */
    protected static $lowCostInstance;

    /** @var $lastUpdateData int */
    protected static $lastUpdateDataTimestamp;

    /** @var string $baseConfigId */
    protected static $baseConfigId;

    /** @var string $customInstanceConfigId */
    protected static $customInstanceConfigId;

    /** @var InstanceConfiguration $customInstanceConfig */
    protected static $customInstanceConfig;

    /** @var string $databaseRole */
    protected static $databaseRole;

    /** @var string serviceAccountEmail */
    protected static $serviceAccountEmail = null;

    public static function setUpBeforeClass(): void
    {
        self::checkProjectEnvVars();

        if (!extension_loaded('grpc')) {
            self::markTestSkipped('Must enable grpc extension.');
        }

        $spanner = new SpannerClient([
            'projectId' => self::$projectId,
        ]);

        self::$autoscalingInstanceId = 'test-' . time() . rand();
        self::$instanceId = 'test-' . time() . rand();
        self::$lowCostInstanceId = 'test-' . time() . rand();
        self::$instancePartitionInstanceId = 'test-' . time() . rand();
        self::$instancePartitionInstance = $spanner->instance(self::$instancePartitionInstanceId);
        self::$databaseId = 'test-' . time() . rand();
        self::$encryptedDatabaseId = 'en-test-' . time() . rand();
        self::$encryptedMrCmekDatabaseId = 'mr-test-' . time() . rand();
        self::$backupId = 'backup-' . self::$databaseId;
        self::$instance = $spanner->instance(self::$instanceId);
        self::$kmsKeyName =
            'projects/' . self::$projectId . '/locations/us-central1/keyRings/spanner-test-keyring/cryptoKeys/spanner-test-cmek';
        self::$kmsKeyName2 =
            'projects/' . self::$projectId . '/locations/us-east1/keyRings/spanner-test-keyring2/cryptoKeys/spanner-test-cmek2';
        self::$kmsKeyName3 =
            'projects/' . self::$projectId . '/locations/us-east4/keyRings/spanner-test-keyring3/cryptoKeys/spanner-test-cmek3';
        self::$lowCostInstance = $spanner->instance(self::$lowCostInstanceId);

        self::$multiInstanceId = 'kokoro-multi-instance';
        self::$multiDatabaseId = 'test-' . time() . rand() . 'm';
        self::$instanceConfig = 'nam3';
        self::$defaultLeader = 'us-east1';
        self::$updatedDefaultLeader = 'us-east4';
        self::$multiInstance = $spanner->instance(self::$multiInstanceId);
        self::$baseConfigId = 'nam7';
        self::$customInstanceConfigId = 'custom-' . time() . rand();
        self::$customInstanceConfig = $spanner->instanceConfiguration(self::$customInstanceConfigId);
        self::$databaseRole = 'new_parent';
    }

    public function testCreateInstance()
    {
        $output = $this->runAdminFunctionSnippet('create_instance', [
            'project_id' => self::$projectId,
            'instance_id' => self::$instanceId
        ]);
        $this->assertStringContainsString('Waiting for operation to complete...', $output);
        $this->assertStringContainsString('Created instance test-', $output);
    }

    public function testCreateInstanceWithProcessingUnits()
    {
        $output = $this->runAdminFunctionSnippet('create_instance_with_processing_units', [
            'project_id' => self::$projectId,
            'instance_id' => self::$lowCostInstanceId
        ]);
        $this->assertStringContainsString('Waiting for operation to complete...', $output);
        $this->assertStringContainsString('Created instance test-', $output);
    }

    public function testCreateInstanceConfig()
    {
        $output = $this->runAdminFunctionSnippet('create_instance_config', [
            self::$projectId, self::$customInstanceConfigId, self::$baseConfigId
        ]);

        $this->assertStringContainsString(sprintf('Created instance configuration %s', self::$customInstanceConfigId), $output);
    }

    public function testCreateInstanceWithAutoscalingConfig()
    {
        $output = $this->runAdminFunctionSnippet('create_instance_with_autoscaling_config', [
            'project_id' => self::$projectId,
            'instance_id' => self::$autoscalingInstanceId
        ]);
        $this->assertStringContainsString('Waiting for operation to complete...', $output);
        $this->assertStringContainsString('Created instance test-', $output);
        $this->assertStringContainsString('minNodes set to 1', $output);
    }

    /**
     * @depends testCreateInstanceConfig
     */
    public function testUpdateInstanceConfig()
    {
        $output = $this->runAdminFunctionSnippet('update_instance_config', [
            self::$projectId,
            self::$customInstanceConfigId
        ]);

        $this->assertStringContainsString(sprintf('Updated instance configuration %s', self::$customInstanceConfigId), $output);
    }

    /**
     * @depends testListInstanceConfigOperations
     */
    public function testDeleteInstanceConfig()
    {
        $output = $this->runAdminFunctionSnippet('delete_instance_config', [
            self::$projectId,
            self::$customInstanceConfigId
        ]);
        $this->assertStringContainsString(sprintf('Deleted instance configuration %s', self::$customInstanceConfigId), $output);
    }

    /**
     * @depends testUpdateInstanceConfig
     */
    public function testListInstanceConfigOperations()
    {
        $output = $this->runAdminFunctionSnippet('list_instance_config_operations', [
            self::$projectId
        ]);

        $this->assertStringContainsString(
            sprintf(
                'Instance config operation for projects/%s/instanceConfigs/%s of type %s has status done.',
                self::$projectId,
                self::$customInstanceConfigId,
                'type.googleapis.com/google.spanner.admin.instance.v1.CreateInstanceConfigMetadata'
            ),
            $output);

        $this->assertStringContainsString(
            sprintf(
                'Instance config operation for projects/%s/instanceConfigs/%s of type %s has status done.',
                self::$projectId,
                self::$customInstanceConfigId,
                'type.googleapis.com/google.spanner.admin.instance.v1.UpdateInstanceConfigMetadata'
            ),
            $output);
    }

    public function testCreateInstancePartition()
    {
        $spanner = new SpannerClient([
            'projectId' => self::$projectId,
        ]);
        $instanceConfig = $spanner->instanceConfiguration('regional-us-central1');
        $operation = $spanner->createInstance(
            $instanceConfig,
            self::$instancePartitionInstanceId,
            [
                'displayName' => 'Instance partitions test.',
                'nodeCount' => 1,
                'labels' => [
                    'cloud_spanner_samples' => true,
                ]
            ]
        );
        $operation->pollUntilComplete();
        $output = $this->runAdminFunctionSnippet('create_instance_partition', [
            'project_id' => self::$projectId,
            'instance_id' => self::$instancePartitionInstanceId,
            'instance_partition_id' => 'my-instance-partition'
        ]);
        $this->assertStringContainsString('Waiting for operation to complete...', $output);
        $this->assertStringContainsString('Created instance partition my-instance-partition', $output);
    }

    /**
     * @depends testCreateInstance
     */
    public function testCreateDatabase()
    {
        $output = $this->runAdminFunctionSnippet('create_database');
        $this->assertStringContainsString('Waiting for operation to complete...', $output);
        $this->assertStringContainsString('Created database test-', $output);
    }

    /**
     * @depends testCreateInstance
     */
    public function testCreateDatabaseWithEncryptionKey()
    {
        $output = $this->runAdminFunctionSnippet('create_database_with_encryption_key', [
            self::$projectId,
            self::$instanceId,
            self::$encryptedDatabaseId,
            self::$kmsKeyName,
        ]);
        $this->assertStringContainsString('Waiting for operation to complete...', $output);
        $this->assertStringContainsString('Created database en-test-', $output);
    }

    public function testCreateDatabaseWithMrCmek()
    {
        $spanner = new SpannerClient([
            'projectId' => self::$projectId,
        ]);
        $mrCmekInstanceId = 'test-mr-' . time() . rand();
        $instanceConfig = $spanner->instanceConfiguration('nam3');
        $operation = $spanner->createInstance(
            $instanceConfig,
            $mrCmekInstanceId,
            [
                'displayName' => 'Mr Cmek test.',
                'nodeCount' => 1,
                'labels' => [
                    'cloud_spanner_samples' => true,
                ]
            ]
        );
        $operation->pollUntilComplete();
        $kmsKeyNames = array(self::$kmsKeyName, self::$kmsKeyName2, self::$kmsKeyName3);
        $output = $this->runAdminFunctionSnippet('create_database_with_mr_cmek', [
            self::$projectId,
            $mrCmekInstanceId,
            self::$encryptedMrCmekDatabaseId,
            $kmsKeyNames,
        ]);
        $this->assertStringContainsString('Waiting for operation to complete...', $output);
        $this->assertStringContainsString('Created database mr-test-', $output);
    }

    /**
     * @depends testCreateDatabase
     */
    public function testUpdateDatabase()
    {
        $output = $this->runAdminFunctionSnippet('update_database', [
            'project_id' => self::$projectId,
            'instanceId' => self::$instanceId,
            'databaseId' => self::$databaseId
        ]);
        $this->assertStringContainsString(self::$databaseId, $output);
        $this->assertStringContainsString(true, $output);

        // reset the enableDropProtection for test tear down
        $spanner = new SpannerClient();
        $instance = $spanner->instance(self::$instanceId);
        $database = $instance->database(self::$databaseId);
        $op = $database->updateDatabase(['enableDropProtection' => false]);
        $op->pollUntilComplete();
        $database->reload();
        $this->assertFalse($database->info()['enableDropProtection']);
    }

    /**
     * @depends testCreateDatabase
     */
    public function testInsertData()
    {
        $output = $this->runFunctionSnippet('insert_data');
        $this->assertEquals('Inserted data.' . PHP_EOL, $output);
    }

    /**
     * @depends testInsertData
     */
    public function testQueryData()
    {
        $output = $this->runFunctionSnippet('query_data');
        $this->assertStringContainsString('SingerId: 1, AlbumId: 1, AlbumTitle: Total Junk', $output);
        $this->assertStringContainsString('SingerId: 1, AlbumId: 2, AlbumTitle: Go, Go, Go', $output);
        $this->assertStringContainsString('SingerId: 2, AlbumId: 1, AlbumTitle: Green', $output);
        $this->assertStringContainsString('SingerId: 2, AlbumId: 2, AlbumTitle: Forever Hold Your Peace', $output);
        $this->assertStringContainsString('SingerId: 2, AlbumId: 3, AlbumTitle: Terrified', $output);
    }

    /**
     * @depends testInsertData
     */
    public function testBatchQueryData()
    {
        $output = $this->runFunctionSnippet('batch_query_data');
        $this->assertStringContainsString('SingerId: 1, FirstName: Marc, LastName: Richards', $output);
        $this->assertStringContainsString('SingerId: 2, FirstName: Catalina, LastName: Smith', $output);
        $this->assertStringContainsString('SingerId: 3, FirstName: Alice, LastName: Trentor', $output);
        $this->assertStringContainsString('SingerId: 4, FirstName: Lea, LastName: Martin', $output);
        $this->assertStringContainsString('SingerId: 5, FirstName: David, LastName: Lomond', $output);
        $this->assertStringContainsString('Total Partitions:', $output);
        $this->assertStringContainsString('Total Records: 5', $output);
        $this->assertStringContainsString('Average Records Per Partition:', $output);
    }

    /**
     * @depends testInsertData
     */
    public function testReadData()
    {
        $output = $this->runFunctionSnippet('read_data');
        $this->assertStringContainsString('SingerId: 1, AlbumId: 1, AlbumTitle: Total Junk', $output);
        $this->assertStringContainsString('SingerId: 1, AlbumId: 2, AlbumTitle: Go, Go, Go', $output);
        $this->assertStringContainsString('SingerId: 2, AlbumId: 1, AlbumTitle: Green', $output);
        $this->assertStringContainsString('SingerId: 2, AlbumId: 2, AlbumTitle: Forever Hold Your Peace', $output);
        $this->assertStringContainsString('SingerId: 2, AlbumId: 3, AlbumTitle: Terrified', $output);
    }

    /**
     * @depends testInsertData
     */
    public function testDeleteData()
    {
        $output = $this->runFunctionSnippet('delete_data');
        $this->assertStringContainsString('Deleted data.' . PHP_EOL, $output);

        $spanner = new SpannerClient();
        $instance = $spanner->instance(spannerTest::$instanceId);
        $database = $instance->database(spannerTest::$databaseId);

        $results = $database->execute(
            'SELECT SingerId FROM Albums UNION ALL SELECT SingerId FROM Singers'
        );

        foreach ($results as $row) {
            $this->fail('Not all data was deleted.');
        }

        $output = $this->runFunctionSnippet('insert_data');
        $this->assertEquals('Inserted data.' . PHP_EOL, $output);
    }

    /**
     * @depends testDeleteData
     */
    public function testAddColumn()
    {
        $output = $this->runAdminFunctionSnippet('add_column');
        $this->assertStringContainsString('Waiting for operation to complete...', $output);
        $this->assertStringContainsString('Added the MarketingBudget column.', $output);
    }

    /**
     * @depends testAddColumn
     */
    public function testUpdateData()
    {
        $output = $this->runFunctionSnippet('update_data');
        self::$lastUpdateDataTimestamp = time();
        $this->assertEquals('Updated data.' . PHP_EOL, $output);
    }

    /**
     * @depends testAddColumn
     */
    public function testQueryDataWithNewColumn()
    {
        $output = $this->runFunctionSnippet('query_data_with_new_column');
        $this->assertStringContainsString('SingerId: 1, AlbumId: 1, MarketingBudget:', $output);
        $this->assertStringContainsString('SingerId: 1, AlbumId: 2, MarketingBudget:', $output);
        $this->assertStringContainsString('SingerId: 2, AlbumId: 1, MarketingBudget:', $output);
        $this->assertStringContainsString('SingerId: 2, AlbumId: 2, MarketingBudget:', $output);
        $this->assertStringContainsString('SingerId: 2, AlbumId: 3, MarketingBudget:', $output);
    }

    /**
     * @depends testUpdateData
     */
    public function testReadWriteTransaction()
    {
        $this->runFunctionSnippet('update_data');
        $output = $this->runFunctionSnippet('read_write_transaction');
        $this->assertStringContainsString('Setting first album\'s budget to 300000 and the second album\'s budget to 300000', $output);
        $this->assertStringContainsString('Transaction complete.', $output);
    }

    /**
     * @depends testAddColumn
     */
    public function testCreateIndex()
    {
        $output = $this->runAdminFunctionSnippet('create_index');
        $this->assertStringContainsString('Waiting for operation to complete...', $output);
        $this->assertStringContainsString('Added the AlbumsByAlbumTitle index.', $output);
    }

    /**
     * @depends testCreateIndex
     */
    public function testQueryDataWithIndex()
    {
        $output = $this->runFunctionSnippet('query_data_with_index');
        $this->assertStringContainsString('AlbumId: 2, AlbumTitle: Forever Hold Your Peace', $output);
        $this->assertStringContainsString('AlbumId: 2, AlbumTitle: Go, Go, Go', $output);
    }

    /**
     * @depends testCreateIndex
     */
    public function testReadDataWithIndex()
    {
        $output = $this->runFunctionSnippet('read_data_with_index');

        $this->assertStringContainsString('AlbumId: 1, AlbumTitle: Total Junk', $output);
        $this->assertStringContainsString('AlbumId: 2, AlbumTitle: Go, Go, Go', $output);
        $this->assertStringContainsString('AlbumId: 1, AlbumTitle: Green', $output);
        $this->assertStringContainsString('AlbumId: 3, AlbumTitle: Terrified', $output);
        $this->assertStringContainsString('AlbumId: 2, AlbumTitle: Forever Hold Your Peace', $output);
    }

    /**
     * @depends testCreateIndex
     */
    public function testCreateStoringIndex()
    {
        $output = $this->runAdminFunctionSnippet('create_storing_index');
        $this->assertStringContainsString('Waiting for operation to complete...', $output);
        $this->assertStringContainsString('Added the AlbumsByAlbumTitle2 index.', $output);
    }

    /**
     * @depends testCreateStoringIndex
     */
    public function testReadDataWithStoringIndex()
    {
        $output = $this->runFunctionSnippet('read_data_with_storing_index');
        $this->assertStringContainsString('AlbumId: 2, AlbumTitle: Forever Hold Your Peace, MarketingBudget:', $output);
        $this->assertStringContainsString('AlbumId: 2, AlbumTitle: Go, Go, Go, MarketingBudget:', $output);
        $this->assertStringContainsString('AlbumId: 1, AlbumTitle: Green, MarketingBudget:', $output);
        $this->assertStringContainsString('AlbumId: 3, AlbumTitle: Terrified, MarketingBudget:', $output);
        $this->assertStringContainsString('AlbumId: 1, AlbumTitle: Total Junk, MarketingBudget:', $output);
    }

    /**
     * @depends testUpdateData
     */
    public function testReadOnlyTransaction()
    {
        $output = $this->runFunctionSnippet('read_only_transaction');
        $this->assertStringContainsString('SingerId: 1, AlbumId: 1, AlbumTitle: Total Junk', $output);
        $this->assertStringContainsString('SingerId: 1, AlbumId: 2, AlbumTitle: Go, Go, Go', $output);
        $this->assertStringContainsString('SingerId: 2, AlbumId: 1, AlbumTitle: Green', $output);
        $this->assertStringContainsString('SingerId: 2, AlbumId: 2, AlbumTitle: Forever Hold Your Peace', $output);
        $this->assertStringContainsString('SingerId: 2, AlbumId: 3, AlbumTitle: Terrified', $output);
    }

    /**
     * @depends testUpdateData
     */
    public function testReadStaleData()
    {
        // read-stale-data reads data that is exactly 15 seconds old.  So, make sure 15 seconds
        // have elapsed since testUpdateData().
        $elapsed = time() - self::$lastUpdateDataTimestamp;
        if ($elapsed < 16) {
            sleep(16 - $elapsed);
        }
        $output = $this->runFunctionSnippet('read_stale_data');
        $this->assertStringContainsString('SingerId: 1, AlbumId: 1, AlbumTitle: Total Junk', $output);
        $this->assertStringContainsString('SingerId: 1, AlbumId: 2, AlbumTitle: Go, Go, Go', $output);
        $this->assertStringContainsString('SingerId: 2, AlbumId: 1, AlbumTitle: Green', $output);
        $this->assertStringContainsString('SingerId: 2, AlbumId: 2, AlbumTitle: Forever Hold Your Peace', $output);
        $this->assertStringContainsString('SingerId: 2, AlbumId: 3, AlbumTitle: Terrified', $output);
    }

    /**
     * @depends testReadStaleData
     */
    public function testCreateTableTimestamp()
    {
        $output = $this->runAdminFunctionSnippet('create_table_with_timestamp_column');
        $this->assertStringContainsString('Waiting for operation to complete...', $output);
        $this->assertStringContainsString('Created Performances table in database test-', $output);
    }

    /**
     * @depends testCreateTableTimestamp
     */
    public function testInsertDataTimestamp()
    {
        $output = $this->runFunctionSnippet('insert_data_with_timestamp_column');
        $this->assertEquals('Inserted data.' . PHP_EOL, $output);
    }

    /**
     * @depends testInsertDataTimestamp
     */
    public function testAddTimestampColumn()
    {
        $output = $this->runAdminFunctionSnippet('add_timestamp_column');
        $this->assertStringContainsString('Waiting for operation to complete...', $output);
        $this->assertStringContainsString('Added LastUpdateTime as a commit timestamp column in Albums table', $output);
    }

    /**
     * @depends testAddTimestampColumn
     */
    public function testUpdateDataTimestamp()
    {
        $output = $this->runFunctionSnippet('update_data_with_timestamp_column');
        $this->assertEquals('Updated data.' . PHP_EOL, $output);
    }

    /**
     * @depends testUpdateDataTimestamp
     */
    public function testQueryDataTimestamp()
    {
        $output = $this->runFunctionSnippet('query_data_with_timestamp_column');
        $this->assertStringContainsString('SingerId: 1, AlbumId: 1, MarketingBudget: 1000000, LastUpdateTime: 20', $output);
        $this->assertStringContainsString('SingerId: 2, AlbumId: 2, MarketingBudget: 750000, LastUpdateTime: 20', $output);
        $this->assertStringContainsString('SingerId: 1, AlbumId: 2, MarketingBudget: NULL, LastUpdateTime: NULL', $output);
        $this->assertStringContainsString('SingerId: 2, AlbumId: 1, MarketingBudget: NULL, LastUpdateTime: NULL', $output);
        $this->assertStringContainsString('SingerId: 2, AlbumId: 3, MarketingBudget: NULL, LastUpdateTime: NULL', $output);
    }

    /**
     * @depends testCreateDatabase
     */
    public function testInsertStructData()
    {
        $output = $this->runFunctionSnippet('insert_struct_data');
        $this->assertEquals('Inserted data.' . PHP_EOL, $output);
    }

    /**
     * @depends testInsertStructData
     */
    public function testQueryDataWithStruct()
    {
        $output = $this->runFunctionSnippet('query_data_with_struct');
        $this->assertStringContainsString('SingerId: 6', $output);
    }

    /**
     * @depends testInsertStructData
     */
    public function testQueryDataWithArrayOfStruct()
    {
        $output = $this->runFunctionSnippet('query_data_with_array_of_struct');
        $this->assertStringContainsString('SingerId: 6', $output);
        $this->assertStringContainsString('SingerId: 7', $output);
        $this->assertStringContainsString('SingerId: 8', $output);
    }

    /**
     * @depends testInsertStructData
     */
    public function testQueryDataWithStructField()
    {
        $output = $this->runFunctionSnippet('query_data_with_struct_field');
        $this->assertStringContainsString('SingerId: 6', $output);
    }

    /**
     * @depends testInsertStructData
     */
    public function testQueryDataWithNestedStructField()
    {
        $output = $this->runFunctionSnippet('query_data_with_nested_struct_field');
        $this->assertStringContainsString('SingerId: 6 SongName: Imagination', $output);
        $this->assertStringContainsString('SingerId: 9 SongName: Imagination', $output);
    }

    /**
     * @depends testCreateDatabase
     */
    public function testInsertDataWithDml()
    {
        $output = $this->runFunctionSnippet('insert_data_with_dml');
        $this->assertStringContainsString('Inserted 1 row(s)', $output);
    }

    /**
     * @depends testAddColumn
     */
    public function testUpdateDataWithDml()
    {
        $output = $this->runFunctionSnippet('update_data_with_dml');
        self::$lastUpdateDataTimestamp = time();
        $this->assertStringContainsString('Updated 1 row(s)', $output);
    }

    /**
     * @depends testAddColumn
     */
    public function testDeleteDataWithDml()
    {
        $output = $this->runFunctionSnippet('delete_data_with_dml');
        self::$lastUpdateDataTimestamp = time();
        $this->assertStringContainsString('Deleted 1 row(s)', $output);
    }

    /**
     * @depends testInsertData
     */
    public function testUpdateDataWithDmlTimestamp()
    {
        $output = $this->runFunctionSnippet('update_data_with_dml_timestamp');
        self::$lastUpdateDataTimestamp = time();
        $this->assertStringContainsString('Updated 2 row(s)', $output);
    }

    /**
     * @depends testCreateDatabase
     */
    public function testWriteReadWithDml()
    {
        $output = $this->runFunctionSnippet('write_read_with_dml');
        self::$lastUpdateDataTimestamp = time();
        $this->assertStringContainsString('Timothy Campbell', $output);
    }

    /**
     * @depends testCreateDatabase
     */
    public function testUpdateDataWithDmlStructs()
    {
        $output = $this->runFunctionSnippet('update_data_with_dml_structs');
        self::$lastUpdateDataTimestamp = time();
        $this->assertStringContainsString('Updated 1 row(s)', $output);
    }

    /**
     * @depends testInsertData
     */
    public function testWriteDataWithDML()
    {
        $output = $this->runFunctionSnippet('write_data_with_dml');
        self::$lastUpdateDataTimestamp = time();
        $this->assertStringContainsString('Inserted 4 row(s)', $output);
    }

    /**
     * @depends testWriteDataWithDML
     */
    public function testQueryDataWithParameter()
    {
        $output = $this->runFunctionSnippet('query_data_with_parameter');
        self::$lastUpdateDataTimestamp = time();
        $this->assertStringContainsString('SingerId: 12, FirstName: Melissa, LastName: Garcia', $output);
    }

    /**
     * @depends testAddColumn
     */
    public function testUpdateDataWithDmlTransaction()
    {
        $output = $this->runFunctionSnippet('write_data_with_dml_transaction');
        self::$lastUpdateDataTimestamp = time();
        $this->assertStringContainsString('Transaction complete', $output);
    }

    /**
     * @depends testAddColumn
     */
    public function testUpdateDataWithPartitionedDML()
    {
        $output = $this->runFunctionSnippet('update_data_with_partitioned_dml');
        self::$lastUpdateDataTimestamp = time();
        $this->assertStringContainsString('Updated 3 row(s)', $output);
    }

    /**
     * @depends testAddColumn
     */
    public function testDeleteDataWithPartitionedDML()
    {
        $output = $this->runFunctionSnippet('delete_data_with_partitioned_dml');
        self::$lastUpdateDataTimestamp = time();
        $this->assertStringContainsString('Deleted 5 row(s)', $output);
    }

    /**
     * @depends testAddColumn
     */
    public function testUpdateDataWithBatchDML()
    {
        $output = $this->runFunctionSnippet('update_data_with_batch_dml');
        self::$lastUpdateDataTimestamp = time();
        $this->assertStringContainsString('Executed 2 SQL statements using Batch DML', $output);
    }

    /**
     * @depends testAddColumn
     */
    public function testGetCommitStats()
    {
        $output = $this->runFunctionSnippet('get_commit_stats');
        $this->assertStringContainsString('Updated data with 10 mutations.', $output);
    }

    /**
     * @depends testCreateDatabase
     */
    public function testCreateTableDatatypes()
    {
        $output = $this->runAdminFunctionSnippet('create_table_with_datatypes');
        $this->assertStringContainsString('Waiting for operation to complete...', $output);
        $this->assertStringContainsString('Created Venues table in database test-', $output);
    }

    /**
     * @depends testCreateTableDatatypes
     */
    public function testInsertDataWithDatatypes()
    {
        $output = $this->runFunctionSnippet('insert_data_with_datatypes');
        $this->assertEquals('Inserted data.' . PHP_EOL, $output);
    }

    /**
     * @depends testInsertDataWithDatatypes
     */
    public function testQueryDataWithArrayParameter()
    {
        $output = $this->runFunctionSnippet('query_data_with_array_parameter');
        self::$lastUpdateDataTimestamp = time();
        $this->assertStringContainsString('VenueId: 19, VenueName: Venue 19, AvailableDate: 2020-11-01', $output);
        $this->assertStringContainsString('VenueId: 42, VenueName: Venue 42, AvailableDate: 2020-10-01', $output);
    }

    /**
     * @depends testInsertDataWithDatatypes
     */
    public function testQueryDataWithBoolParameter()
    {
        $output = $this->runFunctionSnippet('query_data_with_bool_parameter');
        self::$lastUpdateDataTimestamp = time();
        $this->assertStringContainsString('VenueId: 19, VenueName: Venue 19, OutdoorVenue: True', $output);
    }

    /**
     * @depends testInsertDataWithDatatypes
     */
    public function testQueryDataWithBytesParameter()
    {
        $output = $this->runFunctionSnippet('query_data_with_bytes_parameter');
        self::$lastUpdateDataTimestamp = time();
        $this->assertStringContainsString('VenueId: 4, VenueName: Venue 4', $output);
    }

    /**
     * @depends testInsertDataWithDatatypes
     */
    public function testQueryDataWithDateParameter()
    {
        $output = $this->runFunctionSnippet('query_data_with_date_parameter');
        self::$lastUpdateDataTimestamp = time();
        $this->assertStringContainsString('VenueId: 4, VenueName: Venue 4, LastContactDate: 2018-09-02', $output);
        $this->assertStringContainsString('VenueId: 42, VenueName: Venue 42, LastContactDate: 2018-10-01', $output);
    }

    /**
     * @depends testInsertDataWithDatatypes
     */
    public function testQueryDataWithFloatParameter()
    {
        $output = $this->runFunctionSnippet('query_data_with_float_parameter');
        self::$lastUpdateDataTimestamp = time();
        $this->assertStringContainsString('VenueId: 4, VenueName: Venue 4, PopularityScore: 0.8', $output);
        $this->assertStringContainsString('VenueId: 19, VenueName: Venue 19, PopularityScore: 0.9', $output);
    }

    /**
     * @depends testInsertDataWithDatatypes
     */
    public function testQueryDataWithIntParameter()
    {
        $output = $this->runFunctionSnippet('query_data_with_int_parameter');
        self::$lastUpdateDataTimestamp = time();
        $this->assertStringContainsString('VenueId: 19, VenueName: Venue 19, Capacity: 6300', $output);
        $this->assertStringContainsString('VenueId: 42, VenueName: Venue 42, Capacity: 3000', $output);
    }

    /**
     * @depends testInsertDataWithDatatypes
     */
    public function testQueryDataWithStringParameter()
    {
        $output = $this->runFunctionSnippet('query_data_with_string_parameter');
        self::$lastUpdateDataTimestamp = time();
        $this->assertStringContainsString('VenueId: 42, VenueName: Venue 42', $output);
    }

    /**
     * @depends testInsertDataWithDatatypes
     */
    public function testQueryDataWithTimestampParameter()
    {
        $this->runEventuallyConsistentTest(function () {
            $output = $this->runFunctionSnippet('query_data_with_timestamp_parameter');
            self::$lastUpdateDataTimestamp = time();
            $this->assertStringContainsString('VenueId: 4, VenueName: Venue 4, LastUpdateTime:', $output);
            $this->assertStringContainsString('VenueId: 19, VenueName: Venue 19, LastUpdateTime:', $output);
            $this->assertStringContainsString('VenueId: 42, VenueName: Venue 42, LastUpdateTime:', $output);
        });
    }

    /**
     * @depends testInsertDataWithDatatypes
     */
    public function testQueryDataWithQueryOptions()
    {
        $this->runEventuallyConsistentTest(function () {
            $output = $this->runFunctionSnippet('query_data_with_query_options');
            self::$lastUpdateDataTimestamp = time();
            $this->assertStringContainsString('VenueId: 4, VenueName: Venue 4, LastUpdateTime:', $output);
            $this->assertStringContainsString('VenueId: 19, VenueName: Venue 19, LastUpdateTime:', $output);
            $this->assertStringContainsString('VenueId: 42, VenueName: Venue 42, LastUpdateTime:', $output);
        });
    }

    /**
     * @depends testInsertDataWithDatatypes
     */
    public function testAddNumericColumn()
    {
        $output = $this->runAdminFunctionSnippet('add_numeric_column');
        $this->assertStringContainsString('Waiting for operation to complete...', $output);
        $this->assertStringContainsString('Added Revenue as a NUMERIC column in Venues table', $output);
    }

    /**
     * @depends testAddNumericColumn
     */
    public function testUpdateDataNumeric()
    {
        $output = $this->runFunctionSnippet('update_data_with_numeric_column');
        $this->assertEquals('Updated data.' . PHP_EOL, $output);
    }

    /**
     * @depends testUpdateDataTimestamp
     */
    public function testQueryDataNumeric()
    {
        $output = $this->runFunctionSnippet('query_data_with_numeric_parameter');
        $this->assertStringContainsString('VenueId: 4, Revenue: 35000', $output);
    }

    /**
     * @depends testInsertDataWithDatatypes
     */
    public function testAddJsonColumn()
    {
        $output = $this->runAdminFunctionSnippet('add_json_column');
        $this->assertStringContainsString('Waiting for operation to complete...', $output);
        $this->assertStringContainsString('Added VenueDetails as a JSON column in Venues table', $output);
    }

    /**
     * @depends testAddJsonColumn
     */
    public function testUpdateDataJson()
    {
        $output = $this->runFunctionSnippet('update_data_with_json_column');
        $this->assertEquals('Updated data.' . PHP_EOL, $output);
    }

    /**
     * @depends testUpdateDataJson
     */
    public function testQueryDataJson()
    {
        $output = $this->runFunctionSnippet('query_data_with_json_parameter');
        $this->assertStringContainsString('VenueId: 19, VenueDetails: ', $output);
    }

    /**
     * @depends testInsertDataWithDatatypes
     */
    public function testSetTransactionTag()
    {
        $output = $this->runFunctionSnippet('set_transaction_tag');
        $this->assertStringContainsString('Venue capacities updated.', $output);
        $this->assertStringContainsString('New venue inserted.', $output);
    }

    /**
     * @depends testInsertData
     */
    public function testSetRequestTag()
    {
        $output = $this->runFunctionSnippet('set_request_tag');
        $this->assertStringContainsString('SingerId: 1, AlbumId: 1, AlbumTitle: Total Junk', $output);
    }

    /**
     * @depends testInsertDataWithDatatypes
     */
    public function testCreateClientWithQueryOptions()
    {
        $this->runEventuallyConsistentTest(function () {
            $output = $this->runFunctionSnippet('create_client_with_query_options');
            self::$lastUpdateDataTimestamp = time();
            $this->assertStringContainsString('VenueId: 4, VenueName: Venue 4, LastUpdateTime:', $output);
            $this->assertStringContainsString('VenueId: 19, VenueName: Venue 19, LastUpdateTime:', $output);
            $this->assertStringContainsString('VenueId: 42, VenueName: Venue 42, LastUpdateTime:', $output);
        });
    }

    /**
     * @depends testAddColumn
     */
    public function testSpannerDmlBatchUpdateRequestPriority()
    {
        $output = $this->runFunctionSnippet('dml_batch_update_request_priority');
        $this->assertStringContainsString('Executed 2 SQL statements using Batch DML with PRIORITY_LOW.', $output);
    }

    /**
     * @depends testCreateDatabase
     */
    public function testDmlReturningInsert()
    {
        $output = $this->runFunctionSnippet('insert_dml_returning');

        $expectedOutput = sprintf('Melissa Garcia inserted');
        $this->assertStringContainsString($expectedOutput, $output);

        $expectedOutput = sprintf('Russell Morales inserted');
        $this->assertStringContainsString($expectedOutput, $output);

        $expectedOutput = sprintf('Jacqueline Long inserted');
        $this->assertStringContainsString($expectedOutput, $output);

        $expectedOutput = sprintf('Dylan Shaw inserted');
        $this->assertStringContainsString($expectedOutput, $output);

        $expectedOutput = sprintf('Inserted row(s) count: 4');
        $this->assertStringContainsString($expectedOutput, $output);
    }

    /**
     * @depends testUpdateData
     */
    public function testDmlReturningUpdate()
    {
        $db = self::$instance->database(self::$databaseId);
        $db->runTransaction(function (Transaction $t) {
            $t->update('Albums', [
                'AlbumId' => 1,
                'SingerId' => 1,
                'MarketingBudget' => 1000
            ]);
            $t->commit();
        });

        $output = $this->runFunctionSnippet('update_dml_returning');

        $expectedOutput = sprintf('MarketingBudget: 2000');
        $this->assertStringContainsString($expectedOutput, $output);

        $expectedOutput = sprintf('Updated row(s) count: 1');
        $this->assertStringContainsString($expectedOutput, $output);
    }

    /**
     * @depends testDmlReturningInsert
     */
    public function testDmlReturningDelete()
    {
        $db = self::$instance->database(self::$databaseId);
        $db->runTransaction(function (Transaction $t) {
            $t->insert('Singers', [
                'SingerId' => 3,
                'FirstName' => 'Alice',
                'LastName' => 'Trentor'
            ]);
            $t->commit();
        });

        $output = $this->runFunctionSnippet('delete_dml_returning');

        $expectedOutput = sprintf('3 Alice Trentor');
        $this->assertStringContainsString($expectedOutput, $output);

        $expectedOutput = sprintf('Deleted row(s) count: 1');
        $this->assertStringContainsString($expectedOutput, $output);
    }

    /**
     * @depends testCreateDatabase
     */
    public function testAddDropDatabaseRole()
    {
        $output = $this->runAdminFunctionSnippet('add_drop_database_role');
        $this->assertStringContainsString('Waiting for create role and grant operation to complete...' . PHP_EOL, $output);
        $this->assertStringContainsString('Created roles new_parent and new_child and granted privileges' . PHP_EOL, $output);
        $this->assertStringContainsString('Waiting for revoke role and drop role operation to complete...' . PHP_EOL, $output);
        $this->assertStringContainsString('Revoked privileges and dropped role new_child' . PHP_EOL, $output);
    }

    /**
     * @depends testAddDropDatabaseRole
     */
    public function testListDatabaseRoles()
    {
        $output = $this->runFunctionSnippet('list_database_roles', [
            self::$projectId,
            self::$instanceId,
            self::$databaseId
        ]);
        $this->assertStringContainsString(sprintf('databaseRoles/%s', self::$databaseRole), $output);
    }

    /**
     * @depends testAddDropDatabaseRole
     * @depends testInsertDataWithDml
     */
    public function testReadDataWithDatabaseRole()
    {
        $output = $this->runFunctionSnippet('read_data_with_database_role');
        $this->assertStringContainsString('SingerId: 10, Firstname: Virginia, LastName: Watson', $output);
    }

    /**
     * depends testAddDropDatabaseRole
     */
    public function testEnableFineGrainedAccess()
    {
        self::$serviceAccountEmail = $this->createServiceAccount(str_shuffle('testSvcAcnt'));
        $output = $this->runFunctionSnippet('enable_fine_grained_access', [
            self::$projectId,
            self::$instanceId,
            self::$databaseId,
            sprintf('serviceAccount:%s', self::$serviceAccountEmail),
            self::$databaseRole,
            'DatabaseRoleBindingTitle'
        ]);
        $this->assertStringContainsString('Enabled fine-grained access in IAM', $output);
    }

    /**
     * @depends testUpdateData
     */
    public function testReadWriteRetry()
    {
        $output = $this->runFunctionSnippet('read_write_retry');
        $this->assertStringContainsString('Setting second album\'s budget as the first album\'s budget.', $output);
        $this->assertStringContainsString('Transaction complete.', $output);
    }

    /**
     * @depends testCreateDatabase
     */
    public function testCreateSequence()
    {
        $output = $this->runAdminFunctionSnippet('create_sequence');
        $this->assertStringContainsString(
            'Created Seq sequence and Customers table, where ' .
            'the key column CustomerId uses the sequence as a default value',
            $output
        );
        $this->assertStringContainsString('Number of customer records inserted is: 3', $output);
    }

    /**
     * @depends testCreateSequence
     */
    public function testAlterSequence()
    {
        $output = $this->runAdminFunctionSnippet('alter_sequence');
        $this->assertStringContainsString(
            'Altered Seq sequence to skip an inclusive range between 1000 and 5000000',
            $output
        );
        $this->assertStringContainsString('Number of customer records inserted is: 3', $output);
    }

    /**
     * @depends testAlterSequence
     */
    public function testDropSequence()
    {
        $output = $this->runAdminFunctionSnippet('drop_sequence');
        $this->assertStringContainsString(
            'Altered Customers table to drop DEFAULT from CustomerId ' .
            'column and dropped the Seq sequence',
            $output
        );
    }

    public function testGetInstanceConfig()
    {
        $output = $this->runAdminFunctionSnippet('get_instance_config', [
            'project_id' => self::$projectId,
            'instance_config' => self::$instanceConfig
        ]);
        $this->assertStringContainsString(self::$instanceConfig, $output);
    }

    public function testListInstanceConfigs()
    {
        $output = $this->runAdminFunctionSnippet('list_instance_configs', [
            'project_id' => self::$projectId
        ]);
        $this->assertStringContainsString(
            'Available leader options for instance config',
            $output
        );
    }

    public function testCreateDatabaseWithDefaultLeader()
    {
        $output = $this->runAdminFunctionSnippet('create_database_with_default_leader', [
            'project_id' => self::$projectId,
            'instance_id' => self::$multiInstanceId,
            'database_id' => self::$multiDatabaseId,
            'defaultLeader' => self::$defaultLeader
        ]);
        $this->assertStringContainsString(self::$defaultLeader, $output);
    }

    /**
     * @depends testCreateDatabaseWithDefaultLeader
     */
    private function testQueryInformationSchemaDatabaseOptions()
    {
        $output = $this->runFunctionSnippet('query_information_schema_database_options', [
            'instance_id' => self::$multiInstanceId,
            'database_id' => self::$multiDatabaseId,
        ]);
        $this->assertStringContainsString(self::$defaultLeader, $output);
    }

    /**
     * @depends testCreateDatabaseWithDefaultLeader
     */
    public function testUpdateDatabaseWithDefaultLeader()
    {
        $output = $this->runAdminFunctionSnippet('update_database_with_default_leader', [
            'project_id' => self::$projectId,
            'instance_id' => self::$multiInstanceId,
            'database_id' => self::$multiDatabaseId,
            'defaultLeader' => self::$updatedDefaultLeader
        ]);
        $this->assertStringContainsString(self::$updatedDefaultLeader, $output);
    }

    /**
     * @depends testUpdateDatabaseWithDefaultLeader
     */
    public function testGetDatabaseDdl()
    {
        $output = $this->runAdminFunctionSnippet('get_database_ddl', [
            'project_id' => self::$projectId,
            'instance_id' => self::$multiInstanceId,
            'database_id' => self::$multiDatabaseId,
        ]);
        $this->assertStringContainsString(self::$multiDatabaseId, $output);
        $this->assertStringContainsString(self::$updatedDefaultLeader, $output);
    }

    /**
     * @depends testUpdateDatabaseWithDefaultLeader
     */
    public function testListDatabases()
    {
        $output = $this->runAdminFunctionSnippet('list_databases', [
            'project_id' => self::$projectId,
            'instance_id' => self::$multiInstanceId,
        ]);
        $this->assertStringContainsString(self::$multiDatabaseId, $output);
        $this->assertStringContainsString(self::$updatedDefaultLeader, $output);
    }

    private function runFunctionSnippet($sampleName, $params = [])
    {
        return $this->traitRunFunctionSnippet(
            $sampleName,
            array_values($params) ?: [self::$instanceId, self::$databaseId]
        );
    }

    private function runAdminFunctionSnippet($sampleName, $params = [])
    {
        return $this->traitRunFunctionSnippet(
            $sampleName,
            array_values($params) ?: [self::$projectId, self::$instanceId, self::$databaseId]
        );
    }

    private function createServiceAccount($serviceAccountId)
    {
        $client = self::getIamHttpClient();
        // make the request
        $response = $client->post('/v1/projects/' . self::$projectId . '/serviceAccounts', [
            'json' => [
                'accountId' => $serviceAccountId,
                'serviceAccount' => [
                    'displayName' => 'Test Service Account',
                    'description' => 'This account should be deleted automatically after the unit tests complete.'
                ]
            ]
        ]);

        return json_decode($response->getBody())->email;
    }

    public static function deleteServiceAccount($serviceAccountEmail)
    {
        $client = self::getIamHttpClient();
        // make the request
        $client->delete('/v1/projects/' . self::$projectId . '/serviceAccounts/' . $serviceAccountEmail);
    }

    private static function getIamHttpClient()
    {
        // TODO: When this method is exposed in googleapis/google-cloud-php, remove the use of the following
        $scopes = ['https://www.googleapis.com/auth/cloud-platform'];

        // create middleware
        $middleware = ApplicationDefaultCredentials::getMiddleware($scopes);
        $stack = HandlerStack::create();
        $stack->push($middleware);

        // create the HTTP client
        $client = new Client([
            'handler' => $stack,
            'base_uri' => 'https://iam.googleapis.com',
            'auth' => 'google_auth'  // authorize all requests
        ]);
        return $client;
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$instance->exists()) {// Clean up database
            $database = self::$instance->database(self::$databaseId);
            $database->drop();
        }
        if (self::$multiInstance->exists()) {//Clean up database
            $database = self::$multiInstance->database(self::$databaseId);
            $database->drop();
        }
        self::$instance->delete();
        self::$lowCostInstance->delete();
        self::$instancePartitionInstance->delete();
        if (self::$customInstanceConfig->exists()) {
            self::$customInstanceConfig->delete();
        }
        if (!is_null(self::$serviceAccountEmail)) {
            self::deleteServiceAccount(self::$serviceAccountEmail);
        }
    }

    public function testCreateTableForeignKeyDeleteCascade()
    {
        $output = $this->runAdminFunctionSnippet('create_table_with_foreign_key_delete_cascade');
        $this->assertStringContainsString('Waiting for operation to complete...', $output);
        $this->assertStringContainsString(
            'Created Customers and ShoppingCarts table with FKShoppingCartsCustomerId ' .
            'foreign key constraint on database',
            $output
        );
    }

    /**
     * @depends testCreateTableForeignKeyDeleteCascade
     */
    public function testAlterTableDropForeignKeyDeleteCascade()
    {
        $output = $this->runAdminFunctionSnippet('drop_foreign_key_constraint_delete_cascade');
        $this->assertStringContainsString('Waiting for operation to complete...', $output);
        $this->assertStringContainsString(
            'Altered ShoppingCarts table to drop FKShoppingCartsCustomerName ' .
            'foreign key constraint on database',
            $output
        );
    }

    /**
     * @depends testAlterTableDropForeignKeyDeleteCascade
     */
    public function testAlterTableAddForeignKeyDeleteCascade()
    {
        $output = $this->runAdminFunctionSnippet('alter_table_with_foreign_key_delete_cascade');
        $this->assertStringContainsString('Waiting for operation to complete...', $output);
        $this->assertStringContainsString(
            'Altered ShoppingCarts table with FKShoppingCartsCustomerName ' .
            'foreign key constraint on database',
            $output
        );
    }

    /**
     * @depends testInsertData
     */
    public function testDirectedRead()
    {
        $output = $this->runFunctionSnippet('directed_read');
        $this->assertStringContainsString('SingerId: 1, AlbumId: 1, AlbumTitle: Total Junk', $output);
        $this->assertStringContainsString('SingerId: 1, AlbumId: 2, AlbumTitle: Go, Go, Go', $output);
        $this->assertStringContainsString('SingerId: 2, AlbumId: 1, AlbumTitle: Green', $output);
        $this->assertStringContainsString('SingerId: 2, AlbumId: 2, AlbumTitle: Forever Hold Your Peace', $output);
        $this->assertStringContainsString('SingerId: 2, AlbumId: 3, AlbumTitle: Terrified', $output);
    }
}
