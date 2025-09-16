<?php
/**
 * Health check endpoint for photo storage system
 * Monitors photo directory, permissions, and database consistency
 *
 * Returns JSON with status information
 */

header('Content-Type: application/json');

require_once __DIR__ . '/startup.php';
use Models\Connection;
use Models\DAO;
use Config\Config;

$response = [
	'status' => 'healthy',
	'timestamp' => date('Y-m-d H:i:s'),
	'checks' => []
];

try {
	// Check 1: Photo directory exists and is writable
	$photoPath = Config::get('PHOTO_STORAGE_PATH', 'img/album');
	$absolutePhotoPath = __DIR__ . '/' . $photoPath;

	$dirCheck = [
		'name' => 'photo_directory',
		'status' => 'ok',
		'details' => []
	];

	if (!is_dir($absolutePhotoPath)) {
		$dirCheck['status'] = 'error';
		$dirCheck['details']['error'] = 'Photo directory does not exist';
		$response['status'] = 'unhealthy';
	} else {
		$dirCheck['details']['path'] = $absolutePhotoPath;
		$dirCheck['details']['exists'] = true;

		// Check write permissions
		if (!is_writable($absolutePhotoPath)) {
			$dirCheck['status'] = 'warning';
			$dirCheck['details']['writable'] = false;
			$dirCheck['details']['error'] = 'Directory is not writable';
			$response['status'] = 'degraded';
		} else {
			$dirCheck['details']['writable'] = true;
		}

		// Count files
		$files = glob($absolutePhotoPath . '/*');
		$fileCount = 0;
		$totalSize = 0;
		$extensions = [];

		foreach ($files as $file) {
			if (is_file($file)) {
				$fileCount++;
				$totalSize += filesize($file);
				$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
				if (!isset($extensions[$ext])) {
					$extensions[$ext] = 0;
				}
				$extensions[$ext]++;
			}
		}

		$dirCheck['details']['file_count'] = $fileCount;
		$dirCheck['details']['total_size_mb'] = round($totalSize / (1024 * 1024), 2);
		$dirCheck['details']['extensions'] = $extensions;

		// Check disk space
		$freeSpace = disk_free_space($absolutePhotoPath);
		$totalSpace = disk_total_space($absolutePhotoPath);

		if ($freeSpace !== false && $totalSpace !== false) {
			$usedPercent = (($totalSpace - $freeSpace) / $totalSpace) * 100;
			$dirCheck['details']['disk_free_gb'] = round($freeSpace / (1024 * 1024 * 1024), 2);
			$dirCheck['details']['disk_used_percent'] = round($usedPercent, 2);

			if ($usedPercent > 90) {
				$dirCheck['status'] = 'warning';
				$dirCheck['details']['warning'] = 'Disk space running low';
				$response['status'] = 'degraded';
			}
		}
	}

	$response['checks'][] = $dirCheck;

	// Check 2: Database connectivity and consistency
	$dbCheck = [
		'name' => 'database_consistency',
		'status' => 'ok',
		'details' => []
	];

	try {
		$pdo = Connection::getPDO();
		$dao = new DAO($pdo);

		// Count total image records
		$query = "SELECT COUNT(*) as total, COUNT(DISTINCT fk_id_diario_obra) as diarios FROM imagem";
		$stmt = $pdo->query($query);
		$result = $stmt->fetch(PDO::FETCH_ASSOC);

		$dbCheck['details']['total_records'] = $result['total'];
		$dbCheck['details']['diarios_with_photos'] = $result['diarios'];

		// Check for orphaned records (sample check)
		$orphanedCount = 0;
		$sampleSize = 100; // Check first 100 records for performance

		$query = "SELECT url FROM imagem ORDER BY id_imagem DESC LIMIT $sampleSize";
		$stmt = $pdo->query($query);
		$recentImages = $stmt->fetchAll(PDO::FETCH_ASSOC);

		foreach ($recentImages as $image) {
			$filename = basename($image['url']);
			$testPaths = [
				$absolutePhotoPath . '/' . $filename,
				$absolutePhotoPath . '/' . str_replace('.jpeg', '.jpg', $filename),
				$absolutePhotoPath . '/' . str_replace('.jpg', '.jpeg', $filename)
			];

			$found = false;
			foreach ($testPaths as $testPath) {
				if (file_exists($testPath)) {
					$found = true;
					break;
				}
			}

			if (!$found) {
				$orphanedCount++;
			}
		}

		if ($orphanedCount > 0) {
			$dbCheck['details']['orphaned_records_sample'] = $orphanedCount . '/' . $sampleSize;
			$dbCheck['status'] = 'warning';
			$response['status'] = 'degraded';
		}

		$dbCheck['details']['database_connected'] = true;

	} catch (Exception $e) {
		$dbCheck['status'] = 'error';
		$dbCheck['details']['database_connected'] = false;
		$dbCheck['details']['error'] = $e->getMessage();
		$response['status'] = 'unhealthy';
	}

	$response['checks'][] = $dbCheck;

	// Check 3: Recent upload activity (last 24 hours)
	$activityCheck = [
		'name' => 'recent_activity',
		'status' => 'ok',
		'details' => []
	];

	try {
		// Check for recent uploads based on file modification time
		$recentFiles = 0;
		$cutoffTime = time() - (24 * 60 * 60); // 24 hours ago

		if (isset($files) && is_array($files)) {
			foreach ($files as $file) {
				if (is_file($file) && filemtime($file) > $cutoffTime) {
					$recentFiles++;
				}
			}
		}

		$activityCheck['details']['files_last_24h'] = $recentFiles;

		// Check recent database entries
		if (isset($pdo)) {
			$query = "SELECT COUNT(*) as count FROM imagem WHERE DATE(data_cadastro) >= DATE_SUB(NOW(), INTERVAL 1 DAY)";
			$stmt = $pdo->query($query);
			$result = $stmt->fetch(PDO::FETCH_ASSOC);

			$activityCheck['details']['db_records_last_24h'] = $result['count'];
		}

	} catch (Exception $e) {
		$activityCheck['status'] = 'warning';
		$activityCheck['details']['error'] = $e->getMessage();
	}

	$response['checks'][] = $activityCheck;

	// Check 4: Volume mount status (for Docker)
	$volumeCheck = [
		'name' => 'volume_status',
		'status' => 'ok',
		'details' => []
	];

	// Check if running in Docker
	if (file_exists('/.dockerenv') || getenv('DOCKER_CONTAINER') === 'true') {
		$volumeCheck['details']['running_in_docker'] = true;

		// Check if volume is mounted (photo directory should persist)
		$testFile = $absolutePhotoPath . '/.volume_test_' . time();
		$volumeMounted = false;

		if (@touch($testFile)) {
			@unlink($testFile);
			$volumeMounted = true;
		}

		$volumeCheck['details']['volume_mounted'] = $volumeMounted;

		if (!$volumeMounted) {
			$volumeCheck['status'] = 'error';
			$volumeCheck['details']['error'] = 'Volume not properly mounted';
			$response['status'] = 'unhealthy';
		}
	} else {
		$volumeCheck['details']['running_in_docker'] = false;
	}

	$response['checks'][] = $volumeCheck;

	// Overall health determination
	$errorCount = 0;
	$warningCount = 0;

	foreach ($response['checks'] as $check) {
		if ($check['status'] === 'error') $errorCount++;
		if ($check['status'] === 'warning') $warningCount++;
	}

	if ($errorCount > 0) {
		$response['status'] = 'unhealthy';
	} else if ($warningCount > 0) {
		$response['status'] = 'degraded';
	}

	// Set appropriate HTTP status code
	if ($response['status'] === 'unhealthy') {
		http_response_code(503); // Service Unavailable
	} else if ($response['status'] === 'degraded') {
		http_response_code(200); // Still OK, but with warnings
	}

} catch (Exception $e) {
	$response['status'] = 'unhealthy';
	$response['error'] = $e->getMessage();
	http_response_code(503);
}

// Add response time
$response['response_time_ms'] = round((microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"]) * 1000, 2);

// Output JSON response
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);