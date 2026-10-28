<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/admin/_functions.php';
require_once dirname(__DIR__) . '/admin/_inventory.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function api_response(int $status, array $data = []): never
{
    http_response_code($status);
    if ($status === 204) {
        exit;
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function api_error(int $status, string $code, string $message, array $details = []): never
{
    api_response($status, ['success' => false, 'error' => $code, 'message' => $message] + $details);
}

function api_input(): array
{
    $contentType = strtolower(trim(explode(';', (string) ($_SERVER['CONTENT_TYPE'] ?? ''))[0]));
    if ($contentType !== 'application/json') {
        api_error(415, 'UNSUPPORTED_MEDIA_TYPE', 'Content-Type must be application/json.');
    }

    $raw = file_get_contents('php://input');
    if ($raw === false || strlen($raw) > 65536) {
        api_error(413, 'REQUEST_TOO_LARGE', 'Request body is too large.');
    }
    try {
        $input = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        api_error(400, 'INVALID_JSON', 'Request body must contain valid JSON.');
    }
    if (!is_array($input) || array_is_list($input)) {
        api_error(400, 'INVALID_JSON', 'JSON body must be an object.');
    }
    return $input;
}

function api_string_field(array $source, string $key, string $default = ''): string
{
    if (!array_key_exists($key, $source) || $source[$key] === null) {
        return $default;
    }
    if (!is_string($source[$key])) {
        api_error(422, 'VALIDATION_ERROR', "{$key} must be a string.");
    }
    return $source[$key];
}

function api_csrf(): void
{
    $submitted = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($submitted) || !hash_equals(csrf_token(), $submitted)) {
        api_error(419, 'CSRF_TOKEN_INVALID', 'A valid X-CSRF-Token header is required.');
    }
}

function api_user(PDO $pdo, array $roles = [], bool $enforcePasswordChange = true): array
{
    if (!isset($_SESSION['user_id']) || !is_int($_SESSION['user_id'])) {
        api_error(401, 'AUTHENTICATION_REQUIRED', 'Sign in is required.');
    }
    if (isset($_SESSION['last_activity']) && time() - (int) $_SESSION['last_activity'] > 1800) {
        $_SESSION = [];
        session_regenerate_id(true);
        api_error(401, 'SESSION_EXPIRED', 'Sign in again to continue.');
    }
    $query = $pdo->prepare(
        "SELECT id, emri, username, rooli, statusi, duhet_ndryshim_fjalekalimi
         FROM me_perdoruesit WHERE id=? LIMIT 1"
    );
    $query->execute([$_SESSION['user_id']]);
    $user = $query->fetch();
    if (!$user || $user['statusi'] !== 'Aktiv') {
        $_SESSION = [];
        session_regenerate_id(true);
        api_error(401, 'AUTHENTICATION_REQUIRED', 'The account is unavailable. Sign in again.');
    }
    if ($roles !== [] && !in_array($user['rooli'], $roles, true)) {
        api_error(403, 'FORBIDDEN', 'Your role is not allowed to perform this operation.');
    }
    if ($enforcePasswordChange && (int) $user['duhet_ndryshim_fjalekalimi'] === 1) {
        api_error(403, 'PASSWORD_CHANGE_REQUIRED', 'Change your password before using the API.');
    }
    $_SESSION['last_activity'] = time();
    return $user;
}

function api_order_access(array $order, array $user): void
{
    if ($user['rooli'] === 'kurier' && (int) ($order['kurier_id'] ?? 0) !== (int) $user['id']) {
        api_error(404, 'ORDER_NOT_FOUND', 'Order does not exist.');
    }
}

function api_order_payload(PDO $pdo, array $order, array $user, bool $includeItems = true): array
{
    $payload = [
        'id' => (int) $order['id'],
        'tracking_code' => $order['kodi_gjurmimit'],
        'source' => $order['burimi'],
        'customer' => $order['klienti'],
        'phone' => $order['telefoni'],
        'city' => $order['qyteti'],
        'address' => $order['adresa'],
        'status' => $order['statusi'],
        'courier_id' => $order['kurier_id'] === null ? null : (int) $order['kurier_id'],
        'created_at' => $order['data_porosise'],
        'delivered_at' => $order['data_dorezimit'],
    ];
    if ($user['rooli'] === 'admin') {
        $payload['subtotal'] = (float) $order['nentotali'];
        $payload['delivery_fee'] = (float) $order['tarifa_dorezimit'];
        $payload['total'] = (float) $order['totali'];
    }
    if ($includeItems) {
        $unitPriceColumn = $user['rooli'] === 'admin' ? 'cmimi,' : '';
        $itemsQuery = $pdo->prepare(
            "SELECT emri_produktit, {$unitPriceColumn} sasia FROM me_porosi_items WHERE porosia_id=? ORDER BY id"
        );
        $itemsQuery->execute([$order['id']]);
        $payload['items'] = array_map(static function (array $item) use ($user): array {
            $itemPayload = ['product_name' => $item['emri_produktit'], 'quantity' => (int) $item['sasia']];
            if ($user['rooli'] === 'admin') {
                $itemPayload['unit_price'] = (float) $item['cmimi'];
            }
            return $itemPayload;
        }, $itemsQuery->fetchAll());
    }
    return $payload;
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/api'), PHP_URL_PATH) ?: '/api';
$route = (string) ($_GET['route'] ?? '');
if ($route !== '') {
    $routePath = parse_url($route, PHP_URL_PATH) ?: $route;
    $routePath = preg_replace('~^/herbabloom/api~', '', $routePath) ?? $routePath;
    $routePath = preg_replace('~^/api~', '', $routePath) ?? $routePath;
    $requestPath = '/api' . '/' . trim($routePath, '/');
} else {
    $requestPath = preg_replace('~^/herbabloom(?=/api(?:/|$))~', '', $requestPath) ?? $requestPath;
}
$path = '/' . trim((string) preg_replace('~^/api(?:/|$)~', '', $requestPath), '/');
$path = $path === '/' ? '/' : rtrim($path, '/');
$ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 45);

try {
    if ($method === 'GET' && $path === '/auth/csrf') {
        api_response(200, ['success' => true, 'csrf_token' => csrf_token()]);
    }

    if ($method === 'POST' && $path === '/auth/login') {
        api_csrf();
        $input = api_input();
        $username = trim(api_string_field($input, 'username'));
        $password = api_string_field($input, 'password');
        if ($username === '' || strlen($username) > 50 || $password === '') {
            api_error(422, 'VALIDATION_ERROR', 'Username and password are required.', [
                'fields' => ['username' => 'required', 'password' => 'required'],
            ]);
        }
        $rateQuery = $pdo->prepare(
            'SELECT COUNT(*) FROM me_login_tentativa WHERE sukses=0
             AND koha >= DATE_SUB(NOW(), INTERVAL 15 MINUTE) AND (username=? OR ip=?)'
        );
        $rateQuery->execute([$username, $ip]);
        if ((int) $rateQuery->fetchColumn() >= 5) {
            api_error(429, 'LOGIN_RATE_LIMITED', 'Too many sign-in attempts. Try again later.');
        }
        $userQuery = $pdo->prepare(
            'SELECT id, username, fjalekalimi, statusi FROM me_perdoruesit WHERE username=? LIMIT 1'
        );
        $userQuery->execute([$username]);
        $user = $userQuery->fetch();
        if (strlen($password) > 72 || !$user || $user['statusi'] !== 'Aktiv'
            || !password_verify($password, $user['fjalekalimi'])) {
            $pdo->prepare('INSERT INTO me_login_tentativa (username, ip, sukses) VALUES (?, ?, 0)')
                ->execute([$username, $ip]);
            api_error(401, 'INVALID_CREDENTIALS', 'Username or password is incorrect.');
        }

        $smtpHost = app_env('SMTP_HOST');
        $smtpUser = app_env('SMTP_USER');
        $smtpPassword = app_env('SMTP_PASSWORD');
        $smtpFrom = app_env('SMTP_FROM', $smtpUser);
        $smtpPort = (int) app_env('SMTP_PORT', '587');
        $recipient = (string) $pdo->query('SELECT otp_email FROM me_cilesimet WHERE id=1')->fetchColumn();
        $autoload = dirname(__DIR__) . '/vendor/autoload.php';
        if ($smtpHost === '' || $smtpUser === '' || $smtpPassword === '' || $recipient === '' || !is_file($autoload)) {
            api_error(503, 'OTP_SERVICE_UNAVAILABLE', 'OTP email service is not configured.');
        }

        session_regenerate_id(true);
        $pdo->prepare('INSERT INTO me_login_tentativa (username, ip, sukses) VALUES (?, ?, 0)')
            ->execute([$username, $ip]);
        $attemptId = (int) $pdo->lastInsertId();
        $code = (string) random_int(100000, 999999);
        $pdo->prepare('UPDATE me_otp_kodet SET perdorur=1 WHERE perdorues_id=? AND perdorur=0')
            ->execute([$user['id']]);
        $pdo->prepare(
            'INSERT INTO me_otp_kodet (perdorues_id, kodi_hash, skadon_me, ip)
             VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 5 MINUTE), ?)'
        )->execute([$user['id'], password_hash($code, PASSWORD_DEFAULT), $ip]);

        require_once $autoload;
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $smtpHost;
            $mail->SMTPAuth = true;
            $mail->Username = $smtpUser;
            $mail->Password = $smtpPassword;
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $smtpPort;
            $mail->setFrom($smtpFrom, 'HerbaBloom');
            $mail->addAddress($recipient);
            $mail->Subject = 'Kodi i verifikimit HerbaBloom';
            $mail->Body = "Kodi juaj i hyrjes është {$code}. Skadon pas 5 minutash.";
            $mail->send();
        } catch (PHPMailer\PHPMailer\Exception $exception) {
            error_log('HerbaBloom API OTP email failed: ' . $exception->getMessage());
            $pdo->prepare('UPDATE me_otp_kodet SET perdorur=1 WHERE perdorues_id=? AND perdorur=0')
                ->execute([$user['id']]);
            api_error(503, 'OTP_DELIVERY_FAILED', 'OTP could not be delivered. Try again later.');
        }
        $_SESSION['pending_auth_user_id'] = (int) $user['id'];
        $_SESSION['pending_attempt_id'] = $attemptId;
        api_response(202, ['success' => true, 'status' => 'OTP_REQUIRED', 'message' => 'OTP was sent.']);
    }

    if ($method === 'POST' && $path === '/auth/verify-otp') {
        api_csrf();
        $input = api_input();
        $code = api_string_field($input, 'otp_code');
        if (!preg_match('/^\d{6}$/D', $code)) {
            api_error(422, 'VALIDATION_ERROR', 'otp_code must contain six digits.');
        }
        if (!isset($_SESSION['pending_auth_user_id'])) {
            api_error(401, 'OTP_SESSION_REQUIRED', 'Submit valid credentials before verifying OTP.');
        }
        $userQuery = $pdo->prepare(
            "SELECT id, emri, username, rooli, statusi, duhet_ndryshim_fjalekalimi
             FROM me_perdoruesit WHERE id=? LIMIT 1"
        );
        $userQuery->execute([$_SESSION['pending_auth_user_id']]);
        $user = $userQuery->fetch();
        $otpQuery = $pdo->prepare(
            'SELECT id, kodi_hash, skadon_me, tentativa FROM me_otp_kodet
             WHERE perdorues_id=? AND perdorur=0 ORDER BY id DESC LIMIT 1'
        );
        $otpQuery->execute([$_SESSION['pending_auth_user_id']]);
        $otp = $otpQuery->fetch();
        if (!$user || $user['statusi'] !== 'Aktiv' || !$otp
            || (int) $otp['tentativa'] >= 5 || strtotime((string) $otp['skadon_me']) <= time()
            || !password_verify($code, $otp['kodi_hash'])) {
            if ($otp && (int) $otp['tentativa'] < 5) {
                $pdo->prepare('UPDATE me_otp_kodet SET tentativa=tentativa+1 WHERE id=?')->execute([$otp['id']]);
            }
            $pdo->prepare('INSERT INTO me_login_tentativa (username, ip, sukses) VALUES (?, ?, 0)')
                ->execute([$user['username'] ?? 'unknown', $ip]);
            api_error(401, 'INVALID_OR_EXPIRED_OTP', 'OTP is invalid, expired, or has reached its attempt limit.');
        }
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE me_otp_kodet SET perdorur=1 WHERE id=?')->execute([$otp['id']]);
        $pdo->prepare('UPDATE me_perdoruesit SET hyrja_e_fundit=NOW() WHERE id=?')->execute([$user['id']]);
        if (isset($_SESSION['pending_attempt_id'])) {
            $pdo->prepare('UPDATE me_login_tentativa SET sukses=1 WHERE id=? AND username=? AND ip=?')
                ->execute([$_SESSION['pending_attempt_id'], $user['username'], $ip]);
        }
        $pdo->prepare(
            'INSERT INTO me_audit_log (perdorues_id, perdoruesi, veprimi, ip) VALUES (?, ?, ?, ?)'
        )->execute([$user['id'], substr($user['username'], 0, 50), 'Hyrje e suksesshme përmes REST API', $ip]);
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['name'] = $user['emri'];
        $_SESSION['role'] = $user['rooli'];
        $_SESSION['last_activity'] = time();
        unset($_SESSION['pending_auth_user_id'], $_SESSION['pending_attempt_id']);
        $pdo->commit();
        api_response(200, [
            'success' => true,
            'user' => ['id' => (int) $user['id'], 'username' => $user['username'], 'name' => $user['emri'], 'role' => $user['rooli']],
            'must_change_password' => (int) $user['duhet_ndryshim_fjalekalimi'] === 1,
        ]);
    }

    if ($method === 'POST' && $path === '/auth/logout') {
        api_csrf();
        api_user($pdo);
        $_SESSION = [];
        session_regenerate_id(true);
        api_response(200, ['success' => true, 'message' => 'Signed out.', 'csrf_token' => csrf_token()]);
    }

    if ($method === 'POST' && $path === '/auth/change-password') {
        api_csrf();
        $user = api_user($pdo, [], false);
        $input = api_input();
        $currentPassword = api_string_field($input, 'current_password');
        $newPassword = api_string_field($input, 'new_password');
        $confirmPassword = api_string_field($input, 'confirm_password');
        $passwordQuery = $pdo->prepare('SELECT fjalekalimi FROM me_perdoruesit WHERE id=?');
        $passwordQuery->execute([$user['id']]);
        $oldHash = $passwordQuery->fetchColumn();
        if (!is_string($oldHash) || !password_verify($currentPassword, $oldHash)
            || strlen($newPassword) < 12 || strlen($newPassword) > 72
            || !hash_equals($newPassword, $confirmPassword) || password_verify($newPassword, $oldHash)) {
            api_error(422, 'VALIDATION_ERROR', 'Current password or new password does not meet the requirements.');
        }
        $pdo->prepare(
            'UPDATE me_perdoruesit SET fjalekalimi=?, duhet_ndryshim_fjalekalimi=0 WHERE id=?'
        )->execute([password_hash($newPassword, PASSWORD_DEFAULT), $user['id']]);
        audit_log($pdo, 'Ndryshoi fjalëkalimin përmes REST API');
        api_response(200, ['success' => true, 'message' => 'Password changed successfully.']);
    }

    if ($method === 'GET' && $path === '/products') {
        $search = trim(api_string_field($_GET, 'q'));
        $category = trim(api_string_field($_GET, 'category'));
        $page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
        $pageSize = filter_var($_GET['page_size'] ?? 20, FILTER_VALIDATE_INT);
        if ($page === false || $page < 1 || $pageSize === false || $pageSize < 1 || $pageSize > 100) {
            api_error(422, 'VALIDATION_ERROR', 'page must be positive and page_size must be between 1 and 100.');
        }
        $conditions = ["p.aktiv=1", "p.i_dukshem_ne_web=1", "(c.id IS NULL OR c.statusi='Aktiv')"];
        $parameters = [];
        if ($search !== '') {
            $conditions[] = '(p.emri LIKE :name OR p.marka LIKE :brand OR p.pershkrimi LIKE :description)';
            $pattern = '%' . $search . '%';
            $parameters += ['name' => $pattern, 'brand' => $pattern, 'description' => $pattern];
        }
        if ($category !== '') {
            $conditions[] = 'c.slug=:category';
            $parameters['category'] = $category;
        }
        $where = implode(' AND ', $conditions);
        $countQuery = $pdo->prepare(
            "SELECT COUNT(*) FROM me_produktet p LEFT JOIN me_kategorite c ON c.id=p.kategoria_id WHERE {$where}"
        );
        $countQuery->execute($parameters);
        $total = (int) $countQuery->fetchColumn();
        $offset = ($page - 1) * $pageSize;
        $productsQuery = $pdo->prepare(
            "SELECT p.id,p.sku,p.slug,p.emri,p.marka,p.pershkrimi,p.cmimi,p.imazhi,
                    c.emri AS kategoria,ps.stoku_aktual,ps.statusi_stokut
             FROM me_produktet p JOIN me_produktet_stoku ps ON ps.id=p.id
             LEFT JOIN me_kategorite c ON c.id=p.kategoria_id
             WHERE {$where} ORDER BY p.emri LIMIT {$pageSize} OFFSET {$offset}"
        );
        $productsQuery->execute($parameters);
        $products = array_map(static fn(array $product): array => [
            'id' => (int) $product['id'],
            'sku' => $product['sku'],
            'slug' => $product['slug'],
            'name' => $product['emri'],
            'brand' => $product['marka'],
            'description' => $product['pershkrimi'],
            'price' => (float) $product['cmimi'],
            'image' => $product['imazhi'],
            'category' => $product['kategoria'],
            'stock' => (int) $product['stoku_aktual'],
            'stock_status' => $product['statusi_stokut'],
        ], $productsQuery->fetchAll());
        api_response(200, [
            'success' => true,
            'data' => $products,
            'pagination' => ['page' => $page, 'page_size' => $pageSize, 'total' => $total],
        ]);
    }

    if ($method === 'GET' && preg_match('~^/orders/track/([A-Za-z0-9]{1,20})$~D', $path, $match)) {
        $query = $pdo->prepare(
            'SELECT id,kodi_gjurmimit,klienti,qyteti,statusi,totali,tarifa_dorezimit,data_porosise,data_dorezimit,arsyeja_anullimit
             FROM me_porosite_online WHERE kodi_gjurmimit=? LIMIT 1'
        );
        $query->execute([strtoupper($match[1])]);
        $order = $query->fetch();
        if (!$order) {
            api_error(404, 'ORDER_NOT_FOUND', 'Order does not exist.');
        }
        $itemsQuery = $pdo->prepare(
            'SELECT emri_produktit,sasia FROM me_porosi_items WHERE porosia_id=? ORDER BY id'
        );
        $itemsQuery->execute([$order['id']]);
        api_response(200, [
            'success' => true,
            'data' => [
                'tracking_code' => $order['kodi_gjurmimit'],
                'customer' => $order['klienti'],
                'city' => $order['qyteti'],
                'status' => $order['statusi'],
                'items' => array_map(static fn(array $item): array => [
                    'product_name' => $item['emri_produktit'],
                    'quantity' => (int) $item['sasia'],
                ], $itemsQuery->fetchAll()),
                'total' => (float) $order['totali'],
                'delivery_fee' => (float) $order['tarifa_dorezimit'],
                'created_at' => $order['data_porosise'],
                'delivered_at' => $order['data_dorezimit'],
                'cancellation_reason' => $order['arsyeja_anullimit'],
            ],
        ]);
    }

    if ($method === 'GET' && $path === '/orders') {
        $user = api_user($pdo, ['admin', 'farmacist', 'kurier']);
        $status = trim(api_string_field($_GET, 'status'));
        $allowedStatuses = ['Në pritje', 'Në përgatitje', 'Në rrugë', 'Dorëzuar', 'Anuluar'];
        if ($status !== '' && !in_array($status, $allowedStatuses, true)) {
            api_error(422, 'VALIDATION_ERROR', 'status is not recognized.');
        }
        $conditions = [];
        $parameters = [];
        if ($status !== '') {
            $conditions[] = 'o.statusi=:status';
            $parameters['status'] = $status;
        }
        if ($user['rooli'] === 'kurier') {
            $conditions[] = 'o.kurier_id=:courier';
            $parameters['courier'] = $user['id'];
        }
        $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);
        $financialColumns = $user['rooli'] === 'admin'
            ? 'o.nentotali,o.tarifa_dorezimit,o.totali,'
            : '';
        $query = $pdo->prepare(
            "SELECT o.id,o.kodi_gjurmimit,o.burimi,o.klienti,o.telefoni,o.qyteti,o.adresa,
                    {$financialColumns}o.statusi,o.kurier_id,o.data_porosise,o.data_dorezimit
             FROM me_porosite_online o {$where} ORDER BY o.data_porosise DESC LIMIT 200"
        );
        $query->execute($parameters);
        $orders = array_map(static fn(array $order): array => api_order_payload($pdo, $order, $user), $query->fetchAll());
        api_response(200, ['success' => true, 'data' => $orders]);
    }

    if ($method === 'POST' && $path === '/orders') {
        api_csrf();
        $user = api_user($pdo, ['admin', 'farmacist']);
        $input = api_input();
        $customer = trim(api_string_field($input, 'customer'));
        $email = trim(api_string_field($input, 'email'));
        $phone = trim(api_string_field($input, 'phone'));
        $city = trim(api_string_field($input, 'city'));
        $address = trim(api_string_field($input, 'address'));
        $notes = trim(api_string_field($input, 'notes'));
        $items = $input['items'] ?? null;
        if ($customer === '' || strlen($customer) > 100 || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))
            || strlen($email) > 100 || $phone === '' || strlen($phone) > 30 || $city === ''
            || strlen($city) > 50 || $address === '' || strlen($address) > 255 || strlen($notes) > 1000
            || !is_array($items) || $items === [] || count($items) > 50) {
            api_error(422, 'VALIDATION_ERROR', 'Valid customer details and between 1 and 50 order items are required.');
        }
        $lineItems = [];
        foreach ($items as $item) {
            if (!is_array($item) || !isset($item['product_id'], $item['quantity'])
                || !filter_var($item['product_id'], FILTER_VALIDATE_INT)
                || !filter_var($item['quantity'], FILTER_VALIDATE_INT)
                || (int) $item['product_id'] < 1 || (int) $item['quantity'] < 1 || (int) $item['quantity'] > 999) {
                api_error(422, 'VALIDATION_ERROR', 'Each item requires a positive product_id and quantity.');
            }
            $productId = (int) $item['product_id'];
            $lineItems[$productId] = ($lineItems[$productId] ?? 0) + (int) $item['quantity'];
        }

        try {
            $pdo->beginTransaction();
            $productIds = array_keys($lineItems);
            sort($productIds, SORT_NUMERIC);
            $placeholders = implode(',', array_fill(0, count($productIds), '?'));
            $productQuery = $pdo->prepare(
                "SELECT id,emri,cmimi FROM me_produktet WHERE aktiv=1 AND id IN ({$placeholders}) ORDER BY id FOR UPDATE"
            );
            $productQuery->execute($productIds);
            $products = [];
            foreach ($productQuery->fetchAll() as $product) {
                $products[(int) $product['id']] = $product;
            }
            if (count($products) !== count($lineItems)) {
                $pdo->rollBack();
                api_error(404, 'PRODUCT_NOT_FOUND', 'One or more products do not exist or are inactive.');
            }
            $subtotal = 0.0;
            foreach ($lineItems as $productId => $quantity) {
                $subtotal += (float) $products[$productId]['cmimi'] * $quantity;
            }
            $settings = $pdo->query('SELECT tarifa_dorezimit,dergesa_falas_mbi FROM me_cilesimet WHERE id=1')->fetch();
            if (!$settings) {
                throw new RuntimeException('Store settings row is missing.');
            }
            $shipping = $settings['dergesa_falas_mbi'] !== null
                && $subtotal >= (float) $settings['dergesa_falas_mbi']
                ? 0.0 : (float) $settings['tarifa_dorezimit'];
            $trackingCode = generate_tracking_code($pdo);
            $pdo->prepare(
                "INSERT INTO me_porosite_online
                 (kodi_gjurmimit,burimi,klienti,email,telefoni,qyteti,adresa,shenime,nentotali,tarifa_dorezimit,totali)
                 VALUES (?,'manual',?,?,?,?,?,?,?,?,?)"
            )->execute([
                $trackingCode,$customer,$email ?: null,$phone,$city,$address,$notes ?: null,
                $subtotal,$shipping,$subtotal+$shipping,
            ]);
            $orderId = (int) $pdo->lastInsertId();
            $insertItem = $pdo->prepare(
                'INSERT INTO me_porosi_items (porosia_id,produkt_id,emri_produktit,cmimi,sasia) VALUES (?,?,?,?,?)'
            );
            foreach ($lineItems as $productId => $quantity) {
                $insertItem->execute([$orderId,$productId,$products[$productId]['emri'],$products[$productId]['cmimi'],$quantity]);
            }
            $pdo->prepare(
                'INSERT INTO me_porosi_statuset (porosia_id,statusi,nga_user_id,shenim) VALUES (?,?,?,?)'
            )->execute([$orderId,'Në pritje',$user['id'],'Porosi manuale përmes REST API']);
            foreach ($lineItems as $productId => $quantity) {
                reserve_stock($pdo,$productId,$quantity,$orderId,(int)$user['id']);
            }
            audit_log($pdo, "Krijoi porosinë API {$trackingCode}");
            $pdo->commit();
            $result = ['id'=>$orderId,'tracking_code'=>$trackingCode,'status'=>'Në pritje'];
            if ($user['rooli'] === 'admin') {
                $result['subtotal'] = $subtotal;
                $result['delivery_fee'] = $shipping;
                $result['total'] = $subtotal + $shipping;
            }
            api_response(201, ['success'=>true,'data'=>$result]);
        } catch (DomainException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            api_error(409, 'INSUFFICIENT_STOCK', $exception->getMessage());
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    if (($method === 'PATCH' || $method === 'DELETE') && preg_match('~^/orders/([1-9]\d*)$~D', $path, $match)) {
        api_csrf();
        $user = api_user($pdo, ['admin', 'farmacist', 'kurier']);
        $orderId = (int) $match[1];
        $input = api_input();
        $hasStatus = $method === 'DELETE' || array_key_exists('status', $input);
        $newStatus = $method === 'DELETE' ? 'Anuluar' : api_string_field($input, 'status');
        $reason = trim(api_string_field($input, 'cancellation_reason'));
        $courierId = $method === 'PATCH' && array_key_exists('courier_id', $input)
            ? filter_var($input['courier_id'], FILTER_VALIDATE_INT) : null;
        if ((!$hasStatus && $courierId === null)
            || ($hasStatus && !in_array($newStatus, ['Në përgatitje','Në rrugë','Dorëzuar','Anuluar'], true))
            || ($newStatus === 'Anuluar' && ($reason === '' || strlen($reason) > 255))
            || ($method === 'PATCH' && array_key_exists('courier_id', $input) && (!$courierId || $courierId < 1))) {
            api_error(422, 'VALIDATION_ERROR', 'Provide a valid status, cancellation reason, or courier_id.');
        }
        try {
            $pdo->beginTransaction();
            $orderQuery = $pdo->prepare(
                'SELECT id,statusi,kurier_id FROM me_porosite_online WHERE id=? FOR UPDATE'
            );
            $orderQuery->execute([$orderId]);
            $order = $orderQuery->fetch();
            if (!$order) {
                $pdo->rollBack();
                api_error(404, 'ORDER_NOT_FOUND', 'Order does not exist.');
            }
            api_order_access($order, $user);
            $newCourierId = $order['kurier_id'];
            if ($courierId !== null) {
                if (!in_array($user['rooli'], ['admin','farmacist'], true)) {
                    $pdo->rollBack();
                    api_error(403, 'FORBIDDEN', 'Only admin and pharmacist can assign couriers.');
                }
                $courierQuery = $pdo->prepare(
                    "SELECT id FROM me_perdoruesit WHERE id=? AND rooli='kurier' AND statusi='Aktiv'"
                );
                $courierQuery->execute([$courierId]);
                if (!$courierQuery->fetchColumn()) {
                    $pdo->rollBack();
                    api_error(404, 'COURIER_NOT_FOUND', 'Active courier does not exist.');
                }
                $newCourierId = $courierId;
            }
            $oldStatus = $order['statusi'];
            if (!$hasStatus) {
                $newStatus = $oldStatus;
            }
            $transitions = [
                'Në pritje' => ['Në përgatitje','Anuluar'],
                'Në përgatitje' => ['Në rrugë','Anuluar'],
                'Në rrugë' => ['Dorëzuar','Anuluar'],
            ];
            if (($hasStatus && !in_array($newStatus, $transitions[$oldStatus] ?? [], true))
                || (!$hasStatus && in_array($oldStatus, ['Dorëzuar','Anuluar'], true))
                || ($newStatus === 'Në rrugë' && $newCourierId === null)
                || ($user['rooli'] === 'kurier'
                    && ((int) $order['kurier_id'] !== (int) $user['id']
                        || !in_array($oldStatus, ['Në përgatitje','Në rrugë'], true)
                        || !in_array($newStatus, ['Në rrugë','Dorëzuar','Anuluar'], true)))) {
                $pdo->rollBack();
                api_error(409, 'INVALID_STATUS_TRANSITION', 'This order cannot transition to the requested status.');
            }
            if ($newStatus === 'Anuluar') {
                restore_order_stock($pdo, $orderId, (int) $user['id']);
            }
            $cancelledBy = $newStatus === 'Anuluar' ? ($user['rooli'] === 'kurier' ? 'kurieri' : 'stafi') : null;
            $deliveredAt = $newStatus === 'Dorëzuar' ? date('Y-m-d H:i:s') : null;
            $pdo->prepare(
                'UPDATE me_porosite_online SET statusi=?,kurier_id=?,data_dorezimit=?,
                 anuluar_nga=?,arsyeja_anullimit=? WHERE id=?'
            )->execute([
                $newStatus,$newCourierId,$deliveredAt,$cancelledBy,
                $newStatus === 'Anuluar' ? $reason : null,$orderId,
            ]);
            $pdo->prepare(
                'INSERT INTO me_porosi_statuset (porosia_id,statusi,nga_user_id,shenim) VALUES (?,?,?,?)'
            )->execute([$orderId,$newStatus,$user['id'],$newStatus === 'Anuluar' ? $reason : ($courierId !== null ? 'U caktua kurieri' : null)]);
            audit_log($pdo, "Përditësoi porosinë API #{$orderId} në {$newStatus}");
            $pdo->commit();
            api_response(200, ['success'=>true,'data'=>['id'=>$orderId,'status'=>$newStatus,'courier_id'=>$newCourierId]]);
        } catch (DomainException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            api_error(409, 'STOCK_RESTORATION_FAILED', $exception->getMessage());
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    if ($method === 'OPTIONS') {
        header('Allow: GET, POST, PATCH, DELETE, OPTIONS');
        api_response(204);
    }
    api_error(404, 'ENDPOINT_NOT_FOUND', 'API endpoint does not exist.');
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('HerbaBloom API error: ' . $exception->getMessage());
    api_error(500, 'INTERNAL_SERVER_ERROR', 'An unexpected server error occurred.');
}
