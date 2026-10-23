<?php

namespace App\Services;

use App\Enums\DefaultRolesEnum;
use App\Models\Deal;
use App\Models\PartnerClientDeal;
use App\Models\User;
use Mockery\Exception;

class PartnerDealService
{
    private const ERROR_MESSAGES = [
        'dealHasPartnerClient' => 'У сделки уже есть привязанный к ней клиент.',
        'dealDoesntHaveClient' => 'У сделки нет такого клиента, привязанного к ней.',
        'noAccess' => 'Нет доступа.',
    ];
    public function attach(Deal $deal, array $data): void
    {
        $user = auth()->user();
        $clientId = $data['client_id'];

        if($user->hasRole(DefaultRolesEnum::PARTNER->value) && $deal->creator_id === $user->id) {
            $client = User::find($clientId);

            if(!$deal->partnerClient) {
                PartnerClientDeal::create([
                    'client_id' => $client->id,
                    'deal_id' => $deal->id,
                ]);
            } else {
                if(($deal->partnerClient && $deal->partnerClient->id !== $client->id)) {
                    throw new Exception(self::ERROR_MESSAGES['dealHasPartnerClient']);
                }
            }
        } else {
            throw new Exception(self::ERROR_MESSAGES['noAccess']);
        }
    }

    public function detach(Deal $deal, array $data): void
    {
        $user = auth()->user();
        $clientId = $data['client_id'];

        if($user->hasRole(DefaultRolesEnum::PARTNER->value) && $deal->creator_id === $user->id) {
            if($deal->partnerClient && $deal->partnerClient->id !== $clientId) {
                throw new Exception(self::ERROR_MESSAGES['dealDoesntHaveClient']);
            }

            PartnerClientDeal::where('client_id', $clientId)->where('deal_id', $deal->id)->delete();
        } else {
            throw new Exception(self::ERROR_MESSAGES['noAccess']);
        }
    }
}
