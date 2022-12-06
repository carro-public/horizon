<?php

namespace Laravel\Horizon\Jobs;

use Laravel\Horizon\Events\JobDeleted;

class SqsJob extends \Illuminate\Queue\Jobs\SqsJob
{
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
