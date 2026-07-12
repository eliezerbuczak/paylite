<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Entity\User;
use App\DTO\RegisterUserInput;
use App\Exception\MalformedRequestException;
use App\Service\RegisterUserService;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Psr\Http\Message\ResponseInterface as PsrResponse;

final class UserController
{
    public function __construct(
        private readonly RegisterUserService $registerUser,
        private readonly ResponseInterface $response,
    ) {
    }

    public function store(RequestInterface $request): PsrResponse
    {
        $user = $this->registerUser->execute(new RegisterUserInput(
            fullName: $this->requireString($request, 'full_name'),
            document: $this->requireString($request, 'document'),
            email: $this->requireString($request, 'email'),
            password: $this->requireString($request, 'password'),
            type: $this->requireString($request, 'type'),
        ));

        return $this->response
            ->json($this->present($user))
            ->withStatus(201)
            ->withAddedHeader('Location', "/users/{$user->id}");
    }

    private function requireString(RequestInterface $request, string $field): string
    {
        $value = $request->input($field);

        if (!is_string($value) || $value === '') {
            throw MalformedRequestException::missingField($field);
        }

        return $value;
    }

    /**
     * @return array<string, int|string>
     */
    private function present(User $user): array
    {
        return [
            'id' => $user->id,
            'full_name' => $user->fullName,
            'document' => $user->document->value,
            'email' => $user->email->value,
            'type' => $user->type->value,
            'created_at' => $user->createdAt->format(DATE_ATOM),
        ];
    }
}
