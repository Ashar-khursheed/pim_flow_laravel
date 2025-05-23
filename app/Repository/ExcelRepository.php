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
	 * To create new spreadsheet
	 * @return Spreadsheet
	 */
	public function newSpreadsheet()
	{
		$spreadsheet = new Spreadsheet;
		return $spreadsheet;
	}

	/**
	 * To Set header of excel export file
	 * @return Spreadsheet
	 */
	public function setHeader($activeSheet, $headerArray)
	{
		$styleArray =
		[
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
		$row++;
	}

	/**
	 * To Set dropdown in excel export file
	 * @return Spreadsheet
	 */
	public function setDropdown(Spreadsheet $spreadsheet, Worksheet $sheet, string $cell, string $attributeName, array $dropdownVals, string $existingVal = '')
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
	 * To Set the border in excel file
	 * @return Spreadsheet
	 */
	public function setBorder($spreadsheet, $range)
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
	 * Excel function to load a excel file and return reader object
	 * @param $fileName
	 * @return Spreadsheet
	 */
	public function loadFile($fileName) {
		// $reader =IOFactory::createReaderForFile($fileName);
		// return $reader->load($fileName);
		return IOFactory::load($fileName);
	}

	/**
	 * Function to download excel file based on given filename and excelObject
	 * @param string $fileName
	 * @param $excelObject
	 */
	// public function downloadFile($fileName, $excelObject) {

	// 	header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
	// 	header('Content-Disposition: attachment;filename=' . $fileName);
	// 	header('Cache-Control: max-age=0');
	// 	// If you're serving to IE 9, then the following may be needed
	// 	header('Cache-Control: max-age=1');

	// 	// If you're serving to IE over SSL, then the following may be needed
	// 	header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
	// 	header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
	// 	header('Cache-Control: cache, must-revalidate'); // HTTP/1.1
	// 	header('Pragma: public'); // HTTP/1.0
	// 	$writer = IOFactory::createWriter($excelObject, 'Xlsx');

	// 	$writer->save('php://output');
	// 	exit;
	// }
	public function downloadFile($fileName, $excelObject): StreamedResponse
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
	 * To save the excel file at the given folder
	 */
	public function saveFile($fileNameWithPath, $excelObject) {
		$writer = IOFactory::createWriter($excelObject, "Xlsx");
		$writer->save($fileNameWithPath);
		// return;
	}

	/**
	 * To save the excel file at the given folder
	 */
	public function saveCsvFile($fileNameWithPath, $csvObject) {
		$writer = new \PhpOffice\PhpSpreadsheet\Writer\Csv($csvObject);
		$writer->save($fileNameWithPath);
		// return;
	}

	/**
	 * Get all worksheet details
	 */
	public function getAllWorksheetInfo($fileName)
	{
		$reader = new Reader();
		$worksheetInfo = $reader->listWorksheetInfo($fileName);
		return $worksheetInfo;
	}

	/**
	 * Excel function to load a excel file and return reader object
	 * @param $fileName
	 * @return Spreadsheet
	 */
	public function loadExcelFileData($fileName, $worksheetName, $startRow, $endRow, $lastColumnLetter)
	{
		$reader = new Reader();
		$worksheetList = $reader->listWorksheetNames($fileName);
		$reader->setReadDataOnly(true);
		$reader->setReadEmptyCells(false);
		$reader->setLoadSheetsOnly([$worksheetName]);
		$chunkFilter = new ChunkReadFilter();

		// Tell the Reader that we want to use the Read Filter that we've Instantiated
		$reader->setReadFilter($chunkFilter);

		// Tell the Read Filter, the limits on which rows we want to read this iteration
		$chunkFilter->setRows($startRow, $endRow);

		// Load only the rows that match our filter from $inputFileName to a PhpSpreadsheet Object
		$spreadsheet = $reader->load($fileName);

		$sheet = $spreadsheet->getSheetByName($worksheetName);

		// $maxDataRow = $sheet->getHighestDataRow();
		// return $sheet->rangeToArray("A{$startRow}:{$lastColumnLetter}{$maxDataRow}");
		return $sheet->rangeToArray("A{$startRow}:{$lastColumnLetter}{$endRow}");
	}
}

/**
 * Define a Read Filter class implementing \PhpOffice\PhpSpreadsheet\Reader\IReadFilter
 */
class ChunkReadFilter implements \PhpOffice\PhpSpreadsheet\Reader\IReadFilter
{
	private $startRow = 0;
	private $endRow   = 0;

	/**  Set the list of rows that we want to read  */
	public function setRows($startRow, $endRow) {
		$this->startRow = $startRow;
		$this->endRow = $endRow;
	}

	public function readCell(string $column, int $row, string $worksheetName = ''):bool {
		# Only read the heading row, and the configured rows
		if ($row >= $this->startRow && $row <= $this->endRow) {
			return true;
		}
		return false;
	}
}