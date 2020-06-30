<?php
namespace src\services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use src\vo\Thread;
use src\vo\ThreadPart;
use src\core\pdo\ApiPDO;
use src\vo\User;

class MailService
{

    private static $_instance;

    /**
     *
     * @return \src\services\MailService
     */
    static function instance()
    {
        if (! self::$_instance) {
            self::$_instance = new MailService();
        }
        return self::$_instance;
    }

    private function getUsersToNotify($fromId)
    {
        $watch = WatchService::instance();
        $usersOn = $watch->getLoggedUsers();
        $userList = DatabaseService::instance()->getUserList();
        $users = [];
        $from = null;
        foreach ($userList as $user) {
            if ($user->id == $fromId) {
                $from = $user;
                continue;
            }
            if ($user->notify_by_email == 0)
                continue;

            if (array_search($user->id, $usersOn) === false)
                $users[] = $user;
        }
        return [
            $from,
            $users
        ];
    }

    private $config;
    private function __construct()
    {
        $this->config = FileService::instance()->getConfig()->mail;
    }

    function isDevMode()
    {
        return ApiPDO::instance()->isDevMode();
    }

    function notifyAccountCreated(User $current, User $new)
    {
        $url = $this->getUrlSite() . "/account";
        $mail = $this->getMailer();
        $mail->Subject = "Les Guernettes | Compte créé";
        $mail->Body = $this->getHtmlBody("Ton compte a été créé", <<<EOL
<p>$current->name a créé ton compte sur le site des Guernettes.</p>
<p>Tu peut dès à présent te connecter en utilisant <a href="$url">ce lien</a>.
<p>Identifies toi en utilisant ton email.</p>
<p>Tu serras redirigé vers la page <span style="font-style: italic;color: #444">Mon compte</span> et tu pourras éditer ton profil.</p>
EOL
);
        $mail->AltBody = $this->getTextBody("Ton compte a été créé", <<<EOL
$current->name a créé ton compte sur le site des Guernettes.
Tu peut dès à présent te connecter en utilisant ce lien:
$url, puis identifies-toi en utilisant ton email.
Tu serras redirigé vers la page Mon compte et tu pourras éditer ton profil.
EOL
);
        $mail->addAddress($new->email, $new->name);
        $mail->send();
    }

    function notifyAccountRemoved(User $user)
    {
        $mail = $this->getMailer();
        $mail->Subject = "Les Guernettes | Compte supprimé";
        $mail->Body = $this->getHtmlBody("Ton compte a été supprimé", <<<EOL
<p>Tes messages ont été supprimés.</p>
<p>Tu ne recevra plus de notification par email.</p>
<p>Tu ne peut plus te connecter au site des Guernettes.</p>
<p>Tu peux à tout moment demander à un membre de te créer un nouveau compte.</p>
<p>Les Guernettes te saluent.</p>
EOL
);
        $mail->AltBody = $this->getTextBody("Ton compte a été supprimé", <<<EOL
Tes messages ont été supprimés.
Tu ne recevra plus de notification par email.
Tu ne peut plus te connecter au site des Guernettes.
Tu peux à tout moment demander à un membre de te créer un nouveau compte.
Les Guernettes te saluent.
EOL
);
        $mail->addAddress($user->email, $user->name);
        $mail->send();
    }

    function getUrlSite()
    {
        return $this->isDevMode() ? "http://localhost:4200" : "https://jp.ketmie.com";
    }

    function getApiSite()
    {
        return $this->isDevMode() ? "http://localhost:4201" : "https://jp.api.ketmie.com";
    }

    function notifyNewMessage(Thread $thread)
    {
        $data = $this->getUsersToNotify($thread->user_id);
        $users = $data[1];
        if (! count($users))
            return false;
        $from = $data[0];
        $link = $this->getUrlSite();
        $link .= "/messages/$thread->id";
        $mail = $this->getMailer();
        $mail->Subject = "Les Guernettes | Nouveau message de {$from->name}";
        $mail->Body = $this->getHtmlBody($thread->subject, <<<EOL
<p>Tu peux voir ce nouveau message en cliquant sur le lien ci-dessous:</p>
<p><a href="$link" style="color: #1565c0">Ouvrir le message</a></p>
EOL
);
        $mail->AltBody = $this->getTextBody($thread->subject, <<<EOL
Tu peux voir ce nouveau message en te rendant
sur le site des Guernettes :
$link
EOL
);
        foreach ($users as $user) {
            $mail->addAddress($user->email, $user->name);
        }
        return $mail->send();
    }

    function notifyNewReply(ThreadPart $threadPart)
    {
        $thread = DatabaseService::instance()->getThreadById($threadPart->thread_id);
        $data = $this->getUsersToNotify($threadPart->user_id);
        $users = $data[1];
        if (! count($users))
            return false;
        $from = $data[0];
        $link = $this->isDevMode() ? "http://localhost:4200" : "https://jp.ketmie.com";
        $link .= "/messages/$thread->id";
        $mail = $this->getMailer();
        $mail->Subject = "Les Guernettes | {$from->name} a ajouté une réponse";

        $mail->Body = $this->getHtmlBody($thread->subject, <<<EOL
<p>Tu peux voir cette réponse en cliquant sur le lien ci-dessous:</p>
<p><a href="$link" style="color: #1565c0">Ouvrir le message</a></p> 
EOL
);
        $mail->AltBody = $this->getTextBody($thread->subject, <<<EOL
Tu peux voir cette réponse en te rendant sur le 
site des Guernettes:
$link
EOL
);
        foreach ($users as $user) {
            $mail->addAddress($user->email, $user->name);
        }
        return $mail->send();
    }

    private function getHtmlBody($subject, $message)
    {
        $img = $this->getApiSite() . "/assets/images/kermit-small.png";
        return <<<EOL
<div style="font-family: Roboto, 'Helvetica Neue', sans-serif; color: rgba(0, 0, 0, 0.87); padding:16px">
    <h2 style="padding: 0;margin: 0;display: flex;flex-direction: row;flex-wrap: nowrap;justify-content: flex-start;align-content: stretch;align-items: center;font-size: 36px;">
        <img style="order: 0;flex: 0 1 auto;align-self: auto;" src="$img"/>
        <span style="order: 0;flex: 0 1 auto;align-self: auto;">$subject</span>
    </h2>
    $message
    <p style="font-style: italic;color: #444;margin-top:32px">Message automatique, merci de ne pas répondre.</p>
</div>
EOL;
    }

    private function getTextBody($subject, $message)
    {
        return <<<EOL
-----------------------------------------------
$subject
-----------------------------------------------
$message
-----------------------------------------------
Message automatique, merci de ne pas répondre.
-----------------------------------------------
EOL;
    }

    /**
     *
     * @return \PHPMailer\PHPMailer\PHPMailer
     */
    private function getMailer()
    {
        $cfg = $this->config;
        $admin = $cfg->admin;
        $isDev = $this->isDevMode();
        $mail = new PHPMailer(true);
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        $mail->setLanguage('fr', FileService::instance()->root("PHPMailer", "PHPMailer"));
        $mail->SMTPDebug = SMTP::DEBUG_OFF; // Disable verbose debug output
        $mail->isSMTP(); // Send using SMTP
        $mail->SMTPAuth = true; // Enable SMTP authentication
        if ($isDev) {
            $mail->Host = 'mail';
            $mail->Username = '';
            $mail->Password = '';
            $mail->Port = 1025;
        } else {
            $srv = $cfg->server;
            if($srv->smtps)
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` encouraged
            $mail->Host = $srv->host; // Set the SMTP server to send through
            $mail->Username = $admin->email; // SMTP username
            $mail->Password = $admin->password; // SMTP password
            $mail->Port = $srv->port; // TCP port to connect to, use 465 for `PHPMailer::ENCRYPTION_SMTPS` above
        }
        $mail->setFrom($admin->email, $admin->name);
        // why sended mails are not present in ovh webmail ?
        $mail->addBCC($admin->email, $admin->name);
        $mail->isHTML();
        return $mail;
    }
}

