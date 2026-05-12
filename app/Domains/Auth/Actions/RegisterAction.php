<?php

namespace App\Domains\Auth\Actions;

use App\Domains\Auth\DTOs\RegisterDTO;
use App\Models\User;

final class RegisterAction
{
    public function execute(RegisterDTO $dto): User
    {
        return User::create([
            'name' => $dto->name,
            'email' => $dto->email,
            'password' => $dto->password,
            'timezone' => $dto->timezone,
            'locale' => $dto->locale,
        ]);
    }
}
