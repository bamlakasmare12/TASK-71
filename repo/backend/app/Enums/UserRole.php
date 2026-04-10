<?php

namespace App\Enums;

enum UserRole: string
{
    case Learner = 'learner';
    case Editor = 'editor';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Learner => 'Learner',
            self::Editor => 'Content Editor',
            self::Admin => 'Administrator',
        };
    }
}
