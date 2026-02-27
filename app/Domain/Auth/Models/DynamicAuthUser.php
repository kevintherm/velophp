<?php

namespace App\Domain\Auth\Models;

use App\Domain\Collections\Models\Collection;
use Illuminate\Foundation\Auth\User as Authenticatable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

/**
 * A dynamic User model mapped to a specific auth collection table.
 */
class DynamicAuthUser extends Authenticatable implements JWTSubject
{
    protected $guarded = [];

    /**
     * Set the table associated with the model dynamically.
     */
    public function setTable($table)
    {
        $this->table = $table;
        return $this;
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [
            'collection_table' => $this->getTable() // Inject the source table so we know where this user belongs
        ];
    }
}
