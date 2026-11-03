<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(title: 'CRM', version: '1.0')]
#[OA\SecurityScheme(securityScheme: 'bearer_token', type: 'http', scheme: 'bearer')]
final class ApiDefinition {}
