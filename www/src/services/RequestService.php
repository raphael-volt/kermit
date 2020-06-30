<?php
namespace src\services;

use src\core\http\HTTPMethods;
use src\core\http\HTTPRequest;
use src\vo\User;
use src\vo\Thread;
use src\vo\JPFile;
use src\vo\ThreadData;
use src\vo\ThreadPart;

class RequestService
{

    /**
     *
     * @var RequestService
     */
    private static $_instance;

    /**
     *
     * @return \src\services\RequestService
     */
    static function instance()
    {
        if (! self::$_instance)
            self::$_instance = new RequestService();
        return self::$_instance;
    }

    private function __construct()
    {
        ;
    }

    function getJsonError($code, $message, $setResponse = true)
    {
        $error = new \stdClass();
        $error->code = $code;
        $error->message = $message;
        if ($setResponse) {
            $response = $this->responseJson;
            if ($response == NULL) {
                $response = new \stdClass();
                $this->responseJson = $response;
            }
            $response->error = $error;
        }
        return $error;
    }

    const AUTH_KEY = "Jp-Auth";

    private $responseCode = 0;

    private $responseJson = NULL;

    /**
     *
     * @param string $uri
     * @return boolean|\src\core\http\HTTPRequest
     */
    function createRequest(string $uri)
    {
        $request = new HTTPRequest($uri);
        if ($request->valid())
            return $request;
        return false;
    }

    /**
     *
     * @return boolean|\src\core\http\HTTPRequest
     */
    function checkRequest($method)
    {
        if (! HTTPMethods::is($method))
            return FALSE;
        $request = $this->createRequest($_SERVER['REQUEST_URI']);
        if ($request !== FALSE) {
            $request->method = $method;
            if ($method == HTTPMethods::POST || $method == HTTPMethods::PUT)
                $request->content = $this->getInput();
            return $request;
        }
        return FALSE;
    }

    /**
     *
     * @return boolean
     */
    function handle()
    {
        $this->responseCode = 200;
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, " . self::AUTH_KEY);
        header("Content-Type: application/json");
        HTTPMethods::header();
        $method = $_SERVER['REQUEST_METHOD'];
        if (! HTTPMethods::is($method)) {
            return false;
        }
        // XDEBUG_SESSION_STOP_NO_EXEC
        $debug = isset($_GET['XDEBUG_SESSION_STOP_NO_EXEC']) || isset($_GET['XDEBUG_SESSION_START']);
        if ($method == HTTPMethods::OPTIONS || $debug) {
            return TRUE;
        }

        $headers = apache_request_headers();
        $authValue = null;
        $authKey = self::AUTH_KEY;
        if (array_key_exists($authKey, $headers))
            $authValue = $headers[$authKey];
        /*
         * if (is_array($headers)) {
         * } else {
         * if (is_object($headers)) {
         * if (property_exists($headers, $authKey))
         * $authValue = $headers->{$authKey};
         * }
         * }
         */
        if ($authValue == null) {
            $this->getJsonError(400, "Missing {$authKey} request header");
            return false;
        }
        $auth = AuthService::instance();
        if (! $auth->validate($authValue)) {
            $this->getJsonError(401, "Not authorized");
            return false;
        }

        $request = $this->checkRequest($method);

        if ($request->routeName == "auth") {
            $this->responseJson = $auth->getUser();
            return TRUE;
        }
        if ($request === FALSE) {
            $this->getJsonError(400, "Bad request");
            return false;
        }
        switch ($request->routeName) {
            case "watch":
                $this->watchRequest($request->content);
                break;
            case "testdata":
                $this->testdata($request);
                break;
            case "user":
                $this->userRequest($request);
                break;
            case "thread":
                $this->threadRequest($request);
                break;
            case "thread_part":
                $this->threadPartRequest($request);
                break;

            default:
                ;
                break;
        }
        return true;
    }

    private function watchRequest($value)
    {
        $watch = WatchService::instance();
        $this->responseJson = $watch->check($value);
        $this->responseCode = 200;
        $watch->save();
    }

    private function testdata(HTTPRequest $request)
    {
        $this->responseCode = 200;
        $filename = FileService::instance()->getFilename(JPFile::TYPE_DOWNLOAD, "thread-1.json");
        file_put_contents($filename, json_encode($request->content));
        $this->responseJson = $request->content;
    }
    
    private function notifyUsersChange() {
        $watch = WatchService::instance();
        $watch->setUserChange();
        $watch->save();
    }

    private function userRequest(HTTPRequest $request)
    {
        $db = DatabaseService::instance();
        switch ($request->method) {
            case HTTPMethods::GET:
                {
                    if ($request->routeId !== NULL) {
                        $user = $db->getUserById($request->routeId);
                        if ($user !== NULL) {
                            $this->responseJson = $user;
                        } else {
                            $this->getJsonError(400, "User[{$request->routeId}] does not exists");
                            // $this->responseCode = 401;
                        }
                    } else {
                        $this->responseJson = $db->getUserList();
                    }
                    break;
                }
            case HTTPMethods::POST:
                {
                    $user = new User($request->content);
                    $user->allow_sounds = 0;
                    $user->notify_by_email = 0;
                    $user->picto = null;
                    $srv = UserService::instance();
                    $srv->add($user);
                    $this->responseCode = 200;
                    $this->responseJson = $user;
                    break;
                }
            case HTTPMethods::PUT:
                {
                    $user = new User();
                    $user->assign($request->content);
                    $errors = [];
                    if ($user->picto !== null) {
                        $fs = FileService::instance();
                        $pictoId = $db->getUserPicto($user->id);
                        $pictoId = $pictoId->picto;
                        if ($pictoId) {
                            $fs->unlinkImage($pictoId);
                        }
                        $db->getUserById($user->id);
                        $file = new JPFile();
                        $file->filetype = JPFile::TYPE_PICTO;

                        $db->addFile($file);
                        $pictoId = $file->id;
                        $file->filename = $fs->createImageFromString($user->picto, "picto_{$pictoId}", 200, 200);
                        $vo = new JPFile();
                        $vo->id = $pictoId;
                        $vo->filename = $file->filename;
                        $db->updateFile($vo);
                        if ($file->filename == false) {
                            $this->responseCode = 401;
                            return;
                        }
                        $user->picto = $file->id;
                        if (count($errors)) {
                            LogService::instance()->error(...$errors);
                        }
                    }
                    if (! $db->updateUser($user)) {
                        $this->getJsonError(400, "An error has occurred while updating the user");
                        return false;
                    }
                    $this->responseJson = $user->toObject();
                    $this->notifyUsersChange();
                    break;
                }

            case HTTPMethods::DELETE:
                {
                    if($request->routeId === NULL) {
                        $this->getJsonError(400, "Missing user id");
                        break;
                    }
                    $service = UserService::instance();
                    $service->delete($request->routeId);
                    break;
                }

            default:
                ;
                break;
        }
    }

    private function threadRequest(HTTPRequest $request)
    {
        $db = DatabaseService::instance();
        $this->responseCode = 200;
        switch ($request->method) {
            case HTTPMethods::GET:
                {
                    if ($request->routeId !== NULL) {

                        $thread = $db->getThreadData($request->routeId);
                        if ($request->hasParam("user")) {
                            $db->setThreadReadBy($thread->thread->id, intval($request->getParam("user")));
                        }
                        if ($thread !== NULL) {
                            $this->responseJson = $thread->toObject();
                        } else
                            $this->getJsonError(400, "Thread[{$request->routeId}] does not exists");
                    } else {
                        if ($request->hasParam("next")) {
                            $this->responseJson = $db->getThreadsNext($request->getParam('next'));
                            break;
                        }
                        $this->responseJson = $db->getThreadList();
                    }
                    break;
                }
            case HTTPMethods::POST:
                {
                    /*
                     * {
                     * "thread": {
                     * "subject": "Hook[15:19:1]",
                     * "user_id": 66
                     * },
                     * "inserts": [
                     * {
                     * "insert": "Contents\n"
                     * }
                     * ]
                     * }
                     */

                    $db = DatabaseService::instance();
                    $fs = FileService::instance();
                    $tree = new ThreadData($request->content);
                    $thread = $tree->thread;
                    // $thread->last_part = $db->lastInsertId(DatabaseService::TN_THREAD_PART) + 1;
                    if ($db->addThread($thread)) {
                        $result = new \stdClass();
                        $result->id = $thread->id;
                        $ops = [];
                        foreach ($tree->inserts as $op) {
                            if ($fs->isImageOp($op)) {
                                $jf = new JPFile();
                                $jf->filetype = JPFile::TYPE_IMAGE;
                                $id = $db->lastInsertId(DatabaseService::TN_FILE) + 1;
                                $jf->filename = $fs->saveInsert($op, "thread_{$thread->id}_{$id}");
                                $db->addFile($jf);
                                $op->insert->image = $jf->id;
                                unset($op->attributes);
                            }
                            $ops[] = $op;
                        }

                        $threadPart = new ThreadPart();
                        $threadPart->content = $ops;
                        $threadPart->thread_id = $thread->id;
                        $threadPart->user_id = $thread->user_id;
                        $threadPart->read_by = [
                            $thread->user_id
                        ];
                        $db->addThreadPart($threadPart);
                        $thread->last_part = $threadPart->id;
                        $vo = new Thread();
                        $vo->id = $thread->id;
                        $vo->last_part = $thread->last_part;
                        $db->getPDO()->updateVO(DatabaseService::TN_THREAD, $vo);
                        $tree->inserts = null;
                        $this->responseJson = $tree->toObject();
                        $watch = WatchService::instance();
                        $watch->setThread($thread->id, $thread->user_id);
                        $watch->setThreadPart($threadPart->id, $thread->user_id);
                        $watch->save();
                        MailService::instance()->notifyNewMessage($thread);
                    } else {
                        $this->getJsonError(400, "An error has occurred while adding the thread");
                    }
                    break;
                }
            case HTTPMethods::DELETE:
                {
                    if (! $db->deleteThread($request->routeId))
                        $this->getJsonError(400, "An error has occurred while deleting the thread");
                    ;
                }

            default:
                ;
                break;
        }
    }

    private function threadPartRequest(HTTPRequest $request)
    {
        $db = DatabaseService::instance();
        $this->responseCode = 200;
        switch ($request->method) {
            case HTTPMethods::GET:
                {
                    if ($request->routeId !== NULL) {} else {
                        $this->responseJson = $db->getThreadList();
                    }
                    break;
                }
            case HTTPMethods::POST:
                {
                    /*
                     * const ops = (data.content as Delta).ops
                     * const tp: ThreadPart = {
                     * thread_id: this.thread.id,
                     * user_id: this.thread.user_id,
                     * content: ops
                     * }
                     */
                    $tp = new ThreadPart($request->content);
                    $tid = $tp->thread_id;
                    $tp->read_by = [
                        $tp->user_id
                    ];
                    $fs = FileService::instance();
                    $tb = DatabaseService::TN_FILE;
                    foreach ($tp->content as $op) {
                        if ($fs->isImageOp($op)) {
                            $jf = new JPFile();
                            $jf->filetype = JPFile::TYPE_IMAGE;
                            $id = $db->lastInsertId($tb) + 1;
                            $jf->filename = $fs->saveInsert($op, "thread_{$tid}_{$id}");
                            $db->addFile($jf);
                            $op->insert->image = $jf->id;
                            unset($op->attributes);
                        }
                    }

                    if ($db->addThreadPart($tp)) {
                        $thread = new Thread();
                        $thread->id = $tid;
                        $thread->last_part = $tp->id;
                        $db->getPDO()->updateVO(DatabaseService::TN_THREAD, $thread);
                        $this->responseJson = $tp->toObject();
                        $watch = WatchService::instance();
                        $watch->setThread($tid, $tp->user_id);
                        $watch->setThreadPart($tp->id, $tp->user_id);
                        $watch->save();
                        MailService::instance()->notifyNewReply($tp);
                    } else {
                        $this->getJsonError(400, "An error has occurred while adding the thread part");
                    }
                    break;
                }
            case HTTPMethods::DELETE:
                {
                    if (! $db->deleteThread($request->routeId))
                        $this->getJsonError(400, "An error has occurred while adding the thread part");
                }

            default:
                ;
                break;
        }
    }

    private function setResponseCode($code = 200)
    {
        HTTPMethods::setResponseCode($code);
    }

    /**
     *
     * @return \stdClass
     */
    private function getInput()
    {
        $input = file_get_contents('php://input');
        return json_decode($input);
    }

    function getResponse()
    {
        $this->setResponseCode($this->responseCode);
        if ($this->responseJson !== NULL) {
            echo json_encode($this->responseJson, JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT);
        }
    }
}
