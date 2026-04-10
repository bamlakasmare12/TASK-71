<?php

namespace App\Enums;

enum ReservationStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case CheckedIn = 'checked_in';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';
    case PartialAttendance = 'partial_attendance';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending Confirmation',
            self::Confirmed => 'Confirmed',
            self::CheckedIn => 'Checked In',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
            self::NoShow => 'No Show',
            self::PartialAttendance => 'Partial Attendance',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Confirmed => 'blue',
            self::CheckedIn => 'green',
            self::Completed => 'gray',
            self::Cancelled => 'red',
            self::NoShow => 'red',
            self::PartialAttendance => 'orange',
        };
    }
}
