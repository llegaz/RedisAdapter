<?php

declare(strict_types=1);

/**
 * @author Laurent LEGAZ <laurent@legaz.eu>
 */
require_once realpath(__DIR__ . '/../../') . '/vendor/autoload.php';

use LLegaz\Redis\RedisAdapter;
use LLegaz\Redis\RedisClientsPool;

if (!function_exists('pcntl_fork')) {
    die('PCNTL functions not available on this PHP installation');
}
pcntl_async_signals(true);
pcntl_signal(SIGINT, 'shutdown');
pcntl_signal(SIGQUIT, 'shutdown');
pcntl_signal(SIGTSTP, 'shutdown');
$children = [];

function shutdown()
{
    global $children;
    while (count($children)) {
        $pid = array_pop($children);
        exec('kill -9 ' . $pid);
        dump('killed ' . $pid);
    }
    echo 'Done! :^)' . PHP_EOL;
    exit;
}

/**
 * @todo mb enhance here try with docker instances, test auth on persistent conns
 *       see if refactoring is needed for persistent conns in RedisAdapter class?
 *       refacto dump below..
 */
function test()
{
    $redis = new RedisAdapter('127.0.0.1', 6379, null, 'tcp', mt_rand(0, 15), true);
    //dump('***', $redis->getContext()['client_id']/*, RedisClientsPool::clientCount(), $redis->checkRedisClientId()*/, $redis->getRedisClientID(), $redis->getClientCtxtFromRemote()['id'], '----');
    dump($redis->getContext()['client_id'] . ' ' . $redis->getRedisClientID() . ' ' . $redis->getClientCtxtFromRemote()['id']);
}

$ppid = getmypid();
for ($x = 1; $x < 4; $x++) {
    if (getmypid() === $ppid) {
        switch ($pid = pcntl_fork()) {
            case -1:
                // @fail
                die('Fork failed');

                break;
            case 0:
                // @child
                print "FORK: Child #{$x}\n";
                while (1) {
                    usleep(mt_rand(500000, 4000000));
                    test();
                }

                break;
            default:
                // @parent
                print "FORK: Parent, letting the child run...\n";
                $children[] = $pid;
                pcntl_waitpid($pid, $status, WNOHANG);

                break;
        }
    }
}

while (1) {
    usleep(10000);
    echo '.';
}
