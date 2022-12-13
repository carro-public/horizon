<?php

namespace Laravel\Horizon\Connectors;

use Aws\Sqs\SqsClient;
use Illuminate\Support\Arr;
use Laravel\Horizon\SqsQueue;

class SqsConnector extends \Illuminate\Queue\Connectors\SqsConnector
{
    /**
     * Establish a queue connection.
     *
     * @param  array  $config
     * @return SqsQueue
     */
    public function connect(array $config)
    {
        $config = $this->getDefaultConfiguration($config);

        if (! empty($config['key']) && ! empty($config['secret'])) {
            $config['credentials'] = Arr::only($config, ['key', 'secret', 'token']);
        }

        return new SqsQueue(
            new SqsClient($config),
            $config['queue'],
            $config['prefix'] ?? '',
            $config['suffix'] ?? '',
            $config['after_commit'] ?? null
        );
    }
}
