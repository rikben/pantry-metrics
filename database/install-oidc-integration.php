<?php
// /database/install-oidc-integration.php

declare(strict_types=1);

$root = dirname(__DIR__);

function backupAndWrite(
    string $path,
    string $contents
): void {
    $backup = $path
        . '.bak-'
        . date('Ymd-His');

    if (!copy($path, $backup)) {
        throw new RuntimeException(
            "Unable to create backup for {$path}"
        );
    }

    if (file_put_contents(
        $path,
        $contents
    ) === false) {
        throw new RuntimeException(
            "Unable to write {$path}"
        );
    }

    echo "Patched: {$path}\n";
    echo "Backup: {$backup}\n";
}

$bootstrapPath =
    $root
    . '/public_html/app/bootstrap.php';
$bootstrap = file_get_contents(
    $bootstrapPath
);

if ($bootstrap === false) {
    throw new RuntimeException(
        'Unable to read bootstrap.php'
    );
}

$changed = false;

if (!str_contains(
    $bootstrap,
    'use App\Auth\AuthentikAuthService;'
)) {
    $bootstrap = str_replace(
        'use App\Auth\AuthServiceInterface;',
        "use App\\Auth\\AuthServiceInterface;\n"
        . "use App\\Auth\\AuthentikAuthService;",
        $bootstrap
    );
    $changed = true;
}

if (str_contains(
    $bootstrap,
    'use App\Auth\DevelopmentAuthService;'
)) {
    $bootstrap = str_replace(
        "use App\\Auth\\DevelopmentAuthService;\n",
        '',
        $bootstrap
    );
    $changed = true;
}

$oldBinding =
    '$container->set(AuthServiceInterface::class, '
    . 'static fn (): AuthServiceInterface => '
    . 'new DevelopmentAuthService());';

$newBinding =
    '$container->set('
    . "AuthServiceInterface::class,\n"
    . '    static fn (): AuthServiceInterface => '
    . "new AuthentikAuthService()\n"
    . ');';

if (str_contains(
    $bootstrap,
    $oldBinding
)) {
    $bootstrap = str_replace(
        $oldBinding,
        $newBinding,
        $bootstrap
    );
    $changed = true;
}

if ($changed) {
    backupAndWrite(
        $bootstrapPath,
        $bootstrap
    );
} else {
    echo "bootstrap.php already looks OIDC-enabled.\n";
}

$indexPath =
    $root
    . '/public_html/index.php';
$index = file_get_contents(
    $indexPath
);

if ($index === false) {
    throw new RuntimeException(
        'Unable to read index.php'
    );
}

$changed = false;

if (!str_contains(
    $index,
    'use App\Controllers\AuthController;'
)) {
    $index = str_replace(
        'use App\Controllers\HomeController;',
        "use App\\Controllers\\AuthController;\n"
        . 'use App\Controllers\HomeController;',
        $index
    );
    $changed = true;
}

if (!str_contains(
    $index,
    "\$router->getPublic('/auth/login'"
)) {
    $needle = '$router = new Router();';

    $routes = <<<'PHP'

$router->getPublic(
    '/auth/login',
    [AuthController::class, 'login']
);
$router->getPublic(
    '/auth/callback',
    [AuthController::class, 'callback']
);
$router->getPublic(
    '/auth/logged-out',
    [AuthController::class, 'loggedOut']
);
$router->post(
    '/auth/logout',
    [AuthController::class, 'logout']
);
PHP;

    $index = str_replace(
        $needle,
        $needle . $routes,
        $index
    );
    $changed = true;
}

if ($changed) {
    backupAndWrite(
        $indexPath,
        $index
    );
} else {
    echo "index.php already contains auth routes.\n";
}

$layoutCandidates = [
    $root
        . '/public_html/views/layouts/app.php',
    $root
        . '/public_html/views/layout.php',
];

$layoutPath = null;

foreach ($layoutCandidates as $candidate) {
    if (is_file($candidate)) {
        $layoutPath = $candidate;
        break;
    }
}

if ($layoutPath === null) {
    throw new RuntimeException(
        'Application layout not found.'
    );
}

$layout = file_get_contents(
    $layoutPath
);

if ($layout === false) {
    throw new RuntimeException(
        'Unable to read application layout.'
    );
}

$changed = false;

$authCss =
    '<link rel="stylesheet" '
    . 'href="/assets/css/auth.css">';

if (!str_contains(
    $layout,
    $authCss
)) {
    $layout = str_replace(
        '</head>',
        "    {$authCss}\n</head>",
        $layout
    );
    $changed = true;
}

if (!str_contains(
    $layout,
    'class="auth-user"'
)) {
    $navigationInsert = <<<'PHP'
            <?php
            $layoutAuth = \App\Core\Container::instance()
                ->get(\App\Auth\AuthServiceInterface::class);
            ?>
            <?php if ($layoutAuth->check()): ?>
                <?php $layoutUser = $layoutAuth->user(); ?>
                <span class="auth-user">
                    <strong>
                        <?= e(
                            $layoutUser['display_name']
                            ?: $layoutUser['email']
                        ) ?>
                    </strong>

                    <form
                        class="auth-logout-form"
                        method="post"
                        action="/auth/logout"
                    >
                        <?= csrf_field() ?>
                        <button
                            class="auth-logout-button"
                            type="submit"
                        >
                            Sign out
                        </button>
                    </form>
                </span>
            <?php endif; ?>
PHP;

    $navEnd = strpos(
        $layout,
        '</nav>'
    );

    if ($navEnd === false) {
        throw new RuntimeException(
            'Could not find </nav> in layout.'
        );
    }

    $layout =
        substr($layout, 0, $navEnd)
        . $navigationInsert
        . "\n        "
        . substr($layout, $navEnd);

    $changed = true;
}

if ($changed) {
    backupAndWrite(
        $layoutPath,
        $layout
    );
} else {
    echo "Layout already contains OIDC UI.\n";
}

$envExamplePath =
    $root
    . '/.env.example';

if (is_file($envExamplePath)) {
    $envExample = file_get_contents(
        $envExamplePath
    );

    if (
        is_string($envExample)
        && !str_contains(
            $envExample,
            'OIDC_ENABLED='
        )
    ) {
        $envExample .= <<<'ENV'

# Authentik / OpenID Connect
OIDC_ENABLED=false
OIDC_ISSUER=https://sso.example.com/application/o/pantry-metrics/
OIDC_DISCOVERY_URL=https://sso.example.com/application/o/pantry-metrics/.well-known/openid-configuration
OIDC_CLIENT_ID=pantry-metrics
OIDC_CLIENT_SECRET=change-me
OIDC_REDIRECT_URI=http://localhost:8080/auth/callback
OIDC_POST_LOGOUT_REDIRECT_URI=http://localhost:8080/auth/logged-out
OIDC_SCOPES=openid profile email
OIDC_SESSION_TTL=28800

# Set to the existing local user ID for the first SSO login.
# Remove/zero this after the identity has been linked.
OIDC_BOOTSTRAP_USER_ID=1

# Prefer false. Enable only if you intentionally want to link an
# unlinked local user based solely on matching email.
OIDC_ALLOW_EMAIL_LINK=false
ENV;

        backupAndWrite(
            $envExamplePath,
            $envExample
        );
    }
}

echo "\nOIDC integration patch complete.\n";
