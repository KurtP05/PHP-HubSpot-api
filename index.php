<?php

if (!file_exists(__DIR__ . '/config.php')) {
    exit('Missing config.php — copy config.example.php to config.php and paste in your HubSpot service key.');
}

require __DIR__ . '/config.php';
require __DIR__ . '/hubspot.php';

session_start();

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function friendly_error(RuntimeException $e): string
{
    switch ($e->getCode()) {
        case 401: return 'HubSpot rejected your key. Check the service key in config.php.';
        case 403: return 'Your service key is missing a scope. Add crm.objects.contacts.read and crm.objects.contacts.write in HubSpot.';
        case 409: return 'A contact with that email already exists in HubSpot.';
        case 429: return 'Too many requests to HubSpot. Wait a few seconds and try again.';
        default:  return $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $properties = [
        'firstname' => trim($_POST['firstname'] ?? ''),
        'lastname'  => trim($_POST['lastname'] ?? ''),
        'email'     => trim($_POST['email'] ?? ''),
        'phone'     => trim($_POST['phone'] ?? ''),
    ];
    $properties = array_filter($properties, fn($value) => $value !== '');

    if (!filter_var($properties['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
        $_SESSION['flash'] = ['error', 'Please enter a valid email address.'];
    } else {
        try {
            create_contact($properties);
            $_SESSION['flash'] = ['success', "Added {$properties['email']} to HubSpot."];
        } catch (RuntimeException $e) {
            $_SESSION['flash'] = ['error', friendly_error($e)];
        }
    }

    header('Location: index.php');
    exit;
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$search = trim($_GET['q'] ?? '');

$contacts = [];
$loadError = null;
try {
    $contacts = $search !== '' ? search_contacts($search) : get_contacts();
} catch (RuntimeException $e) {
    $loadError = friendly_error($e);
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>HubSpot Contacts</title>
    <style>
        :root { --accent: #ff7a59; --ink: #1f2933; --muted: #6b7785; --line: #e3e8ee; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; color: var(--ink); background: #f6f8fa; }
        main { max-width: 900px; margin: 40px auto; padding: 0 16px; }
        h1 { margin: 0 0 4px; font-size: 1.6rem; }
        .sub { margin: 0 0 24px; color: var(--muted); }
        .card { background: #fff; border: 1px solid var(--line); border-radius: 10px; padding: 20px; margin-bottom: 20px; }
        h2 { margin: 0 0 14px; font-size: 1.1rem; }
        form { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 10px; align-items: end; }
        label { display: flex; flex-direction: column; gap: 4px; font-size: .85rem; color: var(--muted); }
        input { padding: 8px 10px; border: 1px solid var(--line); border-radius: 6px; font: inherit; color: var(--ink); }
        button { padding: 9px 14px; border: 0; border-radius: 6px; background: var(--accent); color: #fff; font: inherit; font-weight: 600; cursor: pointer; }
        button:hover { filter: brightness(.95); }
        form.search { grid-template-columns: 1fr auto; margin-bottom: 14px; }
        input[type="search"] { appearance: none; }
        .flash { padding: 10px 14px; border-radius: 6px; margin-bottom: 20px; }
        .flash.success { background: #e7f6ec; color: #1e6b3a; }
        .flash.error { background: #fdecea; color: #a12b1f; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 10px 8px; border-bottom: 1px solid var(--line); }
        th { font-size: .8rem; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); }
        tr:last-child td { border-bottom: 0; }
        .empty { color: var(--muted); }
    </style>
</head>
<body>
<main>
    <h1>HubSpot Contacts</h1>
    <p class="sub">Contacts pulled live from your HubSpot account through the CRM API.</p>

    <?php if ($flash): ?>
        <div class="flash <?= e($flash[0]) ?>"><?= e($flash[1]) ?></div>
    <?php endif; ?>

    <section class="card">
        <h2>Add a contact</h2>
        <form method="post">
            <label>First name <input name="firstname" autocomplete="given-name"></label>
            <label>Last name <input name="lastname" autocomplete="family-name"></label>
            <label>Email <input name="email" type="email" required autocomplete="email"></label>
            <label>Phone <input name="phone" autocomplete="tel"></label>
            <button type="submit">Add to HubSpot</button>
        </form>
    </section>

    <section class="card">
        <h2>Contacts (<?= count($contacts) ?>)</h2>

        <form method="get" class="search">
            <label>Search by name or email <input name="q" type="search" value="<?= e($search) ?>"></label>
            <button type="submit">Search</button>
        </form>

        <?php if ($loadError): ?>
            <div class="flash error"><?= e($loadError) ?></div>
        <?php elseif (!$contacts && $search !== ''): ?>
            <p class="empty">No contacts match "<?= e($search) ?>".</p>
        <?php elseif (!$contacts): ?>
            <p class="empty">No contacts yet. Add one above.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr><th>Name</th><th>Email</th><th>Phone</th></tr>
                </thead>
                <tbody>
                <?php foreach ($contacts as $contact):
                    $p = $contact['properties'] ?? [];
                    $name = trim(($p['firstname'] ?? '') . ' ' . ($p['lastname'] ?? ''));
                ?>
                    <tr>
                        <td><?= e($name !== '' ? $name : '—') ?></td>
                        <td><?= e($p['email'] ?? '') ?></td>
                        <td><?= e($p['phone'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
