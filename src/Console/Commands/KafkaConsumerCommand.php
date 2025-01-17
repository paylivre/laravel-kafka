<?php

namespace Junges\Kafka\Console\Commands;

use Illuminate\Console\Command;
use Junges\Kafka\Config\Config;
use Junges\Kafka\Console\Commands\KafkaConsumer\Options;
use Junges\Kafka\Consumers\Consumer;
use Junges\Kafka\Contracts\MessageDeserializer;

class KafkaConsumerCommand extends Command
{
    protected $signature = 'kafka:consume 
            {--topics= : The topics to listen for messages (topic1,topic2,...,topicN)} 
            {--handler= : The consumer which will consume messages in the specified topic} 
            {--groupId=anonymous : The consumer group id} 
            {--commit=1} 
            {--dlq=? : The Dead Letter Queue} 
            {--maxMessage=? : The max number of messages that should be handled}
            {--securityProtocol=?}';

    protected $description = 'A Kafka Consumer for Laravel.';

    private array $config;

    public function __construct()
    {
        parent::__construct();

        $this->config = [
            'brokers' => config('kafka.consumers.default.brokers'),
            'groupId' => config('kafka.consumers.default.group_id'),
            'securityProtocol' => config('kafka.consumers.security_protocol'),
            'sasl' => [
                'mechanisms' => config('kafka.consumers.default.sasl.mechanisms'),
                'username' => config('kafka.consumers.default.sasl.username'),
                'password' => config('kafka.consumers.default.sasl.password'),
            ],
        ];
    }

    public function handle()
    {
        if (empty($this->option('handler'))) {
            $this->error('The [--handler] option is required.');

            return;
        }

        if (empty($this->option('topics'))) {
            $this->error('The [--topics option is required.');

            return;
        }

        $options = new Options($this->options(), $this->config);

        $handler = $options->getHandler();

        $config = new Config(
            broker: $options->getBroker(),
            topics: $options->getTopics(),
            securityProtocol: $options->getSecurityProtocol(),
            commit: $options->getCommit(),
            groupId: $options->getGroupId(),
            handler: new $handler(),
            sasl: $options->getSasl(),
            dlq: $options->getDlq(),
            maxMessages: $options->getMaxMessages()
        );

        /** @var Consumer $handler */
        $handler = app(Consumer::class, [
            'config' => $config,
            'deserializer' => app(MessageDeserializer::class),
        ]);

        $handler->consume();
    }
}
