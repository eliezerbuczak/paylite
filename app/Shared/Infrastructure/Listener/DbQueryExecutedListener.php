<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Listener;

use DateTimeInterface;
use Hyperf\Collection\Arr;
use Hyperf\Database\Events\QueryExecuted;
use Hyperf\Event\Annotation\Listener;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Logger\LoggerFactory;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

#[Listener]
class DbQueryExecutedListener implements ListenerInterface
{
    private LoggerInterface $logger;

    public function __construct(ContainerInterface $container)
    {
        $this->logger = $container->get(LoggerFactory::class)->get('sql');
    }

    public function listen(): array
    {
        return [
            QueryExecuted::class,
        ];
    }

    public function process(object $event): void
    {
        if (!$event instanceof QueryExecuted) {
            return;
        }

        $sql = $event->sql;
        if (!Arr::isAssoc($event->bindings)) {
            $position = 0;
            foreach ($event->bindings as $value) {
                $position = strpos($sql, '?', $position);
                if ($position === false) {
                    break;
                }
                $value = "'" . $this->stringify($value) . "'";
                $sql = substr_replace($sql, $value, $position, 1);
                $position += strlen($value);
            }
        }

        $this->logger->info(sprintf('[%s] %s', $event->time, $sql));
    }

    /**
     * Bindings are logged before the grammar formats them for PDO, and a
     * plain DateTimeImmutable — unlike Carbon — has no __toString(), which
     * crashes the interpolation above.
     */
    private function stringify(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s.u');
        }

        return (string) $value;
    }
}
