<?php
namespace Espo\Custom\Services\Logistics;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as Writer;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

final class Xlsx
{
    public function render(array $rows, bool $tracking): string
    {
        $columns=$tracking
            ? ['name'=>'Mã tracking','packageCount'=>'Số kiện','weight'=>'kg','volume'=>'m³','status'=>'Trạng thái',
                'receivedAt'=>'Ngày nhập mã (VN)','deliveredAt'=>'Ngày giao gần nhất (VN)','returnedAt'=>'Ngày hoàn gần nhất (VN)']
            : ['code'=>'Mã khách','name'=>'Tên khách','receivedTracking'=>'Tracking nhập trong kỳ',
                'receivedWeight'=>'kg nhập trong kỳ','receivedVolume'=>'m³ nhập trong kỳ',
                'stockTracking'=>'Tracking tồn hiện tại','stockWeight'=>'kg tồn hiện tại','stockVolume'=>'m³ tồn hiện tại'];
        $book=new Spreadsheet(); $sheet=$book->getActiveSheet(); $sheet->setTitle('Ton kho');
        $sheet->fromArray(array_values($columns),null,'A1');
        $numberFields=['packageCount','weight','volume','receivedTracking','receivedWeight','receivedVolume','stockTracking','stockWeight','stockVolume'];
        foreach ($rows as $r=>$row) {
            $c=1;
            foreach ($columns as $key=>$label) {
                $value=$row[$key]??'';
                if ($key==='status') { $value=['undelivered'=>'Chưa giao','delivered'=>'Đã giao','returned'=>'Đã trả lại'][$value]??$value; }
                $numeric=in_array($key,$numberFields,true) && is_numeric($value);
                // Explicit strings prevent tracking/customer names beginning '='
                // from becoming spreadsheet formulas.
                $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($c++).($r+2),
                    $numeric?(float)$value:(string)$value,$numeric?DataType::TYPE_NUMERIC:DataType::TYPE_STRING);
            }
        }
        $last=Coordinate::stringFromColumnIndex(count($columns));
        $sheet->getStyle('A1:'.$last.'1')->getFont()->setBold(true);
        $sheet->freezePane('A2'); $sheet->setAutoFilter('A1:'.$last.(count($rows)+1));
        for($c=1;$c<=count($columns);$c++) { $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setAutoSize(true); }
        ob_start();
        try { (new Writer($book))->save('php://output'); return ob_get_contents(); }
        finally { ob_end_clean(); $book->disconnectWorksheets(); }
    }
}
