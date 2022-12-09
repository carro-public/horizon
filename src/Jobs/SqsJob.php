<?php

namespace Laravel\Horizon\Jobs;

use Aws\Sqs\SqsClient;
use Illuminate\Container\Container;
use Laravel\Horizon\Events\JobDeleted;
use Laravel\Horizon\Events\JobReserved;

class SqsJob extends \Illuminate\Queue\Jobs\SqsJob
{
    public function __construct(Container $container, SqsClient $sqs, array $job, $connectionName, $queue)
    {
        parent::__construct($container, $sqs, $job, $connectionName, $queue);

        event(new JobReserved($this->getReservedJob()));
    }

    public function delete()
    {
        parent::delete();

        event(new JobDeleted($this, json_encode($this->payload())));
    }

    public function getReservedJob()
    {
        $payloadBody = json_decode($this->getRawBody(), true);
        $payloadBody['attempts'] = $this->attempts();
        return json_encode($payloadBody);
    }
}
