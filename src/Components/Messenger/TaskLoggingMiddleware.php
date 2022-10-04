<?php declare(strict_types=1);

namespace Frosh\Tools\Components\Messenger;

use Psr\Log\LoggerInterface;
use Shopware\Core\Content\ImportExport\Message\DeleteFileMessage as DeleteImportExportFile;
use Shopware\Core\Content\Media\Message\DeleteFileMessage;
use Shopware\Core\Content\Media\Message\GenerateThumbnailsMessage;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\MessageQueue\IterateEntityIndexerMessage;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Shopware\Storefront\Framework\Cache\CacheWarmer\WarmUpMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

/**
 * @see https://tideways.com/profiler/blog/log-all-tasks-the-shopware-6-queue-processes
 */
class TaskLoggingMiddleware implements MiddlewareInterface
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $taskLogging = (bool) EnvironmentHelper::getVariable('FROSH_TOOLS_TASK_LOGGING', '0');
        if ($taskLogging === false) {
            return $stack->next()->handle($envelope, $stack);
        }

        $message = $envelope->getMessage();
        $taskName = $this->getTaskName($message);
        $args = $this->extractArgumentsFromMessage($message);

        $start = microtime(true);
        try {
            return $stack->next()->handle($envelope, $stack);
        } catch (HandlerFailedException $e) {
            $args = $this->addExceptionToArgs($e, $args);

            throw $e;
        } finally {
            $this->logTaskProcessing($taskName, $args, $start);
        }
    }

    private function getTaskName(object $message): string
    {
        if ($message instanceof ScheduledTask) {
            return $message->getTaskName();
        }

        $classParts = explode('\\', get_class($message));
        $taskName = end($classParts);

        if (str_ends_with($taskName, 'Message')) {
            $taskName = mb_substr($taskName, 0, -7);
        }

        return $taskName;
    }

    private function extractArgumentsFromMessage(object $message): array
    {
        if ($message instanceof EntityIndexingMessage) {
            $data = $message->getData();

            if (is_array($data)) {
                return [
                    'indexer' => $message->getIndexer(),
                    'data' => implode(',', $data),
                ];
            }
        }

        if ($message instanceof ScheduledTask) {
            return ['taskId' => $message->getTaskId()];
        }

        if ($message instanceof DeleteImportExportFile || $message instanceof DeleteFileMessage) {
            return ['files' => implode(',', array_map(static function ($f) { return basename($f); }, $message->getFiles()))];
        }

        if ($message instanceof GenerateThumbnailsMessage) {
            return ['mediaIds' => implode(',', $message->getMediaIds())];
        }

        if ($message instanceof WarmUpMessage) {
            return ['route' => $message->getRoute(), 'domain' => $message->getDomain(), 'cache_id' => $message->getCacheId()];
        }

        if ($message instanceof IterateEntityIndexerMessage) {
            return ['indexer' => $message->getIndexer()];
        }

        return [];
    }

    private function logTaskProcessing(string $taskName, array $args, $start): void
    {
        $args = ['duration' => round(microtime(true) - $start, 3)] + $args;

        if (isset($args['exception'])) {
            $this->logger->error($taskName, $args);

            return;
        }

        $taskLoggingInfo = (bool) EnvironmentHelper::getVariable('FROSH_TOOLS_TASK_LOGGING_INFO', '0');
        if ($taskLoggingInfo) {
            $this->logger->info($taskName, $args);
        }
    }

    private function addExceptionToArgs($e, array $args): array
    {
        $exceptions = $e->getNestedExceptions();
        $args['exception'] = get_class($exceptions[0]);
        $args['exception.msg'] = $exceptions[0]->getMessage();

        return $args;
    }
}
