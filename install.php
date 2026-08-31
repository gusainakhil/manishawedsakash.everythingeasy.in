<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Wedding Website Installer
|--------------------------------------------------------------------------
| The database must already exist in your hosting control panel.
| This installer creates tables, settings, events and the administrator.
*/

$configPath = __DIR__ . '/config.php';
$schemaPath = __DIR__ . '/schema.sql';

$error = '';
$success = false;

/*
|--------------------------------------------------------------------------
| Prevent accidental reinstallation
|--------------------------------------------------------------------------
*/

if (is_file($configPath) && !isset($_GET['force'])) {
    http_response_code(403);

    exit(
        'The website is already configured. ' .
        'Delete or rename config.php before running the installer again.'
    );
}

/*
|--------------------------------------------------------------------------
| Installation
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host = trim((string) ($_POST['host'] ?? 'localhost'));
    $port = (int) ($_POST['port'] ?? 3306);

    // Dots are allowed because your hosting database name contains dots.
    $database = trim((string) ($_POST['database'] ?? ''));
    $databaseUser = trim((string) ($_POST['database_user'] ?? ''));
    $databasePassword = (string) ($_POST['database_password'] ?? '');

    $baseUrl = rtrim(
        trim((string) ($_POST['base_url'] ?? '')),
        '/'
    );

    $adminName = trim((string) ($_POST['admin_name'] ?? 'Administrator'));
    $adminEmail = filter_var(
        trim((string) ($_POST['admin_email'] ?? '')),
        FILTER_VALIDATE_EMAIL
    );

    $adminPassword = (string) ($_POST['admin_password'] ?? '');

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if ($host === '') {
        $error = 'Database host is required.';
    } elseif ($database === '') {
        $error = 'Database name is required.';
    } elseif ($databaseUser === '') {
        $error = 'Database username is required.';
    } elseif ($baseUrl === '' || !filter_var($baseUrl, FILTER_VALIDATE_URL)) {
        $error = 'Enter a valid website URL.';
    } elseif (!str_starts_with($baseUrl, 'https://')) {
        $error = 'The website URL must start with https://';
    } elseif (!$adminEmail) {
        $error = 'Enter a valid administrator email address.';
    } elseif (strlen($adminPassword) < 8) {
        $error = 'Administrator password must contain at least 8 characters.';
    } elseif (!is_file($schemaPath)) {
        $error = 'schema.sql was not found in the website folder.';
    }

    if ($error === '') {
        try {
            /*
            |--------------------------------------------------------------------------
            | Connect to existing database
            |--------------------------------------------------------------------------
            */

            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $host,
                $port,
                $database
            );

            $pdo = new PDO(
                $dsn,
                $databaseUser,
                $databasePassword,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | Create database tables
            |--------------------------------------------------------------------------
            */

            $schema = file_get_contents($schemaPath);

            if ($schema === false || trim($schema) === '') {
                throw new RuntimeException(
                    'schema.sql is empty or cannot be read.'
                );
            }

            // Execute each SQL statement separately for cPanel compatibility.
            $statements = array_filter(
                array_map(
                    'trim',
                    preg_split('/;\s*(?:\r?\n|$)/', $schema)
                )
            );

            foreach ($statements as $statement) {
                if ($statement !== '') {
                    $pdo->exec($statement);
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Create or update administrator
            |--------------------------------------------------------------------------
            */

            $adminStatement = $pdo->prepare(
                'INSERT INTO admins
                    (name, email, password, created_at, updated_at)
                 VALUES
                    (:name, :email, :password, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE
                    name = VALUES(name),
                    password = VALUES(password),
                    updated_at = NOW()'
            );

            $adminStatement->execute([
                'name' => $adminName,
                'email' => $adminEmail,
                'password' => password_hash(
                    $adminPassword,
                    PASSWORD_DEFAULT
                ),
            ]);

            /*
            |--------------------------------------------------------------------------
            | Wedding settings
            |--------------------------------------------------------------------------
            */

            $settings = [
                'bride_name' => 'मनीषा',
                'groom_name' => 'आकाश',
                'wedding_date' => '2026-10-18 19:00:00',
                'venue_name' => 'मंगलम् पैलेस',

                'venue_address' =>
                    'मवाकोट रोड, निम्बूचौड़, कोटद्वार, पौड़ी गढ़वाल',

                'map_url' =>
                    'https://www.google.com/maps/search/?api=1&query=' .
                    rawurlencode(
                        'Mangalam Palace Mawakot Road Kotdwar'
                    ),

                'family_message' =>
                    'परमपिता परमेश्वर की असीम अनुकम्पा एवं पितरों के ' .
                    'आशीर्वाद से इस मंगल बेला पर आपकी गरिमामयी उपस्थिति ' .
                    'सादर प्रार्थनीय है।',

                'bride_details' =>
                    'सुपुत्री श्रीमती शोभा गुसाईं एवं श्री रमेश सिंह ' .
                    'गुसाईं • ग्राम चमस्यूँ, पौड़ी गढ़वाल',

                'groom_details' =>
                    'सुपुत्र श्रीमती सरोजनी देवी एवं श्री दलीप सिंह ' .
                    'रावत • मवाकोट, कोटद्वार, पौड़ी गढ़वाल',
            ];

            $settingStatement = $pdo->prepare(
                'INSERT INTO settings
                    (setting_key, setting_value)
                 VALUES
                    (:setting_key, :setting_value)
                 ON DUPLICATE KEY UPDATE
                    setting_value = VALUES(setting_value)'
            );

            foreach ($settings as $settingKey => $settingValue) {
                $settingStatement->execute([
                    'setting_key' => $settingKey,
                    'setting_value' => $settingValue,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Insert wedding events when table is empty
            |--------------------------------------------------------------------------
            */

            $eventCount = (int) $pdo
                ->query('SELECT COUNT(*) FROM wedding_events')
                ->fetchColumn();

            if ($eventCount === 0) {
                $eventStatement = $pdo->prepare(
                    'INSERT INTO wedding_events
                        (
                            event_name,
                            event_date,
                            event_time,
                            venue,
                            address,
                            description,
                            map_url,
                            sort_order
                        )
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                );

                $mainVenue = 'मंगलम् पैलेस';

                $mainAddress =
                    'मवाकोट रोड, निम्बूचौड़, कोटद्वार, पौड़ी गढ़वाल';

                $mapUrl = $settings['map_url'];

                $events = [
                    [
                        'महिला संगीत',
                        '2026-10-16',
                        'दोपहर 2:00 बजे',
                        'निवास स्थान',
                        'बालावाला, देहरादून',
                        'संगीत और उल्लास के साथ उत्सव का शुभारंभ',
                        '',
                        1,
                    ],
                    [
                        'मेहंदी',
                        '2026-10-17',
                        'शाम 5:00 बजे',
                        'निवास स्थान',
                        'बालावाला, देहरादून',
                        'मेहंदी की रस्म',
                        '',
                        2,
                    ],
                    [
                        'अतिथि सत्कार (न्यूतेर)',
                        '2026-10-17',
                        'हर पल',
                        $mainVenue,
                        $mainAddress,
                        'अतिथियों का आत्मीय स्वागत',
                        $mapUrl,
                        3,
                    ],
                    [
                        'प्रीतिभोज',
                        '2026-10-17',
                        'रात्रि 8:00 बजे',
                        $mainVenue,
                        $mainAddress,
                        'परिवार संग प्रीतिभोज',
                        $mapUrl,
                        4,
                    ],
                    [
                        'हल्दी हाथ',
                        '2026-10-18',
                        'प्रातः 10:00 बजे',
                        $mainVenue,
                        $mainAddress,
                        'हल्दी की मंगल रस्म',
                        $mapUrl,
                        5,
                    ],
                    [
                        'मंगल स्नान',
                        '2026-10-18',
                        'प्रातः 11:00 बजे',
                        $mainVenue,
                        $mainAddress,
                        'मंगल स्नान',
                        $mapUrl,
                        6,
                    ],
                    [
                        'बारात स्वागत',
                        '2026-10-18',
                        'रात्रि 7:00 बजे',
                        $mainVenue,
                        $mainAddress,
                        'बारात का स्नेहपूर्ण स्वागत',
                        $mapUrl,
                        7,
                    ],
                    [
                        'प्रीतिभोज',
                        '2026-10-18',
                        'रात्रि 8:00 बजे',
                        $mainVenue,
                        $mainAddress,
                        'विवाह प्रीतिभोज',
                        $mapUrl,
                        8,
                    ],
                    [
                        'विवाह संस्कार',
                        '2026-10-18',
                        'शुभ लग्नानुसार',
                        $mainVenue,
                        $mainAddress,
                        'पावन परिणय सूत्र बंधन',
                        $mapUrl,
                        9,
                    ],
                    [
                        'विदाई (भीगी पलकें)',
                        '2026-10-19',
                        'तारों की छांव में',
                        $mainVenue,
                        $mainAddress,
                        'स्नेहपूर्ण विदाई',
                        $mapUrl,
                        10,
                    ],
                ];

                foreach ($events as $event) {
                    $eventStatement->execute($event);
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Create config.php
            |--------------------------------------------------------------------------
            */

            $configuration = [
                'app_name' => 'Manisha & Akash Wedding',
                'base_url' => $baseUrl,
                'timezone' => 'Asia/Kolkata',
                'db' => [
                    'host' => $host,
                    'port' => $port,
                    'name' => $database,
                    'user' => $databaseUser,
                    'pass' => $databasePassword,
                    'charset' => 'utf8mb4',
                ],
            ];

            $configContents =
                "<?php\n" .
                "declare(strict_types=1);\n\n" .
                'return ' .
                var_export($configuration, true) .
                ";\n";

            if (
                file_put_contents(
                    $configPath,
                    $configContents,
                    LOCK_EX
                ) === false
            ) {
                throw new RuntimeException(
                    'Tables were created, but config.php could not be written. ' .
                    'Make the website folder writable and try again.'
                );
            }

            $success = true;
        } catch (PDOException $exception) {
            $error =
                'Database connection or installation failed: ' .
                $exception->getMessage();
        } catch (Throwable $exception) {
            $error = 'Installation failed: ' . $exception->getMessage();
        }
    }
}

function oldValue(string $key, string $default = ''): string
{
    return htmlspecialchars(
        (string) ($_POST[$key] ?? $default),
        ENT_QUOTES,
        'UTF-8'
    );
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>Manisha & Akash Wedding — Installation</title>

    <style>
        :root {
            --maroon: #6b1428;
            --deep-maroon: #3b0b19;
            --gold: #bf9552;
            --background: #f8f3ea;
            --white: #ffffff;
            --text: #332522;
            --muted: #74625e;
            --border: #e0d3c1;
            --success: #176b45;
            --success-bg: #e7f6ee;
            --error: #941f32;
            --error-bg: #fdebed;
        }

        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            margin: 0;
            padding: 24px;
            display: grid;
            place-items: center;
            background:
                radial-gradient(circle at top, #f1dfcc, transparent 50%),
                var(--background);
            color: var(--text);
            font-family: Arial, sans-serif;
        }

        .installer {
            width: min(620px, 100%);
            padding: 36px;
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 20px;
            box-shadow: 0 25px 70px rgba(59, 11, 25, 0.12);
        }

        .seal {
            width: 72px;
            height: 72px;
            margin: 0 auto 20px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            background: var(--maroon);
            color: #f1d39a;
            font-family: Georgia, serif;
            font-size: 20px;
        }

        h1 {
            margin: 0 0 8px;
            color: var(--maroon);
            text-align: center;
            font-family: Georgia, serif;
            font-size: 32px;
        }

        .description {
            margin: 0 0 28px;
            color: var(--muted);
            text-align: center;
            line-height: 1.6;
        }

        .form-grid {
            display: grid;
            gap: 17px;
        }

        .two-columns {
            display: grid;
            grid-template-columns: 1fr 140px;
            gap: 14px;
        }

        label {
            display: grid;
            gap: 7px;
            color: #55484b;
            font-size: 13px;
            font-weight: 700;
        }

        input {
            width: 100%;
            padding: 13px 14px;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: #fffdf9;
            font: inherit;
        }

        input:focus {
            outline: 2px solid rgba(191, 149, 82, 0.25);
            border-color: var(--gold);
        }

        hr {
            width: 100%;
            margin: 6px 0;
            border: 0;
            border-top: 1px solid var(--border);
        }

        button,
        .button {
            display: inline-flex;
            justify-content: center;
            width: 100%;
            padding: 14px 20px;
            border: 0;
            border-radius: 10px;
            background: var(--maroon);
            color: white;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }

        button:hover,
        .button:hover {
            background: var(--deep-maroon);
        }

        .message {
            margin-bottom: 20px;
            padding: 14px 16px;
            border-radius: 10px;
            line-height: 1.5;
        }

        .message.error {
            color: var(--error);
            background: var(--error-bg);
        }

        .message.success {
            color: var(--success);
            background: var(--success-bg);
        }

        .security-note {
            margin-top: 16px;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.6;
        }

        @media (max-width: 560px) {
            body {
                padding: 14px;
            }

            .installer {
                padding: 26px 18px;
            }

            .two-columns {
                grid-template-columns: 1fr;
            }

            h1 {
                font-size: 27px;
            }
        }
    </style>
</head>

<body>
<main class="installer">
    <div class="seal">म & आ</div>

    <h1>Wedding Website Installation</h1>

    <p class="description">
        Connect the website to your existing hosting database
        and create the administrator account.
    </p>

    <?php if ($success): ?>
        <div class="message success">
            <strong>Installation completed successfully.</strong><br>
            The database tables, wedding information and administrator
            account have been created.
        </div>

        <a class="button" href="admin/login.php">
            Open Admin Panel
        </a>

        <p class="security-note">
            Important: Delete <strong>install.php</strong> from your hosting
            after confirming that the administrator login works.
        </p>
    <?php else: ?>
        <?php if ($error !== ''): ?>
            <div class="message error">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form method="post" class="form-grid">
            <label>
                Website URL
                <input
                    type="url"
                    name="base_url"
                    required
                    value="<?= oldValue(
                        'base_url',
                        'https://manishawedsakash.everythingeasy.in'
                    ) ?>"
                >
            </label>

            <div class="two-columns">
                <label>
                    Database Host
                    <input
                        type="text"
                        name="host"
                        required
                        value="<?= oldValue('host', 'localhost') ?>"
                    >
                </label>

                <label>
                    Database Port
                    <input
                        type="number"
                        name="port"
                        required
                        value="<?= oldValue('port', '3306') ?>"
                    >
                </label>
            </div>

            <label>
                Existing Database Name
                <input
                    type="text"
                    name="database"
                    required
                    value="<?= oldValue(
                        'database',
                        'manishawedsakash.everythingeasy.in'
                    ) ?>"
                >
            </label>

            <label>
                Database Username
                <input
                    type="text"
                    name="database_user"
                    required
                    autocomplete="username"
                    value="<?= oldValue(
                        'database_user',
                        'manishawedsakash'
                    ) ?>"
                >
            </label>

            <label>
                Database Password
                <input
                    type="password"
                    name="database_password"
                    required
                    autocomplete="current-password"
                >
            </label>

            <hr>

            <label>
                Administrator Name
                <input
                    type="text"
                    name="admin_name"
                    required
                    value="<?= oldValue(
                        'admin_name',
                        'Administrator'
                    ) ?>"
                >
            </label>

            <label>
                Administrator Email
                <input
                    type="email"
                    name="admin_email"
                    required
                    value="<?= oldValue('admin_email') ?>"
                >
            </label>

            <label>
                Administrator Password
                <input
                    type="password"
                    name="admin_password"
                    minlength="8"
                    required
                    autocomplete="new-password"
                >
            </label>

            <button type="submit">
                Install Wedding Website
            </button>
        </form>

        <p class="security-note">
            The database must already exist. Ensure the database user is
            assigned to it with all privileges.
        </p>
    <?php endif; ?>
</main>
</body>
</html>