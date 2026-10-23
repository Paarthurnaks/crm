<?php

namespace App\Services;

use App\Http\Filters\SalesTunnelFilter;
use App\Models\SalesStage;
use App\Models\SalesTunnel;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class SalesTunnelService
{
    /**
     * @param  SalesTunnelFilter  $filter
     * @param  array  $queryParams
     */
    public function getSalesTunnels(): Collection
    {
        return SalesTunnel::all();
    }

    /**
     * @param array
     */
    public function createSalesTunnel(array $salesTunnelData): SalesTunnel
    {

        $fromStage = SalesStage::find($salesTunnelData['from_stage_id']);
        $toStage = SalesStage::find($salesTunnelData['to_stage_id']);

        if (SalesTunnel::where('to_stage_id', $fromStage->id)->where('from_stage_id', $toStage->id)->exists()) {
            throw new AccessDeniedHttpException('Запрещено делать цикличные туннели');
        }

        if ($fromStage->salesFunnel->id == $toStage->salesFunnel->id) {
            throw new AccessDeniedHttpException('Тунели доступно делать только в рамках разных направлений');
        }

        $salesTunnel = SalesTunnel::create([
            'from_stage_id' => $salesTunnelData['from_stage_id'],
            'to_stage_id' => $salesTunnelData['to_stage_id'],
        ]);

        return $salesTunnel;
    }

    public function deleteSalesTunnel(SalesTunnel $salesTunnel): void
    {
        $salesTunnel->delete();
    }
}
