<?php

declare(strict_types=1);

$app = require dirname(__DIR__).'/bootstrap.php';
/** @var array $app */
extract($app);

use LicenseApi\DomainHelper;
use LicenseApi\Env;
use LicenseApi\Markdown;
use LicenseApi\ProductService;
use LicenseApi\SafeUrl;
use LicenseApi\Security;

/**
 * @return never
 */
function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * @return never
 */
function redirect(string $to): void
{
    header('Location: '.$to);
    exit;
}

function render(string $view, array $data = [], string $nav = 'guest', string $layout = 'layout_white'): void
{
    extract($data, EXTR_SKIP);
    ob_start();
    require dirname(__DIR__).'/views/'.$view.'.php';
    $content = ob_get_clean();
    $appName = $data['appName'] ?? 'License Portal';
    $title = $data['title'] ?? '';
    $active = $data['active'] ?? '';
    $wide = ! empty($data['wide']);
    require dirname(__DIR__).'/views/'.$layout.'.php';
}

function request_json(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);

    return is_array($data) ? $data : [];
}

function require_csrf(): void
{
    if (! Security::verifyCsrf($_POST['_csrf'] ?? null)) {
        http_response_code(419);
        echo 'CSRF inválido';
        exit;
    }
}

/** Bytes do post_max_size do PHP (0 = desconhecido/ilimitado). */
function php_post_max_bytes(): int
{
    $raw = trim((string) ini_get('post_max_size'));
    if ($raw === '' || $raw === '0') {
        return 0;
    }
    $unit = strtolower(substr($raw, -1));
    $num = (float) $raw;
    return (int) match ($unit) {
        'g' => $num * 1024 * 1024 * 1024,
        'm' => $num * 1024 * 1024,
        'k' => $num * 1024,
        default => $num,
    };
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

// --- Public API ---
if ($path === '/api/health' && $method === 'GET') {
    json_response(['ok' => true]);
}

if ($path === '/api/v1/activate' && $method === 'POST') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
    if (! Security::rateLimit('activate:'.$ip, 60, 60, $basePath)) {
        json_response(['success' => false, 'message' => 'Too many requests'], 429);
    }
    $input = request_json();
    $key = trim((string) ($input['license_key'] ?? ''));
    $domain = DomainHelper::normalize((string) ($input['domain'] ?? ''));
    $installId = trim((string) ($input['install_id'] ?? ''));
    $appVersion = isset($input['app_version']) ? trim((string) $input['app_version']) : null;
    if ($key === '' || $domain === '' || $installId === '') {
        json_response(['success' => false, 'message' => 'license_key, domain and install_id are required'], 422);
    }
    $payload = $licenses->activate($key, $domain, $installId, $appVersion, $_SERVER['REMOTE_ADDR'] ?? null);
    json_response(['success' => (bool) $payload['valid'], 'license' => $payload], $payload['valid'] ? 200 : 403);
}

if ($path === '/api/v1/heartbeat' && $method === 'POST') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
    if (! Security::rateLimit('heartbeat:'.$ip, 120, 60, $basePath)) {
        json_response(['success' => false, 'message' => 'Too many requests'], 429);
    }
    $input = request_json();
    $key = trim((string) ($input['license_key'] ?? ''));
    $domain = DomainHelper::normalize((string) ($input['domain'] ?? ''));
    $installId = trim((string) ($input['install_id'] ?? ''));
    $appVersion = isset($input['app_version']) ? trim((string) $input['app_version']) : null;
    if ($key === '' || $domain === '' || $installId === '') {
        json_response(['success' => false, 'message' => 'license_key, domain and install_id are required'], 422);
    }
    $payload = $licenses->heartbeat($key, $domain, $installId, $appVersion, $_SERVER['REMOTE_ADDR'] ?? null);
    json_response(['success' => (bool) $payload['valid'], 'license' => $payload], $payload['valid'] ? 200 : 403);
}

if ($path === '/api/v1/install/authorize' && $method === 'POST') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
    if (! Security::rateLimit('install-auth:'.$ip, 20, 60, $basePath)) {
        json_response(['ok' => false, 'message' => 'Too many requests'], 429);
    }
    $input = request_json();
    $key = trim((string) ($input['license_key'] ?? ''));
    $result = $releases->authorizeInstall($key, is_string($ip) ? $ip : null);
    json_response($result, ! empty($result['ok']) ? 200 : 403);
}

if ($path === '/api/v1/install/download' && $method === 'GET') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
    if (! Security::rateLimit('install-dl:'.$ip, 30, 60, $basePath)) {
        http_response_code(429);
        echo 'Too many requests';
        exit;
    }
    $token = trim((string) ($_GET['token'] ?? ''));
    $consumed = $releases->consumeDownloadToken($token);
    if (! $consumed) {
        http_response_code(403);
        echo 'Token inválido ou expirado';
        exit;
    }
    $rel = $consumed['release'];
    $downloadName = 'honeypay-gateway-'.preg_replace('/[^\w.\-]+/', '-', (string) $rel['version']).'.zip';
    $releases->streamRelease($rel, $downloadName);
}

if (($path === '/vps-install.sh' || $path === '/install.sh') && $method === 'GET') {
    $scriptPath = $basePath.'/templates/vps-install.sh';
    if (! is_file($scriptPath)) {
        http_response_code(404);
        echo 'Installer missing';
        exit;
    }
    $script = (string) file_get_contents($scriptPath);
    $script = str_replace(
        ['__PORTAL_URL__', '__APP_NAME__'],
        [$appUrl, $appName],
        $script
    );
    header('Content-Type: text/x-shellscript; charset=utf-8');
    header('Content-Disposition: inline; filename="vps-install.sh"');
    header('Cache-Control: no-store');
    echo $script;
    exit;
}

if ($path === '/vps-update.sh' && $method === 'GET') {
    $scriptPath = $basePath.'/templates/vps-update.sh';
    if (! is_file($scriptPath)) {
        http_response_code(404);
        echo 'Updater missing';
        exit;
    }
    $script = (string) file_get_contents($scriptPath);
    $script = str_replace(
        ['__PORTAL_URL__', '__APP_NAME__'],
        [$appUrl, $appName],
        $script
    );
    header('Content-Type: text/x-shellscript; charset=utf-8');
    header('Content-Disposition: inline; filename="vps-update.sh"');
    header('Cache-Control: no-store');
    echo $script;
    exit;
}

// --- Checkout webhook (per-product) ---
if ($method === 'POST' && preg_match('#^/webhooks/checkout/([a-f0-9]{32})$#', $path, $whMatch)) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
    if (! Security::rateLimit('webhook:'.$ip, 30, 60, $basePath)) {
        json_response(['ok' => false, 'message' => 'Too many requests'], 429);
    }

    $authHeader = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    $bearer = '';
    if (preg_match('/^\s*Bearer\s+(\S+)\s*$/i', $authHeader, $m)) {
        $bearer = $m[1];
    }
    $provided = $bearer !== '' ? $bearer : (string) ($_SERVER['HTTP_X_WEBHOOK_SECRET'] ?? '');

    $bound = $products->findByWebhookToken($whMatch[1]);
    if (! $bound || empty($bound['webhook_secret'])) {
        json_response(['ok' => false, 'message' => 'Unknown webhook endpoint'], 404);
    }
    $secret = (string) $bound['webhook_secret'];
    $boundProductId = (int) $bound['id'];

    if ($secret === '' || $provided === '' || ! hash_equals($secret, $provided)) {
        json_response(['ok' => false, 'message' => 'Unauthorized'], 401);
    }

    $body = request_json();
    $result = $webhooks->handle($body, $boundProductId);
    json_response([
        'ok' => (bool) ($result['ok'] ?? false),
        'message' => $result['message'] ?? '',
    ], ! empty($result['ok']) ? 200 : 422);
}

// --- Auth pages ---
if ($path === '/logout') {
    if ($method === 'POST') {
        require_csrf();
        $auth->logout();
        Security::startSession();
        redirect('/login');
    }
    http_response_code(405);
    header('Allow: POST');
    echo 'Use POST /logout';
    exit;
}

if ($path === '/login' || $path === '/') {
    if ($auth->customerCheck()) {
        redirect('/app');
    }
    if ($auth->adminCheck()) {
        redirect('/admin');
    }
    if ($method === 'POST') {
        require_csrf();
        $email = (string) ($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
        if (! Security::rateLimit('login:'.$ip.':'.strtolower($email), 5, 60, $basePath)) {
            render('auth/login', ['appName' => $appName, 'title' => 'Login', 'error' => 'Muitas tentativas. Aguarde um minuto.', 'csrf' => Security::csrfToken()]);
            exit;
        }
        $customer = $customers->attempt($email, $password);
        if ($customer) {
            $auth->loginCustomer($customer);
            redirect('/app');
        }
        render('auth/login', ['appName' => $appName, 'title' => 'Login', 'error' => 'Credenciais inválidas.', 'csrf' => Security::csrfToken()]);
        exit;
    }
    render('auth/login', ['appName' => $appName, 'title' => 'Login', 'error' => null, 'csrf' => Security::csrfToken()]);
    exit;
}

if ($path === '/admin/login') {
    if ($auth->adminCheck()) {
        redirect('/admin');
    }
    if ($auth->adminPending2fa()) {
        redirect('/admin/2fa');
    }
    if ($method === 'POST') {
        require_csrf();
        $email = (string) ($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
        if (! Security::rateLimit('adminlogin:'.$ip, 5, 60, $basePath)) {
            render('auth/admin_login', ['appName' => $appName, 'title' => 'Admin', 'error' => 'Muitas tentativas.', 'csrf' => Security::csrfToken()]);
            exit;
        }
        if ($auth->attemptAdmin($email, $password)) {
            if ($auth->adminPending2fa()) {
                redirect('/admin/2fa');
            }
            redirect('/admin');
        }
        render('auth/admin_login', ['appName' => $appName, 'title' => 'Admin', 'error' => 'Credenciais inválidas.', 'csrf' => Security::csrfToken()]);
        exit;
    }
    render('auth/admin_login', ['appName' => $appName, 'title' => 'Admin', 'error' => null, 'csrf' => Security::csrfToken()]);
    exit;
}

if ($path === '/admin/2fa') {
    if ($auth->adminCheck()) {
        redirect('/admin');
    }
    if (! $auth->adminPending2fa()) {
        redirect('/admin/login');
    }
    $pendingId = (int) $auth->pendingAdminId();
    if ($method === 'POST') {
        require_csrf();
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
        if (! Security::rateLimit('admin2fa:'.$ip.':'.$pendingId, 5, 60, $basePath)) {
            render('auth/admin_2fa', ['appName' => $appName, 'title' => '2FA', 'error' => 'Muitas tentativas.', 'csrf' => Security::csrfToken()]);
            exit;
        }
        $code = (string) ($_POST['code'] ?? '');
        if ($adminTotp->verifyLogin($pendingId, $code)) {
            $admin = $adminTotp->findAdmin($pendingId);
            if ($admin) {
                $auth->completeAdminLogin($admin);
                redirect('/admin');
            }
        }
        render('auth/admin_2fa', ['appName' => $appName, 'title' => '2FA', 'error' => 'Código inválido.', 'csrf' => Security::csrfToken()]);
        exit;
    }
    render('auth/admin_2fa', ['appName' => $appName, 'title' => '2FA', 'error' => null, 'csrf' => Security::csrfToken()]);
    exit;
}

if ($path === '/forgot-password') {
    if ($method === 'POST') {
        require_csrf();
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
        if (! Security::rateLimit('forgot:'.$ip, 5, 300, $basePath)) {
            render('auth/forgot', ['appName' => $appName, 'title' => 'Recuperar senha', 'flash' => 'Muitas tentativas. Aguarde alguns minutos.', 'csrf' => Security::csrfToken()]);
            exit;
        }
        $email = (string) ($_POST['email'] ?? '');
        $customer = $customers->findByEmail($email);
        if ($customer) {
            $token = $customers->createResetToken((int) $customer['id']);
            $link = $appUrl.'/reset-password/'.$token;
            $mailer->send(
                (string) $customer['email'],
                'Redefinir senha',
                $mailer->welcomeHtml(
                    (string) $customer['name'],
                    $appUrl.'/login',
                    $link,
                    '—',
                    true
                )
            );
        }
        render('auth/forgot', ['appName' => $appName, 'title' => 'Recuperar senha', 'flash' => 'Se o e-mail existir, enviamos o link.', 'csrf' => Security::csrfToken()]);
        exit;
    }
    render('auth/forgot', ['appName' => $appName, 'title' => 'Recuperar senha', 'flash' => null, 'csrf' => Security::csrfToken()]);
    exit;
}

if (preg_match('#^/reset-password/([a-f0-9]{64})$#', $path, $m)) {
    $token = $m[1];
    if ($method === 'POST') {
        require_csrf();
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
        if (! Security::rateLimit('reset:'.$ip, 10, 300, $basePath)) {
            render('auth/reset', ['appName' => $appName, 'title' => 'Definir senha', 'error' => 'Muitas tentativas.', 'token' => $token, 'csrf' => Security::csrfToken()]);
            exit;
        }
        $password = (string) ($_POST['password'] ?? '');
        if (strlen($password) < 8) {
            render('auth/reset', ['appName' => $appName, 'title' => 'Definir senha', 'error' => 'Mínimo 8 caracteres.', 'token' => $token, 'csrf' => Security::csrfToken()]);
            exit;
        }
        $customer = $customers->consumeResetToken($token);
        if (! $customer) {
            render('auth/reset', ['appName' => $appName, 'title' => 'Definir senha', 'error' => 'Token inválido ou expirado.', 'token' => $token, 'csrf' => Security::csrfToken()]);
            exit;
        }
        $customers->setPassword((int) $customer['id'], $password);
        $auth->loginCustomer($customers->findById((int) $customer['id']) ?? $customer);
        redirect('/app');
    }
    render('auth/reset', ['appName' => $appName, 'title' => 'Definir senha', 'error' => null, 'token' => $token, 'csrf' => Security::csrfToken()]);
    exit;
}

// --- Customer app ---
if (str_starts_with($path, '/app')) {
    $isDocsPath = $path === '/app/docs' || preg_match('#^/app/docs/[a-z0-9\-]+$#', $path);
    $adminReadingDocs = $isDocsPath
        && $auth->adminCheck()
        && ! $auth->adminPending2fa();

    if (! $adminReadingDocs) {
        if (! $auth->customerCheck()) {
            // Admin autenticado sem sessão de cliente: resto de /app não se aplica.
            if ($auth->adminCheck() && ! $auth->adminPending2fa()) {
                redirect('/admin');
            }
            redirect('/login');
        }
        $customerId = (int) $auth->customerId();
        $customer = $customers->findById($customerId);
        if (! $customer || ($customer['status'] ?? '') !== 'active') {
            $auth->logout();
            Security::startSession();
            redirect('/login');
        }
    } else {
        $customerId = 0;
        $customer = null;
    }

    if ($path === '/app' || $path === '/app/') {
        render('customer/dashboard', [
            'appName' => $appName,
            'title' => 'Início',
            'active' => 'dashboard',
            'customer' => $customer,
            'licenses' => $licenses->listForCustomer($customerId),
            'entitlements' => $products->entitlementsForCustomer($customerId),
        ], 'customer');
        exit;
    }

    if ($path === '/app/account') {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if ($method === 'POST') {
            require_csrf();
            if (! Security::rateLimit('cust-account:'.$customerId.':'.$ip, 20, 600, $basePath)) {
                $_SESSION['flash_error'] = 'Muitas tentativas. Aguarde alguns minutos.';
                redirect('/app/account');
            }
            $action = (string) ($_POST['action'] ?? '');
            if ($action === 'update_profile') {
                $result = $customers->updateOwnProfile(
                    $customerId,
                    (string) ($_POST['name'] ?? ''),
                    (string) ($_POST['phone'] ?? '')
                );
                $_SESSION[$result['ok'] ? 'flash' : 'flash_error'] = $result['message'];
            }
            if ($action === 'update_password') {
                $new = (string) ($_POST['new_password'] ?? '');
                $confirm = (string) ($_POST['new_password_confirmation'] ?? '');
                if ($new !== $confirm) {
                    $_SESSION['flash_error'] = 'A confirmação da senha não confere.';
                } else {
                    $result = $customers->changeOwnPassword(
                        $customerId,
                        (string) ($_POST['current_password'] ?? ''),
                        $new
                    );
                    $_SESSION[$result['ok'] ? 'flash' : 'flash_error'] = $result['message'];
                }
            }
            redirect('/app/account');
        }
        $customer = $customers->findById($customerId) ?? $customer;
        render('customer/account', [
            'appName' => $appName,
            'title' => 'Minha conta',
            'active' => 'account',
            'customer' => $customer,
            'csrf' => Security::csrfToken(),
            'flash' => $_SESSION['flash'] ?? null,
            'error' => $_SESSION['flash_error'] ?? null,
        ], 'customer');
        unset($_SESSION['flash'], $_SESSION['flash_error']);
        exit;
    }

    if ($path === '/app/license') {
        if ($method === 'POST' && ($_POST['action'] ?? '') === 'clear_localhost') {
            require_csrf();
            $lid = (int) ($_POST['license_id'] ?? 0);
            $owned = array_filter($licenses->listForCustomer($customerId), static fn ($l) => (int) $l['id'] === $lid);
            if ($owned) {
                $licenses->clearLocalhostActivations($lid);
            }
            redirect('/app/license');
        }
        render('customer/license', [
            'appName' => $appName,
            'title' => 'Licença',
            'active' => 'license',
            'licenses' => $licenses->listForCustomer($customerId),
            'csrf' => Security::csrfToken(),
            'flash' => $_SESSION['flash'] ?? null,
        ], 'customer');
        unset($_SESSION['flash']);
        exit;
    }

    if ($path === '/app/install') {
        $canDownload = $releases->customerMayDownload($customerId);
        $currentRelease = $releases->currentRelease();
        render('customer/install', [
            'appName' => $appName,
            'title' => 'Instalação + Update',
            'active' => 'install',
            'canDownload' => $canDownload,
            'currentRelease' => $currentRelease,
            'installCommand' => 'curl -fsSL '.$appUrl.'/vps-install.sh | sudo bash',
            'updateCommand' => 'curl -fsSL '.$appUrl.'/vps-update.sh | sudo bash',
            'appUrl' => $appUrl,
            'error' => $_SESSION['flash_error'] ?? null,
        ], 'customer');
        unset($_SESSION['flash_error']);
        exit;
    }

    if ($path === '/app/install/download' && $method === 'GET') {
        if (! $releases->customerMayDownload($customerId)) {
            $_SESSION['flash_error'] = 'Licença ativa necessária para baixar.';
            redirect('/app/install');
        }
        $current = $releases->currentRelease();
        if (! $current) {
            $_SESSION['flash_error'] = 'Nenhum release disponível.';
            redirect('/app/install');
        }
        $downloadName = 'honeypay-gateway-'.preg_replace('/[^\w.\-]+/', '-', (string) $current['version']).'.zip';
        $releases->streamRelease($current, $downloadName);
    }

    if ($path === '/app/install/download-schema' && $method === 'GET') {
        if (! $releases->customerMayDownload($customerId)) {
            $_SESSION['flash_error'] = 'Licença ativa necessária para baixar.';
            redirect('/app/install');
        }
        $current = $releases->currentRelease();
        if (! $current || ! $releases->hasSchema($current)) {
            $_SESSION['flash_error'] = 'Nenhum dump SQL disponível nesta versão.';
            redirect('/app/install');
        }
        $downloadName = 'honeypay-database-'.preg_replace('/[^\w.\-]+/', '-', (string) $current['version']).'.sql';
        $releases->streamSchema($current, $downloadName);
    }

    if ($path === '/app/products') {
        render('customer/products', [
            'appName' => $appName,
            'title' => 'Produtos',
            'active' => 'products',
            'entitlements' => $products->entitlementsForCustomer($customerId),
            'catalog' => $products->catalogForCustomer($customerId),
            'csrf' => Security::csrfToken(),
            'flash' => $_SESSION['flash'] ?? null,
            'error' => $_SESSION['flash_error'] ?? null,
        ], 'customer');
        unset($_SESSION['flash'], $_SESSION['flash_error']);
        exit;
    }

    if ($path === '/app/products/refund' && $method === 'POST') {
        require_csrf();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if (! Security::rateLimit('refund:'.$customerId.':'.$ip, 10, 600, $basePath)) {
            $_SESSION['flash_error'] = 'Muitas tentativas. Aguarde alguns minutos.';
            redirect('/app/products');
        }
        $result = $refunds->request(
            $customerId,
            (int) ($_POST['order_id'] ?? 0),
            (string) ($_POST['reason'] ?? '')
        );
        $_SESSION[$result['ok'] ? 'flash' : 'flash_error'] = $result['message'];
        redirect('/app/products');
    }

    if (preg_match('#^/app/products/([a-z0-9\-]+)/image$#', $path, $m)) {
        $product = $products->findBySlug($m[1]);
        if (! $product) {
            http_response_code(404);
            exit;
        }
        $owned = $products->customerHasProduct($customerId, (int) $product['id']);
        if (empty($product['is_published']) && ! $owned) {
            http_response_code(404);
            exit;
        }
        $products->streamImage($product);
    }

    if (preg_match('#^/app/products/([a-z0-9\-]+)/download$#', $path, $m)) {
        $product = $products->findBySlug($m[1]);
        if (! $product) {
            http_response_code(404);
            echo 'Produto não encontrado';
            exit;
        }
        if (($product['kind'] ?? '') === ProductService::KIND_GATEWAY) {
            redirect('/app/install');
        }
        if (! $products->customerHasProduct($customerId, (int) $product['id'])) {
            http_response_code(403);
            echo 'Compra necessária para baixar este plugin.';
            exit;
        }
        if (empty($product['plugin_zip_path'])) {
            http_response_code(404);
            echo 'ZIP ainda não disponível.';
            exit;
        }
        if (! Security::rateLimit('plugin-dl:'.$customerId.':'.(int) $product['id'], 30, 3600, $basePath)) {
            http_response_code(429);
            echo 'Muitos downloads. Tente novamente mais tarde.';
            exit;
        }
        $products->streamPluginZip($product);
    }

    if ($path === '/app/docs') {
        $hasAccess = $adminReadingDocs || $products->customerHasActiveEntitlement($customerId);
        $publishedOnly = ! $adminReadingDocs;
        $sections = $hasAccess ? $lessons->sectionsWithLessons($publishedOnly) : [];
        $flat = $hasAccess
            ? ($publishedOnly ? $lessons->flatPublishedPages() : $lessons->flatPages(false))
            : [];
        render('customer/docs_index', [
            'appName' => $appName,
            'title' => 'Documentação',
            'active' => 'docs',
            'sections' => $sections,
            'hasAccess' => $hasAccess,
            'activeSlug' => null,
            'toc' => [],
            'prev' => null,
            'next' => $flat[0] ?? null,
            'viewerIsAdmin' => $adminReadingDocs,
            'docsBase' => '/app/docs',
            'docsHomeHref' => $adminReadingDocs ? '/admin' : '/app',
            'docsSecondaryHref' => $adminReadingDocs ? '/admin/docs' : '/app/install',
            'docsSecondaryLabel' => $adminReadingDocs ? 'Editar docs' : 'Instalar / atualizar',
            'docsPrimaryLabel' => $adminReadingDocs ? 'Admin' : 'Minha conta',
        ], 'customer', 'layout_docs');
        exit;
    }

    if (preg_match('#^/app/docs/([a-z0-9\-]+)$#', $path, $m)) {
        $hasAccess = $adminReadingDocs || $products->customerHasActiveEntitlement($customerId);
        $lesson = $lessons->findBySlug($m[1]);
        if (! $lesson) {
            http_response_code(404);
            echo 'Aula não encontrada';
            exit;
        }
        if (! $adminReadingDocs && empty($lesson['published'])) {
            http_response_code(404);
            echo 'Aula não encontrada';
            exit;
        }
        if (! $hasAccess && empty($lesson['docs_public'])) {
            redirect('/app/docs');
        }
        $html = Markdown::toHtml((string) $lesson['body_markdown']);
        $neighbors = $adminReadingDocs
            ? $lessons->neighbors((string) $lesson['slug'], false)
            : $lessons->neighbors((string) $lesson['slug'], true);
        render('customer/docs_show', [
            'appName' => $appName,
            'title' => (string) $lesson['title'],
            'active' => 'docs',
            'lesson' => $lesson,
            'html' => $html,
            'sections' => $lessons->sectionsWithLessons(! $adminReadingDocs),
            'hasAccess' => true,
            'activeSlug' => (string) $lesson['slug'],
            'toc' => Markdown::extractToc($html),
            'prev' => $neighbors['prev'],
            'next' => $neighbors['next'],
            'viewerIsAdmin' => $adminReadingDocs,
            'docsBase' => '/app/docs',
            'docsHomeHref' => $adminReadingDocs ? '/admin' : '/app',
            'docsSecondaryHref' => $adminReadingDocs ? '/admin/docs' : '/app/install',
            'docsSecondaryLabel' => $adminReadingDocs ? 'Editar docs' : 'Instalar / atualizar',
            'docsPrimaryLabel' => $adminReadingDocs ? 'Admin' : 'Minha conta',
        ], 'customer', 'layout_docs');
        exit;
    }
}

// --- Admin ---
if (str_starts_with($path, '/admin')) {
    if ($auth->adminPending2fa()) {
        redirect('/admin/2fa');
    }
    if (! $auth->adminCheck()) {
        redirect('/admin/login');
    }

    if ($path === '/admin' || $path === '/admin/') {
        if ($method === 'POST' && ($_POST['action'] ?? '') === 'create_license') {
            require_csrf();
            $created = $licenses->createLicense(
                max(1, (int) ($_POST['max_activations'] ?? 1)),
                trim((string) ($_POST['customer_note'] ?? '')) ?: null,
                trim((string) ($_POST['expires_at'] ?? '')) ?: null
            );
            $_SESSION['flash'] = 'Licença criada: '.$created['license_key'];
            redirect('/admin');
        }
        if ($method === 'POST' && ($_POST['action'] ?? '') === 'set_status') {
            require_csrf();
            $licenses->setStatus((int) ($_POST['id'] ?? 0), (string) ($_POST['status'] ?? 'blocked'));
            redirect('/admin');
        }
        render('admin/licenses', [
            'appName' => $appName,
            'title' => 'Licenças',
            'licenses' => $licenses->listLicenses(),
            'flash' => $_SESSION['flash'] ?? null,
            'csrf' => Security::csrfToken(),
        ], 'admin');
        unset($_SESSION['flash']);
        exit;
    }

    if (preg_match('#^/admin/licenses/(\d+)$#', $path, $m)) {
        $id = (int) $m[1];
        $all = $licenses->listLicenses();
        $license = null;
        foreach ($all as $row) {
            if ((int) $row['id'] === $id) {
                $license = $row;
                break;
            }
        }
        if (! $license) {
            http_response_code(404);
            echo 'Não encontrado';
            exit;
        }

        if ($method === 'POST') {
            require_csrf();
            $action = (string) ($_POST['action'] ?? '');
            $activationId = (int) ($_POST['activation_id'] ?? 0);
            try {
                if ($action === 'set_activation_domain') {
                    $licenses->adminSetActivationDomain($id, $activationId, (string) ($_POST['domain'] ?? ''));
                    $_SESSION['flash'] = 'Domínio da ativação atualizado.';
                } elseif ($action === 'delete_activation') {
                    $licenses->adminDeleteActivation($id, $activationId);
                    $_SESSION['flash'] = 'Ativação removida.';
                } else {
                    $_SESSION['flash_error'] = 'Ação inválida.';
                }
            } catch (Throwable $e) {
                $_SESSION['flash_error'] = $e->getMessage();
            }
            redirect('/admin/licenses/'.$id);
        }

        render('admin/license_show', [
            'appName' => $appName,
            'title' => 'Licença',
            'license' => $license,
            'activations' => $licenses->listActivations($id),
            'csrf' => Security::csrfToken(),
            'flash' => $_SESSION['flash'] ?? null,
            'error' => $_SESSION['flash_error'] ?? null,
        ], 'admin');
        unset($_SESSION['flash'], $_SESSION['flash_error']);
        exit;
    }

    if ($path === '/admin/customers') {
        if ($method === 'POST') {
            require_csrf();
            $action = (string) ($_POST['action'] ?? '');
            if ($action === 'create') {
                $result = $customers->createManual(
                    (string) ($_POST['email'] ?? ''),
                    (string) ($_POST['name'] ?? ''),
                    trim((string) ($_POST['phone'] ?? '')) ?: null,
                    trim((string) ($_POST['password'] ?? '')) ?: null,
                    (string) ($_POST['status'] ?? 'active')
                );
                $_SESSION['flash'] = $result['message'];
                if (! empty($result['ok']) && ! empty($result['customer']['id'])) {
                    redirect('/admin/customers/'.(int) $result['customer']['id']);
                }
            }
            redirect('/admin/customers');
        }
        render('admin/customers', [
            'appName' => $appName,
            'title' => 'Clientes',
            'customers' => $customers->listAll(),
            'flash' => $_SESSION['flash'] ?? null,
            'csrf' => Security::csrfToken(),
        ], 'admin');
        unset($_SESSION['flash']);
        exit;
    }

    if (preg_match('#^/admin/customers/(\d+)$#', $path, $m)) {
        $id = (int) $m[1];
        $customer = $customers->findById($id);
        if (! $customer) {
            http_response_code(404);
            echo 'Cliente não encontrado';
            exit;
        }
        if ($method === 'POST') {
            require_csrf();
            $action = (string) ($_POST['action'] ?? 'update');
            if ($action === 'clear_password') {
                $customers->clearPassword($id);
                $_SESSION['flash'] = 'Senha removida. O cliente precisará definir uma nova.';
                redirect('/admin/customers/'.$id);
            }
            if ($action === 'send_reset') {
                $token = $customers->createResetToken($id);
                $link = $appUrl.'/reset-password/'.$token;
                $mailer->send(
                    (string) $customer['email'],
                    'Redefinir senha',
                    $mailer->welcomeHtml(
                        (string) $customer['name'],
                        $appUrl.'/login',
                        $link,
                        '—',
                        true
                    )
                );
                $_SESSION['flash'] = 'Link de redefinição enviado (veja também storage/mail.log).';
                redirect('/admin/customers/'.$id);
            }

            $result = $customers->updateManual(
                $id,
                (string) ($_POST['email'] ?? ''),
                (string) ($_POST['name'] ?? ''),
                trim((string) ($_POST['phone'] ?? '')) ?: null,
                (string) ($_POST['status'] ?? 'active'),
                trim((string) ($_POST['password'] ?? '')) ?: null
            );
            $_SESSION['flash'] = $result['message'];
            redirect('/admin/customers/'.$id);
        }
        render('admin/customer_edit', [
            'appName' => $appName,
            'title' => 'Editar cliente',
            'customer' => $customer,
            'licenses' => $licenses->listForCustomer($id),
            'flash' => $_SESSION['flash'] ?? null,
            'csrf' => Security::csrfToken(),
        ], 'admin');
        unset($_SESSION['flash']);
        exit;
    }

    if ($path === '/admin/orders') {
        render('admin/orders', [
            'appName' => $appName,
            'title' => 'Pedidos',
            'orders' => $webhooks->listOrders(),
        ], 'admin');
        exit;
    }

    if ($path === '/admin/refunds') {
        if ($method === 'POST') {
            require_csrf();
            $action = (string) ($_POST['action'] ?? '');
            if ($action === 'complete') {
                $result = $refunds->complete(
                    (int) ($_POST['id'] ?? 0),
                    isset($_POST['admin_notes']) ? (string) $_POST['admin_notes'] : null
                );
                $_SESSION[$result['ok'] ? 'flash' : 'flash_error'] = $result['message'];
            } else {
                $_SESSION['flash_error'] = 'Ação inválida.';
            }
            redirect('/admin/refunds');
        }
        render('admin/refunds', [
            'appName' => $appName,
            'title' => 'Reembolsos',
            'refunds' => $refunds->listForAdmin(),
            'csrf' => Security::csrfToken(),
            'flash' => $_SESSION['flash'] ?? null,
            'error' => $_SESSION['flash_error'] ?? null,
        ], 'admin');
        unset($_SESSION['flash'], $_SESSION['flash_error']);
        exit;
    }

    if ($path === '/admin/webhooks') {
        if ($method === 'POST') {
            require_csrf();
            $action = (string) ($_POST['action'] ?? '');
            if ($action === 'rotate_product') {
                $pid = (int) ($_POST['product_id'] ?? 0);
                $result = $products->rotateWebhookCredentials($pid, $appUrl);
                $_SESSION[$result['ok'] ? 'flash' : 'flash_error'] = $result['message'];
                if ($result['ok']) {
                    $_SESSION['reveal_product_id'] = $pid;
                }
            }
            redirect('/admin/webhooks');
        }

        $products->ensureAllWebhookCredentials();
        render('admin/webhooks', [
            'appName' => $appName,
            'title' => 'Webhooks entrada',
            'events' => $webhooks->listEvents(100),
            'productsList' => $products->listAll(),
            'appUrl' => $appUrl,
            'revealProductId' => isset($_SESSION['reveal_product_id']) ? (int) $_SESSION['reveal_product_id'] : null,
            'csrf' => Security::csrfToken(),
            'flash' => $_SESSION['flash'] ?? null,
            'error' => $_SESSION['flash_error'] ?? null,
            'acceptTest' => filter_var(Env::get('WEBHOOK_ACCEPT_TEST', 'false'), FILTER_VALIDATE_BOOLEAN),
        ], 'admin');
        unset($_SESSION['flash'], $_SESSION['flash_error'], $_SESSION['reveal_product_id']);
        exit;
    }

    if ($path === '/admin/account') {
        $adminId = (int) $auth->adminId();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if ($method === 'POST') {
            require_csrf();
            if (! Security::rateLimit('admin-account:'.$adminId.':'.$ip, 20, 600, $basePath)) {
                $_SESSION['flash_error'] = 'Muitas tentativas. Aguarde alguns minutos.';
                redirect('/admin/account');
            }
            $action = (string) ($_POST['action'] ?? '');
            if ($action === 'update_email') {
                $result = $auth->updateAdminEmail(
                    $adminId,
                    (string) ($_POST['email'] ?? ''),
                    (string) ($_POST['current_password'] ?? '')
                );
                $_SESSION[$result['ok'] ? 'flash' : 'flash_error'] = $result['message'];
            }
            if ($action === 'update_password') {
                $new = (string) ($_POST['new_password'] ?? '');
                $confirm = (string) ($_POST['new_password_confirmation'] ?? '');
                if ($new !== $confirm) {
                    $_SESSION['flash_error'] = 'A confirmação da senha não confere.';
                } else {
                    $result = $auth->updateAdminPassword(
                        $adminId,
                        (string) ($_POST['current_password'] ?? ''),
                        $new
                    );
                    $_SESSION[$result['ok'] ? 'flash' : 'flash_error'] = $result['message'];
                }
            }
            if ($action === 'create_admin') {
                $pass = (string) ($_POST['password'] ?? '');
                $confirm = (string) ($_POST['password_confirmation'] ?? '');
                if ($pass !== $confirm) {
                    $_SESSION['flash_error'] = 'A confirmação da senha inicial não confere.';
                } else {
                    $result = $auth->createAdmin(
                        (string) ($_POST['email'] ?? ''),
                        $pass,
                        (string) ($_POST['current_password'] ?? ''),
                        $adminId
                    );
                    $_SESSION[$result['ok'] ? 'flash' : 'flash_error'] = $result['message'];
                }
            }
            if ($action === 'delete_admin') {
                $result = $auth->deleteAdmin(
                    (int) ($_POST['admin_id'] ?? 0),
                    $adminId,
                    (string) ($_POST['current_password'] ?? '')
                );
                $_SESSION[$result['ok'] ? 'flash' : 'flash_error'] = $result['message'];
            }
            redirect('/admin/account');
        }

        $me = $auth->findAdminById($adminId);
        render('admin/account', [
            'appName' => $appName,
            'title' => 'Minha conta',
            'admin' => [
                'id' => $adminId,
                'email' => (string) ($me['email'] ?? $auth->adminEmail() ?? ''),
            ],
            'admins' => $auth->listAdmins(),
            'csrf' => Security::csrfToken(),
            'flash' => $_SESSION['flash'] ?? null,
            'error' => $_SESSION['flash_error'] ?? null,
        ], 'admin');
        unset($_SESSION['flash'], $_SESSION['flash_error']);
        exit;
    }

    if ($path === '/admin/settings') {
        $adminId = (int) $auth->adminId();
        if ($method === 'POST') {
            require_csrf();
            $action = (string) ($_POST['action'] ?? 'save');
            if ($action === 'save') {
                $password = trim((string) ($_POST['mail_password'] ?? ''));
                $settings->saveMailConfig([
                    'mail_from' => trim((string) ($_POST['mail_from'] ?? '')),
                    'mail_host' => trim((string) ($_POST['mail_host'] ?? '')),
                    'mail_port' => trim((string) ($_POST['mail_port'] ?? '587')),
                    'mail_username' => trim((string) ($_POST['mail_username'] ?? '')),
                    'mail_password' => $password,
                    'mail_encryption' => trim((string) ($_POST['mail_encryption'] ?? 'tls')),
                ], $password !== '');
                $_SESSION['flash'] = 'Configurações SMTP salvas.';
            }
            if ($action === 'test') {
                $to = trim((string) ($_POST['test_to'] ?? ''));
                if ($to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
                    $_SESSION['flash'] = 'Informe um e-mail de teste válido.';
                } else {
                    $result = $mailer->send(
                        $to,
                        'Teste SMTP — '.$appName,
                        '<p>Este é um e-mail de teste do portal de licenças.</p><p>Se você recebeu, o SMTP está ok.</p>'
                    );
                    $_SESSION['flash'] = $result['ok']
                        ? 'E-mail de teste: '.$result['message']
                        : 'Falha no teste: '.$result['message'];
                }
            }
            if ($action === 'totp_begin') {
                $setup = $adminTotp->beginSetup($adminId, (string) ($auth->adminEmail() ?? 'admin'));
                $_SESSION['totp_setup'] = [
                    'secret' => $setup['secret'],
                    'otpauth' => $setup['otpauth'],
                    'qr_svg' => $setup['qr_svg'],
                ];
                $_SESSION['flash'] = 'Escaneie o QR e confirme com um código.';
            }
            if ($action === 'totp_confirm') {
                $code = (string) ($_POST['code'] ?? '');
                if ($adminTotp->confirmSetup($adminId, $code)) {
                    unset($_SESSION['totp_setup']);
                    $_SESSION['flash'] = '2FA ativado com sucesso.';
                } else {
                    $_SESSION['flash'] = 'Código inválido. Tente novamente.';
                }
            }
            if ($action === 'totp_disable') {
                $ok = $adminTotp->disable(
                    $adminId,
                    (string) ($_POST['password'] ?? ''),
                    (string) ($_POST['code'] ?? '')
                );
                $_SESSION['flash'] = $ok ? '2FA desativado.' : 'Senha ou código inválidos.';
                unset($_SESSION['totp_setup']);
            }
            redirect('/admin/settings');
        }
        $mailCfg = $settings->mailConfig([
            'mail_from' => Env::get('MAIL_FROM'),
            'mail_host' => Env::get('MAIL_HOST') ?: null,
            'mail_port' => Env::get('MAIL_PORT') ?: '587',
            'mail_username' => Env::get('MAIL_USERNAME'),
            'mail_password' => Env::get('MAIL_PASSWORD'),
            'mail_encryption' => Env::get('MAIL_ENCRYPTION') ?: 'tls',
        ]);
        render('admin/settings', [
            'appName' => $appName,
            'title' => 'Configurações',
            'mail' => $mailCfg,
            'password_set' => ! empty($mailCfg['mail_password']),
            'totp_enabled' => $adminTotp->isEnabled($adminId),
            'totp_setup' => $_SESSION['totp_setup'] ?? null,
            'flash' => $_SESSION['flash'] ?? null,
            'csrf' => Security::csrfToken(),
        ], 'admin');
        unset($_SESSION['flash']);
        exit;
    }

    if ($path === '/admin/outbound-webhooks') {
        if ($method === 'POST') {
            require_csrf();
            $action = (string) ($_POST['action'] ?? '');
            $events = [];
            if (! empty($_POST['event_order_paid'])) {
                $events[] = 'order.paid';
            }
            if (! empty($_POST['event_order_refunded'])) {
                $events[] = 'order.refunded';
            }
            if ($action === 'create') {
                $url = trim((string) ($_POST['url'] ?? ''));
                if (! SafeUrl::isAllowedOutbound($url, ($appEnv ?? 'local') === 'local')) {
                    $_SESSION['flash'] = 'URL inválida ou bloqueada pela política SSRF.';
                } else {
                    $outbound->create(
                        trim((string) ($_POST['name'] ?? '')),
                        $url,
                        trim((string) ($_POST['bearer_token'] ?? '')) ?: null,
                        $events !== [] ? $events : ['order.paid'],
                        ! empty($_POST['enabled'])
                    );
                    $_SESSION['flash'] = 'Webhook de saída criado.';
                }
            }
            if ($action === 'update') {
                $token = trim((string) ($_POST['bearer_token'] ?? ''));
                $url = trim((string) ($_POST['url'] ?? ''));
                if (! SafeUrl::isAllowedOutbound($url, ($appEnv ?? 'local') === 'local')) {
                    $_SESSION['flash'] = 'URL inválida ou bloqueada pela política SSRF.';
                } else {
                    $outbound->update(
                        (int) ($_POST['id'] ?? 0),
                        trim((string) ($_POST['name'] ?? '')),
                        $url,
                        $token !== '' ? $token : null,
                        $events !== [] ? $events : ['order.paid'],
                        ! empty($_POST['enabled']),
                        $token !== ''
                    );
                    $_SESSION['flash'] = 'Webhook atualizado.';
                }
            }
            if ($action === 'delete') {
                $outbound->delete((int) ($_POST['id'] ?? 0));
                $_SESSION['flash'] = 'Webhook removido.';
            }
            if ($action === 'test') {
                $hook = $outbound->find((int) ($_POST['id'] ?? 0));
                if ($hook) {
                    $result = $outbound->deliver(
                        (int) $hook['id'],
                        'order.paid',
                        (string) $hook['url'],
                        $hook['bearer_token'] ?? null,
                        [
                            'event' => 'order.paid',
                            'customer' => true,
                            'customer' => [
                                'name' => 'Cliente Teste',
                                'email' => 'teste@example.com',
                                'phone' => '+5500000000000',
                            ],
                            'license_key' => 'LIC-TEST',
                            'portal_url' => $appUrl,
                            'set_password_url' => $appUrl.'/reset-password/test-token',
                            'product' => ['name' => 'Produto teste', 'external_id' => 'test'],
                            'order' => ['external_id' => 'test-order', 'amount' => 0, 'currency' => 'BRL'],
                            'timestamp' => gmdate('c'),
                        ]
                    );
                    $_SESSION['flash'] = $result['ok']
                        ? 'Teste enviado (HTTP '.($result['status'] ?? '?').').'
                        : 'Falha no teste: '.$result['message'];
                }
            }
            redirect('/admin/outbound-webhooks');
        }
        render('admin/outbound_webhooks', [
            'appName' => $appName,
            'title' => 'Webhooks saída',
            'hooks' => $outbound->listAll(),
            'deliveries' => $outbound->listDeliveries(40),
            'flash' => $_SESSION['flash'] ?? null,
            'csrf' => Security::csrfToken(),
        ], 'admin');
        unset($_SESSION['flash']);
        exit;
    }

    if (preg_match('#^/admin/products/image/(\d+)$#', $path, $im)) {
        $product = $products->findById((int) $im[1]);
        if (! $product) {
            http_response_code(404);
            exit;
        }
        $products->streamImage($product);
    }

    if ($path === '/admin/products') {
        if ($method === 'POST') {
            require_csrf();
            $action = (string) ($_POST['action'] ?? '');
            if ($action === 'create') {
                $result = $products->create([
                    'name' => (string) ($_POST['name'] ?? ''),
                    'slug' => (string) ($_POST['slug'] ?? ''),
                    'kind' => (string) ($_POST['kind'] ?? 'plugin'),
                    'description' => (string) ($_POST['description'] ?? ''),
                    'price' => ($_POST['price'] ?? '') !== '' ? (float) $_POST['price'] : null,
                    'currency' => (string) ($_POST['currency'] ?? 'BRL'),
                    'checkout_url' => (string) ($_POST['checkout_url'] ?? ''),
                    'external_product_id' => (string) ($_POST['external_product_id'] ?? ''),
                    'external_offer_id' => (string) ($_POST['external_offer_id'] ?? ''),
                    'is_published' => ! empty($_POST['is_published']),
                    'sort_order' => (int) ($_POST['sort_order'] ?? 100),
                ]);
                if ($result['ok'] && ! empty($result['product']['id'])) {
                    $_SESSION['flash'] = $result['message'];
                    redirect('/admin/products?edit='.(int) $result['product']['id']);
                }
                $_SESSION['flash_error'] = $result['message'];
                redirect('/admin/products?new=1');
            }
            if ($action === 'update') {
                $id = (int) ($_POST['id'] ?? 0);
                $result = $products->update($id, [
                    'name' => (string) ($_POST['name'] ?? ''),
                    'slug' => (string) ($_POST['slug'] ?? ''),
                    'kind' => (string) ($_POST['kind'] ?? 'plugin'),
                    'description' => (string) ($_POST['description'] ?? ''),
                    'price' => ($_POST['price'] ?? '') !== '' ? (float) $_POST['price'] : null,
                    'currency' => (string) ($_POST['currency'] ?? 'BRL'),
                    'checkout_url' => (string) ($_POST['checkout_url'] ?? ''),
                    'external_product_id' => (string) ($_POST['external_product_id'] ?? ''),
                    'external_offer_id' => (string) ($_POST['external_offer_id'] ?? ''),
                    'is_published' => ! empty($_POST['is_published']),
                    'sort_order' => (int) ($_POST['sort_order'] ?? 100),
                ]);
                $_SESSION[$result['ok'] ? 'flash' : 'flash_error'] = $result['message'];
                redirect('/admin/products?edit='.$id);
            }
            if ($action === 'delete') {
                $result = $products->delete((int) ($_POST['id'] ?? 0));
                $_SESSION[$result['ok'] ? 'flash' : 'flash_error'] = $result['message'];
                redirect('/admin/products');
            }
            if ($action === 'upload_image') {
                $id = (int) ($_POST['id'] ?? 0);
                $file = $_FILES['image'] ?? ['error' => UPLOAD_ERR_NO_FILE];
                $result = $products->uploadImage($id, $file);
                $_SESSION[$result['ok'] ? 'flash' : 'flash_error'] = $result['message'];
                redirect('/admin/products?edit='.$id);
            }
            if ($action === 'upload_zip') {
                $id = (int) ($_POST['id'] ?? 0);
                $file = $_FILES['zip'] ?? ['error' => UPLOAD_ERR_NO_FILE];
                $result = $products->uploadPluginZip($id, $file);
                $_SESSION[$result['ok'] ? 'flash' : 'flash_error'] = $result['message'];
                redirect('/admin/products?edit='.$id);
            }
            if ($action === 'clear_zip') {
                $id = (int) ($_POST['id'] ?? 0);
                $result = $products->clearPluginZip($id);
                $_SESSION[$result['ok'] ? 'flash' : 'flash_error'] = $result['message'];
                redirect('/admin/products?edit='.$id);
            }
            if ($action === 'rotate_webhook') {
                $id = (int) ($_POST['id'] ?? 0);
                $result = $products->rotateWebhookCredentials($id, $appUrl);
                $_SESSION[$result['ok'] ? 'flash' : 'flash_error'] = $result['message'];
                if ($result['ok']) {
                    $_SESSION['reveal_product_webhook'] = $id;
                }
                redirect('/admin/products?edit='.$id);
            }
            redirect('/admin/products');
        }

        if (isset($_GET['edit']) || isset($_GET['new'])) {
            $editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
            $product = $editId > 0 ? $products->findById($editId) : null;
            if (isset($_GET['edit']) && ! $product) {
                $_SESSION['flash_error'] = 'Produto não encontrado.';
                redirect('/admin/products');
            }
            if ($product) {
                $products->ensureWebhookCredentials((int) $product['id']);
                $product = $products->findById((int) $product['id']) ?? $product;
            }
            render('admin/product_edit', [
                'appName' => $appName,
                'title' => $product ? 'Editar produto' : 'Novo produto',
                'product' => $product,
                'isNew' => $product === null,
                'appUrl' => $appUrl,
                'revealWebhook' => isset($_SESSION['reveal_product_webhook'])
                    && (int) $_SESSION['reveal_product_webhook'] === (int) ($product['id'] ?? 0),
                'csrf' => Security::csrfToken(),
                'flash' => $_SESSION['flash'] ?? null,
                'error' => $_SESSION['flash_error'] ?? null,
            ], 'admin');
            unset($_SESSION['flash'], $_SESSION['flash_error'], $_SESSION['reveal_product_webhook']);
            exit;
        }

        render('admin/products', [
            'appName' => $appName,
            'title' => 'Produtos',
            'products' => $products->listAll(),
            'csrf' => Security::csrfToken(),
            'flash' => $_SESSION['flash'] ?? null,
            'error' => $_SESSION['flash_error'] ?? null,
        ], 'admin');
        unset($_SESSION['flash'], $_SESSION['flash_error']);
        exit;
    }

    if ($path === '/admin/releases') {
        if ($method === 'POST') {
            require_csrf();
            $action = (string) ($_POST['action'] ?? '');
            try {
                if ($action === 'upload') {
                    $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
                    $postMax = php_post_max_bytes();
                    if ($contentLength > 0 && empty($_POST) && empty($_FILES) && $postMax > 0 && $contentLength > $postMax) {
                        throw new RuntimeException(
                            'O envio excede post_max_size do PHP ('.ini_get('post_max_size').'). '
                            .'Aumente o limite no servidor e tente de novo.'
                        );
                    }
                    $file = $_FILES['zip'] ?? null;
                    if (! is_array($file)) {
                        throw new RuntimeException('Selecione um arquivo ZIP.');
                    }
                    $schemaFile = $_FILES['schema'] ?? null;
                    $releases->createFromUpload(
                        $file,
                        trim((string) ($_POST['version'] ?? '')),
                        trim((string) ($_POST['notes'] ?? '')) ?: null,
                        ! empty($_POST['make_current']),
                        is_array($schemaFile) ? $schemaFile : null
                    );
                    $_SESSION['flash'] = 'Release enviado com sucesso.';
                }
                if ($action === 'attach_schema') {
                    $schemaFile = $_FILES['schema'] ?? null;
                    if (! is_array($schemaFile)) {
                        throw new RuntimeException('Selecione um arquivo .sql.');
                    }
                    $releases->attachSchema((int) ($_POST['id'] ?? 0), $schemaFile);
                    $_SESSION['flash'] = 'Dump SQL anexado ao release.';
                }
                if ($action === 'remove_schema') {
                    $releases->removeSchema((int) ($_POST['id'] ?? 0));
                    $_SESSION['flash'] = 'Dump SQL removido do release.';
                }
                if ($action === 'set_current') {
                    $releases->setCurrent((int) ($_POST['id'] ?? 0));
                    $_SESSION['flash'] = 'Release marcado como atual.';
                }
                if ($action === 'delete') {
                    $releases->delete((int) ($_POST['id'] ?? 0));
                    $_SESSION['flash'] = 'Release excluído.';
                }
            } catch (Throwable $e) {
                $_SESSION['flash_error'] = $e->getMessage();
            }
            redirect('/admin/releases');
        }
        render('admin/releases', [
            'appName' => $appName,
            'title' => 'Releases',
            'releases' => $releases->listReleases(),
            'csrf' => Security::csrfToken(),
            'flash' => $_SESSION['flash'] ?? null,
            'error' => $_SESSION['flash_error'] ?? null,
        ], 'admin');
        unset($_SESSION['flash'], $_SESSION['flash_error']);
        exit;
    }

    if ($path === '/admin/lessons') {
        redirect('/admin/docs');
    }

    if ($path === '/admin/docs/preview' && $method === 'POST') {
        require_csrf();
        json_response([
            'html' => Markdown::toHtml((string) ($_POST['markdown'] ?? '')),
        ]);
    }

    if ($path === '/admin/docs') {
        if ($method === 'POST') {
            require_csrf();
            $action = (string) ($_POST['action'] ?? '');
            if ($action === 'save_lesson') {
                $lessonId = ($_POST['id'] ?? '') !== '' ? (int) $_POST['id'] : null;
                $slug = trim((string) ($_POST['slug'] ?? ''));
                $lessons->upsertLesson(
                    $lessonId,
                    ($_POST['section_id'] ?? '') !== '' ? (int) $_POST['section_id'] : null,
                    trim((string) ($_POST['title'] ?? '')),
                    $slug,
                    (string) ($_POST['body_markdown'] ?? ''),
                    (int) ($_POST['sort_order'] ?? 0),
                    ! empty($_POST['published']),
                    ! empty($_POST['docs_public'])
                );
                $_SESSION['flash'] = 'Página salva.';
                if ($lessonId) {
                    redirect('/admin/docs?edit_lesson='.$lessonId);
                }
                $created = $lessons->findBySlug($slug);
                if ($created) {
                    redirect('/admin/docs?edit_lesson='.(int) $created['id']);
                }
                redirect('/admin/docs');
            }
            if ($action === 'save_section') {
                $sectionId = ($_POST['id'] ?? '') !== '' ? (int) $_POST['id'] : null;
                $lessons->upsertSection(
                    $sectionId,
                    trim((string) ($_POST['title'] ?? '')),
                    trim((string) ($_POST['slug'] ?? '')),
                    (int) ($_POST['sort_order'] ?? 0)
                );
                $_SESSION['flash'] = 'Seção salva.';
                if ($sectionId) {
                    redirect('/admin/docs?edit_section='.$sectionId);
                }
                redirect('/admin/docs');
            }
            if ($action === 'delete_lesson') {
                $lessons->deleteLesson((int) ($_POST['id'] ?? 0));
                $_SESSION['flash'] = 'Página excluída.';
            }
            if ($action === 'delete_section') {
                if (! $lessons->deleteSection((int) ($_POST['id'] ?? 0))) {
                    $_SESSION['flash_error'] = 'Só é possível excluir seção sem páginas.';
                } else {
                    $_SESSION['flash'] = 'Seção excluída.';
                }
            }
            redirect('/admin/docs');
        }

        $editLesson = null;
        $editSection = null;
        if (isset($_GET['edit_lesson'])) {
            $editLesson = $lessons->findById((int) $_GET['edit_lesson']);
        }
        if (isset($_GET['edit_section'])) {
            $editSection = $lessons->findSectionById((int) $_GET['edit_section']);
        }

        render('admin/docs', [
            'appName' => $appName,
            'title' => 'Documentação',
            'sections' => $lessons->listSections(),
            'lessons' => $lessons->listAll(),
            'editLesson' => $editLesson,
            'editSection' => $editSection,
            'csrf' => Security::csrfToken(),
            'flash' => $_SESSION['flash'] ?? null,
            'error' => $_SESSION['flash_error'] ?? null,
            'wide' => isset($_GET['edit_lesson']) || (isset($_GET['new']) && $_GET['new'] === 'lesson'),
        ], 'admin');
        unset($_SESSION['flash'], $_SESSION['flash_error']);
        exit;
    }
}

http_response_code(404);
echo 'Not found';
