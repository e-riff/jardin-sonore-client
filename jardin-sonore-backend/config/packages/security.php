<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use App\Infrastructure\Doctrine\Entity\AdminUserEntity;
use App\Infrastructure\Doctrine\Entity\UserEntity;
use App\Infrastructure\Security\AdminUserChecker;
use App\Infrastructure\Security\PortalAccessTokenAuthenticator;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

return App::config([
    'security' => [
        'password_hashers' => [
            PasswordAuthenticatedUserInterface::class => 'auto',
        ],
        'providers' => [
            'admin_user_provider' => [
                'entity' => [
                    'class' => AdminUserEntity::class,
                    'property' => 'email',
                ],
            ],
            'portal_user_provider' => [
                'entity' => [
                    'class' => UserEntity::class,
                    'property' => 'email',
                ],
            ],
        ],
        'firewalls' => [
            'dev' => [
                'pattern' => '^/(_profiler|_wdt|assets|build)/',
                'security' => false,
            ],
            'portal_api' => [
                'pattern' => '^/api/portal',
                'stateless' => true,
                'provider' => 'portal_user_provider',
                'custom_authenticators' => [PortalAccessTokenAuthenticator::class],
                'entry_point' => PortalAccessTokenAuthenticator::class,
            ],
            'main' => [
                'lazy' => true,
                'provider' => 'admin_user_provider',
                'user_checker' => AdminUserChecker::class,
                'form_login' => [
                    'login_path' => 'app_login',
                    'check_path' => 'app_login',
                    'enable_csrf' => true,
                    'default_target_path' => '/',
                ],
                'remember_me' => [
                    'secret' => '%kernel.secret%',
                    'lifetime' => 2592000,
                    'path' => '/',
                    'secure' => 'auto',
                ],
                'logout' => [
                    'path' => 'app_logout',
                    'target' => 'app_login',
                    'enable_csrf' => true,
                ],
            ],
        ],
        'access_control' => [
            [
                'path' => '^/api/portal/(auth/login|auth/impersonation-launch|auth/password-reset-requests|password-tokens/)',
                'roles' => 'PUBLIC_ACCESS',
            ],
            [
                'path' => '^/api/portal',
                'roles' => 'ROLE_PORTAL_USER',
            ],
            [
                'path' => '^/portail/definir-mot-de-passe/',
                'roles' => 'PUBLIC_ACCESS',
            ],
            [
                'path' => '^/login$',
                'roles' => 'PUBLIC_ACCESS',
            ],
            [
                'path' => '^/',
                'roles' => 'ROLE_ADMIN',
            ],
        ],
    ],
    'when@test' => [
        'security' => [
            'password_hashers' => [
                PasswordAuthenticatedUserInterface::class => [
                    'algorithm' => 'auto',
                    'cost' => 4,
                    'time_cost' => 3,
                    'memory_cost' => 10,
                ],
            ],
        ],
    ],
]);
