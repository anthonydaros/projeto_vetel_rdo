<?php
/**
 * Migration script to normalize photo extensions to .jpg
 * Handles the inconsistency between .jpg and .jpeg extensions
 *
 * Usage: php scripts/migrate_photo_extensions.php [--dry-run]
 */

require_once __DIR__ . '/../startup.php';
use Models\Connection;
use Models\DAO;

// Check for dry-run mode
$dryRun = in_array('--dry-run', $argv);

if ($dryRun) {
	echo "=== DRY RUN MODE - No changes will be made ===\n\n";
}

try {
	$pdo = Connection::getPDO();
	$dao = new DAO($pdo);

	// Get photo storage path
	$photoPath = __DIR__ . '/../img/album';

	echo "Photo Migration Script\n";
	echo "=====================\n";
	echo "Photo directory: $photoPath\n\n";

	// Step 1: Scan all files in the photo directory
	echo "Step 1: Scanning photo directory...\n";
	$files = glob($photoPath . '/*');
	$fileMap = [];
	$extensionStats = [];

	foreach ($files as $file) {
		if (!is_file($file)) continue;

		$filename = basename($file);
		$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

		// Count extensions for statistics
		if (!isset($extensionStats[$extension])) {
			$extensionStats[$extension] = 0;
		}
		$extensionStats[$extension]++;

		// Store file info
		$fileMap[$filename] = [
			'path' => $file,
			'extension' => $extension,
			'needs_rename' => ($extension === 'jpeg')
		];
	}

	echo "Found " . count($fileMap) . " files:\n";
	foreach ($extensionStats as $ext => $count) {
		echo "  - .$ext: $count files\n";
	}
	echo "\n";

	// Step 2: Rename .jpeg files to .jpg
	echo "Step 2: Renaming .jpeg files to .jpg...\n";
	$renamedFiles = 0;
	$renameErrors = 0;

	foreach ($fileMap as $filename => $info) {
		if ($info['needs_rename']) {
			$oldPath = $info['path'];
			$newFilename = preg_replace('/\.jpeg$/i', '.jpg', $filename);
			$newPath = $photoPath . '/' . $newFilename;

			// Check if target file already exists
			if (file_exists($newPath)) {
				echo "  ⚠️  Cannot rename $filename -> $newFilename (file already exists)\n";
				$renameErrors++;
				continue;
			}

			if (!$dryRun) {
				if (rename($oldPath, $newPath)) {
					echo "  ✅ Renamed: $filename -> $newFilename\n";
					$renamedFiles++;

					// Update our map for database updates
					$fileMap[$filename]['new_filename'] = $newFilename;
				} else {
					echo "  ❌ Failed to rename: $filename\n";
					$renameErrors++;
				}
			} else {
				echo "  [DRY RUN] Would rename: $filename -> $newFilename\n";
				$renamedFiles++;
				$fileMap[$filename]['new_filename'] = $newFilename;
			}
		}
	}

	echo "\nRenamed $renamedFiles files";
	if ($renameErrors > 0) {
		echo " ($renameErrors errors)";
	}
	echo "\n\n";

	// Step 3: Update database references
	echo "Step 3: Updating database references...\n";

	// Get all image records from database
	$query = "SELECT id_imagem, url FROM imagem ORDER BY id_imagem";
	$stmt = $pdo->query($query);
	$images = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$updatedRecords = 0;
	$dbErrors = 0;

	foreach ($images as $image) {
		$currentUrl = $image['url'];
		$needsUpdate = false;
		$newUrl = $currentUrl;

		// Check if this URL needs updating
		if (strpos($currentUrl, '.jpeg') !== false) {
			$newUrl = str_replace('.jpeg', '.jpg', $currentUrl);
			$needsUpdate = true;
		}

		// Also check if the file was renamed
		foreach ($fileMap as $oldFilename => $info) {
			if (isset($info['new_filename']) &&
				(basename($currentUrl) === $oldFilename || $currentUrl === $oldFilename)) {
				$newUrl = $info['new_filename'];
				$needsUpdate = true;
				break;
			}
		}

		if ($needsUpdate) {
			if (!$dryRun) {
				$updateQuery = "UPDATE imagem SET url = :new_url WHERE id_imagem = :id";
				$updateStmt = $pdo->prepare($updateQuery);

				try {
					$updateStmt->execute([
						':new_url' => $newUrl,
						':id' => $image['id_imagem']
					]);
					echo "  ✅ Updated DB record #" . $image['id_imagem'] . ": $currentUrl -> $newUrl\n";
					$updatedRecords++;
				} catch (Exception $e) {
					echo "  ❌ Failed to update record #" . $image['id_imagem'] . ": " . $e->getMessage() . "\n";
					$dbErrors++;
				}
			} else {
				echo "  [DRY RUN] Would update DB record #" . $image['id_imagem'] . ": $currentUrl -> $newUrl\n";
				$updatedRecords++;
			}
		}
	}

	echo "\nUpdated $updatedRecords database records";
	if ($dbErrors > 0) {
		echo " ($dbErrors errors)";
	}
	echo "\n\n";

	// Step 4: Verify consistency
	echo "Step 4: Verifying consistency...\n";

	// Check for orphaned database records (references to non-existent files)
	$orphanedRecords = 0;
	$stmt = $pdo->query($query);
	$images = $stmt->fetchAll(PDO::FETCH_ASSOC);

	foreach ($images as $image) {
		$url = $image['url'];
		$filename = basename($url);
		$filepath = $photoPath . '/' . $filename;

		if (!file_exists($filepath)) {
			echo "  ⚠️  Orphaned DB record #" . $image['id_imagem'] . ": $url (file not found)\n";
			$orphanedRecords++;
		}
	}

	// Check for orphaned files (files not referenced in database)
	$currentFiles = glob($photoPath . '/*');
	$orphanedFiles = 0;

	foreach ($currentFiles as $file) {
		if (!is_file($file)) continue;

		$filename = basename($file);
		$found = false;

		foreach ($images as $image) {
			if (basename($image['url']) === $filename || $image['url'] === $filename) {
				$found = true;
				break;
			}
		}

		if (!$found) {
			echo "  ⚠️  Orphaned file: $filename (not in database)\n";
			$orphanedFiles++;
		}
	}

	// Final summary
	echo "\n";
	echo "=====================\n";
	echo "Migration Summary\n";
	echo "=====================\n";
	echo "Files renamed: $renamedFiles\n";
	echo "Database records updated: $updatedRecords\n";
	echo "Orphaned database records: $orphanedRecords\n";
	echo "Orphaned files: $orphanedFiles\n";

	if ($renameErrors > 0 || $dbErrors > 0) {
		echo "\n⚠️  Migration completed with errors\n";
		exit(1);
	} else if ($orphanedRecords > 0 || $orphanedFiles > 0) {
		echo "\n⚠️  Migration completed with warnings\n";
		exit(0);
	} else {
		echo "\n✅ Migration completed successfully\n";
		exit(0);
	}

} catch (Exception $e) {
	echo "\n❌ Fatal error: " . $e->getMessage() . "\n";
	exit(1);
}