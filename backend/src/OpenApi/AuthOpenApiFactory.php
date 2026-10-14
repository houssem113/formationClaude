<?php

namespace App\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\MediaType;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\RequestBody;
use ApiPlatform\OpenApi\Model\Response;
use ApiPlatform\OpenApi\Model\SecurityScheme;
use ApiPlatform\OpenApi\OpenApi;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;

/**
 * Documente dans Swagger (/api) les endpoints d'authentification, qui ne sont pas des ressources
 * API Platform. Doit rester aligné sur docs/api-contract.md.
 */
#[AsDecorator(decorates: 'api_platform.openapi.factory')]
final class AuthOpenApiFactory implements OpenApiFactoryInterface
{
    private const TAG = 'Authentification';

    public function __construct(private readonly OpenApiFactoryInterface $decorated)
    {
    }

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->decorated)($context);
        $components = $openApi->getComponents();

        $schemas = $components->getSchemas() ?? new \ArrayObject();
        foreach (self::schemas() as $name => $schema) {
            $schemas[$name] = new \ArrayObject($schema);
        }

        $securitySchemes = $components->getSecuritySchemes() ?? new \ArrayObject();
        $securitySchemes['bearerAuth'] = new SecurityScheme(
            type: 'http',
            description: 'Access token JWT renvoyé par /api/auth/login ou /api/auth/refresh (valable 15 min).',
            scheme: 'bearer',
            bearerFormat: 'JWT',
        );
        $securitySchemes['refreshTokenCookie'] = new SecurityScheme(
            type: 'apiKey',
            description: 'Cookie httpOnly posé par /api/auth/login, envoyé automatiquement par le navigateur.',
            name: 'refresh_token',
            in: 'cookie',
        );

        $openApi = $openApi->withComponents($components->withSchemas($schemas)->withSecuritySchemes($securitySchemes));

        $paths = $openApi->getPaths();
        $paths->addPath('/api/auth/register', new PathItem(post: $this->register()));
        $paths->addPath('/api/auth/confirm-email', new PathItem(post: $this->confirmEmail()));
        $paths->addPath('/api/auth/resend-confirmation', new PathItem(post: $this->resendConfirmation()));
        $paths->addPath('/api/auth/login', new PathItem(post: $this->login()));
        $paths->addPath('/api/auth/refresh', new PathItem(post: $this->refresh()));
        $paths->addPath('/api/auth/logout', new PathItem(post: $this->logout()));
        $paths->addPath('/api/me', new PathItem(get: $this->me()));

        return $openApi;
    }

    private function register(): Operation
    {
        return new Operation(
            operationId: 'register',
            tags: [self::TAG],
            responses: [
                '201' => self::json('Compte créé (non confirmé) ; un email de confirmation est envoyé.', 'RegisterResponse', ['id' => 1, 'email' => 'alice@example.com', 'isVerified' => false]),
                '400' => self::error('Corps illisible.', 'INVALID_JSON'),
                '409' => self::error('Un compte existe déjà avec cet email.', 'EMAIL_ALREADY_USED'),
                '422' => self::validationError(),
                '429' => self::tooManyRequests(),
                '500' => self::error('Erreur inattendue (ex. email impossible à envoyer : aucun compte n\'est créé).', 'INTERNAL_ERROR'),
            ],
            summary: 'Inscription',
            description: 'Crée un compte non confirmé et envoie un lien {FRONTEND_URL}/confirm-email?token=… valable 24 h. Limité à 3 requêtes par minute et par IP.',
            requestBody: self::body('Credentials', ['email' => 'alice@example.com', 'password' => 'secret123']),
        );
    }

    private function confirmEmail(): Operation
    {
        return new Operation(
            operationId: 'confirmEmail',
            tags: [self::TAG],
            responses: [
                '200' => self::json('Email confirmé.', 'Message', ['message' => 'Email confirmé.']),
                '400' => self::error('Corps illisible, ou token inconnu / déjà utilisé.', 'INVALID_TOKEN'),
                '410' => self::error('Token expiré : proposer un renvoi.', 'TOKEN_EXPIRED'),
                '422' => self::validationError(),
            ],
            summary: 'Confirmation de l\'email',
            description: 'Appelé par la page frontend /confirm-email avec le token reçu par email.',
            requestBody: self::body('ConfirmEmailRequest', ['token' => 'q3J0c2VjcmV0LXRva2VuLWV4YW1wbGU']),
        );
    }

    private function resendConfirmation(): Operation
    {
        return new Operation(
            operationId: 'resendConfirmation',
            tags: [self::TAG],
            responses: [
                '202' => self::json('Réponse toujours identique, que le compte existe ou non (anti-énumération).', 'Message', ['message' => 'Si un compte non confirmé existe, un email a été envoyé.']),
                '400' => self::error('Corps illisible.', 'INVALID_JSON'),
                '422' => self::validationError(),
                '429' => self::tooManyRequests(),
            ],
            summary: 'Renvoi de l\'email de confirmation',
            description: 'Invalide le token précédent. Limité à 3 requêtes par minute et par IP.',
            requestBody: self::body('EmailRequest', ['email' => 'alice@example.com']),
        );
    }

    private function login(): Operation
    {
        return new Operation(
            operationId: 'login',
            tags: [self::TAG],
            responses: [
                '200' => self::json(
                    'Connecté. Pose aussi le cookie refresh_token (HttpOnly; Secure; SameSite=Strict; Path=/api/auth; Max-Age=2592000).',
                    'LoginResponse',
                    ['token' => 'eyJhbGciOiJSUzI1NiJ9…', 'expiresIn' => 900, 'user' => ['id' => 1, 'email' => 'alice@example.com', 'roles' => ['ROLE_USER']]],
                    self::setCookieHeader(),
                ),
                '400' => self::error('Corps illisible ou non JSON.', 'INVALID_JSON'),
                '401' => self::error('Email inconnu ou mot de passe faux (même réponse dans les deux cas).', 'INVALID_CREDENTIALS'),
                '403' => self::error('Identifiants corrects mais email non confirmé.', 'EMAIL_NOT_CONFIRMED'),
                '429' => self::tooManyRequests(),
            ],
            summary: 'Login JWT',
            description: 'Limité à 5 tentatives par minute (IP + email).',
            requestBody: self::body('Credentials', ['email' => 'alice@example.com', 'password' => 'secret123']),
        );
    }

    private function refresh(): Operation
    {
        return new Operation(
            operationId: 'refresh',
            tags: [self::TAG],
            responses: [
                '200' => self::json('Nouvel access token ; le refresh token est remplacé (rotation, usage unique).', 'RefreshResponse', ['token' => 'eyJhbGciOiJSUzI1NiJ9…', 'expiresIn' => 900], self::setCookieHeader()),
                '401' => self::error('Cookie absent, expiré, révoqué ou déjà utilisé ; le cookie est effacé.', 'INVALID_REFRESH_TOKEN'),
            ],
            summary: 'Rafraîchir l\'access token',
            description: 'Sans corps : le refresh token est lu dans le cookie refresh_token.',
            security: [['refreshTokenCookie' => []]],
        );
    }

    private function logout(): Operation
    {
        return new Operation(
            operationId: 'logout',
            tags: [self::TAG],
            responses: [
                '204' => new Response('Refresh token révoqué et cookie effacé (Max-Age=0). Idempotent : 204 même sans cookie.', null, self::setCookieHeader()),
            ],
            summary: 'Logout',
            description: 'Sans corps. L\'access token en cours reste valide jusqu\'à son expiration (15 min au plus) : le front l\'oublie.',
            security: [['refreshTokenCookie' => []]],
        );
    }

    private function me(): Operation
    {
        return new Operation(
            operationId: 'me',
            tags: [self::TAG],
            responses: [
                '200' => self::json('Utilisateur connecté.', 'Me', ['id' => 1, 'email' => 'alice@example.com', 'roles' => ['ROLE_USER'], 'isVerified' => true]),
                '401' => self::error('TOKEN_MISSING (pas d\'en-tête), TOKEN_INVALID ou TOKEN_EXPIRED (appeler /api/auth/refresh puis rejouer).', 'TOKEN_EXPIRED'),
            ],
            summary: 'Utilisateur connecté',
            security: [['bearerAuth' => []]],
        );
    }

    /** @return array<string, array<string, mixed>> */
    private static function schemas(): array
    {
        $string = ['type' => 'string'];
        $user = [
            'type' => 'object',
            'required' => ['id', 'email', 'roles'],
            'properties' => [
                'id' => ['type' => 'integer'],
                'email' => ['type' => 'string', 'format' => 'email'],
                'roles' => ['type' => 'array', 'items' => $string],
            ],
        ];

        return [
            'Credentials' => [
                'type' => 'object',
                'required' => ['email', 'password'],
                'properties' => [
                    'email' => ['type' => 'string', 'format' => 'email', 'maxLength' => 180],
                    'password' => ['type' => 'string', 'minLength' => 8, 'maxLength' => 4096, 'description' => 'Au moins une lettre et un chiffre.'],
                ],
            ],
            'ConfirmEmailRequest' => ['type' => 'object', 'required' => ['token'], 'properties' => ['token' => $string]],
            'EmailRequest' => ['type' => 'object', 'required' => ['email'], 'properties' => ['email' => ['type' => 'string', 'format' => 'email']]],
            'Message' => ['type' => 'object', 'required' => ['message'], 'properties' => ['message' => $string]],
            'RegisterResponse' => [
                'type' => 'object',
                'required' => ['id', 'email', 'isVerified'],
                'properties' => ['id' => ['type' => 'integer'], 'email' => ['type' => 'string', 'format' => 'email'], 'isVerified' => ['type' => 'boolean']],
            ],
            'LoginResponse' => [
                'type' => 'object',
                'required' => ['token', 'expiresIn', 'user'],
                'properties' => ['token' => $string, 'expiresIn' => ['type' => 'integer', 'example' => 900], 'user' => $user],
            ],
            'RefreshResponse' => [
                'type' => 'object',
                'required' => ['token', 'expiresIn'],
                'properties' => ['token' => $string, 'expiresIn' => ['type' => 'integer', 'example' => 900]],
            ],
            'Me' => array_merge_recursive($user, ['required' => ['isVerified'], 'properties' => ['isVerified' => ['type' => 'boolean']]]),
            'Error' => [
                'type' => 'object',
                'required' => ['error'],
                'properties' => [
                    'error' => [
                        'type' => 'object',
                        'required' => ['code', 'message'],
                        'properties' => [
                            'code' => ['type' => 'string', 'description' => 'Code stable, à utiliser côté front.'],
                            'message' => ['type' => 'string', 'description' => 'Texte affichable, susceptible de changer.'],
                            'violations' => [
                                'type' => 'array',
                                'description' => 'Présent uniquement pour VALIDATION_FAILED.',
                                'items' => ['type' => 'object', 'properties' => ['field' => $string, 'message' => $string]],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /** @param array<string, mixed> $example */
    private static function body(string $schema, array $example): RequestBody
    {
        return new RequestBody(content: new \ArrayObject([
            'application/json' => new MediaType(new \ArrayObject(['$ref' => '#/components/schemas/'.$schema]), $example),
        ]), required: true);
    }

    /** @param array<string, mixed> $example */
    private static function json(string $description, string $schema, array $example, ?\ArrayObject $headers = null): Response
    {
        return new Response($description, new \ArrayObject([
            'application/json' => new MediaType(new \ArrayObject(['$ref' => '#/components/schemas/'.$schema]), $example),
        ]), $headers);
    }

    /** @param list<array{field: string, message: string}>|null $violations */
    private static function error(string $description, string $code, ?array $violations = null, ?\ArrayObject $headers = null): Response
    {
        $error = ['code' => $code, 'message' => 'Texte lisible par un humain'];
        if (null !== $violations) {
            $error['violations'] = $violations;
        }

        return self::json($description, 'Error', ['error' => $error], $headers);
    }

    private static function validationError(): Response
    {
        return self::error('Champs invalides (détail dans violations).', 'VALIDATION_FAILED', [
            ['field' => 'email', 'message' => 'Cette adresse email n\'est pas valide.'],
        ]);
    }

    private static function tooManyRequests(): Response
    {
        return self::error('Limite de requêtes atteinte.', 'TOO_MANY_REQUESTS', null, new \ArrayObject([
            'Retry-After' => ['description' => 'Secondes à attendre avant de réessayer.', 'schema' => ['type' => 'integer']],
        ]));
    }

    private static function setCookieHeader(): \ArrayObject
    {
        return new \ArrayObject([
            'Set-Cookie' => ['description' => 'refresh_token=…; HttpOnly; Secure; SameSite=Strict; Path=/api/auth', 'schema' => ['type' => 'string']],
        ]);
    }
}
