<?php

namespace App\Services;

use Illuminate\Support\Facades\Bus;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Validator;
use App\Models\TransactionLog;

class CsvImporterService
{
	public function processCsvImport($file, array $fileFormatArray, string $module, string $queue, string $batchName, string $jobClass, string $userRole = null)
	{
		$requiredRowCount = count($fileFormatArray);
		$requiredHeaderArray = array_keys($fileFormatArray);

		$data = [];

		if (($handle = fopen($file, "r")) !== false) {
			if (($header = fgetcsv($handle, 0, ",", '"', "\\")) !== false) {
				$header = array_map('trim', $header);

				if ($missingColumns = array_diff($requiredHeaderArray, $header)) {
					$columns = implode(', ', array_values($missingColumns));
					$missingCount = count($missingColumns);
					fclose($handle);
					throw new \Exception($missingCount > 1
						? "The uploaded file has an incorrect header. $columns columns are missing."
						: "The uploaded file has an incorrect header. $columns column is missing.");
				}
			}

			$rowIndex = 2;
			while (($row = fgetcsv($handle, 0, ",", '"', "\\")) !== false) {
				$row = array_map(function ($value) {
					if (strpos($value, "\n") !== false || strpos($value, "\r") !== false) {
						$value = '"' . str_replace('"', '""', $value) . '"';
					}
					if (!mb_check_encoding($value, 'UTF-8')) {
						$value = @mb_convert_encoding($value, 'UTF-8', 'auto') ?: utf8_encode($value);
					}
					return trim(preg_replace('/[^\x20-\x7E\xA0-\xFF]/u', '', $value));
				}, $row);

				if (array_filter($row)) {
					if (count($row) != $requiredRowCount) {
						// dd(count($row), $requiredRowCount, $row, $fileFormatArray);
						throw new \Exception("The data in row $rowIndex is not compatible for import.");
					}
					$data[] = $row;
				}
				$rowIndex++;
			}
			fclose($handle);
		}

		if (count($data) == 0) {
			throw new \Exception("The uploaded CSV file does not contain any records. Please ensure the file has valid data and try again.");
		}

		$chunks = array_chunk($data, 100);

		$batch = Bus::batch([])
		->before(function (Batch $batch) use ($module, $data) {
			$descArray = [
				"Total Count" => count($data),
				"Success Count" => 0,
				"Failed Count" => 0,
				"Errors" => []
			];
			TransactionLog::create([
				'module' => $module,
				'action' => "Import",
				'identifier' => $batch->id,
				'status' => 'In-progress',
				'description' => json_encode($descArray, JSON_UNESCAPED_UNICODE),
				'created_by' => auth()->id(),
				'created_at' => now()
			]);
		})
		->catch(function (Batch $batch, Throwable $e) {
			$log = TransactionLog::where('identifier', $batch->id)->first();
			if ($log) {
				TransactionLog::where('id', $log->id)->update([
					'status' => 'Failed',
					'description' => json_encode([
						"Total Count" => 0,
						"Success Count" => 0,
						"Failed Count" => 0,
						"Errors" => [
							"Error: " . $e->getMessage(),
							"File: " . $e->getFile(),
							"Line: " . $e->getLine()
						]
					], JSON_UNESCAPED_UNICODE)
				]);
			}
		})
		->finally(function (Batch $batch) {
			TransactionLog::where('identifier', $batch->id)->update(['status' => 'Completed']);
		})
		->name($batchName)
		->dispatch();

		foreach ($chunks as $chunk) {
			$batch->options['queue'] = $queue;
			$batch->add(new $jobClass([
				'fileFormatArray' => $fileFormatArray,
				'header' => $header,
				'chunk' => $chunk,
				'userId' => auth()->id(),
				'userRole' => $userRole,
			]));
		}
	}
}
