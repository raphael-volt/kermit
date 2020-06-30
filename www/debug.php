<?php
use src\core\pdo\ApiPDO;
use src\vo\User;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use src\services\FileService;
error_reporting(- 1);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
require_once __DIR__ . DIRECTORY_SEPARATOR . 'autoload.php';

function getMailer()
{
    $mail = new PHPMailer(true);
    $mail->isHTML();
    $mail->CharSet = 'UTF-8';
    $mail->Encoding = 'base64';
    $mail->isSMTP();
    $mail->SMTPAuth = true; // Enable SMTP authentication
    //$mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` encouraged
    $mail->Host = 'mail';
    $mail->SMTPAuth = true;
    $mail->Username = '';
    $mail->Password = '';
    $mail->Port = 1025;
    $dir = FileService::instance()->root("PHPMailer", "PHPMailer");
    $mail->setLanguage('fr', $dir);
    // Recipients
    $mail->setFrom('jardin-partage@ketmie.com', 'Les Guernettes');
    return $mail;
}

$pdo = ApiPDO::instance();
$users = $pdo->query("SELECT * FROM jp_user");

$users = $users->fetchAll(PDO::FETCH_CLASS, User::class);
$names = [];
foreach ($users as $user) {
    $user instanceof User;
    $names[] = $user->name . " ($user->email)";
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Debug</title>
</head>
<body>
<?php
if (isset($_GET["send"])) {
    try {
        $mail = getMailer();
        $mail->addAddress('raphael@ketmie.com', 'Raphael Volt'); // Add a recipient
        $now = time();
        $mail->Subject = "Test mail[{$now}]";
        $emailList = implode('</li><li>', $names);
        $body = <<<EOT
<h1>Voici la liste des membres du jardin partagé:</h1>
<ul><li>$emailList</li></ul>
EOT;
        $mail->Body = $body; // 'This is the HTML message body <b>in bold!</b>';
        $mail->AltBody = 'This is the body in plain text for non-HTML mail clients';

        $mail->send();
        echo '<p>Message has been sent</p>';
    } catch (Exception $e) {
        echo "<p>Message could not be sent. Mailer Error: <br><pre>{$mail->ErrorInfo}</pre></p>";
    }
}
?>
	<form action="debug.php" method="get">
		<input type="hidden" name="send" value="">
		<button type="submit">Envoyer le mail</button>
	</form>
	<ul>
		<li><?php echo implode('</li><li>', $names);?></li>
	</ul>
</body>
</html>