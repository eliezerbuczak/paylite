<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

class IndexController extends AbstractController
{
    /**
     * @return array<string, string>
     */
    public function index(): array
    {
        $user = (string) $this->request->input('user', 'Hyperf');
        $method = $this->request->getMethod();

        return [
            'method' => $method,
            'message' => "Hello {$user}.",
        ];
    }
}
