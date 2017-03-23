<?php

namespace App\Controller;

use App\Seedbox\Server;
use App\Seedbox\Users;
use App\Seedbox\Utils;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Flash\Messages as Flash;
use Slim\Views\Twig;
use Symfony\Component\Translation\Translator;
use WriteiniFile\WriteiniFile;

class HomeController
{
    protected $view;
    protected $flash;
    protected $username;
    protected $fileini;
    protected $user;
    protected $server;

    public function __construct(Twig $view, Flash $flash, Translator $translator)
    {
        $this->view = $view;
        $this->flash = $flash;

        $this->username = Utils::getCurrentUser();
        $this->fileini = Utils::getFileini($this->username);
        $this->user = new Users($this->fileini, $this->username);
        $this->server = new Server($this->fileini, $this->username);

        $translator->setLocale($this->user->language());
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response)
    {
        $read_data_reboot = $this->user->readFileDataReboot(__DIR__."/../../conf/users/{$this->user->name()}/data_reboot.txt");
        $server = $request->getServerParams();
        $host = $this->checkhttps($server);

        $message = $this->flash->getMessages();

        return $this->view->render($response, 'index.twig.html', [
            'host'             => $host,
            'ipUser'           => $server['REMOTE_ADDR'],
            'user'             => $this->user,
            'server'           => $this->server,
            'read_data_reboot' => $read_data_reboot,
            'notifications'    => $this->flash->getMessages()
        ]);
    }

    public function settings(ServerRequestInterface $request, ResponseInterface $response)
    {
        return $this->view->render($response, 'settings.twig.html', [
            'user'          => $this->user,
            'server'        => $this->server,
            'notifications' => $this->flash->getMessages()
        ]);
    }

    public function reboot(ServerRequestInterface $request, ResponseInterface $response)
    {
        $param = $request->getParsedBody();
        $option = (isset($param['irssi'])) ? true : false;

        $reboot_rtorrent = $this->user->rebootRtorrent($option);
        $this->flash->addMessage('rtorrent', $reboot_rtorrent);

        return $response->withStatus(302)->withHeader('Location', '/');
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response)
    {
        $param = $request->getParsedBody();
        $update = new WriteiniFile($this->fileini);
        $update->update([
            'user' => [
                'active_bloc_info' => isset($param['active_bloc_info']) ? true : false,
                'theme'            => $param['theme'],
                'language'         => $param['language']
            ],
            'ftp' => [
                'active_ftp' => isset($param['active_ftp']) ? true : false
            ],
            'rtorrent' => [
                'active_reboot' => isset($param['active_reboot']) ? true : false
            ],
            'logout' => [
                'url_redirect' => $param['url_redirect']
            ]
        ]);

        $logs = $update->write();
        $this->flash->addMessage('update_ini_file', $logs);

        return $response->withStatus(302)->withHeader('Location', '/settings');
    }

    protected function checkhttps($param)
    {
        $host = $param['HTTP_HOST'];

        if ($param['HTTP_HOST'] == 'on') {
            $host = "https://{$param['HTTP_HOST']}";
        }

        return $host;
    }
}
