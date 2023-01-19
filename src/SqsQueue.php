<?php

namespace Laravel\Horizon;

use Illuminate\Support\Str;
use Laravel\Horizon\Jobs\SqsJob;
use Illuminate\Events\Dispatcher;
use Laravel\Horizon\Events\JobPushed;
use Laravel\Horizon\Events\JobReserved;

class SqsQueue extends \Illuminate\Queue\SqsQueue
{
    /**
     * The job that last pushed to queue via the "push" method.
     *
     * @var object|string
     */
    protected $lastPushed;

    /**
     * Get the number of queue jobs that are ready to process.
     *
     * @param  string|null  $queue
     * @return int
     */
    public function readyNow($queue = null)
    {
        return $this->size($queue);
    }

    /**
     * Push a new job onto the queue.
     *
     * @param  object|string  $job
     * @param  mixed  $data
     * @param  string|null  $queue
     * @return mixed
     */
    public function push($job, $data = '', $queue = null)
    {
        return $this->enqueueUsing(
            $job,
            $this->createPayload($job, $this->getQueue($queue), $data),
            $queue,
            null,
            function ($payload, $queue) use ($job) {
                $this->lastPushed = $job;

                return $this->pushRaw($payload, $queue);
            }
        );
    }

    /**
     * Push a raw payload onto the queue (with delay support as options)
     *
     * @param  string  $payload
     * @param  string|null  $queue
     * @param  array  $options
     * @return mixed
     */
    public function pushRawWithDelays($payload, $queue = null, array $options = [])
    {
        # Will add DelaySeconds parameter if $options['delay'] was specified
        return $this->sqs->sendMessage(
            [
                'QueueUrl' => $this->getQueue($queue),
                'MessageBody' => $payload,
            ] + (isset($options['delay']) ? [
                'DelaySeconds' => $this->secondsUntil($options['delay'])
            ] : [])
        )->get('MessageId');
    }

    /**
     * Push a raw payload onto the queue.
     *
     * @param  string  $payload
     * @param  string  $queue
     * @param  array  $options
     * @return mixed
     */
    public function pushRaw($payload, $queue = null, array $options = [])
    {
        $payload = (app()->make(JobPayload::class, ['value' => $payload]))->prepare($this->lastPushed);

        $this->pushRawWithDelays($payload->value, $queue, $options);

        $this->event($this->getQueue($queue), new JobPushed($payload->value));

        return $payload->id();
    }

    /**
     * Create a payload string from the given job and data.
     *
     * @param  string  $job
     * @param  string  $queue
     * @param  mixed  $data
     * @return array
     */
    protected function createPayloadArray($job, $queue, $data = '')
    {
        $payload = parent::createPayloadArray($job, $queue, $data);

        $payload['id'] = $payload['uuid'];

        return $payload;
    }

    /**
     * Push a new job onto the queue after a delay.
     *
     * @param  \DateTimeInterface|\DateInterval|int  $delay
     * @param  string  $job
     * @param  mixed  $data
     * @param  string  $queue
     * @return mixed
     */
    public function later($delay, $job, $data = '', $queue = null)
    {
        $payload = (app()->make(JobPayload::class, [
            'value' => $this->createPayload($job, $queue, $data)
        ]))->prepare($job)->value;

        return tap($this->laterRaw($delay, $job, $payload, $queue), function () use ($payload, $queue) {
            $this->event($this->getQueue($queue), new JobPushed($payload));
        });
    }

    /**
     * Push Raw Payload Data into SQS Queue
     * @param $delay
     * @param $job
     * @param $payload
     * @param $queue
     * @return mixed
     */
    public function laterRaw($delay, $job, $payload, $queue = null)
    {
        return $this->enqueueUsing(
            $job,
            $payload,
            $queue,
            $delay,
            function ($payload, $queue) use ($delay) {
                return $this->pushRaw($payload, $queue, [
                    'delay' => $delay,
                ]);
            }
        );
    }

    /**
     * Pop the next job off of the queue.
     *
     * @param  string  $queue
     * @return \Illuminate\Contracts\Queue\Job|null
     */
    public function pop($queue = null)
    {
        $response = $this->sqs->receiveMessage([
            'QueueUrl' => $queue = $this->getQueue($queue),
            'AttributeNames' => ['ApproximateReceiveCount'],
        ]);

        if (! is_null($response['Messages']) && count($response['Messages']) > 0) {
            return app()->make(\Illuminate\Queue\Jobs\SqsJob::class, [
                'container' => $this->container,
                'sqs' => $this->sqs,
                'job' => $response['Messages'][0],
                'connectionName' => $this->connectionName,
                'queue' => $queue,
            ]);
        }
    }

    /**
     * Fire the given event if a dispatcher is bound.
     *
     * @param  string  $queue
     * @param  mixed  $event
     * @return void
     */
    protected function event($queue, $event)
    {
        if ($this->container && $this->container->bound(Dispatcher::class)) {
            $this->container->make(Dispatcher::class)->dispatch(
                $event->connection($this->getConnectionName())->queue($queue)
            );
        }
    }
}
