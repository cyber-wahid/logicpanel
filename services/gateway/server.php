<?php
require __DIR__ . '/vendor/autoload.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Loop;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class TerminalGateway implements MessageComponentInterface
{
    protected $clients;
    protected $sessions;

    public function __construct()
    {
        $this->clients = new \SplObjectStorage;
        $this->sessions = [];
        echo "Terminal Gateway Started (VERSION 4.0 - Native PTY)\n";
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        if ($msg === '')
            return;

        if (!isset($this->sessions[$from->resourceId])) {
            $this->handleAuth($from, $msg);
            return;
        }

        $session = $this->sessions[$from->resourceId];

        // Handle resize
        if ($msg[0] === '{' && strpos($msg, '"cols"') !== false) {
            return; // Resize not supported in this mode
        }

        // Write to PTY
        if (is_resource($session['master'])) {
            @fwrite($session['master'], $msg);
        }
    }

    protected function handleAuth(ConnectionInterface $conn, $token)
    {
        try {
            $secret = getenv('JWT_SECRET');
            if (!$secret)
                throw new Exception('JWT_SECRET not set');

            $decoded = JWT::decode($token, new Key($secret, 'HS256'));
            $containerId = $decoded->container_id;
            $serviceId = $decoded->service_id;

            echo "Auth valid for Service $serviceId\n";
            $this->spawnPty($conn, $containerId, $serviceId);

        } catch (Exception $e) {
            echo "Auth failed: " . $e->getMessage() . "\n";
            $conn->send("\r\n\x1b[31mAuthentication Failed\x1b[0m\r\n");
            $conn->close();
        }
    }

    protected function spawnPty(ConnectionInterface $conn, $containerId, $serviceId)
    {
        echo "[VERSION 4.0] Spawning PTY for Service $serviceId\n";

        // Use PHP's native PTY support
        $descriptors = [
            0 => ['pty'],
            1 => ['pty'],
            2 => ['pty']
        ];

        // Direct docker exec - PTY will be provided by proc_open
        $cmd = "docker exec -i -e TERM=xterm-256color -w /storage/service_{$serviceId} {$containerId} sh";

        $process = proc_open($cmd, $descriptors, $pipes);

        if (!is_resource($process)) {
            $conn->send("\r\n\x1b[31mFailed to spawn shell\x1b[0m\r\n");
            $conn->close();
            return;
        }

        // Get the master PTY (all three are the same in PTY mode)
        $master = $pipes[0];
        stream_set_blocking($master, false);

        $this->sessions[$conn->resourceId] = [
            'process' => $process,
            'master' => $master,
            'pipes' => $pipes,
            'id' => $serviceId,
            'start' => time()
        ];

        // Read from PTY and send to WebSocket
        Loop::addReadStream($master, function ($stream) use ($conn, $process, $serviceId) {
            $data = @fread($stream, 8192);

            if ($data === false || $data === '') {
                // Check if process is still running
                $status = proc_get_status($process);
                if (!$status['running']) {
                    echo "[Service $serviceId] Shell exited\n";
                    $conn->close();
                }
                return;
            }

            $conn->send($data);
        });

        echo "[Service $serviceId] PTY session started\n";
    }

    public function onClose(ConnectionInterface $conn)
    {
        if (isset($this->sessions[$conn->resourceId])) {
            $session = $this->sessions[$conn->resourceId];
            $duration = time() - $session['start'];
            echo "Session {$conn->resourceId} closed after {$duration}s\n";

            // Clean up
            $status = proc_get_status($session['process']);
            if ($status['running']) {
                proc_terminate($session['process'], 9);
            }

            Loop::removeReadStream($session['master']);

            @fclose($session['master']);
            proc_close($session['process']);

            unset($this->sessions[$conn->resourceId]);
        }
        $this->clients->detach($conn);
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "Error: {$e->getMessage()}\n";
        $conn->close();
    }
}

$server = IoServer::factory(
    new HttpServer(new WsServer(new TerminalGateway())),
    3002
);
$server->run();
