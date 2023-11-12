<?php

class PgPool
{
    private array $connections = [];

    private int $createnSize = 0;

    public function __construct(
        private readonly string $connectionString,
        private readonly int $size = 16
    ) {

    }


    public function get(): \PgSql\Connection|false
    {
        if(count($this->connections) > 0) {
            return \array_shift($this->connections);
        }
        if($this->createnSize < $this->size) {
            $this->createnSize++;
            return \pg_connect($this->connectionString, \PGSQL_CONNECT_FORCE_NEW | \PGSQL_CONNECT_ASYNC);
        }
        return false;
    }

    public function put(\PgSql\Connection $conn)
    {
        $this->connections[] = $conn;
    }

    public function fill(): void
    {
        while($this->createnSize < $this->size) {
            $this->createnSize++;
            $this->connections[] = \pg_connect($this->connectionString, \PGSQL_CONNECT_FORCE_NEW | \PGSQL_CONNECT_ASYNC);
        }
    }

    public function shutdown(): void
    {
        foreach($this->connections as $connect) {
            \pg_close($connect);
        }
    }
}

class Loop {
    /** @var Generator[] $tasks */
    private $tasks = [];

    function addTask($task) {
        $this->tasks[] = $task;
    }

    function run($endOnTaskEnd = false) {
        while(true) {
            foreach($this->tasks as $i => $task) {
              //  of($task->)
                $task->next();
                try {
                    $task->getReturn();
                    unset($this->tasks[$i]);
                } catch (\Throwable) {
                    continue;
                }
            }
            if(count($this->tasks) == 0 && $endOnTaskEnd) {
                return;
            }
           // usleep(1);
        }
    }
}
//global $pool;
$connectionString = '';
$query = '';
$pool = new PgPool($connectionString);
function task3() {
    global $pool;
    global $query;
    while (!($connection = $pool->get())) {
        yield;
    }
    pg_ping($connection);
   \pg_send_query($connection, $query);
    while (\pg_connection_busy($connection)) {
        yield;
    }
    $result = pg_get_result($connection);
    $pool->put($connection);
    if(!$result) {
        return [];
    }
    return pg_fetch_assoc($result);
}
/** @return Generator<int,int,int,bool> */
function task1(): Generator {
    foreach(range(0,100) as $temp) {
        echo 'generator 1 = ' . $temp . PHP_EOL;
        yield;
    }
    return false;
}
function task2(): Generator {
    $result = yield from task3();
    echo print_r($result);
    foreach(range(0,10) as $temp) {
        echo 'generator 2 = ' . $temp . PHP_EOL;
        yield;
    }
    return 2;

}
$loop = new Loop();
$loop->addTask(task1());
$loop->addTask(task2());
$loop->addTask((function (){
    echo print_r(yield from task3());
    return 1;
})());
$loop->addTask((function (){
    echo print_r(yield from task3());
    return 1;
})());
$loop->addTask((function (){
    echo print_r(yield from task3());
    return 1;
})());
$loop->addTask((function (){
    echo print_r(yield from task3());
    return 1;
})());
$loop->run(true);
echo 'end';
