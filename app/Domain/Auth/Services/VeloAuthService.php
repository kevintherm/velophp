<?php

namespace App\Domain\Auth\Services;

use App\Domain\Collections\Models\Collection;
use App\Domain\Auth\Models\DynamicAuthUser;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class VeloAuthService
{
    /**
     * Authenticate a user against a specific auth collection.
     */
    public function authenticate(Collection $collection, array $credentials): array
    {
        // 1. Fetch dynamic user from this collection's specific table
        // Requires the collection's API rules to configure what the "username/email" field is logically called.
        // For simplicity, assuming "email" and "password" physical columns for now.
        
        $userModel = new DynamicAuthUser();
        $userModel->setTable($collection->name);

        $user = $userModel->newQuery()->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials do not match our records.'],
            ]);
        }

        // 2. Generate hybrid tokens
        // By using `auth('api')` which is bound to jwt provider, we can log in.
        // However, we want to generate a token for our specific DynamicAuthUser model instance.
        
        $token = auth('api')->login($user);
        
        // Return structured Hybrid response
        return [
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
            // 'refresh_token' => ... (Set HttpOnly cookie in the controller layer)
        ];
    }
    
    /**
     * Create a user in an auth collection
     */
    public function register(Collection $collection, array $data): DynamicAuthUser
    {
         $userModel = new DynamicAuthUser();
         $userModel->setTable($collection->name);
         
         // Hash the password if provided
         if (isset($data['password'])) {
             $data['password'] = Hash::make($data['password']);
         }
         
         return $userModel->newQuery()->create($data);
    }
}
