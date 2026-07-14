<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace c975L\SiteBundle\Service;

use c975L\SiteBundle\Messenger\SingleEnvelopeReceiver;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMessageLimitListener;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Worker;
use Symfony\Component\Mime\Email;

// Reads/purges/retries the Doctrine Messenger failure transport ("messenger_messages" table,
// queue_name = 'failed'), used by MessengerCleanupCommand, MessengerAlertProvider
// and MessengerFailedController so the logic to decode a failed Envelope lives in one place
class MessengerFailedMessageService
{
    // Exception message keywords indicating the failure is caused by the recipient's own
    // reputation (blacklisted spammer domain, ...) rather than an issue worth an admin's attention
    private const MINOR_KEYWORDS = ['blacklist', 'blocklist', 'block-listed', 'rbl', 'spam', 'reputation'];

    public function __construct(
        private readonly Connection $connection,
        // Matches the "failed" transport name assumed by the raw SQL below, per the Symfony
        // Messenger recipe default (framework.messenger.failure_transport: failed)
        #[Autowire(service: 'messenger.transport.failed')]
        private readonly ReceiverInterface&ListableReceiverInterface $failureReceiver,
        private readonly MessageBusInterface $messageBus,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    // Returns every failed message, most recent first, decoded into a readable array
    public function findAll(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT id, body, created_at FROM messenger_messages WHERE queue_name = 'failed' ORDER BY created_at DESC"
        );

        return array_map(fn (array $row) => $this->decode($row), $rows);
    }

    // Counts failed messages not classified as minor (spam-related)
    public function countImportant(): int
    {
        return count(array_filter($this->findAll(), fn (array $message) => $message['important']));
    }

    // Groups already-decoded messages (see findAll()) by their error message, most frequent first,
    // so the dashboard can offer a "delete all N messages with this error" action
    public function groupByError(array $messages): array
    {
        $groups = [];
        foreach ($messages as $message) {
            $key = $message['exceptionMessage'] ?? '(unknown)';
            $groups[$key]['message'] ??= $key;
            $groups[$key]['count'] = ($groups[$key]['count'] ?? 0) + 1;
            $groups[$key]['ids'][] = $message['id'];
        }

        usort($groups, fn (array $a, array $b) => $b['count'] <=> $a['count']);

        return $groups;
    }

    // Removes failed messages older than the given number of days, returns the number removed
    public function purgeOlderThan(int $days): int
    {
        $cutoff = (new \DateTimeImmutable())->modify("-{$days} days")->format('Y-m-d H:i:s');

        return $this->connection->executeStatement(
            "DELETE FROM messenger_messages WHERE queue_name = 'failed' AND created_at < ?",
            [$cutoff]
        );
    }

    // Deletes a single failed message, returns whether a row was actually removed
    public function deleteById(int $id): bool
    {
        return $this->connection->executeStatement(
            "DELETE FROM messenger_messages WHERE id = ? AND queue_name = 'failed'",
            [$id]
        ) > 0;
    }

    // Deletes several failed messages at once (used by the "delete this error group" action)
    public function deleteByIds(array $ids): int
    {
        if ([] === $ids) {
            return 0;
        }

        return $this->connection->executeStatement(
            'DELETE FROM messenger_messages WHERE queue_name = \'failed\' AND id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')',
            array_map('intval', $ids)
        );
    }

    // Replays a single failed message through the real message bus (same handler, same routing
    // as a fresh dispatch), exactly like "messenger:failed:retry" does for one id. On success the
    // message leaves the failure transport; on failure it is re-queued there by Symfony's own
    // retry-strategy listener, and the new error is returned so the caller can display it
    public function retry(int $id): array
    {
        $envelope = $this->failureReceiver->find($id);
        if (null === $envelope) {
            return ['found' => false, 'success' => false, 'error' => null];
        }

        $succeeded = false;
        $newError = null;

        $successListener = function (WorkerMessageHandledEvent $event) use (&$succeeded) {
            $succeeded = true;
        };
        $failedListener = function (WorkerMessageFailedEvent $event) use (&$newError) {
            $throwable = $event->getThrowable();
            if ($throwable instanceof HandlerFailedException) {
                $throwable = $throwable->getPrevious() ?? $throwable;
            }
            $newError = $throwable->getMessage();
        };
        $stopListener = new StopWorkerOnMessageLimitListener(1);

        $this->eventDispatcher->addListener(WorkerMessageHandledEvent::class, $successListener);
        $this->eventDispatcher->addListener(WorkerMessageFailedEvent::class, $failedListener);
        $this->eventDispatcher->addSubscriber($stopListener);

        try {
            $singleReceiver = new SingleEnvelopeReceiver($this->failureReceiver, $envelope);
            $worker = new Worker(['failed' => $singleReceiver], $this->messageBus, $this->eventDispatcher);
            $worker->run(['sleep' => 0]);
        } finally {
            $this->eventDispatcher->removeListener(WorkerMessageHandledEvent::class, $successListener);
            $this->eventDispatcher->removeListener(WorkerMessageFailedEvent::class, $failedListener);
            $this->eventDispatcher->removeSubscriber($stopListener);
        }

        return ['found' => true, 'success' => $succeeded, 'error' => $newError];
    }

    // Decodes a raw messenger_messages row into a readable array
    private function decode(array $row): array
    {
        $envelope = @unserialize($row['body']);
        $exceptionMessage = null;
        $exceptionClass = null;
        $exceptionCode = null;
        $retryCount = 0;
        $originalTransport = null;
        $to = null;
        $subject = null;

        if ($envelope instanceof Envelope) {
            $errorStamp = $envelope->last(ErrorDetailsStamp::class);
            $exceptionMessage = $errorStamp?->getExceptionMessage();
            $exceptionClass = $errorStamp?->getExceptionClass();
            $exceptionCode = $errorStamp?->getExceptionCode();

            $retryCount = RedeliveryStamp::getRetryCountFromEnvelope($envelope);
            $originalTransport = $envelope->last(SentToFailureTransportStamp::class)?->getOriginalReceiverName();

            $message = $envelope->getMessage();
            if ($message instanceof SendEmailMessage) {
                $rawMessage = $message->getMessage();
                if ($rawMessage instanceof Email) {
                    $addresses = $rawMessage->getTo();
                    $to = isset($addresses[0]) ? $addresses[0]->getAddress() : null;
                    $subject = $rawMessage->getSubject();
                }
            }
        }

        return [
            'id' => (int) $row['id'],
            'createdAt' => new \DateTimeImmutable($row['created_at']),
            'to' => $to,
            'subject' => $subject,
            'exceptionMessage' => $exceptionMessage,
            'exceptionClass' => $exceptionClass,
            'exceptionCode' => $exceptionCode,
            'retryCount' => $retryCount,
            'originalTransport' => $originalTransport,
            // Messages we cannot identify as email (to === null) are kept as important by precaution
            'important' => null === $to || null === $exceptionMessage || !$this->isMinor($exceptionMessage),
        ];
    }

    // Checks whether the exception message indicates a minor, spam-related failure
    private function isMinor(string $exceptionMessage): bool
    {
        $lower = strtolower($exceptionMessage);
        foreach (self::MINOR_KEYWORDS as $keyword) {
            if (str_contains($lower, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
