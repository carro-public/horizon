<?php

namespace Laravel\Horizon\Listeners;

use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\TagRepository;
use Laravel\Horizon\Events\JobDeleted;

class MarkJobAsComplete
{
    /**
     * The job repository implementation.
     *
     * @var \Laravel\Horizon\Contracts\JobRepository
     */
    public $jobs;

    /**
     * The tag repository implementation.
     *
     * @var \Laravel\Horizon\Contracts\TagRepository
     */
    public $tags;

    /**
     * Create a new listener instance.
     *
     * @param  \Laravel\Horizon\Contracts\JobRepository  $jobs
     * @param  \Laravel\Horizon\Contracts\TagRepository  $tags
     * @return void
     */
    public function __construct(JobRepository $jobs, TagRepository $tags)
    {
        $this->jobs = $jobs;
        $this->tags = $tags;
    }

    /**
     * Handle the event.
     *
     * @param  \Laravel\Horizon\Events\JobDeleted  $event
     * @return void
     */
    public function handle(JobDeleted $event)
    {
        # If the job want to skip mark as completed, then just remove from pending_jobs
        if (!$event->job->hasFailed() && method_exists($event->job, 'shouldSkipMarkAsCompleted') && $event->job->shouldSkipMarkAsCompleted()) {
            $this->jobs->removeJobFromPending($event->payload);
            return;
        }

        $this->jobs->completed($event->payload, $event->job->hasFailed());

        if (! $event->job->hasFailed() && count($this->tags->monitored($event->payload->tags())) > 0) {
            $this->jobs->remember($event->connectionName, $event->queue, $event->payload);
        }
    }
}
