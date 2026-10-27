<?php

namespace App\Services;

use App\Models\PartnerClient;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class PartnerProfileService
{
    public function replaceLogo(User $partner, UploadedFile $file): Media
    {
        $oldMedia = $partner->getMedia('logo');
        try {
            $logo = $partner->addMedia($file)->toMediaCollection('logo');
            foreach ($oldMedia as $media) {
                if ($media->id !== $logo->id) {
                    $media->delete();
                }
            }
        } catch (\Throwable $exception) {
            Log::error('partner.logo.change_failed', ['partner_id' => $partner->id, 'exception' => $exception::class]);
            throw $exception;
        }
        Log::info('partner.logo.changed', ['partner_id' => $partner->id, 'media_id' => $logo->id]);

        return $logo;
    }

    public function setColor(User $partner, string $color): void
    {
        $clientIds = PartnerClient::where('partner_id', $partner->id)->pluck('client_id')->all();
        $userIds = array_unique([...$clientIds, $partner->id]);
        User::whereIn('id', $userIds)->update(['color' => $color]);
        Log::info('partner.color.changed', ['partner_id' => $partner->id, 'user_count' => count($userIds)]);
    }
}
