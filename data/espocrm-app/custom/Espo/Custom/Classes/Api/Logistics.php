<?php
namespace Espo\Custom\Classes\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Custom\Services\Logistics\Finance;
use Espo\Custom\Services\Logistics\Inventory;
use Espo\Custom\Services\Logistics\Xlsx;

final class Logistics implements Action
{
    public function __construct(private Finance $finance, private Inventory $inventory, private Xlsx $xlsx) {}

    public function process(Request $request): Response
    {
        $op=(string)$request->getRouteParam('operation');
        if (strtoupper($request->getMethod())==='POST') {
            $data=json_decode(json_encode($request->getParsedBody(),JSON_THROW_ON_ERROR),true,512,JSON_THROW_ON_ERROR);
            return ResponseComposer::json($this->finance->execute($op,$data));
        }
        $params=$request->getQueryParams();
        if ($op==='inventory') { return ResponseComposer::json($this->inventory->accounts($params)); }
        if ($op==='tracking') { return ResponseComposer::json($this->inventory->tracking($params)); }
        if ($op==='accounts') { return ResponseComposer::json($this->inventory->accounts($params,'CFinance')); }
        if ($op==='balance') { return ResponseComposer::json($this->finance->balance((string)($params['accountId']??''))); }
        if ($op==='order-options') { return ResponseComposer::json($this->finance->orderOptions((string)($params['accountId']??''))); }
        if ($op==='return-options') { return ResponseComposer::json($this->finance->returnOptions((string)($params['accountId']??''),(string)($params['shipmentId']??''))); }
        if ($op==='xlsx') {
            $tracking=!empty($params['accountId']); $params['offset']=0;
            $result=$tracking?$this->inventory->tracking($params,true):$this->inventory->accounts($params,'CInventory',true);
            return ResponseComposer::json(['filename'=>'ton-kho.xlsx',
                'content'=>base64_encode($this->xlsx->render($result['list'],$tracking))])->setHeader('Cache-Control','no-store');
        }
        throw new BadRequest('Thao tác không hợp lệ.');
    }
}
