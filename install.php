<?php
declare(strict_types=1);
if (is_file(__DIR__ . '/config.php')) {
    http_response_code(403);
    exit('This website is already installed. Delete install.php and open the admin panel.');
}
$error = ''; $success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host = trim($_POST['host'] ?? 'localhost'); $port = (int)($_POST['port'] ?? 3306);
    $name = preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['database'] ?? 'wedding_invitation');
    $user = trim($_POST['user'] ?? ''); $pass = (string)($_POST['pass'] ?? '');
    $adminName = trim($_POST['admin_name'] ?? 'Administrator'); $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $adminPass = (string)($_POST['admin_password'] ?? ''); $baseUrl = rtrim(trim($_POST['base_url'] ?? ''), '/');
    if (!$email || strlen($adminPass) < 8 || !$name || !$baseUrl) $error = 'Enter a valid URL and email; password must be at least 8 characters.';
    else try {
        $root = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $root->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo = new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $pdo->exec(file_get_contents(__DIR__ . '/schema.sql'));
        $stmt=$pdo->prepare('INSERT INTO admins(name,email,password) VALUES(?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name),password=VALUES(password)');
        $stmt->execute([$adminName,$email,password_hash($adminPass,PASSWORD_DEFAULT)]);
        $settings = [
          'bride_name'=>'मनीषा','groom_name'=>'आकाश','wedding_date'=>'2026-10-18 19:00:00','venue_name'=>'मंगलम् पैलेस',
          'venue_address'=>'मवाकोट रोड, निम्बूचौड़, कोटद्वार, पौड़ी गढ़वाल','map_url'=>'https://www.google.com/maps/search/?api=1&query=Mangalam+Palace+Mawakot+Road+Kotdwar',
          'family_message'=>'परमपिता परमेश्वर की असीम अनुकम्पा एवं पितरों के आशीर्वाद से इस मंगल बेला पर आपकी गरिमामयी उपस्थिति सादर प्रार्थनीय है।',
          'bride_details'=>'सुपुत्री श्रीमती शोभा गुसाईं एवं श्री रमेश सिंह गुसाईं • ग्राम चमस्यूँ, पौड़ी गढ़वाल',
          'groom_details'=>'सुपुत्र श्रीमती सरोजनी देवी एवं श्री दलीप सिंह रावत • मवाकोट, कोटद्वार, पौड़ी गढ़वाल'
        ];
        $s=$pdo->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)'); foreach($settings as $k=>$v)$s->execute([$k,$v]);
        $count=(int)$pdo->query('SELECT COUNT(*) FROM wedding_events')->fetchColumn();
        if(!$count){$event=$pdo->prepare('INSERT INTO wedding_events(event_name,event_date,event_time,venue,address,description,map_url,sort_order) VALUES(?,?,?,?,?,?,?,?)');
          $rows=[
            ['महिला संगीत','2026-10-16','दोपहर 2:00 बजे','निवास स्थान','बालावाला, देहरादून','संगीत और उल्लास के साथ उत्सव का शुभारंभ','',1],
            ['मेहंदी','2026-10-17','शाम 5:00 बजे','निवास स्थान','बालावाला, देहरादून','मेहंदी की रस्म','',2],
            ['अतिथि सत्कार (न्यूतेर)','2026-10-17','हर पल','मंगलम् पैलेस','मवाकोट रोड, निम्बूचौड़, कोटद्वार','आत्मीय स्वागत','',3],
            ['प्रीतिभोज','2026-10-17','रात्रि 8:00 बजे','मंगलम् पैलेस','मवाकोट रोड, निम्बूचौड़, कोटद्वार','परिवार संग प्रीतिभोज','',4],
            ['हल्दी हाथ','2026-10-18','प्रातः 10:00 बजे','मंगलम् पैलेस','मवाकोट रोड, निम्बूचौड़, कोटद्वार','हल्दी की मंगल रस्म','',5],
            ['मंगल स्नान','2026-10-18','प्रातः 11:00 बजे','मंगलम् पैलेस','मवाकोट रोड, निम्बूचौड़, कोटद्वार','मंगल स्नान','',6],
            ['बारात स्वागत','2026-10-18','रात्रि 7:00 बजे','मंगलम् पैलेस','मवाकोट रोड, निम्बूचौड़, कोटद्वार','बारात का स्नेहपूर्ण स्वागत',$settings['map_url'],7],
            ['प्रीतिभोज','2026-10-18','रात्रि 8:00 बजे','मंगलम् पैलेस','मवाकोट रोड, निम्बूचौड़, कोटद्वार','विवाह प्रीतिभोज',$settings['map_url'],8],
            ['विवाह संस्कार','2026-10-18','शुभ लग्नानुसार','मंगलम् पैलेस','मवाकोट रोड, निम्बूचौड़, कोटद्वार','पावन परिणय सूत्र बंधन',$settings['map_url'],9],
            ['विदाई (भीगी पलकें)','2026-10-19','तारों की छांव में','मंगलम् पैलेस','मवाकोट रोड, निम्बूचौड़, कोटद्वार','स्नेहपूर्ण विदाई','',10]
          ]; foreach($rows as $row)$event->execute($row);
        }
        $php="<?php\ndeclare(strict_types=1);\nreturn ".var_export(['app_name'=>'Manisha & Akash Wedding','base_url'=>$baseUrl,'timezone'=>'Asia/Kolkata','db'=>['host'=>$host,'port'=>$port,'name'=>$name,'user'=>$user,'pass'=>$pass,'charset'=>'utf8mb4']],true).";\n";
        if(file_put_contents(__DIR__.'/config.php',$php)===false) throw new RuntimeException('Could not write config.php. Make the folder writable and retry.');
        $success=true;
    } catch(Throwable $e){$error=$e->getMessage();}
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Wedding Website Setup</title><link rel="stylesheet" href="assets/admin.css"></head><body class="login-page"><main class="login-card"><span class="eyebrow">ONE-TIME SETUP</span><h1>Wedding website installer</h1><?php if($success):?><div class="alert success">Installation complete. For security, delete <b>install.php</b> after logging in.</div><a class="btn primary block" href="admin/login.php">Open Admin Panel</a><?php else:?><?php if($error):?><div class="alert error"><?=htmlspecialchars($error)?></div><?php endif;?><form method="post" class="form-grid"><label>Website URL<input name="base_url" required value="<?=htmlspecialchars($_POST['base_url']??'http://localhost/wedding-invitation')?>"></label><div class="split"><label>DB Host<input name="host" value="<?=htmlspecialchars($_POST['host']??'localhost')?>"></label><label>Port<input name="port" value="3306"></label></div><label>Database name<input name="database" value="wedding_invitation"></label><div class="split"><label>DB Username<input name="user" required></label><label>DB Password<input name="pass" type="password"></label></div><hr><label>Admin name<input name="admin_name" required value="Administrator"></label><label>Admin email<input name="email" type="email" required></label><label>Admin password<input name="admin_password" type="password" minlength="8" required></label><button class="btn primary block">Install Website</button></form><?php endif;?></main></body></html>
