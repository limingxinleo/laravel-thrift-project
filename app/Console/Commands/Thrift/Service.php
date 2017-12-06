<?php

namespace App\Console\Commands\Thrift;

use App\Console\Socket;
use App\Thrift\Services\AppHandler;
use Xin\Thrift\MicroService\AppProcessor;
use swoole_server;
use Thrift\Protocol\TBinaryProtocol;
use Thrift\TMultiplexedProcessor;
use Thrift\Transport\TMemoryBuffer;

class Service extends Socket
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'thrift:service {--daemonize}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start Thrift RPC Server';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    protected $config = [
        'daemonize' => false,
        'max_request' => 500, // 每个worker进程最大处理请求次数
    ];

    protected $port = 10086;

    protected $host = '127.0.0.1';

    protected $processor;

    protected function events()
    {
        return [
            'receive' => [$this, 'receive'],
            'WorkerStart' => [$this, 'workerStart'],
        ];
    }

    protected function beforeServerStart(swoole_server $server)
    {
        parent::beforeServerStart($server);

        if ($this->option('daemonize')) {
            $this->config['daemonize'] = true;
        }

        // 重置参数
        $server->set($this->config);
    }


    public function workerStart(swoole_server $serv, $workerId)
    {
        // dump(get_included_files()); // 查看不能被平滑重启的文件

        $this->processor = new TMultiplexedProcessor();
        $handler = new AppHandler();
        $this->processor->registerProcessor('app', new AppProcessor($handler));
    }

    public function receive(swoole_server $server, $fd, $reactor_id, $data)
    {
        $transport = new TMemoryBuffer($data);
        $protocol = new TBinaryProtocol($transport);
        $transport->open();
        $this->processor->process($protocol, $protocol);
        $server->send($fd, $transport->getBuffer());
        $transport->close();
    }
}
