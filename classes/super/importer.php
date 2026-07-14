<?php

namespace Classes\Super;

use Classes\Completed\Manager as CManager;
use Classes\DateTimeUtil;
use Classes\EduActivity\Manager as EAManager;
use Classes\Employee\Manager as EManager;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class Importer
{
    const TYPES = [
        self::T_EMPLOYEE => 'Zaměstnanec',
        self::T_CERT => 'Certifikáty'
    ];

    const T_EMPLOYEE = 0;
    const T_CERT = 1;

    private Spreadsheet $spreadsheet;
    private Worksheet $worksheet;

    public function __construct(string $file)
    {
        $this->spreadsheet = IOFactory::load($file);
        $this->worksheet = $this->spreadsheet->getActiveSheet();
    }

    public function importEmployee(): array
    {
        $employee = $workPlace = [];
        $mid = '';
        foreach ($this->worksheet->getRowIterator() as $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);

            foreach ($cellIterator as $cell) {
                if ($cell->getRow() === 1) {continue;}
                if ($cell->getFormattedValue() === '') {break;}
                switch ($cell->getColumn())
                {
                    case 'A':
                        $parts = explode(',', trim($cell->getFormattedValue()));
                        $namePart = trim($parts[0]);
                        $nameParts = explode(' ', $namePart, 2);
                        $employee[$cell->getRow()][EManager::FIRSTNAME] = $nameParts[0];
                        $employee[$cell->getRow()][EManager::LASTNAME] = $nameParts[1] ?? '';
                        $employee[$cell->getRow()][EManager::TITLES] = isset($parts[1]) ? trim($parts[1]) : '';
                    break;
                    case 'B':
                        $employee[$cell->getRow()][EManager::PERSONAL_NUMBER] = trim($cell->getFormattedValue());
                    break;
                    case 'C':
                        $mid = trim($cell->getFormattedValue());
                        $employee[$cell->getRow()]['workPlace'] = $mid;
                    break;
                    case 'D':
                        $workPlace[$mid] = trim($cell->getFormattedValue());
                        $mid = '';
                    break;
                }
            }
        }

        return [
            'employee' => $employee,
            'workPlace' => $workPlace
        ];
    }

    public function importCert(): array
    {
        $eduActivity = $completed = $employees = [];
        foreach ($this->worksheet->getRowIterator() as $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);

            foreach ($cellIterator as $cell) {
                if ($cell->getRow() === 1) {continue;}
                if ($cell->getFormattedValue() === '') {break;}
                switch ($cell->getColumn())
                {
                    case 'A':
                        $completed[$cell->getRow()]['userNumber'] = trim($cell->getFormattedValue());
                        $eduActivity[$cell->getRow()]['userNumber'] = trim($cell->getFormattedValue());
                        $employees[trim($cell->getFormattedValue())] = trim($cell->getFormattedValue());
                    break;
                    case 'C':
                        $eduActivity[$cell->getRow()][EAManager::NAME] = trim($cell->getFormattedValue());
                    break;
                    case 'D':
                        $eduActivity[$cell->getRow()][EAManager::PROVIDER] = trim($cell->getFormattedValue());
                    break;
                    case 'E':
                        $completed[$cell->getRow()][CManager::TO] = new DateTimeUtil(trim($cell->getFormattedValue()));
                    break;
                    case 'F':
                        $completed[$cell->getRow()][CManager::FROM] = new DateTimeUtil(trim($cell->getFormattedValue()));
                    break;
                    case 'G':
                        $eduActivity[$cell->getRow()][EAManager::DES] = trim($cell->getFormattedValue());
                        $completed[$cell->getRow()][CManager::NOTE] = trim($cell->getFormattedValue());
                    break;
                    case 'H':
                        $eduActivity[$cell->getRow()][EAManager::SCOPE] = trim($cell->getFormattedValue());
                    break;
                }
            }
        }

        return [
            'eduActivity' => $eduActivity,
            'completed' => $completed,
            'employees' => $employees
        ];
    }

}