<?php

namespace App\Infrastructure\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Infrastructure\Traits\ApiResponse;

abstract class ApiController extends Controller
{
    use ApiResponse;
}
