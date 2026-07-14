<?php

namespace Classes\Super;

use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PhpOffice\PhpSpreadsheet\Spreadsheet as Spreadsheet;

class excelGenerator
{
    private $xlsxWriter, $xlsxReader, $spreadSheet;

    public function __construct()
    {
        $this->setSpreadSheet();
    }


    private function setSpreadSheet() : void
    {
        $this->spreadSheet = new Spreadsheet();
    }

    public function getSpreadSheet() : Spreadsheet
    {
        return $this->spreadSheet;
    }

    public function save(string $name) : void
    {
        $this->setXLSXWriter($this->getSpreadSheet());

        $writer = $this->getXLSXWriter();
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header(sprintf('Content-Disposition: attachment; filename="%s.xlsx"', $name));
        $writer->save('php://output');
    }

    private function setXLSXWriter(Spreadsheet $spreadsheet) : void
    {
        $this->xlsxWriter = new XlsxWriter($spreadsheet);
    }

    private function getXLSXWriter() : XlsxWriter
    {
        return $this->xlsxWriter;
    }

    public function getSheet() : Worksheet
    {
        return $this->spreadSheet->getActiveSheet();
    }

    public function setDefaultHeader(Spreadsheet &$spreadsheet, Worksheet &$sheet, string $text, string $ending = 'G') : void
    {
        $spreadsheet->getDefaultStyle()->getFont()->setName('Arial');
        $spreadsheet->getDefaultStyle()->getFont()->setSize(11);

        foreach (range('B', $ending) as $col)
        {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $decreased = --$ending;
        $sheet->getStyle('B1:'.$decreased.'3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells('B1:'.$decreased.'1');
        $sheet->getStyle('B1:'.$decreased.'1')->getFont()->setBold(true);
        $sheet->setCellValue('B1', $text);
    }

    public function setDefaultTime( &$sheet, $ending = 'G')
    {
        $now = new \Classes\DateTimeUtil();
        $sheet->mergeCells('B2:'.$ending.'2');
        $sheet->setCellValue('B2', $now->getDateTimeFormat());
    }

    public function getBorderStyleHeader() : array
    {
        return [
            'borders' => [
                'outline' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK,
                    'color' => ['argb' => '00000000'],
                ],
                'inside' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['argb' => 'FFFFFFFF'],
                ],
            ],
        ];
    }

    public function getBorderStyleLabels() : array
    {
        return [
            'borders' => [
                'outline' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK,
                    'color' => ['argb' => '00000000'],
                ],
                'inside' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK,
                    'color' => ['argb' => '00000000'],
                ],
            ],
            'font' => [
                'bold' => true,
            ],
        ];
    }

    public function getBorderStyleBody() : array
    {
        return [
            'borders' => [
                'inside' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['argb' => '00000000'],
                ],
                'outline' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['argb' => '00000000'],
                ],
            ],
        ];
    }
}