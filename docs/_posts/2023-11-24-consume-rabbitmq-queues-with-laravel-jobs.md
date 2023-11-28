---
title: Consume RabbitMQ queues with Laravel jobs
layout: post
---

![RabbitMQ + Laravel]({{ 'assets/images/posts/consume-rabbitmq-queues-with-laravel-jobs/header.png' | relative_url }})

[RabbitMQ](https://www.rabbitmq.com), a powerful message broker, is widely used for building scalable and distributed applications. 
In this guide, we’ll explore the steps to consume [RabbitMQ queues](https://www.rabbitmq.com/queues.html) effectively within [Laravel jobs](https://laravel.com/docs/10.x/queues).

### Setting Up RabbitMQ in Laravel
#### Installation of Required Packages
Before diving into consuming RabbitMQ queues, ensure you have the necessary packages installed in your Laravel application. The [php-amqplib/php-amqplib](https://github.com/php-amqplib/php-amqplib) package allows Laravel to communicate with RabbitMQ. Install it using Composer:

```bash
composer require php-amqplib/php-amqplib
```

#### Configuration
Next, let's create a new configuration file called `rabbitmq.php` in the config folder to hold all the configs for the connection with the broker:

```php
<?php

return [
    'host' => env('RABBITMQ_HOST'),
    'vhost' => env('RABBITMQ_VHOST'),
    'port' => env('RABBITMQ_PORT'),
    'user' => env('RABBITMQ_USER'),
    'password' => env('RABBITMQ_PASSWORD'),
    'options' => [
        'heartbeat' => 60,
        'connection_timeout' => 10, // Set connection timeout in seconds
        'read_write_timeout' => 60 * 2, // Set read/write timeout in seconds
        'channel_rpc_timeout' => 60 * 2, // Set RPC timeout in seconds
    ],
];
```

Ensure you have the corresponding environment variables set in your .env file:
```dotenv
RABBITMQ_HOST=your_host
RABBITMQ_VHOST=your_vhost
RABBITMQ_PORT=your_port
RABBITMQ_USER=your_user
RABBITMQ_PASSWORD=your_password

```

### Creating the consumer
Generate a new job using the [Laravel Artisan CLI](https://laravel.com/docs/10.x/artisan):
```bash
php artisan make:job ProcessRabbitMQMessage
```

Open the generated `ProcessRabbitMQMessage` job file (`app/Jobs/ProcessRabbitMQMessage.php`). This job will handle the consumption of messages from the RabbitMQ queue:
```php
<?php

namespace App\Jobs;

use Carbon\Carbon;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Connection\AMQPStreamConnection;

class ProcessRabbitMQMessage extends Job
{
    public int $timeout = 60 * 60; // 1 hour
    private Carbon $startedAt;

    public function handle(): void
    {
        $connection = new AMQPStreamConnection(
            host: config("rabbitmq.host"),
            port: config("rabbitmq.port"),
            user: config("rabbitmq.user"),
            password: config("rabbitmq.password"),
            vhost: config("rabbitmq.vhost"),
            connection_timeout: config("rabbitmq.options.connection_timeout"),
            read_write_timeout: config("rabbitmq.options.read_write_timeout"),
            heartbeat: config("rabbitmq.options.heartbeat"),
            channel_rpc_timeout: config("rabbitmq.options.channel_rpc_timeout")
        );

        $channel = $connection->channel();

        $channel->basic_consume('your_queue_name', '', false, false, false, false, function (AMQPMessage $message) {
            $this->processMessage($message);
            $message->ack();
        });

        $this->startedAt = now();

        while ($channel->is_consuming()) {
            if ($this->isTimeoutReached()) {
                // These two steps are optional
                $this->cleanup();
                $this->notify();
                break;
            }

            $channel->wait();
        }

        $channel->close();
        $connection->close();
    }

    private function processMessage(AMQPMessage $message): void
    {
        // YOUR CODE HERE
    }

    private function cleanup(): void
    {
        // YOUR CODE HERE
    }

    private function notify(): void
    {
        // YOUR CODE HERE
    }

    private function isTimeoutReached(): bool
    {
        $elapsedTime = $this->startedAt->diffInSeconds(now());

        // Adds 1 minute from the elapse time, so you have time to perform cleanup and notify if necessary.
        // This value is arbitrary and can be changed according to your needs.
        $elapsedTime += 60;

        return $elapsedTime >= $this->timeout;
    }
}
```

Since we want to continuously consume the queue, let's update the scheduler to dispatch the job hourly. 
Add the following line to the method `schedule` on the file  `App\Console\Kernel`:
```php
$schedule->job(new ProcessRabbitMQMessage)->hourly();
```

### Creating a basic producer
Generate a new job using the Laravel Artisan CLI:
```bash
php artisan make:job RabbitMQMessageProducer
```

Open the generated `RabbitMQMessageProducer` job file (`app/Jobs/RabbitMQMessageProducer.php`). This job will handle the logic to send messages to RabbitMQ queue:
```php
<?php

namespace App\Jobs;

use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Connection\AMQPStreamConnection;

class RabbitMQMessageProducer extends Job
{
    public function handle(): void
    {
        // Setup connection
        $connection = new AMQPStreamConnection(
            host: config("rabbitmq.host"),
            port: config("rabbitmq.port"),
            user: config("rabbitmq.user"),
            password: config("rabbitmq.password"),
            vhost: config("rabbitmq.vhost"),
            connection_timeout: config("rabbitmq.options.connection_timeout"),
            read_write_timeout: config("rabbitmq.options.read_write_timeout"),
            heartbeat: config("rabbitmq.options.heartbeat"),
            channel_rpc_timeout: config("rabbitmq.options.channel_rpc_timeout")
        );

        $channel = $connection->channel();

        // Declare a queue
        $channel->queue_declare('your_queue_name', false, false, false, false);

        $messageBody = 'YOUR MESSAGE';
        $message = new AMQPMessage($messageBody);

        // Publish the message to the queue
        $channel->basic_publish($message, '', 'your_queue_name');

        // Close the channel and connection
        $channel->close();
        $connection->close();
    }
}
```
### Running the jobs
Now that we are both consumer and producer, we can run the jobs to see if everything is working as expected. Let's first dispatch the job `RabbitMQMessageProducer` a couple of times so we have some messages to process; we are going to use [Laravel Tinker](https://laravel.com/docs/10.x/artisan#tinker) to dispatch the job:
```php
php artisan tinker
RabbitMQMessageProducer::dispatchSync();
RabbitMQMessageProducer::dispatchSync();
RabbitMQMessageProducer::dispatchSync();
RabbitMQMessageProducer::dispatchSync();
```

Now that we dispatched the job four times, let's run the consumer and check if the messages were successfully processed:
```php
php artisan tinker
ProcessRabbitMQMessage::dispatchSync()
```

If everything goes well, you should see the success exit code on your console.

### Retries and Error Handling
Implement retry mechanisms and error handling within your job logic. If a job fails due to a timeout, have mechanisms to retry or log the failure for further investigation.

Handling timeouts while consuming RabbitMQ queues involves a combination of RabbitMQ connection configurations, graceful job handling, and monitoring strategies. It's essential to balance these approaches to ensure efficient queue consumption without causing interruptions or resource constraints in your application.

### Conclusion
In summary, leveraging RabbitMQ queues in Laravel for distributed applications involves:

* Setting up RabbitMQ in Laravel by installing necessary packages and configuring connections.
* Creating dedicated jobs for message consumption and transmission.
* Utilizing Laravel's scheduler to orchestrate queue consumption.
* Implementing retry mechanisms and error handling within job logic for robust message processing.
* Balancing connection configurations and monitoring strategies for efficient queue consumption without straining application resources.
* This approach enables seamless communication, efficient message handling, and enhanced system reliability in handling timeouts and failures while consuming RabbitMQ queues in Laravel.

That's all for today 🎉🎉🎉
