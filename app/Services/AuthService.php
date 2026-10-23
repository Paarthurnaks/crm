<?php

namespace App\Services;

use App\Mail\SendLinkChangePasswordMail;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AuthService
{
    /**
     * @throws NotFoundHttpException
     */
    public function sendLinkChangePassword(string $email): void
    {
        $key = "reset_password_code_" . $email;
        $code = Str::random(64);
        $lifetime = config("constants.lifetime.code_change_password");

        Redis::setex($key, $lifetime, $code);

        $user = User::where('email', $email)->first();
        Mail::to($email)->send(new SendLinkChangePasswordMail($code, $user));
    }

    /**
     * @throws NotFoundHttpException
     * @throws TokenExpiredException
     * @throws BadRequestHttpException
     */
    public function changePassword(array $changePasswordData): void
    {
        $user = User::where("email", $changePasswordData["email"])->first();
        $code = Redis::get("reset_password_code_".$changePasswordData["email"]);

        if (!$user) {
            throw new NotFoundHttpException();
        } else if ($code == null) {
            throw new TokenExpiredException();
        } else if ($code != $changePasswordData["code"]) {
            throw new BadRequestHttpException();
        }

        $user->update(["password" => Hash::make($changePasswordData["password"])]);


    }
}
