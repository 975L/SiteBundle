<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace c975L\SiteBundle\Service;

use Doctrine\DBAL\Connection;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Mime\Email;

// Reads/purges the Doctrine Messenger failure transport ("messenger_messages" table,
// queue_name = 'failed'), used by MessengerCleanupCommand, MessengerAlertProvider
// and MessengerFailedController so the logic to decode a failed Envelope lives in one place
class MessengerFailedMessageService
{
    // Exception message keywords indicating the failure is caused by the recipient's own
    // reputation (blacklisted spammer domain, ...) rather than an issue worth an admin's attention
    private const MINOR_KEYWORDS = ['blacklist', 'blocklist', 'block-listed', 'rbl', 'spam', 'reputation'];

    public function __construct(
        private readonly Connection $connection,
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

    // Removes failed messages older than the given number of days, returns the number removed
    public function purgeOlderThan(int $days): int
    {
        $cutoff = (new \DateTimeImmutable())->modify("-{$days} days")->format('Y-m-d H:i:s');

        return $this->connection->executeStatement(
            "DELETE FROM messenger_messages WHERE queue_name = 'failed' AND created_at < ?",
            [$cutoff]
        );
    }

    // Decodes a raw messenger_messages row into a readable array
    private function decode(array $row): array
    {
        $envelope = @unserialize($row['body']);
        $exceptionMessage = null;
        $to = null;
        $subject = null;

        if ($envelope instanceof Envelope) {
            $errorStamp = $envelope->last(ErrorDetailsStamp::class);
            $exceptionMessage = $errorStamp?->getExceptionMessage();

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
