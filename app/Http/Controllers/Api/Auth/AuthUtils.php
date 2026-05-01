<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Exceptions\InvalidCredentialsException;
use App\Exceptions\NotCreated;
use App\Exceptions\NotFound;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Http\Requests\Api\Auth\RegisterCoachRequest;
use App\Http\Requests\Api\Auth\RegisterPlayerRequest;
use App\Models\Concerns\LevelTypes;
use App\Models\Concerns\UserTypes;
use App\Models\Profile;
use App\Models\User;
use App\Services\CreateServiceData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

final class AuthUtils
{
    /**
     * @param  LoginRequest  $request
     * @return bool
     *
     * @throws InvalidCredentialsException
     */
    public static function authCredentials(LoginRequest $request): bool
    {
        if ( ! Auth::attempt($request->only('email', 'password'))) {
            throw new InvalidCredentialsException('Not Credentials Found');
        }

        return true;
    }

    /**
     * @param  Model  $model
     * @param  string  $tokenName
     * @return array
     *
     * @throws NotFound
     */
    public static function createTokenFromUser(Model $model, string $tokenName = 'auth_token'): array
    {
        if (0 === $model->count()) {
            throw new NotFound('USER NOT FOUND');
        }
        $response = $model->createToken($tokenName, [$model->type]);

        return [
            'token' => $response->plainTextToken,
            'model' => $response->accessToken->toArray(),
        ];
    }

    /**
     * @param  RegisterPlayerRequest|RegisterCoachRequest  $request
     * @param  bool  $coach
     * @return Model
     *
     * @throws NotCreated
     */
    public static function saveUser(RegisterPlayerRequest|RegisterCoachRequest $request, bool $coach = false): Model
    {
        $user = new CreateServiceData(new User());
        $request_user_data = $request->only(['email', 'phone']);
        $request_user_data['password'] = bcrypt($request->get('password'));
        $request_user_data['type'] = $coach ? UserTypes::COACH->value : UserTypes::PLAYER->value;

        return $user->handle($request_user_data);
    }

    /**
     * @param  RegisterPlayerRequest|RegisterCoachRequest  $request
     * @param  Model  $response_user
     * @return Model
     *
     * @throws NotCreated
     */
    public static function saveProfile(
        RegisterPlayerRequest|RegisterCoachRequest $request,
        Model $response_user,
        string $url
    ): Model {
        $request_profile_data = [
            'first_name' => $request->get('profile')['name']['first'],
            'last_name' => $request->get('profile')['name']['last'],
            'picture' => $url,
            'level' => $request->get('profile')['level'] ?? LevelTypes::PLAYER->value,
            'city' => $request->get('city'),
            'state' => $request->get('state'),
            'zip' => $request->get('zip'),
            'user_id' => $response_user->id,
        ];

        return (new CreateServiceData(new Profile()))->handle($request_profile_data);
    }
}
