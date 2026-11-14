<?php

namespace App\Enums;

enum OrderStatus: string
{
    case New = 'new';
    case Processing = 'processing';
    case Shipped = 'shipped';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Nová',
            self::Processing => 'Zpracovává se',
            self::Shipped => 'Odeslaná',
            self::Completed => 'Dokončená',
            self::Cancelled => 'Zrušená',
        };
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::New => [self::Processing, self::Cancelled],
            self::Processing => [self::Shipped, self::Cancelled],
            self::Shipped => [self::Completed],
            self::Completed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }
}
