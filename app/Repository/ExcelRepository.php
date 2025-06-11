<?php

namespace App\Repository;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as Reader;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\NamedRange;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Symfony\Component\HttpFoundation\StreamedResponse;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExcelRepository
{
	/**
	 * Create and return a new spreadsheet instance
	 *
	 * @return Spreadsheet
	 */
	public function newSpreadsheet(): Spreadsheet
	{
		return new Spreadsheet;
	}

	/**
	 * Set the header row of a worksheet
	 *
	 * @param Worksheet $activeSheet
	 * @param array $headerArray
	 * @return void
	 */
	public function setHeader(Worksheet $activeSheet, array $headerArray): void
	{
		$styleArray = [
			'alignment' => [
				'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
				'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
			],
		];

		$row = 1;
		$col = 'A';
		foreach ($headerArray as $header) {
			$activeSheet->setCellValue($col . $row, $header);
			$activeSheet->getStyle($col . $row)->applyFromArray($styleArray);
			$col++;
		}
	}

	/**
	 * Write a single row of data to the worksheet
	 *
	 * @param Worksheet $sheet
	 * @param array $data
	 * @param int $rowIndex
	 * @return void
	 */
	public function writeRow(Worksheet $sheet, array $data, int $rowIndex): void
	{
		$colIndex = 'A';
		foreach ($data as $cell) {
			$sheet->setCellValue($colIndex . $rowIndex, $cell);
			$colIndex++;
		}
	}

	/**
	 * Set a dropdown (data validation) in a specific cell with fallback for long lists using a hidden sheet
	 *
	 * @param Spreadsheet $spreadsheet
	 * @param Worksheet $sheet
	 * @param string $cell
	 * @param string $attributeName
	 * @param array $dropdownVals
	 * @param string $existingVal
	 * @return void
	 */
	public function setDropdown(Spreadsheet $spreadsheet, Worksheet $sheet, string $cell, string $attributeName, array $dropdownVals, string $existingVal = ''): void
	{
		if (empty($dropdownVals)) {
			// throw new \Exception('Dropdown values must be a non-empty array.');
		}

		/* Escape quotes ONLY for the formula */
		$escapedDropdownVals = array_map(fn($val) => str_replace('"', '""', $val), $dropdownVals);
		$formula = '"' . implode(',', $escapedDropdownVals) . '"';

		/* Check if formula exceeds Excel's 255-character limit */
		if (strlen($formula) > 255) {
			/* Use named range if the dropdown list is too long */
			$hiddenSheetName = 'DropdownData';
			$hiddenSheet = $spreadsheet->getSheetByName($hiddenSheetName);

			/* Create hidden sheet if it doesn't exist */
			if (!$hiddenSheet) {
				$hiddenSheet = $spreadsheet->createSheet();
				$hiddenSheet->setTitle($hiddenSheetName);
			}

			$col = null;
			$lastCol = $hiddenSheet->getHighestColumn();
			$maxColIndex = Coordinate::columnIndexFromString($lastCol); /* Convert to number */

			/* If the first column is empty, force start at Column A */
			$firstCell = $hiddenSheet->getCell('A1')->getValue();
			if (!$firstCell) {
				$maxColIndex = 0; /* Ensures first assignment is "A" */
			}

			/* Check if attribute already exists */
			for ($i = 1; $i <= $maxColIndex; $i++) {
				$existingAttribute = $hiddenSheet->getCell(Coordinate::stringFromColumnIndex($i) . '1')->getValue();
				if ($existingAttribute === $attributeName) {
					$col = Coordinate::stringFromColumnIndex($i);
					break;
				}
			}

			/* If attribute column does not exist, create a new one */
			if (!$col) {
				$col = Coordinate::stringFromColumnIndex($maxColIndex + 1);
				$hiddenSheet->setCellValue($col . '1', $attributeName);
			}

			/* Insert dropdown values into the found/created column starting from row 2 */
			$startRow = 2;
			foreach ($dropdownVals as $index => $value) {
				$hiddenSheet->setCellValue($col . ($startRow + $index), $value);
			}

			/* Define the named range dynamically with absolute references */
			$endRow = $startRow + count($dropdownVals) - 1;
			$uniqueRangeName = "Dropdown_$attributeName";
			$namedRangeReference = "$hiddenSheetName!\${$col}\$${startRow}:\${$col}\$${endRow}";

			/* Check if named range already exists */
			$existingNamedRange = $spreadsheet->getNamedRange($uniqueRangeName);
			if (!$existingNamedRange) {
				$namedRange = new NamedRange($uniqueRangeName, $hiddenSheet, "$col$startRow:$col$endRow");
				$spreadsheet->addNamedRange($namedRange);
			}

			/* Use absolute reference in the formula */
			$formula = "=$namedRangeReference";
		}

		/* Apply data validation */
		$validation = $sheet->getCell($cell)->getDataValidation();
		$validation->setType(DataValidation::TYPE_LIST);
		$validation->setErrorStyle(DataValidation::STYLE_STOP);
		$validation->setAllowBlank(true);
		$validation->setShowDropDown(true);
		$validation->setErrorTitle('Invalid Selection');
		$validation->setError('Please select a value from the dropdown list.');
		$validation->setFormula1($formula);

		$sheet->getCell($cell)->setDataValidation($validation);

		/* Set the existing value if it's valid */
		if ($existingVal !== '' && in_array($existingVal, $dropdownVals, true)) {
			$sheet->setCellValue($cell, $existingVal);
		}
	}

	/**
	 * Apply thin border around a range in the active worksheet
	 *
	 * @param Spreadsheet $spreadsheet
	 * @param string $range
	 * @return void
	 */
	public function setBorder(Spreadsheet $spreadsheet, string $range): void
	{
		$spreadsheet->getActiveSheet()->getStyle($range)->applyFromArray([
			'borders' => [
				'allBorders' => [
					'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
					'color' => ['argb' => '000000'],
				],
			],
		]);
	}

	/**
	 * Load and return a Spreadsheet object from a file
	 *
	 * @param string $fileName
	 * @return Spreadsheet
	 */
	public function loadFile(string $fileName): Spreadsheet
	{
		return IOFactory::load($fileName);
	}

	/**
	 * Download an Excel file via streamed response
	 *
	 * @param string $fileName
	 * @param Spreadsheet $excelObject
	 * @return StreamedResponse
	 */
	public function downloadFile(string $fileName, Spreadsheet $excelObject): StreamedResponse
	{
		return response()->streamDownload(function () use ($excelObject) {
			$writer = new Xlsx($excelObject);
			$writer->save('php://output');
		}, $fileName, [
			'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			'Access-Control-Allow-Origin' => '*',
			'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
			'Access-Control-Allow-Headers' => 'Origin, Content-Type, Accept, Authorization',
			'Cache-Control' => 'no-cache, must-revalidate',
			'Pragma' => 'public',
			'Expires' => '0',
		]);
	}

	/**
	 * Save the Excel file to a specified path
	 *
	 * @param string $fileNameWithPath
	 * @param Spreadsheet $excelObject
	 * @return void
	 */
	public function saveFile(string $fileNameWithPath, Spreadsheet $excelObject): void
	{
		$writer = IOFactory::createWriter($excelObject, "Xlsx");
		$writer->save($fileNameWithPath);
	}

	/**
	 * Save the spreadsheet as a CSV file
	 *
	 * @param string $fileNameWithPath
	 * @param Spreadsheet $csvObject
	 * @return void
	 */
	public function saveCsvFile(string $fileNameWithPath, Spreadsheet $csvObject): void
	{
		$writer = new \PhpOffice\PhpSpreadsheet\Writer\Csv($csvObject);
		$writer->save($fileNameWithPath);
	}

	/**
	 * Return metadata about all worksheets in an Excel file
	 *
	 * @param string $fileName
	 * @return array
	 */
	public function getAllWorksheetInfo(string $fileName): array
	{
		$reader = new Reader();
		return $reader->listWorksheetInfo($fileName);
	}

	/**
	 * Load specific rows and columns from a named worksheet in an Excel file
	 *
	 * @param string $fileName
	 * @param string $worksheetName
	 * @param int $startRow
	 * @param int $endRow
	 * @param string $lastColumnLetter
	 * @return array
	 */
	public function loadExcelFileData(string $fileName, string $worksheetName, int $startRow, int $endRow, string $lastColumnLetter): array
	{
		$reader = new Reader();
		$worksheetList = $reader->listWorksheetNames($fileName);
		$reader->setReadDataOnly(true);
		$reader->setReadEmptyCells(false);
		$reader->setLoadSheetsOnly([$worksheetName]);
		$chunkFilter = new ChunkReadFilter();
		$chunkFilter->setRows($startRow, $endRow);
		$reader->setReadFilter($chunkFilter);

		$spreadsheet = $reader->load($fileName);
		$sheet = $spreadsheet->getSheetByName($worksheetName);
		return $sheet->rangeToArray("A{$startRow}:{$lastColumnLetter}{$endRow}");
	}
}

/**
 * Custom chunk filter to load only specific rows from an Excel file
 */
class ChunkReadFilter implements \PhpOffice\PhpSpreadsheet\Reader\IReadFilter
{
	private int $startRow = 0;
	private int $endRow = 0;

	/**
	 * Define the row range to read
	 *
	 * @param int $startRow
	 * @param int $endRow
	 * @return void
	 */
	public function setRows(int $startRow, int $endRow): void
	{
		$this->startRow = $startRow;
		$this->endRow = $endRow;
	}

	/**
	 * Determine whether a given cell should be read
	 *
	 * @param string $column
	 * @param int $row
	 * @param string $worksheetName
	 * @return bool
	 */
	public function readCell(string $column, int $row, string $worksheetName = ''): bool
	{
		return $row >= $this->startRow && $row <= $this->endRow;
	}
}
